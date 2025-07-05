<?php
require 'vendor/autoload.php'; // Ensure PhpSpreadsheet is loaded

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["timetableData"])) {
    $tableData = json_decode($_POST["timetableData"], true);
    
    if (!$tableData) {
        die("Invalid JSON data");
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = array_keys($tableData[0]);
    $colIndex = 1; // Start at column 1 (A)
    foreach ($headers as $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($colLetter . '1', $header);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true); // Auto-fit column width
        $colIndex++;
    }

    // Fill rows
    $rowNum = 2;
    foreach ($tableData as $row) {
        $colIndex = 1;
        foreach ($headers as $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . $rowNum, $row[$header]);
            $colIndex++;
        }
        $rowNum++;
    }

    // Set headers for download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="SessionsData.xls"');
    header('Cache-Control: max-age=0');

    $writer = new Xls($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
