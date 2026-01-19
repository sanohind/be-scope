# Testing Asakai Chart API

## Database Setup (Already Done)
- ✅ User dummy created (ID: 1, Name: System Admin, Email: admin@scope.com)
- ✅ 13 Asakai Titles available

## Test Create Chart Data

### Using cURL:
```bash
curl -X POST http://localhost:8000/api/asakai/charts \
  -H "Content-Type: application/json" \
  -d "{\"asakai_title_id\": 1, \"date\": \"2026-01-19\", \"qty\": 5}"
```

### Using Postman:
- Method: POST
- URL: http://localhost:8000/api/asakai/charts
- Headers: Content-Type: application/json
- Body (raw JSON):
```json
{
  "asakai_title_id": 1,
  "date": "2026-01-19",
  "qty": 5
}
```

### Expected Response (Success):
```json
{
  "success": true,
  "message": "Chart data created successfully",
  "data": {
    "id": 1,
    "asakai_title_id": 1,
    "asakai_title": "...",
    "category": "...",
    "date": "2026-01-19",
    "qty": 5,
    "user": "System Admin",
    "user_id": 1,
    "created_at": "2026-01-19 07:30:00"
  }
}
```

## Notes
- User ID is hardcoded to 1 (dummy user) for development
- No authentication required for now
- Each asakai_title_id can only have one chart per date (unique constraint)
