<?php
// bpiremittance-helper.php
// Shared helpers for BPIREMITTANCE extraction, normalization and checks

require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function bpiremittance_normalize_amount($val){
    $v = trim((string)$val);
    if($v === '') return null;
    $v = preg_replace('/[^0-9,\.\-() ]+/', '', $v);
    $neg = false;
    if(preg_match('/^\(.*\)$/', $v)){
        $neg = true;
        $v = trim($v, "() ");
    }
    if(strpos($v, ',') !== false && strpos($v, '.') !== false){
        $v = str_replace(',', '', $v);
    } elseif(strpos($v, ',') !== false && strpos($v, '.') === false){
        $v = str_replace(',', '.', $v);
    }
    $v = str_replace(' ', '', $v);
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if($v === '' || !is_numeric($v)) return null;
    if($neg) $v = '-' . $v;
    return $v;
}

function bpiremittance_parse_date_claimed($raw){
    if($raw === null) return null;
    $raw = trim((string)$raw);
    if($raw === '') return null;
    // numeric excel serial
    if(is_numeric($raw)){
        try{
            $dt = ExcelDate::excelToDateTimeObject((float)$raw);
            return $dt->format('Y-m-d H:i:s');
        }catch(Throwable $e){
            // fall through
        }
    }
    $ts = strtotime($raw);
    if($ts !== false){
        return date('Y-m-d H:i:s', $ts);
    }
    try{
        $dt = DateTime::createFromFormat('F d, Y', $raw);
        if($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    }catch(Throwable $e){}
    return null;
}

function bpiremittance_build_pairs_from_payloads(array $payloads){
    $pairs = [];
    foreach($payloads as $pl){
        $dateStr = $pl['dateStr'] ?? '';
        $rows = $pl['rows'] ?? [];
        foreach($rows as $r){
            $ccref = isset($r['CCREF NO']) ? trim((string)$r['CCREF NO']) : '';
            $rawDate = ($r['DATE CLAIMED'] ?? $dateStr ?? '');
            if($ccref !== ''){
                $pairs[] = ['ccref_no'=>$ccref, 'raw_date'=>$rawDate, 'date_claimed'=>bpiremittance_parse_date_claimed($rawDate)];
            }
        }
    }
    return $pairs;
}

function bpiremittance_normalize_pairs(array $pairs){
    $out = [];
    foreach($pairs as $p){
        $cc = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
        $raw = $p['date_claimed'] ?? ($p['raw_date'] ?? ($p['date'] ?? ''));
        $norm = bpiremittance_parse_date_claimed($raw);
        $out[] = ['ccref_no'=>$cc, 'raw_date'=>$raw, 'normalized'=>$norm];
    }
    return $out;
}

// Partner-specific helpers
function bpiremittance_partner_normalize_currency($val){
    $v = trim((string)$val);
    if($v === '') return null;
    $v = preg_replace('/[^0-9,\.\-() ]+/', '', $v);
    $neg = false;
    if(preg_match('/^\(.*\)$/', $v)){
        $neg = true;
        $v = trim($v, "() ");
    }
    if(strpos($v, ',') !== false && strpos($v, '.') !== false){
        $v = str_replace(',', '', $v);
    } elseif(strpos($v, ',') !== false && strpos($v, '.') === false){
        $v = str_replace(',', '.', $v);
    }
    $v = str_replace(' ', '', $v);
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if($v === '' || !is_numeric($v)) return null;
    if($neg) $v = '-' . $v;
    return $v;
}

function bpiremittance_partner_parse_date_time($dateRaw, $timeRaw = null){
    if($dateRaw === null || $dateRaw === '') return null;
    $d = trim((string)$dateRaw);
    $t = $timeRaw !== null ? trim((string)$timeRaw) : '';
    if(is_numeric($d)){
        try{ $dt = ExcelDate::excelToDateTimeObject((float)$d); if($t !== ''){ $ts = strtotime($t); if($ts !== false){ $dt->setTime((int)date('H',$ts),(int)date('i',$ts),(int)date('s',$ts)); } } return $dt->format('Y-m-d H:i:s'); }catch(Throwable $e){}
    }
    $ts = strtotime($d . ' ' . $t);
    if($ts !== false) return date('Y-m-d H:i:s', $ts);
    try{ $dt = DateTime::createFromFormat('F d, Y', $d); if($dt instanceof DateTime){ if($t !== ''){ $tTs = strtotime($t); if($tTs !== false){ $dt->setTime((int)date('H',$tTs),(int)date('i',$tTs),(int)date('s',$tTs)); } } return $dt->format('Y-m-d H:i:s'); } }catch(Throwable $e){}
    return null;
}

function bpiremittance_partner_build_pairs_from_payloads(array $payloads){
    $pairs = [];
    foreach($payloads as $pl){
        $dateStr = $pl['dateStr'] ?? '';
        $rows = $pl['rows'] ?? [];
        foreach($rows as $r){
            $ref = isset($r['Reference No.']) ? trim((string)$r['Reference No.']) : '';
            $rawDate = ($r['Date'] ?? $dateStr ?? '');
            $rawTime = $r['Time'] ?? null;
            if($ref !== ''){
                $pairs[] = ['reference_no'=>$ref, 'raw_date'=>$rawDate, 'raw_time'=>$rawTime, 'date_time'=>bpiremittance_partner_parse_date_time($rawDate, $rawTime)];
            }
        }
    }
    return $pairs;
}

?>
