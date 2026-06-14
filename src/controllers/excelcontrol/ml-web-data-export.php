<?php
// ml-web-data-export.php
// Export filtered ml_web_data results to Excel (XLSX)

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

header('Content-Type: application/json; charset=utf-8');

try {
    // Read filters (same as ml-web-data-report.php)
    $partner = isset($_GET['partner']) ? trim((string)$_GET['partner']) : '';
    $start_date = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
    $mainzone = isset($_GET['mainzone']) ? trim((string)$_GET['mainzone']) : '';
    $zone = isset($_GET['zone']) ? trim((string)$_GET['zone']) : '';
    $region = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    $area = isset($_GET['area']) ? trim((string)$_GET['area']) : '';
    $branch_name = isset($_GET['branch_name']) ? trim((string)$_GET['branch_name']) : '';
    $branch_id = isset($_GET['branch_id']) ? trim((string)$_GET['branch_id']) : '';
    $type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

    // Treat 'ALL' as empty
    if (strcasecmp($mainzone, 'ALL') === 0) $mainzone = '';
    if (strcasecmp($zone, 'ALL') === 0) $zone = '';
    if (strcasecmp($region, 'ALL') === 0) $region = '';
    if (strcasecmp($area, 'ALL') === 0) $area = '';
    if (strcasecmp($branch_name, 'ALL') === 0) $branch_name = '';
    if (strcasecmp($branch_id, 'ALL') === 0) $branch_id = '';

    if ($partner === '') {
        echo json_encode(['success' => false, 'error' => 'Corporate partner is required']);
        exit;
    }

    if ($start_date === '' || $end_date === '') {
        echo json_encode(['success' => false, 'error' => 'Start date and End date are required']);
        exit;
    }

    $pdo = fileRecDbConnection();

    // Determine available columns and branch name column
    $availableColumns = [];
    try {
        $columnRows = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columnRows as $columnRow) {
            $field = strtolower((string)($columnRow['Field'] ?? ''));
            if ($field !== '') $availableColumns[$field] = true;
        }
    } catch (Throwable $e) {
        $availableColumns = [];
    }
    $branchNameColumn = isset($availableColumns['branch_name']) ? 'branch_name' : (isset($availableColumns['branch']) ? 'branch' : null);

    // Build WHERE clause (same logic)
    $whereSql = ' FROM ml_web_data WHERE partnerName = ?';
    $params = [$partner];

    $dateColumn = 'date_claimed';
    if (strtolower($type) === 'sendout' && isset($availableColumns['date_send'])) {
        $dateColumn = 'date_send';
    }

    if ($start_date !== '') { $whereSql .= ' AND DATE(' . $dateColumn . ') >= ?'; $params[] = $start_date; }
    if ($end_date !== '') { $whereSql .= ' AND DATE(' . $dateColumn . ') <= ?'; $params[] = $end_date; }

    if (isset($availableColumns['date_send'])) {
        if (strtolower($type) === 'payout') {
            $whereSql .= ' AND (date_send IS NULL OR TRIM(COALESCE(date_send, "")) = "")';
        } elseif (strtolower($type) === 'sendout') {
            $whereSql .= ' AND (date_send IS NOT NULL AND TRIM(COALESCE(date_send, "")) != "")';
        }
    }

    if ($mainzone !== '' && isset($availableColumns['mainzone'])) { $whereSql .= ' AND TRIM(COALESCE(mainzone, "")) = TRIM(?)'; $params[] = $mainzone; }
    if ($zone !== '' && isset($availableColumns['zone'])) { $whereSql .= ' AND TRIM(COALESCE(zone, "")) = TRIM(?)'; $params[] = $zone; }
    if ($region !== '' && isset($availableColumns['region'])) { $whereSql .= ' AND TRIM(COALESCE(region, "")) = TRIM(?)'; $params[] = $region; }
    if ($area !== '' && isset($availableColumns['area'])) { $whereSql .= ' AND TRIM(COALESCE(area, "")) = TRIM(?)'; $params[] = $area; }
    if ($branch_name !== '' && $branchNameColumn !== null) { $whereSql .= ' AND LOWER(COALESCE(`' . $branchNameColumn . '`, "")) LIKE ?'; $params[] = '%' . strtolower($branch_name) . '%'; }
    if ($branch_id !== '' && isset($availableColumns['branch_id'])) { $whereSql .= ' AND LOWER(COALESCE(branch_id, "")) LIKE ?'; $params[] = '%' . strtolower($branch_id) . '%'; }

    // Query: select all filtered rows, ordered by currency (PHP first, USD second), then date desc
    $selectCols = '';
    $selectColsArr = [];
    if (isset($availableColumns['date_send'])) $selectColsArr[] = 'date_send';
    if (isset($availableColumns['date_claimed'])) $selectColsArr[] = 'date_claimed';
    $selectColsArr = array_merge($selectColsArr, ['no', 'control_series_no', 'ccref_no', 'currency', 'amount', 'sender_name', 'beneficiary_receiver']);
    if (strtolower($type) === 'sendout' && isset($availableColumns['charge'])) {
        $selectColsArr[] = 'charge';
    } else {
        $selectColsArr[] = 'operator';
    }
    $selectColsArr[] = isset($availableColumns['branch']) ? 'branch' : 'branch_name';
    $selectCols = implode(', ', $selectColsArr);

    // Order such that PHP first, USD second, then others; within currency, date desc
    $orderExpr = "(CASE WHEN UPPER(TRIM(currency)) = 'PHP' THEN 1 WHEN UPPER(TRIM(currency)) = 'USD' THEN 2 ELSE 3 END), " . $dateColumn . " DESC";

    $sql = 'SELECT ' . $selectCols . $whereSql . ' ORDER BY ' . $orderExpr;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows) || count($rows) === 0) {
        // No data to export
        echo json_encode(['success' => false, 'error' => 'No transactions available to export']);
        exit;
    }

    // Compute totals (same filters)
    $totalsSql = 'SELECT '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "PHP" THEN COALESCE(amount,0) ELSE 0 END), 0) AS php_total, '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "USD" THEN COALESCE(amount,0) ELSE 0 END), 0) AS usd_total, '
        . 'COALESCE(SUM(CASE WHEN TRIM(COALESCE(charge, "")) = "" THEN 0 ELSE charge END), 0) AS charge_total '
        . $whereSql;
    $totStmt = $pdo->prepare($totalsSql);
    $totStmt->execute($params);
    $totRow = $totStmt->fetch(PDO::FETCH_ASSOC);
    $php_total = isset($totRow['php_total']) ? (float)$totRow['php_total'] : 0.0;
    $usd_total = isset($totRow['usd_total']) ? (float)$totRow['usd_total'] : 0.0;
    $charge_total = isset($totRow['charge_total']) ? (float)$totRow['charge_total'] : 0.0;

    // Build filename (sanitize)
    function sanitize_filename($s) {
        $s = preg_replace('/[\\\/\:\*\?"\<\>\|]+/', '_', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        $s = str_replace(' ', ' ', $s);
        return $s;
    }

    $filePartner = preg_replace('/\s+/', '_', strtoupper($partner));
    $fileStart = $start_date;
    $fileEnd = $end_date;
    $fileType = $type === '' ? 'ALL' : strtoupper($type);
    $fileBranch = $branch_name !== '' ? strtoupper($branch_name) : '';

    $filenameParts = [$filePartner, $fileStart . '_to_' . $fileEnd, $fileType];
    if ($fileBranch !== '') $filenameParts[] = $fileBranch;
    $filename = implode('_', $filenameParts) . '.xlsx';
    $filename = sanitize_filename($filename);

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transactions');

    // Header row
    $headers = ['No.', 'Control Series', ucwords(str_replace('_', ' ', $dateColumn)), 'CCREF NO', 'Currency', 'Amount', 'Sender', 'Beneficiary', strtolower($type) === 'sendout' ? 'Charge' : 'Operator', 'Branch'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    // Bold header and style
    $sheet->getStyle('A1:J1')->getFont()->setBold(true);
    $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->freezePane('A2');

    // Fill rows
    $rowIndex = 2;
    foreach ($rows as $r) {
        $sheet->setCellValue('A' . $rowIndex, $r['no'] ?? '');
        $sheet->setCellValue('B' . $rowIndex, $r['control_series_no'] ?? '');
        $dateVal = $r[$dateColumn] ?? ($r['date_claimed'] ?? '');
        $sheet->setCellValue('C' . $rowIndex, $dateVal);
        $sheet->setCellValue('D' . $rowIndex, $r['ccref_no'] ?? '');
        $sheet->setCellValue('E' . $rowIndex, $r['currency'] ?? '');
        $sheet->setCellValue('F' . $rowIndex, is_null($r['amount']) ? 0 : (float)$r['amount']);
        $sheet->setCellValue('G' . $rowIndex, $r['sender_name'] ?? '');
        $sheet->setCellValue('H' . $rowIndex, $r['beneficiary_receiver'] ?? '');
        $sheet->setCellValue('I' . $rowIndex, strtolower($type) === 'sendout' ? ($r['charge'] ?? '') : ($r['operator'] ?? ''));
        $sheet->setCellValue('J' . $rowIndex, $r['branch'] ?? ($r['branch_name'] ?? ''));
        $rowIndex++;
    }

    $lastDataRow = $rowIndex - 1;

    // Format Amount column with commas and 2 decimals
    $sheet->getStyle("F2:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Apply borders to table
    $sheet->getStyle("A1:J{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Autosize columns
    foreach (range('A', 'J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Totals area (leave one empty row)
    $totRowStart = $lastDataRow + 2;
    $sheet->setCellValue('E' . $totRowStart, 'PHP Total:');
    $sheet->setCellValue('F' . $totRowStart, $php_total);
    $sheet->setCellValue('E' . ($totRowStart + 1), 'USD Total:');
    $sheet->setCellValue('F' . ($totRowStart + 1), $usd_total);
    if (strtolower($type) === 'sendout') {
        $sheet->setCellValue('E' . ($totRowStart + 2), 'Charge Total:');
        $sheet->setCellValue('F' . ($totRowStart + 2), $charge_total);
    }
    $totEndRow = strtolower($type) === 'sendout' ? ($totRowStart + 2) : ($totRowStart + 1);
    $sheet->getStyle('F' . $totRowStart . ':F' . $totEndRow)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('E' . $totRowStart . ':F' . $totEndRow)->getFont()->setBold(true);

    // Output spreadsheet to browser
    // Clear any previous output
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
