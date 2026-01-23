# Asakai Reasons PDF Export Implementation

## Overview
This document describes the implementation of the PDF export feature for Asakai Reasons, which generates a paginated PDF with data from the API.

## Key Features

### 1. **Data Population from API**
- The PDF is now populated with actual data from the `exportPdf` method in `AsakaiReasonController.php`
- All filter parameters (asakai_chart_id, section, period, date_from, date_to, search) are respected
- Data is filtered according to user input before generating the PDF

### 2. **Pagination (5 Rows Per Page)**
- Each page displays exactly 5 rows
- If there are fewer than 5 records, empty rows are displayed to fill the page
- If there are more than 5 records, additional pages are automatically generated
- Row numbers continue sequentially across pages (e.g., Page 1: 1-5, Page 2: 6-10)

### 3. **Empty Row Handling**
- When data count is less than 5, remaining rows are shown as empty
- Example: If there are 6 records:
  - Page 1: Rows 1-5 (all with data)
  - Page 2: Row 6 (with data), Rows 7-10 (empty)

### 4. **Multi-Page Support**
- Automatic page breaks between pages
- Each page includes the full header (DEPT, BULAN, TAHUN, Title)
- Each page includes the complete table structure with headers

## Implementation Details

### Controller Changes (`AsakaiReasonController.php`)

```php
public function exportPdf(Request $request)
{
    // ... filtering logic ...
    
    $reasons = $query->orderBy('date', 'desc')->get();
    
    // Determine dept from section filter or leave empty
    $dept = $request->has('section') ? strtoupper($request->section) : '';
    
    $pdf = Pdf::loadView('pdf.asakai_reasons', [
        'reasons' => $reasons,
        'month' => $month,
        'year' => $year,
        'dept' => $dept
    ]);
    
    return $pdf->download('asakai_reasons.pdf');
}
```

### Blade Template Changes (`asakai_reasons.blade.php`)

#### Page Loop Structure
```php
@php
    $rowsPerPage = 5;
    $allReasons = $reasons->toArray();
    $totalReasons = count($allReasons);
    $totalPages = max(1, ceil($totalReasons / $rowsPerPage));
@endphp

@for($currentPage = 1; $currentPage <= $totalPages; $currentPage++)
    <div class="{{ $currentPage < $totalPages ? 'page-break' : '' }}">
        <!-- Header and table for each page -->
    </div>
@endfor
```

#### Row Generation
```php
@php
    $startIndex = ($currentPage - 1) * $rowsPerPage;
    $pageReasons = array_slice($allReasons, $startIndex, $rowsPerPage);
@endphp

@for($i = 0; $i < $rowsPerPage; $i++)
    @php
        $reason = $pageReasons[$i] ?? null;
        $rowNumber = $startIndex + $i + 1;
    @endphp
    <!-- Row content with null-safe operators -->
@endfor
```

## API Parameters

The PDF export respects all the same parameters as the index method:

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `period` | string | Filter period (daily, monthly, yearly) | `monthly` |
| `date_from` | date | Start date for filtering | `2024-01-01` |
| `date_to` | date | End date for filtering | `2024-12-31` |
| `asakai_chart_id` | integer | Filter by specific chart ID | `1` |
| `section` | string | Filter by section | `brazzing` |
| `search` | string | Search by part number | `ABC123` |

## Usage Examples

### Example 1: Export all reasons for a specific chart
```
GET /api/asakai-reasons/export-pdf?asakai_chart_id=1
```

### Example 2: Export reasons for a specific section and period
```
GET /api/asakai-reasons/export-pdf?section=brazzing&period=monthly&date_from=2024-01-01
```

### Example 3: Export with multiple filters
```
GET /api/asakai-reasons/export-pdf?asakai_chart_id=1&section=chassis&period=daily&date_from=2024-01-15
```

## Data Fields

### Populated Fields
- **NO**: Sequential row number
- **TANGGAL**: Date in format `d-M-y` (e.g., 15-Jan-24)
- **Part No**: Part number
- **Part Name**: Part name
- **Problem**: Problem description
- **Qty**: Quantity
- **Dept**: Section/Department
- **Line**: Production line
- **ANALISA PENYEBAB**: Cause analysis (penyebab)
- **PERBAIKAN**: Improvement/fix (perbaikan)
- **Nama Proses**: Process name (currently shows part_name)

### Empty Fields (Not in Database)
- **MONITORING PROBLEM C/M & AUDIT SCHEDULE**: Grid remains empty
- **RESULT**: Empty
- **CONCERN FEEDBACK**: Empty
- **MANAGER**: Empty

## Notes

1. **DEPT Field**: Automatically populated from the `section` filter parameter (converted to uppercase)
2. **Month/Year**: Extracted from the `date_from` parameter based on the period
3. **Monitoring Grid**: The 3-month monitoring grid with 31 days is currently empty as this data is not in the database
4. **Empty Rows**: All empty rows show sequential numbers but no data

## Future Enhancements

Potential improvements that could be made:

1. Add monitoring data if it becomes available in the database
2. Add result, concern feedback, and manager fields to the database and PDF
3. Add page numbers (e.g., "Page 1 of 3")
4. Add summary statistics at the end of the PDF
5. Customize the monitoring grid based on actual month length (28-31 days)
