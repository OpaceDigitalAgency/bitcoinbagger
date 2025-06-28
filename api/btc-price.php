<?php
header('Content-Type: application/json');
header('Cache-Control: public, max-age=60'); // 1 minute cache for price
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');

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

// Real-time Bitcoin price endpoint - separate from treasury data
// This endpoint is called frequently so uses shorter cache times

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

function getCacheFile($key) {
    global $CACHE_DIR;
    return $CACHE_DIR . md5($key) . '.json';
}

function getCache($key, $maxAge = 60) { // Default 1 minute for price
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
    $key = $API_KEYS[$provider] ?? '';
    return $key;
}

function fetchBitcoinPrice() {
    // IMPROVED CASCADING FALLBACK SYSTEM
    $sources = [
        'coingecko_pro' => [
            'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true',
            'headers' => ["x-cg-demo-api-key: " . getApiKey('COINGECKO')],
            'priority' => 1
        ],
        'coingecko_free' => [
            'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true',
            'headers' => [],
            'priority' => 2
        ],
        'fmp' => [
            'url' => 'https://financialmodelingprep.com/api/v3/quote/BTCUSD?apikey=' . getApiKey('FMP'),
            'headers' => [],
            'priority' => 3
        ],
        'alpha_vantage' => [
            'url' => 'https://www.alphavantage.co/query?function=CURRENCY_EXCHANGE_RATE&from_currency=BTC&to_currency=USD&apikey=' . getApiKey('ALPHA_VANTAGE'),
            'headers' => [],
            'priority' => 4
        ],
        'coinbase' => [
            'url' => 'https://api.coinbase.com/v2/exchange-rates?currency=BTC',
            'headers' => [],
            'priority' => 5
        ],
        'binance' => [
            'url' => 'https://api.binance.com/api/v3/ticker/24hr?symbol=BTCUSDT',
            'headers' => [],
            'priority' => 6
        ]
    ];

    // Try each source in priority order
    foreach ($sources as $source => $config) {
        try {
            // Check cache first (2 minute cache for price data)
            $cacheKey = "btc_price_{$source}";
            $cached = getCache($cacheKey, 120);
            if ($cached !== null && isset($cached['price']) && $cached['price'] > 0) {
                $cached['cache_hit'] = true;
                return $cached;
            }

            // Make API call with timeout and error handling
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $config['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BitcoinBagger/1.0');

            if (!empty($config['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $config['headers']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception("CURL Error for {$source}: {$curlError}");
            }

            if ($httpCode !== 200) {
                throw new Exception("HTTP {$httpCode} for {$source}");
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON from {$source}");
            }

            // Parse response based on source
            $priceData = parseSourceResponse($source, $data);

            if ($priceData && $priceData['price'] > 0) {
                $priceData['cache_hit'] = false;
                $priceData['api_source'] = $source;
                setCache($cacheKey, $priceData);
                return $priceData;
            }

        } catch (Exception $e) {
            error_log("Bitcoin price API error ({$source}): " . $e->getMessage());
            continue;
        }
    }

    // If all live sources fail, try stale cache (up to 6 hours old)
    foreach (array_keys($sources) as $source) {
        $staleCache = getCache("btc_price_{$source}", 21600);
        if ($staleCache !== null && isset($staleCache['price']) && $staleCache['price'] > 0) {
            $staleCache['stale'] = true;
            $staleCache['cache_hit'] = true;
            return $staleCache;
        }
    }

    // Final fallback - throw exception instead of returning fake data
    throw new Exception('All Bitcoin price sources failed - no cached data available');
}

function parseSourceResponse($source, $data) {
    switch ($source) {
        case 'coingecko_pro':
        case 'coingecko_free':
            if (isset($data['bitcoin']['usd'])) {
                return [
                    'price' => floatval($data['bitcoin']['usd']),
                    'change_24h' => floatval($data['bitcoin']['usd_24h_change'] ?? 0),
                    'market_cap' => floatval($data['bitcoin']['usd_market_cap'] ?? 0),
                    'source' => 'CoinGecko',
                    'timestamp' => time()
                ];
            }
            break;

        case 'fmp':
            if (isset($data[0]['price'])) {
                return [
                    'price' => floatval($data[0]['price']),
                    'change_24h' => floatval($data[0]['changesPercentage'] ?? 0),
                    'market_cap' => 0,
                    'source' => 'FMP',
                    'timestamp' => time()
                ];
            }
            break;

        case 'alpha_vantage':
            if (isset($data['Realtime Currency Exchange Rate']['5. Exchange Rate'])) {
                return [
                    'price' => floatval($data['Realtime Currency Exchange Rate']['5. Exchange Rate']),
                    'change_24h' => 0,
                    'market_cap' => 0,
                    'source' => 'Alpha Vantage',
                    'timestamp' => time()
                ];
            }
            break;

        case 'coinbase':
            if (isset($data['data']['rates']['USD'])) {
                return [
                    'price' => floatval($data['data']['rates']['USD']),
                    'change_24h' => 0,
                    'market_cap' => 0,
                    'source' => 'Coinbase',
                    'timestamp' => time()
                ];
            }
            break;

        case 'binance':
            if (isset($data['lastPrice'])) {
                return [
                    'price' => floatval($data['lastPrice']),
                    'change_24h' => floatval($data['priceChangePercent'] ?? 0),
                    'market_cap' => 0,
                    'source' => 'Binance',
                    'timestamp' => time()
                ];
            }
            break;
    }

    return null;
}

// Main execution with improved error handling
try {
    $priceData = fetchBitcoinPrice();

    $response = [
        'success' => true,
        'data' => $priceData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'cache_duration' => 120,
            'endpoint' => 'btc-price',
            'source' => $priceData['source'] ?? 'Unknown',
            'stale' => $priceData['stale'] ?? false,
            'cache_hit' => $priceData['cache_hit'] ?? false
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Instead of returning 500, provide fallback data
    error_log("Bitcoin price API critical failure: " . $e->getMessage());

    // Try to get any cached data, even very old
    $emergencyCache = null;
    $sources = ['coingecko_pro', 'coingecko_free', 'fmp', 'alpha_vantage', 'coinbase', 'binance'];

    foreach ($sources as $source) {
        $cache = getCache("btc_price_{$source}", 86400 * 7); // Accept week-old data
        if ($cache !== null && isset($cache['price']) && $cache['price'] > 0) {
            $emergencyCache = $cache;
            break;
        }
    }

    if ($emergencyCache) {
        // Return very stale data with warning
        $response = [
            'success' => true,
            'data' => array_merge($emergencyCache, [
                'emergency_fallback' => true,
                'stale' => true,
                'warning' => 'Using emergency cached data - live APIs unavailable'
            ]),
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'cache_duration' => 120,
                'endpoint' => 'btc-price',
                'source' => 'EMERGENCY_CACHE',
                'warning' => 'All live price sources failed'
            ]
        ];
        echo json_encode($response);
    } else {
        // Absolute last resort - return reasonable estimate with clear warning
        http_response_code(503); // Service Temporarily Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'All Bitcoin price sources temporarily unavailable',
            'fallback_data' => [
                'price' => 100000, // Conservative estimate
                'change_24h' => 0,
                'market_cap' => 0,
                'source' => 'EMERGENCY_ESTIMATE',
                'timestamp' => time(),
                'warning' => 'This is an emergency fallback price - not real market data'
            ],
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'endpoint' => 'btc-price',
                'status' => 'CRITICAL_FAILURE'
            ]
        ]);
    }
}
