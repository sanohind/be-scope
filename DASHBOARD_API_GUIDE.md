# Dashboard API Guide

## Base URL
```
http://your-domain.com/api/dashboard
```

---

## Dashboard 1: Inventory Management & Stock Control

Base Path: `/api/dashboard/inventory`

### 1.1 Stock Level Overview
**Endpoint:** `GET /api/dashboard/inventory/stock-level-overview`

**Description:** Menampilkan KPI cards untuk overview level stok secara keseluruhan.

**Request:** No parameters required

**Response:**
```json
{
  "total_onhand": 150000,
  "items_below_safety_stock": 45,
  "items_above_max_stock": 12,
  "total_items": 500,
  "average_stock_level": 300.50
}
```

**Response Fields:**
- `total_onhand` (integer): Total stok yang tersedia
- `items_below_safety_stock` (integer): Jumlah item di bawah safety stock
- `items_above_max_stock` (integer): Jumlah item di atas max stock
- `total_items` (integer): Total jumlah item unik
- `average_stock_level` (float): Rata-rata level stok

---

### 1.2 Stock Health by Warehouse
**Endpoint:** `GET /api/dashboard/inventory/stock-health-by-warehouse`

**Description:** Menampilkan kondisi kesehatan stok per warehouse dalam bentuk stacked bar chart. **Hanya menampilkan warehouse: WHRM01, WHRM02, WHFG01, WHFG02, WHMT01**

**Query Parameters:**
- `product_type` (optional): Filter berdasarkan tipe produk
- `group` (optional): Filter berdasarkan grup produk
- `customer` (optional): Filter berdasarkan customer

**Request Example:**
```
GET /api/dashboard/inventory/stock-health-by-warehouse?product_type=FG&customer=ABC
```

**Response:**
```json
[
  {
    "warehouse": "WHRM01",
    "critical": 5,
    "low": 10,
    "normal": 150,
    "overstock": 3
  },
  {
    "warehouse": "WHRM02",
    "critical": 2,
    "low": 8,
    "normal": 120,
    "overstock": 5
  }
]
```

**Response Fields:**
- `warehouse` (string): Kode warehouse
- `critical` (integer): Jumlah item dengan stok di bawah min_stock
- `low` (integer): Jumlah item dengan stok antara min_stock dan safety_stock
- `normal` (integer): Jumlah item dengan stok antara safety_stock dan max_stock
- `overstock` (integer): Jumlah item dengan stok di atas max_stock

---

### 1.3 Top Critical Items
**Endpoint:** `GET /api/dashboard/inventory/top-critical-items`

**Description:** Menampilkan top 20 item dengan kondisi stok kritis dalam bentuk data table.

**Query Parameters:**
- `status` (optional): Filter berdasarkan status stok
  - `critical`: onhand < min_stock
  - `low`: onhand >= min_stock AND onhand < safety_stock
  - `overstock`: onhand > max_stock

**Request Example:**
```
GET /api/dashboard/inventory/top-critical-items?status=critical
```

**Response:**
```json
[
  {
    "warehouse": "WHRM01",
    "partno": "PART-001",
    "desc": "Product Description",
    "onhand": 50,
    "safety_stock": 100,
    "min_stock": 75,
    "max_stock": 200,
    "location": "A-01-01",
    "gap": 50
  }
]
```

**Response Fields:**
- `warehouse` (string): Kode warehouse
- `partno` (string): Nomor part
- `desc` (string): Deskripsi produk
- `onhand` (integer): Stok yang tersedia
- `safety_stock` (integer): Safety stock level
- `min_stock` (integer): Minimum stock level
- `max_stock` (integer): Maximum stock level
- `location` (string): Lokasi penyimpanan
- `gap` (integer): Selisih antara safety_stock dan onhand

---

### 1.4 Stock Distribution by Product Type
**Endpoint:** `GET /api/dashboard/inventory/stock-distribution-by-product-type`

