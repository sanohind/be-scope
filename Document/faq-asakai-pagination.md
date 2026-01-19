# FAQ: Mengapa Hanya 10 Hari yang Ditampilkan?

## ❓ Pertanyaan
Ketika saya request data 1 bulan penuh (31 hari), mengapa yang ditampilkan hanya 10 hari?

## ✅ Jawaban
Data **31 hari sudah ada**, tapi karena ada **pagination**, hanya 10 item yang ditampilkan per halaman.

Lihat di response:
```json
"pagination": {
    "current_page": 1,    // Halaman saat ini
    "total": 31,          // Total 31 hari
    "per_page": 10,       // Hanya 10 per halaman
    "last_page": 4        // Ada 4 halaman
}
```

## 🎯 Solusi: 3 Cara Mendapatkan Data 1 Bulan Penuh

### **Cara 1: Gunakan `/charts/data` (RECOMMENDED untuk Chart)**
```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01
```

**Keuntungan:**
- ✅ Langsung return **semua 31 hari**
- ✅ Tidak ada pagination
- ✅ Perfect untuk chart/graph
- ✅ Perfect untuk calendar view

**Response:**
```json
{
  "success": true,
  "data": [
    // 31 items langsung
  ],
  "filter_metadata": {
    "total_dates": 31,
    "dates_with_data": 2,
    "dates_without_data": 29
  }
}
```

---

### **Cara 2: Tambahkan `per_page` Parameter**
```bash
GET /api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=31
```

**Keuntungan:**
- ✅ Semua data dalam 1 halaman
- ✅ Masih ada pagination info
- ✅ Flexible, bisa adjust per_page

**Response:**
```json
{
  "success": true,
  "data": [
    // 31 items
  ],
  "pagination": {
    "current_page": 1,
    "total": 31,
    "per_page": 31,
    "last_page": 1    // Hanya 1 halaman
  }
}
```

---

### **Cara 3: Loop Semua Halaman (untuk Table dengan Pagination)**
```javascript
// Pseudocode
let allData = [];
let currentPage = 1;
let lastPage = 1;

do {
  const response = await fetch(`/api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&page=${currentPage}`);
  const data = await response.json();
  
  allData = [...allData, ...data.data];
  lastPage = data.pagination.last_page;
  currentPage++;
} while (currentPage <= lastPage);

// allData sekarang berisi 31 items
```

---

## 📊 Comparison

| Method | Items Returned | Pagination | Use Case |
|--------|----------------|------------|----------|
| `/charts` (default) | 10 | ✅ Yes (4 pages) | Table dengan pagination |
| `/charts/data` | 31 | ❌ No | **Chart/Graph/Calendar** |
| `/charts?per_page=31` | 31 | ✅ Yes (1 page) | Table tanpa pagination |

---

## 💡 Rekomendasi

### Untuk **Chart/Graph Visualization**:
```bash
GET /api/asakai/charts/data?asakai_title_id=1&period=daily&date_from=2026-01-01
```
→ Langsung dapat 31 hari, tidak perlu handle pagination

### Untuk **Table dengan Pagination**:
```bash
GET /api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=10
```
→ Gunakan pagination untuk UX yang lebih baik

### Untuk **Export/Download All Data**:
```bash
GET /api/asakai/charts?asakai_title_id=1&period=daily&date_from=2026-01-01&per_page=100
```
→ Set per_page besar untuk mendapatkan semua data sekaligus

---

## 🧪 Test Results

```
1. /charts (default)
   Items returned: 10 / 31
   Pages: 4 pages

2. /charts/data (no pagination)
   Items returned: 31 items
   All data in one response: YES

3. /charts?per_page=31
   Items returned: 31 / 31
   Pages: 1 page(s)
```

---

## 📝 Summary

**Kenapa hanya 10?** → Karena pagination default `per_page=10`

**Solusi tercepat?** → Gunakan `/charts/data` untuk mendapatkan semua data sekaligus

**Untuk production?** → Pilih endpoint sesuai use case (chart vs table)
