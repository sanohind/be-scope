# Warehouse Inventory Dashboard - Development Guide

## Overview
Dashboard komprehensif untuk monitoring dan analisis inventory warehouse yang menggabungkan data statis (stockbywh) dan data transaksi (inventory_transaction).

**Target:** Single warehouse view (WHMT01, WHRM01, WHRM02, WHFG01, WHFG02)  
**Total Charts:** 12 charts utama  
**Data Sources:** 2 tables (stockbywh + inventory_transaction)

---

## Dashboard Structure

### Section 1: Key Performance Indicators

#### **CHART 1: Comprehensive KPI Cards**
- **Type:** KPI Cards (6 metrics)
- **Layout:** Horizontal row
- **Metrics:**
  1. Total SKU
  2. Current Onhand Quantity
  3. Critical Items Count
  4. Today's Transaction Count
  5. Net Movement (30 days)
- **Refresh:** Real-time / Hourly
- **Color Coding:** Red (critical), Green (normal)

---

### Section 2: Stock Health & Movement

#### **CHART 2: Stock Health Distribution + Activity**
- **Type:** Donut Chart
- **Inner Ring:** Stock status distribution
  - Critical (< min_stock) - Red
  - Low Stock (< safety_stock) - Orange
  - Normal (in range) - Green
  - Overstock (> max_stock) - Blue
- **Metric:** Count of items per status
- **Tooltip:** Show transaction count (30d) per category
- **Interactivity:** Click to filter other charts

#### **CHART 3: Stock Movement Trend**
- **Type:** Area Chart (Stacked)
- **Time Period:** Last 30 days
- **X-axis:** Date (daily)
- **Y-axis:** Quantity
- **Series:**
  - Receipt (Green area)
  - Shipment (Red area)
  - Current Onhand Level (Blue reference line)
- **Features:** Date range selector

---

### Section 3: Critical Items & Active Items

#### **CHART 4: Top 15 Critical Items with Transaction History**
- **Type:** Data Table with Conditional Formatting
- **Columns:**
  - Part No
  - Description
  - Current Onhand (color coded)
  - Safety Stock
  - Gap (safety_stock - onhand)
  - Last 7 Days Activity (sparkline)
  - Last Transaction Date
  - Location
- **Sorting:** Gap descending
- **Conditional Formatting:**
  - Red row: Critical (onhand < min_stock)
  - Orange row: Low stock (onhand < safety_stock)
- **Filter:** Show only items below safety stock

#### **CHART 5: Top 15 Most Active Items**
- **Type:** Horizontal Bar Chart
- **X-axis:** Transaction count (30 days)
- **Y-axis:** Part No + Description
- **Color Coding:** Based on stock status
  - Red: At Risk (high activity + low stock)
  - Orange: Normal high activity
  - Blue: Overstock
- **Tooltip:**
  - Current onhand
  - Safety stock
  - Total shipment
  - Activity level

---

### Section 4: Product & Customer Analysis

#### **CHART 6: Stock & Activity by Product Type**
- **Type:** Combo Chart (Clustered Column + Line)
- **X-axis:** Product Type
- **Y-axis Primary (Columns):**
  - Current Onhand (Blue)
  - Safety Stock Total (Orange)
- **Y-axis Secondary (Lines):**
  - Transaction Count 30d (Green)
  - Turnover Rate (Red)
- **Sorting:** By total onhand descending

#### **CHART 7: Stock by Customer with Activity Indicator**
- **Type:** Treemap
- **Hierarchy:** Customer → Product Type → Model
- **Size:** Onhand quantity
- **Color:** Transaction activity level
  - Dark Green: High activity
  - Light Green: Medium activity
  - Yellow: Low activity
  - Gray: No activity
- **Tooltip:**
  - Current onhand
  - Transaction count (30d)
  - Last shipment date

---

### Section 5: Transaction Details

