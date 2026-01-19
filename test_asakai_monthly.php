<?php

// Test Asakai Chart Data API - Monthly
$url = 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=monthly&date_from=2026-01-01';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$data = json_decode($response, true);
echo "Total months: " . count($data['data']) . "\n";
echo "Filter metadata:\n";
echo json_encode($data['filter_metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\nFirst 3 months:\n";
echo json_encode(array_slice($data['data'], 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";
