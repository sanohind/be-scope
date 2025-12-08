# Stock Health Distribution API - Dokumentasi

## Overview

API untuk menampilkan distribusi kesehatan stok (stock health distribution) dengan grouping berdasarkan date dan filtering by period (daily, monthly, yearly).

## Endpoint

```
GET /api/dashboard/inventory-rev/stock-health-distribution
```

## Request Parameters

| Parameter         | Type   | Required | Description                                                       |
| ----------------- | ------ | -------- | ----------------------------------------------------------------- |
| `warehouse`       | string | ✅ Yes   | Kode warehouse (WHMT01, WHRM01, WHRM02, WHFG01, WHFG02)           |
| `period`          | string | ❌ No    | Periode grouping: `daily`, `monthly`, `yearly` (default: `daily`) |
| `date_from`       | string | ❌ No    | Tanggal mulai (format: YYYY-MM-DD)                                |
| `date_to`         | string | ❌ No    | Tanggal akhir (format: YYYY-MM-DD)                                |
| `customer`        | string | ❌ No    | Filter berdasarkan customer                                       |
| `group_type_desc` | string | ❌ No    | Filter berdasarkan tipe group                                     |

## Response Format

### Daily Period

```json
{
    "data": [
        {
            "date": "2025-01-01",
            "data": [
                {
                    "stock_status": "Critical",
                    "item_count": 5,
                    "total_onhand": 150.5,
                    "trans_count": 45,
                    "total_shipment": 12
                },
                {
                    "stock_status": "Low Stock",
                    "item_count": 8,
                    "total_onhand": 320.75,
                    "trans_count": 62,
                    "total_shipment": 18
                },
                {
                    "stock_status": "Normal",
                    "item_count": 120,
                    "total_onhand": 5420.0,
                    "trans_count": 156,
                    "total_shipment": 45
                },
                {
                    "stock_status": "Overstock",
                    "item_count": 15,
                    "total_onhand": 2100.25,
                    "trans_count": 28,
                    "total_shipment": 8
                }
            ]
        }
    ],
    "date_range": {
        "from": "2025-01-01",
        "to": "2025-01-31 23:59:59"
    },
    "period": "daily",
    "grouping": "date"
}
```

### Monthly Period

```json
{
    "data": [
        {
            "month": "2025-01",
            "data": [
                {
                    "stock_status": "Critical",
                    "item_count": 349,
                    "total_onhand": 116034.0,
                    "trans_count": 84,
                    "total_shipment": 84
                },
                {
                    "stock_status": "Low Stock",
                    "item_count": 24,
                    "total_onhand": 652.0,
                    "trans_count": 18,
                    "total_shipment": 18
                },
                {
                    "stock_status": "Normal",
                    "item_count": 389,
                    "total_onhand": 2368256.0,
                    "trans_count": 134,
                    "total_shipment": 134
                },
                {
                    "stock_status": "Overstock",
                    "item_count": 17,
                    "total_onhand": 5549800.0,
                    "trans_count": 13,
                    "total_shipment": 13
                }
            ]
        },
        {
            "month": "2025-02",
            "data": [
                {
                    "stock_status": "Critical",
                    "item_count": 361,
                    "total_onhand": 167954.0,
                    "trans_count": 112,
                    "total_shipment": 112
                }
            ]
        }
    ],
    "date_range": {
        "from": "2025-01-01",
        "to": "2025-12-31 23:59:59"
    },
    "period": "monthly",
    "grouping": "month"
}
```

### Yearly Period

```json
{
    "data": [
        {
            "year": "2025",
            "data": [
                {
                    "stock_status": "Critical",
                    "item_count": 349,
                    "total_onhand": 116034.0,
                    "trans_count": 84,
                    "total_shipment": 84
                }
            ]
        }
    ],
    "date_range": {
        "from": "2025-01-01",
        "to": "2025-12-31 23:59:59"
    },
    "period": "yearly",
    "grouping": "year"
}
```

