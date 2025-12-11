<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SyncLog;
use Carbon\Carbon;
use Exception;

class SyncErpData extends Command
{
    protected $signature = 'sync:erp-data
                            {--manual : Manual sync flag}
                            {--month= : Month to sync (format: YYYY-MM, e.g., 2025-08). Only used with --manual}
                            {--init : Initialize sync (sync all data from both ERP and ERP2)}';
    protected $description = 'Sync ERP data to local database';

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
            $this->info("Starting initialization sync (all data from ERP and ERP2)...");
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

        // Create sync log BEFORE disabling query log
        $syncLog = SyncLog::create([
            'sync_type' => $syncType,
            'status' => 'running',
            'started_at' => now(),
            'total_records' => 0,
            'success_records' => 0,
            'failed_records' => 0,
        ]);

        $this->info("Sync Log ID: {$syncLog->id}");

        // Disable query logging to save memory (only for ERP connections)
        DB::connection('erp')->disableQueryLog();
        DB::connection('erp2')->disableQueryLog();

        try {
            $totalRecords = 0;
            $successRecords = 0;
            $failedRecords = 0;

            // Sync StockByWh
            $this->info('Syncing StockByWh...');
            $result = $this->syncStockByWh();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync WarehouseOrder
            $this->info('Syncing WarehouseOrder...');
            $result = $this->syncWarehouseOrder();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync WarehouseOrderLine
            $this->info('Syncing WarehouseOrderLine...');
            $result = $this->syncWarehouseOrderLine();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync SoInvoiceLine (merged with SoInvoiceLine2)
            $this->info('Syncing SoInvoiceLine...');
            $result = $this->syncSoInvoiceLine();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync ProdHeader
            $this->info('Syncing ProdHeader...');
            $result = $this->syncProdHeader();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync ReceiptPurchase
            $this->info('Syncing ReceiptPurchase...');
            $result = $this->syncReceiptPurchase();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync InventoryTransaction
            $this->info('Syncing InventoryTransaction...');
            $result = $this->syncInventoryTransaction();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync SoMonitor
            $this->info('Syncing SoMonitor...');
            $result = $this->syncSoMonitor();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync SalesShipment
            $this->info('Syncing SalesShipment...');
            $result = $this->syncSalesShipment();
            $totalRecords += $result['total'];
            $successRecords += $result['success'];
            $failedRecords += $result['failed'];

            // Sync DnDetail
            $this->info('Syncing DnDetail...');
            $result = $this->syncDnDetail();
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

    private function syncStockByWh()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // StockByWh unique key: warehouse + partno
            $uniqueKeys = ['warehouse', 'partno'];

            // Process data in chunks to avoid memory issues
            DB::connection('erp')->table('stockbywh')->orderBy('partno')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'warehouse' => $record->warehouse,
                            'partno' => $record->partno,
                            'desc' => $record->desc,
                            'partname' => $record->partname,
                            'oldpartno' => $record->oldpartno,
                            'group' => $record->group,
                            'groupkey' => $record->groupkey,
                            'product_type' => $record->product_type,
                            'model' => $record->model,
                            'customer' => $record->customer,
                            'onhand' => $record->onhand,
                            'allocated' => $record->allocated,
                            'onorder' => $record->onorder,
                            'economicstock' => $record->economicstock,
                            'safety_stock' => $record->safety_stock,
                            'min_stock' => $record->min_stock,
                            'max_stock' => $record->max_stock,
                            'unit' => $record->unit,
                            'location' => $record->location,
                        ];

