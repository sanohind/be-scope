# Dokumentasi API

Panduan singkat untuk menggunakan API di proyek ini. Semua rute didefinisikan di `routes/api.php` dan sebagian besar controller berada di `app/Http/Controllers/Api/`.

## Ringkasan
- **Base URL**: `https://{host}/api`
- **Format**: JSON
- **Header umum**:
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Autentikasi**: endpoint tertentu menggunakan `auth:sanctum` (contoh: `GET /api/user`)

## Pola Respons
Semua controller turunan `ApiController` menggunakan pola berikut:

- Sukses
```json
{
  "success": true,
  "data": {...},
  "message": "Deskripsi singkat"
}
```

- Error
```json
{
  "success": false,
  "message": "Pesan error",
  "data": {"detail": "opsional"}
}
```

## Endpoint Public Resources
Sesuai `Route::apiResources([...], ['only' => ['index', 'show']]);` maka setiap resource hanya menyediakan `index` (GET list) dan `show` (GET detail by id).

- `GET /api/stocks`
- `GET /api/stocks/{id}`
  - Controller: `StockByWhController`
  - Catatan: Mengembalikan daftar/objek Stock By Warehouse.

- `GET /api/warehouse-orders`
- `GET /api/warehouse-orders/{id}`
  - Controller: `WarehouseOrderController`
  - Catatan: Mengembalikan daftar/objek Warehouse Order.

- `GET /api/warehouse-order-lines`
- `GET /api/warehouse-order-lines/{id}`
  - Controller: `WarehouseOrderLineController`
  - Catatan: Rute terdaftar, namun file controllernya tidak ditemukan di repo saat ini.

- `GET /api/production-headers`
- `GET /api/production-headers/{id}`
  - Controller: `ProdHeaderController`

- `GET /api/invoice-lines`
- `GET /api/invoice-lines/{id}`
  - Controller: `SoInvoiceLineController`

- `GET /api/receipt-purchases`
- `GET /api/receipt-purchases/{id}`
  - Controller: `ReceiptPurchaseController`

- `GET /api/sync-logs`
- `GET /api/sync-logs/{id}`
  - Controller: `SyncLogController`

### Contoh cURL (Resources)
- List stocks
```bash
curl -X GET "https://{host}/api/stocks" -H "Accept: application/json"
```

- Detail warehouse order
```bash
curl -X GET "https://{host}/api/warehouse-orders/123" -H "Accept: application/json"
```

## Endpoint Sinkronisasi ERP (`/api/sync/*`)
Didefinisikan di `SyncController`.

- `POST /api/sync/start`
  - Mulai sinkronisasi manual.
  - Respons sukses: `200` dengan `sync_log_id` dan `status`.
  - Konflik: `409` jika masih ada proses berjalan.

- `GET /api/sync/status?sync_id={id}`
  - Tanpa `sync_id`: kembalikan status sync terbaru (atau `data: null`).
  - Dengan `sync_id`: status dari log tertentu, `404` jika tidak ditemukan.

- `GET /api/sync/logs?per_page=10&status=completed|failed|running&sync_type={type}`
  - Mengembalikan `data` (array log) + objek `pagination`:
  ```json
  {
    "success": true,
    "data": [ ... ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 10,
      "total": 25,
      "from": 1,
      "to": 10
    }
  }
  ```

- `GET /api/sync/statistics?days=7`
  - Statistik agregat dan 5 log terbaru dalam jangka waktu `days`.

- `POST /api/sync/cancel`
  - Batalkan proses sinkronisasi yang sedang berjalan. `404` jika tidak ada proses berjalan.

### Contoh cURL (Sync)
- Mulai sinkronisasi
```bash
curl -X POST "https://{host}/api/sync/start" -H "Accept: application/json"
```

- Status terakhir
```bash
curl -X GET "https://{host}/api/sync/status" -H "Accept: application/json"
```

- Status by id
```bash
curl -G "https://{host}/api/sync/status" \
  --data-urlencode "sync_id=42" \
  -H "Accept: application/json"
```

- Daftar log dengan filter
```bash
curl -G "https://{host}/api/sync/logs" \
  --data-urlencode "per_page=10" \
  --data-urlencode "status=completed" \
  --data-urlencode "sync_type=erp" \
  -H "Accept: application/json"
```

- Statistik 14 hari terakhir
```bash
curl -X GET "https://{host}/api/sync/statistics?days=14" -H "Accept: application/json"
```

