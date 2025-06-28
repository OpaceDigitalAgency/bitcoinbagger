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
    $sources = [
        'bitcoinetfdata' => [
            'url' => 'https://btcetfdata.com/v1/current.json',
            'priority' => 1
        ],
        'coingecko_etf' => [
            'url' => 'https://api.coingecko.com/api/v3/search?query=bitcoin%20etf',
            'priority' => 2
        ]
    ];

    // Try primary source: BitcoinETFData.com
    foreach ($sources as $sourceName => $config) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'BitcoinBagger/1.0'
                ]
            ]);

            $response = file_get_contents($config['url'], false, $context);
            if ($response === false) {
                throw new Exception("Failed to fetch from {$sourceName}");
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON from {$sourceName}");
            }

            if ($sourceName === 'bitcoinetfdata' && !empty($data) && isset($data['data'])) {
                foreach ($data['data'] as $ticker => $etfInfo) {
                    if (isset($etfInfo['holdings']) && $etfInfo['holdings'] > 0) {
                        // Fetch live price data for major ETFs
                        $priceData = ['price' => 0, 'nav' => 0];
                        $majorETFs = ['IBIT', 'FBTC', 'GBTC', 'ARKB', 'BITB'];
                        if (in_array(strtoupper($ticker), $majorETFs)) {
                            $priceData = fetchETFPrice(strtoupper($ticker));
                        }

                        $etfData[] = [
                            'ticker' => strtoupper($ticker),
                            'name' => $etfInfo['name'] ?? $ticker . ' Bitcoin ETF',
                            'btcHeld' => floatval($etfInfo['holdings']),
                            'sharesOutstanding' => $priceData['sharesOutstanding'] ?: floatval($etfInfo['shares_outstanding'] ?? 0),
                            'nav' => $priceData['nav'] ?: floatval($etfInfo['nav'] ?? 0),
                            'price' => $priceData['price'] ?: floatval($etfInfo['price'] ?? 0),
                            'aum' => floatval($etfInfo['aum'] ?? 0),
                            'expenseRatio' => floatval($etfInfo['expense_ratio'] ?? 0),
                            'lastUpdated' => date('Y-m-d H:i:s'),
                            'dataSource' => 'BITCOINETFDATA_COM_LIVE'
                        ];
                    }
                }

                if (!empty($etfData)) {
                    return $etfData; // Success with primary source
                }
            }

        } catch (Exception $e) {
            error_log("ETF data source {$sourceName} failed: " . $e->getMessage());
            continue;
        }
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

    // Try Yahoo Finance first (free, no API key required)
    try {
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}";
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                $price = floatval($data['chart']['result'][0]['meta']['regularMarketPrice']);
            }
        }
    } catch (Exception $e) {
        // Continue to next API
    }

    // Try Yahoo Finance quote API as backup
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
    // Check cache first (1 hour cache for ETF data)
    $cacheKey = 'etf_holdings_data';
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
