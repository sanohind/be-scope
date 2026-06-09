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

        // 1. Prepare ProdReport Query
        $query = ProdReport::query();

        // Apply divisi filter
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('divisi', $divisiSelection['codes']);
        }

        // Apply period-specific date filtering for Report
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($period === 'daily') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $month = date('m', strtotime($dateFrom));
                $query->whereRaw("YEAR(trans_date) = ?", [$year])
                      ->whereRaw("MONTH(trans_date) = ?", [$month]);
            }
        } elseif ($period === 'monthly') {
            if ($dateFrom) {
                $year = date('Y', strtotime($dateFrom));
                $query->whereRaw("YEAR(trans_date) = ?", [$year]);
            }
        } elseif ($period === 'yearly') {
            if ($dateFrom) {
                $query->where('trans_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('trans_date', '<=', $dateTo);
            }
        }

        // 2 & 3. Prepare ProductionPlan Query and Aggregate
        $planQuery = ProductionPlan::query();

        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $planQuery->whereIn('divisi', $divisiSelection['codes']);
        }

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

        $planDateFormat = $this->getDateFormatByPeriod($period, 'plan_date', $planQuery);
        $planDataByPeriod = $planQuery->selectRaw("$planDateFormat as period")
            ->selectRaw('SUM(qty_plan) as qty_plan')
            ->groupByRaw($planDateFormat)
            ->orderByRaw($planDateFormat)
            ->get()
            ->map(function ($item) use ($period) {
                $periodValue = trim((string) $item->period);

                if ($period === 'daily' && !preg_match('/^\d{4}-\d{2}-\d{2}/', $periodValue)) {
                    try {
                        $periodValue = Carbon::parse($periodValue)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Keep original
                    }
                }

                return [
                    'period' => $periodValue,
                    'qty_plan' => (float) ($item->qty_plan ?? 0)
                ];
            })
            ->keyBy('period');

        // 4. Aggregate Report Data by Period
        $dateFormat = $this->getDateFormatByPeriod($period, 'trans_date', $query);
        $reportData = $query->select([
                'part_number',
                'divisi',
                'trans_date',
                'qty_pelaporan'
            ])
            ->selectRaw("$dateFormat as period")
            ->get();

        $data = $reportData->groupBy('period')
            ->map(function ($group, $periodKey) use ($period) {
                $qtyPelaporan = 0;
                foreach ($group as $item) {
                    $qtyPelaporan += (float) ($item->qty_pelaporan ?? 0);
                }

                $periodValue = trim((string) $periodKey);
                if ($period === 'daily' && !preg_match('/^\d{4}-\d{2}-\d{2}/', $periodValue)) {
                    try {
                        $periodValue = Carbon::parse($periodValue)->format('Y-m-d');
                    } catch (\Exception $e) {
                         // Keep original
                    }
                }

                return [
                    'period' => $periodValue,
                    'qty_pelaporan' => $qtyPelaporan
                ];
            })
            ->keyBy('period');

        // 5. Generate All Periods and Merge
        $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
        
        $filledData = collect($allPeriods)->map(function ($periodValue) use ($data, $planDataByPeriod) {
            $periodKey = (string) $periodValue;

            $qtyPelaporan = '0.00';
            $qtyPlan = '0.00';

            // Get Report Data
            if ($data->has($periodKey)) {
                $qtyPelaporan = number_format($data->get($periodKey)['qty_pelaporan'], 2, '.', '');
            }

            // Get Plan Data
            if ($planDataByPeriod->has($periodKey)) {
                $qtyPlan = number_format($planDataByPeriod->get($periodKey)['qty_plan'], 2, '.', '');
            }

            return [
                'period' => $periodKey,
                'qty_pelaporan' => $qtyPelaporan,
                'qty_plan' => $qtyPlan
            ];
        })->values();

        // Fallback if no periods generated (should rarely happen if date_from is set)
        if ($filledData->isEmpty() && ($data->isNotEmpty() || $planDataByPeriod->isNotEmpty())) {
            $allKeys = $data->keys()->merge($planDataByPeriod->keys())->unique()->sort()->values();
            $filledData = $allKeys->map(function($key) use ($data, $planDataByPeriod) {
                 $qtyPelaporan = $data->has($key) ? number_format($data->get($key)['qty_pelaporan'], 2, '.', '') : '0.00';
                 $qtyPlan = $planDataByPeriod->has($key) ? number_format($planDataByPeriod->get($key)['qty_plan'], 2, '.', '') : '0.00';
                 return [
                    'period' => $key,
                    'qty_pelaporan' => $qtyPelaporan,
                    'qty_plan' => $qtyPlan
                 ];
            });
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
            'outstanding_trend' => $this->outstandingTrend($request)->getData(true),
            'daily_production_qty' => $this->dailyProductionQty($request)->getData(true),
            'daily_ng_qty' => $this->dailyNgQty($request)->getData(true),
            'top_ng_type' => $this->topNgType($request)->getData(true),
            'breakdown_cause_distribution' => $this->breakdownCauseDistribution($request)->getData(true)
        ]);
    }

    /**
     * Helper to extract date flexibly based on BSON/JSON formats
     */
    private function extractMongoDate($timeObj, $period = 'daily')
    {
        if (!$timeObj) return null;
        
        $carbon = null;
        if (is_array($timeObj) && isset($timeObj['$date'])) {
            $carbon = Carbon::parse($timeObj['$date']);
        } elseif (is_object($timeObj) && method_exists($timeObj, 'toDateTime')) {
            $carbon = Carbon::instance($timeObj->toDateTime());
        } elseif (is_string($timeObj)) {
            $carbon = Carbon::parse($timeObj);
        }

        if ($carbon) {
            if ($period === 'monthly') {
                return $carbon->format('Y-m');
            } elseif ($period === 'yearly') {
                return $carbon->format('Y');
            }
            return $carbon->format('Y-m-d');
        }
        
        return null;
    }

    /**
     * Helper to map ERP division codes to Kelola plan_code
     */
    private function mapKelolaDivisions(array $erpCodes): array
    {
        $map = [
            'NL' => 'NYL',
            'BZ' => 'BRZ',
            'CH' => 'CHS',
        ];

        return array_map(function ($code) use ($map) {
            return $map[$code] ?? $code;
        }, $erpCodes);
    }

    /**
     * Menampilkan qty production harian dari MongoDB kelola
     * Di grouping per hari, dipisahkan status ok dan ng
     */
    public function dailyProductionQty(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $period = $request->get('period', 'daily');
        $divisiSelection = $this->resolveDivisionFilter($request);

        $query = DB::connection('kelola')->table('productions');
        if ($dateFrom) {
            $query->where('production_time', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('production_time', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }

        $productions = $query->get();

        $dailyData = [];

        // Process OK and NG directly from productions
        foreach ($productions as $prod) {
            $date = $this->extractMongoDate($prod->production_time ?? $prod->created_at ?? null, $period);
            if (!$date) continue;

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = ['period' => $date, 'qty_ok' => 0, 'qty_ng' => 0, 'total_qty' => 0, 'qty_plan' => 0];
            }

            $status = strtolower($prod->status ?? '');
            $qty = (int) ($prod->qty ?? 0);

            if ($status === 'ok') {
                $dailyData[$date]['qty_ok'] += $qty;
                $dailyData[$date]['total_qty'] += $qty;
            } elseif ($status === 'ng') {
                $dailyData[$date]['qty_ng'] += $qty;
                $dailyData[$date]['total_qty'] += $qty;
            }
        }

        // Fetch Planning Data
        $planConnection = 'kelola';
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            if (in_array('CH', $divisiSelection['codes'])) {
                $planConnection = 'kelola7';
            }
        }
        $planQuery = DB::connection($planConnection)->table('production_plannings')->where('status', true);
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $planQuery->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }
        
        if ($dateFrom && $dateTo) {
            $start = Carbon::parse($dateFrom);
            $end = Carbon::parse($dateTo);
            
            if ($start->year === $end->year) {
                $planQuery->where('year', (string)$start->year);
            } else {
                $years = [];
                for ($y = $start->year; $y <= $end->year; $y++) {
                    $years[] = (string)$y;
                }
                $planQuery->whereIn('year', $years);
            }
        }
        $plannings = $planQuery->get();

        $dayWords = [
            1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fiveteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
            21 => 'twenty_one', 22 => 'twenty_two', 23 => 'twenty_three', 24 => 'twenty_four', 25 => 'twenty_five',
            26 => 'twenty_six', 27 => 'twenty_seven', 28 => 'twenty_eight', 29 => 'twenty_nine', 30 => 'thirty',
            31 => 'thirty_one'
        ];

        foreach ($plannings as $plan) {
            $year = (int)$plan->year;
            $month = (int)$plan->month;
            
            for ($d = 1; $d <= 31; $d++) {
                if (!checkdate($month, $d, $year)) continue;
                
                $word = $dayWords[$d];
                $qty = (int)($plan->{$word} ?? 0);
                
                if ($qty > 0) {
                    if ($period === 'daily') {
                        $periodKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        // Optional: Filter precisely by dateFrom and dateTo for daily period
                        if ($dateFrom && $dateTo) {
                            if ($periodKey < Carbon::parse($dateFrom)->format('Y-m-d') || $periodKey > Carbon::parse($dateTo)->format('Y-m-d')) {
                                continue;
                            }
                        }
                    } elseif ($period === 'monthly') {
                        $periodKey = sprintf('%04d-%02d', $year, $month);
                        if ($dateFrom && $dateTo) {
                            $mKey = Carbon::createFromDate($year, $month, 1)->format('Y-m');
                            if ($mKey < Carbon::parse($dateFrom)->format('Y-m') || $mKey > Carbon::parse($dateTo)->format('Y-m')) {
                                continue;
                            }
                        }
                    } else {
                        $periodKey = (string)$year;
                    }
                    
                    if (!isset($dailyData[$periodKey])) {
                        $dailyData[$periodKey] = ['period' => $periodKey, 'qty_ok' => 0, 'qty_ng' => 0, 'total_qty' => 0, 'qty_plan' => 0];
                    } elseif (!isset($dailyData[$periodKey]['qty_plan'])) {
                        $dailyData[$periodKey]['qty_plan'] = 0;
                    }
                    
                    $dailyData[$periodKey]['qty_plan'] += $qty;
                }
            }
        }

        // Ensure all items have qty_plan
        foreach ($dailyData as $key => $item) {
            if (!isset($item['qty_plan'])) {
                $dailyData[$key]['qty_plan'] = 0;
            }
        }

        // Sort by date ascending
        ksort($dailyData);

        // Fill missing periods
        if ($dateFrom && $dateTo) {
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
            $dailyData = collect($allPeriods)->map(function ($date) use ($dailyData) {
                return $dailyData[$date] ?? ['period' => $date, 'qty_ok' => 0, 'qty_ng' => 0, 'total_qty' => 0, 'qty_plan' => 0];
            })->toArray();
        } else {
            $dailyData = array_values($dailyData);
        }

        return response()->json([
            'data' => $dailyData,
            'filter_metadata' => $this->getPeriodMetadata($request, 'production_time'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Menampilkan qty production harian detail berdasarkan main process
     */
    public function dailyProductionQtyDetail(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $period = $request->get('period', 'daily');
        $divisiSelection = $this->resolveDivisionFilter($request);

        $query = DB::connection('kelola')->table('productions');

        if ($dateFrom) {
            $query->where('production_time', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('production_time', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }

        $productions = $query->get();
        $dailyData = [];
        $uniqueMainProcesses = [];

        foreach ($productions as $prod) {
            $date = $this->extractMongoDate($prod->production_time ?? $prod->created_at ?? null, $period);
            if (!$date) continue;

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = ['period' => $date, 'total_qty' => 0];
            }

            $qty = (int) ($prod->qty ?? 0);
            $dailyData[$date]['total_qty'] += $qty;

            $mainProcess = $prod->main_process_name ?? 'Unknown';
            if ($mainProcess === '') $mainProcess = 'Unknown';
            $uniqueMainProcesses[$mainProcess] = true;

            if (!isset($dailyData[$date][$mainProcess])) {
                $dailyData[$date][$mainProcess] = 0;
            }
            $dailyData[$date][$mainProcess] += $qty;
        }

        // Fill missing 0 for all unique main processes in every period
        foreach ($dailyData as $date => &$item) {
            foreach (array_keys($uniqueMainProcesses) as $process) {
                if (!isset($item[$process])) {
                    $item[$process] = 0;
                }
            }
        }
        unset($item);

        ksort($dailyData);

        if ($dateFrom && $dateTo) {
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
            
            if ($period === 'daily') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m-d') && $p <= Carbon::parse($dateTo)->format('Y-m-d');
                });
            } elseif ($period === 'monthly') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m') && $p <= Carbon::parse($dateTo)->format('Y-m');
                });
            }

            $allPeriods = array_values($allPeriods);
            
            $dailyData = collect($allPeriods)->map(function ($date) use ($dailyData, $uniqueMainProcesses) {
                if (isset($dailyData[$date])) {
                    return $dailyData[$date];
                }
                
                $emptyItem = ['period' => $date, 'total_qty' => 0];
                foreach (array_keys($uniqueMainProcesses) as $process) {
                    $emptyItem[$process] = 0;
                }
                return $emptyItem;
            })->toArray();
        } else {
            $dailyData = array_values($dailyData);
        }

        return response()->json([
            'data' => $dailyData,
            'main_processes' => array_keys($uniqueMainProcesses),
            'filter_metadata' => $this->getPeriodMetadata($request, 'production_time'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Menampilkan qty production harian dari Odoo (PostgreSQL)
     */
    public function dailyProductionQtyOdoo(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $period = $request->get('period', 'daily');

        // Fetch Actual Qty
        $actualQuery = DB::connection('odoo')->table('public.stock_move_line')
            ->selectRaw('date(date) as date, sum(quantity) as actual_qty')
            ->where('reference', 'Stock FG Receipt From E-Chutter');

        if ($dateFrom) {
            $actualQuery->where('date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $actualQuery->where('date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $actuals = $actualQuery->groupByRaw('date(date)')
            ->orderByRaw('date(date) asc')
            ->get();

        // Fetch Plan Qty
        $planQuery = DB::connection('odoo')->table('public.pramadya_rail')
            ->selectRaw('date(schedule_date) as schedule_date, sum(qty) as qty')
            ->where('rail_type', 'fg');

        if ($dateFrom) {
            $planQuery->where('schedule_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $planQuery->where('schedule_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $plans = $planQuery->groupByRaw('date(schedule_date)')
            ->orderByRaw('date(schedule_date) asc')
            ->get();

        $dailyData = [];

        // Process Actual Qty
        foreach ($actuals as $act) {
            $date = $this->extractOdooDate($act->date, $period);
            if (!$date) continue;

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'period' => $date,
                    'qty_actual' => 0,
                    'qty_plan' => 0
                ];
            }

            $qty = (float) ($act->actual_qty ?? 0);
            $dailyData[$date]['qty_actual'] += $qty;
        }

        // Process Plan Qty
        foreach ($plans as $pl) {
            $date = $this->extractOdooDate($pl->schedule_date, $period);
            if (!$date) continue;

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'period' => $date,
                    'qty_actual' => 0,
                    'qty_plan' => 0
                ];
            }

            $qty = (float) ($pl->qty ?? 0);
            $dailyData[$date]['qty_plan'] += $qty;
        }

        // Sort by date ascending
        ksort($dailyData);

        // Fill missing periods
        if ($dateFrom && $dateTo) {
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
            $dailyData = collect($allPeriods)->map(function ($date) use ($dailyData) {
                return $dailyData[$date] ?? [
                    'period' => $date,
                    'qty_actual' => 0,
                    'qty_plan' => 0
                ];
            })->toArray();
        } else {
            $dailyData = array_values($dailyData);
        }

        return response()->json([
            'data' => $dailyData,
            'filter_metadata' => $this->getPeriodMetadata($request, 'date'),
        ]);
    }

    /**
     * Helper to extract date flexibly for Odoo queries
     */
    private function extractOdooDate($dateVal, $period = 'daily')
    {
        if (!$dateVal) return null;
        try {
            $carbon = Carbon::parse($dateVal);
            if ($period === 'monthly') {
                return $carbon->format('Y-m');
            } elseif ($period === 'yearly') {
                return $carbon->format('Y');
            }
            return $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Menampilkan qty NG harian
     */
    public function dailyNgQty(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $period = $request->get('period', 'daily');
        $divisiSelection = $this->resolveDivisionFilter($request);

        $query = DB::connection('kelola')->table('productions')
            ->where('status', 'ng');

        if ($dateFrom) {
            $query->where('production_time', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('production_time', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }

        $productions = $query->get();
        $dailyData = [];
        $prodIdToPeriod = [];
        $searchIds = [];
        $uniqueNgNames = [];

        foreach ($productions as $prod) {
            $date = $this->extractMongoDate($prod->production_time ?? $prod->created_at ?? null, $period);
            if (!$date) continue;

            if (!isset($dailyData[$date])) {
                $dailyData[$date] = ['period' => $date, 'qty_ng' => 0];
            }

            $dailyData[$date]['qty_ng'] += (int) ($prod->qty ?? 0);

            $rawId = $prod->id ?? $prod->_id ?? null;
            if ($rawId) {
                $strId = (string) $rawId;
                $searchIds[] = $strId;
                $prodIdToPeriod[$strId] = $date;
            }
        }

        $searchIds = array_unique($searchIds);

        if (!empty($searchIds)) {
            $chunks = array_chunk($searchIds, 1000);
            
            foreach ($chunks as $chunk) {
                $details = DB::connection('kelola')->table('production_details')
                    ->whereIn('production_id', $chunk)
                    ->get();

                foreach ($details as $detail) {
                    $prodId = (string) ($detail->production_id ?? '');
                    if (isset($prodIdToPeriod[$prodId])) {
                        $date = $prodIdToPeriod[$prodId];
                        $ngName = $detail->ng_name ?? 'Unknown';
                        $uniqueNgNames[$ngName] = true;
                        
                        if (!isset($dailyData[$date][$ngName])) {
                            $dailyData[$date][$ngName] = 0;
                        }
                        $dailyData[$date][$ngName] += (int) ($detail->qty ?? 0);
                    }
                }
            }
        }

        // Fill missing 0 for all unique NG names in every period
        foreach ($dailyData as $date => &$item) {
            foreach (array_keys($uniqueNgNames) as $ngName) {
                if (!isset($item[$ngName])) {
                    $item[$ngName] = 0;
                }
            }
        }
        unset($item);

        ksort($dailyData);

        if ($dateFrom && $dateTo) {
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
            
            if ($period === 'daily') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m-d') && $p <= Carbon::parse($dateTo)->format('Y-m-d');
                });
            } elseif ($period === 'monthly') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m') && $p <= Carbon::parse($dateTo)->format('Y-m');
                });
            }

            $allPeriods = array_values($allPeriods);
            
            $dailyData = collect($allPeriods)->map(function ($date) use ($dailyData, $uniqueNgNames) {
                if (isset($dailyData[$date])) {
                    return $dailyData[$date];
                }
                
                $emptyItem = ['period' => $date, 'qty_ng' => 0];
                foreach (array_keys($uniqueNgNames) as $ngName) {
                    $emptyItem[$ngName] = 0;
                }
                return $emptyItem;
            })->toArray();
        } else {
            $dailyData = array_values($dailyData);
        }

        return response()->json([
            'data' => $dailyData,
            'ng_names' => array_keys($uniqueNgNames),
            'filter_metadata' => $this->getPeriodMetadata($request, 'production_time'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Menampilkan top NG Type (ng_name) dari production_details
     */
    public function topNgType(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $divisiSelection = $this->resolveDivisionFilter($request);
        $limit = $request->get('limit', 10);

        $query = DB::connection('kelola')->table('productions')
            ->where('status', 'ng');

        if ($dateFrom) {
            $query->where('production_time', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('production_time', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }

        $productions = $query->get();
        $searchIds = [];

        // Collect IDs (as string since production_id in details is a string)
        foreach ($productions as $prod) {
            $rawId = $prod->id ?? $prod->_id ?? null;
            if ($rawId) {
                $searchIds[] = (string) $rawId;
            }
        }

        $searchIds = array_unique($searchIds);
        $ngTypes = [];

        if (!empty($searchIds)) {
            // Chunking query to prevent query payload from being too massive
            $chunks = array_chunk($searchIds, 1000);
            
            foreach ($chunks as $chunk) {
                $details = DB::connection('kelola')->table('production_details')
                    ->whereIn('production_id', $chunk)
                    ->get();

                foreach ($details as $detail) {
                    $ngName = $detail->ng_name ?? 'Unknown';
                    if (!isset($ngTypes[$ngName])) {
                        $ngTypes[$ngName] = 0;
                    }
                    $ngTypes[$ngName] += (int) ($detail->qty ?? 0);
                }
            }
        }

        arsort($ngTypes);

        $data = [];
        $count = 0;
        foreach ($ngTypes as $name => $qty) {
            if ($count++ >= $limit) break;
            $data[] = [
                'ng_name' => $name,
                'qty' => $qty
            ];
        }

        return response()->json([
            'data' => $data,
            'filter_metadata' => $this->getPeriodMetadata($request, 'production_time'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }

    /**
     * Chart: Breakdown Cause Distribution - Pie/Donut Chart
     *
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (start)
     * - date_to: End date filter (start)
     */
    public function breakdownCauseDistribution(Request $request): JsonResponse
    {
        $divisiSelection = $this->resolveDivisionFilter($request);
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $period = $request->get('period', 'daily');
        $metric = $request->get('metric', 'duration'); // 'duration' or 'count'

        $query = DB::connection('kelola')->table('breakdown_productions');

        if ($dateFrom) {
            $query->where('start', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $query->where('start', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if (!empty($divisiSelection['codes']) && !$divisiSelection['is_all']) {
            $query->whereIn('plan_code', $this->mapKelolaDivisions($divisiSelection['codes']));
        }

        $breakdowns = $query->get();

        $dailyData = [];
        $uniqueCauses = [];
        $totalBreakdowns = 0;

        foreach ($breakdowns as $breakdown) {
            $startObj = is_array($breakdown) ? ($breakdown['start'] ?? null) : ($breakdown->start ?? null);
            $dateStr = $this->extractMongoDate($startObj, $period);
            if (!$dateStr) continue;

            if (!isset($dailyData[$dateStr])) {
                $dailyData[$dateStr] = [
                    'period' => $dateStr,
                    'total_duration_minutes' => 0,
                    'total_count' => 0
                ];
            }

            $cause = is_array($breakdown) ? ($breakdown['cause'] ?? 'UNKNOWN') : ($breakdown->cause ?? 'UNKNOWN');
            $uniqueCauses[$cause] = true;

            $endObj = is_array($breakdown) ? ($breakdown['end'] ?? null) : ($breakdown->end ?? null);

            $startTime = null;
            $endTime = null;

            if ($startObj) {
                if (is_array($startObj) && isset($startObj['$date'])) {
                    $startTime = Carbon::parse($startObj['$date']);
                } elseif (is_object($startObj) && method_exists($startObj, 'toDateTime')) {
                    $startTime = Carbon::instance($startObj->toDateTime());
                } elseif (is_string($startObj)) {
                    $startTime = Carbon::parse($startObj);
                }
            }

            if ($endObj) {
                if (is_array($endObj) && isset($endObj['$date'])) {
                    $endTime = Carbon::parse($endObj['$date']);
                } elseif (is_object($endObj) && method_exists($endObj, 'toDateTime')) {
                    $endTime = Carbon::instance($endObj->toDateTime());
                } elseif (is_string($endObj)) {
                    $endTime = Carbon::parse($endObj);
                }
            }

            if (!isset($dailyData[$dateStr][$cause])) {
                $dailyData[$dateStr][$cause] = 0;
            }

            if ($startTime && $endTime) {
                $diffSec = $startTime->diffInSeconds($endTime);
                $dailyData[$dateStr]['total_duration_minutes'] += ($diffSec / 60);
                
                if ($metric === 'duration') {
                    $dailyData[$dateStr][$cause] += $diffSec;
                }
            }
            
            if ($metric === 'count') {
                $dailyData[$dateStr][$cause] += 1;
            }
            
            $dailyData[$dateStr]['total_count']++;
            $totalBreakdowns++;
        }

        // Convert seconds to minutes for each cause if metric is duration
        foreach ($dailyData as $dateStr => &$item) {
            foreach (array_keys($uniqueCauses) as $cause) {
                if (isset($item[$cause])) {
                    if ($metric === 'duration') {
                        $item[$cause] = round($item[$cause] / 60, 2);
                    }
                } else {
                    $item[$cause] = 0;
                }
            }
            $item['total_duration_minutes'] = round($item['total_duration_minutes'], 2);
        }
        unset($item);

        // Sort by date ascending
        ksort($dailyData);

        // Fill missing periods if filters are applied
        if ($dateFrom && $dateTo) {
            $allPeriods = $this->generateAllPeriods($period, $dateFrom, $dateTo);
            
            // Filter out periods that are out of bounds based on period type
            if ($period === 'daily') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m-d') && $p <= Carbon::parse($dateTo)->format('Y-m-d');
                });
            } elseif ($period === 'monthly') {
                $allPeriods = array_filter($allPeriods, function($p) use ($dateFrom, $dateTo) {
                    return $p >= Carbon::parse($dateFrom)->format('Y-m') && $p <= Carbon::parse($dateTo)->format('Y-m');
                });
            }

            $allPeriods = array_values($allPeriods);

            $dailyData = collect($allPeriods)->map(function ($date) use ($dailyData, $uniqueCauses) {
                $emptyItem = [
                    'period' => $date,
                    'total_duration_minutes' => 0,
                    'total_count' => 0
                ];
                foreach (array_keys($uniqueCauses) as $cause) {
                    $emptyItem[$cause] = 0;
                }
                return $dailyData[$date] ?? $emptyItem;
            })->toArray();
        } else {
            $dailyData = array_values($dailyData);
        }

        return response()->json([
            'data' => $dailyData,
            'causes' => array_keys($uniqueCauses),
            'total_breakdowns' => $totalBreakdowns,
            'filter_metadata' => $this->getPeriodMetadata($request, 'start'),
            'divisi_filter' => $this->getDivisiFilterMetadata($divisiSelection),
        ]);
    }
}
