<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyUseWh;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DailyUseWhController extends ApiController
{
    /**
     * Import DailyUseWh data from Excel file
     * New format: partno, warehouse, year, period, qty
     * The qty value will apply to all days in the specified month
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validasi file gagal', [
                'errors' => $e->errors(),
                'message' => 'File harus berformat Excel (.xlsx, .xls) atau CSV (.csv)'
            ], 422);
        }

        $file = $request->file('file');

        try {
            // Load spreadsheet
            try {
                $spreadsheet = IOFactory::load($file->getRealPath());
            } catch (\Exception $e) {
                return $this->sendError('Gagal membaca file Excel', [
                    'error' => 'File tidak dapat dibaca atau format tidak valid',
                    'detail' => $e->getMessage()
                ], 422);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                return $this->sendError('File kosong', [
                    'error' => 'File Excel tidak berisi data'
                ], 422);
            }

            // Skip header rows (row 1-3), data starts from row 4
            unset($rows[1], $rows[2], $rows[3]);

            if (empty($rows)) {
                return $this->sendError('Tidak ada data untuk diproses', [
                    'error' => 'File hanya berisi header, tidak ada data di baris 4 ke bawah'
                ], 422);
            }

            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            // Column positions
            $PARTNO_COL = 'B';
            $WAREHOUSE_COL = 'C';
            $YEAR_COL = 'D';
            $PERIOD_COL = 'E';
            $QTY_COL = 'F';

            foreach ($rows as $rowIndex => $row) {
                try {
                    // Get values from columns
                    $partno = $row[$PARTNO_COL] ?? null;
                    $warehouse = $row[$WAREHOUSE_COL] ?? null;
                    $year = $row[$YEAR_COL] ?? null;
                    $period = $row[$PERIOD_COL] ?? null;
                    $qty = $row[$QTY_COL] ?? null;

                    // Skip if all required fields are empty
                    if (empty($partno) && empty($warehouse) && empty($year) && empty($period)) {
                        continue;
                    }

                    // Validate partno
                    $partnoTrimmed = !empty($partno) ? trim((string) $partno) : null;
                    if (empty($partnoTrimmed)) {
                        $errors[] = "Baris {$rowIndex}: Part Number (kolom B) kosong";
                        $skipped++;
                        continue;
                    }

                    // Validate warehouse
                    $warehouseTrimmed = !empty($warehouse) ? trim((string) $warehouse) : null;
                    if (empty($warehouseTrimmed)) {
                        $errors[] = "Baris {$rowIndex}: Warehouse (kolom C) kosong";
                        $skipped++;
                        continue;
                    }

                    // Validate year
                    $yearParsed = !empty($year) ? (int) $year : null;
                    if ($yearParsed === null || $yearParsed < 2000 || $yearParsed > 2100) {
                        $errors[] = "Baris {$rowIndex}: Year (kolom D) tidak valid. Nilai: '{$year}'. Harus antara 2000-2100";
                        $skipped++;
                        continue;
                    }

                    // Validate period (month)
                    $periodParsed = !empty($period) ? (int) $period : null;
                    if ($periodParsed === null || $periodParsed < 1 || $periodParsed > 12) {
                        $errors[] = "Baris {$rowIndex}: Period (kolom E) tidak valid. Nilai: '{$period}'. Harus antara 1-12 (Januari-Desember)";
                        $skipped++;
                        continue;
                    }

                    // Parse qty, default to 0 if empty
                    $qtyParsed = ($qty === null || $qty === '') ? 0 : (int) $qty;
                    if ($qtyParsed < 0) {
                        $errors[] = "Baris {$rowIndex}: Qty (kolom F) tidak boleh negatif. Nilai: '{$qty}'";
                        $skipped++;
                        continue;
                    }

                    // Check if record exists
                    $existingRecord = DailyUseWh::where('partno', $partnoTrimmed)
                        ->where('warehouse', $warehouseTrimmed)
                        ->where('year', $yearParsed)
                        ->where('period', $periodParsed)
                        ->first();

                    if ($existingRecord) {
                        // Update existing record
                        $updates[] = [
                            'id' => $existingRecord->id,
                            'qty' => $qtyParsed,
                            'updated_at' => $now,
                        ];
                    } else {
                        // Insert new record
                        $inserts[] = [
                            'partno' => $partnoTrimmed,
                            'warehouse' => $warehouseTrimmed,
                            'year' => $yearParsed,
                            'period' => $periodParsed,
                            'qty' => $qtyParsed,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = "Baris {$rowIndex}: Error tidak terduga - " . $e->getMessage();
                    $skipped++;
                    continue;
                }
            }

            if (empty($inserts) && empty($updates)) {
                $errorMessage = 'Tidak ada data yang dapat diproses';
                $errorDetails = [
                    'total_rows' => count($rows),
                    'skipped' => $skipped,
                    'errors' => array_slice($errors, 0, 20) // Show first 20 errors
                ];
                
                if (!empty($errors)) {
                    $errorMessage .= '. Silakan periksa format data Excel Anda.';
                }
                
                return $this->sendError($errorMessage, $errorDetails, 422);
            }

            // Execute database operations
            try {
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
            } catch (\Exception $e) {
                return $this->sendError('Gagal menyimpan data ke database', [
                    'error' => $e->getMessage(),
                    'inserted_before_error' => $inserted,
                    'updated_before_error' => $updated
                ], 500);
            }

            $response = [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'total_processed' => $inserted + $updated,
            ];

            if (!empty($errors)) {
                $response['errors'] = array_slice($errors, 0, 20); // Limit to first 20 errors
                $response['total_errors'] = count($errors);
                if (count($errors) > 20) {
                    $response['error_note'] = 'Menampilkan 20 error pertama dari total ' . count($errors) . ' error';
                }
            }

            $message = 'Import berhasil';
            if ($skipped > 0) {
                $message .= " dengan {$skipped} baris dilewati";
            }

            return $this->sendResponse($response, $message);
            
        } catch (\Exception $e) {
            return $this->sendError('Gagal memproses file', [
                'error' => 'Terjadi kesalahan saat memproses file Excel',
                'detail' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Create/Insert DailyUseWh data via API body
     * Accepts single record or array of records
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'data.*.partno' => 'required|string|max:100',
                'data.*.warehouse' => 'required|string|max:100',
                'data.*.year' => 'required|integer|min:2000|max:2100',
                'data.*.period' => 'required|integer|min:1|max:12',
                'data.*.qty' => 'required|integer|min:0',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validasi gagal', $e->errors(), 422);
        }

        try {
            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;

            foreach ($request->input('data') as $item) {
                $partnoTrimmed = trim((string) $item['partno']);
                $warehouseTrimmed = trim((string) $item['warehouse']);
                $yearParsed = (int) $item['year'];
                $periodParsed = (int) $item['period'];
                $qtyParsed = (int) $item['qty'];

                // Check if record exists
                $existingRecord = DailyUseWh::where('partno', $partnoTrimmed)
                    ->where('warehouse', $warehouseTrimmed)
                    ->where('year', $yearParsed)
                    ->where('period', $periodParsed)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $updates[] = [
                        'id' => $existingRecord->id,
                        'qty' => $qtyParsed,
                        'updated_at' => $now,
                    ];
                } else {
                    // Insert new record
                    $inserts[] = [
                        'partno' => $partnoTrimmed,
                        'warehouse' => $warehouseTrimmed,
                        'year' => $yearParsed,
                        'period' => $periodParsed,
                        'qty' => $qtyParsed,
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
        } catch (\Exception $e) {
            return $this->sendError('Gagal menyimpan data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get DailyUseWh data with optional filters
     * Returns data in simple format: partno, warehouse, year, period, qty
     */
    public function index(Request $request): JsonResponse
    {
        $query = DailyUseWh::query();

        // Filters
        if ($request->has('partno')) {
            $query->where('partno', 'like', '%' . $request->input('partno') . '%');
        }

        if ($request->has('warehouse')) {
            $query->where('warehouse', $request->input('warehouse'));
        }

        if ($request->has('year')) {
            $query->where('year', (int) $request->input('year'));
        }

        if ($request->has('period')) {
            $query->where('period', (int) $request->input('period'));
        }

        // Pagination
        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $paginatedData = $query->orderBy('year', 'desc')
            ->orderBy('period', 'desc')
            ->orderBy('partno')
            ->orderBy('warehouse')
            ->paginate($perPage);

        return $this->sendResponse($paginatedData, 'Data berhasil diambil');
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
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = DailyUseWh::findOrFail($id);

            $request->validate([
                'partno' => 'sometimes|required|string|max:100',
                'warehouse' => 'sometimes|required|string|max:100',
                'year' => 'sometimes|required|integer|min:2000|max:2100',
                'period' => 'sometimes|required|integer|min:1|max:12',
                'qty' => 'sometimes|required|integer|min:0',
            ]);

            $updateData = [];

            if ($request->has('partno')) {
                $updateData['partno'] = trim((string) $request->input('partno'));
            }

            if ($request->has('warehouse')) {
                $updateData['warehouse'] = trim((string) $request->input('warehouse'));
            }

            if ($request->has('year')) {
                $updateData['year'] = (int) $request->input('year');
            }

            if ($request->has('period')) {
                $updateData['period'] = (int) $request->input('period');
            }

            if ($request->has('qty')) {
                $updateData['qty'] = (int) $request->input('qty');
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
