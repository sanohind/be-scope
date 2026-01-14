<?php

// Direct database query test
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StockByWhSnapshot;
use Carbon\Carbon;

$warehouse = 'WHRM02';
$dateFrom = '2026-01-01';
$dateTo = '2026-01-31';

echo "=== Testing Snapshot Data for $warehouse ===\n\n";

// Test 1: Check if there's any snapshot data
$totalSnapshots = StockByWhSnapshot::where('warehouse', $warehouse)
    ->whereBetween('snapshot_date', [$dateFrom, $dateTo])
    ->count();

echo "Total snapshots in range: $totalSnapshots\n\n";

// Test 2: Get snapshot data grouped by date
$snapshots = StockByWhSnapshot::where('warehouse', $warehouse)
    ->whereBetween('snapshot_date', [$dateFrom, $dateTo])
    ->selectRaw("CAST(snapshot_date AS DATE) as snapshot_day, SUM(onhand) as total_onhand, COUNT(*) as count")
    ->groupByRaw("CAST(snapshot_date AS DATE)")
    ->orderBy('snapshot_day')
    ->get();

echo "Snapshot data by day:\n";
echo str_repeat("-", 60) . "\n";
printf("%-15s | %15s | %10s\n", "Date", "Total Onhand", "Count");
echo str_repeat("-", 60) . "\n";

foreach ($snapshots as $snap) {
    printf("%-15s | %15d | %10d\n", $snap->snapshot_day, $snap->total_onhand, $snap->count);
}

echo str_repeat("-", 60) . "\n";

// Test 3: Check for Jan 13 and 14 specifically
echo "\nDetailed check for Jan 13-14:\n";
$jan13 = StockByWhSnapshot::where('warehouse', $warehouse)
    ->whereDate('snapshot_date', '2026-01-13')
    ->sum('onhand');
    
$jan14 = StockByWhSnapshot::where('warehouse', $warehouse)
    ->whereDate('snapshot_date', '2026-01-14')
    ->sum('onhand');

echo "Jan 13 total onhand: $jan13\n";
echo "Jan 14 total onhand: $jan14\n";