#### **CHART 8: Receipt vs Shipment Trend**
- **Type:** Clustered Column Chart with Line
- **Time Period:** Last 12 weeks
- **X-axis:** Week number
- **Y-axis Primary (Columns):**
  - Receipt (Green)
  - Shipment (Red)
- **Y-axis Secondary (Line):**
  - Net Movement (Blue)
- **Features:** Week/Month toggle

#### **CHART 9: Transaction Type Distribution**
- **Type:** Stacked Bar Chart (Horizontal)
- **Y-axis:** Transaction Type
- **X-axis:** Quantity or Count
- **Stack:** Order Type
- **Color:** Different per order type
- **Tooltip:** Show unique parts and users involved

---

### Section 6: Performance Analysis

#### **CHART 10: Fast vs Slow Moving Items**
- **Type:** Scatter Plot with Quadrants
- **X-axis:** Transaction frequency (30 days)
- **Y-axis:** Current onhand quantity
- **Bubble Size:** Gap from safety stock
- **Color:** Stock status
- **Quadrants:**
  - Q1 (High Trans, High Stock): Healthy
  - Q2 (Low Trans, High Stock): Overstock/Slow
  - Q3 (Low Trans, Low Stock): Review needed
  - Q4 (High Trans, Low Stock): High Risk
- **Reference Lines:** 
  - Vertical: 10 transactions threshold
  - Horizontal: Safety stock level

#### **CHART 11: Stock Turnover Rate (Top 20)**
- **Type:** Horizontal Bar Chart with Color Gradient
- **X-axis:** Turnover rate
- **Y-axis:** Part No + Description (truncated)
- **Color Gradient:**
  - Green: Fast moving (turnover > 1.5)
  - Yellow: Medium (0.5 - 1.5)
  - Red: Slow moving (< 0.5)
- **Additional Metric:** Days of stock (displayed as label)
- **Sorting:** Turnover rate descending

---

### Section 7: Recent Activity

#### **CHART 12: Recent Transaction History**
- **Type:** Data Table with Pagination
- **Columns:**
  - Trans Date & Time
  - Part No
  - Description
  - Trans Type
  - Order Type / Order No
  - Receipt (green highlight)
  - Shipment (red highlight)
  - Qty After Transaction
  - Current Onhand
  - Variance (if any)
  - User
  - Location
- **Features:**
  - Pagination (50 records per page)
  - Export to Excel
  - Clickable Part No (drill-down)
  - Date/Time filter
  - Trans type filter
  - User filter
- **Default Sort:** Trans date descending

---

## Dashboard Layout

```
┌─────────────────────────────────────────────────────┐
│  [WAREHOUSE NAME] - INVENTORY CONTROL DASHBOARD     │
│  [Filters: Date Range | Product Type | Customer]    │
├─────────────────────────────────────────────────────┤
│  CHART 1: KPI Cards (6 metrics - full width)        │
├──────────────────────────┬──────────────────────────┤
│  CHART 2: Stock Health   │  CHART 3: Movement Trend │
│  (40% width)             │  (60% width)             │
├──────────────────────────┴──────────────────────────┤
│  CHART 4: Critical Items Table (full width)         │
├──────────────────────────┬──────────────────────────┤
│  CHART 5: Most Active    │  CHART 6: Product Type   │
│  Items (50% width)       │  Analysis (50% width)    │
├──────────────────────────┴──────────────────────────┤
│  CHART 7: Customer Treemap (full width)             │
├──────────────────────────┬──────────────────────────┤
│  CHART 8: Receipt vs     │  CHART 9: Transaction    │
│  Shipment (50% width)    │  Type (50% width)        │
├──────────────────────────┴──────────────────────────┤
│  CHART 10: Fast vs Slow Moving (full width)         │
├──────────────────────────┬──────────────────────────┤
│  CHART 11: Turnover      │  CHART 12: Recent Trans  │
│  Rate (40% width)        │  History (60% width)     │
└──────────────────────────┴──────────────────────────┘
```

---

## Data Requirements

