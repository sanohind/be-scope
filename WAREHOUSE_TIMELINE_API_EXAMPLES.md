# Warehouse Order Timeline API - Usage Examples

## Quick Start

### 1. Basic Usage - Get Recent Orders

```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline"
```

**Response Summary:**
- Returns last 50 orders (default)
- Grouped by order_no
- Includes timeline (order → planned → actual)
- Status indicator (on_time/delayed/pending)

---

## Common Use Cases

### 2. Filter by Date Range

Get orders from October 2025:
```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?date_from=2025-10-01&date_to=2025-10-31"
```

**Use Case**: Monthly performance review

---

### 3. Filter by Status - Find Delayed Orders

```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?status=delayed"
```

**Use Case**: Identify problematic orders for investigation

---

### 4. Filter by Status - Monitor Pending Orders

```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?status=pending&limit=100"
```

**Use Case**: Active order monitoring dashboard

---

### 5. Filter by Warehouse

Get orders from specific warehouse:
```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?ship_from=WH01"
```

**Use Case**: Warehouse-specific performance tracking

---

### 6. Combined Filters

Delayed orders from WH01 in October:
```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?ship_from=WH01&status=delayed&date_from=2025-10-01&date_to=2025-10-31"
```

**Use Case**: Root cause analysis for specific warehouse

---

### 7. Get Order Detail (Drill-down)

```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline/SO-2025-001"
```

**Use Case**: Click on Gantt Chart bar to see line items

---

### 8. Get Filter Options

```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline/filters"
```

**Use Case**: Populate filter dropdowns in UI

---

## Response Examples

### Example 1: Timeline Response (Abbreviated)

