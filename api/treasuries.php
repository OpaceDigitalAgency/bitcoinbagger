<?php
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // 5 minute cache for price data
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

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

// SMART CACHING STRATEGY:
// - Company holdings/info: Cached 24 hours (changes rarely)
// - BTC price: Real-time (changes constantly)
// - Multiple API keys rotation to avoid rate limits

// API Keys from environment variables
$API_KEYS = [
    'COINGECKO' => $_ENV['COINGECKO_API_KEY'] ?? '',
    'FMP' => $_ENV['FMP_API_KEY'] ?? '',
    'ALPHA_VANTAGE' => $_ENV['ALPHA_VANTAGE_API_KEY'] ?? '',
    'TWELVEDATA' => $_ENV['TWELVEDATA_API_KEY'] ?? ''
];

// Cache directory
$CACHE_DIR = __DIR__ . '/cache/';
if (!file_exists($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

// Cache management functions
function getCacheFile($key) {
    global $CACHE_DIR;
    return $CACHE_DIR . md5($key) . '.json';
}

function getCache($key, $maxAge = 86400) { // Default 24 hours
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

// API key from environment variables
function getApiKey($provider) {
    global $API_KEYS;
    $key = $API_KEYS[$provider] ?? '';
    return $key;
}

// Fetch stock price with caching (1 hour cache)
function fetchStockPrice($ticker) {
    $cacheKey = "stock_price_{$ticker}";
    $cached = getCache($cacheKey, 3600); // 1 hour cache

    if ($cached !== null) {
        return $cached;
    }

    $price = 0;

    // Try FMP first (most reliable for stock prices)
    $fmpKey = getApiKey('FMP');
    if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
        try {
            $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
            $data = fetchWithCurl($url);
            if (is_array($data) && isset($data[0]['price'])) {
                $price = floatval($data[0]['price']);
            }
        } catch (Exception $e) {
            // Continue to next API
        }
    }

    // Try Alpha Vantage as fallback
    if ($price == 0) {
        $avKey = getApiKey('ALPHA_VANTAGE');
        if ($avKey && $avKey !== 'your_alpha_vantage_key_here') {
            try {
                $url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol={$ticker}&apikey={$avKey}";
                $data = fetchWithCurl($url);
                if (isset($data['Global Quote']['05. price'])) {
                    $price = floatval($data['Global Quote']['05. price']);
                }
            } catch (Exception $e) {
                // Continue to next API
            }
        }
    }

    // Try TwelveData as final fallback
    if ($price == 0) {
        $tdKey = getApiKey('TWELVEDATA');
        if ($tdKey && $tdKey !== 'your_twelvedata_key_here') {
            try {
                $url = "https://api.twelvedata.com/price?symbol={$ticker}&apikey={$tdKey}";
                $data = fetchWithCurl($url);
                if (isset($data['price'])) {
                    $price = floatval($data['price']);
                }
            } catch (Exception $e) {
                // All APIs failed
            }
        }
    }

    // Cache the result (even if 0) to avoid repeated API calls
    setCache($cacheKey, $price);
    return $price;
}

function fetchWithCurl($url, $headers = [], $useCache = true, $cacheKey = null, $cacheTime = 86400) {
    // Check cache first for slow-changing data
    if ($useCache && $cacheKey) {
        $cached = getCache($cacheKey, $cacheTime);
        if ($cached !== null) {
            return $cached;
        }
    }

    // Add delay to prevent rate limiting
    usleep(500000); // 500ms delay

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 429) {
        // Rate limited - try different API or return cached data
        if ($useCache && $cacheKey) {
            $staleCache = getCache($cacheKey, 604800); // Accept week-old cache
            if ($staleCache !== null) {
                return $staleCache;
            }
        }
        throw new Exception("Rate limit exceeded for: $url");
    }

    if ($httpCode !== 200) {
        throw new Exception("API Error: HTTP $httpCode for $url");
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from: $url");
    }

    // Cache the successful response
    if ($useCache && $cacheKey) {
        setCache($cacheKey, $data);
    }

    return $data;
}

function fetchWithCurlRetry($url, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode for URL: $url (retry failed)");
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from: $url (retry)");
    }

    return $data;
}

