DASHBOARD 5: PROCUREMENT & RECEIPT ANALYSIS
Tujuan Dashboard
Monitoring purchase order receipt, evaluasi supplier performance, analisis procurement efficiency, dan tracking payment status.
Target User
•	Procurement Manager
•	Purchase Manager
•	Finance Manager (AP)
________________________________________
CHART 5.1: Procurement KPI
Tipe Visualisasi: KPI Cards (5-6 cards)
Sumber Data: data_receipt_purchase
Metrik:
•	Total PO Value: SUM(receipt_amount)
•	Total Receipts: COUNT(DISTINCT receipt_no)
•	Total Approved Qty: SUM(approve_qty)
•	Pending Receipts: COUNT dari table dn_header yang status_desc = Under Review. 
•	Average Receipt Time: AVG(DATEDIFF(actual_receipt_date, po_date))
•	Receipt Accuracy Rate: (SUM(approve_qty) / SUM(actual_receipt_qty)) * 100
________________________________________
CHART 5.2: Receipt Performance
Tipe Visualisasi: Gauge Chart (Multiple)
Sumber Data: data_receipt_purchase
Metrik:
Gauge 1: Receipt Fulfillment Rate
•	Calculation: (SUM(actual_receipt_qty) / SUM(request_qty)) * 100
•	Target: 98%
Gauge 2: Approval Rate
•	Calculation: (SUM(approve_qty) / SUM(actual_receipt_qty)) * 100
•	Target: 95%
Color Coding:
•	Green: Above target
•	Yellow: 90-target%
•	Red: Below 90%
________________________________________
CHART 5.3: Top Suppliers by Value
Tipe Visualisasi: Horizontal Bar Chart
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Total Receipt Amount (receipt_amount)
•	Y-axis: Supplier (bp_name) - Top 20
Additional Metrics:
•	Number of POs
•	Average PO value
•	Receipt count
Color: Gradient by value
Sorting: Descending by receipt amount
________________________________________
CHART 5.4: Receipt Trend
Tipe Visualisasi: Line Chart with Area
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Receipt Date (actual_receipt_date) - daily/weekly/monthly
•	Y-axis Primary: Receipt volume (qty atau amount)
•	Y-axis Secondary: Receipt count
Series:
•	Receipt Amount (Area - Blue)
•	Receipt Count (Line - Orange)
Filter: Supplier, Item group, Date range
________________________________________
CHART 5.5: Supplier Delivery Performance
Tipe Visualisasi: Scatter Plot (Bubble Chart)
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Delivery Time Variance (days early/late)
•	Y-axis: Receipt Accuracy Rate (approve_qty/actual_receipt_qty)
•	Bubble Size: Total receipt value (receipt_amount)
•	Color: Supplier category atau performance tier
Quadrant Lines:
•	X=0 (on-time line)
•	Y=95% (target accuracy)
Tooltip: Supplier name, Metrics details
________________________________________
CHART 5.6: Receipt by Item Group
Tipe Visualisasi: Treemap
Sumber Data: data_receipt_purchase
Hierarchy:
•	Level 1: Item Group (item_group)
•	Level 2: Item Type (item_type)
•	Level 3: Item (item_no)
Size: Receipt value (receipt_amount)
Color: Receipt volume atau supplier count
Tooltip: Item details, Total receipts, Suppliers
________________________________________
CHART 5.7: PO vs Invoice Status
Tipe Visualisasi: Waterfall Chart
Sumber Data: data_receipt_purchase
Flow:
1.	Total PO Amount (Starting)
2.	(-) Not Yet Received
3.	(+) Received Amount
4.	(-) Not Yet Invoiced
5.	(+) Invoiced Amount
6.	(-) Not Yet Paid
7.	Final: Paid Amount
Color:
•	Green: Positive flow (received, invoiced, paid)
•	Red: Negative flow (pending)
•	Blue: Total bars
________________________________________
CHART 5.8: Outstanding PO Analysis
Tipe Visualisasi: Data Table
Sumber Data: data_receipt_purchase
Kolom:
•	PO No (po_no)
•	Supplier (bp_name)
•	Item (part_no, item_desc)
•	Request Qty (request_qty)
•	Actual Receipt Qty (actual_receipt_qty)
•	Pending Qty (request_qty - actual_receipt_qty)
•	Receipt Date (actual_receipt_date)
•	Days Outstanding (DATEDIFF(today, expected_date))
•	Status (is_final_receipt)
Filter: Status = Not final OR Pending qty > 0
Sorting: By Days Outstanding descending
Conditional Formatting:
•	Red: Days Outstanding > 30
•	Yellow: Days Outstanding 15-30
•	Green: Days Outstanding < 15
________________________________________
CHART 5.9: Receipt Approval Rate by Supplier
Tipe Visualisasi: Bar Chart with Target Line
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Supplier (bp_name) - Top 20 by volume
•	Y-axis: Approval Rate %
Calculation: (SUM(approve_qty) / SUM(actual_receipt_qty)) * 100
Target Line: 95% approval rate
Color Coding:
•	Green: >95% (Good)
•	Yellow: 90-95% (Warning)
•	Red: <90% (Poor)
Tooltip: Supplier, Approval rate, Total receipts, Rejected qty
________________________________________
CHART 5.10: Purchase Price Trend
Tipe Visualisasi: Line Chart (Multiple Lines)
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Receipt Date (actual_receipt_date)
•	Y-axis: Average Unit Price (receipt_unit_price)
Series: Top 10-15 items (by volume atau value)
Features:
•	Trend line per item
•	Price variance indicator
•	Moving average
Filter: Item, Supplier, Date range
Analysis: Price increase/decrease tracking
________________________________________
CHART 5.11: Payment Status Tracking
Tipe Visualisasi: Stacked Area Chart
Sumber Data: data_receipt_purchase
Dimensi:
•	X-axis: Date (invoice_doc_date atau payment_doc_date)
•	Y-axis: Amount (inv_amount)
Series (Stacked):
•	Invoiced Not Yet Paid (payment_doc IS NULL)
•	Paid (payment_doc IS NOT NULL)
Additional Line: Payment due date violations
Filter: Supplier, Date range
________________________________________
 
