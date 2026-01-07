# Fix: Sales Growth Calculation - Period-Aware Comparison

## Masalah yang Diperbaiki

Method `salesOverviewKpi` sebelumnya **selalu membandingkan dengan bulan sebelumnya**, tidak peduli apakah user memilih period `daily`, `monthly`, atau `yearly`. Ini menyebabkan perhitungan growth yang **tidak akurat**.

---

## Contoh Masalah

### Scenario: Period Yearly

**Request:**
```bash
GET /api/dashboard/sales/overview-kpi?period=yearly&date_from=2026-01-01&date_to=2026-12-31
```

**Data:**
- **Tahun 2026** (current): 100M
- **Tahun 2025** (previous year): 150M
- **Desember 2025** (previous month): 50M

**Sebelum Fix (SALAH):**
```json
{
  "total_sales_amount": 100000000,
  "sales_growth": 100.00    // ❌ Membandingkan 2026 vs Des 2025
}
```

**Perhitungan Salah:**
```
Growth = (100M - 50M) / 50M × 100 = +100%  ❌ SALAH!
```

**Setelah Fix (BENAR):**
```json
{
  "total_sales_amount": 100000000,
  "sales_growth": -33.33,   // ✅ Membandingkan 2026 vs 2025
  "previous_period": {
    "from": "2025-01-01",
    "to": "2025-12-31",
    "sales_amount": 150000000
  }
}
```

**Perhitungan Benar:**
```
Growth = (100M - 150M) / 150M × 100 = -33.33%  ✅ BENAR!
```

---

## Logika Baru: Period-Aware Comparison

### 1. **Period = `yearly`**

Membandingkan dengan **tahun sebelumnya** (same date range, previous year)

**Contoh:**
```
Current:  2026-01-01 to 2026-12-31
Previous: 2025-01-01 to 2025-12-31  ✅ Year-over-Year
```

**Code:**
```php
if ($period === 'yearly') {
    $prevStart = $range['from']->copy()->subYear();
    $prevEnd = $range['to']->copy()->subYear();
}
```

---

### 2. **Period = `monthly`**

Membandingkan dengan **bulan sebelumnya** (previous month)

**Contoh:**
```
Current:  2026-01-01 to 2026-01-31
Previous: 2025-12-01 to 2025-12-31  ✅ Month-over-Month
```

**Partial Month Support:**
```
Current:  2026-01-01 to 2026-01-15 (15 hari)
Previous: 2025-12-01 to 2025-12-15 (15 hari)  ✅ Same length
```

**Code:**
```php
else {
    // For monthly (default): compare with previous month
    $prevStart = $range['from']->copy()->subMonth()->startOfMonth();
    $prevEnd = $prevStart->copy()->endOfMonth();
    
    // If current period is partial month, use same day range
    if (!$range['to']->isLastOfMonth() && $range['from']->isStartOfMonth()) {
        $prevEnd = $prevStart->copy()->addDays($range['from']->diffInDays($range['to']));
    }
}
```

---

### 3. **Period = `daily`**

Membandingkan dengan **periode sebelumnya dengan panjang yang sama**

**Contoh:**
```
Current:  2026-01-01 to 2026-01-07 (7 hari)
Previous: 2025-12-25 to 2025-12-31 (7 hari)  ✅ Same length
```

**Code:**
```php
elseif ($period === 'daily') {
    // For daily: compare with same number of days in previous period
    $daysDiff = $range['from']->diffInDays($range['to']);
    $prevEnd = $range['from']->copy()->subDay();
    $prevStart = $prevEnd->copy()->subDays($daysDiff);
}
```

---

## Response Format Baru

### Response Sekarang Termasuk `previous_period`

```json
{
  "total_sales_amount": 100000000,
  "total_shipments": 500,
  "total_invoices": 450,
  "outstanding_invoices": 50,
  "sales_growth": -33.33,
  "previous_period": {              // ✅ BARU
    "from": "2025-01-01",
    "to": "2025-12-31",
    "sales_amount": 150000000
  },
  "filter_metadata": {
    "period": "yearly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-12-31"
  }
}
```