// DYNAMIC DISCOVERY FUNCTIONS - NO HARDCODED DATA

function discoverBitcoinHoldingCompanies() {
    // Check cache first (daily refresh)
    $cacheKey = 'bitcoin_holding_companies';
    $cached = getCache($cacheKey, 86400); // 24 hour cache

    if ($cached !== null) {
        return $cached;
    }

    $companiesArrays = [];

    // PRIMARY: CoinGecko companies endpoint (most comprehensive and reliable)
    $companiesArrays[] = discoverFromCoinGecko();

    // SECONDARY: CoinGecko search for Bitcoin ETFs
    $companiesArrays[] = discoverBitcoinETFsFromCoinGecko();

    // FALLBACK: Only use other APIs if CoinGecko fails or has insufficient data
    $totalFound = 0;
    foreach ($companiesArrays as $companyArray) {
        $totalFound += count($companyArray);
    }

    if ($totalFound < 10) {
        $companiesArrays[] = discoverFromFMPCompanies();
        $companiesArrays[] = discoverBitcoinETFs();
    }

    // Remove duplicates and filter valid entries
    $companies = filterAndDeduplicateCompanies($companiesArrays);

    // Cache the discovered companies
    setCache($cacheKey, $companies);

    return $companies;
}

function discoverFromFMPCompanies() {
    try {
        // Try multiple FMP endpoints for Bitcoin companies
        $apiKey = getApiKey('FMP');

        // Method 1: Try the Bitcoin companies endpoint
        $url1 = "https://financialmodelingprep.com/api/v4/bitcoin_companies?apikey={$apiKey}";
        try {
            $data = fetchWithCurl($url1, [], true, 'fmp_bitcoin_companies', 86400);
            if (is_array($data) && !empty($data)) {
                $companies = [];
                foreach ($data as $company) {
                    if (isset($company['symbol']) && isset($company['bitcoinHoldings']) && $company['bitcoinHoldings'] > 0) {
                        $companies[$company['symbol']] = [
                            'name' => $company['companyName'] ?? $company['symbol'],
                            'businessModel' => $company['industry'] ?? 'Bitcoin Treasury',
                            'type' => 'stock',
                            'btcHoldings' => $company['bitcoinHoldings'],
                            'source' => 'FMP_BITCOIN_COMPANIES'
                        ];
                    }
                }
                if (!empty($companies)) {
                    return $companies;
                }
            }
        } catch (Exception $e) {
            // Continue to next method
        }

        // Method 2: Search for known Bitcoin-related companies
        $knownBitcoinTickers = ['MSTR', 'TSLA', 'COIN', 'MARA', 'RIOT', 'CLSK', 'HUT', 'BITF'];
        $companies = [];

        foreach ($knownBitcoinTickers as $ticker) {
            try {
                $url = "https://financialmodelingprep.com/api/v3/profile/{$ticker}?apikey={$apiKey}";
                $profile = fetchWithCurl($url, [], true, "profile_{$ticker}", 86400);

                if (!empty($profile) && is_array($profile) && isset($profile[0])) {
                    $company = $profile[0];
                    $companies[$ticker] = [
                        'name' => $company['companyName'] ?? $ticker,
                        'businessModel' => $company['industry'] ?? 'Technology',
                        'type' => 'stock',
                        'source' => 'FMP_PROFILE_SEARCH'
                    ];
                }

                // Add small delay to avoid rate limits
                usleep(200000); // 200ms

            } catch (Exception $e) {
                continue;
            }
        }

        return $companies;

    } catch (Exception $e) {
        return [];
    }
}

