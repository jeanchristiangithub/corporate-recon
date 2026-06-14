<?php
// wic-webdata.php
// Accepts uploaded Excel file and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if(empty($_FILES['file'])){
    echo json_encode(['success'=>false,'error'=>'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if($file['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['success'=>false,'error'=>'Upload error code: '.$file['error']]);
    exit;
}

$allowedExt = ['xls','xlsx','xlsm','xlsb','ods','csv'];
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

    $required = [
        'NO','CONTROL SERIES NO','DATE CLAIMED','KPTN','CCREF NO','CURRENCY','AMOUNT','CTC','CTP','SENDER NAME','SENDER COUNTRY','BENEFICIARY/RECEIVER','RECEIVER KYC','RECEIVER PHONE','OPERATOR','BRANCH','REMOTE OPERATOR','REMOTE BRANCH'
    ];

    $headers = [];
    $headerRow = null;
    $requiredNorm = array_map(function($r){ return strtoupper($r); }, $required);
    $maxHeaderSearch = min(12, (int)$highestRow);
    for($rowCheck=1;$rowCheck<=$maxHeaderSearch;$rowCheck++){
        $found = 0;
        $tmp = [];
        for($c=1;$c<=$highestColIndex;$c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = (string)$sheet->getCell($colLetter . $rowCheck)->getValue();
            $n = $normalizeHeader($val);
            if($n !== ''){
                $tmp[$c] = $val;
                if(in_array($n, $requiredNorm, true)) $found++;
            }
        }
        if($found >= 2){
            $headers = $tmp;
            $headerRow = $rowCheck;
            break;
        }
    }
    if($headerRow === null){
        for($c=1;$c<=$highestColIndex;$c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = (string) $sheet->getCell($colLetter . '1')->getValue();
            if($val !== '') $headers[$c] = $val;
        }
        $headerRow = 1;
    }

    $required = [
        'NO','CONTROL SERIES NO','DATE CLAIMED','KPTN','CCREF NO','CURRENCY','AMOUNT','CTC','CTP','SENDER NAME','SENDER COUNTRY','BENEFICIARY/RECEIVER','RECEIVER KYC','RECEIVER PHONE','OPERATOR','BRANCH','REMOTE OPERATOR','REMOTE BRANCH'
    ];

    $map = [];
    foreach($headers as $colIdx => $h){
        $hNorm = $normalizeHeader($h);
        foreach($required as $r){
            if($hNorm === $normalizeHeader($r)){
                $map[$r] = $colIdx;
                break;
            }
        }
    }

    if(!isset($map['CCREF NO']) || !isset($map['DATE CLAIMED'])){
        echo json_encode(['success' => false, 'error' => 'Required columns missing: CCREF NO or DATE CLAIMED']);
        exit;
    }

    $rows = [];
    $foundDateStr = null;

    for($r=$headerRow+1;$r<=$highestRow;$r++){
        $ccrefColIdx = $map['CCREF NO'];
        $ccrefColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ccrefColIdx);
        $ccrefCell = $sheet->getCell($ccrefColLetter . $r);
        $ccref = trim((string) $ccrefCell->getValue());
        if($ccref === ''){
            break;
        }

        if($foundDateStr === null){
            $dateColIdx = $map['DATE CLAIMED'];
            $dateColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateColIdx);
            $cellObj = $sheet->getCell($dateColLetter . $r);
            $v = $cellObj->getValue();
            if(is_numeric($v) && ExcelDate::isDateTime($cellObj)){
                $dt = ExcelDate::excelToDateTimeObject($v);
                $foundDateStr = $dt->format('F d, Y');
            } else {
                $ts = strtotime((string)$v);
                if($ts !== false){
                    $foundDateStr = date('F d, Y', $ts);
                } else {
                    $foundDateStr = (string)$v;
                }
            }
        }

        $item = [];
        foreach($required as $colName){
            if(isset($map[$colName])){
                $colIdx = $map[$colName];
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $val = $sheet->getCell($colLetter . $r)->getValue();
                $item[$colName] = is_null($val) ? '' : (string) $val;
            } else {
                $item[$colName] = '';
            }
        }
        $rows[] = $item;
    }

    if($foundDateStr === null) $foundDateStr = date('F d, Y');

    $payload = [
        'filename' => $fname,
        'dateStr' => $foundDateStr,
        'rows' => $rows,
    ];

    echo json_encode(['success' => true, 'payload' => $payload]);
    exit;

 } catch (Throwable $e){
     http_response_code(500);
     echo json_encode(['success'=>false,'error'=>'Exception: '.$e->getMessage()]);
     exit;
 }
