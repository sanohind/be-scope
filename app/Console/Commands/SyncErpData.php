<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SyncLog;
use Exception;

class SyncErpData extends Command
{
    protected $signature = 'sync:erp-data {--manual : Manual sync flag}';
    protected $description = 'Sync ERP data to local database';

    public function handle()
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '1024M');
        
        $isManual = $this->option('manual');
        $syncType = $isManual ? 'manual' : 'scheduled';

        $this->info("Starting {$syncType} sync...");

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

            // Sync SoInvoiceLine
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

            // Sync SoInvoiceLine2 from ERP2
            $this->info('Syncing SoInvoiceLine2 from ERP2...');
            $result = $this->syncSoInvoiceLine2();
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

    private function syncStockByWh()
    {
        try {
            // Clear existing data
            DB::table('stockbywh')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            // Process data in chunks to avoid memory issues
            DB::connection('erp')->table('stockbywh')->orderBy('partno')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                    $total++;
                }

                try {
                    DB::table('stockbywh')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " StockByWh records...");
                    
                    // Free memory
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert stockbywh batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing StockByWh: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncWarehouseOrder()
    {
        try {
            DB::table('view_warehouse_order')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp')->table('view_warehouse_order')->orderBy('order_origin_code')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                    $total++;
                }

                try {
                    DB::table('view_warehouse_order')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " WarehouseOrder records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert warehouse order batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing WarehouseOrder: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncWarehouseOrderLine()
    {
        try {
            DB::table('view_warehouse_order_line')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp')->table('view_warehouse_order_line')->orderBy('order_origin_code')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                    $total++;
                }

                try {
                    DB::table('view_warehouse_order_line')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " WarehouseOrderLine records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert warehouse order line batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing WarehouseOrderLine: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncSoInvoiceLine()
    {
        try {
            DB::table('so_invoice_line')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp')->table('so_invoice_line')->orderBy('sales_order')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                        'receipt' => $record->receipt,
                        'receipt_line' => $record->receipt_line,
                        'receipt_date' => $record->receipt_date,
                        'part_no' => $record->part_no,
                        'old_partno' => $record->old_partno,
                        'product_type' => $record->product_type,
                        'cust_partno' => $record->cust_partno,
                        'cust_partname' => $record->cust_partname,
                        'item_group' => $record->item_group,
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
                    $total++;
                }

                try {
                    DB::table('so_invoice_line')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " SoInvoiceLine records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert so invoice line batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing SoInvoiceLine: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncProdHeader()
    {
        try {
            DB::table('view_prod_header')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp')->table('view_prod_header')->orderBy('prod_index')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                    $total++;
                }

                try {
                    DB::table('view_prod_header')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " ProdHeader records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert prod header batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing ProdHeader: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncReceiptPurchase()
    {
        try {
            DB::table('data_receipt_purchase')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp')->table('data_receipt_purchase')->orderBy('receipt_no')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                    $total++;
                }

                try {
                    DB::table('data_receipt_purchase')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " ReceiptPurchase records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert receipt purchase batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing ReceiptPurchase: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
    }

    private function syncSoInvoiceLine2()
    {
        try {
            DB::table('so_invoice_line_2')->truncate();

            $success = 0;
            $failed = 0;
            $total = 0;
            $chunkSize = 500;

            DB::connection('erp2')->table('so_invoice_line')->orderBy('sales_order')->chunk($chunkSize, function ($records) use (&$success, &$failed, &$total) {
                $batch = [];
                
                foreach ($records as $record) {
                    $batch[] = [
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
                        'part_no' => $record->part_no,
                        'old_partno' => $record->old_partno,
                        'product_type' => $record->product_type,
                        'cust_partno' => $record->cust_partno,
                        'cust_partname' => $record->cust_partname,
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
                    $total++;
                }

                try {
                    DB::table('so_invoice_line_2')->insert($batch);
                    $success += count($batch);
                    $this->info("Processed " . number_format($total) . " SoInvoiceLine2 records...");
                    unset($batch);
                    gc_collect_cycles();
                } catch (Exception $e) {
                    $failed += count($batch);
                    $this->warn("Failed to insert so invoice line 2 batch: " . $e->getMessage());
                }
            });

            return ['total' => $total, 'success' => $success, 'failed' => $failed];

        } catch (Exception $e) {
            $this->error("Error syncing SoInvoiceLine2: " . $e->getMessage());
            return ['total' => 0, 'success' => 0, 'failed' => 0];
        }
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
