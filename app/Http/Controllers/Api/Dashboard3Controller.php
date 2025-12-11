<?php

namespace App\Http\Controllers\Api;

use App\Models\ProdHeader;
use App\Models\ProdReport;
use App\Models\ProductionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard3Controller extends ApiController
{
    private const VALID_DIVISIONS = ['NL', 'CH', 'PS', 'BZ', 'SC'];

    /**
     * Normalize and validate divisi filter input.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function resolveDivisionFilter(Request $request): array
    {
        $rawInput = $request->input('divisi');

        if (is_null($rawInput) || $rawInput === '' || $rawInput === []) {
            return [
                'requested' => 'ALL',
                'codes' => self::VALID_DIVISIONS,
                'is_all' => true,
            ];
        }

        if (is_string($rawInput)) {
            $rawInput = explode(',', $rawInput);
        } elseif (!is_array($rawInput)) {
            $rawInput = [$rawInput];
        }

        $selected = array_values(array_unique(array_filter(array_map(function ($value) {
            return strtoupper(trim((string) $value));
        }, $rawInput))));

        if (empty($selected)) {
            return [
                'requested' => 'ALL',
                'codes' => self::VALID_DIVISIONS,
                'is_all' => true,
            ];
        }

        foreach ($selected as $code) {
            if (!in_array($code, self::VALID_DIVISIONS, true)) {
                abort(400, "Invalid divisi code: {$code}");
            }
        }

        return [
            'requested' => count($selected) === 1 ? $selected[0] : $selected,
            'codes' => $selected,
            'is_all' => count($selected) === count(self::VALID_DIVISIONS),
        ];
    }

    private function getDivisiFilterMetadata(array $selection): array
    {
        return [
            'requested' => $selection['requested'],
            'applied' => $selection['codes'],
            'available' => self::VALID_DIVISIONS,
            'is_all' => $selection['is_all'],
        ];
    }

    private function applyDivisiFilter($query, array $divisiCodes): void
    {
        if (!empty($divisiCodes)) {
            $query->whereIn('divisi', $divisiCodes);
        }
    }

    /**
     * Generate all periods in the range based on period type
     *
     * @param string $period (daily, monthly, yearly)
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array Array of period strings
     */
    protected function generateAllPeriods(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $periods = [];

        if ($period === 'daily') {
            // Generate all dates in the month
            if ($dateFrom) {
                $startDate = Carbon::parse($dateFrom)->startOfMonth();
                $endDate = Carbon::parse($dateFrom)->endOfMonth();

                $current = $startDate->copy();
                while ($current->lte($endDate)) {
                    $periods[] = $current->format('Y-m-d');
                    $current->addDay();
                }
            }
        } elseif ($period === 'monthly') {
            // Generate all months in the year
            if ($dateFrom) {
                $year = Carbon::parse($dateFrom)->year;
                for ($month = 1; $month <= 12; $month++) {
                    $periods[] = sprintf('%04d-%02d', $year, $month);
                }
            }
        } elseif ($period === 'yearly') {
            // Generate all years in the range
            if ($dateFrom && $dateTo) {
                $startYear = Carbon::parse($dateFrom)->year;
                $endYear = Carbon::parse($dateTo)->year;

                for ($year = $startYear; $year <= $endYear; $year++) {
                    $periods[] = (string) $year;
                }
            } elseif ($dateFrom) {
                $year = Carbon::parse($dateFrom)->year;
                $periods[] = (string) $year;
            }
        }

        return $periods;
    }

    /**
     * Chart 3.1: Production KPI Summary - KPI Cards
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function productionKpiSummary(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        $totalProductionOrders = (clone $query)->distinct('prod_no')->count('prod_no');
        $totalQtyOrdered = (clone $query)->sum('qty_order');
        $totalQtyDelivered = (clone $query)->sum('qty_delivery');
        $totalOutstandingQty = (clone $query)->sum('qty_os');

        $qty_order = (clone $query)->avg('qty_order');
        $qty_delivery = (clone $query)->avg('qty_delivery');
        $avgCompletionRate = $qty_order > 0
            ? round(($qty_delivery / $qty_order) * 100, 2)
            : 0;

        return response()->json([
            'total_production_orders' => $totalProductionOrders,
            'total_qty_ordered' => $totalQtyOrdered,
            'total_qty_delivered' => $totalQtyDelivered,
            'total_outstanding_qty' => $totalOutstandingQty,
            'completion_rate' => $avgCompletionRate,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.2: Production Status Distribution - Pie/Donut Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function productionStatusDistribution(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        $data = $query->select('status')
            ->selectRaw('COUNT(prod_no) as count')
            ->selectRaw('SUM(qty_order) as total_qty')
            ->groupBy('status')
            ->get();

        $totalOrders = $data->sum('count');

        return response()->json([
            'data' => $data,
            'total_orders' => $totalOrders,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.3: Production by Customer - Clustered Bar Chart
     *
     * Query Parameters:
     * - limit: Number of records to return (default: 15)
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function productionByCustomer(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        $data = $query->select('customer')
            ->selectRaw('SUM(qty_order) as qty_ordered')
            ->selectRaw('SUM(qty_delivery) as qty_delivered')
            ->selectRaw('SUM(qty_os) as qty_outstanding')
            ->groupBy('customer')
            ->orderBy('qty_ordered', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.4: Production by Model - Horizontal Bar Chart
     *
     * Query Parameters:
     * - limit: Number of records to return (default: 20)
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function productionByModel(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        $data = $query->select('model', 'customer')
            ->selectRaw('SUM(qty_order) as total_qty')
            ->selectRaw('COUNT(DISTINCT prod_no) as total_orders')
            ->groupBy('model', 'customer')
            ->orderBy('total_qty', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.5: Production Schedule Timeline - Gantt Chart
     *
     * Returns optimized production timeline data for Gantt Chart visualization
     * Only essential fields: prod_no, planning_date, status, and basic metadata
     *
     * Query Parameters:
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     * - status: Filter by status
     * - customer: Filter by customer
     * - divisi: Filter by division
     *
     * Returns active orders + orders from last 30 days by default
     */
    public function productionScheduleTimeline(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Default: Active orders (qty_os > 0) OR orders from last 30 days
        if (!$request->has('date_from') && !$request->has('date_to') && !$request->has('status')) {
            $thirtyDaysAgo = now()->subDays(30)->format('Y-m-d');
            $query->where(function ($q) use ($thirtyDaysAgo) {
                $q->where('qty_os', '>', 0) // Active orders
                  ->orWhere('planning_date', '>=', $thirtyDaysAgo); // Recent 30 days
            });
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        // Division filter handled globally
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        $data = $query->select([
                'prod_no',
                'planning_date',
                'status',
                'customer',
                'divisi',
                'qty_os'
            ])
            ->selectRaw('CASE
                WHEN qty_os = 0 THEN "completed"
                WHEN planning_date < CAST(GETDATE() AS DATE) AND qty_os > 0 THEN "delayed"
                ELSE "active"
            END as timeline_status')
            ->orderBy('planning_date', 'asc')
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.6: Production Outstanding Analysis - Data Table with Progress Bar
     */
    public function productionOutstandingAnalysis(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->has('date_from')) {
            $query->where('planning_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('planning_date', '<=', $request->date_to);
        }

        $sortBy = $request->get('sort_by', 'percent_complete');
        $sortOrder = $request->get('sort_order', 'asc');

        $data = $query->select([
                'prod_no',
                'planning_date',
                'item',
                'description',
                'customer',
                'qty_order',
                'qty_delivery',
                'qty_os',
                'status',
                'divisi'
            ])
            ->selectRaw('ROUND((qty_delivery / NULLIF(qty_order, 0)) * 100, 2) as percent_complete')
            ->get();

        // Sort data
        if ($sortBy === 'percent_complete') {
            $data = $sortOrder === 'asc'
                ? $data->sortBy('percent_complete')
                : $data->sortByDesc('percent_complete');
        } elseif ($sortBy === 'qty_os') {
            $data = $sortOrder === 'asc'
                ? $data->sortBy('qty_os')
                : $data->sortByDesc('qty_os');
        }

        return response()->json([
            'data' => $data->values(),
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.7: Production by Division - Stacked Bar Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function productionByDivision(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $query = ProdHeader::query();
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        $data = $query->select('divisi')
            ->selectRaw('SUM(qty_delivery) as qty_delivery')
            ->groupBy('divisi')
            ->orderBy('divisi')
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.8: Production Trend - Combo Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - group_by: Alias for period parameter
     * - customer: Filter by customer
     * - divisi: Filter by division
     * - date_from: Start date filter (for daily: month, for monthly: year, for yearly: date range)
     * - date_to: End date filter
     *
     * Filtering Logic:
     * - Daily: Compare dates within the selected month (date_from should be a date in the month)
     * - Monthly: Compare months within the selected year (date_from should be a date in the year)
     * - Yearly: Compare data across years (date_from and date_to define the year range)
     */
    public function productionTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', $request->get('group_by', 'monthly'));
        $divisiSelection = $this->resolveDivisionFilter($request);

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'monthly';
        }

        $query = ProdReport::query();

        // Apply divisi filter
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('divisi', $divisiSelection['codes']);
        }

        // Apply period-specific date filtering
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($period === 'daily') {
            // Daily: Filter by month from date_from (compare dates within selected month)
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $month = date('m', strtotime($dateFrom));
                // Use SQL Server compatible date filtering
                $query->whereRaw("YEAR(trans_date) = ?", [$year])
                      ->whereRaw("MONTH(trans_date) = ?", [$month]);
            }
        } elseif ($period === 'monthly') {
            // Monthly: Filter by year from date_from (compare months within selected year)
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $query->whereRaw("YEAR(trans_date) = ?", [$year]);
            }
        } elseif ($period === 'yearly') {
            // Yearly: Filter by date range (compare data across years)
            if ($dateFrom) {
                $query->where('trans_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('trans_date', '<=', $dateTo);
            }
        }

        // Query ProductionPlan with same filters for matching
        $planQuery = ProductionPlan::query();

        // Apply divisi filter to ProductionPlan
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $planQuery->whereIn('divisi', $divisiSelection['codes']);
        }

        // Apply period-specific date filtering to ProductionPlan (using plan_date)
        if ($period === 'daily') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $month = date('m', strtotime($dateFrom));
                $planQuery->whereRaw("YEAR(plan_date) = ?", [$year])
                          ->whereRaw("MONTH(plan_date) = ?", [$month]);
            }
        } elseif ($period === 'monthly') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $planQuery->whereRaw("YEAR(plan_date) = ?", [$year]);
            }
        } elseif ($period === 'yearly') {
            if ($dateFrom) {
                $planQuery->where('plan_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $planQuery->where('plan_date', '<=', $dateTo);
            }
        }

        // Get ProductionPlan data with matching keys (partno, divisi, plan_date)
        // Sum qty_plan for records with same partno, divisi, and plan_date
        $planDataMap = $planQuery->get()->groupBy(function ($item) {
            return strtolower(
                trim($item->partno ?? '') . '|' .
                trim($item->divisi ?? '') . '|' .
                ($item->plan_date ? $item->plan_date->format('Y-m-d') : '')
            );
        })->map(function ($group) {
            return $group->sum(function ($item) {
                return (float) ($item->qty_plan ?? 0);
            });
        });

        // Get date format based on period for grouping
        $dateFormat = $this->getDateFormatByPeriod($period, 'trans_date', $query);

        // Get ProdReport data with detail for matching, then group by period
        // We need to match each ProdReport row with ProductionPlan before grouping
        $reportData = $query->select([
                'part_number',
                'divisi',
                'trans_date',
                'qty_pelaporan'
            ])
            ->selectRaw("$dateFormat as period")
            ->get();

        // Match with ProductionPlan and group by period
        $data = $reportData->groupBy('period')
            ->map(function ($group, $periodKey) use ($planDataMap, $period) {
            $qtyPelaporan = 0;
            $qtyPlan = 0;

            foreach ($group as $item) {
                $qtyPelaporan += (float) ($item->qty_pelaporan ?? 0);

                // Match with ProductionPlan: part_number=partno, divisi=divisi, trans_date=plan_date
                $matchKey = strtolower(
                    trim($item->part_number ?? '') . '|' .
                    trim($item->divisi ?? '') . '|' .
                    ($item->trans_date ? Carbon::parse($item->trans_date)->format('Y-m-d') : '')
                );

                if ($planDataMap->has($matchKey)) {
                    $qtyPlan += (float) $planDataMap->get($matchKey);
                }
            }

            // Normalize period format
            $periodValue = trim((string) $periodKey);

            // For daily period, ensure format is Y-m-d
            if ($period === 'daily' && preg_match('/^\d{4}-\d{2}-\d{2}/', $periodValue)) {
                // Already in correct format
            } elseif ($period === 'daily') {
                try {
                    $periodValue = Carbon::parse($periodValue)->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            }

            return [
                'period' => $periodValue,
                'qty_pelaporan' => number_format($qtyPelaporan, 2, '.', ''),
                'qty_plan' => number_format($qtyPlan, 2, '.', '')
            ];
        });

        // Generate all periods in range and fill missing ones with 0
        $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);

        // Also get ProductionPlan data grouped by period (for periods that might not have ProdReport data)
        // Create a new query since $planQuery was already executed
        $planQueryForPeriod = ProductionPlan::query();

        // Apply divisi filter to ProductionPlan
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $planQueryForPeriod->whereIn('divisi', $divisiSelection['codes']);
        }

        // Apply period-specific date filtering to ProductionPlan (using plan_date)
        if ($period === 'daily') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $month = date('m', strtotime($dateFrom));
                $planQueryForPeriod->whereRaw("YEAR(plan_date) = ?", [$year])
                          ->whereRaw("MONTH(plan_date) = ?", [$month]);
            }
        } elseif ($period === 'monthly') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $planQueryForPeriod->whereRaw("YEAR(plan_date) = ?", [$year]);
            }
        } elseif ($period === 'yearly') {
            if ($dateFrom) {
                $planQueryForPeriod->where('plan_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $planQueryForPeriod->where('plan_date', '<=', $dateTo);
            }
        }

        $planDateFormat = $this->getDateFormatByPeriod($period, 'plan_date', $planQueryForPeriod);
        $planDataByPeriod = $planQueryForPeriod->selectRaw("$planDateFormat as period")
            ->selectRaw('SUM(qty_plan) as qty_plan')
            ->groupByRaw($planDateFormat)
            ->orderByRaw($planDateFormat)
            ->get()
            ->map(function ($item) use ($period) {
                $periodValue = trim((string) $item->period);

                if ($period === 'daily' && preg_match('/^\d{4}-\d{2}-\d{2}/', $periodValue)) {
                    // Already in correct format
                } elseif ($period === 'daily') {
                    try {
                        $periodValue = Carbon::parse($periodValue)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original if parsing fails
                    }
                }

                return [
                    'period' => $periodValue,
                    'qty_plan' => number_format((float) ($item->qty_plan ?? 0), 2, '.', '')
                ];
            })
            ->keyBy('period');

        // Merge data: use qty_plan from matched data, or from planDataByPeriod if no match
        if (empty($allPeriods)) {
            $filledData = $data->map(function ($item) use ($planDataByPeriod) {
                $periodKey = $item['period'];
                // Use matched qty_plan if available, otherwise use planDataByPeriod
                $qtyPlan = $item['qty_plan'] !== '0.00'
                    ? $item['qty_plan']
                    : ($planDataByPeriod->has($periodKey) ? $planDataByPeriod->get($periodKey)['qty_plan'] : '0.00');

                return [
                    'period' => $item['period'],
                    'qty_pelaporan' => $item['qty_pelaporan'],
                    'qty_plan' => $qtyPlan
                ];
            })
            ->sortBy('period')
            ->values();
        } else {
            $filledData = collect($allPeriods)->map(function ($periodValue) use ($data, $planDataByPeriod) {
                $periodKey = (string) $periodValue;

                $qtyPelaporan = '0.00';
                $qtyPlan = '0.00';

                if ($data->has($periodKey)) {
                    $item = $data->get($periodKey);
                    $qtyPelaporan = $item['qty_pelaporan'];
                    $qtyPlan = $item['qty_plan'];
                }

                // If no ProdReport data for this period, check ProductionPlan directly
                if ($qtyPlan === '0.00' && $planDataByPeriod->has($periodKey)) {
                    $qtyPlan = $planDataByPeriod->get($periodKey)['qty_plan'];
                }

                return [
                    'period' => $periodKey,
                    'qty_pelaporan' => $qtyPelaporan,
                    'qty_plan' => $qtyPlan
                ];
            })->values();
        }

        return response()->json([
            'data' => $filledData,
            'filter_metadata' => $this->getPeriodMetadata($request, 'trans_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart 3.9: Outstanding Trend - Line Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (planning_date)
     * - date_to: End date filter (planning_date)
     */
    public function outstandingTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', 'monthly');
        $divisiSelection = $this->resolveDivisionFilter($request);

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'monthly';
        }

        $query = ProdHeader::query()->where('qty_os', '>', 0);
        $this->applyDivisiFilter($query, $divisiSelection['codes']);

        // Apply date range filter
        $this->applyDateRangeFilter($query, $request, 'planning_date');

        // Get date format based on period
        $dateFormat = $this->getDateFormatByPeriod($period, 'planning_date', $query);

        $data = $query->selectRaw("$dateFormat as periode")
            ->selectRaw('COUNT(DISTINCT prod_no) as total_prod')
            ->selectRaw('SUM(qty_order) as total_order')
            ->selectRaw('SUM(qty_delivery) as total_delivery')
            ->selectRaw('SUM(qty_os) as total_outstanding')
            ->selectRaw('CAST(SUM(qty_os) * 100.0 / NULLIF(SUM(qty_order), 0) AS DECIMAL(10,2)) as pct_outstanding')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'planning_date'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Get all dashboard 3 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'production_kpi_summary' => $this->productionKpiSummary($request)->getData(true),
            'production_status_distribution' => $this->productionStatusDistribution($request)->getData(true),
            'production_by_customer' => $this->productionByCustomer($request)->getData(true),
            'production_by_model' => $this->productionByModel($request)->getData(true),
            'production_schedule_timeline' => $this->productionScheduleTimeline($request)->getData(true),
            'production_outstanding_analysis' => $this->productionOutstandingAnalysis($request)->getData(true),
            'production_by_division' => $this->productionByDivision($request)->getData(true),
            'production_trend' => $this->productionTrend($request)->getData(true),
            'outstanding_trend' => $this->outstandingTrend($request)->getData(true)
        ]);
    }
}
