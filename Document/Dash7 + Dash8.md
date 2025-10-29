DASHBOARD 7: FINANCIAL OVERVIEW
Tujuan Dashboard
Analisis financial performance, monitoring revenue vs cost, evaluasi profitability, dan tracking outstanding receivables/payables.
Target User
•	Finance Manager
•	CFO
•	Accounting Manager
________________________________________
CHART 7.1: Financial KPI
Tipe Visualisasi: KPI Cards (5-6 cards)
Sumber Data: so_invoice_line + data_receipt_purchase
Metrik:
•	Total Revenue: SUM(amount_hc) from so_invoice_line
•	Total Cost: SUM(receipt_amount) from data_receipt_purchase
•	Gross Margin: (Revenue - Cost) / Revenue * 100
•	Outstanding AR: SUM(amount_hc) WHERE invoice_status <> 'Paid'
•	Outstanding AP: SUM(inv_amount) WHERE payment_doc IS NULL
•	DSO (Days Sales Outstanding): (Outstanding AR / Avg daily sales) days
Period: MTD, QTD, YTD with comparison
________________________________________
CHART 7.2: Revenue vs Cost Trend
Tipe Visualisasi: Combo Chart (Area + Line)
Sumber Data: so_invoice_line + data_receipt_purchase
Dimensi:
•	X-axis: Month/Quarter
•	Y-axis Primary: Amount (currency)
•	Y-axis Secondary: Margin percentage
Series:
•	Revenue (Area - Blue) = SUM(amount_hc) from so_invoice_line
•	Cost (Area - Red) = SUM(receipt_amount) from data_receipt_purchase
•	Gross Margin % (Line - Green) = (Revenue - Cost) / Revenue * 100
Target Line: Target margin %
Filter: Date range, Product type, Customer
________________________________________
CHART 7.3: Revenue by Customer Segment
Tipe Visualisasi: Stacked Bar Chart
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Time Period (Monthly/Quarterly)
•	Y-axis: Revenue (amount_hc)
•	Stack: Top customers (Top 10) + Others
Features:
•	Customer contribution %
•	Growth trend per customer
•	Click to drill-down
Filter: Date range, Product type
________________________________________
CHART 7.4: Cost Analysis by Category
Tipe Visualisasi: Waterfall Chart
Sumber Data: data_receipt_purchase
Structure:
•	Starting: Total Budget/Target
•	Categories: Cost breakdown by item_group atau item_type
•	Ending: Actual total cost
Additional Info:
•	Variance vs budget
•	Cost saving/overspend areas
Color:
•	Green: Under budget
•	Red: Over budget
•	Blue: Total bars
________________________________________
CHART 7.5: Margin Analysis by Product
Tipe Visualisasi: Scatter Plot (Bubble Chart)
Sumber Data: so_invoice_line + data_receipt_purchase (joined by part_no)
Dimensi:
•	X-axis: Sales Volume (SUM(delivered_qty))
•	Y-axis: Gross Margin % = (Sales price - Purchase cost) / Sales price * 100
•	Bubble Size: Total Revenue (amount_hc)
•	Color: Product Type
Quadrant Lines:
•	High Volume/High Margin (Star products)
•	High Volume/Low Margin (Volume drivers)
•	Low Volume/High Margin (Niche products)
•	Low Volume/Low Margin (Question marks)
Tooltip: Product details, Margin amount, Revenue
________________________________________
CHART 7.6: Outstanding Receivables Aging
Tipe Visualisasi: Stacked Column Chart
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Customer (bp_name) - Top 20 by AR
•	Y-axis: Outstanding amount (amount_hc)
Stack (Aging Buckets):
•	Current (Green): 0-30 days from invoice_date
•	1-30 days overdue (Yellow): 31-60 days
•	31-60 days overdue (Orange): 61-90 days
•	61-90 days overdue (Red): 91-120 days
•	Over 90 days (Dark Red): >120 days
Calculation: WHERE invoice_status <> 'Paid'
Sorting: By total outstanding descending
Tooltip: Customer, Amount per bucket, Invoice count
________________________________________
CHART 7.7: Outstanding Payables Aging
Tipe Visualisasi: Stacked Column Chart
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Supplier (bp_name) - Top 20 by AP
•	Y-axis: Outstanding amount (inv_amount)
Stack (Aging Buckets):
•	Not Yet Due (Blue): Due date > today
•	Current (Green): 0-30 days overdue
•	1-30 days overdue (Yellow): 31-60 days overdue
•	31-60 days overdue (Orange): 61-90 days overdue
•	Over 60 days (Red): >90 days overdue
Calculation: WHERE payment_doc IS NULL
Sorting: By total outstanding descending
________________________________________
CHART 7.8: Cash Flow Projection
Tipe Visualisasi: Waterfall Chart
Sumber Data: so_invoice_line + data_receipt_purchase
Structure:
•	Starting: Current Cash Balance (atau Period opening)
•	(+) Expected Receipts: Outstanding AR expected collection
•	(-) Expected Payments: Outstanding AP due for payment
•	(+/-) Net Operating Cash Flow
•	Ending: Projected Cash Balance
Time Buckets: Current, Next 30 days, 31-60 days, 61-90 days
Color:
•	Green: Positive inflow
•	Red: Outflow
•	Blue: Balance bars
________________________________________
CHART 7.9: Top Profitable Products
Tipe Visualisasi: Data Table with Conditional Formatting
Sumber Data: so_invoice_line + data_receipt_purchase
Kolom:
•	Rank
•	Product (part_no, description)
•	Revenue (SUM(amount_hc) from sales)
•	Cost (SUM(receipt_amount) from purchase)
•	Gross Margin (Revenue - Cost)
•	Margin % ((Revenue - Cost) / Revenue * 100)
•	Sales Volume (SUM(delivered_qty))
•	Contribution to Total Profit %
Sorting: By Margin Amount descending
Conditional Formatting:
•	Green: Margin % > 30%
•	Yellow: Margin % 15-30%
•	Red: Margin % < 15%
Limit: Top 50 products
Filter: Product type, Customer, Date range
________________________________________
CHART 7.10: Revenue by Currency Exchange Impact
Tipe Visualisasi: Combo Chart (Bar + Line)
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Month/Quarter
•	Y-axis Primary: Revenue amount
•	Y-axis Secondary: Exchange rate variance %
Series:
•	Revenue in Original Currency (Bar - Blue) = SUM(amount)
•	Revenue in Home Currency (Bar - Green) = SUM(amount_hc)
•	Exchange Variance % (Line - Orange) = ((amount_hc - amount) / amount) * 100
Breakdown: By currency type
Purpose: Analyze forex impact on revenue
________________________________________
 
