<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
     * Chart 1.1: Stock Level Overview - KPI Cards
     */
    public function stockLevelOverview(): JsonResponse
    {
        $warehouses = $this->resolveWarehouses();

        // Use DB::connection to ensure we're using the ERP database
        $totalItems = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->whereNotNull('partno')
            ->distinct()
            ->count('partno');

        $baseQuery = StockByWh::whereIn('warehouse', $warehouses);

        // Count distinct items that have onhand > 0
        $totalOnhand = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->where('onhand', '>', 0)
            ->distinct()
            ->count('partno');

        $itemsBelowSafetyStock = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->whereNotNull('partno')
            ->whereRaw('onhand < safety_stock')
            ->distinct()
            ->count('partno');

        $itemsAboveMaxStock = DB::connection('erp')->table('stockbywh')
            ->whereIn('warehouse', $warehouses)
            ->whereNotNull('partno')
            ->whereRaw('onhand > max_stock')
            ->distinct()
            ->count('partno');

        $avgStockLevel = (clone $baseQuery)->avg('onhand');

        return response()->json([
            'total_onhand' => $totalOnhand,
            'items_below_safety_stock' => $itemsBelowSafetyStock,
            'items_above_max_stock' => $itemsAboveMaxStock,
            'total_items' => $totalItems,
            'average_stock_level' => round($avgStockLevel, 2)
        ]);
    }

    /**
     * Chart 1.2: Stock Health by Warehouse - Stacked Bar Chart
     */
    public function stockHealthByWarehouse(Request $request): JsonResponse
    {
        $query = StockByWh::query();

        // Filter allowed warehouses (supports RM/FG aliases)
        $query->whereIn('warehouse', $this->resolveWarehouses($request));

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

        $data = $query->select('warehouse')
            ->selectRaw('
                COUNT(CASE WHEN onhand < min_stock THEN 1 END) as critical,
                COUNT(CASE WHEN onhand >= min_stock AND onhand < safety_stock THEN 1 END) as low,
                COUNT(CASE WHEN onhand >= safety_stock AND onhand <= max_stock THEN 1 END) as normal,
                COUNT(CASE WHEN onhand > max_stock THEN 1 END) as overstock
            ')
            ->groupBy('warehouse')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 1.3: Top 20 Critical Items - Data Table
     */
    public function topCriticalItems(Request $request): JsonResponse
    {
        $query = StockByWh::query();

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
            ->whereIn('warehouse', $this->resolveWarehouses($request))
            ->selectRaw('(safety_stock - onhand) as gap')
            ->orderByRaw('(safety_stock - onhand) DESC')
            ->limit(20)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 1.4: Stock Distribution by Product Type - Treemap
     */
    public function stockDistributionByProductType(): JsonResponse
    {
        $data = StockByWh::select([
                'product_type',
                'model',
                'partno',
                'desc',
                'onhand',
                'allocated'
            ])
            ->selectRaw('(onhand - allocated) as available')
            ->whereIn('warehouse', $this->resolveWarehouses())
            ->orderBy('onhand', 'desc')
            ->get()
            ->groupBy(['product_type', 'model']);

        return response()->json($data);
    }

    /**
     * Chart 1.5: Stock by Customer - Donut Chart
     */
    public function stockByCustomer(): JsonResponse
    {
        $warehouses = $this->resolveWarehouses();

        $data = DB::connection('erp')->table('stockbywh')
            ->select('group_type_desc')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
            ->whereIn('warehouse', $warehouses)
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
            'warehouses' => $warehouses
        ]);
    }

    /**
     * Chart 1.6: Inventory Availability vs Demand - Combo Chart
     */
    public function inventoryAvailabilityVsDemand(Request $request): JsonResponse
    {
        $warehouses = $this->resolveWarehouses($request);
        $groupBy = $request->get('group_by', 'warehouse');
        if (!in_array($groupBy, ['warehouse', 'group'], true)) {
            $groupBy = 'warehouse';
        }

        $query = StockByWh::query()->whereIn('warehouse', $warehouses);

        $query->groupBy($groupBy);

        $data = $query->select($groupBy)
            ->selectRaw('SUM(onhand) as total_onhand')
            // ->selectRaw('SUM(allocated) as total_allocated')
            // ->selectRaw('SUM(onorder) as total_onorder')
            ->selectRaw('((SUM(onhand) - SUM(allocated)) + SUM(onorder)) as available_to_promise')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 1.7: Stock Movement Trend - Area Chart
     * Note: This requires historical data or snapshots
     */
    public function stockMovementTrend(Request $request): JsonResponse
    {
        // This would typically require a separate table with historical snapshots
        // For now, returning current data structure
        $warehouses = $this->resolveWarehouses($request);

        $query = StockByWh::query()->whereIn('warehouse', $warehouses);

        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        $data = $query->select([
                'warehouse',
                'partno',
                'desc',
                'onhand',
                'allocated'
            ])
            ->selectRaw('(onhand - allocated) as available')
            ->get();

        return response()->json([
            'message' => 'Historical data required for trend analysis',
            'warehouses_filtered' => $warehouses,
            'total_records' => $data->count(),
            'current_data' => $data
        ]);
    }

    /**
     * Table: Stock Level Detail (All Warehouses)
     */
    public function stockLevelTable(Request $request): JsonResponse
    {
        $statusFilter = $request->input('status');
        $search = $request->input('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $warehouses = $this->resolveWarehouses($request);

        $statusCase = "
            CASE
                WHEN s.onhand < s.min_stock THEN 'Critical'
                WHEN s.onhand < s.safety_stock THEN 'Low'
                WHEN s.onhand > s.max_stock THEN 'Overstock'
                ELSE 'Normal'
            END
        ";

        $baseQuery = DB::connection('erp')
            ->table('stockbywh as s')
            ->whereIn('s.warehouse', $warehouses);

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
                'warehouses' => $warehouses
            ],
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
            'stock_level_overview' => $this->stockLevelOverview()->getData(true),
            'stock_health_by_warehouse' => $this->stockHealthByWarehouse($request)->getData(true),
            'top_critical_items' => $this->topCriticalItems($request)->getData(true),
            'stock_distribution_by_product_type' => $this->stockDistributionByProductType()->getData(true),
            'stock_by_customer' => $this->stockByCustomer()->getData(true),
            'inventory_availability_vs_demand' => $this->inventoryAvailabilityVsDemand($request)->getData(true),
            'stock_movement_trend' => $this->stockMovementTrend($request)->getData(true)
        ]);
    }
}
