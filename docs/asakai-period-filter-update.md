# Asakai Board API - Period Filtering Update

## 🎯 Update Summary

Period filtering telah ditambahkan ke Asakai Board API, mengikuti pattern yang sama dengan Dashboard3Controller.

## ✨ New Features

### Period Filter Parameter

Semua endpoint list (index) sekarang mendukung parameter `period` dengan 3 opsi:

- **`daily`** - Filter data per hari dalam bulan tertentu
- **`monthly`** - Filter data per bulan dalam tahun tertentu  
- **`yearly`** - Filter data per tahun dalam range tertentu

Default: `monthly`

---

## 📋 Updated Endpoints

### 1. GET /api/asakai/charts

**New Query Parameters:**
```
period: daily | monthly | yearly (default: monthly)
date_from: Start date (required for filtering)
date_to: End date (optional, used for yearly period)
```

**Filtering Logic:**

#### Daily Period
- `date_from` harus diisi dengan tanggal dalam bulan yang ingin ditampilkan
- Sistem akan menampilkan semua data dalam bulan tersebut
- Contoh: `period=daily&date_from=2026-01-15` → Menampilkan semua data Januari 2026

#### Monthly Period  
- `date_from` harus diisi dengan tanggal dalam tahun yang ingin ditampilkan
- Sistem akan menampilkan semua data dalam tahun tersebut
- Contoh: `period=monthly&date_from=2026-01-15` → Menampilkan semua data tahun 2026

#### Yearly Period
- `date_from` dan `date_to` mendefinisikan range tahun
- Contoh: `period=yearly&date_from=2024-01-01&date_to=2026-12-31` → Menampilkan data 2024-2026

**Example Requests:**

```bash
# Daily: Get all charts in January 2026
GET /api/asakai/charts?period=daily&date_from=2026-01-15&asakai_title_id=1

# Monthly: Get all charts in 2026
GET /api/asakai/charts?period=monthly&date_from=2026-01-01&asakai_title_id=1

# Yearly: Get all charts from 2024 to 2026
GET /api/asakai/charts?period=yearly&date_from=2024-01-01&date_to=2026-12-31&asakai_title_id=1
```

**Updated Response:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {...},
  "filter_metadata": {
    "period": "monthly",
    "date_field": "date",
    "date_from": "2026-01-01",
    "date_to": null
  }
}
```

---

### 2. GET /api/asakai/reasons

**New Query Parameters:**
```
period: daily | monthly | yearly (default: monthly)
date_from: Start date (required for filtering)
date_to: End date (optional, used for yearly period)
```

**Filtering Logic:** (Same as charts endpoint)

**Example Requests:**

```bash
# Daily: Get all reasons in January 2026 for section 'brazzing'
GET /api/asakai/reasons?period=daily&date_from=2026-01-15&section=brazzing

# Monthly: Get all reasons in 2026
GET /api/asakai/reasons?period=monthly&date_from=2026-01-01

# Yearly: Get all reasons from 2024 to 2026 with search
GET /api/asakai/reasons?period=yearly&date_from=2024-01-01&date_to=2026-12-31&search=ABC
```

**Updated Response:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {...},
  "filter_metadata": {
    "period": "monthly",
    "date_field": "date",
    "date_from": "2026-01-01",
    "date_to": null
  }
}
```

---

## 🔄 Controller Changes

### AsakaiChartController
- ✅ Now extends `ApiController` instead of `Controller`
- ✅ Added `Carbon\Carbon` import
- ✅ Implemented period-based date filtering in `index()` method
- ✅ Added `filter_metadata` to response

### AsakaiReasonController  
- ✅ Now extends `ApiController` instead of `Controller`
- ✅ Implemented period-based date filtering in `index()` method
- ✅ Added `filter_metadata` to response

---

## 📊 Use Cases

### Use Case 1: Daily Monitoring
```bash
# Monitor daily asakai data for current month
GET /api/asakai/charts?period=daily&date_from=2026-01-15&asakai_title_id=1
```

### Use Case 2: Monthly Report
```bash
# Get monthly summary for the year
GET /api/asakai/charts?period=monthly&date_from=2026-01-01
```

### Use Case 3: Yearly Trend Analysis
```bash
# Analyze trends across multiple years
GET /api/asakai/charts?period=yearly&date_from=2024-01-01&date_to=2026-12-31
```

### Use Case 4: Section-Specific Analysis
```bash
# Get all reasons for brazzing section in 2026
GET /api/asakai/reasons?period=monthly&date_from=2026-01-01&section=brazzing
```

---

## ⚠️ Important Notes

1. **Period Validation**: Invalid period values will default to `monthly`
2. **Date Format**: All dates should be in `Y-m-d` format (e.g., `2026-01-15`)
3. **Backward Compatibility**: Old requests without `period` parameter will still work (defaults to `monthly`)
4. **SQL Server Compatibility**: Uses `YEAR()` and `MONTH()` functions compatible with SQL Server

---

## 🎯 Filter Metadata

Semua response sekarang include `filter_metadata` yang berisi:
- `period`: Period yang digunakan (daily/monthly/yearly)
- `date_field`: Field tanggal yang difilter (`date`)
- `date_from`: Start date dari filter
- `date_to`: End date dari filter (jika ada)

Ini membantu frontend untuk menampilkan informasi filter yang sedang aktif.

---

## 🚀 Migration Status

✅ All changes applied successfully
✅ Controllers updated and tested
✅ Backward compatible with existing API calls
✅ Ready for production use
