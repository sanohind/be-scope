<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard1RevisionController extends ApiController
{
    /**
     * Get warehouse parameter from request or throw error
     */
    private function getWarehouse(Request $request): string
    {
        $warehouse = $request->input('warehouse');

        if (!$warehouse) {
            abort(400, 'Warehouse parameter is required');
        }

        $validWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];
        if (!in_array($warehouse, $validWarehouses)) {
            abort(400, 'Invalid warehouse code');
        }

        return $warehouse;
    }

    /**
     * Get date range parameters from request
     * Returns array with date_from and date_to
     * Default: current month if not specified
     */
    private function getDateRange(Request $request, int $defaultDays = 30): array
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // If no dates provided, use current month
        if (!$dateFrom && !$dateTo) {
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now();
        } else {
            // Parse provided dates
            $dateFrom = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now()->subDays($defaultDays);
            $dateTo = $dateTo ? Carbon::parse($dateTo) : Carbon::now();
        }

        return [
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d 23:59:59'),
            'days_diff' => $dateFrom->diffInDays($dateTo)
        ];
    }

    /**
     * Build date filter SQL condition
     */
    private function buildDateFilter(string $tableAlias, array $dateRange): string
    {
        return sprintf(
            "%s.trans_date >= '%s' AND %s.trans_date <= '%s'",
            $tableAlias,
            $dateRange['date_from'],
            $tableAlias,
            $dateRange['date_to']
        );
    }

    /**
     * CHART 1: Comprehensive KPI Cards
     * 6 metrics combining stock + transaction data
     * DATE FILTER: Applied to transaction metrics only
     */
    public function comprehensiveKpi(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        // Stock Metrics (no date filter - current snapshot)
        $totalSku = DB::connection('erp')->table('stockbywh')
            ->where('warehouse', $warehouse)
            ->distinct()
            ->count('partno');

        $totalOnhand = DB::connection('erp')->table('stockbywh')
            ->where('warehouse', $warehouse)
            ->sum('onhand');

        $criticalItems = DB::connection('erp')->table('stockbywh')
            ->where('warehouse', $warehouse)
            ->whereRaw('onhand < safety_stock')
            ->count();

        // Transaction Metrics (with date filter)
        $transInPeriod = DB::connection('erp')->table('inventory_transaction')
            ->where('warehouse', $warehouse)
            ->whereBetween('trans_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->count();

        // Net Movement (with date filter)
        // Convert nvarchar to numeric using CAST/TRY_CAST
        $netMovement = DB::connection('erp')
            ->table('inventory_transaction')
            ->where('warehouse', $warehouse)
            ->whereBetween('trans_date', [$dateRange['date_from'], $dateRange['date_to']])
            ->selectRaw("
                SUM(
                    CASE
                        WHEN trans_type = 'Receipt' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                        WHEN trans_type = 'Issue' THEN -TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                        ELSE 0
                    END
                ) AS net
            ")
            ->value('net');


        return response()->json([
            'total_sku' => $totalSku,
            'total_onhand' => round($totalOnhand, 2),
            'critical_items' => $criticalItems,
            'trans_in_period' => $transInPeriod,
            'net_movement' => round($netMovement ?? 0, 2),
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 2: Stock Health Distribution + Activity
     * Donut chart with transaction activity
     * DATE FILTER: Applied to transaction counts
     */
    public function stockHealthDistribution(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        $dateFilter = $this->buildDateFilter('t', $dateRange);

        $data = DB::connection('erp')->select("
            SELECT
                CASE
                    WHEN s.onhand < s.min_stock THEN 'Critical'
                    WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                COUNT(DISTINCT s.partno) as item_count,
                SUM(s.onhand) as total_onhand,
                COUNT(t.trans_id) as trans_count,
                COUNT(t.shipment) as total_shipment
            FROM stockbywh s
            LEFT JOIN inventory_transaction t
                ON s.partno = t.partno
                AND s.warehouse = t.warehouse
                AND $dateFilter
            WHERE s.warehouse = ?
            GROUP BY
                CASE
                    WHEN s.onhand < s.min_stock THEN 'Critical'
                    WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END
        ", [$warehouse]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to']
            ]
        ]);
    }

    /**
     * CHART 3: Stock Movement Trend
     * Area chart showing receipt, shipment, and net movement
     * DATE FILTER: Applied to entire trend
     */
    public function stockMovementTrend(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        $data = DB::connection('erp')->select("
            SELECT
                CAST(trans_date AS DATE) as date,
                SUM(CASE WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_receipt,
                SUM(CASE WHEN shipment IS NOT NULL AND shipment != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                SUM(CASE
                    WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    WHEN shipment IS NOT NULL AND shipment != '' THEN -TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    ELSE 0
                END) as net_movement,
                COUNT(trans_id) as trans_count
            FROM inventory_transaction
            WHERE warehouse = ?
                AND trans_date >= ?
                AND trans_date <= ?
            GROUP BY CAST(trans_date AS DATE)
            ORDER BY date
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        // Current total onhand for reference
        $currentOnhand = DB::connection('erp')->table('stockbywh')
            ->where('warehouse', $warehouse)
            ->sum('onhand');

        return response()->json([
            'trend_data' => $data,
            'current_total_onhand' => round($currentOnhand, 2),
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 4: Top 15 Items Without Issue Activity
     * Highlights SKUs with large on-hand quantity but little to no "Issue" transactions
     * DATE FILTER: Applied to transaction summary (default 90 days)
     */
    public function topCriticalItems(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 90);
        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');

        $dateFilterClause = '';
        $dateFilterParams = [];

        if ($dateFromInput || $dateToInput) {
            $dateFilterClause = "
                AND COALESCE(t.trans_date2, t.trans_date) >= ?
                AND COALESCE(t.trans_date2, t.trans_date) <= ?
            ";
            $dateFilterParams = [$dateRange['date_from'], $dateRange['date_to']];
        }

        $issueSummaryParams = array_merge([$warehouse], $dateFilterParams);
        $data = DB::connection('erp')->select("
            WITH issue_summary AS (
                SELECT
                    t.partno,
                    COUNT(
                        CASE
                            WHEN t.trans_type = 'Issue'
                                OR (t.shipment IS NOT NULL AND t.shipment != '')
                            THEN 1
                        END
                    ) as issue_count,
                    SUM(CASE
                        WHEN t.trans_type = 'Issue'
                            OR (t.shipment IS NOT NULL AND t.shipment != '')
                        THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2))
                        ELSE 0
                    END) as total_issue_qty,
                    MAX(CASE
                        WHEN t.trans_type = 'Issue'
                            OR (t.shipment IS NOT NULL AND t.shipment != '')
                        THEN COALESCE(t.trans_date2, t.trans_date)
                        ELSE NULL
                    END) as last_issue_date
                FROM inventory_transaction t
                WHERE t.warehouse = ?
                    $dateFilterClause
                GROUP BY t.partno
            )
            SELECT TOP 15
                s.partno,
                s.[desc] as description,
                s.product_type,
                CAST(s.onhand AS DECIMAL(18,2)) as onhand,
                CAST(ISNULL(isum.total_issue_qty, 0) AS DECIMAL(18,2)) as total_issue_qty,
                ISNULL(isum.issue_count, 0) as issue_count,
                isum.last_issue_date,
                CAST(s.onhand - ISNULL(isum.total_issue_qty, 0) AS DECIMAL(18,2)) as qty_gap,
                CASE
                    WHEN ISNULL(isum.issue_count, 0) = 0 THEN 'No Issue Activity'
                    WHEN ISNULL(isum.issue_count, 0) <= 2 THEN 'Low Issue Activity'
                    ELSE 'Has Issue Activity'
                END as activity_flag
            FROM stockbywh s
            LEFT JOIN issue_summary isum ON s.partno = isum.partno
            WHERE s.warehouse = ?
                AND s.onhand > 0
            ORDER BY qty_gap DESC, s.onhand DESC
        ", array_merge($issueSummaryParams, [$warehouse]));

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 5: Top 15 Most Active Items & Top 15 Most Non-Active Items
     * Horizontal bar chart with high transaction frequency + items with oldest last transaction
     * DATE FILTER: Applied to transaction analysis
     */
    public function mostActiveItems(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        // Query for most active items (highest transaction count in date range)
        $activeData = DB::connection('erp')->select("
            SELECT TOP 15
                t.partno,
                LEFT(t.part_desc, 30) as description,
                s.product_type,
                COUNT(t.trans_id) as trans_count,
                SUM(CASE WHEN t.receipt IS NOT NULL AND t.receipt != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_receipt,
                SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                s.onhand as current_onhand,
                s.safety_stock,
                MAX(t.trans_date) as last_trans_date,
                CASE
                    WHEN s.onhand < s.safety_stock THEN 'At Risk'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN COUNT(t.trans_id) >= 20 AND s.onhand < s.safety_stock THEN 'High Risk'
                    WHEN COUNT(t.trans_id) >= 20 THEN 'High Activity'
                    WHEN COUNT(t.trans_id) >= 10 THEN 'Medium Activity'
                    ELSE 'Low Activity'
                END as activity_level
            FROM inventory_transaction t
            LEFT JOIN stockbywh s ON t.partno = s.partno AND t.warehouse = s.warehouse
            WHERE t.warehouse = ?
                AND t.trans_date >= ?
                AND t.trans_date <= ?
            GROUP BY t.partno, t.part_desc, s.product_type, s.onhand, s.safety_stock, s.max_stock
            ORDER BY COUNT(t.trans_id) DESC
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        // Query for most non-active items (items with oldest last transaction date)
        $nonActiveData = DB::connection('erp')->select("
            WITH last_transaction AS (
                SELECT
                    partno,
                    MAX(trans_date) as last_trans_date
                FROM inventory_transaction
                WHERE warehouse = ?
                GROUP BY partno
            )
            SELECT TOP 15
                s.partno,
                LEFT(COALESCE(NULLIF(s.partname, ''), s.[desc]), 30) as description,
                s.product_type,
                0 as trans_count,
                0 as total_receipt,
                0 as total_shipment,
                s.onhand as current_onhand,
                s.safety_stock,
                ISNULL(lt.last_trans_date, '1900-01-01') as last_trans_date,
                CASE
                    WHEN s.onhand < s.safety_stock THEN 'At Risk'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -6, GETDATE()) AND s.onhand < s.safety_stock THEN 'Critical Inactive'
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -6, GETDATE()) THEN 'Long Inactive'
                    WHEN ISNULL(lt.last_trans_date, '1900-01-01') < DATEADD(month, -3, GETDATE()) THEN 'Moderately Inactive'
                    ELSE 'Recently Inactive'
                END as activity_level,
                DATEDIFF(day, ISNULL(lt.last_trans_date, '1900-01-01'), GETDATE()) as days_since_last_trans
            FROM stockbywh s
            LEFT JOIN last_transaction lt ON s.partno = lt.partno
            WHERE s.warehouse = ?
                AND s.onhand > 0
            ORDER BY ISNULL(lt.last_trans_date, '1900-01-01') ASC
        ", [$warehouse, $warehouse]);

        return response()->json([
            'most_active_items' => $activeData,
            'most_non_active_items' => $nonActiveData,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 6: Stock & Activity by Product Type
     * Combo chart (columns + lines)
     * DATE FILTER: Applied to transaction counts and turnover calculation
     */
    public function stockActivityByProductType(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        $dateFilter = $this->buildDateFilter('t', $dateRange);

        $data = DB::connection('erp')->select("
            SELECT
                s.product_type,
                COUNT(DISTINCT s.partno) as sku_count,
                SUM(s.onhand) as total_onhand,
                SUM(s.safety_stock) as total_safety_stock,
                SUM(s.onhand - s.allocated) as total_available,
                COUNT(t.trans_id) as trans_count,
                COUNT(t.shipment) as total_shipment,
                CASE
                    WHEN SUM(s.onhand) > 0 THEN CAST(COUNT(t.shipment) / SUM(s.onhand) AS DECIMAL(10,2))
                    ELSE 0
                END as turnover_rate
            FROM stockbywh s
            LEFT JOIN inventory_transaction t
                ON s.partno = t.partno
                AND s.warehouse = t.warehouse
                AND $dateFilter
            WHERE s.warehouse = ?
            GROUP BY s.product_type
            ORDER BY SUM(s.onhand) DESC
        ", [$warehouse]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 7: Stock by Group Type - Donut Chart
     * Groups stock by group_type_desc with warehouse filter
     */
    public function stockByGroupType(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);

        $data = DB::connection('erp')->table('stockbywh')
            ->select('group_type_desc')
            ->selectRaw('SUM(onhand) as total_onhand')
            ->selectRaw('COUNT(DISTINCT partno) as total_items')
            ->where('warehouse', $warehouse)
            ->groupBy('group_type_desc')
            ->orderBy('total_onhand', 'desc')
            ->get();

        $totalOnhand = $data->sum('total_onhand');
        $totalItems = $data->sum('total_items');

        return response()->json([
            'data' => $data,
            'summary' => [
                'total_onhand' => $totalOnhand,
                'total_items' => $totalItems,
                'total_group_types' => $data->count()
            ],
            'warehouse' => $warehouse
        ]);
    }

    /**
     * CHART 8: Receipt vs Shipment Trend (Weekly)
     * Clustered column chart with line
     * DATE FILTER: Applied to entire trend (default 90 days to show weekly properly)
     */
    public function receiptVsShipmentTrend(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 90); // Default 90 days for weekly view

        $data = DB::connection('erp')->select("
            SELECT
                DATEPART(WEEK, trans_date) as week_num,
                DATEPART(YEAR, trans_date) as year,
                MIN(CAST(trans_date AS DATE)) as week_start,
                SUM(CASE WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_receipt,
                SUM(CASE WHEN shipment IS NOT NULL AND shipment != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                SUM(CASE
                    WHEN receipt IS NOT NULL AND receipt != '' THEN TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    WHEN shipment IS NOT NULL AND shipment != '' THEN -TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2))
                    ELSE 0
                END) as net_movement,
                COUNT(trans_id) as trans_count
            FROM inventory_transaction
            WHERE warehouse = ?
                AND trans_date >= ?
                AND trans_date <= ?
            GROUP BY DATEPART(WEEK, trans_date), DATEPART(YEAR, trans_date)
            ORDER BY year, week_num
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 9: Transaction Type Distribution
     * Stacked bar chart
     * DATE FILTER: Applied to transaction analysis
     */
    public function transactionTypeDistribution(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        $data = DB::connection('erp')->select("
            SELECT
                trans_type,
                order_type,
                COUNT(trans_id) as trans_count,
                SUM(ABS(TRY_CAST(ISNULL(qty, 0) AS DECIMAL(18,2)))) as total_qty,
                COUNT(DISTINCT partno) as unique_parts,
                COUNT(DISTINCT [user]) as unique_users
            FROM inventory_transaction
            WHERE warehouse = ?
                AND trans_date >= ?
                AND trans_date <= ?
            GROUP BY trans_type, order_type
            ORDER BY trans_count DESC
        ", [$warehouse, $dateRange['date_from'], $dateRange['date_to']]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to']
            ]
        ]);
    }

    /**
     * CHART 10: Fast vs Slow Moving Items
     * Scatter plot data with quadrant classification
     * DATE FILTER: Applied to transaction frequency analysis
     */
    public function fastVsSlowMoving(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);

        $dateFilter = $this->buildDateFilter('t', $dateRange);

        $data = DB::connection('erp')->select("
            SELECT
                s.partno,
                LEFT(s.[desc], 25) as description,
                s.product_type,
                s.onhand,
                s.safety_stock,
                s.max_stock,
                (s.safety_stock - s.onhand) as gap_from_safety,
                COUNT(t.trans_id) as trans_count,
                SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                CASE
                    WHEN s.onhand > 0 THEN CAST(SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / s.onhand AS DECIMAL(10,2))
                    ELSE 0
                END as turnover_rate,
                CASE
                    WHEN s.onhand < s.min_stock THEN 'Critical'
                    WHEN s.onhand < s.safety_stock THEN 'Low Stock'
                    WHEN s.onhand > s.max_stock THEN 'Overstock'
                    ELSE 'Normal'
                END as stock_status,
                CASE
                    WHEN COUNT(t.trans_id) >= 15 AND s.onhand >= s.safety_stock THEN 'Healthy'
                    WHEN COUNT(t.trans_id) >= 15 AND s.onhand < s.safety_stock THEN 'High Risk'
                    WHEN COUNT(t.trans_id) < 5 AND s.onhand > s.max_stock THEN 'Slow Moving'
                    WHEN COUNT(t.trans_id) < 5 AND s.onhand < s.safety_stock THEN 'Review'
                    ELSE 'Normal'
                END as classification
            FROM stockbywh s
            LEFT JOIN inventory_transaction t
                ON s.partno = t.partno
                AND s.warehouse = t.warehouse
                AND $dateFilter
            WHERE s.warehouse = ?
            GROUP BY s.partno, s.[desc], s.product_type, s.onhand, s.safety_stock, s.min_stock, s.max_stock
            HAVING COUNT(t.trans_id) > 0 OR s.onhand > 0
            ORDER BY COUNT(t.trans_id) DESC
        ", [$warehouse]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $dateRange['days_diff']
            ]
        ]);
    }

    /**
     * CHART 11: Stock Turnover Rate (Top 20)
     * Horizontal bar chart with color gradient
     * DATE FILTER: Applied to shipment calculation for turnover
     */
    public function stockTurnoverRate(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $dateRange = $this->getDateRange($request, 30);
        $days = $dateRange['days_diff'];

        $data = DB::connection('erp')->select("
            WITH turnover_calc AS (
                SELECT
                    s.partno,
                    s.[desc],
                    s.product_type,
                    s.onhand,
                    s.safety_stock,
                    SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) as total_shipment,
                    SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ? as avg_daily_shipment,
                    CASE
                        WHEN s.onhand > 0 THEN CAST(SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / s.onhand AS DECIMAL(10,2))
                        ELSE 0
                    END as turnover_rate,
                    CASE
                        WHEN SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ? > 0 THEN CAST(s.onhand / (SUM(CASE WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) ELSE 0 END) / ?) AS INT)
                        ELSE 999
                    END as days_of_stock
                FROM stockbywh s
                LEFT JOIN inventory_transaction t
                    ON s.partno = t.partno
                    AND s.warehouse = t.warehouse
                    AND t.trans_date >= ?
                    AND t.trans_date <= ?
                WHERE s.warehouse = ?
                GROUP BY s.partno, s.[desc], s.product_type, s.onhand, s.safety_stock
            )
            SELECT TOP 20
                partno,
                LEFT([desc], 30) as description,
                product_type,
                onhand,
                safety_stock,
                total_shipment,
                turnover_rate,
                days_of_stock,
                CASE
                    WHEN turnover_rate >= 1.5 THEN 'Fast Moving'
                    WHEN turnover_rate >= 0.5 THEN 'Medium Moving'
                    ELSE 'Slow Moving'
                END as movement_category,
                CASE
                    WHEN days_of_stock < 30 AND onhand < safety_stock THEN 'Urgent Reorder'
                    WHEN days_of_stock < 30 THEN 'Monitor Closely'
                    WHEN days_of_stock > 90 THEN 'Potential Overstock'
                    ELSE 'Normal'
                END as recommendation
            FROM turnover_calc
            WHERE total_shipment > 0
            ORDER BY turnover_rate DESC
        ", [
            $days, $days, $days,
            $dateRange['date_from'],
            $dateRange['date_to'],
            $warehouse
        ]);

        return response()->json([
            'data' => $data,
            'date_range' => [
                'from' => $dateRange['date_from'],
                'to' => $dateRange['date_to'],
                'days' => $days
            ]
        ]);
    }

    /**
     * CHART 12: Recent Transaction History
     * Data table with pagination
     */
    public function recentTransactionHistory(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('per_page', 50))); // Limit to max 100 per page
        $offset = ($page - 1) * $perPage;

        // Filters
        $transType = $request->input('trans_type');
        $user = $request->input('user');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $whereConditions = ["t.warehouse = ?"];
        $params = [$warehouse];

        if ($transType) {
            $whereConditions[] = "t.trans_type = ?";
            $params[] = $transType;
        }

        if ($user) {
            $whereConditions[] = "t.[user] = ?";
            $params[] = $user;
        }

        if ($dateFrom) {
            $whereConditions[] = "t.trans_date >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = "t.trans_date <= ?";
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $totalQuery = "
            SELECT COUNT(*) as total
            FROM inventory_transaction t
            WHERE $whereClause
        ";
        $total = DB::connection('erp')->select($totalQuery, $params)[0]->total;

        // Get paginated data
        $dataParams = array_merge($params, [$offset, $perPage]);
        $data = DB::connection('erp')->select("
            SELECT
                t.trans_date,
                t.trans_id,
                t.partno,
                t.part_desc,
                t.trans_type,
                t.order_type,
                t.order_no,
                t.receipt,
                t.shipment,
                TRY_CAST(ISNULL(t.qty, 0) AS DECIMAL(18,2)) as qty,
                TRY_CAST(ISNULL(t.qty_hand, 0) AS DECIMAL(18,2)) as qty_after_trans,
                s.onhand as current_onhand,
                (s.onhand - TRY_CAST(ISNULL(t.qty_hand, 0) AS DECIMAL(18,2))) as variance,
                s.location,
                t.[user],
                t.lotno,
                CASE
                    WHEN t.receipt IS NOT NULL AND t.receipt != '' THEN 'IN'
                    WHEN t.shipment IS NOT NULL AND t.shipment != '' THEN 'OUT'
                    ELSE 'ADJUSTMENT'
                END as movement_type
            FROM inventory_transaction t
            LEFT JOIN stockbywh s ON t.partno = s.partno AND t.warehouse = s.warehouse
            WHERE $whereClause
            ORDER BY t.trans_date DESC, t.trans_id DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ", $dataParams);

        return response()->json([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }

    /**
     * Get all dashboard data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'kpi' => $this->comprehensiveKpi($request)->getData(true),
            'stock_health' => $this->stockHealthDistribution($request)->getData(true),
            'movement_trend' => $this->stockMovementTrend($request)->getData(true),
            'critical_items' => $this->topCriticalItems($request)->getData(true),
            'active_items' => $this->mostActiveItems($request)->getData(true),
            'product_type_analysis' => $this->stockActivityByProductType($request)->getData(true),
            'group_type_analysis' => $this->stockByGroupType($request)->getData(true),
            'receipt_shipment_trend' => $this->receiptVsShipmentTrend($request)->getData(true),
            'transaction_types' => $this->transactionTypeDistribution($request)->getData(true),
            'fast_slow_moving' => $this->fastVsSlowMoving($request)->getData(true),
            'turnover_rate' => $this->stockTurnoverRate($request)->getData(true),
            'stock_level_table' => $this->stockLevelTable($request)->getData(true)
            // Note: Recent transactions excluded from bulk call due to pagination
        ]);
    }

    /**
     * TABLE: Stock Level Detail
     * Provides paginated table for stock level monitoring
     */
    public function stockLevelTable(Request $request): JsonResponse
    {
        $warehouse = $this->getWarehouse($request);

        $statusFilter = $request->input('status');
        $search = $request->input('search');
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(200, max(10, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $statusCase = "
            CASE
                WHEN s.onhand < s.min_stock THEN 'Critical'
                WHEN s.onhand < s.safety_stock THEN 'Low'
                WHEN s.onhand > s.max_stock THEN 'Overstock'
                ELSE 'Normal'
            END
        ";

        $baseQuery = DB::connection('erp')
            ->table('stockbywh as s')
            ->where('s.warehouse', $warehouse);

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('s.partno', 'LIKE', "%{$search}%")
                    ->orWhere('s.partname', 'LIKE', "%{$search}%")
                    ->orWhereRaw('s.[desc] LIKE ?', ["%{$search}%"]);
            });
        }

        if ($statusFilter) {
            $baseQuery->whereRaw("$statusCase = ?", [$statusFilter]);
        }

        $total = (clone $baseQuery)->count();

        $data = (clone $baseQuery)
            ->selectRaw("
                s.partno,
                COALESCE(NULLIF(s.partname, ''), s.[desc]) as part_name,
                s.unit,
                s.warehouse,
                CAST(s.onhand AS DECIMAL(18,2)) as onhand,
                CAST(s.min_stock AS DECIMAL(18,2)) as min_stock,
                CAST(s.safety_stock AS DECIMAL(18,2)) as safety_stock,
                CAST(s.max_stock AS DECIMAL(18,2)) as max_stock,
                ($statusCase) as status
            ")
            ->orderByRaw("(s.safety_stock - s.onhand) DESC")
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'filters' => [
                'warehouse' => $warehouse,
                'status' => $statusFilter,
                'search' => $search
            ],
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total)
            ]
        ]);
    }
}
