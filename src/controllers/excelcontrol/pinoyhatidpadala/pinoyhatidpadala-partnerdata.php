<?php
// pinoyhatidpadala-partnerdata.php
// Accepts uploaded Excel file (Partner Data format) and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Ensure caller intended PINOYHATIDPADALA processing
$company = isset($_POST['company']) ? (string) $_POST['company'] : '';
$aliases = ['PINOYHATIDPADALA','PINOY HATID PADALA','PINOY HATIDPADALA'];
if(!in_array(strtoupper(trim($company)), $aliases, true)){
    echo json_encode(['success'=>false,'error'=>'Partner extractor only allowed for PINOYHATIDPADALA']);
    exit;
}

if(empty($_FILES['file'])){
    echo json_encode(['success'=>false,'error'=>'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if($file['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['success'=>false,'error'=>'Upload error code: '.$file['error']]);
    exit;
}

$allowedExt = ['xls','xlsx'];
$fname = isset($_POST['filename'])?basename($_POST['filename']):$file['name'];
$ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
if(!in_array($ext, $allowedExt)){
    echo json_encode(['success'=>false,'error'=>'Unsupported file type']);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$tmp = $file['tmp_name'];
try{
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    $normalizeHeader = function($v){
        $s = (string)$v;
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);
        if(function_exists('mb_strtoupper')) $s = mb_strtoupper($s);
        else $s = strtoupper($s);
        return $s;
    };

    $coverDateNormalized = null; $coverDateRaw = null;
    $searchRows = min(20, (int)$highestRow);
    for($rr=1;$rr<=$searchRows;$rr++){
        for($cc=1;$cc<=min(10,$highestColIndex);$cc++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cc);
            $v = (string) $sheet->getCell($colLetter . $rr)->getValue();
            if($v === '') continue;
            if(preg_match('/period\s+covered/i', $v)){
                if(preg_match('/([A-Za-z]{3,9}\s+\d{1,2},?\s*\d{4})/i', $v, $m)){
                    $coverDateRaw = $m[1];
                    $ts = strtotime($coverDateRaw);
                    if($ts !== false) $coverDateNormalized = date('Y-m-d', $ts);
                    else { $dt = DateTime::createFromFormat('F d, Y', $coverDateRaw); if($dt instanceof DateTime) $coverDateNormalized = $dt->format('Y-m-d'); }
                    break 2;
                }
            }
        }
    }

    $required = [ 'Date','Time','Reference No.','RTS Tracer No.','Provider','Beneficiary Name','Remitter Name','PHP','USD','in PHP' ];

    $headers = []; $headerRow = null; $requiredNorm = array_map(function($r){ return strtoupper($r); }, $required);
    $maxHeaderSearch = min(50, (int)$highestRow);
    for($rowCheck=1;$rowCheck<=$maxHeaderSearch;$rowCheck++){
        $found = 0; $tmpH = [];
        for($c=1;$c<=$highestColIndex;$c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = (string)$sheet->getCell($colLetter . $rowCheck)->getValue();
            $n = $normalizeHeader($val);
            if($n !== ''){ $tmpH[$c] = $val; if(in_array($n, $requiredNorm, true)) $found++; }
        }
        if($found >= 2){ $headers = $tmpH; $headerRow = $rowCheck; break; }
    }
    if($headerRow === null){
        $bestCount = -1; $bestRow = 1;
        for($rowCheck=1;$rowCheck<=$maxHeaderSearch;$rowCheck++){
            $count = 0; $tmpH = [];
            for($c=1;$c<=$highestColIndex;$c++){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $val = (string) $sheet->getCell($colLetter . $rowCheck)->getValue();
                if($val !== ''){ $tmpH[$c] = $val; $count++; }
            }
            if($count > $bestCount){ $bestCount = $count; $bestRow = $rowCheck; $bestHeaders = $tmpH; }
        }
        $headers = isset($bestHeaders) ? $bestHeaders : []; $headerRow = $bestRow;
    }

    $map = [];
    $normalizeKey = function($s){
        $t = (string)$s; $t = preg_replace('/^\xEF\xBB\xBF/', '', $t); $t = str_replace("\xC2\xA0", ' ', $t); $t = trim($t); $t = preg_replace('/\s+/u', ' ', $t); $t = preg_replace('/[^A-Z0-9 ]+/i', ' ', $t); $t = trim(preg_replace('/\s+/', ' ', $t)); return strtoupper($t);
    };
    $requiredNormMap = []; foreach($required as $r){ $requiredNormMap[$r] = $normalizeKey($r); } uasort($requiredNormMap, function($a,$b){ return strlen($b)-strlen($a); });

    foreach($headers as $colIdx => $h){ $hNorm = $normalizeKey($h); foreach($requiredNormMap as $orig => $rNorm){ if($hNorm === $rNorm || strpos($hNorm,$rNorm)!==false){ if(!isset($map[$orig])) $map[$orig]=$colIdx; break; } } }

    if(!isset($map['Date'])){ echo json_encode(['success'=>false,'error'=>'Required column missing: Date']); exit; }

    $rows = []; $foundDateStr = null; $stopByRef = isset($map['Reference No.']); $refColIdx = $stopByRef ? $map['Reference No.'] : null; $dateColIdx = isset($map['Date']) ? $map['Date'] : null;

    for($r=$headerRow+1;$r<=$highestRow;$r++){
        $dateCell = null; $dateVal = null; if($dateColIdx !== null){ $dateColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateColIdx); $dateCell = $sheet->getCell($dateColLetter . $r); $dateVal = $dateCell->getValue(); }
        if($stopByRef && $refColIdx !== null){ $refColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($refColIdx); $refCell = $sheet->getCell($refColLetter . $r); $refVal = $refCell->getValue(); if($refVal === null || trim((string)$refVal) === ''){ break; } }
        elseif($dateColIdx !== null){ if($dateVal === null || trim((string)$dateVal) === ''){ break; } }

        if($foundDateStr === null){ if($dateVal !== null && is_numeric($dateVal) && ExcelDate::isDateTime($dateCell)){ $dt = ExcelDate::excelToDateTimeObject($dateVal); $foundDateStr = $dt->format('F d, Y'); } else { $ts = strtotime((string)$dateVal); if($ts !== false) $foundDateStr = date('F d, Y', $ts); else $foundDateStr = (string)$dateVal; } }

        $item = []; foreach($required as $colName){ if(isset($map[$colName])){ $colIdx = $map[$colName]; $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx); $val = $sheet->getCell($colLetter . $r)->getValue(); $item[$colName] = is_null($val) ? '' : (string) $val; } else { $item[$colName] = ''; } }

        $refValForRow = isset($item['Reference No.']) ? trim($item['Reference No.']) : ''; if($refValForRow === ''){ continue; }
        $rows[] = $item;
    }

    if($foundDateStr === null) $foundDateStr = date('F d, Y');

    $payload = ['filename'=>$fname,'dateStr'=>$foundDateStr,'coverDate'=>$coverDateNormalized,'rows'=>$rows];
    echo json_encode(['success'=>true,'payload'=>$payload]); exit;

} catch (Throwable $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>'Exception: '.$e->getMessage()]); exit; }
