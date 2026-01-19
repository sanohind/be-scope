# Asakai Chart Data API - With Reasons

## ✅ Update: Reasons Included

Endpoint `/api/asakai/charts/data` sekarang **include data reasons** untuk setiap chart entry.

## Endpoint
```
GET /api/asakai/charts/data
```

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `asakai_title_id` | integer | ✅ Yes | - | ID dari asakai title |
| `period` | string | ❌ No | `monthly` | Period type: `daily`, `monthly`, `yearly` |
| `date_from` | date | ✅ Yes | - | Start date (format: Y-m-d) |
| `date_to` | date | ❌ No | Auto | End date (format: Y-m-d) |

## Response Format

### Data with Chart & Reasons
```json
{
  "date": "2026-01-19",
  "qty": 5,
  "has_data": true,
  "chart_id": 5,
  "reasons": [
    {
      "id": 1,
      "date": "2026-01-19",
      "part_no": "PART001",
      "part_name": "Engine Component",
      "problem": "Machine breakdown",
      "qty": 2,
      "section": "Assembly",
      "line": "Line 1",
      "penyebab": "Overheating",
      "perbaikan": "Replaced cooling fan",
      "user": "System Admin",
      "user_id": 1,
      "created_at": "2026-01-19 10:30:00"
    },
    {
      "id": 2,
      "date": "2026-01-19",
      "part_no": "PART002",
      "part_name": "Transmission Part",
      "problem": "Material shortage",
      "qty": 3,
      "section": "Welding",
      "line": "Line 2",
      "penyebab": "Supplier delay",
      "perbaikan": "Used alternative supplier",
      "user": "System Admin",
      "user_id": 1,
      "created_at": "2026-01-19 11:15:00"
    }
  ],
  "reasons_count": 2
}
```

### Data without Chart (Filled with 0)
```json
{
  "date": "2026-01-15",
  "qty": 0,
  "has_data": false,
  "chart_id": null,
  "reasons": [],
  "reasons_count": 0
}
```

## Complete Response Example

```json
{
  "success": true,
  "data": [
    {
      "date": "2026-01-15",
      "qty": 0,
      "has_data": false,
      "chart_id": null,
      "reasons": [],
      "reasons_count": 0
    },
    {
      "date": "2026-01-16",
      "qty": 0,
      "has_data": false,
      "chart_id": null,
      "reasons": [],
      "reasons_count": 0
    },
    {
      "date": "2026-01-17",
      "qty": 0,
      "has_data": false,
      "chart_id": null,
      "reasons": [],
      "reasons_count": 0
    },
    {
      "date": "2026-01-18",
      "qty": 0,
      "has_data": false,
      "chart_id": null,
      "reasons": [],
      "reasons_count": 0
    },
    {
      "date": "2026-01-19",
      "qty": 5,
      "has_data": true,
      "chart_id": 5,
      "reasons": [
        {
          "id": 1,
          "date": "2026-01-19",
          "part_no": "PART001",
          "part_name": "Engine Component",
          "problem": "Machine breakdown",
          "qty": 2,
          "section": "Assembly",
          "line": "Line 1",
          "penyebab": "Overheating",
          "perbaikan": "Replaced cooling fan",
          "user": "System Admin",
          "user_id": 1,
          "created_at": "2026-01-19 10:30:00"
        }
      ],
      "reasons_count": 1
    },
    {
      "date": "2026-01-20",
      "qty": 3,
      "has_data": true,
      "chart_id": 7,
      "reasons": [],
      "reasons_count": 0
    }
  ],
  "filter_metadata": {
    "asakai_title_id": 1,
    "period": "daily",
    "date_from": "2026-01-15",
    "date_to": "2026-01-20",
    "total_dates": 6,
    "dates_with_data": 2,
    "dates_without_data": 4
  }
}
```

## Response Fields

### Main Data Fields
| Field | Type | Description |
|-------|------|-------------|
| `date` | string | Date (Y-m-d format) |
| `qty` | integer | Quantity (0 if no data) |
| `has_data` | boolean | `true` if actual data exists, `false` if filled |
| `chart_id` | integer\|null | Chart ID if exists, `null` if no data |
| `reasons` | array | Array of reason objects |
| `reasons_count` | integer | Number of reasons |

### Reason Object Fields
| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Reason ID |
| `date` | string | Reason date |
| `part_no` | string | Part number |
| `part_name` | string | Part name |
| `problem` | string | Problem description |
| `qty` | integer | Quantity affected |
| `section` | string | Section/department |
| `line` | string | Production line |
| `penyebab` | string | Root cause (Indonesian) |
| `perbaikan` | string | Corrective action (Indonesian) |
| `user` | string | User who created the reason |
| `user_id` | integer | User ID |
| `created_at` | string | Creation timestamp |

## Use Cases

### 1. **Chart with Drill-down**
Display chart with ability to click on a date and see reasons:
```javascript
// Chart shows qty per date
// On click, show reasons for that date
const dateData = chartData.find(d => d.date === clickedDate);
if (dateData.reasons_count > 0) {
  showReasonsModal(dateData.reasons);
}
```

### 2. **Dashboard Summary**
Show total issues and breakdown by reason:
```javascript
const totalReasons = chartData.reduce((sum, d) => sum + d.reasons_count, 0);
const allReasons = chartData.flatMap(d => d.reasons);
```

### 3. **Trend Analysis**
Analyze which problems occur most frequently:
```javascript
const problemFrequency = {};
chartData.forEach(d => {
  d.reasons.forEach(r => {
    problemFrequency[r.problem] = (problemFrequency[r.problem] || 0) + 1;
  });
});
```

## Example Request

```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01&date_to=2026-01-31
```

## Benefits

✅ **Single API Call**: Get chart data + reasons in one request
✅ **Complete Context**: See qty and reasons together
✅ **Easy Drill-down**: Click on chart to see detailed reasons
✅ **Efficient**: Uses eager loading to avoid N+1 queries
✅ **Consistent**: Same structure for all dates (with/without data)

## Performance

- Uses `with(['reasons.user'])` for eager loading
- Avoids N+1 query problem
- Efficient for large date ranges
- Reasons are loaded only once per chart

## Notes

- `reasons` array is **always present** (empty array if no reasons)
- `reasons_count` shows the number of reasons (0 if none)
- Reasons are sorted by creation date
- Each reason includes the user who created it
