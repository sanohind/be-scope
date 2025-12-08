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
     * Get date format expression based on period for SQL Server
     * Override parent method with SQL Server specific format
     */
    protected function getDateFormatByPeriod($period, $dateField = 'order_date', $query = null)
    {
        // Use SQL Server FORMAT function for consistent date formatting
        return match($period) {
            'daily' => "FORMAT({$dateField}, 'yyyy-MM-dd')",
            'monthly' => "FORMAT({$dateField}, 'yyyy-MM')",
            'yearly' => "FORMAT({$dateField}, 'yyyy')",
            default => "FORMAT({$dateField}, 'yyyy-MM-dd')",
        };
    }

    /**
     * Fill missing periods with zero values
     */
    private function fillMissingPeriods(array $data, array $allPeriods, string $period, string $periodKey = 'period_date'): array
    {
        if (empty($data)) {
            // If no data, create zero entries for all periods
            return collect($allPeriods)->map(function ($periodValue) use ($periodKey) {
                return (object) [
                    $periodKey => $periodValue,
                    'total_order_qty' => 0,
                    'total_ship_qty' => 0,
                    'gap_qty' => 0,
                    'order_count' => 0,
                ];
            })->toArray();
        }

        // Create a keyed array by period
        $dataByPeriod = [];
        foreach ($data as $item) {
            $key = is_object($item) ? $item->{$periodKey} : $item[$periodKey];
            // Normalize key format
            $key = $this->normalizePeriodKey($key, $period);
            $dataByPeriod[$key] = $item;
        }

        // Get sample item to determine structure
        $sampleItem = is_object($data[0]) ? $data[0] : (object) $data[0];

        // Fill missing periods
        $filledData = [];
        foreach ($allPeriods as $periodValue) {
            if (isset($dataByPeriod[$periodValue])) {
                $item = $dataByPeriod[$periodValue];
                // Ensure period_date is normalized
                if (is_object($item)) {
                    $item->{$periodKey} = $periodValue;
                } else {
                    $item[$periodKey] = $periodValue;
                }
                $filledData[] = $item;
            } else {
                // Create zero value entry based on sample structure
                $zeroEntry = is_object($sampleItem) ? (object)[] : [];

                foreach (get_object_vars($sampleItem) as $field => $value) {
                    if ($field === $periodKey) {
                        if (is_object($zeroEntry)) {
                            $zeroEntry->{$field} = $periodValue;
                        } else {
                            $zeroEntry[$field] = $periodValue;
                        }
                    } elseif (is_numeric($value)) {
                        if (is_object($zeroEntry)) {
                            $zeroEntry->{$field} = 0;
                        } else {
                            $zeroEntry[$field] = 0;
                        }
                    } else {
                        if (is_object($zeroEntry)) {
                            $zeroEntry->{$field} = $value ?? null;
                        } else {
                            $zeroEntry[$field] = $value ?? null;
                        }
                    }
                }
                $filledData[] = $zeroEntry;
            }
        }

        return $filledData;
    }

    /**
     * Normalize period key format
     */
    private function normalizePeriodKey(string $key, string $period): string
    {
        $key = trim($key);

        if ($period === 'daily') {
            try {
                return Carbon::parse($key)->format('Y-m-d');
            } catch (\Exception $e) {
                return $key;
            }
        } elseif ($period === 'monthly') {
            try {
                return Carbon::parse($key)->format('Y-m');
            } catch (\Exception $e) {
                return $key;
            }
        } elseif ($period === 'yearly') {
            return (string) intval($key);
        }

        return $key;
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

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
            'period' => $period,
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

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
            'period' => $period,
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        $data = DB::connection('erp')
            ->table('view_warehouse_order')
            ->select([
                DB::raw("CASE WHEN order_origin = 'JSC Production (Manual)' THEN 'Supply & Aux Consume' ELSE order_origin END as order_origin"),
                'status_desc'
            ])
            ->selectRaw('COUNT(*) as count')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->groupBy([DB::raw("CASE WHEN order_origin = 'JSC Production (Manual)' THEN 'Supply & Aux Consume' ELSE order_origin END"), 'status_desc'])
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
            'period' => $period,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days_diff' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 4: Daily Order Volume - Line Chart with Area
     * Filtered by warehouse parameter
     * DATE FILTER: Applied to order_date
     * Supports period: daily, monthly, yearly
     * Fills missing periods with zero values
     */
    public function dailyOrderVolume(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

        $query = DB::connection('erp')
            ->table('view_warehouse_order_line')
            ->whereIn('ship_from', $warehouseCodes)
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        // Get date format based on period
        $dateFormat = $this->getDateFormatByPeriod($period, 'order_date');

        $data = $query->selectRaw("$dateFormat as period_date")
            ->selectRaw('SUM(order_qty) as total_order_qty')
            ->selectRaw('SUM(ship_qty) as total_ship_qty')
            ->selectRaw('SUM(order_qty - ship_qty) as gap_qty')
            ->selectRaw('COUNT(*) as order_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get()
            ->toArray();

        // Generate all periods and fill missing ones
        $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);
        $filledData = $this->fillMissingPeriods($data, $allPeriods, $period, 'period_date');

        return response()->json([
            'data' => $filledData,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'period' => $period,
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

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
            'period' => $period,
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);
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
            'period' => $period,
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
     * Supports period: daily, monthly, yearly
     * Fills missing periods with zero values
     */
    public function monthlyInboundVsOutbound(Request $request): JsonResponse
    {
        $warehouseSelection = $this->getWarehouse($request);
        $warehouseCodes = $warehouseSelection['codes'];
        $period = $this->getPeriod($request);
        // Default days based on period
        $defaultDays = match($period) {
            'yearly' => 365 * 2, // 2 years
            'monthly' => 180, // 6 months
            default => 90 // 3 months for daily
        };
        $dateRange = $this->getDateRange($request, $defaultDays, $period);
        $dateFormat = $this->getDateFormatByPeriod($period, 'order_date');
        $periodKey = match($period) {
            'daily' => 'date',
            'monthly' => 'month',
            'yearly' => 'year',
            default => 'period'
        };

        $baseQuery = DB::connection('erp')
            ->table('view_warehouse_order_line')
            ->whereBetween('order_date', [$dateRange['date_from'], $dateRange['date_to']]);

        $inbound = (clone $baseQuery)
            ->whereIn('ship_to', $warehouseCodes)
            ->selectRaw("$dateFormat as {$periodKey}")
            ->selectRaw('SUM(ship_qty) as inbound')
            ->groupByRaw($dateFormat)
            ->pluck('inbound', $periodKey);

        $outbound = (clone $baseQuery)
            ->whereIn('ship_from', $warehouseCodes)
            ->selectRaw("$dateFormat as {$periodKey}")
            ->selectRaw('SUM(ship_qty) as outbound')
            ->groupByRaw($dateFormat)
            ->pluck('outbound', $periodKey);

        // Generate all periods and fill missing ones
        $allPeriods = $this->generateAllPeriods($period, $dateRange['date_from'], $dateRange['date_to']);

        $data = collect($allPeriods)->map(function ($periodValue) use ($inbound, $outbound, $periodKey) {
            return [
                $periodKey => $periodValue,
                'inbound' => (float) ($inbound->get($periodValue, 0)),
                'outbound' => (float) ($outbound->get($periodValue, 0)),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'warehouse' => $warehouseSelection['requested'],
            'warehouse_codes' => $warehouseCodes,
            'period' => $period,
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
        $period = $this->getPeriod($request);
        $dateRange = $this->getDateRange($request, 30, $period);

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
            'period' => $period,
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
