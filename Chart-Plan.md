# Spesifikasi Dashboard Manufaktur

## Summary List Dashboard

1. Dashboard Inventory Management & Stock Control
2. Dashboard Warehouse Operations
3. Dashboard Production Planning & Monitoring
4. Dashboard Sales & Shipment Analysis
5. Dashboard Procurement & Receipt Analysis
6. Dashboard Supply Chain Integration
7. Dashboard Financial Overview
8. Dashboard Executive Summary (KPI Overview)

---

## 1. Dashboard Inventory Management & Stock Control

### Chart 1.1: Stock Level Overview
**Tipe Chart:** Gauge / KPI Cards
**Sumber Data:** `stockbywh`
- Total Onhand
- Items Below Safety Stock
- Items Above Max Stock

### Chart 1.2: Stock Health by Warehouse
**Tipe Chart:** Stacked Bar Chart
**Sumber Data:** `stockbywh`
- Kategorisasi: Critical (below min_stock), Low (below safety_stock), Normal, Overstock (above max_stock)
- Grouping: Per warehouse

### Chart 1.3: Top 20 Critical Items
**Tipe Chart:** Table with Conditional Formatting
**Sumber Data:** `stockbywh`
- Columns: Part No, Description, Warehouse, Onhand, Safety Stock, Min Stock, Location

### Chart 1.4: Stock by Product Type
**Tipe Chart:** Treemap
**Sumber Data:** `stockbywh`
- Hierarchy: Product Type → Model → Part No
- Size: Onhand value

### Chart 1.5: Stock by Customer
**Tipe Chart:** Pie Chart / Donut Chart
**Sumber Data:** `stockbywh`
- Breakdown stock onhand per customer

### Chart 1.6: Inventory Availability vs Demand
**Tipe Chart:** Combo Chart (Bar + Line)
**Sumber Data:** `stockbywh`
- Onhand (bar), Allocated (bar), On Order (line)
- Grouping: Per warehouse atau product group

### Chart 1.7: Stock Movement Trend
**Tipe Chart:** Area Chart
**Sumber Data:** `stockbywh` (perlu data historis)
- Onhand, Allocated, Available (onhand-allocated) per periode

---

## 2. Dashboard Warehouse Operations

### Chart 2.1: Warehouse Order Summary
**Tipe Chart:** KPI Cards
**Sumber Data:** `view_warehouse_order`
- Total Orders
- Orders by Transaction Type
- Pending Deliveries
- Completed Orders

### Chart 2.2: Order Flow Analysis
**Tipe Chart:** Sankey Diagram
**Sumber Data:** `view_warehouse_order`
- Flow: Ship From → Ship To
- Thickness: Volume orders

### Chart 2.3: Delivery Performance
**Tipe Chart:** Gauge Chart
**Sumber Data:** `view_warehouse_order_line`
- On-Time Delivery Rate (delivery_date vs plan_delivery_date)
- Early, On-Time, Late percentage

### Chart 2.4: Order Status Distribution
**Tipe Chart:** Stacked Bar Chart
**Sumber Data:** `view_warehouse_order_line`
- Status per transaction type
- Grouping: Per ship_from warehouse

### Chart 2.5: Daily Order Volume
**Tipe Chart:** Line Chart
**Sumber Data:** `view_warehouse_order_line`
- Order quantity trend per day
- Filter: Transaction type

### Chart 2.6: Order Fulfillment Rate
**Tipe Chart:** Bar Chart
**Sumber Data:** `view_warehouse_order_line`
- Ship qty vs Order qty per warehouse
- Percentage fulfillment

### Chart 2.7: Top Items Moved
**Tipe Chart:** Horizontal Bar Chart
**Sumber Data:** `view_warehouse_order_line`
- Top 20 items by movement quantity
- Columns: Item code, Description, Total qty

### Chart 2.8: Warehouse Order Timeline
**Tipe Chart:** Gantt Chart / Timeline
**Sumber Data:** `view_warehouse_order_line`
- Order date, Delivery date, Receipt date per order

---

## 3. Dashboard Production Planning & Monitoring

### Chart 3.1: Production KPI Summary
**Tipe Chart:** KPI Cards
**Sumber Data:** `view_prod_header`
- Total Production Orders
- Total Qty Ordered
- Total Qty Delivered
- Total Outstanding Qty
- Completion Rate

