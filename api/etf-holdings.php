<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// SIMPLIFIED ETF HOLDINGS API - USING ONLY TRUSTED SOURCES
// - FMP Starter Plan (300 calls/minute)
// - BitcoinETFData.com (reliable for major ETFs)
// - CoinGecko (Bitcoin price)

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

// REMOVED: getBitcoinHoldingsFromBitcoinETFData function
// Using only FMP ETF Holdings API for all Bitcoin ETF data

// Get ETF holdings from FMP
function getFMPETFHoldings($ticker) {
    $fmpKey = getFMPKey();
    if (!$fmpKey) {
        return 0;
    }

    try {
        $url = "https://financialmodelingprep.com/api/v3/etf-holder/{$ticker}?apikey={$fmpKey}";
        $response = file_get_contents($url);

        if ($response !== false) {
            $data = json_decode($response, true);
            if (is_array($data) && !empty($data)) {
                // Look for Bitcoin holdings in ETF
                foreach ($data as $holding) {
                    $asset = strtolower($holding['asset'] ?? '');
                    if (stripos($asset, 'bitcoin') !== false || stripos($asset, 'btc') !== false) {
                        return floatval($holding['sharesNumber'] ?? 0);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("FMP ETF holdings failed for {$ticker}: " . $e->getMessage());
    }

    return 0;
}

// Get ETF financial data from FMP
function getFMPETFData($ticker) {
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
                    'sharesOutstanding' => floatval($quote['sharesOutstanding'] ?? 0),
                    'marketCap' => floatval($quote['marketCap'] ?? 0),
                    'volume' => floatval($quote['volume'] ?? 0)
                ];
            }
        }
    } catch (Exception $e) {
        error_log("FMP ETF data failed for {$ticker}: " . $e->getMessage());
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

// SIMPLIFIED: Get ETF data using only trusted APIs
function fetchLiveETFData() {
    $etfData = [];

    // COMPREHENSIVE BITCOIN ETF LIST - Based on ETFdb.com complete list
    $bitcoinETFs = [
        // US Spot Bitcoin ETFs (Physical Bitcoin ETFs - January 2024 launches)
        'IBIT' => 'iShares Bitcoin Trust ETF',
        'FBTC' => 'Fidelity Wise Origin Bitcoin Fund',
        'GBTC' => 'Grayscale Bitcoin Trust ETF',
        'ARKB' => 'ARK 21Shares Bitcoin ETF',
        'BITB' => 'Bitwise Bitcoin ETF Trust',
        'BTCO' => 'Invesco Galaxy Bitcoin ETF',
        'HODL' => 'VanEck Bitcoin ETF',
        'BRRR' => 'Coinshares Valkyrie Bitcoin Fund',
        'EZBC' => 'Franklin Bitcoin ETF',
        'BTCW' => 'WisdomTree Bitcoin Fund',

        // Grayscale Mini Trust
        'BTC' => 'Grayscale Bitcoin Mini Trust ETF',

        // Bitcoin Futures and Strategy ETFs
        'BITO' => 'ProShares Bitcoin ETF',
        'BITX' => '2x Bitcoin Strategy ETF',
        'BITU' => 'ProShares Ultra Bitcoin ETF',
        'BITI' => 'ProShares Short Bitcoin ETF',
        'SBIT' => 'ProShares UltraShort Bitcoin ETF',

        // Bitcoin Income and Strategy ETFs
        'BTCI' => 'NEOS Bitcoin High Income ETF',
        'SPBC' => 'Simplify US Equity PLUS Bitcoin Strategy ETF',

        // Crypto Industry and Mining ETFs (Bitcoin-related)
        'BITQ' => 'Bitwise Crypto Industry Innovators ETF',
        'WGMI' => 'CoinShares Valkyrie Bitcoin Miners ETF',
        'STCE' => 'Schwab Crypto Thematic ETF',

        // MicroStrategy Leveraged ETFs (Bitcoin proxy)
        'MSTX' => 'Defiance Daily Target 2x Long MSTR ETF',
        'MST' => 'Defiance Leveraged Long Income MSTR ETF',

        // Multi-Crypto ETFs (includes Bitcoin)
        'NCIQ' => 'Hashdex Nasdaq Crypto Index US ETF',

        // Canadian Bitcoin ETFs (for completeness)
        'BTCC' => 'Purpose Bitcoin ETF',
        'EBIT' => 'Evolve Bitcoin ETF'
    ];

    // STEP 1: Get Bitcoin holdings from FMP ETF Holdings API only
    $holdingsData = [];

    // Use FMP ETF Holdings API for ALL Bitcoin ETFs
    foreach ($bitcoinETFs as $ticker => $name) {
        $fmpHoldings = getFMPETFHoldings($ticker);
        if ($fmpHoldings > 0) {
            $holdingsData[$ticker] = [
                'name' => $name,
                'btcHeld' => $fmpHoldings
            ];
        }
    }

    // STEP 2: Get ETF financial data from FMP for all ETFs
    foreach ($bitcoinETFs as $ticker => $name) {
        // Get Bitcoin holdings
        $btcHeld = isset($holdingsData[$ticker]) ? $holdingsData[$ticker]['btcHeld'] : 0;
        $etfName = isset($holdingsData[$ticker]) ? $holdingsData[$ticker]['name'] : $name;

        // Get ETF financial data from FMP (price, shares, market cap)
        $etfFinancialData = getFMPETFData($ticker);

        if (!$etfFinancialData || $etfFinancialData['price'] <= 0) {
            // Skip ETFs without valid price data
            continue;
        }

        $price = $etfFinancialData['price'];
        $sharesOutstanding = $etfFinancialData['sharesOutstanding'];
        $marketCap = $etfFinancialData['marketCap'];

        // Calculate AUM from price and shares if market cap not available
        $aum = $marketCap > 0 ? $marketCap : ($price * $sharesOutstanding);
        $nav = $price; // For ETFs, NAV typically equals market price

        // Calculate BTC per share
        $btcPerShare = 0;
        if ($btcHeld > 0 && $sharesOutstanding > 0) {
            $btcPerShare = $btcHeld / $sharesOutstanding;
        }

        // Calculate premium/discount
        $premium = 0;
        if ($btcPerShare > 0 && $price > 0) {
            $btcPrice = getCurrentBitcoinPrice();
            if ($btcPrice > 0) {
                $theoreticalNAV = $btcPerShare * $btcPrice;
                if ($theoreticalNAV > 0) {
                    $premium = (($price - $theoreticalNAV) / $theoreticalNAV) * 100;
                }
            }
        }

        // Include ALL ETFs with valid price data
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
            'expenseRatio' => 0, // Not available from FMP basic quote
            'volume' => $etfFinancialData['volume'] ?? 0,
            'lastUpdated' => date('Y-m-d H:i:s'),
            'dataSource' => isset($holdingsData[$ticker]) ? 'FMP_ETF_HOLDINGS' : 'FMP_PRICE_ONLY'
        ];
    }

    return $etfData;
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

    // Fetch fresh data using simplified approach
    $etfData = fetchLiveETFData();

    if (empty($etfData)) {
        throw new Exception('No ETF data retrieved from trusted sources');
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
            'source' => 'LIVE_TRUSTED_APIS',
            'cache' => false,
            'totalETFs' => count($etfData),
            'totalBTC' => $totalBTC,
            'totalAUM' => $totalAUM,
            'data_freshness' => 'REAL_TIME',
            'apis_used' => ['FMP_STARTER', 'COINGECKO']
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
?>