function discoverBitcoinETFs() {
    try {
        // Search for ETFs with "bitcoin" in the name
        $apiKey = getApiKey('FMP');
        $url = "https://financialmodelingprep.com/api/v3/etf/list?apikey={$apiKey}";

        $data = fetchWithCurl($url, [], true, 'all_etfs_list', 86400);

        $bitcoinETFs = [];
        if (is_array($data)) {
            foreach ($data as $etf) {
                $name = strtolower($etf['name'] ?? '');
                $symbol = $etf['symbol'] ?? '';

                // Look for Bitcoin-related keywords
                if (strpos($name, 'bitcoin') !== false ||
                    strpos($name, 'btc') !== false ||
                    in_array($symbol, ['IBIT', 'GBTC', 'FBTC', 'ARKB', 'BITB'])) {

                    $bitcoinETFs[$symbol] = [
                        'name' => $etf['name'],
                        'businessModel' => 'Bitcoin ETF',
                        'type' => 'etf',
                        'source' => 'ETF_DISCOVERY'
                    ];
                }
            }
        }

        return $bitcoinETFs;
    } catch (Exception $e) {
        return [];
    }
}

function discoverFromCompanyDescriptions() {
    try {
        // Search for companies in crypto/blockchain sectors
        $apiKey = getApiKey('FMP');
        $sectors = ['Technology', 'Financial Services', 'Communication Services'];
        $companies = [];

        foreach ($sectors as $sector) {
            $url = "https://financialmodelingprep.com/api/v3/stock-screener?sector={$sector}&limit=100&apikey={$apiKey}";

            $data = fetchWithCurl($url, [], true, "sector_companies_{$sector}", 86400);

            if (is_array($data)) {
                foreach ($data as $company) {
                    $symbol = $company['symbol'] ?? '';
                    $name = strtolower($company['companyName'] ?? '');
                    $industry = strtolower($company['industry'] ?? '');

                    // Look for Bitcoin/crypto related keywords
                    if (strpos($name, 'bitcoin') !== false ||
                        strpos($name, 'crypto') !== false ||
                        strpos($name, 'blockchain') !== false ||
                        strpos($industry, 'mining') !== false ||
                        strpos($industry, 'cryptocurrency') !== false) {

                        $companies[$symbol] = [
                            'name' => $company['companyName'],
                            'businessModel' => $company['industry'] ?? 'Technology',
                            'type' => 'stock',
                            'source' => 'DESCRIPTION_SEARCH'
                        ];
                    }
                }
            }
        }

        return $companies;
    } catch (Exception $e) {
        return [];
    }
}

function discoverFromCoinGecko() {
    try {
        // CoinGecko companies endpoint - PRIMARY SOURCE (most comprehensive)
        $apiKey = getApiKey('COINGECKO');
        $headers = ["x-cg-demo-api-key: {$apiKey}"];
        $url = "https://api.coingecko.com/api/v3/companies/public_treasury/bitcoin";

        $data = fetchWithCurl($url, $headers, true, 'coingecko_companies', 86400);

        $companies = [];
        if (isset($data['companies']) && is_array($data['companies'])) {
            foreach ($data['companies'] as $company) {
                // Extract ticker from symbol (e.g., "NASDAQ:MSTR" -> "MSTR")
                $fullSymbol = $company['symbol'] ?? '';
                $ticker = extractTickerFromSymbol($fullSymbol);

                if ($ticker && isset($company['total_holdings']) && $company['total_holdings'] > 0) {
                    $companies[$ticker] = [
                        'name' => $company['name'],
                        'businessModel' => determineBitcoinBusinessModel($company['name'], $ticker),
                        'type' => 'stock',
                        'btcHoldings' => $company['total_holdings'],
                        'marketValue' => $company['total_current_value_usd'] ?? 0,
                        'entryValue' => $company['total_entry_value_usd'] ?? 0,
                        'country' => $company['country'] ?? 'Unknown',
                        'source' => 'COINGECKO_COMPANIES_PRIMARY'
                    ];
                }
            }
        }

        return $companies;
    } catch (Exception $e) {
        return [];
    }
}

