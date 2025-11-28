# Dashboard 2 Revision API Guide

## Overview

Dashboard 2 Revision Controller provides warehouse-specific analytics for warehouse operations. All endpoints require a `warehouse` parameter to filter data by specific warehouse (WHMT01, WHFG01, WHFG02, WHRM01, WHRM02).

## Base URL

```
/api/dashboard/warehouse-rev
```

## Common Parameters

### Required Parameters

-   `warehouse` (string): Warehouse code (WHMT01, WHFG01, WHFG02, WHRM01, WHRM02)

### Optional Parameters

-   `date_from` (date): Start date filter (YYYY-MM-DD format)
-   `date_to` (date): End date filter (YYYY-MM-DD format)
-   `period` (string): Time period grouping (daily, monthly, yearly) - for applicable endpoints

## Endpoints

### 1. Warehouse Order Summary - KPI Cards

**GET** `/order-summary`

Returns key performance indicators for warehouse operations.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "total_order_lines": 1250,
    "pending_deliveries": 320,
    "completed_orders": 930,
    "average_fulfillment_rate": 94.5,
    "status_breakdown": {
        "staged": 45,
        "null_status": 12,
        "adviced": 89,
        "released": 134,
        "open": 40,
        "shipped": 930
    },
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 2. Delivery Performance - Gauge Chart

**GET** `/delivery-performance`

Shows delivery performance metrics for the specified warehouse.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "on_time_delivery_rate": 87.5,
    "target_rate": 95,
    "early_deliveries": 45,
    "on_time_deliveries": 320,
    "late_deliveries": 58,
    "total_orders": 423,
    "performance_status": "good",
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 3. Order Status Distribution - Stacked Bar Chart

**GET** `/order-status-distribution`

Shows order status distribution by transaction type for the warehouse.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": {
        "Transfer": [
            {
                "trx_type": "Transfer",
                "line_status": "Shipped",
                "count": 450,
                "percentage": 75.5
            },
            {
                "trx_type": "Transfer",
                "line_status": "Open",
                "count": 146,
                "percentage": 24.5
            }
        ],
        "Sales": [
            {
                "trx_type": "Sales",
                "line_status": "Shipped",
                "count": 320,
                "percentage": 82.1
            }
        ]
    },
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 4. Daily Order Volume - Line Chart with Area

**GET** `/daily-order-volume`

Shows order volume trends over time for the warehouse.

**Parameters:**

-   `warehouse` (required)
-   `period` (optional, default: daily) - daily, monthly, yearly
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": [
        {
            "period_date": "2024-11-15",
            "total_order_qty": 1250.5,
            "total_ship_qty": 1180.2,
            "gap_qty": 70.3,
            "order_count": 45
        },
        {
            "period_date": "2024-11-16",
            "total_order_qty": 980.0,
            "total_ship_qty": 950.5,
            "gap_qty": 29.5,
            "order_count": 32
        }
    ],
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 5. Order Fulfillment by Transaction Type - Bar Chart

**GET** `/order-fulfillment-by-transaction-type`

Shows fulfillment rates grouped by transaction type (modified from original warehouse-based).

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": [
        {
            "trx_type": "Transfer",
            "total_order_qty": 5420.5,
            "total_ship_qty": 5180.2,
            "fulfillment_rate": 95.57,
            "order_count": 234
        },
        {
            "trx_type": "Sales",
            "total_order_qty": 3250.0,
            "total_ship_qty": 2980.5,
            "fulfillment_rate": 91.71,
            "order_count": 156
        }
    ],
    "target_rate": 100,
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 6. Top Items Moved - Horizontal Bar Chart

**GET** `/top-items-moved`

Shows top items moved from the specified warehouse.

**Parameters:**

-   `warehouse` (required)
-   `limit` (optional, default: 20)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": [
        {
            "item_code": "ITM001",
            "item_desc": "Product A Description",
            "total_qty_moved": 1250.5,
            "total_orders": 45,
            "avg_qty_per_order": 27.79
        },
        {
            "item_code": "ITM002",
            "item_desc": "Product B Description",
            "total_qty_moved": 980.2,
            "total_orders": 32,
            "avg_qty_per_order": 30.63
        }
    ],
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 7. Monthly Inbound vs Outbound - Grouped Bar Chart

**GET** `/monthly-inbound-vs-outbound`

