<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/session.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function moneygram_recon_input_date(string $key): string
{
    $value = trim((string) ($_GET[$key] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new InvalidArgumentException('Invalid start/end date.');
    }

    return $value;
}

function moneygram_recon_input_filter(): string
{
    $filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
    return in_array($filter, ['all', 'matched', 'mismatch', 'duplicates'], true) ? $filter : 'all';
}

function moneygram_recon_sheet_titles_for_filter(string $filter): array
{
    if ($filter === 'matched') {
        return ['MATCHED PHP', 'MATCHED USD'];
    }
    if ($filter === 'mismatch') {
        return ['MISMATCH PHP', 'MISMATCH USD'];
    }
    if ($filter === 'duplicates') {
        return ['DUPLICATE PHP', 'DUPLICATE USD'];
    }

    return ['MATCHED PHP', 'MATCHED USD', 'MISMATCH PHP', 'MISMATCH USD', 'DUPLICATE PHP', 'DUPLICATE USD'];
}

function moneygram_recon_generated_by(): string
{
    bootSecureSession();

    $user = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
    $fullName = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['lastname'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string) ($user['username'] ?? ''));
}

function moneygram_recon_fetch_data(string $startDate, string $endDate, string $partnerName): array
{
    $oldGet = $_GET;
    $_GET = [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'partnerName' => $partnerName,
        'detail' => '1',
        'range_detail' => '1',
    ];

    if (!defined('MONEYGRAM_RECON_RETURN_DATA')) {
        define('MONEYGRAM_RECON_RETURN_DATA', true);
    }

    ob_start();
    $data = require __DIR__ . '/../../../../../controllers/recon/moneygram-recon.php';
    ob_end_clean();
    $_GET = $oldGet;

    if (!is_array($data) || empty($data['success'])) {
        $message = is_array($data) && isset($data['error']) ? (string) $data['error'] : 'Unable to prepare MoneyGram reconciliation data.';
        throw new RuntimeException($message);
    }

    return $data;
}

function moneygram_recon_date_label(string $date): string
{
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    return $dateObj ? $dateObj->format('m-d-Y') : $date;
}

function moneygram_recon_report_date_label(string $startDate, string $endDate): string
{
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$start || !$end) {
        return $startDate . ' To ' . $endDate;
    }

    return $start->format('F d, Y') . ' To ' . $end->format('F d, Y');
}

function moneygram_recon_row_currency(array $row): string
{
    $currency = (string) ($row['partner_settlement_currency']
        ?? $row['partner_transaction_currency']
        ?? $row['partner_base_cncy']
        ?? $row['partner_currency']
        ?? $row['partner_coin']
        ?? $row['web_currency']
        ?? '');

    return strtoupper(trim($currency));
}

function moneygram_recon_partner_currency(array $row): string
{
    return strtoupper(trim((string) ($row['partner_settlement_currency']
        ?? $row['partner_transaction_currency']
        ?? $row['partner_base_cncy']
        ?? $row['partner_currency']
        ?? $row['partner_coin']
        ?? '')));
}

function moneygram_recon_web_currency(array $row): string
{
    return strtoupper(trim((string) ($row['web_currency'] ?? '')));
}

function moneygram_recon_row_partner_date(array $row, string $fallbackDate): string
{
    return (string) ($row['partner_tran_date'] ?? $row['partner_date'] ?? $row['partner_fx_date_trn'] ?? $fallbackDate);
}

function moneygram_recon_row_web_date(array $row, string $fallbackDate): string
{
    return (string) ($row['web_date_claimed'] ?? $row['web_date'] ?? $fallbackDate);
}

function moneygram_recon_has_partner(array $row): bool
{
    return trim((string) ($row['partner_reference_id'] ?? '')) !== '' || (float) ($row['partner_principal'] ?? 0) !== 0.0;
}

function moneygram_recon_has_web(array $row): bool
{
    return trim((string) ($row['web_ccref_no'] ?? $row['web_cc_ref'] ?? '')) !== '' || (float) ($row['web_amount'] ?? 0) !== 0.0;
}

function moneygram_recon_partner_ref(array $row): string
{
    return trim((string) ($row['partner_reference_id'] ?? ($row['ref'] ?? '')));
}

function moneygram_recon_web_ref(array $row): string
{
    return trim((string) ($row['web_ccref_no'] ?? ($row['web_cc_ref'] ?? ($row['ref'] ?? ''))));
}

function moneygram_recon_currency_bucket(string $currency): string
{
    return strtoupper(trim($currency)) === 'USD' ? 'USD' : 'PHP';
}

function moneygram_recon_partner_key(array $row, string $fallbackDate): string
{
    $date = moneygram_recon_date_label(substr(moneygram_recon_row_partner_date($row, $fallbackDate), 0, 10));
    return strtoupper($date . '|' . moneygram_recon_partner_ref($row) . '|' . moneygram_recon_currency_bucket(moneygram_recon_partner_currency($row)));
}

