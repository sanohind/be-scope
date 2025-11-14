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
                ->get();

            return response()->json([
                'data' => $data,
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

        // Merge all periods
        $allPeriods = collect($procurementTrend->keys())
            ->merge($productionTrend->keys())
            ->merge($deliveryTrend->keys())
            ->unique()
            ->sort()
            ->values();

        $data = $allPeriods->map(function ($period) use ($procurementTrend, $productionTrend, $deliveryTrend) {
            $procurement = $procurementTrend->get($period);
            $production = $productionTrend->get($period);
            $delivery = $deliveryTrend->get($period);

            $procurementTime = $procurement->procurement_cycle_time ?? 0;
            $productionTime = $production->production_cycle_time ?? 0;
            $deliveryTime = $delivery->delivery_cycle_time ?? 0;

            return [
                'period' => $period,
                'procurement_cycle_time' => round($procurementTime, 2),
                'production_cycle_time' => round($productionTime, 2),
                'delivery_cycle_time' => round($deliveryTime, 2),
                'total_order_to_cash_cycle_time' => round($procurementTime + $productionTime + $deliveryTime, 2)
            ];
        });

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'multiple')
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
            'supply_chain_cycle_time_trend' => $this->supplyChainCycleTimeTrend($request)->getData(true)
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
