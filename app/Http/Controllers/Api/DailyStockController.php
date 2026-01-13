<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseStockSummary;
use App\Models\StockByWhSnapshot;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyStockController extends Controller
{
    // Warehouse mapping by category
    private $warehouseCategories = [
        'RM' => ['WHRM01', 'WHRM02', 'WHMT01'],
        'FG' => ['WHFG01', 'WHFG02'],
    ];

    private $allWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

    /**
     * Get period parameter from request (daily, monthly, yearly)
     */
    private function getPeriod(Request $request): string
    {
        $period = $request->input('period', 'daily');
        if (!in_array($period, ['daily', 'monthly', 'yearly'], true)) {
            $period = 'daily';
        }
        return $period;
    }

    /**
     * Get date range parameters from request
     * Returns array with date_from and date_to based on period
     * Default: current month if not specified
     */
    private function getDateRange(Request $request, int $defaultDays = 30, string $period = 'daily'): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // If no dates provided, use default based on period
        if (!$dateFrom && !$dateTo) {
            switch ($period) {
                case 'yearly':
                    $dateFrom = Carbon::now()->startOfYear();
                    $dateTo = Carbon::now();
                    break;
                case 'monthly':
                    $dateFrom = Carbon::now()->startOfMonth();
                    $dateTo = Carbon::now();
                    break;
                case 'daily':
                default:
                    $dateFrom = Carbon::now()->startOfMonth();
                    $dateTo = Carbon::now();
                    break;
            }
        } else {
            // Parse provided dates
            $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subDays($defaultDays);
            $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();
        }

        return [
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d 23:59:59'),
            'date_from_carbon' => $dateFrom,
            'date_to_carbon' => $dateTo,
            'days_diff' => $dateFrom->diffInDays($dateTo)
        ];
    }

    /**
     * Generate all periods in the range based on period type
     */
    private function generateAllPeriods(string $period, string $dateFrom, string $dateTo): array
    {
        $periods = [];
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);

        if ($period === 'daily') {
            $current = $start->copy();
            while ($current->lte($end)) {
                $periods[] = $current->format('Y-m-d');
                $current->addDay();
            }
        } elseif ($period === 'monthly') {
            $current = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();
            while ($current->lte($endMonth)) {
                $periods[] = $current->format('Y-m');
                $current->addMonth();
            }
        } elseif ($period === 'yearly') {
            $currentYear = $start->year;
            $endYear = $end->year;
            while ($currentYear <= $endYear) {
                $periods[] = (string) $currentYear;
                $currentYear++;
            }
        }

        return $periods;
    }

    /**
     * Get database driver name
     */
    private function getDatabaseDriver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    /**
     * Get date format expression based on period and database driver
     */
    private function getDateFormatByPeriod(string $period): string
    {
        $driver = $this->getDatabaseDriver();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB syntax
            return match($period) {
                'daily' => "DATE(period_start)",
                'monthly' => "DATE_FORMAT(period_start, '%Y-%m')",
                'yearly' => "CAST(YEAR(period_start) AS CHAR)",
                default => "DATE(period_start)",
            };
        } elseif ($driver === 'sqlsrv') {
            // SQL Server syntax
            return match($period) {
                'daily' => "CAST(period_start AS DATE)",
                'monthly' => "FORMAT(period_start, 'yyyy-MM')",
                'yearly' => "FORMAT(period_start, 'yyyy')",
                default => "CAST(period_start AS DATE)",
            };
        } else {
            // Default to MySQL syntax for other databases
            return match($period) {
                'daily' => "DATE(period_start)",
                'monthly' => "DATE_FORMAT(period_start, '%Y-%m')",
                'yearly' => "CAST(YEAR(period_start) AS CHAR)",
                default => "DATE(period_start)",
            };
        }
    }

    /**
     * Get date format expression for snapshot_date based on period and database driver
     */
    private function getSnapshotDateFormatByPeriod(string $period): string
    {
        $driver = $this->getDatabaseDriver();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB syntax
            return match($period) {
                'daily' => "DATE(snapshot_date)",
                'monthly' => "DATE_FORMAT(snapshot_date, '%Y-%m')",
                'yearly' => "CAST(YEAR(snapshot_date) AS CHAR)",
                default => "DATE(snapshot_date)",
            };
        } elseif ($driver === 'sqlsrv') {
            // SQL Server syntax
            return match($period) {
                'daily' => "CAST(snapshot_date AS DATE)",
                'monthly' => "FORMAT(snapshot_date, 'yyyy-MM')",
                'yearly' => "FORMAT(snapshot_date, 'yyyy')",
                default => "CAST(snapshot_date AS DATE)",
            };
        } else {
            // Default to MySQL syntax for other databases
            return match($period) {
                'daily' => "DATE(snapshot_date)",
                'monthly' => "DATE_FORMAT(snapshot_date, '%Y-%m')",
                'yearly' => "CAST(YEAR(snapshot_date) AS CHAR)",
                default => "DATE(snapshot_date)",
            };
        }
    }

    /**
     * Get date format expression for trans_date2 based on period and database driver
     */
    private function getTransDateFormatByPeriod(string $period): string
    {
        // Use 'erp' connection since InventoryTransaction uses it
        $driver = $this->getDatabaseDriver('erp');

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB syntax
            return match($period) {
                'daily' => "DATE(trans_date2)",
                'monthly' => "DATE_FORMAT(trans_date2, '%Y-%m')",
                'yearly' => "CAST(YEAR(trans_date2) AS CHAR)",
                default => "DATE(trans_date2)",
            };
        } elseif ($driver === 'sqlsrv') {
            // SQL Server syntax
            return match($period) {
                'daily' => "CAST(trans_date2 AS DATE)",
                'monthly' => "FORMAT(trans_date2, 'yyyy-MM')",
                'yearly' => "FORMAT(trans_date2, 'yyyy')",
                default => "CAST(trans_date2 AS DATE)",
            };
        } else {
            // Default to MySQL syntax for other databases
            return match($period) {
                'daily' => "DATE(trans_date2)",
                'monthly' => "DATE_FORMAT(trans_date2, '%Y-%m')",
                'yearly' => "CAST(YEAR(trans_date2) AS CHAR)",
                default => "DATE(trans_date2)",
            };
        }
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'warehouse' => [
                'nullable',
                'string',
                Rule::in(array_merge($this->allWarehouses, array_keys($this->warehouseCategories))),
            ],
            // Use unified date parameters: date_from and date_to
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'period' => 'nullable|in:daily,monthly,yearly',
        ]);

        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        // Determine which warehouses to query
        $warehousesToQuery = $this->allWarehouses;

        if (!empty($validated['warehouse'])) {
            $warehouse = $validated['warehouse'];

            // If it's a category (RM or FG), get warehouses in that category
            if (in_array($warehouse, array_keys($this->warehouseCategories))) {
                $warehousesToQuery = $this->warehouseCategories[$warehouse];
            } else {
                // Otherwise, it's a specific warehouse code
                $warehousesToQuery = [$warehouse];
            }
        }

        // Map period to granularity
        $granularity = match($period) {
            'yearly' => 'yearly',
            'monthly' => 'monthly',
            default => 'daily'
        };

        // Get date format for grouping
        $dateFormat = $this->getDateFormatByPeriod($period);

        // Query data grouped by period
        // For monthly and yearly, always aggregate from daily data
        $queryGranularity = $period === 'daily' ? 'daily' : 'daily';

        // Get date format for StockByWhSnapshot (using snapshot_date instead of period_start)
        $snapshotDateFormat = $this->getSnapshotDateFormatByPeriod($period);

        // Query 1: Get onhand from StockByWhSnapshot
        $onhandRecords = StockByWhSnapshot::query()
            ->whereIn('warehouse', $warehousesToQuery)
            ->whereBetween('snapshot_date', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ])
            ->selectRaw("
                {$snapshotDateFormat} as period_key,
                warehouse,
                SUM(onhand) as onhand_total
            ")
            ->groupByRaw("{$snapshotDateFormat}, warehouse")
            ->get()
            ->keyBy(function($item) {
                return $item->warehouse . '|' . trim($item->period_key);
            });

        // Get date format for InventoryTransaction (using trans_date2)
        $transDateFormat = $this->getTransDateFormatByPeriod($period);

        // Query 2: Get receipt from InventoryTransaction (trans_type = 'Receipt')
        $receiptRecords = InventoryTransaction::query()
            ->whereIn('warehouse', $warehousesToQuery)
            ->where('trans_type', 'Receipt')
            ->whereBetween('trans_date2', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ])
            ->selectRaw("
                {$transDateFormat} as period_key,
                warehouse,
                SUM(qty) as receipt_total
            ")
            ->groupByRaw("{$transDateFormat}, warehouse")
            ->get()
            ->keyBy(function($item) {
                return $item->warehouse . '|' . trim($item->period_key);
            });

        // Query 3: Get issue from InventoryTransaction (trans_type = 'Issue')
        $issueRecords = InventoryTransaction::query()
            ->whereIn('warehouse', $warehousesToQuery)
            ->where('trans_type', 'Issue')
            ->whereBetween('trans_date2', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ])
            ->selectRaw("
                {$transDateFormat} as period_key,
                warehouse,
                SUM(qty) as issue_total
            ")
            ->groupByRaw("{$transDateFormat}, warehouse")
            ->get()
            ->keyBy(function($item) {
                return $item->warehouse . '|' . trim($item->period_key);
            });

        // Query 4: Get inventory adjustment from InventoryTransaction (trans_type = 'Inventory Adjustment')
        $adjustmentRecords = InventoryTransaction::query()
            ->whereIn('warehouse', $warehousesToQuery)
            ->where('trans_type', 'Inventory Adjustment')
            ->whereBetween('trans_date2', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ])
            ->selectRaw("
                {$transDateFormat} as period_key,
                warehouse,
                SUM(qty) as adjustment_total
            ")
            ->groupByRaw("{$transDateFormat}, warehouse")
            ->get()
            ->keyBy(function($item) {
                return $item->warehouse . '|' . trim($item->period_key);
            });

        // Combine all data by creating a unified collection
        // Get all unique period_key + warehouse combinations
        $allKeys = collect($onhandRecords->keys())
            ->merge($receiptRecords->keys())
            ->merge($issueRecords->keys())
            ->merge($adjustmentRecords->keys())
            ->unique();

        $records = $allKeys->map(function($key) use ($onhandRecords, $receiptRecords, $issueRecords, $adjustmentRecords, $period, $dateRange) {
            [$warehouse, $periodKey] = explode('|', $key);
            
            // Clean up period_key - trim whitespace
            $periodKey = trim($periodKey);
            $warehouse = trim($warehouse);
            
            // Recreate key with trimmed values
            $cleanKey = $warehouse . '|' . $periodKey;
            
            $onhandData = $onhandRecords->get($cleanKey);
            $receiptData = $receiptRecords->get($cleanKey);
            $issueData = $issueRecords->get($cleanKey);
            $adjustmentData = $adjustmentRecords->get($cleanKey);

            // Determine period_start and period_end based on period_key
            try {
                $periodDate = match($period) {
                    'daily' => Carbon::parse($periodKey),
                    'monthly' => Carbon::createFromFormat('Y-m', trim($periodKey))->startOfMonth(),
                    'yearly' => Carbon::createFromFormat('Y', trim($periodKey))->startOfYear(),
                    default => Carbon::parse($periodKey),
                };
            } catch (\Exception $e) {
                // If parsing fails, use a default date from the range
                $periodDate = $dateRange['date_from_carbon']->copy();
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
        })
            ->sortBy('warehouse')
            ->sortBy('period_key');

        // Generate all periods in range for filling missing dates
        $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);

        // Group by warehouse and fill missing periods
        $warehouses = collect($warehousesToQuery)->map(function ($warehouseCode) use ($records, $allPeriods, $period, $granularity) {
            $warehouseData = $records->where('warehouse', $warehouseCode);

            // Normalize period_key format and create a keyed collection
            $dataByPeriod = $warehouseData->mapWithKeys(function ($item) use ($period) {
                // Get the raw period_key value
                $rawPeriodKey = $item->period_key ?? $item->getAttribute('period_key') ?? '';
                $periodKey = trim((string) $rawPeriodKey);

                // For daily, ensure Y-m-d format
                if ($period === 'daily') {
                    try {
                        $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                }
                // For monthly, the period_key from SQL Server FORMAT() is already in 'yyyy-MM' format
                // Just ensure it's trimmed and matches the expected format
                elseif ($period === 'monthly') {
                    // SQL Server FORMAT returns 'yyyy-MM', so we just need to ensure it's clean
                    $periodKey = trim($periodKey);
                    // Remove any extra whitespace and validate format (should be YYYY-MM)
                    if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                        $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        // If format doesn't match, try to parse as date and reformat
                        try {
                            $parsed = Carbon::parse($periodKey);
                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    }
                }
                // For yearly, the period_key from SQL Server FORMAT() is already just the year
                // Just ensure it's a clean integer string
                elseif ($period === 'yearly') {
                    $periodKey = trim($periodKey);
                    // Extract year from string if it contains other characters
                    if (preg_match('/(\d{4})/', $periodKey, $matches)) {
                        $periodKey = (string) intval($matches[1]);
                    } else {
                        $periodKey = (string) intval($periodKey);
                    }
                }

                return [$periodKey => $item];
            });

            // Fill missing periods - use previous day's data for today and future dates
            $filledData = collect($allPeriods)->map(function ($periodValue, $index) use ($dataByPeriod, $warehouseCode, $period, $granularity, $allPeriods) {
                $existing = $dataByPeriod->get($periodValue);

                if ($existing) {
                    // Handle period_start and period_end - they might be strings from raw query or Carbon instances
                    $periodStart = $existing->period_start ?? $existing->getAttribute('period_start');
                    if (is_string($periodStart)) {
                        try {
                            $periodStart = Carbon::parse($periodStart);
                        } catch (\Exception $e) {
                            $periodStart = null;
                        }
                    } elseif ($periodStart instanceof \DateTime) {
                        $periodStart = Carbon::instance($periodStart);
                    }

                    $periodEnd = $existing->period_end ?? $existing->getAttribute('period_end');
                    if (is_string($periodEnd)) {
                        try {
                            $periodEnd = Carbon::parse($periodEnd);
                        } catch (\Exception $e) {
                            $periodEnd = null;
                        }
                    } elseif ($periodEnd instanceof \DateTime) {
                        $periodEnd = Carbon::instance($periodEnd);
                    }

                    // Access aggregated values - try both property access and getAttribute
                    $onhand = $existing->onhand_total ?? $existing->getAttribute('onhand_total') ?? 0;
                    $receipt = $existing->receipt_total ?? $existing->getAttribute('receipt_total') ?? 0;
                    $issue = $existing->issue_total ?? $existing->getAttribute('issue_total') ?? 0;
                    $adjustment = $existing->adjustment_total ?? $existing->getAttribute('adjustment_total') ?? 0;

                    return [
                        'period' => $periodValue,
                        'period_start' => $periodStart ? $periodStart->toDateTimeString() : null,
                        'period_end' => $periodEnd ? $periodEnd->toDateTimeString() : null,
                        'granularity' => $existing->granularity ?? $granularity,
                        'warehouse' => $warehouseCode,
                        'onhand' => (int) $onhand,
                        'receipt' => (int) $receipt,
                        'issue' => (int) $issue,
                        'adjustment' => (int) $adjustment,
                    ];
                } else {
                    // No data exists for this period
                    $periodDate = match($period) {
                        'daily' => Carbon::parse($periodValue),
                        'monthly' => Carbon::createFromFormat('Y-m', $periodValue)->startOfMonth(),
                        'yearly' => Carbon::createFromFormat('Y', $periodValue)->startOfYear(),
                        default => Carbon::parse($periodValue),
                    };

                    // Check if this period is today or in the future
                    $now = Carbon::now();
                    $isTodayOrFuture = $periodDate->greaterThanOrEqualTo($now->copy()->startOfDay());

                    // Default values
                    $onhand = 0;
                    $receipt = 0;
                    $issue = 0;
                    $adjustment = 0;

                    // If it's today or future date, only use previous period's data for ONHAND
                    // Receipt, issue, and adjustment remain 0 as they haven't occurred yet
                    if ($isTodayOrFuture && $index > 0) {
                        // Get previous period value
                        $previousPeriodValue = $allPeriods[$index - 1];
                        $previousData = $dataByPeriod->get($previousPeriodValue);

                        if ($previousData) {
                            // Use previous period's onhand data only
                            $onhand = $previousData->onhand_total ?? $previousData->getAttribute('onhand_total') ?? 0;
                        } else {
                            // If previous period also doesn't have data, look for the most recent available onhand data
                            for ($i = $index - 1; $i >= 0; $i--) {
                                $lookbackPeriodValue = $allPeriods[$i];
                                $lookbackData = $dataByPeriod->get($lookbackPeriodValue);
                                
                                if ($lookbackData) {
                                    $onhand = $lookbackData->onhand_total ?? $lookbackData->getAttribute('onhand_total') ?? 0;
                                    break;
                                }
                            }
                        }
                    }

                    return [
                        'period' => $periodValue,
                        'period_start' => $periodDate->startOfDay()->toDateTimeString(),
                        'period_end' => match($period) {
                            'daily' => $periodDate->endOfDay()->toDateTimeString(),
                            'monthly' => $periodDate->endOfMonth()->endOfDay()->toDateTimeString(),
                            'yearly' => $periodDate->endOfYear()->endOfDay()->toDateTimeString(),
                            default => $periodDate->endOfDay()->toDateTimeString(),
                        },
                        'granularity' => $granularity,
                        'warehouse' => $warehouseCode,
                        'onhand' => (int) $onhand,
                        'receipt' => (int) $receipt,
                        'issue' => (int) $issue,
                        'adjustment' => (int) $adjustment,
                    ];
                }
            })->values();

            return [
                'warehouse' => $warehouseCode,
                'data' => $filledData->all(),
            ];
        })->keyBy('warehouse');

        return response()->json([
            'meta' => [
                'warehouse_filter' => $validated['warehouse'] ?? 'all',
                'warehouses_queried' => $warehousesToQuery,
                'date_from_filter' => $validated['date_from'] ?? null,
                'date_to_filter' => $validated['date_to'] ?? null,
                'period' => $period,
                'granularity' => $granularity,
                'total_records' => $records->count(),
            ],
            'warehouses' => $warehouses->values(),
        ]);
    }
}

