# Asakai Chart API - Index with Filled Dates

## ✅ Update Summary

Method `index()` di `AsakaiChartController` sekarang **otomatis mengisi tanggal kosong dengan qty = 0**, mengikuti pola dari `Dashboard1RevisionController`.

## Endpoint
```
GET /api/asakai/charts
```

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `asakai_title_id` | integer | ✅ Yes* | - | ID dari asakai title (required untuk filled dates) |
| `period` | string | ❌ No | `monthly` | Period type: `daily`, `monthly`, `yearly` |
| `date_from` | date | ❌ No | - | Start date (format: Y-m-d) |
| `date_to` | date | ❌ No | Auto | End date (auto-calculated based on period) |
| `per_page` | integer | ❌ No | `10` | Items per page |
| `page` | integer | ❌ No | `1` | Current page number |

\* `asakai_title_id` **wajib** jika ingin mendapatkan filled dates dengan qty = 0

## Behavior

### ✅ **Dengan `asakai_title_id` + `date_from`**
- Semua tanggal dalam range akan terisi
- Tanggal kosong otomatis diisi dengan `qty = 0`
- `has_data = false` untuk tanggal yang tidak ada data
- `has_data = true` untuk tanggal yang ada data asli

### ⚠️ **Tanpa `asakai_title_id` atau `date_from`**
- Hanya menampilkan data yang ada di database
- Tidak ada filling untuk tanggal kosong
- Backward compatible dengan behavior lama

## Auto Date Range Calculation

Berdasarkan `period`:

- **daily**: 
  - `date_from` → Start of month
  - `date_to` → End of month
  - Contoh: `2026-01-15` → `2026-01-01` s/d `2026-01-31`

- **monthly**:
  - `date_from` → Start of year
  - `date_to` → End of year
  - Contoh: `2026-06-15` → `2026-01-01` s/d `2026-12-31`

- **yearly**:
  - `date_from` → As is
  - `date_to` → 5 years from `date_from` (if not provided)
  - Contoh: `2026-01-01` → `2026-01-01` s/d `2031-01-01`

## Example Requests

### 1. Daily Chart - Januari 2026 (Filled)
```bash
GET /api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": null,
      "asakai_title_id": 1,
      "asakai_title": null,
      "category": null,
      "date": "2026-01-01",
      "qty": 0,
      "user": null,
      "user_id": null,
      "reasons_count": 0,
      "created_at": null,
      "has_data": false
    },
    {
      "id": 5,
      "asakai_title_id": 1,
      "asakai_title": "Safety - Fatal Accident",
      "category": "Safety",
      "date": "2026-01-19",
      "qty": 5,
      "user": "System Admin",
      "user_id": 1,
      "reasons_count": 0,
      "created_at": "2026-01-19 07:34:00",
      "has_data": true
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 31,
    "per_page": 10,
    "last_page": 4
  },
  "filter_metadata": {
    "period": "daily",
    "date_field": "date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31",
    "asakai_title_id": 1,
    "total_dates": 31,
    "dates_with_data": 2,
    "dates_without_data": 29
  }
}
```

### 2. Monthly Chart - 2026 (Filled)
```bash
GET /api/asakai/charts?asakai_title_id=1&period=monthly&date_from=2026-01-01
```

Returns 12 months (Jan-Dec 2026), missing months have `qty = 0`

### 3. Without asakai_title_id (Original Behavior)
```bash
GET /api/asakai/charts?period=daily&date_from=2026-01-01
```

Returns only existing data, no filling

## Response Fields

### Data with Data (`has_data = true`)
```json
{
  "id": 5,
  "asakai_title_id": 1,
  "asakai_title": "Safety - Fatal Accident",
  "category": "Safety",
  "date": "2026-01-19",
  "qty": 5,
  "user": "System Admin",
  "user_id": 1,
  "reasons_count": 0,
  "created_at": "2026-01-19 07:34:00",
  "has_data": true
}
```

### Data without Data (`has_data = false`)
```json
{
  "id": null,
  "asakai_title_id": 1,
  "asakai_title": null,
  "category": null,
  "date": "2026-01-01",
  "qty": 0,
  "user": null,
  "user_id": null,
  "reasons_count": 0,
  "created_at": null,
  "has_data": false
}
```

### Filter Metadata (with filled dates)
```json
{
  "period": "daily",
  "date_field": "date",
  "date_from": "2026-01-01",
  "date_to": "2026-01-31",
  "asakai_title_id": 1,
  "total_dates": 31,
  "dates_with_data": 2,
  "dates_without_data": 29
}
```

## Use Cases

### ✅ Chart Visualization
Perfect untuk chart karena semua tanggal terisi, tidak ada gap

### ✅ Calendar View
Bisa digunakan untuk menampilkan calendar dengan data per hari

### ✅ Data Entry Form
Frontend bisa langsung render form untuk semua tanggal, highlight yang sudah ada data

### ✅ Progress Tracking
Mudah track berapa tanggal yang sudah diisi vs yang belum

## Testing

```bash
# Test filled dates
php test_asakai_index_filled.php

# Test page 2 (to see actual data)
php test_asakai_page2.php
```

## Key Differences from `getChartData()`

| Feature | `index()` | `getChartData()` |
|---------|-----------|------------------|
| Pagination | ✅ Yes | ❌ No |
| Filled dates | ✅ Yes (if asakai_title_id provided) | ✅ Always |
| Backward compatible | ✅ Yes | ❌ N/A (new endpoint) |
| Use case | General listing | Chart data only |

## Notes

- ✅ Backward compatible: Jika tidak ada `asakai_title_id`, behavior tetap sama seperti sebelumnya
- ✅ Pagination tetap berfungsi dengan filled data
- ✅ `has_data` field memudahkan frontend membedakan data asli vs filled
- ✅ Mengikuti pola dari `Dashboard1RevisionController.stockMovementTrend()`
