<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

header('Content-Type: application/json; charset=UTF-8');
bootSecureSession();

function respondJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function cellText($value): string
{
    if ($value === null) return '';
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d H:i:s');
    if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
    return trim((string)$value);
}

function dateText($value): string
{
    $text = cellText($value);
    if ($text === '') return '';
    if (is_numeric($text)) {
        try {
            return ExcelDate::excelToDateTimeObject((float)$text)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return $text;
        }
    }
    $ts = strtotime($text);
    return $ts === false ? $text : date('Y-m-d H:i:s', $ts);
}

function moneyText($value): string
{
    $text = cellText($value);
    if ($text === '') return '';

    $negative = false;
    if (preg_match('/^\(.*\)$/', $text)) {
        $negative = true;
        $text = trim($text, "() \t\n\r\0\x0B");
    }

    $text = preg_replace('/[^0-9,\.\-]/', '', $text);
    if ($text === '' || $text === '-' || $text === '.' || $text === ',') return '';

    if (strpos($text, ',') !== false && strpos($text, '.') !== false) {
        $text = str_replace(',', '', $text);
    } elseif (strpos($text, ',') !== false) {
        $text = str_replace(',', '.', $text);
    }

    if (!is_numeric($text)) return '';
    $number = (float)$text;
    if ($negative) $number *= -1;
    return number_format($number, 2, '.', '');
}

function detectCsvDelimiter(string $path): string
{
    $sample = (string)file_get_contents($path, false, null, 0, 8192);
    $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
    foreach (preg_split('/\r\n|\r|\n/', $sample) ?: [] as $line) {
        if (trim($line) === '') continue;
        foreach (array_keys($delimiters) as $delimiter) {
            $delimiters[$delimiter] += substr_count($line, $delimiter);
        }
    }
    arsort($delimiters);
    $detected = (string)array_key_first($delimiters);
    return $detected !== '' ? $detected : ',';
}

function loadSheet(string $path, string $name): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'csv') {
        $reader = new CsvReader();
        $reader->setDelimiter(detectCsvDelimiter($path));
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        if (method_exists($reader, 'setInputEncoding')) $reader->setInputEncoding('UTF-8');
        return $reader->load($path)->getActiveSheet();
    }
    return IOFactory::load($path)->getActiveSheet();
}

function branchIdFromControlSeries(string $controlSeriesNo): string
{
    if (preg_match('/^PPO([^-]{1,4})/i', trim($controlSeriesNo), $matches)) return trim($matches[1]);
    if (preg_match('/^[A-Z]+([0-9]{1,4})(?:-|$)/i', trim($controlSeriesNo), $matches)) return trim($matches[1]);
    return '';
}

function branchProfile(string $branchId): array
{
    static $cache = [];
    static $stmt = null;
    static $available = true;

    $branchId = trim($branchId);
    if ($branchId === '') return ['area' => '', 'region_code' => ''];
    if (isset($cache[$branchId])) return $cache[$branchId];
    if (!$available) return ['area' => '', 'region_code' => ''];

    try {
        if (!$stmt) {
            $stmt = masterDataConnection()->prepare('SELECT area, region_code FROM branch_profile WHERE TRIM(branch_id) = TRIM(?) LIMIT 1');
        }
        $stmt->execute([$branchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return $cache[$branchId] = [
            'area' => trim((string)($row['area'] ?? '')),
            'region_code' => trim((string)($row['region_code'] ?? '')),
        ];
    } catch (Throwable $e) {
        $available = false;
        return ['area' => '', 'region_code' => ''];
    }
}

function readCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $column, int $row, bool $date = false): string
{
    $value = $sheet->getCell($column . $row)->getCalculatedValue();
    return $date ? dateText($value) : cellText($value);
}

function readMoneyCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $column, int $row): string
{
    return moneyText($sheet->getCell($column . $row)->getCalculatedValue());
}

