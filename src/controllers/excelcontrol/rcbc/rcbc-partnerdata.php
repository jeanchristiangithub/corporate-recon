<?php
// rcbc-partnerdata.php
// Accepts uploaded Excel file (Partner Data format) and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$company = isset($_POST['company']) ? (string) $_POST['company'] : '';

$wicAliases = ['RCBC','RIZAL COMMERCIAL BANKING CORPORATION','RIZAL COMMERCIAL BANK','RIZAL BANK'];
if(!in_array(strtoupper(trim($company)), $wicAliases, true)){
    echo json_encode(['success'=>false,'error'=>'Partner extractor only allowed for RCBC']);
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

$allowedExt = ['xls','xlsx','csv'];
$fname = isset($_POST['filename'])?basename($_POST['filename']):$file['name'];
$ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
if(!in_array($ext, $allowedExt)){
    echo json_encode(['success'=>false,'error'=>'Unsupported file type']);
    exit;
}

$tmp = $file['tmp_name'];

$inferDateFromFilename = function(string $filename): ?string {
    $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));

    // Match compact dates like 021326, 02-13-26, 02_13_2026, etc.
    if(preg_match('/(?:^|[^0-9])(\d{2})[^0-9]?(\d{2})[^0-9]?(\d{2}|\d{4})(?:[^0-9]|$)/', $base, $m)){
        $mm = (int)$m[1];
        $dd = (int)$m[2];
        $yy = (int)$m[3];
        if($yy < 100) $yy += 2000;
        if(checkdate($mm, $dd, $yy)){
            return date('F d, Y', strtotime(sprintf('%04d-%02d-%02d', $yy, $mm, $dd)));
        }
    }

    // Match ISO-ish dates like 2026-02-13 or 20260213
    if(preg_match('/(20\d{2})[^0-9]?(\d{2})[^0-9]?(\d{2})/', $base, $m)){
        $yy = (int)$m[1];
        $mm = (int)$m[2];
        $dd = (int)$m[3];
        if(checkdate($mm, $dd, $yy)){
            return date('F d, Y', strtotime(sprintf('%04d-%02d-%02d', $yy, $mm, $dd)));
        }
    }

    return null;
};