function moneygram_recon_web_key(array $row, string $fallbackDate): string
{
    $date = moneygram_recon_date_label(substr(moneygram_recon_row_web_date($row, $fallbackDate), 0, 10));
    return strtoupper($date . '|' . moneygram_recon_web_ref($row) . '|' . moneygram_recon_currency_bucket(moneygram_recon_web_currency($row)));
}

function moneygram_recon_excel_row(?array $partnerRow, ?array $webRow, string $fallbackDate): array
{
    $partnerCurrency = $partnerRow ? moneygram_recon_currency_bucket(moneygram_recon_partner_currency($partnerRow)) : '';
    $webCurrency = $webRow ? moneygram_recon_currency_bucket(moneygram_recon_web_currency($webRow)) : '';

    return [
        $partnerRow ? moneygram_recon_date_label(substr(moneygram_recon_row_partner_date($partnerRow, $fallbackDate), 0, 10)) : '',
        $partnerRow ? moneygram_recon_partner_ref($partnerRow) : '',
        $partnerRow ? (float) ($partnerRow['partner_principal'] ?? 0) : null,
        $partnerRow ? (float) ($partnerRow['partner_commission'] ?? 0) : null,
        $partnerRow ? $partnerCurrency : '',
        $webRow ? moneygram_recon_date_label(substr(moneygram_recon_row_web_date($webRow, $fallbackDate), 0, 10)) : '',
        $webRow ? (string) ($webRow['web_kptn'] ?? '') : '',
        $webRow ? moneygram_recon_web_ref($webRow) : '',
        $webRow ? (float) ($webRow['web_amount'] ?? 0) : null,
        $webRow ? $webCurrency : '',
    ];
}

function moneygram_recon_add_bucket_row(array &$bucket, string $key, string $side, array $row): void
{
    if (!isset($bucket[$key])) {
        $bucket[$key] = ['partners' => [], 'web' => []];
    }

    $bucket[$key][$side][] = $row;
}

function moneygram_recon_flush_bucket(array &$groups, string $type, array $bucket, string $fallbackDate): void
{
    ksort($bucket);
    foreach ($bucket as $items) {
        $partners = array_values($items['partners'] ?? []);
        $webRows = array_values($items['web'] ?? []);
        $maxRows = max(count($partners), count($webRows));
        for ($index = 0; $index < $maxRows; $index++) {
            $partnerRow = $partners[$index] ?? null;
            $webRow = $webRows[$index] ?? null;
            $currency = $partnerRow
                ? moneygram_recon_currency_bucket(moneygram_recon_partner_currency($partnerRow))
                : moneygram_recon_currency_bucket($webRow ? moneygram_recon_web_currency($webRow) : '');
            $groups[$type . ' ' . $currency][] = moneygram_recon_excel_row($partnerRow, $webRow, $fallbackDate);
        }
    }
}

