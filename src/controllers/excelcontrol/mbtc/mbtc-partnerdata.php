<?php
// mbtc-partnerdata.php
// Accepts uploaded Excel file (Partner Data format) and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Ensure caller intended MBTC processing
$company = isset($_POST['company']) ? (string) $_POST['company'] : '';
$mbtcAliases = ['MBTC', 'METROBANK HEAD OFFICE'];
if(!in_array(strtoupper(trim($company)), $mbtcAliases, true)){
    echo json_encode(['success'=>false,'error'=>'Partner extractor only allowed for MBTC']);
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

function isWorkbookEncryptionException(Throwable $e): bool
{
    $message = strtolower((string) $e->getMessage());
    return strpos($message, 'unsupported encryption algorithm') !== false
        || strpos($message, 'file is encrypted') !== false
        || strpos($message, 'decryption password incorrect') !== false
        || strpos($message, 'encrypted') !== false;
}

function buildDecryptOutputPath(string $originalName): string
{
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $extension = $extension !== '' ? '.' . $extension : '.xlsx';
    $base = tempnam(sys_get_temp_dir(), 'mbtc_dec_');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary file for decryption.');
    }

    $target = $base . $extension;
    if (!@rename($base, $target)) {
        @unlink($base);
        throw new RuntimeException('Unable to prepare decrypted workbook path.');
    }

    return $target;
}

