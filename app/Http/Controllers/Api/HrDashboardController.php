<?php

namespace App\Http\Controllers\Api;

use App\Models\HrApiToken;
use App\Models\AttendanceByPeriod;
use App\Models\EmployeeMaster;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HrDashboardController extends ApiController
{
    private const BASE_URL = 'https://dev.greatdayhr.com/api';
    private const ACCESS_KEY = 'd2870a90-2aab-43a0-8668-27032f9b5f4c';
    private const ACCESS_SECRET = '$2a$10$MYl6gStqw3O1SRHDxxMZZO05fduYSY4zy3Aa1nQcyEZpgN/lsH.N2';

    /**
     * Login to HR API and store tokens
     * POST /api/dashboard/hr/login
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $response = Http::post(self::BASE_URL . '/auth/login', [
                'accessKey' => self::ACCESS_KEY,
                'accessSecret' => self::ACCESS_SECRET,
            ]);

            if (!$response->successful()) {
                return $this->sendError(
                    'Failed to login to HR API',
                    ['error' => $response->body()],
                    500
                );
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

            // Convert timestamp to datetime if needed
            if (is_numeric($createdAt)) {
                $createdAtFormatted = date('Y-m-d H:i:s', $createdAt);
            } else {
                $createdAtFormatted = $createdAt;
            }
            if (is_numeric($expiredAt)) {
                $expiredAtFormatted = date('Y-m-d H:i:s', $expiredAt);
            } else {
                $expiredAtFormatted = $expiredAt;
            }

            // Store or update token
            HrApiToken::updateOrCreate(
                ['id' => 1], // Single record
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_created_at' => $createdAtFormatted,
                    'expired_at' => $expiredAtFormatted,
                ]
            );

            return $this->sendResponse([
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'createdAt' => $createdAtFormatted,
                'expiredAt' => $expiredAtFormatted,
            ], 'Login successful');
        } catch (\Exception $e) {
            Log::error('HR API Login Error: ' . $e->getMessage());
            return $this->sendError('Login failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Refresh HR API token
     * POST /api/dashboard/hr/refresh
     */
    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $token = HrApiToken::getLatest();

            if (!$token || !$token->refresh_token) {
                // If no token exists, try to login first
                return $this->login($request);
            }

            $response = Http::post(self::BASE_URL . '/auth/refresh', [
                'refreshToken' => $token->refresh_token,
            ]);

            if (!$response->successful()) {
                // If refresh fails, try to login again
                Log::warning('HR API Token refresh failed, attempting login');
                return $this->login($request);
            }

            $data = $response->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

            // Convert timestamp to datetime if needed
            if (is_numeric($createdAt)) {
                $createdAtFormatted = date('Y-m-d H:i:s', $createdAt);
            } else {
                $createdAtFormatted = $createdAt;
            }
            if (is_numeric($expiredAt)) {
                $expiredAtFormatted = date('Y-m-d H:i:s', $expiredAt);
            } else {
                $expiredAtFormatted = $expiredAt;
            }

            // Update token
            $token->update([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_created_at' => $createdAtFormatted,
                'expired_at' => $expiredAtFormatted,
            ]);

            return $this->sendResponse([
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'createdAt' => $createdAtFormatted,
                'expiredAt' => $expiredAtFormatted,
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            Log::error('HR API Token Refresh Error: ' . $e->getMessage());
            return $this->sendError('Token refresh failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get access token, refresh if needed
     */
    private function getAccessToken(): ?string
    {
        $token = HrApiToken::getLatest();

        if (!$token) {
            // Try to login
            $loginResponse = Http::post(self::BASE_URL . '/auth/login', [
                'accessKey' => self::ACCESS_KEY,
                'accessSecret' => self::ACCESS_SECRET,
            ]);

            if (!$loginResponse->successful()) {
                Log::error('HR API Login failed in getAccessToken: HTTP ' . $loginResponse->status() . ' - ' . $loginResponse->body());
                return null;
            }

            $data = $loginResponse->json();

            // Handle both camelCase and snake_case response formats
            $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
            $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
            $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
            $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

            // Validate response structure
            if (!$accessToken || !$refreshToken) {
                Log::error('HR API Invalid response structure in getAccessToken', ['response' => $data]);
                return null;
            }

            // Convert timestamp to datetime if needed
            if (is_numeric($createdAt)) {
                $createdAt = date('Y-m-d H:i:s', $createdAt);
            }
            if (is_numeric($expiredAt)) {
                $expiredAt = date('Y-m-d H:i:s', $expiredAt);
            }

            HrApiToken::updateOrCreate(
                ['id' => 1],
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_created_at' => $createdAt,
                    'expired_at' => $expiredAt,
                ]
            );

            return $accessToken;
        }

        // Check if token is expired or will expire soon (within 1 hour)
        if ($token->expired_at && now()->addHour()->greaterThan($token->expired_at)) {
            // Try to refresh
            $refreshResponse = Http::post(self::BASE_URL . '/auth/refresh', [
                'refreshToken' => $token->refresh_token,
            ]);

            if ($refreshResponse->successful()) {
                $data = $refreshResponse->json();

                // Handle both camelCase and snake_case response formats
                $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
                $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
                $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
                $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

                // Validate response structure
                if (!$accessToken || !$refreshToken) {
                    Log::error('HR API Invalid response structure in refresh', ['response' => $data]);
                    // Fall through to login
                } else {
                    // Convert timestamp to datetime if needed
                    if (is_numeric($createdAt)) {
                        $createdAt = date('Y-m-d H:i:s', $createdAt);
                    }
                    if (is_numeric($expiredAt)) {
                        $expiredAt = date('Y-m-d H:i:s', $expiredAt);
                    }

                    $token->update([
                        'access_token' => $accessToken,
                        'refresh_token' => $refreshToken,
                        'token_created_at' => $createdAt,
                        'expired_at' => $expiredAt,
                    ]);
                    return $accessToken;
                }
            }

            // Refresh failed, try login
            $loginResponse = Http::post(self::BASE_URL . '/auth/login', [
                'accessKey' => self::ACCESS_KEY,
                'accessSecret' => self::ACCESS_SECRET,
            ]);

            if ($loginResponse->successful()) {
                $data = $loginResponse->json();

                // Handle both camelCase and snake_case response formats
                $accessToken = $data['accessToken'] ?? $data['access_token'] ?? null;
                $refreshToken = $data['refreshToken'] ?? $data['refresh_token'] ?? null;
                $createdAt = $data['createdAt'] ?? $data['created_at'] ?? null;
                $expiredAt = $data['expiredAt'] ?? $data['expired_at'] ?? null;

                // Validate response structure
                if (!$accessToken || !$refreshToken) {
                    Log::error('HR API Invalid response structure in login fallback', ['response' => $data]);
                    return null;
                }

                // Convert timestamp to datetime if needed
                if (is_numeric($createdAt)) {
                    $createdAt = date('Y-m-d H:i:s', $createdAt);
                }
                if (is_numeric($expiredAt)) {
                    $expiredAt = date('Y-m-d H:i:s', $expiredAt);
                }

                $token->update([
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_created_at' => $createdAt,
                    'expired_at' => $expiredAt,
                ]);
                return $accessToken;
            } else {
                Log::error('HR API Login fallback failed: HTTP ' . $loginResponse->status() . ' - ' . $loginResponse->body());
            }
        }

        return $token->access_token;
    }

    /**
     * Helper method to fetch all data from HR API with pagination
     */
    private function fetchAllDataFromApi(string $endpoint, array $body = [], string $method = 'post'): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Failed to obtain access token');
        }

        $page = 0; // Start from 0 as per API documentation
        $limit = 100;
        $allData = [];
        $totalPages = 1;
        $retryCount = 0;
        $maxRetries = 2;

        do {
            $requestBody = array_merge($body, [
                'page' => $page,
                'limit' => $limit,
            ]);

            if ($method === 'post') {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->post(self::BASE_URL . $endpoint, $requestBody);
            } else {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get(self::BASE_URL . $endpoint, $requestBody);
            }

            if (!$response->successful()) {
                if ($response->status() === 401 && $retryCount < $maxRetries) {
                    $retryCount++;
                    // Force refresh token
                    $token = HrApiToken::getLatest();
                    if ($token && $token->refresh_token) {
                        $refreshResponse = Http::post(self::BASE_URL . '/auth/refresh', [
                            'refreshToken' => $token->refresh_token,
                        ]);

                        if ($refreshResponse->successful()) {
                            $refreshData = $refreshResponse->json();
                            $accessToken = $refreshData['accessToken'] ?? $refreshData['access_token'] ?? null;

                            if ($accessToken) {
                                $createdAt = $refreshData['createdAt'] ?? $refreshData['created_at'] ?? null;
                                $expiredAt = $refreshData['expiredAt'] ?? $refreshData['expired_at'] ?? null;

                                if (is_numeric($createdAt)) {
                                    $createdAt = date('Y-m-d H:i:s', $createdAt);
                                }
                                if (is_numeric($expiredAt)) {
                                    $expiredAt = date('Y-m-d H:i:s', $expiredAt);
                                }

                                $token->update([
                                    'access_token' => $accessToken,
                                    'refresh_token' => $refreshData['refreshToken'] ?? $refreshData['refresh_token'] ?? $token->refresh_token,
                                    'token_created_at' => $createdAt,
                                    'expired_at' => $expiredAt,
                                ]);
                                continue; // Retry with new token
                            }
                        }
                    }
                    throw new \Exception('Failed to refresh token');
                }
                throw new \Exception('API request failed: ' . $response->body());
            }

            $data = $response->json();
            $totalPages = $data['totalPage'] ?? $data['total_page'] ?? 1;

            if (isset($data['data']) && is_array($data['data'])) {
                $allData = array_merge($allData, $data['data']);
            }

            $page++;
        } while ($page < $totalPages);

        return $allData;
    }

    /**
     * Count active employees (end_date is null)
     * GET /api/dashboard/hr/active-employees-count
     * Data source: Local database (employee_master table)
     */
    public function activeEmployeesCount(Request $request): JsonResponse
    {
        try {
            // Count active employees directly from database
            $totalActive = EmployeeMaster::whereNull('end_date')->count();

            return $this->sendResponse([
                'total_active_employees' => $totalActive,
            ], 'Active employees count retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Active Employees Count Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to count active employees: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Debug endpoint to test HR API connection
     * GET /api/dashboard/hr/debug
     */
    public function debug(Request $request): JsonResponse
    {
        try {
            $token = HrApiToken::getLatest();
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                return $this->sendError('Failed to obtain access token', [], 500);
            }

            // Decode JWT token to see its contents
            $tokenParts = explode('.', $accessToken);
            $tokenPayload = null;
            if (count($tokenParts) === 3) {
                $decoded = base64_decode($tokenParts[1]);
                $tokenPayload = json_decode($decoded, true);
            }

            // Test different authorization formats
            $authFormats = [
                'Bearer' => 'Bearer ' . $accessToken,
                'Token' => 'Token ' . $accessToken,
            ];

            // Test different endpoints
            $endpoints = ['/users', '/employee', '/employees', '/user'];
            $results = [];

            foreach ($endpoints as $endpoint) {
                $endpointResults = [];

                // Try different auth formats
                foreach ($authFormats as $formatName => $authHeader) {
                    // Use POST method for /employees endpoint
                    $method = ($endpoint === '/employees') ? 'post' : 'get';
                    $requestData = ($endpoint === '/employees')
                        ? ['page' => 1, 'limit' => 10]
                        : ['page' => 1, 'limit' => 10];

                    if ($method === 'post') {
                        $response = Http::withHeaders([
                            'Authorization' => $authHeader,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])->timeout(10)->post(self::BASE_URL . $endpoint, $requestData);
                    } else {
                        $response = Http::withHeaders([
                            'Authorization' => $authHeader,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])->timeout(10)->get(self::BASE_URL . $endpoint, $requestData);
                    }

                    $endpointResults[$formatName] = [
                        'status' => $response->status(),
                        'success' => $response->successful(),
                        'body_preview' => substr($response->body(), 0, 300),
                        'json' => $response->successful() ? $response->json() : null,
                    ];

                    // If successful, break and use this format
                    if ($response->successful()) {
                        break;
                    }
                }

                $results[$endpoint] = $endpointResults;
            }

            // Test POST with /employees endpoint (correct method)
            $postEmployeesTest = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(10)->post(self::BASE_URL . '/employees', [
                'page' => 1,
                'limit' => 10,
            ]);

            return $this->sendResponse([
                'token_info' => [
                    'has_token' => $token ? true : false,
                    'token_preview' => $token ? substr($token->access_token, 0, 50) . '...' : null,
                    'expired_at' => $token ? $token->expired_at : null,
                    'is_expired' => $token && $token->expired_at ? now()->greaterThan($token->expired_at) : null,
                    'token_length' => $token ? strlen($token->access_token) : 0,
                    'token_payload' => $tokenPayload,
                ],
                'endpoint_tests' => $results,
                'post_employees_test' => [
                    'status' => $postEmployeesTest->status(),
                    'success' => $postEmployeesTest->successful(),
                    'body_preview' => substr($postEmployeesTest->body(), 0, 500),
                    'json_preview' => $postEmployeesTest->successful() ? $postEmployeesTest->json() : null,
                ],
                'note' => 'If all return 401 "Unauthorized Privilege", the token may not have permission to access these endpoints. This is NOT an authorization format issue - the format "Bearer <JWT>" is correct. The issue is that the token does not have the required privileges/permissions. Please contact GreatDayHR support to grant the necessary permissions to your access key.',
                'recommendation' => [
                    '1' => 'Contact GreatDayHR support to verify that your access key has permission to access employee/user data endpoints',
                    '2' => 'Check the API documentation at https://dev.greatdayhr.com/api/docs/ for the correct endpoint and required permissions',
                    '3' => 'Verify if there are specific scopes or permissions that need to be requested during login',
                    '4' => 'Check if there is a different endpoint or API version that should be used',
                ],
            ], 'Debug information retrieved');
        } catch (\Exception $e) {
            Log::error('HR API Debug Error: ' . $e->getMessage());
            return $this->sendError('Debug failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Chart 1: Employment Status Comparison
     * GET /api/dashboard/hr/employment-status-comparison
     *
     * Returns count of ACTIVE employees by employment status: PERMANENT, CONTRACT, OUTSOURCING, PROBATION
     * Only includes employees with end_date = null (active employees)
     * Data source: Local database (employee_master table)
     */
    public function employmentStatusComparison(Request $request): JsonResponse
    {
        try {
            // Get employment status counts for active employees (end_date is null)
            $statusCounts = EmployeeMaster::whereNull('end_date')
                ->groupBy('employment_status')
                ->selectRaw('employment_status as status, count(*) as count')
                ->get();

            $data = [];
            $summary = [];
            $total = 0;

            foreach ($statusCounts as $item) {
                $data[] = [
                    'status' => $item->status,
                    'count' => $item->count,
                ];
                $summary[$item->status] = $item->count;
                $total += $item->count;
            }

            return $this->sendResponse([
                'data' => $data,
                'total' => $total,
                'summary' => $summary,
            ], 'Employment status comparison retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Employment Status Comparison Error: ' . $e->getMessage());
            return $this->sendError('Failed to get employment status comparison: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Chart 2: Gender Distribution
     * GET /api/dashboard/hr/gender-distribution
     *
     * Returns count of ACTIVE male and female employees from attendance status API
     * Only includes employees with endDate = null (active employees)
     */
    public function genderDistribution(Request $request): JsonResponse
    {
        try {
            // Request attendances across a wide date range so the endpoint
            // returns as many historical attendance records as possible (these
            // records include `gender`). Using a very early start date and
            // today's date as endDate to cover full history.
            $startDate = '1900-01-01';
            $endDate = date('Y-m-d');
            $params = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];

            $allData = $this->fetchAllDataFromApi('/attendances/status', $params, 'get');

            $genderCount = [
                'Male' => 0,
                'Female' => 0,
            ];

            // Use unique empNo to avoid counting same employee multiple times
            $processedEmployees = [];

            foreach ($allData as $record) {
                $empNo = $record['empNo'] ?? null;
                $gender = $record['gender'] ?? null;

                // Filter only active employees (endDate = null)
                $endDate = $record['empEndDate'] ?? $record['endDate'] ?? $record['end_date'] ?? null;

                // Skip if employee is not active (has endDate)
                if ($endDate !== null) {
                    continue;
                }

                if ($empNo && $gender && !isset($processedEmployees[$empNo])) {
                    if (isset($genderCount[$gender])) {
                        $genderCount[$gender]++;
                        $processedEmployees[$empNo] = true;
                    }
                }
            }

            $data = [
                [
                    'gender' => 'Male',
                    'count' => $genderCount['Male'],
                ],
                [
                    'gender' => 'Female',
                    'count' => $genderCount['Female'],
                ],
            ];

            return $this->sendResponse([
                'data' => $data,
                'total' => array_sum($genderCount),
                'summary' => $genderCount,
            ], 'Gender distribution retrieved successfully');
        } catch (\Exception $e) {
            Log::error('HR API Gender Distribution Error: ' . $e->getMessage());
            return $this->sendError('Failed to get gender distribution: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Chart 3: Present Attendance by Shift
     * GET /api/dashboard/hr/present-attendance-by-shift
     *
     * Returns count of Present Attendance (attend_code: "PRS") grouped by shiftdaily_code
     * Supports filtering: period (daily/monthly), startDate, endDate
     * Data source: Local database (attendance_by_period table)
     *
     * Query Parameters:
     * - period: 'daily' or 'monthly' (default: 'daily')
     * - startDate: Start date (format: YYYY-MM-DD)
     * - endDate: End date (format: YYYY-MM-DD)
     */
    public function presentAttendanceByShift(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'daily'); // daily or monthly
            $startDate = $request->get('startDate');
            $endDate = $request->get('endDate');

            // Default to current month if not specified
            if (!$startDate || !$endDate) {
                $startDate = now()->startOfMonth()->format('Y-m-d');
                $endDate = now()->endOfMonth()->format('Y-m-d');
            }

            // Fetch PRS (Present) attendance data from local database
            $prsData = AttendanceByPeriod::where('attend_code', 'PRS')
                ->whereBetween(DB::raw('DATE(shiftstarttime)'), [$startDate, $endDate])
                ->get();

            // Get all unique shift codes from the data
            $allShiftCodes = $prsData->pluck('shiftdaily_code')->unique()->values()->toArray();

            // Generate all periods in the range
            $allPeriods = [];
            if ($period === 'daily') {
                $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
                $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);
                $currentDate = $startDateObj->copy();
                while ($currentDate->lte($endDateObj)) {
                    $allPeriods[] = $currentDate->format('Y-m-d');
                    $currentDate->addDay();
                }
            } else {
                $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
                $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);
                $currentDate = $startDateObj->copy()->startOfMonth();
                while ($currentDate->lte($endDateObj)) {
                    $allPeriods[] = $currentDate->format('Y-m');
                    $currentDate->addMonth();
                }
            }

            // Group by shiftdaily_code and period
            $groupedData = [];

            foreach ($prsData as $item) {
                $shiftCode = $item->shiftdaily_code ?? 'UNKNOWN';
                $shiftStartTime = $item->shiftstarttime;

                if ($period === 'daily') {
                    // Group by date (YYYY-MM-DD)
                    $dateKey = $shiftStartTime ? $shiftStartTime->format('Y-m-d') : 'UNKNOWN';
                    $groupKey = $shiftCode . '|' . $dateKey;
                } else {
                    // Group by month (YYYY-MM)
                    $monthKey = $shiftStartTime ? $shiftStartTime->format('Y-m') : 'UNKNOWN';
                    $groupKey = $shiftCode . '|' . $monthKey;
                }

                if (!isset($groupedData[$groupKey])) {
                    $groupedData[$groupKey] = [
                        'shiftdailyCode' => $shiftCode,
                        'period' => $period === 'daily'
                            ? ($shiftStartTime ? $shiftStartTime->format('Y-m-d') : 'UNKNOWN')
                            : ($shiftStartTime ? $shiftStartTime->format('Y-m') : 'UNKNOWN'),
                        'count' => 0,
                    ];
                }
                $groupedData[$groupKey]['count']++;
            }

            // Add missing periods with count 0
            foreach ($allShiftCodes as $shiftCode) {
                foreach ($allPeriods as $periodValue) {
                    $groupKey = $shiftCode . '|' . $periodValue;
                    if (!isset($groupedData[$groupKey])) {
                        $groupedData[$groupKey] = [
                            'shiftdailyCode' => $shiftCode,
                            'period' => $periodValue,
                            'count' => 0,
                        ];
                    }
                }
            }

            // Convert to array and sort
            $data = array_values($groupedData);
            usort($data, function ($a, $b) {
                if ($a['shiftdailyCode'] === $b['shiftdailyCode']) {
                    return strcmp($a['period'], $b['period']);
                }
                return strcmp($a['shiftdailyCode'], $b['shiftdailyCode']);
            });

            return $this->sendResponse([
                'data' => $data,
                'total' => count($prsData),
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'filter_metadata' => [
                    'period' => $period,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
            ], 'Present attendance by shift retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Present Attendance By Shift Error: ' . $e->getMessage());
            return $this->sendError('Failed to get present attendance by shift: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Overtime Per Day
     * GET /api/dashboard/hr/overtime-per-day
     *
     * Returns total overtime per day in hours (converted from minutes)
     * Supports filtering: month, year OR startDate, endDate
     *
     * Query Parameters:
     * - month: Month (1-12, default: current month)
     * - year: Year (default: current year)
     * - startDate: Start date (format: YYYY-MM-DD) - overrides month/year if provided
     * - endDate: End date (format: YYYY-MM-DD) - overrides month/year if provided
     */
    public function overtimePerDay(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('startDate');
            $endDate = $request->get('endDate');

            // If startDate and endDate are not provided, use month and year
            if (!$startDate || !$endDate) {
                $month = $request->get('month', now()->month);
                $year = $request->get('year', now()->year);

                // Validate month
                if ($month < 1 || $month > 12) {
                    $month = now()->month;
                }

                // Validate year
                if ($year < 2000 || $year > 2100) {
                    $year = now()->year;
                }

                // Build date range for the selected month
                $startDateObj = Carbon::create($year, $month, 1)->startOfMonth();
                $endDateObj = $startDateObj->copy()->endOfMonth();

                $startDate = $startDateObj->format('Y-m-d');
                $endDate = $endDateObj->format('Y-m-d');
            }

            // Query: Get total overtime per day
            $overtimeData = AttendanceByPeriod::query()
                ->select(
                    DB::raw("DATE(attendance_by_period.starttime) as date"),
                    DB::raw('SUM(COALESCE(attendance_by_period.total_ot, 0)) as total_ot_minutes')
                )
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('attendance_by_period.starttime', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ])
                    ->orWhereBetween('attendance_by_period.shiftstarttime', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59'
                    ]);
                })
                ->groupBy(DB::raw("DATE(attendance_by_period.starttime)"))
                ->orderBy(DB::raw("DATE(attendance_by_period.starttime)"), 'asc')
                ->get();

            // Create a map of existing dates with overtime data
            $overtimeMap = [];
            foreach ($overtimeData as $item) {
                $overtimeMap[$item->date] = (float)$item->total_ot_minutes;
            }

            // Generate all dates in the range
            $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
            $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);
            $allDates = [];
            $currentDate = $startDateObj->copy();

            while ($currentDate->lte($endDateObj)) {
                $dateStr = $currentDate->format('Y-m-d');
                $totalMinutes = $overtimeMap[$dateStr] ?? 0;
                $hours = intdiv((int)$totalMinutes, 60);
                $minutes = (int)$totalMinutes % 60;

                $allDates[] = [
                    'date' => $dateStr,
                    'total_ot_minutes' => $totalMinutes,
                    'total_ot_hours' => $hours,
                    'total_ot_minutes_remainder' => $minutes,
                    'total_ot_formatted' => $hours . ' jam ' . ($minutes > 0 ? $minutes . ' menit' : ''),
                ];

                $currentDate->addDay();
            }

            // Calculate total overtime for the period
            $totalMinutes = array_sum(array_values($overtimeMap));
            $totalHours = intdiv((int)$totalMinutes, 60);
            $totalMinutesRemainder = (int)$totalMinutes % 60;

            // Get month and year for metadata
            $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
            $month = $startDateObj->month;
            $year = $startDateObj->year;

            return $this->sendResponse([
                'data' => $allDates,
                'total_records' => count($allDates),
                'total_ot_minutes' => $totalMinutes,
                'total_ot_hours' => $totalHours,
                'total_ot_minutes_remainder' => $totalMinutesRemainder,
                'total_ot_formatted' => $totalHours . ' jam ' . ($totalMinutesRemainder > 0 ? $totalMinutesRemainder . ' menit' : ''),
                'filter_metadata' => [
                    'month' => (int)$month,
                    'year' => (int)$year,
                    'month_name' => $startDateObj->format('F'),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ], 'Overtime per day retrieved successfully');
        } catch (\Exception $e) {
            Log::error('HR API Overtime Per Day Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get overtime per day: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Top 15 Employees by Overtime Index
     * GET /api/dashboard/hr/top-employees-overtime
     *
     * Query Parameters:
     * - month: Month (1-12, default: current month)
     * - year: Year (default: current year)
     */
    public function topEmployeesOvertime(Request $request): JsonResponse
    {
        try {
            // Get filter parameters
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Validate month
            if ($month < 1 || $month > 12) {
                $month = now()->month;
            }

            // Validate year
            if ($year < 2000 || $year > 2100) {
                $year = now()->year;
            }

            // Build date range for the selected month
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // If current month, don't go beyond today
            if ($startDate->isSameMonth(now())) {
                $endDate = now();
            }

            // Query: Get top 15 employees by total overtime (in minutes)
            // Use COALESCE to handle NULL values and sum only non-null values
            $topEmployees = AttendanceByPeriod::query()
                ->select(
                    'attendance_by_period.emp_id',
                    'employee_master.full_name',
                    'employee_master.emp_no',
                    'employee_master.costcenter_name',
                    'employee_master.dept_name_en',
                    DB::raw('SUM(COALESCE(attendance_by_period.total_ot, 0)) as total_overtime_minutes')
                )
                ->join('employee_master', 'attendance_by_period.emp_id', '=', 'employee_master.emp_id')
                ->where(function($query) {
                    $query->whereNotNull('attendance_by_period.total_ot')
                          ->orWhere('attendance_by_period.total_ot', '!=', 0);
                })
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('attendance_by_period.starttime', [
                        $startDate->format('Y-m-d 00:00:00'),
                        $endDate->format('Y-m-d 23:59:59')
                    ])
                    ->orWhereBetween('attendance_by_period.shiftstarttime', [
                        $startDate->format('Y-m-d 00:00:00'),
                        $endDate->format('Y-m-d 23:59:59')
                    ]);
                })
                ->groupBy(
                    'attendance_by_period.emp_id',
                    'employee_master.full_name',
                    'employee_master.emp_no',
                    'employee_master.costcenter_name',
                    'employee_master.dept_name_en'
                )
                ->havingRaw('SUM(COALESCE(attendance_by_period.total_ot, 0)) > 0')
                ->orderByDesc('total_overtime_minutes')
                ->limit(15)
                ->get();

            // Format response: convert minutes to hours
            $data = $topEmployees->map(function ($employee, $index) {
                $totalMinutes = (float)$employee->total_overtime_minutes;
                $hours = intdiv((int)$totalMinutes, 60);
                $minutes = (int)$totalMinutes % 60;

                return [
                    'rank' => $index + 1,
                    'emp_id' => $employee->emp_id,
                    'emp_no' => $employee->emp_no,
                    'full_name' => $employee->full_name,
                    'department' => $employee->dept_name_en,
                    'cost_center' => $employee->costcenter_name,
                    'total_overtime_index' => $hours,
                    'total_overtime_index_minutes' => $minutes,
                    'total_overtime_index_formatted' => $hours . ' jam ' . ($minutes > 0 ? $minutes . ' menit' : ''),
                ];
            });

            return $this->sendResponse([
                'data' => $data,
                'total' => $topEmployees->count(),
                'filter_metadata' => [
                    'month' => (int)$month,
                    'year' => (int)$year,
                    'month_name' => $startDate->format('F'),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
            ], 'Top 15 employees by overtime index retrieved successfully');
        } catch (\Exception $e) {
            Log::error('HR API Top Employees Overtime Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get top employees overtime: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Top 10 Departments by Overtime Index
     * GET /api/dashboard/hr/top-departments-overtime
     *
     * Query Parameters:
     * - month: Month (1-12, default: current month)
     * - year: Year (default: current year)
     */
    public function topDepartmentsOvertime(Request $request): JsonResponse
    {
        try {
            // Get filter parameters
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Validate month
            if ($month < 1 || $month > 12) {
                $month = now()->month;
            }

            // Validate year
            if ($year < 2000 || $year > 2100) {
                $year = now()->year;
            }

            // Build date range for the selected month
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // If current month, don't go beyond today
            if ($startDate->isSameMonth(now())) {
                $endDate = now();
            }

            // Query: Get top 10 departments by total overtime (in minutes)
            // Use COALESCE to handle NULL values and sum only non-null values
            $topDepartments = AttendanceByPeriod::query()
                ->select(
                    'employee_master.costcenter_name',
                    'employee_master.dept_name_en',
                    DB::raw('SUM(COALESCE(attendance_by_period.total_ot, 0)) as total_overtime_minutes'),
                    DB::raw('COUNT(DISTINCT attendance_by_period.emp_id) as total_employees')
                )
                ->join('employee_master', 'attendance_by_period.emp_id', '=', 'employee_master.emp_id')
                ->where(function($query) {
                    $query->whereNotNull('attendance_by_period.total_ot')
                          ->orWhere('attendance_by_period.total_ot', '!=', 0);
                })
                ->whereNotNull('employee_master.costcenter_name')
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('attendance_by_period.starttime', [
                        $startDate->format('Y-m-d 00:00:00'),
                        $endDate->format('Y-m-d 23:59:59')
                    ])
                    ->orWhereBetween('attendance_by_period.shiftstarttime', [
                        $startDate->format('Y-m-d 00:00:00'),
                        $endDate->format('Y-m-d 23:59:59')
                    ]);
                })
                ->groupBy(
                    'employee_master.costcenter_name',
                    'employee_master.dept_name_en'
                )
                ->havingRaw('SUM(COALESCE(attendance_by_period.total_ot, 0)) > 0')
                ->orderByDesc('total_overtime_minutes')
                ->limit(10)
                ->get();

            // Format response: convert minutes to hours
            $data = $topDepartments->map(function ($department, $index) {
                $totalMinutes = (float)$department->total_overtime_minutes;
                $hours = intdiv((int)$totalMinutes, 60);
                $minutes = (int)$totalMinutes % 60;

                return [
                    'rank' => $index + 1,
                    'department' => $department->dept_name_en,
                    'cost_center' => $department->costcenter_name,
                    'total_overtime_index' => $hours,
                    'total_overtime_index_minutes' => $minutes,
                    'total_overtime_index_formatted' => $hours . ' jam ' . ($minutes > 0 ? $minutes . ' menit' : ''),
                    'total_employees' => (int)$department->total_employees,
                ];
            });

            return $this->sendResponse([
                'data' => $data,
                'total' => $topDepartments->count(),
                'filter_metadata' => [
                    'month' => (int)$month,
                    'year' => (int)$year,
                    'month_name' => $startDate->format('F'),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
            ], 'Top 10 departments by overtime index retrieved successfully');
        } catch (\Exception $e) {
            Log::error('HR API Top Departments Overtime Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get top departments overtime: ' . $e->getMessage(), [], 500);
        }
    }
}