- Batalkan proses berjalan
```bash
curl -X POST "https://{host}/api/sync/cancel" -H "Accept: application/json"
```

## Endpoint Terproteksi
- `GET /api/user` (middleware: `auth:sanctum`)
  - Header: `Authorization: Bearer {token}`
  - Respons: data user yang terkait token.

### Contoh cURL (Sanctum)
```bash
curl -X GET "https://{host}/api/user" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}"
```

## Contoh Input & Output

### Stocks
- Input: Tidak ada (GET)
- `GET /api/stocks` (200)
```json
{
  "success": true,
  "data": [
    {
      "warehouse": "WH-A",
      "partno": "P-001",
      "desc": "Part A",
      "partname": "Part Name A",
      "oldpartno": "OLD-001",
      "group": "GRP1",
      "groupkey": "GRP1-KEY",
      "product_type": "FG",
      "model": "Model-X",
      "customer": "Customer-1",
      "onhand": 150,
      "allocated": 20,
      "onorder": 50,
      "economicstock": 100,
      "safety_stock": 30,
      "min_stock": 50,
      "max_stock": 500,
      "unit": "PCS",
      "location": "R1-A2"
    }
  ],
  "message": "Stocks retrieved successfully."
}
```

- `GET /api/stocks/{id}` (200)
```json
{
  "success": true,
  "data": {
    "warehouse": "WH-A",
    "partno": "P-001",
    "desc": "Part A",
    "partname": "Part Name A",
    "oldpartno": "OLD-001",
    "group": "GRP1",
    "groupkey": "GRP1-KEY",
    "product_type": "FG",
    "model": "Model-X",
    "customer": "Customer-1",
    "onhand": 150,
    "allocated": 20,
    "onorder": 50,
    "economicstock": 100,
    "safety_stock": 30,
    "min_stock": 50,
    "max_stock": 500,
    "unit": "PCS",
    "location": "R1-A2"
  },
  "message": "Stock retrieved successfully."
}
```

- `GET /api/stocks/{id}` (404)
```json
{
  "success": false,
  "message": "Stock not found."
}
```

### Warehouse Orders
- Input: Tidak ada (GET)
- `GET /api/warehouse-orders` (200)
```json
{
  "success": true,
  "data": [
    {
      "order_origin_code": "SO",
      "order_origin": "Sales Order",
      "trx_type": "SHIPMENT",
      "order_date": "2025-10-01",
      "plan_delivery_date": "2025-10-03",
      "ship_from_type": "WH",
      "ship_from": "WH-A",
      "ship_from_desc": "Warehouse A",
      "ship_to_type": "CUST",
      "ship_to": "CUST-01",
      "ship_to_desc": "PT Contoh Pelanggan"
    }
  ],
  "message": "Warehouse orders retrieved successfully."
}
```

- `GET /api/warehouse-orders/{id}` (200)
```json
{
  "success": true,
  "data": {
    "order_origin_code": "SO",
    "order_origin": "Sales Order",
    "trx_type": "SHIPMENT",
    "order_date": "2025-10-01",
    "plan_delivery_date": "2025-10-03",
    "ship_from_type": "WH",
    "ship_from": "WH-A",
    "ship_from_desc": "Warehouse A",
    "ship_to_type": "CUST",
    "ship_to": "CUST-01",
    "ship_to_desc": "PT Contoh Pelanggan"
  },
  "message": "Warehouse order retrieved successfully."
}
```

- `GET /api/warehouse-orders/{id}` (404)
```json
{
  "success": false,
  "message": "Warehouse order not found."
}
```

### Warehouse Order Lines
- Catatan: Rute terdaftar namun controller tidak ditemukan di repo saat ini. Output dapat bervariasi tergantung implementasi. Berdasarkan model `WarehouseOrderLine`, struktur data tipikal:
```json
{
  "order_origin_code": "SO",
  "order_origin": "Sales Order",
  "trx_type": "SHIPMENT",
  "order_date": "2025-10-01",
  "delivery_date": "2025-10-03",
  "receipt_date": null,
  "order_no": "SO-10001",
  "line_no": 1,
  "ship_from_type": "WH",
  "ship_from": "WH-A",
  "ship_from_desc": "Warehouse A",
  "ship_to_type": "CUST",
  "ship_to": "CUST-01",
  "ship_to_desc": "PT Contoh Pelanggan",
  "item_code": "P-001",
  "item_desc": "Part A",
  "item_desc2": null,
  "order_qty": 100,
  "ship_qty": 50,
  "unit": "PCS",
  "line_status_code": "OPEN",
  "line_status": "Open"
}
```