### Chart 3.2: Production Status Distribution
**Tipe Chart:** Pie Chart
**Sumber Data:** `view_prod_header`
- Breakdown by status field

### Chart 3.3: Production by Customer
**Tipe Chart:** Bar Chart
**Sumber Data:** `view_prod_header`
- Top customers by production volume
- Metrics: qty_order, qty_delivery

### Chart 3.4: Production by Model
**Tipe Chart:** Horizontal Bar Chart
**Sumber Data:** `view_prod_header`
- Production volume per model

### Chart 3.5: Production Schedule Timeline
**Tipe Chart:** Gantt Chart
**Sumber Data:** `view_prod_header`
- Planning date vs actual completion
- Status indicator per production order

### Chart 3.6: Production Outstanding Analysis
**Tipe Chart:** Table with Progress Bar
**Sumber Data:** `view_prod_header`
- Columns: Prod No, Item, Customer, Qty Order, Qty Delivery, Qty OS, % Complete, Status

### Chart 3.7: Production by Division
**Tipe Chart:** Stacked Bar Chart
**Sumber Data:** `view_prod_header`
- Production volume per division
- Status breakdown

### Chart 3.8: Production Trend
**Tipe Chart:** Line Chart
**Sumber Data:** `view_prod_header`
- Qty ordered vs Qty delivered per planning period

### Chart 3.9: Production by Warehouse
**Tipe Chart:** Treemap
**Sumber Data:** `view_prod_header`
- Warehouse → Division → Production volume

---

## 4. Dashboard Sales & Shipment Analysis

### Chart 4.1: Sales Overview KPI
**Tipe Chart:** KPI Cards
**Sumber Data:** `so_invoice_line`
- Total Sales Amount (amount_hc)
- Total Shipments
- Total Invoices
- Average Invoice Value
- Outstanding Invoices

### Chart 4.2: Revenue Trend
**Tipe Chart:** Line Chart with Area
**Sumber Data:** `so_invoice_line`
- Monthly/Daily revenue (amount_hc)
- Invoice count trend

### Chart 4.3: Top Customers by Revenue
**Tipe Chart:** Horizontal Bar Chart
**Sumber Data:** `so_invoice_line`
- Top 20 customers by total amount_hc

### Chart 4.4: Sales by Product Type
**Tipe Chart:** Donut Chart
**Sumber Data:** `so_invoice_line`
- Revenue distribution per product_type

### Chart 4.5: Shipment Status Tracking
**Tipe Chart:** Funnel Chart
**Sumber Data:** `so_invoice_line`
- Sales Order → Shipment → Receipt → Invoice
- Conversion rate per stage

### Chart 4.6: Delivery Performance
**Tipe Chart:** Gauge Chart
**Sumber Data:** `so_invoice_line`
- On-time delivery rate (delivery_date vs receipt_date)

### Chart 4.7: Invoice Status Distribution
**Tipe Chart:** Stacked Bar Chart
**Sumber Data:** `so_invoice_line`
- Invoice status per customer/periode

### Chart 4.8: Sales Order Fulfillment
**Tipe Chart:** Combo Chart
**Sumber Data:** `so_invoice_line`
- Delivered qty vs Invoiced qty
- Fulfillment percentage

### Chart 4.9: Top Selling Products
**Tipe Chart:** Table with Sparkline
**Sumber Data:** `so_invoice_line`
- Columns: Part No, Description, Total Qty, Total Amount, Trend

### Chart 4.10: Revenue by Currency
**Tipe Chart:** Pie Chart
**Sumber Data:** `so_invoice_line`
- Revenue breakdown per currency

### Chart 4.11: Monthly Sales Comparison
**Tipe Chart:** Clustered Column Chart
**Sumber Data:** `so_invoice_line`
- Current year vs Previous year comparison

---

## 5. Dashboard Procurement & Receipt Analysis

### Chart 5.1: Procurement KPI
**Tipe Chart:** KPI Cards
**Sumber Data:** `data_receipt_purchase`
- Total PO Value
- Total Receipts
- Total Approved Qty
- Pending Receipts
- Average Receipt Time

### Chart 5.2: Receipt Performance
**Tipe Chart:** Gauge Chart
**Sumber Data:** `data_receipt_purchase`
- Receipt fulfillment rate (actual_receipt_qty vs request_qty)
- Approval rate (approve_qty vs actual_receipt_qty)

