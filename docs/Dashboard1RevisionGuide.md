# Dashboard 1 Revision – Inventory Dashboard Guide

Panduan ringkas ini memadukan struktur visual dari `docs/UPDATE_INVENTORY_DASHBOARD.md` dengan implementasi API pada `Dashboard1RevisionController`. Gunakan dokumen ini untuk membangun chart secara cepat dan konsisten.

---

## 1. Ringkasan Sistem

-   **Scope**: Single warehouse view (`WHMT01`, `WHRM01`, `WHRM02`, `WHFG01`, `WHFG02`).
-   **Sumber Data**: `stockbywh` (snapshot stok) + `inventory_transaction` (riwayat transaksi).
-   **Jumlah Chart**: 12 (KPI → Recent Activity) + endpoint bulk `all-data`.
-   **Layout**: Ikuti urutan di `UPDATE_INVENTORY_DASHBOARD.md` (KPI full width, kombinasi 40/60 dsb).
-   **Warna Status**: Critical #DC3545, Low #FD7E14, Normal #28A745, Overstock #007BFF. Aktivitas mengikuti palet hijau/kuning/oranye/abu.

---

## 2. Dasar Integrasi API

-   **Base URL**: `/api/dashboard/inventory-rev`.
-   **Auth**: Public by default, tambahkan middleware jika dibutuhkan.
-   **Parameter wajib**: `warehouse=<kode valid>`.
-   **Parameter tanggal opsional**: `date_from`, `date_to` (format `Y-m-d` atau `Y-m-d H:i:s`). Jika kosong, controller fallback ke default (30 hari kecuali disebut).
-   **Filter tambahan** (hanya Chart 12): `trans_type`, `user`, `page`, `per_page`.
-   **Saran Frontend**:
    -   Gunakan `date_range` dari response sebagai sumber truth untuk filter global.
    -   Terapkan conditional formatting sesuai rules di dokumen dashboard.
    -   Manfaatkan endpoint `all-data` untuk prefetch Chart 1–11 sekaligus saat load awal.

---

## 3. Peta Endpoint vs Chart

| Chart | Endpoint                                  | Default Range           | Fokus Utama                                                |
| ----- | ----------------------------------------- | ----------------------- | ---------------------------------------------------------- |
| 1     | `GET /kpi`                                | 30 hari (transaksi)     | KPI kartu (SKU, onhand, kritikal, transaksi, net movement) |
| 2     | `GET /stock-health-distribution`          | 30 hari                 | Donut stok vs aktivitas                                    |
| 3     | `GET /stock-movement-trend`               | 30 hari                 | Area chart receipt vs shipment harian                      |
| 4     | `GET /top-critical-items`                 | 7 hari                  | Tabel 15 item di bawah safety stock                        |
| 5     | `GET /most-active-items`                  | 30 hari                 | Bar horizontal 15 item dengan transaksi terbanyak          |
| 6     | `GET /stock-and-activity-by-product-type` | 30 hari                 | Combo kolom+line per product_type                          |
| 7     | `GET /stock-by-customer`                  | 30 hari                 | Treemap customer → tipe → model                            |
| 8     | `GET /receipt-vs-shipment-trend`          | 90 hari                 | Tren mingguan receipt vs shipment                          |
| 9     | `GET /transaction-type-distribution`      | 30 hari                 | Stacked bar trans_type vs order_type                       |
| 10    | `GET /fast-vs-slow-moving`                | 30 hari                 | Scatter plot frekuensi vs stok                             |
| 11    | `GET /stock-turnover-rate`                | 30 hari                 | Bar horizontal top 20 turnover                             |
| 12    | `GET /recent-transaction-history`         | -                       | Tabel transaksi terbaru + paginasi                         |
| Bulk  | `GET /all-data`                           | Mengikuti masing-masing | Bundel Chart 1–11                                          |

---

## 4. Detail Chart & Sample Output

> Catatan: Contoh JSON di bawah ini hanya untuk gambaran struktur. Gantilah nilai sesuai hasil real API.

