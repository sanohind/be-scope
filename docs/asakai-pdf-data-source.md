# Asakai PDF - Sumber Data

## Overview
Dokumen ini menjelaskan dari mana saja logic print PDF Asakai Reasons mengambil data.

---

## 🗂️ Sumber Data Utama

### 1. **Database Table: `asakai_reasons`**
Tabel utama yang menyimpan semua data reason/problem yang ditampilkan di PDF.

**Kolom yang digunakan:**
- `id` - ID unik
- `asakai_chart_id` - Foreign key ke tabel asakai_charts
- `date` - Tanggal kejadian
- `part_no` - Nomor part
- `part_name` - Nama part
- `problem` - Deskripsi masalah
- `qty` - Kuantitas
- `section` - Departemen (brazzing, chassis, nylon, subcon, passthrough, no_section)
- `line` - Line produksi
- `penyebab` - Analisa penyebab
- `perbaikan` - Tindakan perbaikan
- `user_id` - User yang membuat record

**Model:** `App\Models\AsakaiReason`

---

## 📍 Flow Pengambilan Data

### Step 1: API Request
```
GET /api/asakai/reasons/export-pdf
```

**Parameter yang bisa digunakan:**
- `period` - daily, monthly, yearly (default: monthly)
- `date_from` - Tanggal mulai filter
- `date_to` - Tanggal akhir filter
- `asakai_chart_id` - Filter berdasarkan chart ID tertentu
- `section` - Filter berdasarkan section/dept
- `search` - Pencarian berdasarkan part_no

### Step 2: Controller Processing
**File:** `app/Http/Controllers/Api/AsakaiReasonController.php`
**Method:** `exportPdf(Request $request)`

```php
public function exportPdf(Request $request)
{
    // 1. Ambil parameter period (default: monthly)
    $period = $request->get('period', 'monthly');
    
    // 2. Buat query dari model AsakaiReason
    $query = AsakaiReason::with(['asakaiChart.asakaiTitle', 'user']);
    
    // 3. Filter berdasarkan asakai_chart_id (jika ada)
    if ($request->has('asakai_chart_id')) {
        $query->where('asakai_chart_id', $request->asakai_chart_id);
    }
    
    // 4. Filter berdasarkan period dan date
    $dateFrom = $request->get('date_from');
    $dateTo = $request->get('date_to');
    
    if ($period === 'daily') {
        // Filter by month from date_from
        if ($dateFrom) {
            $year = date('Y', strtotime($dateFrom));
            $month = date('m', strtotime($dateFrom));
            $query->whereRaw("YEAR(date) = ?", [$year])
                  ->whereRaw("MONTH(date) = ?", [$month]);
        }
    } elseif ($period === 'monthly') {
        // Filter by year from date_from
        if ($dateFrom) {
            $year = date('Y', strtotime($dateFrom));
            $query->whereRaw("YEAR(date) = ?", [$year]);
        }
    } elseif ($period === 'yearly') {
        // Filter by date range
        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }
    }
    
    // 5. Filter berdasarkan section (jika ada)
    if ($request->has('section')) {
        $query->where('section', $request->section);
    }
    
    // 6. Search berdasarkan part_no (jika ada)
    if ($request->has('search')) {
        $query->where('part_no', 'like', '%' . $request->search . '%');
    }
    
    // 7. Ambil data dari database
    $reasons = $query->orderBy('date', 'desc')->get();
    
    // 8. Tentukan nilai DEPT dari parameter section
    $dept = $request->has('section') ? strtoupper($request->section) : '';
    
    // 9. Tentukan BULAN dan TAHUN untuk header
    $month = date('F'); // Default: bulan sekarang
    $year = date('Y');  // Default: tahun sekarang
    
    // Override jika ada date_from
    if ($dateFrom) {
        $month = date('F', strtotime($dateFrom));
        $year = date('Y', strtotime($dateFrom));
    }
    
    // 10. Generate PDF
    $pdf = Pdf::loadView('pdf.asakai_reasons', [
        'reasons' => $reasons,
        'month' => $month,
        'year' => $year,
        'dept' => $dept
    ]);
    
    return $pdf->download('asakai_reasons.pdf');
}
```

---

