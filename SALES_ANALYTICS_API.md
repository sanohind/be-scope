# Sales Analytics API Documentation

## Overview

API ini menyediakan data untuk bar chart yang menggabungkan data dari tabel `sales_shipment` dan `so_monitor`. API mendukung filter berdasarkan year dan period.

## Base URL

```
/api/dashboard/sales-analytics
```

## Endpoints

### 1. Get Bar Chart Data (Combined)

Mengambil data gabungan dari sales_shipment dan so_monitor dengan aggregasi berdasarkan year dan period.

**Endpoint:**

```
GET /api/dashboard/sales-analytics/bar-chart
```

**Parameters:**

-   `year` (optional, integer): Filter berdasarkan tahun (e.g., 2025)
-   `period` (optional, integer): Filter berdasarkan bulan (1-12)

**Example Requests:**

```bash
# Get all data
curl "http://localhost:8000/api/dashboard/sales-analytics/bar-chart"

# Get data for specific year
curl "http://localhost:8000/api/dashboard/sales-analytics/bar-chart?year=2025"

# Get data for specific year and period
curl "http://localhost:8000/api/dashboard/sales-analytics/bar-chart?year=2025&period=12"

# Get data for specific period across all years
curl "http://localhost:8000/api/dashboard/sales-analytics/bar-chart?period=12"
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "year": 2025,
            "period": 1,
            "total_delivery": 1500.5,
            "total_po": 2000.0
        },
        {
            "year": 2025,
            "period": 2,
            "total_delivery": 1800.75,
            "total_po": 2200.5
        }
    ],
    "count": 2
}
```

---

### 2. Get Sales Shipment Data by Period

Mengambil data delivery dari tabel sales_shipment dengan aggregasi berdasarkan year dan period.

**Endpoint:**

```
GET /api/dashboard/sales-analytics/sales-shipment
```

**Parameters:**

-   `year` (optional, integer): Filter berdasarkan tahun
-   `period` (optional, integer): Filter berdasarkan bulan

**Example Requests:**

```bash
# Get all sales shipment data
curl "http://localhost:8000/api/dashboard/sales-analytics/sales-shipment"

# Get sales shipment for specific year
curl "http://localhost:8000/api/dashboard/sales-analytics/sales-shipment?year=2025"

# Get sales shipment for specific year and period
curl "http://localhost:8000/api/dashboard/sales-analytics/sales-shipment?year=2025&period=12"
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "year": 2025,
            "period": 1,
            "total_delivery": 1500.5
        },
        {
            "year": 2025,
            "period": 2,
            "total_delivery": 1800.75
        }
    ],
    "count": 2
}
```

---

### 3. Get SO Monitor Data by Period

Mengambil data PO dari tabel so_monitor dengan aggregasi berdasarkan year dan period.

**Endpoint:**

```
GET /api/dashboard/sales-analytics/so-monitor
```

**Parameters:**

-   `year` (optional, integer): Filter berdasarkan tahun
-   `period` (optional, integer): Filter berdasarkan bulan

**Example Requests:**

```bash
# Get all SO monitor data
curl "http://localhost:8000/api/dashboard/sales-analytics/so-monitor"

# Get SO monitor for specific year
curl "http://localhost:8000/api/dashboard/sales-analytics/so-monitor?year=2025"

# Get SO monitor for specific year and period
curl "http://localhost:8000/api/dashboard/sales-analytics/so-monitor?year=2025&period=12"
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "year": 2025,
            "period": 1,
            "total_po": 2000.0
        },
        {
            "year": 2025,
            "period": 2,
            "total_po": 2200.5
        }
    ],
    "count": 2
}
```

---

### 4. Get Combined Data with Details

Mengambil data gabungan dengan detail per business partner (bp_code dan bp_name).

**Endpoint:**

```
GET /api/dashboard/sales-analytics/combined-details
```

**Parameters:**

-   `year` (optional, integer): Filter berdasarkan tahun
-   `period` (optional, integer): Filter berdasarkan bulan

**Example Requests:**

