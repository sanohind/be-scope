<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard1Controller extends ApiController
{
    /**
     * Chart 1.1: Stock Level Overview - KPI Cards
     */
    public function stockLevelOverview(): JsonResponse
    {
        $warehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

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

        // Filter only specific warehouses
        $query->whereIn('warehouse', ['WHRM01', 'WHRM02', 'WHFG01', 'WHFG02', 'WHMT01']);

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
            ->whereIn('warehouse', ['WHRM01', 'WHRM02', 'WHFG01', 'WHFG02', 'WHMT01'])
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
            ->whereIn('warehouse', ['WHRM01', 'WHRM02', 'WHFG01', 'WHFG02', 'WHMT01'])
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
        $query = StockByWh::query();
        
        $data = StockByWh::select('customer')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
            ->whereIn('warehouse', ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'])
            ->groupBy('customer')
            ->orderBy('total_onhand', 'desc')
            ->get();

        $totalOnhand = $data->sum('total_onhand');
        $totalItems = $data->sum('total_items');

        return response()->json([
            'data' => $data,
            'total_onhand' => $totalOnhand,
            'total_items' => $totalItems
        ]);
    }

    /**
     * Chart 1.6: Inventory Availability vs Demand - Combo Chart
     */
    public function inventoryAvailabilityVsDemand(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'warehouse'); 

        $query = StockByWh::query();

        // Filter hanya warehouse tertentu
        if ($groupBy === 'warehouse') {
            $query->whereIn('warehouse', ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02']);
            $query->groupBy('warehouse');
        } else {
            $query->groupBy('group');
        }

        $data = $query->select($groupBy)
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->selectRaw('SUM(onorder) as total_onorder')
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
        $warehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];
        
        $query = StockByWh::query();
        
        // Filter by specified warehouses
        $query->whereIn('warehouse', $warehouses);

        // Allow specific warehouse override if provided in request
        if ($request->has('warehouse')) {
            $query->where('warehouse', $request->warehouse);
        }
        
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
     * Debug endpoint to check query and data
     */
    public function debugStockCount(): JsonResponse
    {
        $warehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

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
