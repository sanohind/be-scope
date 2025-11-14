<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ApiController extends Controller
{
    /**
     * Success response method.
     *
     * @param mixed $result
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function sendResponse($result, $message = '', $code = 200)
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];

        return response()->json($response, $code);
    }

    /**
     * Error response method.
     *
     * @param string $error
     * @param array $errorMessages
     * @param int $code
     * @return JsonResponse
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Get date format based on period type for grouping
     *
     * @param string $period (daily, monthly, yearly)
     * @param string $dateField The date field name
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|null $query Query builder to detect connection
     * @return string SQL date format expression
     */
    protected function getDateFormatByPeriod($period, $dateField = 'date', $query = null)
    {
        // Detect database driver from query or use default
        if ($query) {
            $connectionName = $query->getConnection()->getName();
            $driver = config("database.connections.{$connectionName}.driver");
        } else {
            $driver = config('database.connections.' . config('database.default') . '.driver');
        }
        
        $isSqlServer = in_array($driver, ['sqlsrv']);
        
        switch ($period) {
            case 'daily':
                if ($isSqlServer) {
                    return "CAST($dateField AS DATE)";
                } else {
                    return "DATE($dateField)";
                }
            case 'yearly':
                if ($isSqlServer) {
                    return "CAST(YEAR($dateField) AS VARCHAR)";
                } else {
                    return "YEAR($dateField)";
                }
            case 'monthly':
            default:
                if ($isSqlServer) {
                    return "LEFT(CONVERT(VARCHAR(10), $dateField, 23), 7)";
                } else {
                    return "DATE_FORMAT($dateField, '%Y-%m')";
                }
        }
    }

    /**
     * Apply date range filter to query
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param string $dateField The date field to filter
     * @return void
     */
    protected function applyDateRangeFilter($query, Request $request, $dateField)
    {
        if ($request->has('date_from')) {
            $query->where($dateField, '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where($dateField, '<=', $request->date_to);
        }
    }

    /**
     * Get period metadata for response
     *
     * @param \Illuminate\Http\Request $request
     * @param string $dateField The date field used
     * @return array
     */
    protected function getPeriodMetadata(Request $request, $dateField = null)
    {
        $period = $request->get('period', $request->get('group_by', 'monthly'));

        // Validate period
        if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
            $period = 'monthly';
        }

        return [
            'period' => $period,
            'date_field' => $dateField,
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];
    }
}
