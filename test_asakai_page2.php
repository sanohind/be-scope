<?php

// Test to see data with actual values (page 2)
$url = 'http://localhost:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&page=2';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);

if (isset($data['data'])) {
    echo "Page 2 data (items 11-20):\n";
    foreach ($data['data'] as $item) {
        $hasData = $item['has_data'] ? '✓' : '✗';
        echo "{$hasData} {$item['date']}: qty={$item['qty']}\n";
    }
}
