<?php

echo "=== Testing URL Differences ===\n\n";

// Wrong URL (will try to find chart with ID "1&period=...")
echo "1. WRONG URL: /charts/1&period=...\n";
$url1 = 'http://127.0.0.1:8000/api/asakai/charts/1&period=daily&date_from=2026-01-01';
$ch1 = curl_init($url1);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);
echo "   HTTP Code: $httpCode1\n";
$data1 = json_decode($response1, true);
echo "   Success: " . ($data1['success'] ? 'true' : 'false') . "\n";
echo "   Message: " . ($data1['message'] ?? 'N/A') . "\n\n";

// Correct URL 1: /charts?asakai_title_id=...
echo "2. CORRECT URL: /charts?asakai_title_id=...\n";
$url2 = 'http://127.0.0.1:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=31';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
echo "   HTTP Code: $httpCode2\n";
$data2 = json_decode($response2, true);
echo "   Success: " . ($data2['success'] ? 'true' : 'false') . "\n";
echo "   Items: " . count($data2['data'] ?? []) . "\n\n";

// Correct URL 2: /charts/data?asakai_title_id=...
echo "3. CORRECT URL: /charts/data?asakai_title_id=...\n";
$url3 = 'http://127.0.0.1:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01';
$ch3 = curl_init($url3);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);
echo "   HTTP Code: $httpCode3\n";
$data3 = json_decode($response3, true);
echo "   Success: " . ($data3['success'] ? 'true' : 'false') . "\n";
echo "   Items: " . count($data3['data'] ?? []) . "\n\n";

echo "=== Summary ===\n";
echo "❌ URL 1: WRONG - Returns 404 error\n";
echo "✅ URL 2: CORRECT - Returns data with pagination\n";
echo "✅ URL 3: CORRECT - Returns all data without pagination\n";