**Description:** Menampilkan distribusi stok berdasarkan tipe produk dalam bentuk treemap.

**Request:** No parameters required

**Response:**
```json
{
  "FG": {
    "Model A": [
      {
        "product_type": "FG",
        "model": "Model A",
        "partno": "PART-001",
        "desc": "Product Description",
        "onhand": 100,
        "allocated": 20,
        "available": 80
      }
    ]
  }
}
```

**Response Structure:**
- Grouped by `product_type` → `model`
- Each item contains:
  - `product_type` (string): Tipe produk
  - `model` (string): Model produk
  - `partno` (string): Nomor part
  - `desc` (string): Deskripsi produk
  - `onhand` (integer): Stok yang tersedia
  - `allocated` (integer): Stok yang dialokasikan
  - `available` (integer): Stok yang tersedia (onhand - allocated)

---

### 1.5 Stock by Customer
**Endpoint:** `GET /api/dashboard/inventory/stock-by-customer`

**Description:** Menampilkan distribusi stok berdasarkan customer dalam bentuk donut chart.

**Request:** No parameters required

**Response:**
```json
{
  "data": [
    {
      "customer": "Customer A",
      "total_onhand": 5000,
      "total_items": 150
    },
    {
      "customer": "Customer B",
      "total_onhand": 3500,
      "total_items": 120
    }
  ],
  "total_onhand": 8500,
  "total_items": 270
}
```

**Response Fields:**
- `data` (array): Array data per customer
  - `customer` (string): Nama customer
  - `total_onhand` (integer): Total stok untuk customer
  - `total_items` (integer): Total jumlah item unik
- `total_onhand` (integer): Total keseluruhan stok
- `total_items` (integer): Total keseluruhan item

---

### 1.6 Inventory Availability vs Demand
**Endpoint:** `GET /api/dashboard/inventory/inventory-availability-vs-demand`

**Description:** Menampilkan perbandingan ketersediaan inventory vs demand dalam bentuk combo chart.

**Query Parameters:**
- `group_by` (optional, default: `warehouse`): Grouping data
  - `warehouse`: Group berdasarkan warehouse
  - `product_group`: Group berdasarkan product group

**Request Example:**
```
GET /api/dashboard/inventory/inventory-availability-vs-demand?group_by=warehouse
```

**Response:**
```json
[
  {
    "warehouse": "WHRM01",
    "total_onhand": 10000,
    "total_allocated": 2000,
    "total_onorder": 1500,
    "available_percentage": 80.00
  }
]
```

**Response Fields:**
- `warehouse` or `group` (string): Warehouse atau group (tergantung parameter group_by)
- `total_onhand` (integer): Total stok yang tersedia
- `total_allocated` (integer): Total stok yang dialokasikan
- `total_onorder` (integer): Total stok yang sedang dipesan
- `available_percentage` (float): Persentase ketersediaan stok

---

### 1.7 Stock Movement Trend
**Endpoint:** `GET /api/dashboard/inventory/stock-movement-trend`

**Description:** Menampilkan trend pergerakan stok dalam bentuk area chart.

**Note:** Endpoint ini memerlukan data historis. Saat ini menampilkan data current.

**Query Parameters:**
- `warehouse` (optional): Filter berdasarkan warehouse
- `product_type` (optional): Filter berdasarkan tipe produk

**Request Example:**
```
GET /api/dashboard/inventory/stock-movement-trend?warehouse=WHRM01
```

**Response:**
```json
{
  "message": "Historical data required for trend analysis",
  "current_data": [
    {
      "partno": "PART-001",
      "desc": "Product Description",
      "onhand": 100,
      "allocated": 20,
      "available": 80
    }
  ]
}
```

---

### 1.8 Get All Dashboard 1 Data
**Endpoint:** `GET /api/dashboard/inventory/all-data`

**Description:** Mengambil semua data dashboard 1 dalam satu request.

**Query Parameters:** Mendukung semua parameter dari endpoint individual