// If CSV file, parse it with a lightweight CSV parser and map to partner fields
if($ext === 'csv'){
    $lines = @file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if($lines === false || count($lines) === 0){
        echo json_encode(['success'=>false,'error'=>'Empty or unreadable CSV file']);
        exit;
    }
    // header
    $hdr = str_getcsv(array_shift($lines));
    $hmap = [];
    foreach($hdr as $i => $h){ $k = strtolower(trim(trim($h), "\xEF\xBB\xBF \t\"'")); $hmap[$k] = $i; }
    $dateIdx = $hmap['date'] ?? ($hmap['date_claimed'] ?? ($hmap['cover_date'] ?? null));
    $transIdx = $hmap['transaction id'] ?? ($hmap['transactionid'] ?? ($hmap['reference no.'] ?? ($hmap['reference_no'] ?? ($hmap['ref_no'] ?? ($hmap['payout_id'] ?? null)))));
    $amountIdx = $hmap['amount'] ?? ($hmap['in php'] ?? ($hmap['php'] ?? ($hmap['bene_amt'] ?? ($hmap['total payable'] ?? ($hmap['total_payable'] ?? null)))));
    $coinIdx = $hmap['coin'] ?? ($hmap['currency'] ?? ($hmap['crc_code'] ?? null));

    $headersByIndex = [];
    foreach($hdr as $i => $h){
        $key = trim((string)$h);
        if($key === '') $key = 'col_' . ($i + 1);
        $headersByIndex[$i] = $key;
    }

    $rows = [];
    $foundDateStr = null;
    foreach($lines as $ln){
        $cells = str_getcsv($ln);
        $dateVal = $dateIdx !== null && isset($cells[$dateIdx]) ? trim($cells[$dateIdx]) : '';
        $transVal = $transIdx !== null && isset($cells[$transIdx]) ? trim($cells[$transIdx]) : '';
        $amtVal = $amountIdx !== null && isset($cells[$amountIdx]) ? trim($cells[$amountIdx]) : '';
        $coinVal = $coinIdx !== null && isset($cells[$coinIdx]) ? trim($cells[$coinIdx]) : '';

        if($foundDateStr === null && $dateVal !== ''){
            $ts = strtotime($dateVal);
            if($ts !== false) $foundDateStr = date('F d, Y', $ts);
            else $foundDateStr = $dateVal;
        }

        // produce both legacy keys and simple keys matching simplified DB schema
        $cleanAmt = preg_replace('/[^0-9\.-]/','', $amtVal);
        $row = [
            // legacy keys expected by existing partner insert flow
            'Date' => $dateVal,
            'Time' => '',
            'Reference No.' => $transVal,
            'RTS Tracer No.' => '',
            'Provider' => '',
            'Beneficiary Name' => '',
            'Remitter Name' => '',
            'PHP' => ($coinVal === 'PHP' || stripos($coinVal,'php')!==false) ? $cleanAmt : '',
            'USD' => ($coinVal === 'USD' || stripos($coinVal,'usd')!==false) ? $cleanAmt : '',
            'in PHP' => ($coinVal === 'PHP' || stripos($coinVal,'php')!==false) ? $cleanAmt : $cleanAmt,
            // simplified keys for the simple `rcbc_partner_data` table (date, transaction_id, amount, coin)
            'date' => $dateVal,
            'transaction_id' => $transVal,
            'amount' => $cleanAmt,
            'coin' => $coinVal,
        ];

        // keep original CSV columns so payout-style schemas are preserved in payload rows
        foreach($headersByIndex as $idx => $headerName){
            $row[$headerName] = isset($cells[$idx]) ? trim((string)$cells[$idx]) : '';
        }

        if($row['Reference No.'] === '') continue;
        $rows[] = $row;
    }

    if($foundDateStr === null){
        $foundDateStr = $inferDateFromFilename($fname);
    }
    if($foundDateStr === null) $foundDateStr = date('F d, Y');
    $payload = ['filename' => $fname, 'dateStr' => $foundDateStr, 'coverDate' => null, 'rows' => $rows];
    echo json_encode(['success' => true, 'payload' => $payload]);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

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

    $coverDateNormalized = null;
    $coverDateRaw = null;
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
                    if($ts !== false){
                        $coverDateNormalized = date('Y-m-d', $ts);
                    } else {
                        $dt = DateTime::createFromFormat('F d, Y', $coverDateRaw);
                        if($dt instanceof DateTime) $coverDateNormalized = $dt->format('Y-m-d');
                    }
                    break 2;
                }
            }
        }
    }

    $required = [
        'Date','Time','Reference No.','RTS Tracer No.','Provider','Beneficiary Name','Remitter Name','PHP','USD','in PHP'
    ];

    $headers = [];
    $headerRow = null;
    $requiredNorm = array_map(function($r){ return strtoupper($r); }, $required);
    $maxHeaderSearch = min(50, (int)$highestRow);
    for($rowCheck=1;$rowCheck<=$maxHeaderSearch;$rowCheck++){
        $found = 0;
        $tmpH = [];
        for($c=1;$c<=$highestColIndex;$c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $val = (string)$sheet->getCell($colLetter . $rowCheck)->getValue();
            $n = $normalizeHeader($val);
            if($n !== ''){
                $tmpH[$c] = $val;
                if(in_array($n, $requiredNorm, true)) $found++;
            }
        }
        if($found >= 2){
            $headers = $tmpH;
            $headerRow = $rowCheck;
            break;
        }
    }
    if($headerRow === null){
        $bestCount = -1;
        $bestRow = 1;
        for($rowCheck=1;$rowCheck<=$maxHeaderSearch;$rowCheck++){
            $count = 0;
            $tmpH = [];
            for($c=1;$c<=$highestColIndex;$c++){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $val = (string) $sheet->getCell($colLetter . $rowCheck)->getValue();
                if($val !== ''){
                    $tmpH[$c] = $val;
                    $count++;
                }
            }
            if($count > $bestCount){
                $bestCount = $count;
                $bestRow = $rowCheck;
                $bestHeaders = $tmpH;
            }
        }
        $headers = isset($bestHeaders) ? $bestHeaders : [];
        $headerRow = $bestRow;
    }

    $map = [];
    $normalizeKey = function($s){
        $t = (string)$s;
        $t = preg_replace('/^\xEF\xBB\xBF/', '', $t);
        $t = str_replace("\xC2\xA0", ' ', $t);
        $t = trim($t);
        $t = preg_replace('/\s+/u', ' ', $t);
        $t = preg_replace('/[^A-Z0-9 ]+/i', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));
        return strtoupper($t);
    };
    $requiredNormMap = [];
    foreach($required as $r){ $requiredNormMap[$r] = $normalizeKey($r); }
    uasort($requiredNormMap, function($a, $b){ return strlen($b) - strlen($a); });

    foreach($headers as $colIdx => $h){
        $hNorm = $normalizeKey($h);
        foreach($requiredNormMap as $orig => $rNorm){
            if($hNorm === $rNorm || strpos($hNorm, $rNorm) !== false){
                if(!isset($map[$orig])) $map[$orig] = $colIdx;
                break;
            }
        }
    }

    $hasLegacyDate = isset($map['Date']);

    // If legacy headers are not present (e.g., payout_id/ref_no/bene_amt schema),
    // build rows from all detected headers and map common aliases for UI/insert flow.
    if(!$hasLegacyDate){
        $rows = [];
        $foundDateStr = null;

        $findHeader = function(array $headers, array $candidates) use ($normalizeKey){
            foreach($headers as $colIdx => $h){
                $hn = $normalizeKey($h);
                foreach($candidates as $c){
                    if($hn === $normalizeKey($c)) return $colIdx;
                }
            }
            return null;
        };

        $dateIdx = $findHeader($headers, ['date','date claimed','date_claimed','cover date','cover_date']);
        $txIdx = $findHeader($headers, ['transaction id','transaction_id','reference no','reference_no','ref no','ref_no','payout id','payout_id']);
        $amtIdx = $findHeader($headers, ['amount','in php','php','bene amt','bene_amt','total payable','total_payable']);
        $coinIdx = $findHeader($headers, ['coin','currency','crc code','crc_code']);

        for($r=$headerRow+1;$r<=$highestRow;$r++){
            $item = [];
            foreach($headers as $colIdx => $h){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $val = $sheet->getCell($colLetter . $r)->getValue();
                $k = trim((string)$h);
                if($k === '') $k = 'col_' . $colIdx;
                $item[$k] = is_null($val) ? '' : (string)$val;
            }

            $txVal = '';
            if($txIdx !== null){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($txIdx);
                $txVal = trim((string)$sheet->getCell($colLetter . $r)->getValue());
            }
            if($txVal === '') continue;

            $dateVal = '';
            if($dateIdx !== null){
                $dateCell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateIdx) . $r);
                $rawDate = $dateCell->getValue();
                $dateVal = is_null($rawDate) ? '' : (string)$rawDate;
                if($foundDateStr === null && $dateVal !== ''){
                    if(is_numeric($rawDate) && ExcelDate::isDateTime($dateCell)){
                        $dt = ExcelDate::excelToDateTimeObject($rawDate);
                        $foundDateStr = $dt->format('F d, Y');
                    } else {
                        $ts = strtotime((string)$dateVal);
                        $foundDateStr = ($ts !== false) ? date('F d, Y', $ts) : (string)$dateVal;
                    }
                }
            }

            $amtVal = '';
            if($amtIdx !== null){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($amtIdx);
                $amtVal = (string)$sheet->getCell($colLetter . $r)->getValue();
            }
            $coinVal = '';
            if($coinIdx !== null){
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($coinIdx);
                $coinVal = trim((string)$sheet->getCell($colLetter . $r)->getValue());
            }

            $cleanAmt = preg_replace('/[^0-9\.-]/','', (string)$amtVal);
            $item['Date'] = $dateVal;
            $item['Reference No.'] = $txVal;
            $item['transaction_id'] = $txVal;
            $item['date'] = $dateVal;
            $item['amount'] = $cleanAmt;
            $item['coin'] = $coinVal;
            $item['PHP'] = ($coinVal === 'PHP' || stripos($coinVal,'php')!==false) ? $cleanAmt : '';
            $item['USD'] = ($coinVal === 'USD' || stripos($coinVal,'usd')!==false) ? $cleanAmt : '';
            $item['in PHP'] = $cleanAmt;

            $rows[] = $item;
        }

        if($foundDateStr === null && !empty($coverDateNormalized)){
            $ts = strtotime($coverDateNormalized);
            if($ts !== false) $foundDateStr = date('F d, Y', $ts);
        }
        if($foundDateStr === null){
            $foundDateStr = $inferDateFromFilename($fname);
        }
        if($foundDateStr === null) $foundDateStr = date('F d, Y');

        $payload = [
            'filename' => $fname,
            'dateStr' => $foundDateStr,
            'coverDate' => $coverDateNormalized,
            'rows' => $rows,
            'partnerName' => 'RCBC',
        ];

        echo json_encode(['success' => true, 'payload' => $payload]);
        exit;
    }

    $rows = [];
    $foundDateStr = null;

    $stopByRef = isset($map['Reference No.']);
    $refColIdx = $stopByRef ? $map['Reference No.'] : null;
    $dateColIdx = isset($map['Date']) ? $map['Date'] : null;

    for($r=$headerRow+1;$r<=$highestRow;$r++){
        $dateCell = null;
        $dateVal = null;
        if($dateColIdx !== null){
            $dateColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateColIdx);
            $dateCell = $sheet->getCell($dateColLetter . $r);
            $dateVal = $dateCell->getValue();
        }

        if($stopByRef && $refColIdx !== null){
            $refColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($refColIdx);
            $refCell = $sheet->getCell($refColLetter . $r);
            $refVal = $refCell->getValue();
            if($refVal === null || trim((string)$refVal) === ''){
                break;
            }
        } elseif($dateColIdx !== null){
            if($dateVal === null || trim((string)$dateVal) === ''){
                break;
            }
        }

        if($foundDateStr === null){
            if($dateVal !== null && is_numeric($dateVal) && ExcelDate::isDateTime($dateCell)){
                $dt = ExcelDate::excelToDateTimeObject($dateVal);
                $foundDateStr = $dt->format('F d, Y');
            } else {
                $ts = strtotime((string)$dateVal);
                if($ts !== false){
                    $foundDateStr = date('F d, Y', $ts);
                } else {
                    $foundDateStr = (string)$dateVal;
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

        $refValForRow = isset($item['Reference No.']) ? trim($item['Reference No.']) : '';
        if($refValForRow === ''){
            continue;
        }

        $rows[] = $item;
    }

    if($foundDateStr === null && !empty($coverDateNormalized)){
        $ts = strtotime($coverDateNormalized);
        if($ts !== false) $foundDateStr = date('F d, Y', $ts);
    }
    if($foundDateStr === null){
        $foundDateStr = $inferDateFromFilename($fname);
    }
    if($foundDateStr === null) $foundDateStr = date('F d, Y');

    $payload = [
        'filename' => $fname,
        'dateStr' => $foundDateStr,
        'coverDate' => $coverDateNormalized,
        'rows' => $rows,
        'partnerName' => 'RCBC',
    ];

    echo json_encode(['success' => true, 'payload' => $payload]);
    exit;

} catch (Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Exception: '.$e->getMessage()]);
    exit;
}

