# File Download Guide

## Overview

Panduan untuk mengintegrasikan download file Excel dari folder `public/prod_plan` di frontend.

---

## Opsi 1: Direct Link (Paling Sederhana)

Jika file sudah ada di folder `public`, gunakan link langsung tanpa API.

### HTML

```html
<a href="/prod_plan/ProdPlanTest1.xlsx" download>
    Download ProdPlanTest1.xlsx
</a>

<a href="/prod_plan/DailyUseTest1.xlsx" download>
    Download DailyUseTest1.xlsx
</a>
```

### JavaScript

```javascript
// Simple download
function downloadFile(filename) {
    const link = document.createElement("a");
    link.href = `/prod_plan/${filename}`;
    link.download = filename;
    link.click();
}

// Usage
downloadFile("ProdPlanTest1.xlsx");
```

### React Component

```jsx
export function DirectDownloadButton() {
    return (
        <div>
            <a
                href="/prod_plan/ProdPlanTest1.xlsx"
                download
                className="btn btn-primary"
            >
                Download ProdPlanTest1.xlsx
            </a>
            <a
                href="/prod_plan/DailyUseTest1.xlsx"
                download
                className="btn btn-primary"
            >
                Download DailyUseTest1.xlsx
            </a>
        </div>
    );
}
```

---

## Opsi 2: API Endpoint (Lebih Fleksibel & Aman)

Gunakan API endpoint untuk list dan download file dengan kontrol akses yang lebih baik.

### API Endpoints

#### 1. List Files

**GET** `/api/files/list/{folder}`

Mengambil daftar semua file yang tersedia di folder.

**Parameters:**

-   `folder` - Nama folder (saat ini hanya `prod_plan` yang tersedia)

**Example Request:**

```
GET /api/files/list/prod_plan
```

**Response (Success):**

```json
{
    "success": true,
    "data": [
        {
            "name": "ProdPlanTest1.xlsx",
            "size": 9499,
            "size_formatted": "9.28 KB",
            "modified": "2025-12-11 10:30:45",
            "download_url": "/api/files/download/prod_plan/ProdPlanTest1.xlsx"
        },
        {
            "name": "DailyUseTest1.xlsx",
            "size": 9444,
            "size_formatted": "9.22 KB",
            "modified": "2025-12-11 10:25:30",
            "download_url": "/api/files/download/prod_plan/DailyUseTest1.xlsx"
        }
    ],
    "message": "Daftar file berhasil diambil"
}
```

#### 2. Download File

**GET** `/api/files/download/{folder}/{filename}`

Download file secara langsung.

**Parameters:**

-   `folder` - Nama folder (saat ini hanya `prod_plan`)
-   `filename` - Nama file yang ingin didownload

**Example Request:**

```
GET /api/files/download/prod_plan/ProdPlanTest1.xlsx
```

**Response:**

-   File akan di-download langsung ke komputer user

**Error Response (404):**

```json
{
    "success": false,
    "message": "File tidak ditemukan",
    "data": []
}
```

---

## Frontend Implementation

### JavaScript (Vanilla)

```javascript
// List files
async function listFiles() {
    try {
        const response = await fetch("/api/files/list/prod_plan");
        const result = await response.json();

        if (result.success) {
            console.log("Available files:", result.data);
            displayFileList(result.data);
        } else {
            console.error("Error:", result.message);
        }
    } catch (error) {
        console.error("Failed to fetch files:", error);
    }
}

// Display file list
function displayFileList(files) {
    const container = document.getElementById("file-list");
    container.innerHTML = files
        .map(
            (file) => `
    <div class="file-item">
      <span>${file.name} (${file.size_formatted})</span>
      <button onclick="downloadFile('${file.name}')">Download</button>
      <small>Modified: ${file.modified}</small>
    </div>
  `
        )
        .join("");
}

// Download file
function downloadFile(filename) {
    window.location.href = `/api/files/download/prod_plan/${filename}`;
}

// Call on page load
listFiles();
```

### React Component

