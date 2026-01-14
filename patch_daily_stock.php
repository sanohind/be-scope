<?php
// Script untuk memperbaiki DailyStockController.php
// Menambahkan logika H-1 untuk onhand ketika ada transaksi tapi tidak ada snapshot

$file = __DIR__ . '/app/Http/Controllers/Api/DailyStockController.php';
$content = file_get_contents($file);

// Cari dan replace bagian yang bermasalah
$search = <<<'EOD'
            }

            return (object) [
                'period_key' => $periodKey,
                'warehouse' => $warehouse,
                'period_start' => $periodDate->startOfDay(),
                'period_end' => match($period) {
                    'daily' => $periodDate->copy()->endOfDay(),
                    'monthly' => $periodDate->copy()->endOfMonth()->endOfDay(),
                    'yearly' => $periodDate->copy()->endOfYear()->endOfDay(),
                    default => $periodDate->copy()->endOfDay(),
                },
                'granularity' => $period,
                'onhand_total' => $onhandData ? (int)$onhandData->onhand_total : 0,
                'receipt_total' => $receiptData ? (int)$receiptData->receipt_total : 0,
                'issue_total' => $issueData ? (int)$issueData->issue_total : 0,
                'adjustment_total' => $adjustmentData ? (int)$adjustmentData->adjustment_total : 0,
            ];
EOD;

$replace = <<<'EOD'
            }

            // Get onhand value - use H-1 if no snapshot for today/future
            $onhandTotal = $onhandData ? (int)$onhandData->onhand_total : 0;
            
            // If no onhand snapshot data and this is today or future date, use previous day's data
            if (!$onhandData) {
                $now = Carbon::now();
                $isTodayOrFuture = $periodDate->greaterThanOrEqualTo($now->copy()->startOfDay());
                
                if ($isTodayOrFuture) {
                    // Look for previous day's data
                    $previousDate = $periodDate->copy()->subDay();
                    $previousKey = match($period) {
                        'daily' => $previousDate->format('Y-m-d'),
                        'monthly' => $previousDate->format('Y-m'),
                        'yearly' => (string) $previousDate->year,
                        default => $previousDate->format('Y-m-d'),
                    };
                    $previousCleanKey = $warehouse . '|' . $previousKey;
                    $previousOnhandData = $onhandRecords->get($previousCleanKey);
                    
                    if ($previousOnhandData) {
                        $onhandTotal = (int)$previousOnhandData->onhand_total;
                    } else {
                        // Lookback up to 30 days to find most recent snapshot
                        for ($i = 2; $i <= 30; $i++) {
                            $lookbackDate = $periodDate->copy()->subDays($i);
                            $lookbackKey = match($period) {
                                'daily' => $lookbackDate->format('Y-m-d'),
                                'monthly' => $lookbackDate->format('Y-m'),
                                'yearly' => (string) $lookbackDate->year,
                                default => $lookbackDate->format('Y-m-d'),
                            };
                            $lookbackCleanKey = $warehouse . '|' . $lookbackKey;
                            $lookbackOnhandData = $onhandRecords->get($lookbackCleanKey);
                            
                            if ($lookbackOnhandData) {
                                $onhandTotal = (int)$lookbackOnhandData->onhand_total;
                                break;
                            }
                        }
                    }
                }
            }

            return (object) [
                'period_key' => $periodKey,
                'warehouse' => $warehouse,
                'period_start' => $periodDate->startOfDay(),
                'period_end' => match($period) {
                    'daily' => $periodDate->copy()->endOfDay(),
                    'monthly' => $periodDate->copy()->endOfMonth()->endOfDay(),
                    'yearly' => $periodDate->copy()->endOfYear()->endOfDay(),
                    default => $periodDate->copy()->endOfDay(),
                },
                'granularity' => $period,
                'onhand_total' => $onhandTotal,
                'receipt_total' => $receiptData ? (int)$receiptData->receipt_total : 0,
                'issue_total' => $issueData ? (int)$issueData->issue_total : 0,
                'adjustment_total' => $adjustmentData ? (int)$adjustmentData->adjustment_total : 0,
            ];
EOD;

$newContent = str_replace($search, $replace, $content, $count);

if ($count > 0) {
    file_put_contents($file, $newContent);
    echo "✅ File berhasil diupdate!\n";
    echo "Jumlah perubahan: $count\n";
    echo "\nPerubahan yang dilakukan:\n";
    echo "- Menambahkan logika H-1 untuk onhand ketika tidak ada snapshot\n";
    echo "- Onhand sekarang menggunakan data kemarin untuk hari ini dan kedepannya\n";
    echo "- Lookback sampai 30 hari untuk mencari snapshot terakhir\n";
} else {
    echo "❌ Tidak ada perubahan yang dilakukan.\n";
    echo "Kemungkinan kode sudah diupdate atau format berbeda.\n";
}
