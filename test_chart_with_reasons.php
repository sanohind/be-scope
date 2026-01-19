<?php

// Add some test reasons first
echo "=== Adding Test Reasons ===\n";

// Add reason for chart ID 5 (2026-01-19)
$reason1 = [
    'asakai_chart_id' => 5,
    'date' => '2026-01-19',
    'part_no' => 'PART001',
    'part_name' => 'Test Part 1',
    'problem' => 'Machine breakdown',
    'qty' => 2,
    'section' => 'Assembly',
    'line' => 'Line 1',
    'penyebab' => 'Overheating',
    'perbaikan' => 'Replaced cooling fan',
];

$ch1 = curl_init('http://localhost:8000/api/asakai/reasons');
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($reason1));
curl_setopt($ch1, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "Reason 1: HTTP $httpCode1\n";

// Add another reason for chart ID 5
$reason2 = [
    'asakai_chart_id' => 5,
    'date' => '2026-01-19',
    'part_no' => 'PART002',
    'part_name' => 'Test Part 2',
    'problem' => 'Material shortage',
    'qty' => 3,
    'section' => 'Welding',
    'line' => 'Line 2',
    'penyebab' => 'Supplier delay',
    'perbaikan' => 'Used alternative supplier',
];

$ch2 = curl_init('http://localhost:8000/api/asakai/reasons');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($reason2));
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Reason 2: HTTP $httpCode2\n\n";

// Now test the updated endpoint
echo "=== Testing /charts/data with Reasons ===\n";
$url = 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-15&date_to=2026-01-20';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['data'])) {
    foreach ($data['data'] as $item) {
        $hasData = $item['has_data'] ? '✓' : '✗';
        $reasonsCount = $item['reasons_count'];
        echo "{$hasData} {$item['date']}: qty={$item['qty']}, reasons={$reasonsCount}\n";
        
        if ($reasonsCount > 0) {
            foreach ($item['reasons'] as $reason) {
                echo "   - {$reason['part_no']}: {$reason['problem']} (qty: {$reason['qty']})\n";
            }
        }
    }
}
