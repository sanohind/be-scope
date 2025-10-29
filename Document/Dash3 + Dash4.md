DASHBOARD 3: PRODUCTION PLANNING & MONITORING
Tujuan Dashboard
Monitoring production order, tracking production achievement, analisis outstanding order, dan evaluasi production capacity utilization.
Target User
•	Production Manager
•	PPIC Manager
•	Operations Director
________________________________________
CHART 3.1: Production KPI Summary
Tipe Visualisasi: KPI Cards (5-6 cards)
Sumber Data: view_prod_header
Metrik:
•	Total Production Orders: COUNT(DISTINCT prod_no) -> filtered by prod_index
•	Total Qty Ordered: SUM(qty_order)
•	Total Qty Delivered: SUM(qty_delivery)
•	Total Outstanding Qty: SUM(qty_os)
•	Completion Rate: (SUM(qty_delivery) / SUM(qty_order)) * 100
________________________________________
CHART 3.2: Production Status Distribution
Tipe Visualisasi: Pie Chart atau Donut Chart
Sumber Data: view_prod_header
Breakdown:
•	Slice: Status field (sts atau status)
•	Value: COUNT(prod_no) atau SUM(qty_order)
•	Center Label: Total production orders
Color Coding: Status-based color scheme
________________________________________
CHART 3.3: Production by Customer
Tipe Visualisasi: Clustered Bar Chart
Sumber Data: view_prod_header
Dimensi:
•	X-axis: Customer (Top 15-20)
•	Y-axis: Quantity
Series:
•	Qty Ordered (Blue)
•	Qty Delivered (Green)
•	Qty Outstanding (Red)
Sorting: By total qty ordered descending
________________________________________
CHART 3.4: Production by Model
Tipe Visualisasi: Horizontal Bar Chart
Sumber Data: view_prod_header
Dimensi:
•	X-axis: Production volume (qty_order)
•	Y-axis: Model (Top 20)
Color: Gradient atau category-based
Tooltip: Model, Customer, Total orders, Total qty
________________________________________
CHART 3.5: Production Schedule Timeline
Tipe Visualisasi: Gantt Chart
Sumber Data: view_prod_header
Struktur:
•	Rows: Production order (prod_no)
•	Timeline: Planning date → Estimated completion
•	Status bar: Color by status
Features:
•	Today marker line
•	Delayed orders highlight (Red)
•	Completed orders (Green)
Filter: Date range, Status, Customer, Division
Limit: Active orders + recent 30 days
________________________________________
CHART 3.6: Production Outstanding Analysis
Tipe Visualisasi: Data Table with Progress Bar
Sumber Data: view_prod_header
Kolom:
•	Prod No (prod_no)
•	Planning Date (planning_date)
•	Item/Description (item, description)
•	Customer
•	Qty Order (qty_order)
•	Qty Delivery (qty_delivery)
•	Qty Outstanding (qty_os)
•	% Complete (Progress bar) = (qty_delivery/qty_order)*100
•	Status (sts/status)
•	Division (divisi)
Filter: Status, Customer, Date range
Sorting: By % Complete ascending atau qty_os descending
Conditional Formatting:
•	Red: % Complete < 50%
•	Yellow: % Complete 50-90%
•	Green: % Complete > 90%
________________________________________
CHART 3.7: Production by Division
Tipe Visualisasi: Stacked Bar Chart
Sumber Data: view_prod_header
Dimensi:
•	X-axis: Division (divisi)
•	Y-axis: Production volume
Stack: Status (sts/status)
Color: Status-based
Additional Metrics:
•	Total orders per division
•	Average completion rate
________________________________________
CHART 3.8: Production Trend
Tipe Visualisasi: Combo Chart (Column + Line)
Sumber Data: view_prod_header
Dimensi:
•	X-axis: Planning Period (monthly/weekly)
•	Y-axis Primary: Quantity
•	Y-axis Secondary: Percentage
Series:
•	Qty Ordered (Column - Blue)
•	Qty Delivered (Column - Green)
•	Achievement Rate (Line - Orange) = (qty_delivery/qty_order)*100
Filter: Date range, Customer, Division
________________________________________
CHART 3.9: Production by Warehouse
Tipe Visualisasi: Treemap
Sumber Data: view_prod_header
Hierarchy:
•	Level 1: Warehouse
•	Level 2: Division
•	Level 3: Customer
Size: Production volume (qty_order)
Color: Completion rate atau status
Tooltip: Detailed metrics per segment
________________________________________
 
