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
$sheet->setCellValue('C3', 'year');
$sheet->setCellValue('D3', 'period');

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
    $columnIndex = 4 + $day; // E=5, F=6, etc.
    $column = getColumnLetter($columnIndex);
    $sheet->setCellValue($column . '3', $day);
}

// Add sample data (Row 4-5)
// Row 4: January 2026
$sheet->setCellValue('A4', 1);
$sheet->setCellValue('B4', 'RL1EX045133MERD20000');
$sheet->setCellValue('C4', 2026);
$sheet->setCellValue('D4', 1); // January

// Add some daily_use values for January (31 days)
$sheet->setCellValue('E4', 100);  // Day 1
$sheet->setCellValue('F4', 150);  // Day 2
$sheet->setCellValue('G4', 200);  // Day 3
$sheet->setCellValue('H4', 120);  // Day 4
$sheet->setCellValue('I4', 180);  // Day 5

// Row 5: February 2026
$sheet->setCellValue('A5', 2);
$sheet->setCellValue('B5', 'RL1EX045133MERD20000');
$sheet->setCellValue('C5', 2026);
$sheet->setCellValue('D5', 2); // February

// Add some daily_use values for February (28 days in 2026)
$sheet->setCellValue('E5', 90);   // Day 1
$sheet->setCellValue('F5', 110);  // Day 2
$sheet->setCellValue('G5', 130);  // Day 3

// Save to file
$writer = new Xlsx($spreadsheet);
$filename = __DIR__ . '/storage/app/sample_daily_use.xlsx';

// Ensure directory exists
if (!is_dir(__DIR__ . '/storage/app')) {
    mkdir(__DIR__ . '/storage/app', 0755, true);
}

$writer->save($filename);

echo "Sample Excel file created successfully at: {$filename}\n";
echo "File contains:\n";
echo "- Row 4: partno=RL1EX045133MERD20000, year=2026, period=1 (January), with 5 daily values\n";
echo "- Row 5: partno=RL1EX045133MERD20000, year=2026, period=2 (February), with 3 daily values\n";
