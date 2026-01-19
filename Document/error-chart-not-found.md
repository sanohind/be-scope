# Common Error: Chart Data Not Found

## ❌ Error Message
```json
{
  "success": false,
  "message": "Chart data not found",
  "error": "The requested chart does not exist or has been deleted."
}
```

## 🔍 Root Cause

Anda menggunakan **URL yang salah**:

### ❌ WRONG URL:
```
http://127.0.0.1:8000/api/asakai/charts/1&period=daily&date_from=2026-01-01
                                       ↑
                                       Missing "?" - Parameters are ignored!
```

URL ini diparsing sebagai:
- Endpoint: `/api/asakai/charts/1`
- Method: `show($id)` dengan `$id = 1`
- Query params: **DIABAIKAN** karena tidak ada `?`

Laravel mengira Anda ingin **melihat detail chart dengan ID 1**, bukan mendapatkan list chart data.

---

## ✅ Correct URLs

### **Option 1: List dengan Pagination**
```
http://127.0.0.1:8000/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&date_to=2026-01-31&per_page=31
                                       ↑
                                       Add "?" here!
```

**Response:**
```json
{
  "success": true,
  "data": [ /* 31 items */ ],
  "pagination": {
    "current_page": 1,
    "total": 31,
    "per_page": 31,
    "last_page": 1
  }
}
```

---

### **Option 2: Chart Data (Tanpa Pagination) - RECOMMENDED**
```
http://127.0.0.1:8000/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01&date_to=2026-01-31
                                       ↑
                                       /data endpoint
```

**Response:**
```json
{
  "success": true,
  "data": [ /* 31 items */ ],
  "filter_metadata": {
    "total_dates": 31,
    "dates_with_data": 2,
    "dates_without_data": 29
  }
}
```

---

## 📋 Endpoint Comparison

| URL Pattern | Method | Purpose | Pagination |
|-------------|--------|---------|------------|
| `/api/asakai/charts?asakai_title_id=1&...` | `index()` | List semua chart | ✅ Yes |
| `/api/asakai/charts/data?asakai_title_id=1&...` | `getChartData()` | Chart data | ❌ No |
| `/api/asakai/charts/{id}` | `show($id)` | Detail 1 chart | N/A |

---

## 🧪 Test Results

```
❌ /charts/1&period=...
   HTTP Code: 404
   Success: false
   Message: Chart data not found

✅ /charts?asakai_title_id=...
   HTTP Code: 200
   Success: true
   Items: 31

✅ /charts/data?asakai_title_id=...
   HTTP Code: 200
   Success: true
   Items: 31
```

---

## 💡 Quick Fix

**Ganti URL Anda dari:**
```
/api/asakai/charts/1&period=daily&date_from=2026-01-01
```

**Menjadi:**
```
/api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01
```

**Perubahan:**
1. Ganti `/1` menjadi `/data`
2. Ganti `&` pertama menjadi `?`
3. Tambahkan `asakai_title_id=1` sebagai query parameter

---

## 📝 URL Structure Explained

### Correct URL Structure:
```
http://127.0.0.1:8000/api/asakai/charts/data?asakai_title_id=1&period=daily
                                           ↑                   ↑
                                           |                   |
                                      Endpoint path      Query parameters
                                                         (start with ?)
```

### Wrong URL Structure:
```
http://127.0.0.1:8000/api/asakai/charts/1&period=daily
                                        ↑ ↑
                                        | |
                                        | Parameters are treated as part of path!
                                        |
                                   This is ID parameter, not query param
```

---

## 🎯 Recommendation

For getting chart data with filled dates, always use:

```bash
GET /api/asakai/charts/data?asakai_title_id={id}&period={period}&date_from={date}
```

This endpoint:
- ✅ Returns all dates in range
- ✅ Fills missing dates with qty = 0
- ✅ No pagination (perfect for charts)
- ✅ Clear and simple
