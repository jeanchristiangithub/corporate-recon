<?php
// partner-data-export.php
// Export MONEYGRAM partner data to Excel (XLSX)

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

header('Content-Type: application/json; charset=utf-8');

try {
    $partner = isset($_GET['partner']) ? trim((string)$_GET['partner']) : '';
    $start_date = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    if ($partner === '') {
        echo json_encode(['success' => false, 'error' => 'Corporate partner is required']);
        exit;
    }

    if (strtoupper(trim($partner)) !== 'MONEYGRAM') {
        echo json_encode(['success' => false, 'error' => 'This export endpoint only supports MONEYGRAM']);
        exit;
    }

    if ($start_date === '' || $end_date === '') {
        echo json_encode(['success' => false, 'error' => 'Start date and End date are required']);
        exit;
    }

    $pdo = fileRecDbConnection();

    // Determine existing columns to safely map request params
    $colRows = $pdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($colRows as $c) {
        $existing[(string)$c['Field']] = true;
    }

    // Partner column candidates (pick first existing)
    $partnerColCandidates = ['partnerName','partner_name','corporate_partner','partner','company_name'];
    $partnerCol = null;
    foreach ($partnerColCandidates as $pc) { if (isset($existing[$pc])) { $partnerCol = $pc; break; } }

    // Date column: prefer tran_date
    $dateCol = isset($existing['tran_date']) ? 'tran_date' : (isset($existing['date']) ? 'date' : (isset($existing['date_claimed']) ? 'date_claimed' : null));

    // Accept optional UI filters
    $branchFilter = isset($_GET['branch']) ? trim((string)$_GET['branch']) : '';
    $legacyFilter = isset($_GET['legacy_id']) ? trim((string)$_GET['legacy_id']) : '';
    $agentFilter = isset($_GET['agent_name']) ? trim((string)$_GET['agent_name']) : '';
    $typeFilter = isset($_GET['tran_type']) ? trim((string)$_GET['tran_type']) : '';

    // Columns to export (in required order)
    $cols = ['transaction_id','reference_id','tran_date','tran_type','base_tran_amt','total_tran_amt','settlement_currency','agent_name','legacy_id'];

    // Build WHERE parts and params
    $whereParts = [];
    $paramsExec = [];
    if ($partnerCol !== null) {
        $whereParts[] = "`$partnerCol` = ?";
        $paramsExec[] = $partner;
    }
    if ($dateCol !== null) {
        $whereParts[] = 'DATE(`' . $dateCol . '`) >= ?';
        $paramsExec[] = $start_date;
        $whereParts[] = 'DATE(`' . $dateCol . '`) <= ?';
        $paramsExec[] = $end_date;
    }
    if ($branchFilter !== '') {
        if (isset($existing['branch_name'])) { $whereParts[] = 'LOWER(COALESCE(branch_name, "")) LIKE ?'; $paramsExec[] = '%' . strtolower($branchFilter) . '%'; }
        elseif (isset($existing['branch'])) { $whereParts[] = 'LOWER(COALESCE(branch, "")) LIKE ?'; $paramsExec[] = '%' . strtolower($branchFilter) . '%'; }
    }
    if ($legacyFilter !== '' && isset($existing['legacy_id'])) { $whereParts[] = 'legacy_id = ?'; $paramsExec[] = $legacyFilter; }
    if ($agentFilter !== '' && isset($existing['agent_name'])) { $whereParts[] = 'LOWER(COALESCE(agent_name, "")) LIKE ?'; $paramsExec[] = '%' . strtolower($agentFilter) . '%'; }
    if ($typeFilter !== '' && isset($existing['tran_type'])) { $whereParts[] = 'tran_type = ?'; $paramsExec[] = $typeFilter; }

    $whereSql = '';
    if (!empty($whereParts)) { $whereSql = ' WHERE ' . implode(' AND ', $whereParts); }

    // Sorting: PHP first, USD second, then tran_date ASC
    $orderExpr = "(CASE WHEN UPPER(TRIM(settlement_currency)) = 'PHP' THEN 1 WHEN UPPER(TRIM(settlement_currency)) = 'USD' THEN 2 ELSE 3 END), `" . ($dateCol ?: 'tran_date') . "` ASC";

    $sql = 'SELECT ' . implode(', ', $cols) . ' FROM moneygram_partner_data' . $whereSql . ' ORDER BY ' . $orderExpr;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsExec);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute totals (filtered)
    $totalsSql = 'SELECT '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "PHP" THEN COALESCE(total_tran_amt,0) ELSE 0 END),0) AS php_total, '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "USD" THEN COALESCE(total_tran_amt,0) ELSE 0 END),0) AS usd_total '
        . 'FROM moneygram_partner_data' . $whereSql;
    $totStmt = $pdo->prepare($totalsSql);
    $totStmt->execute($paramsExec);
    $totRow = $totStmt->fetch(PDO::FETCH_ASSOC) ?: ['php_total' => 0, 'usd_total' => 0];
    $php_total = (float)($totRow['php_total'] ?? 0.0);
    $usd_total = (float)($totRow['usd_total'] ?? 0.0);

    // Build filename
    $filePartner = preg_replace('/\s+/', '_', strtoupper($partner));
    $filename = sprintf('%s_%s_to_%s.xlsx', $filePartner, $start_date, $end_date);
    $filename = preg_replace('/[\\\/\:\*\?"\<\>\|]+/', '_', $filename);

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Moneygram');

    // Top summary rows
    $sheet->setCellValue('A1', 'Partner: ' . strtoupper($partner));
    $sheet->setCellValue('A2', 'Date Duration: ' . $start_date . ' to ' . $end_date);
    $sheet->setCellValue('A3', 'PHP Total: ₱' . number_format($php_total, 2, '.', ','));
    $sheet->setCellValue('A4', 'USD Total: $' . number_format($usd_total, 2, '.', ','));

    // Leave one blank row (row 5)
    $headerRow = 6;
    $headers = ['Transaction ID','Reference ID','Tran Date','Tran Type','Base Tran Amt','Total Tran Amt','Settlement Currency','Agent Name','Legacy ID'];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col . $headerRow, $h); $col++; }

    $lastCol = chr(ord('A') + count($headers) - 1);
    // Header style
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->freezePane('A' . ($headerRow + 1));

    // Fill rows starting at headerRow+1
    $rowIndex = $headerRow + 1;
    if (is_array($rows) && count($rows) > 0) {
        foreach ($rows as $r) {
            $sheet->setCellValue('A' . $rowIndex, $r['transaction_id'] ?? '');
            $sheet->setCellValue('B' . $rowIndex, $r['reference_id'] ?? '');
            $sheet->setCellValue('C' . $rowIndex, $r['tran_date'] ?? '');
            $sheet->setCellValue('D' . $rowIndex, $r['tran_type'] ?? '');
            $sheet->setCellValue('E' . $rowIndex, is_null($r['base_tran_amt']) ? 0 : (float)$r['base_tran_amt']);
            $sheet->setCellValue('F' . $rowIndex, is_null($r['total_tran_amt']) ? 0 : (float)$r['total_tran_amt']);
            $sheet->setCellValue('G' . $rowIndex, $r['settlement_currency'] ?? '');
            $sheet->setCellValue('H' . $rowIndex, $r['agent_name'] ?? '');
            $sheet->setCellValue('I' . $rowIndex, $r['legacy_id'] ?? '');
            $rowIndex++;
        }
    } else {
        // No data row
        $sheet->setCellValue('A' . ($rowIndex), 'No transactions found');
    }

    $lastDataRow = $rowIndex - 1;

    // Format amount columns (E, F)
    if ($lastDataRow >= $headerRow + 1) {
        $sheet->getStyle("E" . ($headerRow + 1) . ":E{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F" . ($headerRow + 1) . ":F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    // Center Legacy ID column
    if ($lastDataRow >= $headerRow + 1) {
        $sheet->getStyle("I" . ($headerRow + 1) . ":I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Apply borders to used area
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    // Autosize columns
    foreach (range('A', $lastCol) as $columnID) { $sheet->getColumnDimension($columnID)->setAutoSize(true); }

    // Output spreadsheet
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
