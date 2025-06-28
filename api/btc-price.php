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
    // PRIMARY: CoinGecko (most reliable and comprehensive)
    $apiKey = getApiKey('COINGECKO');
    $sources = [
        'coingecko' => [
            'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true',
            'headers' => ["x-cg-demo-api-key: {$apiKey}"]
        ],
        'coingecko_free' => [
            'url' => 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true',
            'headers' => []
        ],
        'coinbase' => [
            'url' => 'https://api.coinbase.com/v2/exchange-rates?currency=BTC',
            'headers' => []
        ],
        'fmp' => [
            'url' => 'https://financialmodelingprep.com/api/v3/quote/BTCUSD?apikey=' . getApiKey('FMP'),
            'headers' => []
        ]
    ];

    foreach ($sources as $source => $config) {
        try {
            // Check cache first (5 minute cache to reduce API calls)
            $cacheKey = "btc_price_{$source}";
            $cached = getCache($cacheKey, 300);
            if ($cached !== null) {
                return $cached;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $config['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if (!empty($config['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $config['headers']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                
                // Parse response based on source
                $priceData = null;
                switch ($source) {
                    case 'coingecko':
                        if (isset($data['bitcoin'])) {
                            $priceData = [
                                'price' => $data['bitcoin']['usd'],
                                'change_24h' => $data['bitcoin']['usd_24h_change'] ?? 0,
                                'market_cap' => $data['bitcoin']['usd_market_cap'] ?? 0,
                                'source' => 'CoinGecko',
                                'timestamp' => time()
                            ];
                        }
                        break;
                        
                    case 'coinbase':
                        if (isset($data['data']['rates']['USD'])) {
                            $priceData = [
                                'price' => floatval($data['data']['rates']['USD']),
                                'change_24h' => 0, // Coinbase doesn't provide 24h change in this endpoint
                                'market_cap' => 0,
                                'source' => 'Coinbase',
                                'timestamp' => time()
                            ];
                        }
                        break;
                        
                    case 'fmp':
                        if (isset($data[0]['price'])) {
                            $priceData = [
                                'price' => $data[0]['price'],
                                'change_24h' => $data[0]['changesPercentage'] ?? 0,
                                'market_cap' => 0,
                                'source' => 'FMP',
                                'timestamp' => time()
                            ];
                        }
                        break;
                }
                
                if ($priceData) {
                    setCache($cacheKey, $priceData);
                    return $priceData;
                }
            }
        } catch (Exception $e) {
            // Continue to next source
            continue;
        }
    }
    
    // If all sources fail, return cached data (even if stale)
    foreach (['coingecko', 'coinbase', 'fmp'] as $source) {
        $staleCache = getCache("btc_price_{$source}", 3600); // Accept 1-hour old data
        if ($staleCache !== null) {
            $staleCache['stale'] = true;
            return $staleCache;
        }
    }
    
    // Last resort - return estimated price
    return [
        'price' => 107000, // Approximate current price
        'change_24h' => 0,
        'market_cap' => 2100000000000,
        'source' => 'ESTIMATED',
        'timestamp' => time(),
        'error' => 'All price sources unavailable'
    ];
}

// Main execution
try {
    $priceData = fetchBitcoinPrice();
    
    $response = [
        'success' => true,
        'data' => $priceData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'cache_duration' => 60,
            'endpoint' => 'btc-price'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>
