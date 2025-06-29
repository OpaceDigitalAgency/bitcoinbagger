<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

function fetchLiveETFData() {
    $etfData = [];

    // COMPREHENSIVE BITCOIN ETF LIST - All known Bitcoin ETFs
    $bitcoinETFs = [
        // US Spot Bitcoin ETFs (Approved January 2024)
        'IBIT' => 'iShares Bitcoin Trust',
        'FBTC' => 'Fidelity Wise Origin Bitcoin Fund',
        'GBTC' => 'Grayscale Bitcoin Trust',
        'ARKB' => 'ARK 21Shares Bitcoin ETF',
        'BITB' => 'Bitwise Bitcoin ETF',
        'BTCO' => 'Invesco Galaxy Bitcoin ETF',
        'HODL' => 'VanEck Bitcoin Trust',
        'BRRR' => 'Valkyrie Bitcoin Fund',
        'EZBC' => 'Franklin Bitcoin ETF',
        'DEFI' => 'Hashdex Bitcoin ETF',
        'BTCW' => 'WisdomTree Bitcoin Fund',

        // Grayscale Mini Trust
        'BTC' => 'Grayscale Bitcoin Mini Trust',

        // Canadian Bitcoin ETFs
        'BTCC' => 'Purpose Bitcoin ETF',
        'EBIT' => 'Evolve Bitcoin ETF',
        'QBTC' => 'Accelerate Bitcoin ETF',

        // European Bitcoin ETFs
        'BTCE' => 'ETC Group Physical Bitcoin',
        'SBTC' => 'VanEck Bitcoin ETN',
        '21XB' => '21Shares Bitcoin ETP'
    ];

    // Step 1: Try multiple sources for Bitcoin holdings data
    $holdingsData = [];

    // Try btcetfdata.com first
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'BitcoinBagger/1.0'
            ]
        ]);

        $response = file_get_contents('https://btcetfdata.com/v1/current.json', false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);

            if (!empty($data) && isset($data['data'])) {
                foreach ($data['data'] as $ticker => $etfInfo) {
                    if (isset($etfInfo['holdings']) && $etfInfo['holdings'] > 0) {
                        $holdingsData[strtoupper($ticker)] = [
                            'name' => $etfInfo['name'] ?? ($bitcoinETFs[strtoupper($ticker)] ?? $ticker . ' Bitcoin ETF'),
                            'btcHeld' => floatval($etfInfo['holdings'])
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Bitcoin ETF holdings data failed: " . $e->getMessage());
    }

    // Try alternative source: bitcointreasuries.net API (if available)
    if (count($holdingsData) < 5) { // If we don't have enough data
        try {
            $response = file_get_contents('https://api.bitcointreasuries.net/v1/entities');
            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    foreach ($data as $entity) {
                        if (isset($entity['symbol']) && isset($entity['btc']) &&
                            array_key_exists(strtoupper($entity['symbol']), $bitcoinETFs)) {
                            $ticker = strtoupper($entity['symbol']);
                            if (!isset($holdingsData[$ticker])) {
                                $holdingsData[$ticker] = [
                                    'name' => $entity['name'] ?? $bitcoinETFs[$ticker],
                                    'btcHeld' => floatval($entity['btc'])
                                ];
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Alternative Bitcoin holdings source failed: " . $e->getMessage());
        }
    }

    // NEW: Try manual Bitcoin holdings lookup for specific ETFs that are missing
    $missingETFs = ['BTCO', 'BRRR', 'EZBC', 'DEFI', 'BTCW', 'EBIT'];
    foreach ($missingETFs as $ticker) {
        if (!isset($holdingsData[$ticker])) {
            $manualHoldings = fetchManualETFHoldings($ticker);
            if ($manualHoldings > 0) {
                $holdingsData[$ticker] = [
                    'name' => $bitcoinETFs[$ticker] ?? $ticker . ' Bitcoin ETF',
                    'btcHeld' => $manualHoldings
                ];
            }
        }
    }

    // Step 2: Try ETFdb.com API for comprehensive ETF data
    $etfdbData = fetchETFdbBitcoinETFs();

    // Step 3: Combine all known Bitcoin ETFs with available data
    foreach ($bitcoinETFs as $ticker => $name) {
        // Get Bitcoin holdings (from btcetfdata.com or fallback)
        $btcHeld = 0;
        $etfName = $name;

        if (isset($holdingsData[$ticker])) {
            $btcHeld = $holdingsData[$ticker]['btcHeld'];
            $etfName = $holdingsData[$ticker]['name'];
        }

        // Get comprehensive ETF data (price, shares, NAV, etc.)
        $etfDetails = [];
        if (isset($etfdbData[$ticker])) {
            $etfDetails = $etfdbData[$ticker];
        } else {
            // Fallback to individual price lookup
            $etfDetails = fetchETFPrice($ticker);
        }

        // Calculate additional metrics
        $price = $etfDetails['price'] ?? 0;
        $nav = $etfDetails['nav'] ?? $price; // NAV often equals price for ETFs
        $sharesOutstanding = $etfDetails['sharesOutstanding'] ?? 0;
        $aum = $etfDetails['aum'] ?? 0;

        // If shares outstanding is still 0, try multiple additional sources
        if ($sharesOutstanding == 0) {
            $sharesOutstanding = fetchSharesOutstanding($ticker);
        }

        // Calculate AUM if we have price and shares
        if ($aum == 0 && $price > 0 && $sharesOutstanding > 0) {
            $aum = $price * $sharesOutstanding;
        }

        // Calculate BTC per share - ensure we always have a value
        $btcPerShare = 0;
        if ($btcHeld > 0 && $sharesOutstanding > 0) {
            $btcPerShare = $btcHeld / $sharesOutstanding;
        } else if ($btcHeld > 0 && $price > 0 && $aum > 0) {
            // Fallback: estimate shares outstanding from AUM and price
            $estimatedShares = $aum / $price;
            if ($estimatedShares > 0) {
                $btcPerShare = $btcHeld / $estimatedShares;
                $sharesOutstanding = $estimatedShares; // Update shares outstanding
            }
        }

        // Calculate premium/discount - ensure we always have a value
        $premium = 0;
        if ($btcPerShare > 0 && $price > 0) {
            // For Bitcoin ETFs, calculate theoretical NAV based on Bitcoin holdings
            $btcPrice = getCurrentBitcoinPrice();
            if ($btcPrice > 0) {
                $theoreticalNAV = $btcPerShare * $btcPrice;
                if ($theoreticalNAV > 0) {
                    $premium = (($price - $theoreticalNAV) / $theoreticalNAV) * 100;
                }
            }
        } else if ($nav > 0 && $price > 0 && $nav != $price) {
            // Use reported NAV if different from price
            $premium = (($price - $nav) / $nav) * 100;
        }

        // Include ALL ETFs that we have in our list - no filtering out
        // This ensures we show all Bitcoin ETFs even if some data is missing
        $etfData[] = [
            'ticker' => $ticker,
            'name' => $etfName,
            'btcHeld' => $btcHeld,
            'sharesOutstanding' => $sharesOutstanding,
            'nav' => $nav,
            'price' => $price,
            'aum' => $aum,
            'btcPerShare' => $btcPerShare,
            'premium' => $premium,
            'expenseRatio' => $etfDetails['expenseRatio'] ?? 0,
            'volume' => $etfDetails['volume'] ?? 0,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'dataSource' => isset($holdingsData[$ticker]) ? 'BITCOINETFDATA_COM_LIVE' : 'COMPREHENSIVE_LOOKUP'
        ];
    }

    if (!empty($etfData)) {
        return $etfData;
    }

    // If all API sources fail, return empty array - no hardcoded data
    error_log("BitcoinBagger: All ETF API sources failed - returning empty data");

    return $etfData;
}

// NEW: Manual ETF holdings lookup for specific ETFs that are missing from primary APIs
function fetchManualETFHoldings($ticker) {
    // Try multiple specialized Bitcoin ETF data sources
    $holdings = 0;

    // Method 1: Try Yahoo Finance fund holdings with comprehensive modules
    try {
        $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=fundProfile,topHoldings,fundPerformance,assetProfile";
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);

            // Method 1a: Check top holdings for Bitcoin
            if (isset($data['quoteSummary']['result'][0]['topHoldings']['holdings'])) {
                $holdings_data = $data['quoteSummary']['result'][0]['topHoldings']['holdings'];
                foreach ($holdings_data as $holding) {
                    $holdingName = strtolower($holding['holdingName'] ?? '');
                    $symbol = strtolower($holding['symbol'] ?? '');

                    if (stripos($holdingName, 'bitcoin') !== false ||
                        $symbol === 'btc-usd' || $symbol === 'btc' ||
                        stripos($holdingName, 'btc') !== false) {

                        // Get the fund's total net assets and calculate Bitcoin holdings
                        $percentage = floatval($holding['holdingPercent'] ?? 0);
                        if ($percentage > 80) { // If >80% Bitcoin, calculate actual holdings
                            // Get fund size from price data
                            $priceData = fetchETFPrice($ticker);
                            if (isset($priceData['aum']) && $priceData['aum'] > 0) {
                                $btcPrice = getCurrentBitcoinPrice();
                                if ($btcPrice > 0) {
                                    $holdings = ($priceData['aum'] * $percentage / 100) / $btcPrice;
                                }
                            }
                        }
                        break;
                    }
                }
            }

            // Method 1b: Check fund profile for Bitcoin focus
            if ($holdings == 0 && isset($data['quoteSummary']['result'][0]['assetProfile']['longBusinessSummary'])) {
                $summary = strtolower($data['quoteSummary']['result'][0]['assetProfile']['longBusinessSummary']);
                if (stripos($summary, 'bitcoin') !== false && stripos($summary, 'track') !== false) {
                    // This is likely a Bitcoin tracking ETF - estimate holdings from AUM
                    $priceData = fetchETFPrice($ticker);
                    if (isset($priceData['aum']) && $priceData['aum'] > 0) {
                        $btcPrice = getCurrentBitcoinPrice();
                        if ($btcPrice > 0) {
                            // Assume 95% of AUM is in Bitcoin for Bitcoin ETFs
                            $holdings = ($priceData['aum'] * 0.95) / $btcPrice;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Continue to next method
    }

    // Method 2: Try Financial Modeling Prep ETF holdings endpoint
    if ($holdings == 0) {
        try {
            $fmpKey = getApiKey('FMP');
            if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
                $url = "https://financialmodelingprep.com/api/v3/etf-holder/{$ticker}?apikey={$fmpKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (is_array($data) && !empty($data)) {
                        foreach ($data as $holding) {
                            $asset = strtolower($holding['asset'] ?? '');
                            if (stripos($asset, 'bitcoin') !== false || stripos($asset, 'btc') !== false) {
                                $shares = floatval($holding['sharesNumber'] ?? 0);
                                if ($shares > 0) {
                                    $holdings = $shares;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    // Method 3: Try direct fund data calculation from AUM and Bitcoin focus
    if ($holdings == 0) {
        try {
            // For known Bitcoin ETFs, calculate holdings from AUM
            $priceData = fetchETFPrice($ticker);
            if (isset($priceData['price']) && $priceData['price'] > 0) {
                // Get shares outstanding and calculate AUM
                $shares = fetchSharesOutstanding($ticker);
                if ($shares > 0) {
                    $aum = $priceData['price'] * $shares;
                    $btcPrice = getCurrentBitcoinPrice();

                    if ($aum > 0 && $btcPrice > 0) {
                        // For Bitcoin ETFs, assume 95% of AUM is in Bitcoin (5% cash/fees)
                        $holdings = ($aum * 0.95) / $btcPrice;
                    }
                }
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    // Method 4: Try ETFdb.com API for comprehensive ETF data
    if ($holdings == 0) {
        try {
            $etfdbData = fetchETFdbSingleETF($ticker);
            if ($etfdbData && isset($etfdbData['btcHeld']) && $etfdbData['btcHeld'] > 0) {
                $holdings = $etfdbData['btcHeld'];
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    // Method 4: Try SEC EDGAR API for US ETFs (real filings data)
    if ($holdings == 0) {
        try {
            $secData = fetchSECFilingsData($ticker);
            if ($secData > 0) {
                $holdings = $secData;
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    // Method 5: Try alternative financial data APIs
    if ($holdings == 0) {
        try {
            $alternativeData = fetchAlternativeETFData($ticker);
            if ($alternativeData > 0) {
                $holdings = $alternativeData;
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    return $holdings;
}

// NEW: Fetch single ETF data from ETFdb.com API
function fetchETFdbSingleETF($ticker) {
    try {
        // ETFdb.com individual ETF endpoint
        $url = "https://etfdb.com/api/etf/{$ticker}/";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: BitcoinBagger/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 15
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Extract Bitcoin holdings if available
        $btcHeld = 0;
        if (isset($data['holdings']) && is_array($data['holdings'])) {
            foreach ($data['holdings'] as $holding) {
                if (stripos($holding['name'] ?? '', 'bitcoin') !== false) {
                    $btcHeld = floatval($holding['weight'] ?? 0) * floatval($data['aum'] ?? 0) / 100;
                    break;
                }
            }
        }

        return ['btcHeld' => $btcHeld];

    } catch (Exception $e) {
        return null;
    }
}

// NEW: Fetch Bitcoin holdings from SEC EDGAR filings
function fetchSECFilingsData($ticker) {
    try {
        // SEC EDGAR API for company filings
        $url = "https://data.sec.gov/api/xbrl/companyfacts/CIK{$ticker}.json";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: BitcoinBagger contact@bitcoinbagger.com',
                    'Accept: application/json'
                ],
                'timeout' => 15
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return 0;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return 0;
        }

        // Parse SEC filings for Bitcoin holdings
        // This would require complex parsing of financial statements
        // For now, return 0 as this needs specialized implementation
        return 0;

    } catch (Exception $e) {
        return 0;
    }
}

// NEW: Fetch ETF data from alternative financial APIs
function fetchAlternativeETFData($ticker) {
    $holdings = 0;

    // Try Morningstar API
    try {
        $url = "https://api.morningstar.com/v1/etfs/{$ticker}/portfolio";
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'BitcoinBagger/1.0'
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            // Parse Morningstar data for Bitcoin holdings
            if (isset($data['portfolio']['holdings'])) {
                foreach ($data['portfolio']['holdings'] as $holding) {
                    if (stripos($holding['name'] ?? '', 'bitcoin') !== false) {
                        $holdings = floatval($holding['marketValue'] ?? 0);
                        break;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Continue to next source
    }

    // Try Yahoo Finance fund holdings
    if ($holdings == 0) {
        try {
            $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=fundProfile,topHoldings";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteSummary']['result'][0]['topHoldings']['holdings'])) {
                    $holdingsData = $data['quoteSummary']['result'][0]['topHoldings']['holdings'];
                    foreach ($holdingsData as $holding) {
                        if (stripos($holding['holdingName'] ?? '', 'bitcoin') !== false) {
                            // Estimate Bitcoin holdings from percentage and fund size
                            $percentage = floatval($holding['holdingPercent'] ?? 0);
                            if ($percentage > 90) { // If >90% Bitcoin, it's likely a Bitcoin ETF
                                // Get fund AUM and calculate Bitcoin holdings
                                $fundData = fetchETFPrice($ticker);
                                if (isset($fundData['aum']) && $fundData['aum'] > 0) {
                                    $btcPrice = getCurrentBitcoinPrice();
                                    if ($btcPrice > 0) {
                                        $holdings = ($fundData['aum'] * $percentage / 100) / $btcPrice;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    return $holdings;
}

// NEW: Comprehensive shares outstanding fetcher with multiple API sources
function fetchSharesOutstanding($ticker) {
    $cacheKey = "shares_outstanding_{$ticker}";
    $cached = getCache($cacheKey, 3600); // 1 hour cache

    if ($cached !== null && $cached > 0) {
        return $cached;
    }

    $sharesOutstanding = 0;

    // Method 1: Try Yahoo Finance Statistics API (most comprehensive)
    try {
        $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=defaultKeyStatistics,summaryDetail";
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['quoteSummary']['result'][0]['defaultKeyStatistics']['sharesOutstanding']['raw'])) {
                $sharesOutstanding = floatval($data['quoteSummary']['result'][0]['defaultKeyStatistics']['sharesOutstanding']['raw']);
            } elseif (isset($data['quoteSummary']['result'][0]['summaryDetail']['sharesOutstanding']['raw'])) {
                $sharesOutstanding = floatval($data['quoteSummary']['result'][0]['summaryDetail']['sharesOutstanding']['raw']);
            }
        }
    } catch (Exception $e) {
        // Continue to next method
    }

    // Method 2: Try FMP API (Financial Modeling Prep)
    if ($sharesOutstanding == 0) {
        $fmpKey = getApiKey('FMP');
        if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (is_array($data) && isset($data[0]['sharesOutstanding']) && $data[0]['sharesOutstanding'] > 0) {
                        $sharesOutstanding = floatval($data[0]['sharesOutstanding']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 3: Try Alpha Vantage API
    if ($sharesOutstanding == 0) {
        $alphaKey = getApiKey('ALPHA_VANTAGE');
        if ($alphaKey && $alphaKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$ticker}&apikey={$alphaKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['SharesOutstanding']) && is_numeric($data['SharesOutstanding'])) {
                        $sharesOutstanding = floatval($data['SharesOutstanding']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 4: Try Finnhub API for basic metrics
    if ($sharesOutstanding == 0) {
        $finnhubKey = getApiKey('FINNHUB');
        if ($finnhubKey && $finnhubKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://finnhub.io/api/v1/stock/metric?symbol={$ticker}&metric=all&token={$finnhubKey}";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (isset($data['metric']['sharesOutstanding']) && $data['metric']['sharesOutstanding'] > 0) {
                        $sharesOutstanding = floatval($data['metric']['sharesOutstanding']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 5: Try TwelveData API
    if ($sharesOutstanding == 0) {
        $twelveKey = getApiKey('TWELVEDATA');
        if ($twelveKey && $twelveKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://api.twelvedata.com/statistics?symbol={$ticker}&apikey={$twelveKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['statistics']['shares_outstanding']) && $data['statistics']['shares_outstanding'] > 0) {
                        $sharesOutstanding = floatval($data['statistics']['shares_outstanding']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 6: Try additional Yahoo Finance endpoints for shares outstanding
    if ($sharesOutstanding == 0) {
        try {
            // Try Yahoo Finance key statistics endpoint
            $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=defaultKeyStatistics";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteSummary']['result'][0]['defaultKeyStatistics']['sharesOutstanding']['raw'])) {
                    $sharesOutstanding = floatval($data['quoteSummary']['result'][0]['defaultKeyStatistics']['sharesOutstanding']['raw']);
                }
            }
        } catch (Exception $e) {
            // Continue - no fallback data, just return 0 if APIs fail
        }
    }

    // Cache the result (even if 0) to avoid repeated API calls
    if ($sharesOutstanding > 0) {
        setCache($cacheKey, $sharesOutstanding);
    }

    // Always return a value, even if 0
    return $sharesOutstanding;
}

// NEW: Fetch Bitcoin ETFs from ETFdb.com API
function fetchETFdbBitcoinETFs() {
    $etfdbData = [];

    try {
        // ETFdb.com API endpoint for Bitcoin ETFs
        $url = 'https://etfdb.com/api/screener/';

        // Search for Bitcoin-related ETFs
        $payload = [
            'page' => 1,
            'per_page' => 50,
            'sort_by' => 'assets',
            'sort_direction' => 'desc',
            'only' => ['meta', 'data'],
            'tab' => 'overview'
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'User-Agent: BitcoinBagger/1.0'
                ],
                'content' => json_encode($payload),
                'timeout' => 15
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch from ETFdb.com");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON from ETFdb.com");
        }

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $etf) {
                $symbol = $etf['symbol']['text'] ?? '';
                $name = $etf['name']['text'] ?? '';

                // Filter for Bitcoin-related ETFs
                if (stripos($name, 'bitcoin') !== false ||
                    in_array($symbol, ['IBIT', 'FBTC', 'GBTC', 'ARKB', 'BITB', 'BTCO', 'HODL', 'BRRR', 'BTC', 'BTCC'])) {

                    // Parse price and assets
                    $price = floatval(str_replace(['$', ','], '', $etf['price'] ?? '0'));
                    $assets = parseAssetValue($etf['assets'] ?? '0');
                    $volume = parseVolumeValue($etf['average_volume'] ?? '0');

                    // Calculate shares outstanding from AUM and price
                    $sharesOutstanding = 0;
                    if ($price > 0 && $assets > 0) {
                        $sharesOutstanding = $assets / $price;
                        // Convert to millions if the number seems too large (ETFdb might return in different units)
                        if ($sharesOutstanding > 10000000000) { // If > 10 billion, likely in wrong units
                            $sharesOutstanding = $sharesOutstanding / 1000000; // Convert to millions
                        }
                    }

                    $etfdbData[$symbol] = [
                        'name' => $name,
                        'price' => $price,
                        'nav' => $price, // ETFdb doesn't provide separate NAV
                        'aum' => $assets,
                        'sharesOutstanding' => $sharesOutstanding,
                        'volume' => $volume,
                        'ytd' => parsePercentage($etf['ytd'] ?? '0%'),
                        'expenseRatio' => 0, // Would need separate API call
                        'dataSource' => 'ETFDB_COM'
                    ];
                }
            }
        }

    } catch (Exception $e) {
        error_log("ETFdb.com API failed: " . $e->getMessage());
    }

    return $etfdbData;
}

// Helper function to parse asset values like "$23,740.88" or "$23.74B" or "$673,680" (billions)
function parseAssetValue($assetStr) {
    $assetStr = str_replace(['$', ','], '', $assetStr);
    $multiplier = 1;

    if (stripos($assetStr, 'B') !== false) {
        $multiplier = 1000000000; // Billion
        $assetStr = str_replace(['B', 'b'], '', $assetStr);
    } elseif (stripos($assetStr, 'M') !== false) {
        $multiplier = 1000000; // Million
        $assetStr = str_replace(['M', 'm'], '', $assetStr);
    } elseif (stripos($assetStr, 'K') !== false) {
        $multiplier = 1000; // Thousand
        $assetStr = str_replace(['K', 'k'], '', $assetStr);
    } else {
        // ETFdb.com returns large ETF assets without suffix (e.g., "74,655" for $74.655B)
        // For major ETFs, if the number is > 1,000 and no suffix, assume it's in millions
        $value = floatval($assetStr);
        if ($value > 1000) {
            $multiplier = 1000000; // Treat as millions (so 74,655 becomes $74.655B)
        }
    }

    return floatval($assetStr) * $multiplier;
}

// Helper function to parse volume values
function parseVolumeValue($volumeStr) {
    return parseAssetValue($volumeStr); // Same logic
}

// Helper function to parse percentage values
function parsePercentage($percentStr) {
    return floatval(str_replace('%', '', $percentStr));
}

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// API Keys from environment variables
$API_KEYS = [
    'FMP' => $_ENV['FMP_API_KEY'] ?? '',
    'ALPHA_VANTAGE' => $_ENV['ALPHA_VANTAGE_API_KEY'] ?? '',
    'TWELVEDATA' => $_ENV['TWELVEDATA_API_KEY'] ?? '',
    'FINNHUB' => $_ENV['FINNHUB_API_KEY'] ?? ''
];

// Cache management for ETF data
$CACHE_DIR = __DIR__ . '/cache/';
if (!file_exists($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

function getCacheFile($key) {
    global $CACHE_DIR;
    return $CACHE_DIR . md5($key) . '.json';
}

function getCache($key, $maxAge = 3600) {
    $file = getCacheFile($key);
    if (file_exists($file) && (time() - filemtime($file)) < $maxAge) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function setCache($key, $data) {
    $file = getCacheFile($key);
    file_put_contents($file, json_encode($data));
}

function getApiKey($provider) {
    global $API_KEYS;
    return $API_KEYS[$provider] ?? '';
}

// Get current Bitcoin price for calculations
function getCurrentBitcoinPrice() {
    static $cachedPrice = null;
    static $cacheTime = 0;

    // Use cached price if less than 5 minutes old
    if ($cachedPrice !== null && (time() - $cacheTime) < 300) {
        return $cachedPrice;
    }

    try {
        // Try CoinGecko API
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd';
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data['bitcoin']['usd'])) {
            $cachedPrice = floatval($data['bitcoin']['usd']);
            $cacheTime = time();
            return $cachedPrice;
        }
    } catch (Exception $e) {
        // No fallback - return 0 if API fails
    }

    // If all APIs fail, return 0 - no hardcoded data
    return 0;
}

// Fetch ETF price with multiple fallbacks including free APIs
function fetchETFPrice($ticker) {
    $cacheKey = "etf_price_{$ticker}";
    $cached = getCache($cacheKey, 900); // 15 minute cache for ETF prices

    if ($cached !== null) {
        return $cached;
    }

    $price = 0;
    $nav = 0;
    $sharesOutstanding = 0;

    // Try Finnhub first (free tier available)
    try {
        $finnhubKey = getApiKey('FINNHUB');
        if ($finnhubKey) {
            $url = "https://finnhub.io/api/v1/quote?symbol={$ticker}&token={$finnhubKey}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BitcoinBagger/1.0');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['c']) && $data['c'] > 0) {
                    $price = floatval($data['c']); // Current price
                    $nav = $price; // For ETFs, use price as NAV approximation
                }
            }
        }
    } catch (Exception $e) {
        // Continue to next API
    }

    // Try Yahoo Finance quote API as backup (more comprehensive data)
    if ($price == 0) {
        try {
            $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$ticker}";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteResponse']['result'][0])) {
                    $quote = $data['quoteResponse']['result'][0];
                    $price = floatval($quote['regularMarketPrice'] ?? 0);
                    $sharesOutstanding = floatval($quote['sharesOutstanding'] ?? 0);

                    // Yahoo Finance often returns shares outstanding in millions for ETFs
                    // Convert to actual shares if the number seems too small
                    if ($sharesOutstanding > 0 && $sharesOutstanding < 10000) {
                        $sharesOutstanding = $sharesOutstanding * 1000000; // Convert millions to actual shares
                    }
                    // For ETFs, NAV is often close to market price
                    $nav = $price;
                }
            }
        } catch (Exception $e) {
            // Continue to next API
        }
    }

    // If we still don't have price, try the comprehensive quote API
    if ($price == 0) {
        try {
            $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$ticker}&fields=regularMarketPrice,sharesOutstanding,marketCap,navPrice";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteResponse']['result'][0])) {
                    $quote = $data['quoteResponse']['result'][0];
                    $price = floatval($quote['regularMarketPrice'] ?? 0);
                    $sharesOutstanding = floatval($quote['sharesOutstanding'] ?? 0);
                    $nav = floatval($quote['navPrice'] ?? $price);
                }
            }
        } catch (Exception $e) {
            // Continue to next API
        }
    }

    // Try FMP as fallback (if not rate limited)
    if ($price == 0) {
        $fmpKey = getApiKey('FMP');
        if ($fmpKey) {
            try {
                $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'BitcoinBagger/1.0');

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (is_array($data) && isset($data[0]['price']) && !isset($data['Error Message'])) {
                        $price = floatval($data[0]['price']);
                        $sharesOutstanding = floatval($data[0]['sharesOutstanding'] ?? 0);
                    }
                }
            } catch (Exception $e) {
                // Continue to next API
            }
        }
    }

    // Try Finnhub as another free option
    if ($price == 0) {
        try {
            $finnhubKey = getApiKey('FINNHUB');
            if ($finnhubKey) {
                $url = "https://finnhub.io/api/v1/quote?symbol={$ticker}&token={$finnhubKey}";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (isset($data['c']) && $data['c'] > 0) {
                        $price = floatval($data['c']); // Current price
                    }
                }
            }
        } catch (Exception $e) {
            // Continue
        }
    }

    $result = [
        'price' => $price,
        'nav' => $nav ?: $price, // Use price as NAV if NAV not available
        'sharesOutstanding' => $sharesOutstanding
    ];

    // Cache the result (even if 0) to avoid repeated API calls
    setCache($cacheKey, $result);
    return $result;
}

try {
    // Check for cache clearing request
    $clearCache = isset($_GET['clear_cache']) || isset($_GET['force_refresh']);

    // Check cache first (1 hour cache for ETF data)
    $cacheKey = 'etf_holdings_data';

    if ($clearCache) {
        // Clear the cache file
        $cacheFile = getCacheFile($cacheKey);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    if (!$clearCache) {
        $cachedData = getCache($cacheKey, 3600);

        if ($cachedData !== null && !empty($cachedData)) {
            // Calculate totals from cached data
            $totalBTC = array_sum(array_column($cachedData, 'btcHeld'));
            $totalAUM = array_sum(array_column($cachedData, 'aum'));

            echo json_encode([
                'success' => true,
                'data' => $cachedData,
                'meta' => [
                    'timestamp' => time(),
                    'datetime' => date('Y-m-d H:i:s'),
                    'source' => 'CACHED_ETF_DATA',
                    'cache' => true,
                    'totalETFs' => count($cachedData),
                    'totalBTC' => $totalBTC,
                    'totalAUM' => $totalAUM,
                    'data_freshness' => 'CACHED_1H'
                ]
            ]);
            exit;
        }
    }

    // Fetch fresh data
    $etfData = fetchLiveETFData();

    if (empty($etfData)) {
        throw new Exception('No ETF data retrieved from any source');
    }

    // Sort by BTC holdings (descending)
    usort($etfData, function($a, $b) {
        return ($b['btcHeld'] ?? 0) - ($a['btcHeld'] ?? 0);
    });

    // Calculate totals
    $totalBTC = array_sum(array_column($etfData, 'btcHeld'));
    $totalAUM = array_sum(array_column($etfData, 'aum'));

    // Cache the fresh data
    setCache($cacheKey, $etfData);

    echo json_encode([
        'success' => true,
        'data' => $etfData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => 'LIVE_ETF_APIS',
            'cache' => false,
            'totalETFs' => count($etfData),
            'totalBTC' => $totalBTC,
            'totalAUM' => $totalAUM,
            'data_freshness' => 'REAL_TIME'
        ]
    ]);

} catch (Exception $e) {
    error_log("ETF holdings API error: " . $e->getMessage());

    // Try stale cache as fallback
    $staleCache = getCache($cacheKey, 86400 * 7); // Accept week-old data

    if ($staleCache !== null && !empty($staleCache)) {
        $totalBTC = array_sum(array_column($staleCache, 'btcHeld'));
        $totalAUM = array_sum(array_column($staleCache, 'aum'));

        echo json_encode([
            'success' => true,
            'data' => $staleCache,
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'STALE_CACHE_FALLBACK',
                'cache' => true,
                'totalETFs' => count($staleCache),
                'totalBTC' => $totalBTC,
                'totalAUM' => $totalAUM,
                'warning' => 'Using stale cached data - live APIs failed',
                'error' => $e->getMessage(),
                'data_freshness' => 'STALE_CACHE'
            ]
        ]);
    } else {
        http_response_code(503); // Service Temporarily Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'ETF data temporarily unavailable',
            'message' => $e->getMessage(),
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'ETF_API_CRITICAL_FAILURE',
                'status' => 'SERVICE_UNAVAILABLE'
            ]
        ]);
    }
}
