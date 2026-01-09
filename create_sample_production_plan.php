<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers (Row 3)
$sheet->setCellValue('A3', 'No.');
$sheet->setCellValue('B3', 'partno');
$sheet->setCellValue('C3', 'divisi');
$sheet->setCellValue('D3', 'year');
$sheet->setCellValue('E3', 'period');

// Helper function to convert column index to letter
function getColumnLetter($columnIndex) {
    $columnLetter = '';
    while ($columnIndex > 0) {
        $modulo = ($columnIndex - 1) % 26;
        $columnLetter = chr(65 + $modulo) . $columnLetter;
        $columnIndex = (int)(($columnIndex - $modulo) / 26);
    }
    return $columnLetter;
}

// Add day headers (1-31)
for ($day = 1; $day <= 31; $day++) {
    $columnIndex = 5 + $day; // F=6, G=7, etc.
    $column = getColumnLetter($columnIndex);
    $sheet->setCellValue($column . '3', $day);
}

// Add sample data (Row 4-5)
// Row 4: January 2026, Divisi A
$sheet->setCellValue('A4', 1);
$sheet->setCellValue('B4', 'RL1EX045133MERD20000');
$sheet->setCellValue('C4', 'A');
$sheet->setCellValue('D4', 2026);
$sheet->setCellValue('E4', 1); // January

// Add some qty_plan values for January (31 days)
$sheet->setCellValue('F4', 500);  // Day 1
$sheet->setCellValue('G4', 600);  // Day 2
$sheet->setCellValue('H4', 550);  // Day 3
$sheet->setCellValue('I4', 700);  // Day 4
$sheet->setCellValue('J4', 650);  // Day 5

// Row 5: February 2026, Divisi B
$sheet->setCellValue('A5', 2);
$sheet->setCellValue('B5', 'RL1EX045133MERD20000');
$sheet->setCellValue('C5', 'B');
$sheet->setCellValue('D5', 2026);
$sheet->setCellValue('E5', 2); // February

// Add some qty_plan values for February (28 days in 2026)
$sheet->setCellValue('F5', 400);  // Day 1
$sheet->setCellValue('G5', 450);  // Day 2
$sheet->setCellValue('H5', 500);  // Day 3

// Save to file
$writer = new Xlsx($spreadsheet);
$filename = __DIR__ . '/storage/app/sample_production_plan.xlsx';

// Ensure directory exists
if (!is_dir(__DIR__ . '/storage/app')) {
    mkdir(__DIR__ . '/storage/app', 0755, true);
}

$writer->save($filename);

echo "Sample Production Plan Excel file created successfully at: {$filename}\n";
echo "File contains:\n";
echo "- Row 4: partno=RL1EX045133MERD20000, divisi=A, year=2026, period=1 (January), with 5 qty_plan values\n";
echo "- Row 5: partno=RL1EX045133MERD20000, divisi=B, year=2026, period=2 (February), with 3 qty_plan values\n";