**Response:**
```json
{
  "stock_level_overview": { ... },
  "stock_health_by_warehouse": [ ... ],
  "top_critical_items": [ ... ],
  "stock_distribution_by_product_type": { ... },
  "stock_by_customer": { ... },
  "inventory_availability_vs_demand": [ ... ],
  "stock_movement_trend": { ... }
}
```

---

## Dashboard 2: Warehouse Operations

Base Path: `/api/dashboard/warehouse`

### 2.1 Warehouse Order Summary
**Endpoint:** `GET /api/dashboard/warehouse/order-summary`

**Description:** Menampilkan KPI cards untuk summary warehouse order.

**Request:** No parameters required

**Response:**
```json
{
  "total_order_lines": 1500,
  "pending_deliveries": 250,
  "completed_orders": 1200,
  "average_fulfillment_rate": 95.50
}
```

**Response Fields:**
- `total_order_lines` (integer): Total order lines
- `pending_deliveries` (integer): Jumlah pengiriman yang pending
- `completed_orders` (integer): Jumlah order yang completed
- `average_fulfillment_rate` (float): Rata-rata fulfillment rate (%)

---

### 2.2 Order Flow Analysis
**Endpoint:** `GET /api/dashboard/warehouse/order-flow-analysis`

**Description:** Menampilkan analisis alur order dalam bentuk Sankey diagram.

**Request:** No parameters required

**Response:**
```json
[
  {
    "ship_from": "WHRM01",
    "ship_to": "WHFG01",
    "trx_type": "Transfer",
    "total_qty": 5000,
    "order_count": 150
  }
]
```

**Response Fields:**
- `ship_from` (string): Warehouse asal
- `ship_to` (string): Warehouse tujuan
- `trx_type` (string): Tipe transaksi
- `total_qty` (integer): Total quantity yang dipindahkan
- `order_count` (integer): Jumlah order

---

### 2.3 Delivery Performance
**Endpoint:** `GET /api/dashboard/warehouse/delivery-performance`

**Description:** Menampilkan performa pengiriman dalam bentuk gauge chart.

**Request:** No parameters required

**Response:**
```json
{
  "on_time_delivery_rate": 92.50,
  "target_rate": 95,
  "early_deliveries": 100,
  "on_time_deliveries": 850,
  "late_deliveries": 50,
  "total_orders": 1000,
  "performance_status": "good"
}
```

**Response Fields:**
- `on_time_delivery_rate` (float): Persentase pengiriman tepat waktu
- `target_rate` (integer): Target rate yang diharapkan (95%)
- `early_deliveries` (integer): Jumlah pengiriman lebih awal
- `on_time_deliveries` (integer): Jumlah pengiriman tepat waktu
- `late_deliveries` (integer): Jumlah pengiriman terlambat
- `total_orders` (integer): Total order
- `performance_status` (string): Status performa
  - `excellent`: >= 95%
  - `good`: >= 85%
  - `needs_improvement`: < 85%

---

### 2.4 Order Status Distribution
**Endpoint:** `GET /api/dashboard/warehouse/order-status-distribution`

**Description:** Menampilkan distribusi status order dalam bentuk stacked bar chart.

**Query Parameters:**
- `ship_from` (optional): Filter berdasarkan warehouse asal

**Request Example:**
```
GET /api/dashboard/warehouse/order-status-distribution?ship_from=WHRM01
```

**Response:**
```json
{
  "Transfer": [
    {
      "trx_type": "Transfer",
      "line_status": "Completed",
      "count": 500,
      "percentage": 80.00
    },
    {
      "trx_type": "Transfer",
      "line_status": "Pending",
      "count": 125,
      "percentage": 20.00
    }
  ]
}
```

**Response Structure:**
- Grouped by `trx_type`
- Each item contains:
  - `trx_type` (string): Tipe transaksi
  - `line_status` (string): Status line order
  - `count` (integer): Jumlah order
  - `percentage` (float): Persentase dari total per trx_type

