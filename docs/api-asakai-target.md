# Asakai Target API Documentation

## Overview
This API allows management of **Asakai Targets**, which define the target values for specific Asakai Titles (charts) per period (Year/Month). The logic is similar to Min/Max stock management.

**Base URL**: `/api/asakai/charts/target`

---

## 1. Get Asakai Targets
Retrieve a list of Asakai targets with optional filtering.

- **URL**: `/api/asakai/charts/target`
- **Method**: `GET`
- **Authentication**: Required (Sanctum)

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `asakai_title_id` | integer | No | Filter by specific Asakai Title ID |
| `year` | integer | No | Filter by year (e.g., 2025) |
| `period` | integer | No | Filter by period/month (1-12) |
| `per_page` | integer | No | Number of items per page (Default: 50, Max: 100) |

### Success Response
**Code**: `200 OK`
```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "asakai_title_id": 1,
                "year": 2025,
                "period": 1,
                "target": 1500,
                "created_at": "2026-01-21T03:00:00.000000Z",
                "updated_at": "2026-01-21T03:00:00.000000Z"
            }
        ],
        "first_page_url": "...",
        "from": 1,
        "last_page": 1,
        "last_page_url": "...",
        "links": [ ... ],
        "next_page_url": null,
        "path": "...",
        "per_page": 50,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    },
    "message": "Data Target berhasil diambil"
}
```

---

## 2. Store or Update Asakai Target
Create a new target record or update an existing one if the combination of `asakai_title_id`, `year`, and `period` already exists.

- **URL**: `/api/asakai/charts/target`
- **Method**: `POST`
- **Authentication**: Required (Sanctum)

### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `asakai_title_id` | integer | Yes | ID of the Asakai Title |
| `year` | integer | Yes | Year (2000-2100) |
| `period` | integer | Yes | Month period (1-12) |
| `target` | integer | Yes | Target value (Min: 0) |

### Example Request
```json
{
    "asakai_title_id": 1,
    "year": 2025,
    "period": 1,
    "target": 2000
}
```

### Success Response
**Code**: `200 OK`
```json
{
    "success": true,
    "data": {
        "asakai_title_id": 1,
        "year": 2025,
        "period": 1,
        "target": 2000,
        "updated_at": "2026-01-21T03:05:00.000000Z",
        "created_at": "2026-01-21T03:00:00.000000Z",
        "id": 1
    },
    "message": "Data Target berhasil disimpan"
}
```

### Error Response (Validation)
**Code**: `422 Unprocessable Entity`
```json
{
    "success": false,
    "message": "Validation error",
    "errors": {
        "target": [
            "The target field is required."
        ]
    }
}
```

---

## 3. Delete Asakai Target
Delete a specific target record by its ID.

- **URL**: `/api/asakai/charts/target/{id}`
- **Method**: `DELETE`
- **Authentication**: Required (Sanctum)

### URL Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | The ID of the Asakai Target record to delete |

### Success Response
**Code**: `200 OK`
```json
{
    "success": true,
    "message": "Data Target berhasil dihapus"
}
```

### Error Response (Not Found)
**Code**: `404 Not Found`
```json
{
    "success": false,
    "message": "Data tidak ditemukan",
    "error": "Data Target tidak ditemukan"
}
```
