<?php
// moneygram-webdata.php
// Accepts uploaded Excel file and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../recon/daycard-locks-common.php';
reconDaycardLocksBoot();

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
require_once __DIR__ . '/moneygram-helper.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function moneygramNormalizeWebRowDate($rawDate, string $fallback = ''): string
{
    $rawDate = $rawDate !== null ? trim((string) $rawDate) : '';
    if ($rawDate === '') {
        $rawDate = trim($fallback);
    }

    if ($rawDate === '') {
        return '';
    }

    $normalized = moneygram_parse_date_claimed($rawDate);
    if ($normalized !== null) {
        return reconDaycardLocksNormalizeDate($normalized);
    }

    return reconDaycardLocksNormalizeDate($rawDate);
}

function moneygramExtractWebRowDates(array $row, string $fallback = ''): array
{
    $candidateKeys = ['DATE CLAIMED', 'DATE SEND', 'DATE', 'TRAN DATE', 'TRANSACTION DATE'];
    $normalizedRow = [];

    foreach ($row as $key => $value) {
        if (!is_string($key)) {
            continue;
        }

        $normalizedKey = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $key))));
        $normalizedRow[$normalizedKey] = $value;
    }

    $dates = [];
    foreach ($candidateKeys as $candidateKey) {
        if (array_key_exists($candidateKey, $normalizedRow)) {
            $dates[] = $normalizedRow[$candidateKey];
        }
    }

    if (empty($dates) && $fallback !== '') {
        $dates[] = $fallback;
    }

    return $dates;
}
$tmp = $file['tmp_name'];
try{
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    // Find the best header row by selecting the row with the highest number of non-empty cells.
    $headers = [];
    $headerRow = 1;
    $maxSearch = min(50, (int)$highestRow);
    $best = -1;
    for($rr = 1; $rr <= $maxSearch; $rr++){
        $tmpHeaders = [];
        for($c = 1; $c <= $highestColIndex; $c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $raw = trim((string)$sheet->getCell($colLetter . $rr)->getValue());
            if($raw !== '') $tmpHeaders[$c] = $raw;
        }
        if(count($tmpHeaders) > $best){
            $best = count($tmpHeaders);
            $headers = $tmpHeaders;
            $headerRow = $rr;
        }
        if($rr > 1 && count($tmpHeaders) < $best && $best >= 3) break;
    }

    if(empty($headers)){
        for($c = 1; $c <= $highestColIndex; $c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $headers[$c] = trim((string)$sheet->getCell($colLetter . '1')->getValue());
        }
        $headerRow = 1;
    }

    $cellVal = function(int $colIdx, int $rowIdx) use ($sheet): string {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $cell = $sheet->getCell($letter . $rowIdx);
        $val = $cell->getValue();
        if($val === null || $val === '') return '';

        if(is_numeric($val)){
            $fval = (float)$val;
            if($fval >= 1){
                try{
                    if(ExcelDate::isDateTime($cell)){
                        $dt = ExcelDate::excelToDateTimeObject($fval);
                        return $dt->format('Y-m-d H:i:s');
                    }
                }catch(Throwable $e){}
            }
            if($fval > 0 && $fval < 1){
                $totalSec = (int)round($fval * 86400);
                return sprintf('%02d:%02d:%02d', intdiv($totalSec, 3600), intdiv($totalSec % 3600, 60), $totalSec % 60);
            }
        }

        return (string)$val;
    };

    // choose an anchor column to avoid capturing table trailers/noise
    $anchorColIdx = null;
    foreach($headers as $colIdx => $h){
        $n = strtoupper((string)$h);
        if(strpos($n, 'REFERENCE') !== false || strpos($n, 'DATE') !== false || strpos($n, 'NO') !== false || strpos($n, 'ID') !== false){
            $anchorColIdx = $colIdx;
            break;
        }
    }
    if($anchorColIdx === null && !empty($headers)) $anchorColIdx = array_key_first($headers);

    $rows = [];
    $foundDateStr = '';
    $emptyStreak = 0;
    $footerKeywords = ['TOTAL COUNT','TOTAL AMOUNT','TOTAL CHARGE','GRAND TOTAL','SUMMARY','SUBTOTAL'];

    for($r = $headerRow + 1; $r <= $highestRow; $r++){
        $rowEmpty = true;
        for($c = 1; $c <= $highestColIndex; $c++){
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            if((string)$sheet->getCell($letter . $r)->getValue() !== ''){ $rowEmpty = false; break; }
        }
        if($rowEmpty){
            if(++$emptyStreak >= 3) break;
            continue;
        }
        $emptyStreak = 0;

        if($anchorColIdx !== null && trim($cellVal($anchorColIdx, $r)) === '') continue;

        $item = [];
        foreach($headers as $colIdx => $headerLabel){
            $label = trim((string)$headerLabel);
            if($label === '') $label = 'Column ' . $colIdx;
            $item[$label] = $cellVal($colIdx, $r);
        }

        // Detect footer/summary rows: if any cell contains known footer keywords, stop parsing further.
        $isFooter = false;
        foreach($item as $v){
            if($v === '') continue;
            $up = strtoupper((string)$v);
            foreach($footerKeywords as $kw){
                if(strpos($up, $kw) !== false){
                    $isFooter = true;
                    break 2;
                }
            }
        }
        if($isFooter){
            // stop reading further rows once we encounter trailer/summary rows
            break;
        }

        if($foundDateStr === ''){
            foreach($item as $v){
                if($v === '') continue;
                $ts = strtotime((string)$v);
                if($ts !== false && $ts > mktime(0,0,0,1,1,2000)){
                    $foundDateStr = date('F d, Y', $ts);
                    break;
                }
            }
        }

        $rows[] = $item;
    }

    if($foundDateStr === '') $foundDateStr = date('F d, Y');

    $rowDates = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        foreach (moneygramExtractWebRowDates($row, $foundDateStr) as $rawDate) {
            $dateOnly = moneygramNormalizeWebRowDate($rawDate, $foundDateStr);
            if ($dateOnly !== '') {
                $rowDates[$dateOnly] = true;
            }
        }
    }

    $blockedDates = reconDaycardLocksFindLockedDates(reconDaycardLocksDb(), 'MONEYGRAM', array_keys($rowDates));
    if (!empty($blockedDates)) {
        echo json_encode([
            'success' => false,
            'error' => reconDaycardLocksFormatBlockedUploadMessage('MONEYGRAM', $blockedDates),
            'errorCode' => 'daycard_locked',
        ]);
        exit;
    }

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
