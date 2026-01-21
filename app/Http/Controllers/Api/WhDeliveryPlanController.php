<?php

namespace App\Http\Controllers\Api;

use App\Models\WhDeliveryPlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class WhDeliveryPlanController extends ApiController
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
            $WAREHOUSE_COL = 'C';
            $YEAR_COL = 'D';
            $PERIOD_COL = 'E';
            $FIRST_DAY_COL = 'F'; // Column F = day 1

            foreach ($rows as $rowIndex => $row) {
                // Get partno, warehouse, year, period from fixed columns
                $partno = $row[$PARTNO_COL] ?? null;
                $warehouse = $row[$WAREHOUSE_COL] ?? null;
                $year = $row[$YEAR_COL] ?? null;
                $period = $row[$PERIOD_COL] ?? null;

                // Skip if all are empty
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

                    // Get qty_delivery value from the cell
                    $qtyDelivery = $row[$columnLetter] ?? null;

                    // Parse qty_delivery as integer, default to 0 if empty
                    if ($qtyDelivery === null || $qtyDelivery === '') {
                        $qtyDeliveryParsed = 0;
                    } else {
                        $qtyDeliveryParsed = (int) $qtyDelivery;
                    }

                    // Construct delivery_date from year + period + day
                    try {
                        $deliveryDate = Carbon::create($yearParsed, $periodParsed, $day)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $errors[] = "Row {$rowIndex}, Day {$day}: tanggal tidak valid ({$yearParsed}-{$periodParsed}-{$day})";
                        continue;
                    }

                    // Check if record with same partno, warehouse and delivery_date exists
                    $existingRecord = WhDeliveryPlan::where('partno', $partnoTrimmed)
                        ->where('warehouse', $warehouseTrimmed)
                        ->where('delivery_date', $deliveryDate)
                        ->first();

                    if ($existingRecord) {
                        // Update existing record
                        $updates[] = [
                            'id' => $existingRecord->id,
                            'qty_delivery' => $qtyDeliveryParsed,
                            'updated_at' => $now,
                        ];
                    } else {
                        // Insert new record
                        $inserts[] = [
                            'partno' => $partnoTrimmed,
                            'warehouse' => $warehouseTrimmed,
                            'qty_delivery' => $qtyDeliveryParsed,
                            'delivery_date' => $deliveryDate,
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
                        WhDeliveryPlan::insert($chunk);
                    }
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    WhDeliveryPlan::where('id', $id)->update($updateData);
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
     * Create/Insert WhDeliveryPlan data via API body (for testing)
     * Accepts single record or array of records
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.partno' => 'required|string|max:100',
            'data.*.warehouse' => 'required|string|max:100',
            'data.*.qty_delivery' => 'required|integer|min:0',
            'data.*.delivery_date' => 'required|date',
        ]);

        try {
            $now = Carbon::now();
            $inserts = [];
            $updates = [];
            $inserted = 0;
            $updated = 0;

            foreach ($request->input('data') as $item) {
                $partnoTrimmed = trim((string) $item['partno']);
                $warehouseTrimmed = trim((string) $item['warehouse']);
                $qtyDeliveryParsed = (int) $item['qty_delivery'];
                $deliveryDateParsed = Carbon::parse($item['delivery_date'])->format('Y-m-d');

                // Check if record with same partno, warehouse and delivery_date exists
                $existingRecord = WhDeliveryPlan::where('partno', $partnoTrimmed)
                    ->where('warehouse', $warehouseTrimmed)
                    ->where('delivery_date', $deliveryDateParsed)
                    ->first();

                if ($existingRecord) {
                    // Update existing record
                    $updates[] = [
                        'id' => $existingRecord->id,
                        'qty_delivery' => $qtyDeliveryParsed,
                        'updated_at' => $now,
                    ];
                } else {
                    // Insert new record
                    $inserts[] = [
                        'partno' => $partnoTrimmed,
                        'warehouse' => $warehouseTrimmed,
                        'qty_delivery' => $qtyDeliveryParsed,
                        'delivery_date' => $deliveryDateParsed,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            DB::transaction(function () use ($inserts, $updates, &$inserted, &$updated) {
                if (!empty($inserts)) {
                    $chunks = array_chunk($inserts, 1000);
                    foreach ($chunks as $chunk) {
                        WhDeliveryPlan::insert($chunk);
                    }
                    $inserted = count($inserts);
                }
                
                foreach ($updates as $updateData) {
                    $id = $updateData['id'];
                    unset($updateData['id']);
                    WhDeliveryPlan::where('id', $id)->update($updateData);
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
     * Get WhDeliveryPlan data with optional filters
     * Returns data in horizontal format (like Excel) grouped by partno, warehouse, year, and period
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhDeliveryPlan::query();

        // Filters
        if ($request->has('partno')) {
            $query->where('partno', $request->input('partno'));
        }

        if ($request->has('warehouse')) {
            $query->where('warehouse', $request->input('warehouse'));
        }

        if ($request->has('delivery_date')) {
            $deliveryDate = Carbon::parse($request->input('delivery_date'))->format('Y-m-d');
            $query->where('delivery_date', $deliveryDate);
        }

        if ($request->has('delivery_date_from')) {
            $deliveryDateFrom = Carbon::parse($request->input('delivery_date_from'))->format('Y-m-d');
            $query->where('delivery_date', '>=', $deliveryDateFrom);
        }

        if ($request->has('delivery_date_to')) {
            $deliveryDateTo = Carbon::parse($request->input('delivery_date_to'))->format('Y-m-d');
            $query->where('delivery_date', '<=', $deliveryDateTo);
        }

        // Get all matching records
        $records = $query->orderBy('delivery_date')
            ->orderBy('partno')
            ->orderBy('warehouse')
            ->get();

        // Group data by partno, warehouse, year, and period
        $grouped = [];
        foreach ($records as $record) {
            $deliveryDate = Carbon::parse($record->delivery_date);
            $year = $deliveryDate->year;
            $period = $deliveryDate->month;
            $day = $deliveryDate->day;

            $key = "{$record->partno}|{$record->warehouse}|{$year}|{$period}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'partno' => $record->partno,
                    'warehouse' => $record->warehouse,
                    'year' => $year,
                    'period' => $period,
                    'days' => [],
                ];
            }

            $grouped[$key]['days'][$day] = $record->qty_delivery;
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
     * Get single WhDeliveryPlan record by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $data = WhDeliveryPlan::findOrFail($id);
            return $this->sendResponse($data, 'Data berhasil diambil');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Data tidak ditemukan', [], 404);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal mengambil data: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update WhDeliveryPlan record by ID
     * Accepts partial or full update
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = WhDeliveryPlan::findOrFail($id);

            $request->validate([
                'partno' => 'sometimes|required|string|max:100',
                'warehouse' => 'sometimes|required|string|max:100',
                'qty_delivery' => 'sometimes|required|integer|min:0',
                'delivery_date' => 'sometimes|required|date',
            ]);

            $updateData = [];

            // Update partno jika ada
            if ($request->has('partno')) {
                $updateData['partno'] = trim((string) $request->input('partno'));
            }

            // Update warehouse jika ada
            if ($request->has('warehouse')) {
                $updateData['warehouse'] = trim((string) $request->input('warehouse'));
            }

            // Update qty_delivery jika ada
            if ($request->has('qty_delivery')) {
                $updateData['qty_delivery'] = (int) $request->input('qty_delivery');
            }

            // Update delivery_date jika ada
            if ($request->has('delivery_date')) {
                $deliveryDateRaw = $request->input('delivery_date');
                $deliveryDate = null;

                try {
                    if (is_numeric($deliveryDateRaw)) {
                        // Format Excel numeric
                        $deliveryDate = Carbon::instance(ExcelDate::excelToDateTimeObject($deliveryDateRaw))->format('Y-m-d');
                    } else {
                        // Try parsing dengan berbagai format
                        $dateString = trim((string) $deliveryDateRaw);
                        
                        // Format: DD/MM/YYYY atau DD-MM-YYYY
                        if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $dateString)) {
                            $deliveryDate = Carbon::createFromFormat('d/m/Y', str_replace('-', '/', $dateString))->format('Y-m-d');
                        } else {
                            // Format lainnya: YYYY-MM-DD, MM/DD/YYYY, dll
                            $deliveryDate = Carbon::parse($dateString)->format('Y-m-d');
                        }
                    }
                } catch (\Throwable $e) {
                    return $this->sendError('Format tanggal tidak valid', [], 422);
                }

                $updateData['delivery_date'] = $deliveryDate;
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
     * Delete WhDeliveryPlan record by ID
     */
    public function destroy($id): JsonResponse
    {
        try {
            $data = WhDeliveryPlan::findOrFail($id);

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
     * Delete multiple WhDeliveryPlan records by IDs
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
                WhDeliveryPlan::whereIn('id', $ids)->delete();
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
