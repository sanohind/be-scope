# API Documentation: getDailyBarChartData

## Endpoint
```
GET /api/dashboard/sales-analytics/daily-bar-chart
```

## Description
Mendapatkan data bar chart harian yang menggabungkan data dari `SalesShipment` (so_invoice_line) dan `SoMonitor` berdasarkan tanggal. API ini mirip dengan `getBarChartData` tetapi menggunakan grouping per hari (daily) bukan per bulan (monthly).

## Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date_from` | string (Y-m-d) | No | Tanggal 1 bulan ini | Tanggal awal filter (format: YYYY-MM-DD) |
| `date_to` | string (Y-m-d) | No | Tanggal akhir bulan ini | Tanggal akhir filter (format: YYYY-MM-DD) |

### Parameter Behavior:
- **Jika kedua parameter kosong**: Menggunakan range tanggal bulan berjalan (dari tanggal 1 sampai akhir bulan)
- **Jika hanya `date_from` diisi**: `date_to` akan sama dengan `date_from`
- **Jika hanya `date_to` diisi**: `date_from` akan sama dengan `date_to`
- **Jika kedua parameter diisi**: Menggunakan range yang ditentukan

## Response Format

### Success Response (200 OK)
```json
{
  "success": true,
  "data": [
    {
      "date": "2026-01-01",
      "total_delivery": 1500.50,
      "total_po": 2000.00
    },
    {
      "date": "2026-01-02",
      "total_delivery": 0,
      "total_po": 0
    },
    {
      "date": "2026-01-03",
      "total_delivery": 2300.75,
      "total_po": 2500.00
    }
  ],
  "count": 31,
  "date_from": "2026-01-01",
  "date_to": "2026-01-31"
}
```

### Error Response (500 Internal Server Error)
```json
{
  "success": false,
  "message": "Failed to fetch daily bar chart data",
  "error": "Error message details"
}
```

## Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Status keberhasilan request |
| `data` | array | Array berisi data harian |
| `data[].date` | string | Tanggal dalam format Y-m-d |
| `data[].total_delivery` | float | Total quantity yang sudah dikirim (dari so_invoice_line) |
| `data[].total_po` | float | Total quantity PO/order (dari so_monitor) |
| `count` | integer | Jumlah data yang dikembalikan |
| `date_from` | string | Tanggal awal yang digunakan |
| `date_to` | string | Tanggal akhir yang digunakan |

## Features

1. **Auto-fill Missing Dates**: Tanggal yang tidak memiliki data akan diisi dengan nilai 0
2. **Sorted by Date**: Data otomatis diurutkan berdasarkan tanggal (ascending)
3. **Default to Current Month**: Jika tidak ada parameter, otomatis menggunakan bulan berjalan
4. **Flexible Date Range**: Mendukung range tanggal custom

## Example Requests

### 1. Get Current Month Data (Default)
```bash
GET /api/dashboard/sales-analytics/daily-bar-chart
```

### 2. Get Specific Date Range
```bash
GET /api/dashboard/sales-analytics/daily-bar-chart?date_from=2026-01-01&date_to=2026-01-15
```

### 3. Get Single Day Data
```bash
GET /api/dashboard/sales-analytics/daily-bar-chart?date_from=2026-01-05&date_to=2026-01-05
```

### 4. Get Data from Specific Date to Today
```bash
GET /api/dashboard/sales-analytics/daily-bar-chart?date_from=2026-01-01
# date_to akan otomatis sama dengan date_from
```

## Data Sources

### 1. Sales Shipment (Delivery)
- **Table**: `so_invoice_line` (ERP connection)
- **Field**: `delivered_qty` (SUM)
- **Date Field**: `delivery_date`

### 2. SO Monitor (Purchase Order)
- **Table**: `so_monitor` (ERP connection)
- **Field**: `order_qty` (SUM)
- **Date Field**: `planned_delivery_date`
- **Filter**: `sequence = 0`

## Implementation Details

### Helper Methods Used:
1. **`generateAllDates()`**: Generate semua tanggal antara date_from dan date_to
2. **`fillMissingDates()`**: Mengisi tanggal yang hilang dengan nilai 0

### Query Logic:
1. Query data delivery dari `so_invoice_line` dengan grouping per tanggal
2. Query data PO dari `so_monitor` dengan grouping per tanggal
3. Merge kedua hasil query berdasarkan tanggal
4. Fill missing dates dengan nilai 0
5. Sort berdasarkan tanggal

### SQL Server Compatibility:
- Menggunakan `CAST(column AS DATE)` untuk konversi tanggal (kompatibel dengan SQL Server)
- Tidak menggunakan fungsi `DATE()` yang hanya tersedia di MySQL

## Notes

- API ini menggunakan koneksi database `erp` dengan **SQL Server**
- Semua tanggal dalam response menggunakan format `Y-m-d` (YYYY-MM-DD)
- Data akan selalu mencakup semua tanggal dalam range yang ditentukan, bahkan jika tidak ada data (akan diisi 0)
- Cocok untuk visualisasi chart harian seperti line chart atau bar chart
- Query menggunakan `CAST()` untuk kompatibilitas dengan SQL Server

## Comparison with getBarChartData

| Feature | getBarChartData | getDailyBarChartData |
|---------|-----------------|----------------------|
| Grouping | Year-Month | Date (Daily) |
| Parameters | `year`, `period` | `date_from`, `date_to` |
| Default | Current year, all months | Current month |
| Response Key | `year`, `period` | `date` |
| Use Case | Monthly trends | Daily trends |
