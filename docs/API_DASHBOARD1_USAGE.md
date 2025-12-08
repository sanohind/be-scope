# Dashboard 1 API Usage Guide

## Overview

Dashboard 1 API sekarang mendukung historical data analysis dengan parameter baru:

-   `date_from` & `date_to`: Filter berdasarkan tanggal
-   `period`: Periodisasi data (daily, monthly, yearly)
-   `customer`: Filter berdasarkan customer
-   `group_type_desc`: Filter berdasarkan group type description
-   `warehouse`: Filter berdasarkan warehouse (required untuk Dashboard1RevisionController)

---

## Base URL

```
/api/dashboard/inventory
/api/dashboard/inventory-rev
```

---

## 1. Stock Level Overview

### Endpoint

```
GET /api/dashboard/inventory/stock-level-overview
GET /api/dashboard/inventory-rev/kpi
```

### Parameters

| Parameter         | Type   | Required       | Default             | Description                           |
| ----------------- | ------ | -------------- | ------------------- | ------------------------------------- |
| `warehouse`       | string | Yes (rev only) | -                   | Warehouse code (WHMT01, WHRM01, etc.) |
| `date_from`       | date   | No             | Current month start | Start date (YYYY-MM-DD)               |
| `date_to`         | date   | No             | Today               | End date (YYYY-MM-DD)                 |
| `period`          | string | No             | daily               | Period: daily, monthly, yearly        |
| `customer`        | string | No             | -                   | Filter by customer                    |
| `group_type_desc` | string | No             | -                   | Filter by group type description      |

### Example Requests

#### Basic Request (Current Data)

```bash
GET /api/dashboard/inventory/stock-level-overview
```

#### With Date Range

```bash
GET /api/dashboard/inventory/stock-level-overview?date_from=2025-11-01&date_to=2025-11-30
```

#### With Period (Monthly - Tahun 2024)

```bash
GET /api/dashboard/inventory/stock-level-overview?period=monthly&date_from=2024-01-01&date_to=2024-12-31
```

#### With Period (Monthly - Tahun 2025)

```bash
GET /api/dashboard/inventory/stock-level-overview?period=monthly&date_from=2025-01-01&date_to=2025-12-31
```

#### With Filters

```bash
GET /api/dashboard/inventory-rev/kpi?warehouse=WHMT01&customer=CUST001&group_type_desc=Finished Goods&date_from=2025-11-01&date_to=2025-11-30&period=daily
```

### Example Response

```json
{
    "total_onhand": 125000.5,
    "items_below_safety_stock": 45,
    "items_above_max_stock": 12,
    "total_items": 3500,
    "average_stock_level": 35.71,
    "snapshot_date": "2025-11-30",
    "date_range": {
        "from": "2025-11-01",
        "to": "2025-11-30",
        "days_diff": 29
    },
    "period": "daily"
}
```

---

## 2. Stock Health by Warehouse

### Endpoint

```
GET /api/dashboard/inventory/stock-health-by-warehouse
GET /api/dashboard/inventory-rev/stock-health-distribution
```

### Example Requests

#### Basic Request

```bash
GET /api/dashboard/inventory/stock-health-by-warehouse?warehouse=WHMT01
```

#### With Historical Data

```bash
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHMT01&date_from=2025-11-01&date_to=2025-11-30&period=daily&customer=CUST001
```

### Example Response

```json
{
    "data": [
        {
            "warehouse": "WHMT01",
            "critical": 15,
            "low": 30,
            "normal": 3200,
            "overstock": 5
        }
    ],
    "snapshot_date": "2025-11-30",
    "date_range": {
        "from": "2025-11-01",
        "to": "2025-11-30"
    },
    "period": "daily"
}
```

---

## 3. Top Critical Items

### Endpoint

```
GET /api/dashboard/inventory/top-critical-items
GET /api/dashboard/inventory-rev/top-critical-items
```

### Parameters

| Parameter | Type   | Description                      |
| --------- | ------ | -------------------------------- |
| `status`  | string | Filter: critical, low, overstock |

### Example Requests

#### Get Critical Items

```bash
GET /api/dashboard/inventory/top-critical-items?status=critical&warehouse=WHMT01&date_from=2025-11-01&date_to=2025-11-30
```

#### With All Filters

```bash
GET /api/dashboard/inventory-rev/top-critical-items?warehouse=WHMT01&status=critical&customer=CUST001&group_type_desc=Raw Materials&date_from=2025-11-01&date_to=2025-11-30&period=daily
```

---

## 4. Stock by Customer

### Endpoint

```
GET /api/dashboard/inventory/stock-by-customer
GET /api/dashboard/inventory-rev/customer-analysis
```

