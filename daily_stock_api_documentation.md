# 📦 Daily Stock Calculation API

Dokumentasi Perhitungan Daily On-Hand Stock\
Last Updated: 2025

## 📑 Deskripsi Singkat

Sistem ini menghitung on-hand stock harian setiap warehouse berdasarkan:

1.  On-hand hari sebelumnya (daily_stock)
2.  Transaksi harian dari inventory_transaction
    -   Receipt → tambah stock\
    -   Issue → kurang stock

Hasil perhitungan disimpan setiap hari pukul 06:00 pagi.

## 🗄️ Struktur Database

### 1. inventory_transaction

Kolom penting: - warehouse - partno - trans_date - trans_type (Receipt,
Issue) - qty

### 2. stockbywh

Kolom penting: - warehouse - partno - onhand

### 3. daily_stock

``` sql
CREATE TABLE daily_stock (
    id BIGINT IDENTITY PRIMARY KEY,
    warehouse VARCHAR(50),
    partno VARCHAR(100),
    onhand INT,
    date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## 🔢 Logika Perhitungan

1.  Ambil onhand kemarin (dari daily_stock).\
2.  Jika tidak ditemukan, ambil dari stockbywh.\
3.  Ambil transaksi hari ini berdasarkan trans_type.\
4.  Hitung:

```{=html}
<!-- -->
```
    onhand_today = onhand_yesterday + receipt - issue

5.  Insert ke daily_stock.

## ⏰ Scheduler

Jalankan setiap hari pukul 06:00:

    $schedule->command('daily-stock:calculate')->dailyAt('06:00');

## 🔌 API Endpoint: GET /api/stock/daily

Parameter: - warehouse (required) - from (optional) - to (optional)

SQL:

``` sql
SELECT date, SUM(onhand) AS onhand
FROM daily_stock
WHERE warehouse = :warehouse
  AND date BETWEEN :from AND :to
GROUP BY date
ORDER BY date;
```

Response:

``` json
[
  { "date": "2025-01-01", "onhand": 100 },
  { "date": "2025-01-02", "onhand": 90 }
]
```

## 🧪 Test Case

Onhand kemarin: 100\
Receipt: 20\
Issue: 30\
Hasil: 90

## 🧩 Checklist

-   Migration daily_stock\
-   Model DailyStock\
-   Command daily-stock:calculate\
-   Scheduler 06:00\
-   API Controller\
-   Validasi warehouse
