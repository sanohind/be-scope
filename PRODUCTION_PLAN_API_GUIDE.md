# Production Plan API Guide

## Base URL

```
/api/production-plan
```

## Endpoints

### 1. Import Data dari File (Excel/CSV)

**POST** `/api/production-plan/import`

Upload file Excel atau CSV untuk import data production plan.

**Request:**

```
Content-Type: multipart/form-data

file: <file.xlsx atau file.csv>
```

**Required Columns in File:**

-   `partno` - Nomor part
-   `divisi` - Divisi/departemen
-   `qty_plan` - Jumlah rencana produksi
-   `plan_date` - Tanggal rencana (format: DD/MM/YYYY, YYYY-MM-DD, atau Excel numeric)

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "inserted": 10,
        "updated": 5
    },
    "message": "Import berhasil"
}
```

**Response (Error):**

```json
{
    "success": false,
    "message": "Kolom partno tidak ditemukan pada header",
    "data": []
}
```

---

### 2. Create/Update Data via JSON

**POST** `/api/production-plan/store`

Menyimpan data production plan melalui request body (untuk testing atau direct API call).

**Request Body:**

```json
{
    "data": [
        {
            "partno": "PART001",
            "divisi": "Assembly",
            "qty_plan": 100,
            "plan_date": "2025-12-15"
        },
        {
            "partno": "PART002",
            "divisi": "Welding",
            "qty_plan": 50,
            "plan_date": "2025-12-15"
        }
    ]
}
```

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "inserted": 1,
        "updated": 1
    },
    "message": "Data berhasil disimpan"
}
```

---

### 3. Get All Data dengan Filter

**GET** `/api/production-plan`

Mengambil semua data production plan dengan pagination dan filter opsional.

**Query Parameters:**

-   `plan_date` (optional) - Filter berdasarkan tanggal (format: YYYY-MM-DD)
-   `partno` (optional) - Filter berdasarkan nomor part
-   `divisi` (optional) - Filter berdasarkan divisi
-   `per_page` (optional) - Jumlah data per halaman (default: 50, min: 10, max: 100)

**Example Request:**

```
GET /api/production-plan?plan_date=2025-12-15&divisi=Assembly&per_page=20
```

**Response:**

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "partno": "PART001",
                "divisi": "Assembly",
                "qty_plan": 100,
                "plan_date": "2025-12-15",
                "created_at": "2025-12-11T09:20:00.000000Z",
                "updated_at": "2025-12-11T09:20:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost/api/production-plan?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost/api/production-plan?page=1",
        "links": [],
        "next_page_url": null,
        "path": "http://localhost/api/production-plan",
        "per_page": 20,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    },
    "message": "Data berhasil diambil"
}
```

---

### 4. Get Single Record by ID

**GET** `/api/production-plan/{id}`

Mengambil data production plan berdasarkan ID.

**Example Request:**

```
GET /api/production-plan/1
```

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "partno": "PART001",
        "divisi": "Assembly",
        "qty_plan": 100,
        "plan_date": "2025-12-15",
        "created_at": "2025-12-11T09:20:00.000000Z",
        "updated_at": "2025-12-11T09:20:00.000000Z"
    },
    "message": "Data berhasil diambil"
}
```

**Response (Not Found):**

```json
{
    "success": false,
    "message": "Data tidak ditemukan",
    "data": []
}
```

---

### 5. Update Record by ID

**PUT** `/api/production-plan/{id}`

Update data production plan. Bisa partial update (hanya field yang diubah).

**Request Body (Partial Update):**

```json
{
    "qty_plan": 150
}
```

**Request Body (Full Update):**

```json
{
    "partno": "PART001",
    "divisi": "Assembly",
    "qty_plan": 150,
    "plan_date": "2025-12-20"
}
```

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "partno": "PART001",
        "divisi": "Assembly",
        "qty_plan": 150,
        "plan_date": "2025-12-20",
        "created_at": "2025-12-11T09:20:00.000000Z",
        "updated_at": "2025-12-11T10:30:00.000000Z"
    },
    "message": "Data berhasil diupdate"
}
```

---

### 6. Delete Single Record

**DELETE** `/api/production-plan/{id}`

Menghapus satu record production plan.

**Example Request:**

```
DELETE /api/production-plan/1
```

**Response (Success):**

```json
{
    "success": true,
    "data": [],
    "message": "Data berhasil dihapus"
}
```

---

### 7. Delete Multiple Records

**POST** `/api/production-plan/delete-multiple`

Menghapus multiple records sekaligus.

**Request Body:**

```json
{
    "ids": [1, 2, 3, 4, 5]
}
```

**Response (Success):**

```json
{
    "success": true,
    "data": {
        "deleted": 5
    },
    "message": "Data berhasil dihapus"
}
```

---

## Data Attributes

| Attribute   | Type    | Description             | Validation                   |
| ----------- | ------- | ----------------------- | ---------------------------- |
| `partno`    | string  | Nomor part              | Required, max 100 chars      |
| `divisi`    | string  | Divisi/departemen       | Required, max 100 chars      |
| `qty_plan`  | integer | Jumlah rencana produksi | Required, min 0              |
| `plan_date` | date    | Tanggal rencana         | Required, format: YYYY-MM-DD |

---

## Duplicate Handling

Sistem akan otomatis mendeteksi duplikat berdasarkan kombinasi:

-   `partno` + `divisi` + `plan_date`

Jika data dengan kombinasi yang sama sudah ada:

-   **Import/Store**: Data akan di-**update** (qty_plan akan diganti)
-   Response akan menunjukkan jumlah `inserted` dan `updated`

---

## Error Handling

### Validation Errors (422)

```json
{
    "success": false,
    "message": "Validasi gagal",
    "data": {
        "partno": ["The partno field is required."],
        "qty_plan": ["The qty_plan must be at least 0."]
    }
}
```

### Not Found (404)

```json
{
    "success": false,
    "message": "Data tidak ditemukan",
    "data": []
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Gagal menyimpan data: [error details]",
    "data": []
}
```

---

## Example Frontend Usage

### Using Fetch API

**Get All Data:**

```javascript
fetch("/api/production-plan?divisi=Assembly&per_page=20")
    .then((res) => res.json())
    .then((data) => console.log(data.data));
```

**Create Data:**

```javascript
fetch("/api/production-plan/store", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        data: [
            {
                partno: "PART001",
                divisi: "Assembly",
                qty_plan: 100,
                plan_date: "2025-12-15",
            },
        ],
    }),
})
    .then((res) => res.json())
    .then((data) => console.log(data));
```

**Update Data:**

```javascript
fetch("/api/production-plan/1", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        qty_plan: 150,
    }),
})
    .then((res) => res.json())
    .then((data) => console.log(data));
```

**Delete Data:**

```javascript
fetch("/api/production-plan/1", {
    method: "DELETE",
})
    .then((res) => res.json())
    .then((data) => console.log(data));
```

**Import File:**

```javascript
const formData = new FormData();
formData.append("file", fileInput.files[0]);

fetch("/api/production-plan/import", {
    method: "POST",
    body: formData,
})
    .then((res) => res.json())
    .then((data) => console.log(data));
```

---

## Notes

-   Semua response mengikuti format standard dengan `success`, `data`, dan `message`
-   Date format yang diterima: YYYY-MM-DD (ISO format)
-   Pagination default per_page adalah 50 records
-   Semua operasi menggunakan database transaction untuk data integrity
