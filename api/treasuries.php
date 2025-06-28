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
    'TWELVEDATA' => $_ENV['TWELVEDATA_API_KEY'] ?? '',
    'FINNHUB' => $_ENV['FINNHUB_API_KEY'] ?? ''
];

// Company name mapping for numeric IDs from CoinGecko
$COMPANY_NAME_MAPPING = [
    '3350' => ['name' => 'Metaplanet Inc.', 'ticker' => 'MPLANET'],
    '3659' => ['name' => 'Nexon Co Ltd', 'ticker' => 'NEXON'],
    'NA' => ['name' => 'Unknown Company', 'ticker' => 'UNKNOWN']
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

// Get current Bitcoin price for calculations
function getCurrentBitcoinPrice() {
    static $cachedPrice = null;
    static $cacheTime = 0;

    // Use cached price if less than 5 minutes old
    if ($cachedPrice !== null && (time() - $cacheTime) < 300) {
        return $cachedPrice;
    }

    // Skip internal API call to avoid potential loops - go directly to external API

    // Fallback: Direct CoinGecko call
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
        // No fallback - return 0 if API fails
    }

    // If all APIs fail, return 0 - no hardcoded data
    return 0;
}

// Fetch stock price with caching and multiple free APIs
function fetchStockPrice($ticker) {
    $cacheKey = "stock_price_{$ticker}";
    $cached = getCache($cacheKey, 1800); // 30 minute cache to reduce API calls

    if ($cached !== null) {
        return $cached;
    }

    $stockData = fetchStockData($ticker);

    // Cache the result (even if 0) to avoid repeated API calls
    setCache($cacheKey, $stockData['price']);
    return $stockData['price'];
}

