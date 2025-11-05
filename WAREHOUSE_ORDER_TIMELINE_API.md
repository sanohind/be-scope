# Warehouse Order Timeline API Documentation

## Overview
API untuk visualisasi Warehouse Order Timeline menggunakan Gantt Chart atau Timeline. API ini menyediakan data order yang dikelompokkan berdasarkan order number dengan informasi timeline lengkap (order_date → delivery_date → receipt_date) dan status indicator (on-time/delayed/pending).

## Endpoints

### 1. Get Warehouse Order Timeline (Main API)
**GET** `/api/dashboard/warehouse/order-timeline`

Mendapatkan data timeline order untuk visualisasi Gantt Chart, dengan aggregasi per order number.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date_from` | date | No | - | Filter tanggal awal (order_date) format: YYYY-MM-DD |
| `date_to` | date | No | - | Filter tanggal akhir (order_date) format: YYYY-MM-DD |
| `status` | string | No | - | Filter status: `on_time`, `delayed`, `pending` |
| `ship_from` | string | No | - | Filter warehouse asal |
| `ship_to` | string | No | - | Filter warehouse tujuan |
| `limit` | integer | No | 50 | Jumlah order (max: 100) |

#### Example Request
```bash
# Get last 50 orders
GET /api/dashboard/warehouse/order-timeline

# Get delayed orders from specific warehouse
GET /api/dashboard/warehouse/order-timeline?status=delayed&ship_from=WH01

