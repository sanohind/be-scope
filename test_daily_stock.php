<?php

// Test API Daily Stock
$warehouses = ['WHRM02', 'WHFG01'];

foreach ($warehouses as $warehouse) {
    echo "\n=== Testing $warehouse ===\n";
    
    $url = "http://127.0.0.1:8000/api/stock/daily?warehouse=$warehouse&date_from=2026-01-01&date_to=2026-01-31&period=daily";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Error: HTTP $httpCode\n";
        echo "Response: $response\n";
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['warehouses'][0]['data'])) {
        echo "Error: Invalid response format\n";
        continue;
    }
    
    $warehouseData = $data['warehouses'][0]['data'];
    
    // Show data for Jan 13, 14, 15
    foreach ([12, 13, 14] as $index) {
        if (isset($warehouseData[$index])) {
            $day = $warehouseData[$index];
            echo sprintf(
                "Date: %s | Onhand: %d | Receipt: %d | Issue: %d | Adjustment: %d\n",
                $day['period'],
                $day['onhand'],
                $day['receipt'],
                $day['issue'],
                $day['adjustment']
            );
        }
    }
}

echo "\n=== Test Complete ===\n";