DASHBOARD 4: SALES & SHIPMENT ANALYSIS
Tujuan Dashboard
Analisis performa penjualan, monitoring shipment, tracking invoice, evaluasi customer performance, dan product profitability analysis.
Target User
•	Sales Manager
•	Commercial Manager
•	Business Development Manager
________________________________________
CHART 4.1: Sales Overview KPI
Tipe Visualisasi: KPI Cards (5-6 cards)
Sumber Data: so_invoice_line
Metrik:
•	Total Sales Amount: SUM(amount_hc)
•	Total Shipments: COUNT(DISTINCT shipment)
•	Total Invoices: COUNT(DISTINCT invoice_no)
•	Outstanding Invoices: COUNT(DISTINCT invoice_no) WHERE invoice_status = 'Outstanding'
•	Sales Growth: ((Current Period - Previous Period) / Previous Period) * 100
Period Comparison: MTD, QTD, YTD
________________________________________
CHART 4.2: Revenue Trend
Tipe Visualisasi: Area Chart with Line
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Invoice Date (monthly/weekly/daily)
•	Y-axis Primary: Revenue (amount_hc)
•	Y-axis Secondary: Count
Series:
•	Revenue (Area - Blue)
•	Invoice Count (Line - Orange)
Features:
•	Period comparison (YoY, MoM)
•	Trend line
•	Moving average
Filter: Date range, Customer, Product type
________________________________________
CHART 4.3: Top Customers by Revenue
Tipe Visualisasi: Horizontal Bar Chart
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Total Revenue (amount_hc)
•	Y-axis: Customer (bp_name) - Top 20
Additional Info:
•	Revenue contribution %
•	Number of orders
•	Average order value
Color: Gradient by value
Tooltip: Customer details, Total qty, Avg price
________________________________________
CHART 4.4: Sales by Product Type
Tipe Visualisasi: Donut Chart
Sumber Data: so_invoice_line
Breakdown:
•	Slice: Product Type (product_type)
•	Value: SUM(amount_hc)
•	Center: Total revenue
Interactivity: Click to filter other charts
Tooltip: Product type, Revenue, %, Qty sold
________________________________________
CHART 4.5: Shipment Status Tracking
Tipe Visualisasi: Funnel Chart
Sumber Data: so_invoice_line
Stages:
1.	Sales Orders Created: COUNT(DISTINCT sales_order)
2.	Shipments Generated: COUNT(DISTINCT shipment)
3.	Receipts Confirmed: COUNT(DISTINCT receipt)
4.	Invoices Issued: COUNT(DISTINCT invoice_no)
5.	Invoices Paid: COUNT(DISTINCT invoice_no) WHERE invoice_status = 'Paid'
Conversion Rates: Between each stage
Color: Stage-based gradient
________________________________________
CHART 4.6: Delivery Performance
Tipe Visualisasi: Gauge Chart
Sumber Data: so_invoice_line
Metrik:
•	On-Time Delivery Rate: (COUNT() WHERE receipt_date <= delivery_date) / COUNT() * 100
Breakdown:
•	Early Delivery %
•	On-Time Delivery %
•	Late Delivery %
Target: 95% on-time delivery
Color Coding:
•	Green: >95%
•	Yellow: 85-95%
•	Red: <85%
________________________________________
CHART 4.7: Invoice Status Distribution
Tipe Visualisasi: Stacked Bar Chart (100%)
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Time Period (monthly) atau Customer
•	Y-axis: Percentage
•	Stack: Invoice Status (invoice_status)
Status Categories:
•	Paid (Green)
•	Outstanding (Yellow)
•	Overdue (Red)
•	Cancelled (Gray)
________________________________________
CHART 4.8: Sales Order Fulfillment
Tipe Visualisasi: Combo Chart (Column + Line)
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Period atau Product Type
•	Y-axis Primary: Quantity
•	Y-axis Secondary: Fulfillment %
Series:
•	Delivered Qty (Column - Blue)
•	Invoiced Qty (Column - Green)
•	Fulfillment Rate (Line - Orange) = (invoice_qty/delivered_qty)*100
________________________________________
CHART 4.9: Top Selling Products
Tipe Visualisasi: Data Table with Sparkline
Sumber Data: so_invoice_line
Kolom:
•	Rank
•	Part No (part_no)
•	Description
•	Total Qty Sold (SUM(delivered_qty))
•	Total Amount (SUM(amount_hc))
•	Number of Orders
•	Avg Price (AVG(price_hc))
•	Trend (Sparkline - last 12 months)
Sorting: By total amount descending
Filter: Product type, Customer, Date range
Limit: Top 50
________________________________________
CHART 4.10: Revenue by Currency
Tipe Visualisasi: Pie Chart
Sumber Data: so_invoice_line
Breakdown:
•	Slice: Currency
•	Value: SUM(amount) - in original currency
Supporting Info:
•	Revenue in home currency (amount_hc)
•	Exchange rate impact analysis
________________________________________
CHART 4.11: Monthly Sales Comparison
Tipe Visualisasi: Clustered Column Chart
Sumber Data: so_invoice_line
Dimensi:
•	X-axis: Month
•	Y-axis: Revenue (amount_hc)
Series:
•	Current Year (Blue)
•	Previous Year (Gray)
Additional Line: YoY Growth % (Line chart overlay)
Filter: Product type, Customer