### Production Headers
- Input: Tidak ada (GET)
- `GET /api/production-headers` (200)
```json
{
  "success": true,
  "data": [
    {
      "prod_index": 1,
      "prod_no": "PRD-0001",
      "planning_date": "2025-10-01",
      "item": "P-001",
      "old_partno": "OLD-001",
      "description": "Produk A",
      "mat_desc": "Material A",
      "customer": "Customer-1",
      "model": "Model-X",
      "unique_no": "UNQ-001",
      "sanoh_code": "SNH-001",
      "snp": 10,
      "sts": "OK",
      "status": "PLANNED",
      "qty_order": 1000,
      "qty_delivery": 400,
      "qty_os": 600,
      "warehouse": "WH-A",
      "divisi": "DIV-1"
    }
  ],
  "message": "Production headers retrieved successfully."
}
```

- `GET /api/production-headers/{id}` (200)
```json
{
  "success": true,
  "data": {
    "prod_index": 1,
    "prod_no": "PRD-0001",
    "planning_date": "2025-10-01",
    "item": "P-001",
    "old_partno": "OLD-001",
    "description": "Produk A",
    "mat_desc": "Material A",
    "customer": "Customer-1",
    "model": "Model-X",
    "unique_no": "UNQ-001",
    "sanoh_code": "SNH-001",
    "snp": 10,
    "sts": "OK",
    "status": "PLANNED",
    "qty_order": 1000,
    "qty_delivery": 400,
    "qty_os": 600,
    "warehouse": "WH-A",
    "divisi": "DIV-1"
  },
  "message": "Production header retrieved successfully."
}
```

- `GET /api/production-headers/{id}` (404)
```json
{
  "success": false,
  "message": "Production header not found."
}
```

### Invoice Lines
- Input: Tidak ada (GET)
- `GET /api/invoice-lines` (200)
```json
{
  "success": true,
  "data": [
    {
      "bp_code": "CUST-01",
      "bp_name": "PT Contoh Pelanggan",
      "sales_order": "SO-10001",
      "so_date": "2025-09-28",
      "so_line": 1,
      "so_sequence": 1,
      "customer_po": "PO-777",
      "shipment": "SHP-555",
      "shipment_line": 1,
      "delivery_date": "2025-09-30",
      "receipt": "RCPT-123",
      "receipt_line": 1,
      "receipt_date": "2025-10-01",
      "part_no": "P-001",
      "old_partno": "OLD-001",
      "product_type": "FG",
      "cust_partno": "CP-001",
      "cust_partname": "Part A",
      "item_group": "GRP1",
      "delivered_qty": 100,
      "unit": "PCS",
      "shipment_reference": "REF-1",
      "status": "DELIVERED",
      "shipment_status": "DONE",
      "invoice_no": "INV-888",
      "inv_line": 1,
      "invoice_date": "2025-10-02",
      "invoice_qty": 100,
      "currency": "IDR",
      "price": 10000,
      "amount": 1000000,
      "price_hc": 10000,
      "amount_hc": 1000000,
      "inv_stat": "OPEN",
      "invoice_status": "OPEN",
      "dlv_log_date": "2025-10-02"
    }
  ],
  "message": "Sales order invoice lines retrieved successfully."
}
```

- `GET /api/invoice-lines/{id}` (200)
```json
{
  "success": true,
  "data": {
    "bp_code": "CUST-01",
    "bp_name": "PT Contoh Pelanggan",
    "sales_order": "SO-10001",
    "so_date": "2025-09-28",
    "so_line": 1,
    "so_sequence": 1,
    "customer_po": "PO-777",
    "shipment": "SHP-555",
    "shipment_line": 1,
    "delivery_date": "2025-09-30",
    "receipt": "RCPT-123",
    "receipt_line": 1,
    "receipt_date": "2025-10-01",
    "part_no": "P-001",
    "old_partno": "OLD-001",
    "product_type": "FG",
    "cust_partno": "CP-001",
    "cust_partname": "Part A",
    "item_group": "GRP1",
    "delivered_qty": 100,
    "unit": "PCS",
    "shipment_reference": "REF-1",
    "status": "DELIVERED",
    "shipment_status": "DONE",
    "invoice_no": "INV-888",
    "inv_line": 1,
    "invoice_date": "2025-10-02",
    "invoice_qty": 100,
    "currency": "IDR",
    "price": 10000,
    "amount": 1000000,
    "price_hc": 10000,
    "amount_hc": 1000000,
    "inv_stat": "OPEN",
    "invoice_status": "OPEN",
    "dlv_log_date": "2025-10-02"
  },
  "message": "Sales order invoice line retrieved successfully."
}
```

