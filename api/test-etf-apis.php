<?php
header('Content-Type: application/json');

// Test the new ETF data sources
$results = [];

// Test 1: BitcoinETFData.com bulk endpoint
echo "Testing BitcoinETFData.com...\n";
try {
    $url = "https://btcetfdata.com/v1/current.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['bitcoinetfdata'] = [
        'url' => $url,
        'http_code' => $httpCode,
        'success' => $httpCode === 200,
        'data_preview' => $httpCode === 200 ? substr($response, 0, 200) : 'Failed',
        'error' => $httpCode !== 200 ? "HTTP $httpCode" : null
    ];
} catch (Exception $e) {
    $results['bitcoinetfdata'] = ['error' => $e->getMessage()];
}

// Test 2: BitcoinETFData.com individual ETF
echo "Testing BitcoinETFData.com IBIT...\n";
try {
    $url = "https://btcetfdata.com/v1/IBIT.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['bitcoinetfdata_ibit'] = [
        'url' => $url,
        'http_code' => $httpCode,
        'success' => $httpCode === 200,
        'data_preview' => $httpCode === 200 ? substr($response, 0, 200) : 'Failed',
        'error' => $httpCode !== 200 ? "HTTP $httpCode" : null
    ];
} catch (Exception $e) {
    $results['bitcoinetfdata_ibit'] = ['error' => $e->getMessage()];
}

// Test 3: Yahoo Finance
echo "Testing Yahoo Finance...\n";
try {
    $url = "https://query2.finance.yahoo.com/v10/finance/quoteSummary/IBIT?modules=topHoldings,fundProfile";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BitcoinBagger/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['yahoo_finance'] = [
        'url' => $url,
        'http_code' => $httpCode,
        'success' => $httpCode === 200,
        'data_preview' => $httpCode === 200 ? substr($response, 0, 200) : 'Failed',
        'error' => $httpCode !== 200 ? "HTTP $httpCode" : null
    ];
} catch (Exception $e) {
    $results['yahoo_finance'] = ['error' => $e->getMessage()];
}

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'test_results' => $results,
    'summary' => [
        'bitcoinetfdata_working' => $results['bitcoinetfdata']['success'] ?? false,
        'bitcoinetfdata_ibit_working' => $results['bitcoinetfdata_ibit']['success'] ?? false,
        'yahoo_working' => $results['yahoo_finance']['success'] ?? false,
        'recommendation' => 'If BitcoinETFData.com is working, ETF data should populate properly'
    ]
], JSON_PRETTY_PRINT);
?>