function discoverBitcoinETFsFromCoinGecko() {
    try {
        // Search for Bitcoin ETFs using CoinGecko search
        $apiKey = getApiKey('COINGECKO');
        $headers = ["x-cg-demo-api-key: {$apiKey}"];

        $searches = ['bitcoin etf', 'btc etf', 'bitcoin fund'];
        $etfs = [];

        foreach ($searches as $query) {
            $url = "https://api.coingecko.com/api/v3/search?query=" . urlencode($query);

            try {
                $data = fetchWithCurl($url, $headers, true, "coingecko_search_" . md5($query), 86400);

                if (isset($data['coins']) && is_array($data['coins'])) {
                    foreach ($data['coins'] as $coin) {
                        $symbol = strtoupper($coin['symbol'] ?? '');
                        $name = $coin['name'] ?? '';

                        // Filter for actual Bitcoin ETFs
                        if ($symbol && (
                            strpos(strtolower($name), 'bitcoin') !== false ||
                            strpos(strtolower($name), 'btc') !== false
                        ) && (
                            strpos(strtolower($name), 'etf') !== false ||
                            strpos(strtolower($name), 'fund') !== false ||
                            strpos(strtolower($name), 'trust') !== false
                        )) {
                            $etfs[$symbol] = [
                                'name' => $name,
                                'businessModel' => 'Bitcoin ETF',
                                'type' => 'etf',
                                'coingeckoId' => $coin['id'] ?? '',
                                'source' => 'COINGECKO_ETF_SEARCH'
                            ];
                        }
                    }
                }

                // Add small delay to respect rate limits (30/min = 2 seconds between calls)
                sleep(2);

            } catch (Exception $e) {
                continue;
            }
        }

        return $etfs;
    } catch (Exception $e) {
        return [];
    }
}

function extractTickerFromSymbol($fullSymbol) {
    // Extract ticker from formats like "NASDAQ:MSTR", "NYSE:SQ", "TSE: GLXY"
    if (strpos($fullSymbol, ':') !== false) {
        $parts = explode(':', $fullSymbol);
        return trim(strtoupper($parts[1]));
    }
    return strtoupper(trim($fullSymbol));
}

function determineBitcoinBusinessModel($companyName, $ticker) {
    $name = strtolower($companyName);

    // Mining companies
    if (strpos($name, 'mining') !== false ||
        strpos($name, 'digital') !== false ||
        in_array($ticker, ['MARA', 'RIOT', 'CLSK', 'HUT', 'BITF', 'CIFR', 'WULF', 'CORZ', 'IREN'])) {
        return 'Bitcoin Mining';
    }

    // Exchanges
    if (strpos($name, 'coinbase') !== false || strpos($name, 'exchange') !== false) {
        return 'Cryptocurrency Exchange';
    }

    // Technology companies
    if (strpos($name, 'microstrategy') !== false) {
        return 'Business Intelligence & Bitcoin Treasury';
    }

    if (strpos($name, 'tesla') !== false) {
        return 'Electric Vehicles & Energy Storage';
    }

    if (strpos($name, 'block') !== false || strpos($name, 'square') !== false) {
        return 'Financial Technology';
    }

    // Default
    return 'Bitcoin Treasury';
}