### Example Requests

#### Basic Request

```bash
GET /api/dashboard/inventory/stock-by-customer
```

#### With Historical Data and Filters

```bash
GET /api/dashboard/inventory-rev/customer-analysis?warehouse=WHMT01&group_type_desc=Finished Goods&date_from=2025-11-01&date_to=2025-11-30&period=monthly
```

### Example Response

```json
{
    "data": [
        {
            "customer": "CUST001",
            "total_onhand": 45000.0,
            "total_items": 250
        },
        {
            "customer": "CUST002",
            "total_onhand": 32000.0,
            "total_items": 180
        }
    ],
    "summary": {
        "total_onhand": 77000.0,
        "total_items": 430,
        "total_customers": 2
    },
    "snapshot_date": "2025-11-30",
    "date_range": {
        "from": "2025-11-01",
        "to": "2025-11-30"
    },
    "period": "monthly"
}
```

---

## 5. Stock by Group Type

### Endpoint

```
GET /api/dashboard/inventory-rev/group-type-analysis
```

### Example Request

```bash
GET /api/dashboard/inventory-rev/group-type-analysis?warehouse=WHMT01&customer=CUST001&date_from=2025-11-01&date_to=2025-11-30&period=daily
```

---

## 6. Stock Movement Trend

### Endpoint

```
GET /api/dashboard/inventory/stock-movement-trend
GET /api/dashboard/inventory-rev/movement-trend
```

### Example Requests

#### Daily Trend

```bash
GET /api/dashboard/inventory/stock-movement-trend?warehouse=WHMT01&period=daily&date_from=2025-11-01&date_to=2025-11-30
```

#### Monthly Trend (Tahun 2024)

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31
```

**Penjelasan**:

-   `period=monthly`: Data akan di-aggregate per bulan
-   `date_from=2024-01-01`: Mulai dari awal tahun 2024
-   `date_to=2024-12-31`: Sampai akhir tahun 2024
-   **Hasil**: Akan mengembalikan data per bulan di tahun 2024 (Jan, Feb, Mar, ..., Des)

#### Monthly Trend (Tahun 2025)

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2025-01-01&date_to=2025-12-31
```

