<?php

// Compare: index() vs getChartData()

echo "=== 1. Using /charts (with pagination) ===\n";
$url1 = 'http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01';
$ch1 = curl_init($url1);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
$response1 = curl_exec($ch1);
curl_close($ch1);
$data1 = json_decode($response1, true);

echo "Total items returned: " . count($data1['data']) . "\n";
echo "Pagination info: " . json_encode($data1['pagination']) . "\n\n";

echo "=== 2. Using /charts/data (no pagination) ===\n";
$url2 = 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($ch2);
curl_close($ch2);
$data2 = json_decode($response2, true);

echo "Total items returned: " . count($data2['data']) . "\n";
echo "Has pagination: " . (isset($data2['pagination']) ? 'Yes' : 'No') . "\n\n";

echo "=== 3. Using /charts with per_page=31 ===\n";
$url3 = 'http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=31';
$ch3 = curl_init($url3);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$response3 = curl_exec($ch3);
curl_close($ch3);
$data3 = json_decode($response3, true);

echo "Total items returned: " . count($data3['data']) . "\n";
echo "Pagination info: " . json_encode($data3['pagination']) . "\n\n";

echo "=== Summary ===\n";
echo "Option 1 (/charts): " . count($data1['data']) . " items (paginated)\n";
echo "Option 2 (/charts/data): " . count($data2['data']) . " items (all data)\n";
echo "Option 3 (/charts?per_page=31): " . count($data3['data']) . " items (all in one page)\n";
