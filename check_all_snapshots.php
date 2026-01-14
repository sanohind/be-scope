<?php

// Check all snapshot data
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockByWhSnapshot;

echo "=== Checking All Snapshot Data ===\n\n";

// Get date range of available snapshots
$minDate = StockByWhSnapshot::min('snapshot_date');
$maxDate = StockByWhSnapshot::max('snapshot_date');
$totalCount = StockByWhSnapshot::count();

echo "Total snapshot records: $totalCount\n";
echo "Earliest snapshot: $minDate\n";
echo "Latest snapshot: $maxDate\n\n";

// Get warehouses with data
$warehouses = StockByWhSnapshot::select('warehouse')
    ->groupBy('warehouse')
    ->pluck('warehouse');

echo "Warehouses with snapshot data:\n";
foreach ($warehouses as $wh) {
    $count = StockByWhSnapshot::where('warehouse', $wh)->count();
    echo "- $wh: $count records\n";
}

// Check WHRM02 specifically
echo "\n=== WHRM02 Snapshot Data ===\n";
$whrm02Latest = StockByWhSnapshot::where('warehouse', 'WHRM02')
    ->orderBy('snapshot_date', 'desc')
    ->limit(5)
    ->get(['snapshot_date', 'partno', 'onhand']);

if ($whrm02Latest->count() > 0) {
    echo "Latest 5 snapshots for WHRM02:\n";
    foreach ($whrm02Latest as $snap) {
        echo "- Date: {$snap->snapshot_date}, Part: {$snap->partno}, Onhand: {$snap->onhand}\n";
    }
} else {
    echo "No snapshot data found for WHRM02\n";
}
