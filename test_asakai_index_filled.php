<?php

// Test Updated Asakai Chart Index API with filled dates
$url = 'http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$data = json_decode($response, true);

if (isset($data['filter_metadata'])) {
    echo "Filter Metadata:\n";
    echo json_encode($data['filter_metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n\n";
}

if (isset($data['data'])) {
    echo "Total items in current page: " . count($data['data']) . "\n";
    echo "First 5 items:\n";
    echo json_encode(array_slice($data['data'], 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n";
}
