# Sync Log Fix Documentation

## Masalah
Sync log tidak tercatat di tabel `sync_logs` setelah menjalankan command `php artisan sync:erp-data --manual`.

## Penyebab Root Cause

### 1. **Query Logging Dinonaktifkan Terlalu Dini**
```php
// SEBELUM (❌ SALAH)
DB::connection()->disableQueryLog();        // Default connection dinonaktifkan
DB::connection('erp')->disableQueryLog();   

$syncLog = SyncLog::create([...]);          // Tidak bisa insert karena query log dinonaktifkan
```

Ketika `DB::connection()->disableQueryLog()` dipanggil **sebelum** membuat `SyncLog`, Eloquent tidak dapat menjalankan query INSERT ke database.

### 2. **Default Connection Terpengaruh**
Menonaktifkan query log untuk **default connection** mempengaruhi semua operasi database pada aplikasi, termasuk model `SyncLog` yang seharusnya tetap bisa melakukan operasi CRUD.

## Solusi yang Diterapkan

### 1. **Urutan Operasi Diperbaiki**
```php
// SESUDAH (✅ BENAR)
// 1. Buat sync log DULU
$syncLog = SyncLog::create([
    'sync_type' => $syncType,
    'status' => 'running',
    'started_at' => now(),
    'total_records' => 0,
    'success_records' => 0,
    'failed_records' => 0,
]);

// 2. BARU nonaktifkan query log untuk ERP saja
DB::connection('erp')->disableQueryLog();
```

### 2. **Hanya Nonaktifkan Query Log ERP**
```php
// Hanya koneksi ERP yang dinonaktifkan
DB::connection('erp')->disableQueryLog();

// Default connection tetap aktif untuk SyncLog
// DB::connection()->disableQueryLog(); // ❌ DIHAPUS
```

### 3. **Update Method Diperbaiki**
```php
// SEBELUM (kadang tidak reliable dengan query log dinonaktifkan)
$syncLog->update([...]);

// SESUDAH (lebih reliable)
$syncLog->status = 'completed';
$syncLog->completed_at = now();
$syncLog->total_records = $totalRecords;
$syncLog->success_records = $successRecords;
$syncLog->failed_records = $failedRecords;
$syncLog->save();
```

### 4. **Model Configuration Ditambahkan**
```php
class SyncLog extends Model
{
    protected $table = 'sync_logs';      // Explicit table name
    public $timestamps = true;           // Ensure timestamps enabled
    
    protected $fillable = [...];
    protected $casts = [...];
}
```

### 5. **Progress Indicator Ditambahkan**
```php
$this->info("Sync Log ID: {$syncLog->id}");  // Show log ID for verification
```

## Hasil Testing

### Test Command
```bash
php artisan test:sync-log
```

**Output:**
```
✓ Created sync log with ID: 28
✓ Updated sync log ID: 28
✓ Found sync log:
+-----------------+---------------------+
| Field           | Value               |
+-----------------+---------------------+
| ID              | 28                  |
| Sync Type       | test                |
| Status          | completed           |
| Total Records   | 100                 |
| Success Records | 100                 |
+-----------------+---------------------+

All sync logs:
+----+-----------+-----------+---------------+---------------------+---------------------+
| ID | Type      | Status    | Total Records | Started At          | Completed At        |
+----+-----------+-----------+---------------+---------------------+---------------------+
| 27 | manual    | completed | 338443        | 2025-11-03 07:59:56 | 2025-11-03 08:15:48 |
+----+-----------+-----------+---------------+---------------------+---------------------+

✓ SyncLog test completed successfully!
```

### Actual Sync Command
```bash
php artisan sync:erp-data --manual
```

**Hasil:**
- ✅ **338,443 records** berhasil di-sync
- ✅ **Log ID 27** tercatat dengan lengkap
- ✅ Tidak ada memory error
- ✅ Durasi: ~15 menit (07:59:56 - 08:15:48)

