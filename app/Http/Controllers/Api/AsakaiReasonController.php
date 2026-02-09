<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\AsakaiReason;
use App\Models\AsakaiChart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class AsakaiReasonController extends ApiController
{
    /**
     * Display a listing of asakai reasons.
     * 
     * Query Parameters:
     * - period: Filter by period (daily, monthly, yearly) - default: monthly
     * - date_from: Start date filter
     * - date_to: End date filter
     * - asakai_chart_id: Filter by chart ID
     * - section: Filter by section
     * - search: Search by part_no
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

            $query = AsakaiReason::with(['asakaiChart.asakaiTitle', 'user']);

            // Filter by asakai_chart_id
            if ($request->has('asakai_chart_id')) {
                $query->where('asakai_chart_id', $request->asakai_chart_id);
            }

            // Filter by asakai_title_id (through asakaiChart relationship)
            if ($request->has('asakai_title_id')) {
                $query->whereHas('asakaiChart', function($q) use ($request) {
                    $q->where('asakai_title_id', $request->asakai_title_id);
                });
            }

            // Apply period-specific date filtering
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            if ($period === 'daily') {
                // Daily: Filter by month from date_from
                if ($dateFrom) {
                    $year = date('Y', strtotime($dateFrom));
                    $month = date('m', strtotime($dateFrom));
                    $query->whereRaw("YEAR(date) = ?", [$year])
                          ->whereRaw("MONTH(date) = ?", [$month]);
                }
            } elseif ($period === 'monthly') {
                // Monthly: Filter by year from date_from
                if ($dateFrom) {
                    $year = date('Y', strtotime($dateFrom));
                    $query->whereRaw("YEAR(date) = ?", [$year]);
                }
            } elseif ($period === 'yearly') {
                // Yearly: Filter by date range
                if ($dateFrom) {
                    $query->where('date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->where('date', '<=', $dateTo);
                }
            }

            // Filter by section
            if ($request->has('section')) {
                $query->where('section', $request->section);
            }

            // Search by part_no
            if ($request->has('search')) {
                $query->where('part_no', 'like', '%' . $request->search . '%');
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $reasons = $query->orderBy('date', 'desc')->paginate($perPage);

            // Transform data
            $data = $reasons->map(function ($reason) {
                return [
                    'id' => $reason->id,
                    'asakai_chart_id' => $reason->asakai_chart_id,
                    'asakai_title' => $reason->asakaiChart->asakaiTitle->title,
                    'category' => $reason->asakaiChart->asakaiTitle->category,
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

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $reasons->currentPage(),
                    'total' => $reasons->total(),
                    'per_page' => $reasons->perPage(),
                    'last_page' => $reasons->lastPage(),
                ],
                'filter_metadata' => $this->getPeriodMetadata($request, 'date'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch asakai reasons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created asakai reason.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'asakai_chart_id' => 'required|exists:asakai_charts,id',
                'date' => 'required|date',
                'part_no' => 'nullable|string',
                'part_name' => 'nullable|string',
                'problem' => 'nullable|string',
                'qty' => 'nullable|integer',
                'section' => 'nullable|in:brazzing,chassis,nylon,subcon,passthrough,no_section',
                'line' => 'nullable|string',
                'penyebab' => 'nullable|string',
                'perbaikan' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify that the chart exists and the date matches
            $chart = AsakaiChart::findOrFail($request->asakai_chart_id);
            
            if ($chart->date->format('Y-m-d') !== $request->date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date must match the chart date (' . $chart->date->format('Y-m-d') . ')',
                    'error' => 'The date you provided does not match the chart date. Please use the same date as the chart.'
                ], 422);
            }

            // Check if reason already exists for this chart
            $exists = AsakaiReason::where('asakai_chart_id', $request->asakai_chart_id)
                ->where('date', $request->date)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reason already exists for this chart and date',
                    'error' => 'Duplicate entry detected. A reason for this chart and date already exists.'
                ], 422);
            }

            $reason = AsakaiReason::create([
                'asakai_chart_id' => $request->asakai_chart_id,
                'date' => $request->date,
                'part_no' => $request->part_no,
                'part_name' => $request->part_name,
                'problem' => $request->problem,
                'qty' => $request->qty,
                'section' => $request->section,
                'line' => $request->line,
                'penyebab' => $request->penyebab,
                'perbaikan' => $request->perbaikan,
                'user_id' => Auth::id(),
            ]);

            $reason->load(['asakaiChart.asakaiTitle', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Reason created successfully',
                'data' => [
                    'id' => $reason->id,
                    'asakai_chart_id' => $reason->asakai_chart_id,
                    'asakai_title' => $reason->asakaiChart->asakaiTitle->title,
                    'category' => $reason->asakaiChart->asakaiTitle->category,
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
                ]
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Chart not found',
                'error' => 'The chart you are trying to add a reason to does not exist.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while creating reason',
                'error' => 'Unable to save data to database. Please try again or contact administrator.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reason',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified asakai reason.
     */
    public function show($id)
    {
        try {
            $reason = AsakaiReason::with(['asakaiChart.asakaiTitle', 'user'])->findOrFail($id);

            $data = [
                'id' => $reason->id,
                'asakai_chart_id' => $reason->asakai_chart_id,
                'asakai_title' => $reason->asakaiChart->asakaiTitle->title,
                'category' => $reason->asakaiChart->asakaiTitle->category,
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

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reason not found',
                'error' => 'The requested reason does not exist or has been deleted.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reason data',
                'error' => 'An unexpected error occurred while fetching reason data.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified asakai reason.
     */
    public function update(Request $request, $id)
    {
        try {
            $reason = AsakaiReason::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'asakai_chart_id' => 'sometimes|required|exists:asakai_charts,id',
                'date' => 'sometimes|required|date',
                'part_no' => 'nullable|string',
                'part_name' => 'nullable|string',
                'problem' => 'nullable|string',
                'qty' => 'nullable|integer',
                'section' => 'nullable|in:brazzing,chassis,nylon,subcon,passthrough,no_section',
                'line' => 'nullable|string',
                'penyebab' => 'nullable|string',
                'perbaikan' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If updating chart_id or date, verify they match
            if ($request->has('asakai_chart_id') || $request->has('date')) {
                $chartId = $request->asakai_chart_id ?? $reason->asakai_chart_id;
                $date = $request->date ?? $reason->date;

                $chart = AsakaiChart::findOrFail($chartId);
                
                if ($chart->date->format('Y-m-d') !== $date) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Date must match the chart date (' . $chart->date->format('Y-m-d') . ')'
                    ], 422);
                }

                // Check if updating would create duplicate
                $exists = AsakaiReason::where('asakai_chart_id', $chartId)
                    ->where('date', $date)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Reason already exists for this chart and date',
                        'error' => 'Duplicate entry detected. Another reason with the same chart and date already exists.'
                    ], 422);
                }
            }

            $reason->update($request->only([
                'asakai_chart_id', 'date', 'part_no', 'part_name', 
                'problem', 'qty', 'section', 'line', 'penyebab', 'perbaikan'
            ]));

            $reason->load(['asakaiChart.asakaiTitle', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Reason updated successfully',
                'data' => [
                    'id' => $reason->id,
                    'asakai_chart_id' => $reason->asakai_chart_id,
                    'asakai_title' => $reason->asakaiChart->asakaiTitle->title,
                    'category' => $reason->asakaiChart->asakaiTitle->category,
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
                    'updated_at' => $reason->updated_at->format('Y-m-d H:i:s'),
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reason not found',
                'error' => 'The reason you are trying to update does not exist or has been deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating reason',
                'error' => 'Unable to update data in database. Please try again or contact administrator.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reason',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified asakai reason.
     */
    public function destroy($id)
    {
        try {
            $reason = AsakaiReason::findOrFail($id);
            $reason->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reason deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Reason not found',
                'error' => 'The reason you are trying to delete does not exist or has already been deleted.'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while deleting reason',
                'error' => 'Unable to delete data from database. Please try again or contact administrator.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete reason',
                'error' => 'An unexpected error occurred. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get reasons by chart ID.
     */
    public function getByChart($chartId)
    {
        try {
            $chart = AsakaiChart::with(['asakaiTitle'])->findOrFail($chartId);
            
            $reasons = AsakaiReason::with(['user'])
                ->where('asakai_chart_id', $chartId)
                ->orderBy('date', 'desc')
                ->get();

            $data = $reasons->map(function ($reason) {
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

            return response()->json([
                'success' => true,
                'chart' => [
                    'id' => $chart->id,
                    'asakai_title' => $chart->asakaiTitle->title,
                    'category' => $chart->asakaiTitle->category,
                    'date' => $chart->date->format('Y-m-d'),
                    'qty' => $chart->qty,
                ],
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reasons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export asakai reasons to PDF.
     * 
     * Query Parameters:
     * - asakai_title_id: Filter by asakai title ID (required or optional)
     * - date_from: Start date for filtering (required)
     * - date_to: End date for filtering (required)
     * - section: Filter by section
     */
    public function exportPdf(Request $request)
    {
        try {
            // Get date range parameters
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            // Validate required parameters
            if (!$dateFrom || !$dateTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'date_from and date_to are required',
                    'error' => 'Please provide both date_from and date_to parameters'
                ], 422);
            }

            // Build query to get reasons
            $query = AsakaiReason::with(['asakaiChart.asakaiTitle', 'user'])
                ->whereBetween('date', [$dateFrom, $dateTo]);

            // Filter by asakai_title_id if provided
            if ($request->has('asakai_title_id')) {
                $query->whereHas('asakaiChart', function($q) use ($request) {
                    $q->where('asakai_title_id', $request->asakai_title_id);
                });
            }

            // Filter by section if provided
            if ($request->has('section')) {
                $query->where('section', $request->section);
            }

            // Search by part_no if provided
            if ($request->has('search')) {
                $query->where('part_no', 'like', '%' . $request->search . '%');
            }

            // Get all reasons ordered by date
            $reasonsCollection = $query->orderBy('date', 'desc')->get();

            // Transform reasons to array format for PDF
            $reasons = $reasonsCollection->map(function ($reason) {
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
                    'user' => optional($reason->user)->name,
                ];
            });

            // Determine month and year from date_from
            $month = date('F', strtotime($dateFrom));
            $year = date('Y', strtotime($dateFrom));

            // Determine dept from section filter or leave empty
            $dept = $request->has('section') ? strtoupper($request->section) : '';

            // Get title name if asakai_title_id is provided
            $titleName = '';
            if ($request->has('asakai_title_id') && $reasonsCollection->isNotEmpty()) {
                $titleName = $reasonsCollection->first()->asakaiChart->asakaiTitle->title ?? '';
            }

            $pdf = Pdf::loadView('pdf.asakai_reasons', [
                'reasons' => $reasons,
                'month' => $month,
                'year' => $year,
                'dept' => $dept,
                'titleName' => $titleName,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);

            return $pdf->download('asakai_reasons.pdf');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
