<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

if (!isAuthenticated()) {
    http_response_code(401);
    exit('Your session has expired. Please log in again.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
verifyCsrfOrFail();

function cashflowExportNumber(mixed $value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

function cashflowExportUser(): string
{
    $user = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
    $name = trim(implode(' ', array_filter([
        trim((string) ($user['firstname'] ?? '')),
        trim((string) ($user['lastname'] ?? '')),
    ])));
    return $name !== '' ? $name : trim((string) ($user['username'] ?? $user['id_number'] ?? ''));
}

function cashflowPdfEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cashflowPdfNumber(mixed $value, bool $dashWhenEmpty = false): string
{
    if ($dashWhenEmpty && ($value === null || $value === '')) return '–';
    return number_format(cashflowExportNumber($value), 2, '.', ',');
}

function cashflowExportStatusLabel(mixed $value): string
{
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['valid', 'validated'], true)
        ? 'VALIDATED'
        : 'NOT YET VALIDATED';
}

function cashflowBuildPdfHtml(
    array $reports,
    array $accounts,
    string $partner,
    string $monthLabel,
    string $bankLabel,
    string $generatedDate,
    string $generatedBy
): string {
    $logoPath = __DIR__ . '/../../assets/ml.png';
    $logo = is_file($logoPath)
        ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
        : '';
    $pages = [];

    foreach (['PHP', 'USD'] as $currency) {
        $report = is_array($reports[$currency] ?? null) ? $reports[$currency] : [];
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $rowsHtml = '';
        $columnTotals = [
            'payout_principal' => 0.0,
            'payout_commission' => 0.0,
            'sendout_principal' => 0.0,
            'sendout_charge' => 0.0,
            'sendout_commission' => 0.0,
            'adjustment' => 0.0,
            'net_transaction_amount' => 0.0,
            'deposit' => 0.0,
        ];
        foreach (array_slice(is_array($report['rows'] ?? null) ? $report['rows'] : [], 0, 100) as $row) {
            if (!is_array($row)) continue;
            $running = cashflowExportNumber($row['running'] ?? 0);
            $runningClass = $running < 0 ? ' negative' : '';
            if (!empty($row['commission'])) {
                $columnTotals['payout_commission'] += cashflowExportNumber(
                    $row['payout_commission'] ?? $row['principal'] ?? 0
                );
                $columnTotals['net_transaction_amount'] += cashflowExportNumber(
                    $row['net_transaction_amount'] ?? $row['principal'] ?? 0
                );
                $rowsHtml .= '<tr class="commission"><td colspan="3">' . cashflowPdfEscape($row['date'] ?? '') . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['payout_commission'] ?? $row['principal'] ?? 0) . '</td>'
                    . '<td colspan="4"></td>'
                    . '<td class="number">' . cashflowPdfNumber($row['net_transaction_amount'] ?? $row['principal'] ?? 0) . '</td>'
                    . '<td></td>';
            } else {
                foreach (array_keys($columnTotals) as $totalKey) {
                    $columnTotals[$totalKey] += cashflowExportNumber($row[$totalKey] ?? 0);
                }
                $rowsHtml .= '<tr><td class="date">' . nl2br(cashflowPdfEscape($row['date'] ?? ''), false) . '</td>'
                    . '<td class="number integer volume-value">' . number_format((int) ($row['volume'] ?? 0)) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['payout_principal'] ?? 0) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['payout_commission'] ?? null, true) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['sendout_principal'] ?? 0) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['sendout_charge'] ?? 0) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['sendout_commission'] ?? 0) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['adjustment'] ?? null, true) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['net_transaction_amount'] ?? $row['principal'] ?? 0) . '</td>'
                    . '<td class="number">' . cashflowPdfNumber($row['deposit'] ?? null, true) . '</td>';
            }
            $rowsHtml .= '<td class="number' . $runningClass . '">' . cashflowPdfNumber($running) . '</td>'
                . '<td class="remarks">' . cashflowExportStatusLabel($row['remarks'] ?? 'NOT VALID') . '</td></tr>';
        }

        $beginning = cashflowExportNumber($summary['beginning'] ?? 0);
        $ending = cashflowExportNumber($summary['running'] ?? 0);
        $beginningClass = $beginning < 0 ? ' negative' : '';
        $endingClass = $ending < 0 ? ' negative' : '';
        $pages[] = '<section class="report-page">'
            . ($logo !== '' ? '<div class="logo"><img src="' . $logo . '" alt="MLhuillier"></div>' : '')
            . '<table class="details"><tr><th>Account Number:</th><td>' . cashflowPdfEscape($accounts[strtolower($currency)] ?? '') . '</td></tr>'
            . '<tr><th>Bank Deposit:</th><td>' . cashflowPdfEscape($bankLabel) . '</td></tr>'
            . '<tr><th>Transaction Date:</th><td>' . cashflowPdfEscape(strtoupper($monthLabel)) . '</td></tr>'
            . '<tr><th>Generated Date:</th><td>' . cashflowPdfEscape($generatedDate) . '</td></tr>'
            . '<tr><th>Generated By:</th><td>' . cashflowPdfEscape(strtoupper($generatedBy)) . '</td></tr></table>'
            . '<h1>CASH FLOW REPORT</h1><h2>' . cashflowPdfEscape(strtoupper($partner) . ' ' . $currency) . '</h2>'
            . '<table class="report-table"><colgroup><col class="c-date"><col class="c-volume"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-adjustment"><col class="c-net"><col class="c-deposit"><col class="c-running"><col class="c-remarks"></colgroup>'
            . '<thead><tr><th rowspan="3">DATE</th><th colspan="8">PARTNER SETTLEMENT DATA</th><th rowspan="3">BANK DEPOSIT</th><th rowspan="3">RUNNING<br>BALANCE</th><th rowspan="3">REMARK</th></tr>'
            . '<tr><th rowspan="2">VOLUME</th><th colspan="2">PAYOUT / PAYOUT CANCELLED</th><th colspan="3">SENDOUT / SENDOUT CANCELLED</th><th rowspan="2">ADJUSTMENT<br>/ REFUND</th><th rowspan="2">NET TRANSACTION<br>AMOUNT FOR<br>SETTLEMENT</th></tr>'
            . '<tr><th>PRINCIPAL</th><th>COMMISSION</th><th>PRINCIPAL</th><th>CHARGE</th><th>COMMISSION</th></tr></thead><tbody>'
            . '<tr class="forwarded"><td class="date">' . nl2br(cashflowPdfEscape($report['forwarded_date'] ?? ''), false) . '</td>'
            . '<td colspan="9" class="forwarded-label">(Ending Balance)</td>'
            . '<td class="number' . $beginningClass . '">' . cashflowPdfNumber($beginning) . '</td><td></td></tr>'
            . $rowsHtml . '</tbody></table>'
            . '<table class="grand-total"><colgroup><col class="c-date"><col class="c-volume"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-small"><col class="c-adjustment"><col class="c-net"><col class="c-deposit"><col class="c-running"><col class="c-remarks"></colgroup>'
            . '<tr><th>GRAND TOTAL:</th><td>' . number_format((int) ($summary['volume'] ?? 0)) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['payout_principal']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['payout_commission']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['sendout_principal']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['sendout_charge']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['sendout_commission']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['adjustment']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['net_transaction_amount']) . '</td>'
            . '<td>' . cashflowPdfNumber($columnTotals['deposit']) . '</td>'
            . '<td class="' . $endingClass . '">' . cashflowPdfNumber($ending) . '</td><td></td></tr></table>'
            . '<div class="summary-title">SUMMARY</div><table class="summary">'
            . '<tr><th>Ending Balance:</th><td class="' . $beginningClass . '">' . cashflowPdfNumber($beginning) . '</td></tr>'
            . '<tr><th>Less: Transactions</th><td>' . cashflowPdfNumber($summary['transactions'] ?? 0) . '</td></tr>'
            . '<tr><th>Add: Adjustment</th><td>' . cashflowPdfNumber($summary['adjustment'] ?? 0) . '</td></tr>'
            . '<tr><th>Deposits:</th><td>' . cashflowPdfNumber($summary['deposits'] ?? 0) . '</td></tr>'
            . '<tr><th>Running Balance:</th><td class="' . $endingClass . '">' . cashflowPdfNumber($ending) . '</td></tr></table></section>';
    }

    return '<!doctype html><html><head><meta charset="UTF-8"><style>'
        . '@page{size:letter landscape;margin:16pt 24pt}*{box-sizing:border-box}body{margin:0;color:#000;font-family:DejaVu Sans,Arial,sans-serif;font-size:6.4pt}'
        . '.report-page{page-break-after:always}.report-page:last-child{page-break-after:auto}.logo{text-align:center;height:50pt}.logo img{width:205pt;height:auto}'
        . '.details{width:270pt;margin:0 0 4pt 5pt;border-collapse:collapse}.details th,.details td{padding:0 3pt;line-height:1.2;text-align:left}.details th{width:90pt}'
        . 'h1,h2{margin:0;text-align:center;font-weight:700}h1{font-size:13pt;margin-top:2pt}h2{font-size:12pt;margin:7pt 0 8pt}'
        . '.report-table{width:100%;border-collapse:collapse;table-layout:fixed}.report-table th,.report-table td{border:.65pt solid #111;padding:1.2pt 2pt;line-height:1.05;vertical-align:middle}'
        . '.report-table th{text-align:center;font-weight:700}.c-date{width:10%}.c-volume{width:5%}.c-small{width:7%}.c-adjustment{width:9%}.c-net{width:11%}.c-deposit{width:8%}.c-running{width:11%}.c-remarks{width:11%}'
        . '.date{text-align:left;font-size:5.2pt;line-height:1.15;white-space:normal}.number{text-align:right;white-space:nowrap}.volume-value{text-align:center}.remarks{text-align:center;white-space:nowrap;font-size:5pt;letter-spacing:-.1pt}.negative{color:#ed2947}.commission td:first-child{text-align:right;font-weight:700;font-style:italic}.forwarded td{font-weight:700}.forwarded .date{font-weight:400;text-align:left}.forwarded .forwarded-label{text-align:left;color:#ed2947}'
        . '.grand-total{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:5pt;font-size:4.6pt;font-weight:700;letter-spacing:-.12pt}.grand-total th,.grand-total td{padding:1pt .6pt;text-align:right;border-top:1.2pt solid #111;white-space:nowrap}.grand-total td:nth-child(2){text-align:center}.grand-total .c-date{width:10%}.grand-total .c-volume{width:5%}.grand-total .c-small{width:7%}.grand-total .c-adjustment{width:9%}.grand-total .c-net{width:11%}.grand-total .c-deposit{width:8%}.grand-total .c-running{width:11%}.grand-total .c-remarks{width:11%}'
        . '.summary-title{width:270pt;margin:10pt 0 2pt 82pt;font-weight:700;text-decoration:underline}.summary{width:270pt;margin-left:5pt;border-collapse:collapse}.summary th,.summary td{padding:0 3pt;line-height:1.25}.summary th{text-align:left;width:115pt}.summary td{text-align:right;white-space:nowrap}'
        . '</style></head><body>' . implode('', $pages) . '</body></html>';
}

