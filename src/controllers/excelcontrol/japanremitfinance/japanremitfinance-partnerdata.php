<?php
// japanremitfinance-partnerdata.php
// Extract partner format files (if JapanRemit partner uses partner-style sheets)

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

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/japanremitfinance-helper.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$tmp = $file['tmp_name'];
try{
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestCol = $sheet->getHighestColumn();
    $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    // Attempt to find header row for partner format
    $headers = [];
    $headerRow = 1;
    for($c=1;$c<=$highestColIndex;$c++){
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $val = (string)$sheet->getCell($colLetter . $headerRow)->getValue();
        if($val !== '') $headers[$c] = $val;
    }

    $rows = [];
    for($r=$headerRow+1;$r<=$highestRow;$r++){
        $refCol = null;
        // find Reference No. column index heuristically
        foreach($headers as $idx => $h){
            $n = strtoupper(trim((string)$h));
            if(strpos($n, 'REFERENCE') !== false || strpos($n, 'REFERENCE NO') !== false){ $refCol = $idx; break; }
        }
        if($refCol === null){ break; }
        $refLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($refCol);
        $ref = trim((string)$sheet->getCell($refLetter . $r)->getValue());
        if($ref === '') break;

        $item = [];
        foreach($headers as $colIdx => $h){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $val = $sheet->getCell($colLetter . $r)->getValue();
            $item[trim((string)$h)] = is_null($val) ? '' : (string)$val;
        }
        $rows[] = $item;
    }

    $payload = [ 'filename'=>isset($file['name'])?$file['name']:'', 'dateStr'=>'', 'rows'=>$rows ];
    echo json_encode(['success'=>true,'payload'=>$payload]); exit;

} catch(Throwable $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }
