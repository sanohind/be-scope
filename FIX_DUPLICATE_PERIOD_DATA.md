# Fix: Duplikasi Data untuk Period yang Sama

## Masalah yang Diperbaiki

Ketika menggunakan period `yearly` (atau `monthly`) dengan date range yang melintasi **split date (2025-08-01)**, data untuk periode yang sama muncul **2 kali** dengan format berbeda:

**Contoh Masalah:**
```json
{
  "data": [
    {
      "period": 2025,              // ❌ Integer dari database 1
      "delivered_qty": "16270384.00"
    },
    {
      "period": "2025",            // ❌ String dari database 2
      "delivered_qty": "11766383.0"
    },
    {
      "period": "2026",
      "delivered_qty": "0.0"
    }
  ]
}
```

---

## Penyebab Masalah

### 1. **Data dari 2 Database Berbeda**

Dashboard 4 menggunakan 2 database:
- **SoInvoiceLine2** (ERP2) - Data sebelum Agustus 2025
- **SoInvoiceLine** (ERP) - Data dari Agustus 2025 ke atas

### 2. **Driver Database Berbeda**

Kedua database menggunakan driver yang berbeda:
- **SQL Server** - Return YEAR sebagai **integer** (2025)
- **MySQL** - Return YEAR sebagai **string** ("2025")

### 3. **Tidak Ada Merge Logic**

Sebelumnya, data dari kedua database hanya di-merge tanpa pengecekan duplikasi:

```php
// SEBELUM (Salah)
$allData = $allData->merge($erp1Data);
$sortedData = $allData->sortBy('period')->values();
// Result: Bisa ada duplikasi period dengan format berbeda
```

---

## Solusi yang Diterapkan

### 1. **Normalisasi Format Period**

Semua period dinormalisasi menjadi **string** untuk konsistensi:

```php
$mergedData = $allData->groupBy(function ($item) {
    // Normalize period to string for consistent grouping
    return (string) $item->period;
})
```

### 2. **Group dan Sum Data dengan Period yang Sama**

Data dengan period yang sama digabungkan dan nilai-nilainya dijumlahkan:

```php
->map(function ($group) {
    // Sum delivered_qty for items with same period
    $totalQty = $group->sum(function ($item) {
        return (float) $item->delivered_qty;
    });
    
    return [
        'period' => (string) $group->first()->period,
        'delivered_qty' => number_format($totalQty, 1, '.', '')
    ];
})
```

### 3. **Sort Hasil Akhir**

Data yang sudah di-merge kemudian di-sort berdasarkan period:

```php
$sortedData = $mergedData->sortBy('period')->values();
```

---

## Method yang Diperbaiki

### 1. **`salesOrderFulfillment`**

**Sebelum:**
```php
$allData = $allData->merge($erp1Data);
$sortedData = $allData->sortBy('period')->values();
```

**Sesudah:**
```php
$allData = $allData->merge($erp1Data);

// Normalize period format to string and merge duplicates
$mergedData = $allData->groupBy(function ($item) {
    return (string) $item->period;
})->map(function ($group) {
    $totalQty = $group->sum(function ($item) {
        return (float) $item->delivered_qty;
    });
    
    return [
        'period' => (string) $group->first()->period,
        'delivered_qty' => number_format($totalQty, 1, '.', '')
    ];
})->values();

$sortedData = $mergedData->sortBy('period')->values();
```

### 2. **`monthlySalesComparison`**

**Sebelum:**
```php
$allData = $allData->merge($erp1Data);
$sortedData = $allData->sortBy('period')->values();
```

**Sesudah:**
```php
$allData = $allData->merge($erp1Data);

// Normalize period format to string and merge duplicates
$mergedData = $allData->groupBy(function ($item) {
    return (string) $item->period;
})->map(function ($group) {
    $totalRevenue = $group->sum(function ($item) {
        return (float) $item->revenue;
    });
    
    return (object) [
        'period' => (string) $group->first()->period,
        'revenue' => $totalRevenue
    ];
})->values();

$sortedData = $mergedData->sortBy('period')->values();
```

---

## Hasil Setelah Fix

### Response yang Benar

**Request:**
```bash
GET /api/dashboard/sales/order-fulfillment?period=yearly&date_from=2025-01-01&date_to=2026-12-31
```