DASHBOARD 6: SUPPLY CHAIN INTEGRATION
Tujuan Dashboard
Analisis end-to-end supply chain process, monitoring cycle time, evaluasi supply vs demand, dan identifikasi bottleneck dalam supply chain.
Target User
•	Supply Chain Manager
•	Operations Director
•	Planning Manager
________________________________________
CHART 6.1: Supply Chain KPI
Tipe Visualisasi: KPI Cards (4-5 cards)
Sumber Data: Multiple tables (so_invoice_line, view_prod_header, data_receipt_purchase, stockbywh)
Metrik:
•	Order to Cash Cycle Time: AVG(DATEDIFF(invoice_date, so_date))
•	Procure to Pay Cycle Time: AVG(DATEDIFF(payment_doc_date, po_date))
•	Average Production Lead Time: AVG(DATEDIFF(delivery_date, planning_date))
•	Stock Availability Rate: (Items with onhand > safety_stock / Total items) * 100
•	Supply Chain Cost Efficiency: Total cost / Total output value
________________________________________
CHART 6.2: Order to Cash Flow
Tipe Visualisasi: Sankey Diagram
Sumber Data: so_invoice_line + view_prod_header + stockbywh
Flow Structure:
•	Node 1: Available Stock (stockbywh)
•	Node 2: Production Orders (view_prod_header)
•	Node 3: Sales Orders (so_invoice_line.sales_order)
•	Node 4: Shipments (so_invoice_line.shipment)
•	Node 5: Invoices (so_invoice_line.invoice_no)
Link Thickness: Value atau quantity flow
Color: Process stage
Tooltip: Volume, Value, Average cycle time
________________________________________
CHART 6.3: Procure to Pay Flow
Tipe Visualisasi: Sankey Diagram
Sumber Data: data_receipt_purchase
Flow Structure:
•	Node 1: PO Created (po_no)
•	Node 2: Receipts (receipt_no)
•	Node 3: Invoices (inv_doc_no)
•	Node 4: Payments (payment_doc)
Link Thickness: Receipt amount
Color: Status atau supplier category
Metrics: Cycle time per stage
________________________________________
CHART 6.4: Demand vs Supply Analysis
Tipe Visualisasi: Combo Chart (Clustered Column + Line)
Sumber Data: stockbywh + view_prod_header + so_invoice_line
Dimensi:
•	X-axis: Product Type atau Time Period
•	Y-axis Primary: Quantity
•	Y-axis Secondary: Coverage days
Series:
•	Available Stock (Column - Blue) = onhand - allocated
•	Production Plan (Column - Green) = SUM(qty_order)
•	Sales Demand (Column - Orange) = SUM(delivered_qty)
•	Stock Coverage (Line - Red) = Available stock / Avg daily demand
Target Line: Minimum coverage days (e.g., 30 days)
________________________________________
CHART 6.5: Lead Time Analysis
Tipe Visualisasi: Box Plot / Violin Chart
Sumber Data: Multiple tables
Categories:
•	Procurement Lead Time (data_receipt_purchase)
•	Production Lead Time (view_prod_header)
•	Delivery Lead Time (so_invoice_line)
•	Total Order Fulfillment Time
Display:
•	Min, Q1, Median, Q3, Max
•	Outliers
•	Average line
Grouping: By category, supplier, customer, or product type
Filter: Date range, Product type
________________________________________
CHART 6.6: Material Availability for Production
Tipe Visualisasi: Heat Map
Sumber Data: stockbywh + view_prod_header
Structure:
•	Rows: Production Orders (prod_no) - Active orders
•	Columns: Required Materials (partno)
•	Cell Color: Availability status
Color Scale:
•	Dark Green: Available (onhand >= required)
•	Light Green: Partial (onhand < required but > 50%)
•	Yellow: Low (onhand < 50% required)
•	Red: Not available (onhand = 0 atau < 20% required)
Tooltip: Part no, Required qty, Available qty, Shortage
Filter: Production order status, Planning date range
________________________________________
CHART 6.7: Backorder Analysis
Tipe Visualisasi: Pareto Chart (Bar + Line)
Sumber Data: stockbywh + so_invoice_line
Dimensi:
•	X-axis: Part No (items with frequent stockout)
•	Y-axis Primary: Stockout frequency atau backorder qty
•	Y-axis Secondary: Cumulative percentage
Calculation:
•	Stockout incidents: COUNT(periods where onhand < demand)
•	Cumulative impact: Running total %
80/20 Line: Mark items causing 80% of problems
Color: Bars (Blue), Cumulative line (Orange)
Sorting: Descending by stockout frequency
________________________________________
CHART 6.8: Supply Chain Cycle Time Trend
Tipe Visualisasi: Line Chart (Multiple Lines)
Sumber Data: Multiple tables
Dimensi:
•	X-axis: Month/Week
•	Y-axis: Average cycle time (days)
Series:
•	Procurement Cycle Time (Blue)
•	Production Cycle Time (Green)
•	Delivery Cycle Time (Orange)
•	Total Order to Cash Cycle Time (Red - Bold)
Target Lines: Target cycle time per process
Filter: Product type, Customer, Date range
