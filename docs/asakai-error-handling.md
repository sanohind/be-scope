# Asakai Board API - Enhanced Error Handling

## 🎯 Overview

Enhanced error handling telah ditambahkan ke semua operasi CRUD di Asakai Board API untuk memberikan error message yang lebih informatif dan membantu debugging.

---

## ✨ Error Handling Features

### 1. **Specific Exception Types**
Setiap error ditangani berdasarkan tipe exception:
- `ModelNotFoundException` - Resource tidak ditemukan (404)
- `QueryException` - Database error (500)
- `ValidationException` - Validation error (422)
- `General Exception` - Unexpected error (500)

### 2. **Detailed Error Messages**
Setiap error response include:
- `success`: false
- `message`: User-friendly error message
- `error`: Detailed explanation
- `details`: Technical details (hanya muncul jika `APP_DEBUG=true`)

### 3. **Debug Mode Support**
- **Production** (`APP_DEBUG=false`): Hanya menampilkan user-friendly message
- **Development** (`APP_DEBUG=true`): Menampilkan technical details untuk debugging

---

## 📋 Error Response Examples

### **1. Validation Error (422)**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "asakai_title_id": ["The asakai title id field is required."],
    "date": ["The date field is required."]
  }
}
```

### **2. Authentication Error (401)**
```json
{
  "success": false,
  "message": "User not authenticated",
  "error": "Please login to create chart data"
}
```

### **3. Duplicate Entry (422)**
```json
{
  "success": false,
  "message": "Chart data already exists for this title and date",
  "error": "Duplicate entry detected. Please use a different date or update the existing chart."
}
```

### **4. Not Found (404)**
```json
{
  "success": false,
  "message": "Chart data not found",
  "error": "The requested chart does not exist or has been deleted."
}
```

### **5. Database Error (500)**
```json
{
  "success": false,
  "message": "Database error occurred while creating chart data",
  "error": "Unable to save data to database. Please try again or contact administrator.",
  "details": "SQLSTATE[23000]: Integrity constraint violation..." // Only in debug mode
}
```

### **6. Foreign Key Constraint (422)**
```json
{
  "success": false,
  "message": "Cannot delete chart with associated reasons",
  "error": "This chart has 3 associated reason(s). Please delete the reasons first or use force delete.",
  "reason_count": 3
}
```

### **7. Date Mismatch (422)**
```json
{
  "success": false,
  "message": "Date must match the chart date (2026-01-15)",
  "error": "The date you provided does not match the chart date. Please use the same date as the chart."
}
```

### **8. General Error (500)**
```json
{
  "success": false,
  "message": "Failed to create chart data",
  "error": "An unexpected error occurred. Please try again.",
  "details": "Exception message here..." // Only in debug mode
}
```

---

## 🔍 Error Handling by Operation

### **AsakaiChartController**

#### **Create (POST /api/asakai/charts)**
| Error Type | Status | Message |
|------------|--------|---------|
| Validation Failed | 422 | Validation error |
| User Not Authenticated | 401 | User not authenticated |
| Duplicate Entry | 422 | Chart data already exists for this title and date |
| Database Error | 500 | Database error occurred while creating chart data |
| General Error | 500 | Failed to create chart data |

#### **Read (GET /api/asakai/charts/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Not Found | 404 | Chart data not found |
| General Error | 500 | Failed to retrieve chart data |

#### **Update (PUT /api/asakai/charts/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Validation Failed | 422 | Validation error |
| Not Found | 404 | Chart data not found |
| Duplicate Entry | 422 | Chart data already exists for this title and date |
| Database Error | 500 | Database error occurred while updating chart data |
| General Error | 500 | Failed to update chart data |

#### **Delete (DELETE /api/asakai/charts/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Not Found | 404 | Chart data not found |
| Has Associated Reasons | 422 | Cannot delete chart with associated reasons |
| Database Error | 500 | Database error occurred while deleting chart data |
| General Error | 500 | Failed to delete chart data |

---

### **AsakaiReasonController**

#### **Create (POST /api/asakai/reasons)**
| Error Type | Status | Message |
|------------|--------|---------|
| Validation Failed | 422 | Validation error |
| User Not Authenticated | 401 | User not authenticated |
| Chart Not Found | 404 | Chart not found |
| Date Mismatch | 422 | Date must match the chart date |
| Duplicate Entry | 422 | Reason already exists for this chart and date |
| Database Error | 500 | Database error occurred while creating reason |
| General Error | 500 | Failed to create reason |

#### **Read (GET /api/asakai/reasons/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Not Found | 404 | Reason not found |
| General Error | 500 | Failed to retrieve reason data |

#### **Update (PUT /api/asakai/reasons/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Validation Failed | 422 | Validation error |
| Not Found | 404 | Reason not found |
| Date Mismatch | 422 | Date must match the chart date |
| Duplicate Entry | 422 | Reason already exists for this chart and date |
| Database Error | 500 | Database error occurred while updating reason |
| General Error | 500 | Failed to update reason |

#### **Delete (DELETE /api/asakai/reasons/{id})**
| Error Type | Status | Message |
|------------|--------|---------|
| Not Found | 404 | Reason not found |
| Database Error | 500 | Database error occurred while deleting reason |
| General Error | 500 | Failed to delete reason |

---

## 🛡️ Special Validations

### **1. Authentication Check**
Semua operasi CREATE memvalidasi user authentication:
```php
if (!Auth::check()) {
    return response()->json([
        'success' => false,
        'message' => 'User not authenticated',
        'error' => 'Please login to create data'
    ], 401);
}
```

### **2. Date Matching Validation**
Reason harus memiliki tanggal yang sama dengan chart:
```php
if ($chart->date->format('Y-m-d') !== $request->date) {
    return response()->json([
        'success' => false,
        'message' => 'Date must match the chart date',
        'error' => 'The date you provided does not match the chart date.'
    ], 422);
}
```

### **3. Cascade Delete Protection**
Chart tidak bisa dihapus jika masih memiliki reason:
```php
if ($hasReasons) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete chart with associated reasons',
        'error' => "This chart has {$reasonCount} associated reason(s).",
        'reason_count' => $reasonCount
    ], 422);
}
```

### **4. Duplicate Entry Check**
Mencegah duplikasi data untuk title+date atau chart+date:
```php
if ($exists) {
    return response()->json([
        'success' => false,
        'message' => 'Data already exists',
        'error' => 'Duplicate entry detected.'
    ], 422);
}
```

---

## 💡 Best Practices

### **Frontend Implementation**

```javascript
// Example: Handling API errors in frontend
async function createChart(data) {
  try {
    const response = await fetch('/api/asakai/charts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (!result.success) {
      // Display user-friendly error
      alert(result.error || result.message);
      
      // Log technical details for debugging (if available)
      if (result.details) {
        console.error('Technical details:', result.details);
      }
      
      // Handle validation errors
      if (result.errors) {
        Object.keys(result.errors).forEach(field => {
          console.error(`${field}: ${result.errors[field].join(', ')}`);
        });
      }
      
      return null;
    }
    
    return result.data;
  } catch (error) {
    console.error('Network error:', error);
    alert('Failed to connect to server. Please check your connection.');
    return null;
  }
}
```

### **Error Logging**

Untuk production, pastikan `APP_DEBUG=false` di `.env`:
```env
APP_DEBUG=false
```

Untuk development, set `APP_DEBUG=true`:
```env
APP_DEBUG=true
```

---

## 🎯 HTTP Status Codes

| Status Code | Meaning | When Used |
|-------------|---------|-----------|
| 200 | OK | Successful GET, PUT, DELETE |
| 201 | Created | Successful POST |
| 401 | Unauthorized | User not authenticated |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed, duplicate entry, business logic error |
| 500 | Internal Server Error | Database error, unexpected error |

---

## ✅ Benefits

1. **Better User Experience**: Clear, actionable error messages
2. **Easier Debugging**: Technical details available in debug mode
3. **Consistent Format**: All errors follow the same structure
4. **Security**: Sensitive details hidden in production
5. **Maintainability**: Specific exception handling makes code easier to maintain

---

## 🚀 Migration Status

✅ All CRUD operations updated with enhanced error handling
✅ Authentication checks added to CREATE operations
✅ Cascade delete protection implemented
✅ Debug mode support configured
✅ Ready for production use