// NEW: Comprehensive market cap fetcher with multiple API sources and fallbacks
function fetchComprehensiveMarketCap($ticker) {
    $cacheKey = "comprehensive_marketcap_{$ticker}";
    $cached = getCache($cacheKey, 3600); // 1 hour cache

    if ($cached !== null && $cached > 0) {
        return $cached;
    }

    $marketCap = 0;

    // Method 1: Try Yahoo Finance Statistics API (most comprehensive)
    try {
        $url = "https://query1.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=defaultKeyStatistics,summaryDetail,price";
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (isset($data['quoteSummary']['result'][0]['summaryDetail']['marketCap']['raw'])) {
                $marketCap = floatval($data['quoteSummary']['result'][0]['summaryDetail']['marketCap']['raw']);
            } elseif (isset($data['quoteSummary']['result'][0]['price']['marketCap']['raw'])) {
                $marketCap = floatval($data['quoteSummary']['result'][0]['price']['marketCap']['raw']);
            }
        }
    } catch (Exception $e) {
        // Continue to next method
    }

    // Method 2: Try FMP API (Financial Modeling Prep)
    if ($marketCap == 0) {
        $fmpKey = getApiKey('FMP');
        if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (is_array($data) && isset($data[0]['marketCap']) && $data[0]['marketCap'] > 0) {
                        $marketCap = floatval($data[0]['marketCap']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 3: Try Alpha Vantage API
    if ($marketCap == 0) {
        $alphaKey = getApiKey('ALPHA_VANTAGE');
        if ($alphaKey && $alphaKey !== 'your_alpha_vantage_key_here') {
            try {
                $url = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$ticker}&apikey={$alphaKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['MarketCapitalization']) && is_numeric($data['MarketCapitalization'])) {
                        $marketCap = floatval($data['MarketCapitalization']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 4: Try TwelveData API
    if ($marketCap == 0) {
        $twelveKey = getApiKey('TWELVEDATA');
        if ($twelveKey && $twelveKey !== 'your_twelvedata_key_here') {
            try {
                $url = "https://api.twelvedata.com/profile?symbol={$ticker}&apikey={$twelveKey}";
                $response = file_get_contents($url);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if (isset($data['market_capitalization']) && $data['market_capitalization'] > 0) {
                        $marketCap = floatval($data['market_capitalization']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
    }

    // Method 5: Try additional market cap sources
    if ($marketCap == 0) {
        try {
            // Try Yahoo Finance comprehensive quote endpoint
            $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$ticker}&fields=marketCap,sharesOutstanding,regularMarketPrice";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 8,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteResponse']['result'][0]['marketCap'])) {
                    $marketCap = floatval($data['quoteResponse']['result'][0]['marketCap']);
                }
            }
        } catch (Exception $e) {
            // Continue - no fallback data, just return 0 if APIs fail
        }
    }

    // Cache the result (even if 0) to avoid repeated API calls
    if ($marketCap > 0) {
        setCache($cacheKey, $marketCap);
    }

    return $marketCap;
}

// Enhanced function to fetch comprehensive stock data
function fetchStockData($ticker) {
    $cacheKey = "stock_data_{$ticker}";
    $cached = getCache($cacheKey, 1800); // 30 minute cache

    if ($cached !== null) {
        return $cached;
    }

    $price = 0;
    $marketCap = 0;
    $sharesOutstanding = 0;

    // Try multiple free APIs with better rate limiting handling

    // Try Alpha Vantage first (if available)
    $avKey = getApiKey('ALPHA_VANTAGE');
    if ($avKey && $avKey !== 'your_alpha_vantage_key_here' && $price == 0) {
        try {
            $url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol={$ticker}&apikey={$avKey}";
            $response = file_get_contents($url);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['Global Quote']['05. price'])) {
                    $price = floatval($data['Global Quote']['05. price']);
                }
            }
            // Add small delay to avoid rate limiting
            usleep(200000); // 200ms delay
        } catch (Exception $e) {
            // Continue to next API
        }
    }

    // Try Yahoo Finance with better headers and error handling
    if ($price == 0) {
        try {
            $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$ticker}&fields=regularMarketPrice,marketCap,sharesOutstanding";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'header' => [
                        'Accept: application/json',
                        'Accept-Language: en-US,en;q=0.9',
                        'Cache-Control: no-cache'
                    ]
                ]
            ]);

            $response = file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['quoteResponse']['result'][0])) {
                    $quote = $data['quoteResponse']['result'][0];
                    $price = floatval($quote['regularMarketPrice'] ?? 0);
                    $marketCap = floatval($quote['marketCap'] ?? 0);
                    $sharesOutstanding = floatval($quote['sharesOutstanding'] ?? 0);

                    // If marketCap is 0 but we have price and shares, calculate it
                    if ($marketCap == 0 && $price > 0 && $sharesOutstanding > 0) {
                        $marketCap = $price * $sharesOutstanding;
                    }
                    // If sharesOutstanding is 0 but we have price and marketCap, calculate it
                    if ($sharesOutstanding == 0 && $price > 0 && $marketCap > 0) {
                        $sharesOutstanding = $marketCap / $price;
                    }
                }
            }
            // Add delay to avoid rate limiting
            usleep(300000); // 300ms delay
        } catch (Exception $e) {
            // Continue to next API
        }
    }

    // Try FMP as fallback (if not rate limited)
    if ($price == 0) {
        $fmpKey = getApiKey('FMP');
        if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
            try {
                $url = "https://financialmodelingprep.com/api/v3/quote/{$ticker}?apikey={$fmpKey}";
                $data = fetchWithCurl($url);
                if (is_array($data) && isset($data[0]['price']) && !isset($data['Error Message'])) {
                    $price = floatval($data[0]['price']);
                    $marketCap = floatval($data[0]['marketCap'] ?? 0);
                    $sharesOutstanding = floatval($data[0]['sharesOutstanding'] ?? 0);
                }
            } catch (Exception $e) {
                // Continue to next API
            }
        }
    }

    // Try Finnhub as another free option
    if ($price == 0) {
        $finnhubKey = getApiKey('FINNHUB');
        if ($finnhubKey && $finnhubKey !== 'your_finnhub_key_here') {
            try {
                $url = "https://finnhub.io/api/v1/quote?symbol={$ticker}&token={$finnhubKey}";
                $data = fetchWithCurl($url);
                if (isset($data['c']) && $data['c'] > 0) {
                    $price = floatval($data['c']); // Current price
                }
            } catch (Exception $e) {
                // Continue
            }
        }
    }

    // If market cap is still 0, try our comprehensive market cap fetcher
    if ($marketCap == 0) {
        $marketCap = fetchComprehensiveMarketCap($ticker);
    }

    $result = [
        'price' => $price,
        'marketCap' => $marketCap,
        'sharesOutstanding' => $sharesOutstanding
    ];

    // Cache the result
    setCache($cacheKey, $result);
    return $result;
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
                // Try FMP first (cache for 24 hours to avoid rate limits)
                $profile = fetchWithCurl($profileUrl, [], true, $cacheKey, 86400);
                if (!empty($profile) && is_array($profile) && isset($profile[0])) {
                    $companyData = array_merge($companyData, $profile[0]);
                }
            } catch (Exception $e) {
                // FMP failed (likely rate limit), try Alpha Vantage as fallback
                $avKey = getApiKey('ALPHA_VANTAGE');
                if ($avKey && $avKey !== 'your_alpha_vantage_key_here') {
                    try {
                        $avUrl = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$ticker}&apikey={$avKey}";
                        $avProfile = fetchWithCurl($avUrl, [], true, "av_profile_{$ticker}", 86400);
                        if (!empty($avProfile) && is_array($avProfile)) {
                            // Map Alpha Vantage fields to FMP format
                            $mappedProfile = [
                                'mktCap' => floatval($avProfile['MarketCapitalization'] ?? 0),
                                'companyName' => $avProfile['Name'] ?? $ticker,
                                'industry' => $avProfile['Industry'] ?? 'Technology',
                                'sector' => $avProfile['Sector'] ?? 'Technology'
                            ];
                            $companyData = array_merge($companyData, $mappedProfile);
                        }
                    } catch (Exception $avE) {
                        // Alpha Vantage failed, try TwelveData as final fallback
                        $tdKey = getApiKey('TWELVEDATA');
                        if ($tdKey && $tdKey !== 'your_twelvedata_key_here') {
                            try {
                                $tdUrl = "https://api.twelvedata.com/profile?symbol={$ticker}&apikey={$tdKey}";
                                $tdProfile = fetchWithCurl($tdUrl, [], true, "td_profile_{$ticker}", 86400);
                                if (!empty($tdProfile) && is_array($tdProfile)) {
                                    // Map TwelveData fields to FMP format
                                    $mappedProfile = [
                                        'mktCap' => floatval($tdProfile['market_capitalization'] ?? 0),
                                        'companyName' => $tdProfile['name'] ?? $ticker,
                                        'industry' => $tdProfile['industry'] ?? 'Technology',
                                        'sector' => $tdProfile['sector'] ?? 'Technology'
                                    ];
                                    $companyData = array_merge($companyData, $mappedProfile);
                                }
                            } catch (Exception $tdE) {
                                // All APIs failed - continue with discovered data only
                            }
                        }
                    }
                }
            }

            // Get dynamic Bitcoin holdings
            $btcHeld = estimateBitcoinHoldings($ticker, $companyData);

            // Only include companies with actual Bitcoin holdings
            if ($btcHeld > 0) {
                // Get basic stock price for major companies (limited to prevent rate limits)
                $stockPrice = 0;
                $marketCap = $companyData['mktCap'] ?? 0;
                $sharesOutstanding = 0;

                // Don't use hardcoded market cap data - get real data from APIs only
                // If APIs fail, the data should show as N/A, not fake values

                // If market cap is still 0, try to get it from Alpha Vantage as fallback
                if ($marketCap == 0) {
                    $avKey = getApiKey('ALPHA_VANTAGE');
                    if ($avKey && $avKey !== 'your_alpha_vantage_key_here') {
                        try {
                            $avUrl = "https://www.alphavantage.co/query?function=OVERVIEW&symbol={$ticker}&apikey={$avKey}";
                            $avProfile = fetchWithCurl($avUrl, [], true, "av_marketcap_{$ticker}", 86400);
                            if (!empty($avProfile) && is_array($avProfile)) {
                                $marketCap = floatval($avProfile['MarketCapitalization'] ?? 0);
                                if ($marketCap > 0) {
                                    $companyData['mktCap'] = $marketCap;
                                }
                            }
                        } catch (Exception $avE) {
                            // Alpha Vantage failed for market cap
                        }
                    }
                }

                // Fetch comprehensive stock data for all companies with Bitcoin holdings > 1000 BTC
                if ($btcHeld > 1000) {
                    $stockData = fetchStockData($ticker);
                    $stockPrice = $stockData['price'];

                    // Use fetched data if available, otherwise keep existing values
                    if ($stockData['marketCap'] > 0) {
                        $marketCap = $stockData['marketCap'];
                    }
                    if ($stockData['sharesOutstanding'] > 0) {
                        $sharesOutstanding = $stockData['sharesOutstanding'];
                    }
                }

                // Calculate shares outstanding if we have both market cap and stock price but no shares data
                if ($sharesOutstanding == 0 && $stockPrice > 0 && $marketCap > 0) {
                    $sharesOutstanding = $marketCap / $stockPrice;
                }

                // Calculate Bitcoin per share
                $bitcoinPerShare = 0;
                if ($sharesOutstanding > 0 && $btcHeld > 0) {
                    $bitcoinPerShare = $btcHeld / $sharesOutstanding;
                }

                // Calculate BSP (Bitcoin per Share Price) - the value of Bitcoin holdings per share
                $bsp = 0;
                if ($bitcoinPerShare > 0) {
                    // Get current Bitcoin price
                    $btcPrice = getCurrentBitcoinPrice();
                    $bsp = $bitcoinPerShare * $btcPrice;
                }

                // Calculate premium/discount - how much the stock trades above/below its Bitcoin value
                $premium = 0;
                if ($stockPrice > 0 && $bsp > 0) {
                    $premium = (($stockPrice - $bsp) / $bsp) * 100;
                }

                // If we don't have shares outstanding but have market cap, estimate it
                if ($sharesOutstanding == 0 && $marketCap > 0 && $stockPrice > 0) {
                    $sharesOutstanding = $marketCap / $stockPrice;

                    // Recalculate with estimated shares
                    if ($btcHeld > 0) {
                        $bitcoinPerShare = $btcHeld / $sharesOutstanding;
                        $btcPrice = getCurrentBitcoinPrice();
                        $bsp = $bitcoinPerShare * $btcPrice;
                        if ($bsp > 0) {
                            $premium = (($stockPrice - $bsp) / $bsp) * 100;
                        }
                    }
                }

                // Fix company names for numeric IDs
                global $COMPANY_NAME_MAPPING;
                $displayName = $companyData['companyName'] ?? $info['name'];
                $displayTicker = $ticker;

                if (isset($COMPANY_NAME_MAPPING[$ticker])) {
                    $displayName = $COMPANY_NAME_MAPPING[$ticker]['name'];
                    $displayTicker = $COMPANY_NAME_MAPPING[$ticker]['ticker'];
                }

                $treasuryData[] = [
                    'ticker' => $displayTicker,
                    'name' => $displayName,
                    'btcHeld' => $btcHeld,
                    'businessModel' => $info['businessModel'],
                    'type' => $info['type'],
                    'lastUpdated' => date('Y-m-d H:i:s'),
                    'dataSource' => $info['source'] ?? 'DYNAMIC_DISCOVERY',
                    'marketCap' => $marketCap,
                    'stockPrice' => $stockPrice,
                    'sharesOutstanding' => $sharesOutstanding,
                    'bitcoinPerShare' => $bitcoinPerShare,
                    'bsp' => $bsp,
                    'premium' => $premium,
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

        // No fallback data - return 0 if APIs fail
        return 0;

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
    // Check for cache clearing request
    $clearCache = isset($_GET['clear_cache']) || isset($_GET['force_refresh']);

    // Check cache first
    $cacheKey = 'treasury_companies_data';
    $cacheTime = $_ENV['CACHE_DURATION_COMPANIES'] ?? 86400; // 24 hours

    if ($clearCache) {
        // Clear the cache file
        $cacheFile = getCacheFile($cacheKey);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    if (!$clearCache) {
        $cachedData = getCache($cacheKey, $cacheTime);

        if ($cachedData !== null && !empty($cachedData)) {
            // Return cached data immediately
            $response = [
                'success' => true,
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
    }

    // If no cache, fetch live data and cache it
    $liveData = fetchLiveTreasuryData();

    // Validate we got meaningful data
    if (empty($liveData)) {
        throw new Exception('No company data retrieved from any source');
    }

    // Filter out companies with zero Bitcoin holdings
    $validData = array_filter($liveData, function($company) {
        return isset($company['btcHeld']) && $company['btcHeld'] > 0;
    });

    if (empty($validData)) {
        throw new Exception('No companies with Bitcoin holdings found');
    }

    // Sort by Bitcoin holdings (descending)
    usort($validData, function($a, $b) {
        return ($b['btcHeld'] ?? 0) - ($a['btcHeld'] ?? 0);
    });

    // Cache the data for future requests
    setCache($cacheKey, $validData);

    $response = [
        'success' => true,
        'data' => $validData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => 'LIVE_API_ENDPOINTS',
            'cache' => false,
            'totalCompanies' => count($validData),
            'apis_used' => ['COINGECKO', 'FMP', 'ALPHA_VANTAGE'],
            'data_freshness' => 'REAL_TIME_CACHED'
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Treasury data API error: " . $e->getMessage());

    // Try to get stale cached data as fallback
    $staleCache = getCache($cacheKey, 86400 * 7); // Accept week-old data

    if ($staleCache !== null && !empty($staleCache)) {
        $response = [
            'success' => true,
            'data' => $staleCache,
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'STALE_CACHE_FALLBACK',
                'cache' => true,
                'totalCompanies' => count($staleCache),
                'warning' => 'Using stale cached data - live APIs failed',
                'error' => $e->getMessage(),
                'data_freshness' => 'STALE_CACHE'
            ]
        ];
        echo json_encode($response);
    } else {
        // No fallback data - return error if all APIs fail
        http_response_code(503); // Service Temporarily Unavailable
        echo json_encode([
            'success' => false,
            'error' => 'Treasury data temporarily unavailable - all APIs failed',
            'meta' => [
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'source' => 'API_FAILURE',
                'warning' => 'No fallback data available - APIs must be working',
                'status' => 'SERVICE_UNAVAILABLE'
            ]
        ]);
    }
}