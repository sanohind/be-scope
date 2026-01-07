# Dashboard 4 API - Panduan Filter Parameter

## 📋 Ringkasan

Dashboard 4 API menyediakan berbagai endpoint untuk analisis Sales & Shipment dengan sistem filter yang konsisten dan fleksibel. Semua endpoint mendukung filter berdasarkan periode waktu dan parameter tambahan lainnya.

---

## 🎯 Parameter Filter Utama

### 1. **Period (Periode Grouping)**

Parameter untuk menentukan bagaimana data akan dikelompokkan.

| Parameter | Nilai | Default | Keterangan |
|-----------|-------|---------|------------|
| `period` atau `group_by` | `daily`, `monthly`, `yearly` | `monthly` | Menentukan granularity data |

**Logika:**
- **`daily`**: Data dikelompokkan per hari (format: `YYYY-MM-DD`)
- **`monthly`**: Data dikelompokkan per bulan (format: `YYYY-MM`)
- **`yearly`**: Data dikelompokkan per tahun (format: `YYYY`)

**Contoh:**
```bash
# Data per hari
GET /api/dashboard/sales/revenue-trend?period=daily

# Data per bulan
GET /api/dashboard/sales/revenue-trend?period=monthly

# Data per tahun
GET /api/dashboard/sales/revenue-trend?period=yearly
```

---

### 2. **Date Range (Rentang Tanggal)**

Parameter untuk membatasi data berdasarkan rentang tanggal.

| Parameter | Format | Default | Keterangan |
|-----------|--------|---------|------------|
| `date_from` | `YYYY-MM-DD` | Tergantung period | Tanggal mulai |
| `date_to` | `YYYY-MM-DD` | Hari ini | Tanggal akhir |

**Default Berdasarkan Period:**

| Period | Default `date_from` | Default `date_to` | Range |
|--------|---------------------|-------------------|-------|
| `daily` | 7 hari yang lalu | Hari ini | 7 hari |
| `monthly` | Awal bulan ini | Hari ini | Bulan ini |
| `yearly` | Awal tahun ini | Hari ini | Tahun ini |

**Logika:**
1. Jika user **tidak mengirim** `date_from` dan `date_to`, sistem menggunakan default berdasarkan `period`
2. Jika user **mengirim** `date_from` dan/atau `date_to`, sistem menggunakan nilai yang diberikan
3. Sistem otomatis memvalidasi bahwa `date_to` >= `date_from`

**Contoh:**
```bash
# Menggunakan default (7 hari terakhir untuk daily)
GET /api/dashboard/sales/revenue-trend?period=daily

# Custom range - data Januari 2026
GET /api/dashboard/sales/revenue-trend?period=daily&date_from=2026-01-01&date_to=2026-01-31

# Custom range - data 3 bulan terakhir
GET /api/dashboard/sales/revenue-trend?period=monthly&date_from=2025-11-01&date_to=2026-01-31
```

---

### 3. **Filter Tambahan**

Parameter opsional untuk memfilter data lebih spesifik.

| Parameter | Tipe | Keterangan | Endpoint yang Mendukung |
|-----------|------|------------|-------------------------|
| `customer` | string | Filter berdasarkan nama customer (bp_name) | `revenueTrend`, `topSellingProducts`, `salesOrderFulfillment`, `monthlySalesComparison` |
| `product_type` | string | Filter berdasarkan tipe produk | `revenueTrend`, `topSellingProducts`, `salesOrderFulfillment`, `monthlySalesComparison` |
| `limit` | integer | Batasi jumlah hasil | `topCustomersByRevenue`, `topSellingProducts` |

**Contoh:**
```bash
# Filter berdasarkan customer
GET /api/dashboard/sales/revenue-trend?period=monthly&customer=PT%20ABC

# Filter berdasarkan product type
GET /api/dashboard/sales/revenue-trend?period=monthly&product_type=FG

# Kombinasi filter
GET /api/dashboard/sales/revenue-trend?period=daily&customer=PT%20ABC&product_type=FG&date_from=2026-01-01&date_to=2026-01-31

# Limit hasil
GET /api/dashboard/sales/top-customers-by-revenue?limit=10&date_from=2026-01-01&date_to=2026-01-31
```

---

## 🔄 Logika Pemilihan Database

Dashboard 4 menggunakan 2 sumber data berbeda berdasarkan tahun:

| Tahun | Database | Model | Tabel |
|-------|----------|-------|-------|
| **≥ 2025** | ERP (Baru) | `SoInvoiceLine` | `so_invoice_line` |
| **< 2025** | ERP2 (Lama) | `SoInvoiceLine2` | `so_invoice_line_2` |

