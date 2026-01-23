# Asakai Target - Change to Decimal

## 📋 Summary of Changes

Changed the `target` field in `asakai_targets` table from **integer** to **decimal(10,2)** to support decimal values.

---

## ✅ Files Modified

### 1. **Model: `app/Models/AsakaiTarget.php`**
```php
// BEFORE ❌
'target' => 'integer',

// AFTER ✅
'target' => 'decimal:2',
```

### 2. **Migration (Create): `database/migrations/2026_01_21_103220_create_asakai_targets_table.php`**
```php
// BEFORE ❌
$table->integer('target')->default(0);

// AFTER ✅
$table->decimal('target', 10, 2)->default(0);
```

### 3. **Migration (Alter): `database/migrations/2026_01_23_143741_change_target_to_decimal_in_asakai_targets_table.php`**
New migration file created to change existing column type.

```php
public function up(): void
{
    Schema::table('asakai_targets', function (Blueprint $table) {
        $table->decimal('target', 10, 2)->default(0)->change();
    });
}

public function down(): void
{
    Schema::table('asakai_targets', function (Blueprint $table) {
        $table->integer('target')->default(0)->change();
    });
}
```

### 4. **Controller: `app/Http/Controllers/Api/AsakaiChartController.php`**

#### Method: `storeTarget`
```php
// BEFORE ❌
'target' => 'required|integer|min:0',
...
$target = (int) $request->input('target');

// AFTER ✅
'target' => 'required|numeric|min:0',
...
$target = $request->input('target'); // Keep as numeric
```

---

## 🔧 How to Apply Changes

### Step 1: Run the new migration
```bash
php artisan migrate
```

This will change the `target` column from `integer` to `decimal(10,2)`.

### Step 2: Verify the change
```bash
php artisan tinker
```

Then test:
```php
$target = new App\Models\AsakaiTarget();
$target->asakai_title_id = 1;
$target->year = 2024;
$target->period = 1;
$target->target = 10.5; // Decimal value
$target->save();

// Check the value
App\Models\AsakaiTarget::find($target->id)->target; // Should return "10.50"
```

---

## 📊 Data Type Details

| Aspect | Before | After |
|--------|--------|-------|
| **Database Type** | `INT` | `DECIMAL(10,2)` |
| **Model Cast** | `integer` | `decimal:2` |
| **Validation** | `integer` | `numeric` |
| **Example Values** | `10`, `100` | `10.50`, `100.75` |
| **Max Value** | 2,147,483,647 | 99,999,999.99 |

---

## 🧪 Testing

### API Request Example:
```json
POST /api/asakai/charts/target
{
  "asakai_title_id": 1,
  "year": 2024,
  "period": 1,
  "target": 10.5
}
```

### Expected Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "asakai_title_id": 1,
    "year": 2024,
    "period": 1,
    "target": "10.50",
    "created_at": "2024-01-23T14:37:00.000000Z",
    "updated_at": "2024-01-23T14:37:00.000000Z"
  },
  "message": "Data Target berhasil disimpan"
}
```

---

## ⚠️ Important Notes

1. **Existing Data**: The migration will automatically convert existing integer values to decimal format (e.g., `10` becomes `10.00`)

2. **Precision**: The decimal type supports 2 decimal places (e.g., `10.50`, `99.99`)

3. **Validation**: The API now accepts both integer and decimal values:
   - ✅ `10` (will be stored as `10.00`)
   - ✅ `10.5` (will be stored as `10.50`)
   - ✅ `10.75` (will be stored as `10.75`)
   - ❌ `10.123` (will be rounded to `10.12`)

4. **Backward Compatibility**: Integer values will still work, they'll just be stored with `.00` decimal places

---

## 🔄 Rollback (if needed)

If you need to rollback:
```bash
php artisan migrate:rollback --step=1
```

This will revert the `target` column back to `integer` type.
