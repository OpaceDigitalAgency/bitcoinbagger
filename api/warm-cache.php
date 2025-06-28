<?php
/**
 * Cache Warming Script for BitcoinBagger APIs
 * Run this script to pre-populate cache for faster user experience
 */

echo "BitcoinBagger Cache Warming Script\n";
echo "==================================\n\n";

// Warm up treasuries cache
echo "1. Warming treasuries cache...\n";
$start = microtime(true);
$response = file_get_contents('https://bitcoinbagger.com/api/treasuries.php');
$end = microtime(true);
$data = json_decode($response, true);

if ($data && isset($data['data'])) {
    $count = count($data['data']);
    $time = round($end - $start, 2);
    $cached = $data['meta']['cache'] ? 'YES' : 'NO';
    echo "   ✓ Loaded {$count} companies in {$time}s (Cached: {$cached})\n\n";
} else {
    echo "   ✗ Failed to load treasuries data\n\n";
}

// Warm up ETF cache
echo "2. Warming ETF cache...\n";
$start = microtime(true);
$response = file_get_contents('https://bitcoinbagger.com/api/etf-holdings.php');
$end = microtime(true);
$data = json_decode($response, true);

if ($data && isset($data['data'])) {
    $count = count($data['data']);
    $time = round($end - $start, 2);
    $cached = $data['meta']['cache'] ? 'YES' : 'NO';
    echo "   ✓ Loaded {$count} ETFs in {$time}s (Cached: {$cached})\n\n";
} else {
    echo "   ✗ Failed to load ETF data\n\n";
}

// Test Bitcoin price (should be fast)
echo "3. Testing Bitcoin price API...\n";
$start = microtime(true);
$response = file_get_contents('https://bitcoinbagger.com/api/btc-price.php');
$end = microtime(true);
$data = json_decode($response, true);

if ($data && isset($data['data']['price'])) {
    $price = number_format($data['data']['price']);
    $time = round($end - $start, 2);
    echo "   ✓ Bitcoin price: ${price} (loaded in {$time}s)\n\n";
} else {
    echo "   ✗ Failed to load Bitcoin price\n\n";
}

echo "Cache warming complete!\n";
echo "Your site should now load much faster for the next 24 hours.\n";
?>
