<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// SIMPLIFIED TREASURIES API - USING ONLY TRUSTED SOURCES
// - FMP Starter Plan (300 calls/minute)
// - CoinGecko (company discovery + Bitcoin price)

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

// SIMPLIFIED HELPER FUNCTIONS - USING ONLY TRUSTED APIS

// Get FMP API key
function getFMPKey() {
    return $_ENV['FMP_API_KEY'] ?? '';
}

// Get companies with Bitcoin holdings from CoinGecko
function getCompaniesFromCoinGecko() {
    $companies = [];

    try {
        $url = 'https://api.coingecko.com/api/v3/companies/public_treasury/bitcoin';
        $response = file_get_contents($url);

        if ($response !== false) {
            $data = json_decode($response, true);

            if (isset($data['companies']) && is_array($data['companies'])) {
                foreach ($data['companies'] as $company) {
                    $ticker = strtoupper($company['symbol'] ?? '');
                    $btcHeld = floatval($company['total_holdings'] ?? 0);

                    if ($ticker && $btcHeld > 0) {
                        $companies[$ticker] = [
                            'name' => $company['name'] ?? $ticker,
                            'btcHeld' => $btcHeld,
                            'country' => $company['country'] ?? 'Unknown'
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("CoinGecko companies API failed: " . $e->getMessage());
    }

    return $companies;
}

// Get stock data from FMP
function getFMPStockData($ticker) {
    $fmpKey = getFMPKey();
    if (!$fmpKey) {
        return null;
    }

    try {
        $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
        $response = file_get_contents($url);

        if ($response !== false) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data) && isset($data[0])) {
                $quote = $data[0];
                return [
                    'price' => floatval($quote['price'] ?? 0),
                    'marketCap' => floatval($quote['marketCap'] ?? 0),
                    'sharesOutstanding' => floatval($quote['sharesOutstanding'] ?? 0),
                    'volume' => floatval($quote['volume'] ?? 0),
                    'pe' => floatval($quote['pe'] ?? 0)
                ];
            }
        }
    } catch (Exception $e) {
        error_log("FMP stock data failed for {$ticker}: " . $e->getMessage());
    }

    return null;
}

// Get current Bitcoin price from CoinGecko
function getCurrentBitcoinPrice() {
    static $cachedPrice = null;
    static $cacheTime = 0;

    // Use cached price if less than 5 minutes old
    if ($cachedPrice !== null && (time() - $cacheTime) < 300) {
        return $cachedPrice;
    }

    try {
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd';
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data['bitcoin']['usd'])) {
            $cachedPrice = floatval($data['bitcoin']['usd']);
            $cacheTime = time();
            return $cachedPrice;
        }
    } catch (Exception $e) {
        error_log("CoinGecko Bitcoin price failed: " . $e->getMessage());
    }

    return 0;
}

// SIMPLIFIED: Get company data using only trusted APIs
function fetchLiveCompanyData() {
    $companyData = [];

    // STEP 1: Get companies with Bitcoin holdings from CoinGecko
    $companies = getCompaniesFromCoinGecko();

    if (empty($companies)) {
        throw new Exception('No companies found from CoinGecko');
    }

    // STEP 2: Get stock data from FMP for each company
    foreach ($companies as $ticker => $companyInfo) {
        // Get stock financial data from FMP
        $stockData = getFMPStockData($ticker);

        if (!$stockData || $stockData['price'] <= 0) {
            // Skip companies without valid stock data
            continue;
        }

        $stockPrice = $stockData['price'];
        $marketCap = $stockData['marketCap'];
        $sharesOutstanding = $stockData['sharesOutstanding'];
        $btcHeld = $companyInfo['btcHeld'];

        // Calculate Bitcoin per share
        $bitcoinPerShare = 0;
        if ($btcHeld > 0 && $sharesOutstanding > 0) {
            $bitcoinPerShare = $btcHeld / $sharesOutstanding;
        }

        // Calculate BSP (Bitcoin per Share Price)
        $bsp = 0;
        if ($bitcoinPerShare > 0) {
            $btcPrice = getCurrentBitcoinPrice();
            if ($btcPrice > 0) {
                $bsp = $bitcoinPerShare * $btcPrice;
            }
        }

        // Calculate premium/discount
        $premium = 0;
        if ($stockPrice > 0 && $bsp > 0) {
            $premium = (($stockPrice - $bsp) / $bsp) * 100;
        }

        // Only include companies with complete data
        if ($stockPrice > 0 && $marketCap > 0 && $bsp > 0) {
            $companyData[] = [
                'ticker' => $ticker,
                'name' => $companyInfo['name'],
                'btcHeld' => $btcHeld,
                'stockPrice' => $stockPrice,
                'marketCap' => $marketCap,
                'sharesOutstanding' => $sharesOutstanding,
                'bitcoinPerShare' => $bitcoinPerShare,
                'bsp' => $bsp,
                'premium' => $premium,
                'volume' => $stockData['volume'] ?? 0,
                'pe' => $stockData['pe'] ?? 0,
                'country' => $companyInfo['country'],
                'lastUpdated' => date('Y-m-d H:i:s'),
                'dataSource' => 'COINGECKO_FMP'
            ];
        }
    }

    return $companyData;
}