#### Yearly Trend

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=yearly&date_from=2023-01-01&date_to=2025-12-31
```

### Example Response (Monthly)

**Note**: Ketika `period=monthly`, data sudah di-aggregate per bulan. Setiap bulan menampilkan data dari snapshot terakhir di bulan tersebut.

```json
{
    "trend_data": [
        {
            "period": "2025-01",
            "total_onhand": 120000.0,
            "total_receipt": 10000.0,
            "total_shipment": 8000.0,
            "net_movement": 2000.0
        },
        {
            "period": "2025-02",
            "total_onhand": 122000.0,
            "total_receipt": 12000.0,
            "total_shipment": 10000.0,
            "net_movement": 2000.0
        },
        {
            "period": "2025-03",
            "total_onhand": 125000.5,
            "total_receipt": 15000.0,
            "total_shipment": 12000.0,
            "net_movement": 3000.0
        }
    ],
    "period": "monthly",
    "granularity": "monthly",
    "current_total_onhand": 125000.5,
    "snapshot_date": "2025-03-31",
    "date_range": {
        "from": "2025-01-01",
        "to": "2025-12-31",
        "days": 365
    }
}
```

**Penjelasan**:

-   Setiap object dalam `trend_data` mewakili **satu bulan** (bukan per tanggal)
-   `period: "2025-01"` berarti data untuk bulan Januari 2025
-   Data diambil dari snapshot terakhir yang tersedia di setiap bulan

---

## 7. Stock Level Table (Grouped by Group Type)

### Endpoint

```
GET /api/dashboard/inventory/stock-level
GET /api/dashboard/inventory-rev/stock-level-table
```

### Parameters

| Parameter  | Type    | Description                                       |
| ---------- | ------- | ------------------------------------------------- |
| `page`     | integer | Page number (default: 1)                          |
| `per_page` | integer | Items per page (default: 50, max: 200)            |
| `status`   | string  | Filter: Critical, Low, Normal, Overstock          |
| `search`   | string  | Search in partno, partname, desc, group_type_desc |

### Example Requests

#### Basic Request

```bash
GET /api/dashboard/inventory-rev/stock-level-table?warehouse=WHMT01&page=1&per_page=50
```

#### With Filters

```bash
GET /api/dashboard/inventory-rev/stock-level-table?warehouse=WHMT01&status=Critical&customer=CUST001&date_from=2025-11-01&date_to=2025-11-30&period=daily&page=1&per_page=100
```

#### With Search

```bash
GET /api/dashboard/inventory-rev/stock-level-table?warehouse=WHMT01&search=PART001&date_from=2025-11-01&date_to=2025-11-30
```

### Example Response

```json
{
    "data": [
        {
            "group_type_desc": "Finished Goods",
            "total_items": 250,
            "total_onhand": 45000.0,
            "total_min_stock": 10000.0,
            "total_safety_stock": 20000.0,
            "total_max_stock": 50000.0,
            "critical_count": 5,
            "low_count": 15,
            "normal_count": 220,
            "overstock_count": 10,
            "gap_from_safety": 25000.0
        }
    ],
    "filters": {
        "warehouse": "WHMT01",
        "status": "Critical",
        "search": null,
        "customer": "CUST001"
    },
    "snapshot_date": "2025-11-30",
    "date_range": {
        "from": "2025-11-01",
        "to": "2025-11-30"
    },
    "period": "daily",
    "pagination": {
        "total": 10,
        "per_page": 50,
        "current_page": 1,
        "last_page": 1,
        "from": 1,
        "to": 10
    }
}
```

---

## 8. Get All Data (Bulk Request)

### Endpoint

```
GET /api/dashboard/inventory/all-data
GET /api/dashboard/inventory-rev/all-data
```

### Example Request

```bash
GET /api/dashboard/inventory-rev/all-data?warehouse=WHMT01&date_from=2025-11-01&date_to=2025-11-30&period=daily&customer=CUST001&group_type_desc=Finished Goods
```

### Response

Returns all dashboard data in one call, including:

-   KPI summary
-   Stock health distribution
-   Movement trend
-   Critical items
-   Active items
-   Product type analysis
-   Group type analysis
-   Customer analysis
-   Receipt/shipment trend
-   Transaction types
-   Fast/slow moving items
-   Turnover rate
-   Stock level table

---

## Period Types

### Daily

-   Uses the latest snapshot within the selected date range
-   Returns single data point representing the latest state
-   Best for: Current state analysis, latest snapshot view
-   Example: `period=daily&date_from=2025-11-01&date_to=2025-11-30`
-   **Note**: Returns data from the latest snapshot date in the range, not aggregated daily data

### Monthly

-   **Aggregates data per month** - Uses the latest snapshot from each month
-   Returns data grouped by month (e.g., "2024-01", "2024-02", "2024-03", ..., "2024-12")
-   Best for: Monthly comparison, seasonal trends, month-over-month analysis
-   **Contoh untuk tahun 2024**: `period=monthly&date_from=2024-01-01&date_to=2024-12-31`
-   **Contoh untuk tahun 2025**: `period=monthly&date_from=2025-01-01&date_to=2025-12-31`
-   **Note**: Each month uses the latest snapshot available in that month. For trend endpoints, data is aggregated per month.

### Yearly

-   **Aggregates data per year** - Uses the latest snapshot from each year
-   Returns data grouped by year (e.g., "2023", "2024", "2025")
-   Best for: Year-over-year analysis, long-term trends
-   Example: `period=yearly&date_from=2023-01-01&date_to=2025-12-31`
-   **Note**: Each year uses the latest snapshot available in that year. For trend endpoints, data is aggregated per year.

### Important Notes on Period Aggregation

1. **For Single Value Endpoints** (KPI, Overview):

    - `daily`: Returns data from latest snapshot in date range
    - `monthly`: Returns data from latest snapshot in the last month of the range
    - `yearly`: Returns data from latest snapshot in the last year of the range

2. **For Trend/Array Endpoints** (Movement Trend, etc.):

    - `daily`: Returns single data point (latest snapshot)
    - `monthly`: **Returns array of data per month** - each month shows aggregated data
    - `yearly`: **Returns array of data per year** - each year shows aggregated data

3. **Snapshot Selection**:
    - For monthly/yearly periods, the system automatically selects the latest snapshot available in each period
    - This ensures you get the most recent data representation for each period

---

## Warehouse Codes

### Valid Warehouse Codes

-   `WHMT01` - Main Warehouse
-   `WHRM01` - Raw Materials Warehouse 1
-   `WHRM02` - Raw Materials Warehouse 2
-   `WHFG01` - Finished Goods Warehouse 1
-   `WHFG02` - Finished Goods Warehouse 2

### Warehouse Aliases (Dashboard1Controller only)

-   `RM` - Returns: WHRM01, WHRM02, WHMT01
-   `FG` - Returns: WHFG01, WHFG02

---

## Complete Example: JavaScript/Axios

```javascript
// Example: Get stock overview with all filters
const getStockOverview = async () => {
    try {
        const response = await axios.get("/api/dashboard/inventory-rev/kpi", {
            params: {
                warehouse: "WHMT01",
                date_from: "2025-11-01",
                date_to: "2025-11-30",
                period: "daily",
                customer: "CUST001",
                group_type_desc: "Finished Goods",
            },
        });

        console.log("Stock Overview:", response.data);
    } catch (error) {
        console.error("Error:", error.response.data);
    }
};

