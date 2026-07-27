<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

bootSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user']['role']) || strcasecmp((string) $_SESSION['user']['role'], 'Admin') !== 0) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Administrator access is required']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
$rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];
if ($rows === []) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'No branch data to export']);
    exit;
}
if (count($rows) > 50000) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'The export is too large']);
    exit;
}

$headers = ['Branch ID', 'Partner', 'First Detected', 'Last Detected', 'Transactions'];
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('New Branch IDs');

foreach ($headers as $index => $heading) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $heading);
}

$rowNumber = 2;
foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $values = [
        trim((string) ($row['branch_id'] ?? '')),
        trim((string) ($row['partner'] ?? '')),
        trim((string) ($row['first_detected'] ?? '')),
        trim((string) ($row['last_detected'] ?? '')),
    ];
    foreach ($values as $columnIndex => $value) {
        $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowNumber;
        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
    }
    $sheet->setCellValue('E' . $rowNumber, (int) ($row['transactions'] ?? 0));
    $rowNumber++;
}

$lastRow = max(2, $rowNumber - 1);
$sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDC3545');
$sheet->getStyle('A1:E' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');
$sheet->getStyle('A1:E' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('E2:E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:E' . $lastRow);
$sheet->getColumnDimension('A')->setWidth(16);
$sheet->getColumnDimension('B')->setWidth(34);
$sheet->getColumnDimension('C')->setWidth(24);
$sheet->getColumnDimension('D')->setWidth(24);
$sheet->getColumnDimension('E')->setWidth(16);

$filename = 'new-branch-ids-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-store');

(new Xlsx($spreadsheet))->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
