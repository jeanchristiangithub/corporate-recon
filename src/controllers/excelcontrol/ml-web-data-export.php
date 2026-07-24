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
    $reference_id = isset($_GET['reference_id']) ? trim((string)$_GET['reference_id']) : '';
    $type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
    $normalizedType = strtolower($type);
    $currency = strtoupper(trim((string)($_GET['currency'] ?? '')));

    // Treat 'ALL' as empty
    if (strcasecmp($mainzone, 'ALL') === 0) $mainzone = '';
    if (strcasecmp($zone, 'ALL') === 0) $zone = '';
    if (strcasecmp($region, 'ALL') === 0) $region = '';
    if (strcasecmp($area, 'ALL') === 0) $area = '';
    if (strcasecmp($branch_name, 'ALL') === 0) $branch_name = '';
    if (strcasecmp($branch_id, 'ALL') === 0) $branch_id = '';

    if ($reference_id === '' && ($partner === '' || $start_date === '' || $end_date === '')) {
        echo json_encode(['success' => false, 'error' => 'Corporate Partner, Start date, and End date are required.']);
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
    $whereSql = ' FROM ml_web_data WHERE 1 = 1';
    $params = [];
    if ($partner !== '') {
        $whereSql .= ' AND partnerName = ?';
        $params[] = $partner;
    }

    $dateColumn = 'date_claimed';
    if (in_array($normalizedType, ['sendout', 'sendout_cancelled'], true) && isset($availableColumns['date_send'])) {
        $dateColumn = 'date_send';
    }

    if ($start_date !== '') { $whereSql .= ' AND DATE(' . $dateColumn . ') >= ?'; $params[] = $start_date; }
    if ($end_date !== '') { $whereSql .= ' AND DATE(' . $dateColumn . ') <= ?'; $params[] = $end_date; }

    if (isset($availableColumns['date_send'])) {
        if ($normalizedType === 'payout') {
            $whereSql .= ' AND (date_send IS NULL OR TRIM(COALESCE(date_send, "")) = "")';
        } elseif ($normalizedType === 'sendout') {
            $whereSql .= ' AND (date_send IS NOT NULL AND TRIM(COALESCE(date_send, "")) != "")';
        }
    }

    if ($normalizedType === 'payout_cancelled') {
        $whereSql .= ' AND (date_claimed IS NOT NULL AND TRIM(COALESCE(date_claimed, "")) != "")';
        $whereSql .= ' AND (date_cancelled IS NOT NULL AND TRIM(COALESCE(date_cancelled, "")) != "")';
    } elseif ($normalizedType === 'sendout_cancelled') {
        $whereSql .= ' AND (date_send IS NOT NULL AND TRIM(COALESCE(date_send, "")) != "")';
        $whereSql .= ' AND (date_cancelled IS NOT NULL AND TRIM(COALESCE(date_cancelled, "")) != "")';
    }

    if ($mainzone !== '' && isset($availableColumns['mainzone'])) { $whereSql .= ' AND TRIM(COALESCE(mainzone, "")) = TRIM(?)'; $params[] = $mainzone; }
    if ($zone !== '' && isset($availableColumns['zone'])) { $whereSql .= ' AND TRIM(COALESCE(zone, "")) = TRIM(?)'; $params[] = $zone; }
    if ($region !== '' && isset($availableColumns['region'])) { $whereSql .= ' AND TRIM(COALESCE(region, "")) = TRIM(?)'; $params[] = $region; }
    if ($area !== '' && isset($availableColumns['area'])) { $whereSql .= ' AND TRIM(COALESCE(area, "")) = TRIM(?)'; $params[] = $area; }
    if ($branch_name !== '' && $branchNameColumn !== null) { $whereSql .= ' AND LOWER(COALESCE(`' . $branchNameColumn . '`, "")) LIKE ?'; $params[] = '%' . strtolower($branch_name) . '%'; }
    if ($branch_id !== '' && isset($availableColumns['branch_id'])) { $whereSql .= ' AND LOWER(COALESCE(branch_id, "")) LIKE ?'; $params[] = '%' . strtolower($branch_id) . '%'; }
    if ($reference_id !== '' && isset($availableColumns['ccref_no'])) { $whereSql .= ' AND TRIM(COALESCE(ccref_no, "")) = ?'; $params[] = $reference_id; }
    if (in_array($currency, ['PHP', 'USD'], true) && isset($availableColumns['currency'])) { $whereSql .= ' AND UPPER(TRIM(currency)) = ?'; $params[] = $currency; }

    // Query: select all filtered rows, ordered by currency (PHP first, USD second), then date desc
    $selectCols = '';
    $selectColsArr = [];
    if (isset($availableColumns['date_send'])) $selectColsArr[] = 'date_send';
    if (isset($availableColumns['date_claimed'])) $selectColsArr[] = 'date_claimed';
    $selectColsArr = array_merge($selectColsArr, ['control_series_no', 'kptn', 'ccref_no', 'currency', 'amount', 'sender_name', 'beneficiary_receiver', 'operator']);
    if (isset($availableColumns['branch_id'])) {
        $selectColsArr[] = 'branch_id';
    }
    if (in_array($normalizedType, ['sendout', 'sendout_cancelled'], true) && isset($availableColumns['charge'])) {
        $selectColsArr[] = 'charge';
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
        $s = preg_replace('#[\\\\/:*?"<>|]+#', '_', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));
        $s = str_replace(' ', ' ', $s);
        return $s;
    }

    $filePartner = $partner !== '' ? preg_replace('/\s+/', '_', strtoupper($partner)) : 'ALL_PARTNERS';
    $fileStart = $start_date;
    $fileEnd = $end_date;
    $fileType = $type === '' ? 'ALL' : strtoupper($type);
    $fileCurrency = in_array($currency, ['PHP', 'USD'], true) ? $currency : 'ALL';
    $fileBranch = $branch_name !== '' ? strtoupper($branch_name) : '';

    $filenameParts = [$filePartner, $fileStart . '_to_' . $fileEnd, $fileType, $fileCurrency];
    if ($fileBranch !== '') $filenameParts[] = $fileBranch;
    $filename = implode('_', $filenameParts) . '.xlsx';
    $filename = sanitize_filename($filename);

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Transactions');

    // Summary headers (rows 1-6)
    $reportTitle = 'KPX Web Data Transactions';
    $sheet->setCellValue('A1', $reportTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sheet->setCellValue('A2', 'Corporate Partner: ' . ($partner !== '' ? $partner : 'ALL'));
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

    $transactionTypeDisplay = $type === '' ? 'ALL' : strtoupper($type);
    $sheet->setCellValue('A3', 'Transaction Type: ' . $transactionTypeDisplay);
    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

    $sheet->setCellValue('A4', $end_date !== ''
        ? 'Report Date: ' . date('F d, Y', strtotime($end_date))
        : 'CCREF NO: ' . $reference_id);
    $sheet->getStyle('A4')->getFont()->setSize(10);

    $sheet->setCellValue('A5', 'Volume: ' . number_format(count($rows)) . ' transactions');
    $sheet->setCellValue('A6', 'Principal: PHP: ' . number_format(abs($php_total), 2, '.', ',') . ' USD: ' . number_format(abs($usd_total), 2, '.', ','));
    if (in_array($normalizedType, ['sendout', 'sendout_cancelled'], true)) {
        $sheet->setCellValue('A7', 'Charge: PHP: ' . number_format(abs($charge_total), 2, '.', ','));
    }
    $sheet->getStyle('A5:A7')->getFont()->setBold(true);

    // Header row
    $isSendout = in_array($normalizedType, ['sendout', 'sendout_cancelled'], true);
    $headerRowNum = $isSendout ? 9 : 8;
    $headers = [$dateColumn === 'date_send' ? 'Date Send' : 'Date Claimed', 'Branch', 'Branch ID', 'Control Series', 'KPTN', 'CCREF NO', 'Amount'];
    if ($isSendout) {
        $headers[] = 'Charge';
    }
    $headers = array_merge($headers, ['Currency', 'Sender', 'Beneficiary', 'Operator']);
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $headerRowNum, $h);
        $col++;
    }

    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));

    // Bold header and style
    $sheet->getStyle('A' . $headerRowNum . ':' . $lastCol . $headerRowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRowNum . ':' . $lastCol . $headerRowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->freezePane('A' . ($headerRowNum + 1));

    // Fill rows
    $rowIndex = $headerRowNum + 1;
    foreach ($rows as $r) {
        $dateVal = $r[$dateColumn] ?? ($r['date_claimed'] ?? '');
        $values = [
            $dateVal,
            $r['branch'] ?? ($r['branch_name'] ?? ''),
            $r['branch_id'] ?? '',
            $r['control_series_no'] ?? '',
            $r['kptn'] ?? '',
            $r['ccref_no'] ?? '',
            is_null($r['amount'] ?? null) ? 0 : (float)$r['amount'],
        ];
        if ($isSendout) {
            $values[] = is_null($r['charge'] ?? null) || $r['charge'] === '' ? 0 : (float)$r['charge'];
        }
        $values = array_merge($values, [
            $r['currency'] ?? '',
            $r['sender_name'] ?? '',
            $r['beneficiary_receiver'] ?? '',
            $r['operator'] ?? '',
        ]);
        foreach ($values as $idx => $value) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue($columnLetter . $rowIndex, $value);
        }
        $rowIndex++;
    }

    $lastDataRow = $rowIndex - 1;

    // Format amount/charge columns with commas and 2 decimals
    $dataStartRow = $headerRowNum + 1;
    $sheet->getStyle("G{$dataStartRow}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    if ($isSendout) {
        $sheet->getStyle("H{$dataStartRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    // Apply borders to table
    $sheet->getStyle("A{$headerRowNum}:{$lastCol}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Autosize columns
    foreach (range('A', $lastCol) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

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
