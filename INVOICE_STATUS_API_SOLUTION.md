# Solusi Error 404 - Invoice Status Distribution API

## Masalah yang Ditemukan

### 1. **URL yang Salah**
Error 404 terjadi karena kemungkinan URL yang diakses tidak benar atau server tidak berjalan dengan baik.

### 2. **Chart Numbering Mismatch** ✅ FIXED
- Dokumentasi menyebutkan **Chart 4.6** untuk Invoice Status Distribution
- Kode controller sebelumnya menggunakan **Chart 4.7**
- **Sudah diperbaiki** - sekarang sudah sesuai dengan dokumentasi

## Solusi

### **Langkah 1: Pastikan Server Laravel Berjalan**

Pilih salah satu cara menjalankan server:

#### **Opsi A: Menggunakan Laravel Development Server (Recommended)**
```bash
php artisan serve
```

Jika berhasil, server akan berjalan di:
- `http://localhost:8000`
- `http://127.0.0.1:8000`

#### **Opsi B: Menggunakan XAMPP/Apache**
Jika menggunakan XAMPP, pastikan:
1. Apache sudah running
2. Project ada di folder `htdocs`
3. Akses melalui: `http://localhost/be-scope/public/`

### **Langkah 2: Clear Cache Laravel**

Jalankan perintah berikut untuk clear semua cache:

```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### **Langkah 3: Verifikasi Route Terdaftar**

Cek apakah route sudah terdaftar:

```bash
php artisan route:list --path=dashboard/sales
```

Pastikan ada endpoint:
```
GET|HEAD  api/dashboard/sales/invoice-status-distribution  Api\Dashboard4Controller@invoiceStatusDistribution
```

## URL yang Benar

### **Jika menggunakan `php artisan serve`:**
```
http://localhost:8000/api/dashboard/sales/invoice-status-distribution
```

### **Jika menggunakan XAMPP/Apache:**
```
http://localhost/be-scope/public/api/dashboard/sales/invoice-status-distribution
```

### **Dengan Virtual Host (jika sudah dikonfigurasi):**
```
http://be-scope.local/api/dashboard/sales/invoice-status-distribution
```

## Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `group_by` | string | `monthly` | Group by: `customer`, `daily`, `monthly`, `yearly` |
| `date_from` | date | null | Start date filter (YYYY-MM-DD) |
| `date_to` | date | null | End date filter (YYYY-MM-DD) |

## Contoh Penggunaan

### **1. Group by Monthly (Default)**
```http
GET /api/dashboard/sales/invoice-status-distribution
```

### **2. Group by Customer**
```http
GET /api/dashboard/sales/invoice-status-distribution?group_by=customer
```

### **3. Filter dengan Date Range**
```http
GET /api/dashboard/sales/invoice-status-distribution?date_from=2024-01-01&date_to=2024-12-31
```

### **4. Group by Customer dengan Date Filter**
```http
GET /api/dashboard/sales/invoice-status-distribution?group_by=customer&date_from=2024-01-01&date_to=2024-12-31
```

### **5. Group by Daily**
```http
GET /api/dashboard/sales/invoice-status-distribution?group_by=daily&date_from=2024-11-01&date_to=2024-11-30
```

### **6. Group by Yearly**
```http
GET /api/dashboard/sales/invoice-status-distribution?group_by=yearly
```

## Format Response

### **Success Response (HTTP 200)**

```json
{
  "data": [
    {
      "category": "2024-01",
      "invoice_status": "Paid",
      "count": 150
    },
    {
      "category": "2024-01",
      "invoice_status": "Outstanding",
      "count": 45
    },
    {
      "category": "2024-01",
      "invoice_status": "Overdue",
      "count": 12
    },
    {
      "category": "2024-01",
      "invoice_status": "Cancelled",
      "count": 3
    },
    {
      "category": "2024-02",
      "invoice_status": "Paid",
      "count": 165
    },
    ...
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": null,
    "date_to": null
  }
}
```

### **Response Ketika Group by Customer**

```json
{
  "data": [
    {
      "category": "PT. Customer A",
      "invoice_status": "Paid",
      "count": 85
    },
    {
      "category": "PT. Customer A",
      "invoice_status": "Outstanding",
      "count": 25
    },
    {
      "category": "PT. Customer B",
      "invoice_status": "Paid",
      "count": 120
    },
    ...
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2024-01-01",
    "date_to": "2024-12-31"
  }
}
```

## Testing dengan cURL

### **Windows PowerShell:**
```powershell
# Test basic endpoint
curl http://localhost:8000/api/dashboard/sales/invoice-status-distribution