---

### 2.5 Daily Order Volume
**Endpoint:** `GET /api/dashboard/warehouse/daily-order-volume`

**Description:** Menampilkan volume order harian dalam bentuk line chart dengan area.

**Query Parameters:**
- `trx_type` (optional): Filter berdasarkan tipe transaksi
- `ship_from` (optional): Filter berdasarkan warehouse asal
- `date_from` (optional): Filter tanggal mulai (format: YYYY-MM-DD)
- `date_to` (optional): Filter tanggal akhir (format: YYYY-MM-DD)

**Request Example:**
```
GET /api/dashboard/warehouse/daily-order-volume?date_from=2024-01-01&date_to=2024-01-31
```

**Response:**
```json
[
  {
    "order_date": "2024-01-01",
    "total_order_qty": 1000,
    "total_ship_qty": 950,
    "gap_qty": 50,
    "order_count": 25
  }
]
```

**Response Fields:**
- `order_date` (date): Tanggal order
- `total_order_qty` (integer): Total quantity yang dipesan
- `total_ship_qty` (integer): Total quantity yang dikirim
- `gap_qty` (integer): Selisih antara order dan ship
- `order_count` (integer): Jumlah order

---

### 2.6 Order Fulfillment Rate
**Endpoint:** `GET /api/dashboard/warehouse/order-fulfillment-rate`

**Description:** Menampilkan fulfillment rate per warehouse dalam bentuk bar chart dengan target line.

**Request:** No parameters required

**Response:**
```json
{
  "data": [
    {
      "ship_from": "WHRM01",
      "total_order_qty": 10000,
      "total_ship_qty": 9500,
      "fulfillment_rate": 95.00
    }
  ],
  "target_rate": 100
}
```

**Response Fields:**
- `data` (array): Array data per warehouse
  - `ship_from` (string): Warehouse asal
  - `total_order_qty` (integer): Total quantity yang dipesan
  - `total_ship_qty` (integer): Total quantity yang dikirim
  - `fulfillment_rate` (float): Persentase fulfillment
- `target_rate` (integer): Target rate yang diharapkan (100%)

---

### 2.7 Top Items Moved
**Endpoint:** `GET /api/dashboard/warehouse/top-items-moved`

**Description:** Menampilkan top items yang paling banyak dipindahkan dalam bentuk horizontal bar chart.

**Query Parameters:**
- `limit` (optional, default: 20): Jumlah item yang ditampilkan

**Request Example:**
```
GET /api/dashboard/warehouse/top-items-moved?limit=10
```

**Response:**
```json
[
  {
    "item_code": "ITEM-001",
    "item_desc": "Item Description",
    "total_qty_moved": 5000,
    "total_orders": 150,
    "avg_qty_per_order": 33.33
  }
]
```

**Response Fields:**
- `item_code` (string): Kode item
- `item_desc` (string): Deskripsi item
- `total_qty_moved` (integer): Total quantity yang dipindahkan
- `total_orders` (integer): Total jumlah order
- `avg_qty_per_order` (float): Rata-rata quantity per order

---

### 2.8 Warehouse Order Timeline
**Endpoint:** `GET /api/dashboard/warehouse/order-timeline`

**Description:** Menampilkan timeline warehouse order dalam bentuk Gantt chart.

**Query Parameters:**
- `date_from` (optional): Filter tanggal mulai (format: YYYY-MM-DD)
- `date_to` (optional): Filter tanggal akhir (format: YYYY-MM-DD)
- `line_status` (optional): Filter berdasarkan status line
- `ship_from` (optional): Filter berdasarkan warehouse asal

**Request Example:**
```
GET /api/dashboard/warehouse/order-timeline?date_from=2024-01-01&line_status=Completed
```

