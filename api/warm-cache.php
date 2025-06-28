<?php
/**
 * Enhanced Cache Warming Script for BitcoinBagger APIs
 * Run this script to pre-populate cache for faster user experience
 * Can be run via CLI or web browser
 */

// Detect if running via web or CLI
$isWeb = isset($_SERVER['HTTP_HOST']);
if ($isWeb) {
    header('Content-Type: text/plain');
}

function output($message) {
    global $isWeb;
    echo $message;
    if ($isWeb) {
        echo "<br>";
        flush();
    }
}

output("BitcoinBagger Enhanced Cache Warming Script\n");
output("=============================================\n\n");

// Determine base URL
if ($isWeb) {
    $baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
} else {
    $baseUrl = 'https://bitcoinbagger.com/api'; // Default for CLI
}

function warmEndpoint($url, $name, $timeout = 30) {
    output("Warming {$name}...\n");
    $start = microtime(true);

    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'user_agent' => 'BitcoinBagger-CacheWarmer/1.0'
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    $end = microtime(true);
    $time = round($end - $start, 2);

    if ($response === false) {
        output("   ✗ Failed to load {$name}\n\n");
        return false;
    }

    $data = json_decode($response, true);
    if (!$data) {
        output("   ✗ Invalid response from {$name}\n\n");
        return false;
    }

    if (isset($data['data'])) {
        $count = is_array($data['data']) ? count($data['data']) : 0;
        $cached = $data['meta']['cache'] ?? false ? 'YES' : 'NO';
        $source = $data['meta']['source'] ?? 'Unknown';
        output("   ✓ Loaded {$count} items in {$time}s (Cached: {$cached}, Source: {$source})\n\n");
    } else {
        output("   ✓ Loaded in {$time}s\n\n");
    }

    return true;
}

// Warm up all endpoints
$results = [];
$results['treasuries'] = warmEndpoint($baseUrl . '/treasuries.php', 'treasuries cache', 60);
$results['etf_holdings'] = warmEndpoint($baseUrl . '/etf-holdings.php', 'ETF cache', 30);
$results['btc_price'] = warmEndpoint($baseUrl . '/btc-price.php', 'Bitcoin price', 10);

// Summary
$successful = array_sum($results);
$total = count($results);
output("Cache warming complete!\n");
output("Results: {$successful}/{$total} endpoints warmed successfully\n");

if ($successful === $total) {
    output("✓ All caches warmed - your site should load much faster!\n");
} else {
    output("⚠ Some endpoints failed - check logs for details\n");
}

output("\nNext recommended run: " . date('Y-m-d H:i:s', time() + 3600) . " (1 hour)\n");
?>