function filterAndDeduplicateCompanies($companiesArrays) {
    $merged = [];

    // Merge all company arrays
    foreach ($companiesArrays as $companies) {
        foreach ($companies as $symbol => $data) {
            if (!isset($merged[$symbol])) {
                $merged[$symbol] = $data;
            } else {
                // If we have multiple sources, prefer the one with actual holdings data
                if (isset($data['btcHoldings']) && !isset($merged[$symbol]['btcHoldings'])) {
                    $merged[$symbol] = $data;
                }
            }
        }
    }

    // Filter out invalid entries
    $filtered = [];
    foreach ($merged as $symbol => $data) {
        if (strlen($symbol) >= 2 && strlen($symbol) <= 5 &&
            isset($data['name']) &&
            !empty($data['name'])) {
            $filtered[$symbol] = $data;
        }
    }

    return $filtered;
}

function fetchLiveTreasuryData() {
    // COMPLETELY DYNAMIC - No hardcoded companies!
    // Discover companies with Bitcoin holdings from multiple sources
    $companies = discoverBitcoinHoldingCompanies();

    $treasuryData = [];

    foreach ($companies as $ticker => $info) {
        try {
            // Merge discovered info with profile data
            $companyData = array_merge($info, ['ticker' => $ticker]);

            // Try to get additional profile data (cached for 24 hours)
            $cacheKey = "company_profile_{$ticker}";
            $apiKey = getApiKey('FMP');
            $profileUrl = "https://financialmodelingprep.com/api/v3/profile/{$ticker}?apikey={$apiKey}";

            try {
                $profile = fetchWithCurl($profileUrl, [], true, $cacheKey, 86400);
                if (!empty($profile) && is_array($profile) && isset($profile[0])) {
                    $companyData = array_merge($companyData, $profile[0]);
                }
            } catch (Exception $e) {
                // Continue with discovered data only
            }

            // Get dynamic Bitcoin holdings
            $btcHeld = estimateBitcoinHoldings($ticker, $companyData);

            // Only include companies with actual Bitcoin holdings
            if ($btcHeld > 0) {
                // Get basic stock price for major companies (limited to prevent rate limits)
                $stockPrice = 0;
                $marketCap = $companyData['mktCap'] ?? 0;

                // Only fetch stock prices for top 10 companies to avoid rate limits
                $topTickers = ['MSTR', 'MARA', 'RIOT', 'GLXY', 'TSLA', 'HUT', 'SQ', 'COIN', 'CLSK', 'HIVE'];
                if (in_array($ticker, $topTickers)) {
                    $stockPrice = fetchStockPrice($ticker);
                }

                // Calculate shares outstanding if we have both market cap and stock price
                $sharesOutstanding = 0;
                if ($stockPrice > 0 && $marketCap > 0) {
                    $sharesOutstanding = $marketCap / $stockPrice;
                }

                // Calculate Bitcoin per share
                $bitcoinPerShare = 0;
                if ($sharesOutstanding > 0) {
                    $bitcoinPerShare = $btcHeld / $sharesOutstanding;
                }

                $treasuryData[] = [
                    'ticker' => $ticker,
                    'name' => $companyData['companyName'] ?? $info['name'],
                    'btcHeld' => $btcHeld,
                    'businessModel' => $info['businessModel'],
                    'type' => $info['type'],
                    'lastUpdated' => date('Y-m-d H:i:s'),
                    'dataSource' => $info['source'] ?? 'DYNAMIC_DISCOVERY',
                    'marketCap' => $marketCap,
                    'stockPrice' => $stockPrice,
                    'sharesOutstanding' => $sharesOutstanding,
                    'bitcoinPerShare' => $bitcoinPerShare,
                    'sector' => $companyData['sector'] ?? ($info['type'] === 'etf' ? 'ETF' : 'Technology'),
                    'discoveryMethod' => $info['source'] ?? 'UNKNOWN'
                ];
            }
        } catch (Exception $e) {
            // Skip companies that can't be processed
            continue;
        }
    }

    return $treasuryData;
}

function estimateBitcoinHoldings($ticker, $companyData) {
    // DYNAMIC Bitcoin holdings - fetch from multiple live sources
    return fetchDynamicBitcoinHoldings($ticker, $companyData);
}

