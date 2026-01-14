<?php

// Test API Daily Stock - Detailed Debug
$warehouse = 'WHRM02';

echo "=== Testing $warehouse ===\n\n";

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
    exit(1);
}

$data = json_decode($response, true);

if (!$data || !isset($data['warehouses'][0]['data'])) {
    echo "Error: Invalid response format\n";
    var_dump($data);
    exit(1);
}

echo "Meta Info:\n";
echo "- Total Records: " . $data['meta']['total_records'] . "\n";
echo "- Period: " . $data['meta']['period'] . "\n\n";

$warehouseData = $data['warehouses'][0]['data'];

// Show data for Jan 12-15
echo "Daily Stock Data:\n";
echo str_repeat("-", 100) . "\n";
printf("%-12s | %12s | %12s | %12s | %12s\n", "Date", "Onhand", "Receipt", "Issue", "Adjustment");
echo str_repeat("-", 100) . "\n";

foreach ([11, 12, 13, 14] as $index) {
    if (isset($warehouseData[$index])) {
        $day = $warehouseData[$index];
        printf(
            "%-12s | %12d | %12d | %12d | %12d\n",
            $day['period'],
            $day['onhand'],
            $day['receipt'],
            $day['issue'],
            $day['adjustment']
        );
    }
}

echo str_repeat("-", 100) . "\n";
