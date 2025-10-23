# Cara Menjalankan Sistem Sync ERP

## 📌 **Penjelasan Pesan Scheduler**

### ✅ Pesan Normal:

```
INFO  No scheduled commands are ready to run.
```

**Ini BUKAN error!** Artinya scheduler sudah berjalan dengan benar, tetapi command belum waktunya dieksekusi.

### ✅ Pesan Ketika Command Berjalan:

```
INFO  Running scheduled command: sync:erp-data
```

## 🚀 **Cara Menjalankan Sync**

### **Option 1: Sync Manual via Command**

```bash
# Jalankan sync manual langsung
php artisan sync:erp-data --manual
```

### **Option 2: Sync Manual via API**

```bash
# Start sync via API
curl -X POST http://localhost:8000/api/sync/start

# Check status
curl -X GET http://localhost:8000/api/sync/status
```

### **Option 3: Sync Otomatis (Production)**

#### **Step 1: Jalankan Web Server**

```bash
# Terminal 1
php artisan serve
```

#### **Step 2: Jalankan Scheduler**

```bash
# Terminal 2
php artisan schedule:work
```

**Catatan:** Command akan berjalan **setiap jam pada menit ke-0** (10:00, 11:00, 12:00, dst)

### **Option 4: Sync Otomatis untuk Testing**

Jika ingin test sync otomatis setiap 5 menit:

1. Edit file `app/Console/Kernel.php`
2. Uncomment baris `everyFiveMinutes()`:

```php
// Run every 5 minutes for testing (uncomment to test)
$schedule->command('sync:erp-data')
         ->everyFiveMinutes()
         ->withoutOverlapping()
         ->runInBackground()
         ->appendOutputTo(storage_path('logs/sync.log'));
```

3. Comment baris `hourly()`:

```php
// Run ERP sync every hour (for production)
// $schedule->command('sync:erp-data')
//          ->hourly()
//          ->withoutOverlapping()
//          ->runInBackground()
//          ->appendOutputTo(storage_path('logs/sync.log'));
```

4. Jalankan scheduler:

```bash
php artisan schedule:work
```

Sekarang sync akan berjalan setiap 5 menit.

## 📊 **Monitoring Sync**

### **1. Via API:**

```bash
# Get latest sync status
curl -X GET http://localhost:8000/api/sync/status

# Get sync logs
curl -X GET "http://localhost:8000/api/sync/logs?per_page=10"

# Get sync statistics
curl -X GET "http://localhost:8000/api/sync/statistics?days=7"
```

### **2. Via Log File:**

```bash
# Monitor log real-time
tail -f storage/logs/sync.log

# Check Laravel log
tail -f storage/logs/laravel.log
```

### **3. Via Database:**

```bash
php artisan tinker --execute="App\Models\SyncLog::latest()->take(5)->get();"
```

## ⚙️ **Setup Production (Crontab)**

Untuk production server, tambahkan ke crontab:

```bash
# Edit crontab
crontab -e

# Tambahkan baris berikut:
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Ini akan menjalankan scheduler setiap menit, dan Laravel akan menentukan command mana yang perlu dieksekusi.

## 🔍 **Troubleshooting**

### **Masalah: "No scheduled commands are ready to run"**

-   ✅ Ini NORMAL! Scheduler sudah berjalan dengan benar
-   ✅ Command akan berjalan sesuai jadwal (setiap jam atau setiap 5 menit)
-   ✅ Tunggu sampai waktu eksekusi tiba

### **Masalah: Scheduler tidak berjalan**

1. Cek apakah command terdaftar:
    ```bash
    php artisan list | findstr sync
    ```
2. Cek apakah ada error:
    ```bash
    php artisan schedule:run --verbose
    ```

### **Masalah: Database connection failed**

1. Cek konfigurasi `.env`:
    ```env
    DB_CONNECTION2=sqlsrv
    DB_HOST2=10.1.10.52
    DB_PORT2=1433
    DB_DATABASE2=soi107
    DB_USERNAME2=portal
    DB_PASSWORD2=San0h!nd
    ```
2. Test koneksi:
    ```bash
    php artisan tinker --execute="DB::connection('erp')->getPdo(); echo 'Connected!';"
    ```

### **Masalah: Waktu di sync_logs tidak sesuai (UTC vs WIB)**

1. Cek timezone aplikasi:
    ```bash
    php artisan tinker --execute="echo 'Timezone: ' . config('app.timezone'); echo 'Current time: ' . now()->format('Y-m-d H:i:s');"
    ```
2. Jika masih UTC, update `config/app.php`:
    ```php
    'timezone' => 'Asia/Jakarta',
    ```
3. Clear cache:
    ```bash
    php artisan config:clear
    ```
4. Restart scheduler:
    ```bash
    # Stop scheduler (Ctrl+C)
    # Jalankan lagi
    php artisan schedule:work
    ```

## 📝 **Checklist Setup**

-   [ ] Konfigurasi `.env` sudah benar
-   [ ] Migration `sync_logs` sudah dijalankan
-   [ ] Command `sync:erp-data` sudah terdaftar
-   [ ] Koneksi database ERP sudah berhasil
-   [ ] Scheduler sudah berjalan (`php artisan schedule:work`)
-   [ ] Web server sudah berjalan (`php artisan serve`)

## 💡 **Tips**

1. **Untuk development:** Gunakan `everyFiveMinutes()` untuk testing
2. **Untuk production:** Gunakan `hourly()` dan setup crontab
3. **Monitoring:** Selalu cek log file dan database untuk memastikan sync berjalan dengan baik
4. **Performance:** Jika data besar, pertimbangkan menggunakan queue untuk sync
