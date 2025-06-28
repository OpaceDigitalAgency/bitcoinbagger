<?php
// Comprehensive API testing script
header('Content-Type: application/json');

function testEndpoint($endpoint, $expectedFields = []) {
    $startTime = microtime(true);
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'BitcoinBagger-APITester/1.0'
            ]
        ]);
        
        $response = file_get_contents($endpoint, false, $context);
        $endTime = microtime(true);
        
        if ($response === false) {
            throw new Exception('Failed to fetch endpoint');
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }
        
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        // Validate expected fields
        $missingFields = [];
        foreach ($expectedFields as $field) {
            if (!isset($data[$field])) {
                $missingFields[] = $field;
            }
        }
        
        // Check data quality
        $dataQuality = 'GOOD';
        $warnings = [];
        
        if (isset($data['data']) && is_array($data['data'])) {
            if (empty($data['data'])) {
                $dataQuality = 'POOR';
                $warnings[] = 'Empty data array';
            } else {
                // Check for meaningful data
                $hasRealData = false;
                foreach ($data['data'] as $item) {
                    if (isset($item['btcHeld']) && $item['btcHeld'] > 0) {
                        $hasRealData = true;
                        break;
                    }
                }
                if (!$hasRealData) {
                    $dataQuality = 'POOR';
                    $warnings[] = 'No meaningful Bitcoin holdings data';
                }
            }
        }
        
        return [
            'status' => 'SUCCESS',
            'response_time' => $responseTime . 'ms',
            'data_size' => strlen($response) . ' bytes',
            'data_quality' => $dataQuality,
            'warnings' => $warnings,
            'missing_fields' => $missingFields,
            'cache_hit' => $data['meta']['cache'] ?? false,
            'source' => $data['meta']['source'] ?? 'Unknown',
            'data_count' => isset($data['data']) ? count($data['data']) : 0
        ];
        
    } catch (Exception $e) {
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        return [
            'status' => 'FAILED',
            'error' => $e->getMessage(),
            'response_time' => $responseTime . 'ms'
        ];
    }
}

$baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

$tests = [
    'btc_price' => [
        'endpoint' => $baseUrl . '/btc-price.php',
        'expected_fields' => ['success', 'data', 'meta'],
        'description' => 'Bitcoin price API with fallback sources'
    ],
    'treasuries' => [
        'endpoint' => $baseUrl . '/treasuries.php',
        'expected_fields' => ['success', 'data', 'meta'],
        'description' => 'Company Bitcoin holdings with dynamic discovery'
    ],
    'etf_holdings' => [
        'endpoint' => $baseUrl . '/etf-holdings.php',
        'expected_fields' => ['success', 'data', 'meta'],
        'description' => 'ETF Bitcoin holdings with multiple sources'
    ],
    'cache_status' => [
        'endpoint' => $baseUrl . '/cache-status.php',
        'expected_fields' => ['cache_status', 'summary'],
        'description' => 'Cache health monitoring'
    ]
];

$results = [];
$overallSuccess = true;

foreach ($tests as $testName => $testConfig) {
    echo "Testing {$testName}...\n";
    $result = testEndpoint($testConfig['endpoint'], $testConfig['expected_fields']);
    $result['description'] = $testConfig['description'];
    $results[$testName] = $result;
    
    if ($result['status'] !== 'SUCCESS') {
        $overallSuccess = false;
    }
}

// Calculate summary statistics
$totalTests = count($results);
$passedTests = 0;
$totalResponseTime = 0;

foreach ($results as $result) {
    if ($result['status'] === 'SUCCESS') {
        $passedTests++;
    }
    $totalResponseTime += floatval(str_replace('ms', '', $result['response_time']));
}

$successRate = ($passedTests / $totalTests) * 100;
$avgResponseTime = round($totalResponseTime / $totalTests, 2);

echo json_encode([
    'test_results' => $results,
    'summary' => [
        'overall_status' => $overallSuccess ? 'PASS' : 'FAIL',
        'total_tests' => $totalTests,
        'passed_tests' => $passedTests,
        'failed_tests' => $totalTests - $passedTests,
        'success_rate' => round($successRate, 1) . '%',
        'average_response_time' => $avgResponseTime . 'ms'
    ],
    'recommendations' => [
        'api_health' => $successRate >= 75 ? 'HEALTHY' : ($successRate >= 50 ? 'DEGRADED' : 'CRITICAL'),
        'action_needed' => $successRate < 75 ? 'Investigate failed endpoints' : 'No action needed'
    ],
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>
