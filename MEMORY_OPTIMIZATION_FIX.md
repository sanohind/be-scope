# Memory Optimization Fix untuk Sync ERP Data

## Masalah
Terjadi error **"Allowed memory size of 536870912 bytes exhausted"** saat menjalankan `php artisan sync:erp-data --manual`.

## Penyebab
Command ini memuat **seluruh data dari ERP ke memory sekaligus** menggunakan `->get()`, yang menyebabkan memory limit (512MB) terlampaui ketika data sangat besar.

## Solusi yang Diterapkan

### 1. **Chunking Data** (Batch Processing)
Mengganti `->get()` dengan `->chunk(500)` pada semua 6 sync methods:
- `syncStockByWh()`
- `syncWarehouseOrder()`
- `syncWarehouseOrderLine()`
- `syncSoInvoiceLine()`
- `syncProdHeader()`
- `syncReceiptPurchase()`

**Sebelum:**
```php
$erpData = DB::connection('erp')->table('stockbywh')->get();
foreach ($erpData as $record) {
    DB::table('stockbywh')->insert([...]);
}
```

**Sesudah:**
```php
DB::connection('erp')->table('stockbywh')
    ->orderBy('partno')
    ->chunk(500, function ($records) use (&$success, &$failed, &$total) {
        $batch = [];
        foreach ($records as $record) {
            $batch[] = [...];
        }
        DB::table('stockbywh')->insert($batch);
        unset($batch);
        gc_collect_cycles();
    });
```

### 2. **Batch Insert**
Mengganti insert individual dengan batch insert untuk efisiensi yang lebih baik.

### 3. **Memory Management**
- Menambahkan `ini_set('memory_limit', '1024M')` di awal command
- Menonaktifkan query logging: `DB::connection()->disableQueryLog()`
- Menambahkan `gc_collect_cycles()` setelah setiap chunk untuk membersihkan memory
- Menambahkan `unset($batch)` untuk membebaskan memory

### 4. **Progress Indicator**
Menambahkan output progress untuk setiap batch yang diproses:
```
Processed 500 StockByWh records...
Processed 1,000 StockByWh records...
```

## Keuntungan

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| **Memory Usage** | Memuat semua data sekaligus | Memuat 500 records per batch |
| **Efisiensi Insert** | 1 query per record | 1 query per 500 records |
| **Error Handling** | Gagal semua jika 1 error | Hanya batch yang gagal |
| **Visibility** | Tidak ada progress | Progress setiap 500 records |
| **Memory Limit** | 512MB | 1024MB dengan cleanup |

## Cara Testing

1. **Jalankan sync command:**
   ```bash
   php artisan sync:erp-data --manual
   ```

2. **Monitor progress:**
   - Perhatikan output yang menampilkan jumlah records yang diproses
   - Command seharusnya berjalan tanpa memory error

3. **Verifikasi hasil:**
   ```bash
   # Check sync logs
   php artisan tinker
   >>> App\Models\SyncLog::latest()->first()
   ```

## Catatan Penting

- **Chunk Size:** Saat ini set ke 500 records per batch. Bisa disesuaikan jika diperlukan:
  - Lebih kecil (200-300) jika masih ada memory issue
  - Lebih besar (1000-2000) jika ingin lebih cepat dan memory cukup

- **Memory Limit:** Jika masih terjadi error, bisa tingkatkan di:
  - `SyncErpData.php`: `ini_set('memory_limit', '2048M')`
  - `php.ini`: `memory_limit = 2048M`

- **Performance:** Dengan chunking, sync mungkin sedikit lebih lambat tetapi jauh lebih stabil dan tidak akan crash.

## Troubleshooting

### Jika masih ada memory error:
1. Turunkan chunk size ke 200 atau 100
2. Tingkatkan memory limit di `php.ini`
3. Pastikan `gc_collect_cycles()` dipanggil

### Jika terlalu lambat:
1. Naikkan chunk size ke 1000
2. Pertimbangkan menambahkan index di tabel database
3. Pastikan koneksi ERP stabil

## File yang Dimodifikasi
- `app/Console/Commands/SyncErpData.php`

## Tanggal Fix
3 November 2024
