# Dashboard4Controller API Standardization

## Ringkasan Perubahan

File `Dashboard4Controller.php` telah distandarisasi untuk konsistensi parameter dan response format mengikuti pola yang digunakan di `Dashboard3Controller.php`.

## Perubahan Utama

### 1. **Standarisasi Parameter**

Semua method API sekarang menggunakan parameter yang konsisten:

| Parameter | Tipe | Default | Deskripsi |
|-----------|------|---------|-----------|
| `period` | string | `monthly` | Filter periode: `daily`, `monthly`, `yearly` |
| `date_from` | date | Start of current month | Tanggal mulai filter (format: YYYY-MM-DD) |
| `date_to` | date | Today | Tanggal akhir filter (format: YYYY-MM-DD) |
| `limit` | integer | Varies | Jumlah record yang ditampilkan |
| `customer` | string | - | Filter berdasarkan customer (bp_name) |
| `product_type` | string | - | Filter berdasarkan tipe produk |
| `group_by` | string | - | Alias untuk parameter `period` |

### 2. **Response Format Konsisten**

Semua method sekarang mengembalikan response dengan format:

```json
{
  "data": [...],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-06"
  }
}
```

### 3. **Penggunaan Helper Methods**

Semua method sekarang menggunakan helper methods dari `ApiController`:

- `applyDateRangeFilter($query, $request, $dateField)` - Apply date range filter
- `getDateFormatByPeriod($period, $dateField, $query)` - Get SQL date format
- `getPeriodMetadata($request, $dateField)` - Generate filter metadata
- `ensureDateRange($request, $defaultStart, $defaultEnd)` - Ensure date range exists

### 4. **Default Date Range**

Semua method yang sebelumnya tidak memiliki date range filter sekarang memiliki default:
- **Default Start**: Awal bulan ini (`now()->startOfMonth()`)
- **Default End**: Hari ini (`now()`)

---

## Detail Perubahan Per Method

### ✅ Chart 4.1: `salesOverviewKpi`

**Perubahan:**
- ❌ Removed: Parameter `year` dan `month`
- ✅ Added: Parameter `date_from`, `date_to`, `period`
- ✅ Added: Default date range (current month)
- ✅ Changed: Response menggunakan `filter_metadata`

**Contoh Request:**
```
GET /api/dashboard/sales/overview-kpi?date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.2: `revenueTrend`

**Perubahan:**
- ✅ Maintained: Semua parameter existing
- ✅ Changed: Response menggunakan `filter_metadata`

**Contoh Request:**
```
GET /api/dashboard/sales/revenue-trend?period=monthly&date_from=2025-01-01&date_to=2025-12-31&customer=PT%20ABC
```

---

### ✅ Chart 4.3: `topCustomersByRevenue`

**Perubahan:**
- ✅ Maintained: Semua parameter existing
- ✅ Changed: Response menggunakan `filter_metadata`

**Contoh Request:**
```
GET /api/dashboard/sales/top-customers-by-revenue?limit=20&date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.4: `salesByProductType`

**Perubahan:**
- ✅ Added: Default date range (current month)
- ✅ Changed: Response menggunakan `filter_metadata`

**Contoh Request:**
```
GET /api/dashboard/sales/by-product-type?date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.5: `shipmentStatusTracking`

**Perubahan:**
- ✅ Added: Parameter `date_from`, `date_to`, `period`
- ✅ Added: Default date range (current month)
- ✅ Added: Date range filter pada query
- ✅ Changed: Response menggunakan `filter_metadata`
- ✅ Changed: Method signature dari `()` menjadi `(Request $request)`

**Contoh Request:**
```
GET /api/dashboard/sales/shipment-status-tracking?date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.6: `invoiceStatusDistribution`

**Perubahan:**
- ✅ Maintained: Semua parameter existing
- ✅ Changed: Response menggunakan `filter_metadata`