function cashflowBuildSheet(
    Spreadsheet $spreadsheet,
    string $currency,
    array $report,
    string $partner,
    string $monthLabel,
    string $accountNumber,
    string $bankLabel,
    string $generatedDate,
    string $generatedBy,
    bool $firstSheet
): void {
    $sheet = $firstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
    $sheet->setTitle('CASH FLOW ' . $currency);
    $sheet->setCellValue('A1', 'MLHUILLIER PHILIPPINES');
    $sheet->setCellValue('A2', 'CASH FLOW REPORT');
    $sheet->setCellValue('A3', $partner . ' ' . $currency);
    $sheet->setCellValue('A5', 'Account Number:');
    $sheet->setCellValueExplicit('B5', $accountNumber, DataType::TYPE_STRING);
    $sheet->setCellValue('A6', 'Bank Deposit:');
    $sheet->setCellValue('B6', $bankLabel);
    $sheet->setCellValue('A7', 'Transaction Date:');
    $sheet->setCellValue('B7', strtoupper($monthLabel));
    $sheet->setCellValue('A9', 'Generated Date:');
    $sheet->setCellValue('B9', $generatedDate);
    $sheet->setCellValue('A10', 'Generated By:');
    $sheet->setCellValue('B10', $generatedBy);

    $sheet->mergeCells('A12:A14');
    $sheet->mergeCells('B12:I12');
    $sheet->mergeCells('J12:J14');
    $sheet->mergeCells('K12:K14');
    $sheet->mergeCells('L12:L14');
    $sheet->mergeCells('B13:B14');
    $sheet->mergeCells('C13:D13');
    $sheet->mergeCells('E13:G13');
    $sheet->mergeCells('H13:H14');
    $sheet->mergeCells('I13:I14');
    $sheet->setCellValue('A12', 'DATE');
    $sheet->setCellValue('B12', 'PARTNER SETTLEMENT DATA');
    $sheet->setCellValue('B13', 'VOLUME');
    $sheet->setCellValue('C13', 'PAYOUT / PAYOUT CANCELLED');
    $sheet->setCellValue('E13', 'SENDOUT / SENDOUT CANCELLED');
    $sheet->setCellValue('H13', 'ADJUSTMENT/REFUND');
    $sheet->setCellValue('I13', 'NET TRANSACTION AMOUNT FOR SETTLEMENT');
    $sheet->setCellValue('C14', 'PRINCIPAL');
    $sheet->setCellValue('D14', 'COMMISSION');
    $sheet->setCellValue('E14', 'PRINCIPAL');
    $sheet->setCellValue('F14', 'CHARGE');
    $sheet->setCellValue('G14', 'COMMISSION');
    $sheet->setCellValue('J12', 'BANK DEPOSIT');
    $sheet->setCellValue('K12', 'RUNNING BALANCE');
    $sheet->setCellValue('L12', 'REMARK');

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $sheet->mergeCells('B15:J15');
    $sheet->setCellValue(
        'A15',
        preg_replace('/\s+/', ' ', trim((string) ($report['forwarded_date'] ?? '')))
    );
    $sheet->setCellValue('B15', '(Ending Balance)');
    $beginningBalance = cashflowExportNumber($summary['beginning'] ?? 0);
    $sheet->setCellValue('K15', $beginningBalance);
    if ($beginningBalance < 0) {
        $sheet->getStyle('K15')->getFont()->getColor()->setARGB('FFED2947');
    }

    $rowNumber = 16;
    $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
    foreach (array_slice($rows, 0, 100) as $row) {
        if (!is_array($row)) continue;
        $isCommission = !empty($row['commission']);
        if ($isCommission) {
            $sheet->mergeCells("A{$rowNumber}:C{$rowNumber}");
            $sheet->mergeCells("E{$rowNumber}:H{$rowNumber}");
            $sheet->getStyle("A{$rowNumber}:C{$rowNumber}")
                ->getFont()
                ->setBold(true);
            $sheet->getStyle("A{$rowNumber}:C{$rowNumber}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->setCellValue(
            "A{$rowNumber}",
            preg_replace('/\s+/', ' ', trim((string) ($row['date'] ?? '')))
        );
        if (!$isCommission) {
            $sheet->setCellValue("B{$rowNumber}", (int) ($row['volume'] ?? 0));
            $sheet->setCellValue("C{$rowNumber}", cashflowExportNumber($row['payout_principal'] ?? 0));
            if ($row['payout_commission'] !== null) $sheet->setCellValue("D{$rowNumber}", cashflowExportNumber($row['payout_commission']));
            $sheet->setCellValue("E{$rowNumber}", cashflowExportNumber($row['sendout_principal'] ?? 0));
            $sheet->setCellValue("F{$rowNumber}", cashflowExportNumber($row['sendout_charge'] ?? 0));
            $sheet->setCellValue("G{$rowNumber}", cashflowExportNumber($row['sendout_commission'] ?? 0));
            if ($row['adjustment'] !== null) $sheet->setCellValue("H{$rowNumber}", cashflowExportNumber($row['adjustment']));
            $sheet->setCellValue("I{$rowNumber}", cashflowExportNumber($row['net_transaction_amount'] ?? $row['principal'] ?? 0));
            if ($row['deposit'] !== null) $sheet->setCellValue("J{$rowNumber}", cashflowExportNumber($row['deposit']));
        } else {
            $sheet->setCellValue("D{$rowNumber}", cashflowExportNumber($row['payout_commission'] ?? $row['principal'] ?? 0));
            $sheet->setCellValue("I{$rowNumber}", cashflowExportNumber($row['net_transaction_amount'] ?? $row['principal'] ?? 0));
        }
        $running = cashflowExportNumber($row['running'] ?? 0);
        $sheet->setCellValue("K{$rowNumber}", $running);
        $sheet->setCellValue("L{$rowNumber}", cashflowExportStatusLabel($row['remarks'] ?? 'NOT VALID'));
        if ($running < 0) $sheet->getStyle("K{$rowNumber}")->getFont()->getColor()->setARGB('FFED2947');
        $rowNumber++;
    }

    $totalRow = $rowNumber + 1;
    $sheet->setCellValue("A{$totalRow}", 'GRAND TOTAL:');
    $sheet->setCellValue("B{$totalRow}", (int) ($summary['volume'] ?? 0));
    $lastDataRow = max(16, $rowNumber - 1);
    foreach (range('C', 'J') as $column) {
        $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}16:{$column}{$lastDataRow})");
    }
    $endingBalance = cashflowExportNumber($summary['running'] ?? 0);
    $sheet->setCellValue("K{$totalRow}", $endingBalance);
    if ($endingBalance < 0) {
        $sheet->getStyle("K{$totalRow}")->getFont()->getColor()->setARGB('FFED2947');
    }

    $summaryRow = $totalRow + 3;
    $sheet->setCellValue("A{$summaryRow}", 'SUMMARY');
    foreach ([
        'Ending Balance:' => 'beginning',
        'Less: Transactions' => 'transactions',
        'Add: Adjustment' => 'adjustment',
        'Deposits:' => 'deposits',
        'Running Balance:' => 'running',
    ] as $label => $key) {
        $summaryRow++;
        $sheet->setCellValue("A{$summaryRow}", $label);
        $sheet->setCellValue("B{$summaryRow}", cashflowExportNumber($summary[$key] ?? 0));
    }

    $lastRow = $summaryRow;
    $sheet->getStyle("A12:L{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('A12:L15')->getFont()->setBold(true);
    $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
    $sheet->getStyle('A12:L14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('I13')->getAlignment()->setWrapText(true);
    $sheet->getStyle("C16:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
    $sheet->getStyle('B' . ($totalRow + 4) . ":B{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
    $sheet->getStyle('K15')->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
    $sheet->getStyle("B16:B{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle("B16:B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B15:K15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('B15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle('B15')->getFont()->getColor()->setARGB('FFED2947');
    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("A{$totalRow}:J{$totalRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);
    $sheet->getStyle("A" . ($totalRow + 3))->getFont()->setBold(true)->setUnderline(true);
    $sheet->getColumnDimension('A')->setWidth(34);
    $sheet->getColumnDimension('B')->setWidth(17);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(16);
    $sheet->getColumnDimension('G')->setWidth(18);
    $sheet->getColumnDimension('H')->setWidth(22);
    $sheet->getColumnDimension('I')->setWidth(26);
    $sheet->getColumnDimension('J')->setWidth(19);
    $sheet->getColumnDimension('K')->setWidth(22);
    $sheet->getColumnDimension('L')->setWidth(22);
    $sheet->freezePane('A16');
}

try {
    $payload = json_decode((string) ($_POST['payload'] ?? ''), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Export data is required.');
    $partner = trim((string) ($payload['partner'] ?? ''));
    $month = trim((string) ($payload['month'] ?? ''));
    if ($partner === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Partner and report month are required.');
    }
    $monthDate = DateTimeImmutable::createFromFormat('!Y-m', $month);
    if (!$monthDate) throw new InvalidArgumentException('Invalid report month.');

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setCreator('ML Auto Recon')->setTitle('Cash Flow Report');
    $generatedDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('F d, Y g:iA');
    $generatedBy = cashflowExportUser();
    $bankLabel = trim((string) ($payload['bank_label'] ?? ''));
    $accounts = is_array($payload['accounts'] ?? null) ? $payload['accounts'] : [];
    $reports = is_array($payload['reports'] ?? null) ? $payload['reports'] : [];
    foreach (['PHP', 'USD'] as $index => $currency) {
        cashflowBuildSheet(
            $spreadsheet,
            $currency,
            is_array($reports[$currency] ?? null) ? $reports[$currency] : [],
            $partner,
            $monthDate->format('F Y'),
            trim((string) ($accounts[strtolower($currency)] ?? '')),
            $bankLabel,
            $generatedDate,
            $generatedBy,
            $index === 0
        );
    }
    $spreadsheet->setActiveSheetIndex(0);
    $format = strtolower(trim((string) ($_POST['format'] ?? 'xls')));
    if ($format === 'pdf') {
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($partner)) . '_CASH_FLOW_REPORT_' . str_replace('-', '_', $month) . '.pdf';
        if (ob_get_length()) ob_end_clean();
        $pdf = new Dompdf\Dompdf();
        $pdf->loadHtml(cashflowBuildPdfHtml(
            $reports,
            $accounts,
            $partner,
            $monthDate->format('F Y'),
            $bankLabel,
            $generatedDate,
            $generatedBy
        ));
        $pdf->setPaper('letter', 'landscape');
        $pdf->render();
        $pdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($partner)) . '_CASH_FLOW_REPORT_' . str_replace('-', '_', $month) . '.xls';
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new Xls($spreadsheet))->save('php://output');
    exit;
} catch (Throwable $exception) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $exception->getMessage();
}
