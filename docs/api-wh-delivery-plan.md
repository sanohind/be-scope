# WH Delivery Plan API Documentation

## Overview
API untuk mengelola data WH Delivery Plan. Data ini berisi rencana pengiriman qty per part number dan warehouse berdasarkan tanggal.

## Database Structure

### Tabel: `wh_delivery_plan`

| Column        | Type    | Description                                    |
|---------------|---------|------------------------------------------------|
| id            | integer | Primary key                                    |
| partno        | string  | Part number                                    |
| warehouse     | string  | Warehouse code (previously divisi)             |
| qty_delivery  | integer | Quantity delivery                              |
| delivery_date | date    | Tanggal rencana delivery (YYYY-MM-DD)          |
| created_at    | timestamp | Waktu pembuatan record                       |
| updated_at    | timestamp | Waktu update terakhir record                   |

## API Endpoints

### 1. Import Data dari Excel
**POST** `/api/wh-delivery-plan/import`

**Request:**
- `file`: Excel file (.xlsx, .xls, .csv)

**Format Excel:**
- Row 1-3: Header (akan di-skip)
- Row 4+: Data
- Column B: `partno`
- Column C: `warehouse`
- Column D: `year` (ex: 2025)
- Column E: `period` (Month 1-12)
- Column F...: Tanggal 1, 2, dst.

**Proses Import:**
- Sistem akan membaca `year` dan `period` untuk menentukan bulan.
- Kolom F adalah tanggal 1, G adalah tanggal 2, dst.
- Sistem akan menggenerate tanggal `delivery_date` berdasarkan `year`, `period`, dan kolom hari.
- Jika kombinasi `partno`, `warehouse`, dan `delivery_date` sudah ada, data akan diupdate. Jika belum, data baru dibuat.

**Response:**
```json
{
  "success": true,
  "message": "Import berhasil",
  "data": {
    "inserted": 30,
    "updated": 0,
    "skipped": 0,
    "errors": []
  }
}
```

---

### 2. Create/Insert Data (Manual/JSON)
**POST** `/api/wh-delivery-plan/store`

**Request Body:**
```json
{
  "data": [
    {
      "partno": "PART-001",
      "warehouse": "WH01",
      "qty_delivery": 500,
      "delivery_date": "2025-01-01"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil disimpan",
  "data": {
    "inserted": 1,
    "updated": 0
  }
}
```

---

### 3. Get Data (List)
**GET** `/api/wh-delivery-plan`

Returns data grouped by `partno` and `warehouse`, similar to a horizontal/pivot view.

**Query Parameters:**
- `partno` (optional): Exact match part number
- `warehouse` (optional): Exact match warehouse code
- `delivery_date` (optional): Exact match date (YYYY-MM-DD)
- `delivery_date_from` (optional): Start date filter
- `delivery_date_to` (optional): End date filter
- `per_page` (optional): Items per page (default: 50)
- `page` (optional): Page number

**Response:**
Data dikelompokkan agar mudah ditampilkan dalam tabel horizontal (tanggal 1-31 sebagai kolom).

```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": {
    "current_page": 1,
    "data": [
      {
        "partno": "PART-001",
        "warehouse": "WH01",
        "year": 2025,
        "period": 1,
        "days": {
          "1": 500,
          "2": 200
        }
      }
    ],
    "first_page_url": "...",
    "total": 1
  }
}
```
*Note: `days` berisi key tanggal (1-31) dan value `qty_delivery`.*

---

### 4. Get Single Record
**GET** `/api/wh-delivery-plan/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": {
    "id": 1,
    "partno": "PART-001",
    "warehouse": "WH01",
    "qty_delivery": 500,
    "delivery_date": "2025-01-01",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

### 5. Update Record
**PUT/PATCH** `/api/wh-delivery-plan/{id}`

**Request Body** (Partial update allowed):
```json
{
  "qty_delivery": 600
}
```

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil diupdate",
  "data": { ... }
}
```

---

### 6. Delete Record
**DELETE** `/api/wh-delivery-plan/{id}`

---

### 7. Delete Multiple Records
**POST** `/api/wh-delivery-plan/delete-multiple`

**Request Body:**
```json
{
  "ids": [1, 2, 5]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil dihapus",
  "data": {
    "deleted": 3
  }
}
```