**Contoh Request:**
```
GET /api/dashboard/sales/invoice-status-distribution?group_by=customer&date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.7: `deliveryPerformance`

**Perubahan:**
- ✅ Added: Parameter `date_from`, `date_to`, `period`
- ✅ Added: Default date range (current month)
- ✅ Added: Date range filter pada query
- ✅ Changed: Response menggunakan `filter_metadata`
- ✅ Changed: Method signature dari `()` menjadi `(Request $request)`
- ✅ Changed: Dari menggunakan semua data historis menjadi filtered by date range

**Contoh Request:**
```
GET /api/dashboard/sales/delivery-performance?date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.8: `salesOrderFulfillment`

**Perubahan:**
- ✅ Maintained: Semua parameter existing
- ✅ Changed: Response menggunakan `getPeriodMetadata` helper

**Contoh Request:**
```
GET /api/dashboard/sales/order-fulfillment?period=monthly&product_type=FG&date_from=2026-01-01&date_to=2026-12-31
```

---

### ✅ Chart 4.9: `topSellingProducts`

**Perubahan:**
- ✅ Added: Default date range (current month)
- ✅ Added: Dokumentasi parameter yang lebih lengkap
- ✅ Changed: Response menggunakan `filter_metadata`
- ✅ Changed: Menggunakan `applyDateRangeFilter` helper

**Contoh Request:**
```
GET /api/dashboard/sales/top-selling-products?limit=20&product_type=FG&date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.10: `revenueByCurrency`

**Perubahan:**
- ✅ Added: Parameter `date_from`, `date_to`, `period`
- ✅ Added: Default date range (current month)
- ✅ Changed: Response menggunakan `filter_metadata`
- ✅ Changed: Method signature dari `()` menjadi `(Request $request)`
- ✅ Removed: Hardcoded logic untuk bulan sebelumnya (`$actualMonth = $currentMonth - 1`)
- ✅ Changed: Dari `whereYear` + `whereMonth` menjadi `whereBetween`

**Contoh Request:**
```
GET /api/dashboard/sales/revenue-by-currency?date_from=2026-01-01&date_to=2026-01-31
```

---

### ✅ Chart 4.11: `monthlySalesComparison`

**Perubahan:**
- ✅ Maintained: Semua parameter existing
- ✅ Changed: Response menggunakan `getPeriodMetadata` helper

**Contoh Request:**
```
GET /api/dashboard/sales/monthly-sales-comparison?date_from=2025-01-01&date_to=2026-01-31&customer=PT%20ABC
```

---

### ✅ `getAllData`

**Perubahan:**
- ✅ Updated: Semua method calls sekarang pass `$request` parameter
- ✅ Fixed: `shipmentStatusTracking($request)`
- ✅ Fixed: `deliveryPerformance($request)`
- ✅ Fixed: `revenueByCurrency($request)`

**Contoh Request:**
```
GET /api/dashboard/sales/all-data?date_from=2026-01-01&date_to=2026-01-31&period=monthly
```

---

## Backward Compatibility

### ⚠️ Breaking Changes

1. **`salesOverviewKpi`**: Parameter `year` dan `month` tidak lagi didukung. Gunakan `date_from` dan `date_to`.

2. **Method Signatures Changed**:
   - `shipmentStatusTracking()` → `shipmentStatusTracking(Request $request)`
   - `deliveryPerformance()` → `deliveryPerformance(Request $request)`
   - `revenueByCurrency()` → `revenueByCurrency(Request $request)`

3. **Response Format**: Semua response sekarang memiliki `filter_metadata` instead of custom fields seperti `date_range`, `period`, dll.

### ✅ Non-Breaking Changes

- Semua parameter bersifat optional dengan default values
- Jika tidak ada parameter yang dikirim, akan menggunakan default (current month)
- Filter tambahan (`customer`, `product_type`) tetap optional

---

## Testing Recommendations

### 1. Test Default Behavior
```bash
# Should return current month data
GET /api/dashboard/sales/overview-kpi
GET /api/dashboard/sales/shipment-status-tracking
GET /api/dashboard/sales/delivery-performance
```

### 2. Test Date Range Filter
```bash
# Should return data for January 2026
GET /api/dashboard/sales/overview-kpi?date_from=2026-01-01&date_to=2026-01-31
```

### 3. Test Period Grouping
```bash
# Daily grouping
GET /api/dashboard/sales/revenue-trend?period=daily&date_from=2026-01-01&date_to=2026-01-31

