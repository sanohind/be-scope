<?php
// Debug script untuk melihat masalah WHRM02
// Simulasi logika yang seharusnya terjadi

$warehouse = 'WHRM02';
$periodKey = '2026-01-14';

// Simulasi data yang ada
$onhandRecords = [
    'WHRM02|2026-01-13' => (object)['onhand_total' => 7139481],
    // Tidak ada data untuk 2026-01-14
];

$issueRecords = [
    'WHRM02|2026-01-14' => (object)['issue_total' => 3],
];

// Key yang akan diproses
$cleanKey = $warehouse . '|' . $periodKey;

// Cek apakah ada onhand data
$onhandData = $onhandRecords[$cleanKey] ?? null;

echo "=== DEBUG INFO ===\n";
echo "Warehouse: $warehouse\n";
echo "Period Key: $periodKey\n";
echo "Clean Key: $cleanKey\n";
echo "Onhand Data: " . ($onhandData ? "ADA" : "TIDAK ADA") . "\n";

if (!$onhandData) {
    echo "\nTidak ada onhand data, cari H-1...\n";
    
    // Cari H-1
    $previousKey = 'WHRM02|2026-01-13';
    $previousOnhandData = $onhandRecords[$previousKey] ?? null;
    
    echo "Previous Key: $previousKey\n";
    echo "Previous Onhand Data: " . ($previousOnhandData ? "ADA ({$previousOnhandData->onhand_total})" : "TIDAK ADA") . "\n";
    
    if ($previousOnhandData) {
        $onhandTotal = $previousOnhandData->onhand_total;
        echo "\n✅ SOLUSI: Gunakan onhand H-1 = $onhandTotal\n";
    } else {
        echo "\n❌ MASALAH: H-1 juga tidak ada\n";
    }
} else {
    $onhandTotal = $onhandData->onhand_total;
    echo "\n✅ Ada onhand data = $onhandTotal\n";
}

echo "\n=== EXPECTED RESULT ===\n";
echo "Onhand untuk 2026-01-14 seharusnya: 7139481 (dari H-1)\n";