**Kegunaan `previous_period`:**
- ✅ Frontend bisa menampilkan detail periode pembanding
- ✅ User bisa verify bahwa perhitungan growth sudah benar
- ✅ Memudahkan debugging jika ada masalah

---

## Testing

### Test 1: Yearly Period

**Request:**
```bash
curl "http://localhost:8000/api/dashboard/sales/overview-kpi?period=yearly&date_from=2026-01-01&date_to=2026-12-31"
```

**Expected:**
- Current period: 2026-01-01 to 2026-12-31
- Previous period: 2025-01-01 to 2025-12-31
- Growth: Year-over-Year comparison

---

### Test 2: Monthly Period

**Request:**
```bash
curl "http://localhost:8000/api/dashboard/sales/overview-kpi?period=monthly&date_from=2026-01-01&date_to=2026-01-31"
```

**Expected:**
- Current period: 2026-01-01 to 2026-01-31
- Previous period: 2025-12-01 to 2025-12-31
- Growth: Month-over-Month comparison

---

### Test 3: Partial Month

**Request:**
```bash
curl "http://localhost:8000/api/dashboard/sales/overview-kpi?period=monthly&date_from=2026-01-01&date_to=2026-01-15"
```

**Expected:**
- Current period: 2026-01-01 to 2026-01-15 (15 hari)
- Previous period: 2025-12-01 to 2025-12-15 (15 hari)
- Growth: Same-length comparison

---

### Test 4: Daily Period

**Request:**
```bash
curl "http://localhost:8000/api/dashboard/sales/overview-kpi?period=daily&date_from=2026-01-01&date_to=2026-01-07"
```

**Expected:**
- Current period: 2026-01-01 to 2026-01-07 (7 hari)
- Previous period: 2025-12-25 to 2025-12-31 (7 hari)
- Growth: Same-length comparison

---

## Contoh Perhitungan

### Scenario 1: Positive Growth (Yearly)

**Data:**
- Current (2026): 150M
- Previous (2025): 100M

**Calculation:**
```
Growth = (150M - 100M) / 100M × 100 = +50%
```

**Response:**
```json
{
  "total_sales_amount": 150000000,
  "sales_growth": 50.00,
  "previous_period": {
    "sales_amount": 100000000
  }
}
```

---

### Scenario 2: Negative Growth (Yearly)

**Data:**
- Current (2026): 80M
- Previous (2025): 100M

**Calculation:**
```
Growth = (80M - 100M) / 100M × 100 = -20%
```

**Response:**
```json
{
  "total_sales_amount": 80000000,
  "sales_growth": -20.00,    // ✅ Negative (benar!)
  "previous_period": {
    "sales_amount": 100000000
  }
}
```

---

### Scenario 3: No Previous Data

**Data:**
- Current (2026): 100M
- Previous (2025): 0 (no data)

**Calculation:**
```
Growth = 0 (karena previous = 0)
```

**Response:**
```json
{
  "total_sales_amount": 100000000,
  "sales_growth": 0,
  "previous_period": {
    "sales_amount": 0
  }
}
```

---

## Keuntungan Fix Ini

1. ✅ **Akurat** - Growth dihitung dengan membandingkan periode yang setara
2. ✅ **Konsisten** - Logika sesuai dengan period yang dipilih user
3. ✅ **Transparan** - Response menyertakan detail previous period
4. ✅ **Fleksibel** - Mendukung daily, monthly, yearly comparison
5. ✅ **Partial Period Support** - Bisa handle partial month dengan benar

---

## Breaking Changes

⚠️ **Response Format Berubah**

Field baru ditambahkan:
```json
{
  "previous_period": {
    "from": "...",
    "to": "...",
    "sales_amount": 0
  }
}
```

Ini **backward compatible** karena hanya menambah field, tidak mengubah field yang sudah ada.

---

**Tanggal Fix**: 2026-01-06  
**Status**: ✅ Fixed  
**Issue**: Sales growth selalu positif meskipun sales turun  
**Solution**: Period-aware previous period calculation
