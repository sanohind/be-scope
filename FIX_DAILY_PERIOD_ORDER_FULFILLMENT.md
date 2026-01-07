# Fix: Daily Period Support untuk Sales Order Fulfillment

## Masalah yang Diperbaiki

Method `salesOrderFulfillment` sebelumnya **tidak mendukung period `daily`** dengan benar. Ketika user mengirim `period=daily`, data masih di-group per bulan (monthly) karena:

1. ❌ Validasi period hanya mengizinkan `monthly` dan `yearly`
2. ❌ Date format hardcoded untuk monthly/yearly saja
3. ❌ Tidak ada default date range yang period-aware

## Perubahan yang Dilakukan

### 1. **Menambahkan Support untuk Period `daily`**

**Sebelum:**
```php
// Validate period - only monthly and yearly allowed
if (!in_array($period, ['monthly', 'yearly'])) {
    $period = 'monthly';
}
```

**Sesudah:**
```php
// Validate period - daily, monthly and yearly allowed
if (!in_array($period, ['daily', 'monthly', 'yearly'])) {
    $period = 'monthly';
}
```

### 2. **Menggunakan Helper Method untuk Date Format**

**Sebelum (Hardcoded):**
```php
$dateFormat2 = $period === 'yearly'
    ? ($isSqlServer2 ? "CAST(YEAR(invoice_date) AS VARCHAR)" : "YEAR(invoice_date)")
    : ($isSqlServer2 ? "LEFT(CONVERT(VARCHAR(10), invoice_date, 23), 7)" : "DATE_FORMAT(invoice_date, '%Y-%m')");
```

**Sesudah (Dynamic):**
```php
// Use helper method for date format
$dateFormat2 = $this->getDateFormatByPeriod($period, 'invoice_date', $query2);
```

Sekarang `getDateFormatByPeriod` akan otomatis menangani:
- **daily**: `CAST(invoice_date AS DATE)` atau `DATE(invoice_date)`
- **monthly**: `LEFT(CONVERT(...), 7)` atau `DATE_FORMAT(..., '%Y-%m')`
- **yearly**: `CAST(YEAR(...) AS VARCHAR)` atau `YEAR(...)`

### 3. **Menambahkan Period-Aware Default Date Range**

**Sebelum:**
```php
// Determine date range - default to current year if not specified
$dateTo = $request->get('date_to', date('Y-12-31'));
$dateFrom = $request->get('date_from', date('Y-01-01'));
```

**Sesudah:**
```php
// Get default date range based on period
$defaults = $this->getDefaultDateRangeByPeriod($request);
$this->ensureDateRange($request, $defaults['start'], $defaults['end']);

// Get date range from request (now guaranteed to exist)
$dateFrom = $request->get('date_from');
$dateTo = $request->get('date_to');
```

Sekarang default disesuaikan dengan period:
- **daily**: 7 hari terakhir
- **monthly**: Bulan ini
- **yearly**: Tahun ini

---

## Testing

### Test 1: Daily Period (Default 7 Hari)
```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=daily"
```

**Expected Response:**
```json
{
  "data": [
    {"period": "2026-01-01", "delivered_qty": "10000.0"},
    {"period": "2026-01-02", "delivered_qty": "12000.0"},
    {"period": "2026-01-03", "delivered_qty": "9000.0"},
    {"period": "2026-01-04", "delivered_qty": "11000.0"},
    {"period": "2026-01-05", "delivered_qty": "13000.0"},
    {"period": "2026-01-06", "delivered_qty": "10500.0"}
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

### Test 2: Daily Period dengan Custom Range
```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=daily&date_from=2025-11-01&date_to=2025-11-30"
```

**Expected Response:**
```json
{
  "data": [
    {"period": "2025-11-01", "delivered_qty": "10000.0"},
    {"period": "2025-11-02", "delivered_qty": "12000.0"},
    ...
    {"period": "2025-11-30", "delivered_qty": "15000.0"}
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2025-11-01",
    "date_to": "2025-11-30"
  }
}
```

### Test 3: Monthly Period (Masih Berfungsi Normal)
```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=monthly&date_from=2025-01-01&date_to=2025-12-31"
```

**Expected Response:**
```json
{
  "data": [
    {"period": "2025-01", "delivered_qty": "300000.0"},
    {"period": "2025-02", "delivered_qty": "320000.0"},
    ...
    {"period": "2025-12", "delivered_qty": "350000.0"}
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2025-01-01",
    "date_to": "2025-12-31"
  }
}
```

### Test 4: Yearly Period (Masih Berfungsi Normal)
```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=yearly&date_from=2023-01-01&date_to=2025-12-31"
```

**Expected Response:**
```json
{
  "data": [
    {"period": "2023", "delivered_qty": "3500000.0"},
    {"period": "2024", "delivered_qty": "3800000.0"},
    {"period": "2025", "delivered_qty": "4000000.0"}
  ],
  "filter_metadata": {
    "period": "yearly",
    "date_field": "invoice_date",
    "date_from": "2023-01-01",
    "date_to": "2025-12-31"
  }
}
```

---

## Keuntungan Perubahan

1. ✅ **Daily Period Sekarang Berfungsi** - Data di-group per hari, bukan per bulan
2. ✅ **Konsisten dengan Method Lain** - Menggunakan helper method yang sama
3. ✅ **Smart Defaults** - Default disesuaikan dengan period type
4. ✅ **Lebih Maintainable** - Tidak ada hardcoded date format
5. ✅ **Backward Compatible** - Monthly dan yearly tetap berfungsi normal

---

## Method Lain yang Sudah Mendukung Daily Period

Setelah perubahan ini, method berikut sudah mendukung period `daily` dengan benar:

1. ✅ `revenueTrend` - Area Chart
2. ✅ `invoiceStatusDistribution` - Stacked Bar Chart
3. ✅ `salesOrderFulfillment` - Bar Chart (BARU DIPERBAIKI)

---

## Catatan untuk Method `monthlySalesComparison`

Method `monthlySalesComparison` **sengaja** hanya mendukung monthly karena:
- Namanya sudah `monthlySalesComparison` (bukan `salesComparison`)
- Fungsinya untuk membandingkan Month-over-Month (MoM) growth
- Jika perlu daily/yearly comparison, bisa buat method baru: `salesComparison`

---

**Tanggal Fix**: 2026-01-06  
**Status**: ✅ Fixed  
**Issue**: Daily period menampilkan data monthly  
**Solution**: Gunakan `getDateFormatByPeriod` helper dan period-aware defaults