function tryDecryptWorkbook(string $inputPath, string $originalName, string $password): string
{
    if ($password === '') {
        throw new RuntimeException('Encrypted workbook requires a password.');
    }

    $outputPath = buildDecryptOutputPath($originalName);
    $appData = getenv('APPDATA');
    $scriptCandidates = $appData ? glob($appData . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python*' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'msoffcrypto-tool.exe') : [];
    $scriptPath = (!empty($scriptCandidates) && is_array($scriptCandidates)) ? (string) reset($scriptCandidates) : '';
    $commands = [
        'msoffcrypto-tool -p %s %s %s',
        'python -m msoffcrypto -p %s %s %s',
        'py -m msoffcrypto -p %s %s %s',
        'python -m msoffcrypto.cli -p %s %s %s',
        'py -m msoffcrypto.cli -p %s %s %s',
    ];

    if ($scriptPath !== '' && is_file($scriptPath)) {
        array_unshift($commands, escapeshellarg($scriptPath) . ' -p %s %s %s');
    }

    $escapedPassword = escapeshellarg($password);
    $escapedInput = escapeshellarg($inputPath);
    $escapedOutput = escapeshellarg($outputPath);
    $lastOutput = '';

    foreach ($commands as $template) {
        $command = sprintf($template, $escapedPassword, $escapedInput, $escapedOutput) . ' 2>&1';
        $lines = [];
        $exitCode = 1;
        @exec($command, $lines, $exitCode);
        $lastOutput = trim(implode(PHP_EOL, $lines));

        if ($exitCode === 0 && is_file($outputPath) && filesize($outputPath) > 0) {
            return $outputPath;
        }

        if (is_file($outputPath)) {
            @unlink($outputPath);
            $outputPath = buildDecryptOutputPath($originalName);
            $escapedOutput = escapeshellarg($outputPath);
        }
    }

    if (is_file($outputPath)) {
        @unlink($outputPath);
    }

    throw new RuntimeException('Unable to decrypt workbook with the provided password.' . ($lastOutput !== '' ? ' ' . $lastOutput : ''));
}

function loadSpreadsheetWithFallback(string $inputPath, string $originalName, string $password): array
{
    try {
        return [
            'spreadsheet' => IOFactory::load($inputPath),
            'decryptedPath' => null,
        ];
    } catch (Throwable $e) {
        if (!isWorkbookEncryptionException($e)) {
            throw $e;
        }

        $decryptedPath = tryDecryptWorkbook($inputPath, $originalName, $password);

        try {
            return [
                'spreadsheet' => IOFactory::load($decryptedPath),
                'decryptedPath' => $decryptedPath,
            ];
        } catch (Throwable $inner) {
            @unlink($decryptedPath);
            throw $inner;
        }
    }
}

$tmp = $file['tmp_name'];
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$decryptedTmp = null;
try{
    $loadResult = loadSpreadsheetWithFallback($tmp, $fname, $password);
    $spreadsheet = $loadResult['spreadsheet'];
    $decryptedTmp = $loadResult['decryptedPath'];
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

    // attempt to locate cover date (e.g. "For Period Covered [Feb 05 2026 to Feb 05 2026]")
    $coverDateNormalized = null; // YYYY-MM-DD
    $coverDateRaw = null;
    $searchRows = min(20, (int)$highestRow);
    for($rr=1;$rr<=$searchRows;$rr++){
        for($cc=1;$cc<=min(10,$highestColIndex);$cc++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cc);
            $v = (string) $sheet->getCell($colLetter . $rr)->getValue();
            if($v === '') continue;
            if(preg_match('/period\s+covered/i', $v)){
                // try extract first date-like token (e.g. 'Feb 05 2026' or 'February 05, 2026')
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

    // partner required columns (use Title Case exactly as provided by user)
    $required = [
        'Date','Time','Reference No.','RTS Tracer No.','Provider','Beneficiary Name','Remitter Name','PHP','USD','in PHP'
    ];

    // search header row in the first N rows (don't assume row 1)
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
    // if header row still not found, pick the row with most non-empty cells within the same search range
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

    // map headers with tolerant matching (normalize and allow fuzzy matches)
    $map = [];
    $normalizeKey = function($s){
        $t = (string)$s;
        $t = preg_replace('/^\xEF\xBB\xBF/', '', $t);
        $t = str_replace("\xC2\xA0", ' ', $t);
        $t = trim($t);
        $t = preg_replace('/\s+/u', ' ', $t);
        // keep only letters, numbers and spaces for comparison
        $t = preg_replace('/[^A-Z0-9 ]+/i', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', $t));
        return strtoupper($t);
    };
    // precompute normalized required keys (map expected label -> normalized form)
    $requiredNormMap = [];
    foreach($required as $r){ $requiredNormMap[$r] = $normalizeKey($r); }
    // prefer matching more specific/longer labels first (e.g. 'IN PHP' before 'PHP')
    uasort($requiredNormMap, function($a, $b){ return strlen($b) - strlen($a); });

    foreach($headers as $colIdx => $h){
        $hNorm = $normalizeKey($h);
        foreach($requiredNormMap as $orig => $rNorm){
            // exact match or header contains required label (allow 'Date (MM/DD/YYYY)' etc.)
            if($hNorm === $rNorm || strpos($hNorm, $rNorm) !== false){
                if(!isset($map[$orig])) $map[$orig] = $colIdx;
                break;
            }
        }
    }

    if(!isset($map['Date'])){
        echo json_encode(['success'=>false,'error'=>'Required column missing: Date']);
        exit;
    }

    $rows = [];
    $foundDateStr = null;


    // decide stopping column: prefer Reference No. if available, else Date
    $stopByRef = isset($map['Reference No.']);
    $refColIdx = $stopByRef ? $map['Reference No.'] : null;
    $dateColIdx = isset($map['Date']) ? $map['Date'] : null;

    for($r=$headerRow+1;$r<=$highestRow;$r++){
        // fetch date cell/value if available (used for both stopping and payload)
        $dateCell = null;
        $dateVal = null;
        if($dateColIdx !== null){
            $dateColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateColIdx);
            $dateCell = $sheet->getCell($dateColLetter . $r);
            $dateVal = $dateCell->getValue();
        }

        // check stopping condition using Reference No. if present
        if($stopByRef && $refColIdx !== null){
            $refColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($refColIdx);
            $refCell = $sheet->getCell($refColLetter . $r);
            $refVal = $refCell->getValue();
            if($refVal === null || trim((string)$refVal) === ''){
                // treat empty reference as end-of-data
                break;
            }
        } elseif($dateColIdx !== null){
            if($dateVal === null || trim((string)$dateVal) === ''){
                break;
            }
        }

        // capture first row dateStr if not yet captured
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

        // only record rows that have a Reference No. (per your instruction)
        $refValForRow = isset($item['Reference No.']) ? trim($item['Reference No.']) : '';
        if($refValForRow === ''){
            // skip rows without reference number
            continue;
        }

        $rows[] = $item;
    }

    if($foundDateStr === null) $foundDateStr = date('F d, Y');

    $payload = [
        'filename' => $fname,
        'dateStr' => $foundDateStr,
        'coverDate' => $coverDateNormalized,
        'rows' => $rows,
    ];

    echo json_encode(['success' => true, 'payload' => $payload]);
    exit;

} catch (Throwable $e){
    $message = (string) $e->getMessage();

    if (stripos($message, 'Encrypted workbook requires a password') !== false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'This workbook is encrypted. Enter the file password and try again.',
            'errorCode' => 'encrypted_password_required',
        ]);
        exit;
    }

    if (stripos($message, 'Unable to decrypt workbook with the provided password') !== false || stripos($message, 'decryption password incorrect') !== false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to decrypt workbook with the provided password.',
            'errorCode' => 'decrypt_failed',
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Exception: '.$message]);
    exit;
} finally {
    if ($decryptedTmp && is_file($decryptedTmp)) {
        @unlink($decryptedTmp);
    }
}