DASHBOARD 8: EXECUTIVE SUMMARY (KPI OVERVIEW)
Tujuan Dashboard
High-level overview seluruh business performance untuk executive decision making, mencakup semua key metrics dalam single view.
Target User
•	C-Level Executives (CEO, COO, CFO)
•	Board of Directors
•	General Manager
________________________________________
CHART 8.1: Overall Business Health
Tipe Visualisasi: Scorecard / KPI Grid (3x3 atau 4x3)
Sumber Data: Multiple tables
Metrik dengan Target & Status:
Financial:
•	Revenue Growth %: (Current vs Previous period)
•	Profit Margin %: (Revenue - Cost) / Revenue
•	ROI %: Net profit / Investment
Operations:
•	Inventory Turnover Ratio: Cost of goods sold / Avg inventory
•	Order Fulfillment Rate %: Completed orders / Total orders
•	On-Time Delivery %: On-time deliveries / Total deliveries
Production:
•	Production Achievement %: Actual output / Planned output
•	OEE %: Availability × Performance × Quality
•	Production Cycle Time: Avg time from start to finish
Supply Chain:
•	Supplier Performance Score: Composite score (delivery, quality, price)
•	Stock Availability %: Items in stock / Total items
•	Backorder Rate %: Backorders / Total orders
Status Indicator:
•	Green: Above target
•	Yellow: Near target (90-100%)
•	Red: Below target (<90%)
________________________________________
CHART 8.2: Key Metrics Trend
Tipe Visualisasi: Multi-Line Chart
Sumber Data: Multiple tables
Dimensi:
•	X-axis: Month (Last 12 months)
•	Y-axis: Indexed value (Base 100 = 12 months ago)
Series (All normalized to index):
•	Revenue (Blue - Bold)
•	Orders (Green)
•	Production Volume (Orange)
•	Shipments (Purple)
Features:
•	Trend lines
•	Period markers (Quarter boundaries)
•	YoY comparison option
Purpose: Quick visual of business momentum
________________________________________
CHART 8.3: Inventory Health Summary
Tipe Visualisasi: Bullet Chart (Multiple)
Sumber Data: stockbywh
Metrics:
Bullet 1: Stock Coverage Days
•	Actual: Current days of inventory
•	Target: Optimal days (e.g., 45 days)
•	Good range: 30-60 days
•	Warning range: 15-30 or 60-90 days
•	Critical: <15 or >90 days
Bullet 2: Stock Value Efficiency
•	Actual: Current inventory value
•	Target: Target inventory value
•	Ranges: Based on business strategy
Bullet 3: Stockout Rate
•	Actual: % items below safety stock
•	Target: <5%
•	Ranges: Good <5%, Warning 5-10%, Critical >10%
Color: Green (good), Yellow (warning), Red (critical)
________________________________________
CHART 8.4: Production Performance
Tipe Visualisasi: Gauge Chart (Speedometer)
Sumber Data: view_prod_header
Metrics:
Gauge 1: Production Completion Rate
•	Calculation: (SUM(qty_delivery) / SUM(qty_order)) * 100
•	Range: 0-100%
•	Zones: Red (0-80%), Yellow (80-95%), Green (95-100%)
•	Target: 98%
Gauge 2: On-Time Production Rate
•	Calculation: (On-time completions / Total completions) * 100
•	Target: 95%
Display: Side by side gauges with current value and trend arrow
________________________________________
CHART 8.5: Sales Performance
Tipe Visualisasi: Speedometer Gauge
Sumber Data: so_invoice_line
Metric:
•	Sales Achievement vs Target: (Actual revenue / Target revenue) * 100
•	Range: 0-150%
•	Zones: 
o	Red: 0-70%
o	Yellow: 70-90%
o	Light Green: 90-100%
o	Dark Green: 100-120%
o	Blue: >120%
Supporting Info:
•	YTD Achievement
•	Remaining target
•	Days to period end
•	Required daily run-rate
________________________________________
CHART 8.6: Operational Efficiency
Tipe Visualisasi: Radar Chart (Spider Chart)
Sumber Data: Multiple tables
Dimensions (5-6 axes):
•	Production Efficiency: Production achievement rate
•	Delivery Performance: On-time delivery rate
•	Stock Management: Inventory turnover ratio (normalized)
•	Procurement Efficiency: Supplier on-time delivery rate
•	Order Fulfillment: Order fulfillment rate
•	Quality: Approval rate / First-pass yield
Display:
•	Actual Performance (Solid line - Blue)
•	Target/Benchmark (Dashed line - Red)
•	Shaded area between
Scale: 0-100% for each dimension
Purpose: Identify strengths and improvement areas
________________________________________
CHART 8.7: Financial Summary
Tipe Visualisasi: Combo Chart (Column + Line)
Sumber Data: so_invoice_line + data_receipt_purchase
Dimensi:
•	X-axis: Month (Last 12 months)
•	Y-axis Primary: Amount (currency)
•	Y-axis Secondary: Percentage
Series:
•	Revenue (Column - Blue) = SUM(amount_hc) from sales
•	Cost (Column - Red) = SUM(receipt_amount) from purchase
•	Gross Margin % (Line - Green) = (Revenue - Cost) / Revenue * 100
Additional Elements:
•	Target margin line
•	Budget vs actual variance
•	Trend projection (dotted line for next 3 months)
Filter: Currency, Include/Exclude items
________________________________________
CHART 8.8: Critical Alerts & Actions
Tipe Visualisasi: Alert List / Traffic Light Table
Sumber Data: Multiple tables
Struktur:
Table Columns:
•	Priority (Icon: Red circle, Yellow triangle, Blue info)
•	Category (Inventory, Production, Sales, Finance, Procurement)
•	Alert Description
•	Metric Value
•	Threshold
•	Status (Critical/Warning/Info)
•	Action Required
•	Owner/Department
•	Due Date
Alert Types:
1.	Inventory Alerts:
o	Critical stock items (onhand < min_stock)
o	Overstock items (onhand > max_stock)
o	Slow-moving items
2.	Production Alerts:
o	Delayed production orders
o	Material shortage for production
o	Low production achievement
3.	Sales Alerts:
o	Overdue deliveries
o	Pending invoices >60 days
o	Low sales performance vs target
4.	Finance Alerts:
o	High AR aging (>90 days)
o	High AP overdue (>30 days)
o	Low margin products
5.	Procurement Alerts:
o	Delayed PO receipts
o	Supplier quality issues (low approval rate)
o	Pending confirmations
Sorting: By priority (Critical → Warning → Info)
Limit: Top 20 most critical
Refresh: Real-time or hourly
________________________________________
CHART 8.9: Department Performance Comparison
Tipe Visualisasi: Grouped Bar Chart
Sumber Data: Multiple tables
Dimensi:
•	X-axis: Department (Production, Warehouse, Sales, Procurement, Finance)
•	Y-axis: Performance Score (0-100%)
Metrics per Department:
•	Production: Production achievement rate
•	Warehouse: Order fulfillment rate
•	Sales: Sales target achievement
•	Procurement: Supplier performance score
•	Finance: Collection efficiency (AR turnover)
Bars per Department:
•	Target (Gray - dashed outline)
•	Actual (Color-coded by department)
Color Coding:
•	Green: Actual >= Target
•	Yellow: Actual 90-99% of target
•	Red: Actual < 90% of target
Tooltip: Department, Actual %, Target %, Variance
________________________________________
CHART 8.10: Monthly Business Overview
Tipe Visualisasi: Summary Table with Sparklines
Sumber Data: Multiple tables
Table Structure:
Rows (Key Metrics):
1.	Revenue (Total sales amount)
2.	Orders (Total order count)
3.	Production Output (Total qty produced)
4.	Shipments (Total deliveries)
5.	Procurement (Total PO value)
6.	Operating Cost (Total expenses)
7.	Gross Margin (Revenue - Cost)
8.	Cash Flow (AR - AP)
Columns:
•	Metric Name
•	Current Month (Value)
•	Last Month (Value)
•	Variance (Value & %)
•	YTD (Value)
•	YTD vs Target (%)
•	Trend (Sparkline - Last 12 months)
Conditional Formatting:
•	Green: Positive variance or above target
•	Red: Negative variance or below target
•	Gray: Neutral or N/A
Sparkline: Mini line chart showing trend
Purpose: Quick monthly review for executives