## Verification

### 1. Cek Log Terbaru
```bash
php artisan tinker
>>> App\Models\SyncLog::latest()->first()
```

### 2. Cek Semua Log
```bash
php artisan tinker
>>> App\Models\SyncLog::orderBy('id', 'desc')->take(5)->get()
```

### 3. Cek Log Spesifik
```bash
php artisan tinker
>>> App\Models\SyncLog::find(27)
```

## Testing Command

Command baru untuk testing SyncLog:
```bash
php artisan test:sync-log
```

Command ini akan:
1. ✅ Membuat test sync log
2. ✅ Update test sync log
3. ✅ Membaca dari database
4. ✅ Menampilkan semua sync logs
5. ✅ Menghapus test log

## File yang Dimodifikasi

1. **app/Console/Commands/SyncErpData.php**
   - Pindahkan pembuatan SyncLog sebelum disableQueryLog()
   - Hanya nonaktifkan query log untuk koneksi ERP
   - Ganti update() dengan save()
   - Tambahkan output Sync Log ID

2. **app/Models/SyncLog.php**
   - Tambahkan explicit table name
   - Tambahkan public $timestamps = true

3. **app/Console/Commands/TestSyncLog.php** (NEW)
   - Command untuk testing SyncLog functionality

## Catatan Penting

### Memory Optimization Tetap Aktif
```php
ini_set('memory_limit', '1024M');              // ✅ Tetap
DB::connection('erp')->disableQueryLog();      // ✅ Tetap (hanya ERP)
```

### Chunking Tetap Berjalan
```php
DB::connection('erp')->table('stockbywh')
    ->orderBy('partno')
    ->chunk(500, function ($records) {
        // Process in batches
        DB::table('stockbywh')->insert($batch);
        gc_collect_cycles();
    });
```

### Log Cleanup
Log lama dengan status 'running' (dari sync yang gagal sebelumnya) telah dibersihkan dan diubah statusnya menjadi 'failed'.

## Best Practices

1. **Selalu Cek Log ID:**
   ```
   Sync Log ID: 27
   ```
   Pastikan ID muncul di output untuk verifikasi.

2. **Monitor Status:**
   ```bash
   php artisan tinker --execute="App\Models\SyncLog::latest()->first()"
   ```

3. **Cleanup Log Lama:**
   ```bash
   php artisan tinker --execute="App\Models\SyncLog::where('status', 'running')->where('created_at', '<', now()->subHours(24))->delete()"
   ```

## Summary

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| **Log Tercatat** | ❌ Tidak | ✅ Ya |
| **Memory Usage** | ❌ Exhausted | ✅ Optimal |
| **Query Log Default** | ❌ Dinonaktifkan | ✅ Aktif |
| **Query Log ERP** | ❌ Dinonaktifkan | ✅ Dinonaktifkan |
| **Progress Tracking** | ❌ Tidak ada ID | ✅ Ada ID |
| **Data Integrity** | ❌ Tidak reliable | ✅ Reliable |

## Troubleshooting

### Jika Log Masih Tidak Tercatat

1. **Cek koneksi database:**
   ```bash
   php artisan tinker --execute="DB::connection()->getPdo()"
   ```

2. **Cek tabel sync_logs ada:**
   ```bash
   php artisan tinker --execute="Schema::hasTable('sync_logs')"
   ```

3. **Jalankan test command:**
   ```bash
   php artisan test:sync-log
   ```

4. **Cek permissions:**
   Pastikan user database memiliki permission INSERT, UPDATE di tabel sync_logs.

## Tanggal Fix
3 November 2024

## Developer Notes
Fix ini memastikan bahwa:
- ✅ Memory optimization tetap berjalan
- ✅ Chunking tetap efisien
- ✅ Sync log tercatat dengan benar
- ✅ Default database operations tidak terpengaruh
- ✅ Hanya koneksi ERP yang query log-nya dinonaktifkan
