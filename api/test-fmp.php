<?php
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

$fmpKey = $_ENV['FMP_API_KEY'] ?? '';

echo json_encode([
    'fmp_key_exists' => !empty($fmpKey),
    'fmp_key_length' => strlen($fmpKey),
    'fmp_key_preview' => substr($fmpKey, 0, 8) . '...',
    'test_url' => "https://financialmodelingprep.com/api/v3/etf/profile/IBIT?apikey=" . substr($fmpKey, 0, 8) . "..."
]);

// Test actual API call
if (!empty($fmpKey)) {
    $testUrl = "https://financialmodelingprep.com/api/v3/etf/profile/IBIT?apikey={$fmpKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BitcoinBagger/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "\n\nAPI Test Results:\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Error: " . ($error ?: 'None') . "\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}
?>
