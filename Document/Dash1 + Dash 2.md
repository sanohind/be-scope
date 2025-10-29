DASHBOARD 1: INVENTORY MANAGEMENT & STOCK CONTROL
Tujuan Dashboard
Monitoring dan analisis stock inventory untuk memastikan ketersediaan material optimal, mencegah stockout dan overstock, serta optimasi biaya inventory.
Target User
•	Inventory Manager
•	Warehouse Manager
•	Supply Chain Manager
________________________________________
CHART 1.1: Stock Level Overview
Tipe Visualisasi: KPI Cards (4-5 cards)
Sumber Data: stockbywh
Metrik:
•	Total Onhand: SUM(onhand)
•	Items Below Safety Stock: COUNT(DISTINCT partno) WHERE onhand < safety_stock
•	Items Above Max Stock: COUNT(DISTINCT partno) WHERE onhand > max_stock
Refresh Rate: Real-time / Hourly
________________________________________
CHART 1.2: Stock Health by Warehouse
Tipe Visualisasi: Stacked Bar Chart (Horizontal)
Sumber Data: stockbywh
Dimensi:
•	X-axis: Warehouse
•	Y-axis: Count of items
•	Series/Stack: 
o	Critical (onhand < min_stock) - Red
o	Low (onhand < safety_stock) - Orange
o	Normal (onhand >= safety_stock AND onhand <= max_stock) - Green
o	Overstock (onhand > max_stock) - Blue
Filter: Product type, Group, Customer
Interaktivitas: Drill-down ke detail items
________________________________________
CHART 1.3: Top 20 Critical Items
Tipe Visualisasi: Data Table with Conditional Formatting
Sumber Data: stockbywh
Kolom:
•	Part No (partno)
•	Description (desc)
•	Warehouse (warehouse dibuat jadi kolom pertama)
•	Onhand (dengan color coding)
•	Safety Stock
•	Min Stock
•	Max Stock
•	Location
•	Gap (safety_stock - onhand)
Sorting: Gap descending
Filter: Status (Critical/Low/Overstock)
Conditional Formatting:
•	Red: onhand < min_stock
•	Orange: onhand < safety_stock
•	Blue: onhand > max_stock
________________________________________
CHART 1.4: Stock Distribution by Product Type
Tipe Visualisasi: Treemap
Sumber Data: stockbywh
Hierarchy:
•	Level 1: Product Type (product_type)
•	Level 2: Model (model)
•	Level 3: Part No (partno)
Size Metric: Onhand quantity
Color Metric: Stock health status atau value
Tooltip: Part name, Onhand, Allocated, Available
________________________________________
CHART 1.5: Stock by Customer
Tipe Visualisasi: Donut Chart
Sumber Data: stockbywh
Metrik:
•	Slice: Customer
•	Value: SUM(onhand) atau COUNT(partno)
•	Center: Total items/qty
Interaktivitas: Click to filter other charts
________________________________________
CHART 1.6: Inventory Availability vs Demand
Tipe Visualisasi: Combo Chart (Clustered Column + Line)
Sumber Data: stockbywh
Dimensi:
•	X-axis: Warehouse atau Product Group
•	Y-axis Primary: Quantity
•	Y-axis Secondary: Percentage
Series:
•	Onhand (Column - Blue)
•	Allocated (Column - Orange)
•	On Order (Column - Green)
•	Available % (Line - Red) = (onhand-allocated)/onhand * 100
________________________________________
CHART 1.7: Stock Movement Trend
Tipe Visualisasi: Area Chart (Stacked)
Sumber Data: stockbywh (memerlukan data historis atau snapshot)
Dimensi:
•	X-axis: Date/Period
•	Y-axis: Quantity
Series:
•	Onhand (Area)
•	Allocated (Area)
•	Available (Onhand - Allocated) (Area)
Filter: Warehouse, Product type, Date range
________________________________________
 