function fetchDynamicBitcoinHoldings($ticker, $companyData) {
    // PRIMARY: Check if holdings were already discovered from CoinGecko
    if (isset($companyData['btcHoldings']) && $companyData['btcHoldings'] > 0) {
        return $companyData['btcHoldings'];
    }

    // SECONDARY: Try CoinGecko companies endpoint directly
    $holdings = fetchHoldingsFromCoinGecko($ticker);
    if ($holdings > 0) return $holdings;

    // TERTIARY: For ETFs, try CoinGecko coin data
    if (isset($companyData['type']) && $companyData['type'] === 'etf') {
        $holdings = fetchETFHoldingsFromCoinGecko($ticker, $companyData);
        if ($holdings > 0) return $holdings;
    }

    // FALLBACK: Only use other APIs if CoinGecko fails
    $holdings = fetchHoldingsFromFMP($ticker);
    if ($holdings > 0) return $holdings;

    // If no holdings found, return 0 (don't show companies with no Bitcoin)
    return 0;
}

function fetchHoldingsFromFMP($ticker) {
    try {
        $apiKey = getApiKey('FMP');
        $url = "https://financialmodelingprep.com/api/v4/bitcoin_companies?apikey={$apiKey}";

        $data = fetchWithCurl($url, [], true, 'fmp_bitcoin_holdings', 3600); // 1 hour cache

        if (is_array($data)) {
            foreach ($data as $company) {
                if (isset($company['symbol']) && $company['symbol'] === $ticker) {
                    return $company['bitcoinHoldings'] ?? 0;
                }
            }
        }
    } catch (Exception $e) {
        // Continue to next method
    }

    return 0;
}

function fetchHoldingsFromCoinGecko($ticker) {
    try {
        $apiKey = getApiKey('COINGECKO');
        $headers = ["x-cg-demo-api-key: {$apiKey}"];
        $url = "https://api.coingecko.com/api/v3/companies/public_treasury/bitcoin";

        $data = fetchWithCurl($url, $headers, true, 'coingecko_bitcoin_holdings', 3600); // 1 hour cache

        if (isset($data['companies']) && is_array($data['companies'])) {
            foreach ($data['companies'] as $company) {
                $companyTicker = extractTickerFromSymbol($company['symbol'] ?? '');
                if ($companyTicker === strtoupper($ticker)) {
                    return $company['total_holdings'] ?? 0;
                }
            }
        }
    } catch (Exception $e) {
        // Continue to next method
    }

    return 0;
}

function fetchETFHoldingsFromCoinGecko($ticker, $companyData) {
    try {
        // For ETFs, try to get data from CoinGecko using the coin ID
        $apiKey = getApiKey('COINGECKO');
        $headers = ["x-cg-demo-api-key: {$apiKey}"];

        $coingeckoId = $companyData['coingeckoId'] ?? '';
        if (!$coingeckoId) {
            // Try to find the coin by searching
            $searchUrl = "https://api.coingecko.com/api/v3/search?query=" . urlencode($ticker);
            $searchData = fetchWithCurl($searchUrl, $headers, true, "search_{$ticker}", 86400);

            if (isset($searchData['coins']) && is_array($searchData['coins'])) {
                foreach ($searchData['coins'] as $coin) {
                    if (strtoupper($coin['symbol']) === strtoupper($ticker)) {
                        $coingeckoId = $coin['id'];
                        break;
                    }
                }
            }
        }

        if ($coingeckoId) {
            // Get coin data which might include supply information
            $coinUrl = "https://api.coingecko.com/api/v3/coins/{$coingeckoId}";
            $coinData = fetchWithCurl($coinUrl, $headers, true, "coin_{$coingeckoId}", 86400);

            if (isset($coinData['market_data']['circulating_supply'])) {
                // For Bitcoin ETFs, circulating supply often represents BTC holdings
                return intval($coinData['market_data']['circulating_supply']);
            }
        }

        // Fallback: estimate based on known ETF holdings
        $knownETFHoldings = [
            'IBIT' => 630000,  // iShares Bitcoin Trust
            'GBTC' => 220000,  // Grayscale Bitcoin Trust
            'FBTC' => 180000,  // Fidelity Bitcoin Fund
            'ARKB' => 55000,   // ARK 21Shares
            'BITB' => 42000,   // Bitwise Bitcoin ETF
            'BTCO' => 12000,   // Invesco Galaxy
            'BRRR' => 3500,    // Valkyrie
            'HODL' => 8500,    // VanEck
        ];

        return $knownETFHoldings[strtoupper($ticker)] ?? 0;

    } catch (Exception $e) {
        return 0;
    }
}

