<?php

namespace App\Http\Controllers\Api;

use App\Models\ProdHeader;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard3Controller extends ApiController
{
    /**
     * Chart 3.1: Production KPI Summary - KPI Cards
     */
    public function productionKpiSummary(): JsonResponse
    {
        $totalProductionOrders = ProdHeader::distinct('prod_no')->count('prod_no');
        $totalQtyOrdered = ProdHeader::sum('qty_order');
        $totalQtyDelivered = ProdHeader::sum('qty_delivery');
        $totalOutstandingQty = ProdHeader::sum('qty_os');
        
        $completionRate = $totalQtyOrdered > 0 
            ? round(($totalQtyDelivered / $totalQtyOrdered) * 100, 2) 
            : 0;

        return response()->json([
            'total_production_orders' => $totalProductionOrders,
            'total_qty_ordered' => $totalQtyOrdered,
            'total_qty_delivered' => $totalQtyDelivered,
            'total_outstanding_qty' => $totalOutstandingQty,
            'completion_rate' => $completionRate
        ]);
    }

    /**
     * Chart 3.2: Production Status Distribution - Pie/Donut Chart
     */
    public function productionStatusDistribution(): JsonResponse
    {
        $data = ProdHeader::select('status')
            ->selectRaw('COUNT(prod_no) as count')
            ->selectRaw('SUM(qty_order) as total_qty')
            ->groupBy('status')
            ->get();

        $totalOrders = $data->sum('count');

        return response()->json([
            'data' => $data,
            'total_orders' => $totalOrders
        ]);
    }

    /**
     * Chart 3.3: Production by Customer - Clustered Bar Chart
     */
    public function productionByCustomer(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);

        $data = ProdHeader::select('customer')
            ->selectRaw('SUM(qty_order) as qty_ordered')
            ->selectRaw('SUM(qty_delivery) as qty_delivered')
            ->selectRaw('SUM(qty_os) as qty_outstanding')
            ->groupBy('customer')
            ->orderBy('qty_ordered', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 3.4: Production by Model - Horizontal Bar Chart
     */
    public function productionByModel(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $data = ProdHeader::select('model', 'customer')
            ->selectRaw('SUM(qty_order) as total_qty')
            ->selectRaw('COUNT(DISTINCT prod_no) as total_orders')
            ->groupBy('model', 'customer')
            ->orderBy('total_qty', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 3.5: Production Schedule Timeline - Gantt Chart
     */
    public function productionScheduleTimeline(Request $request): JsonResponse
    {
        $query = ProdHeader::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('divisi')) {
            $query->where('divisi', $request->divisi);
        }
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        $data = $query->select([
                'prod_no',
                'planning_date',
                'item',
                'description',
                'customer',
                'model',
                'status',
                'qty_order',
                'qty_delivery',
                'qty_os',
                'divisi'
            ])
            ->orderBy('planning_date', 'asc')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 3.6: Production Outstanding Analysis - Data Table with Progress Bar
     */
    public function productionOutstandingAnalysis(Request $request): JsonResponse
    {
        $query = ProdHeader::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        $sortBy = $request->get('sort_by', 'percent_complete');
        $sortOrder = $request->get('sort_order', 'asc');

        $data = $query->select([
                'prod_no',
                'planning_date',
                'item',
                'description',
                'customer',
                'qty_order',
                'qty_delivery',
                'qty_os',
                'status',
                'divisi'
            ])
            ->selectRaw('ROUND((qty_delivery / NULLIF(qty_order, 0)) * 100, 2) as percent_complete')
            ->get();

        // Sort data
        if ($sortBy === 'percent_complete') {
            $data = $sortOrder === 'asc' 
                ? $data->sortBy('percent_complete') 
                : $data->sortByDesc('percent_complete');
        } elseif ($sortBy === 'qty_os') {
            $data = $sortOrder === 'asc' 
                ? $data->sortBy('qty_os') 
                : $data->sortByDesc('qty_os');
        }

        return response()->json($data->values());
    }

    /**
     * Chart 3.7: Production by Division - Stacked Bar Chart
     */
    public function productionByDivision(): JsonResponse
    {
        $data = ProdHeader::select('divisi', 'status')
            ->selectRaw('SUM(qty_order) as production_volume')
            ->selectRaw('COUNT(DISTINCT prod_no) as total_orders')
            ->selectRaw('ROUND(AVG((qty_delivery / NULLIF(qty_order, 0)) * 100), 2) as avg_completion_rate')
            ->groupBy('divisi', 'status')
            ->orderBy('divisi')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 3.8: Production Trend - Combo Chart
     */
    public function productionTrend(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        
        $query = ProdHeader::query();

        // Apply filters
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('divisi')) {
            $query->where('divisi', $request->divisi);
        }
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        // Group by period
        if ($groupBy === 'weekly') {
            $dateFormat = "DATE_FORMAT(planning_date, '%Y-W%u')";
        } else {
            $dateFormat = "DATE_FORMAT(planning_date, '%Y-%m')";
        }

        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('SUM(qty_order) as qty_ordered')
            ->selectRaw('SUM(qty_delivery) as qty_delivered')
            ->selectRaw('ROUND((SUM(qty_delivery) / NULLIF(SUM(qty_order), 0)) * 100, 2) as achievement_rate')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 3.9: Production by Warehouse - Treemap
     */
    public function productionByWarehouse(): JsonResponse
    {
        $data = ProdHeader::select([
                'warehouse',
                'divisi',
                'customer',
                'qty_order',
                'qty_delivery',
                'status'
            ])
            ->selectRaw('ROUND((qty_delivery / NULLIF(qty_order, 0)) * 100, 2) as completion_rate')
            ->orderBy('qty_order', 'desc')
            ->get()
            ->groupBy(['warehouse', 'divisi', 'customer']);

        return response()->json($data);
    }

    /**
     * Get all dashboard 3 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'production_kpi_summary' => $this->productionKpiSummary()->getData(true),
            'production_status_distribution' => $this->productionStatusDistribution()->getData(true),
            'production_by_customer' => $this->productionByCustomer($request)->getData(true),
            'production_by_model' => $this->productionByModel($request)->getData(true),
            'production_schedule_timeline' => $this->productionScheduleTimeline($request)->getData(true),
            'production_outstanding_analysis' => $this->productionOutstandingAnalysis($request)->getData(true),
            'production_by_division' => $this->productionByDivision()->getData(true),
            'production_trend' => $this->productionTrend($request)->getData(true),
            'production_by_warehouse' => $this->productionByWarehouse()->getData(true)
        ]);
    }
}