```json
{
  "data": [
    {
      "order_no": "WO250001",
      "trx_type": "Customer Order",
      "ship_from": "WH01",
      "ship_from_desc": "Main Warehouse Jakarta",
      "ship_to": "WH02",
      "ship_to_desc": "Distribution Center Surabaya",
      "order_date": "2025-10-01",
      "planned_delivery_date": "2025-10-15",
      "actual_receipt_date": "2025-10-14",
      "total_lines": 8,
      "total_order_qty": 2500,
      "total_ship_qty": 2450,
      "delivery_status": "on_time",
      "days_difference": -1,
      "planned_duration_days": 14,
      "actual_duration_days": 13,
      "fulfillment_rate": 98.0,
      "status_color": "#10B981"
    },
    {
      "order_no": "WO250002",
      "trx_type": "Transfer Order",
      "ship_from": "WH01",
      "ship_from_desc": "Main Warehouse Jakarta",
      "ship_to": "WH03",
      "ship_to_desc": "Regional Warehouse Bandung",
      "order_date": "2025-10-02",
      "planned_delivery_date": "2025-10-16",
      "actual_receipt_date": "2025-10-18",
      "total_lines": 5,
      "total_order_qty": 1200,
      "total_ship_qty": 1200,
      "delivery_status": "delayed",
      "days_difference": 2,
      "planned_duration_days": 14,
      "actual_duration_days": 16,
      "fulfillment_rate": 100.0,
      "status_color": "#EF4444"
    },
    {
      "order_no": "WO250003",
      "trx_type": "Customer Order",
      "ship_from": "WH02",
      "ship_from_desc": "Distribution Center Surabaya",
      "ship_to": "CUST001",
      "ship_to_desc": "Customer A Jakarta",
      "order_date": "2025-10-05",
      "planned_delivery_date": "2025-10-20",
      "actual_receipt_date": null,
      "total_lines": 12,
      "total_order_qty": 3500,
      "total_ship_qty": 0,
      "delivery_status": "pending",
      "days_difference": null,
      "planned_duration_days": 15,
      "actual_duration_days": null,
      "fulfillment_rate": 0,
      "status_color": "#F59E0B"
    }
  ],
  "summary": {
    "total_orders": 50,
    "on_time": 35,
    "delayed": 10,
    "pending": 5,
    "avg_planned_duration": 13.5,
    "avg_actual_duration": 14.2,
    "on_time_rate": 77.78
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

---

### Example 2: Order Detail Response

```json
{
  "order_summary": {
    "order_no": "WO250001",
    "total_lines": 8,
    "total_order_qty": 2500,
    "total_ship_qty": 2450,
    "earliest_order_date": "2025-10-01",
    "latest_receipt_date": "2025-10-14",
    "overall_status": "on_time",
    "ship_from": "WH01",
    "ship_from_desc": "Main Warehouse Jakarta",
    "ship_to": "WH02",
    "ship_to_desc": "Distribution Center Surabaya"
  },
  "order_lines": [
    {
      "order_no": "WO250001",
      "line_no": 1,
      "order_date": "2025-10-01",
      "delivery_date": "2025-10-15",
      "receipt_date": "2025-10-14",
      "item_code": "PART-001",
      "item_desc": "Brake Pad Assembly",
      "item_desc2": "Front Disc",
      "order_qty": 500,
      "ship_qty": 500,
      "unit": "PCS",
      "line_status": "Shipped",
      "delivery_status": "on_time",
      "days_difference": -1,
      "fulfillment_rate": 100.0
    },
    {
      "order_no": "WO250001",
      "line_no": 2,
      "order_date": "2025-10-01",
      "delivery_date": "2025-10-15",
      "receipt_date": "2025-10-14",
      "item_code": "PART-002",
      "item_desc": "Oil Filter",
      "item_desc2": "Type A",
      "order_qty": 800,
      "ship_qty": 750,
      "unit": "PCS",
      "line_status": "Shipped",
      "delivery_status": "on_time",
      "days_difference": -1,
      "fulfillment_rate": 93.75
    }
  ]
}
```

---

### Example 3: Filters Response

```json
{
  "warehouses": [
    {
      "value": "WH01",
      "label": "Main Warehouse Jakarta"
    },
    {
      "value": "WH02",
      "label": "Distribution Center Surabaya"
    },
    {
      "value": "WH03",
      "label": "Regional Warehouse Bandung"
    }
  ],
  "destinations": [
    {
      "value": "WH02",
      "label": "Distribution Center Surabaya"
    },
    {
      "value": "WH03",
      "label": "Regional Warehouse Bandung"
    },
    {
      "value": "CUST001",
      "label": "Customer A Jakarta"
    },
    {
      "value": "CUST002",
      "label": "Customer B Surabaya"
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
    "max": "2025-11-03"
  }
}
```

---

## JavaScript/TypeScript Integration

### Basic Fetch

```javascript
// Get timeline data
async function getOrderTimeline(filters = {}) {
  const params = new URLSearchParams(filters);
  const response = await fetch(
    `/api/dashboard/warehouse/order-timeline?${params}`
  );
  
  if (!response.ok) {
    throw new Error('Failed to fetch timeline');
  }
  
  return await response.json();
}

// Usage
const timeline = await getOrderTimeline({
  date_from: '2025-10-01',
  date_to: '2025-10-31',
  limit: 50
});

console.log(`Total orders: ${timeline.summary.total_orders}`);
console.log(`On-time rate: ${timeline.summary.on_time_rate}%`);
```

---

### With Axios

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: '/api/dashboard/warehouse'
});

// Get timeline
export const getOrderTimeline = async (filters) => {
  const { data } = await api.get('/order-timeline', { params: filters });
  return data;
};

// Get order detail
export const getOrderDetail = async (orderNo) => {
  const { data } = await api.get(`/order-timeline/${orderNo}`);
  return data;
};

// Get filters
export const getTimelineFilters = async () => {
  const { data } = await api.get('/order-timeline/filters');
  return data;
};
```

---

### React Hook Example

```javascript
import { useState, useEffect } from 'react';

function useOrderTimeline(filters) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const params = new URLSearchParams(filters);
        const response = await fetch(
          `/api/dashboard/warehouse/order-timeline?${params}`
        );
        
        if (!response.ok) throw new Error('Failed to fetch');
        
        const result = await response.json();
        setData(result);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [JSON.stringify(filters)]);

  return { data, loading, error };
}

// Usage in component
function TimelineChart() {
  const [filters, setFilters] = useState({
    limit: 50,
    status: null
  });
  
  const { data, loading, error } = useOrderTimeline(filters);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h2>Order Timeline</h2>
      <p>Total Orders: {data.summary.total_orders}</p>
      <p>On-Time Rate: {data.summary.on_time_rate}%</p>
      {/* Render Gantt Chart here */}
    </div>
  );
}
```

---

## Python Integration

```python
import requests
import pandas as pd

BASE_URL = "http://localhost:8000/api/dashboard/warehouse"

def get_order_timeline(filters=None):
    """Fetch order timeline data"""
    url = f"{BASE_URL}/order-timeline"
    response = requests.get(url, params=filters)
    response.raise_for_status()
    return response.json()

def get_order_detail(order_no):
    """Fetch order detail"""
    url = f"{BASE_URL}/order-timeline/{order_no}"
    response = requests.get(url)
    response.raise_for_status()
    return response.json()

# Usage
timeline = get_order_timeline({
    'date_from': '2025-10-01',
    'date_to': '2025-10-31',
    'limit': 100
})

# Convert to DataFrame
df = pd.DataFrame(timeline['data'])

# Analysis
print(f"Total Orders: {len(df)}")
print(f"On-Time Rate: {timeline['summary']['on_time_rate']}%")
print(f"\nStatus Distribution:")
print(df['delivery_status'].value_counts())
```

---

## Postman Collection

### 1. Get Timeline
```
GET {{base_url}}/api/dashboard/warehouse/order-timeline
Params:
  - date_from: 2025-10-01
  - date_to: 2025-10-31
  - limit: 50
```

### 2. Get Timeline - Delayed Only
```
GET {{base_url}}/api/dashboard/warehouse/order-timeline
Params:
  - status: delayed
  - limit: 100
```

### 3. Get Order Detail
```
GET {{base_url}}/api/dashboard/warehouse/order-timeline/WO250001
```

### 4. Get Filters
```
GET {{base_url}}/api/dashboard/warehouse/order-timeline/filters
```

---

## Testing Checklist

- [ ] Basic timeline retrieval (default 50 orders)
- [ ] Date range filtering
- [ ] Status filtering (on_time, delayed, pending)
- [ ] Warehouse filtering (ship_from, ship_to)
- [ ] Limit parameter (50, 100)
- [ ] Combined filters
- [ ] Order detail drill-down
- [ ] Filter options retrieval
- [ ] Invalid order number handling (404)
- [ ] Performance with 100 orders
- [ ] Response time < 2 seconds

---

## Performance Tips

1. **Always use date filters** for production:
   ```javascript
   // ✅ Good
   getTimeline({ date_from: '2025-10-01', date_to: '2025-10-31' })
   
   // ❌ Avoid (loads too much data)
   getTimeline()
   ```

2. **Cache filter options**:
   ```javascript
   // Fetch once on app load
   const filters = await getTimelineFilters();
   localStorage.setItem('timelineFilters', JSON.stringify(filters));
   ```

3. **Implement pagination** for large datasets:
   ```javascript
   // Load in batches
   const batch1 = await getTimeline({ limit: 50, page: 1 });
   const batch2 = await getTimeline({ limit: 50, page: 2 });
   ```

---

## Troubleshooting

### Issue: No data returned
**Solution**: Check if data exists in date range
```bash
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline/filters"
# Check date_range.min and date_range.max
```

### Issue: Slow response
**Solution**: Add date filters and reduce limit
```bash
# Instead of this:
curl -X GET "...?limit=100"

# Do this:
curl -X GET "...?date_from=2025-10-01&date_to=2025-10-31&limit=50"
```

### Issue: Order not found (404)
**Solution**: Verify order number exists
```bash
# Check available orders
curl -X GET "http://localhost:8000/api/dashboard/warehouse/order-timeline?limit=10"
```

---

## Next Steps

1. **Implement Gantt Chart UI** using libraries like:
   - Frappe Gantt
   - DHTMLX Gantt
   - Chart.js with Gantt plugin
   - Custom D3.js visualization

2. **Add Export functionality**:
   - CSV export for timeline data
   - PDF report generation
   - Excel export with charts

3. **Real-time updates**:
   - WebSocket integration for live order status
   - Auto-refresh every 5 minutes
   - Push notifications for delayed orders

4. **Advanced Analytics**:
   - Trend analysis over time
   - Warehouse performance comparison
   - Predictive delay alerts
