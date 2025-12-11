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
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            if (empty($rows)) {
                return $this->sendError('File kosong', [], 422);
            }

            $headerRow = array_shift($rows);
            $headers = [];
            foreach ($headerRow as $column => $value) {
                $normalized = strtolower(trim((string) $value));
                if ($normalized) {
                    $headers[$normalized] = $column;
                }
            }

            foreach (['partno', 'divisi', 'qty_plan', 'plan_date'] as $required) {
                if (!isset($headers[$required])) {
                    return $this->sendError("Kolom {$required} tidak ditemukan pada header", [], 422);
                }
            }

            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $partno = $row[$headers['partno']] ?? null;
                $divisi = $row[$headers['divisi']] ?? null;
                $qtyPlan = $row[$headers['qty_plan']] ?? null;
                $planDateRaw = $row[$headers['plan_date']] ?? null;

                if ($partno === null && $divisi === null && $qtyPlan === null && $planDateRaw === null) {
                    continue;
                }

                $planDate = null;
                if ($planDateRaw !== null && $planDateRaw !== '') {
                    try {
                        if (is_numeric($planDateRaw)) {
                            // Format Excel numeric (contoh: 45300)
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
                        // Jika parsing gagal, set null
                        $planDate = null;
                    }
                }

                $partnoTrimmed = $partno !== '' ? trim((string) $partno) : null;
                $divisiTrimmed = $divisi !== '' ? trim((string) $divisi) : null;
                $qtyPlanParsed = $qtyPlan !== '' ? (int) $qtyPlan : null;

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

            if (empty($inserts) && empty($updates)) {
                return $this->sendError('Tidak ada baris data yang diproses', [], 422);
            }

            DB::transaction(function () use ($inserts, $updates, &$inserted, &$updated) {
                if (!empty($inserts)) {
                    ProductionPlan::insert($inserts);
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
            ], 'Import berhasil');
        } catch (\Throwable $e) {
            return $this->sendError('Gagal memproses file: ' . $e->getMessage(), [], 500);
        }
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
                    ProductionPlan::insert($inserts);
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
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductionPlan::query();

        if ($request->has('plan_date')) {
            $query->where('plan_date', Carbon::parse($request->input('plan_date'))->format('Y-m-d'));
        }

        if ($request->has('partno')) {
            $query->where('partno', $request->input('partno'));
        }

        if ($request->has('divisi')) {
            $query->where('divisi', $request->input('divisi'));
        }

        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $data = $query->orderBy('plan_date', 'desc')
            ->orderBy('partno')
            ->orderBy('divisi')
            ->paginate($perPage);

        return $this->sendResponse($data, 'Data berhasil diambil');
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
