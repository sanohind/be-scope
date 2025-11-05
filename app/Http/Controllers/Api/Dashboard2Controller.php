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

        // Count by actual line_status values: Staged, NULL, Adviced, Released, Open, Shipped
        $stagedOrders = WarehouseOrderLine::where('line_status', 'Staged')->count();
        $nullStatusOrders = WarehouseOrderLine::whereNull('line_status')->count();
        $advicedOrders = WarehouseOrderLine::where('line_status', 'Adviced')->count();
        $releasedOrders = WarehouseOrderLine::where('line_status', 'Released')->count();
        $openOrders = WarehouseOrderLine::where('line_status', 'Open')->count();
        $shippedOrders = WarehouseOrderLine::where('line_status', 'Shipped')->count();

        // Pending deliveries: Staged, NULL, Adviced, Released, Open
        $pendingDeliveries = $stagedOrders + $nullStatusOrders + $advicedOrders + $releasedOrders + $openOrders;

        // Completed orders: Shipped
        $completedOrders = $shippedOrders;

        $avgFulfillmentRate = WarehouseOrderLine::where('order_qty', '>', 0)
            ->selectRaw('AVG((ship_qty / order_qty) * 100) as avg_fulfillment_rate')
            ->value('avg_fulfillment_rate');

        return response()->json([
            'total_order_lines' => $totalOrderLines,
            'pending_deliveries' => $pendingDeliveries,
            'completed_orders' => $completedOrders,
            'average_fulfillment_rate' => round($avgFulfillmentRate ?? 0, 2),
            'status_breakdown' => [
                'staged' => $stagedOrders,
                'null_status' => $nullStatusOrders,
                'adviced' => $advicedOrders,
                'released' => $releasedOrders,
                'open' => $openOrders,
                'shipped' => $shippedOrders
            ]
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
     *
     * Returns optimized order timeline data for Gantt Chart visualization
     * Only essential fields: order_no, dates (order/delivery/receipt), status, and basic metadata
     *
     * Query Parameters:
     * - date_from: Start date filter (order_date)
     * - date_to: End date filter (order_date)
     * - status: Filter by delivery status (on_time, delayed, pending)
     * - ship_from: Filter by warehouse (ship_from)
     * - ship_to: Filter by destination (ship_to)
     * - limit: Number of orders to return (default: 50, max: 100)
     */
    public function warehouseOrderTimeline(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 50), 100);

        $query = DB::table('view_warehouse_order_line')
            ->select([
                'order_no',
                'ship_from',
                'ship_from_desc',
                'ship_to',
                'ship_to_desc',
            ])
            ->selectRaw('MIN(order_date) as order_date')
            ->selectRaw('MIN(delivery_date) as delivery_date')
            ->selectRaw('MAX(receipt_date) as receipt_date')
            ->selectRaw('CASE
                WHEN MAX(receipt_date) IS NULL THEN "pending"
                WHEN MAX(receipt_date) <= MIN(delivery_date) THEN "on_time"
                WHEN MAX(receipt_date) > MIN(delivery_date) THEN "delayed"
                ELSE "pending"
            END as status')
            ->groupBy([
                'order_no',
                'ship_from',
                'ship_from_desc',
                'ship_to',
                'ship_to_desc'
            ]);

        // Apply filters
        if ($request->has('date_from')) {
            $query->havingRaw('MIN(order_date) >= ?', [$request->date_from]);
        }
        if ($request->has('date_to')) {
            $query->havingRaw('MIN(order_date) <= ?', [$request->date_to]);
        }
        if ($request->has('ship_from')) {
            $query->where('ship_from', $request->ship_from);
        }
        if ($request->has('ship_to')) {
            $query->where('ship_to', $request->ship_to);
        }

        // Apply status filter in SQL for better performance
        if ($request->has('status')) {
            $status = $request->status;
            $query->havingRaw('CASE
                WHEN MAX(receipt_date) IS NULL THEN "pending"
                WHEN MAX(receipt_date) <= MIN(delivery_date) THEN "on_time"
                WHEN MAX(receipt_date) > MIN(delivery_date) THEN "delayed"
                ELSE "pending"
            END = ?', [$status]);
        }

        $data = $query->orderBy('order_date', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 2.9: Warehouse Order Timeline Detail
     *
     * Get detailed line items for a specific order
     * Used for drill-down from Gantt Chart
     *
     * @param string $orderNo
     */
    public function warehouseOrderTimelineDetail(string $orderNo): JsonResponse
    {
        $orderLines = WarehouseOrderLine::where('order_no', $orderNo)
            ->select([
                'order_no',
                'line_no',
                'order_date',
                'delivery_date',
                'receipt_date',
                'item_code',
                'item_desc',
                'item_desc2',
                'order_qty',
                'ship_qty',
                'unit',
                'line_status_code',
                'line_status',
                'ship_from',
                'ship_from_desc',
                'ship_to',
                'ship_to_desc'
            ])
            ->selectRaw('CASE
                WHEN receipt_date IS NULL THEN "pending"
                WHEN receipt_date <= delivery_date THEN "on_time"
                WHEN receipt_date > delivery_date THEN "delayed"
                ELSE "pending"
            END as delivery_status')
            ->selectRaw('CASE
                WHEN receipt_date IS NOT NULL AND delivery_date IS NOT NULL
                THEN DATEDIFF(receipt_date, delivery_date)
                ELSE NULL
            END as days_difference')
            ->selectRaw('CASE
                WHEN order_qty > 0
                THEN ROUND((ship_qty / order_qty) * 100, 2)
                ELSE 0
            END as fulfillment_rate')
            ->orderBy('line_no')
            ->get();

        if ($orderLines->isEmpty()) {
            return response()->json([
                'error' => 'Order not found'
            ], 404);
        }

        $orderSummary = [
            'order_no' => $orderNo,
            'total_lines' => $orderLines->count(),
            'total_order_qty' => $orderLines->sum('order_qty'),
            'total_ship_qty' => $orderLines->sum('ship_qty'),
            'earliest_order_date' => $orderLines->min('order_date'),
            'latest_receipt_date' => $orderLines->max('receipt_date'),
            'overall_status' => $orderLines->contains('delivery_status', 'delayed') ? 'delayed' :
                               ($orderLines->contains('delivery_status', 'pending') ? 'pending' : 'on_time'),
            'ship_from' => $orderLines->first()->ship_from,
            'ship_from_desc' => $orderLines->first()->ship_from_desc,
            'ship_to' => $orderLines->first()->ship_to,
            'ship_to_desc' => $orderLines->first()->ship_to_desc,
        ];

        return response()->json([
            'order_summary' => $orderSummary,
            'order_lines' => $orderLines
        ]);
    }

    /**
     * Get filter options for Warehouse Order Timeline
     * Returns unique values for dropdowns
     */
    public function warehouseOrderTimelineFilters(): JsonResponse
    {
        $warehouses = DB::table('view_warehouse_order_line')
            ->select('ship_from', 'ship_from_desc')
            ->distinct()
            ->whereNotNull('ship_from')
            ->orderBy('ship_from')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->ship_from,
                    'label' => $item->ship_from_desc ?? $item->ship_from
                ];
            });

        $destinations = DB::table('view_warehouse_order_line')
            ->select('ship_to', 'ship_to_desc')
            ->distinct()
            ->whereNotNull('ship_to')
            ->orderBy('ship_to')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->ship_to,
                    'label' => $item->ship_to_desc ?? $item->ship_to
                ];
            });

        $statuses = [
            ['value' => 'on_time', 'label' => 'On Time', 'color' => '#10B981'],
            ['value' => 'delayed', 'label' => 'Delayed', 'color' => '#EF4444'],
            ['value' => 'pending', 'label' => 'Pending', 'color' => '#F59E0B']
        ];

        $dateRange = DB::table('view_warehouse_order_line')
            ->selectRaw('MIN(order_date) as min_date')
            ->selectRaw('MAX(order_date) as max_date')
            ->first();

        return response()->json([
            'warehouses' => $warehouses,
            'destinations' => $destinations,
            'statuses' => $statuses,
            'date_range' => [
                'min' => $dateRange->min_date,
                'max' => $dateRange->max_date
            ]
        ]);
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