**Logika Otomatis:**
1. Sistem memeriksa `date_from` dan `date_to`
2. Jika **kedua tanggal ≥ 2025**, gunakan `SoInvoiceLine`
3. Jika **kedua tanggal < 2025**, gunakan `SoInvoiceLine2`
4. Jika **range melintasi 2025**, sistem akan:
   - Query dari `SoInvoiceLine2` untuk data < 2025
   - Query dari `SoInvoiceLine` untuk data ≥ 2025
   - Merge hasil dari kedua query

**Contoh:**
```bash
# Data 2024 - menggunakan SoInvoiceLine2
GET /api/dashboard/sales/revenue-trend?date_from=2024-01-01&date_to=2024-12-31

# Data 2025 - menggunakan SoInvoiceLine
GET /api/dashboard/sales/revenue-trend?date_from=2025-01-01&date_to=2025-12-31

# Data melintasi 2025 - menggunakan KEDUA database
GET /api/dashboard/sales/revenue-trend?date_from=2024-11-01&date_to=2025-02-28
```

---

## 📊 Response Format

Semua endpoint mengembalikan response dengan format konsisten:

```json
{
  "data": [
    // Array data hasil query
  ],
  "filter_metadata": {
    "period": "daily",           // Period yang digunakan
    "date_field": "invoice_date", // Field tanggal yang difilter
    "date_from": "2026-01-01",   // Tanggal mulai aktual
    "date_to": "2026-01-31"      // Tanggal akhir aktual
  }
}
```

**Kegunaan `filter_metadata`:**
- Frontend bisa menampilkan filter yang sedang aktif
- User tahu range data yang ditampilkan
- Memudahkan debugging jika ada masalah

---

## 🎨 Contoh Penggunaan per Endpoint

### 1. **Revenue Trend** - Area Chart

Menampilkan trend revenue berdasarkan periode.

**Endpoint:** `GET /api/dashboard/sales/revenue-trend`

**Parameter:**
- `period` - daily/monthly/yearly
- `date_from`, `date_to` - Range tanggal
- `customer` - Filter customer (optional)
- `product_type` - Filter product type (optional)

**Contoh:**
```bash
# Trend revenue harian minggu ini
GET /api/dashboard/sales/revenue-trend?period=daily

# Trend revenue bulanan tahun 2025
GET /api/dashboard/sales/revenue-trend?period=monthly&date_from=2025-01-01&date_to=2025-12-31

# Trend revenue customer tertentu
GET /api/dashboard/sales/revenue-trend?period=monthly&customer=PT%20ABC&date_from=2025-01-01&date_to=2025-12-31
```

**Response:**
```json
{
  "data": [
    {
      "period": "2026-01-01",
      "revenue": 1000000,
      "invoice_count": 10
    },
    {
      "period": "2026-01-02",
      "revenue": 1200000,
      "invoice_count": 12
    }
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

---

### 2. **Top Customers by Revenue** - Horizontal Bar Chart

Menampilkan customer dengan revenue tertinggi.

**Endpoint:** `GET /api/dashboard/sales/top-customers-by-revenue`

**Parameter:**
- `limit` - Jumlah customer (default: 20)
- `date_from`, `date_to` - Range tanggal
- `period` - Untuk metadata saja

**Contoh:**
```bash
# Top 10 customer bulan ini
GET /api/dashboard/sales/top-customers-by-revenue?limit=10

