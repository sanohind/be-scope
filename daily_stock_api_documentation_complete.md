# 📦 Daily Stock Calculation API Documentation

**Last Updated:** 2025\
**Author:** System Planning -- Stock Calculation Module

------------------------------------------------------------------------

# 📘 Overview

Dokumen ini menjelaskan proses perhitungan *Daily On-Hand Stock*
berdasarkan **sumber data utama**:\
- `inventory_transaction` (transaksi harian)\
- `stockbywh` (stock on-hand real-time saat ini)\
- `daily_stock` (rekaman stock per hari)

Setiap hari pukul **06:00 pagi**, sistem akan menghitung *on-hand stock
per warehouse* dan menyimpannya ke tabel `daily_stock`.

------------------------------------------------------------------------

# 🗂️ Sumber Data

## 1️⃣ inventory_transaction (Sumber Transaksi Harian)

Tabel ini berisi semua transaksi yang mempengaruhi stock.

### Kolom Penting

  Kolom        Deskripsi
  ------------ --------------------------------------
  partno       Kode part
  warehouse    Kode gudang
  trans_date   Tanggal transaksi
  trans_type   Jenis transaksi (`Receipt`, `Issue`)
  qty          Quantity transaksi
  lotno        Lot number
  trans_id     ID transaksi

### Contoh Query Struktur

``` sql
SELECT TOP 1000 
    partno,
    part_desc,
    warehouse,
    trans_date,
    trans_type,
    qty,
    lotno,
    trans_id
FROM soi107.dbo.inventory_transaction;
```

------------------------------------------------------------------------

## 2️⃣ stockbywh (Sumber Stock Real-Time / Initial Stock)

Tabel ini menyimpan **on-hand stock saat ini**, tetapi **tidak memiliki
riwayat harian**.\
Oleh karena itu digunakan sebagai **initial base** ketika daily_stock
belum memiliki data sebelumnya.

### Kolom Penting

  Kolom       Deskripsi
  ----------- ------------------------
  warehouse   Kode gudang
  partno      Kode part
  onhand      Stock on-hand saat ini
  allocated   Qty allocated
  onorder     Qty on order
  min_stock   Minimum stock
  max_stock   Maximum stock

### Contoh Query Struktur

``` sql
SELECT TOP 1000 
    warehouse,
    partno,
    partname,
    onhand,
    min_stock,
    max_stock
FROM soi107.dbo.stockbywh;
```

------------------------------------------------------------------------

## 3️⃣ daily_stock (Riwayat Stock Harian -- Dibuat oleh Sistem)

Tabel ini menyimpan hasil perhitungan stock harian.

### Struktur Tabel

``` sql
CREATE TABLE daily_stock (
    id BIGINT IDENTITY PRIMARY KEY,
    warehouse VARCHAR(50) NOT NULL,
    partno VARCHAR(100) NOT NULL,
    onhand INT NOT NULL,
    date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_daily_stock_wh_part_date
    ON daily_stock (warehouse, partno, date);
```

------------------------------------------------------------------------

# 🔢 Logika Perhitungan

## 1️⃣ Ambil On-Hand stock hari sebelumnya

``` sql
SELECT TOP 1 onhand
FROM daily_stock
WHERE warehouse = :warehouse
  AND partno = :partno
ORDER BY date DESC;
```

Jika data tidak ada → ambil dari `stockbywh.onhand`.

------------------------------------------------------------------------

## 2️⃣ Ambil transaksi untuk tanggal hari ini

``` sql
SELECT trans_type, qty
FROM inventory_transaction
WHERE warehouse = :warehouse
  AND partno = :partno
  AND CAST(trans_date AS DATE) = :today;
```

------------------------------------------------------------------------

## 3️⃣ Lakukan perhitungan

    receipt_today = SUM(qty WHERE trans_type = 'Receipt')
    issue_today   = SUM(qty WHERE trans_type = 'Issue')

    onhand_today = onhand_yesterday + receipt_today - issue_today

------------------------------------------------------------------------

## 4️⃣ Simpan hasilnya ke daily_stock

``` sql
INSERT INTO daily_stock (warehouse, partno, onhand, date)
VALUES (:warehouse, :partno, :onhand_today, :today);
```

------------------------------------------------------------------------

# ⏰ Scheduler / Cron Job

Sistem akan menjalankan proses perhitungan otomatis setiap hari **jam
06:00 pagi**.

### Laravel Scheduler

``` php
$schedule->command('daily-stock:calculate')->dailyAt('06:00');
```

------------------------------------------------------------------------

# 🔌 API Endpoint

## **GET /api/stock/daily**

### Query Parameters

  Param       Wajib   Deskripsi
  ----------- ------- ------------------------------
  warehouse   ✔       Filter berdasarkan warehouse
  from        ✖       Default: 30 hari lalu
  to          ✖       Default: Hari ini

### SQL Query API

``` sql
SELECT date, SUM(onhand) AS onhand
FROM daily_stock
WHERE warehouse = :warehouse
  AND date BETWEEN :from AND :to
GROUP BY date
ORDER BY date;
```

### Contoh Response

``` json
[
  { "date": "2025-01-01", "onhand": 100 },
  { "date": "2025-01-02", "onhand": 90 },
  { "date": "2025-01-03", "onhand": 120 }
]
```

------------------------------------------------------------------------

# 🧪 Test Case Perhitungan

### Initial

Onhand (1 Jan) = 100

### Transaksi (2 Jan)

  Jenis     Qty
  --------- -----
  Receipt   +20
  Issue     -30

### Perhitungan

    100 + 20 - 30 = 90

Hasil 90 disimpan sebagai onhand untuk 2 Jan.

------------------------------------------------------------------------

# 🧩 Checklist Implementasi

-   [ ] Migration tabel `daily_stock`
-   [ ] Model DailyStock
-   [ ] Command perhitungan `daily-stock:calculate`
-   [ ] Scheduler `dailyAt('06:00')`
-   [ ] API endpoint untuk chart
-   [ ] Validasi input warehouse
-   [ ] Logging proses perhitungan
-   [ ] Unit test untuk receipt/issue

------------------------------------------------------------------------

# 📝 Penutup

Dokumentasi ini merangkum seluruh proses perhitungan daily stock, sumber
data, struktur tabel, logic, scheduler, dan API endpoint yang digunakan
untuk menampilkan grafik stock balance harian.
