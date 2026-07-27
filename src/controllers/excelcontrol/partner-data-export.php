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

function moneygram_export_pick(array $row, array $keys)
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return '';
}

function moneygram_export_date_mdy($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
        return $m[2] . '-' . $m[3] . '-' . $m[1];
    }
    $ts = strtotime($value);
    return $ts !== false ? date('m-d-Y', $ts) : $value;
}

function moneygram_export_float($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return (float)$value;
}

function moneygram_export_transaction_type_prefix(string $type): string
{
    $normalized = strtolower(trim($type));
    if ($normalized === 'payout' || $normalized === 'rec' || $normalized === 'receive') {
        return 'REC';
    }
    if ($normalized === 'sendout' || $normalized === 'send' || $normalized === 'sen') {
        return 'SEN';
    }
    return '';
}

try {
    $partner = isset($_GET['partner']) ? trim((string)$_GET['partner']) : '';
    $start_date = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
    $currencyFilter = strtoupper(trim((string)($_GET['settlement_currency'] ?? '')));

    if ($partner === '') {
        echo json_encode(['success' => false, 'error' => 'Corporate partner is required']);
        exit;
    }

    if (strtoupper(trim($partner)) !== 'MONEYGRAM') {
        echo json_encode(['success' => false, 'error' => 'This export endpoint only supports MONEYGRAM']);
        exit;
    }

    $referenceIdFilter = trim((string)($_GET['reference_id'] ?? ''));
    if (($start_date === '' || $end_date === '') && $referenceIdFilter === '') {
        echo json_encode(['success' => false, 'error' => 'Start date and End date are required']);
        exit;
    }

    $pdo = fileRecDbConnection();

    $colRows = $pdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($colRows as $c) {
        $existing[(string)$c['Field']] = true;
    }

    $partnerCol = null;
    foreach (['partnerName', 'partner_name', 'corporate_partner', 'partner', 'company_name'] as $candidate) {
        if (isset($existing[$candidate])) {
            $partnerCol = $candidate;
            break;
        }
    }

    $dateCol = isset($existing['tran_date']) ? 'tran_date' : (isset($existing['date']) ? 'date' : (isset($existing['date_claimed']) ? 'date_claimed' : null));

    $branchFilter = isset($_GET['branch']) ? trim((string)$_GET['branch']) : '';
    $legacyFilter = isset($_GET['legacy_id']) ? trim((string)$_GET['legacy_id']) : '';
    $agentFilter = isset($_GET['agent_name']) ? trim((string)$_GET['agent_name']) : '';
    $typeFilter = isset($_GET['type']) ? trim((string)$_GET['type']) : (isset($_GET['tran_type']) ? trim((string)$_GET['tran_type']) : '');

    $whereParts = [];
    $paramsExec = [];
    if ($partnerCol !== null) {
        $whereParts[] = "`$partnerCol` = ?";
        $paramsExec[] = $partner;
    }
    if ($dateCol !== null && $start_date !== '') {
        $whereParts[] = 'DATE(`' . $dateCol . '`) >= ?';
        $paramsExec[] = $start_date;
    }
    if ($dateCol !== null && $end_date !== '') {
        $whereParts[] = 'DATE(`' . $dateCol . '`) <= ?';
        $paramsExec[] = $end_date;
    }
    if ($branchFilter !== '') {
        if (isset($existing['branch_name'])) {
            $whereParts[] = 'LOWER(COALESCE(branch_name, "")) LIKE ?';
            $paramsExec[] = '%' . strtolower($branchFilter) . '%';
        } elseif (isset($existing['branch'])) {
            $whereParts[] = 'LOWER(COALESCE(branch, "")) LIKE ?';
            $paramsExec[] = '%' . strtolower($branchFilter) . '%';
        }
    }
    if ($legacyFilter !== '' && isset($existing['legacy_id'])) {
        $whereParts[] = 'legacy_id = ?';
        $paramsExec[] = $legacyFilter;
    }
    if ($agentFilter !== '' && isset($existing['agent_name'])) {
        $whereParts[] = 'LOWER(COALESCE(agent_name, "")) LIKE ?';
        $paramsExec[] = '%' . strtolower($agentFilter) . '%';
    }
    if ($typeFilter !== '' && isset($existing['tran_type'])) {
        $moneygramTypePrefix = moneygram_export_transaction_type_prefix($typeFilter);
        if ($moneygramTypePrefix !== '') {
            $whereParts[] = 'UPPER(TRIM(tran_type)) LIKE ?';
            $paramsExec[] = $moneygramTypePrefix . '%';
        }
    }
    if (in_array($currencyFilter, ['PHP', 'USD'], true) && isset($existing['settlement_currency'])) {
        $whereParts[] = 'UPPER(TRIM(settlement_currency)) = ?';
        $paramsExec[] = $currencyFilter;
    }
    if ($referenceIdFilter !== '' && isset($existing['reference_id'])) {
        $whereParts[] = 'TRIM(reference_id) = ?';
        $paramsExec[] = $referenceIdFilter;
    }

    $whereSql = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';
    $orderExpr = "(CASE WHEN UPPER(TRIM(settlement_currency)) = 'PHP' THEN 1 WHEN UPPER(TRIM(settlement_currency)) = 'USD' THEN 2 ELSE 3 END), `" . ($dateCol ?: 'tran_date') . "` ASC";

    $stmt = $pdo->prepare('SELECT * FROM moneygram_partner_data' . $whereSql . ' ORDER BY ' . $orderExpr);
    $stmt->execute($paramsExec);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseAmountExpr = 'ABS(COALESCE(base_amt, 0))';
    $commissionAmountExpr = isset($existing['comm_amt']) && isset($existing['comm_tran_amt'])
        ? 'COALESCE(comm_amt, comm_tran_amt, 0)'
        : (isset($existing['comm_amt']) ? 'COALESCE(comm_amt, 0)' : 'COALESCE(comm_tran_amt, 0)');

    $totalsSql = 'SELECT '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "PHP" THEN ' . $baseAmountExpr . ' ELSE 0 END),0) AS php_total, '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "USD" THEN ' . $baseAmountExpr . ' ELSE 0 END),0) AS usd_total, '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "PHP" THEN ' . $commissionAmountExpr . ' ELSE 0 END),0) AS php_commission_total, '
        . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "USD" THEN ' . $commissionAmountExpr . ' ELSE 0 END),0) AS usd_commission_total '
        . 'FROM moneygram_partner_data' . $whereSql;
    $totStmt = $pdo->prepare($totalsSql);
    $totStmt->execute($paramsExec);
    $totRow = $totStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $filePartner = preg_replace('/\s+/', '_', strtoupper($partner));
    $fileCurrency = in_array($currencyFilter, ['PHP', 'USD'], true) ? '_' . $currencyFilter : '';
    $fileTypePrefix = moneygram_export_transaction_type_prefix($typeFilter);
    $fileType = $fileTypePrefix === 'REC' ? '_PAYOUT' : ($fileTypePrefix === 'SEN' ? '_SENDOUT' : '');
    $filename = sprintf('%s%s%s_%s_to_%s.xlsx', $filePartner, $fileCurrency, $fileType, $start_date, $end_date);
    $filename = preg_replace('#[\\\\/:*?"<>|]+#', '_', $filename);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Moneygram');

    $sheet->setCellValue('A1', 'Partner: ' . strtoupper($partner));
    $sheet->setCellValue('A2', 'Date Duration: ' . $start_date . ' to ' . $end_date);
    $sheet->setCellValue('A3', 'Currency: ' . (in_array($currencyFilter, ['PHP', 'USD'], true) ? $currencyFilter : 'ALL'));
    $sheet->setCellValue('A4', 'Transaction Type: ' . ($fileTypePrefix === 'REC' ? 'PAYOUT' : ($fileTypePrefix === 'SEN' ? 'SENDOUT' : 'ALL')));
    $sheet->setCellValue('A5', 'Volume: ' . number_format(count($rows)) . ' transactions');
    $sheet->setCellValue('A6', 'Principal: PHP: ' . number_format(abs((float)($totRow['php_total'] ?? 0)), 2, '.', ',') . ' USD: ' . number_format(abs((float)($totRow['usd_total'] ?? 0)), 2, '.', ','));
    $sheet->setCellValue('A7', 'Commission: PHP: ' . number_format(abs((float)($totRow['php_commission_total'] ?? 0)), 2, '.', ',') . ' USD: ' . number_format(abs((float)($totRow['usd_commission_total'] ?? 0)), 2, '.', ','));

    $headerRow = 9;
    $headers = ['Tran Date', 'Agent Name', 'Legacy ID', 'Account Number', 'Reference ID', 'Tran Type', 'Tran Fx Rate', 'Fx Rev Share Amt', 'Base Amt', 'Comm Amt', 'Settlement Currency', 'Orig Cntry', 'Rcv Cntry'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . $headerRow, $h);
        $col++;
    }

    $lastCol = 'M';
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->freezePane('A' . ($headerRow + 1));

    $rowIndex = $headerRow + 1;
    foreach ($rows as $r) {
        $tranType = trim((string)moneygram_export_pick($r, ['tran_type']));
        if (strtoupper($tranType) === 'REC') {
            $tranType = 'REC(PAY OUT)';
        }

        $sheet->setCellValue('A' . $rowIndex, moneygram_export_date_mdy(moneygram_export_pick($r, ['tran_date'])));
        $sheet->setCellValue('B' . $rowIndex, moneygram_export_pick($r, ['agent_name']));
        $sheet->setCellValue('C' . $rowIndex, moneygram_export_pick($r, ['legacy_id']));
        $sheet->setCellValue('D' . $rowIndex, moneygram_export_pick($r, ['account_number']));
        $sheet->setCellValue('E' . $rowIndex, moneygram_export_pick($r, ['reference_id']));
        $sheet->setCellValue('F' . $rowIndex, $tranType);
        $sheet->setCellValue('G' . $rowIndex, moneygram_export_float(moneygram_export_pick($r, ['tran_fx_rate', 'fx_rate_trn'])));
        $sheet->setCellValue('H' . $rowIndex, moneygram_export_float(moneygram_export_pick($r, ['fx_rev_share_amt', 'fx_rev_share_tran_amt'])));
        $sheet->setCellValue('I' . $rowIndex, moneygram_export_float(moneygram_export_pick($r, ['base_amt'])));
        $sheet->setCellValue('J' . $rowIndex, moneygram_export_float(moneygram_export_pick($r, ['comm_amt', 'comm_tran_amt'])));
        $sheet->setCellValue('K' . $rowIndex, moneygram_export_pick($r, ['settlement_currency']));
        $sheet->setCellValue('L' . $rowIndex, moneygram_export_pick($r, ['orig_cntry']));
        $sheet->setCellValue('M' . $rowIndex, moneygram_export_pick($r, ['rcv_cntry']));
        $rowIndex++;
    }

    if (count($rows) === 0) {
        $sheet->setCellValue('A' . $rowIndex, 'No transactions found');
    }

    $lastDataRow = max($headerRow, $rowIndex - 1);
    if ($lastDataRow >= $headerRow + 1) {
        $sheet->getStyle("G" . ($headerRow + 1) . ":J{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C" . ($headerRow + 1) . ":C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    foreach (range('A', $lastCol) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    if (ob_get_length()) {
        ob_end_clean();
    }
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
