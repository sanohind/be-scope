<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SyncLog;
use App\Models\HrApiToken;
use App\Models\EmployeeMaster;
use App\Models\AttendanceByPeriod;
use Carbon\Carbon;
use Exception;

class SyncHrData extends Command
{
    protected $signature = 'sync:hr-data
                            {--manual : Manual sync flag}
                            {--month= : Month to sync (format: YYYY-MM, e.g., 2025-08). Only used with --manual}
                            {--init : Initialize sync (sync all data)}';
    protected $description = 'Sync HR API data to local database';

    private const BASE_URL = 'https://dev.greatdayhr.com/api';
    private const ACCESS_KEY = 'd2870a90-2aab-43a0-8668-27032f9b5f4c';
    private const ACCESS_SECRET = '$2a$10$MYl6gStqw3O1SRHDxxMZZO05fduYSY4zy3Aa1nQcyEZpgN/lsH.N2';

    private $isInitialization = false;
    private $syncMonth = null;
    private $dateFrom = null;
    private $dateTo = null;

    public function handle()
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '1024M');

        $isManual = $this->option('manual');
        $isInit = $this->option('init');
        $monthOption = $this->option('month');

        // Determine sync type
        if ($isInit) {
            $this->isInitialization = true;
            $syncType = 'initialization';
            $this->info("Starting initialization sync (all HR data)...");
        } else {
            $syncType = $isManual ? 'manual' : 'scheduled';

            // Determine date range
            if ($isManual && $monthOption) {
                // Manual sync with specific month
                try {
                    $this->syncMonth = Carbon::createFromFormat('Y-m', $monthOption);
                    $this->dateFrom = $this->syncMonth->copy()->startOfMonth();
                    $this->dateTo = $this->syncMonth->copy()->endOfMonth();
                    $this->info("Starting manual sync for month: {$monthOption}");
                } catch (Exception $e) {
                    $this->error("Invalid month format. Use YYYY-MM (e.g., 2025-08)");
                    return 1;
                }
            } else {
                // Scheduled sync - current month only
                $this->syncMonth = Carbon::now();
                $this->dateFrom = $this->syncMonth->copy()->startOfMonth();
                $this->dateTo = $this->syncMonth->copy()->endOfMonth();
                $this->info("Starting scheduled sync for current month: {$this->syncMonth->format('Y-m')}");
            }
        }

        // Create sync log BEFORE processing
        $syncLog = SyncLog::create([
            'sync_type' => $syncType,
            'status' => 'running',
            'started_at' => now(),
            'total_records' => 0,
            'success_records' => 0,
            'failed_records' => 0,
        ]);

        $this->info("Sync Log ID: {$syncLog->id}");

        try {
            $totalRecords = 0;
            $successRecords = 0;
            $failedRecords = 0;

            // Sync EmployeeMaster
            $this->info('Syncing EmployeeMaster...');
            $result = $this->syncEmployeeMaster();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync AttendanceByPeriod
            $this->info('Syncing AttendanceByPeriod...');
            $result = $this->syncAttendanceByPeriod();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Update sync log with final results
            $syncLog->status = 'completed';
            $syncLog->completed_at = now();
            $syncLog->total_records = $totalRecords;
            $syncLog->success_records = $successRecords;
            $syncLog->failed_records = $failedRecords;
            $syncLog->error_message = null;
            $syncLog->save();

            $this->info("Sync completed successfully!");
            $this->info("Sync Log ID: {$syncLog->id}");
            $this->info("Total records: {$totalRecords}");
            $this->info("Success: {$successRecords}");
            $this->info("Failed: {$failedRecords}");

        } catch (Exception $e) {
            // Update sync log on failure
            $syncLog->status = 'failed';
            $syncLog->completed_at = now();
            $syncLog->error_message = $e->getMessage();
            $syncLog->save();

            $this->error("Sync failed: " . $e->getMessage());
            $this->error("Sync Log ID: {$syncLog->id}");
            return 1;
        }

        return 0;
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
                $response = Http::timeout(300) // 5 minutes timeout
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])->post(self::BASE_URL . $endpoint, $requestBody);
            } else {
                $response = Http::timeout(300) // 5 minutes timeout
                    ->withHeaders([
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

            // Check if response has pagination info
            $totalPages = $data['totalPage'] ?? $data['total_page'] ?? null;

            // If no pagination info, endpoint likely returns all data in one response
            if ($totalPages === null) {
                // Endpoint might not use pagination (e.g., /attendances/byPeriod)
                if (isset($data['data']) && is_array($data['data'])) {
                    $allData = array_merge($allData, $data['data']);
                } elseif (is_array($data) && !isset($data['data'])) {
                    // Direct array response
                    $allData = array_merge($allData, $data);
                }
                // Break after first request if no pagination info
                break;
            } else {
                // Has pagination info, continue paginating
                if (isset($data['data']) && is_array($data['data'])) {
                    $allData = array_merge($allData, $data['data']);
                }
            }

            $page++;
        } while ($totalPages !== null && $page < $totalPages);

        return $allData;
    }

    /**
     * Helper method to check if two records are the same
     */
    private function recordsAreEqual($record1, $record2, $fields)
    {
        foreach ($fields as $field) {
            $val1 = $record1[$field] ?? null;
            $val2 = $record2[$field] ?? null;

            // Handle null comparison
            if ($val1 === null && $val2 === null) {
                continue;
            }
            if ($val1 === null || $val2 === null) {
                return false;
            }

            // Compare values (handle numeric comparison)
            if (is_numeric($val1) && is_numeric($val2)) {
                if (abs((float)$val1 - (float)$val2) > 0.01) {
                    return false;
                }
            } else {
                if ((string)$val1 !== (string)$val2) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Helper method to perform upsert (insert or update)
     */
    private function upsertRecord($table, $record, $uniqueKeys)
    {
        $query = DB::table($table);

        // Build where clause for unique keys
        foreach ($uniqueKeys as $key) {
            if (isset($record[$key])) {
                $query->where($key, $record[$key]);
            } else {
                // If unique key is null, treat as new record
                DB::table($table)->insert($record);
                return ['action' => 'inserted', 'record' => $record];
            }
        }

        $existing = $query->first();

        if ($existing) {
            // Check if data has changed
            $existingArray = (array)$existing;
            // Remove id from comparison if it exists
            unset($existingArray['id']);
            $allFields = array_keys($record);

            if ($this->recordsAreEqual($existingArray, $record, $allFields)) {
                // Data is the same, no update needed
                return ['action' => 'skipped', 'record' => $existing];
            } else {
                // Data has changed, update it - rebuild query for update
                $updateQuery = DB::table($table);
                foreach ($uniqueKeys as $key) {
                    $updateQuery->where($key, $record[$key]);
                }
                $updateQuery->update($record);
                return ['action' => 'updated', 'record' => $record];
            }
        } else {
            // New record, insert it
            DB::table($table)->insert($record);
            return ['action' => 'inserted', 'record' => $record];
        }
    }

    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake($camelCase)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camelCase));
    }

    /**
     * Convert API date string to database format
     */
    private function convertDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Handle ISO 8601 format with timezone
            $date = Carbon::parse($dateString);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Convert API datetime string to database format
     */
    private function convertDateTime($dateTimeString)
    {
        if (empty($dateTimeString)) {
            return null;
        }

        try {
            // Handle ISO 8601 format with timezone
            $dateTime = Carbon::parse($dateTimeString);
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Sync EmployeeMaster from HR API
     */
    private function syncEmployeeMaster()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;

            // EmployeeMaster unique key: emp_id
            $uniqueKeys = ['emp_id'];

            // Fetch all employees from API
            $this->info('Fetching employees from HR API...');
            $allEmployees = $this->fetchAllDataFromApi('/employees', [], 'post');

            $this->info('Processing ' . count($allEmployees) . ' employee records...');

            // Process in chunks to avoid memory issues
            $chunkSize = 500;
            $chunks = array_chunk($allEmployees, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $employee) {
                    try {
                        // Map API fields (camelCase) to database fields (snake_case)
                        $data = [
                            'user_id' => $employee['userId'] ?? null,
                            'first_name' => $employee['firstName'] ?? null,
                            'middle_name' => $employee['middleName'] ?? null,
                            'last_name' => $employee['lastName'] ?? null,
                            'user_name' => $employee['userName'] ?? null,
                            'company_name' => $employee['companyName'] ?? null,
                            'full_name' => $employee['fullName'] ?? null,
                            'emp_id' => $employee['empId'] ?? null,
                            'emp_no' => $employee['empNo'] ?? null,
                            'position_id' => $employee['positionId'] ?? null,
                            'pos_code' => $employee['posCode'] ?? null,
                            'pos_name_en' => $employee['posNameEn'] ?? null,
                            'employment_status_code' => $employee['employmentStatusCode'] ?? null,
                            'employment_status' => $employee['employmentStatus'] ?? null,
                            'email' => $employee['email'] ?? null,
                            'company_id' => $employee['companyId'] ?? null,
                            'spv_parent' => $employee['spvParent'] ?? null,
                            'spv_path' => $employee['spvPath'] ?? null,
                            'start_date' => $this->convertDate($employee['startDate'] ?? null),
                            'end_date' => $this->convertDate($employee['endDate'] ?? null),
                            'photo' => $employee['photo'] ?? null,
                            'address' => $employee['address'] ?? null,
                            'phone' => $employee['phone'] ?? null,
                            'job_status' => $employee['jobStatus'] ?? null,
                            'email_verified' => isset($employee['emailVerified']) ? (bool)$employee['emailVerified'] : false,
                            'worklocation_code' => $employee['worklocationCode'] ?? null,
                            'worklocation_name' => $employee['worklocationName'] ?? null,
                            'cost_code' => $employee['costCode'] ?? null,
                            'costcenter_name' => $employee['costcenterName'] ?? null,
                            'dept_code' => $employee['deptCode'] ?? null,
                            'dept_name_en' => $employee['deptNameEn'] ?? null,
                            'org_unit' => $employee['orgUnit'] ?? null,
                            'employment_start_date' => $this->convertDate($employee['employmentStartDate'] ?? null),
                            'employment_end_date' => $this->convertDate($employee['employmentEndDate'] ?? null),
                            'customfield1' => $employee['customfield1'] ?? null,
                            'customfield2' => $employee['customfield2'] ?? null,
                            'customfield3' => $employee['customfield3'] ?? null,
                            'customfield4' => $employee['customfield4'] ?? null,
                            'customfield5' => $employee['customfield5'] ?? null,
                            'customfield6' => $employee['customfield6'] ?? null,
                            'customfield7' => $employee['customfield7'] ?? null,
                            'customfield8' => $employee['customfield8'] ?? null,
                            'customfield9' => $employee['customfield9'] ?? null,
                            'customfield10' => $employee['customfield10'] ?? null,
                        ];

                        // Skip if emp_id is null
                        if (empty($data['emp_id'])) {
                            $this->warn("Skipping employee record with null emp_id");
                            continue;
                        }

                        $result = $this->upsertRecord('employee_master', $data, $uniqueKeys);

                        if ($result['action'] === 'updated') {
                            $updated++;
                        } elseif ($result['action'] === 'inserted') {
                            $inserted++;
                        } else {
                            $skipped++;
                        }

                        $success++;
                        $total++;
                    } catch (Exception $e) {
                        $failed++;
                        $total++;
                        $empId = $employee['empId'] ?? 'UNKNOWN';
                        $this->warn("Failed to sync EmployeeMaster record (emp_id: {$empId}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " EmployeeMaster records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            }

            $this->info("EmployeeMaster sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing EmployeeMaster: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    /**
     * Sync AttendanceByPeriod from HR API
     */
    private function syncAttendanceByPeriod()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;

            // AttendanceByPeriod unique key: attend_id
            $uniqueKeys = ['attend_id'];

            // Determine date range and sync strategy
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                // Manual or scheduled sync - use date range (single month)
                $this->info("Fetching attendance data from HR API (startDate: {$this->dateFrom->format('Y-m-d')}, endDate: {$this->dateTo->format('Y-m-d')})...");

                $allAttendances = $this->fetchAllDataFromApi(
                    '/attendances/byPeriod',
                    [
                        'startDate' => $this->dateFrom->format('Y-m-d'),
                        'endDate' => $this->dateTo->format('Y-m-d'),
                    ],
                    'get'
                );
            } else {
                // Initialization - sync month by month to avoid timeout
                $this->info("Initialization sync: Fetching attendance data month by month...");

                // Start from November 2022 to current month
                $currentMonth = Carbon::now();
                $startMonth = Carbon::create(2024, 11, 1);
                $totalMonths = $startMonth->diffInMonths($currentMonth) + 1;

                $allAttendances = [];
                $monthCount = 0;
                $tempMonth = $startMonth->copy();

                while ($tempMonth->lte($currentMonth)) {
                    $monthStart = $tempMonth->copy()->startOfMonth();
                    $monthEnd = $tempMonth->copy()->endOfMonth();

                    // Don't go beyond current date
                    if ($monthEnd->gt($currentMonth)) {
                        $monthEnd = $currentMonth->copy();
                    }

                    $monthCount++;
                    $this->info("Fetching attendance for month {$monthStart->format('Y-m')} ({$monthCount} of {$totalMonths})...");

                    try {
                        $monthData = $this->fetchAllDataFromApi(
                            '/attendances/byPeriod',
                            [
                                'startDate' => $monthStart->format('Y-m-d'),
                                'endDate' => $monthEnd->format('Y-m-d'),
                            ],
                            'get'
                        );

                        $allAttendances = array_merge($allAttendances, $monthData);
                        $this->info("Fetched " . count($monthData) . " records for {$monthStart->format('Y-m')} (Total so far: " . count($allAttendances) . ")");

                        // Small delay to avoid overwhelming the API
                        usleep(500000); // 0.5 second delay
                    } catch (Exception $e) {
                        $this->warn("Failed to fetch attendance for {$monthStart->format('Y-m')}: " . $e->getMessage());
                        // Continue with next month
                    }

                    $tempMonth->addMonth();
                }
            }

            $this->info('Processing ' . count($allAttendances) . ' attendance records...');

            // Process in chunks to avoid memory issues
            $chunkSize = 500;
            $chunks = array_chunk($allAttendances, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $attendance) {
                    try {
                        // Map API fields (camelCase) to database fields (snake_case)
                        $data = [
                            'attend_id' => $attendance['attendId'] ?? null,
                            'emp_id' => $attendance['empId'] ?? null,
                            'shiftdaily_code' => $attendance['shiftdailyCode'] ?? null,
                            'company_id' => $attendance['companyId'] ?? null,
                            'shiftstarttime' => $this->convertDateTime($attendance['shiftstarttime'] ?? null),
                            'shiftendtime' => $this->convertDateTime($attendance['shiftendtime'] ?? null),
                            'attend_code' => $attendance['attendCode'] ?? null,
                            'starttime' => $this->convertDateTime($attendance['starttime'] ?? null),
                            'endtime' => $this->convertDateTime($attendance['endtime'] ?? null),
                            'actual_in' => $attendance['actualIn'] ?? null,
                            'actual_out' => $attendance['actualOut'] ?? null,
                            'daytype' => $attendance['daytype'] ?? null,
                            'ip_starttime' => $attendance['ipStarttime'] ?? null,
                            'ip_endtime' => $attendance['ipEndtime'] ?? null,
                            'remark' => $attendance['remark'] ?? null,
                            'default_shift' => $attendance['defaultShift'] ?? null,
                            'total_ot' => $attendance['totalOt'] ?? null,
                            'total_otindex' => $attendance['totalOtindex'] ?? null,
                            'overtime_code' => $attendance['overtimeCode'] ?? null,
                            'flexibleshift' => $attendance['flexibleshift'] ?? null,
                            'auto_ovt' => isset($attendance['autoOvt']) ? (bool)$attendance['autoOvt'] : null,
                            'actualworkmnt' => $attendance['actualworkmnt'] ?? null,
                            'actual_lti' => $attendance['actualLti'] ?? null,
                            'actual_eao' => $attendance['actualEao'] ?? null,
                            'geolocation' => $attendance['geolocation'] ?? null,
                            'geoloc_start' => $attendance['geolocStart'] ?? null,
                            'geoloc_end' => $attendance['geolocEnd'] ?? null,
                            'photo_start' => $attendance['photoStart'] ?? null,
                            'photo_end' => $attendance['photoEnd'] ?? null,
                            'emp_no' => $attendance['empNo'] ?? null,
                            'spv_no' => $attendance['spvNo'] ?? null,
                            'spv_id' => $attendance['spvId'] ?? null,
                            'pos_name_en' => $attendance['posNameEn'] ?? null,
                            'pos_name_id' => $attendance['posNameId'] ?? null,
                            'pos_name_my' => $attendance['posNameMy'] ?? null,
                            'pos_name_th' => $attendance['posNameTh'] ?? null,
                        ];

                        // Skip if attend_id is null
                        if (empty($data['attend_id'])) {
                            $this->warn("Skipping attendance record with null attend_id");
                            continue;
                        }

                        $result = $this->upsertRecord('attendance_by_period', $data, $uniqueKeys);

                        if ($result['action'] === 'updated') {
                            $updated++;
                        } elseif ($result['action'] === 'inserted') {
                            $inserted++;
                        } else {
                            $skipped++;
                        }

                        $success++;
                        $total++;
                    } catch (Exception $e) {
                        $failed++;
                        $total++;
                        $attendId = $attendance['attendId'] ?? 'UNKNOWN';
                        $this->warn("Failed to sync AttendanceByPeriod record (attend_id: {$attendId}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " AttendanceByPeriod records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            }

            $this->info("AttendanceByPeriod sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing AttendanceByPeriod: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }
}

