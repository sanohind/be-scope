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

    /**
     * Generate all periods in the range based on period type
     *
     * @param string $period (daily, monthly, yearly)
     * @param string|null $dateFrom Start date (Y-m-d format)
     * @param string|null $dateTo End date (Y-m-d format)
     * @return array Array of period strings
     */
    protected function generateAllPeriods(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $periods = [];

        if (!$dateFrom && !$dateTo) {
            return $periods;
        }

        $start = $dateFrom ? \Carbon\Carbon::parse($dateFrom) : \Carbon\Carbon::now()->startOfMonth();
        $end = $dateTo ? \Carbon\Carbon::parse($dateTo) : \Carbon\Carbon::now();

        if ($period === 'daily') {
            $current = $start->copy();
            while ($current->lte($end)) {
                $periods[] = $current->format('Y-m-d');
                $current->addDay();
            }
        } elseif ($period === 'monthly') {
            $current = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();
            while ($current->lte($endMonth)) {
                $periods[] = $current->format('Y-m');
                $current->addMonth();
            }
        } elseif ($period === 'yearly') {
            $currentYear = $start->year;
            $endYear = $end->year;
            while ($currentYear <= $endYear) {
                $periods[] = (string) $currentYear;
                $currentYear++;
            }
        }

        return $periods;
    }

    /**
     * Fill missing periods with zero values
     *
     * @param \Illuminate\Support\Collection|array $data Existing data
     * @param array $allPeriods All periods that should exist
     * @param string $periodKey The key name for period in data (default: 'period')
     * @param callable|null $zeroValueCallback Callback to create zero value entry
     * @return array Filled data array
     */
    protected function fillMissingPeriods($data, array $allPeriods, string $periodKey = 'period', ?callable $zeroValueCallback = null): array
    {
        if (empty($allPeriods)) {
            return is_array($data) ? $data : $data->values()->toArray();
        }

        // Convert data to keyed collection
        $dataByPeriod = collect($data)->mapWithKeys(function ($item) use ($periodKey) {
            $key = is_object($item) ? ($item->{$periodKey} ?? null) : ($item[$periodKey] ?? null);
            if ($key === null) {
                return [];
            }
            // Normalize key format
            $key = trim((string) $key);
            return [$key => $item];
        });

        // Fill missing periods
        $filledData = collect($allPeriods)->map(function ($periodValue) use ($dataByPeriod, $periodKey, $zeroValueCallback) {
            $periodKeyStr = trim((string) $periodValue);

            if ($dataByPeriod->has($periodKeyStr)) {
                $item = $dataByPeriod->get($periodKeyStr);
                // Ensure period key is normalized
                if (is_object($item)) {
                    $item->{$periodKey} = $periodKeyStr;
                } else {
                    $item[$periodKey] = $periodKeyStr;
                }
                return $item;
            } else {
                // Create zero value entry
                if ($zeroValueCallback) {
                    return $zeroValueCallback($periodValue);
                }

                // Default: create object with period and numeric fields as 0
                $zeroEntry = (object) [$periodKey => $periodKeyStr];
                return $zeroEntry;
            }
        })->values();

        return $filledData->toArray();
    }
}
