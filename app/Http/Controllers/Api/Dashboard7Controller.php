<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use App\Models\ReceiptPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard7Controller extends ApiController
{
    /**
     * Chart 7.1: Financial KPI - KPI Cards
     */
    public function financialKpi(Request $request): JsonResponse
    {
        $period = $request->get('period', 'mtd');
        
        $salesQuery = SoInvoiceLine::query();
        $costQuery = ReceiptPurchase::query();
        
        // Apply period filter
        $this->applyPeriodFilter($salesQuery, $period, 'invoice_date');
        $this->applyPeriodFilter($costQuery, $period, 'actual_receipt_date');

        $totalRevenue = $salesQuery->sum('amount_hc');
        $totalCost = $costQuery->sum('receipt_amount');
        
        $grossMargin = $totalRevenue > 0 
            ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2) 
            : 0;

        // Outstanding AR
        $outstandingAr = SoInvoiceLine::where('invoice_status', '<>', 'Paid')
            ->sum('amount_hc');

        // Outstanding AP
        $outstandingAp = ReceiptPurchase::whereNull('payment_doc')
            ->sum('inv_amount');

        // DSO (Days Sales Outstanding)
        $avgDailySales = $totalRevenue / 30; // Simplified calculation
        $dso = $avgDailySales > 0 
            ? round($outstandingAr / $avgDailySales, 2) 
            : 0;

        return response()->json([
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'gross_margin' => $grossMargin,
            'outstanding_ar' => $outstandingAr,
            'outstanding_ap' => $outstandingAp,
            'dso' => $dso,
            'period' => $period
        ]);
    }

    /**
     * Chart 7.2: Revenue vs Cost Trend - Combo Chart
     */
    public function revenueVsCostTrend(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        
        $dateFormat = $groupBy === 'quarterly' 
            ? "CONCAT(YEAR(invoice_date), '-Q', QUARTER(invoice_date))"
            : "DATE_FORMAT(invoice_date, '%Y-%m')";

        // Revenue trend
        $revenueTrend = SoInvoiceLine::selectRaw("$dateFormat as period")
            ->selectRaw('SUM(amount_hc) as revenue')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get()
            ->keyBy('period');

        // Cost trend
        $costDateFormat = $groupBy === 'quarterly' 
            ? "CONCAT(YEAR(actual_receipt_date), '-Q', QUARTER(actual_receipt_date))"
            : "DATE_FORMAT(actual_receipt_date, '%Y-%m')";

        $costTrend = ReceiptPurchase::selectRaw("$costDateFormat as period")
            ->selectRaw('SUM(receipt_amount) as cost')
            ->groupByRaw($costDateFormat)
            ->orderByRaw($costDateFormat)
            ->get()
            ->keyBy('period');

        // Merge data
        $allPeriods = collect($revenueTrend->keys())
            ->merge($costTrend->keys())
            ->unique()
            ->sort()
            ->values();

        $data = $allPeriods->map(function ($period) use ($revenueTrend, $costTrend) {
            $revenue = $revenueTrend->get($period);
            $cost = $costTrend->get($period);

            $revenueAmount = $revenue->revenue ?? 0;
            $costAmount = $cost->cost ?? 0;

            $grossMarginPercent = $revenueAmount > 0 
                ? round((($revenueAmount - $costAmount) / $revenueAmount) * 100, 2) 
                : 0;

            return [
                'period' => $period,
                'revenue' => $revenueAmount,
                'cost' => $costAmount,
                'gross_margin_percent' => $grossMarginPercent
            ];
        });

        return response()->json([
            'data' => $data,
            'target_margin' => 25
        ]);
    }

    /**
     * Chart 7.3: Revenue by Customer Segment - Stacked Bar Chart
     */
    public function revenueByCustomerSegment(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        $topCustomersLimit = $request->get('top_customers', 10);

        $dateFormat = $groupBy === 'quarterly' 
            ? "CONCAT(YEAR(invoice_date), '-Q', QUARTER(invoice_date))"
            : "DATE_FORMAT(invoice_date, '%Y-%m')";

        // Get top customers
        $topCustomers = SoInvoiceLine::select('bp_name')
            ->selectRaw('SUM(amount_hc) as total_revenue')
            ->groupBy('bp_name')
            ->orderBy('total_revenue', 'desc')
            ->limit($topCustomersLimit)
            ->pluck('bp_name');

        // Get revenue by period and customer
        $data = SoInvoiceLine::selectRaw("$dateFormat as period")
            ->selectRaw("CASE WHEN bp_name IN ('" . $topCustomers->implode("','") . "') THEN bp_name ELSE 'Others' END as customer")
            ->selectRaw('SUM(amount_hc) as revenue')
            ->groupByRaw($dateFormat)
            ->groupByRaw("CASE WHEN bp_name IN ('" . $topCustomers->implode("','") . "') THEN bp_name ELSE 'Others' END")
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 7.4: Cost Analysis by Category - Waterfall Chart
     */
    public function costAnalysisByCategory(): JsonResponse
    {
        $data = ReceiptPurchase::select('item_group')
            ->selectRaw('SUM(receipt_amount) as total_cost')
            ->groupBy('item_group')
            ->orderBy('total_cost', 'desc')
            ->get();

        $totalCost = $data->sum('total_cost');

        return response()->json([
            'categories' => $data,
            'total_cost' => $totalCost,
            'budget' => $totalCost * 1.1 // Example: 10% buffer
        ]);
    }

    /**
     * Chart 7.5: Margin Analysis by Product - Scatter Plot
     */
    public function marginAnalysisByProduct(): JsonResponse
    {
        // Get sales data
        $salesData = SoInvoiceLine::select('part_no', 'product_type')
            ->selectRaw('SUM(delivered_qty) as sales_volume')
            ->selectRaw('SUM(amount_hc) as total_revenue')
            ->selectRaw('AVG(price_hc) as avg_sales_price')
            ->groupBy('part_no', 'product_type')
            ->get()
            ->keyBy('part_no');

        // Get cost data
        $costData = ReceiptPurchase::select('part_no')
            ->selectRaw('AVG(receipt_unit_price) as avg_purchase_cost')
            ->groupBy('part_no')
            ->get()
            ->keyBy('part_no');

        // Merge and calculate margin
        $data = $salesData->map(function ($item) use ($costData) {
            $cost = $costData->get($item->part_no);
            $avgCost = $cost->avg_purchase_cost ?? 0;
            $avgPrice = $item->avg_sales_price ?? 0;

            $marginPercent = $avgPrice > 0 
                ? round((($avgPrice - $avgCost) / $avgPrice) * 100, 2) 
                : 0;

            return [
                'part_no' => $item->part_no,
                'product_type' => $item->product_type,
                'sales_volume' => $item->sales_volume,
                'total_revenue' => $item->total_revenue,
                'margin_percent' => $marginPercent,
                'margin_amount' => ($avgPrice - $avgCost) * $item->sales_volume
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Chart 7.6: Outstanding Receivables Aging - Stacked Column Chart
     */
    public function outstandingReceivablesAging(): JsonResponse
    {
        $data = SoInvoiceLine::select('bp_name')
            ->selectRaw("
                SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 0 AND 30 THEN amount_hc ELSE 0 END) as current_0_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 31 AND 60 THEN amount_hc ELSE 0 END) as overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 61 AND 90 THEN amount_hc ELSE 0 END) as overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 91 AND 120 THEN amount_hc ELSE 0 END) as overdue_91_120,
                SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) > 120 THEN amount_hc ELSE 0 END) as overdue_over_120,
                SUM(amount_hc) as total_outstanding
            ")
            ->where('invoice_status', '<>', 'Paid')
            ->groupBy('bp_name')
            ->orderBy('total_outstanding', 'desc')
            ->limit(20)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 7.7: Outstanding Payables Aging - Stacked Column Chart
     */
    public function outstandingPayablesAging(): JsonResponse
    {
        $data = ReceiptPurchase::select('bp_name')
            ->selectRaw("
                SUM(CASE WHEN inv_due_date > CURDATE() THEN inv_amount ELSE 0 END) as not_yet_due,
                SUM(CASE WHEN DATEDIFF(CURDATE(), inv_due_date) BETWEEN 0 AND 30 THEN inv_amount ELSE 0 END) as current_0_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), inv_due_date) BETWEEN 31 AND 60 THEN inv_amount ELSE 0 END) as overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), inv_due_date) BETWEEN 61 AND 90 THEN inv_amount ELSE 0 END) as overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), inv_due_date) > 90 THEN inv_amount ELSE 0 END) as overdue_over_90,
                SUM(inv_amount) as total_outstanding
            ")
            ->whereNull('payment_doc')
            ->groupBy('bp_name')
            ->orderBy('total_outstanding', 'desc')
            ->limit(20)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 7.8: Cash Flow Projection - Waterfall Chart
     */
    public function cashFlowProjection(): JsonResponse
    {
        // Expected receipts by time bucket
        $expectedReceipts = [
            'current' => SoInvoiceLine::where('invoice_status', '<>', 'Paid')
                ->whereRaw('DATEDIFF(CURDATE(), invoice_date) <= 30')
                ->sum('amount_hc'),
            'next_30_days' => SoInvoiceLine::where('invoice_status', '<>', 'Paid')
                ->whereRaw('DATEDIFF(CURDATE(), invoice_date) BETWEEN 31 AND 60')
                ->sum('amount_hc'),
            'next_31_60_days' => SoInvoiceLine::where('invoice_status', '<>', 'Paid')
                ->whereRaw('DATEDIFF(CURDATE(), invoice_date) BETWEEN 61 AND 90')
                ->sum('amount_hc'),
            'next_61_90_days' => SoInvoiceLine::where('invoice_status', '<>', 'Paid')
                ->whereRaw('DATEDIFF(CURDATE(), invoice_date) > 90')
                ->sum('amount_hc')
        ];

        // Expected payments by time bucket
        $expectedPayments = [
            'current' => ReceiptPurchase::whereNull('payment_doc')
                ->whereRaw('DATEDIFF(CURDATE(), inv_due_date) <= 0')
                ->sum('inv_amount'),
            'next_30_days' => ReceiptPurchase::whereNull('payment_doc')
                ->whereRaw('DATEDIFF(inv_due_date, CURDATE()) BETWEEN 1 AND 30')
                ->sum('inv_amount'),
            'next_31_60_days' => ReceiptPurchase::whereNull('payment_doc')
                ->whereRaw('DATEDIFF(inv_due_date, CURDATE()) BETWEEN 31 AND 60')
                ->sum('inv_amount'),
            'next_61_90_days' => ReceiptPurchase::whereNull('payment_doc')
                ->whereRaw('DATEDIFF(inv_due_date, CURDATE()) > 60')
                ->sum('inv_amount')
        ];

        $currentCashBalance = 0; // This would come from accounting system
        $netOperatingCashFlow = array_sum($expectedReceipts) - array_sum($expectedPayments);
        $projectedCashBalance = $currentCashBalance + $netOperatingCashFlow;

        return response()->json([
            'current_cash_balance' => $currentCashBalance,
            'expected_receipts' => $expectedReceipts,
            'expected_payments' => $expectedPayments,
            'net_operating_cash_flow' => $netOperatingCashFlow,
            'projected_cash_balance' => $projectedCashBalance
        ]);
    }

    /**
     * Chart 7.9: Top Profitable Products - Data Table
     */
    public function topProfitableProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 50);

        // Get sales data
        $salesData = SoInvoiceLine::select('part_no')
            ->selectRaw('SUM(amount_hc) as revenue')
            ->selectRaw('SUM(delivered_qty) as sales_volume')
            ->groupBy('part_no')
            ->get()
            ->keyBy('part_no');

        // Get cost data
        $costData = ReceiptPurchase::select('part_no', 'item_desc')
            ->selectRaw('SUM(receipt_amount) as cost')
            ->groupBy('part_no', 'item_desc')
            ->get()
            ->keyBy('part_no');

        // Calculate total profit for percentage
        $totalProfit = 0;

        // Merge and calculate
        $data = $salesData->map(function ($item) use ($costData, &$totalProfit) {
            $cost = $costData->get($item->part_no);
            $costAmount = $cost->cost ?? 0;
            $revenue = $item->revenue;

            $grossMargin = $revenue - $costAmount;
            $marginPercent = $revenue > 0 
                ? round(($grossMargin / $revenue) * 100, 2) 
                : 0;

            $totalProfit += $grossMargin;

            return [
                'part_no' => $item->part_no,
                'description' => $cost->item_desc ?? '',
                'revenue' => $revenue,
                'cost' => $costAmount,
                'gross_margin' => $grossMargin,
                'margin_percent' => $marginPercent,
                'sales_volume' => $item->sales_volume
            ];
        })
        ->sortByDesc('gross_margin')
        ->take($limit)
        ->values();

        // Add contribution percentage
        $data = $data->map(function ($item, $index) use ($totalProfit) {
            $item['rank'] = $index + 1;
            $item['contribution_percent'] = $totalProfit > 0 
                ? round(($item['gross_margin'] / $totalProfit) * 100, 2) 
                : 0;
            
            // Add conditional formatting status
            if ($item['margin_percent'] > 30) {
                $item['status'] = 'green';
            } elseif ($item['margin_percent'] >= 15) {
                $item['status'] = 'yellow';
            } else {
                $item['status'] = 'red';
            }
            
            return $item;
        });

        return response()->json($data);
    }

    /**
     * Chart 7.10: Revenue by Currency Exchange Impact - Combo Chart
     */
    public function revenueByCurrencyExchangeImpact(): JsonResponse
    {
        $dateFormat = "DATE_FORMAT(invoice_date, '%Y-%m')";

        $data = SoInvoiceLine::selectRaw("$dateFormat as period")
            ->select('currency')
            ->selectRaw('SUM(amount) as revenue_original')
            ->selectRaw('SUM(amount_hc) as revenue_home_currency')
            ->selectRaw('ROUND(((SUM(amount_hc) - SUM(amount)) / NULLIF(SUM(amount), 0)) * 100, 2) as exchange_variance_percent')
            ->groupByRaw($dateFormat)
            ->groupBy('currency')
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Get all dashboard 7 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'financial_kpi' => $this->financialKpi($request)->getData(true),
            'revenue_vs_cost_trend' => $this->revenueVsCostTrend($request)->getData(true),
            'revenue_by_customer_segment' => $this->revenueByCustomerSegment($request)->getData(true),
            'cost_analysis_by_category' => $this->costAnalysisByCategory()->getData(true),
            'margin_analysis_by_product' => $this->marginAnalysisByProduct()->getData(true),
            'outstanding_receivables_aging' => $this->outstandingReceivablesAging()->getData(true),
            'outstanding_payables_aging' => $this->outstandingPayablesAging()->getData(true),
            'cash_flow_projection' => $this->cashFlowProjection()->getData(true),
            'top_profitable_products' => $this->topProfitableProducts($request)->getData(true),
            'revenue_by_currency_exchange_impact' => $this->revenueByCurrencyExchangeImpact()->getData(true)
        ]);
    }

    /**
     * Helper function to apply period filter
     */
    private function applyPeriodFilter($query, $period, $dateField)
    {
        $now = now();
        
        switch ($period) {
            case 'mtd':
                $query->whereYear($dateField, $now->year)
                      ->whereMonth($dateField, $now->month);
                break;
            case 'qtd':
                $quarter = ceil($now->month / 3);
                $query->whereYear($dateField, $now->year)
                      ->whereRaw("QUARTER($dateField) = ?", [$quarter]);
                break;
            case 'ytd':
                $query->whereYear($dateField, $now->year);
                break;
        }
    }
}