- `GET /api/invoice-lines/{id}` (404)
```json
{
  "success": false,
  "message": "Sales order invoice line not found."
}
```

### Receipt Purchases
- Input: Tidak ada (GET)
- `GET /api/receipt-purchases` (200)
```json
{
  "success": true,
  "data": [
    {
      "po_no": "PO-2025-0001",
      "bp_id": "SUP-01",
      "bp_name": "PT Contoh Supplier",
      "currency": "IDR",
      "po_type": "STD",
      "po_reference": "REF-PO",
      "po_line": 1,
      "po_sequence": 1,
      "po_receipt_sequence": 1,
      "actual_receipt_date": "2025-10-02",
      "actual_receipt_year": 2025,
      "actual_receipt_period": 10,
      "receipt_no": "GR-1001",
      "receipt_line": 1,
      "gr_no": "GR-1001",
      "packing_slip": "PK-001",
      "item_no": "RM-01",
      "ics_code": "ICS-1",
      "ics_part": "ICS-P",
      "part_no": "RM-01",
      "item_desc": "Raw Material 01",
      "item_group": "RM",
      "item_type": "RAW",
      "item_type_desc": "Raw Material",
      "request_qty": 100,
      "actual_receipt_qty": 100,
      "approve_qty": 100,
      "unit": "KG",
      "receipt_amount": 5000000,
      "receipt_unit_price": 50000,
      "is_final_receipt": false,
      "is_confirmed": true,
      "inv_doc_no": "BILL-01",
      "inv_doc_date": "2025-10-05",
      "inv_qty": 100,
      "inv_amount": 5000000,
      "inv_supplier_no": "SUP-01",
      "inv_due_date": "2025-11-05",
      "payment_doc": null,
      "payment_doc_date": null
    }
  ],
  "message": "Receipt purchases retrieved successfully."
}
```

- `GET /api/receipt-purchases/{id}` (200)
```json
{
  "success": true,
  "data": {
    "po_no": "PO-2025-0001",
    "bp_id": "SUP-01",
    "bp_name": "PT Contoh Supplier",
    "currency": "IDR",
    "po_type": "STD",
    "po_reference": "REF-PO",
    "po_line": 1,
    "po_sequence": 1,
    "po_receipt_sequence": 1,
    "actual_receipt_date": "2025-10-02",
    "actual_receipt_year": 2025,
    "actual_receipt_period": 10,
    "receipt_no": "GR-1001",
    "receipt_line": 1,
    "gr_no": "GR-1001",
    "packing_slip": "PK-001",
    "item_no": "RM-01",
    "ics_code": "ICS-1",
    "ics_part": "ICS-P",
    "part_no": "RM-01",
    "item_desc": "Raw Material 01",
    "item_group": "RM",
    "item_type": "RAW",
    "item_type_desc": "Raw Material",
    "request_qty": 100,
    "actual_receipt_qty": 100,
    "approve_qty": 100,
    "unit": "KG",
    "receipt_amount": 5000000,
    "receipt_unit_price": 50000,
    "is_final_receipt": false,
    "is_confirmed": true,
    "inv_doc_no": "BILL-01",
    "inv_doc_date": "2025-10-05",
    "inv_qty": 100,
    "inv_amount": 5000000,
    "inv_supplier_no": "SUP-01",
    "inv_due_date": "2025-11-05",
    "payment_doc": null,
    "payment_doc_date": null
  },
  "message": "Receipt purchase retrieved successfully."
}
```

- `GET /api/receipt-purchases/{id}` (404)
```json
{
  "success": false,
  "message": "Receipt purchase not found."
}
```

