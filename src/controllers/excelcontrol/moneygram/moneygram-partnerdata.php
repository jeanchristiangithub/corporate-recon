<?php
// moneygram-partnerdata.php
// Accepts uploaded Excel file (Partner Data format) and returns extracted rows and formatted date as JSON

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../recon/daycard-locks-common.php';
reconDaycardLocksBoot();

// Ensure caller intended MONEYGRAM processing
$company = isset($_POST['company']) ? (string) $_POST['company'] : '';
$moneygramAliases = ['MONEYGRAM'];
if(!in_array(strtoupper(trim($company)), $moneygramAliases, true)){
    echo json_encode(['success'=>false,'error'=>'Partner extractor only allowed for MONEYGRAM']);
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
require_once __DIR__ . '/moneygram-helper.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function moneygramNormalizePartnerRowDate($rawDate, string $fallback = ''): string
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

function moneygramExtractPartnerRowDates(array $row, string $fallback = ''): array
{
    $candidateKeys = ['TRAN DATE', 'TRANSACTION DATE', 'DATE', 'UPLOAD DATE'];
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
    $base = tempnam(sys_get_temp_dir(), 'moneygram_dec_');
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

    $headers = [];
    $headerRow = 1;
    $maxSearch = min(50, (int)$highestRow);
    $best = -1;

    for($rr = 1; $rr <= $maxSearch; $rr++){
        $tmpH = [];
        for($c = 1; $c <= $highestColIndex; $c++){
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $raw = (string)$sheet->getCell($colLetter . $rr)->getValue();
            if($raw !== '') $tmpH[$c] = trim($raw);
        }
        if(count($tmpH) > $best){
            $best = count($tmpH);
            $headers = $tmpH;
            $headerRow = $rr;
        }
        if($rr > 1 && count($tmpH) < $best && $best >= 3) break;
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
                    if(\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)){
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

    $anchorColIdx = null;
    foreach($headers as $colIdx => $h){
        $n = strtoupper((string)$h);
        if(strpos($n, 'REFERENCE') !== false || strpos($n, 'DATE') !== false || strpos($n, 'NO') !== false || strpos($n, 'ID') !== false){
            $anchorColIdx = $colIdx;
            break;
        }
    }
    if($anchorColIdx === null && !empty($headers)){
        $anchorColIdx = array_key_first($headers);
    }

    $rows = [];
    $foundDateStr = '';
    $emptyStreak = 0;

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
            $item[$headerLabel] = $cellVal($colIdx, $r);
        }

        if($foundDateStr === ''){
            foreach($item as $v){
                if($v === '') continue;
                $ts = strtotime($v);
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

        foreach (moneygramExtractPartnerRowDates($row, $foundDateStr) as $rawDate) {
            $dateOnly = moneygramNormalizePartnerRowDate($rawDate, $foundDateStr);
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