# Test with customer grouping
curl "http://localhost:8000/api/dashboard/sales/invoice-status-distribution?group_by=customer"

# Test with date filter
curl "http://localhost:8000/api/dashboard/sales/invoice-status-distribution?date_from=2024-01-01&date_to=2024-12-31"
```

### **Testing dengan Postman/Thunder Client:**
1. Method: `GET`
2. URL: `http://localhost:8000/api/dashboard/sales/invoice-status-distribution`
3. Query Params:
   - `group_by`: `customer` atau `monthly` atau `daily` atau `yearly`
   - `date_from`: `2024-01-01` (optional)
   - `date_to`: `2024-12-31` (optional)

## Chart Specification (Chart 4.6)

**Tipe Visualisasi:** Stacked Bar Chart (100%)

**Data Source:** `so_invoice_line` atau `so_invoice_line_2` (tergantung tahun)

**Dimensi:**
- **X-axis:** Time Period (monthly) atau Customer
- **Y-axis:** Percentage
- **Stack:** Invoice Status (`invoice_status`)

**Status Categories:**
- ✅ **Paid** (Green)
- ⚠️ **Outstanding** (Yellow)
- 🔴 **Overdue** (Red)
- ⚪ **Cancelled** (Gray)

## Logic Pemilihan Data Source

Controller menggunakan logic berikut:

1. **Jika `date_from` dan `date_to` kedua nya >= 2025:** Menggunakan `SoInvoiceLine` (so_invoice_line)
2. **Jika `date_from` dan `date_to` kedua nya < 2025:** Menggunakan `SoInvoiceLine2` (so_invoice_line_2)
3. **Jika range melintasi tahun 2025 atau tidak ada filter:** Default ke `SoInvoiceLine2`

## Troubleshooting

### **Error: "Connection refused" atau "Timeout"**
**Solusi:** Server Laravel tidak berjalan. Jalankan:
```bash
php artisan serve
```

### **Error: "404 Not Found"**
**Solusi:**
1. Clear cache:
   ```bash
   php artisan route:clear
   php artisan config:clear
   php artisan cache:clear
   ```
2. Pastikan URL sudah benar (include `/api/` prefix)
3. Pastikan port sesuai dengan server yang digunakan

### **Error: "500 Internal Server Error"**
**Solusi:**
1. Check Laravel log: `storage/logs/laravel.log`
2. Pastikan database connection sudah benar
3. Pastikan table `so_invoice_line` atau `so_invoice_line_2` ada

### **Response Data Kosong**
**Solusi:**
1. Pastikan ada data di database untuk periode yang diminta
2. Coba tanpa filter date terlebih dahulu
3. Check filter yang digunakan apakah benar

## Perubahan yang Sudah Dilakukan

✅ **Chart numbering diperbaiki:**
- Chart 4.6: Invoice Status Distribution (sesuai dokumentasi)
- Chart 4.7: Delivery Performance
- Chart 4.7: Sales Order Fulfillment
- Chart 4.8: Top Selling Products
- Chart 4.9: Revenue by Currency
- Chart 4.10: Monthly Sales Comparison

✅ **Dokumentasi di controller sudah dilengkapi** dengan detail spesifikasi chart

✅ **Test script dibuat:** `test_invoice_status_api.php`

## Next Steps

1. **Jalankan server Laravel:**
   ```bash
   php artisan serve
   ```

2. **Test endpoint dengan browser atau Postman:**
   ```
   http://localhost:8000/api/dashboard/sales/invoice-status-distribution
   ```

3. **Implementasi di Frontend:**
   - Buat Stacked Bar Chart (100%)
   - Gunakan color coding sesuai status:
     - Paid: Green (#4CAF50 atau #10B981)
     - Outstanding: Yellow (#FFC107 atau #FBBF24)
     - Overdue: Red (#F44336 atau #EF4444)
     - Cancelled: Gray (#9E9E9E atau #6B7280)

## Contact & Support

Jika masih mengalami masalah, check:
1. Laravel log: `storage/logs/laravel.log`
2. Apache/XAMPP error log
3. Browser console untuk CORS atau network errors