Shows inbound and outbound quantities by month for the warehouse.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 6 months ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": [
        {
            "month": "2024-06",
            "inbound": 2450.5,
            "outbound": 3120.8
        },
        {
            "month": "2024-07",
            "inbound": 2890.2,
            "outbound": 2750.4
        },
        {
            "month": "2024-08",
            "inbound": 3120.0,
            "outbound": 3450.6
        }
    ],
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-05-20",
        "to": "2024-11-19 23:59:59",
        "days": 183
    }
}
```

### 8. Top Destinations - Horizontal Bar Chart

**GET** `/top-destinations`

Shows top 10 destinations from the specified warehouse.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional, default: 30 days ago)
-   `date_to` (optional, default: today)

**Response:**

```json
{
    "data": [
        {
            "ship_to": "WHFG01",
            "ship_to_desc": "Finished Goods Warehouse 1",
            "ship_to_type": "Warehouse",
            "order_count": 145,
            "total_qty": 2450.5
        },
        {
            "ship_to": "CUST001",
            "ship_to_desc": "Customer A Location",
            "ship_to_type": "Customer",
            "order_count": 89,
            "total_qty": 1890.2
        }
    ],
    "warehouse": "WHMT01",
    "date_range": {
        "from": "2024-10-20",
        "to": "2024-11-19 23:59:59",
        "days": 30
    }
}
```

### 9. Get All Data - Combined Response

**GET** `/all-data`

Returns all dashboard data in a single API call for performance optimization.

**Parameters:**

-   `warehouse` (required)
-   `date_from` (optional)
-   `date_to` (optional)

**Response:**

```json
{
    "warehouse_order_summary": {
        /* KPI data */
    },
    "delivery_performance": {
        /* Performance data */
    },
    "order_status_distribution": {
        /* Status data */
    },
    "daily_order_volume": {
        /* Volume data */
    },
    "order_fulfillment_by_transaction_type": {
        /* Fulfillment data */
    },
    "top_items_moved": {
        /* Items data */
    },
    "monthly_inbound_vs_outbound": {
        /* Inbound/Outbound data */
    },
    "top_destinations": {
        /* Destinations data */
    }
}
```

## Error Responses

### 400 Bad Request

```json
{
    "message": "Warehouse parameter is required"
}
```

```json
{
    "message": "Invalid warehouse code"
}
```

### Valid Warehouse Codes

-   `WHMT01` - Main Warehouse
-   `WHFG01` - Finished Goods Warehouse 1
-   `WHFG02` - Finished Goods Warehouse 2
-   `WHRM01` - Raw Materials Warehouse 1
-   `WHRM02` - Raw Materials Warehouse 2

## Usage Examples

### Basic Usage

```bash
# Get KPI summary for WHMT01
curl "http://localhost:8000/api/dashboard/warehouse-rev/order-summary?warehouse=WHMT01"

# Get delivery performance for WHFG01 with date range
curl "http://localhost:8000/api/dashboard/warehouse-rev/delivery-performance?warehouse=WHFG01&date_from=2024-11-01&date_to=2024-11-19"

# Get monthly order volume for WHRM01
curl "http://localhost:8000/api/dashboard/warehouse-rev/daily-order-volume?warehouse=WHRM01&period=monthly"
```

### JavaScript Example

```javascript
// Fetch KPI data for a specific warehouse
async function getWarehouseKPI(warehouse, dateFrom, dateTo) {
    const params = new URLSearchParams({
        warehouse: warehouse,
        ...(dateFrom && { date_from: dateFrom }),
        ...(dateTo && { date_to: dateTo }),
    });

    const response = await fetch(
        `/api/dashboard/warehouse-rev/order-summary?${params}`
    );
    return await response.json();
}

// Usage
const kpiData = await getWarehouseKPI("WHMT01", "2024-11-01", "2024-11-19");
```

## Chart Type Recommendations

1. **KPI Cards**: Use card/metric components
2. **Delivery Performance**: Gauge chart with color coding
3. **Order Status Distribution**: Stacked bar chart
4. **Daily Order Volume**: Line chart with area fill
5. **Order Fulfillment by Transaction Type**: Horizontal bar chart
6. **Top Items Moved**: Horizontal bar chart
7. **Monthly Inbound vs Outbound**: Grouped bar chart (Orange for Inbound, Blue for Outbound)
8. **Top Destinations**: Horizontal bar chart with color coding by ship_to_type

## Performance Notes

-   Use the `/all-data` endpoint for dashboard initialization to reduce API calls
-   Implement caching on the frontend for frequently accessed data
-   Consider pagination for large datasets (though most endpoints are already limited)
-   Date ranges are automatically validated and constrained for performance