```jsx
import { useState, useEffect } from "react";

export function FileDownloadList() {
    const [files, setFiles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        fetchFiles();
    }, []);

    const fetchFiles = async () => {
        try {
            const response = await fetch("/api/files/list/prod_plan");
            const result = await response.json();

            if (result.success) {
                setFiles(result.data);
            } else {
                setError(result.message);
            }
        } catch (err) {
            setError("Failed to fetch files");
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = (filename) => {
        window.location.href = `/api/files/download/prod_plan/${filename}`;
    };

    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div className="file-list">
            <h3>Available Files</h3>
            {files.length === 0 ? (
                <p>No files available</p>
            ) : (
                <table className="table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Modified</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {files.map((file) => (
                            <tr key={file.name}>
                                <td>{file.name}</td>
                                <td>{file.size_formatted}</td>
                                <td>{file.modified}</td>
                                <td>
                                    <button
                                        onClick={() =>
                                            handleDownload(file.name)
                                        }
                                        className="btn btn-sm btn-primary"
                                    >
                                        Download
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
```

### Vue Component

```vue
<template>
    <div class="file-list">
        <h3>Available Files</h3>

        <div v-if="loading" class="alert alert-info">Loading...</div>
        <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
        <div v-else-if="files.length === 0" class="alert alert-warning">
            No files available
        </div>

        <table v-else class="table">
            <thead>
                <tr>
                    <th>File Name</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="file in files" :key="file.name">
                    <td>{{ file.name }}</td>
                    <td>{{ file.size_formatted }}</td>
                    <td>{{ file.modified }}</td>
                    <td>
                        <button
                            @click="downloadFile(file.name)"
                            class="btn btn-sm btn-primary"
                        >
                            Download
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    data() {
        return {
            files: [],
            loading: true,
            error: null,
        };
    },

    mounted() {
        this.fetchFiles();
    },

    methods: {
        async fetchFiles() {
            try {
                const response = await fetch("/api/files/list/prod_plan");
                const result = await response.json();

                if (result.success) {
                    this.files = result.data;
                } else {
                    this.error = result.message;
                }
            } catch (err) {
                this.error = "Failed to fetch files";
            } finally {
                this.loading = false;
            }
        },

        downloadFile(filename) {
            window.location.href = `/api/files/download/prod_plan/${filename}`;
        },
    },
};
</script>
```

---

## Security Features

✅ **Directory Traversal Prevention** - Mencegah akses ke file di luar folder yang diizinkan  
✅ **File Type Validation** - Hanya file Excel (.xlsx, .xls) dan CSV yang diizinkan  
✅ **Folder Whitelist** - Hanya folder `prod_plan` yang bisa diakses (mudah ditambah)  
✅ **File Existence Check** - Validasi file ada sebelum download

---

## Menambah Folder Baru

Jika ingin menambah folder baru untuk download, edit `FileDownloadController.php`:

```php
private const ALLOWED_FOLDERS = [
    'prod_plan' => 'public/prod_plan',
    'reports' => 'public/reports',        // Tambah folder baru
    'templates' => 'public/templates',    // Tambah folder baru
];
```

Kemudian gunakan di frontend:

```javascript
// List files dari folder reports
fetch("/api/files/list/reports");

// Download dari folder templates
window.location.href = "/api/files/download/templates/template.xlsx";
```

---

## Perbandingan Opsi

| Aspek         | Opsi 1 (Direct)  | Opsi 2 (API)        |
| ------------- | ---------------- | ------------------- |
| Kompleksitas  | Sangat Sederhana | Sedang              |
| Keamanan      | Rendah           | Tinggi              |
| Kontrol       | Minimal          | Maksimal            |
| Metadata File | Tidak            | Ya (size, modified) |
| Logging       | Tidak            | Bisa ditambah       |
| Validasi      | Tidak            | Ya                  |
| Scalability   | Terbatas         | Baik                |

**Rekomendasi:** Gunakan **Opsi 2 (API)** untuk production karena lebih aman dan fleksibel.

---

## Troubleshooting

### File tidak ditemukan

-   Pastikan file ada di folder `public/prod_plan`
-   Pastikan nama file benar (case-sensitive)
-   Pastikan file extension adalah `.xlsx`, `.xls`, atau `.csv`

### Download tidak berfungsi

-   Cek browser console untuk error messages
-   Pastikan API endpoint `/api/files/list/prod_plan` bisa diakses
-   Cek permission folder `public/prod_plan`

### CORS Error

Jika frontend di domain berbeda, tambahkan CORS middleware di `config/cors.php`:

```php
'allowed_origins' => ['*'],
'allowed_methods' => ['GET', 'POST'],
```
