<?php
header('Content-Type: application/json');

// Test BitcoinETFData.com parsing
echo "Testing BitcoinETFData.com bulk endpoint...\n\n";

try {
    $url = "https://btcetfdata.com/v1/current.json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        echo "Raw response preview:\n";
        echo substr($response, 0, 500) . "\n\n";
        
        echo "Parsed data structure:\n";
        if (isset($data['data'])) {
            echo "Found 'data' key with " . count($data['data']) . " ETFs\n";
            
            foreach ($data['data'] as $ticker => $etfInfo) {
                if (isset($etfInfo['holdings']) && $etfInfo['holdings'] > 0) {
                    echo "- $ticker: " . number_format($etfInfo['holdings']) . " BTC\n";
                }
            }
        } else {
            echo "No 'data' key found. Keys: " . implode(', ', array_keys($data)) . "\n";
        }
        
    } else {
        echo "HTTP Error: $httpCode\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