### Chart 5.3: Top Suppliers by Value
**Tipe Chart:** Horizontal Bar Chart
**Sumber Data:** `data_receipt_purchase`
- Top 20 suppliers by receipt_amount

### Chart 5.4: Receipt Trend
**Tipe Chart:** Line Chart
**Sumber Data:** `data_receipt_purchase`
- Daily/Monthly receipt volume
- Receipt amount trend

### Chart 5.5: Supplier Delivery Performance
**Tipe Chart:** Scatter Plot
**Sumber Data:** `data_receipt_purchase`
- X-axis: Delivery time variance
- Y-axis: Receipt accuracy (approve_qty/actual_receipt_qty)
- Size: Receipt value

### Chart 5.6: Receipt by Item Group
**Tipe Chart:** Treemap
**Sumber Data:** `data_receipt_purchase`
- Item Group → Item Type → Receipt volume

### Chart 5.7: PO vs Invoice Status
**Tipe Chart:** Waterfall Chart
**Sumber Data:** `data_receipt_purchase`
- PO Amount → Receipt Amount → Invoice Amount → Payment Amount

### Chart 5.8: Outstanding PO Analysis
**Tipe Chart:** Table
**Sumber Data:** `data_receipt_purchase`
- Columns: PO No, Supplier, Item, Request Qty, Actual Receipt Qty, Pending Qty, Status

### Chart 5.9: Receipt Approval Rate by Supplier
**Tipe Chart:** Bar Chart
**Sumber Data:** `data_receipt_purchase`
- Approval rate per supplier
- Color coding: Good (>95%), Warning (90-95%), Poor (<90%)

### Chart 5.10: Purchase Price Trend
**Tipe Chart:** Line Chart
**Sumber Data:** `data_receipt_purchase`
- Average receipt_unit_price per item over time

### Chart 5.11: Payment Status Tracking
**Tipe Chart:** Stacked Area Chart
**Sumber Data:** `data_receipt_purchase`
- Invoiced vs Paid amount over time

---

## 6. Dashboard Supply Chain Integration

### Chart 6.1: Supply Chain KPI
**Tipe Chart:** KPI Cards
**Sumber Data:** Multiple tables
- Order to Cash Cycle Time
- Procure to Pay Cycle Time
- Average Lead Time
- Stock Availability Rate

### Chart 6.2: Order to Cash Flow
**Tipe Chart:** Sankey Diagram
**Sumber Data:** `so_invoice_line` + `view_prod_header` + `stockbywh`
- Flow: Stock → Production Order → Shipment → Invoice

### Chart 6.3: Procure to Pay Flow
**Tipe Chart:** Sankey Diagram
**Sumber Data:** `data_receipt_purchase`
- Flow: PO → Receipt → Invoice → Payment

### Chart 6.4: Demand vs Supply Analysis
**Tipe Chart:** Combo Chart (Line + Bar)
**Sumber Data:** `stockbywh` + `view_prod_header` + `so_invoice_line`
- Available Stock (bar)
- Production Plan (bar)
- Sales Demand (line)

### Chart 6.5: Lead Time Analysis
**Tipe Chart:** Box Plot / Violin Chart
**Sumber Data:** Multiple tables
- Procurement Lead Time
- Production Lead Time
- Delivery Lead Time
- By category/supplier/customer

### Chart 6.6: Material Availability for Production
**Tipe Chart:** Heat Map
**Sumber Data:** `stockbywh` + `view_prod_header`
- Rows: Production orders
- Columns: Required materials
- Color: Availability status

### Chart 6.7: Backorder Analysis
**Tipe Chart:** Pareto Chart
**Sumber Data:** `stockbywh` + `so_invoice_line`
- Items with frequent stockout
- Cumulative impact percentage

### Chart 6.8: Supply Chain Cycle Time Trend
**Tipe Chart:** Line Chart
**Sumber Data:** Multiple tables
- Monthly cycle time trend for each process

---

## 7. Dashboard Financial Overview

### Chart 7.1: Financial KPI
**Tipe Chart:** KPI Cards
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- Total Revenue
- Total Cost
- Gross Margin
- Outstanding AR
- Outstanding AP

