<?php

namespace App\Http\Controllers\Api;

use App\Models\WarehouseOrder;
use App\Models\WarehouseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard2Controller extends ApiController
{
    /**
     * Chart 2.1: Warehouse Order Summary - KPI Cards
     */
    public function warehouseOrderSummary(): JsonResponse
    {
        $totalOrderLines = WarehouseOrderLine::count();

        $pendingDeliveries = WarehouseOrderLine::where('line_status', 'Pending')->count();

        $completedOrders = WarehouseOrderLine::where('line_status', 'Completed')->count();

        $avgFulfillmentRate = WarehouseOrderLine::where('order_qty', '>', 0)
            ->selectRaw('AVG((ship_qty / order_qty) * 100) as avg_fulfillment_rate')
            ->value('avg_fulfillment_rate');

        return response()->json([
            'total_order_lines' => $totalOrderLines,
            'pending_deliveries' => $pendingDeliveries,
            'completed_orders' => $completedOrders,
            'average_fulfillment_rate' => round($avgFulfillmentRate ?? 0, 2)
        ]);
    }

    /**
     * Chart 2.2: Order Flow Analysis - Sankey Diagram
     */
    public function orderFlowAnalysis(): JsonResponse
    {
        $data = WarehouseOrderLine::select([
                'ship_from',
                'ship_to',
                'trx_type'
            ])
            ->selectRaw('SUM(ship_qty) as total_qty')
            ->selectRaw('COUNT(order_no) as order_count')
            ->groupBy(['ship_from', 'ship_to', 'trx_type'])
            ->orderBy('total_qty', 'desc')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 2.3: Delivery Performance - Gauge Chart
     */
    public function deliveryPerformance(): JsonResponse
    {
        $totalOrders = WarehouseOrderLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->count();

        $onTimeOrders = WarehouseOrderLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date <= delivery_date')
            ->count();

        $earlyOrders = WarehouseOrderLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date < delivery_date')
            ->count();

        $lateOrders = WarehouseOrderLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date > delivery_date')
            ->count();

        $onTimeRate = $totalOrders > 0 ? round(($onTimeOrders / $totalOrders) * 100, 2) : 0;

        return response()->json([
            'on_time_delivery_rate' => $onTimeRate,
            'target_rate' => 95,
            'early_deliveries' => $earlyOrders,
            'on_time_deliveries' => $onTimeOrders - $earlyOrders,
            'late_deliveries' => $lateOrders,
            'total_orders' => $totalOrders,
            'performance_status' => $onTimeRate >= 95 ? 'excellent' : ($onTimeRate >= 85 ? 'good' : 'needs_improvement')
        ]);
    }

    /**
     * Chart 2.4: Order Status Distribution - Stacked Bar Chart
     */
    public function orderStatusDistribution(Request $request): JsonResponse
    {
        $query = WarehouseOrderLine::query();

        if ($request->has('ship_from')) {
            $query->where('ship_from', $request->ship_from);
        }

        $data = $query->select([
                'trx_type',
                'line_status'
            ])
            ->selectRaw('COUNT(*) as count')
            ->groupBy(['trx_type', 'line_status'])
            ->get()
            ->groupBy('trx_type')
            ->map(function ($group) {
                $total = $group->sum('count');
                return $group->map(function ($item) use ($total) {
                    $item->percentage = round(($item->count / $total) * 100, 2);
                    return $item;
                });
            });

        return response()->json($data);
    }

    /**
     * Chart 2.5: Daily Order Volume - Line Chart with Area
     */
    public function dailyOrderVolume(Request $request): JsonResponse
    {
        $query = WarehouseOrderLine::query();

        if ($request->has('trx_type')) {
            $query->where('trx_type', $request->trx_type);
        }
        if ($request->has('ship_from')) {
            $query->where('ship_from', $request->ship_from);
        }
        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->date_to);
        }

        $data = $query->select('order_date')
            ->selectRaw('SUM(order_qty) as total_order_qty')
            ->selectRaw('SUM(ship_qty) as total_ship_qty')
            ->selectRaw('SUM(order_qty - ship_qty) as gap_qty')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 2.6: Order Fulfillment Rate - Bar Chart with Target Line
     */
    public function orderFulfillmentRate(): JsonResponse
    {
        $data = WarehouseOrderLine::select('ship_from')
            ->selectRaw('SUM(order_qty) as total_order_qty')
            ->selectRaw('SUM(ship_qty) as total_ship_qty')
            ->selectRaw('ROUND((SUM(ship_qty) / SUM(order_qty)) * 100, 2) as fulfillment_rate')
            ->where('order_qty', '>', 0)
            ->groupBy('ship_from')
            ->orderBy('fulfillment_rate', 'desc')
            ->get();

        return response()->json([
            'data' => $data,
            'target_rate' => 100
        ]);
    }

    /**
     * Chart 2.7: Top Items Moved - Horizontal Bar Chart
     */
    public function topItemsMoved(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $data = WarehouseOrderLine::select([
                'item_code',
                'item_desc'
            ])
            ->selectRaw('SUM(ship_qty) as total_qty_moved')
            ->selectRaw('COUNT(DISTINCT order_no) as total_orders')
            ->selectRaw('ROUND(AVG(ship_qty), 2) as avg_qty_per_order')
            ->groupBy(['item_code', 'item_desc'])
            ->orderBy('total_qty_moved', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 2.8: Warehouse Order Timeline - Gantt Chart
     */
    public function warehouseOrderTimeline(Request $request): JsonResponse
    {
        $query = WarehouseOrderLine::query();

        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->date_to);
        }
        if ($request->has('line_status')) {
            $query->where('line_status', $request->line_status);
        }
        if ($request->has('ship_from')) {
            $query->where('ship_from', $request->ship_from);
        }

        $data = $query->select([
                'order_no',
                'line_no',
                'order_date',
                'delivery_date',
                'receipt_date',
                'line_status',
                'item_code',
                'item_desc',
                'order_qty',
                'ship_qty'
            ])
            ->selectRaw('CASE
                WHEN receipt_date IS NULL THEN "pending"
                WHEN receipt_date <= delivery_date THEN "on_time"
                ELSE "delayed"
            END as delivery_status')
            ->orderBy('order_date', 'desc')
            ->limit(100)
            ->get();

        return response()->json($data);
    }

    /**
     * Get all dashboard 2 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'warehouse_order_summary' => $this->warehouseOrderSummary()->getData(true),
            'order_flow_analysis' => $this->orderFlowAnalysis()->getData(true),
            'delivery_performance' => $this->deliveryPerformance()->getData(true),
            'order_status_distribution' => $this->orderStatusDistribution($request)->getData(true),
            'daily_order_volume' => $this->dailyOrderVolume($request)->getData(true),
            'order_fulfillment_rate' => $this->orderFulfillmentRate()->getData(true),
            'top_items_moved' => $this->topItemsMoved($request)->getData(true),
            'warehouse_order_timeline' => $this->warehouseOrderTimeline($request)->getData(true)
        ]);
    }
}
