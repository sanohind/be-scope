<?php

namespace App\Http\Controllers\Api;

use App\Models\SalesShipment;
use App\Models\SoMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesAnalyticsController extends ApiController
{
    /**
     * Generate all year-period combinations based on year and period filters
     *
     * @param int|null $year
     * @param int|null $period
     * @return array Array of ['year' => int, 'period' => int] combinations
     */
    private function generateAllYearPeriods(?int $year, ?int $period): array
    {
        $combinations = [];

        // If both year and period are specified, return only that combination
        if ($year && $period) {
            return [['year' => $year, 'period' => $period]];
        }

        // If only year is specified, generate all 12 months
        if ($year && !$period) {
            for ($month = 1; $month <= 12; $month++) {
                $combinations[] = ['year' => $year, 'period' => $month];
            }
            return $combinations;
        }

        // If only period is specified, we need to get all years from data
        // For now, return empty and let the data determine the years
        if (!$year && $period) {
            // We'll need to get years from actual data
            return [];
        }

        // If neither is specified, generate current year with all months
        if (!$year && !$period) {
            $currentYear = Carbon::now()->year;
            for ($month = 1; $month <= 12; $month++) {
                $combinations[] = ['year' => $currentYear, 'period' => $month];
            }
            return $combinations;
        }

        return $combinations;
    }

    /**
     * Fill missing year-period combinations with zero values
     *
     * @param array $data Existing data
     * @param array $allYearPeriods All year-period combinations that should exist
     * @param array $zeroFields Fields to set to 0 for missing periods
     * @return array Filled data array
     */
    private function fillMissingYearPeriods(array $data, array $allYearPeriods, array $zeroFields = ['total_delivery' => 0, 'total_po' => 0]): array
    {
        if (empty($allYearPeriods)) {
            return $data;
        }

        // Create keyed array from existing data
        $dataByKey = [];
        foreach ($data as $item) {
            $key = $item['year'] . '-' . $item['period'];
            $dataByKey[$key] = $item;
        }

        // Fill missing periods
        $filledData = [];
        foreach ($allYearPeriods as $yp) {
            $key = $yp['year'] . '-' . $yp['period'];

            if (isset($dataByKey[$key])) {
                $filledData[] = $dataByKey[$key];
            } else {
                $zeroEntry = [
                    'year' => $yp['year'],
                    'period' => $yp['period'],
                ];
                foreach ($zeroFields as $field => $value) {
                    $zeroEntry[$field] = $value;
                }
                $filledData[] = $zeroEntry;
            }
        }

        return $filledData;
    }
    /**
     * Generate all dates between date_from and date_to
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array Array of dates in Y-m-d format
     */
    private function generateAllDates(?string $dateFrom, ?string $dateTo): array
    {
        $dates = [];
        
        // If no dates provided, use current month
        if (!$dateFrom && !$dateTo) {
            $dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
            $dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
        }
        
        // If only one date provided, use it for both
        if ($dateFrom && !$dateTo) {
            $dateTo = $dateFrom;
        }
        if (!$dateFrom && $dateTo) {
            $dateFrom = $dateTo;
        }
        
        $start = Carbon::parse($dateFrom);
        $end = Carbon::parse($dateTo);
        
        // Generate all dates in range (inclusive of end date)
        while ($start->lte($end)) {
            $dates[] = $start->format('Y-m-d');
            $start->addDay();
        }
        
        return $dates;
    }

    /**
     * Fill missing dates with zero values
     *
     * @param array $data Existing data
     * @param array $allDates All dates that should exist
     * @param array $zeroFields Fields to set to 0 for missing dates
     * @return array Filled data array
     */
    private function fillMissingDates(array $data, array $allDates, array $zeroFields = ['total_delivery' => 0, 'total_po' => 0]): array
    {
        if (empty($allDates)) {
            return $data;
        }

        // Create keyed array from existing data
        $dataByDate = [];
        foreach ($data as $item) {
            $dataByDate[$item['date']] = $item;
        }

        // Fill missing dates
        $filledData = [];
        foreach ($allDates as $date) {
            if (isset($dataByDate[$date])) {
                $filledData[] = $dataByDate[$date];
            } else {
                $zeroEntry = ['date' => $date];
                foreach ($zeroFields as $field => $value) {
                    $zeroEntry[$field] = $value;
                }
                $filledData[] = $zeroEntry;
            }
        }

        return $filledData;
    }

    /**
     * Get bar chart data combining SalesShipment and SoMonitor
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBarChartData(Request $request): JsonResponse
    {
        try {
            // Validate input parameters
            $year = $request->input('year');
            $period = $request->input('period');

            // Build query for SalesShipment (from so_invoice_line)
            $salesShipmentQuery = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    DB::raw('SUM(delivered_qty) as total_delivery'),
                    DB::raw('0 as total_po')
                );

            // Apply year filter if provided
            if ($year) {
                $salesShipmentQuery->whereRaw('YEAR(delivery_date) = ?', [$year]);
            }

            // Apply period filter if provided
            if ($period) {
                $salesShipmentQuery->whereRaw('MONTH(delivery_date) = ?', [$period]);
            }

            $salesShipmentQuery->groupBy(
                DB::raw('YEAR(delivery_date)'),
                DB::raw('MONTH(delivery_date)')
            );

            // Build query for SoMonitor
            $soMonitorQuery = DB::connection('erp')->table('so_monitor')
                ->select(
                    DB::raw('YEAR(planned_delivery_date) as year'),
                    DB::raw('MONTH(planned_delivery_date) as period'),
                    DB::raw('0 as total_delivery'),
                    DB::raw('SUM(order_qty) as total_po')
                )
                ->where('sequence', 0);

            if ($year) {
                $soMonitorQuery->whereRaw('YEAR(planned_delivery_date) = ?', [$year]);
            }

            if ($period) {
                $soMonitorQuery->whereRaw('MONTH(planned_delivery_date) = ?', [$period]);
            }

            $soMonitorQuery->groupBy(
                DB::raw('YEAR(planned_delivery_date)'),
                DB::raw('MONTH(planned_delivery_date)')
            );

            // Get data from both queries separately and merge in PHP
            $salesShipmentData = $salesShipmentQuery->get();
            $soMonitorData = $soMonitorQuery->get();

            // Merge data by year and period
            $mergedData = [];

            foreach ($salesShipmentData as $row) {
                $key = $row->year . '-' . $row->period;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$key]['total_delivery'] += (float)$row->total_delivery;
            }

            foreach ($soMonitorData as $row) {
                $key = $row->year . '-' . $row->period;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$key]['total_po'] += (float)$row->total_po;
            }

            // Reset array keys and sort
            $result = array_values($mergedData);
            usort($result, function ($a, $b) {
                if ($a['year'] !== $b['year']) {
                    return $a['year'] - $b['year'];
                }
                return $a['period'] - $b['period'];
            });

            // Generate all year-period combinations and fill missing ones
            $allYearPeriods = $this->generateAllYearPeriods($year ? (int)$year : null, $period ? (int)$period : null);

            // If we have year-period combinations to fill, do it
            if (!empty($allYearPeriods)) {
                $result = $this->fillMissingYearPeriods($result, $allYearPeriods, ['total_delivery' => 0, 'total_po' => 0]);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bar chart data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily bar chart data combining SalesShipment and SoMonitor
     * Accepts date_from and date_to parameters (optional, defaults to current month)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDailyBarChartData(Request $request): JsonResponse
    {
        try {
            // Get date parameters
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            // If no dates provided, use current month
            if (!$dateFrom && !$dateTo) {
                $dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
                $dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
            }

            // If only one date provided, use it for both
            if ($dateFrom && !$dateTo) {
                $dateTo = $dateFrom;
            }
            if (!$dateFrom && $dateTo) {
                $dateFrom = $dateTo;
            }

            // Build query for SalesShipment (from so_invoice_line)
            // Using CAST for SQL Server compatibility
            $salesShipmentQuery = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('CAST(delivery_date AS DATE) as date'),
                    DB::raw('SUM(delivered_qty) as total_delivery'),
                    DB::raw('0 as total_po')
                )
                ->whereRaw('CAST(delivery_date AS DATE) >= ?', [$dateFrom])
                ->whereRaw('CAST(delivery_date AS DATE) <= ?', [$dateTo])
                ->groupBy(DB::raw('CAST(delivery_date AS DATE)'));

            // Build query for SoMonitor
            // Using CAST for SQL Server compatibility
            $soMonitorQuery = DB::connection('erp')->table('so_monitor')
                ->select(
                    DB::raw('CAST(planned_delivery_date AS DATE) as date'),
                    DB::raw('0 as total_delivery'),
                    DB::raw('SUM(order_qty) as total_po')
                )
                ->where('sequence', 0)
                ->whereRaw('CAST(planned_delivery_date AS DATE) >= ?', [$dateFrom])
                ->whereRaw('CAST(planned_delivery_date AS DATE) <= ?', [$dateTo])
                ->groupBy(DB::raw('CAST(planned_delivery_date AS DATE)'));

            // Get data from both queries separately and merge in PHP
            $salesShipmentData = $salesShipmentQuery->get();
            $soMonitorData = $soMonitorQuery->get();

            // Merge data by date
            $mergedData = [];

            foreach ($salesShipmentData as $row) {
                $date = $row->date;

                if (!isset($mergedData[$date])) {
                    $mergedData[$date] = [
                        'date' => $date,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$date]['total_delivery'] += (float)$row->total_delivery;
            }

            foreach ($soMonitorData as $row) {
                $date = $row->date;

                if (!isset($mergedData[$date])) {
                    $mergedData[$date] = [
                        'date' => $date,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$date]['total_po'] += (float)$row->total_po;
            }

            // Reset array keys and sort by date
            $result = array_values($mergedData);
            usort($result, function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            // Generate all dates and fill missing ones with zeros
            $allDates = $this->generateAllDates($dateFrom, $dateTo);
            
            if (!empty($allDates)) {
                $result = $this->fillMissingDates($result, $allDates, ['total_delivery' => 0, 'total_po' => 0]);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daily bar chart data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sales shipment data by period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSalesShipmentByPeriod(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year');
            $period = $request->input('period');

            $query = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    DB::raw('SUM(delivered_qty) as total_delivery')
                )
                ->groupBy(
                    DB::raw('YEAR(delivery_date)'),
                    DB::raw('MONTH(delivery_date)')
                );

            if ($year) {
                $query->whereRaw('YEAR(delivery_date) = ?', [$year]);
            }

            if ($period) {
                $query->whereRaw('MONTH(delivery_date) = ?', [$period]);
            }

            $data = $query
                ->orderBy('year')
                ->orderBy('period')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => (int)$item->year,
                        'period' => (int)$item->period,
                        'total_delivery' => (float)$item->total_delivery,
                    ];
                })
                ->toArray();

            // Generate all year-period combinations and fill missing ones
            $allYearPeriods = $this->generateAllYearPeriods($year ? (int)$year : null, $period ? (int)$period : null);

            // If we have year-period combinations to fill, do it
            if (!empty($allYearPeriods)) {
                $data = $this->fillMissingYearPeriods($data, $allYearPeriods, ['total_delivery' => 0]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales shipment data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get SO monitor data by period
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSoMonitorByPeriod(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year');
            $period = $request->input('period');

            $query = DB::connection('erp')->table('so_monitor')
                ->select(
                    'year',
                    'period',
                    DB::raw('SUM(total_po) as total_po')
                )
                ->where('sequence', 0)
                ->groupBy('year', 'period');

            if ($year) {
                $query->where('year', $year);
            }

            if ($period) {
                $query->where('period', $period);
            }

            $data = $query
                ->orderBy('year')
                ->orderBy('period')
                ->get()
                ->map(function ($item) {
                    return [
                        'year' => (int)$item->year,
                        'period' => (int)$item->period,
                        'total_po' => (float)$item->total_po,
                    ];
                })
                ->toArray();

            // Generate all year-period combinations and fill missing ones
            $allYearPeriods = $this->generateAllYearPeriods($year ? (int)$year : null, $period ? (int)$period : null);

            // If we have year-period combinations to fill, do it
            if (!empty($allYearPeriods)) {
                $data = $this->fillMissingYearPeriods($data, $allYearPeriods, ['total_po' => 0]);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch SO monitor data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get combined data with details by business partner
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCombinedDataWithDetails(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year');
            $period = $request->input('period');

            // Get sales shipment details
            $salesShipmentQuery = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    'bp_code',
                    'bp_name',
                    DB::raw('SUM(delivered_qty) as total_delivery'),
                    DB::raw('0 as total_po')
                );

            if ($year) {
                $salesShipmentQuery->whereRaw('YEAR(delivery_date) = ?', [$year]);
            }

            if ($period) {
                $salesShipmentQuery->whereRaw('MONTH(delivery_date) = ?', [$period]);
            }

            $salesShipmentQuery->groupBy(
                DB::raw('YEAR(delivery_date)'),
                DB::raw('MONTH(delivery_date)'),
                'bp_code',
                'bp_name'
            );

            // Get SO monitor details
            $soMonitorQuery = DB::connection('erp')->table('so_monitor')
                ->select(
                    'year',
                    'period',
                    'bp_code',
                    'bp_name',
                    DB::raw('0 as total_delivery'),
                    DB::raw('SUM(total_po) as total_po')
                )
                ->where('sequence', 0);

            if ($year) {
                $soMonitorQuery->where('year', $year);
            }

            if ($period) {
                $soMonitorQuery->where('period', $period);
            }

            $soMonitorQuery->groupBy('year', 'period', 'bp_code', 'bp_name');

            // Get data from both queries separately and merge in PHP
            $salesShipmentData = $salesShipmentQuery->get();
            $soMonitorData = $soMonitorQuery->get();

            // Merge data by year, period, and bp_code
            $mergedData = [];

            foreach ($salesShipmentData as $row) {
                $key = $row->year . '-' . $row->period . '-' . $row->bp_code;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'bp_code' => $row->bp_code,
                        'bp_name' => $row->bp_name,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$key]['total_delivery'] += (float)$row->total_delivery;
            }

            foreach ($soMonitorData as $row) {
                $key = $row->year . '-' . $row->period . '-' . $row->bp_code;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'bp_code' => $row->bp_code,
                        'bp_name' => $row->bp_name,
                        'total_delivery' => 0,
                        'total_po' => 0,
                    ];
                }

                $mergedData[$key]['total_po'] += (float)$row->total_po;
            }

            $result = array_values($mergedData);
            usort($result, function ($a, $b) {
                if ($a['year'] !== $b['year']) {
                    return $a['year'] - $b['year'];
                }
                if ($a['period'] !== $b['period']) {
                    return $a['period'] - $b['period'];
                }
                return strcmp($a['bp_code'], $b['bp_code']);
            });

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch combined data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get delivery performance chart data
     * Performance = (total_delivery / total_po) * 100
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDeliveryPerformance(Request $request): JsonResponse
    {
        try {
            // Validate input parameters
            $year = $request->input('year');
            $period = $request->input('period');

            // Get sales shipment data (delivery)
            $salesShipmentQuery = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    DB::raw('SUM(delivered_qty) as total_delivery'),
                    DB::raw('0 as total_po')
                );

            if ($year) {
                $salesShipmentQuery->whereRaw('YEAR(delivery_date) = ?', [$year]);
            }

            if ($period) {
                $salesShipmentQuery->whereRaw('MONTH(delivery_date) = ?', [$period]);
            }

            $salesShipmentQuery->groupBy(
                DB::raw('YEAR(delivery_date)'),
                DB::raw('MONTH(delivery_date)')
            );

            // Get SO monitor data (PO)
            $soMonitorQuery = DB::connection('erp')->table('so_monitor')
                ->select(
                    DB::raw('YEAR(planned_delivery_date) as year'),
                    DB::raw('MONTH(planned_delivery_date) as period'),
                    DB::raw('0 as total_delivery'),
                    DB::raw('SUM(order_qty) as total_po')
                )
                ->where('sequence', 0);

            if ($year) {
                $soMonitorQuery->whereRaw('YEAR(planned_delivery_date) = ?', [$year]);
            }

            if ($period) {
                $soMonitorQuery->whereRaw('MONTH(planned_delivery_date) = ?', [$period]);
            }

            $soMonitorQuery->groupBy(
                DB::raw('YEAR(planned_delivery_date)'),
                DB::raw('MONTH(planned_delivery_date)')
            );

            // Get data from both queries
            $salesShipmentData = $salesShipmentQuery->get();
            $soMonitorData = $soMonitorQuery->get();

            // Merge data and calculate performance
            $mergedData = [];

            foreach ($salesShipmentData as $row) {
                $key = $row->year . '-' . $row->period;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'total_delivery' => 0,
                        'total_po' => 0,
                        'performance' => 0,
                    ];
                }

                $mergedData[$key]['total_delivery'] += (float)$row->total_delivery;
            }

            foreach ($soMonitorData as $row) {
                $key = $row->year . '-' . $row->period;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'total_delivery' => 0,
                        'total_po' => 0,
                        'performance' => 0,
                    ];
                }

                $mergedData[$key]['total_po'] += (float)$row->total_po;
            }

            // Calculate performance percentage for each period
            foreach ($mergedData as &$data) {
                if ($data['total_po'] > 0) {
                    $data['performance'] = round(($data['total_delivery'] / $data['total_po']) * 100, 2);
                } else {
                    $data['performance'] = 0;
                }
            }

            // Reset array keys and sort
            $result = array_values($mergedData);
            usort($result, function ($a, $b) {
                if ($a['year'] !== $b['year']) {
                    return $a['year'] - $b['year'];
                }
                return $a['period'] - $b['period'];
            });

            // Generate all year-period combinations and fill missing ones
            $allYearPeriods = $this->generateAllYearPeriods($year ? (int)$year : null, $period ? (int)$period : null);

            // If we have year-period combinations to fill, do it
            if (!empty($allYearPeriods)) {
                $result = $this->fillMissingYearPeriods($result, $allYearPeriods, ['total_delivery' => 0, 'total_po' => 0, 'performance' => 0]);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery performance data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get delivery performance with business partner details
     * Performance = (total_delivery / total_po) * 100
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDeliveryPerformanceByBp(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year');
            $period = $request->input('period');

            // Get sales shipment details
            $salesShipmentQuery = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    'bp_code',
                    'bp_name',
                    DB::raw('SUM(delivered_qty) as total_delivery'),
                    DB::raw('0 as total_po')
                );

            if ($year) {
                $salesShipmentQuery->whereRaw('YEAR(delivery_date) = ?', [$year]);
            }

            if ($period) {
                $salesShipmentQuery->whereRaw('MONTH(delivery_date) = ?', [$period]);
            }

            $salesShipmentQuery->groupBy(
                DB::raw('YEAR(delivery_date)'),
                DB::raw('MONTH(delivery_date)'),
                'bp_code',
                'bp_name'
            );

            // Get SO monitor details
            $soMonitorQuery = DB::connection('erp')->table('so_monitor')
                ->select(
                    DB::raw('YEAR(planned_delivery_date) as year'),
                    DB::raw('MONTH(planned_delivery_date) as period'),
                    'bp_code',
                    'bp_name',
                    DB::raw('0 as total_delivery'),
                    DB::raw('SUM(order_qty) as total_po')
                )
                ->where('sequence', 0);

            if ($year) {
                $soMonitorQuery->whereRaw('YEAR(planned_delivery_date) = ?', [$year]);
            }

            if ($period) {
                $soMonitorQuery->whereRaw('MONTH(planned_delivery_date) = ?', [$period]);
            }

            $soMonitorQuery->groupBy(
                DB::raw('YEAR(planned_delivery_date)'),
                DB::raw('MONTH(planned_delivery_date)'),
                'bp_code',
                'bp_name'
            );

            // Get data from both queries
            $salesShipmentData = $salesShipmentQuery->get();
            $soMonitorData = $soMonitorQuery->get();

            // Merge data and calculate performance
            $mergedData = [];

            foreach ($salesShipmentData as $row) {
                $key = $row->year . '-' . $row->period . '-' . $row->bp_code;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'bp_code' => $row->bp_code,
                        'bp_name' => $row->bp_name,
                        'total_delivery' => 0,
                        'total_po' => 0,
                        'performance' => 0,
                    ];
                }

                $mergedData[$key]['total_delivery'] += (float)$row->total_delivery;
            }

            foreach ($soMonitorData as $row) {
                $key = $row->year . '-' . $row->period . '-' . $row->bp_code;

                if (!isset($mergedData[$key])) {
                    $mergedData[$key] = [
                        'year' => $row->year,
                        'period' => $row->period,
                        'bp_code' => $row->bp_code,
                        'bp_name' => $row->bp_name,
                        'total_delivery' => 0,
                        'total_po' => 0,
                        'performance' => 0,
                    ];
                }

                $mergedData[$key]['total_po'] += (float)$row->total_po;
            }

            // Calculate performance percentage for each business partner
            foreach ($mergedData as &$data) {
                if ($data['total_po'] > 0) {
                    $data['performance'] = round(($data['total_delivery'] / $data['total_po']) * 100, 2);
                } else {
                    $data['performance'] = 0;
                }
            }

            $result = array_values($mergedData);
            usort($result, function ($a, $b) {
                if ($a['year'] !== $b['year']) {
                    return $a['year'] - $b['year'];
                }
                if ($a['period'] !== $b['period']) {
                    return $a['period'] - $b['period'];
                }
                return strcmp($a['bp_code'], $b['bp_code']);
            });

            // Note: For methods with business partner details, we don't fill missing periods
            // because missing periods might be intentional (no data for that BP in that period)
            // Only fill if specifically needed for aggregation views

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery performance by BP data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
