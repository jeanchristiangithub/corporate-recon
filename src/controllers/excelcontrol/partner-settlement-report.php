<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

function settlementType(string $type): string {
    $map = ['payout'=>'REC','payout-cancelled'=>'RRC','sendout'=>'SEN','sendout-cancelled'=>'RSN'];
    $key = strtolower(trim($type));
    return $map[$key] ?? strtoupper(trim($type));
}
function settlementCsvCell($value): string { return '"' . str_replace('"', '""', (string)$value) . '"'; }
function settlementExportDate(string $value): string {
    $time = strtotime($value);
    return $time === false ? $value : date('F d, Y', $time);
}
function settlementExportType(string $type): string {
    return match (strtoupper(trim($type))) {
        'REC' => 'REC(PAY OUT)',
        'RRC' => 'RRC(PAY OUT CANCELLED)',
        'SEN' => 'SEN(SEND OUT)',
        'RSN' => 'RSN(SEND OUT CANCELLED)',
        default => trim($type),
    };
}

try {
    $partner = trim((string)($_GET['partner'] ?? ''));
    $start = trim((string)($_GET['start_date'] ?? ''));
    $end = trim((string)($_GET['end_date'] ?? ''));
    $referenceId = trim((string)($_GET['reference_id'] ?? ''));
    if ($referenceId === '' && ($partner === '' || $start === '' || $end === '')) {
        throw new InvalidArgumentException('Corporate Partner, Start date, and End date are required when Reference ID is empty.');
    }
    if (($start === '') !== ($end === '')) {
        throw new InvalidArgumentException('Please provide both Start date and End date.');
    }
    $pdo = fileRecDbConnection();
    $columns = $pdo->query('SHOW COLUMNS FROM partner_settlement_data')->fetchAll(PDO::FETCH_COLUMN);
    $accountColumn = in_array('account_number', $columns, true) ? 'account_number' : (in_array('accout_number', $columns, true) ? 'accout_number' : null);
    if ($accountColumn === null) throw new RuntimeException('The settlement account-number column was not found.');
    $where = [];
    $params = [];
    if ($partner !== '') { $where[] = 'partner_name = ?'; $params[] = $partner; }
    if ($start !== '' && $end !== '') {
        $where[] = 'DATE(tran_date) >= ?'; $params[] = $start;
        $where[] = 'DATE(tran_date) <= ?'; $params[] = $end;
    }
    $currency = strtoupper(trim((string)($_GET['currency'] ?? '')));
    if ($currency !== '') { $where[] = 'UPPER(TRIM(transaction_currency)) = ?'; $params[] = $currency; }
    $type = settlementType((string)($_GET['type'] ?? ''));
    if ($type !== '') { $where[] = 'UPPER(TRIM(tran_type)) = ?'; $params[] = $type; }
    if ($referenceId !== '') { $where[] = 'TRIM(reference_id) = ?'; $params[] = $referenceId; }
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $select = 'id, partner_id, partner_name, `' . $accountColumn . '` AS account_number, agent_name, legacy_id, tran_date, transaction_id, reference_id, product, tran_type, orig_cntry, rcv_cntry, fx_rate_trn, fx_date_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt, total_tran_amt, settlement_currency, transaction_currency, created_at, created_by, updated_at, updated_by';

    if ((string)($_GET['export'] ?? '') === '1') {
        $stmt = $pdo->prepare('SELECT ' . $select . ' FROM partner_settlement_data' . $whereSql . ' ORDER BY tran_date DESC, id DESC');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportPartner = $partner !== '' ? $partner : (string)($rows[0]['partner_name'] ?? '');
        $rowDates = array_values(array_filter(array_map(
            static fn(array $row): string => substr(trim((string)($row['tran_date'] ?? '')), 0, 10),
            $rows
        )));
        $reportStart = $start;
        $reportEnd = $end;
        if ($reportStart === '' && $rowDates !== []) {
            sort($rowDates);
            $reportStart = $rowDates[0];
            $reportEnd = $rowDates[count($rowDates) - 1];
        }
        $totals = ['PHP' => ['principal'=>0.0,'fx'=>0.0,'commission'=>0.0], 'USD' => ['principal'=>0.0,'fx'=>0.0,'commission'=>0.0]];
        foreach ($rows as $row) {
            $rowCurrency = strtoupper(trim((string)($row['transaction_currency'] ?? '')));
            if (isset($totals[$rowCurrency])) {
                $totals[$rowCurrency]['principal'] += (float)($row['base_tran_amt'] ?? 0);
                $totals[$rowCurrency]['fx'] += (float)($row['fx_rev_share_tran_amt'] ?? 0);
                $totals[$rowCurrency]['commission'] += (float)($row['comm_tran_amt'] ?? 0);
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Settlement');
        $singleDay = $reportStart === $reportEnd;
        $typeLabel = $type === '' ? 'ALL' : settlementExportType($type);
        $currencyLabel = $currency === '' ? 'ALL' : $currency;
        $sheet->setCellValue('A1', 'SETTLEMENT REPORT');
        $sheet->setCellValue('A2', 'Partner:'); $sheet->setCellValue('B2', strtoupper($reportPartner));
        $sheet->setCellValue('A3', $singleDay ? 'Transaction Date:' : 'Date Range:');
        $sheet->setCellValue('B3', $singleDay ? settlementExportDate($reportStart) : settlementExportDate($reportStart) . ' to ' . settlementExportDate($reportEnd));
        $sheet->setCellValue('A4', 'Currency:'); $sheet->setCellValue('B4', $currencyLabel);
        $sheet->setCellValue('A5', 'Transaction Type:'); $sheet->setCellValue('B5', $typeLabel);
        $sheet->setCellValue('A7', 'Volume:'); $sheet->setCellValue('B7', count($rows));
        $sheet->setCellValue('A8', 'Principal:'); $sheet->setCellValue('B8', 'PHP:'); $sheet->setCellValue('C8', $totals['PHP']['principal']); $sheet->setCellValue('D8', 'USD:'); $sheet->setCellValue('E8', $totals['USD']['principal']);
        $sheet->setCellValue('A9', 'FX Revenue Share:'); $sheet->setCellValue('B9', 'PHP:'); $sheet->setCellValue('C9', $totals['PHP']['fx']); $sheet->setCellValue('D9', 'USD:'); $sheet->setCellValue('E9', $totals['USD']['fx']);
        $sheet->setCellValue('A10', 'Commission:'); $sheet->setCellValue('B10', 'PHP:'); $sheet->setCellValue('C10', $totals['PHP']['commission']); $sheet->setCellValue('D10', 'USD:'); $sheet->setCellValue('E10', $totals['USD']['commission']);

        $headers = ['Date','Agent Name','Legacy ID','Account Number','Reference ID','Transaction Type','Transaction FX Rate','Base Amount','Fx Revenue Share','Commission Amount','Transaction Currency','Origin Country','Received Country'];
        $sheet->fromArray($headers, null, 'A12');
        $dataRow = 13;
        foreach ($rows as $row) {
            $sheet->setCellValue('A'.$dataRow, settlementExportDate((string)$row['tran_date']));
            $sheet->setCellValue('B'.$dataRow, (string)$row['agent_name']);
            $sheet->setCellValueExplicit('C'.$dataRow, (string)$row['legacy_id'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$dataRow, (string)$row['account_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$dataRow, (string)$row['reference_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('F'.$dataRow, settlementExportType((string)$row['tran_type']));
            $sheet->setCellValue('G'.$dataRow, (float)($row['fx_rate_trn'] ?? 0));
            $sheet->setCellValue('H'.$dataRow, (float)($row['base_tran_amt'] ?? 0));
            $sheet->setCellValue('I'.$dataRow, (float)($row['fx_rev_share_tran_amt'] ?? 0));
            $sheet->setCellValue('J'.$dataRow, (float)($row['comm_tran_amt'] ?? 0));
            $sheet->setCellValue('K'.$dataRow, (string)$row['transaction_currency']);
            $sheet->setCellValue('L'.$dataRow, (string)$row['orig_cntry']);
            $sheet->setCellValue('M'.$dataRow, (string)$row['rcv_cntry']);
            $dataRow++;
        }

        $lastRow = max(12, $dataRow - 1);
        $sheet->getStyle('A1:A10')->getFont()->setBold(true);
        $sheet->getStyle('B8:B10')->getFont()->setBold(true); $sheet->getStyle('D8:D10')->getFont()->setBold(true);
        $sheet->getStyle('B7')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A12:M12')->getFont()->setBold(true);
        $sheet->getStyle('A12:M'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('G13:J'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('C8:E10')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A12:M12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (range('A', 'M') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
        $sheet->freezePane('A13');

        $safePartner = trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '-', strtoupper($reportPartner)), '-');
        $extension = $singleDay ? 'xls' : 'csv';
        $filename = $safePartner . '-settlement-' . $reportStart . ($singleDay ? '' : '-to-' . $reportEnd) . '.' . $extension;
        if (ob_get_length()) ob_end_clean();
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        if ($singleDay) {
            header('Content-Type: application/vnd.ms-excel');
            (new Xls($spreadsheet))->save('php://output');
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            $writer = new Csv($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $writer->save('php://output');
        }
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $page = max(1, (int)($_GET['page'] ?? 1)); $perPage = min(1000, max(1, (int)($_GET['per_page'] ?? 500)));
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM partner_settlement_data' . $whereSql); $countStmt->execute($params); $count=(int)$countStmt->fetchColumn();
    $pages=max(1,(int)ceil($count/$perPage)); $page=min($page,$pages); $offset=($page-1)*$perPage;
    $stmt=$pdo->prepare('SELECT '.$select.' FROM partner_settlement_data'.$whereSql.' ORDER BY tran_date DESC, id DESC LIMIT ? OFFSET ?'); $query=$params; $query[]=$perPage; $query[]=$offset; $stmt->execute($query); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalSql='SELECT COALESCE(SUM(CASE WHEN UPPER(TRIM(transaction_currency))="PHP" THEN ABS(COALESCE(base_tran_amt,0)) ELSE 0 END),0) principal_php, COALESCE(SUM(CASE WHEN UPPER(TRIM(transaction_currency))="USD" THEN ABS(COALESCE(base_tran_amt,0)) ELSE 0 END),0) principal_usd, COALESCE(SUM(CASE WHEN UPPER(TRIM(transaction_currency))="PHP" THEN ABS(COALESCE(comm_tran_amt,0)) ELSE 0 END),0) commission_php, COALESCE(SUM(CASE WHEN UPPER(TRIM(transaction_currency))="USD" THEN ABS(COALESCE(comm_tran_amt,0)) ELSE 0 END),0) commission_usd FROM partner_settlement_data'.$whereSql;
    $tot=$pdo->prepare($totalSql);$tot->execute($params);$totals=$tot->fetch(PDO::FETCH_ASSOC)?:[];
    $resolvedPartner = $partner !== '' ? $partner : (string)($rows[0]['partner_name'] ?? '');
    $resolvedStart = $start;
    $resolvedEnd = $end;
    if ($resolvedStart === '' && $rows !== []) {
        $pageDates = array_values(array_filter(array_map(
            static fn(array $row): string => substr(trim((string)($row['tran_date'] ?? '')), 0, 10),
            $rows
        )));
        if ($pageDates !== []) {
            sort($pageDates);
            $resolvedStart = $pageDates[0];
            $resolvedEnd = $pageDates[count($pageDates) - 1];
        }
    }
    echo json_encode(['success'=>true,'partner'=>$resolvedPartner,'start_date'=>$resolvedStart,'end_date'=>$resolvedEnd,'count'=>$count,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$pages,'rows'=>$rows,'totals'=>$totals], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) { if(!headers_sent()) header('Content-Type: application/json; charset=utf-8'); http_response_code($e instanceof InvalidArgumentException?422:500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
