# Period-Aware Default Date Range - Update

## Perubahan Terbaru

Menambahkan logika **period-aware default date range** untuk memberikan default yang lebih masuk akal berdasarkan tipe period yang dipilih.

---

## 🎯 Logika Default Date Range

### 1. **Period = `daily`**
- **Default**: 7 hari terakhir
- **Range**: Dari 6 hari yang lalu sampai hari ini
- **Contoh**: Jika hari ini 2026-01-06, maka default `date_from=2026-01-01` dan `date_to=2026-01-06`

### 2. **Period = `monthly`**
- **Default**: Bulan ini
- **Range**: Dari tanggal 1 bulan ini sampai hari ini
- **Contoh**: Jika hari ini 2026-01-06, maka default `date_from=2026-01-01` dan `date_to=2026-01-06`

### 3. **Period = `yearly`**
- **Default**: Tahun ini
- **Range**: Dari tanggal 1 Januari tahun ini sampai hari ini
- **Contoh**: Jika hari ini 2026-01-06, maka default `date_from=2026-01-01` dan `date_to=2026-01-06`

---

## 📝 Helper Method Baru

### `getDefaultDateRangeByPeriod(Request $request)`

Method ini menentukan default date range berdasarkan parameter `period` atau `group_by`:

```php
private function getDefaultDateRangeByPeriod(Request $request): array
{
    $period = $request->get('period', $request->get('group_by', 'monthly'));
    
    switch ($period) {
        case 'daily':
            return [
                'start' => now()->subDays(6)->startOfDay(),
                'end' => now()->endOfDay()
            ];
        case 'yearly':
            return [
                'start' => now()->startOfYear(),
                'end' => now()->endOfDay()
            ];
        case 'monthly':
        default:
            return [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfDay()
            ];
    }
}
```

---

## 🔄 Method yang Diupdate

### 1. **`revenueTrend`**
✅ Sekarang menggunakan period-aware default

**Contoh Penggunaan:**
```bash
# Daily - akan default ke 7 hari terakhir
GET /api/dashboard/sales/revenue-trend?period=daily

# Monthly - akan default ke bulan ini
GET /api/dashboard/sales/revenue-trend?period=monthly

# Yearly - akan default ke tahun ini
GET /api/dashboard/sales/revenue-trend?period=yearly

# Custom range (override default)
GET /api/dashboard/sales/revenue-trend?period=daily&date_from=2026-01-01&date_to=2026-01-31
```

### 2. **`invoiceStatusDistribution`**
✅ Sekarang menggunakan period-aware default (kecuali jika `group_by=customer`)

**Contoh Penggunaan:**
```bash
# Daily grouping - default 7 hari terakhir
GET /api/dashboard/sales/invoice-status-distribution?group_by=daily

# Monthly grouping - default bulan ini
GET /api/dashboard/sales/invoice-status-distribution?group_by=monthly

# Group by customer - tidak terpengaruh period
GET /api/dashboard/sales/invoice-status-distribution?group_by=customer&date_from=2026-01-01&date_to=2026-01-31
```

---

## 🧪 Testing

### Test 1: Daily Period Default
```bash
# Request tanpa date_from dan date_to
curl "http://localhost:8000/api/dashboard/sales/revenue-trend?period=daily"

# Expected: Data 7 hari terakhir (2026-01-01 sampai 2026-01-06)
```

### Test 2: Monthly Period Default
```bash
# Request tanpa date_from dan date_to
curl "http://localhost:8000/api/dashboard/sales/revenue-trend?period=monthly"

# Expected: Data bulan ini (2026-01-01 sampai 2026-01-06)
```

### Test 3: Yearly Period Default
```bash
# Request tanpa date_from dan date_to
curl "http://localhost:8000/api/dashboard/sales/revenue-trend?period=yearly"

# Expected: Data tahun ini (2026-01-01 sampai 2026-01-06)
```

### Test 4: Custom Date Range (Override Default)
```bash
# Request dengan date_from dan date_to custom
curl "http://localhost:8000/api/dashboard/sales/revenue-trend?period=daily&date_from=2025-12-01&date_to=2025-12-31"

# Expected: Data sesuai range yang diberikan (Desember 2025)
```

### Test 5: Daily dengan Custom Range
```bash
# Request daily dengan range 1 bulan penuh
curl "http://localhost:8000/api/dashboard/sales/revenue-trend?period=daily&date_from=2026-01-01&date_to=2026-01-31"

# Expected: Data per hari dari 1-31 Januari 2026 (31 data points)
```

---

## ✅ Keuntungan Perubahan Ini

1. **Lebih Masuk Akal** - Default daily tidak lagi menampilkan 30+ hari data
2. **Performa Lebih Baik** - Daily default hanya 7 hari, lebih cepat
3. **User Experience** - User langsung dapat data yang relevan tanpa perlu set date range
4. **Fleksibel** - User tetap bisa override dengan custom date range
5. **Konsisten** - Semua method yang menggunakan period grouping punya behavior yang sama

---

## 📊 Contoh Response

### Daily Period (Default 7 Hari)
```json
{
  "data": [
    {"period": "2026-01-01", "revenue": 1000000, "invoice_count": 10},
    {"period": "2026-01-02", "revenue": 1200000, "invoice_count": 12},
    {"period": "2026-01-03", "revenue": 900000, "invoice_count": 8},
    {"period": "2026-01-04", "revenue": 1100000, "invoice_count": 11},
    {"period": "2026-01-05", "revenue": 1300000, "invoice_count": 13},
    {"period": "2026-01-06", "revenue": 1050000, "invoice_count": 9}
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

### Monthly Period (Default Bulan Ini)
```json
{
  "data": [
    {"period": "2026-01", "revenue": 6550000, "invoice_count": 63}
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

---

## 🔍 Catatan Penting

1. **User tetap bisa override** - Jika user mengirim `date_from` dan `date_to`, sistem akan menggunakan nilai tersebut
2. **Backward compatible** - Method yang tidak menggunakan period grouping tidak terpengaruh
3. **Smart defaults** - Default disesuaikan dengan tipe visualisasi yang diharapkan

---

**Tanggal Update**: 2026-01-06  
**Status**: ✅ Implemented
