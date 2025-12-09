<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use App\Models\ProdHeader;
use App\Models\ReceiptPurchase;
use App\Models\StockByWh;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard6Controller extends ApiController
{
    /**
     * Chart 6.1: Supply Chain KPI - KPI Cards
     */
    public function supplyChainKpi(): JsonResponse
    {
        // Order to Cash Cycle Time
        $orderToCashCycleTime = SoInvoiceLine::whereNotNull('invoice_date')
            ->whereNotNull('so_date')
            ->selectRaw('AVG(DATEDIFF(day, so_date, invoice_date)) as avg_cycle_time')
            ->value('avg_cycle_time') ?? 0;

        // Procure to Pay Cycle Time
        $procureToPayCycleTime = ReceiptPurchase::whereNotNull('payment_doc_date')
            ->selectRaw('AVG(DATEDIFF(day, actual_receipt_date, payment_doc_date)) as avg_cycle_time')
            ->value('avg_cycle_time') ?? 0;

        // Average Production Lead Time
        $avgProductionLeadTime = ProdHeader::whereNotNull('planning_date')
            ->selectRaw('AVG(DATEDIFF(day, planning_date, planning_date)) as avg_lead_time')
            ->value('avg_lead_time') ?? 0;

        // Stock Availability Rate
        $totalItems = StockByWh::distinct('partno')->count('partno');
        $itemsAboveSafetyStock = StockByWh::whereRaw('onhand > safety_stock')
            ->distinct('partno')
            ->count('partno');

        $stockAvailabilityRate = $totalItems > 0
            ? round(($itemsAboveSafetyStock / $totalItems) * 100, 2)
            : 0;

        // Supply Chain Cost Efficiency (simplified calculation)
        $totalCost = ReceiptPurchase::sum('receipt_amount');
        $totalOutput = SoInvoiceLine::sum('amount_hc');
        $costEfficiency = $totalOutput > 0
            ? round(($totalCost / $totalOutput) * 100, 2)
            : 0;

        return response()->json([
            'order_to_cash_cycle_time' => round($orderToCashCycleTime, 2),
            'procure_to_pay_cycle_time' => round($procureToPayCycleTime, 2),
            'average_production_lead_time' => round($avgProductionLeadTime, 2),
            'stock_availability_rate' => $stockAvailabilityRate,
            'supply_chain_cost_efficiency' => $costEfficiency
        ]);
    }

    /**
     * Chart 6.2: Order to Cash Flow - Sankey Diagram
     */
    public function orderToCashFlow(): JsonResponse
    {
        // Available Stock
        $availableStock = StockByWh::selectRaw('SUM(onhand - allocated) as total_available')
            ->value('total_available') ?? 0;

        // Production Orders
        $productionOrders = ProdHeader::selectRaw('COUNT(DISTINCT prod_no) as count')
            ->selectRaw('SUM(qty_order) as total_qty')
            ->first();

        // Sales Orders
        $salesOrders = SoInvoiceLine::selectRaw('COUNT(DISTINCT sales_order) as count')
            ->selectRaw('SUM(delivered_qty) as total_qty')
            ->first();

        // Shipments
        $shipments = SoInvoiceLine::selectRaw('COUNT(DISTINCT shipment) as count')
            ->selectRaw('SUM(delivered_qty) as total_qty')
            ->first();

        // Invoices
        $invoices = SoInvoiceLine::selectRaw('COUNT(DISTINCT invoice_no) as count')
            ->selectRaw('SUM(amount_hc) as total_value')
            ->first();

        return response()->json([
            'nodes' => [
                ['id' => 'available_stock', 'name' => 'Available Stock', 'value' => $availableStock],
                ['id' => 'production_orders', 'name' => 'Production Orders', 'value' => $productionOrders->total_qty ?? 0],
                ['id' => 'sales_orders', 'name' => 'Sales Orders', 'value' => $salesOrders->total_qty ?? 0],
                ['id' => 'shipments', 'name' => 'Shipments', 'value' => $shipments->total_qty ?? 0],
                ['id' => 'invoices', 'name' => 'Invoices', 'value' => $invoices->total_value ?? 0]
            ],
            'links' => [
                ['source' => 'available_stock', 'target' => 'production_orders', 'value' => $productionOrders->total_qty ?? 0],
                ['source' => 'production_orders', 'target' => 'sales_orders', 'value' => $salesOrders->total_qty ?? 0],
                ['source' => 'sales_orders', 'target' => 'shipments', 'value' => $shipments->total_qty ?? 0],
                ['source' => 'shipments', 'target' => 'invoices', 'value' => $invoices->total_value ?? 0]
            ]
        ]);
    }

    /**
     * Chart 6.3: Procure to Pay Flow - Sankey Diagram
     */
    public function procureToPayFlow(): JsonResponse
    {
        // PO Created
        $poCreated = ReceiptPurchase::selectRaw('COUNT(DISTINCT po_no) as count')
            ->selectRaw('SUM(receipt_amount) as total_amount')
            ->first();

        // Receipts
        $receipts = ReceiptPurchase::whereNotNull('receipt_no')
            ->selectRaw('COUNT(DISTINCT receipt_no) as count')
            ->selectRaw('SUM(receipt_amount) as total_amount')
            ->first();

        // Invoices
        $invoices = ReceiptPurchase::whereNotNull('inv_doc_no')
            ->selectRaw('COUNT(DISTINCT inv_doc_no) as count')
            ->selectRaw('SUM(inv_amount) as total_amount')
            ->first();

        // Payments
        $payments = ReceiptPurchase::whereNotNull('payment_doc')
            ->selectRaw('COUNT(DISTINCT payment_doc) as count')
            ->selectRaw('SUM(inv_amount) as total_amount')
            ->first();

        return response()->json([
            'nodes' => [
                ['id' => 'po_created', 'name' => 'PO Created', 'value' => $poCreated->total_amount ?? 0],
                ['id' => 'receipts', 'name' => 'Receipts', 'value' => $receipts->total_amount ?? 0],
                ['id' => 'invoices', 'name' => 'Invoices', 'value' => $invoices->total_amount ?? 0],
                ['id' => 'payments', 'name' => 'Payments', 'value' => $payments->total_amount ?? 0]
            ],
            'links' => [
                ['source' => 'po_created', 'target' => 'receipts', 'value' => $receipts->total_amount ?? 0],
                ['source' => 'receipts', 'target' => 'invoices', 'value' => $invoices->total_amount ?? 0],
                ['source' => 'invoices', 'target' => 'payments', 'value' => $payments->total_amount ?? 0]
            ]
        ]);
    }

    /**
     * Chart 6.4: Demand vs Supply Analysis - Combo Chart
     */
    public function demandVsSupplyAnalysis(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'product_type');

        if ($groupBy === 'product_type') {
            // Available Stock by product type
            $availableStock = StockByWh::select('product_type')
                ->selectRaw('SUM(onhand - allocated) as available_stock')
                ->groupBy('product_type')
                ->get()
                ->keyBy('product_type');

            // Production Plan by product type (using model field as proxy)
            $productionPlan = ProdHeader::select('model as product_type')
                ->selectRaw('SUM(qty_order) as production_plan')
                ->groupBy('model')
                ->get()
                ->keyBy('product_type');

            // Sales Demand by product type
            $salesDemand = SoInvoiceLine::select('product_type')
                ->selectRaw('SUM(delivered_qty) as sales_demand')
                ->selectRaw('AVG(delivered_qty) / 30 as avg_daily_demand')
                ->groupBy('product_type')
                ->get()
                ->keyBy('product_type');

            // Merge data
            $allProductTypes = collect($availableStock->keys())
                ->merge($productionPlan->keys())
                ->merge($salesDemand->keys())
                ->unique();

            $data = $allProductTypes->map(function ($productType) use ($availableStock, $productionPlan, $salesDemand) {
                $stock = $availableStock->get($productType);
                $plan = $productionPlan->get($productType);
                $demand = $salesDemand->get($productType);

                $availableStockQty = $stock->available_stock ?? 0;
                $avgDailyDemand = $demand->avg_daily_demand ?? 1;
                $stockCoverage = $avgDailyDemand > 0 ? round($availableStockQty / $avgDailyDemand, 2) : 0;

                return [
                    'category' => $productType,
                    'available_stock' => $availableStockQty,
                    'production_plan' => $plan->production_plan ?? 0,
                    'sales_demand' => $demand->sales_demand ?? 0,
                    'stock_coverage' => $stockCoverage
                ];
            });

            return response()->json([
                'data' => $data,
                'target_coverage' => 30
            ]);
        } else {
            // Time period analysis
            $period = $request->get('period', 'monthly');

            // Validate period
            if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
                $period = 'monthly';
            }

            $query = ProdHeader::query();

            // Apply date range filter
            $this->applyDateRangeFilter($query, $request, 'planning_date');

            $dateFormat = $this->getDateFormatByPeriod($period, 'planning_date', $query);

            $data = $query->selectRaw("$dateFormat as period")
                ->selectRaw('SUM(qty_order) as production_plan')
                ->groupByRaw($dateFormat)
                ->orderByRaw($dateFormat)
                ->get()
                ->map(function ($item) use ($period) {
                    // Normalize period format
                    $rawPeriod = $item->period ?? '';
                    $periodKey = trim((string) $rawPeriod);

                    if ($period === 'daily') {
                        try {
                            $periodKey = \Carbon\Carbon::parse($periodKey)->format('Y-m-d');
                        } catch (\Exception $e) {
                            // Keep original if parsing fails
                        }
                    } elseif ($period === 'monthly') {
                        if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                            $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        } else {
                            try {
                                $parsed = \Carbon\Carbon::parse($periodKey);
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
                        'production_plan' => $item->production_plan ?? 0,
                    ];
                })
                ->keyBy('period');

            // Generate all periods in range
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);

            // Fill missing periods with zero values
            $filledData = collect($allPeriods)->map(function ($periodValue) use ($data) {
                $existing = $data->get($periodValue);

                if ($existing) {
                    return [
                        'period' => $periodValue,
                        'production_plan' => $existing->production_plan ?? 0,
                    ];
                } else {
                    return [
                        'period' => $periodValue,
                        'production_plan' => 0,
                    ];
                }
            })->values();

            return response()->json([
                'data' => $filledData,
                'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date')
            ]);
        }
    }

    /**
     * Chart 6.5: Lead Time Analysis - Box Plot
     */
    public function leadTimeAnalysis(Request $request): JsonResponse
    {
        // Procurement Lead Time
        $procurementLeadTimes = ReceiptPurchase::selectRaw('DATEDIFF(day, actual_receipt_date, actual_receipt_date) as lead_time')
            ->whereNotNull('actual_receipt_date')
            ->pluck('lead_time')
            ->sort()
            ->values();

        // Production Lead Time
        $productionLeadTimes = ProdHeader::selectRaw('DATEDIFF(day, planning_date, planning_date) as lead_time')
            ->whereNotNull('planning_date')
            ->pluck('lead_time')
            ->sort()
            ->values();

        // Delivery Lead Time
        $deliveryLeadTimes = SoInvoiceLine::selectRaw('DATEDIFF(day, delivery_date, receipt_date) as lead_time')
            ->whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->pluck('lead_time')
            ->sort()
            ->values();

        return response()->json([
            'procurement_lead_time' => $this->calculateBoxPlotStats($procurementLeadTimes),
            'production_lead_time' => $this->calculateBoxPlotStats($productionLeadTimes),
            'delivery_lead_time' => $this->calculateBoxPlotStats($deliveryLeadTimes)
        ]);
    }

    /**
     * Chart 6.6: Material Availability for Production - Heat Map
     */
    public function materialAvailabilityForProduction(Request $request): JsonResponse
    {
        $query = ProdHeader::query();

        // Filter active orders
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        $productionOrders = $query->select('prod_no', 'item', 'qty_order', 'planning_date')
            ->limit(50)
            ->get();

        // Get stock availability for items
        $items = $productionOrders->pluck('item')->unique();
        $stockData = StockByWh::whereIn('partno', $items)
            ->select('partno')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->groupBy('partno')
            ->get()
            ->keyBy('partno');

        $heatmapData = $productionOrders->map(function ($order) use ($stockData) {
            $stock = $stockData->get($order->item);
            $available = $stock->total_onhand ?? 0;
            $required = $order->qty_order;

            // Calculate availability status
            if ($available >= $required) {
                $status = 'available';
                $color = 'dark_green';
            } elseif ($available >= $required * 0.5) {
                $status = 'partial';
                $color = 'light_green';
            } elseif ($available >= $required * 0.2) {
                $status = 'low';
                $color = 'yellow';
            } else {
                $status = 'not_available';
                $color = 'red';
            }

            return [
                'prod_no' => $order->prod_no,
                'part_no' => $order->item,
                'required_qty' => $required,
                'available_qty' => $available,
                'shortage' => max(0, $required - $available),
                'status' => $status,
                'color' => $color,
                'planning_date' => $order->planning_date
            ];
        });

        return response()->json($heatmapData);
    }

    /**
     * Chart 6.7: Backorder Analysis - Pareto Chart
     */
    public function backorderAnalysis(): JsonResponse
    {
        // Items with low stock or stockout
        $data = StockByWh::select('partno', 'desc')
            ->selectRaw('COUNT(CASE WHEN onhand < safety_stock THEN 1 END) as stockout_frequency')
            ->selectRaw('SUM(CASE WHEN onhand < safety_stock THEN (safety_stock - onhand) ELSE 0 END) as backorder_qty')
            ->groupBy('partno', 'desc')
            ->having('stockout_frequency', '>', 0)
            ->orderBy('stockout_frequency', 'desc')
            ->limit(50)
            ->get();

        // Calculate cumulative percentage
        $totalStockouts = $data->sum('stockout_frequency');
        $cumulative = 0;

        $data = $data->map(function ($item, $index) use ($totalStockouts, &$cumulative) {
            $cumulative += $item->stockout_frequency;
            $item->cumulative_percentage = $totalStockouts > 0
                ? round(($cumulative / $totalStockouts) * 100, 2)
                : 0;
            $item->rank = $index + 1;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'pareto_line' => 80
        ]);
    }

    /**
     * Chart 6.8: Supply Chain Cycle Time Trend - Line Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (for all date fields)
     * - date_to: End date filter (for all date fields)
     */
    public function supplyChainCycleTimeTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', 'monthly');

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'monthly';
        }

        // Get date format based on period
        $procurementDateFormat = $this->getDateFormatByPeriod($period, 'actual_receipt_date', null);
        $productionDateFormat = $this->getDateFormatByPeriod($period, 'planning_date', null);
        $deliveryDateFormat = $this->getDateFormatByPeriod($period, 'invoice_date', null);

        // Procurement Cycle Time Trend
        $procurementQuery = ReceiptPurchase::query();
        $this->applyDateRangeFilter($procurementQuery, $request, 'actual_receipt_date');

        $procurementTrend = $procurementQuery->selectRaw("$procurementDateFormat as period")
            ->selectRaw('AVG(DATEDIFF(day, actual_receipt_date, actual_receipt_date)) as procurement_cycle_time')
            ->groupByRaw($procurementDateFormat)
            ->orderByRaw($procurementDateFormat)
            ->get()
            ->keyBy('period');

        // Production Cycle Time Trend
        $productionQuery = ProdHeader::query();
        $this->applyDateRangeFilter($productionQuery, $request, 'planning_date');

        $productionTrend = $productionQuery->selectRaw("$productionDateFormat as period")
            ->selectRaw('AVG(DATEDIFF(day, planning_date, planning_date)) as production_cycle_time')
            ->groupByRaw($productionDateFormat)
            ->orderByRaw($productionDateFormat)
            ->get()
            ->keyBy('period');

        // Delivery Cycle Time Trend
        $deliveryQuery = SoInvoiceLine::query();
        $this->applyDateRangeFilter($deliveryQuery, $request, 'invoice_date');

        $deliveryTrend = $deliveryQuery->selectRaw("$deliveryDateFormat as period")
            ->selectRaw('AVG(DATEDIFF(day, delivery_date, receipt_date)) as delivery_cycle_time')
            ->whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->groupByRaw($deliveryDateFormat)
            ->orderByRaw($deliveryDateFormat)
            ->get()
            ->keyBy('period');

        // Normalize period keys
        $procurementTrend = $procurementTrend->mapWithKeys(function ($item) use ($period) {
            $rawPeriod = $item->period ?? '';
            $periodKey = trim((string) $rawPeriod);

            if ($period === 'daily') {
                try {
                    $periodKey = \Carbon\Carbon::parse($periodKey)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            } elseif ($period === 'monthly') {
                if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                    $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                } else {
                    try {
                        $parsed = \Carbon\Carbon::parse($periodKey);
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

            return [$periodKey => $item];
        });

        $productionTrend = $productionTrend->mapWithKeys(function ($item) use ($period) {
            $rawPeriod = $item->period ?? '';
            $periodKey = trim((string) $rawPeriod);

            if ($period === 'daily') {
                try {
                    $periodKey = \Carbon\Carbon::parse($periodKey)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            } elseif ($period === 'monthly') {
                if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                    $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                } else {
                    try {
                        $parsed = \Carbon\Carbon::parse($periodKey);
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

            return [$periodKey => $item];
        });

        $deliveryTrend = $deliveryTrend->mapWithKeys(function ($item) use ($period) {
            $rawPeriod = $item->period ?? '';
            $periodKey = trim((string) $rawPeriod);

            if ($period === 'daily') {
                try {
                    $periodKey = \Carbon\Carbon::parse($periodKey)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            } elseif ($period === 'monthly') {
                if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $periodKey, $matches)) {
                    $periodKey = $matches[1] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                } else {
                    try {
                        $parsed = \Carbon\Carbon::parse($periodKey);
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

            return [$periodKey => $item];
        });

        // Generate all periods in range
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);

        // Fill missing periods with zero values
        $data = collect($allPeriods)->map(function ($periodValue) use ($procurementTrend, $productionTrend, $deliveryTrend) {
            $procurement = $procurementTrend->get($periodValue);
            $production = $productionTrend->get($periodValue);
            $delivery = $deliveryTrend->get($periodValue);

            $procurementTime = $procurement ? ($procurement->procurement_cycle_time ?? 0) : 0;
            $productionTime = $production ? ($production->production_cycle_time ?? 0) : 0;
            $deliveryTime = $delivery ? ($delivery->delivery_cycle_time ?? 0) : 0;

            return [
                'period' => $periodValue,
                'procurement_cycle_time' => round($procurementTime, 2),
                'production_cycle_time' => round($productionTime, 2),
                'delivery_cycle_time' => round($deliveryTime, 2),
                'total_order_to_cash_cycle_time' => round($procurementTime + $productionTime + $deliveryTime, 2)
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'multiple')
        ]);
    }

    /**
     * Chart 6.9: Shipment Table - Table View
     *
     * Query Parameters:
     * - page: Page number for pagination (default: 1)
     * - per_page: Number of records per page (default: 10, max: 100)
     * - sort_by: Column to sort by (shipment, customer_po, delivery_date, lead_time) - default: delivery_date
     * - sort_order: Sort direction (asc, desc) - default: desc
     * - search: Search term for shipment or customer_po
     */
    public function shipmentTable(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $sortBy = $request->get('sort_by', 'delivery_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $search = $request->get('search', '');

        // Validate sort_by
        $allowedSortColumns = ['shipment', 'customer_po', 'delivery_date', 'lead_time'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'delivery_date';
        }

        // Validate sort_order
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        // Detect database driver to use appropriate SQL syntax
        $query = SoInvoiceLine::query();
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();
        $isSqlServer = ($driver === 'sqlsrv');

        // Build date difference calculation based on database type
        if ($isSqlServer) {
            $leadTimeExpression = "DATEDIFF(day, delivery_date, GETDATE())";
        } else {
            // MySQL: DATEDIFF returns date1 - date2, so we need to reverse the order
            $leadTimeExpression = "DATEDIFF(NOW(), delivery_date)";
        }

        $query->select(
                'shipment',
                'shipment_status',
                'customer_po',
                'delivery_date',
                'product_type',
                'shipment_reference'
            )
            ->selectRaw("{$leadTimeExpression} as lead_time")
            ->whereNotNull('shipment')
            ->whereNotNull('delivery_date')
            // only include shipments with shipment_status = 'Approved' (case-insensitive)
            ->whereRaw("UPPER(LTRIM(RTRIM(shipment_status))) = UPPER(?)", ['Approved']);

        // Group by to get distinct shipments
        $query->groupBy('shipment', 'shipment_status', 'customer_po', 'delivery_date', 'product_type', 'shipment_reference');

        // Apply search filter
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('shipment', 'LIKE', "%{$search}%")
                ->orWhere('customer_po', 'LIKE', "%{$search}%");
            });
        }

        // Apply sorting
        if ($sortBy === 'lead_time') {
            $query->orderByRaw("{$leadTimeExpression} {$sortOrder}");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Check if 'all' parameter is requested
        if (strtolower($perPage) === 'all') {
            // Get all results without pagination
            $shipments = $query->get();

            return response()->json([
                'data' => $shipments,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $shipments->count(),
                    'total' => $shipments->count(),
                    'last_page' => 1,
                    'from' => $shipments->count() > 0 ? 1 : null,
                    'to' => $shipments->count()
                ],
                'filters' => [
                    'search' => $search,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'shipment_status' => 'Approved'
                ]
            ]);
        }

        // Ensure per_page is a valid number and within limits
        $perPage = min((int)$perPage, 100);

        // Get paginated results
        $shipments = $query->paginate($perPage);

        return response()->json([
            'data' => $shipments->items(),
            'pagination' => [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
                'from' => $shipments->firstItem(),
                'to' => $shipments->lastItem()
            ],
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'shipment_status' => 'Approved'
            ]
        ]);
    }

    /**
     * Chart 6.10: Shipment Status Comparison - Bar Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly) - default: monthly
     * - date_from: Start date filter
     * - date_to: End date filter
     *
     * Returns:
     * - period: The period label (date for daily, month for monthly)
     * - total_shipment: Total count of distinct shipments
     * - approved_count: Count of shipments with status 'Approved'
     * - released_count: Count of shipments with status 'Released'
     * - invoiced_count: Count of shipments with status 'Invoiced'
     */
    public function shipmentStatusComparison(Request $request): JsonResponse
    {
        $period = $request->get('period', 'monthly');

        // Validate period
        if (!in_array($period, ['daily', 'monthly'])) {
            $period = 'monthly';
        }

        $query = SoInvoiceLine::query();

        // Apply date range filter using delivery_date
        $this->applyDateRangeFilter($query, $request, 'delivery_date');

        // Get date format based on period
        $dateFormat = $this->getDateFormatByPeriod($period, 'delivery_date', $query);

        // Detect database driver for case-insensitive comparison
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();
        $isSqlServer = ($driver === 'sqlsrv');

        // Build case-insensitive status comparison
        if ($isSqlServer) {
            $approvedCondition = "UPPER(LTRIM(RTRIM(shipment_status))) = 'APPROVED'";
            $releasedCondition = "UPPER(LTRIM(RTRIM(shipment_status))) = 'RELEASED'";
            $invoicedCondition = "UPPER(LTRIM(RTRIM(shipment_status))) = 'INVOICED'";
            $processedCondition = "UPPER(LTRIM(RTRIM(shipment_status))) = 'PROCESSED'";
        } else {
            $approvedCondition = "UPPER(TRIM(shipment_status)) = 'APPROVED'";
            $releasedCondition = "UPPER(TRIM(shipment_status)) = 'RELEASED'";
            $invoicedCondition = "UPPER(TRIM(shipment_status)) = 'INVOICED'";
            $processedCondition = "UPPER(TRIM(shipment_status)) = 'PROCESSED'";
        }

        // Query to get grouped data by period
        // Count distinct shipments per status
        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('COUNT(DISTINCT shipment) as total_shipment')
            ->selectRaw("COUNT(DISTINCT CASE WHEN $approvedCondition THEN shipment END) as approved_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN $releasedCondition THEN shipment END) as released_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN $invoicedCondition THEN shipment END) as invoiced_count")
            ->selectRaw("COUNT(DISTINCT CASE WHEN $processedCondition THEN shipment END) as processed_count")
            ->whereNotNull('shipment')
            ->whereNotNull('delivery_date')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get()
            ->map(function ($item) use ($period) {
                // Normalize period format
                $rawPeriod = $item->period ?? '';
                $periodKey = trim((string) $rawPeriod);

                if ($period === 'daily') {
                    try {
                        $periodKey = \Carbon\Carbon::parse($periodKey)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                } elseif ($period === 'monthly') {
                    // Handle various monthly formats: "2024-01", "2024-1", "2024/01", etc.
                    // Remove any whitespace first
                    $periodKey = preg_replace('/\s+/', '', $periodKey);

                    // Try to match YYYY-MM or YYYY-M format (with dash or slash)
                    if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $periodKey, $matches)) {
                        $year = $matches[1];
                        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $periodKey = $year . '-' . $month;
                    } elseif (preg_match('/^(\d{4})(\d{2})$/', $periodKey, $matches)) {
                        // Handle format like "202401" (YYYYMM)
                        $year = $matches[1];
                        $month = $matches[2];
                        $periodKey = $year . '-' . $month;
                    } else {
                        // Try to parse as date and format
                        try {
                            // Try various date formats
                            $parsed = null;
                            $formats = ['Y-m-d', 'Y-m', 'Y/m/d', 'Y/m', 'Ymd', 'Ym', 'Y'];
                            foreach ($formats as $format) {
                                try {
                                    $parsed = \Carbon\Carbon::createFromFormat($format, $periodKey);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }

                            if (!$parsed) {
                                $parsed = \Carbon\Carbon::parse($periodKey);
                            }

                            $periodKey = $parsed->format('Y-m');
                        } catch (\Exception $e) {
                            // If all parsing fails, try to extract year-month manually
                            if (preg_match('/(\d{4}).*?(\d{1,2})/', $periodKey, $matches)) {
                                $year = $matches[1];
                                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                $periodKey = $year . '-' . $month;
                            }
                        }
                    }
                }

                return (object) [
                    'period' => $periodKey,
                    'total_shipment' => (int) ($item->total_shipment ?? 0),
                    'approved_count' => (int) ($item->approved_count ?? 0),
                    'released_count' => (int) ($item->released_count ?? 0),
                    'invoiced_count' => (int) ($item->invoiced_count ?? 0),
                    'processed_count' => (int) ($item->processed_count ?? 0)
                ];
            })
            ->keyBy('period');

        // Generate all periods in range
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);

        // Get all unique periods from data (in case normalization created different keys)
        $dataPeriods = $data->keys()->toArray();

        // For monthly period, if no explicit date range, generate periods from data
        if (empty($allPeriods) && $period === 'monthly' && !empty($dataPeriods)) {
            // Sort data periods and generate all months for the year(s) that have data
            $sortedPeriods = collect($dataPeriods)->sort()->values()->toArray();
            if (!empty($sortedPeriods)) {
                $minPeriod = reset($sortedPeriods);
                $maxPeriod = end($sortedPeriods);
                
                // Parse min and max periods
                $start = \Carbon\Carbon::createFromFormat('Y-m', $minPeriod);
                $end = \Carbon\Carbon::createFromFormat('Y-m', $maxPeriod);
                
                // Generate all months from January of start year to December of end year
                $current = $start->copy()->startOfYear();
                $endDate = $end->copy()->endOfYear();
                
                $allPeriods = [];
                while ($current->lte($endDate)) {
                    $allPeriods[] = $current->format('Y-m');
                    $current->addMonth();
                }
            }
        }

        // If still no periods generated, return data as is
        if (empty($allPeriods)) {
            return response()->json([
                'data' => $data->values(),
                'filter_metadata' => $this->getPeriodMetadata($request, 'delivery_date')
            ]);
        }

        // Fill missing periods with zero values using allPeriods
        $filledData = collect($allPeriods)->map(function ($periodValue) use ($data) {
            $existing = $data->get($periodValue);

            if ($existing) {
                return [
                    'period' => $periodValue,
                    'total_shipment' => $existing->total_shipment,
                    'approved_count' => $existing->approved_count,
                    'released_count' => $existing->released_count,
                    'invoiced_count' => $existing->invoiced_count,
                    'processed_count' => $existing->processed_count
                ];
            } else {
                return [
                    'period' => $periodValue,
                    'total_shipment' => 0,
                    'approved_count' => 0,
                    'released_count' => 0,
                    'invoiced_count' => 0,
                    'processed_count' => 0
                ];
            }
        })->values();

        return response()->json([
            'data' => $filledData,
            'filter_metadata' => $this->getPeriodMetadata($request, 'delivery_date')
        ]);
    }


    /**
     * Get all dashboard 6 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'supply_chain_kpi' => $this->supplyChainKpi()->getData(true),
            'order_to_cash_flow' => $this->orderToCashFlow()->getData(true),
            'procure_to_pay_flow' => $this->procureToPayFlow()->getData(true),
            'demand_vs_supply_analysis' => $this->demandVsSupplyAnalysis($request)->getData(true),
            'lead_time_analysis' => $this->leadTimeAnalysis($request)->getData(true),
            'material_availability_for_production' => $this->materialAvailabilityForProduction($request)->getData(true),
            'backorder_analysis' => $this->backorderAnalysis()->getData(true),
            'supply_chain_cycle_time_trend' => $this->supplyChainCycleTimeTrend($request)->getData(true),
            'shipment_table' => $this->shipmentTable($request)->getData(true)
        ]);
    }

    /**
     * Helper function to calculate box plot statistics
     */
    private function calculateBoxPlotStats($data)
    {
        if ($data->isEmpty()) {
            return [
                'min' => 0,
                'q1' => 0,
                'median' => 0,
                'q3' => 0,
                'max' => 0,
                'average' => 0
            ];
        }

        $count = $data->count();
        $min = $data->first();
        $max = $data->last();
        $average = round($data->average(), 2);

        $q1Index = floor($count * 0.25);
        $medianIndex = floor($count * 0.5);
        $q3Index = floor($count * 0.75);

        return [
            'min' => $min,
            'q1' => $data->get($q1Index),
            'median' => $data->get($medianIndex),
            'q3' => $data->get($q3Index),
            'max' => $max,
            'average' => $average
        ];
    }
}