**Response:**
```json
[
  {
    "order_no": "WO-2024-001",
    "line_no": 1,
    "order_date": "2024-01-01",
    "delivery_date": "2024-01-05",
    "receipt_date": "2024-01-04",
    "line_status": "Completed",
    "item_code": "ITEM-001",
    "item_desc": "Item Description",
    "order_qty": 100,
    "ship_qty": 100,
    "delivery_status": "on_time"
  }
]
```

**Response Fields:**
- `order_no` (string): Nomor order
- `line_no` (integer): Nomor line
- `order_date` (date): Tanggal order
- `delivery_date` (date): Tanggal pengiriman yang diharapkan
- `receipt_date` (date): Tanggal penerimaan aktual
- `line_status` (string): Status line order
- `item_code` (string): Kode item
- `item_desc` (string): Deskripsi item
- `order_qty` (integer): Quantity yang dipesan
- `ship_qty` (integer): Quantity yang dikirim
- `delivery_status` (string): Status pengiriman
  - `pending`: receipt_date masih NULL
  - `on_time`: receipt_date <= delivery_date
  - `delayed`: receipt_date > delivery_date

---

### 2.9 Get All Dashboard 2 Data
**Endpoint:** `GET /api/dashboard/warehouse/all-data`

**Description:** Mengambil semua data dashboard 2 dalam satu request.

**Query Parameters:** Mendukung semua parameter dari endpoint individual

**Response:**
```json
{
  "warehouse_order_summary": { ... },
  "order_flow_analysis": [ ... ],
  "delivery_performance": { ... },
  "order_status_distribution": { ... },
  "daily_order_volume": [ ... ],
  "order_fulfillment_rate": { ... },
  "top_items_moved": [ ... ],
  "warehouse_order_timeline": [ ... ]
}
```

---

## Error Responses

Semua endpoint menggunakan format error response yang konsisten:

**Error Response Format:**
```json
{
  "success": false,
  "message": "Error message description"
}
```

**Common HTTP Status Codes:**
- `200 OK`: Request berhasil
- `400 Bad Request`: Parameter tidak valid
- `404 Not Found`: Resource tidak ditemukan
- `500 Internal Server Error`: Error server

---

## Notes

1. **Date Format**: Semua tanggal menggunakan format `YYYY-MM-DD`
2. **Warehouse Filter**: Dashboard 1 endpoint `stock-health-by-warehouse` hanya menampilkan warehouse: WHRM01, WHRM02, WHFG01, WHFG02, WHMT01
3. **Pagination**: Saat ini belum ada pagination, beberapa endpoint menggunakan limit
4. **Authentication**: Tambahkan authentication header jika diperlukan
5. **Rate Limiting**: Implementasikan rate limiting sesuai kebutuhan

---

## Testing Examples

### Using cURL

**Example 1: Get Stock Level Overview**
```bash
curl -X GET "http://your-domain.com/api/dashboard/inventory/stock-level-overview"
```

**Example 2: Get Stock Health by Warehouse with Filter**
```bash
curl -X GET "http://your-domain.com/api/dashboard/inventory/stock-health-by-warehouse?product_type=FG"
```

**Example 3: Get Daily Order Volume with Date Range**
```bash
curl -X GET "http://your-domain.com/api/dashboard/warehouse/daily-order-volume?date_from=2024-01-01&date_to=2024-01-31"
```

### Using JavaScript (Fetch API)

```javascript
// Example: Get All Dashboard 1 Data
fetch('http://your-domain.com/api/dashboard/inventory/all-data')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));

// Example: Get Delivery Performance
fetch('http://your-domain.com/api/dashboard/warehouse/delivery-performance')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error('Error:', error));
```

---

## Changelog

### Version 1.0.0 (2024)
- Initial release
- Dashboard 1: 8 endpoints untuk Inventory Management & Stock Control
- Dashboard 2: 9 endpoints untuk Warehouse Operations
- Warehouse filter untuk Dashboard 1 (WHRM01, WHRM02, WHFG01, WHFG02, WHMT01)
