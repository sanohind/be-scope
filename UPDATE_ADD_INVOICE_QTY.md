# Update: Menambahkan invoice_qty ke Sales Order Fulfillment

## Perubahan

Method `salesOrderFulfillment` sekarang mengembalikan **2 field quantity**:
1. ✅ `delivered_qty` - Jumlah yang sudah dikirim
2. ✅ `invoice_qty` - Jumlah yang di-invoice (BARU)

---

## Response Format

### Sebelum
```json
{
  "data": [
    {
      "period": "2026-01-01",
      "delivered_qty": "75000.0"
    }
  ]
}
```

### Sesudah
```json
{
  "data": [
    {
      "period": "2026-01-01",
      "delivered_qty": "75000.0",
      "invoice_qty": "80000.0"        // ✅ BARU
    }
  ]
}
```

---

## Kegunaan

### 1. **Analisis Fulfillment Rate**
Membandingkan antara yang di-invoice vs yang sudah dikirim:

```javascript
const fulfillmentRate = (delivered_qty / invoice_qty) * 100;
// Contoh: (75000 / 80000) * 100 = 93.75%
```

### 2. **Identifikasi Outstanding Delivery**
Mengetahui berapa banyak yang belum dikirim:

```javascript
const outstanding = invoice_qty - delivered_qty;
// Contoh: 80000 - 75000 = 5000 (belum dikirim)
```

### 3. **Visualisasi Comparison**
Frontend bisa membuat chart yang membandingkan:
- Bar untuk `invoice_qty` (target)
- Bar untuk `delivered_qty` (actual)

---

## Contoh Penggunaan

### Request
```bash
GET /api/dashboard/sales/order-fulfillment?period=daily&date_from=2026-01-01&date_to=2026-01-07
```

### Response
```json
{
  "data": [
    {
      "period": "2026-01-01",
      "delivered_qty": "75000.0",
      "invoice_qty": "80000.0"
    },
    {
      "period": "2026-01-02",
      "delivered_qty": "82000.0",
      "invoice_qty": "85000.0"
    },
    {
      "period": "2026-01-03",
      "delivered_qty": "78000.0",
      "invoice_qty": "78000.0"
    }
  ],
  "filter_metadata": {
    "period": "daily",
    "date_field": "invoice_date",
    "date_from": "2026-01-01",
    "date_to": "2026-01-07"
  }
}
```

### Analisis dari Response
- **01 Jan**: 75k/80k = 93.75% fulfillment, 5k outstanding
- **02 Jan**: 82k/85k = 96.47% fulfillment, 3k outstanding
- **03 Jan**: 78k/78k = 100% fulfillment, 0 outstanding ✅

---

## Implementasi Frontend

### Chart.js Example
```javascript
const chartData = {
  labels: data.map(d => d.period),
  datasets: [
    {
      label: 'Invoice Qty (Target)',
      data: data.map(d => parseFloat(d.invoice_qty)),
      backgroundColor: 'rgba(54, 162, 235, 0.5)',
      borderColor: 'rgba(54, 162, 235, 1)',
      borderWidth: 1
    },
    {
      label: 'Delivered Qty (Actual)',
      data: data.map(d => parseFloat(d.delivered_qty)),
      backgroundColor: 'rgba(75, 192, 192, 0.5)',
      borderColor: 'rgba(75, 192, 192, 1)',
      borderWidth: 1
    }
  ]
};
```

### Calculate Metrics
```javascript
const metrics = data.map(item => ({
  period: item.period,
  invoiceQty: parseFloat(item.invoice_qty),
  deliveredQty: parseFloat(item.delivered_qty),
  fulfillmentRate: (parseFloat(item.delivered_qty) / parseFloat(item.invoice_qty) * 100).toFixed(2),
  outstanding: parseFloat(item.invoice_qty) - parseFloat(item.delivered_qty)
}));
```

---

## Catatan Teknis

### Merge Logic
Sama seperti `delivered_qty`, `invoice_qty` juga di-sum ketika ada duplikasi period:

```php
$totalInvoiceQty = $group->sum(function ($item) {
    return (float) $item->invoice_qty;
});
```

### Database Support
Field `invoice_qty` tersedia di:
- ✅ `SoInvoiceLine` (ERP)
- ✅ `SoInvoiceLine2` (ERP2)

---

**Tanggal Update**: 2026-01-06  
**Status**: ✅ Implemented  
**Breaking Change**: ❌ No (backward compatible - hanya menambah field)
