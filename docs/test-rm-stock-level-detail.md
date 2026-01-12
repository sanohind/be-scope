# Test Raw Material Stock Level Detail API

## Test Script

```bash
# Test 1: Basic request with WHRM01
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01"

# Test 2: With date range
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&date_from=2026-01-01&date_to=2026-01-31"

# Test 3: With pagination
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM01&page=1&per_page=10"

# Test 4: Invalid warehouse (should return error)
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHFG01"

# Test 5: WHRM02
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHRM02"

# Test 6: WHMT01
curl "http://localhost:8000/api/dashboard/inventory-rev/rm-stock-level-detail?warehouse=WHMT01"
```

## Expected Results

### Test 1-3, 5-6: Success
- Status: 200 OK
- Returns data array with partno details
- Includes daily_use and estimated_consumption
- Proper pagination info

### Test 4: Error
- Status: 400 Bad Request
- Error message: "This endpoint is only available for RM warehouses (WHRM01, WHRM02, WHMT01)"

## Validation Checklist

- [ ] Endpoint accessible
- [ ] Warehouse validation works
- [ ] Pagination works correctly
- [ ] Date filtering works
- [ ] Daily use matching works
- [ ] Estimated consumption calculated correctly
- [ ] Stock status assigned correctly
- [ ] Returns 0 for items without daily use data
- [ ] Sorting by partno works
- [ ] All fields present in response
