<?php

// Just test the endpoint with existing data
echo "=== Testing /charts/data with Reasons (Full Response) ===\n\n";

$url = 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-15&date_to=2026-01-20';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

$data = json_decode($response, true);

// Show one item with data
if (isset($data['data'])) {
    echo "Sample data (date with data):\n";
    foreach ($data['data'] as $item) {
        if ($item['has_data']) {
            echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            echo "\n\n";
            break;
        }
    }
    
    echo "Sample data (date without data):\n";
    foreach ($data['data'] as $item) {
        if (!$item['has_data']) {
            echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            echo "\n";
            break;
        }
    }
}
