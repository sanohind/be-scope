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
        $totalOnhand = StockByWh::sum('onhand');

        $itemsBelowSafetyStock = StockByWh::whereRaw('onhand < safety_stock')
            ->distinct('partno')
            ->count('partno');

        $itemsAboveMaxStock = StockByWh::whereRaw('onhand > max_stock')
            ->distinct('partno')
            ->count('partno');

        $totalItems = StockByWh::distinct('partno')->count('partno');

        $avgStockLevel = StockByWh::avg('onhand');

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
        $data = StockByWh::select('customer')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
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
        $groupBy = $request->get('group_by', 'warehouse'); // warehouse or product_group

        $query = StockByWh::query();

        if ($groupBy === 'warehouse') {
            $query->groupBy('warehouse');
        } else {
            $query->groupBy('group');
        }

        $data = $query->select($groupBy)
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->selectRaw('SUM(onorder) as total_onorder')
            ->selectRaw('ROUND(((SUM(onhand) - SUM(allocated)) / SUM(onhand)) * 100, 2) as available_percentage')
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
        $query = StockByWh::query();

        if ($request->has('warehouse')) {
            $query->where('warehouse', $request->warehouse);
        }
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        $data = $query->select([
                'partno',
                'desc',
                'onhand',
                'allocated'
            ])
            ->selectRaw('(onhand - allocated) as available')
            ->get();

        return response()->json([
            'message' => 'Historical data required for trend analysis',
            'current_data' => $data
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
