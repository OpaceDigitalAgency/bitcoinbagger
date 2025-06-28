<?php
// Cache status endpoint - shows current cache state
header('Content-Type: application/json');

$CACHE_DIR = __DIR__ . '/cache/';
if (!file_exists($CACHE_DIR)) {
    mkdir($CACHE_DIR, 0755, true);
}

function getCacheInfo($key) {
    global $CACHE_DIR;
    $file = $CACHE_DIR . md5($key) . '.json';
    
    if (!file_exists($file)) {
        return [
            'exists' => false,
            'age' => null,
            'size' => 0,
            'status' => 'MISSING'
        ];
    }
    
    $age = time() - filemtime($file);
    $size = filesize($file);
    $data = json_decode(file_get_contents($file), true);
    
    // Determine freshness status
    $status = 'FRESH';
    if ($age > 86400) { // 24 hours
        $status = 'STALE';
    } elseif ($age > 3600) { // 1 hour
        $status = 'AGING';
    }
    
    return [
        'exists' => true,
        'age' => $age,
        'age_human' => formatAge($age),
        'size' => $size,
        'size_human' => formatBytes($size),
        'status' => $status,
        'last_modified' => date('Y-m-d H:i:s', filemtime($file)),
        'data_count' => is_array($data) ? count($data) : (isset($data['data']) ? count($data['data']) : 0)
    ];
}

function formatAge($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return round($seconds / 60) . 'm';
    if ($seconds < 86400) return round($seconds / 3600) . 'h';
    return round($seconds / 86400) . 'd';
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . 'B';
    if ($bytes < 1048576) return round($bytes / 1024) . 'KB';
    return round($bytes / 1048576, 1) . 'MB';
}

// Check cache status for all endpoints
$cacheStatus = [
    'btc_price_coingecko_pro' => getCacheInfo('btc_price_coingecko_pro'),
    'btc_price_coingecko_free' => getCacheInfo('btc_price_coingecko_free'),
    'btc_price_fmp' => getCacheInfo('btc_price_fmp'),
    'treasury_companies_data' => getCacheInfo('treasury_companies_data'),
    'etf_holdings_data' => getCacheInfo('etf_holdings_data')
];

// Calculate overall cache health
$totalCaches = count($cacheStatus);
$freshCaches = 0;
$existingCaches = 0;

foreach ($cacheStatus as $cache) {
    if ($cache['exists']) {
        $existingCaches++;
        if ($cache['status'] === 'FRESH') {
            $freshCaches++;
        }
    }
}

$healthScore = ($existingCaches / $totalCaches) * 100;
$freshnessScore = $existingCaches > 0 ? ($freshCaches / $existingCaches) * 100 : 0;

echo json_encode([
    'cache_status' => $cacheStatus,
    'summary' => [
        'total_caches' => $totalCaches,
        'existing_caches' => $existingCaches,
        'fresh_caches' => $freshCaches,
        'health_score' => round($healthScore, 1) . '%',
        'freshness_score' => round($freshnessScore, 1) . '%',
        'overall_status' => $healthScore >= 80 ? 'HEALTHY' : ($healthScore >= 50 ? 'DEGRADED' : 'CRITICAL')
    ],
    'recommendations' => [
        'cache_warming_needed' => $freshnessScore < 50,
        'cache_cleanup_needed' => false, // Could implement cache size monitoring
        'next_action' => $healthScore < 50 ? 'Run cache warmer immediately' : 'Normal operation'
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
