<?php

namespace App\Http\Controllers\Api;

use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $logs = SyncLog::orderBy('created_at', 'desc')->get();
            return $this->sendResponse($logs, 'Synchronization logs retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving synchronization logs: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $log = SyncLog::find($id);
            
            if (is_null($log)) {
                return $this->sendError('Synchronization log not found.');
            }

            return $this->sendResponse($log, 'Synchronization log retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving synchronization log: ' . $e->getMessage());
        }
    }
}
