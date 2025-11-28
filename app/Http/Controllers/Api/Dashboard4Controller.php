<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use App\Models\SoInvoiceLine2;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard4Controller extends ApiController
{
    /**
     * Get the appropriate model based on year
     * - Year >= 2025: use SoInvoiceLine (so_invoice_line)
     * - Year < 2025: use SoInvoiceLine2 (so_invoice_line_2)
     */
    private function getModelByYear($year)
    {
        return $year >= 2025 ? SoInvoiceLine::class : SoInvoiceLine2::class;
    }

    /**
     * Get query builder for date range
     * Rules:
     * - If date_from and date_to both >= 2025: use SoInvoiceLine
     * - If date_from and date_to both < 2025: use SoInvoiceLine2
     * - If no dates specified or range crosses 2025: default to SoInvoiceLine2
     */
    private function getQueryForDateRange(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // If both dates specified
        if ($dateFrom && $dateTo) {
            $yearFrom = (int) substr($dateFrom, 0, 4);
            $yearTo = (int) substr($dateTo, 0, 4);

            // If both years >= 2025, use SoInvoiceLine
            if ($yearFrom >= 2025 && $yearTo >= 2025) {
                return SoInvoiceLine::query();
            }

            // If both years < 2025, use SoInvoiceLine2
            if ($yearFrom < 2025 && $yearTo < 2025) {
                return SoInvoiceLine2::query();
            }
        }

        // If only date_from specified
        if ($dateFrom && !$dateTo) {
            $yearFrom = (int) substr($dateFrom, 0, 4);
            if ($yearFrom >= 2025) {
                return SoInvoiceLine::query();
            }
        }

        // If only date_to specified
        if (!$dateFrom && $dateTo) {
            $yearTo = (int) substr($dateTo, 0, 4);
            if ($yearTo < 2025) {
                return SoInvoiceLine2::query();
            }
        }

        // Default to SoInvoiceLine2 for backward compatibility and mixed ranges
        return SoInvoiceLine2::query();
    }

    /**
     * Ensure the request contains a valid date range, defaulting to the current month
     */
    private function ensureDateRange(Request $request, ?Carbon $defaultStart = null, ?Carbon $defaultEnd = null): array
    {
        $defaultStart = $defaultStart ?? now()->startOfMonth();
        $defaultEnd = $defaultEnd ?? now();

        $start = $request->filled('date_from')
            ? Carbon::parse($request->get('date_from'))
            : $defaultStart;

        $end = $request->filled('date_to')
            ? Carbon::parse($request->get('date_to'))
            : $defaultEnd;

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $request->merge([
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
        ]);

        return [
            'from' => $start,
            'to' => $end,
        ];
    }

    /**
     * Build a query for the given date range, falling back to the alternate source when no data exists.
     */
    private function buildRangeQueryWithFallback(string $preferredModel, Carbon $start, Carbon $end)
    {
        $query = $preferredModel::query()
            ->whereBetween('invoice_date', [
                $start->toDateString(),
                $end->toDateString()
            ]);

        if (!(clone $query)->exists()) {
            $alternateModel = $preferredModel === SoInvoiceLine::class
                ? SoInvoiceLine2::class
                : SoInvoiceLine::class;

            $query = $alternateModel::query()
                ->whereBetween('invoice_date', [
                    $start->toDateString(),
                    $end->toDateString()
                ]);
        }

        return $query;
    }
    /**
     * Chart 4.1: Sales Overview KPI - KPI Cards
     *
     * Query Parameters:
     * - year: Year to display (default: latest data year)
     * - month: Month to display (default: latest data month)
     */
    public function salesOverviewKpi(Request $request): JsonResponse
    {
        $defaultStart = now()->startOfMonth();
        $defaultEnd = now();

        if ($request->has('year') || $request->has('month')) {
            $targetYear = (int) $request->get('year', now()->year);
            $targetMonth = (int) $request->get('month', now()->month);
            $defaultStart = Carbon::create($targetYear, $targetMonth, 1)->startOfMonth();
            $defaultEnd = $defaultStart->copy()->endOfMonth();

            if ($defaultStart->isSameMonth(now())) {
                $defaultEnd = now();
            }
        }

        $range = $this->ensureDateRange($request, $defaultStart, $defaultEnd);
        $year = (int) $range['from']->year;
        $month = (int) $range['from']->month;

        $model = $this->getModelByYear($year);
        $currentMonthQuery = $this->buildRangeQueryWithFallback(
            $model,
            $range['from'],
            $range['to']
        );

        $totalSalesAmount = (clone $currentMonthQuery)->sum('amount_hc');
        $totalShipments = (clone $currentMonthQuery)->distinct('shipment')->count('shipment');
        $totalInvoices = (clone $currentMonthQuery)->distinct('invoice_no')->count('invoice_no');

        // Outstanding invoices berdasarkan delivery_date
        $outstandingInvoicesQuery = $model::query()
            ->whereBetween('delivery_date', [
                $range['from']->toDateString(),
                $range['to']->toDateString()
            ])
            ->where('shipment_status', ['Approved', 'Released']);
        $outstandingInvoices = $outstandingInvoicesQuery
            ->distinct('shipment')
            ->count('shipment');

        $prevStart = $range['from']->copy()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevModel = $this->getModelByYear($prevStart->year);
        $previousMonthQuery = $this->buildRangeQueryWithFallback(
            $prevModel,
            $prevStart,
            $prevEnd
        );
        $previousSalesAmount = (clone $previousMonthQuery)->sum('amount_hc');

        $salesGrowth = $previousSalesAmount > 0
            ? round((($totalSalesAmount - $previousSalesAmount) / $previousSalesAmount) * 100, 2)
            : 0;

        return response()->json([
            'total_sales_amount' => $totalSalesAmount,
            'total_shipments' => $totalShipments,
            'total_invoices' => $totalInvoices,
            'outstanding_invoices' => $outstandingInvoices,
            'sales_growth' => $salesGrowth,
            'period' => sprintf('%04d-%02d', $year, $month),
            'month' => Carbon::create($year, $month, 1)->format('F Y'),
            'date_range' => [
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
            ],
        ]);
    }

    /**
     * Chart 4.2: Revenue Trend - Area Chart with Line
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - group_by: Alias for period parameter
     * - customer: Filter by customer
     * - product_type: Filter by product type
     * - date_from: Start date filter (invoice_date)
     * - date_to: End date filter (invoice_date)
     */
    public function revenueTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', $request->get('group_by', 'monthly'));

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'monthly';
        }

        $query = $this->getQueryForDateRange($request);

        // Apply filters
        if ($request->has('customer')) {
            $query->where('bp_name', $request->customer);
        }
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'invoice_date');

        // Get date format based on period
        $dateFormat = $this->getDateFormatByPeriod($period, 'invoice_date', $query);

        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('SUM(amount_hc) as revenue')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'invoice_date')
        ]);
    }

    /**
     * Chart 4.3: Top Customers by Revenue - Horizontal Bar Chart
     *
     * Query Parameters:
     * - limit: Number of records to return (default: 20)
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (invoice_date)
     * - date_to: End date filter (invoice_date)
     */
    public function topCustomersByRevenue(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $this->ensureDateRange($request);
        $query = $this->getQueryForDateRange($request);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'invoice_date');

        // Get total revenue before executing the main query
        $totalRevenue = (clone $query)->sum('amount_hc');

        $data = $query->select('bp_name')
            ->selectRaw('SUM(amount_hc) as total_revenue')
            ->selectRaw('COUNT(DISTINCT sales_order) as number_of_orders')
            ->selectRaw('ROUND(AVG(amount_hc), 2) as avg_order_value')
            ->selectRaw('SUM(delivered_qty) as total_qty')
            ->selectRaw('ROUND(AVG(price_hc), 2) as avg_price')
            ->groupBy('bp_name')
            ->orderByRaw('SUM(amount_hc) desc')
            ->limit($limit)
            ->get();

        // Calculate revenue contribution percentage
        $data = $data->map(function ($item) use ($totalRevenue) {
            $item->revenue_contribution = $totalRevenue > 0
                ? round(($item->total_revenue / $totalRevenue) * 100, 2)
                : 0;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'invoice_date')
        ]);
    }

    /**
     * Chart 4.4: Sales by Product Type - Donut Chart
     * Shows revenue for the current month only
     */
    public function salesByProductType(Request $request): JsonResponse
    {
        $range = $this->ensureDateRange($request);
        $query = $this->getQueryForDateRange($request);

        $data = $query->select('product_type')
            ->selectRaw('SUM(amount_hc) as revenue')
            ->selectRaw('SUM(delivered_qty) as qty_sold')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->whereBetween('invoice_date', [
                $range['from']->toDateString(),
                $range['to']->toDateString()
            ])
            ->groupBy('product_type')
            ->orderBy('revenue', 'desc')
            ->get();

        $totalRevenue = $data->sum('revenue');

        $data = $data->map(function ($item) use ($totalRevenue) {
            $item->percentage = $totalRevenue > 0
                ? round(($item->revenue / $totalRevenue) * 100, 2)
                : 0;
            return $item;
        });

        return response()->json([
            'data' => $data,
            'total_revenue' => $totalRevenue,
            'date_range' => [
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
            ],
        ]);
    }

    /**
     * Chart 4.5: Shipment Status Tracking - Bar Chart
     * Shows distinct counts for each stage of the sales process
     * Note: Receipt data only available in ERP database (SoInvoiceLine)
     */
    public function shipmentStatusTracking(): JsonResponse
    {
        // Use SoInvoiceLine (ERP) for complete data including receipt information
        $salesOrdersCreated = SoInvoiceLine::distinct('sales_order')->count('sales_order');
        $shipmentsGenerated = SoInvoiceLine::distinct('shipment')->count('shipment');
        $receiptsConfirmed = SoInvoiceLine::distinct('receipt')->count('receipt');
        $invoicesIssued = SoInvoiceLine::distinct('invoice_no')->count('invoice_no');
        $invoicesPosted = SoInvoiceLine::where('invoice_status', 'Posted')
            ->distinct('invoice_no')
            ->count('invoice_no');

        return response()->json([
            'data' => [
                [
                    'stage' => 'Sales Orders Created',
                    'count' => $salesOrdersCreated
                ],
                [
                    'stage' => 'Shipments Generated',
                    'count' => $shipmentsGenerated
                ],
                [
                    'stage' => 'Receipts Confirmed',
                    'count' => $receiptsConfirmed
                ],
                [
                    'stage' => 'Invoices Issued',
                    'count' => $invoicesIssued
                ],
                [
                    'stage' => 'Invoices Posted',
                    'count' => $invoicesPosted
                ]
            ]
        ]);
    }

    /**
     * Chart 4.7: Delivery Performance - Gauge Chart
     * Note: Using invoice_date vs delivery_date comparison (receipt_date not available in ERP2)
     */
    public function deliveryPerformance(): JsonResponse
    {
        // Default to SoInvoiceLine2 for historical aggregate data
        $totalDeliveries = SoInvoiceLine2::whereNotNull('invoice_date')
            ->whereNotNull('delivery_date')
            ->count();

        $earlyDeliveries = SoInvoiceLine2::whereNotNull('invoice_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('invoice_date < delivery_date')
            ->count();

        $onTimeDeliveries = SoInvoiceLine2::whereNotNull('invoice_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('invoice_date = delivery_date')
            ->count();

        $lateDeliveries = SoInvoiceLine2::whereNotNull('invoice_date')
            ->whereNotNull('delivery_date')
            ->whereRaw('invoice_date > delivery_date')
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
     * Chart 4.6: Invoice Status Distribution - Stacked Bar Chart (100%)
     * Data Source: so_invoice_line
     *
     * Dimensions:
     * - X-axis: Time Period (monthly) or Customer
     * - Y-axis: Percentage
     * - Stack: Invoice Status (invoice_status)
     *
     * Status Categories:
     * - Paid (Green)
     * - Outstanding (Yellow)
     * - Overdue (Red)
     * - Cancelled (Gray)
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - group_by: Can be 'customer' or period (daily/monthly/yearly)
     * - date_from: Start date filter (invoice_date)
     * - date_to: End date filter (invoice_date)
     */
    public function invoiceStatusDistribution(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        $query = $this->getQueryForDateRange($request);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'invoice_date');

        // Group by period or customer
        if ($groupBy === 'customer') {
            $data = $query->select('bp_name as category', 'invoice_status')
                ->selectRaw('COUNT(DISTINCT invoice_no) as count')
                ->groupBy('bp_name', 'invoice_status')
                ->orderBy('bp_name')
                ->get();
        } else {
            // Validate period
            $period = in_array($groupBy, ['daily', 'monthly', 'yearly']) ? $groupBy : 'monthly';
            $dateFormat = $this->getDateFormatByPeriod($period, 'invoice_date', $query);

            $data = $query->selectRaw("$dateFormat as category")
                ->select('invoice_status')
                ->selectRaw('COUNT(DISTINCT invoice_no) as count')
                ->groupByRaw($dateFormat)
                ->groupBy('invoice_status')
                ->orderByRaw($dateFormat)
                ->get();
        }

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'invoice_date')
        ]);
    }

    /**
     * Chart 4.7: Sales Order Fulfillment - Bar Chart
     * Shows delivered quantity with monthly and yearly filter options
     * Data split: Aug 2025+ from ERP (SoInvoiceLine), before Aug 2025 from ERP2 (SoInvoiceLine2)
     *
     * Query Parameters:
     * - period: Filter by period (monthly, yearly) - default: monthly
     * - date_from: Start date filter (invoice_date)
     * - date_to: End date filter (invoice_date)
     * - product_type: Filter by product type
     * - customer: Filter by customer
     */
    public function salesOrderFulfillment(Request $request): JsonResponse
    {
        $period = $request->get('period', 'monthly');

        // Validate period - only monthly and yearly allowed
        if (!in_array($period, ['monthly', 'yearly'])) {
            $period = 'monthly';
        }

        // Determine date range - default to current year if not specified
        $dateTo = $request->get('date_to', date('Y-12-31'));
        $dateFrom = $request->get('date_from', date('Y-01-01'));

        $allData = collect();

        // Helper function to apply filters
        $applyFilters = function($query, $request) {
            if ($request->has('product_type')) {
                $query->where('product_type', $request->product_type);
            }
            if ($request->has('customer')) {
                $query->where('bp_name', $request->customer);
            }
        };

        // Parse dates to determine which data source to use
        $fromDate = \Carbon\Carbon::parse($dateFrom);
        $toDate = \Carbon\Carbon::parse($dateTo);
        $splitDate = \Carbon\Carbon::parse('2025-08-01');

        // Query data from ERP2 (before Aug 2025)
        if ($fromDate->lt($splitDate)) {
            $query2 = SoInvoiceLine2::query();
            $applyFilters($query2, $request);

            $erp2EndDate = $toDate->lt($splitDate) ? $toDate : $splitDate->copy()->subDay();

            $isSqlServer2 = $query2->getConnection()->getDriverName() === 'sqlsrv';
            $dateFormat2 = $period === 'yearly'
                ? ($isSqlServer2 ? "CAST(YEAR(invoice_date) AS VARCHAR)" : "YEAR(invoice_date)")
                : ($isSqlServer2 ? "LEFT(CONVERT(VARCHAR(10), invoice_date, 23), 7)" : "DATE_FORMAT(invoice_date, '%Y-%m')");

            $erp2Data = $query2
                ->selectRaw("$dateFormat2 as period")
                ->selectRaw('SUM(delivered_qty) as delivered_qty')
                ->where('invoice_date', '>=', $fromDate->format('Y-m-d'))
                ->where('invoice_date', '<=', $erp2EndDate->format('Y-m-d'))
                ->groupByRaw($dateFormat2)
                ->orderByRaw($dateFormat2)
                ->get();

            $allData = $allData->merge($erp2Data);
        }

        // Query data from ERP (Aug 2025 onwards)
        if ($toDate->gte($splitDate)) {
            $query1 = SoInvoiceLine::query();
            $applyFilters($query1, $request);

            $erp1StartDate = $fromDate->gte($splitDate) ? $fromDate : $splitDate;

            $isSqlServer1 = $query1->getConnection()->getDriverName() === 'sqlsrv';
            $dateFormat1 = $period === 'yearly'
                ? ($isSqlServer1 ? "CAST(YEAR(invoice_date) AS VARCHAR)" : "YEAR(invoice_date)")
                : ($isSqlServer1 ? "LEFT(CONVERT(VARCHAR(10), invoice_date, 23), 7)" : "DATE_FORMAT(invoice_date, '%Y-%m')");

            $erp1Data = $query1
                ->selectRaw("$dateFormat1 as period")
                ->selectRaw('SUM(delivered_qty) as delivered_qty')
                ->where('invoice_date', '>=', $erp1StartDate->format('Y-m-d'))
                ->where('invoice_date', '<=', $toDate->format('Y-m-d'))
                ->groupByRaw($dateFormat1)
                ->orderByRaw($dateFormat1)
                ->get();

            $allData = $allData->merge($erp1Data);
        }

        // Sort and merge data by period
        $sortedData = $allData->sortBy('period')->values();

        return response()->json([
            'data' => $sortedData,
            'filter_metadata' => [
                'period' => $period,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    /**
     * Chart 4.8: Top Selling Products - Data Table with Sparkline
     */
    public function topSellingProducts(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        $this->ensureDateRange($request);

        $query = $this->getQueryForDateRange($request);

        // Apply filters
        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }
        if ($request->has('customer')) {
            $query->where('bp_name', $request->customer);
        }
        $query->whereBetween('invoice_date', [
            $request->get('date_from'),
            $request->get('date_to'),
        ]);

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
     * Chart 4.9: Revenue by Currency - Pie Chart
     * Shows revenue for the current month only
     */
    public function revenueByCurrency(): JsonResponse
    {
        // Get current year and month
        $now = now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        // Use appropriate model based on current year
        $model = $this->getModelByYear($currentYear);

        $actualMonth = $currentMonth - 1;

        $data = $model::select('currency')
            ->selectRaw('SUM(amount) as amount_original')
            ->selectRaw('SUM(amount_hc) as amount_hc')
            ->selectRaw('COUNT(DISTINCT invoice_no) as invoice_count')
            ->whereYear('invoice_date', $currentYear)
            ->whereMonth('invoice_date', $actualMonth)
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
            'total_amount_hc' => $totalAmountHc,
            'period' => sprintf('%04d-%02d', $currentYear, $actualMonth)
        ]);
    }

    /**
     * Chart 4.10: Monthly Sales Comparison - Bar Chart
     * Shows month-over-month (MoM) revenue comparison
     * Data split: Aug 2025+ from ERP (SoInvoiceLine), before Aug 2025 from ERP2 (SoInvoiceLine2)
     *
     * Query Parameters:
     * - date_from: Start date filter (invoice_date) - default: 12 months ago
     * - date_to: End date filter (invoice_date) - default: current month
     * - product_type: Filter by product type
     * - customer: Filter by customer
     */
    public function monthlySalesComparison(Request $request): JsonResponse
    {
        $range = $this->ensureDateRange(
            $request,
            now()->startOfYear(),
            now()
        );

        $dateFrom = $range['from']->toDateString();
        $dateTo = $range['to']->toDateString();

        $allData = collect();

        // Helper function to apply filters
        $applyFilters = function($query, $request) {
            if ($request->has('product_type')) {
                $query->where('product_type', $request->product_type);
            }
            if ($request->has('customer')) {
                $query->where('bp_name', $request->customer);
            }
        };

        // Parse dates to determine which data source to use
        $fromDate = Carbon::parse($dateFrom);
        $toDate = Carbon::parse($dateTo);
        $splitDate = Carbon::parse('2025-08-01');

        // Query data from ERP2 (before Aug 2025)
        if ($fromDate->lt($splitDate)) {
            $query2 = SoInvoiceLine2::query();
            $applyFilters($query2, $request);

            $erp2EndDate = $toDate->lt($splitDate) ? $toDate : $splitDate->copy()->subDay();

            $isSqlServer2 = $query2->getConnection()->getDriverName() === 'sqlsrv';
            $dateFormat2 = $isSqlServer2
                ? "LEFT(CONVERT(VARCHAR(10), invoice_date, 23), 7)"
                : "DATE_FORMAT(invoice_date, '%Y-%m')";

            $erp2Data = $query2
                ->selectRaw("$dateFormat2 as period")
                ->selectRaw('SUM(amount_hc) as revenue')
                ->where('invoice_status', 'Posted')
                ->where('invoice_date', '>=', $fromDate->format('Y-m-d'))
                ->where('invoice_date', '<=', $erp2EndDate->format('Y-m-d'))
                ->groupByRaw($dateFormat2)
                ->orderByRaw($dateFormat2)
                ->get();

            $allData = $allData->merge($erp2Data);
        }

        // Query data from ERP (Aug 2025 onwards)
        if ($toDate->gte($splitDate)) {
            $query1 = SoInvoiceLine::query();
            $applyFilters($query1, $request);

            $erp1StartDate = $fromDate->gte($splitDate) ? $fromDate : $splitDate;

            $isSqlServer1 = $query1->getConnection()->getDriverName() === 'sqlsrv';
            $dateFormat1 = $isSqlServer1
                ? "LEFT(CONVERT(VARCHAR(10), invoice_date, 23), 7)"
                : "DATE_FORMAT(invoice_date, '%Y-%m')";

            $erp1Data = $query1
                ->selectRaw("$dateFormat1 as period")
                ->selectRaw('SUM(amount_hc) as revenue')
                ->where('invoice_date', '>=', $erp1StartDate->format('Y-m-d'))
                ->where('invoice_date', '<=', $toDate->format('Y-m-d'))
                ->groupByRaw($dateFormat1)
                ->orderByRaw($dateFormat1)
                ->get();

            $allData = $allData->merge($erp1Data);
        }

        // Sort data by period
        $sortedData = $allData->sortBy('period')->values();

        // Calculate MoM (Month-over-Month) growth
        $result = [];
        $previousRevenue = null;

        foreach ($sortedData as $index => $item) {
            $currentRevenue = $item->revenue;

            $momGrowth = null;
            if ($previousRevenue !== null && $previousRevenue > 0) {
                $momGrowth = round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2);
            }

            $result[] = [
                'period' => $item->period,
                'revenue' => $currentRevenue,
                'previous_month_revenue' => $previousRevenue,
                'mom_growth' => $momGrowth
            ];

            $previousRevenue = $currentRevenue;
        }

        return response()->json([
            'data' => $result,
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ]);
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
            'sales_by_product_type' => $this->salesByProductType($request)->getData(true),
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
