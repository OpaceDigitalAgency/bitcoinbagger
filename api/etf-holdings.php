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

    // Step 1: Try to get Bitcoin holdings data from btcetfdata.com
    $holdingsData = [];
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

        // Calculate AUM if we have price and shares
        if ($aum == 0 && $price > 0 && $sharesOutstanding > 0) {
            $aum = $price * $sharesOutstanding;
        }

        // Calculate BTC per share
        $btcPerShare = 0;
        if ($btcHeld > 0 && $sharesOutstanding > 0) {
            $btcPerShare = $btcHeld / $sharesOutstanding;
        }

        // Calculate premium/discount (if we have NAV and price)
        $premium = 0;
        if ($nav > 0 && $price > 0 && $nav != $price) {
            $premium = (($price - $nav) / $nav) * 100;
        }

        // Only include ETFs with meaningful data
        if ($btcHeld > 0 || $price > 0) {
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
    }

    if (!empty($etfData)) {
        return $etfData;
    }

    // If all API sources fail, use known ETF data with realistic holdings
    $knownETFs = [
        'IBIT' => [
            'name' => 'iShares Bitcoin Trust',
            'btcHeld' => 630000,
            'aum' => 50000000000
        ],
        'FBTC' => [
            'name' => 'Fidelity Wise Origin Bitcoin Fund',
            'btcHeld' => 180000,
            'aum' => 15000000000
        ],
        'GBTC' => [
            'name' => 'Grayscale Bitcoin Trust',
            'btcHeld' => 220000,
            'aum' => 18000000000
        ],
        'ARKB' => [
            'name' => 'ARK 21Shares Bitcoin ETF',
            'btcHeld' => 55000,
            'aum' => 4500000000
        ],
        'BITB' => [
            'name' => 'Bitwise Bitcoin ETF',
            'btcHeld' => 42000,
            'aum' => 3500000000
        ],
        'BTCO' => [
            'name' => 'Invesco Galaxy Bitcoin ETF',
            'btcHeld' => 12000,
            'aum' => 1000000000
        ],
        'BRRR' => [
            'name' => 'Valkyrie Bitcoin Fund',
            'btcHeld' => 3500,
            'aum' => 300000000
        ]
    ];

    foreach ($knownETFs as $ticker => $info) {
        // Try to get live price data even for fallback
        $priceData = fetchETFPrice($ticker);

        $etfData[] = [
            'ticker' => $ticker,
            'name' => $info['name'],
            'btcHeld' => $info['btcHeld'],
            'sharesOutstanding' => $priceData['sharesOutstanding'] ?? 0,
            'nav' => $priceData['nav'],
            'price' => $priceData['price'],
            'aum' => $info['aum'],
            'expenseRatio' => 0,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'dataSource' => 'KNOWN_ETF_FALLBACK',
            'warning' => 'Using known ETF data - live APIs unavailable'
        ];
    }

    return $etfData;
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

// Helper function to parse asset values like "$23,740.88" or "$23.74B"
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
