<?php

namespace App\Http\Controllers\Api;

use App\Models\ProductionPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProductionPlanController extends ApiController
{
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return $this->sendError('File kosong', [], 422);
            }

            // Skip header rows (row 1-3), data starts from row 4
            // Remove rows 1, 2, 3
            unset($rows[1], $rows[2], $rows[3]);

            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            // Fixed column positions
            $PARTNO_COL = 'B';
            $DIVISI_COL = 'C';
            $YEAR_COL = 'D';
            $PERIOD_COL = 'E';
            $FIRST_DAY_COL = 'F'; // Column F = day 1

            foreach ($rows as $rowIndex => $row) {
                // Get partno, divisi, year, period from fixed columns
                $partno = $row[$PARTNO_COL] ?? null;
                $divisi = $row[$DIVISI_COL] ?? null;
                $year = $row[$YEAR_COL] ?? null;
                $period = $row[$PERIOD_COL] ?? null;

                // Skip if all are empty
                if (empty($partno) && empty($divisi) && empty($year) && empty($period)) {
                    continue;
                }

                // Validate and parse partno
                $partnoTrimmed = !empty($partno) ? trim((string) $partno) : null;
                if (empty($partnoTrimmed)) {
                    $errors[] = "Row {$rowIndex}: partno kosong";
                    $skipped++;
                    continue;
                }

                // Validate and parse divisi
                $divisiTrimmed = !empty($divisi) ? trim((string) $divisi) : null;
                if (empty($divisiTrimmed)) {
                    $errors[] = "Row {$rowIndex}: divisi kosong";
                    $skipped++;
                    continue;
                }

                // Validate and parse year
                $yearParsed = !empty($year) ? (int) $year : null;
                if ($yearParsed === null || $yearParsed < 2000 || $yearParsed > 2100) {
                    $errors[] = "Row {$rowIndex}: year tidak valid ({$year})";
                    $skipped++;
                    continue;
                }

                // Validate and parse period (month)
                $periodParsed = !empty($period) ? (int) $period : null;
                if ($periodParsed === null || $periodParsed < 1 || $periodParsed > 12) {
                    $errors[] = "Row {$rowIndex}: period tidak valid ({$period})";
                    $skipped++;
                    continue;
                }

                // Get number of days in this month
                $daysInMonth = Carbon::create($yearParsed, $periodParsed, 1)->daysInMonth;

                // Loop through each day (columns F, G, H, ... for day 1, 2, 3, ...)
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    // Calculate column letter for this day
                    // F = day 1, G = day 2, H = day 3, etc.
                    // Column F is the 6th column (A=1, B=2, C=3, D=4, E=5, F=6)
                    $columnIndex = 5 + $day; // F=6, G=7, H=8, etc.
                    $columnLetter = $this->getColumnLetter($columnIndex);

                    // Get qty_plan value from the cell
                    $qtyPlan = $row[$columnLetter] ?? null;

                    // Parse qty_plan as integer, default to 0 if empty
                    if ($qtyPlan === null || $qtyPlan === '') {
                        $qtyPlanParsed = 0;
                    } else {
                        $qtyPlanParsed = (int) $qtyPlan;
                    }

                    // Construct plan_date from year + period + day
                    try {
                        $planDate = Carbon::create($yearParsed, $periodParsed, $day)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $errors[] = "Row {$rowIndex}, Day {$day}: tanggal tidak valid ({$yearParsed}-{$periodParsed}-{$day})";
                        continue;
                    }

                    // Check if record with same partno, divisi and plan_date exists
                    $existingRecord = ProductionPlan::where('partno', $partnoTrimmed)
                        ->where('divisi', $divisiTrimmed)
                        ->where('plan_date', $planDate)
                        ->first();

                    if ($existingRecord) {
                        // Update existing record
                        $updates[] = [
                            'id' => $existingRecord->id,
                            'qty_plan' => $qtyPlanParsed,
                            'updated_at' => $now,
                        ];
                    } else {
                        // Insert new record
                        $inserts[] = [
                            'partno' => $partnoTrimmed,
                            'divisi' => $divisiTrimmed,
                            'qty_plan' => $qtyPlanParsed,
                            'plan_date' => $planDate,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            if (empty($inserts) && empty($updates)) {
                $errorMessage = 'Tidak ada data yang diproses';
                if (!empty($errors)) {
                    $errorMessage .= '. Errors: ' . implode('; ', array_slice($errors, 0, 5));
                }
                return $this->sendError($errorMessage, ['errors' => $errors], 422);
            }

            DB::transaction(function () use ($inserts, $updates, &$inserted, &$updated) {
                if (!empty($inserts)) {
                    $chunks = array_chunk($inserts, 1000);
                    foreach ($chunks as $chunk) {
                        ProductionPlan::insert($chunk);
                    }
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    ProductionPlan::where('id', $id)->update($updateData);
                    $updated++;
                }
            });

            $response = [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
            ];

            if (!empty($errors)) {
                $response['errors'] = array_slice($errors, 0, 10); // Limit to first 10 errors
            }

            return $this->sendResponse($response, 'Import berhasil');
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memproses file: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Convert column index to Excel column letter
     * Example: 1 => A, 2 => B, 27 => AA, etc.
     */
    private function getColumnLetter(int $columnIndex): string
    {
        $columnLetter = '';
        while ($columnIndex > 0) {
            $modulo = ($columnIndex - 1) % 26;
            $columnLetter = chr(65 + $modulo) . $columnLetter;
            $columnIndex = (int)(($columnIndex - $modulo) / 26);
        }
        return $columnLetter;
    }

    /**
     * Create/Insert ProductionPlan data via API body (for testing)
     * Accepts single record or array of records
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.partno' => 'required|string|max:100',
            'data.*.divisi' => 'required|string|max:100',
            'data.*.qty_plan' => 'required|integer|min:0',
            'data.*.plan_date' => 'required|date',
        ]);

        try {
            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;

            foreach ($request->input('data') as $item) {
                $partnoTrimmed = trim((string) $item['partno']);
                $divisiTrimmed = trim((string) $item['divisi']);
                $qtyPlanParsed = (int) $item['qty_plan'];
                $planDateParsed = Carbon::parse($item['plan_date'])->format('Y-m-d');

                // Check if record with same partno, divisi and plan_date exists
                $existingRecord = ProductionPlan::where('partno', $partnoTrimmed)
                    ->where('divisi', $divisiTrimmed)
                    ->where('plan_date', $planDateParsed)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $updates[] = [
                        'id' => $existingRecord->id,
                        'qty_plan' => $qtyPlanParsed,
                        'updated_at' => $now,
                    ];
                } else {
                    // Insert new record
                    $inserts[] = [
                        'partno' => $partnoTrimmed,
                        'divisi' => $divisiTrimmed,
                        'qty_plan' => $qtyPlanParsed,
                        'plan_date' => $planDateParsed,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::transaction(function () use ($inserts, $updates, &$inserted, &$updated) {
                if (!empty($inserts)) {
                    $chunks = array_chunk($inserts, 1000);
                    foreach ($chunks as $chunk) {
                        ProductionPlan::insert($chunk);
                    }
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    ProductionPlan::where('id', $id)->update($updateData);
                    $updated++;
                }
            });

            return $this->sendResponse([
                'inserted' => $inserted,
                'updated' => $updated,
            ], 'Data berhasil disimpan');
        } catch (\Throwable $e) {
            return $this->sendError('Gagal menyimpan data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get ProductionPlan data with optional filters
     * Returns data in horizontal format (like Excel) grouped by partno, divisi, year, and period
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductionPlan::query();

        // Filters
        if ($request->has('partno')) {
            $query->where('partno', $request->input('partno'));
        }

        if ($request->has('divisi')) {
            $query->where('divisi', $request->input('divisi'));
        }

        if ($request->has('plan_date')) {
            $planDate = Carbon::parse($request->input('plan_date'))->format('Y-m-d');
            $query->where('plan_date', $planDate);
        }

        if ($request->has('plan_date_from')) {
            $planDateFrom = Carbon::parse($request->input('plan_date_from'))->format('Y-m-d');
            $query->where('plan_date', '>=', $planDateFrom);
        }

        if ($request->has('plan_date_to')) {
            $planDateTo = Carbon::parse($request->input('plan_date_to'))->format('Y-m-d');
            $query->where('plan_date', '<=', $planDateTo);
        }

        // Get all matching records
        $records = $query->orderBy('plan_date')
            ->orderBy('partno')
            ->orderBy('divisi')
            ->get();

        // Group data by partno, divisi, year, and period
        $grouped = [];
        foreach ($records as $record) {
            $planDate = Carbon::parse($record->plan_date);
            $year = $planDate->year;
            $period = $planDate->month;
            $day = $planDate->day;

            $key = "{$record->partno}|{$record->divisi}|{$year}|{$period}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'partno' => $record->partno,
                    'divisi' => $record->divisi,
                    'year' => $year,
                    'period' => $period,
                    'days' => [],
                ];
            }

            $grouped[$key]['days'][$day] = $record->qty_plan;
        }

        // Convert to indexed array
        $result = array_values($grouped);

        // Manual pagination
        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $page = max(1, (int) $request->input('page', 1));
        $total = count($result);
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($result, $offset, $perPage);

        $paginatedData = [
            'current_page' => $page,
            'data' => $items,
            'first_page_url' => $request->url() . '?page=1',
            'from' => $offset + 1,
            'last_page' => $lastPage,
            'last_page_url' => $request->url() . '?page=' . $lastPage,
            'next_page_url' => $page < $lastPage ? $request->url() . '?page=' . ($page + 1) : null,
            'path' => $request->url(),
            'per_page' => $perPage,
            'prev_page_url' => $page > 1 ? $request->url() . '?page=' . ($page - 1) : null,
            'to' => min($offset + $perPage, $total),
            'total' => $total,
        ];

        return $this->sendResponse($paginatedData, 'Data berhasil diambil');
    }

    /**
     * Get single ProductionPlan record by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $data = ProductionPlan::findOrFail($id);
            return $this->sendResponse($data, 'Data berhasil diambil');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Data tidak ditemukan', [], 404);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal mengambil data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update ProductionPlan record by ID
     * Accepts partial or full update
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = ProductionPlan::findOrFail($id);

            $request->validate([
                'partno' => 'sometimes|required|string|max:100',
                'divisi' => 'sometimes|required|string|max:100',
                'qty_plan' => 'sometimes|required|integer|min:0',
                'plan_date' => 'sometimes|required|date',
            ]);

            $updateData = [];

            // Update partno jika ada
            if ($request->has('partno')) {
                $updateData['partno'] = trim((string) $request->input('partno'));
            }

            // Update divisi jika ada
            if ($request->has('divisi')) {
                $updateData['divisi'] = trim((string) $request->input('divisi'));
            }

            // Update qty_plan jika ada
            if ($request->has('qty_plan')) {
                $updateData['qty_plan'] = (int) $request->input('qty_plan');
            }

            // Update plan_date jika ada
            if ($request->has('plan_date')) {
                $planDateRaw = $request->input('plan_date');
                $planDate = null;

                try {
                    if (is_numeric($planDateRaw)) {
                        // Format Excel numeric
                        $planDate = Carbon::instance(ExcelDate::excelToDateTimeObject($planDateRaw))->format('Y-m-d');
                    } else {
                        // Try parsing dengan berbagai format
                        $dateString = trim((string) $planDateRaw);
                        
                        // Format: DD/MM/YYYY atau DD-MM-YYYY
                        if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $dateString)) {
                            $planDate = Carbon::createFromFormat('d/m/Y', str_replace('-', '/', $dateString))->format('Y-m-d');
                        } else {
                            // Format lainnya: YYYY-MM-DD, MM/DD/YYYY, dll
                            $planDate = Carbon::parse($dateString)->format('Y-m-d');
                        }
                    }
                } catch (\Throwable $e) {
                    return $this->sendError('Format tanggal tidak valid', [], 422);
                }

                $updateData['plan_date'] = $planDate;
            }

            if (empty($updateData)) {
                return $this->sendError('Tidak ada data yang diupdate', [], 422);
            }

            DB::transaction(function () use ($data, $updateData) {
                $data->update($updateData);
            });

            $data->refresh();

            return $this->sendResponse($data, 'Data berhasil diupdate');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Data tidak ditemukan', [], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validasi gagal', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal mengupdate data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete ProductionPlan record by ID
     */
    public function destroy($id): JsonResponse
    {
        try {
            $data = ProductionPlan::findOrFail($id);

            DB::transaction(function () use ($data) {
                $data->delete();
            });

            return $this->sendResponse([], 'Data berhasil dihapus');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Data tidak ditemukan', [], 404);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal menghapus data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete multiple ProductionPlan records by IDs
     */
    public function destroyMultiple(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer',
            ]);

            $ids = $request->input('ids');

            DB::transaction(function () use ($ids) {
                ProductionPlan::whereIn('id', $ids)->delete();
            });

            return $this->sendResponse([
                'deleted' => count($ids),
            ], 'Data berhasil dihapus');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validasi gagal', $e->errors(), 422);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal menghapus data: ' . $e->getMessage(), [], 500);
        }
    }
}
