# Contoh Penggunaan API - Monthly Period untuk Tahun 2024

## Overview

Dokumen ini menjelaskan cara menggunakan API dengan `period=monthly` untuk menampilkan data tahun 2024 yang sudah di-aggregate per bulan.

---

## Parameter yang Diperlukan

Untuk menampilkan data monthly tahun 2024, gunakan parameter berikut:

| Parameter   | Value        | Penjelasan                                    |
| ----------- | ------------ | --------------------------------------------- |
| `period`    | `monthly`    | Periodisasi data per bulan                    |
| `date_from` | `2024-01-01` | Tanggal mulai (awal tahun 2024)               |
| `date_to`   | `2024-12-31` | Tanggal akhir (akhir tahun 2024)              |
| `warehouse` | `WHMT01`     | Warehouse code (required untuk inventory-rev) |

---

## Contoh Request

### 1. Stock Movement Trend - Monthly 2024

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31
```

**cURL:**

```bash
curl -X GET "http://your-domain.com/api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31" \
  -H "Accept: application/json"
```

**JavaScript/Axios:**

```javascript
const getMonthlyTrend2024 = async () => {
    try {
        const response = await axios.get(
            "/api/dashboard/inventory-rev/movement-trend",
            {
                params: {
                    warehouse: "WHMT01",
                    period: "monthly",
                    date_from: "2024-01-01",
                    date_to: "2024-12-31",
                },
            }
        );

        console.log("Monthly Trend 2024:", response.data.trend_data);
        // Output: Array dengan 12 bulan (Jan - Des 2024)
    } catch (error) {
        console.error("Error:", error.response.data);
    }
};
```

**Expected Response:**

```json
{
    "trend_data": [
        {
            "period": "2024-01",
            "total_onhand": 120000.0,
            "total_receipt": 10000.0,
            "total_shipment": 8000.0,
            "net_movement": 2000.0
        },
        {
            "period": "2024-02",
            "total_onhand": 122000.0,
            "total_receipt": 12000.0,
            "total_shipment": 10000.0,
            "net_movement": 2000.0
        },
        {
            "period": "2024-03",
            "total_onhand": 125000.0,
            "total_receipt": 15000.0,
            "total_shipment": 12000.0,
            "net_movement": 3000.0
        },
        // ... sampai Desember 2024
        {
            "period": "2024-12",
            "total_onhand": 135000.0,
            "total_receipt": 18000.0,
            "total_shipment": 15000.0,
            "net_movement": 3000.0
        }
    ],
    "period": "monthly",
    "granularity": "monthly",
    "current_total_onhand": 135000.0,
    "snapshot_date": "2024-12-31",
    "date_range": {
        "from": "2024-01-01",
        "to": "2024-12-31",
        "days": 365
    }
}
```

---

### 2. Stock Level Overview - Monthly 2024

```bash
GET /api/dashboard/inventory-rev/kpi?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31
```

**Note**: Untuk single value endpoints, ini akan mengembalikan data dari snapshot terakhir di bulan terakhir (Desember 2024).

---

### 3. Stock by Customer - Monthly 2024

```bash
GET /api/dashboard/inventory-rev/customer-analysis?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31&group_type_desc=Finished Goods
```

---

### 4. Stock Health Distribution - Monthly 2024

```bash
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31&customer=CUST001
```

---

## Variasi Request

### Hanya Beberapa Bulan (Q1 2024)

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-03-31
```

**Hasil**: Hanya mengembalikan data untuk Jan, Feb, Mar 2024

### Hanya Beberapa Bulan (Q4 2024)

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-10-01&date_to=2024-12-31
```

**Hasil**: Hanya mengembalikan data untuk Okt, Nov, Des 2024

### Dengan Filter Customer

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31&customer=CUST001
```

### Dengan Filter Group Type

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2024-01-01&date_to=2024-12-31&group_type_desc=Finished Goods
```

---

## Perbandingan dengan Tahun Lain

### Tahun 2023

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2023-01-01&date_to=2023-12-31
```

### Tahun 2025

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2025-01-01&date_to=2025-12-31
```

### Multi-Year (2023-2024)

```bash
GET /api/dashboard/inventory-rev/movement-trend?warehouse=WHMT01&period=monthly&date_from=2023-01-01&date_to=2024-12-31
```

**Hasil**: Mengembalikan data untuk semua bulan dari Jan 2023 sampai Des 2024 (24 bulan)

---

## Catatan Penting

1. **Format Tanggal**: Selalu gunakan format `YYYY-MM-DD`
2. **Period Monthly**: Data akan di-aggregate per bulan, bukan per tanggal
3. **Snapshot Selection**: Setiap bulan menggunakan snapshot terakhir yang tersedia di bulan tersebut
4. **Date Range**:
    - `date_from=2024-01-01`: Mulai dari awal tahun 2024
    - `date_to=2024-12-31`: Sampai akhir tahun 2024
5. **Response Format**:
    - `period: "2024-01"` = Januari 2024
    - `period: "2024-02"` = Februari 2024
    - dst sampai `period: "2024-12"` = Desember 2024

---

## Quick Reference

| Tahun | date_from  | date_to    |
| ----- | ---------- | ---------- |
| 2023  | 2023-01-01 | 2023-12-31 |
| 2024  | 2024-01-01 | 2024-12-31 |
| 2025  | 2025-01-01 | 2025-12-31 |

**Parameter tetap sama**: `period=monthly`
