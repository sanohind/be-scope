# ERP Sync System Setup

Sistem ini menyediakan fitur untuk sinkronisasi data ERP ke database lokal dengan fitur sync otomatis per jam dan sync manual melalui API.

## Konfigurasi Database ERP

Tambahkan konfigurasi berikut ke file `.env`:

```env
# ERP Database Configuration
DB_CONNECTION2=sqlsrv
DB_HOST2=10.1.10.52
DB_PORT2=1433
DB_DATABASE2=soi107
DB_USERNAME2=portal
DB_PASSWORD2=PASSWORD

# Timezone Configuration (WIB)
APP_TIMEZONE=Asia/Jakarta
```

## API Endpoints

### 1. Start Manual Sync

**POST** `/api/sync/start`

Memulai sync manual dari ERP ke database lokal.

**Response:**

```json
{
    "success": true,
    "message": "Manual sync started successfully",
    "sync_log_id": 1,
    "status": "running"
}
```

### 2. Get Sync Status

**GET** `/api/sync/status?sync_id=1`

Mendapatkan status sync terbaru atau sync dengan ID tertentu.

**Response:**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "sync_type": "manual",
        "status": "completed",
        "started_at": "2025-10-22T10:00:00.000000Z",
        "completed_at": "2025-10-22T10:05:00.000000Z",
        "total_records": 1000,
        "success_records": 950,
        "failed_records": 50,
        "error_message": null,
        "duration": 300,
        "success_rate": 95.0
    }
}
```

### 3. Get Sync Logs

**GET** `/api/sync/logs?per_page=10&status=completed&sync_type=manual`

Mendapatkan daftar log sync dengan pagination dan filter.

**Query Parameters:**

-   `per_page`: Jumlah data per halaman (default: 10)
-   `status`: Filter berdasarkan status (running, completed, failed, cancelled)
-   `sync_type`: Filter berdasarkan tipe sync (manual, scheduled)

**Response:**

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 10,
        "total": 50,
        "from": 1,
        "to": 10
    }
}
```

### 4. Get Sync Statistics

**GET** `/api/sync/statistics?days=7`

Mendapatkan statistik sync dalam periode tertentu.

**Query Parameters:**

-   `days`: Jumlah hari untuk statistik (default: 7)

**Response:**

```json
{
    "success": true,
    "data": {
        "statistics": {
            "total_syncs": 24,
            "successful_syncs": 22,
            "failed_syncs": 2,
            "running_syncs": 0,
            "avg_records": 1250.5,
            "avg_duration": 180.5
        },
        "recent_logs": [...]
    }
}
```

### 5. Cancel Running Sync

**POST** `/api/sync/cancel`

Membatalkan sync yang sedang berjalan.

**Response:**

```json
{
    "success": true,
    "message": "Sync cancelled successfully",
    "sync_log_id": 1
}
```

## Scheduler Setup

Sistem sync otomatis sudah dikonfigurasi untuk berjalan setiap jam. Untuk mengaktifkan scheduler, jalankan:

```bash
# Untuk development
php artisan schedule:work

# Untuk production, tambahkan ke crontab:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Catatan Penting:

-   Scheduler akan berjalan **setiap jam pada menit ke-0** (contoh: 10:00, 11:00, 12:00)
-   Pesan "No scheduled commands are ready to run" adalah **NORMAL** jika belum waktunya eksekusi
-   Untuk testing, Anda bisa uncomment baris `everyFiveMinutes()` di `app/Console/Kernel.php`
-   Scheduler **HARUS** berjalan bersamaan dengan `php artisan serve` (gunakan terminal terpisah)

## Tabel yang Disinkronkan

1. **stockbywh** - Data stock berdasarkan warehouse
2. **view_warehouse_order** - Data warehouse order header
3. **view_warehouse_order_line** - Data warehouse order line
4. **so_invoice_line** - Data shipment/invoice
5. **view_prod_header** - Data production order
6. **data_receipt_purchase** - Data receipt purchase order

## Monitoring

-   Log sync tersimpan di tabel `sync_logs`
-   Log file tersimpan di `storage/logs/sync.log`
-   Setiap sync mencatat total records, success records, dan failed records
-   Durasi sync dan success rate dapat dimonitor melalui API

## Error Handling

-   Jika sync gagal, status akan diupdate menjadi 'failed' dengan error message
-   Sync yang sedang berjalan tidak dapat dimulai sync baru
-   Log error tersimpan untuk debugging

## Testing

Untuk test API endpoints, gunakan tools seperti Postman atau curl:

```bash
# Start manual sync
curl -X POST http://localhost:8000/api/sync/start

# Get sync status
curl -X GET http://localhost:8000/api/sync/status

# Get sync logs
curl -X GET "http://localhost:8000/api/sync/logs?per_page=5"

# Get statistics
curl -X GET "http://localhost:8000/api/sync/statistics?days=7"
```