### From `stockbywh` table:
- warehouse, partno, desc, product_type, model, customer
- onhand, allocated, safety_stock, min_stock, max_stock
- location, group

### From `inventory_transaction` table:
- warehouse, partno, part_desc, trans_date
- trans_id, trans_type, order_type, order_no
- qty, receipt, shipment, qty_hand
- user, lotno

### Join Key:
```
stockbywh.partno = inventory_transaction.partno
AND stockbywh.warehouse = inventory_transaction.warehouse
```

---

## Filters & Interactivity

### Global Filters (Apply to all charts):
1. **Date Range** (for transaction data)
   - Last 7 days
   - Last 30 days (default)
   - Last 90 days
   - Custom range

2. **Product Type** (multi-select)
3. **Customer** (multi-select)
4. **Stock Status** (Critical/Low/Normal/Overstock)

### Chart-Specific Interactions:
- **Click on chart elements** → Filter other charts
- **Hover tooltips** → Show detailed metrics
- **Drill-down capability** → Part No details
- **Export options** → Excel, PDF, CSV

---

## Performance Considerations

### Optimization Tips:
1. **Use indexed columns** for warehouse, partno, trans_date
2. **Pre-aggregate** transaction data (daily summary tables)
3. **Limit date range** for transaction queries (default 30 days)
4. **Implement pagination** for large tables
5. **Cache KPI metrics** (refresh every 15-60 minutes)
6. **Use TOP N** for ranking queries (limit to 15-20 items)

### Refresh Strategy:
- **KPI Cards:** Every 15 minutes
- **Charts 2-7:** Every 30 minutes
- **Charts 8-11:** Every 1 hour
- **Chart 12 (Recent Trans):** Real-time or every 5 minutes

---

## Color Palette

### Status Colors:
- **Critical:** #DC3545 (Red)
- **Low Stock:** #FD7E14 (Orange)
- **Normal:** #28A745 (Green)
- **Overstock:** #007BFF (Blue)

### Transaction Colors:
- **Receipt:** #28A745 (Green)
- **Shipment:** #DC3545 (Red)
- **Net Movement:** #007BFF (Blue)

### Activity Colors:
- **High Activity:** #198754 (Dark Green)
- **Medium Activity:** #FFC107 (Yellow)
- **Low Activity:** #FD7E14 (Orange)
- **No Activity:** #6C757D (Gray)

---

## Implementation Priority

### Phase 1 (MVP - Must Have):
1. Chart 1 - KPI Cards
2. Chart 2 - Stock Health
3. Chart 4 - Critical Items
4. Chart 12 - Recent Transactions

### Phase 2 (Enhanced):
5. Chart 3 - Movement Trend
6. Chart 5 - Most Active Items
7. Chart 8 - Receipt vs Shipment

### Phase 3 (Advanced Analytics):
8. Chart 6 - Product Type Analysis
9. Chart 10 - Fast vs Slow Moving
10. Chart 11 - Turnover Rate

### Phase 4 (Nice to Have):
11. Chart 7 - Customer Treemap
12. Chart 9 - Transaction Type

---

## Testing Checklist

### Data Validation:
- [ ] KPI metrics match source data
- [ ] Transaction counts are accurate
- [ ] Stock calculations are correct (onhand, allocated, available)
- [ ] Date ranges filter correctly
- [ ] No duplicate records in tables

### Visual Validation:
- [ ] Colors match status correctly
- [ ] Charts resize properly (responsive)
- [ ] Tooltips display complete information
- [ ] Legends are clear and positioned well
- [ ] Text is readable (no truncation issues)

### Performance Testing:
- [ ] Dashboard loads within 5 seconds
- [ ] Filters apply without lag
- [ ] Export functions work correctly
- [ ] No memory leaks on long sessions
- [ ] Mobile view is functional

### User Acceptance:
- [ ] Warehouse managers can identify critical items quickly
- [ ] Transaction history is easy to track
- [ ] Charts answer key business questions
- [ ] Export data matches displayed data
- [ ] Drill-down provides useful details

---