# Top 20 customer tahun 2025
GET /api/dashboard/sales/top-customers-by-revenue?limit=20&date_from=2025-01-01&date_to=2025-12-31
```

**Response:**
```json
{
  "data": [
    {
      "bp_name": "PT ABC",
      "total_revenue": 5000000,
      "number_of_orders": 50,
      "avg_order_value": 100000,
      "total_qty": 1000,
      "avg_price": 5000,
      "revenue_contribution": 25.5
    }
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

---

### 3. **Sales Order Fulfillment** - Bar Chart

Menampilkan jumlah delivered quantity berdasarkan periode.

**Endpoint:** `GET /api/dashboard/sales/order-fulfillment`

**Parameter:**
- `period` - daily/monthly/yearly
- `date_from`, `date_to` - Range tanggal
- `product_type` - Filter product type (optional)
- `customer` - Filter customer (optional)

**Contoh:**
```bash
# Fulfillment harian minggu ini
GET /api/dashboard/sales/order-fulfillment?period=daily

# Fulfillment bulanan tahun 2025
GET /api/dashboard/sales/order-fulfillment?period=monthly&date_from=2025-01-01&date_to=2025-12-31

# Fulfillment product tertentu
GET /api/dashboard/sales/order-fulfillment?period=monthly&product_type=FG&date_from=2025-01-01&date_to=2025-12-31
```

**Response:**
```json
{
  "data": [
    {
      "period": "2026-01-01",
      "delivered_qty": "75000.0"
    },
    {
      "period": "2026-01-02",
      "delivered_qty": "80000.0"
    }
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

---

### 4. **Invoice Status Distribution** - Stacked Bar Chart

Menampilkan distribusi status invoice berdasarkan periode atau customer.

**Endpoint:** `GET /api/dashboard/sales/invoice-status-distribution`

**Parameter:**
- `group_by` - `daily`/`monthly`/`yearly`/`customer`
- `date_from`, `date_to` - Range tanggal

**Contoh:**
```bash
# Distribusi per hari
GET /api/dashboard/sales/invoice-status-distribution?group_by=daily

# Distribusi per customer
GET /api/dashboard/sales/invoice-status-distribution?group_by=customer&date_from=2026-01-01&date_to=2026-01-31

# Distribusi per bulan tahun 2025
GET /api/dashboard/sales/invoice-status-distribution?group_by=monthly&date_from=2025-01-01&date_to=2025-12-31
```

**Response (group by period):**
```json
{
  "data": [
    {
      "category": "2026-01-01",
      "invoice_status": "Posted",
      "count": 10
    },
    {
      "category": "2026-01-01",
      "invoice_status": "Pending",
      "count": 5
    }
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

**Response (group by customer):**
```json
{
  "data": [
    {
      "category": "PT ABC",
      "invoice_status": "Posted",
      "count": 50
    },
    {
      "category": "PT ABC",
      "invoice_status": "Pending",
      "count": 10
    }
  ],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31"
  }
}
```

---

## 🔍 Tips & Best Practices

### 1. **Gunakan Period yang Sesuai dengan Range**

❌ **Tidak Disarankan:**
```bash
# Daily untuk range 1 tahun (365 data points - terlalu banyak)
GET /api/dashboard/sales/revenue-trend?period=daily&date_from=2025-01-01&date_to=2025-12-31
```

✅ **Disarankan:**
```bash
# Daily untuk range 1 minggu - 1 bulan
GET /api/dashboard/sales/revenue-trend?period=daily&date_from=2026-01-01&date_to=2026-01-31

# Monthly untuk range 1 tahun
GET /api/dashboard/sales/revenue-trend?period=monthly&date_from=2025-01-01&date_to=2025-12-31

# Yearly untuk range multi-tahun
GET /api/dashboard/sales/revenue-trend?period=yearly&date_from=2023-01-01&date_to=2025-12-31
```

### 2. **Manfaatkan Default Values**

Jika tidak perlu custom range, biarkan kosong untuk menggunakan default yang smart:

```bash
# Otomatis dapat data 7 hari terakhir
GET /api/dashboard/sales/revenue-trend?period=daily

# Otomatis dapat data bulan ini
GET /api/dashboard/sales/revenue-trend?period=monthly

# Otomatis dapat data tahun ini
GET /api/dashboard/sales/revenue-trend?period=yearly
```

### 3. **Kombinasikan Filter untuk Analisis Spesifik**

```bash
# Analisis customer tertentu, product tertentu, periode tertentu
GET /api/dashboard/sales/revenue-trend?period=monthly&customer=PT%20ABC&product_type=FG&date_from=2025-01-01&date_to=2025-12-31
```

### 4. **Gunakan Limit untuk Performa**

```bash
# Top 5 customer saja (lebih cepat)
GET /api/dashboard/sales/top-customers-by-revenue?limit=5

# Top 10 product saja
GET /api/dashboard/sales/top-selling-products?limit=10
```

---

## 📝 Daftar Endpoint dan Parameter yang Didukung

| Endpoint | `period` | `date_from`/`date_to` | `customer` | `product_type` | `limit` | `group_by` |
|----------|----------|----------------------|------------|----------------|---------|------------|
| `/overview-kpi` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/revenue-trend` | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ (alias) |
| `/top-customers-by-revenue` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `/by-product-type` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/shipment-status-tracking` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/delivery-performance` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/invoice-status-distribution` | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| `/order-fulfillment` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `/top-selling-products` | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| `/revenue-by-currency` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `/monthly-sales-comparison` | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `/all-data` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Keterangan:**
- ✅ = Parameter didukung
- ❌ = Parameter tidak didukung/tidak relevan

---

## 🚀 Quick Reference

### Default Values Cheat Sheet

```
period=daily   → date_from = 7 hari lalu, date_to = hari ini
period=monthly → date_from = awal bulan, date_to = hari ini
period=yearly  → date_from = awal tahun, date_to = hari ini

limit (top-customers) = 20
limit (top-products) = 10
```

### URL Encoding

Jika parameter mengandung spasi atau karakter khusus, gunakan URL encoding:

```
PT ABC → PT%20ABC
FG-001 → FG-001 (tidak perlu encoding)
```

---

**Dibuat:** 2026-01-06  
**Versi:** 1.0  
**Status:** ✅ Production Ready
