# Asakai Board API Documentation

## Overview
API untuk mengelola Asakai Board yang mencakup Chart dan Reason data untuk berbagai kategori (Safety, Quality, Delivery).

## Base URL
```
/api/asakai
```

---

## 1. Asakai Titles (Master Data)

### 1.1 Get All Titles
**Endpoint:** `GET /api/asakai/titles`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Safety - Fatal Accident",
      "category": "Safety",
      "created_at": "2026-01-15T14:30:00.000000Z",
      "updated_at": "2026-01-15T14:30:00.000000Z"
    },
    ...
  ]
}
```

### 1.2 Get Title by ID
**Endpoint:** `GET /api/asakai/titles/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Safety - Fatal Accident",
    "category": "Safety",
    "created_at": "2026-01-15T14:30:00.000000Z",
    "updated_at": "2026-01-15T14:30:00.000000Z"
  }
}
```

---

## 2. Asakai Charts

### 2.1 Get All Charts
**Endpoint:** `GET /api/asakai/charts`

**Query Parameters:**
- `asakai_title_id` (optional) - Filter by asakai title ID
- `date` (optional) - Filter by specific date (YYYY-MM-DD)
- `date_from` (optional) - Filter from date (YYYY-MM-DD)
- `date_to` (optional) - Filter to date (YYYY-MM-DD)
- `per_page` (optional, default: 10) - Items per page

**Example Request:**
```
GET /api/asakai/charts?asakai_title_id=1&date_from=2026-01-01&date_to=2026-01-31&per_page=20
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "asakai_title_id": 1,
      "asakai_title": "Safety - Fatal Accident",
      "category": "Safety",
      "date": "2026-01-15",
      "qty": 5,
      "user": "John Doe",
      "user_id": 1,
      "reasons_count": 1,
      "created_at": "2026-01-15 14:20:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 50,
    "per_page": 10,
    "last_page": 5
  }
}
```

### 2.2 Create Chart
**Endpoint:** `POST /api/asakai/charts`

**Request Body:**
```json
{
  "asakai_title_id": 1,
  "date": "2026-01-15",
  "qty": 5
}
```

**Validation Rules:**
- `asakai_title_id`: required, must exist in asakai_titles table
- `date`: required, must be valid date
- `qty`: required, integer (can be negative)
- `user_id`: automatically set from authenticated user

**Response:**
```json
{
  "success": true,
  "message": "Chart data created successfully",
  "data": {
    "id": 1,
    "asakai_title_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "qty": 5,
    "user": "John Doe",
    "user_id": 1,
    "created_at": "2026-01-15 14:20:00"
  }
}
```

**Error Response (Duplicate):**
```json
{
  "success": false,
  "message": "Chart data already exists for this title and date"
}
```

### 2.3 Get Chart by ID (with Reasons)
**Endpoint:** `GET /api/asakai/charts/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "asakai_title_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "qty": 5,
    "user": "John Doe",
    "user_id": 1,
    "created_at": "2026-01-15 14:20:00",
    "reasons": [
      {
        "id": 1,
        "date": "2026-01-15",
        "part_no": "ABC123",
        "part_name": "Engine Part",
        "problem": "Crack detected",
        "qty": 2,
        "section": "brazzing",
        "line": "Line A",
        "penyebab": "Material defect",
        "perbaikan": "Replace material",
        "user": "Jane Doe",
        "user_id": 2,
        "created_at": "2026-01-15 15:00:00"
      }
    ]
  }
}
```

### 2.4 Update Chart
**Endpoint:** `PUT /api/asakai/charts/{id}`

**Request Body:**
```json
{
  "asakai_title_id": 1,
  "date": "2026-01-15",
  "qty": 10
}
```

**Note:** All fields are optional. Only send fields you want to update.

**Response:**
```json
{
  "success": true,
  "message": "Chart data updated successfully",
  "data": {
    "id": 1,
    "asakai_title_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "qty": 10,
    "user": "John Doe",
    "user_id": 1,
    "updated_at": "2026-01-15 16:00:00"
  }
}
```

### 2.5 Delete Chart
**Endpoint:** `DELETE /api/asakai/charts/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Chart data deleted successfully"
}
```

**Note:** Deleting a chart will also delete all associated reasons (cascade delete).

### 2.6 Get Available Dates for Reason Input
**Endpoint:** `GET /api/asakai/charts/available-dates`

**Query Parameters:**
- `asakai_title_id` (required) - Asakai title ID

**Example Request:**
```
GET /api/asakai/charts/available-dates?asakai_title_id=1
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "chart_id": 1,
      "date": "2026-01-15",
      "qty": 5,
      "has_reason": true
    },
    {
      "chart_id": 2,
      "date": "2026-01-14",
      "qty": 3,
      "has_reason": false
    }
  ]
}
```

**Use Case:** Digunakan untuk menampilkan dropdown tanggal yang tersedia saat input reason.

---

## 3. Asakai Reasons

### 3.1 Get All Reasons
**Endpoint:** `GET /api/asakai/reasons`

**Query Parameters:**
- `asakai_chart_id` (optional) - Filter by chart ID
- `date_from` (optional) - Filter from date (YYYY-MM-DD)
- `date_to` (optional) - Filter to date (YYYY-MM-DD)
- `section` (optional) - Filter by section (brazzing, chassis, nylon, subcon, passthrough)
- `search` (optional) - Search by part_no
- `per_page` (optional, default: 10) - Items per page

**Example Request:**
```
GET /api/asakai/reasons?section=brazzing&date_from=2026-01-01&search=ABC
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "asakai_chart_id": 1,
      "asakai_title": "Safety - Fatal Accident",
      "category": "Safety",
      "date": "2026-01-15",
      "part_no": "ABC123",
      "part_name": "Engine Part",
      "problem": "Crack detected",
      "qty": 2,
      "section": "brazzing",
      "line": "Line A",
      "penyebab": "Material defect",
      "perbaikan": "Replace material",
      "user": "Jane Doe",
      "user_id": 2,
      "created_at": "2026-01-15 15:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 20,
    "per_page": 10,
    "last_page": 2
  }
}
```

### 3.2 Create Reason
**Endpoint:** `POST /api/asakai/reasons`

**Request Body:**
```json
{
  "asakai_chart_id": 1,
  "date": "2026-01-15",
  "part_no": "ABC123",
  "part_name": "Engine Part",
  "problem": "Crack detected",
  "qty": 2,
  "section": "brazzing",
  "line": "Line A",
  "penyebab": "Material defect",
  "perbaikan": "Replace material"
}
```

**Validation Rules:**
- `asakai_chart_id`: required, must exist in asakai_charts table
- `date`: required, must be valid date, **must match the chart's date**
- `part_no`: required, string
- `part_name`: required, string
- `problem`: required, string
- `qty`: required, integer (can be negative)
- `section`: required, must be one of: brazzing, chassis, nylon, subcon, passthrough
- `line`: required, string
- `penyebab`: required, string
- `perbaikan`: required, string
- `user_id`: automatically set from authenticated user

**Response:**
```json
{
  "success": true,
  "message": "Reason created successfully",
  "data": {
    "id": 1,
    "asakai_chart_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "part_no": "ABC123",
    "part_name": "Engine Part",
    "problem": "Crack detected",
    "qty": 2,
    "section": "brazzing",
    "line": "Line A",
    "penyebab": "Material defect",
    "perbaikan": "Replace material",
    "user": "Jane Doe",
    "user_id": 2,
    "created_at": "2026-01-15 15:00:00"
  }
}
```

**Error Response (Date Mismatch):**
```json
{
  "success": false,
  "message": "Date must match the chart date (2026-01-15)"
}
```

**Error Response (Duplicate):**
```json
{
  "success": false,
  "message": "Reason already exists for this chart and date"
}
```

### 3.3 Get Reason by ID
**Endpoint:** `GET /api/asakai/reasons/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "asakai_chart_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "part_no": "ABC123",
    "part_name": "Engine Part",
    "problem": "Crack detected",
    "qty": 2,
    "section": "brazzing",
    "line": "Line A",
    "penyebab": "Material defect",
    "perbaikan": "Replace material",
    "user": "Jane Doe",
    "user_id": 2,
    "created_at": "2026-01-15 15:00:00"
  }
}
```

### 3.4 Update Reason
**Endpoint:** `PUT /api/asakai/reasons/{id}`

**Request Body:**
```json
{
  "part_no": "ABC456",
  "qty": 5,
  "perbaikan": "Updated solution"
}
```

**Note:** All fields are optional. Only send fields you want to update.

**Response:**
```json
{
  "success": true,
  "message": "Reason updated successfully",
  "data": {
    "id": 1,
    "asakai_chart_id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "part_no": "ABC456",
    "part_name": "Engine Part",
    "problem": "Crack detected",
    "qty": 5,
    "section": "brazzing",
    "line": "Line A",
    "penyebab": "Material defect",
    "perbaikan": "Updated solution",
    "user": "Jane Doe",
    "user_id": 2,
    "updated_at": "2026-01-15 16:30:00"
  }
}
```

### 3.5 Delete Reason
**Endpoint:** `DELETE /api/asakai/reasons/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Reason deleted successfully"
}
```

### 3.6 Get Reasons by Chart ID
**Endpoint:** `GET /api/asakai/charts/{chartId}/reasons`

**Response:**
```json
{
  "success": true,
  "chart": {
    "id": 1,
    "asakai_title": "Safety - Fatal Accident",
    "category": "Safety",
    "date": "2026-01-15",
    "qty": 5
  },
  "data": [
    {
      "id": 1,
      "date": "2026-01-15",
      "part_no": "ABC123",
      "part_name": "Engine Part",
      "problem": "Crack detected",
      "qty": 2,
      "section": "brazzing",
      "line": "Line A",
      "penyebab": "Material defect",
      "perbaikan": "Replace material",
      "user": "Jane Doe",
      "user_id": 2,
      "created_at": "2026-01-15 15:00:00"
    }
  ]
}
```

---

## 4. Workflow Example

### Typical Usage Flow:

1. **Get Available Titles**
   ```
   GET /api/asakai/titles
   ```

2. **Create Chart Data**
   ```
   POST /api/asakai/charts
   {
     "asakai_title_id": 1,
     "date": "2026-01-15",
     "qty": 5
   }
   ```

3. **Get Available Dates for Reason Input**
   ```
   GET /api/asakai/charts/available-dates?asakai_title_id=1
   ```

4. **Create Reason (using chart_id from step 2)**
   ```
   POST /api/asakai/reasons
   {
     "asakai_chart_id": 1,
     "date": "2026-01-15",
     "part_no": "ABC123",
     "part_name": "Engine Part",
     "problem": "Crack detected",
     "qty": 2,
     "section": "brazzing",
     "line": "Line A",
     "penyebab": "Material defect",
     "perbaikan": "Replace material"
   }
   ```

5. **View Chart with Reasons**
   ```
   GET /api/asakai/charts/1
   ```

---

## 5. Database Schema

### asakai_titles
- `id` - Primary Key
- `title` - String (e.g., "Safety - Fatal Accident")
- `category` - Enum (Safety, Quality, Delivery)
- `created_at`, `updated_at`

### asakai_charts
- `id` - Primary Key
- `asakai_title_id` - Foreign Key to asakai_titles
- `date` - Date
- `qty` - Integer (can be negative)
- `user_id` - Foreign Key to users
- `created_at`, `updated_at`
- **Unique Constraint:** (asakai_title_id, date)

### asakai_reasons
- `id` - Primary Key
- `asakai_chart_id` - Foreign Key to asakai_charts
- `date` - Date (must match chart date)
- `part_no` - String
- `part_name` - String
- `problem` - Text
- `qty` - Integer (can be negative)
- `section` - Enum (brazzing, chassis, nylon, subcon, passthrough)
- `line` - String
- `penyebab` - Text
- `perbaikan` - Text
- `user_id` - Foreign Key to users
- `created_at`, `updated_at`
- **Unique Constraint:** (asakai_chart_id, date)

---

## 6. Section Enum Values
- `brazzing`
- `chassis`
- `nylon`
- `subcon`
- `passthrough`

---

## 7. Error Handling

All endpoints return consistent error responses:

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Resource not found",
  "error": "Error details"
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "Operation failed",
  "error": "Error details"
}
```