# Monthly grouping
GET /api/dashboard/sales/revenue-trend?period=monthly&date_from=2026-01-01&date_to=2026-12-31

# Yearly grouping
GET /api/dashboard/sales/revenue-trend?period=yearly&date_from=2024-01-01&date_to=2026-12-31
```

### 4. Test Database Selection Logic
```bash
# Should use SoInvoiceLine (year >= 2025)
GET /api/dashboard/sales/overview-kpi?date_from=2025-01-01&date_to=2025-12-31

# Should use SoInvoiceLine2 (year < 2025)
GET /api/dashboard/sales/overview-kpi?date_from=2024-01-01&date_to=2024-12-31
```

### 5. Test getAllData
```bash
# Should return all dashboard data with consistent filters
GET /api/dashboard/sales/all-data?date_from=2026-01-01&date_to=2026-01-31&period=monthly
```

---

## Migration Guide for Frontend

### Before (Old API)
```javascript
// Old way - using year and month
fetch('/api/dashboard/sales/overview-kpi?year=2026&month=1')

// Old way - no parameters
fetch('/api/dashboard/sales/shipment-status-tracking')
fetch('/api/dashboard/sales/delivery-performance')
fetch('/api/dashboard/sales/revenue-by-currency')
```

### After (New API)
```javascript
// New way - using date_from and date_to
fetch('/api/dashboard/sales/overview-kpi?date_from=2026-01-01&date_to=2026-01-31')

// New way - with default current month
fetch('/api/dashboard/sales/shipment-status-tracking')
// Or with custom date range
fetch('/api/dashboard/sales/shipment-status-tracking?date_from=2026-01-01&date_to=2026-01-31')

// Same for other methods
fetch('/api/dashboard/sales/delivery-performance?date_from=2026-01-01&date_to=2026-01-31')
fetch('/api/dashboard/sales/revenue-by-currency?date_from=2026-01-01&date_to=2026-01-31')
```

### Response Handling
```javascript
// Old response format
{
  "data": [...],
  "period": "2026-01",
  "date_range": {
    "from": "2026-01-01",
    "to": "2026-01-31"
  }
}

// New response format
{
  "data": [...],
  "filter_metadata": {
    "period": "monthly",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-31"
  }
}
```

---

## Benefits of Standardization

1. ✅ **Konsistensi**: Semua endpoint menggunakan parameter yang sama
2. ✅ **Fleksibilitas**: Semua endpoint bisa difilter dengan date range
3. ✅ **Maintainability**: Menggunakan helper methods yang reusable
4. ✅ **Predictability**: Response format yang konsisten
5. ✅ **Documentation**: Parameter terdokumentasi dengan jelas di PHPDoc
6. ✅ **Default Values**: Semua parameter memiliki default yang masuk akal

---

## Notes

- Logika pemilihan database (SoInvoiceLine vs SoInvoiceLine2) tetap dipertahankan
- Helper methods `getModelByYear`, `getQueryForDateRange`, `ensureDateRange`, dan `buildRangeQueryWithFallback` tetap digunakan
- Semua perubahan mengikuti pola yang sama dengan Dashboard3Controller
- Filter metadata sekarang di-generate otomatis oleh `getPeriodMetadata` helper

---

**Tanggal Refactoring**: 2026-01-06  
**Refactored by**: Antigravity AI Assistant