**Response:**
```json
{
  "data": [
    {
      "period": "2025",                    // ✅ String, data sudah di-merge
      "delivered_qty": "28036767.0"        // ✅ Sum dari kedua database
    },
    {
      "period": "2026",
      "delivered_qty": "0.0"
    }
  ],
  "filter_metadata": {
    "period": "yearly",
    "date_field": "invoice_date",
    "date_from": "2025-01-01",
    "date_to": "2026-12-31"
  }
}
```

**Penjelasan:**
- ✅ Hanya ada **1 entry** untuk tahun 2025
- ✅ `delivered_qty` adalah **total** dari kedua database:
  - 16270384.00 (dari SoInvoiceLine2)
  - 11766383.0 (dari SoInvoiceLine)
  - **Total: 28036767.0**
- ✅ Format period konsisten sebagai **string**

---

## Testing

### Test 1: Yearly Period Melintasi Split Date

```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=yearly&date_from=2025-01-01&date_to=2026-12-31"
```

**Expected:**
- ✅ Tahun 2025 hanya muncul 1 kali
- ✅ `delivered_qty` adalah total dari kedua database
- ✅ Format period adalah string

### Test 2: Monthly Period Melintasi Split Date

```bash
curl "http://localhost:8000/api/dashboard/sales/order-fulfillment?period=monthly&date_from=2025-07-01&date_to=2025-09-30"
```

**Expected:**
```json
{
  "data": [
    {"period": "2025-07", "delivered_qty": "xxx"},  // Dari SoInvoiceLine2
    {"period": "2025-08", "delivered_qty": "xxx"},  // Merged dari kedua DB
    {"period": "2025-09", "delivered_qty": "xxx"}   // Dari SoInvoiceLine
  ]
}
```

### Test 3: Monthly Sales Comparison

```bash
curl "http://localhost:8000/api/dashboard/sales/monthly-sales-comparison?date_from=2025-07-01&date_to=2025-09-30"
```

**Expected:**
- ✅ Tidak ada duplikasi bulan
- ✅ Revenue untuk Agustus 2025 adalah total dari kedua database
- ✅ MoM growth dihitung dengan benar

---

## Keuntungan Fix Ini

1. ✅ **Tidak Ada Duplikasi** - Setiap period hanya muncul 1 kali
2. ✅ **Data Akurat** - Nilai di-sum dengan benar dari kedua database
3. ✅ **Format Konsisten** - Semua period dalam format string
4. ✅ **Sorting Benar** - Data terurut dengan benar berdasarkan period
5. ✅ **MoM Growth Akurat** - Perhitungan growth tidak terpengaruh duplikasi

---

## Catatan Teknis

### Mengapa Perlu Normalisasi ke String?

```php
// Tanpa normalisasi
groupBy('period')
// Result: 2025 dan "2025" dianggap berbeda

// Dengan normalisasi
groupBy(function ($item) {
    return (string) $item->period;
})
// Result: 2025 dan "2025" dianggap sama
```

### Mengapa Perlu Sum?

Karena data untuk periode yang sama bisa datang dari 2 database:
- **Januari - Juli 2025**: Dari SoInvoiceLine2
- **Agustus - Desember 2025**: Dari SoInvoiceLine

Untuk mendapatkan total tahun 2025, kita perlu menjumlahkan keduanya.

---

## Method Lain yang Tidak Terpengaruh

Method berikut **tidak terpengaruh** masalah ini karena:

1. **`revenueTrend`** - Menggunakan single query dengan `getQueryForDateRange`
2. **`topCustomersByRevenue`** - Tidak group by period
3. **`salesByProductType`** - Tidak group by period
4. **`invoiceStatusDistribution`** - Menggunakan single query

Hanya method yang:
- ✅ Menggunakan **2 query terpisah** (ERP dan ERP2)
- ✅ **Merge hasil** dari kedua query
- ✅ **Group by period**

yang memerlukan fix ini.

---

**Tanggal Fix**: 2026-01-06  
**Status**: ✅ Fixed  
**Issue**: Duplikasi data untuk period yang sama  
**Solution**: Normalize period format dan merge duplicates dengan sum
