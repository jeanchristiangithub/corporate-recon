<?php
// eec-helper.php
// Shared helpers for EEC extraction, normalization and checks.

require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function eec_normalize_amount($val)
{
    $v = trim((string) $val);
    if ($v === '') return null;
    $v = preg_replace('/[^0-9,\.\-() ]+/', '', $v);
    $neg = false;
    if (preg_match('/^\(.*\)$/', $v)) {
        $neg = true;
        $v = trim($v, '() ');
    }
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        $v = str_replace(',', '', $v);
    } elseif (strpos($v, ',') !== false && strpos($v, '.') === false) {
        $v = str_replace(',', '.', $v);
    }
    $v = str_replace(' ', '', $v);
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if ($v === '' || !is_numeric($v)) return null;
    if ($neg) $v = '-' . $v;
    return $v;
}

function eec_parse_date_claimed($raw)
{
    if ($raw === null) return null;
    $raw = trim((string) $raw);
    if ($raw === '') return null;
    if (is_numeric($raw)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float) $raw);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
        }
    }
    $ts = strtotime($raw);
    if ($ts !== false) return date('Y-m-d H:i:s', $ts);
    try {
        $dt = DateTime::createFromFormat('F d, Y', $raw);
        if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
    }
    return null;
}

function eec_build_pairs_from_payloads(array $payloads)
{
    $pairs = [];
    foreach ($payloads as $pl) {
        $dateStr = $pl['dateStr'] ?? '';
        $rows = $pl['rows'] ?? [];
        foreach ($rows as $r) {
            $ccref = isset($r['CCREF NO']) ? trim((string) $r['CCREF NO']) : '';
            $rawDate = $r['DATE CLAIMED'] ?? $dateStr ?? '';
            if ($ccref !== '') {
                $pairs[] = ['ccref_no' => $ccref, 'raw_date' => $rawDate, 'date_claimed' => eec_parse_date_claimed($rawDate)];
            }
        }
    }
    return $pairs;
}

function eec_normalize_pairs(array $pairs)
{
    $out = [];
    foreach ($pairs as $p) {
        $cc = isset($p['ccref_no']) ? trim((string) $p['ccref_no']) : '';
        $raw = $p['date_claimed'] ?? ($p['raw_date'] ?? ($p['date'] ?? ''));
        $out[] = ['ccref_no' => $cc, 'raw_date' => $raw, 'normalized' => eec_parse_date_claimed($raw)];
    }
    return $out;
}