// Cache management functions
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

// MAIN API EXECUTION - SIMPLIFIED
try {
    // Check for cache clearing request
    $clearCache = isset($_GET['clear_cache']) || isset($_GET['force_refresh']);

    // Check cache first (1 hour cache for company data)
    $cacheKey = 'company_treasuries_data';

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
            $totalMarketCap = array_sum(array_column($cachedData, 'marketCap'));

            echo json_encode([
                'success' => true,
                'data' => $cachedData,
                'meta' => [
                    'timestamp' => time(),
                    'datetime' => date('Y-m-d H:i:s'),
                    'source' => 'CACHED_COMPANY_DATA',
                    'cache' => true,
                    'totalCompanies' => count($cachedData),
                    'totalBTC' => $totalBTC,
                    'totalMarketCap' => $totalMarketCap,
                    'data_freshness' => 'CACHED_1H'
                ]
            ]);
            exit;
        }
    }

    // Fetch fresh data using simplified approach
    $companyData = fetchLiveCompanyData();

    if (empty($companyData)) {
        throw new Exception('No company data retrieved from trusted sources');
    }

    // Sort by BTC holdings (descending)
    usort($companyData, function($a, $b) {
        return ($b['btcHeld'] ?? 0) - ($a['btcHeld'] ?? 0);
    });

    // Calculate totals
    $totalBTC = array_sum(array_column($companyData, 'btcHeld'));
    $totalMarketCap = array_sum(array_column($companyData, 'marketCap'));

    // Cache the fresh data
    setCache($cacheKey, $companyData);

    echo json_encode([
        'success' => true,
        'data' => $companyData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => 'LIVE_TRUSTED_APIS',
            'cache' => false,
            'totalCompanies' => count($companyData),
            'totalBTC' => $totalBTC,
            'totalMarketCap' => $totalMarketCap,
            'data_freshness' => 'REAL_TIME',
            'apis_used' => ['FMP_STARTER', 'COINGECKO']
        ]
    ]);

} catch (Exception $e) {
    error_log("Company treasuries API error: " . $e->getMessage());

    // Try stale cache as fallback
    $staleCache = getCache($cacheKey, 86400 * 7); // Accept week-old data

    if ($staleCache !== null && !empty($staleCache)) {
        $totalBTC = array_sum(array_column($staleCache, 'btcHeld'));
        $totalMarketCap = array_sum(array_column($staleCache, 'marketCap'));

        echo json_encode([
            'success' => true,
            'data' => $staleCache,
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'STALE_CACHE_FALLBACK',
                'cache' => true,
                'totalCompanies' => count($staleCache),
                'totalBTC' => $totalBTC,
                'totalMarketCap' => $totalMarketCap,
                'warning' => 'Using stale cached data - live APIs failed',
                'error' => $e->getMessage(),
                'data_freshness' => 'STALE_CACHE'
            ]
        ]);
    } else {
        http_response_code(503); // Service Temporarily Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'Company data temporarily unavailable',
            'message' => $e->getMessage(),
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'COMPANY_API_CRITICAL_FAILURE',
                'status' => 'SERVICE_UNAVAILABLE'
            ]
        ]);
    }
}
?>