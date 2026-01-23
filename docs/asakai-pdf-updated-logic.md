# Asakai PDF Export - Updated Logic

## 🔄 Perubahan Logic

### ❌ **Logic LAMA (Salah):**
- Filter berdasarkan `asakai_chart_id` 
- Hanya mengambil 1 reason dari 1 chart entry
- Tidak fleksibel untuk export berdasarkan title

### ✅ **Logic BARU (Benar):**
- Filter berdasarkan `asakai_title_id` + `date_range`
- Mengambil SEMUA reasons dari semua charts yang memiliki title tersebut dalam date range
- Lebih fleksibel dan sesuai kebutuhan

---

## 📋 Parameter API (Updated)

### **Required Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `date_from` | date | Tanggal mulai (REQUIRED) | `2024-01-01` |
| `date_to` | date | Tanggal akhir (REQUIRED) | `2024-01-31` |

### **Optional Parameters:**
| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `asakai_title_id` | integer | Filter by Asakai Title ID | `1` |
| `section` | string | Filter by section/dept | `brazzing` |
| `search` | string | Search by part_no | `ABC123` |

---

## 🔍 Query Logic Baru

### Flow:
```
1. User input: asakai_title_id + date_range
                  ↓
2. Cari semua asakai_charts dengan:
   - asakai_title_id = [input]
   - date BETWEEN [date_from] AND [date_to]
                  ↓
3. Dari charts tersebut, ambil SEMUA asakai_reasons
                  ↓
4. Generate PDF dengan semua reasons yang ditemukan
```

### SQL Query yang Dihasilkan:
```sql
SELECT asakai_reasons.* 
FROM asakai_reasons
INNER JOIN asakai_charts ON asakai_reasons.asakai_chart_id = asakai_charts.id
WHERE asakai_charts.asakai_title_id = [input_title_id]
  AND asakai_charts.date BETWEEN '[date_from]' AND '[date_to]'
  AND asakai_reasons.section = '[section]' -- jika ada filter section
ORDER BY asakai_reasons.date DESC
```

---

## 📊 Tabel yang Terlibat

### 1. **asakai_titles** (Master Data)
```
id | title                  | category
---|------------------------|----------
1  | Quality Issue          | quality
2  | Safety Problem         | safety
3  | Production Delay       | production
```

### 2. **asakai_charts** (Data Harian per Title)
```
id | asakai_title_id | date       | qty
---|-----------------|------------|-----
1  | 1               | 2024-01-15 | 5
2  | 1               | 2024-01-16 | 3
3  | 2               | 2024-01-15 | 2
4  | 1               | 2024-01-17 | 4
```

### 3. **asakai_reasons** (Detail Problem per Chart)
```
id | asakai_chart_id | date       | part_no | problem      | ...
---|-----------------|------------|---------|--------------|----
1  | 1               | 2024-01-15 | ABC123  | Bocor        | ...
2  | 1               | 2024-01-15 | XYZ789  | Retak        | ...
3  | 2               | 2024-01-16 | DEF456  | Aus          | ...
4  | 4               | 2024-01-17 | GHI012  | Patah        | ...
```

---

## 💡 Contoh Penggunaan

### **Contoh 1: Export semua reasons untuk Title ID 1 dalam Januari 2024**
```
GET /api/asakai/reasons/export-pdf?asakai_title_id=1&date_from=2024-01-01&date_to=2024-01-31
```

**Hasil:**
- Mengambil chart_id: 1, 2, 4 (semua chart dengan title_id=1 di Januari)
- Mengambil reason_id: 1, 2, 3, 4 (semua reasons dari chart tersebut)
- PDF berisi 4 reasons

---

### **Contoh 2: Export semua reasons dalam date range (tanpa filter title)**
```
GET /api/asakai/reasons/export-pdf?date_from=2024-01-15&date_to=2024-01-17
```

**Hasil:**
- Mengambil SEMUA charts dalam date range 15-17 Jan
- Mengambil SEMUA reasons dari charts tersebut
- PDF berisi semua reasons dari berbagai titles

---

### **Contoh 3: Export dengan filter section**
```
GET /api/asakai/reasons/export-pdf?asakai_title_id=1&date_from=2024-01-01&date_to=2024-01-31&section=brazzing
```

**Hasil:**
- Mengambil chart dengan title_id=1 di Januari
- Filter hanya reasons dengan section='brazzing'
- PDF header DEPT: BRAZZING

---

## 🎯 Perbedaan dengan Logic Lama

| Aspek | Logic Lama ❌ | Logic Baru ✅ |
|-------|--------------|--------------|
| **Filter Utama** | `asakai_chart_id` | `asakai_title_id` + `date_range` |
| **Jumlah Data** | 1 chart = beberapa reasons | Banyak charts = banyak reasons |
| **Fleksibilitas** | Rendah (harus tahu chart_id) | Tinggi (cukup tahu title_id) |
| **Use Case** | Export 1 hari saja | Export 1 bulan/periode |
| **Parameter Wajib** | `asakai_chart_id` | `date_from` + `date_to` |

---

## 📝 Validasi

### Error jika date_from atau date_to tidak ada:
```json
{
  "success": false,
  "message": "date_from and date_to are required",
  "error": "Please provide both date_from and date_to parameters"
}
```

---

## 🔗 Relasi Database yang Digunakan

```
asakai_reasons
    ↓ (belongsTo)
asakai_charts
    ↓ (belongsTo)
asakai_titles
```

**Query menggunakan `whereHas`:**
```php
AsakaiReason::with(['asakaiChart.asakaiTitle', 'user'])
    ->whereHas('asakaiChart', function($q) use ($dateFrom, $dateTo, $request) {
        $q->whereBetween('date', [$dateFrom, $dateTo]);
        
        if ($request->has('asakai_title_id')) {
            $q->where('asakai_title_id', $request->asakai_title_id);
        }
    })
```

---

## ✨ Fitur Tambahan di PDF

PDF sekarang menerima data tambahan:
- `titleName` - Nama Asakai Title (jika filter by title_id)
- `dateFrom` - Tanggal mulai
- `dateTo` - Tanggal akhir

Bisa digunakan untuk menampilkan info di header PDF (opsional).
