<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use App\Models\StockByWhSnapshot;
use App\Models\WarehouseStockSummary;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard1Controller extends ApiController
{
    /**
     * Default warehouse list when no filter supplied.
     */
    private array $defaultWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

    /**
     * Resolve warehouse codes based on request input.
     */
    private function resolveWarehouses(?Request $request = null): array
    {
        $request = $request ?? request();
        $warehouseParam = $request->input('warehouse');

        $aliases = [
            'RM' => ['WHRM01', 'WHRM02', 'WHMT01'],
            'FG' => ['WHFG01', 'WHFG02'],
            'WHFG01' => ['WHFG01'],
            'WHFG02' => ['WHFG02']
        ];

        if (!$warehouseParam) {
            return $this->defaultWarehouses;
        }

        if (isset($aliases[$warehouseParam])) {
            return $aliases[$warehouseParam];
        }

        if (in_array($warehouseParam, $this->defaultWarehouses, true)) {
            return [$warehouseParam];
        }

        abort(400, 'Invalid warehouse parameter');
    }

    /**
     * Get date range parameters from request
     * Returns array with date_from and date_to based on period
     */
    private function getDateRange(Request $request, string $period = 'daily'): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!$dateFrom && !$dateTo) {
            // Default based on period
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
            $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
            $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();
        }

        return [
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'date_from_carbon' => $dateFrom,
            'date_to_carbon' => $dateTo,
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
        $dateRange = $this->getDateRange($request, $period);

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
        $dateRange = $this->getDateRange($request, $period);

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
     * Chart 1.1: Stock Level Overview - KPI Cards
     * Uses snapshot data for historical view
     */
    public function stockLevelOverview(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $baseQuery = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->whereIn('warehouse', $warehouses)
            : StockByWh::whereIn('warehouse', $warehouses);

        // Apply filters
        if ($request->has('customer')) {
            $baseQuery->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $baseQuery->where('group_type_desc', $request->group_type_desc);
        }

        $totalItems = (clone $baseQuery)
            ->whereNotNull('partno')
            ->distinct()
            ->count('partno');

        $totalOnhand = (clone $baseQuery)->sum('onhand');

        $itemsBelowSafetyStock = (clone $baseQuery)
            ->whereNotNull('partno')
            ->whereRaw('onhand < safety_stock')
            ->distinct()
            ->count('partno');

        $itemsAboveMaxStock = (clone $baseQuery)
            ->whereNotNull('partno')
            ->whereRaw('onhand > max_stock')
            ->distinct()
            ->count('partno');

        $avgStockLevel = (clone $baseQuery)->avg('onhand');

        return response()->json([
            'total_onhand' => round($totalOnhand, 2),
            'items_below_safety_stock' => $itemsBelowSafetyStock,
            'items_above_max_stock' => $itemsAboveMaxStock,
            'total_items' => $totalItems,
            'average_stock_level' => round($avgStockLevel, 2),
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Chart 1.2: Stock Health by Warehouse - Stacked Bar Chart
     * Uses snapshot data for historical view
     */
    public function stockHealthByWarehouse(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->whereIn('warehouse', $warehouses)
            : StockByWh::whereIn('warehouse', $warehouses);

        // Apply filters
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->has('group')) {
            $query->where('group', $request->group);
        }
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $data = $query->select('warehouse')
            ->selectRaw('
                COUNT(CASE WHEN onhand < min_stock THEN 1 END) as critical,
                COUNT(CASE WHEN onhand >= min_stock AND onhand < safety_stock THEN 1 END) as low,
                COUNT(CASE WHEN onhand >= safety_stock AND onhand <= max_stock THEN 1 END) as normal,
                COUNT(CASE WHEN onhand > max_stock THEN 1 END) as overstock
            ')
            ->groupBy('warehouse')
            ->get();

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Chart 1.3: Top 20 Critical Items - Data Table
     * Uses snapshot data for historical view
     */
    public function topCriticalItems(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->whereIn('warehouse', $warehouses)
            : StockByWh::whereIn('warehouse', $warehouses);

        // Apply status filter
        if ($request->has('status')) {
            switch ($request->status) {
                case 'critical':
                    $query->whereRaw('onhand < min_stock');
                    break;
                case 'low':
                    $query->whereRaw('onhand >= min_stock AND onhand < safety_stock');
                    break;
                case 'overstock':
                    $query->whereRaw('onhand > max_stock');
                    break;
            }
        }

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $data = $query->select([
                'warehouse',
                'partno',
                'desc',
                'onhand',
                'safety_stock',
                'min_stock',
                'max_stock',
                'location'
            ])
            ->selectRaw('(safety_stock - onhand) as gap')
            ->orderByRaw('(safety_stock - onhand) DESC')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Chart 1.4: Stock Distribution by Product Type - Treemap
     * Uses snapshot data for historical view
     */
    public function stockDistributionByProductType(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->whereIn('warehouse', $warehouses)
            : StockByWh::whereIn('warehouse', $warehouses);

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $data = $query->select([
                'product_type',
                'model',
                'partno',
                'desc',
                'onhand',
                'allocated'
            ])
            ->selectRaw('(onhand - allocated) as available')
            ->orderBy('onhand', 'desc')
            ->get()
            ->groupBy(['product_type', 'model']);

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Chart 1.5: Stock by Customer - Donut Chart
     * Uses snapshot data for historical view
     */
    public function stockByCustomer(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)
            : StockByWh::query();

        $query->whereIn('warehouse', $warehouses)
            ->whereNotNull('customer');

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
            'warehouses' => $warehouses,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Chart 1.6: Inventory Availability vs Demand - Combo Chart
     * Uses snapshot data for historical view
     */
    public function inventoryAvailabilityVsDemand(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);
        $groupBy = $request->get('group_by', 'warehouse');
        if (!in_array($groupBy, ['warehouse', 'group'], true)) {
            $groupBy = 'warehouse';
        }

        // Use snapshot if available, otherwise use current data
        $query = $snapshotDate
            ? StockByWhSnapshot::where('snapshot_date', $snapshotDate)->whereIn('warehouse', $warehouses)
            : StockByWh::whereIn('warehouse', $warehouses);

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $query->where('group_type_desc', $request->group_type_desc);
        }

        $query->groupBy($groupBy);

        $data = $query->select($groupBy)
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->selectRaw('SUM(onorder) as total_onorder')
            ->selectRaw('((SUM(onhand) - SUM(allocated)) + SUM(onorder)) as available_to_promise')
            ->get();

        return response()->json([
            'data' => $data,
            'snapshot_date' => $snapshotDate,
            'date_range' => $dateRange,
            'period' => $period
        ]);
    }

    /**
     * Get database driver name
     */
    private function getDatabaseDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Get date format expression based on period and database driver
     * Override parent method with specific implementation for WarehouseStockSummary
     * Uses 'period_start' as the date field
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
     * Generate all periods in the range based on period type
     */
    protected function generateAllPeriods(string $period, ?string $dateFrom, ?string $dateTo): array
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
     * Chart 1.7: Stock Movement Trend - Area Chart
     * Uses WarehouseStockSummary for trend data
     */
    public function stockMovementTrend(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);

        // Map period to granularity
        $granularity = match($period) {
            'yearly' => 'yearly',
            'monthly' => 'monthly',
            default => 'daily'
        };

        // Build date/period expression based on database driver
        $dateFormat = $this->getDateFormatByPeriod($period);

        // For monthly and yearly, always aggregate from daily data
        $queryGranularity = $period === 'daily' ? 'daily' : 'daily';

        $query = WarehouseStockSummary::whereIn('warehouse', $warehouses)
            ->where('granularity', $queryGranularity)
            ->whereBetween('period_start', [
                $dateRange['date_from_carbon']->startOfDay(),
                $dateRange['date_to_carbon']->endOfDay()
            ]);

        $records = $query->selectRaw("
                $dateFormat as period,
                SUM(onhand_total) as total_onhand,
                SUM(receipt_total) as total_receipt,
                SUM(issue_total) as total_issue,
                COUNT(DISTINCT warehouse) as warehouse_count
            ")
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        // Generate all periods in range for filling missing dates
        $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);

        // Normalize period_key format and create a keyed collection
        $dataByPeriod = $records->mapWithKeys(function ($item) use ($period) {
            // Get the raw period value
            $rawPeriod = $item->period ?? $item->getAttribute('period') ?? '';
            $periodKey = trim((string) $rawPeriod);

            // Normalize period_key to match generateAllPeriods format
            if ($period === 'daily') {
                try {
                    $periodKey = Carbon::parse($periodKey)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            } elseif ($period === 'monthly') {
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
            } elseif ($period === 'yearly') {
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

        // Fill missing periods with zero values
        $trendData = collect($allPeriods)->map(function ($periodValue) use ($dataByPeriod, $period) {
            $existing = $dataByPeriod->get($periodValue);

            if ($existing) {
                return [
                    'period' => $periodValue,
                    'total_onhand' => (string) ($existing->total_onhand ?? 0),
                    'total_receipt' => (string) ($existing->total_receipt ?? 0),
                    'total_issue' => (string) ($existing->total_issue ?? 0),
                    'warehouse_count' => (int) ($existing->warehouse_count ?? 0),
                ];
            } else {
                // Fill with zero values for missing periods
                return [
                    'period' => $periodValue,
                    'total_onhand' => '0',
                    'total_receipt' => '0',
                    'total_issue' => '0',
                    'warehouse_count' => 0,
                ];
            }
        })->values();

        return response()->json([
            'trend_data' => $trendData,
            'date_range' => $dateRange,
            'period' => $period,
            'granularity' => $granularity
        ]);
    }

    /**
     * Table: Stock Level Detail (All Warehouses)
     * Uses snapshot data for historical view
     */
    public function stockLevelTable(Request $request): JsonResponse
    {
        $statusFilter = $request->input('status');
        $search = $request->input('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $warehouses = $this->resolveWarehouses($request);
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, $period);
        $snapshotDate = $this->getSnapshotDate($request, $period);

        $statusCase = "
            CASE
                WHEN s.onhand < s.min_stock THEN 'Critical'
                WHEN s.onhand < s.safety_stock THEN 'Low'
                WHEN s.onhand > s.max_stock THEN 'Overstock'
                ELSE 'Normal'
            END
        ";

        // Use snapshot if available, otherwise use current data
        $tableName = $snapshotDate ? 'stock_by_wh_snapshots' : 'stockbywh';
        $connection = $snapshotDate ? null : 'erp';

        $baseQuery = $connection
            ? DB::connection($connection)->table($tableName . ' as s')
            : DB::table($tableName . ' as s');

        $baseQuery->whereIn('s.warehouse', $warehouses);

        if ($snapshotDate) {
            $baseQuery->where('s.snapshot_date', $snapshotDate);
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('s.partno', 'LIKE', "%{$search}%")
                    ->orWhere('s.partname', 'LIKE', "%{$search}%")
                    ->orWhereRaw('s.[desc] LIKE ?', ["%{$search}%"]);
            });
        }

        if ($statusFilter) {
            $baseQuery->whereRaw("$statusCase = ?", [$statusFilter]);
        }

        // Apply filters
        if ($request->has('customer')) {
            $baseQuery->where('s.customer', $request->customer);
        }
        if ($request->has('group_type_desc')) {
            $baseQuery->where('s.group_type_desc', $request->group_type_desc);
        }

        $total = (clone $baseQuery)->count();

        $data = (clone $baseQuery)
            ->selectRaw("
                s.partno,
                COALESCE(NULLIF(s.partname, ''), s.[desc]) as part_name,
                s.unit,
                s.warehouse,
                CAST(s.onhand AS DECIMAL(18,2)) as onhand,
                CAST(s.min_stock AS DECIMAL(18,2)) as min_stock,
                CAST(s.safety_stock AS DECIMAL(18,2)) as safety_stock,
                CAST(s.max_stock AS DECIMAL(18,2)) as max_stock,
                ($statusCase) as status
            ")
            ->orderByRaw("(s.safety_stock - s.onhand) DESC")
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'filters' => [
                'status' => $statusFilter,
                'search' => $search,
                'warehouses' => $warehouses,
                'customer' => $request->input('customer'),
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

    /**
     * Debug endpoint to check query and data
     */
    public function debugStockCount(): JsonResponse
    {
        $warehouses = $this->resolveWarehouses();

        // Method 1: Using distinct()->count() with ERP connection
        $method1 = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->whereNotNull('partno')
            ->distinct()
            ->count('partno');

        // Method 2: Using selectRaw with COUNT(DISTINCT) with ERP connection
        $method2 = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->selectRaw('COUNT(DISTINCT partno) as total')
            ->value('total');

        // Method 3: Check if there are NULL values
        $nullCount = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->whereNull('partno')
            ->count();

        // Method 4: Check total rows
        $totalRows = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->count();

        // Get sample of partno values to check for whitespace or special characters
        $samplePartno = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->select('partno')
            ->distinct()
            ->limit(10)
            ->pluck('partno');

        return response()->json([
            'method1_distinct_count' => $method1,
            'method2_count_distinct' => $method2,
            'null_partno_count' => $nullCount,
            'total_rows' => $totalRows,
            'sample_partno' => $samplePartno,
            'difference' => 6034 - $method2
        ]);
    }

    /**
     * Get all dashboard 1 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'stock_level_overview' => $this->stockLevelOverview($request)->getData(true),
            'stock_health_by_warehouse' => $this->stockHealthByWarehouse($request)->getData(true),
            'top_critical_items' => $this->topCriticalItems($request)->getData(true),
            'stock_distribution_by_product_type' => $this->stockDistributionByProductType($request)->getData(true),
            'stock_by_customer' => $this->stockByCustomer($request)->getData(true),
            'inventory_availability_vs_demand' => $this->inventoryAvailabilityVsDemand($request)->getData(true),
            'stock_movement_trend' => $this->stockMovementTrend($request)->getData(true)
        ]);
    }
}
