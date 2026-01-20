<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\AsakaiChart;
use App\Models\AsakaiTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AsakaiChartController extends ApiController
{
    /**
     * Display a listing of asakai charts.
     * 
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter
     * - date_to: End date filter
     * - asakai_title_id: Filter by asakai title
     * - date: Filter by specific date
     * - per_page: Items per page (default: 10)
     */
    public function index(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            
            // Validate period
            if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
                $period = 'monthly';
            }

            // Get asakai_title_id filter (required for filling dates)
            $asakaiTitleId = $request->get('asakai_title_id');
            
            // Apply period-specific date filtering
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Calculate date range based on period
            if ($period === 'daily') {
                // Daily: Filter by month from date_from
                if ($dateFrom) {
                    $dateFromCarbon = Carbon::parse($dateFrom);
                    $dateFrom = $dateFromCarbon->startOfMonth()->format('Y-m-d');
                    $dateTo = $dateFromCarbon->endOfMonth()->format('Y-m-d');
                }
            } elseif ($period === 'monthly') {
                // Monthly: Filter by year from date_from
                if ($dateFrom) {
                    $dateFromCarbon = Carbon::parse($dateFrom);
                    $dateFrom = $dateFromCarbon->startOfYear()->format('Y-m-d');
                    $dateTo = $dateFromCarbon->endOfYear()->format('Y-m-d');
                }
            } elseif ($period === 'yearly') {
                // Yearly: Use provided date range or default to 5 years
                if ($dateFrom && !$dateTo) {
                    $dateFromCarbon = Carbon::parse($dateFrom);
                    $dateTo = $dateFromCarbon->copy()->addYears(5)->format('Y-m-d');
                }
            }

            // Build query for existing chart data
            $query = AsakaiChart::with(['asakaiTitle', 'user', 'reasons']);

            // Filter by asakai_title_id
            if ($asakaiTitleId) {
                $query->where('asakai_title_id', $asakaiTitleId);
            }

            // Apply date range filter
            if ($dateFrom && $dateTo) {
                $query->whereBetween('date', [$dateFrom, $dateTo]);
            }

            // Filter by specific date (overrides period filtering)
            if ($request->has('date')) {
                $query->whereDate('date', $request->date);
            }

            // Get existing chart data
            $charts = $query->orderBy('date', 'desc')->get();

            // Transform existing data and key by date
            $chartsByDate = $charts->map(function ($chart) {
                return [
                    'id' => $chart->id,
                    'asakai_title_id' => $chart->asakai_title_id,
                    'asakai_title' => $chart->asakaiTitle->title,
                    'category' => $chart->asakaiTitle->category,
                    'date' => $chart->date->format('Y-m-d'),
                    'qty' => $chart->qty,
                    'user' => $chart->user->name,
                    'user_id' => $chart->user_id,
                    'reasons_count' => $chart->reasons->count(),
                    'created_at' => $chart->created_at->format('Y-m-d H:i:s'),
                    'has_data' => true,
                ];
            })->keyBy('date');

            // If asakai_title_id is provided and we have date range, fill missing dates
            if ($asakaiTitleId && $dateFrom && $dateTo) {
                // Generate all periods in range
                $carbonDateFrom = Carbon::parse($dateFrom);
                $carbonDateTo = Carbon::parse($dateTo);
                
                $dateRange = $this->generateDateRange($carbonDateFrom, $carbonDateTo, $period);
                $allPeriods = array_map(function($date) {
                    return $date->format('Y-m-d');
                }, $dateRange);

                // Fill missing dates with qty = 0
                $filledData = collect($allPeriods)->map(function ($periodValue) use ($chartsByDate, $asakaiTitleId) {
                    if ($chartsByDate->has($periodValue)) {
                        return $chartsByDate->get($periodValue);
                    } else {
                        return [
                            'id' => null,
                            'asakai_title_id' => (int) $asakaiTitleId,
                            'asakai_title' => null,
                            'category' => null,
                            'date' => $periodValue,
                            'qty' => 0,
                            'user' => null,
                            'user_id' => null,
                            'reasons_count' => 0,
                            'created_at' => null,
                            'has_data' => false,
                        ];
                    }
                })->values();

                // Pagination for filled data
                $perPage = $request->get('per_page', 10);
                $currentPage = $request->get('page', 1);
                $total = $filledData->count();
                $lastPage = (int) ceil($total / $perPage);
                
                $paginatedData = $filledData->forPage($currentPage, $perPage);

                return response()->json([
                    'success' => true,
                    'data' => $paginatedData->values(),
                    'pagination' => [
                        'current_page' => (int) $currentPage,
                        'total' => $total,
                        'per_page' => (int) $perPage,
                        'last_page' => $lastPage,
                    ],
                    'filter_metadata' => [
                        'period' => $period,
                        'date_field' => 'date',
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'asakai_title_id' => (int) $asakaiTitleId,
                        'total_dates' => $total,
                        'dates_with_data' => $chartsByDate->count(),
                        'dates_without_data' => $total - $chartsByDate->count(),
                    ],
                ], 200);
            } else {
                // If no asakai_title_id or date range, return original paginated data
                $perPage = $request->get('per_page', 10);
                $currentPage = $request->get('page', 1);
                $total = $chartsByDate->count();
                $lastPage = (int) ceil($total / $perPage);
                
                $paginatedData = $chartsByDate->forPage($currentPage, $perPage);

                return response()->json([
                    'success' => true,
                    'data' => $paginatedData->values(),
                    'pagination' => [
                        'current_page' => (int) $currentPage,
                        'total' => $total,
                        'per_page' => (int) $perPage,
                        'last_page' => $lastPage,
                    ],
                    'filter_metadata' => [
                        'period' => $period,
                        'date_field' => 'date',
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ],
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch asakai charts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created asakai chart.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'asakai_title_id' => 'required|exists:asakai_titles,id',
                'date' => 'required|date',
                'qty' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if chart already exists for this title and date
            $exists = AsakaiChart::where('asakai_title_id', $request->asakai_title_id)
                ->where('date', $request->date)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chart data already exists for this title and date',
                    'error' => 'Duplicate entry detected. Please use a different date or update the existing chart.'
                ], 422);
            }

            $chart = AsakaiChart::create([
                'asakai_title_id' => $request->asakai_title_id,
                'date' => $request->date,
                'qty' => $request->qty,
                'user_id' => 1, // Dummy user for development
            ]);

            $chart->load(['asakaiTitle', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Chart data created successfully',
                'data' => [
                    'id' => $chart->id,
                    'asakai_title_id' => $chart->asakai_title_id,
                    'asakai_title' => $chart->asakaiTitle->title,
                    'category' => $chart->asakaiTitle->category,
                    'date' => $chart->date->format('Y-m-d'),
                    'qty' => $chart->qty,
                    'user' => $chart->user->name,
                    'user_id' => $chart->user_id,
                    'created_at' => $chart->created_at->format('Y-m-d H:i:s'),
                ]
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating chart data',
                'error' => 'Unable to save data to database. Please try again or contact administrator.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create chart data',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified asakai chart with reasons.
     */
    public function show($id)
    {
        try {
            $chart = AsakaiChart::with(['asakaiTitle', 'user', 'reasons.user'])->findOrFail($id);

            $data = [
                'id' => $chart->id,
                'asakai_title_id' => $chart->asakai_title_id,
                'asakai_title' => $chart->asakaiTitle->title,
                'category' => $chart->asakaiTitle->category,
                'date' => $chart->date->format('Y-m-d'),
                'qty' => $chart->qty,
                'user' => $chart->user->name,
                'user_id' => $chart->user_id,
                'created_at' => $chart->created_at->format('Y-m-d H:i:s'),
                'reasons' => $chart->reasons->map(function ($reason) {
                    return [
                        'id' => $reason->id,
                        'date' => $reason->date->format('Y-m-d'),
                        'part_no' => $reason->part_no,
                        'part_name' => $reason->part_name,
                        'problem' => $reason->problem,
                        'qty' => $reason->qty,
                        'section' => $reason->section,
                        'line' => $reason->line,
                        'penyebab' => $reason->penyebab,
                        'perbaikan' => $reason->perbaikan,
                        'user' => $reason->user->name,
                        'user_id' => $reason->user_id,
                        'created_at' => $reason->created_at->format('Y-m-d H:i:s'),
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chart data not found',
                'error' => 'The requested chart does not exist or has been deleted.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve chart data',
                'error' => 'An unexpected error occurred while fetching chart data.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified asakai chart.
     */
    public function update(Request $request, $id)
    {
        try {
            $chart = AsakaiChart::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'asakai_title_id' => 'sometimes|required|exists:asakai_titles,id',
                'date' => 'sometimes|required|date',
                'qty' => 'sometimes|required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if updating would create duplicate
            if ($request->has('asakai_title_id') || $request->has('date')) {
                $titleId = $request->asakai_title_id ?? $chart->asakai_title_id;
                $date = $request->date ?? $chart->date;

                $exists = AsakaiChart::where('asakai_title_id', $titleId)
                    ->where('date', $date)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Chart data already exists for this title and date',
                        'error' => 'Duplicate entry detected. Another chart with the same title and date already exists.'
                    ], 422);
                }
            }

            $chart->update($request->only(['asakai_title_id', 'date', 'qty']));
            $chart->load(['asakaiTitle', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Chart data updated successfully',
                'data' => [
                    'id' => $chart->id,
                    'asakai_title_id' => $chart->asakai_title_id,
                    'asakai_title' => $chart->asakaiTitle->title,
                    'category' => $chart->asakaiTitle->category,
                    'date' => $chart->date->format('Y-m-d'),
                    'qty' => $chart->qty,
                    'user' => $chart->user->name,
                    'user_id' => $chart->user_id,
                    'updated_at' => $chart->updated_at->format('Y-m-d H:i:s'),
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chart data not found',
                'error' => 'The chart you are trying to update does not exist or has been deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating chart data',
                'error' => 'Unable to update data in database. Please try again or contact administrator.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update chart data',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified asakai chart.
     */
    public function destroy($id)
    {
        try {
            $chart = AsakaiChart::findOrFail($id);
            
            // Delete associated reasons first
            $chart->reasons()->delete();
            
            $chart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Chart data deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chart data not found',
                'error' => 'The chart you are trying to delete does not exist or has already been deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while deleting chart data',
                'error' => 'Unable to delete data from database. This may be due to foreign key constraints.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete chart data',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get chart data with filled dates (missing dates default to qty = 0).
     * 
     * Query Parameters:
     * - asakai_title_id: Filter by asakai title (required)
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter (required)
     * - date_to: End date filter (optional, defaults based on period)
     */
    public function getChartData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'asakai_title_id' => 'required|exists:asakai_titles,id',
                'period' => 'sometimes|in:daily,monthly,yearly',
                'date_from' => 'required|date',
                'date_to' => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = $request->has('date_to') 
                ? Carbon::parse($request->date_to) 
                : $this->getDefaultDateTo($dateFrom, $period);

            // Get existing chart data with reasons
            $charts = AsakaiChart::with(['reasons.user'])
                ->where('asakai_title_id', $request->asakai_title_id)
                ->whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
                ->get()
                ->keyBy(function ($item) {
                    return $item->date->format('Y-m-d');
                });

            // Generate all dates in range
            $allDates = $this->generateDateRange($dateFrom, $dateTo, $period);

            // Fill missing dates with qty = 0
            $data = collect($allDates)->map(function ($date) use ($charts, $request) {
                $dateKey = $date->format('Y-m-d');
                
                if ($charts->has($dateKey)) {
                    $chart = $charts->get($dateKey);
                    
                    // Transform reasons data
                    $reasons = $chart->reasons->map(function ($reason) {
                        return [
                            'id' => $reason->id,
                            'date' => $reason->date->format('Y-m-d'),
                            'part_no' => $reason->part_no,
                            'part_name' => $reason->part_name,
                            'problem' => $reason->problem,
                            'qty' => $reason->qty,
                            'section' => $reason->section,
                            'line' => $reason->line,
                            'penyebab' => $reason->penyebab,
                            'perbaikan' => $reason->perbaikan,
                            'user' => $reason->user->name,
                            'user_id' => $reason->user_id,
                            'created_at' => $reason->created_at->format('Y-m-d H:i:s'),
                        ];
                    });
                    
                    return [
                        'date' => $dateKey,
                        'qty' => $chart->qty,
                        'has_data' => true,
                        'chart_id' => $chart->id,
                        'reasons' => $reasons,
                        'reasons_count' => $reasons->count(),
                    ];
                } else {
                    return [
                        'date' => $dateKey,
                        'qty' => 0,
                        'has_data' => false,
                        'chart_id' => null,
                        'reasons' => [],
                        'reasons_count' => 0,
                    ];
                }
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'filter_metadata' => [
                    'asakai_title_id' => (int) $request->asakai_title_id,
                    'period' => $period,
                    'date_from' => $dateFrom->format('Y-m-d'),
                    'date_to' => $dateTo->format('Y-m-d'),
                    'total_dates' => count($allDates),
                    'dates_with_data' => $charts->count(),
                    'dates_without_data' => count($allDates) - $charts->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate date range based on period.
     */
    private function generateDateRange(Carbon $dateFrom, Carbon $dateTo, string $period): array
    {
        $dates = [];
        $current = $dateFrom->copy();

        while ($current->lte($dateTo)) {
            $dates[] = $current->copy();
            
            if ($period === 'daily') {
                $current->addDay();
            } elseif ($period === 'monthly') {
                $current->addMonth();
            } elseif ($period === 'yearly') {
                $current->addYear();
            }
        }

        return $dates;
    }

    /**
     * Get default date_to based on period.
     */
    private function getDefaultDateTo(Carbon $dateFrom, string $period): Carbon
    {
        if ($period === 'daily') {
            // For daily: end of month
            return $dateFrom->copy()->endOfMonth();
        } elseif ($period === 'monthly') {
            // For monthly: end of year
            return $dateFrom->copy()->endOfYear();
        } elseif ($period === 'yearly') {
            // For yearly: 5 years from date_from
            return $dateFrom->copy()->addYears(5);
        }

        return $dateFrom->copy()->endOfMonth();
    }

    /**
     * Get available dates for a specific asakai title (for reason input).
     */
    public function getAvailableDates(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'asakai_title_id' => 'required|exists:asakai_titles,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $charts = AsakaiChart::where('asakai_title_id', $request->asakai_title_id)
                ->with('asakaiTitle')
                ->orderBy('date', 'desc')
                ->get();

            $data = $charts->map(function ($chart) {
                return [
                    'chart_id' => $chart->id,
                    'date' => $chart->date->format('Y-m-d'),
                    'qty' => $chart->qty,
                    'has_reason' => $chart->reasons()->exists(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available dates',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
