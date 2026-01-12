# Raw Material Stock Level Detail API

## Endpoint
```
GET /api/dashboard/inventory-rev/rm-stock-level-detail
```

## Description
Provides detailed stock information per partno for Raw Material (RM) warehouses. This endpoint displays each partno with its stock details, daily use, and estimated consumption calculated by matching data from `stockbywh` and `daily_use_wh` tables.

## Warehouse Restriction
This endpoint is **only available** for RM warehouses:
- `WHRM01`
- `WHRM02`
- `WHMT01`

## Request Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `warehouse` | string | Yes | - | Warehouse code (must be WHRM01, WHRM02, or WHMT01) |
| `date_from` | date | No | Current date | Start date for daily use and snapshot filtering (Y-m-d format) |
| `date_to` | date | No | Current date | End date for daily use and snapshot filtering (Y-m-d format) |
| `group_type` | string | No | - | Filter by group type description (group_type_desc) |
| `search` | string | No | - | Search by part number (partno) - uses LIKE query |
| `page` | integer | No | 1 | Page number for pagination |
| `per_page` | integer | No | 50 | Items per page (min: 10, max: 200) |

## Response Structure

### Success Response (200 OK)
```json
{
    "data": [
        {
            "partno": "ABC123",
            "desc": "Part Description",
            "location": "A-01-02",
            "group_type_desc": "Raw Material Type A",
            "warehouse": "WHRM01",
            "onhand": 1500.50,
            "min_stock": 500.00,
            "max_stock": 2000.00,
            "daily_use": 50.25,
            "estimated_consumption": 29.87,
            "stock_status": "Normal"
        }
    ],
    "filters": {
        "warehouse": "WHRM01",
        "date_from": "2026-01-12",
        "date_to": "2026-01-12",
        "group_type": null,
        "search": null
    },
    "snapshot_date": "2026-01-11",
    "pagination": {
        "total": 800,
        "per_page": 50,
        "current_page": 1,
        "last_page": 16,
        "from": 1,
        "to": 50
    }
}
```

### Error Response (400 Bad Request)
```json
{
    "error": "This endpoint is only available for RM warehouses (WHRM01, WHRM02, WHMT01)"
}
```

## Data Fields

### Stock Information
- **partno**: Part number (sorted alphabetically) - from `stockbywh`
- **desc**: Part description - from `stockbywh`
- **location**: Physical location in warehouse - from `stockbywh`
- **group_type_desc**: Group type description - from `stockbywh`
- **warehouse**: Warehouse code - from `stockbywh`
- **onhand**: Current on-hand quantity - from `stock_by_wh_snapshots` (latest snapshot)
- **min_stock**: Minimum stock level - from `stockbywh`
- **max_stock**: Maximum stock level - from `stockbywh`

### Daily Use Information (from `daily_use_wh`)
- **daily_use**: Daily usage quantity (sum of all matching records in date range)
  - Returns `0` if no daily use data found for the partno
- **estimated_consumption**: Days until stock runs out (onhand / daily_use)
  - Returns `0` if daily_use is 0

### Stock Status
Based on `estimated_consumption` value:
- **Undefined**: `daily_use = 0` (no data uploaded yet)
- **Critical**: `estimated_consumption <= 0` (stock depleted or will run out immediately)
- **Low Stock**: `estimated_consumption <= 3` (stock will last 3 days or less)
- **Normal**: `estimated_consumption <= 9` (stock will last 4-9 days)
- **Overstock**: `estimated_consumption > 9` (stock will last more than 9 days)

## Matching Logic

The system automatically matches data from three tables:

### 1. Basic Information (from `stockbywh`)
- Retrieves partno, desc, location, group_type_desc, min_stock, max_stock
- Filtered by warehouse

### 2. On-Hand Quantity (from `stock_by_wh_snapshots`)
- Gets the latest snapshot date for the warehouse
- Retrieves onhand values from that snapshot
- Matches by partno and warehouse
- Returns 0 if no snapshot data found for a partno

### 3. Daily Use (from `daily_use_wh`)
- Retrieves all daily use records for the date range
- Matches by partno (case-sensitive, trimmed)
- Sums daily_use if multiple records exist for the same partno
- Returns 0 for daily_use and estimated_consumption if no match found

## Notes

1. **No Foreign Key**: Since there's no FK relationship between tables, the system uses partno string matching
2. **Snapshot Data**: On-hand values come from the latest snapshot in `stock_by_wh_snapshots` within the date range, not real-time data
3. **Snapshot Filtering**: The `snapshot_date` is filtered by `date_from` and `date_to` parameters - only snapshots within this range are considered
4. **Date Range**: The date range also filters `plan_date` in `daily_use_wh` table
5. **Aggregation**: If multiple daily use records exist for the same partno within the date range, they are summed
6. **Default Dates**: If no dates provided, defaults to current date
7. **Sorting**: Results are sorted alphabetically by partno
8. **Latest Snapshot**: The API returns `snapshot_date` in the response to show which snapshot was used
9. **Group Type Filter**: When `group_type` is provided, it filters both `stockbywh` and `stock_by_wh_snapshots` by `group_type_desc`
10. **Default Onhand**: If no snapshot data exists, `onhand` defaults to 0
11. **Search Filter**: The `search` parameter uses LIKE query on `partno` field for partial matching

## Example Requests

### Basic Request (Current Date)
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01
```

### With Date Range
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&date_from=2026-01-01&date_to=2026-01-31
```

### With Group Type Filter
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&group_type=RAW%20MATERIAL
```

### With Search (Part Number)
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&search=ABC
```

### With Pagination
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&page=2&per_page=100
```

### Full Example
```bash
GET /api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&date_from=2026-01-01&date_to=2026-01-31&group_type=RAW%20MATERIAL&search=ABC&page=1&per_page=50
```

## Use Cases

1. **Stock Planning**: View detailed stock levels with consumption rates
2. **Reorder Analysis**: Identify items that need reordering based on estimated consumption
3. **Data Validation**: Check which items have/don't have daily use data
4. **Inventory Audit**: Review stock levels by location and group type
