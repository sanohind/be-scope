# API Documentation - Dashboard 3, 4 & 5

## Table of Contents
- [Dashboard 3: Production Planning & Monitoring](#dashboard-3-production-planning--monitoring)
- [Dashboard 4: Sales & Shipment Analysis](#dashboard-4-sales--shipment-analysis)
- [Dashboard 5: Procurement & Receipt Analysis](#dashboard-5-procurement--receipt-analysis)

---

## Dashboard 3: Production Planning & Monitoring

Base URL: `/api/dashboard/production`

### 3.1 Production KPI Summary
**Endpoint:** `GET /api/dashboard/production/kpi-summary`

**Description:** Provides key performance indicators for production operations including total orders, quantities, and completion rates.

**Query Parameters:** None

**Response Example:**
```json
{
  "total_production_orders": 350,
  "total_qty_ordered": 125000,
  "total_qty_delivered": 110000,
  "total_outstanding_qty": 15000,
  "completion_rate": 88.0
}
```

**Response Fields:**
- `total_production_orders` (integer): Total number of unique production orders
- `total_qty_ordered` (float): Total quantity ordered
- `total_qty_delivered` (float): Total quantity delivered
- `total_outstanding_qty` (float): Total outstanding quantity
- `completion_rate` (float): Percentage of ordered quantity that has been delivered

---

### 3.2 Production Status Distribution
**Endpoint:** `GET /api/dashboard/production/status-distribution`

**Description:** Shows distribution of production orders by status, ideal for pie or donut charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "data": [
    {
      "status": "In Progress",
      "count": 120,
      "total_qty": 45000
    },
    {
      "status": "Completed",
      "count": 180,
      "total_qty": 65000
    },
    {
      "status": "Pending",
      "count": 50,
      "total_qty": 15000
    }
  ],
  "total_orders": 350
}
```

**Response Fields:**
- `data` (array): Status breakdown
  - `status` (string): Production status
  - `count` (integer): Number of production orders
  - `total_qty` (float): Total quantity for this status
- `total_orders` (integer): Total number of orders

---

### 3.3 Production by Customer
**Endpoint:** `GET /api/dashboard/production/by-customer`

**Description:** Breaks down production by customer with ordered, delivered, and outstanding quantities.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 15 | Number of customers to return |

**Response Example:**
```json
[
  {
    "customer": "PT ABC Manufacturing",
    "qty_ordered": 35000,
    "qty_delivered": 32000,
    "qty_outstanding": 3000
  },
  {
    "customer": "PT XYZ Industries",
    "qty_ordered": 28000,
    "qty_delivered": 25000,
    "qty_outstanding": 3000
  }
]
```

**Response Fields:**
- `customer` (string): Customer name
- `qty_ordered` (float): Total quantity ordered
- `qty_delivered` (float): Total quantity delivered
- `qty_outstanding` (float): Outstanding quantity

---

### 3.4 Production by Model
**Endpoint:** `GET /api/dashboard/production/by-model`

**Description:** Shows production volume grouped by product model and customer.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 20 | Number of models to return |

**Response Example:**
```json
[
  {
    "model": "MODEL-A-2024",
    "customer": "PT ABC Manufacturing",
    "total_qty": 15000,
    "total_orders": 25
  },
  {
    "model": "MODEL-B-2024",
    "customer": "PT XYZ Industries",
    "total_qty": 12000,
    "total_orders": 18
  }
]
```

**Response Fields:**
- `model` (string): Product model
- `customer` (string): Customer name
- `total_qty` (float): Total quantity
- `total_orders` (integer): Number of production orders

---

### 3.5 Production Schedule Timeline
**Endpoint:** `GET /api/dashboard/production/schedule-timeline`

**Description:** Provides production schedule data for timeline or Gantt chart visualization.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| status | string | No | - | Filter by production status |
| customer | string | No | - | Filter by customer |
| divisi | string | No | - | Filter by division |
| date_from | date | No | - | Start date filter (YYYY-MM-DD) |
| date_to | date | No | - | End date filter (YYYY-MM-DD) |

**Response Example:**
```json
[
  {
    "prod_no": "PROD-2024-001",
    "planning_date": "2024-01-15",
    "item": "ITEM-001",
    "description": "Product Description",
    "customer": "PT ABC Manufacturing",
    "model": "MODEL-A-2024",
    "status": "In Progress",
    "qty_order": 5000,
    "qty_delivery": 3500,
    "qty_os": 1500,
    "divisi": "Division A"
  }
]
```

**Response Fields:**
- `prod_no` (string): Production order number
- `planning_date` (date): Planned production date
- `item` (string): Item code
- `description` (string): Item description
- `customer` (string): Customer name
- `model` (string): Product model
- `status` (string): Production status
- `qty_order` (float): Ordered quantity
- `qty_delivery` (float): Delivered quantity
- `qty_os` (float): Outstanding quantity
- `divisi` (string): Division name

---

### 3.6 Production Outstanding Analysis
**Endpoint:** `GET /api/dashboard/production/outstanding-analysis`

**Description:** Analyzes outstanding production orders with completion percentages and progress tracking.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| status | string | No | - | Filter by production status |
| customer | string | No | - | Filter by customer |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |
| sort_by | string | No | percent_complete | Sort by: `percent_complete`, `qty_os` |
| sort_order | string | No | asc | Sort order: `asc`, `desc` |

**Response Example:**
```json
[
  {
    "prod_no": "PROD-2024-001",
    "planning_date": "2024-01-15",
    "item": "ITEM-001",
    "description": "Product Description",
    "customer": "PT ABC Manufacturing",
    "qty_order": 5000,
    "qty_delivery": 3500,
    "qty_os": 1500,
    "status": "In Progress",
    "divisi": "Division A",
    "percent_complete": 70.0
  }
]
```

**Response Fields:**
- `prod_no` (string): Production order number
- `planning_date` (date): Planning date
- `item` (string): Item code
- `description` (string): Item description
- `customer` (string): Customer name
- `qty_order` (float): Ordered quantity
- `qty_delivery` (float): Delivered quantity
- `qty_os` (float): Outstanding quantity
- `status` (string): Production status
- `divisi` (string): Division name
- `percent_complete` (float): Completion percentage

---

### 3.7 Production by Division
**Endpoint:** `GET /api/dashboard/production/by-division`

**Description:** Groups production data by division and status, suitable for stacked bar charts.

**Query Parameters:** None

**Response Example:**
```json
[
  {
    "divisi": "Division A",
    "status": "In Progress",
    "production_volume": 45000,
    "total_orders": 85,
    "avg_completion_rate": 75.5
  },
  {
    "divisi": "Division A",
    "status": "Completed",
    "production_volume": 35000,
    "total_orders": 65,
    "avg_completion_rate": 100.0
  }
]
```

**Response Fields:**
- `divisi` (string): Division name
- `status` (string): Production status
- `production_volume` (float): Total production volume
- `total_orders` (integer): Number of orders
- `avg_completion_rate` (float): Average completion rate percentage

---

### 3.8 Production Trend
**Endpoint:** `GET /api/dashboard/production/trend`

**Description:** Shows production trends over time with ordered vs delivered quantities and achievement rates.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| group_by | string | No | monthly | Grouping: `weekly`, `monthly` |
| customer | string | No | - | Filter by customer |
| divisi | string | No | - | Filter by division |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |

**Response Example:**
```json
[
  {
    "period": "2024-01",
    "qty_ordered": 25000,
    "qty_delivered": 22000,
    "achievement_rate": 88.0
  },
  {
    "period": "2024-02",
    "qty_ordered": 28000,
    "qty_delivered": 26000,
    "achievement_rate": 92.86
  }
]
```

**Response Fields:**
- `period` (string): Time period (format depends on group_by)
- `qty_ordered` (float): Total quantity ordered
- `qty_delivered` (float): Total quantity delivered
- `achievement_rate` (float): Delivery achievement percentage

---

### 3.9 Production by Warehouse
**Endpoint:** `GET /api/dashboard/production/by-warehouse`

**Description:** Groups production data by warehouse, division, and customer for treemap visualization.

**Query Parameters:** None

**Response Example:**
```json
{
  "Warehouse A": {
    "Division A": {
      "PT ABC Manufacturing": [
        {
          "warehouse": "Warehouse A",
          "divisi": "Division A",
          "customer": "PT ABC Manufacturing",
          "qty_order": 15000,
          "qty_delivery": 13500,
          "status": "In Progress",
          "completion_rate": 90.0
        }
      ]
    }
  }
}
```

**Response Fields:** Nested object grouped by warehouse, division, and customer
- `warehouse` (string): Warehouse name
- `divisi` (string): Division name
- `customer` (string): Customer name
- `qty_order` (float): Ordered quantity
- `qty_delivery` (float): Delivered quantity
- `status` (string): Production status
- `completion_rate` (float): Completion percentage

---

### 3.10 Get All Dashboard 3 Data
**Endpoint:** `GET /api/dashboard/production/all-data`

**Description:** Retrieves all Dashboard 3 charts data in a single API call.

**Query Parameters:** Accepts all query parameters from individual endpoints

**Response:** Combined JSON object with all chart data

---

## Dashboard 4: Sales & Shipment Analysis

Base URL: `/api/dashboard/sales`

### 4.1 Sales Overview KPI
**Endpoint:** `GET /api/dashboard/sales/overview-kpi`

**Description:** Provides key performance indicators for sales overview including total sales amount, shipments, invoices, and growth metrics.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| period | string | No | mtd | Period filter: `mtd` (Month to Date), `qtd` (Quarter to Date), `ytd` (Year to Date) |

**Response Example:**
```json
{
  "total_sales_amount": 15000000.50,
  "total_shipments": 245,
  "total_invoices": 180,
  "outstanding_invoices": 35,
  "sales_growth": 12.5,
  "period": "mtd"
}
```

**Response Fields:**
- `total_sales_amount` (float): Total sales amount in home currency
- `total_shipments` (integer): Total number of unique shipments
- `total_invoices` (integer): Total number of unique invoices
- `outstanding_invoices` (integer): Number of outstanding invoices
- `sales_growth` (float): Percentage growth compared to previous period
- `period` (string): Applied period filter

---

### 4.2 Revenue Trend
**Endpoint:** `GET /api/dashboard/sales/revenue-trend`

**Description:** Shows revenue trends over time with invoice counts, useful for area charts with line visualization.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| group_by | string | No | monthly | Grouping period: `daily`, `weekly`, `monthly` |
| customer | string | No | - | Filter by customer name |
| product_type | string | No | - | Filter by product type |
| date_from | date | No | - | Start date filter (YYYY-MM-DD) |
| date_to | date | No | - | End date filter (YYYY-MM-DD) |

**Response Example:**
```json
[
  {
    "period": "2024-01",
    "revenue": 5000000.00,
    "invoice_count": 45
  },
  {
    "period": "2024-02",
    "revenue": 6200000.00,
    "invoice_count": 52
  }
]
```

**Response Fields:**
- `period` (string): Time period (format depends on group_by parameter)
- `revenue` (float): Total revenue for the period
- `invoice_count` (integer): Number of invoices in the period

---

### 4.3 Top Customers by Revenue
**Endpoint:** `GET /api/dashboard/sales/top-customers-by-revenue`

**Description:** Returns top customers ranked by total revenue with detailed metrics.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 20 | Number of top customers to return |

**Response Example:**
```json
[
  {
    "bp_name": "PT ABC Indonesia",
    "total_revenue": 8500000.00,
    "number_of_orders": 125,
    "avg_order_value": 68000.00,
    "total_qty": 15000,
    "avg_price": 566.67,
    "revenue_contribution": 15.5
  }
]
```

**Response Fields:**
- `bp_name` (string): Customer/Business Partner name
- `total_revenue` (float): Total revenue from customer
- `number_of_orders` (integer): Number of unique sales orders
- `avg_order_value` (float): Average order value
- `total_qty` (float): Total quantity delivered
- `avg_price` (float): Average price per unit
- `revenue_contribution` (float): Percentage contribution to total revenue

---

### 4.4 Sales by Product Type
**Endpoint:** `GET /api/dashboard/sales/by-product-type`

**Description:** Breaks down sales by product type with revenue distribution, ideal for donut charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "data": [
    {
      "product_type": "Finished Goods",
      "revenue": 12000000.00,
      "qty_sold": 5000,
      "invoice_count": 120,
      "percentage": 65.5
    },
    {
      "product_type": "Raw Materials",
      "revenue": 6300000.00,
      "qty_sold": 8000,
      "invoice_count": 85,
      "percentage": 34.5
    }
  ],
  "total_revenue": 18300000.00
}
```

**Response Fields:**
- `data` (array): Array of product type breakdowns
  - `product_type` (string): Product category
  - `revenue` (float): Total revenue for product type
  - `qty_sold` (float): Total quantity sold
  - `invoice_count` (integer): Number of invoices
  - `percentage` (float): Percentage of total revenue
- `total_revenue` (float): Grand total revenue

---

### 4.5 Shipment Status Tracking
**Endpoint:** `GET /api/dashboard/sales/shipment-status-tracking`

**Description:** Tracks shipment flow from sales order to payment with conversion rates, useful for funnel charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "stages": {
    "sales_orders_created": 500,
    "shipments_generated": 480,
    "receipts_confirmed": 450,
    "invoices_issued": 440,
    "invoices_paid": 380
  },
  "conversion_rates": {
    "sales_to_shipment": 96.0,
    "shipment_to_receipt": 93.75,
    "receipt_to_invoice": 97.78,
    "invoice_to_paid": 86.36
  }
}
```

**Response Fields:**
- `stages` (object): Count of items at each stage
- `conversion_rates` (object): Percentage conversion between stages

---

### 4.6 Delivery Performance
**Endpoint:** `GET /api/dashboard/sales/delivery-performance`

**Description:** Measures delivery performance with on-time delivery rate, suitable for gauge charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "on_time_delivery_rate": 92.5,
  "early_delivery_percentage": 15.3,
  "on_time_delivery_percentage": 77.2,
  "late_delivery_percentage": 7.5,
  "total_deliveries": 450,
  "target": 95
}
```

**Response Fields:**
- `on_time_delivery_rate` (float): Combined early + on-time percentage
- `early_delivery_percentage` (float): Percentage of early deliveries
- `on_time_delivery_percentage` (float): Percentage of on-time deliveries
- `late_delivery_percentage` (float): Percentage of late deliveries
- `total_deliveries` (integer): Total number of deliveries
- `target` (integer): Target on-time delivery rate

---

### 4.7 Invoice Status Distribution
**Endpoint:** `GET /api/dashboard/sales/invoice-status-distribution`

**Description:** Shows invoice status distribution over time or by customer, ideal for stacked bar charts.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| group_by | string | No | monthly | Group by: `monthly`, `customer` |

**Response Example:**
```json
[
  {
    "category": "2024-01",
    "invoice_status": "Paid",
    "count": 45
  },
  {
    "category": "2024-01",
    "invoice_status": "Outstanding",
    "count": 12
  }
]
```

**Response Fields:**
- `category` (string): Period or customer name (depends on group_by)
- `invoice_status` (string): Invoice status
- `count` (integer): Number of invoices

---

### 4.8 Sales Order Fulfillment
**Endpoint:** `GET /api/dashboard/sales/order-fulfillment`

**Description:** Compares delivered vs invoiced quantities with fulfillment rates.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| group_by | string | No | period | Group by: `period`, `product_type` |

**Response Example:**
```json
[
  {
    "category": "2024-01",
    "delivered_qty": 5000,
    "invoiced_qty": 4800,
    "fulfillment_rate": 96.0
  }
]
```

**Response Fields:**
- `category` (string): Period or product type
- `delivered_qty` (float): Total delivered quantity
- `invoiced_qty` (float): Total invoiced quantity
- `fulfillment_rate` (float): Percentage of delivered qty that was invoiced

---

### 4.9 Top Selling Products
**Endpoint:** `GET /api/dashboard/sales/top-selling-products`

**Description:** Lists top-selling products with detailed sales metrics.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 50 | Number of products to return |
| product_type | string | No | - | Filter by product type |
| customer | string | No | - | Filter by customer |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |

**Response Example:**
```json
[
  {
    "rank": 1,
    "part_no": "P-12345",
    "old_partno": "OLD-12345",
    "cust_partname": "Custom Part Name",
    "total_qty_sold": 5000,
    "total_amount": 2500000.00,
    "number_of_orders": 45,
    "avg_price": 500.00
  }
]
```

**Response Fields:**
- `rank` (integer): Product ranking
- `part_no` (string): Part number
- `old_partno` (string): Old part number
- `cust_partname` (string): Customer part name
- `total_qty_sold` (float): Total quantity sold
- `total_amount` (float): Total sales amount
- `number_of_orders` (integer): Number of orders
- `avg_price` (float): Average price per unit

---

### 4.10 Revenue by Currency
**Endpoint:** `GET /api/dashboard/sales/revenue-by-currency`

**Description:** Breaks down revenue by currency with conversion to home currency.

**Query Parameters:** None

**Response Example:**
```json
{
  "data": [
    {
      "currency": "IDR",
      "amount_original": 15000000000.00,
      "amount_hc": 15000000.00,
      "invoice_count": 120,
      "percentage": 75.0
    },
    {
      "currency": "USD",
      "amount_original": 350000.00,
      "amount_hc": 5000000.00,
      "invoice_count": 25,
      "percentage": 25.0
    }
  ],
  "total_amount_hc": 20000000.00
}
```

**Response Fields:**
- `data` (array): Currency breakdown
  - `currency` (string): Currency code
  - `amount_original` (float): Amount in original currency
  - `amount_hc` (float): Amount in home currency
  - `invoice_count` (integer): Number of invoices
  - `percentage` (float): Percentage of total revenue
- `total_amount_hc` (float): Total in home currency

---

### 4.11 Monthly Sales Comparison
**Endpoint:** `GET /api/dashboard/sales/monthly-sales-comparison`

**Description:** Compares sales between current and previous year with YoY growth.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| current_year | integer | No | Current year | Year to analyze |
| product_type | string | No | - | Filter by product type |
| customer | string | No | - | Filter by customer |

**Response Example:**
```json
[
  {
    "month": "01",
    "current_year_revenue": 5000000.00,
    "previous_year_revenue": 4500000.00,
    "yoy_growth": 11.11
  }
]
```

**Response Fields:**
- `month` (string): Month number (01-12)
- `current_year_revenue` (float): Revenue for current year
- `previous_year_revenue` (float): Revenue for previous year
- `yoy_growth` (float): Year-over-year growth percentage

---

### 4.12 Get All Dashboard 4 Data
**Endpoint:** `GET /api/dashboard/sales/all-data`

**Description:** Retrieves all Dashboard 4 charts data in a single API call.

**Query Parameters:** Accepts all query parameters from individual endpoints

**Response:** Combined JSON object with all chart data

---

## Dashboard 5: Procurement & Receipt Analysis

Base URL: `/api/dashboard/procurement`

### 5.1 Procurement KPI
**Endpoint:** `GET /api/dashboard/procurement/kpi`

**Description:** Key performance indicators for procurement operations.

**Query Parameters:** None

**Response Example:**
```json
{
  "total_po_value": 25000000.00,
  "total_receipts": 350,
  "total_approved_qty": 45000,
  "pending_receipts": 15,
  "average_receipt_time": 3.5,
  "receipt_accuracy_rate": 98.5
}
```

**Response Fields:**
- `total_po_value` (float): Total purchase order value
- `total_receipts` (integer): Total number of receipts
- `total_approved_qty` (float): Total approved quantity
- `pending_receipts` (integer): Number of pending receipts
- `average_receipt_time` (float): Average receipt processing time in days
- `receipt_accuracy_rate` (float): Percentage of approved vs actual received

---

### 5.2 Receipt Performance
**Endpoint:** `GET /api/dashboard/procurement/receipt-performance`

**Description:** Measures receipt fulfillment and approval rates, suitable for gauge charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "receipt_fulfillment_rate": {
    "value": 97.5,
    "target": 98,
    "status": "yellow"
  },
  "approval_rate": {
    "value": 96.2,
    "target": 95,
    "status": "green"
  }
}
```

**Response Fields:**
- `receipt_fulfillment_rate` (object): Fulfillment rate metrics
  - `value` (float): Actual fulfillment rate
  - `target` (integer): Target rate
  - `status` (string): Status indicator: `green`, `yellow`, `red`
- `approval_rate` (object): Approval rate metrics

---

### 5.3 Top Suppliers by Value
**Endpoint:** `GET /api/dashboard/procurement/top-suppliers-by-value`

**Description:** Lists top suppliers ranked by total receipt value.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 20 | Number of suppliers to return |

**Response Example:**
```json
[
  {
    "bp_name": "PT Supplier ABC",
    "total_receipt_amount": 8500000.00,
    "number_of_pos": 45,
    "avg_po_value": 188888.89,
    "receipt_count": 120
  }
]
```

**Response Fields:**
- `bp_name` (string): Supplier name
- `total_receipt_amount` (float): Total receipt amount
- `number_of_pos` (integer): Number of purchase orders
- `avg_po_value` (float): Average PO value
- `receipt_count` (integer): Number of receipts

---

### 5.4 Receipt Trend
**Endpoint:** `GET /api/dashboard/procurement/receipt-trend`

**Description:** Shows receipt trends over time with amounts and quantities.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| group_by | string | No | monthly | Grouping: `daily`, `weekly`, `monthly` |
| supplier | string | No | - | Filter by supplier |
| item_group | string | No | - | Filter by item group |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |

**Response Example:**
```json
[
  {
    "period": "2024-01",
    "receipt_amount": 3500000.00,
    "receipt_qty": 15000,
    "receipt_count": 45
  }
]
```

**Response Fields:**
- `period` (string): Time period
- `receipt_amount` (float): Total receipt amount
- `receipt_qty` (float): Total receipt quantity
- `receipt_count` (integer): Number of receipts

---

### 5.5 Supplier Delivery Performance
**Endpoint:** `GET /api/dashboard/procurement/supplier-delivery-performance`

**Description:** Analyzes supplier performance based on delivery time and accuracy, ideal for scatter plots.

**Query Parameters:** None

**Response Example:**
```json
[
  {
    "bp_name": "PT Supplier ABC",
    "delivery_time_variance": 2.5,
    "accuracy_rate": 98.5,
    "total_receipt_value": 8500000.00,
    "receipt_count": 120
  }
]
```

**Response Fields:**
- `bp_name` (string): Supplier name
- `delivery_time_variance` (float): Average delivery time variance in days
- `accuracy_rate` (float): Receipt accuracy rate percentage
- `total_receipt_value` (float): Total receipt value
- `receipt_count` (integer): Number of receipts

---

### 5.6 Receipt by Item Group
**Endpoint:** `GET /api/dashboard/procurement/receipt-by-item-group`

**Description:** Groups receipts by item group and type, suitable for treemap visualization.

**Query Parameters:** None

**Response Example:**
```json
{
  "Raw Materials": {
    "Type A": [
      {
        "item_no": "RM-001",
        "item_desc": "Steel Sheet",
        "receipt_amount": 500000.00,
        "actual_receipt_qty": 1000,
        "supplier_count": 3,
        "total_receipts": 15
      }
    ]
  }
}
```

**Response Fields:** Nested object grouped by item_group and item_type
- `item_no` (string): Item number
- `item_desc` (string): Item description
- `receipt_amount` (float): Receipt amount
- `actual_receipt_qty` (float): Actual receipt quantity
- `supplier_count` (integer): Number of suppliers
- `total_receipts` (integer): Total receipts

---

### 5.7 PO vs Invoice Status
**Endpoint:** `GET /api/dashboard/procurement/po-vs-invoice-status`

**Description:** Tracks purchase order flow from PO to payment, ideal for waterfall charts.

**Query Parameters:** None

**Response Example:**
```json
{
  "total_po_amount": 25000000.00,
  "not_yet_received": 2000000.00,
  "received_amount": 23000000.00,
  "not_yet_invoiced": 3000000.00,
  "invoiced_amount": 20000000.00,
  "not_yet_paid": 5000000.00,
  "paid_amount": 15000000.00
}
```

**Response Fields:**
- `total_po_amount` (float): Total PO amount
- `not_yet_received` (float): Amount not yet received
- `received_amount` (float): Amount received
- `not_yet_invoiced` (float): Received but not invoiced
- `invoiced_amount` (float): Amount invoiced
- `not_yet_paid` (float): Invoiced but not paid
- `paid_amount` (float): Amount paid

---

### 5.8 Outstanding PO Analysis
**Endpoint:** `GET /api/dashboard/procurement/outstanding-po-analysis`

**Description:** Lists outstanding purchase orders with aging analysis.

**Query Parameters:** None

**Response Example:**
```json
[
  {
    "po_no": "PO-2024-001",
    "bp_name": "PT Supplier ABC",
    "part_no": "RM-001",
    "item_desc": "Steel Sheet",
    "request_qty": 1000,
    "actual_receipt_qty": 800,
    "pending_qty": 200,
    "actual_receipt_date": "2024-01-15",
    "days_outstanding": 45,
    "is_final_receipt": 0,
    "status": "red"
  }
]
```

**Response Fields:**
- `po_no` (string): Purchase order number
- `bp_name` (string): Supplier name
- `part_no` (string): Part number
- `item_desc` (string): Item description
- `request_qty` (float): Requested quantity
- `actual_receipt_qty` (float): Actual received quantity
- `pending_qty` (float): Pending quantity
- `actual_receipt_date` (date): Receipt date
- `days_outstanding` (integer): Days outstanding
- `is_final_receipt` (integer): Final receipt flag (0 or 1)
- `status` (string): Status indicator: `green` (<15 days), `yellow` (15-30 days), `red` (>30 days)

---

### 5.9 Receipt Approval Rate by Supplier
**Endpoint:** `GET /api/dashboard/procurement/receipt-approval-rate-by-supplier`

**Description:** Shows receipt approval rates for each supplier with color coding.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 20 | Number of suppliers to return |

**Response Example:**
```json
{
  "data": [
    {
      "bp_name": "PT Supplier ABC",
      "approval_rate": 98.5,
      "total_receipts": 120,
      "rejected_qty": 150,
      "status": "green"
    }
  ],
  "target": 95
}
```

**Response Fields:**
- `data` (array): Supplier approval data
  - `bp_name` (string): Supplier name
  - `approval_rate` (float): Approval rate percentage
  - `total_receipts` (integer): Total receipts
  - `rejected_qty` (float): Total rejected quantity
  - `status` (string): Status: `green` (>95%), `yellow` (90-95%), `red` (<90%)
- `target` (integer): Target approval rate

---

### 5.10 Purchase Price Trend
**Endpoint:** `GET /api/dashboard/procurement/purchase-price-trend`

**Description:** Tracks purchase price trends for top items over time.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| limit | integer | No | 15 | Number of top items to track |
| item | string | No | - | Filter by specific item |
| supplier | string | No | - | Filter by supplier |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |

**Response Example:**
```json
{
  "RM-001": [
    {
      "item_no": "RM-001",
      "item_desc": "Steel Sheet",
      "actual_receipt_date": "2024-01-15",
      "avg_unit_price": 5000.00
    }
  ]
}
```

**Response Fields:** Object grouped by item_no
- `item_no` (string): Item number
- `item_desc` (string): Item description
- `actual_receipt_date` (date): Receipt date
- `avg_unit_price` (float): Average unit price

---

### 5.11 Payment Status Tracking
**Endpoint:** `GET /api/dashboard/procurement/payment-status-tracking`

**Description:** Tracks payment status over time with overdue analysis.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| supplier | string | No | - | Filter by supplier |
| date_from | date | No | - | Start date filter |
| date_to | date | No | - | End date filter |

**Response Example:**
```json
[
  {
    "period": "2024-01",
    "invoiced_not_paid": 3500000.00,
    "paid": 15000000.00,
    "overdue_count": 5
  }
]
```

**Response Fields:**
- `period` (string): Time period (YYYY-MM)
- `invoiced_not_paid` (float): Amount invoiced but not paid
- `paid` (float): Amount paid
- `overdue_count` (integer): Number of overdue invoices

---

### 5.12 Get All Dashboard 5 Data
**Endpoint:** `GET /api/dashboard/procurement/all-data`

**Description:** Retrieves all Dashboard 5 charts data in a single API call.

**Query Parameters:** Accepts all query parameters from individual endpoints

**Response:** Combined JSON object with all chart data

---

## Common Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid parameters |
| 404 | Not Found - Resource doesn't exist |
| 500 | Internal Server Error |

## Notes

1. **Date Format:** All dates should be in `YYYY-MM-DD` format
2. **Currency:** All monetary values are in home currency unless specified otherwise
3. **Filtering:** Most endpoints support additional filtering via query parameters
4. **Pagination:** Currently not implemented, use `limit` parameter where available
5. **Authentication:** Add authentication middleware if needed in production

## Examples

### cURL Example - Production KPI Summary
```bash
curl -X GET "http://localhost:8000/api/dashboard/production/kpi-summary"
```

### cURL Example - Production Trend with Filters
```bash
curl -X GET "http://localhost:8000/api/dashboard/production/trend?group_by=monthly&customer=PT%20ABC%20Manufacturing&date_from=2024-01-01&date_to=2024-12-31"
```

### cURL Example - Sales Overview KPI
```bash
curl -X GET "http://localhost:8000/api/dashboard/sales/overview-kpi?period=mtd"
```

### cURL Example - Top Customers
```bash
curl -X GET "http://localhost:8000/api/dashboard/sales/top-customers-by-revenue?limit=10"
```

### cURL Example - Receipt Trend with Filters
```bash
curl -X GET "http://localhost:8000/api/dashboard/procurement/receipt-trend?group_by=weekly&supplier=PT%20Supplier%20ABC&date_from=2024-01-01&date_to=2024-03-31"
```

### JavaScript Fetch Example
```javascript
// Get production KPI summary
fetch('/api/dashboard/production/kpi-summary')
  .then(response => response.json())
  .then(data => console.log(data));