### 4.1 Chart 1 – Comprehensive KPI Cards

-   **Endpoint**: `GET /kpi`
-   **Gunakan untuk**: 6 kartu KPI (Total SKU, Onhand, Critical Items, Today's Trans, Net Movement 30d, Date Range info).
-   **Parameter**: `warehouse`, `date_from`, `date_to`.
-   **Response Fields**: `total_sku`, `total_onhand`, `critical_items`, `trans_in_period`, `net_movement`, `date_range{from,to,days}`.

```
{
  "total_sku": 128,
  "total_onhand": 54231.75,
  "critical_items": 32,
  "trans_in_period": 1098,
  "net_movement": -154.25,
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.2 Chart 2 – Stock Health Distribution + Activity

-   **Endpoint**: `GET /stock-health-distribution`
-   **Visual**: Donut stack status (Critical, Low, Normal, Overstock) + tooltip transaksi 30 hari.
-   **Response**: `data[]` dengan `stock_status`, `item_count`, `total_onhand`, `trans_count`, `total_shipment`, plus `date_range`.

```
{
  "data": [
    { "stock_status": "Critical", "item_count": 14, "total_onhand": 120.5, "trans_count": 42, "total_shipment": 87.6 },
    { "stock_status": "Normal", "item_count": 210, "total_onhand": 40123.9, "trans_count": 312, "total_shipment": 645.2 }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59" }
}
```

### 4.3 Chart 3 – Stock Movement Trend

-   **Endpoint**: `GET /stock-movement-trend`
-   **Visual**: Area chart (receipt vs shipment) + net movement line, referensi `current_total_onhand`.
-   **Response**: `trend_data[]` (per tanggal) berisi `date`, `total_receipt`, `total_shipment`, `net_movement`, `trans_count`; plus `current_total_onhand`, `date_range`.

```
{
  "trend_data": [
    { "date": "2025-10-20", "total_receipt": 320.5, "total_shipment": 290.0, "net_movement": 30.5, "trans_count": 58 }
  ],
  "current_total_onhand": 54231.75,
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.4 Chart 4 – Top 15 Critical Items

-   **Endpoint**: `GET /top-critical-items`
-   **Default Range**: 7 hari (aktivitas sparkline).
-   **Response**: `data[]` dengan `partno`, `description`, `product_type`, `onhand`, `safety_stock`, `min_stock`, `gap`, `location`, `trans_in_period`, `shipment_in_period`, `last_trans_date`, `status`; plus `date_range`.

```
{
  "data": [
    {
      "partno": "PT-11023",
      "description": "Bracket Assy",
      "product_type": "Mechanical",
      "onhand": 12,
      "safety_stock": 80,
      "min_stock": 50,
      "gap": 68,
      "location": "A1-03",
      "trans_in_period": 5,
      "shipment_in_period": 34.5,
      "last_trans_date": "2025-11-15 10:22:00",
      "status": "Critical"
    }
  ],
  "date_range": { "from": "2025-11-10", "to": "2025-11-16 23:59:59", "days": 6 }
}
```

### 4.5 Chart 5 – Top 15 Most Active Items

-   **Endpoint**: `GET /most-active-items`
-   **Visual**: Horizontal bar dengan warna berdasarkan stock status / activity level.
-   **Response**: `data[]` (TOP 15) dengan `partno`, `description`, `product_type`, `trans_count`, `total_receipt`, `total_shipment`, `current_onhand`, `safety_stock`, `stock_status`, `activity_level`; plus `date_range`.

```
{
  "data": [
    {
      "partno": "PT-88001",
      "description": "PCB Control Board",
      "product_type": "Electronics",
      "trans_count": 42,
      "total_receipt": 520.0,
      "total_shipment": 498.3,
      "current_onhand": 210,
      "safety_stock": 180,
      "stock_status": "Normal",
      "activity_level": "High Activity"
    }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.6 Chart 6 – Stock & Activity by Product Type

-   **Endpoint**: `GET /stock-and-activity-by-product-type`
-   **Gunakan untuk**: Combo chart (Onhand + Safety + Available vs Trans Count + Turnover).
-   **Response**: `data[]` dengan `product_type`, `sku_count`, `total_onhand`, `total_safety_stock`, `total_available`, `trans_count`, `total_shipment`, `turnover_rate`; plus `date_range`.

```
{
  "data": [
    {
      "product_type": "Electronics",
      "sku_count": 180,
      "total_onhand": 23000,
      "total_safety_stock": 18000,
      "total_available": 21500,
      "trans_count": 520,
      "total_shipment": 12450.6,
      "turnover_rate": 0.54
    }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.7 Chart 7 – Stock by Customer (Treemap)

-   **Endpoint**: `GET /stock-by-customer`
-   **Visual**: Treemap customer → product_type → model, warna berdasarkan `activity_level`.
-   **Response**: `data[]` dengan `customer`, `product_type`, `model`, `item_count`, `total_onhand`, `total_allocated`, `trans_count`, `total_shipment`, `last_trans_date`, `activity_level`; plus `date_range`.

```
{
  "data": [
    {
      "customer": "PT ABC",
      "product_type": "Mechanical",
      "model": "M-2200",
      "item_count": 45,
      "total_onhand": 8200,
      "total_allocated": 1200,
      "trans_count": 65,
      "total_shipment": 3400.2,
      "last_trans_date": "2025-11-16 09:15:00",
      "activity_level": "High Activity"
    }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59" }
}
```

### 4.8 Chart 8 – Receipt vs Shipment Trend (Weekly)

-   **Endpoint**: `GET /receipt-vs-shipment-trend`
-   **Default Range**: 90 hari untuk konteks 12 minggu.
-   **Response**: `data[]` per minggu (`week_num`, `year`, `week_start`, `total_receipt`, `total_shipment`, `net_movement`, `trans_count`); plus `date_range`.

```
{
  "data": [
    { "week_num": 43, "year": 2025, "week_start": "2025-10-20", "total_receipt": 980.4, "total_shipment": 910.0, "net_movement": 70.4, "trans_count": 142 }
  ],
  "date_range": { "from": "2025-08-19", "to": "2025-11-16 23:59:59", "days": 89 }
}
```

### 4.9 Chart 9 – Transaction Type Distribution

-   **Endpoint**: `GET /transaction-type-distribution`
-   **Visual**: Stacked bar (trans_type vs order_type) + metrik qty & part/user unik.
-   **Response**: `data[]` dengan `trans_type`, `order_type`, `trans_count`, `total_qty`, `unique_parts`, `unique_users`; plus `date_range`.

```
{
  "data": [
    { "trans_type": "Receipt", "order_type": "PO", "trans_count": 320, "total_qty": 1220.5, "unique_parts": 210, "unique_users": 4 }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59" }
}
```

### 4.10 Chart 10 – Fast vs Slow Moving Items

-   **Endpoint**: `GET /fast-vs-slow-moving`
-   **Visual**: Scatter plot dengan klasifikasi quadran (Healthy, High Risk, Slow Moving, Review).
-   **Response**: `data[]` berisi `partno`, `description`, `product_type`, `onhand`, `safety_stock`, `max_stock`, `gap_from_safety`, `trans_count`, `total_shipment`, `turnover_rate`, `stock_status`, `classification`; plus `date_range`.

```
{
  "data": [
    {
      "partno": "PT-77001",
      "description": "Motor Assembly",
      "product_type": "Mechanical",
      "onhand": 420,
      "safety_stock": 300,
      "max_stock": 600,
      "gap_from_safety": -120,
      "trans_count": 22,
      "total_shipment": 510.8,
      "turnover_rate": 1.22,
      "stock_status": "Normal",
      "classification": "Healthy"
    }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.11 Chart 11 – Stock Turnover Rate (Top 20)

-   **Endpoint**: `GET /stock-turnover-rate`
-   **Gunakan untuk**: Horizontal bar dengan gradient (Fast/Medium/Slow) + label days of stock & rekomendasi.
-   **Response**: `data[]` (TOP 20) dengan `partno`, `description`, `product_type`, `onhand`, `safety_stock`, `total_shipment`, `turnover_rate`, `days_of_stock`, `movement_category`, `recommendation`; plus `date_range`.

```
{
  "data": [
    {
      "partno": "PT-55090",
      "description": "Sensor Module",
      "product_type": "Electronics",
      "onhand": 950,
      "safety_stock": 600,
      "total_shipment": 1840.4,
      "turnover_rate": 1.94,
      "days_of_stock": 15,
      "movement_category": "Fast Moving",
      "recommendation": "Monitor Closely"
    }
  ],
  "date_range": { "from": "2025-10-18", "to": "2025-11-16 23:59:59", "days": 29 }
}
```

### 4.12 Chart 12 – Recent Transaction History

-   **Endpoint**: `GET /recent-transaction-history`
-   **Parameter tambahan**: `page` (default 1), `per_page` (max 100), `trans_type`, `user`, `date_from`, `date_to`.
-   **Response**: `data[]` dengan detail transaksi + `pagination` info (`total`, `per_page`, `current_page`, `last_page`, `from`, `to`).

```
{
  "data": [
    {
      "trans_date": "2025-11-16 14:05:00",
      "trans_id": "TRX-221133",
      "partno": "PT-55090",
      "part_desc": "Sensor Module",
      "trans_type": "Issue",
      "order_type": "SO",
      "order_no": "SO-00215",
      "receipt": 0,
      "shipment": 25,
      "qty": -25,
      "qty_after_trans": 925,
      "current_onhand": 920,
      "variance": -5,
      "location": "B2-11",
      "user": "jdoe",
      "lotno": "LOT-7788",
      "movement_type": "OUT"
    }
  ],
  "pagination": { "total": 1860, "per_page": 50, "current_page": 1, "last_page": 38, "from": 1, "to": 50 }
}
```

### 4.13 Endpoint Bundling – `GET /all-data`

-   Mengembalikan dictionary dengan key `kpi`, `stock_health`, `movement_trend`, `critical_items`, `active_items`, `product_type_analysis`, `customer_analysis`, `receipt_shipment_trend`, `transaction_types`, `fast_slow_moving`, `turnover_rate`.
-   Struktur masing-masing sama dengan endpoint individual (lihat contoh di atas).
-   Gunakan untuk prefetch data awal dashboard; Chart 12 tetap dipanggil terpisah untuk mendukung pagination/filter custom.

---

## 5. Checklist Penerapan Frontend

-   Pastikan filter global (warehouse + date range) meng-update semua chart secara konsisten.
-   Simpan `date_range` dari response untuk ditampilkan di UI (badge rentang tanggal / tooltip).
-   Terapkan conditional formatting:
    -   KPI: highlight metric yang melewati threshold (Critical Items > 0, Net Movement negatif, dsb).
    -   Tabel Chart 4 & 12: warna baris sesuai status stock / movement type.
    -   Chart 5 & 10: gunakan `stock_status` / `classification` untuk warna bar.
-   Ikuti urutan layout yang sudah disediakan agar storytelling dashboard tetap konsisten.
-   Gunakan pagination & TOP N bawaan backend agar performa tetap responsif (<5 detik load).

---

## 6. Troubleshooting Cepat

-   **400 – Warehouse parameter is required**: sertakan `?warehouse=WHMT01`.
-   **400 – Invalid warehouse code**: pastikan kode termasuk whitelist.
-   **Data kosong**: periksa rentang tanggal (gunakan default dengan mengosongkan parameter).
-   **Performa lambat**: batasi filter custom (misal jangan tarik >90 hari), manfaatkan `all-data`.
-   **Inkonistensi angka**: verifikasi join key (partno + warehouse) dan timezone saat menampilkan tanggal.

Dokumentasi ini diringkas agar “to the point” untuk developer frontend/backend yang mengerjakan dashboard inventory versi revisi. Gunakan sebagai referensi utama saat menghubungkan komponen chart dengan API.
