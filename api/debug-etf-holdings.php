<?php
// Debug script to test ETF holdings lookup for specific ETFs

header('Content-Type: application/json');

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

function getApiKey($provider) {
    $API_KEYS = [
        'FMP' => $_ENV['FMP_API_KEY'] ?? '',
        'ALPHA_VANTAGE' => $_ENV['ALPHA_VANTAGE_API_KEY'] ?? '',
        'TWELVEDATA' => $_ENV['TWELVEDATA_API_KEY'] ?? '',
        'FINNHUB' => $_ENV['FINNHUB_API_KEY'] ?? ''
    ];
    return $API_KEYS[$provider] ?? '';
}

function getCurrentBitcoinPrice() {
    try {
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd';
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        if (isset($data['bitcoin']['usd'])) {
            return floatval($data['bitcoin']['usd']);
        }
    } catch (Exception $e) {
        return 0;
    }
    return 0;
}

function debugETFHoldings($ticker) {
    $debug = [];
    
    // Test 1: Yahoo Finance fund holdings
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
            $debug['yahoo_finance'] = [
                'success' => true,
                'has_top_holdings' => isset($data['quoteSummary']['result'][0]['topHoldings']['holdings']),
                'has_asset_profile' => isset($data['quoteSummary']['result'][0]['assetProfile']),
                'top_holdings_count' => isset($data['quoteSummary']['result'][0]['topHoldings']['holdings']) ? count($data['quoteSummary']['result'][0]['topHoldings']['holdings']) : 0,
                'raw_data' => $data
            ];
        } else {
            $debug['yahoo_finance'] = ['success' => false, 'error' => 'No response'];
        }
    } catch (Exception $e) {
        $debug['yahoo_finance'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 2: FMP ETF holdings
    try {
        $fmpKey = getApiKey('FMP');
        if ($fmpKey && $fmpKey !== 'REDACTED_API_KEY') {
            $url = "https://financialmodelingprep.com/api/v3/etf-holder/{$ticker}?apikey={$fmpKey}";
            $response = file_get_contents($url);
            if ($response !== false) {
                $data = json_decode($response, true);
                $debug['fmp_etf_holder'] = [
                    'success' => true,
                    'data_count' => is_array($data) ? count($data) : 0,
                    'raw_data' => $data
                ];
            } else {
                $debug['fmp_etf_holder'] = ['success' => false, 'error' => 'No response'];
            }
        } else {
            $debug['fmp_etf_holder'] = ['success' => false, 'error' => 'No API key'];
        }
    } catch (Exception $e) {
        $debug['fmp_etf_holder'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Test 3: Yahoo Finance basic quote
    try {
        $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$ticker}&fields=regularMarketPrice,sharesOutstanding,marketCap";
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            $debug['yahoo_quote'] = [
                'success' => true,
                'raw_data' => $data
            ];
            
            if (isset($data['quoteResponse']['result'][0])) {
                $quote = $data['quoteResponse']['result'][0];
                $price = floatval($quote['regularMarketPrice'] ?? 0);
                $shares = floatval($quote['sharesOutstanding'] ?? 0);
                $marketCap = floatval($quote['marketCap'] ?? 0);
                
                $debug['calculated_aum'] = [
                    'price' => $price,
                    'shares_outstanding' => $shares,
                    'market_cap' => $marketCap,
                    'calculated_aum' => $price * $shares
                ];
                
                // Calculate potential Bitcoin holdings
                if ($price > 0 && $shares > 0) {
                    $aum = $price * $shares;
                    $btcPrice = getCurrentBitcoinPrice();
                    if ($btcPrice > 0) {
                        $estimatedBtcHoldings = ($aum * 0.95) / $btcPrice; // Assume 95% in Bitcoin
                        $debug['estimated_btc_holdings'] = [
                            'aum' => $aum,
                            'btc_price' => $btcPrice,
                            'estimated_holdings' => $estimatedBtcHoldings
                        ];
                    }
                }
            }
        } else {
            $debug['yahoo_quote'] = ['success' => false, 'error' => 'No response'];
        }
    } catch (Exception $e) {
        $debug['yahoo_quote'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    return $debug;
}

// Test specific ETFs
$testETFs = ['BTCO', 'BRRR', 'EZBC'];
$results = [];

foreach ($testETFs as $ticker) {
    $results[$ticker] = debugETFHoldings($ticker);
}

echo json_encode([
    'debug_results' => $results,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>
