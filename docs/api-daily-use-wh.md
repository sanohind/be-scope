# DailyUseWh API Documentation

## Overview
API untuk mengelola data Daily Use Warehouse dengan format berbasis periode (bulanan).

## Database Structure

### Tabel: `daily_use_wh`

| Column    | Type    | Description                                    |
|-----------|---------|------------------------------------------------|
| id        | integer | Primary key                                     |
| partno    | string  | Part number                                     |
| warehouse | string  | Warehouse code                                  |
| year      | integer | Tahun (2000-2100)                              |
| period    | integer | Periode/bulan (1-12)                           |
| qty       | integer | Quantity yang berlaku untuk seluruh bulan      |

**Catatan Penting**: 
- Satu record mewakili qty untuk **seluruh hari dalam bulan tersebut**
- Contoh: `year=2026, period=1, qty=100` berarti qty 100 berlaku untuk semua tanggal di Januari 2026 (1-31 Januari)

## API Endpoints

### 1. Import Data dari Excel
**POST** `/api/daily-use-wh/import`

**Request:**
- `file`: Excel file (.xlsx, .xls, .csv)

**Format Excel:**
- Row 1-3: Header (akan di-skip)
- Row 4+: Data
- Column B: Part Number
- Column C: Warehouse
- Column D: Year
- Column E: Period (1-12)
- Column F: Qty

**Response:**
```json
{
  "success": true,
  "message": "Import berhasil",
  "data": {
    "inserted": 10,
    "updated": 5,
    "skipped": 2,
    "errors": []
  }
}
```

---

### 2. Create/Update Data
**POST** `/api/daily-use-wh`

**Request Body:**
```json
{
  "data": [
    {
      "partno": "PART001",
      "warehouse": "WH01",
      "year": 2026,
      "period": 1,
      "qty": 100
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
**GET** `/api/daily-use-wh`

**Query Parameters:**
- `partno` (optional): Filter by part number (partial match)
- `warehouse` (optional): Filter by warehouse code
- `year` (optional): Filter by year
- `period` (optional): Filter by period/month
- `per_page` (optional): Items per page (default: 50, max: 100)
- `page` (optional): Page number

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "partno": "PART001",
        "warehouse": "WH01",
        "year": 2026,
        "period": 1,
        "qty": 100,
        "created_at": "2026-01-13T13:48:20.000000Z",
        "updated_at": "2026-01-13T13:48:20.000000Z"
      }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 1,
    "per_page": 50,
    "to": 1,
    "total": 1
  }
}
```

---

### 4. Get Single Record
**GET** `/api/daily-use-wh/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil diambil",
  "data": {
    "id": 1,
    "partno": "PART001",
    "warehouse": "WH01",
    "year": 2026,
    "period": 1,
    "qty": 100,
    "created_at": "2026-01-13T13:48:20.000000Z",
    "updated_at": "2026-01-13T13:48:20.000000Z"
  }
}
```

---

### 5. Update Record
**PUT/PATCH** `/api/daily-use-wh/{id}`

**Request Body (partial update allowed):**
```json
{
  "qty": 150
}
```

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil diupdate",
  "data": {
    "id": 1,
    "partno": "PART001",
    "warehouse": "WH01",
    "year": 2026,
    "period": 1,
    "qty": 150,
    "created_at": "2026-01-13T13:48:20.000000Z",
    "updated_at": "2026-01-13T13:50:00.000000Z"
  }
}
```

---

### 6. Delete Record
**DELETE** `/api/daily-use-wh/{id}`

**Response:**
```json
{
  "success": true,
  "message": "Data berhasil dihapus",
  "data": []
}
```

---

### 7. Delete Multiple Records
**POST** `/api/daily-use-wh/delete-multiple`

**Request Body:**
```json
{
  "ids": [1, 2, 3]
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

---

## Migration Notes

### Perubahan Struktur Database

**Sebelum:**
- `partno` (string)
- `warehouse` (string)
- `plan_date` (date) - tanggal spesifik
- `daily_use` (integer) - qty untuk tanggal tersebut

**Sesudah:**
- `partno` (string)
- `warehouse` (string)
- `year` (integer) - tahun
- `period` (integer) - bulan (1-12)
- `qty` (integer) - qty untuk seluruh bulan

### Keuntungan Format Baru:
1. **Lebih sederhana**: Satu record per bulan, bukan 28-31 record
2. **Lebih efisien**: Mengurangi jumlah row di database
3. **Lebih mudah maintain**: Update qty untuk satu bulan hanya perlu update 1 record
4. **Konsisten**: Qty yang sama untuk semua hari dalam bulan

### Cara Menggunakan Data:
Jika Anda perlu mendapatkan qty untuk tanggal spesifik:
- Query berdasarkan `year` dan `period`
- Qty yang didapat berlaku untuk **semua tanggal** di bulan tersebut

---

## Min/Max Stock API (New)

Fitur baru untuk mengelola Minimum dan Maximum Stock Level per Warehouse per Bulan.

### Database Structure: `daily_use_wh_min_max`

| Column      | Type    | Description                                             |
|-------------|---------|---------------------------------------------------------|
| id          | integer | Primary key                                             |
| warehouse   | string  | Warehouse code                                          |
| year        | integer | Tahun (2000-2100)                                       |
| period      | integer | Periode/bulan (1-12)                                    |
| min_onhand  | integer | Minimum stock level                                     |
| max_onhand  | integer | Maximum stock level                                     |

**Constraint**:
- Unique combination of `warehouse` + `year` + `period`
- `min_onhand` tidak boleh lebih besar dari `max_onhand`

### API Endpoints

### 8. Input/Update Min-Max Stock
Menginput atau mengupdate data limit stock. Jika data sudah ada untuk kombinasi warehouse+year+period, akan diupdate. Jika belum, akan dibuat baru.

**POST** `/api/daily-use-wh/min-max`

**Request Body:**
```json
{
  "warehouse": "WH01",
  "year": 2026,
  "period": 1,
  "min_onhand": 100,
  "max_onhand": 500
}
```

**Validasi:**
- `warehouse`: required, string, max 50 chars
- `year`: required, integer, 2000-2100
- `period`: required, integer, 1-12
- `min_onhand`: required, integer, min 0
- `max_onhand`: required, integer, min 0
- Logika: `min_onhand` <= `max_onhand`

**Response:**
```json
{
  "success": true,
  "message": "Data Min/Max berhasil disimpan",
  "data": {
    "warehouse": "WH01",
    "year": 2026,
    "period": 1,
    "min_onhand": 100,
    "max_onhand": 500,
    "updated_at": "...",
    "created_at": "...",
    "id": 1
  }
}
```

### 9. Get Min-Max Data
Mengambil daftar limit stock dengan filter.

**GET** `/api/daily-use-wh/min-max`

**Query Parameters:**
- `warehouse` (optional): Filter by warehouse code
- `year` (optional): Filter by year
- `period` (optional): Filter by period/month
- `per_page` (optional): Items per page (default: 50, max: 100)

**Response:**
```json
{
  "success": true,
  "message": "Data Min/Max berhasil diambil",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "warehouse": "WH01",
        "year": 2026,
        "period": 1,
        "min_onhand": 100,
        "max_onhand": 500,
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 1,
    "per_page": 50,
    "to": 1,
    "total": 1
  }
}
```
