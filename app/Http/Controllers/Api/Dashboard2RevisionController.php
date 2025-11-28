<?php

namespace App\Http\Controllers\Api;

use App\Models\WarehouseOrder;
use App\Models\WarehouseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard2RevisionController extends ApiController
{
    /**
     * Get warehouse parameter from request or throw error
     */
    private function getWarehouse(Request $request): array
    {
        $warehouse = $request->input('warehouse');

        if (!$warehouse) {
            abort(400, 'Warehouse parameter is required');
        }

        $aliases = [
            'RM' => ['WHRM01', 'WHRM02', 'WHMT01'],
            'FG' => ['WHFG01', 'WHFG02'],
        ];

        $validWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02', 'RM'];

        if (isset($aliases[$warehouse])) {
            return [
                'requested' => $warehouse,
                'codes' => $aliases[$warehouse],
            ];
        }

        if (!in_array($warehouse, $validWarehouses)) {
            abort(400, 'Invalid warehouse code');
        }

        return [
            'requested' => $warehouse,
            'codes' => [$warehouse],
        ];
    }

    /**
     * Get date range parameters from request
     * Returns array with date_from and date_to
     * Default: last 30 days if not specified
     */
    private function getDateRange(Request $request, int $defaultDays = 30): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // If no dates provided, use default
        if (!$dateFrom && !$dateTo) {
            // Default to current month: from first day of month until today
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now();
        } else {
            // Parse provided dates (fall back to sensible defaults if one side missing)
            $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->startOfMonth();
            $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();
        }

        return [
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d 23:59:59'),
            'days_diff' => $dateFrom->diffInDays($dateTo)
        ];
    }

    /**
     * CHART 1: Warehouse Order Summary - KPI Cards
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     */
    public function warehouseOrderSummary(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);

        $query = WarehouseOrder::query()
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        $totalOrderLines = (clone $query)->count();


        // Count by actual line_status values
        $plannedOrders = (clone $query)->where('status_desc', 'Planned')->count();
        $nullStatusOrders = (clone $query)->whereNull('status_desc')->count();
        $putAwayOrders = (clone $query)->where('status_desc', 'Put Away')->count();
        $receivedOrders = (clone $query)->where('status_desc', 'Received')->count();
        $modifiedOrders = (clone $query)->where('status_desc', 'Modified')->count();
        $shippedOrders = (clone $query)->where('status_desc', 'Shipped')->count();
        $openOrders = (clone $query)->where('status_desc', 'Open')->count();

        $closeOrders = $shippedOrders + $putAwayOrders;

        // Pending deliveries: Planned, NULL, Put Away, Received, Modified, Open
        $pendingDeliveries = $plannedOrders + $nullStatusOrders + $putAwayOrders + $receivedOrders + $modifiedOrders + $openOrders;

        // Completed orders: Shipped
        $completedOrders = $shippedOrders;

        return response()->json([
            'total_order_lines' => $totalOrderLines,
            'pending_deliveries' => $pendingDeliveries,
            'completed_orders' => $completedOrders,
            'status_breakdown' => [
                'open' => $openOrders,
                'close' => $closeOrders,
                'warehouse_orders' => $totalOrderLines
            ],
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 2: Delivery Performance - Gauge Chart
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     *
     * Calculation:
     * Closed = COUNT(status_desc = 'Shipped') + COUNT(status_desc = 'Put Away')
     * Total = COUNT(status_desc = 'Shipped') + COUNT(status_desc = 'Put Away') + COUNT(status_desc = 'Open')
     * Performance = (Closed / Total) * 100 (as percentage)
     */
    public function deliveryPerformance(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);

        // Use the WarehouseOrder model (uses 'erp' connection and 'view_warehouse_order' table)
        $query = WarehouseOrder::query()
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        // Count orders by status using the WarehouseOrder view/table
        $shippedCount = (clone $query)->where('status_desc', 'Shipped')->count();
        $putAwayCount = (clone $query)->where('status_desc', 'Put Away')->count();
        $openCount = (clone $query)->where('status_desc', 'Open')->count();

        // Closed = Shipped + Put Away
        $closed = $shippedCount + $putAwayCount;

        // Total = Shipped + Put Away + Open
        $total = $closed + $openCount;

        // Performance percentage
        $performance = $total > 0 ? round(($closed / $total) * 100, 2) : 0;

        return response()->json([
            'closed' => $closed,
            'shipped' => $shippedCount,
            'put_away' => $putAwayCount,
            'open' => $openCount,
            'total' => $total,
            'performance_rate' => $performance,
            'target_rate' => 95,
            'performance_status' => $performance >= 95 ? 'excellent' : ($performance >= 85 ? 'good' : 'needs_improvement'),
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 3: Order Status Distribution - Stacked Bar Chart
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     */
    public function orderStatusDistribution(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);

        $data = DB::connection('erp')
            ->table('view_warehouse_order')
            ->select(['order_origin', 'status_desc'])
            ->selectRaw('COUNT(*) as count')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->groupBy(['order_origin', 'status_desc'])
            ->get()
            ->groupBy('order_origin')
            ->map(function ($group) {
                $total = $group->sum('count');
                return $group->map(function ($item) use ($total) {
                    $item->percentage = round(($item->count / $total) * 100, 2);
                    return $item;
                });
            });

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 4: Daily Order Volume - Line Chart with Area
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     */
    public function dailyOrderVolume(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);
        $period = $request->get('period', 'daily');

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'daily';
        }

        $query = DB::table('view_warehouse_order_line')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        // Get date format based on period
        $dateFormat = match($period) {
            'daily' => "FORMAT(order_date, 'yyyy-MM-dd')",
            'monthly' => "FORMAT(order_date, 'yyyy-MM')",
            'yearly' => "FORMAT(order_date, 'yyyy')",
            default => "FORMAT(order_date, 'yyyy-MM-dd')"
        };

        $data = $query->selectRaw("$dateFormat as period_date")
            ->selectRaw('SUM(order_qty) as total_order_qty')
            ->selectRaw('SUM(ship_qty) as total_ship_qty')
            ->selectRaw('SUM(order_qty - ship_qty) as gap_qty')
            ->selectRaw('COUNT(*) as order_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 5: Order Fulfillment by Transaction Type - Bar Chart
     * Modified from original to show by transaction type instead of warehouse
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     */
    public function orderFulfillmentByTransactionType(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);

        $data = DB::table('view_warehouse_order_line')
            ->select('trx_type')
            ->selectRaw('SUM(order_qty) as total_order_qty')
            ->selectRaw('SUM(ship_qty) as total_ship_qty')
            ->selectRaw('ROUND((SUM(ship_qty) / SUM(order_qty)) * 100, 2) as fulfillment_rate')
            ->selectRaw('COUNT(*) as order_count')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->where('order_qty', '>', 0)
            ->groupBy('trx_type')
            ->orderBy('fulfillment_rate', 'desc')
            ->get();

        return response()->json([
            'data' => $data,
            'target_rate' => 100,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 6: Top 20 Items Moved - Horizontal Bar Chart
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     */
    public function topItemsMoved(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);
        $limit = $request->get('limit', 20);

        $data = DB::table('view_warehouse_order_line')
            ->select(['item_code', 'item_desc'])
            ->selectRaw('SUM(ship_qty) as total_qty_moved')
            ->selectRaw('COUNT(DISTINCT order_no) as total_orders')
            ->selectRaw('ROUND(AVG(ship_qty), 2) as avg_qty_per_order')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->groupBy(['item_code', 'item_desc'])
            ->orderBy('total_qty_moved', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 7: Monthly Inbound vs Outbound - Grouped Bar Chart
     * Shows inbound and outbound quantities for the specified warehouse
     * DATE FILTER: Applied to order_date (default 6 months)
     */
    public function monthlyInboundVsOutbound(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 180); // Default 6 months
        $monthFormat = "FORMAT(order_date, 'yyyy-MM')";

        $baseQuery = DB::connection('erp')
            ->table('view_warehouse_order_line')
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        $inbound = (clone $baseQuery)
            ->whereIn('ship_to', $warehouseCodes)
            ->selectRaw("$monthFormat as month")
            ->selectRaw('SUM(ship_qty) as inbound')
            ->groupByRaw($monthFormat)
            ->pluck('inbound', 'month');

        $outbound = (clone $baseQuery)
            ->whereIn('ship_from', $warehouseCodes)
            ->selectRaw("$monthFormat as month")
            ->selectRaw('SUM(ship_qty) as outbound')
            ->groupByRaw($monthFormat)
            ->pluck('outbound', 'month');

        $months = $inbound->keys()
            ->merge($outbound->keys())
            ->unique()
            ->sort()
            ->values();

        $data = $months->map(function ($month) use ($inbound, $outbound) {
            return [
                'month' => $month,
                'inbound' => (float) ($inbound->get($month, 0)),
                'outbound' => (float) ($outbound->get($month, 0)),
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 8: Top Destinations - Horizontal Bar Chart
     * Shows top 10 destinations from the specified warehouse
     * DATE FILTER: Applied to order_date (default 30 days)
     */
    public function topDestinations(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $dateRange = $this->getDateRange($request, 30);

        $data = DB::connection('erp')
            ->table('view_warehouse_order_line')
            ->select('ship_to', 'ship_to_desc', 'ship_to_type')
            ->selectRaw('COUNT(DISTINCT order_no) as order_count')
            ->selectRaw('SUM(ship_qty) as total_qty')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->groupBy('ship_to', 'ship_to_desc', 'ship_to_type')
            ->orderByDesc('order_count')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * Get all dashboard data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'warehouse_order_summary' => $this->warehouseOrderSummary($request)->getData(true),
            'delivery_performance' => $this->deliveryPerformance($request)->getData(true),
            'order_status_distribution' => $this->orderStatusDistribution($request)->getData(true),
            'daily_order_volume' => $this->dailyOrderVolume($request)->getData(true),
            'order_fulfillment_by_transaction_type' => $this->orderFulfillmentByTransactionType($request)->getData(true),
            'top_items_moved' => $this->topItemsMoved($request)->getData(true),
            'monthly_inbound_vs_outbound' => $this->monthlyInboundVsOutbound($request)->getData(true),
            'top_destinations' => $this->topDestinations($request)->getData(true)
        ]);
    }
}
