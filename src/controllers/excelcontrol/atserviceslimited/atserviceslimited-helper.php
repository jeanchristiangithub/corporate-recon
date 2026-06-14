<?php
// atserviceslimited-helper.php
require_once __DIR__ . '/../../../config/db.php';

function atserviceslimited_normalize_amount($v){
    if($v === null) return 0.0;
    $s = (string)$v;
    $s = str_replace([',',' '], ['', ''], $s);
    $s = preg_replace('/[^0-9\.\-]/', '', $s);
    if($s === '' || $s === '-' ) return 0.0;
    return (float)$s;
}

function atserviceslimited_partner_normalize_currency($v){
    return atserviceslimited_normalize_amount($v);
}

function atserviceslimited_partner_parse_date_time($dateRaw, $timeRaw){
    if($dateRaw === null || $dateRaw === '') return null;
    // Try ISO first
    if(preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$dateRaw)){
        $d = (string)$dateRaw;
        if($timeRaw !== null && $timeRaw !== '') return $d . ' ' . $timeRaw;
        return $d;
    }
    $ts = strtotime((string)$dateRaw . ' ' . ($timeRaw ?? ''));
    if($ts !== false) return date('Y-m-d H:i:s', $ts);
    return null;
}