### Sync Logs (Resources)
- Input: Tidak ada (GET)
- `GET /api/sync-logs` (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 41,
      "sync_type": "erp",
      "status": "completed",
      "started_at": "2025-10-01T08:00:00Z",
      "completed_at": "2025-10-01T08:05:00Z",
      "total_records": 800,
      "success_records": 800,
      "failed_records": 0,
      "error_message": null,
      "created_at": "2025-10-01T08:00:00Z",
      "updated_at": "2025-10-01T08:05:00Z"
    }
  ],
  "message": "Synchronization logs retrieved successfully."
}
```

- `GET /api/sync-logs/{id}` (200)
```json
{
  "success": true,
  "data": {
    "id": 41,
    "sync_type": "erp",
    "status": "completed",
    "started_at": "2025-10-01T08:00:00Z",
    "completed_at": "2025-10-01T08:05:00Z",
    "total_records": 800,
    "success_records": 800,
    "failed_records": 0,
    "error_message": null,
    "created_at": "2025-10-01T08:00:00Z",
    "updated_at": "2025-10-01T08:05:00Z"
  },
  "message": "Synchronization log retrieved successfully."
}
```

- `GET /api/sync-logs/{id}` (404)
```json
{
  "success": false,
  "message": "Synchronization log not found."
}
```

### Sync API
- `POST /api/sync/start` (Input: tidak ada)
- Output 200
```json
{
  "success": true,
  "message": "Manual sync started successfully",
  "sync_log_id": 42,
  "status": "running"
}
```
- Output 409 (sudah berjalan)
```json
{
  "success": false,
  "message": "Sync is already running. Please wait for it to complete.",
  "running_sync_id": 41
}
```

- `GET /api/sync/status?sync_id={id}` (200)
```json
{
  "success": true,
  "data": {
    "id": 42,
    "sync_type": "erp",
    "status": "running",
    "started_at": "2025-10-02T09:00:00Z",
    "completed_at": null,
    "total_records": 1000,
    "success_records": 400,
    "failed_records": 2,
    "error_message": null,
    "duration": 120,
    "success_rate": 40.0
  }
}
```

- `GET /api/sync/status` tanpa `sync_id` dan belum ada log (200)
```json
{
  "success": true,
  "data": null,
  "message": "No sync logs found"
}
```

- `GET /api/sync/logs?per_page=10` (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 41,
      "sync_type": "erp",
      "status": "completed",
      "started_at": "2025-10-01T08:00:00Z",
      "completed_at": "2025-10-01T08:05:00Z",
      "total_records": 800,
      "success_records": 800,
      "failed_records": 0,
      "error_message": null,
      "created_at": "2025-10-01T08:00:00Z",
      "updated_at": "2025-10-01T08:05:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25,
    "from": 1,
    "to": 10
  }
}
```

- `GET /api/sync/statistics?days=7` (200)
```json
{
  "success": true,
  "data": {
    "statistics": {
      "total_syncs": 12,
      "successful_syncs": 10,
      "failed_syncs": 1,
      "running_syncs": 1,
      "avg_records": 900,
      "avg_duration": 260
    },
    "recent_logs": [
      { "id": 42, "sync_type": "erp", "status": "running", "started_at": "2025-10-02T09:00:00Z", "completed_at": null, "total_records": 1000, "success_records": 400, "failed_records": 2 }
    ]
  }
}
```

- `POST /api/sync/cancel` (Input: tidak ada) (200)
```json
{
  "success": true,
  "message": "Sync cancelled successfully",
  "sync_log_id": 42
}
```
- `POST /api/sync/cancel` saat tidak ada proses (404)
```json
{
  "success": false,
  "message": "No running sync found"
}
```

### Endpoint Terproteksi: /api/user
- Input: Header `Authorization: Bearer {token}`
- `GET /api/user` (200)
```json
{
  "id": 1,
  "name": "Admin",
  "email": "admin@example.com",
  "email_verified_at": null,
  "created_at": "2025-09-01T00:00:00Z",
  "updated_at": "2025-09-15T00:00:00Z"
}
```

## Kode Status
- `200` Berhasil
- `404` Tidak ditemukan (mis. detail id tidak ada / log tidak ditemukan)
- `409` Konflik (sinkronisasi sudah berjalan)
- `500` Error server

## Catatan Implementasi
- Rute `warehouse-order-lines` diregistrasikan di `routes/api.php`, tetapi `WarehouseOrderLineController` tidak ditemukan di `app/Http/Controllers/Api/`. Pastikan controller tersedia agar rute aktif.
- Untuk skema field detail tiap model (`StockByWh`, `WarehouseOrder`, `ProdHeader`, `SoInvoiceLine`, `ReceiptPurchase`, `SyncLog`), lihat definisi model terkait di `app/Models/`.

## Tips Penggunaan
- Gunakan query parameter sesuai yang didukung (lihat bagian Sync).
- Pastikan menambahkan header `Accept: application/json` di semua request.
- Untuk lingkungan lokal Laravel default: base URL biasanya `http://localhost:8000/api` (tergantung konfigurasi server Anda).