### Chart 7.2: Revenue vs Cost Trend
**Tipe Chart:** Combo Chart (Area + Line)
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- Monthly revenue (area)
- Monthly cost (area)
- Margin percentage (line)

### Chart 7.3: Revenue by Customer Segment
**Tipe Chart:** Stacked Bar Chart
**Sumber Data:** `so_invoice_line`
- Revenue breakdown per customer
- Time period comparison

### Chart 7.4: Cost Analysis by Category
**Tipe Chart:** Waterfall Chart
**Sumber Data:** `data_receipt_purchase`
- Cost breakdown by item_group or item_type

### Chart 7.5: Margin Analysis by Product
**Tipe Chart:** Scatter Plot
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- X-axis: Sales volume
- Y-axis: Margin percentage
- Size: Total revenue

### Chart 7.6: Outstanding Receivables Aging
**Tipe Chart:** Stacked Column Chart
**Sumber Data:** `so_invoice_line`
- Aging buckets: Current, 1-30 days, 31-60 days, 61-90 days, >90 days
- Per customer

### Chart 7.7: Outstanding Payables Aging
**Tipe Chart:** Stacked Column Chart
**Sumber Data:** `data_receipt_purchase`
- Aging buckets: Current, 1-30 days, 31-60 days, 61-90 days, >90 days
- Per supplier

### Chart 7.8: Cash Flow Projection
**Tipe Chart:** Waterfall Chart
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- Opening balance
- Expected receipts (invoices pending payment)
- Expected payments (supplier invoices)
- Projected balance

### Chart 7.9: Top Profitable Products
**Tipe Chart:** Table with Conditional Formatting
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- Columns: Product, Revenue, Cost, Margin %, Margin Amount

### Chart 7.10: Revenue by Currency Exchange Impact
**Tipe Chart:** Line Chart
**Sumber Data:** `so_invoice_line`
- Revenue in original currency vs home currency (amount vs amount_hc)

---

## 8. Dashboard Executive Summary (KPI Overview)

### Chart 8.1: Overall Business Health
**Tipe Chart:** Scorecard / KPI Grid
**Sumber Data:** Multiple tables
- Revenue Growth %
- Profit Margin %
- Inventory Turnover Ratio
- Order Fulfillment Rate %
- On-Time Delivery %
- Production Achievement %
- Supplier Performance Score

### Chart 8.2: Key Metrics Trend
**Tipe Chart:** Multi-Line Chart
**Sumber Data:** Multiple tables
- Monthly trend for key metrics (Revenue, Orders, Production, Shipments)

### Chart 8.3: Inventory Health Summary
**Tipe Chart:** Bullet Chart
**Sumber Data:** `stockbywh`
- Current stock level vs target range
- Safety stock adherence

### Chart 8.4: Production Performance
**Tipe Chart:** Gauge Chart
**Sumber Data:** `view_prod_header`
- Production completion rate
- On-time production rate

### Chart 8.5: Sales Performance
**Tipe Chart:** Speedometer / Gauge
**Sumber Data:** `so_invoice_line`
- Sales achievement vs target
- YTD vs target

### Chart 8.6: Operational Efficiency
**Tipe Chart:** Radar Chart
**Sumber Data:** Multiple tables
- Dimensions: Production efficiency, Delivery performance, Stock management, Procurement efficiency, Order fulfillment

### Chart 8.7: Financial Summary
**Tipe Chart:** Combo Chart
**Sumber Data:** `so_invoice_line` + `data_receipt_purchase`
- Revenue (bar), Cost (bar), Margin % (line)
- Monthly comparison

### Chart 8.8: Critical Alerts & Actions
**Tipe Chart:** Alert List / Traffic Light Table
**Sumber Data:** Multiple tables
- Critical stock items
- Delayed shipments
- Overdue invoices
- Pending approvals
- Color coded: Red (urgent), Yellow (warning), Green (normal)

### Chart 8.9: Department Performance Comparison
**Tipe Chart:** Grouped Bar Chart
**Sumber Data:** Multiple tables
- Performance metrics per department (Production, Warehouse, Sales, Procurement)
- Target vs Actual

### Chart 8.10: Monthly Business Overview
**Tipe Chart:** Summary Table with Sparklines
**Sumber Data:** Multiple tables
- Rows: Key metrics (Revenue, Orders, Production, Shipments, Costs)
- Columns: Current Month, Last Month, YTD, Trend (sparkline)

---