function moneygram_recon_collect_rows(array $data): array
{
    $groups = [
        'MATCHED PHP' => [], 'MATCHED USD' => [],
        'MISMATCH PHP' => [], 'MISMATCH USD' => [],
        'DUPLICATE PHP' => [], 'DUPLICATE USD' => [],
    ];

    foreach (($data['days'] ?? []) as $day) {
        $date = (string) ($day['date'] ?? '');
        $duplicateRefs = [];
        foreach (($day['duplicates'] ?? []) as $duplicate) {
            $ref = strtoupper(trim((string) ($duplicate['ref'] ?? '')));
            if ($ref !== '') {
                $duplicateRefs[$ref] = true;
            }
        }

        foreach (($day['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $currency = moneygram_recon_row_currency($row);
            if ($currency !== 'USD') {
                $currency = 'PHP';
            }

            $ref = strtoupper(trim((string) ($row['ref'] ?? $row['partner_reference_id'] ?? $row['web_ccref_no'] ?? $row['web_cc_ref'] ?? '')));
            $hasPartner = moneygram_recon_has_partner($row);
            $hasWeb = moneygram_recon_has_web($row);

            if (isset($duplicateRefs[$ref])) {
                if (!isset($duplicateBuckets)) {
                    $duplicateBuckets = [];
                }
                if ($hasPartner) {
                    moneygram_recon_add_bucket_row($duplicateBuckets, moneygram_recon_partner_key($row, $date), 'partners', $row);
                }
                if ($hasWeb) {
                    moneygram_recon_add_bucket_row($duplicateBuckets, moneygram_recon_web_key($row, $date), 'web', $row);
                }
                continue;
            }

            if ($hasPartner && $hasWeb) {
                $groups['MATCHED ' . $currency][] = moneygram_recon_excel_row($row, $row, $date);
                continue;
            }

            if (!isset($mismatchBuckets)) {
                $mismatchBuckets = [];
            }
            if ($hasPartner) {
                moneygram_recon_add_bucket_row($mismatchBuckets, moneygram_recon_partner_key($row, $date), 'partners', $row);
            }
            if ($hasWeb) {
                moneygram_recon_add_bucket_row($mismatchBuckets, moneygram_recon_web_key($row, $date), 'web', $row);
            }
        }

        moneygram_recon_flush_bucket($groups, 'MISMATCH', $mismatchBuckets ?? [], $date);
        moneygram_recon_flush_bucket($groups, 'DUPLICATE', $duplicateBuckets ?? [], $date);
        unset($mismatchBuckets, $duplicateBuckets);
    }

    return $groups;
}

function moneygram_recon_setup_sheet(Worksheet $sheet, string $title, string $reportDate, string $generatedBy): void
{
    $sheet->setTitle($title);
    $sheet->setCellValue('A1', 'MLHUILLIER PHILIPPINES');
    $sheet->setCellValue('A2', 'CORPORATE DEPARTMENT');
    $sheet->setCellValue('A3', 'RECONCILIATION DETAILS REPORT');
    $sheet->setCellValue('A4', (str_contains($title, 'USD') ? 'USD' : 'PHP') . ' Transactions');
    $sheet->setCellValue('A6', 'Bank Partner:');
    $sheet->setCellValue('B6', 'MONEYGRAM');
    $sheet->setCellValue('C6', 'Report Date:');
    $sheet->setCellValue('D6', $reportDate);
    $sheet->setCellValue('A7', 'Generated Date:');
    $sheet->setCellValue('B7', date('F d, Y h:i:s A'));
    $sheet->setCellValue('A8', 'Generated By:');
    $sheet->setCellValue('B8', $generatedBy);

    $sheet->mergeCells('A10:E10');
    $sheet->mergeCells('F10:J10');
    $sheet->setCellValue('A10', 'PARTNERS DATA');
    $sheet->setCellValue('F10', 'KPX WEB DATA');
    $sheet->fromArray(['DATE', 'REFERENCE ID', 'AMOUNT', 'COMMISSION', 'CURRENCY', 'DATE', 'KPTN', 'CCREF NO', 'AMOUNT', 'CURRENCY'], null, 'A11');

    $sheet->getStyle('A10:J11')->getFont()->setBold(true);
    $sheet->getStyle('A10:J11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A10:J11')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->freezePane('A12');

    $widths = [12, 20, 12, 14, 10, 12, 16, 14, 12, 10];
    foreach ($widths as $index => $width) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
    }
}

function moneygram_recon_write_rows(Worksheet $sheet, array $rows): void
{
    $rowNumber = 12;
    foreach ($rows as $row) {
        $sheet->fromArray($row, null, 'A' . $rowNumber);
        $rowNumber++;
    }

    $lastRow = max(12, $rowNumber - 1);
    $sheet->getStyle("A10:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("C12:D{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("I12:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
}

try {
    $startDate = moneygram_recon_input_date('start_date');
    $endDate = moneygram_recon_input_date('end_date');
    $filter = moneygram_recon_input_filter();
    $partnerName = trim((string) ($_GET['partnerName'] ?? 'MONEYGRAM'));
    if ($partnerName === '') {
        $partnerName = 'MONEYGRAM';
    }

    $data = moneygram_recon_fetch_data($startDate, $endDate, $partnerName);
    $groups = moneygram_recon_collect_rows($data);
    $sheetTitles = moneygram_recon_sheet_titles_for_filter($filter);
    $spreadsheet = new Spreadsheet();
    $reportDate = moneygram_recon_report_date_label($startDate, $endDate);
    $generatedBy = moneygram_recon_generated_by();

    $first = true;
    foreach ($sheetTitles as $title) {
        $rows = $groups[$title] ?? [];
        $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
        moneygram_recon_setup_sheet($sheet, $title, $reportDate, $generatedBy);
        moneygram_recon_write_rows($sheet, $rows);
        $first = false;
    }

    $spreadsheet->setActiveSheetIndex(0);
    if ($filter === 'matched') {
        $filename = 'MONEYGRAM-RECON-DETAILS-REPORT-MATCHED-' . $startDate . '-to-' . $endDate . '.xlsx';
    }elseif ($filter === 'mismatch') {
        $filename = 'MONEYGRAM-RECON-DETAILS-REPORT-MISMATCH-' . $startDate . '-to-' . $endDate . '.xlsx';
    }elseif ($filter === 'duplicates') {
        $filename = 'MONEYGRAM-RECON-DETAILS-REPORT-DUPLICATES-' . $startDate . '-to-' . $endDate . '.xlsx';
    }else{
        $filename = 'MONEYGRAM-RECON-DETAILS-REPORT-' . $startDate . '-to-' . $endDate . '.xlsx';
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
}