## Response Fields

### Main Response

-   **data**: Array berisi data yang dikelompokkan berdasarkan period
-   **date_range**: Range tanggal yang digunakan untuk query
-   **period**: Periode yang digunakan (daily/monthly/yearly)
-   **grouping**: Label grouping (date/month/year)

### Data Items

-   **date/month/year**: Identifikasi periode (format berbeda sesuai period)
-   **data**: Array berisi stock status distribution untuk periode tersebut

### Stock Status Distribution

-   **stock_status**: Status stok (Critical, Low Stock, Normal, Overstock)
-   **item_count**: Jumlah item dengan status tersebut
-   **total_onhand**: Total quantity on-hand
-   **trans_count**: Jumlah transaksi dalam periode
-   **total_shipment**: Jumlah shipment

## Stock Status Criteria

| Status        | Kondisi                 |
| ------------- | ----------------------- |
| **Critical**  | `onhand < min_stock`    |
| **Low Stock** | `onhand < safety_stock` |
| **Overstock** | `onhand > max_stock`    |
| **Normal**    | Kondisi lainnya         |

## Contoh Request

### 1. Daily - Bulan Januari 2025

```
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHRM02&period=daily&date_from=2025-01-01&date_to=2025-01-31
```

### 2. Monthly - Tahun 2025

```
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHRM02&period=monthly&date_from=2025-01-01&date_to=2025-12-31
```

### 3. Yearly - Perbandingan 3 Tahun

```
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHRM02&period=yearly&date_from=2023-01-01&date_to=2025-12-31
```

### 4. Monthly dengan Filter Customer

```
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHRM02&period=monthly&date_from=2025-01-01&date_to=2025-12-31&customer=CUST001
```

### 5. Daily dengan Filter Group Type

```
GET /api/dashboard/inventory-rev/stock-health-distribution?warehouse=WHRM02&period=daily&date_from=2025-01-01&date_to=2025-01-31&group_type_desc=RAW_MATERIAL
```

## Fitur Utama

✅ **Grouping berdasarkan Date**

-   Data dikelompokkan berdasarkan kolom `snapshot_date` dari tabel `stock_by_wh_snapshots`

✅ **Filtering by Period**

-   **Daily**: Menampilkan semua tanggal dalam range (format: YYYY-MM-DD)
-   **Monthly**: Menampilkan per bulan (format: YYYY-MM)
-   **Yearly**: Menampilkan per tahun (format: YYYY)

✅ **Multi-Database Support**

-   Mendukung MySQL dan SQL Server dengan automatic driver detection
-   Menggunakan `DATE_FORMAT()` untuk MySQL dan `FORMAT()` untuk SQL Server

✅ **Additional Filters**

-   Filter berdasarkan customer
-   Filter berdasarkan group_type_desc

## Catatan Teknis

-   Menggunakan tabel `stock_by_wh_snapshots` untuk historical data
-   Data transaksi diambil dari tabel `inventory_transaction` dengan date filter
-   Automatic database driver detection untuk kompatibilitas MySQL dan SQL Server
-   Data diurutkan berdasarkan periode (ascending)

## Error Handling

| Error                     | Status | Description                                                             |
| ------------------------- | ------ | ----------------------------------------------------------------------- |
| Warehouse tidak diberikan | 400    | Parameter `warehouse` wajib                                             |
| Warehouse tidak valid     | 400    | Warehouse harus salah satu dari: WHMT01, WHRM01, WHRM02, WHFG01, WHFG02 |

## Changelog

### v1.0 (2025-12-04)

-   ✨ Menambahkan grouping berdasarkan `snapshot_date`
-   ✨ Menambahkan filtering by period (daily, monthly, yearly)
-   ✨ Menambahkan support untuk MySQL dan SQL Server
-   ✨ Mengubah format response menjadi terstruktur per period