DASHBOARD 2: WAREHOUSE OPERATIONS
Tujuan Dashboard
Monitoring operasional warehouse order, analisis fulfillment rate, tracking delivery performance, dan optimasi warehouse movement.
Target User
•	Warehouse Manager
•	Logistics Coordinator
•	Operations Manager
________________________________________CHART 2.1: Warehouse Order Summary
Tipe Visualisasi: KPI Cards (4-5 cards)
Sumber Data: view_warehouse_order & view_warehouse_order_line
Metrik:
•	Total Order Lines: COUNT(*)
•	Pending Deliveries: COUNT(*) WHERE line_status = 'Pending'
•	Completed Orders: COUNT(*) WHERE line_status = 'Completed'
•	Average Fulfillment Rate: AVG(ship_qty/order_qty) * 100
________________________________________
CHART 2.2: Order Flow Analysis
Tipe Visualisasi: Sankey Diagram
Sumber Data: view_warehouse_order_line
Flow Structure:
•	Source Node: ship_from (warehouse/location)
•	Target Node: ship_to (warehouse/location)
•	Link Thickness: SUM(ship_qty) atau COUNT(order_no)
Color Coding: By transaction type
Tooltip: Transaction type, Total qty, Order count
________________________________________
CHART 2.3: Delivery Performance
Tipe Visualisasi: Gauge Chart / Speedometer
Sumber Data: view_warehouse_order_line
Metrik:
•	On-Time Delivery Rate: COUNT() WHERE receipt_date <= delivery_date / COUNT() * 100
•	Target: 95%
Supporting Metrics:
•	Early: receipt_date < delivery_date
•	On-Time: receipt_date = delivery_date
•	Late: receipt_date > delivery_date
Display: Percentage with color coding (Green >95%, Yellow 85-95%, Red <85%)
________________________________________
CHART 2.4: Order Status Distribution
Tipe Visualisasi: Stacked Bar Chart (100%)
Sumber Data: view_warehouse_order_line
Dimensi:
•	X-axis: Transaction Type (trx_type)
•	Y-axis: Percentage
•	Stack: Line Status (line_status)
Grouping Option: By Ship From warehouse
Color: Status-based (Completed-Green, Pending-Yellow, Cancelled-Red)
________________________________________
CHART 2.5: Daily Order Volume
Tipe Visualisasi: Line Chart with Area
Sumber Data: view_warehouse_order_line
Dimensi:
•	X-axis: Order Date
•	Y-axis: Order quantity atau Order count
Series:
•	Order Qty (Area - Blue)
•	Ship Qty (Line - Green)
•	Gap (Order - Ship) (Line - Red)
Filter: Transaction type, Warehouse, Date range
________________________________________
CHART 2.6: Order Fulfillment Rate
Tipe Visualisasi: Bar Chart with Target Line
Sumber Data: view_warehouse_order_line
Dimensi:
•	X-axis: Warehouse (ship_from)
•	Y-axis: Fulfillment rate %
Calculation: (SUM(ship_qty) / SUM(order_qty)) * 100
Target Line: 100% atau target yang ditentukan
Color Coding: Achievement vs target
________________________________________
CHART 2.7: Top Items Moved
Tipe Visualisasi: Horizontal Bar Chart
Sumber Data: view_warehouse_order_line
Dimensi:
•	X-axis: Total quantity moved
•	Y-axis: Item code + description (top 20)
Sorting: Descending by quantity
Color: Gradient based on value
Tooltip: Item details, Total orders, Avg qty per order
________________________________________
CHART 2.8: Warehouse Order Timeline
Tipe Visualisasi: Gantt Chart atau Timeline
Sumber Data: view_warehouse_order_line
Struktur:
•	Rows: Order number
•	Timeline: order_date (start) → delivery_date (plan) → receipt_date (actual)
•	Status indicator: On-time/Delayed
Filter: Date range, Status, Warehouse
Limit: Last 50-100 orders untuk performa