// Get sales overview
fetch('/api/dashboard/sales/overview-kpi?period=ytd')
  .then(response => response.json())
  .then(data => console.log(data));

// Get top suppliers with limit
fetch('/api/dashboard/procurement/top-suppliers-by-value?limit=15')
  .then(response => response.json())
  .then(data => console.log(data));
```

### Axios Example
```javascript
// Get production trend with filters
axios.get('/api/dashboard/production/trend', {
  params: {
    group_by: 'monthly',
    customer: 'PT ABC Manufacturing',
    date_from: '2024-01-01',
    date_to: '2024-12-31'
  }
})
.then(response => console.log(response.data));

// Get revenue trend with filters
axios.get('/api/dashboard/sales/revenue-trend', {
  params: {
    group_by: 'monthly',
    customer: 'PT ABC Indonesia',
    date_from: '2024-01-01',
    date_to: '2024-12-31'
  }
})
.then(response => console.log(response.data));
```

---

**Last Updated:** October 31, 2024  
**Version:** 2.0  
**Maintained by:** Backend Development Team

## Summary

This documentation covers **3 dashboards** with a total of **31 API endpoints**:
- **Dashboard 3 (Production):** 9 endpoints + 1 aggregate endpoint
- **Dashboard 4 (Sales):** 11 endpoints + 1 aggregate endpoint  
- **Dashboard 5 (Procurement):** 11 endpoints + 1 aggregate endpoint