# Get orders in date range with limit
GET /api/dashboard/warehouse/order-timeline?date_from=2025-10-01&date_to=2025-10-31&limit=100
```

#### Response Structure
```json
{
  "data": [
    {
      "order_no": "SO-2025-001",
      "order_origin_code": "ORG001",
      "order_origin": "Origin Name",
      "trx_type": "Customer Order",
      "ship_from": "WH01",
      "ship_from_desc": "Main Warehouse",
      "ship_to": "WH02",
      "ship_to_desc": "Distribution Center",
      "order_date": "2025-10-01",
      "planned_delivery_date": "2025-10-15",
      "actual_receipt_date": "2025-10-14",
      "total_lines": 5,
      "total_order_qty": 1000,
      "total_ship_qty": 950,
      "statuses": "Shipped,Completed",
      "delivery_status": "on_time",
      "days_difference": -1,
      "planned_duration_days": 14,
      "actual_duration_days": 13,
      "fulfillment_rate": 95.0,
      "status_color": "#10B981"
    }
  ],
  "summary": {
    "total_orders": 50,
    "on_time": 35,
    "delayed": 10,
    "pending": 5,
    "avg_planned_duration": 12.5,
    "avg_actual_duration": 13.2,
    "on_time_rate": 70.0
  },
  "filters_applied": {
    "date_from": null,
    "date_to": null,
    "status": null,
    "ship_from": null,
    "ship_to": null,
    "limit": 50
  }
}
```

#### Response Fields

**Data Object:**
| Field | Type | Description |
|-------|------|-------------|
| `order_no` | string | Order number (unique identifier) |
| `order_origin_code` | string | Origin code |
| `order_origin` | string | Origin name |
| `trx_type` | string | Transaction type |
| `ship_from` | string | Warehouse asal code |
| `ship_from_desc` | string | Warehouse asal description |
| `ship_to` | string | Warehouse tujuan code |
| `ship_to_desc` | string | Warehouse tujuan description |
| `order_date` | date | Tanggal order dibuat |
| `planned_delivery_date` | date | Tanggal delivery yang direncanakan |
| `actual_receipt_date` | date | Tanggal actual receipt (null jika pending) |
| `total_lines` | integer | Jumlah line items dalam order |
| `total_order_qty` | decimal | Total quantity yang dipesan |
| `total_ship_qty` | decimal | Total quantity yang dikirim |
| `statuses` | string | Status line items (comma separated) |
| `delivery_status` | string | Status delivery: `on_time`, `delayed`, `pending` |
| `days_difference` | integer | Selisih hari actual vs planned (negative = early) |
| `planned_duration_days` | integer | Durasi planned (order → delivery) |
| `actual_duration_days` | integer | Durasi actual (order → receipt) |
| `fulfillment_rate` | decimal | Persentase fulfillment (ship_qty / order_qty) |
| `status_color` | string | Hex color untuk UI badge |

**Summary Object:**
| Field | Type | Description |
|-------|------|-------------|
| `total_orders` | integer | Total orders dalam response |
| `on_time` | integer | Jumlah orders on-time |
| `delayed` | integer | Jumlah orders delayed |
| `pending` | integer | Jumlah orders pending |
| `avg_planned_duration` | decimal | Rata-rata durasi planned (hari) |
| `avg_actual_duration` | decimal | Rata-rata durasi actual (hari) |
| `on_time_rate` | decimal | Persentase on-time rate |

---

### 2. Get Order Timeline Detail
**GET** `/api/dashboard/warehouse/order-timeline/{orderNo}`

Mendapatkan detail line items untuk order tertentu (drill-down dari Gantt Chart).

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `orderNo` | string | Yes | Order number |

#### Example Request
```bash
GET /api/dashboard/warehouse/order-timeline/SO-2025-001
```

#### Response Structure
```json
{
  "order_summary": {
    "order_no": "SO-2025-001",
    "total_lines": 5,
    "total_order_qty": 1000,
    "total_ship_qty": 950,
    "earliest_order_date": "2025-10-01",
    "latest_receipt_date": "2025-10-14",
    "overall_status": "on_time",
    "ship_from": "WH01",
    "ship_from_desc": "Main Warehouse",
    "ship_to": "WH02",
    "ship_to_desc": "Distribution Center"
  },
  "order_lines": [
    {
      "order_no": "SO-2025-001",
      "line_no": 1,
      "order_date": "2025-10-01",
      "delivery_date": "2025-10-15",
      "receipt_date": "2025-10-14",
      "item_code": "ITEM001",
      "item_desc": "Product Name",
      "item_desc2": "Additional Description",
      "order_qty": 200,
      "ship_qty": 200,
      "unit": "PCS",
      "line_status_code": "SHP",
      "line_status": "Shipped",
      "ship_from": "WH01",
      "ship_from_desc": "Main Warehouse",
      "ship_to": "WH02",
      "ship_to_desc": "Distribution Center",
      "delivery_status": "on_time",
      "days_difference": -1,
      "fulfillment_rate": 100.0
    }
  ]
}
```

#### Error Response (404)
```json
{
  "error": "Order not found"
}
```

---

### 3. Get Timeline Filters
**GET** `/api/dashboard/warehouse/order-timeline/filters`

Mendapatkan daftar filter options untuk dropdown (warehouses, destinations, statuses, date range).

#### Example Request
```bash
GET /api/dashboard/warehouse/order-timeline/filters
```

#### Response Structure
```json
{
  "warehouses": [
    {
      "value": "WH01",
      "label": "Main Warehouse"
    },
    {
      "value": "WH02",
      "label": "Distribution Center"
    }
  ],
  "destinations": [
    {
      "value": "WH02",
      "label": "Distribution Center"
    },
    {
      "value": "WH03",
      "label": "Regional Warehouse"
    }
  ],
  "statuses": [
    {
      "value": "on_time",
      "label": "On Time",
      "color": "#10B981"
    },
    {
      "value": "delayed",
      "label": "Delayed",
      "color": "#EF4444"
    },
    {
      "value": "pending",
      "label": "Pending",
      "color": "#F59E0B"
    }
  ],
  "date_range": {
    "min": "2025-01-01",
    "max": "2025-12-31"
  }
}
```

---

## Visualization Guidelines

### Gantt Chart Structure

```
Order Timeline Gantt Chart
═══════════════════════════════════════════════════════════════

Order No    │ Timeline
────────────┼─────────────────────────────────────────────────
SO-001      │ ●─────────────●═════════●  [On Time]  
            │ Oct 1        Oct 15    Oct 14
            │ Order        Planned   Actual
            │ Date        Delivery  Receipt
────────────┼─────────────────────────────────────────────────
SO-002      │ ●─────────────●══════════════●  [Delayed]
            │ Oct 2        Oct 16        Oct 18
────────────┼─────────────────────────────────────────────────
SO-003      │ ●─────────────●  [Pending]
            │ Oct 3        Oct 17
            │