// Example: Get monthly trend
const getMonthlyTrend = async () => {
    try {
        const response = await axios.get(
            "/api/dashboard/inventory-rev/movement-trend",
            {
                params: {
                    warehouse: "WHMT01",
                    period: "monthly",
                    date_from: "2025-01-01",
                    date_to: "2025-12-31",
                },
            }
        );

        console.log("Monthly Trend:", response.data.trend_data);
    } catch (error) {
        console.error("Error:", error.response.data);
    }
};

// Example: Get stock by customer
const getStockByCustomer = async () => {
    try {
        const response = await axios.get(
            "/api/dashboard/inventory-rev/customer-analysis",
            {
                params: {
                    warehouse: "WHMT01",
                    group_type_desc: "Finished Goods",
                    date_from: "2025-11-01",
                    date_to: "2025-11-30",
                    period: "daily",
                },
            }
        );

        console.log("Stock by Customer:", response.data.data);
    } catch (error) {
        console.error("Error:", error.response.data);
    }
};
```

---

## Complete Example: cURL

```bash
# Get KPI with all filters
curl -X GET "http://your-domain.com/api/dashboard/inventory-rev/kpi?warehouse=WHMT01&date_from=2025-11-01&date_to=2025-11-30&period=daily&customer=CUST001&group_type_desc=Finished%20Goods" \
  -H "Accept: application/json"

# Get monthly trend
curl -X GET "http://your-domain.com/api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2025-01-01&date_to=2025-12-31" \
  -H "Accept: application/json"

# Get stock by customer
curl -X GET "http://your-domain.com/api/dashboard/inventory-rev/customer-analysis?warehouse=WHMT01&group_type_desc=Finished%20Goods&date_from=2025-11-01&date_to=2025-11-30&period=daily" \
  -H "Accept: application/json"
```

---

## Notes

1. **Historical Data**: Semua endpoints sekarang menggunakan snapshot data jika tersedia untuk tanggal yang diminta. Jika snapshot tidak tersedia, akan menggunakan data current.

2. **Period Parameter & Aggregation**:

    - `daily`: Menggunakan snapshot terakhir dalam date range (single data point)
    - `monthly`: **Data di-aggregate per bulan** - setiap bulan menggunakan snapshot terakhir di bulan tersebut. Untuk trend endpoints, mengembalikan array data per bulan.
    - `yearly`: **Data di-aggregate per tahun** - setiap tahun menggunakan snapshot terakhir di tahun tersebut. Untuk trend endpoints, mengembalikan array data per tahun.

3. **Date Format**: Gunakan format `YYYY-MM-DD` untuk semua parameter tanggal.

4. **Warehouse Parameter**:

    - Required untuk semua endpoints di `inventory-rev`
    - Optional untuk endpoints di `inventory` (default: semua warehouse)

5. **Snapshot Date**:

    - Untuk `daily`: Response akan include `snapshot_date` yang menunjukkan tanggal snapshot terakhir yang digunakan
    - Untuk `monthly/yearly`: Response akan include `snapshot_date` yang menunjukkan snapshot terakhir di period terakhir, atau array snapshot dates untuk trend endpoints

6. **Pagination**: Untuk endpoints dengan pagination, gunakan `page` dan `per_page` parameters.

7. **Period Aggregation Behavior**:
    - Ketika `period=monthly`, data yang ditampilkan sudah **di-aggregate per bulan**, bukan per tanggal
    - Ketika `period=yearly`, data yang ditampilkan sudah **di-aggregate per tahun**, bukan per bulan atau tanggal
    - Setiap period (bulan/tahun) menggunakan snapshot terakhir yang tersedia di period tersebut

---

## Error Responses

### 400 Bad Request

```json
{
    "message": "Warehouse parameter is required"
}
```

### 400 Invalid Warehouse

```json
{
    "message": "Invalid warehouse code"
}
```

---

## Migration

Jalankan migration untuk menambahkan kolom baru:

```bash
php artisan migrate
```

Migration files:

-   `2025_12_03_000001_add_group_type_to_stock_by_wh_snapshots_table.php`
-   `2025_12_03_000002_add_group_type_to_stockbywh_table.php`
