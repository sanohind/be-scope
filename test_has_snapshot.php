<?php

// Test API Daily Stock - Check has_snapshot flag
$warehouse = 'WHRM01';

echo "=== Testing $warehouse with has_snapshot flag ===\n\n";

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
    exit(1);
}

$warehouseData = $data['warehouses'][0]['data'];

// Show data for Jan 13-16
echo "Daily Stock Data (Jan 13-16):\n";
echo str_repeat("-", 80) . "\n";
printf("%-12s | %12s | %10s | %10s | %10s\n", "Date", "Onhand", "Receipt", "Issue", "Adjustment");
echo str_repeat("-", 80) . "\n";

foreach ([12, 13, 14, 15] as $index) {
    if (isset($warehouseData[$index])) {
        $day = $warehouseData[$index];
        printf(
            "%-12s | %12d | %10d | %10d | %10d\n",
            $day['period'],
            $day['onhand'],
            $day['receipt'],
            $day['issue'],
            $day['adjustment']
        );
    }
}

echo str_repeat("-", 80) . "\n";
echo "\nExpected for WHRM01:\n";
echo "- Jan 13: onhand = 3,138,998 (has snapshot)\n";
echo "- Jan 14: onhand = 3,138,998 (no snapshot, should use Jan 13 value)\n";
echo "- Jan 15: onhand = 3,138,998 (no snapshot, should use Jan 14 value)\n";