function parseRowsFromFile(array $file, string $partnerId, string $partnerName, string $uploadedBy, string $uploadedDate): array
{
    $sheet = loadSheet((string)$file['tmp_name'], (string)$file['name']);
    $detected = strtoupper(trim(cellText($sheet->getCell('D4')->getCalculatedValue())));
    if ($detected !== 'DATE CLAIMED' && $detected !== 'DATE SEND') {
        throw new RuntimeException('Unsupported cancellation file: ' . (string)$file['name']);
    }

    $isClaimed = $detected === 'DATE CLAIMED';
    $highestRow = $sheet->getHighestDataRow();
    $rows = [];

    for ($i = 5; $i <= $highestRow; $i++) {
        $controlSeriesNo = readCell($sheet, 'B', $i);
        $dateCancelled = readCell($sheet, 'C', $i, true);
        $dateClaimed = $isClaimed ? readCell($sheet, 'D', $i, true) : '';
        $dateSend = $isClaimed ? '' : readCell($sheet, 'D', $i, true);
        $kptn = readCell($sheet, 'E', $i);
        $ccrefNo = readCell($sheet, 'F', $i);
        $currency = readCell($sheet, 'G', $i);
        $amount = readMoneyCell($sheet, 'H', $i);
        $ctc = $isClaimed ? readMoneyCell($sheet, 'I', $i) : '';
        $ctp = $isClaimed ? readMoneyCell($sheet, 'J', $i) : '';
        $charge = $isClaimed ? '' : readMoneyCell($sheet, 'I', $i);
        $senderName = readCell($sheet, $isClaimed ? 'K' : 'J', $i);
        $senderCountry = $isClaimed ? readCell($sheet, 'L', $i) : '';
        $beneficiaryName = $isClaimed ? readCell($sheet, 'M', $i) : '';
        $receiverName = readCell($sheet, $isClaimed ? 'N' : 'K', $i);
        $receiverPhone = readCell($sheet, $isClaimed ? 'O' : 'L', $i);
        $operator = readCell($sheet, $isClaimed ? 'P' : 'M', $i);
        $branch = readCell($sheet, $isClaimed ? 'Q' : 'N', $i);
        $remoteOperator = readCell($sheet, $isClaimed ? 'R' : 'O', $i);
        $remoteBranch = readCell($sheet, $isClaimed ? 'S' : 'P', $i);
        $otherDetails = readCell($sheet, $isClaimed ? 'T' : 'Q', $i);

        $hasValue = implode('', [
            $controlSeriesNo, $dateCancelled, $dateClaimed, $dateSend, $kptn, $ccrefNo,
            $currency, $amount, $ctc, $ctp, $charge, $senderName, $senderCountry,
            $beneficiaryName, $receiverName, $receiverPhone, $operator, $branch,
            $remoteOperator, $remoteBranch, $otherDetails,
        ]) !== '';
        if (!$hasValue) break;

        $branchId = branchIdFromControlSeries($controlSeriesNo);
        $profile = branchProfile($branchId);

        $rows[] = [
            'partner_id' => $partnerId,
            'partnerName' => $partnerName,
            'control_series_no' => $controlSeriesNo,
            'date_cancelled' => $dateCancelled,
            'date_send' => $dateSend,
            'date_claimed' => $dateClaimed,
            'kptn' => $kptn,
            'ccref_no' => $ccrefNo,
            'currency' => $currency,
            'amount' => $amount,
            'ctc' => $ctc,
            'ctp' => $ctp,
            'charge' => $charge,
            'sender_name' => $senderName,
            'sender_country' => $senderCountry,
            'beneficiary_name' => $beneficiaryName,
            'receiver_name' => $receiverName,
            'receiver_phone' => $receiverPhone,
            'operator' => $operator,
            'mbp_branch_id' => $branchId,
            'branch' => $branch,
            'mbp_area' => (string)($profile['area'] ?? ''),
            'mbp_region_code' => (string)($profile['region_code'] ?? ''),
            'remote_operator' => $remoteOperator,
            'remote_branch' => $remoteBranch,
            'other_details' => $otherDetails,
            'uploaded_date' => $uploadedDate,
            'uploaded_by' => $uploadedBy,
        ];
    }

    return $rows;
}

function bindNullable(PDOStatement $stmt, string $param, string $value): void
{
    $stmt->bindValue($param, $value === '' ? null : $value, $value === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
}

$partnerId = trim((string)($_POST['partner_id'] ?? ''));
$partnerName = trim((string)($_POST['partnerName'] ?? ''));
$uploadedBy = trim((string)($_SESSION['user']['id_number'] ?? ''));

if ($partnerName === '') respondJson(['success' => false, 'error' => 'Missing partner name.'], 400);
if ($uploadedBy === '') respondJson(['success' => false, 'error' => 'Missing logged-in user.'], 401);
if (empty($_FILES['files']) || !is_array($_FILES['files'])) respondJson(['success' => false, 'error' => 'No files uploaded.'], 400);

$uploadedDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

try {
    $allRows = [];
    $fileCount = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 0;
    for ($i = 0; $i < $fileCount; $i++) {
        if ((int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $file = [
            'name' => $_FILES['files']['name'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
        ];
        $allRows = array_merge($allRows, parseRowsFromFile($file, $partnerId, $partnerName, $uploadedBy, $uploadedDate));
    }

    if (empty($allRows)) respondJson(['success' => false, 'error' => 'No cancellation rows found.'], 400);

    $pdo = fileRecDbConnection();
    $sql = 'INSERT INTO ml_web_data_cancellation (
        partner_id, partnerName, control_series_no, date_cancelled, date_send, date_claimed, kptn, ccref_no,
        currency, amount, ctc, ctp, charge, sender_name, sender_country, beneficiary_name, receiver_name,
        receiver_phone, operator, mbp_branch_id, branch, mbp_area, mbp_region_code, remote_operator,
        remote_branch, other_details, uploaded_date, uploaded_by
    ) VALUES (
        :partner_id, :partnerName, :control_series_no, :date_cancelled, :date_send, :date_claimed, :kptn, :ccref_no,
        :currency, :amount, :ctc, :ctp, :charge, :sender_name, :sender_country, :beneficiary_name, :receiver_name,
        :receiver_phone, :operator, :mbp_branch_id, :branch, :mbp_area, :mbp_region_code, :remote_operator,
        :remote_branch, :other_details, :uploaded_date, :uploaded_by
    )';
    $stmt = $pdo->prepare($sql);
    $pdo->beginTransaction();
    foreach ($allRows as $row) {
        foreach ($row as $key => $value) {
            bindNullable($stmt, ':' . $key, (string)$value);
        }
        $stmt->execute();
    }
    $pdo->commit();

    respondJson(['success' => true, 'inserted' => count($allRows)]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    respondJson(['success' => false, 'error' => $e->getMessage()], 500);
}