```bash
# Get all combined data with details
curl "http://localhost:8000/api/dashboard/sales-analytics/combined-details"

# Get combined data for specific year
curl "http://localhost:8000/api/dashboard/sales-analytics/combined-details?year=2025"

# Get combined data for specific year and period
curl "http://localhost:8000/api/dashboard/sales-analytics/combined-details?year=2025&period=12"
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "year": 2025,
            "period": 1,
            "bp_code": "BP001",
            "bp_name": "Business Partner 1",
            "total_delivery": 500.0,
            "total_po": 800.0
        },
        {
            "year": 2025,
            "period": 1,
            "bp_code": "BP002",
            "bp_name": "Business Partner 2",
            "total_delivery": 1000.5,
            "total_po": 1200.0
        }
    ],
    "count": 2
}
```

---

## Data Aggregation Logic

### Bar Chart Data (`/bar-chart`)

-   **Source**: Combines `sales_shipment` and `so_monitor` tables
-   **Group By**: `year`, `period`
-   **Aggregation**:
    -   `total_delivery`: SUM of `total_delivery` from sales_shipment
    -   `total_po`: SUM of `total_po` from so_monitor
-   **Order**: By year ASC, then period ASC

### Sales Shipment Data (`/sales-shipment`)

-   **Source**: `sales_shipment` table
-   **Group By**: `year`, `period`
-   **Aggregation**: SUM of `total_delivery`
-   **Order**: By year ASC, then period ASC

### SO Monitor Data (`/so-monitor`)

-   **Source**: `so_monitor` table
-   **Group By**: `year`, `period`
-   **Aggregation**: SUM of `total_po`
-   **Order**: By year ASC, then period ASC

### Combined Details (`/combined-details`)

-   **Source**: Combines `sales_shipment` and `so_monitor` tables
-   **Group By**: `year`, `period`, `bp_code`, `bp_name`
-   **Aggregation**:
    -   `total_delivery`: SUM of `total_delivery` from sales_shipment
    -   `total_po`: SUM of `total_po` from so_monitor
-   **Order**: By year ASC, period ASC, bp_code ASC

---

## Error Handling

All endpoints return error responses in the following format:

```json
{
    "success": false,
    "message": "Failed to fetch bar chart data",
    "error": "Error message details"
}
```

HTTP Status Code: `500`

---

## Usage Examples

### JavaScript/Fetch

```javascript
// Get bar chart data for year 2025
fetch("/api/dashboard/sales-analytics/bar-chart?year=2025")
    .then((response) => response.json())
    .then((data) => {
        if (data.success) {
            console.log("Data:", data.data);
            // Use data for bar chart visualization
        }
    });
```

### JavaScript/Axios

```javascript
import axios from "axios";

// Get combined data for specific period
axios
    .get("/api/dashboard/sales-analytics/bar-chart", {
        params: {
            year: 2025,
            period: 12,
        },
    })
    .then((response) => {
        console.log("Chart Data:", response.data.data);
    })
    .catch((error) => {
        console.error("Error:", error);
    });
```

### PHP/Laravel

```php
// Using Laravel HTTP client
use Illuminate\Support\Facades\Http;

$response = Http::get('/api/dashboard/sales-analytics/bar-chart', [
    'year' => 2025,
    'period' => 12
]);

$data = $response->json();
```

---

## Notes

1. **Distinct Period**: The API automatically handles DISTINCT period values through GROUP BY aggregation
2. **Null Values**: If no data exists for a specific year/period combination, it will not appear in results
3. **Performance**: Data is aggregated at the database level for optimal performance
4. **Timezone**: All dates are stored and returned in server timezone
5. **Decimal Precision**: All monetary values are stored with 2 decimal places

---

## Related Tables

### sales_shipment

-   `id`: Primary key
-   `year`: Year from delivery_date
-   `period`: Month from delivery_date (1-12)
-   `bp_code`: Business partner code
-   `bp_name`: Business partner name
-   `total_delivery`: Sum of delivered quantities
-   `created_at`, `updated_at`: Timestamps

### so_monitor

-   `id`: Primary key
-   `year`: Year from planned_delivery_date
-   `period`: Month from planned_delivery_date (1-12)
-   `bp_code`: Business partner code
-   `bp_name`: Business partner name
-   `total_po`: Sum of order quantities
-   `created_at`, `updated_at`: Timestamps