## 📊 Mapping Data ke PDF

### Header Section (Top of Each Page)

| Field di PDF | Sumber Data | Keterangan |
|-------------|-------------|------------|
| **DEPT** | Parameter `section` (uppercase) | Jika tidak ada parameter, kosong |
| **BULAN** | Dari `date_from` parameter | Format: "January", "February", dll |
| **TAHUN** | Dari `date_from` parameter | Format: "2024", "2025", dll |

### Data Table Rows

| Field di PDF | Kolom Database | Table | Keterangan |
|-------------|----------------|-------|------------|
| **NO** | - | - | Sequential number (1, 2, 3, ...) |
| **TANGGAL** | `date` | `asakai_reasons` | Format: d-M-y (contoh: 15-Jan-24) |
| **Part No** | `part_no` | `asakai_reasons` | Nomor part |
| **Part Name** | `part_name` | `asakai_reasons` | Nama part |
| **Problem** | `problem` | `asakai_reasons` | Deskripsi masalah |
| **Qty** | `qty` | `asakai_reasons` | Kuantitas |
| **Dept** | `section` | `asakai_reasons` | Section/Department |
| **Line** | `line` | `asakai_reasons` | Line produksi |
| **ANALISA PENYEBAB** | `penyebab` | `asakai_reasons` | Analisa penyebab |
| **PERBAIKAN** | `perbaikan` | `asakai_reasons` | Tindakan perbaikan |
| **Nama Proses** | `part_name` | `asakai_reasons` | Saat ini menggunakan part_name |

### Fields yang TIDAK ada di Database (Kosong di PDF)

| Field di PDF | Status |
|-------------|--------|
| **MONITORING PROBLEM C/M & AUDIT SCHEDULE** | Kosong (grid 3 bulan × 31 hari) |
| **RESULT** | Kosong |
| **CONCERN FEEDBACK** | Kosong |
| **MANAGER** | Kosong |

---

## 🔗 Relasi Database

```
asakai_reasons
├── belongsTo: asakai_charts (via asakai_chart_id)
│   └── belongsTo: asakai_titles (via asakai_title_id)
└── belongsTo: users (via user_id)
```

**Eager Loading:**
```php
$query = AsakaiReason::with(['asakaiChart.asakaiTitle', 'user']);
```

Meskipun relasi di-load, data dari `asakai_charts`, `asakai_titles`, dan `users` **TIDAK** ditampilkan di PDF saat ini. Data ini hanya digunakan untuk validasi dan informasi tambahan di API response lainnya.

---

## 📝 Contoh Query SQL yang Dihasilkan

### Contoh 1: Filter by Chart ID
```sql
SELECT * FROM asakai_reasons 
WHERE asakai_chart_id = 1 
ORDER BY date DESC
```

### Contoh 2: Filter by Section + Monthly Period
```sql
SELECT * FROM asakai_reasons 
WHERE section = 'brazzing' 
  AND YEAR(date) = 2024 
ORDER BY date DESC
```

### Contoh 3: Filter by Chart ID + Daily Period
```sql
SELECT * FROM asakai_reasons 
WHERE asakai_chart_id = 1 
  AND YEAR(date) = 2024 
  AND MONTH(date) = 1 
ORDER BY date DESC
```

---

## 🎯 Kesimpulan

### Data BERASAL dari:
1. ✅ **Database Table `asakai_reasons`** - Sumber data utama
2. ✅ **Request Parameters** - Untuk filtering dan header info
3. ✅ **PHP Date Functions** - Untuk format tanggal dan default values

### Data TIDAK berasal dari:
- ❌ Tabel `asakai_charts` (hanya untuk relasi, tidak ditampilkan di PDF)
- ❌ Tabel `asakai_titles` (hanya untuk relasi, tidak ditampilkan di PDF)
- ❌ Tabel `users` (hanya untuk relasi, tidak ditampilkan di PDF)
- ❌ Hardcoded values (semua data dinamis dari database)

### Pagination Logic:
- **Rows per page:** 5 (hardcoded di Blade template)
- **Total pages:** Dihitung otomatis: `ceil(total_data / 5)`
- **Empty rows:** Ditampilkan jika data < 5 per halaman
