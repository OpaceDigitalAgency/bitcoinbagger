<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

function fetchLiveETFData() {
    $etfData = [];
    
    // SIMPLE DIRECT APPROACH: Get real ETF data from BitcoinETFData.com
    try {
        $url = "https://btcetfdata.com/v1/current.json";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (!empty($data) && isset($data['data'])) {
            foreach ($data['data'] as $ticker => $etfInfo) {
                if (isset($etfInfo['holdings']) && $etfInfo['holdings'] > 0) {
                    $etfData[] = [
                        'ticker' => $ticker,
                        'name' => $etfInfo['name'] ?? $ticker . ' Bitcoin ETF',
                        'btcHeld' => floatval($etfInfo['holdings']),
                        'sharesOutstanding' => 0,
                        'lastUpdated' => date('Y-m-d H:i:s'),
                        'dataSource' => 'BITCOINETFDATA_COM_LIVE',
                        'nav' => 0,
                        'aum' => 0,
                        'expenseRatio' => 0
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // If BitcoinETFData.com fails, use fallback list with 0 BTC
        $fallbackETFs = [
            'IBIT' => 'iShares Bitcoin Trust',
            'FBTC' => 'Fidelity Wise Origin Bitcoin Fund', 
            'GBTC' => 'Grayscale Bitcoin Trust',
            'ARKB' => 'ARK 21Shares Bitcoin ETF',
            'BITB' => 'Bitwise Bitcoin ETF'
        ];
        
        foreach ($fallbackETFs as $ticker => $name) {
            $etfData[] = [
                'ticker' => $ticker,
                'name' => $name,
                'btcHeld' => 0,
                'sharesOutstanding' => 0,
                'lastUpdated' => date('Y-m-d H:i:s'),
                'dataSource' => 'FALLBACK_LIST',
                'error' => 'BITCOINETFDATA_FAILED'
            ];
        }
    }
    
    return $etfData;
}

try {
    $etfData = fetchLiveETFData();
    
    // Calculate totals
    $totalBTC = 0;
    $totalAUM = 0;
    
    foreach ($etfData as $etf) {
        $totalBTC += $etf['btcHeld'];
        $totalAUM += $etf['aum'] ?? 0;
    }
    
    echo json_encode([
        'data' => $etfData,
        'meta' => [
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'source' => 'BITCOINETFDATA_COM_DIRECT',
            'cache' => false,
            'totalETFs' => count($etfData),
            'totalBTC' => $totalBTC,
            'totalAUM' => $totalAUM,
            'data_freshness' => 'REAL_TIME'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch ETF data',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'source' => 'LIVE_ETF_API_ERROR'
    ]);
}
?>
