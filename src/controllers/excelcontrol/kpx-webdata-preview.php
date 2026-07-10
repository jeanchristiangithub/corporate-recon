<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

bootSecureSession();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sentToken = (string)($_POST['csrf_token'] ?? '');
$storedToken = (string)($_SESSION['csrf_token'] ?? '');
if ($storedToken !== '' && !hash_equals($storedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload failed.']);
    exit;
}

$originalName = (string)($file['name'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['xls', 'xlsx', 'csv'];
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: .xls, .xlsx, .csv']);
    exit;
}

$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid uploaded file.']);
    exit;
}

function kpxNormalizeHeaderCell(mixed $value): string
{
    $text = strtoupper(trim((string)$value));
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $text = preg_replace('/[^\S\r\n]+/', ' ', $text) ?? $text;
    return trim($text);
}

function kpxMoneygramHeadersForCombination(string $columnC, string $columnD): array
{
    if ($columnC === 'DATE CANCELLED' && $columnD === 'DATE CLAIMED') {
        return [
            'key' => 'cancelledClaimed',
            'headers' => [
                'CONTROL SERIES NO',
                'DATE CANCELLED',
                'DATE CLAIMED',
                'KPTN',
                'CCREF NO',
                'CURRENCY',
                'AMOUNT',
                'CTC',
                'CTP',
                'SENDER NAME',
                'SENDER COUNTRY',
                'BENEFICIARY NAME',
                'RECEIVER NAME',
                'RECEIVER PHONE',
                'OPERATOR',
                'BRANCH',
                'REMOTE OPERATOR',
                'REMOTE BRANCH',
                'OTHER DETAILS',
            ],
            'sourceMap' => [
                'CONTROL SERIES NO' => 'B',
                'DATE CANCELLED' => 'C',
                'DATE CLAIMED' => 'D',
                'KPTN' => 'E',
                'CCREF NO' => 'F',
                'CURRENCY' => 'G',
                'AMOUNT' => 'H',
                'CTC' => 'I',
                'CTP' => 'J',
                'SENDER NAME' => 'K',
                'SENDER COUNTRY' => 'L',
                'BENEFICIARY NAME' => 'M',
                'RECEIVER NAME' => 'N',
                'RECEIVER PHONE' => 'O',
                'OPERATOR' => 'P',
                'BRANCH' => 'Q',
                'REMOTE OPERATOR' => 'R',
                'REMOTE BRANCH' => 'S',
                'OTHER DETAILS' => 'T',
            ],
            'numericHeaders' => ['AMOUNT', 'CTC', 'CTP'],
        ];
    }

    if ($columnC === 'DATE CANCELLED' && $columnD === 'DATE SEND') {
        return [
            'key' => 'cancelledSend',
            'headers' => [
                'CONTROL SERIES NO',
                'DATE CANCELLED',
                'DATE SEND',
                'KPTN',
                'CCREF NO',
                'CURRENCY',
                'AMOUNT',
                'CHARGE',
                'SENDER NAME',
                'RECEIVER NAME',
                'RECEIVER PHONE',
                'OPERATOR',
                'BRANCH',
                'REMOTE OPERATOR',
                'REMOTE BRANCH',
                'OTHER DETAILS',
            ],
            'sourceMap' => [
                'CONTROL SERIES NO' => 'B',
                'DATE CANCELLED' => 'C',
                'DATE SEND' => 'D',
                'KPTN' => 'E',
                'CCREF NO' => 'F',
                'CURRENCY' => 'G',
                'AMOUNT' => 'H',
                'CHARGE' => 'I',
                'SENDER NAME' => 'J',
                'RECEIVER NAME' => 'K',
                'RECEIVER PHONE' => 'L',
                'OPERATOR' => 'M',
                'BRANCH' => 'N',
                'REMOTE OPERATOR' => 'O',
                'REMOTE BRANCH' => 'P',
                'OTHER DETAILS' => 'Q',
            ],
            'numericHeaders' => ['AMOUNT', 'CHARGE'],
        ];
    }

    if ($columnC === 'DATE CLAIMED') {
        return [
            'key' => 'claimed',
            'headers' => [
                'CONTROL SERIES NO',
                'DATE CLAIMED',
                'KPTN',
                'CCREF NO',
                'CURRENCY',
                'AMOUNT',
                'CTC',
                'CTP',
                'SENDER NAME',
                'SENDER COUNTRY',
                'BENEFICIARY/RECEIVER',
                'RECEIVER KYC',
                'RECEIVER PHONE',
                'OPERATOR',
                'BRANCH',
                'REMOTE OPERATOR',
                'REMOTE BRANCH',
            ],
            'sourceMap' => [
                'CONTROL SERIES NO' => 'B',
                'DATE CLAIMED' => 'C',
                'KPTN' => 'D',
                'CCREF NO' => 'E',
                'CURRENCY' => 'F',
                'AMOUNT' => 'G',
                'CTC' => 'H',
                'CTP' => 'I',
                'SENDER NAME' => 'J',
                'SENDER COUNTRY' => 'K',
                'BENEFICIARY/RECEIVER' => 'L',
                'RECEIVER KYC' => 'M',
                'RECEIVER PHONE' => 'N',
                'OPERATOR' => 'O',
                'BRANCH' => 'P',
                'REMOTE OPERATOR' => 'Q',
                'REMOTE BRANCH' => 'R',
            ],
            'numericHeaders' => ['AMOUNT', 'CTC', 'CTP'],
        ];
    }

    if ($columnC === 'DATE SEND') {
        return [
            'key' => 'send',
            'headers' => [
                'CONTROL SERIES NO',
                'DATE SEND',
                'KPTN',
                'CCREF NO',
                'CURRENCY',
                'AMOUNT',
                'CHARGE',
                'SENDER NAME',
                'RECEIVER COUNTRY',
                'RECEIVER NAME',
                'RECEIVER PHONE',
                'OPERATOR',
                'BRANCH',
                'REMOTE OPERATOR',
                'REMOTE BRANCH',
            ],
            'sourceMap' => [
                'CONTROL SERIES NO' => 'B',
                'DATE SEND' => 'C',
                'KPTN' => 'D',
                'CCREF NO' => 'E',
                'CURRENCY' => 'F',
                'AMOUNT' => 'G',
                'CHARGE' => 'H',
                'SENDER NAME' => 'I',
                'RECEIVER COUNTRY' => 'J',
                'RECEIVER NAME' => 'K',
                'RECEIVER PHONE' => 'L',
                'OPERATOR' => 'M',
                'BRANCH' => 'N',
                'REMOTE OPERATOR' => 'O',
                'REMOTE BRANCH' => 'P',
            ],
            'numericHeaders' => ['AMOUNT', 'CHARGE'],
        ];
    }

    return ['key' => '', 'headers' => [], 'sourceMap' => [], 'numericHeaders' => []];
}

function kpxCellValue(mixed $value): string
{
    if ($value === null) return '';
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');
    if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
    return trim((string)$value);
}

function kpxFormatNumericCell(mixed $value): string
{
    $text = kpxCellValue($value);
    if ($text === '') return '';
    $normalized = str_replace(',', '', $text);
    if (!is_numeric($normalized)) return $text;
    return number_format((float)$normalized, 2, '.', '');
}

function kpxBranchIdFromControlSeries(string $controlSeriesNo): string
{
    $controlSeriesNo = trim($controlSeriesNo);
    if ($controlSeriesNo === '') return '';
    $afterPrefix = strlen($controlSeriesNo) > 3 ? substr($controlSeriesNo, 3) : $controlSeriesNo;
    $parts = explode('-', $afterPrefix, 2);
    return trim((string)($parts[0] ?? ''));
}

function kpxPartnerIdForName(string $partnerName): string
{
    if ($partnerName === '') return '';
    try {
        $stmt = masterDataConnection()->prepare('SELECT partner_id FROM corpo_partner_masterfile WHERE TRIM(LOWER(partner_name)) = TRIM(LOWER(?)) LIMIT 1');
        $stmt->execute([$partnerName]);
        return trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        return '';
    }
}

function kpxBranchProfileByName(string $branchName): array
{
    $branchName = trim($branchName);
    if ($branchName === '') return [];
    try {
        $stmt = masterDataConnection()->prepare('SELECT * FROM branch_profile WHERE TRIM(LOWER(branch_name)) = TRIM(LOWER(?)) LIMIT 1');
        $stmt->execute([$branchName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function kpxBranchProfileById(string $branchId): array
{
    $branchId = trim($branchId);
    if ($branchId === '') return [];
    try {
        $stmt = masterDataConnection()->prepare('SELECT * FROM branch_profile WHERE TRIM(branch_id) = TRIM(?) LIMIT 1');
        $stmt->execute([$branchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function kpxProfileValue(array $profile, string $key): string
{
    return trim((string)($profile[$key] ?? ''));
}

function kpxReadMappedRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $sourceMap, array $numericHeaders): array
{
    if (empty($sourceMap)) return [];

    $rows = [];
    $highestRow = $sheet->getHighestDataRow();
    $numericLookup = array_fill_keys($numericHeaders, true);

    for ($rowIndex = 5; $rowIndex <= $highestRow; $rowIndex++) {
        $record = [];
        $hasValue = false;

        foreach ($sourceMap as $header => $columnLetter) {
            $rawValue = $sheet->getCell($columnLetter . $rowIndex)->getCalculatedValue();
            $value = isset($numericLookup[$header]) ? kpxFormatNumericCell($rawValue) : kpxCellValue($rawValue);
            if ($value !== '') $hasValue = true;
            $record[$header] = $value;
        }

        if (!$hasValue) break;
        $rows[] = $record;
    }

    return $rows;
}

function kpxBuildDeveloperRows(array $normalRows, string $detectedKey, string $partnerName, string $partnerId): array
{
    $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    $uploadedBy = trim((string)($_SESSION['user']['id_number'] ?? ''));
    $rows = [];

    foreach ($normalRows as $row) {
        $controlSeriesNo = trim((string)($row['CONTROL SERIES NO'] ?? ''));
        $branch = trim((string)($row['BRANCH'] ?? ''));
        $remoteOperator = trim((string)($row['REMOTE OPERATOR'] ?? ''));
        $remoteBranch = trim((string)($row['REMOTE BRANCH'] ?? ''));
        $hasRemote = $remoteOperator !== '' && $remoteBranch !== '';
        $fallbackBranchId = kpxBranchIdFromControlSeries($controlSeriesNo);
        $profile = $hasRemote ? kpxBranchProfileByName($branch) : kpxBranchProfileById($fallbackBranchId);
        $branchId = $hasRemote ? kpxProfileValue($profile, 'branch_id') : $fallbackBranchId;

        $record = [
            'partner_id' => $partnerId !== '' ? $partnerId : null,
            'partnerName' => $partnerName !== '' ? $partnerName : null,
            'control_series_no' => $controlSeriesNo,
            'date_cancelled' => null,
            'date_claimed' => null,
            'date_send' => null,
            'kptn' => trim((string)($row['KPTN'] ?? '')),
            'ccref_no' => trim((string)($row['CCREF NO'] ?? '')),
            'currency' => trim((string)($row['CURRENCY'] ?? '')),
            'amount' => trim((string)($row['AMOUNT'] ?? '')),
            'ctc' => null,
            'ctp' => null,
            'charge' => null,
            'sender_name' => trim((string)($row['SENDER NAME'] ?? '')),
            'sender_country' => null,
            'receiver_country' => null,
            'beneficiary_receiver' => null,
            'receiver_kyc' => null,
            'receiver_name' => trim((string)($row['RECEIVER NAME'] ?? '')),
            'receiver_phone' => trim((string)($row['RECEIVER PHONE'] ?? '')),
            'operator' => trim((string)($row['OPERATOR'] ?? '')),
            'branch_id' => $branchId !== '' ? $branchId : null,
            'branch' => $branch !== '' ? $branch : null,
            'mainzone' => kpxProfileValue($profile, 'mainzone') ?: null,
            'zone' => kpxProfileValue($profile, 'zone') ?: null,
            'area' => kpxProfileValue($profile, 'area') ?: null,
            'region' => (kpxProfileValue($profile, 'gl_region') ?: kpxProfileValue($profile, 'region')) ?: null,
            'region_code' => kpxProfileValue($profile, 'region_code') ?: null,
            'remote_operator' => $remoteOperator !== '' ? $remoteOperator : null,
            'remote_branch_id' => $hasRemote ? ($fallbackBranchId !== '' ? $fallbackBranchId : null) : null,
            'remote_branch' => $remoteBranch !== '' ? $remoteBranch : null,
            'other_details' => trim((string)($row['OTHER DETAILS'] ?? '')) ?: null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'uploaded_by' => $uploadedBy !== '' ? $uploadedBy : null,
            'data_status' => '',
            'is_data_locked' => '0',
        ];

        if ($detectedKey === 'cancelledClaimed') {
            $record['date_cancelled'] = trim((string)($row['DATE CANCELLED'] ?? '')) ?: null;
            $record['date_claimed'] = trim((string)($row['DATE CLAIMED'] ?? '')) ?: null;
            $record['ctc'] = trim((string)($row['CTC'] ?? '')) ?: null;
            $record['ctp'] = trim((string)($row['CTP'] ?? '')) ?: null;
            $record['sender_country'] = trim((string)($row['SENDER COUNTRY'] ?? '')) ?: null;
            $record['beneficiary_receiver'] = trim((string)($row['BENEFICIARY NAME'] ?? '')) ?: null;
            $record['data_status'] = 'POC';
        } elseif ($detectedKey === 'cancelledSend') {
            $record['date_cancelled'] = trim((string)($row['DATE CANCELLED'] ?? '')) ?: null;
            $record['date_send'] = trim((string)($row['DATE SEND'] ?? '')) ?: null;
            $record['date_claimed'] = $record['date_send'];
            $record['charge'] = trim((string)($row['CHARGE'] ?? '')) ?: null;
            $record['data_status'] = 'SOC';
        } elseif ($detectedKey === 'claimed') {
            $record['date_claimed'] = trim((string)($row['DATE CLAIMED'] ?? '')) ?: null;
            $record['ctc'] = trim((string)($row['CTC'] ?? '')) ?: null;
            $record['ctp'] = trim((string)($row['CTP'] ?? '')) ?: null;
            $record['sender_country'] = trim((string)($row['SENDER COUNTRY'] ?? '')) ?: null;
            $record['beneficiary_receiver'] = trim((string)($row['BENEFICIARY/RECEIVER'] ?? '')) ?: null;
            $record['receiver_kyc'] = trim((string)($row['RECEIVER KYC'] ?? '')) ?: null;
            $record['data_status'] = 'PO';
        } elseif ($detectedKey === 'send') {
            $record['date_send'] = trim((string)($row['DATE SEND'] ?? '')) ?: null;
            $record['date_claimed'] = $record['date_send'];
            $record['charge'] = trim((string)($row['CHARGE'] ?? '')) ?: null;
            $record['receiver_country'] = trim((string)($row['RECEIVER COUNTRY'] ?? '')) ?: null;
            $record['data_status'] = 'SO';
        }

        $rows[] = $record;
    }

    return $rows;
}

function kpxDetectCsvDelimiter(string $path): string
{
    $sample = (string)file_get_contents($path, false, null, 0, 4096);
    $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];

    foreach (preg_split('/\r\n|\r|\n/', $sample) ?: [] as $line) {
        if (trim($line) === '') {
            continue;
        }
        foreach (array_keys($delimiters) as $delimiter) {
            $delimiters[$delimiter] += substr_count($line, $delimiter);
        }
    }

    arsort($delimiters);
    $delimiter = (string)array_key_first($delimiters);
    return $delimiter !== '' ? $delimiter : ',';
}

function kpxReadCsvHeaderCells(string $path): array
{
    $reader = new CsvReader();
    $reader->setDelimiter(kpxDetectCsvDelimiter($path));
    $reader->setEnclosure('"');
    $reader->setSheetIndex(0);
    if (method_exists($reader, 'setInputEncoding')) {
        $reader->setInputEncoding('UTF-8');
    }

    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $cells = kpxReadHeaderCellsFromSheet($sheet);
    $detected = kpxMoneygramHeadersForCombination($cells['C'], $cells['D']);
    $cells['rows'] = kpxReadMappedRows($sheet, $detected['sourceMap'], $detected['numericHeaders']);
    $spreadsheet->disconnectWorksheets();
    return $cells;
}

function kpxReadHeaderCellsFromSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
{
    $fixedCells = [
        'C' => kpxNormalizeHeaderCell($sheet->getCell('C4')->getFormattedValue()),
        'D' => kpxNormalizeHeaderCell($sheet->getCell('D4')->getFormattedValue()),
    ];
    if (kpxMoneygramHeadersForCombination($fixedCells['C'], $fixedCells['D'])['key'] !== '') {
        return $fixedCells;
    }

    for ($row = 1; $row <= 12; $row++) {
        $cells = [
            'C' => kpxNormalizeHeaderCell($sheet->getCell('C' . $row)->getFormattedValue()),
            'D' => kpxNormalizeHeaderCell($sheet->getCell('D' . $row)->getFormattedValue()),
        ];
        if (kpxMoneygramHeadersForCombination($cells['C'], $cells['D'])['key'] !== '') {
            return $cells;
        }
    }

    return $fixedCells;
}

function kpxReadSpreadsheetHeaderCells(string $path, string $extension): array
{
    $readerType = $extension === 'xls' ? 'Xls' : 'Xlsx';
    $reader = IOFactory::createReader($readerType);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getSheet(0);
    $cells = kpxReadHeaderCellsFromSheet($sheet);
    $detected = kpxMoneygramHeadersForCombination($cells['C'], $cells['D']);
    $cells['rows'] = kpxReadMappedRows($sheet, $detected['sourceMap'], $detected['numericHeaders']);
    $spreadsheet->disconnectWorksheets();
    return $cells;
}

try {
    $partnerName = trim((string)($_POST['partnerName'] ?? ''));
    $postedPartnerId = trim((string)($_POST['partner_id'] ?? ''));
    $partnerId = $postedPartnerId !== '' ? $postedPartnerId : kpxPartnerIdForName($partnerName);
    $row4 = $extension === 'csv'
        ? kpxReadCsvHeaderCells($tmpName)
        : kpxReadSpreadsheetHeaderCells($tmpName, $extension);

    $columnC = $row4['C'];
    $columnD = $row4['D'];
    $detected = kpxMoneygramHeadersForCombination($columnC, $columnD);
    $rows = $row4['rows'] ?? [];
    $developerRows = kpxBuildDeveloperRows($rows, (string)$detected['key'], $partnerName, $partnerId);
    $developerHeaders = !empty($developerRows) ? array_keys($developerRows[0]) : [
        'partner_id', 'partnerName', 'control_series_no', 'date_cancelled', 'date_claimed', 'date_send',
        'kptn', 'ccref_no', 'currency', 'amount', 'ctc', 'ctp', 'charge', 'sender_name', 'sender_country',
        'receiver_country', 'beneficiary_receiver', 'receiver_kyc', 'receiver_name', 'receiver_phone',
        'operator', 'branch_id', 'branch', 'mainzone', 'zone', 'area', 'region', 'region_code',
        'remote_operator', 'remote_branch_id', 'remote_branch', 'other_details', 'created_at', 'updated_at',
        'uploaded_by', 'data_status', 'is_data_locked',
    ];

    echo json_encode([
        'success' => true,
        'filename' => $originalName,
        'detectedKey' => $detected['key'],
        'headers' => $detected['headers'],
        'rows' => $rows,
        'developerHeaders' => $developerHeaders,
        'developerRows' => $developerRows,
        'row4' => [
            'C' => $columnC,
            'D' => $columnD,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unable to read this file.']);
}