Legend:
● Order Date
─ Planned period
═ Actual period (after planned date)
```

### Timeline Elements

1. **Order Date (Start)**: First milestone
2. **Planned Delivery Date**: Target milestone (dashed line if not reached)
3. **Actual Receipt Date**: End milestone (if completed)

### Status Colors

- **On Time** (#10B981 - Green): `actual_receipt_date <= planned_delivery_date`
- **Delayed** (#EF4444 - Red): `actual_receipt_date > planned_delivery_date`
- **Pending** (#F59E0B - Yellow): `actual_receipt_date IS NULL`

### Chart Features

1. **Grouping**: By order_no
2. **Timeline**: order_date → planned_delivery_date → actual_receipt_date
3. **Duration**: Show planned_duration_days and actual_duration_days
4. **Fulfillment**: Display fulfillment_rate percentage
5. **Drill-down**: Click order to see line items detail

---

## Performance Optimization

### Built-in Optimizations

1. **Limit Control**: Default 50, max 100 orders
2. **Query Aggregation**: Group by order_no to reduce data volume
3. **Indexed Columns**: order_no, order_date, ship_from
4. **Selective Fields**: Only necessary fields returned

### Recommended Usage

```javascript
// Initial load: Get recent 50 orders
GET /api/dashboard/warehouse/order-timeline

// Filter by date range for specific period
GET /api/dashboard/warehouse/order-timeline?date_from=2025-10-01&date_to=2025-10-31

// Focus on problem areas
GET /api/dashboard/warehouse/order-timeline?status=delayed&limit=100
```

---

## Frontend Integration Example

### React/Vue Component

```javascript
// Fetch timeline data
const fetchTimeline = async (filters = {}) => {
  const params = new URLSearchParams({
    limit: filters.limit || 50,
    ...filters
  });
  
  const response = await fetch(
    `/api/dashboard/warehouse/order-timeline?${params}`
  );
  const data = await response.json();
  
  return data;
};

// Fetch filters for dropdowns
const fetchFilters = async () => {
  const response = await fetch(
    '/api/dashboard/warehouse/order-timeline/filters'
  );
  const filters = await response.json();
  
  return filters;
};

// Fetch order detail on click
const fetchOrderDetail = async (orderNo) => {
  const response = await fetch(
    `/api/dashboard/warehouse/order-timeline/${orderNo}`
  );
  const detail = await response.json();
  
  return detail;
};
```

### Chart.js Gantt Configuration

```javascript
// Transform API data for Chart.js
const transformForGantt = (apiData) => {
  return apiData.data.map(order => ({
    x: [
      new Date(order.order_date),
      new Date(order.actual_receipt_date || order.planned_delivery_date)
    ],
    y: order.order_no,
    backgroundColor: order.status_color,
    label: `${order.order_no} - ${order.delivery_status}`,
    metadata: {
      totalLines: order.total_lines,
      fulfillmentRate: order.fulfillment_rate,
      daysPlanned: order.planned_duration_days,
      daysActual: order.actual_duration_days
    }
  }));
};
```

---

## Use Cases

### 1. Monitor Active Orders
```bash
GET /api/dashboard/warehouse/order-timeline?status=pending&limit=50
```

### 2. Analyze Delayed Shipments
```bash
GET /api/dashboard/warehouse/order-timeline?status=delayed&date_from=2025-10-01&date_to=2025-10-31
```

### 3. Warehouse Performance Review
```bash
GET /api/dashboard/warehouse/order-timeline?ship_from=WH01&limit=100
```

### 4. Customer Delivery Tracking
```bash
GET /api/dashboard/warehouse/order-timeline?ship_to=CUST001
```

---

## Best Practices

1. **Always use date filters** for large datasets to improve performance
2. **Set appropriate limit** based on UI capabilities (50 for mobile, 100 for desktop)
3. **Cache filter options** as they change infrequently
4. **Implement pagination** if needed for very large result sets
5. **Use drill-down API** for detailed information only when needed

---

## Database Schema Reference

### Table: `view_warehouse_order_line`

Key columns used:
- `order_no` - Order identifier
- `order_date` - Order creation date
- `delivery_date` - Planned delivery date
- `receipt_date` - Actual receipt date
- `ship_from` / `ship_to` - Warehouse locations
- `order_qty` / `ship_qty` - Quantities
- `line_status` - Status per line

---

## Changelog

### Version 1.0 (Nov 2025)
- Initial release with Gantt Chart support
- Aggregation by order_no
- Timeline with 3 milestones (order/planned/actual)
- Status indicators (on-time/delayed/pending)
- Filter by date range, status, warehouse
- Drill-down to line items detail
- Filter options API
