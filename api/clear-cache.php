<?php
header('Content-Type: application/json');

// Clear all cache files to force fresh API calls
$cacheDir = __DIR__ . '/cache/';
$cleared = 0;
$errors = [];

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*.json');
    foreach ($files as $file) {
        if (unlink($file)) {
            $cleared++;
        } else {
            $errors[] = basename($file);
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Cache cleared successfully',
    'files_cleared' => $cleared,
    'errors' => $errors,
    'timestamp' => date('Y-m-d H:i:s'),
    'next_steps' => [
        'Visit /api/treasuries.php to rebuild company cache',
        'Visit /api/etf-holdings.php to rebuild ETF cache',
        'Visit /api/btc-price.php to rebuild price cache'
    ]
]);
?>
