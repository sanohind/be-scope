<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncController extends Controller
{
    /**
     * Start manual sync
     */
    public function startManualSync(Request $request)
    {
        try {
            // Check if there's already a running sync
            $runningSync = SyncLog::where('status', 'running')->first();
            if ($runningSync) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sync is already running. Please wait for it to complete.',
                    'running_sync_id' => $runningSync->id
                ], 409);
            }

            // Start the sync process in background
            $exitCode = Artisan::call('sync:erp-data', ['--manual' => true]);

            if ($exitCode === 0) {
                $latestSync = SyncLog::latest()->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Manual sync started successfully',
                    'sync_log_id' => $latestSync->id,
                    'status' => $latestSync->status
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to start sync process'
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Manual sync failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to start manual sync: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status
     */
    public function getSyncStatus(Request $request)
    {
        try {
            $syncId = $request->query('sync_id');

            if ($syncId) {
                $syncLog = SyncLog::find($syncId);
                if (!$syncLog) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sync log not found'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $syncLog->id,
                        'sync_type' => $syncLog->sync_type,
                        'status' => $syncLog->status,
                        'started_at' => $syncLog->started_at,
                        'completed_at' => $syncLog->completed_at,
                        'total_records' => $syncLog->total_records,
                        'success_records' => $syncLog->success_records,
                        'failed_records' => $syncLog->failed_records,
                        'error_message' => $syncLog->error_message,
                        'duration' => $syncLog->duration,
                        'success_rate' => $syncLog->success_rate
                    ]
                ]);
            } else {
                // Get latest sync status
                $latestSync = SyncLog::latest()->first();

                if (!$latestSync) {
                    return response()->json([
                        'success' => true,
                        'data' => null,
                        'message' => 'No sync logs found'
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $latestSync->id,
                        'sync_type' => $latestSync->sync_type,
                        'status' => $latestSync->status,
                        'started_at' => $latestSync->started_at,
                        'completed_at' => $latestSync->completed_at,
                        'total_records' => $latestSync->total_records,
                        'success_records' => $latestSync->success_records,
                        'failed_records' => $latestSync->failed_records,
                        'error_message' => $latestSync->error_message,
                        'duration' => $latestSync->duration,
                        'success_rate' => $latestSync->success_rate
                    ]
                ]);
            }

        } catch (Exception $e) {
            Log::error('Get sync status failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync logs with pagination
     */
    public function getSyncLogs(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $status = $request->query('status');
            $syncType = $request->query('sync_type');

            $query = SyncLog::query();

            if ($status) {
                $query->where('status', $status);
            }

            if ($syncType) {
                $query->where('sync_type', $syncType);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get sync logs failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync statistics
     */
    public function getSyncStatistics(Request $request)
    {
        try {
            $days = $request->query('days', 7);
            $startDate = now()->subDays($days);

            $stats = SyncLog::where('created_at', '>=', $startDate)
                           ->selectRaw('
                               COUNT(*) as total_syncs,
                               SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_syncs,
                               SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_syncs,
                               SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as running_syncs,
                               AVG(CASE WHEN status = "completed" THEN total_records ELSE NULL END) as avg_records,
                               AVG(CASE WHEN status = "completed" THEN TIMESTAMPDIFF(SECOND, started_at, completed_at) ELSE NULL END) as avg_duration
                           ')
                           ->first();

            $recentLogs = SyncLog::where('created_at', '>=', $startDate)
                                ->orderBy('created_at', 'desc')
                                ->limit(5)
                                ->get(['id', 'sync_type', 'status', 'started_at', 'completed_at', 'total_records', 'success_records', 'failed_records']);

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'recent_logs' => $recentLogs
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get sync statistics failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel running sync
     */
    public function cancelSync(Request $request)
    {
        try {
            $runningSync = SyncLog::where('status', 'running')->first();

            if (!$runningSync) {
                return response()->json([
                    'success' => false,
                    'message' => 'No running sync found'
                ], 404);
            }

            $runningSync->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'error_message' => 'Sync cancelled by user'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sync cancelled successfully',
                'sync_log_id' => $runningSync->id
            ]);

        } catch (Exception $e) {
            Log::error('Cancel sync failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel sync: ' . $e->getMessage()
            ], 500);
        }
    }
}
