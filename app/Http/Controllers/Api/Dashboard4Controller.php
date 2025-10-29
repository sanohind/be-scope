<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard4Controller extends ApiController
{
    /**
     * Chart 4.1: Sales Overview KPI - KPI Cards
     */
    public function salesOverviewKpi(Request $request): JsonResponse
    {
        $period = $request->get('period', 'mtd');
        
        $query = SoInvoiceLine::query();
        
        // Apply period filter
        $this->applyPeriodFilter($query, $period);

        $totalSalesAmount = $query->sum('amount_hc');
        $totalShipments = $query->distinct('shipment')->count('shipment');
        $totalInvoices = $query->distinct('invoice_no')->count('invoice_no');
        $outstandingInvoices = $query->where('invoice_status', 'Outstanding')
            ->distinct('invoice_no')
            ->count('invoice_no');

        // Calculate sales growth (requires previous period comparison)
        $previousQuery = SoInvoiceLine::query();
        $this->applyPeriodFilter($previousQuery, $period, true);
        $previousSalesAmount = $previousQuery->sum('amount_hc');
        
        $salesGrowth = $previousSalesAmount > 0 
            ? round((($totalSalesAmount - $previousSalesAmount) / $previousSalesAmount) * 100, 2)
            : 0;

        return response()->json([
            'total_sales_amount' => $totalSalesAmount,
            'total_shipments' => $totalShipments,
            'total_invoices' => $totalInvoices,
            'outstanding_invoices' => $outstandingInvoices,
            'sales_growth' => $salesGrowth,
            'period' => $period
        ]);
    }

    /**
     * Chart 4.2: Revenue Trend - Area Chart with Line
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        
        $query = SoInvoiceLine::query();

        // Apply filters
        if ($request->has('customer')) {
            $query->where('bp_name', $request->customer);
        }
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->has('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        // Group by period
        if ($groupBy === 'daily') {
            $dateFormat = "DATE(invoice_date)";
        } elseif ($groupBy === 'weekly') {
            $dateFormat = "DATE_FORMAT(invoice_date, '%Y-W%u')";
        } else {
            $dateFormat = "DATE_FORMAT(invoice_date, '%Y-%m')";
        }

        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('SUM(amount_hc) as revenue')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 4.3: Top Customers by Revenue - Horizontal Bar Chart
     */
    public function topCustomersByRevenue(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $data = SoInvoiceLine::select('bp_name')
            ->selectRaw('SUM(amount_hc) as total_revenue')
            ->selectRaw('COUNT(DISTINCT sales_order) as number_of_orders')
            ->selectRaw('ROUND(AVG(amount_hc), 2) as avg_order_value')
            ->selectRaw('SUM(delivered_qty) as total_qty')
            ->selectRaw('ROUND(AVG(price_hc), 2) as avg_price')
            ->groupBy('bp_name')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();

        // Calculate revenue contribution percentage
        $totalRevenue = SoInvoiceLine::sum('amount_hc');
        $data = $data->map(function ($item) use ($totalRevenue) {
            $item->revenue_contribution = $totalRevenue > 0 
                ? round(($item->total_revenue / $totalRevenue) * 100, 2) 
                : 0;
            return $item;
        });

        return response()->json($data);
    }

    /**
     * Chart 4.4: Sales by Product Type - Donut Chart
     */
    public function salesByProductType(): JsonResponse
    {
        $data = SoInvoiceLine::select('product_type')
            ->selectRaw('SUM(amount_hc) as revenue')
            ->selectRaw('SUM(delivered_qty) as qty_sold')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->groupBy('product_type')
            ->orderBy('revenue', 'desc')
            ->get();

        $totalRevenue = $data->sum('revenue');

        // Calculate percentage for each product type
        $data = $data->map(function ($item) use ($totalRevenue) {
            $item->percentage = $totalRevenue > 0 
                ? round(($item->revenue / $totalRevenue) * 100, 2) 
                : 0;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'total_revenue' => $totalRevenue
        ]);
    }

    /**
     * Chart 4.5: Shipment Status Tracking - Funnel Chart
     */
    public function shipmentStatusTracking(): JsonResponse
    {
        $salesOrdersCreated = SoInvoiceLine::distinct('sales_order')->count('sales_order');
        $shipmentsGenerated = SoInvoiceLine::distinct('shipment')->count('shipment');
        $receiptsConfirmed = SoInvoiceLine::whereNotNull('receipt')
            ->distinct('receipt')
            ->count('receipt');
        $invoicesIssued = SoInvoiceLine::distinct('invoice_no')->count('invoice_no');
        $invoicesPaid = SoInvoiceLine::where('invoice_status', 'Paid')
            ->distinct('invoice_no')
            ->count('invoice_no');

        // Calculate conversion rates
        $conversionRates = [
            'sales_to_shipment' => $salesOrdersCreated > 0 
                ? round(($shipmentsGenerated / $salesOrdersCreated) * 100, 2) 
                : 0,
            'shipment_to_receipt' => $shipmentsGenerated > 0 
                ? round(($receiptsConfirmed / $shipmentsGenerated) * 100, 2) 
                : 0,
            'receipt_to_invoice' => $receiptsConfirmed > 0 
                ? round(($invoicesIssued / $receiptsConfirmed) * 100, 2) 
                : 0,
            'invoice_to_paid' => $invoicesIssued > 0 
                ? round(($invoicesPaid / $invoicesIssued) * 100, 2) 
                : 0
        ];

        return response()->json([
            'stages' => [
                'sales_orders_created' => $salesOrdersCreated,
                'shipments_generated' => $shipmentsGenerated,
                'receipts_confirmed' => $receiptsConfirmed,
                'invoices_issued' => $invoicesIssued,
                'invoices_paid' => $invoicesPaid
            ],
            'conversion_rates' => $conversionRates
        ]);
    }

    /**
     * Chart 4.6: Delivery Performance - Gauge Chart
     */
    public function deliveryPerformance(): JsonResponse
    {
        $totalDeliveries = SoInvoiceLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->count();

        $earlyDeliveries = SoInvoiceLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date < delivery_date')
            ->count();

        $onTimeDeliveries = SoInvoiceLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date = delivery_date')
            ->count();

        $lateDeliveries = SoInvoiceLine::whereNotNull('receipt_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('receipt_date > delivery_date')
            ->count();

        $earlyPercentage = $totalDeliveries > 0 
            ? round(($earlyDeliveries / $totalDeliveries) * 100, 2) 
            : 0;
        $onTimePercentage = $totalDeliveries > 0 
            ? round(($onTimeDeliveries / $totalDeliveries) * 100, 2) 
            : 0;
        $latePercentage = $totalDeliveries > 0 
            ? round(($lateDeliveries / $totalDeliveries) * 100, 2) 
            : 0;

        $onTimeDeliveryRate = $earlyPercentage + $onTimePercentage;

        return response()->json([
            'on_time_delivery_rate' => $onTimeDeliveryRate,
            'early_delivery_percentage' => $earlyPercentage,
            'on_time_delivery_percentage' => $onTimePercentage,
            'late_delivery_percentage' => $latePercentage,
            'total_deliveries' => $totalDeliveries,
            'target' => 95
        ]);
    }

    /**
     * Chart 4.7: Invoice Status Distribution - Stacked Bar Chart
     */
    public function invoiceStatusDistribution(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        
        $query = SoInvoiceLine::query();

        // Group by period or customer
        if ($groupBy === 'customer') {
            $data = $query->select('bp_name as category', 'invoice_status')
                ->selectRaw('COUNT(DISTINCT invoice_no) as count')
                ->groupBy('bp_name', 'invoice_status')
                ->orderBy('bp_name')
                ->get();
        } else {
            $dateFormat = "DATE_FORMAT(invoice_date, '%Y-%m')";
            $data = $query->selectRaw("$dateFormat as category")
                ->select('invoice_status')
                ->selectRaw('COUNT(DISTINCT invoice_no) as count')
                ->groupByRaw($dateFormat)
                ->groupBy('invoice_status')
                ->orderByRaw($dateFormat)
                ->get();
        }

        return response()->json($data);
    }

    /**
     * Chart 4.8: Sales Order Fulfillment - Combo Chart
     */
    public function salesOrderFulfillment(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'period');
        
        $query = SoInvoiceLine::query();

        if ($groupBy === 'product_type') {
            $data = $query->select('product_type as category')
                ->selectRaw('SUM(delivered_qty) as delivered_qty')
                ->selectRaw('SUM(invoice_qty) as invoiced_qty')
                ->selectRaw('ROUND((SUM(invoice_qty) / NULLIF(SUM(delivered_qty), 0)) * 100, 2) as fulfillment_rate')
                ->groupBy('product_type')
                ->get();
        } else {
            $dateFormat = "DATE_FORMAT(invoice_date, '%Y-%m')";
            $data = $query->selectRaw("$dateFormat as category")
                ->selectRaw('SUM(delivered_qty) as delivered_qty')
                ->selectRaw('SUM(invoice_qty) as invoiced_qty')
                ->selectRaw('ROUND((SUM(invoice_qty) / NULLIF(SUM(delivered_qty), 0)) * 100, 2) as fulfillment_rate')
                ->groupByRaw($dateFormat)
                ->orderByRaw($dateFormat)
                ->get();
        }

        return response()->json($data);
    }

    /**
     * Chart 4.9: Top Selling Products - Data Table with Sparkline
     */
    public function topSellingProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 50);
        
        $query = SoInvoiceLine::query();

        // Apply filters
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->has('customer')) {
            $query->where('bp_name', $request->customer);
        }
        if ($request->has('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        $data = $query->select('part_no', 'old_partno', 'cust_partname')
            ->selectRaw('SUM(delivered_qty) as total_qty_sold')
            ->selectRaw('SUM(amount_hc) as total_amount')
            ->selectRaw('COUNT(DISTINCT sales_order) as number_of_orders')
            ->selectRaw('ROUND(AVG(price_hc), 2) as avg_price')
            ->groupBy('part_no', 'old_partno', 'cust_partname')
            ->orderBy('total_amount', 'desc')
            ->limit($limit)
            ->get();

        // Add rank
        $data = $data->map(function ($item, $index) {
            $item->rank = $index + 1;
            return $item;
        });

        return response()->json($data);
    }

    /**
     * Chart 4.10: Revenue by Currency - Pie Chart
     */
    public function revenueByCurrency(): JsonResponse
    {
        $data = SoInvoiceLine::select('currency')
            ->selectRaw('SUM(amount) as amount_original')
            ->selectRaw('SUM(amount_hc) as amount_hc')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->groupBy('currency')
            ->orderBy('amount_hc', 'desc')
            ->get();

        $totalAmountHc = $data->sum('amount_hc');

        // Calculate percentage
        $data = $data->map(function ($item) use ($totalAmountHc) {
            $item->percentage = $totalAmountHc > 0 
                ? round(($item->amount_hc / $totalAmountHc) * 100, 2) 
                : 0;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'total_amount_hc' => $totalAmountHc
        ]);
    }

    /**
     * Chart 4.11: Monthly Sales Comparison - Clustered Column Chart
     */
    public function monthlySalesComparison(Request $request): JsonResponse
    {
        $currentYear = $request->get('current_year', date('Y'));
        $previousYear = $currentYear - 1;

        // Apply filters
        $query = SoInvoiceLine::query();
        
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->has('customer')) {
            $query->where('bp_name', $request->customer);
        }

        $data = $query->selectRaw("DATE_FORMAT(invoice_date, '%m') as month")
            ->selectRaw("YEAR(invoice_date) as year")
            ->selectRaw('SUM(amount_hc) as revenue')
            ->whereIn(DB::raw('YEAR(invoice_date)'), [$currentYear, $previousYear])
            ->groupByRaw("YEAR(invoice_date), DATE_FORMAT(invoice_date, '%m')")
            ->orderByRaw("DATE_FORMAT(invoice_date, '%m')")
            ->get();

        // Calculate YoY growth
        $grouped = $data->groupBy('month');
        $result = [];

        foreach ($grouped as $month => $items) {
            $currentYearData = $items->where('year', $currentYear)->first();
            $previousYearData = $items->where('year', $previousYear)->first();

            $currentRevenue = $currentYearData ? $currentYearData->revenue : 0;
            $previousRevenue = $previousYearData ? $previousYearData->revenue : 0;

            $yoyGrowth = $previousRevenue > 0 
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2) 
                : 0;

            $result[] = [
                'month' => $month,
                'current_year_revenue' => $currentRevenue,
                'previous_year_revenue' => $previousRevenue,
                'yoy_growth' => $yoyGrowth
            ];
        }

        return response()->json($result);
    }

    /**
     * Get all dashboard 4 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'sales_overview_kpi' => $this->salesOverviewKpi($request)->getData(true),
            'revenue_trend' => $this->revenueTrend($request)->getData(true),
            'top_customers_by_revenue' => $this->topCustomersByRevenue($request)->getData(true),
            'sales_by_product_type' => $this->salesByProductType()->getData(true),
            'shipment_status_tracking' => $this->shipmentStatusTracking()->getData(true),
            'delivery_performance' => $this->deliveryPerformance()->getData(true),
            'invoice_status_distribution' => $this->invoiceStatusDistribution($request)->getData(true),
            'sales_order_fulfillment' => $this->salesOrderFulfillment($request)->getData(true),
            'top_selling_products' => $this->topSellingProducts($request)->getData(true),
            'revenue_by_currency' => $this->revenueByCurrency()->getData(true),
            'monthly_sales_comparison' => $this->monthlySalesComparison($request)->getData(true)
        ]);
    }

    /**
     * Helper function to apply period filter
     */
    private function applyPeriodFilter($query, $period, $previous = false)
    {
        $now = now();
        
        switch ($period) {
            case 'mtd': // Month to Date
                if ($previous) {
                    $query->whereYear('invoice_date', $now->copy()->subMonth()->year)
                          ->whereMonth('invoice_date', $now->copy()->subMonth()->month);
                } else {
                    $query->whereYear('invoice_date', $now->year)
                          ->whereMonth('invoice_date', $now->month);
                }
                break;
            case 'qtd': // Quarter to Date
                $quarter = ceil($now->month / 3);
                if ($previous) {
                    $prevQuarter = $quarter - 1;
                    $year = $now->year;
                    if ($prevQuarter < 1) {
                        $prevQuarter = 4;
                        $year--;
                    }
                    $query->whereYear('invoice_date', $year)
                          ->whereRaw('QUARTER(invoice_date) = ?', [$prevQuarter]);
                } else {
                    $query->whereYear('invoice_date', $now->year)
                          ->whereRaw('QUARTER(invoice_date) = ?', [$quarter]);
                }
                break;
            case 'ytd': // Year to Date
                if ($previous) {
                    $query->whereYear('invoice_date', $now->year - 1);
                } else {
                    $query->whereYear('invoice_date', $now->year);
                }
                break;
        }
    }
}
