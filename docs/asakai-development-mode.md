# Asakai Board API - Development Mode Configuration

## 🔧 Development Changes

Untuk memudahkan development dan testing, authentication requirement telah dinonaktifkan sementara dan menggunakan dummy user.

---

## ⚙️ Changes Made

### **1. Authentication Check Removed**
Authentication check pada method `store()` telah dihapus dari:
- `AsakaiChartController`
- `AsakaiReasonController`

**Before:**
```php
// Check if user is authenticated
if (!Auth::check()) {
    return response()->json([
        'success' => false,
        'message' => 'User not authenticated',
        'error' => 'Please login to create data'
    ], 401);
}

$chart = AsakaiChart::create([
    'user_id' => Auth::id(),
    // ...
]);
```

**After:**
```php
$chart = AsakaiChart::create([
    'user_id' => 1, // Dummy user for development
    // ...
]);
```

---

## 👤 Dummy User Configuration

### **Default User ID: 1**

Semua data yang dibuat akan menggunakan `user_id = 1` sebagai default.

**Pastikan user dengan ID 1 ada di database:**

```sql
-- Check if user exists
SELECT * FROM users WHERE id = 1;

-- If not exists, create dummy user
INSERT INTO users (id, name, email, password, created_at, updated_at)
VALUES (1, 'Development User', 'dev@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW());
```

**Note:** Password hash di atas adalah untuk password `password`

---

## 📋 API Usage (Development Mode)

### **Create Chart - No Authentication Required**

```bash
POST /api/asakai/charts
Content-Type: application/json

{
  "asakai_title_id": 1,
  "date": "2026-01-15",
  "qty": 5
}
```

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
    "user": "Development User",
    "user_id": 1,
    "created_at": "2026-01-15 15:25:00"
  }
}
```

### **Create Reason - No Authentication Required**

```bash
POST /api/asakai/reasons
Content-Type: application/json

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

**Response:**
```json
{
  "success": true,
  "message": "Reason created successfully",
  "data": {
    "id": 1,
    "asakai_chart_id": 1,
    "date": "2026-01-15",
    "user": "Development User",
    "user_id": 1,
    ...
  }
}
```

---

## ⚠️ Important Notes

### **1. Development Only**
Konfigurasi ini **hanya untuk development**. Sebelum production:
- ✅ Aktifkan kembali authentication check
- ✅ Gunakan `Auth::id()` untuk mendapatkan user yang sebenarnya
- ✅ Tambahkan middleware authentication di routes

### **2. Data Tracking**
Semua data yang dibuat akan tercatat dengan `user_id = 1`, sehingga:
- ❌ Tidak bisa tracking user yang sebenarnya membuat data
- ❌ Audit trail tidak akurat
- ✅ Mudah untuk testing dan development

### **3. Migration to Production**

Ketika siap untuk production, ubah kembali:

**AsakaiChartController.php:**
```php
// Tambahkan kembali authentication check
if (!Auth::check()) {
    return response()->json([
        'success' => false,
        'message' => 'User not authenticated',
        'error' => 'Please login to create chart data'
    ], 401);
}

// Gunakan Auth::id()
$chart = AsakaiChart::create([
    'user_id' => Auth::id(), // Real user
    // ...
]);
```

**AsakaiReasonController.php:**
```php
// Tambahkan kembali authentication check
if (!Auth::check()) {
    return response()->json([
        'success' => false,
        'message' => 'User not authenticated',
        'error' => 'Please login to create reason data'
    ], 401);
}

// Gunakan Auth::id()
$reason = AsakaiReason::create([
    'user_id' => Auth::id(), // Real user
    // ...
]);
```

---

## 🔐 Production Checklist

Sebelum deploy ke production:

- [ ] Restore authentication check di `AsakaiChartController::store()`
- [ ] Restore authentication check di `AsakaiReasonController::store()`
- [ ] Change `user_id => 1` to `user_id => Auth::id()`
- [ ] Add authentication middleware to routes
- [ ] Test authentication flow
- [ ] Update API documentation

---

## 🧪 Testing

### **Without Authentication (Current)**
```bash
# Works without token
curl -X POST http://localhost:8000/api/asakai/charts \
  -H "Content-Type: application/json" \
  -d '{
    "asakai_title_id": 1,
    "date": "2026-01-15",
    "qty": 5
  }'
```

### **With Authentication (Production)**
```bash
# Requires token
curl -X POST http://localhost:8000/api/asakai/charts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "asakai_title_id": 1,
    "date": "2026-01-15",
    "qty": 5
  }'
```

---

## 📝 Summary

| Aspect | Development (Current) | Production (Future) |
|--------|----------------------|---------------------|
| Authentication | ❌ Not Required | ✅ Required |
| User ID | Fixed (1) | Dynamic (Auth::id()) |
| Token | ❌ Not Needed | ✅ Required |
| User Tracking | ❌ Not Accurate | ✅ Accurate |
| Testing | ✅ Easy | ⚠️ Requires Auth Setup |

---

## ✅ Current Status

✅ Authentication check removed for development
✅ Dummy user ID (1) configured
✅ API ready for testing without authentication
✅ All CRUD operations work without login
⚠️ Remember to restore authentication before production!
