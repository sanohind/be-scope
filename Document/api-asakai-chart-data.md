# Asakai Chart API - Get Chart Data with Filled Dates

## Endpoint
```
GET /api/asakai/charts/data
```

## Description
Mendapatkan data chart dengan semua tanggal dalam range terisi. Tanggal yang tidak memiliki data akan otomatis diisi dengan `qty = 0`.

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `asakai_title_id` | integer | ✅ Yes | - | ID dari asakai title |
| `period` | string | ❌ No | `monthly` | Period type: `daily`, `monthly`, `yearly` |
| `date_from` | date | ✅ Yes | - | Start date (format: Y-m-d) |
| `date_to` | date | ❌ No | Auto | End date (format: Y-m-d). Jika tidak diisi, akan otomatis berdasarkan period |

### Default `date_to` berdasarkan `period`:
- **daily**: End of month dari `date_from`
- **monthly**: End of year dari `date_from`
- **yearly**: 5 tahun dari `date_from`

## Example Requests

### 1. Daily Chart (Januari 2026)
```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01
```

Response akan berisi semua tanggal dari 1-31 Januari 2026. Tanggal yang tidak ada data akan memiliki `qty = 0`.

### 2. Monthly Chart (2026)
```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=monthly&date_from=2026-01-01
```

Response akan berisi semua bulan dari Januari-Desember 2026.

### 3. Custom Date Range
```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-15&date_to=2026-01-20
```

Response akan berisi tanggal 15-20 Januari 2026.

## Response Format

### Success Response (200 OK)
```json
{
  "success": true,
  "data": [
    {
      "date": "2026-01-01",
      "qty": 5,
      "has_data": true,
      "chart_id": 1
    },
    {
      "date": "2026-01-02",
      "qty": 0,
      "has_data": false,
      "chart_id": null
    },
    {
      "date": "2026-01-03",
      "qty": 3,
      "has_data": true,
      "chart_id": 2
    }
  ],
  "filter_metadata": {
    "asakai_title_id": 1,
    "period": "daily",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31",
    "total_dates": 31,
    "dates_with_data": 2,
    "dates_without_data": 29
  }
}
```

### Data Fields
| Field | Type | Description |
|-------|------|-------------|
| `date` | string | Tanggal (Y-m-d) |
| `qty` | integer | Quantity (0 jika tidak ada data) |
| `has_data` | boolean | `true` jika ada data asli, `false` jika filled dengan 0 |
| `chart_id` | integer\|null | ID chart jika ada data, `null` jika tidak ada |

### Error Response (422 Validation Error)
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "asakai_title_id": ["The asakai title id field is required."],
    "date_from": ["The date from field is required."]
  }
}
```

### Error Response (500 Server Error)
```json
{
  "success": false,
  "message": "Failed to fetch chart data",
  "error": "Error message here"
}
```

## Use Cases

### 1. Chart Visualization
Endpoint ini sangat berguna untuk visualisasi chart karena:
- Semua tanggal dalam range akan terisi
- Tidak ada gap di chart
- Frontend tidak perlu handle missing dates

### 2. Data Entry Form
Bisa digunakan untuk menampilkan form input dengan:
- Tanggal yang sudah ada data (untuk edit)
- Tanggal yang belum ada data (untuk create)

### 3. Comparison
Mudah untuk compare data antar periode karena semua periode memiliki entry.

## Testing with PowerShell

```powershell
# Test daily chart for January 2026
Invoke-WebRequest -Uri 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01' -Method GET | Select-Object -ExpandProperty Content | ConvertFrom-Json | ConvertTo-Json -Depth 10

# Test monthly chart for 2026
Invoke-WebRequest -Uri 'http://localhost:8000/api/asakai/charts/data?asakai_title_id=1&period=monthly&date_from=2026-01-01' -Method GET | Select-Object -ExpandProperty Content | ConvertFrom-Json | ConvertTo-Json -Depth 10
```

## Notes
- ⚠️ Route order penting! `/charts/data` harus sebelum `/charts/{id}` agar tidak conflict
- ✅ Endpoint ini read-only (GET), tidak mengubah data
- ✅ Cocok untuk dashboard dan chart visualization
- ✅ Mengurangi kompleksitas di frontend
