# DailyStockController - Inventory Adjustment Update

## Summary
Menambahkan field `adjustment` ke API `/api/stock/daily` untuk melacak transaksi dengan `trans_type = 'Inventory Adjustment'` dari tabel `InventoryTransaction`.

---

## Changes Made

### 1. Added New Query (Query 4)
**Location:** Line ~330-348

Menambahkan query untuk mengambil data Inventory Adjustment:

```php
// Query 4: Get inventory adjustment from InventoryTransaction (trans_type = 'Inventory Adjustment')
$adjustmentRecords = InventoryTransaction::query()
    ->whereIn('warehouse', $warehousesToQuery)
    ->where('trans_type', 'Inventory Adjustment')
    ->whereBetween('trans_date2', [
        $dateRange['date_from_carbon']->startOfDay(),
        $dateRange['date_to_carbon']->endOfDay()
    ])
    ->selectRaw("
        {$transDateFormat} as period_key,
        warehouse,
        SUM(qty) as adjustment_total
    ")
    ->groupByRaw("{$transDateFormat}, warehouse")
    ->get()
    ->keyBy(function($item) {
        return $item->warehouse . '|' . trim($item->period_key);
    });
```

### 2. Updated Key Merging
**Location:** Line ~353-356

Menambahkan `adjustmentRecords` ke dalam merge keys:

```php
$allKeys = collect($onhandRecords->keys())
    ->merge($receiptRecords->keys())
    ->merge($issueRecords->keys())
    ->merge($adjustmentRecords->keys())  // ← NEW
    ->unique();
```

### 3. Updated Data Mapping
**Location:** Line ~357-398

- Menambahkan `$adjustmentRecords` ke use clause
- Mengambil `$adjustmentData` dari records
- Menambahkan `adjustment_total` ke return object

```php
$records = $allKeys->map(function($key) use ($onhandRecords, $receiptRecords, $issueRecords, $adjustmentRecords, ...) {
    // ...
    $adjustmentData = $adjustmentRecords->get($cleanKey);
    
    return (object) [
        // ...
        'adjustment_total' => $adjustmentData ? (int)$adjustmentData->adjustment_total : 0,
    ];
});
```

### 4. Updated Response Structure
**Location:** Line ~488-527

Menambahkan field `adjustment` ke response data:

**For existing data:**
```php
$adjustment = $existing->adjustment_total ?? $existing->getAttribute('adjustment_total') ?? 0;

return [
    // ...
    'adjustment' => (int) $adjustment,
];
```

**For missing periods (zero-filled):**
```php
return [
    // ...
    'adjustment' => 0,
];
```

---

## API Response Format

### Before (Old Format)
```json
{
  "meta": { ... },
  "warehouses": [
    {
      "warehouse": "WHMT01",
      "data": [
        {
          "period": "2026-01-01",
          "period_start": "2026-01-01 00:00:00",
          "period_end": "2026-01-01 23:59:59",
          "granularity": "daily",
          "warehouse": "WHMT01",
          "onhand": 1000,
          "receipt": 500,
          "issue": 200
        }
      ]
    }
  ]
}
```

### After (New Format)
```json
{
  "meta": { ... },
  "warehouses": [
    {
      "warehouse": "WHMT01",
      "data": [
        {
          "period": "2026-01-01",
          "period_start": "2026-01-01 00:00:00",
          "period_end": "2026-01-01 23:59:59",
          "granularity": "daily",
          "warehouse": "WHMT01",
          "onhand": 1000,
          "receipt": 500,
          "issue": 200,
          "adjustment": 50
        }
      ]
    }
  ]
}
```

---

## Data Source

**Table:** `inventory_transaction_erp` (via `InventoryTransaction` model)

**Filter Criteria:**
- `trans_type = 'Inventory Adjustment'`
- `warehouse IN (selected warehouses)`
- `trans_date2 BETWEEN date_from AND date_to`

**Aggregation:**
- `SUM(qty)` grouped by period and warehouse

---

## Field Description

| Field | Type | Description |
|-------|------|-------------|
| `adjustment` | integer | Total quantity dari transaksi Inventory Adjustment dalam periode tersebut |

**Notes:**
- Nilai positif: Penambahan inventory
- Nilai negatif: Pengurangan inventory
- Nilai 0: Tidak ada adjustment atau periode kosong

---

## Testing

### Test Command
```bash
GET /api/stock/daily?period=daily&date_from=2026-01-01&date_to=2026-01-05
```

### Expected Result
✅ Response includes `adjustment` field in each data record  
✅ Field shows integer value (can be positive, negative, or 0)  
✅ Missing periods are filled with `adjustment: 0`

### Test Result
```json
{
  "period": "2026-01-01",
  "period_start": "2026-01-01 00:00:00",
  "period_end": "2026-01-01 23:59:59",
  "granularity": "daily",
  "warehouse": "WHMT01",
  "onhand": 0,
  "receipt": 0,
  "issue": 0,
  "adjustment": 0  ← ✅ NEW FIELD
}
```

---

## Impact Analysis

### ✅ Backward Compatible
- Existing fields remain unchanged
- Only adds new field `adjustment`
- No breaking changes to existing API consumers

### 📊 Data Completeness
Now the API provides complete inventory transaction data:
1. **Onhand** - Current stock level
2. **Receipt** - Incoming stock
3. **Issue** - Outgoing stock
4. **Adjustment** - Stock adjustments (NEW)

### 🔄 Use Cases
The `adjustment` field is useful for:
- Tracking manual inventory corrections
- Identifying discrepancies between physical and system stock
- Audit trail for inventory adjustments
- Reconciliation reports

---

## Files Modified

1. **app/Http/Controllers/Api/DailyStockController.php**
   - Added Query 4 for Inventory Adjustment
   - Updated key merging logic
   - Updated data mapping
   - Updated response structure

---

## Summary

✅ **Query Added:** Inventory Adjustment trans_type  
✅ **Field Added:** `adjustment` in response  
✅ **Testing:** Confirmed working  
✅ **Backward Compatible:** Yes  
✅ **Documentation:** Complete  

The API now provides comprehensive inventory transaction tracking including adjustments! 🎉
