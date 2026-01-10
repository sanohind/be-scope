<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyUseWh;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class DailyUseWhController extends ApiController
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

            // Fixed column positions (updated format)
            $PARTNO_COL = 'B';
            $WAREHOUSE_COL = 'C';  // New column for warehouse
            $YEAR_COL = 'D';       // Moved from C to D
            $PERIOD_COL = 'E';     // Moved from D to E
            $FIRST_DAY_COL = 'F';  // Column F = day 1 (moved from E to F)

            foreach ($rows as $rowIndex => $row) {
                // Get partno, warehouse, year, period from fixed columns
                $partno = $row[$PARTNO_COL] ?? null;
                $warehouse = $row[$WAREHOUSE_COL] ?? null;
                $year = $row[$YEAR_COL] ?? null;
                $period = $row[$PERIOD_COL] ?? null;

                // Skip if all required fields are empty
                if (empty($partno) && empty($warehouse) && empty($year) && empty($period)) {
                    continue;
                }

                // Validate and parse partno
                $partnoTrimmed = !empty($partno) ? trim((string) $partno) : null;
                if (empty($partnoTrimmed)) {
                    $errors[] = "Row {$rowIndex}: partno kosong";
                    $skipped++;
                    continue;
                }

                // Validate and parse warehouse
                $warehouseTrimmed = !empty($warehouse) ? trim((string) $warehouse) : null;
                if (empty($warehouseTrimmed)) {
                    $errors[] = "Row {$rowIndex}: warehouse kosong";
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

                    // Get daily_use value from the cell
                    $dailyUse = $row[$columnLetter] ?? null;

                    // Parse daily_use as integer, default to 0 if empty
                    if ($dailyUse === null || $dailyUse === '') {
                        $dailyUseParsed = 0;
                    } else {
                        $dailyUseParsed = (int) $dailyUse;
                    }

                    // Construct plan_date from year + period + day
                    try {
                        $planDate = Carbon::create($yearParsed, $periodParsed, $day)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $errors[] = "Row {$rowIndex}, Day {$day}: tanggal tidak valid ({$yearParsed}-{$periodParsed}-{$day})";
                        continue;
                    }

                    // Check if record with same partno, warehouse, and plan_date exists
                    $existingRecord = DailyUseWh::where('partno', $partnoTrimmed)
                        ->where('warehouse', $warehouseTrimmed)
                        ->where('plan_date', $planDate)
                        ->first();

                    if ($existingRecord) {
                        // Update existing record
                        $updates[] = [
                            'id' => $existingRecord->id,
                            'daily_use' => $dailyUseParsed,
                            'updated_at' => $now,
                        ];
                    } else {
                        // Insert new record
                        $inserts[] = [
                            'partno' => $partnoTrimmed,
                            'warehouse' => $warehouseTrimmed,
                            'daily_use' => $dailyUseParsed,
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
                    DailyUseWh::insert($inserts);
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    DailyUseWh::where('id', $id)->update($updateData);
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
     * Create/Insert DailyUseWh data via API body (for testing)
     * Accepts single record or array of records
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.partno' => 'required|string|max:100',
            'data.*.daily_use' => 'required|integer|min:0',
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
                $dailyUseParsed = (int) $item['daily_use'];
                $planDateParsed = Carbon::parse($item['plan_date'])->format('Y-m-d');

                // Check if record with same partno and plan_date exists
                $existingRecord = DailyUseWh::where('partno', $partnoTrimmed)
                    ->where('plan_date', $planDateParsed)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $updates[] = [
                        'id' => $existingRecord->id,
                        'daily_use' => $dailyUseParsed,
                        'updated_at' => $now,
                    ];
                } else {
                    // Insert new record
                    $inserts[] = [
                        'partno' => $partnoTrimmed,
                        'daily_use' => $dailyUseParsed,
                        'plan_date' => $planDateParsed,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::transaction(function () use ($inserts, $updates, &$inserted, &$updated) {
                if (!empty($inserts)) {
                    DailyUseWh::insert($inserts);
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    DailyUseWh::where('id', $id)->update($updateData);
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
     * Get DailyUseWh data with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = DailyUseWh::query();

        if ($request->has('plan_date')) {
            $query->where('plan_date', Carbon::parse($request->input('plan_date'))->format('Y-m-d'));
        }

        if ($request->has('partno')) {
            $query->where('partno', $request->input('partno'));
        }

        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $data = $query->orderBy('plan_date', 'desc')
            ->orderBy('partno')
            ->paginate($perPage);

        return $this->sendResponse($data, 'Data berhasil diambil');
    }

    /**
     * Get single DailyUseWh record by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $data = DailyUseWh::findOrFail($id);
            return $this->sendResponse($data, 'Data berhasil diambil');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Data tidak ditemukan', [], 404);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal mengambil data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update DailyUseWh record by ID
     * Accepts partial or full update
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = DailyUseWh::findOrFail($id);

            $request->validate([
                'partno' => 'sometimes|required|string|max:100',
                'daily_use' => 'sometimes|required|integer|min:0',
                'plan_date' => 'sometimes|required|date',
            ]);

            $updateData = [];

            // Update partno jika ada
            if ($request->has('partno')) {
                $updateData['partno'] = trim((string) $request->input('partno'));
            }

            // Update daily_use jika ada
            if ($request->has('daily_use')) {
                $updateData['daily_use'] = (int) $request->input('daily_use');
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
     * Delete DailyUseWh record by ID
     */
    public function destroy($id): JsonResponse
    {
        try {
            $data = DailyUseWh::findOrFail($id);

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
     * Delete multiple DailyUseWh records by IDs
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
                DailyUseWh::whereIn('id', $ids)->delete();
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