                        $result = $this->upsertRecord('stockbywh', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync StockByWh record (warehouse: {$record->warehouse}, partno: {$record->partno}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " StockByWh records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("StockByWh sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing StockByWh: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncWarehouseOrder()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // WarehouseOrder unique key: order_origin_code
            $uniqueKeys = ['order_origin_code'];

            $query = DB::connection('erp')->table('view_warehouse_order');

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('order_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('order_origin_code')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'order_origin_code' => $record->order_origin_code,
                            'order_origin' => $record->order_origin,
                            'trx_type' => $record->trx_type,
                            'order_date' => $record->order_date,
                            'plan_delivery_date' => $record->plan_delivery_date,
                            'ship_from_type' => $record->ship_from_type,
                            'ship_from' => $record->ship_from,
                            'ship_from_desc' => $record->ship_from_desc,
                            'ship_to_type' => $record->ship_to_type,
                            'ship_to' => $record->ship_to,
                            'ship_to_desc' => $record->ship_to_desc,
                        ];

                        $result = $this->upsertRecord('view_warehouse_order', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync WarehouseOrder record (order_origin_code: {$record->order_origin_code}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " WarehouseOrder records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("WarehouseOrder sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing WarehouseOrder: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncWarehouseOrderLine()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // WarehouseOrderLine unique key: order_origin_code + line_no
            $uniqueKeys = ['order_origin_code', 'line_no'];

            $query = DB::connection('erp')->table('view_warehouse_order_line');

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('order_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('order_origin_code')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'order_origin_code' => $record->order_origin_code,
                            'order_origin' => $record->order_origin,
                            'trx_type' => $record->trx_type,
                            'order_date' => $record->order_date,
                            'delivery_date' => $record->delivery_date,
                            'receipt_date' => $record->receipt_date,
                            'order_no' => $record->order_no,
                            'line_no' => $record->line_no,
                            'ship_from_type' => $record->ship_from_type,
                            'ship_from' => $record->ship_from,
                            'ship_from_desc' => $record->ship_from_desc,
                            'ship_to_type' => $record->ship_to_type,
                            'ship_to' => $record->ship_to,
                            'ship_to_desc' => $record->ship_to_desc,
                            'item_code' => $record->item_code,
                            'item_desc' => $record->item_desc,
                            'item_desc2' => $record->item_desc2,
                            'order_qty' => $record->order_qty,
                            'ship_qty' => $record->ship_qty,
                            'unit' => $record->unit,
                            'line_status_code' => $record->line_status_code,
                            'line_status' => $record->line_status,
                        ];

                        $result = $this->upsertRecord('view_warehouse_order_line', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync WarehouseOrderLine record (order_origin_code: {$record->order_origin_code}, line_no: {$record->line_no}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " WarehouseOrderLine records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("WarehouseOrderLine sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing WarehouseOrderLine: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncSoInvoiceLine()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // SoInvoiceLine unique key: sales_order + so_line + invoice_no + inv_line
            // If invoice_no is null, use sales_order + so_line
            $uniqueKeys = ['sales_order', 'so_line'];

            // Sync from ERP (10.1.10.52) - contains data from month 8 2025 onwards
            $this->info('Syncing SoInvoiceLine from ERP...');
            $result = $this->syncSoInvoiceLineFromSource('erp', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
            $success = $result['success'];
            $failed = $result['failed'];
            $updated = $result['updated'];
            $inserted = $result['inserted'];
            $skipped = $result['skipped'];
            $total = $result['total'];

            // Sync from ERP2 (10.1.10.50) - only during initialization (contains historical data)
            if ($this->isInitialization) {
                $this->info('Syncing SoInvoiceLine from ERP2 (historical data)...');
                $result = $this->syncSoInvoiceLineFromSource('erp2', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
                $success = $result['success'];
                $failed = $result['failed'];
                $updated = $result['updated'];
                $inserted = $result['inserted'];
                $skipped = $result['skipped'];
                $total = $result['total'];
            }

            $this->info("SoInvoiceLine sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing SoInvoiceLine: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    /**
     * Helper method to sync SoInvoiceLine from a specific source (erp or erp2)
     */
    private function syncSoInvoiceLineFromSource($connection, $uniqueKeys, $chunkSize, &$success, &$failed, &$updated, &$inserted, &$skipped, &$total)
    {
        $query = DB::connection($connection)->table('so_invoice_line');

        // Apply date filter if not initialization
        if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
            $query->where(function($q) {
                $q->whereBetween('so_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')])
                  ->orWhereBetween('invoice_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            });
        }

        $query->orderBy('sales_order')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
            foreach ($records as $record) {
                try {
                    $data = [
                        'bp_code' => $record->bp_code,
                        'bp_name' => $record->bp_name,
                        'sales_order' => $record->sales_order,
                        'so_date' => $record->so_date,
                        'so_line' => $record->so_line,
                        'so_sequence' => $record->so_sequence,
                        'customer_po' => $record->customer_po,
                        'shipment' => $record->shipment,
                        'shipment_line' => $record->shipment_line,
                        'delivery_date' => $record->delivery_date,
                        'receipt' => $record->receipt ?? null,
                        'receipt_line' => $record->receipt_line ?? null,
                        'receipt_date' => $record->receipt_date ?? null,
                        'part_no' => $record->part_no,
                        'old_partno' => $record->old_partno,
                        'product_type' => $record->product_type,
                        'cust_partno' => $record->cust_partno,
                        'cust_partname' => $record->cust_partname,
                        'item_group' => $record->item_group ?? null,
                        'delivered_qty' => $record->delivered_qty,
                        'unit' => $record->unit,
                        'shipment_reference' => $record->shipment_reference,
                        'status' => $record->status,
                        'shipment_status' => $record->shipment_status,
                        'invoice_no' => $record->invoice_no,
                        'inv_line' => $record->inv_line,
                        'invoice_date' => $record->invoice_date,
                        'invoice_qty' => $record->invoice_qty,
                        'currency' => $record->currency,
                        'price' => $record->price,
                        'amount' => $record->amount,
                        'price_hc' => $record->price_hc,
                        'amount_hc' => $record->amount_hc,
                        'inv_stat' => $record->inv_stat,
                        'invoice_status' => $record->invoice_status,
                        'dlv_log_date' => $record->dlv_log_date,
                    ];

                    $result = $this->upsertRecord('so_invoice_line', $data, $uniqueKeys);

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
                    $this->warn("Failed to sync SoInvoiceLine record (sales_order: {$record->sales_order}, so_line: {$record->so_line}): " . $e->getMessage());
                }
            }

            if ($total % 1000 == 0) {
                $this->info("Processed " . number_format($total) . " SoInvoiceLine records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
            }

            gc_collect_cycles();
        });

        return [
            'success' => $success,
            'failed' => $failed,
            'updated' => $updated,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => $total
        ];
    }

    private function syncProdHeader()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // ProdHeader unique key: prod_index
            $uniqueKeys = ['prod_index'];

            $query = DB::connection('erp')->table('view_prod_header');

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('planning_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('prod_index')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'prod_index' => $record->prod_index,
                            'prod_no' => $record->prod_no,
                            'planning_date' => $record->planning_date,
                            'item' => $record->item,
                            'old_partno' => $record->old_partno,
                            'description' => $record->description,
                            'mat_desc' => $record->mat_desc,
                            'customer' => $record->customer,
                            'model' => $record->model,
                            'unique_no' => $record->unique_no,
                            'sanoh_code' => $record->sanoh_code,
                            'snp' => $record->snp,
                            'sts' => $record->sts,
                            'status' => $record->status,
                            'qty_order' => $record->qty_order,
                            'qty_delivery' => $record->qty_delivery,
                            'qty_os' => $record->qty_os,
                            'warehouse' => $record->warehouse,
                            'divisi' => $record->divisi,
                        ];

                        $result = $this->upsertRecord('view_prod_header', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync ProdHeader record (prod_index: {$record->prod_index}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " ProdHeader records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("ProdHeader sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing ProdHeader: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncReceiptPurchase()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // ReceiptPurchase unique key: receipt_no + receipt_line
            $uniqueKeys = ['receipt_no', 'receipt_line'];

            $query = DB::connection('erp')->table('data_receipt_purchase');

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('actual_receipt_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('receipt_no')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'po_no' => $record->po_no,
                            'bp_id' => $record->bp_id,
                            'bp_name' => $record->bp_name,
                            'currency' => $record->currency,
                            'po_type' => $record->po_type,
                            'po_reference' => $record->po_reference,
                            'po_line' => $record->po_line,
                            'po_sequence' => $record->po_sequence,
                            'po_receipt_sequence' => $record->po_receipt_sequence,
                            'actual_receipt_date' => $record->actual_receipt_date,
                            'actual_receipt_year' => $record->actual_receipt_year,
                            'actual_receipt_period' => $record->actual_receipt_period,
                            'receipt_no' => $record->receipt_no,
                            'receipt_line' => $record->receipt_line,
                            'gr_no' => $record->gr_no,
                            'packing_slip' => $record->packing_slip,
                            'item_no' => $record->item_no,
                            'ics_code' => $record->ics_code,
                            'ics_part' => $record->ics_part,
                            'part_no' => $record->part_no,
                            'item_desc' => $record->item_desc,
                            'item_group' => $record->item_group,
                            'item_type' => $record->item_type,
                            'item_type_desc' => $record->item_type_desc,
                            'request_qty' => $record->request_qty,
                            'actual_receipt_qty' => $record->actual_receipt_qty,
                            'approve_qty' => $record->approve_qty,
                            'unit' => $record->unit,
                            'receipt_amount' => $record->receipt_amount,
                            'receipt_unit_price' => $record->receipt_unit_price,
                            'is_final_receipt' => $this->convertToBoolean($record->is_final_receipt),
                            'is_confirmed' => $this->convertToBoolean($record->is_confirmed),
                            'inv_doc_no' => $record->inv_doc_no,
                            'inv_doc_date' => $record->inv_doc_date,
                            'inv_qty' => $record->inv_qty,
                            'inv_amount' => $record->inv_amount,
                            'inv_supplier_no' => $record->inv_supplier_no,
                            'inv_due_date' => $record->inv_due_date,
                            'payment_doc' => $record->payment_doc,
                            'payment_doc_date' => $record->payment_doc_date,
                        ];

                        $result = $this->upsertRecord('data_receipt_purchase', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync ReceiptPurchase record (receipt_no: {$record->receipt_no}, receipt_line: {$record->receipt_line}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " ReceiptPurchase records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("ReceiptPurchase sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing ReceiptPurchase: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncInventoryTransaction()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // InventoryTransaction unique key: trans_id
            $uniqueKeys = ['trans_id'];

            // Sync from ERP (10.1.10.52) - contains data from month 8 2025 onwards
            $this->info('Syncing InventoryTransaction from ERP...');
            $result = $this->syncInventoryTransactionFromSource('erp', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
            $success = $result['success'];
            $failed = $result['failed'];
            $updated = $result['updated'];
            $inserted = $result['inserted'];
            $skipped = $result['skipped'];
            $total = $result['total'];

            // Sync from ERP2 (10.1.10.50) - only during initialization (contains historical data)
            if ($this->isInitialization) {
                $this->info('Syncing InventoryTransaction from ERP2 (historical data)...');
                $result = $this->syncInventoryTransactionFromSource('erp2', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
                $success = $result['success'];
                $failed = $result['failed'];
                $updated = $result['updated'];
                $inserted = $result['inserted'];
                $skipped = $result['skipped'];
                $total = $result['total'];
            }

            $this->info("InventoryTransaction sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing InventoryTransaction: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    /**
     * Helper method to sync InventoryTransaction from a specific source (erp or erp2)
     */
    private function syncInventoryTransactionFromSource($connection, $uniqueKeys, $chunkSize, &$success, &$failed, &$updated, &$inserted, &$skipped, &$total)
    {
        $query = DB::connection($connection)->table('inventory_transaction');

        // Apply date filter if not initialization
        if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
            $query->where(function($q) {
                $q->whereBetween('trans_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')])
                  ->orWhereBetween('trans_date2', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            });
        }

        $query->orderBy('trans_id')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
            foreach ($records as $record) {
                try {
                    $data = [
                        'partno' => $record->partno,
                        'part_desc' => $record->part_desc,
                        'std_oldpart' => $record->std_oldpart,
                        'warehouse' => $record->warehouse,
                        'trans_date' => $record->trans_date,
                        'trans_date2' => $record->trans_date2,
                        'lotno' => $record->lotno,
                        'trans_id' => $record->trans_id,
                        'qty' => $record->qty,
                        'qty_hand' => $record->qty_hand,
                        'trans_type' => $record->trans_type,
                        'order_type' => $record->order_type,
                        'order_no' => $record->order_no,
                        'receipt' => $record->receipt,
                        'shipment' => $record->shipment,
                        'user' => $record->user,
                    ];

                    $result = $this->upsertRecord('inventory_transaction', $data, $uniqueKeys);

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
                    $this->warn("Failed to sync InventoryTransaction record (trans_id: {$record->trans_id}): " . $e->getMessage());
                }
            }

            if ($total % 1000 == 0) {
                $this->info("Processed " . number_format($total) . " InventoryTransaction records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
            }

            gc_collect_cycles();
        });

        return [
            'success' => $success,
            'failed' => $failed,
            'updated' => $updated,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => $total
        ];
    }

    private function syncSoMonitor()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // SoMonitor unique key: year + period + bp_code
            $uniqueKeys = ['year', 'period', 'bp_code'];

            $query = DB::connection('erp')->table('so_monitor')
                ->select(
                    DB::raw('YEAR(planned_delivery_date) as year'),
                    DB::raw('MONTH(planned_delivery_date) as period'),
                    'bp_code',
                    'bp_name',
                    DB::raw('SUM(order_qty) as total_po')
                )
                ->where('sequence', 0)
                ->groupBy(
                    DB::raw('YEAR(planned_delivery_date)'),
                    DB::raw('MONTH(planned_delivery_date)'),
                    'bp_code',
                    'bp_name'
                );

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('planned_delivery_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('year')->orderBy('period')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'year' => $record->year,
                            'period' => $record->period,
                            'bp_code' => $record->bp_code,
                            'bp_name' => $record->bp_name,
                            'total_po' => $record->total_po,
                        ];

                        $result = $this->upsertRecord('so_monitor', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync SoMonitor record (year: {$record->year}, period: {$record->period}, bp_code: {$record->bp_code}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " SoMonitor records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("SoMonitor sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing SoMonitor: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncSalesShipment()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // SalesShipment unique key: year + period + bp_code
            $uniqueKeys = ['year', 'period', 'bp_code'];

            $query = DB::connection('erp')->table('so_invoice_line')
                ->select(
                    DB::raw('YEAR(delivery_date) as year'),
                    DB::raw('MONTH(delivery_date) as period'),
                    'bp_code',
                    'bp_name',
                    DB::raw('SUM(delivered_qty) as total_delivery')
                )
                ->groupBy(
                    DB::raw('YEAR(delivery_date)'),
                    DB::raw('MONTH(delivery_date)'),
                    'bp_code',
                    'bp_name'
                );

            // Apply date filter if not initialization
            if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
                $query->whereBetween('delivery_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d')]);
            }

            $query->orderBy('year')->orderBy('period')->orderBy('bp_code')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
                foreach ($records as $record) {
                    try {
                        $data = [
                            'year' => $record->year,
                            'period' => $record->period,
                            'bp_code' => $record->bp_code,
                            'bp_name' => $record->bp_name,
                            'total_delivery' => $record->total_delivery,
                        ];

                        $result = $this->upsertRecord('sales_shipment', $data, $uniqueKeys);

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
                        $this->warn("Failed to sync SalesShipment record (year: {$record->year}, period: {$record->period}, bp_code: {$record->bp_code}): " . $e->getMessage());
                    }
                }

                if ($total % 1000 == 0) {
                    $this->info("Processed " . number_format($total) . " SalesShipment records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
                }

                gc_collect_cycles();
            });

            $this->info("SalesShipment sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing SalesShipment: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncDnDetail()
    {
        try {
            $success = 0;
            $failed = 0;
            $updated = 0;
            $inserted = 0;
            $skipped = 0;
            $total = 0;
            $chunkSize = 500;

            // Natural key: no_dn + dn_line (not enforced in DB)
            $uniqueKeys = ['no_dn', 'dn_line'];

            // Sync from ERP (10.1.10.52) - default
            $this->info('Syncing DnDetail from ERP...');
            $result = $this->syncDnDetailFromSource('erp', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
            $success = $result['success'];
            $failed = $result['failed'];
            $updated = $result['updated'];
            $inserted = $result['inserted'];
            $skipped = $result['skipped'];
            $total = $result['total'];

            // Sync from ERP2 for initialization (historical)
            if ($this->isInitialization) {
                $this->info('Syncing DnDetail from ERP2 (historical data)...');
                $result = $this->syncDnDetailFromSource('erp2', $uniqueKeys, $chunkSize, $success, $failed, $updated, $inserted, $skipped, $total);
                $success = $result['success'];
                $failed = $result['failed'];
                $updated = $result['updated'];
                $inserted = $result['inserted'];
                $skipped = $result['skipped'];
                $total = $result['total'];
            }

            $this->info("DnDetail sync completed: Total: {$total}, Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");
            return ['total' => $total, 'success' => $success, 'failed' => $failed];
        } catch (Exception $e) {
            $this->error("Error syncing DnDetail: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    /**
     * Helper method to sync DnDetail from given connection
     */
    private function syncDnDetailFromSource($connection, $uniqueKeys, $chunkSize, &$success, &$failed, &$updated, &$inserted, &$skipped, &$total)
    {
        $query = DB::connection($connection)->table('dn_detail');

        if (!$this->isInitialization && $this->dateFrom && $this->dateTo) {
            $query->whereBetween('dn_create_date', [$this->dateFrom->format('Y-m-d'), $this->dateTo->format('Y-m-d 23:59:59')]);
        }

        $query->orderBy('no_dn')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$updated, &$inserted, &$skipped, &$total, $uniqueKeys) {
            foreach ($records as $record) {
                try {
                    $data = [
                        'no_dn' => $record->no_dn,
                        'dn_line' => $record->dn_line,
                        'dn_supplier' => $record->dn_supplier,
                        'dn_create_date' => $record->dn_create_date,
                        'dn_year' => $record->dn_year,
                        'dn_period' => $record->dn_period,
                        'plan_delivery_date' => $record->plan_delivery_date,
                        'plan_delivery_time' => $record->plan_delivery_time,
                        'order_origin' => $record->order_origin,
                        'no_order' => $record->no_order,
                        'order_set' => $record->order_set,
                        'order_line' => $record->order_line,
                        'order_seq' => $record->order_seq,
                        'part_no' => $record->part_no,
                        'item_desc_a' => $record->item_desc_a,
                        'item_desc_b' => $record->item_desc_b,
                        'supplier_item_no' => $record->supplier_item_no,
                        'lot_number' => $record->lot_number,
                        'dn_qty' => $record->dn_qty,
                        'receipt_qty' => $record->receipt_qty,
                        'dn_unit' => $record->dn_unit,
                        'dn_snp' => $record->dn_snp,
                        'reference' => $record->reference,
                        'actual_receipt_date' => $record->actual_receipt_date,
                        'actual_receipt_time' => $record->actual_receipt_time,
                        'warehouse' => $record->warehouse,
                        'status_code' => $record->status_code,
                        'status_desc' => $record->status_desc,
                    ];

                    $result = $this->upsertRecord('dn_detail', $data, $uniqueKeys);

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
                    $this->warn("Failed to sync DnDetail record (no_dn: {$record->no_dn}, dn_line: {$record->dn_line}): " . $e->getMessage());
                }
            }

            if ($total % 1000 == 0) {
                $this->info("Processed " . number_format($total) . " DnDetail records... (Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped})");
            }

            gc_collect_cycles();
        });

        return [
            'success' => $success,
            'failed' => $failed,
            'updated' => $updated,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total' => $total
        ];
    }

    /**
     * Convert string values to boolean
     */
    private function convertToBoolean($value)
    {
        if (is_null($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if (in_array($value, ['yes', 'true', '1', 'y'])) {
            return 1;
        }

        if (in_array($value, ['no', 'false', '0', 'n'])) {
            return 0;
        }

        // Default to 0 if value is not recognized
        return 0;
    }
}
