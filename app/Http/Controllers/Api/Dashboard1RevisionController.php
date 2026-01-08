<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use App\Models\StockByWhSnapshot;
use App\Models\WarehouseStockSummary;
use App\Models\InventoryTransaction;
use App\Models\DailyUseWh;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard1RevisionController extends ApiController
{
    /**
     * Get warehouse parameter from request or throw error
     * Supports both individual warehouse codes and warehouse group aliases
     */
    private function getWarehouse(Request $request): string
    {
        $warehouse = $request->input('warehouse');

        if (!$warehouse) {
            abort(400, 'Warehouse parameter is required');
        }

        // Warehouse group aliases
        $aliases = [
            'RM' => ['WHRM01', 'WHRM02', 'WHMT01'],
            'FG' => ['WHFG01', 'WHFG02'],
        ];

        $validWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

        // Check if warehouse is an alias
        if (isset($aliases[$warehouse])) {
            // For now, return the first warehouse in the group
            // In future, this could be  to handle multiple warehouses
            return $aliases[$warehouse][0];
        }

        if (!in_array($warehouse, $validWarehouses)) {
            abort(400, 'Invalid warehouse code or alias');
        }

        return $warehouse;
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
     * Get snapshot date based on period and date range
     * For detail queries, we use the latest snapshot within date range
     * For monthly/yearly, returns latest snapshot in the period
     */
    private function getSnapshotDate(Request $request, string $period = 'daily'): ?string
    {
        $dateRange = $this->getDateRange($request, 30, $period);

        // For monthly/yearly, get latest snapshot in the last period
        if ($period === 'monthly') {
            // Get latest snapshot in the last month of the range
            $lastMonth = $dateRange['date_to_carbon']->copy()->startOfMonth();
            $latestSnapshot = StockByWhSnapshot::whereBetween('snapshot_date', [
                $lastMonth->format('Y-m-d'),
                $dateRange['date_to']
            ])->max('snapshot_date');
        } elseif ($period === 'yearly') {
            // Get latest snapshot in the last year of the range
            $lastYear = $dateRange['date_to_carbon']->copy()->startOfYear();
            $latestSnapshot = StockByWhSnapshot::whereBetween('snapshot_date', [
                $lastYear->format('Y-m-d'),
                $dateRange['date_to']
            ])->max('snapshot_date');
        } else {
            // Daily: Get latest snapshot within range
            $latestSnapshot = StockByWhSnapshot::whereBetween('snapshot_date', [
                $dateRange['date_from'],
                $dateRange['date_to']
            ])->max('snapshot_date');
        }

        return $latestSnapshot ? Carbon::parse($latestSnapshot)->format('Y-m-d') : null;
    }

    /**
     * Get snapshot dates grouped by period for trend analysis
     * Returns array of snapshot dates per period (month/year)
     */
    private function getSnapshotDatesByPeriod(Request $request, string $period = 'daily'): array
    {
        $dateRange = $this->getDateRange($request, 30, $period);

        if ($period === 'monthly') {
            // Get latest snapshot for each month in the range
            $snapshots = StockByWhSnapshot::selectRaw("
                    FORMAT(snapshot_date, 'yyyy-MM') as period,
                    MAX(snapshot_date) as latest_snapshot
                ")
                ->whereBetween('snapshot_date', [
                    $dateRange['date_from'],
                    $dateRange['date_to']
                ])
                ->groupByRaw("FORMAT(snapshot_date, 'yyyy-MM')")
                ->orderByRaw("FORMAT(snapshot_date, 'yyyy-MM')")
                ->get();

            return $snapshots->pluck('latest_snapshot', 'period')->toArray();
        } elseif ($period === 'yearly') {
            // Get latest snapshot for each year in the range
            $snapshots = StockByWhSnapshot::selectRaw("
                    FORMAT(snapshot_date, 'yyyy') as period,
                    MAX(snapshot_date) as latest_snapshot
                ")
                ->whereBetween('snapshot_date', [
                    $dateRange['date_from'],
                    $dateRange['date_to']
                ])
                ->groupByRaw("FORMAT(snapshot_date, 'yyyy')")
                ->orderByRaw("FORMAT(snapshot_date, 'yyyy')")
                ->get();

            return $snapshots->pluck('latest_snapshot', 'period')->toArray();
        } else {
            // Daily: Return single latest snapshot
            $latest = StockByWhSnapshot::whereBetween('snapshot_date', [
                $dateRange['date_from'],
                $dateRange['date_to']
            ])->max('snapshot_date');

            return $latest ? ['latest' => $latest] : [];
        }
    }

    /**
     * Build date filter SQL condition
     */
    private function buildDateFilter(string $tableAlias, array $dateRange): string
    {
        return sprintf(
            "%s.trans_date >= '%s' AND %s.trans_date <= '%s'",
            $tableAlias,
            $dateRange['date_from'],
            $tableAlias,
            $dateRange['date_to']
        );
    }

    /**
     * Get database driver name for a connection
     */
    private function getDatabaseDriver(?string $connection = null): string
    {
        $conn = $connection ? DB::connection($connection) : DB::connection();
        return $conn->getDriverName();
    }

    /**
     * Get date format expression based on period and database driver
     * Override parent method with specific implementation
     * Uses 'period_start' as the date field by default
     */
    protected function getDateFormatByPeriod($period, $dateField = 'period_start', $query = null)
    {
        $driver = $this->getDatabaseDriver();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB syntax
            return match($period) {
                'daily' => "DATE($dateField)",
                'monthly' => "DATE_FORMAT($dateField, '%Y-%m')",
                'yearly' => "CAST(YEAR($dateField) AS CHAR)",
                default => "DATE($dateField)",
            };
        } elseif ($driver === 'sqlsrv') {
            // SQL Server syntax
            return match($period) {
                'daily' => "CAST($dateField AS DATE)",
                'monthly' => "FORMAT($dateField, 'yyyy-MM')",
                'yearly' => "FORMAT($dateField, 'yyyy')",
                default => "CAST($dateField AS DATE)",
            };
        } else {
            // Default to MySQL syntax for other databases
            return match($period) {
                'daily' => "DATE($dateField)",
                'monthly' => "DATE_FORMAT($dateField, '%Y-%m')",
                'yearly' => "CAST(YEAR($dateField) AS CHAR)",
                default => "DATE($dateField)",
            };
        }
    }

    /**
     * Convert SQL Server syntax to MySQL syntax if needed
     */
    private function adaptSqlForDriver(string $sql, ?string $connection = null): string
    {
        $driver = $this->getDatabaseDriver($connection);

        if ($driver === 'mysql') {
            // Replace SQL Server syntax with MySQL syntax
            $sql = preg_replace('/\bTRY_CAST\s*\(/i', 'CAST(', $sql);
            $sql = preg_replace('/\bISNULL\s*\(/i', 'IFNULL(', $sql);
            $sql = preg_replace('/\bTOP\s+(\d+)/i', '', $sql); // Remove TOP, will add LIMIT at end
            $sql = preg_replace('/\[(\w+)\]/', '`$1`', $sql); // Replace [column] with `column`
            $sql = preg_replace('/\bDATEADD\s*\(\s*month\s*,\s*(-?\d+)\s*,\s*([^)]+)\s*\)/i', 'DATE_ADD($2, INTERVAL $1 MONTH)', $sql);
            $sql = preg_replace('/\bGETDATE\s*\(\)/i', 'NOW()', $sql);
            $sql = preg_replace('/\bDATEDIFF\s*\(\s*day\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i', 'DATEDIFF($2, $1)', $sql);

            // Add LIMIT if TOP was removed and ORDER BY exists
            if (preg_match('/\bSELECT\s+(.*?)\s+FROM/i', $sql, $matches)) {
                $hasTop = preg_match('/\bTOP\s+\d+/i', $sql);
                if (!$hasTop && preg_match('/\bORDER\s+BY\s+.*$/i', $sql, $orderMatch)) {
                    // Check if LIMIT already exists
                    if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
                        // Try to extract TOP number from original query if it was there
                        // For now, we'll handle this in the calling method
                    }
                }
            }
        }

        return $sql;
    }

    /**
     * CHART 1: Comprehensive KPI Cards
     * 6 metrics combining stock + transaction data
     * DATE FILTER: Applied to transaction metrics only
     * Uses snapshot data for historical stock view
     * CRITICAL ITEMS: Based on estimatedConsumption (onhand / daily_use) <= 0
     */
    public function comprehensiveKpi(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Stock Metrics (use snapshot for historical view)
        $stockQuery = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->where('warehouse', $warehouse)
            : StockByWh::where('warehouse', $warehouse);

        // Apply filters
        if ($request->has('customer')) {
            $stockQuery->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $stockQuery->where('group_type_desc', $request->group_type_desc);
        }

        $totalSku = (clone $stockQuery)->distinct()->count('partno');
        $totalOnhand = (clone $stockQuery)->sum('onhand');

        // Critical Items Calculation based on estimatedConsumption
        // Get plan_date parameters
        $planDate = $request->input('plan_date');
        $planDateFrom = $request->input('date_from');
        $planDateTo = $request->input('date_to');
        
        $criticalItems = 0;
        
        // Determine plan_date filtering (exact or range)
        $useExactPlanDate = !empty($planDate);
        $usePlanDateRange = !$useExactPlanDate && ($planDateFrom || $planDateTo);
        
        if ($useExactPlanDate || $usePlanDateRange) {
            try {
                $planDateParsed = null;
                $planDateRange = null;

                if ($useExactPlanDate) {
                    $planDateParsed = Carbon::parse($planDate)->format('Y-m-d');
                } elseif ($usePlanDateRange) {
                    // Use date range when plan_date not provided
                    $from = $planDateFrom ? Carbon::parse($planDateFrom)->format('Y-m-d') : null;
                    $to = $planDateTo ? Carbon::parse($planDateTo)->format('Y-m-d') : null;
                    // Fall back to dateRange if not provided
                    if (!$from) {
                        $from = Carbon::parse(substr($dateRange['date_from'], 0, 10))->format('Y-m-d');
                    }
                    if (!$to) {
                        $to = Carbon::parse(substr($dateRange['date_to'], 0, 10))->format('Y-m-d');
                    }
                    $planDateRange = [$from, $to];
                }

                // Get DailyUseWh data for the plan_date
                $dailyUseQuery = DailyUseWh::whereNotNull('partno');

                if ($useExactPlanDate) {
                    $dailyUseQuery->where('plan_date', $planDateParsed);
                } elseif ($planDateRange) {
                    $dailyUseQuery->whereBetween('plan_date', $planDateRange);
                }

                $dailyUseData = $dailyUseQuery->get();

                if ($dailyUseData->isNotEmpty()) {
                    // Get stock items with their partno and onhand
                    $stockItems = (clone $stockQuery)
                        ->select('partno', 'onhand')
                        ->get();

                    // Build mapping: partno -> onhand
                    $partnoOnhandMap = [];
                    foreach ($stockItems as $item) {
                        $partno = trim((string) $item->partno);
                        if ($partno !== '') {
                            $partnoOnhandMap[$partno] = (float) ($item->onhand ?? 0);
                        }
                    }

                    // Calculate critical items based on estimatedConsumption
                    $partnoProcessed = [];
                    foreach ($dailyUseData as $dailyUseItem) {
                        $partno = trim((string) ($dailyUseItem->partno ?? ''));
                        $dailyUse = (float) ($dailyUseItem->daily_use ?? 0);

                        // Skip if already processed or invalid
                        if ($partno === '' || isset($partnoProcessed[$partno])) {
                            continue;
                        }

                        $partnoProcessed[$partno] = true;

                        // Get onhand for this partno
                        $onhand = $partnoOnhandMap[$partno] ?? 0;

                        // Calculate estimatedConsumption
                        if ($dailyUse > 0) {
                            $estimatedConsumption = $onhand / $dailyUse;
                        } else {
                            $estimatedConsumption = 0;
                        }

                        // Count as critical if estimatedConsumption <= 0
                        if ($estimatedConsumption <= 0) {
                            $criticalItems++;
                        }
                    }
                }
            } catch (\Exception $e) {
                // If plan_date parsing fails, critical items remains 0
                // Log error if needed: \Log::error('Critical items calculation error: ' . $e->getMessage());
            }
        }

        // Transaction Metrics (with date filter)
        $transInPeriod = DB::connection('erp')->table('inventory_transaction')
            ->where('warehouse', $warehouse)
            ->whereBetween('trans_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->count();

        // Net Movement (with date filter)
        // Convert nvarchar to numeric using CAST/TRY_CAST
        $netMovement = DB::connection('erp')
            ->table('inventory_transaction')
            ->where('warehouse', $warehouse)
            ->whereBetween('trans_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->selectRaw("
                SUM(
                    CASE
                        WHEN trans_type = 'Receipt' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                        WHEN trans_type = 'Issue' THEN -TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                        ELSE 0
                    END
                ) AS net
            ")
            ->value('net');


        return response()->json([
            'total_sku' => $totalSku,
            'total_onhand' => round($totalOnhand, 2),
            'critical_items' => $criticalItems,
            'trans_in_period' => $transInPeriod,
            'net_movement' => round($netMovement ?? 0, 2),
            'snapshot_date' => $snapshotDate,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 2: Stock Health Distribution + Activity
     * Donut chart with transaction activity
     * DATE FILTER: Applied to transaction counts
     * Uses snapshot data for historical stock view
     * Grouped by date (snapshot_date) with period filtering
     * For RM warehouses (WHRM01, WHRM02, WHMT01): Uses estimatedConsumption logic
     * For other warehouses: Uses traditional onhand-based logic
     */
    public function stockHealthDistribution(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        // Check if warehouse uses estimatedConsumption logic
        $rmWarehouses = ['WHRM01', 'WHRM02', 'WHMT01'];
        $useEstimatedConsumption = in_array($warehouse, $rmWarehouses);

        // Detect database driver for proper date formatting
        $driver = $this->getDatabaseDriver();

        // Determine date grouping based on period and database driver
        $dateGrouping = match($period) {
            'monthly' => $driver === 'mysql'
                ? "DATE_FORMAT(s.snapshot_date, '%Y-%m')"
                : "FORMAT(s.snapshot_date, 'yyyy-MM')",
            'yearly' => $driver === 'mysql'
                ? "DATE_FORMAT(s.snapshot_date, '%Y')"
                : "FORMAT(s.snapshot_date, 'yyyy')",
            default => $driver === 'mysql'
                ? "DATE(s.snapshot_date)"
                : "CAST(s.snapshot_date AS DATE)"
        };

        $dateLabel = match($period) {
            'monthly' => 'month',
            'yearly' => 'year',
            default => 'date'
        };

        // Build where conditions
        $whereConditions = ["s.warehouse = ?"];
        $params = [$warehouse];

        // Add date range filter for snapshot_date
        $whereConditions[] = "s.snapshot_date >= ? AND s.snapshot_date <= ?";
        $params[] = $dateRange['date_from'];
        $params[] = $dateRange['date_to'];

        if ($request->has('customer')) {
            $whereConditions[] = "s.customer = ?";
            $params[] = $request->customer;
        }

        if ($request->has('group_type_desc')) {
            $whereConditions[] = "s.group_type_desc = ?";
            $params[] = $request->group_type_desc;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Build date filter for transaction join
        $dateFilter = $this->buildDateFilter('t', $dateRange);

        if ($useEstimatedConsumption) {
            // For RM warehouses: Use estimatedConsumption logic
            // Get plan_date parameters
            $planDate = $request->input('plan_date');
            $planDateFrom = $request->input('date_from');
            $planDateTo = $request->input('date_to');

            // Determine plan_date filtering (exact or range)
            $useExactPlanDate = !empty($planDate);
            $usePlanDateRange = !$useExactPlanDate && ($planDateFrom || $planDateTo);

            if (!$useExactPlanDate && !$usePlanDateRange) {
                // Default to dateRange if no plan_date provided
                $planDateFrom = Carbon::parse(substr($dateRange['date_from'], 0, 10))->format('Y-m-d');
                $planDateTo = Carbon::parse(substr($dateRange['date_to'], 0, 10))->format('Y-m-d');
                $usePlanDateRange = true;
            }

            $planDateParsed = null;
            $planDateRange = null;

            if ($useExactPlanDate) {
                $planDateParsed = Carbon::parse($planDate)->format('Y-m-d');
            } elseif ($usePlanDateRange) {
                $from = $planDateFrom ? Carbon::parse($planDateFrom)->format('Y-m-d') : null;
                $to = $planDateTo ? Carbon::parse($planDateTo)->format('Y-m-d') : null;
                if (!$from) {
                    $from = Carbon::parse(substr($dateRange['date_from'], 0, 10))->format('Y-m-d');
                }
                if (!$to) {
                    $to = Carbon::parse(substr($dateRange['date_to'], 0, 10))->format('Y-m-d');
                }
                $planDateRange = [$from, $to];
            }

            // Get DailyUseWh data
            $dailyUseQuery = DailyUseWh::whereNotNull('partno');

            if ($useExactPlanDate) {
                $dailyUseQuery->where('plan_date', $planDateParsed);
            } elseif ($planDateRange) {
                $dailyUseQuery->whereBetween('plan_date', $planDateRange);
            }

            $dailyUseData = $dailyUseQuery->get();

            // Build partno -> daily_use mapping
            $partnoDailyUseMap = [];
            foreach ($dailyUseData as $dailyUseItem) {
                $partno = trim((string) ($dailyUseItem->partno ?? ''));
                $dailyUse = (float) ($dailyUseItem->daily_use ?? 0);
                if ($partno !== '') {
                    if (!isset($partnoDailyUseMap[$partno])) {
                        $partnoDailyUseMap[$partno] = 0;
                    }
                    $partnoDailyUseMap[$partno] += $dailyUse;
                }
            }

            // Get stock data with partno and onhand
            $sql = "
                SELECT
                    {$dateGrouping} as {$dateLabel},
                    s.partno,
                    s.onhand
                FROM stock_by_wh_snapshots s
                WHERE {$whereClause}
                ORDER BY {$dateGrouping}
            ";

            $stockData = DB::select($sql, $params);

            // Calculate stock status based on estimatedConsumption
            $groupedData = [];
            foreach ($stockData as $row) {
                $periodKey = $row->{$dateLabel};
                // Normalize period key
                $periodKey = trim((string) $periodKey);
                if ($period === 'daily') {
                    try {
                        $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                } elseif ($period === 'monthly') {
                    if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                        $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        try {
                            $parsed = Carbon::parse($periodKey);
                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    }
                } elseif ($period === 'yearly') {
                    if (preg_match('/(\d{4})/', $periodKey, $matches)) {
                        $periodKey = (string) intval($matches[1]);
                    } else {
                        $periodKey = (string) intval($periodKey);
                    }
                }

                $partno = trim((string) $row->partno);
                $onhand = (float) ($row->onhand ?? 0);
                $dailyUse = $partnoDailyUseMap[$partno] ?? 0;

                // Calculate estimatedConsumption
                if ($dailyUse > 0) {
                    $estimatedConsumption = $onhand / $dailyUse;
                } else {
                    $estimatedConsumption = 0;
                }

                // Determine stock status based on estimatedConsumption
                if ($dailyUse === 0.0 && $estimatedConsumption === 0.0) {
                    $stockStatus = 'Undefined';
                } elseif ($estimatedConsumption <= 0) {
                    $stockStatus = 'Critical';
                } elseif ($estimatedConsumption <= 3) {
                    $stockStatus = 'Low Stock';
                } elseif ($estimatedConsumption <= 9) {
                    $stockStatus = 'Normal';
                } else {
                    $stockStatus = 'Overstock';
                }

                if (!isset($groupedData[$periodKey])) {
                    $groupedData[$periodKey] = [];
                }
                if (!isset($groupedData[$periodKey][$stockStatus])) {
                    $groupedData[$periodKey][$stockStatus] = [
                        'item_count' => 0,
                        'total_onhand' => 0
                    ];
                }
                $groupedData[$periodKey][$stockStatus]['item_count']++;
                $groupedData[$periodKey][$stockStatus]['total_onhand'] += $onhand;
            }

            // Get transaction counts
            $transactionSql = "
                SELECT
                    {$dateGrouping} as {$dateLabel},
                    COUNT(t.trans_id) as trans_count,
                    COUNT(t.shipment) as total_shipment
                FROM stock_by_wh_snapshots s
                LEFT JOIN inventory_transaction t
                    ON s.partno = t.partno
                    AND s.warehouse = t.warehouse
                    AND {$dateFilter}
                WHERE {$whereClause}
                GROUP BY {$dateGrouping}
                ORDER BY {$dateGrouping}
            ";

            $transactionData = DB::select($transactionSql, $params);

            // Merge transaction data
            $transactionMap = [];
            foreach ($transactionData as $row) {
                $periodKey = $row->{$dateLabel};
                $periodKey = trim((string) $periodKey);
                if ($period === 'daily') {
                    try {
                        $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original
                    }
                } elseif ($period === 'monthly') {
                    if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                        $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        try {
                            $parsed = Carbon::parse($periodKey);
                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // Keep original
                        }
                    }
                } elseif ($period === 'yearly') {
                    if (preg_match('/(\d{4})/', $periodKey, $matches)) {
                        $periodKey = (string) intval($matches[1]);
                    } else {
                        $periodKey = (string) intval($periodKey);
                    }
                }
                $transactionMap[$periodKey] = [
                    'trans_count' => $row->trans_count ?? 0,
                    'total_shipment' => $row->total_shipment ?? 0
                ];
            }

            // Format data for response
            $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);
            $formattedData = [];

            foreach ($allPeriods as $periodValue) {
                $periodKeyStr = trim((string) $periodValue);
                $periodData = [];

                if (isset($groupedData[$periodKeyStr])) {
                    foreach ($groupedData[$periodKeyStr] as $status => $stats) {
                        $periodData[] = (object)[
                            'stock_status' => $status,
                            'item_count' => $stats['item_count'],
                            'total_onhand' => round($stats['total_onhand'], 2),
                            'trans_count' => $transactionMap[$periodKeyStr]['trans_count'] ?? 0,
                            'total_shipment' => $transactionMap[$periodKeyStr]['total_shipment'] ?? 0
                        ];
                    }
                }

                $formattedData[] = [
                    $dateLabel => $periodKeyStr,
                    'data' => $periodData
                ];
            }

            return response()->json([
                'data' => $formattedData,
                'date_range' => [
                    'from' => $dateRange['date_from'],
                    'to' => $dateRange['date_to']
                ],
                'period' => $period,
                'grouping' => $dateLabel,
                'calculation_method' => 'estimatedConsumption'
            ]);

        } else {
            // For other warehouses: Use traditional logic
            $sql = "
                SELECT
                    {$dateGrouping} as {$dateLabel},
                    CASE
                        WHEN s.onhand < s.min_stock THEN 'Critical'
                        WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                        WHEN s.onhand > s.max_stock THEN 'Overstock'
                        ELSE 'Normal'
                    END as stock_status,
                    COUNT(DISTINCT s.partno) as item_count,
                    SUM(s.onhand) as total_onhand,
                    COUNT(t.trans_id) as trans_count,
                    COUNT(t.shipment) as total_shipment
                FROM stock_by_wh_snapshots s
                LEFT JOIN inventory_transaction t
                    ON s.partno = t.partno
                    AND s.warehouse = t.warehouse
                    AND {$dateFilter}
                WHERE {$whereClause}
                GROUP BY
                    {$dateGrouping},
                    CASE
                        WHEN s.onhand < s.min_stock THEN 'Critical'
                        WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                        WHEN s.onhand > s.max_stock THEN 'Overstock'
                        ELSE 'Normal'
                    END
                ORDER BY {$dateGrouping}
            ";

            $data = DB::select($sql, $params);

            // Group data by period for cleaner response
            $groupedData = [];
            foreach ($data as $row) {
                $periodKey = $row->{$dateLabel};
                // Normalize period key
                $periodKey = trim((string) $periodKey);
                if ($period === 'daily') {
                    try {
                        $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                } elseif ($period === 'monthly') {
                    if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                        $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        try {
                            $parsed = Carbon::parse($periodKey);
                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    }
                } elseif ($period === 'yearly') {
                    if (preg_match('/(\d{4})/', $periodKey, $matches)) {
                        $periodKey = (string) intval($matches[1]);
                    } else {
                        $periodKey = (string) intval($periodKey);
                    }
                }

                if (!isset($groupedData[$periodKey])) {
                    $groupedData[$periodKey] = [];
                }
                $groupedData[$periodKey][] = $row;
            }

            // Generate all periods in range
            $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);

            // Format response based on period, ensuring all periods are included
            $formattedData = [];
            foreach ($allPeriods as $periodValue) {
                $periodKeyStr = trim((string) $periodValue);
                if (isset($groupedData[$periodKeyStr])) {
                    $formattedData[] = [
                        $dateLabel => $periodKeyStr,
                        'data' => $groupedData[$periodKeyStr]
                    ];
                } else {
                    // Add period with empty data array
                    $formattedData[] = [
                        $dateLabel => $periodKeyStr,
                        'data' => []
                    ];
                }
            }

            return response()->json([
                'data' => $formattedData,
                'date_range' => [
                    'from' => $dateRange['date_from'],
                    'to' => $dateRange['date_to']
                ],
                'period' => $period,
                'grouping' => $dateLabel,
                'calculation_method' => 'traditional'
            ]);
        }
    }

    /**
     * CHART 3: Stock Movement Trend
     * Area chart showing receipt, shipment, and net movement
     * DATE FILTER: Applied to entire trend
     * Uses WarehouseStockSummary for trend data
     */
    public function stockMovementTrend(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        // Map period to granularity
        $granularity = match($period) {
            'yearly' => 'yearly',
            'monthly' => 'monthly',
            default => 'daily'
        };

        // Build date/period expression based on database driver
        $dateFormat = $this->getDateFormatByPeriod($period);

        // For monthly and yearly, always aggregate from daily data
        $queryGranularity = 'daily';

        $query = WarehouseStockSummary::where('warehouse', $warehouse)
            ->where('granularity', $queryGranularity)
            ->whereBetween('period_start', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ]);

        $data = $query->selectRaw("
                $dateFormat as period,
                SUM(onhand_total) as total_onhand,
                SUM(receipt_total) as total_receipt,
                SUM(issue_total) as total_shipment,
                SUM(receipt_total - issue_total) as net_movement
            ")
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get()
            ->map(function ($item) use ($period) {
                // Normalize period format
                $rawPeriod = $item->period ?? '';
                $periodKey = trim((string) $rawPeriod);

                if ($period === 'daily') {
                    try {
                        $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                } elseif ($period === 'monthly') {
                    if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                        $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        try {
                            $parsed = Carbon::parse($periodKey);
                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    }
                } elseif ($period === 'yearly') {
                    if (preg_match('/(\d{4})/', $periodKey, $matches)) {
                        $periodKey = (string) intval($matches[1]);
                    } else {
                        $periodKey = (string) intval($periodKey);
                    }
                }

                return (object) [
                    'period' => $periodKey,
                    'total_onhand' => $item->total_onhand ?? 0,
                    'total_receipt' => $item->total_receipt ?? 0,
                    'total_shipment' => $item->total_shipment ?? 0,
                    'net_movement' => $item->net_movement ?? 0,
                ];
            })
            ->keyBy('period');

        // Generate all periods in range
        $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);

        // Fill missing periods with zero values
        $trendData = collect($allPeriods)->map(function ($periodValue) use ($data) {
            $existing = $data->get($periodValue);

            if ($existing) {
                return [
                    'period' => $periodValue,
                    'total_onhand' => (string) ($existing->total_onhand ?? 0),
                    'total_receipt' => (string) ($existing->total_receipt ?? 0),
                    'total_shipment' => (string) ($existing->total_shipment ?? 0),
                    'net_movement' => (string) ($existing->net_movement ?? 0),
                ];
            } else {
                return [
                    'period' => $periodValue,
                    'total_onhand' => '0',
                    'total_receipt' => '0',
                    'total_shipment' => '0',
                    'net_movement' => '0',
                ];
            }
        })->values();

        // Get current total onhand from snapshot or current data
        $snapshotDate = $this->getSnapshotDate($request, $period);
        $currentOnhand = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->where('warehouse', $warehouse)->sum('onhand')
            : StockByWh::where('warehouse', $warehouse)->sum('onhand');

        return response()->json([
            'trend_data' => $trendData,
            'period' => $period,
            'granularity' => $granularity,
            'current_total_onhand' => round($currentOnhand, 2),
            'snapshot_date' => $snapshotDate,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 4: Top 15 Items Without Issue Activity
     * Highlights SKUs with large on-hand quantity but little to no "Issue" transactions
     * DATE FILTER: Applied to transaction summary (default 90 days)
     * Uses snapshot data for historical stock view
     */
    public function topCriticalItems(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 90);
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');

        $dateFilterClause = '';
        $dateFilterParams = [];

        if ($dateFromInput || $dateToInput) {
            $dateFilterClause = "
                AND COALESCE(t.trans_date2, t.trans_date) >= ?
                AND COALESCE(t.trans_date2, t.trans_date) <= ?
            ";
            $dateFilterParams = [$dateRange['date_from'], $dateRange['date_to']];
        }

        $issueSummaryParams = array_merge([$warehouse], $dateFilterParams);
        $data = DB::connection('erp')->select("
            WITH issue_summary AS (
                SELECT
                    t.partno,
                    COUNT(
                        CASE
                            WHEN t.trans_type = 'Issue'
                                OR (t.shipment IS NOT NULL AND t.shipment != '')
                            THEN 1
                        END
                    ) as issue_count,
                    SUM(CASE
                        WHEN t.trans_type = 'Issue'
                            OR (t.shipment IS NOT NULL AND t.shipment != '')
                        THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2))
                        ELSE 0
                    END) as total_issue_qty,
                    MAX(CASE
                        WHEN t.trans_type = 'Issue'
                            OR (t.shipment IS NOT NULL AND t.shipment != '')
                        THEN COALESCE(t.trans_date2, t.trans_date)
                        ELSE NULL
                    END) as last_issue_date
                FROM inventory_transaction t
                WHERE t.warehouse = ?
                    $dateFilterClause
                GROUP BY t.partno
            )
            SELECT TOP 15
                s.partno,
                s.[desc] as description,
                s.product_type,
                CAST(s.onhand AS DECIMAL(18,2)) as onhand,
                CAST(ISNULL(isum.total_issue_qty, 0) AS DECIMAL(18,2)) as total_issue_qty,
                ISNULL(isum.issue_count, 0) as issue_count,
                isum.last_issue_date,
                CAST(s.onhand - ISNULL(isum.total_issue_qty, 0) AS DECIMAL(18,2)) as qty_gap,
                CASE
                    WHEN ISNULL(isum.issue_count, 0) = 0 THEN 'No Issue Activity'
                    WHEN ISNULL(isum.issue_count, 0) <= 2 THEN 'Low Issue Activity'
                    ELSE 'Has Issue Activity'
                END as activity_flag
            FROM stockbywh s
            LEFT JOIN issue_summary isum ON s.partno = isum.partno
            WHERE s.warehouse = ?
                AND s.onhand > 0
            ORDER BY qty_gap DESC, s.onhand DESC
        ", array_merge($issueSummaryParams, [$warehouse]));

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 5: Top 15 Most Active Items & Top 15 Most Non-Active Items
     * Horizontal bar chart with high transaction frequency + items with oldest last transaction
     * DATE FILTER: Applied to transaction analysis
     * Uses snapshot data for historical stock view
     */
    public function mostActiveItems(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        // Query for most active items (highest transaction count in date range)
        $activeData = DB::connection('erp')->select("
            SELECT TOP 15
                t.partno,
                LEFT(t.part_desc, 30) as description,
                s.product_type,
                COUNT(t.trans_id) as trans_count,
                SUM(CASE WHEN t.receipt IS NOT NULL AND t.receipt != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_receipt,
                SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                s.onhand as current_onhand,
                s.safety_stock,
                MAX(t.trans_date) as last_trans_date,
                CASE
                    WHEN s.onhand < s.safety_stock THEN 'At Risk'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN COUNT(t.trans_id) >= 20 AND s.onhand < s.safety_stock THEN 'High Risk'
                    WHEN COUNT(t.trans_id) >= 20 THEN 'High Activity'
                    WHEN COUNT(t.trans_id) >= 10 THEN 'Medium Activity'
                    ELSE 'Low Activity'
                END as activity_level
            FROM inventory_transaction t
            LEFT JOIN stockbywh s ON t.partno = s.partno AND t.warehouse = s.warehouse
            WHERE t.warehouse = ?
                AND t.trans_date >= ?
                AND t.trans_date <= ?
            GROUP BY t.partno, t.part_desc, s.product_type, s.onhand, s.safety_stock, s.max_stock
            ORDER BY COUNT(t.trans_id) DESC
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        // Query for most non-active items (items with oldest last transaction date)
        $nonActiveData = DB::connection('erp')->select("
            WITH last_transaction AS (
                SELECT
                    partno,
                    MAX(trans_date) as last_trans_date
                FROM inventory_transaction
                WHERE warehouse = ?
                GROUP BY partno
            )
            SELECT TOP 15
                s.partno,
                LEFT(COALESCE(NULLIF(s.partname, ''), s.[desc]), 30) as description,
                s.product_type,
                0 as trans_count,
                0 as total_receipt,
                0 as total_shipment,
                s.onhand as current_onhand,
                s.safety_stock,
                ISNULL(lt.last_trans_date, '1900-01-01') as last_trans_date,
                CASE
                    WHEN s.onhand < s.safety_stock THEN 'At Risk'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -6, GETDATE()) AND s.onhand < s.safety_stock THEN 'Critical Inactive'
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -6, GETDATE()) THEN 'Long Inactive'
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -3, GETDATE()) THEN 'Moderately Inactive'
                    ELSE 'Recently Inactive'
                END as activity_level,
                DATEDIFF(day, ISNULL(lt.last_trans_date, '1900-01-01'), GETDATE()) as days_since_last_trans
            FROM stockbywh s
            LEFT JOIN last_transaction lt ON s.partno = lt.partno
            WHERE s.warehouse = ?
                AND s.onhand > 0
            ORDER BY ISNULL(lt.last_trans_date, '1900-01-01') ASC
        ", [$warehouse, $warehouse]);

        return response()->json([
            'most_active_items' => $activeData,
            'most_non_active_items' => $nonActiveData,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 6: Stock & Activity by Product Type
     * Combo chart (columns + lines)
     * DATE FILTER: Applied to transaction counts and turnover calculation
     * Uses snapshot data for historical stock view
     */
    public function stockActivityByProductType(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        $dateFilter = $this->buildDateFilter('t', $dateRange);

        // Use snapshot table if available
        $stockTable = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $stockConnection = $snapshotDate ? null : 'erp';

        $whereConditions = ["s.warehouse = ?"];
        $params = [$warehouse];

        if ($snapshotDate) {
            $whereConditions[] = "s.snapshot_date = ?";
            $params[] = $snapshotDate;
        }

        if ($request->has('customer')) {
            $whereConditions[] = "s.customer = ?";
            $params[] = $request->customer;
        }

        if ($request->has('group_type_desc')) {
            $whereConditions[] = "s.group_type_desc = ?";
            $params[] = $request->group_type_desc;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT
                s.product_type,
                COUNT(DISTINCT s.partno) as sku_count,
                SUM(s.onhand) as total_onhand,
                SUM(s.safety_stock) as total_safety_stock,
                SUM(s.onhand - s.allocated) as total_available,
                COUNT(t.trans_id) as trans_count,
                COUNT(t.shipment) as total_shipment,
                CASE
                    WHEN SUM(s.onhand) > 0 THEN CAST(COUNT(t.shipment) / SUM(s.onhand) AS DECIMAL(10,2))
                    ELSE 0
                END as turnover_rate
            FROM {$stockTable} s
            LEFT JOIN inventory_transaction t
                ON s.partno = t.partno
                AND s.warehouse = t.warehouse
                AND {$dateFilter}
            WHERE {$whereClause}
            GROUP BY s.product_type
            ORDER BY SUM(s.onhand) DESC
        ";

        $data = $stockConnection
            ? DB::connection($stockConnection)->select($sql, $params)
            : DB::select($sql, $params);

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 7: Stock by Group Type - Donut Chart
     * Groups stock by group_type_desc with warehouse filter
     * Uses snapshot data for historical view
     * Optional filters: customer, group_type_desc
     */
    public function stockByGroupType(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->where('warehouse', $warehouse)
            : StockByWh::where('warehouse', $warehouse);

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }

        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $data = $query->select('group_type_desc')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
            ->whereNotNull('group_type_desc')
            ->groupBy('group_type_desc')
            ->orderBy('total_onhand', 'desc')
            ->get();

        $totalOnhand = $data->sum('total_onhand');
        $totalItems = $data->sum('total_items');

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_onhand' => $totalOnhand,
                'total_items' => $totalItems,
                'total_group_types' => $data->count()
            ],
            'warehouse' => $warehouse,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * CHART 7B: Stock by Customer - Donut Chart
     * Groups stock by customer with warehouse filter
     * Uses snapshot data for historical view
     * Optional filters: customer, group_type_desc
     */
    public function stockByCustomer(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->where('warehouse', $warehouse)
            : StockByWh::where('warehouse', $warehouse);

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }

        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $data = $query->select('customer')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
            ->whereNotNull('customer')
            ->groupBy('customer')
            ->orderBy('total_onhand', 'desc')
            ->get();

        $totalOnhand = $data->sum('total_onhand');
        $totalItems = $data->sum('total_items');

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_onhand' => $totalOnhand,
                'total_items' => $totalItems,
                'total_customers' => $data->count()
            ],
            'warehouse' => $warehouse,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * CHART 8: Receipt vs Shipment Trend (Weekly)
     * Clustered column chart with line
     * DATE FILTER: Applied to entire trend (default 90 days to show weekly properly)
     */
    public function receiptVsShipmentTrend(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 90, $period); // Default 90 days for weekly view

        $data = DB::connection('erp')->select("
            SELECT
                DATEPART(WEEK, trans_date) as week_num,
                DATEPART(YEAR, trans_date) as year,
                MIN(CAST(trans_date AS DATE)) as week_start,
                SUM(CASE WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_receipt,
                SUM(CASE WHEN shipment IS NOT NULL AND shipment != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                SUM(CASE
                    WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    WHEN shipment IS NOT NULL AND shipment != '' THEN -TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    ELSE 0
                END) as net_movement,
                COUNT(trans_id) as trans_count
            FROM inventory_transaction
            WHERE warehouse = ?
                AND trans_date >= ?
                AND trans_date <= ?
            GROUP BY DATEPART(WEEK, trans_date), DATEPART(YEAR, trans_date)
            ORDER BY year, week_num
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 9: Transaction Type Distribution
     * Stacked bar chart
     * DATE FILTER: Applied to transaction analysis
     */
    public function transactionTypeDistribution(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        $data = DB::connection('erp')->select("
            SELECT
                trans_type,
                order_type,
                COUNT(trans_id) as trans_count,
                SUM(ABS(TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)))) as total_qty,
                COUNT(DISTINCT partno) as unique_parts,
                COUNT(DISTINCT [user]) as unique_users
            FROM inventory_transaction
            WHERE warehouse = ?
                AND trans_date >= ?
                AND trans_date <= ?
            GROUP BY trans_type, order_type
            ORDER BY trans_count DESC
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to']
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 10: Fast vs Slow Moving Items
     * Scatter plot data with quadrant classification
     * DATE FILTER: Applied to transaction frequency analysis
     * Uses snapshot data for historical stock view
     */
    public function fastVsSlowMoving(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        $dateFilter = $this->buildDateFilter('t', $dateRange);

        // Use snapshot table if available
        $stockTable = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $stockConnection = $snapshotDate ? null : 'erp';

        $whereConditions = ["s.warehouse = ?"];
        $params = [$warehouse];

        if ($snapshotDate) {
            $whereConditions[] = "s.snapshot_date = ?";
            $params[] = $snapshotDate;
        }

        if ($request->has('customer')) {
            $whereConditions[] = "s.customer = ?";
            $params[] = $request->customer;
        }

        if ($request->has('group_type_desc')) {
            $whereConditions[] = "s.group_type_desc = ?";
            $params[] = $request->group_type_desc;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT
                s.partno,
                LEFT(s.[desc], 25) as description,
                s.product_type,
                s.onhand,
                s.safety_stock,
                s.max_stock,
                (s.safety_stock - s.onhand) as gap_from_safety,
                COUNT(t.trans_id) as trans_count,
                SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                CASE
                    WHEN s.onhand > 0 THEN CAST(SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / s.onhand AS DECIMAL(10,2))
                    ELSE 0
                END as turnover_rate,
                CASE
                    WHEN s.onhand < s.min_stock THEN 'Critical'
                    WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN COUNT(t.trans_id) >= 15 AND s.onhand >= s.safety_stock THEN 'Healthy'
                    WHEN COUNT(t.trans_id) >= 15 AND s.onhand < s.safety_stock THEN 'High Risk'
                    WHEN COUNT(t.trans_id) < 5 AND s.onhand > s.max_stock THEN 'Slow Moving'
                    WHEN COUNT(t.trans_id) < 5 AND s.onhand < s.safety_stock THEN 'Review'
                    ELSE 'Normal'
                END as classification
            FROM {$stockTable} s
            LEFT JOIN inventory_transaction t
                ON s.partno = t.partno
                AND s.warehouse = t.warehouse
                AND {$dateFilter}
            WHERE {$whereClause}
            GROUP BY s.partno, s.[desc], s.product_type, s.onhand, s.safety_stock, s.min_stock, s.max_stock
            HAVING COUNT(t.trans_id) > 0 OR s.onhand > 0
            ORDER BY COUNT(t.trans_id) DESC
        ";

        $data = $stockConnection
            ? DB::connection($stockConnection)->select($sql, $params)
            : DB::select($sql, $params);

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 11: Stock Turnover Rate (Top 20)
     * Horizontal bar chart with color gradient
     * DATE FILTER: Applied to shipment calculation for turnover
     * Uses snapshot data for historical stock view
     */
    public function stockTurnoverRate(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);
        $days = $dateRange['days_diff'];

        // Use snapshot table if available
        $stockTable = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $stockConnection = $snapshotDate ? null : 'erp';

        $whereConditions = ["s.warehouse = ?"];
        $params = [$warehouse];

        if ($snapshotDate) {
            $whereConditions[] = "s.snapshot_date = ?";
            $params[] = $snapshotDate;
        }

        if ($request->has('customer')) {
            $whereConditions[] = "s.customer = ?";
            $params[] = $request->customer;
        }

        if ($request->has('group_type_desc')) {
            $whereConditions[] = "s.group_type_desc = ?";
            $params[] = $request->group_type_desc;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $allParams = array_merge([$days, $days, $days, $dateRange['date_from'], $dateRange['date_to']], $params);

        $sql = "
            WITH turnover_calc AS (
                SELECT
                    s.partno,
                    s.[desc],
                    s.product_type,
                    s.onhand,
                    s.safety_stock,
                    SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                    SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ? as avg_daily_shipment,
                    CASE
                        WHEN s.onhand > 0 THEN CAST(SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / s.onhand AS DECIMAL(10,2))
                        ELSE 0
                    END as turnover_rate,
                    CASE
                        WHEN SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ? > 0 THEN CAST(s.onhand / (SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ?) AS INT)
                        ELSE 999
                    END as days_of_stock
                FROM {$stockTable} s
                LEFT JOIN inventory_transaction t
                    ON s.partno = t.partno
                    AND s.warehouse = t.warehouse
                    AND t.trans_date >= ?
                    AND t.trans_date <= ?
                WHERE {$whereClause}
                GROUP BY s.partno, s.[desc], s.product_type, s.onhand, s.safety_stock
            )
            SELECT TOP 20
                partno,
                LEFT([desc], 30) as description,
                product_type,
                onhand,
                safety_stock,
                total_shipment,
                turnover_rate,
                days_of_stock,
                CASE
                    WHEN turnover_rate >= 1.5 THEN 'Fast Moving'
                    WHEN turnover_rate >= 0.5 THEN 'Medium Moving'
                    ELSE 'Slow Moving'
                END as movement_category,
                CASE
                    WHEN days_of_stock < 30 AND onhand < safety_stock THEN 'Urgent Reorder'
                    WHEN days_of_stock < 30 THEN 'Monitor Closely'
                    WHEN days_of_stock > 90 THEN 'Potential Overstock'
                    ELSE 'Normal'
                END as recommendation
            FROM turnover_calc
            WHERE total_shipment > 0
            ORDER BY turnover_rate DESC
        ";

        $data = $stockConnection
            ? DB::connection($stockConnection)->select($sql, $allParams)
            : DB::select($sql, $allParams);

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $days
            ],
            'period' => $period
        ]);
    }

    /**
     * CHART 12: Recent Transaction History
     * Data table with pagination
     */
    public function recentTransactionHistory(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 50))); // Limit to max 100 per page
        $offset = ($page - 1) * $perPage;

        // Filters
        $transType = $request->input('trans_type');
        $user = $request->input('user');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $whereConditions = ["t.warehouse = ?"];
        $params = [$warehouse];

        if ($transType) {
            $whereConditions[] = "t.trans_type = ?";
            $params[] = $transType;
        }

        if ($user) {
            $whereConditions[] = "t.[user] = ?";
            $params[] = $user;
        }

        if ($dateFrom) {
            $whereConditions[] = "t.trans_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "t.trans_date <= ?";
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $totalQuery = "
            SELECT COUNT(*) as total
            FROM inventory_transaction t
            WHERE $whereClause
        ";
        $total = DB::connection('erp')->select($totalQuery, $params)[0]->total;

        // Get paginated data
        $dataParams = array_merge($params, [$offset, $perPage]);
        $data = DB::connection('erp')->select("
            SELECT
                t.trans_date,
                t.trans_id,
                t.partno,
                t.part_desc,
                t.trans_type,
                t.order_type,
                t.order_no,
                t.receipt,
                t.shipment,
                TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) as qty,
                TRY_CAST(ISNULL(t.qty_hand, 0) AS DECIMAL(18,2)) as qty_after_trans,
                s.onhand as current_onhand,
                (s.onhand - TRY_CAST(ISNULL(t.qty_hand, 0) AS DECIMAL(18,2))) as variance,
                s.location,
                t.[user],
                t.lotno,
                CASE
                    WHEN t.receipt IS NOT NULL AND t.receipt != '' THEN 'IN'
                    WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN 'OUT'
                    ELSE 'ADJUSTMENT'
                END as movement_type
            FROM inventory_transaction t
            LEFT JOIN stockbywh s ON t.partno = s.partno AND t.warehouse = s.warehouse
            WHERE $whereClause
            ORDER BY t.trans_date DESC, t.trans_id DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ", $dataParams);

        return response()->json([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }

    /**
     * Get all dashboard data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'kpi' => $this->comprehensiveKpi($request)->getData(true),
            'stock_health' => $this->stockHealthDistribution($request)->getData(true),
            'movement_trend' => $this->stockMovementTrend($request)->getData(true),
            'critical_items' => $this->topCriticalItems($request)->getData(true),
            'active_items' => $this->mostActiveItems($request)->getData(true),
            'product_type_analysis' => $this->stockActivityByProductType($request)->getData(true),
            'group_type_analysis' => $this->stockByGroupType($request)->getData(true),
            'customer_analysis' => $this->stockByCustomer($request)->getData(true),
            'receipt_shipment_trend' => $this->receiptVsShipmentTrend($request)->getData(true),
            'transaction_types' => $this->transactionTypeDistribution($request)->getData(true),
            'fast_slow_moving' => $this->fastVsSlowMoving($request)->getData(true),
            'turnover_rate' => $this->stockTurnoverRate($request)->getData(true),
            'stock_level_table' => $this->stockLevelTable($request)->getData(true)
            // Note: Recent transactions excluded from bulk call due to pagination
        ]);
    }

    /**
     * TABLE: Stock Level Detail
     * Provides paginated table grouped by group_type_desc
     * Uses snapshot data for historical view
     */
    public function stockLevelTable(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        $statusFilter = $request->input('status');
        $search = $request->input('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $statusCase = "
            CASE
                WHEN s.onhand < s.min_stock THEN 'Critical'
                WHEN s.onhand < s.safety_stock THEN 'Low'
                WHEN s.onhand > s.max_stock THEN 'Overstock'
                ELSE 'Normal'
            END
        ";

        // Use snapshot table if available
        $tableName = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $connection = $snapshotDate ? null : 'erp';

        // Build base query with filters
        $baseQuery = $connection
            ? DB::connection($connection)->table($tableName . ' as s')
            : DB::table($tableName . ' as s');

        $baseQuery->where('s.warehouse', $warehouse)
            ->whereNotNull('s.group_type_desc');

        if ($snapshotDate) {
            $baseQuery->where('s.snapshot_date', $snapshotDate);
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('s.partno', 'LIKE', "%{$search}%")
                    ->orWhere('s.partname', 'LIKE', "%{$search}%")
                    ->orWhereRaw('s.[desc] LIKE ?', ["%{$search}%"])
                    ->orWhere('s.group_type_desc', 'LIKE', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $baseQuery->whereRaw("$statusCase = ?", [$statusFilter]);
        }

        // Apply filters
        if ($request->has('customer')) {
            $baseQuery->where('s.customer', $request->customer);
        }

        // Get total distinct group_type_desc count
        $total = (clone $baseQuery)
            ->distinct()
            ->count('s.group_type_desc');

        // Get grouped data
        $data = (clone $baseQuery)
            ->selectRaw("
                s.group_type_desc,
                COUNT(DISTINCT s.partno) as total_items,
                CAST(SUM(s.onhand) AS DECIMAL(18,2)) as total_onhand,
                CAST(SUM(s.min_stock) AS DECIMAL(18,2)) as total_min_stock,
                CAST(SUM(s.safety_stock) AS DECIMAL(18,2)) as total_safety_stock,
                CAST(SUM(s.max_stock) AS DECIMAL(18,2)) as total_max_stock,
                COUNT(CASE WHEN ($statusCase) = 'Critical' THEN 1 END) as critical_count,
                COUNT(CASE WHEN ($statusCase) = 'Low' THEN 1 END) as low_count,
                COUNT(CASE WHEN ($statusCase) = 'Normal' THEN 1 END) as normal_count,
                COUNT(CASE WHEN ($statusCase) = 'Overstock' THEN 1 END) as overstock_count,
                CAST(SUM(s.onhand - s.safety_stock) AS DECIMAL(18,2)) as gap_from_safety
            ")
            ->groupBy('s.group_type_desc')
            ->orderBy('s.group_type_desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Stock Use Planning: Calculate estimatedConsumption and daily_use based on DailyUseWh
        $planDate = $request->input('plan_date');
        $planDateFrom = $request->input('date_from');
        $planDateTo = $request->input('date_to');
        $estimatedConsumptionMap = [];
        $dailyUseMap = [];

        // Determine plan_date filtering (exact or range)
        $useExactPlanDate = !empty($planDate);
        $usePlanDateRange = !$useExactPlanDate && ($planDateFrom || $planDateTo);

        if ($useExactPlanDate || $usePlanDateRange) {
            try {
                $planDateParsed = null;
                $planDateRange = null;

                if ($useExactPlanDate) {
                    $planDateParsed = Carbon::parse($planDate)->format('Y-m-d');
                } elseif ($usePlanDateRange) {
                    // Use date range when plan_date not provided
                    $from = $planDateFrom ? Carbon::parse($planDateFrom)->format('Y-m-d') : null;
                    $to = $planDateTo ? Carbon::parse($planDateTo)->format('Y-m-d') : null;
                    // Fall back to dateRange (already computed) if not provided
                    if (!$from) {
                        $from = Carbon::parse(substr($dateRange['date_from'], 0, 10))->format('Y-m-d');
                    }
                    if (!$to) {
                        $to = Carbon::parse(substr($dateRange['date_to'], 0, 10))->format('Y-m-d');
                    }
                    $planDateRange = [$from, $to];
                }

                // Get all DailyUseWh data for the plan_date
                $dailyUseQuery = DailyUseWh::whereNotNull('partno');

                if ($useExactPlanDate) {
                    $dailyUseQuery->where('plan_date', $planDateParsed);
                } elseif ($planDateRange) {
                    $dailyUseQuery->whereBetween('plan_date', $planDateRange);
                }

                $dailyUseData = $dailyUseQuery->get();

                if ($dailyUseData->isNotEmpty()) {
                    // Get all partno and their group_type_desc from StockByWh
                    // Use the same base query to ensure consistency
                    $stockQuery = $connection
                        ? DB::connection($connection)->table($tableName . ' as s')
                        : DB::table($tableName . ' as s');

                    $stockQuery->where('s.warehouse', $warehouse)
                        ->whereNotNull('s.group_type_desc');

                    if ($snapshotDate) {
                        $stockQuery->where('s.snapshot_date', $snapshotDate);
                    }

                    // Apply same filters as baseQuery
                    if ($search) {
                        $stockQuery->where(function ($q) use ($search) {
                            $q->where('s.partno', 'LIKE', "%{$search}%")
                                ->orWhere('s.partname', 'LIKE', "%{$search}%")
                                ->orWhereRaw('s.[desc] LIKE ?', ["%{$search}%"])
                                ->orWhere('s.group_type_desc', 'LIKE', "%{$search}%");
                        });
                    }

                    if ($request->has('customer')) {
                        $stockQuery->where('s.customer', $request->customer);
                    }

                    $stockItems = $stockQuery->select('s.partno', 's.group_type_desc')
                        ->distinct()
                        ->get();

                    // Build mapping: partno (as string) -> array of group_type_desc
                    $partnoGroupMap = [];
                    foreach ($stockItems as $item) {
                        // Convert partno to string for consistent matching
                        $partno = trim((string) $item->partno);
                        if ($partno !== '') {
                            if (!isset($partnoGroupMap[$partno])) {
                                $partnoGroupMap[$partno] = [];
                            }
                            if (!in_array($item->group_type_desc, $partnoGroupMap[$partno])) {
                                $partnoGroupMap[$partno][] = $item->group_type_desc;
                            }
                        }
                    }

                    // Calculate total daily_use per group_type_desc
                    $groupDailyUseMap = [];
                    foreach ($dailyUseData as $dailyUseItem) {
                        // Convert partno to string for matching
                        $partno = trim((string) ($dailyUseItem->partno ?? ''));
                        $dailyUse = (int) ($dailyUseItem->daily_use ?? 0);

                        // Default to 0 if daily_use is null or invalid
                        if ($dailyUse < 0) {
                            $dailyUse = 0;
                        }

                        if ($partno !== '' && isset($partnoGroupMap[$partno])) {
                            foreach ($partnoGroupMap[$partno] as $groupTypeDesc) {
                                if (!isset($groupDailyUseMap[$groupTypeDesc])) {
                                    $groupDailyUseMap[$groupTypeDesc] = 0;
                                }
                                $groupDailyUseMap[$groupTypeDesc] += $dailyUse;
                            }
                        }
                    }

                    // Calculate estimatedConsumption and store daily_use for each group_type_desc in the result
                    foreach ($data as $item) {
                        $groupTypeDesc = $item->group_type_desc;
                        $totalOnhand = (float) ($item->total_onhand ?? 0);
                        $totalDailyUse = (float) ($groupDailyUseMap[$groupTypeDesc] ?? 0);

                        // Store daily_use
                        $dailyUseMap[$groupTypeDesc] = $totalDailyUse;

                        // Calculate estimatedConsumption
                        if ($totalDailyUse > 0) {
                            $estimatedConsumption = round($totalOnhand / $totalDailyUse, 2);
                        } else {
                            $estimatedConsumption = 0;
                        }

                        $estimatedConsumptionMap[$groupTypeDesc] = $estimatedConsumption;
                    }
                }
            } catch (\Exception $e) {
                // If plan_date parsing fails, skip estimatedConsumption calculation
                // Log error if needed: \Log::error('Stock Use Planning error: ' . $e->getMessage());
            }
        }

        // Add estimatedConsumption and daily_use to each item
        $data = $data->map(function ($item) use ($estimatedConsumptionMap, $dailyUseMap) {
            $item->estimatedConsumption = (float) ($estimatedConsumptionMap[$item->group_type_desc] ?? 0);
            $item->daily_use = (float) ($dailyUseMap[$item->group_type_desc] ?? 0);
            return $item;
        });

        return response()->json([
            'data' => $data,
            'filters' => [
                'warehouse' => $warehouse,
                'status' => $statusFilter,
                'search' => $search,
                'customer' => $request->input('customer'),
                'plan_date' => $planDate,
                'plan_date_range' => $usePlanDateRange ? ($planDateRange ?? null) : null
            ],
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }

    /**
     * CHART 9: Stock Level by Customer - Table View
     * Provides paginated table grouped by customer
     * Uses snapshot data for historical view
     */
    public function stockLevelByCustomer(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        $statusFilter = $request->input('status');
        $search = $request->input('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $statusCase = "
            CASE
                WHEN s.onhand < s.min_stock THEN 'Critical'
                WHEN s.onhand < s.safety_stock THEN 'Low'
                WHEN s.onhand > s.max_stock THEN 'Overstock'
                ELSE 'Normal'
            END
        ";

        // Use snapshot table if available
        $tableName = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $connection = $snapshotDate ? null : 'erp';

        // Build base query with filters
        $baseQuery = $connection
            ? DB::connection($connection)->table($tableName . ' as s')
            : DB::table($tableName . ' as s');

        $baseQuery->where('s.warehouse', $warehouse)
            ->whereNotNull('s.customer');

        if ($snapshotDate) {
            $baseQuery->where('s.snapshot_date', $snapshotDate);
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('s.partno', 'LIKE', "%{$search}%")
                    ->orWhere('s.partname', 'LIKE', "%{$search}%")
                    ->orWhereRaw('s.[desc] LIKE ?', ["%{$search}%"])
                    ->orWhere('s.customer', 'LIKE', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $baseQuery->whereRaw("$statusCase = ?", [$statusFilter]);
        }

        // Apply filters
        if ($request->has('group_type_desc')) {
            $baseQuery->where('s.group_type_desc', $request->group_type_desc);
        }

        // Get total distinct customer count
        $total = (clone $baseQuery)
            ->distinct()
            ->count('s.customer');

        // Get grouped data
        $data = (clone $baseQuery)
            ->selectRaw("
                s.customer,
                COUNT(DISTINCT s.partno) as total_items,
                CAST(SUM(s.onhand) AS DECIMAL(18,2)) as total_onhand,
                CAST(SUM(s.min_stock) AS DECIMAL(18,2)) as total_min_stock,
                CAST(SUM(s.safety_stock) AS DECIMAL(18,2)) as total_safety_stock,
                CAST(SUM(s.max_stock) AS DECIMAL(18,2)) as total_max_stock,
                COUNT(CASE WHEN ($statusCase) = 'Critical' THEN 1 END) as critical_count,
                COUNT(CASE WHEN ($statusCase) = 'Low' THEN 1 END) as low_count,
                COUNT(CASE WHEN ($statusCase) = 'Normal' THEN 1 END) as normal_count,
                COUNT(CASE WHEN ($statusCase) = 'Overstock' THEN 1 END) as overstock_count,
                CAST(SUM(s.onhand - s.safety_stock) AS DECIMAL(18,2)) as gap_from_safety
            ")
            ->groupBy('s.customer')
            ->orderBy('s.customer')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'filters' => [
                'warehouse' => $warehouse,
                'status' => $statusFilter,
                'search' => $search,
                'group_type_desc' => $request->input('group_type_desc')
            ],
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }
}