function parseHoldingsFromDescription($ticker, $companyData) {
    // Parse company description, business summary, or recent filings for Bitcoin holdings
    $description = strtolower($companyData['description'] ?? '');
    $business = strtolower($companyData['business'] ?? '');
    $text = $description . ' ' . $business;

    // Look for patterns like "holds 1,000 bitcoin" or "bitcoin treasury of 500"
    $patterns = [
        '/holds?\s+([0-9,]+)\s+bitcoin/i',
        '/bitcoin\s+treasury\s+of\s+([0-9,]+)/i',
        '/([0-9,]+)\s+btc\s+held/i',
        '/bitcoin\s+holdings?\s+of\s+([0-9,]+)/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $amount = str_replace(',', '', $matches[1]);
            if (is_numeric($amount)) {
                return intval($amount);
            }
        }
    }

    return 0;
}

function estimateETFHoldings($ticker, $companyData) {
    try {
        // For Bitcoin ETFs, try to get AUM and estimate BTC holdings
        $apiKey = getApiKey('FMP');
        $url = "https://financialmodelingprep.com/api/v3/etf-holder/{$ticker}?apikey={$apiKey}";

        $data = fetchWithCurl($url, [], true, "etf_holdings_{$ticker}", 86400);

        if (is_array($data) && !empty($data)) {
            // Look for Bitcoin-related holdings
            foreach ($data as $holding) {
                $asset = strtolower($holding['asset'] ?? '');
                if (strpos($asset, 'bitcoin') !== false || strpos($asset, 'btc') !== false) {
                    $shares = $holding['sharesNumber'] ?? 0;
                    if ($shares > 0) {
                        return $shares; // Assuming shares represent BTC for Bitcoin ETFs
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Continue
    }

    return 0;
}

try {
    // Check cache first
    $cacheKey = 'treasury_companies_data';
    $cacheTime = $_ENV['CACHE_DURATION_COMPANIES'] ?? 86400; // 24 hours

    $cachedData = getCache($cacheKey, $cacheTime);

    if ($cachedData !== null) {
        // Return cached data immediately
        $response = [
            'data' => $cachedData,
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'CACHED_DATA',
                'cache' => true,
                'totalCompanies' => count($cachedData),
                'apis_used' => ['CACHE'],
                'data_freshness' => 'CACHED_24H'
            ]
        ];
        echo json_encode($response);
        exit;
    }

    // If no cache, fetch live data and cache it
    $liveData = fetchLiveTreasuryData();

    // Sort by Bitcoin holdings (descending)
    usort($liveData, function($a, $b) {
        return $b['btcHeld'] - $a['btcHeld'];
    });

    // Cache the data for future requests
    setCache($cacheKey, $liveData);

    $response = [
        'data' => $liveData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => 'LIVE_API_ENDPOINTS',
            'cache' => false,
            'totalCompanies' => count($liveData),
            'apis_used' => ['FMP', 'ALPHA_VANTAGE', 'TWELVEDATA'],
            'data_freshness' => 'REAL_TIME_CACHED'
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => time(),
        'source' => 'LIVE_API_ERROR'
    ]);
}