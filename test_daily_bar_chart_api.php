&lt;?php

/**
 * Test script for getDailyBarChartData API
 * 
 * Run this file to test the new daily bar chart API endpoint
 * Usage: php test_daily_bar_chart_api.php
 */

echo "=== Testing getDailyBarChartData API ===\n\n";

$baseUrl = "http://localhost:8000/api/dashboard/sales-analytics";

// Test cases
$testCases = [
    [
        'name' => 'Test 1: Default (Current Month)',
        'url' => "$baseUrl/daily-bar-chart",
        'description' => 'Should return current month data'
    ],
    [
        'name' => 'Test 2: Specific Date Range',
        'url' => "$baseUrl/daily-bar-chart?date_from=2024-01-01&date_to=2024-01-15",
        'description' => 'Should return data from Jan 1 to Jan 15, 2024'
    ],
    [
        'name' => 'Test 3: Single Day',
        'url' => "$baseUrl/daily-bar-chart?date_from=2024-01-05&date_to=2024-01-05",
        'description' => 'Should return data for Jan 5, 2024 only'
    ],
    [
        'name' => 'Test 4: Only date_from',
        'url' => "$baseUrl/daily-bar-chart?date_from=2024-01-10",
        'description' => 'Should return data for Jan 10, 2024 (date_to = date_from)'
    ],
];

foreach ($testCases as $index => $test) {
    echo "--- {$test['name']} ---\n";
    echo "Description: {$test['description']}\n";
    echo "URL: {$test['url']}\n\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    
    if ($response) {
        $data = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            
            if (isset($data['count'])) {
                echo "Count: {$data['count']}\n";
            }
            
            if (isset($data['date_from']) && isset($data['date_to'])) {
                echo "Date Range: {$data['date_from']} to {$data['date_to']}\n";
            }
            
            if (isset($data['data']) && is_array($data['data'])) {
                $sampleCount = min(3, count($data['data']));
                echo "Sample Data (first $sampleCount items):\n";
                echo json_encode(array_slice($data['data'], 0, $sampleCount), JSON_PRETTY_PRINT) . "\n";
            }
            
            if (isset($data['error'])) {
                echo "Error: {$data['error']}\n";
            }
        } else {
            echo "JSON Decode Error: " . json_last_error_msg() . "\n";
            echo "Raw Response: " . substr($response, 0, 500) . "\n";
        }
    } else {
        echo "No response received\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

echo "Testing completed!\n";
