<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/csrf.php';

// Load Composer autoloader if available (for PhpSpreadsheet support)
$composerAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

header('Content-Type: application/json; charset=utf-8');
// Test/debug flag: set early so jsonFail can return diagnostics for upload errors
$debug = isset($_POST['debug']) && (string)$_POST['debug'] === '1';

bootSecureSession();
requireAuth();
verifyCsrfOrFail();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Clear recent submissions on non-POST (refresh/visit) so testers can retry
    $_SESSION['excel_compare_recent'] = [];
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

function jsonFail(string $message, int $code = 422): void
{
    http_response_code($code);
    $resp = ['success' => false, 'message' => $message];
    if (!empty($GLOBALS['debug'])) {
        $filesDebug = [];
        foreach ($_FILES as $k => $f) {
            $filesDebug[$k] = [
                'name' => $f['name'] ?? null,
                'size' => $f['size'] ?? null,
                'error' => $f['error'] ?? null,
            ];
        }
        $resp['debug'] = [
            'post' => $_POST,
            'files' => $filesDebug,
        ];
    }
    echo json_encode($resp);
    exit;
}

function normalizeHeader(string $value): string
{
    $value = (string) $value;
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value); // strip UTF-8 BOM
    $value = str_replace("\xC2\xA0", ' ', $value); // non-breaking spaces -> normal
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    if (function_exists('mb_strtoupper')) {
        $value = mb_strtoupper($value);
    } else {
        $value = strtoupper($value);
    }
    return $value;
}

function colLettersToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

function parseCsvRows(string $path): array
{
    $rows = [];
    $fp = fopen($path, 'rb');
    if ($fp === false) {
        return $rows;
    }
    while (($cols = fgetcsv($fp)) !== false) {
        $rows[] = $cols;
    }
    fclose($fp);
    return $rows;
}

function parseXlsxRows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    // Try to locate the first worksheet that contains row data.
    // Some files place data on sheet2/sheet3; don't assume sheet1.xml always holds the rows.
    $sheetXml = false;
    for ($i = 1; $i <= 20; $i++) {
        $candidateName = 'xl/worksheets/sheet' . $i . '.xml';
        $candidate = $zip->getFromName($candidateName);
        if ($candidate === false) {
            continue;
        }
        $candidateDoc = @simplexml_load_string($candidate);
        if ($candidateDoc && isset($candidateDoc->sheetData->row)) {
            $sheetXml = $candidate;
            break;
        }
    }

    if ($sheetXml === false) {
        // nothing found on the common worksheet names
        error_log('[test-controller] parseXlsxRows: no worksheet with rows found in ' . $path);
        $zip->close();
        return [];
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sharedDoc = @simplexml_load_string($sharedXml);
        if ($sharedDoc && isset($sharedDoc->si)) {
            foreach ($sharedDoc->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) ($run->t ?? '');
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    $sheetDoc = @simplexml_load_string($sheetXml);
    if (!$sheetDoc || !isset($sheetDoc->sheetData->row)) {
        $zip->close();
        return [];
    }

    $rows = [];
    foreach ($sheetDoc->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            if (!preg_match('/^([A-Z]+)/', $ref, $m)) {
                continue;
            }

            $index = colLettersToIndex($m[1]);
            $type = (string) ($cell['t'] ?? '');
            $value = '';

            if ($type === 's') {
                $ssid = (int) ($cell->v ?? 0);
                $value = $sharedStrings[$ssid] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
            } else {
                $value = (string) ($cell->v ?? '');
            }

            $row[$index] = $value;
        }

        if (!empty($row)) {
            ksort($row);
            $max = max(array_keys($row));
            $line = array_fill(0, $max + 1, '');
            foreach ($row as $i => $v) {
                $line[$i] = $v;
            }
            $rows[] = $line;
        }
    }

    $zip->close();
    return $rows;
}

function parseRowsFromUpload(array $file): array
{
    $name = strtolower((string) ($file['name'] ?? ''));
    $tmp = (string) ($file['tmp_name'] ?? '');

    if (!is_uploaded_file($tmp)) {
        return [];
    }

    if (str_ends_with($name, '.csv')) {
        return parseCsvRows($tmp);
    }

    // XLSX / XLX -> zipped XML workbook
    if (str_ends_with($name, '.xlsx') || str_ends_with($name, '.xlx')) {
        return parseXlsxRows($tmp);
    }

    // Legacy binary Excel formats (xls/xlsm/xlsb) and other spreadsheet formats (ods)
    // Attempt to parse using PhpSpreadsheet if available. If not installed, return empty
    // so that higher-level logic will report a helpful error to the user.
    if (str_ends_with($name, '.xls') || str_ends_with($name, '.xlsm') || str_ends_with($name, '.xlsb') || str_ends_with($name, '.ods')) {
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            error_log('[test-controller] parseRowsFromUpload: legacy spreadsheet detected but PhpSpreadsheet not installed: ' . $name);
            return [];
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = [];
            $array = $sheet->toArray(null, true, true, true);
            foreach ($array as $r) {
                if (empty($r) || count(array_filter($r, fn($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                ksort($r);
                $rows[] = array_values($r);
            }
            return $rows;
        } catch (Throwable $e) {
            error_log('[test-controller] PhpSpreadsheet parse error: ' . $e->getMessage());
            return [];
        }
    }

    return [];
}

function toNumber(string $value): float
{
    $clean = str_replace([',', ' '], '', trim($value));
    return (float) $clean;
}

function extractByRequiredHeaders(array $rows, array $requiredHeaders): array
{
    if (empty($rows)) {
        return ['ok' => false, 'message' => 'No rows found.', 'records' => []];
    }

    $needles = [];
    foreach ($requiredHeaders as $key => $headerLabel) {
        $needles[$key] = normalizeHeader($headerLabel);
    }

    $headerIndex = null;
    $map = [];

    $rowCount = count($rows);
    for ($r = 0; $r < $rowCount; $r++) {
        $candidate = array_map(static fn($v) => normalizeHeader((string) $v), $rows[$r]);
        $ok = true;
        $tmpMap = [];
        foreach ($needles as $k => $needle) {
            $idx = array_search($needle, $candidate, true);
            if ($idx === false) {
                $ok = false;
                break;
            }
            $tmpMap[$k] = (int) $idx;
        }
        if ($ok) {
            $headerIndex = $r;
            $map = $tmpMap;
            break;
        }
    }

    if ($headerIndex === null) {
        foreach ($needles as $k => $needle) {
            $found = false;
            foreach ($rows as $r) {
                $cand = array_map(static fn($v) => normalizeHeader((string) $v), $r);
                if (in_array($needle, $cand, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return ['ok' => false, 'message' => 'Missing required header: ' . ($requiredHeaders[$k] ?? $k), 'records' => []];
            }
        }

        return ['ok' => false, 'message' => 'Missing required headers', 'records' => []];
    }

    $records = [];
    for ($r = $headerIndex + 1, $len = count($rows); $r < $len; $r++) {
        $line = $rows[$r];
        $record = [];
        foreach ($map as $k => $i) {
            $record[$k] = trim((string) ($line[$i] ?? ''));
        }

        if (implode('', $record) === '') {
            continue;
        }

        $records[] = $record;
    }

    $foundHeaderRow = $rows[$headerIndex];
    $normalizedHeaderRow = array_map(static fn($v) => normalizeHeader((string) $v), $foundHeaderRow);

    return [
        'ok' => true,
        'message' => '',
        'records' => $records,
        'headerRow' => $foundHeaderRow,
        'normalizedHeaderRow' => $normalizedHeaderRow,
        'map' => $map,
        'headerIndex' => $headerIndex,
    ];
}

function checkUploadMeta(string $fieldName): array
{
    if (!isset($_FILES[$fieldName])) {
        jsonFail('Missing file upload.');
    }

    $file = $_FILES[$fieldName];
    $name = strtolower((string) ($file['name'] ?? ''));

    if ($name === '' || (
        !str_ends_with($name, '.xlsx') && !str_ends_with($name, '.csv') && !str_ends_with($name, '.xlx') &&
        !str_ends_with($name, '.xls') && !str_ends_with($name, '.xlsm') && !str_ends_with($name, '.xlsb') && !str_ends_with($name, '.ods')
    )) {
        jsonFail('Invalid file type. Allowed: .xlsx, .xls, .xlx, .xlsm, .xlsb, .ods, .csv');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonFail('Upload failed.');
    }

    return $file;
}

$mode = (string) ($_POST['mode'] ?? '');
if (strcasecmp($mode, 'Test') !== 0) {
    jsonFail('Only Test mode is currently supported.');
}


// Allow fetch-test single-file uploads: only require the files that were provided.
$partnersFile = isset($_FILES['partners_file']) ? checkUploadMeta('partners_file') : null;
$webFile = isset($_FILES['web_file']) ? checkUploadMeta('web_file') : null;

$debug = isset($_POST['debug']) && (string)$_POST['debug'] === '1';


$submissionHash = hash(
    'sha256',
    ($partnersFile['name'] ?? '') . '|' . ($partnersFile['size'] ?? '') . '|' .
    ($webFile['name'] ?? '') . '|' . ($webFile['size'] ?? '') . '|' .
    (string) ($_SESSION['user']['id_number'] ?? '')
);

// Allow test-time overrides:
// - POST 'force' = '1' to bypass duplicate blocking
// - POST 'clear_recent' = '1' to clear stored recent submissions before checking
$force = (isset($_POST['force']) && (string) $_POST['force'] === '1');
$clearRecent = (isset($_POST['clear_recent']) && ((string) $_POST['clear_recent'] === '1' || (string) $_POST['clear_recent'] === 'true'));
if ($clearRecent) {
    $_SESSION['excel_compare_recent'] = [];
    $_SESSION['excel_compare_recent_cleared_at'] = time();
    error_log('[test-controller] clear_recent requested by user=' . ($_SESSION['user']['id_number'] ?? 'unknown'));
}

$_SESSION['excel_compare_recent'] = $_SESSION['excel_compare_recent'] ?? [];

// Short grace period after a clear to allow immediate retry from the client
$graceSeconds = 5;
$clearedAt = (int) ($_SESSION['excel_compare_recent_cleared_at'] ?? 0);
$withinGrace = $clearedAt !== 0 && (time() - $clearedAt) <= $graceSeconds;

// Log current state for debugging when duplicate is detected
if (in_array($submissionHash, $_SESSION['excel_compare_recent'], true)) {
    error_log('[test-controller] submissionHash: ' . $submissionHash);
    error_log('[test-controller] recent: ' . json_encode($_SESSION['excel_compare_recent']));
}

if (!$force && !$withinGrace && in_array($submissionHash, $_SESSION['excel_compare_recent'], true)) {
    jsonFail('Duplicate submission blocked.');
}

$partnersRequired = [
    'date' => 'Date',
    'time' => 'Time',
    'referenceNo' => 'Reference No.',
    'rtsTracerNo' => 'RTS Tracer No.',
    'provider' => 'Provider',
    'beneficiaryName' => 'Beneficiary Name',
    'remitterName' => 'Remitter Name',
    'php' => 'PHP',
    'usd' => 'USD',
    'inPhp' => 'in PHP',
];

$webRequired = [
    'no' => 'NO',
    'controlSeriesNo' => 'CONTROL SERIES NO',
    'dateClaimed' => 'DATE CLAIMED',
    'kptn' => 'KPTN',
    'ccrefNo' => 'CCREF NO',
    'currency' => 'CURRENCY',
    'amount' => 'AMOUNT',
    'ctp' => 'CTP',
    'senderName' => 'SENDER NAME',
    'senderCountry' => 'SENDER COUNTRY',
    'beneficiaryReceiver' => 'BENEFICIARY/RECEIVER',
    'receiverKyc' => 'RECEIVER KYC',
    'receiverPhone' => 'RECEIVER PHONE',
    'operator' => 'OPERATOR',
    'branch' => 'BRANCH',
    'remoteOperator' => 'REMOTE OPERATOR',
    'remoteBranch' => 'REMOTE BRANCH',
];

$partnersRows = $partnersFile ? parseRowsFromUpload($partnersFile) : [];
$webRows = $webFile ? parseRowsFromUpload($webFile) : [];

// Basic debug logging to help trace why headers/rows may be missing
error_log('[test-controller] partnersRows count=' . count($partnersRows) . ' webRows count=' . count($webRows));
if ($debug) {
    error_log('[test-controller][debug] partners sample=' . json_encode(array_slice($partnersRows, 0, 5)));
    error_log('[test-controller][debug] web sample=' . json_encode(array_slice($webRows, 0, 5)));
}

// If parsing produced no rows for an uploaded legacy-format file, give a clearer error.
// This helps fetch-test users know that PhpSpreadsheet is required for .xls/.xlsm/.xlsb/.ods/.xlx files.
$isLegacyExt = static function (?array $file): bool {
    if (empty($file) || empty($file['name'])) return false;
    $n = strtolower((string) $file['name']);
    return str_ends_with($n, '.xls') || str_ends_with($n, '.xlsm') || str_ends_with($n, '.xlsb') || str_ends_with($n, '.ods') || str_ends_with($n, '.xlx');
};

if ($partnersFile && empty($partnersRows) && $isLegacyExt($partnersFile)) {
    $msg = 'Uploaded Partner file appears to be a legacy spreadsheet format (xls/xlsm/xlsb/ods/xlx). Server parsing for these formats requires the phpoffice/phpspreadsheet library.';
    if ($debug) {
        echo json_encode(['success' => false, 'message' => $msg, 'debug' => ['partners_file' => $partnersFile['name'] ?? null]]);
        exit;
    }
    jsonFail($msg);
}

if ($webFile && empty($webRows) && $isLegacyExt($webFile)) {
    $msg = 'Uploaded Web file appears to be a legacy spreadsheet format (xls/xlsm/xlsb/ods/xlx). Server parsing for these formats requires the phpoffice/phpspreadsheet library.';
    if ($debug) {
        echo json_encode(['success' => false, 'message' => $msg, 'debug' => ['web_file' => $webFile['name'] ?? null]]);
        exit;
    }
    jsonFail($msg);
}


// If only one file was uploaded, treat this as a fetch-test and return parsed rows for that file.
if ($partnersFile && !$webFile) {
    $partnersData = extractByRequiredHeaders($partnersRows, $partnersRequired);
    if (!$partnersData['ok']) {
        error_log('[test-controller] partnersData failed: ' . ($partnersData['message'] ?? 'Missing required headers'));
        if ($debug) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format for Partner Data: ' . ($partnersData['message'] ?? 'Missing required headers'), 'debug' => ['partners_rows' => $partnersRows]]);
            exit;
        }
        jsonFail('Invalid file format for Partner Data: ' . ($partnersData['message'] ?? 'Missing required headers'));
    }

    $rowsOut = [];
    foreach ($partnersData['records'] as $pr) {
        $rowsOut[] = [
            'partners' => $pr,
            'web' => [],
            'match' => ['referenceNo' => false, 'php' => false, 'inPhp' => false],
            'all' => false,
        ];
    }

    echo json_encode([
        'success' => true,
        'allMatched' => false,
        'matchedCount' => 0,
        'unmatchedCount' => count($rowsOut),
        'partners_count' => count($partnersData['records'] ?? []),
        'web_count' => 0,
        'rows' => $rowsOut,
        'parsedHeaders' => [
            'partners' => $partnersData['headerRow'] ?? [],
            'partners_normalized' => $partnersData['normalizedHeaderRow'] ?? [],
        ],
        'debug' => $debug ? [
            'partners_rows_count' => count($partnersRows),
            'partners_header_index' => $partnersData['headerIndex'] ?? null,
            'partners_header_normalized' => $partnersData['normalizedHeaderRow'] ?? [],
        ] : null,
    ]);
    exit;
}

if ($webFile && !$partnersFile) {
    $webData = extractByRequiredHeaders($webRows, $webRequired);
    if (!$webData['ok']) {
        error_log('[test-controller] webData failed: ' . ($webData['message'] ?? 'Missing required headers'));
        if ($debug) {
            echo json_encode(['success' => false, 'message' => 'Invalid file format for Web Data: ' . ($webData['message'] ?? 'Missing required headers'), 'debug' => ['web_rows' => $webRows]]);
            exit;
        }
        jsonFail('Invalid file format for Web Data: ' . ($webData['message'] ?? 'Missing required headers'));
    }

    $rowsOut = [];
    foreach ($webData['records'] as $wr) {
        $rowsOut[] = [
            'partners' => [],
            'web' => $wr,
            'match' => ['referenceNo' => false, 'php' => false, 'inPhp' => false],
            'all' => false,
        ];
    }

    echo json_encode([
        'success' => true,
        'allMatched' => false,
        'matchedCount' => 0,
        'unmatchedCount' => count($rowsOut),
        'partners_count' => 0,
        'web_count' => count($webData['records'] ?? []),
        'rows' => $rowsOut,
        'parsedHeaders' => [
            'web' => $webData['headerRow'] ?? [],
            'web_normalized' => $webData['normalizedHeaderRow'] ?? [],
        ],
        'debug' => $debug ? [
            'web_rows_count' => count($webRows),
            'web_header_index' => $webData['headerIndex'] ?? null,
            'web_header_normalized' => $webData['normalizedHeaderRow'] ?? [],
        ] : null,
    ]);
    exit;
}


// Full comparison path: both files present

if (!$partnersFile || !$webFile) {
    jsonFail('Missing file upload.');
}

$partnersData = extractByRequiredHeaders($partnersRows, $partnersRequired);
if (!$partnersData['ok']) {
    error_log('[test-controller] partnersData failed: ' . ($partnersData['message'] ?? 'Missing required headers'));
    if ($debug) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format for Partner Data: ' . ($partnersData['message'] ?? 'Missing required headers'), 'debug' => ['partners_rows' => $partnersRows]]);
        exit;
    }
    jsonFail('Invalid file format for Partner Data: ' . ($partnersData['message'] ?? 'Missing required headers'));
}

$webData = extractByRequiredHeaders($webRows, $webRequired);
if (!$webData['ok']) {
    error_log('[test-controller] webData failed: ' . ($webData['message'] ?? 'Missing required headers'));
    if ($debug) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format for Web Data: ' . ($webData['message'] ?? 'Missing required headers'), 'debug' => ['web_rows' => $webRows]]);
        exit;
    }
    jsonFail('Invalid file format for Web Data: ' . ($webData['message'] ?? 'Missing required headers'));
}

$partnersRecords = $partnersData['records'];

// Exclude any partner rows that have no Reference No. — they are not part of the extraction
$partnersRecords = array_values(array_filter($partnersRecords, static function ($r) {
    $ref = trim((string) ($r['referenceNo'] ?? ''));
    return $ref !== '';
}));
$webByCcref = [];
foreach ($webData['records'] as $record) {
    $key = trim((string) ($record['ccrefNo'] ?? ''));
    if ($key !== '') {
        $webByCcref[(string) mb_strtoupper($key)] = $record;
    }
}

$rows = [];
$matchedCount = 0;
$unmatchedCount = 0;

// Sort partners by Reference No. (case-insensitive, 0 -> Z)
usort($partnersRecords, static function ($a, $b) {
    $ra = trim((string) ($a['referenceNo'] ?? ''));
    $rb = trim((string) ($b['referenceNo'] ?? ''));
    return strcasecmp($ra, $rb);
});

foreach ($partnersRecords as $partnerRow) {
    $ref = trim((string) ($partnerRow['referenceNo'] ?? ''));
    $webRow = $webByCcref[(string) mb_strtoupper($ref)] ?? null;

    $refMatch = $webRow !== null && $ref !== '' && strcasecmp($ref, (string) $webRow['ccrefNo']) === 0;

    $phpVal = toNumber((string) ($partnerRow['php'] ?? '0'));
    $inPhpVal = toNumber((string) ($partnerRow['inPhp'] ?? '0'));
    $amountVal = $webRow ? toNumber((string) ($webRow['amount'] ?? '0')) : 0.0;
    $ctpVal = $webRow ? toNumber((string) ($webRow['ctp'] ?? '0')) : 0.0;

    // partners.php should match web.amount
    $phpAmountMatch = $webRow !== null && abs($phpVal - $amountVal) < 0.00001;
    // partners.inPhp should match web.ctp
    $inPhpCtpMatch = $webRow !== null && abs($inPhpVal - $ctpVal) < 0.00001;

    $all = $refMatch && $phpAmountMatch && $inPhpCtpMatch;

    if ($all) {
        $matchedCount++;
    } else {
        $unmatchedCount++;
    }

    $rows[] = [
        'partners' => [
            'referenceNo' => $ref,
            'php' => (string) ($partnerRow['php'] ?? ''),
            'usd' => (string) ($partnerRow['usd'] ?? ''),
            'inPhp' => (string) ($partnerRow['inPhp'] ?? ''),
        ],
        'web' => [
            'ccrefNo' => (string) ($webRow['ccrefNo'] ?? ''),
            'amount' => (string) ($webRow['amount'] ?? ''),
            'ctp' => (string) ($webRow['ctp'] ?? ''),
        ],
        'match' => [
            'referenceNo' => $refMatch,
            'php' => $phpAmountMatch,
            'inPhp' => $inPhpCtpMatch,
        ],
        'all' => $all,
    ];
}

if (empty($_SESSION['excel_compare_recent'])) {
    $_SESSION['excel_compare_recent'] = [];
}
if (!$clearRecent) {
    $_SESSION['excel_compare_recent'][] = $submissionHash;
}
if (count($_SESSION['excel_compare_recent']) > 20) {
    $_SESSION['excel_compare_recent'] = array_slice($_SESSION['excel_compare_recent'], -20);
}

$response = [
    'success' => true,
    'allMatched' => $unmatchedCount === 0,
    'matchedCount' => $matchedCount,
    'unmatchedCount' => $unmatchedCount,
    'partners_count' => count($partnersData['records'] ?? []),
    'web_count' => count($webData['records'] ?? []),
    'rows' => $rows,
    'parsedHeaders' => [
        'partners' => $partnersData['headerRow'] ?? [],
        'web' => $webData['headerRow'] ?? [],
        'partners_normalized' => $partnersData['normalizedHeaderRow'] ?? [],
        'web_normalized' => $webData['normalizedHeaderRow'] ?? [],
    ],
    'debug' => $debug ? [
        'partners_rows_count' => count($partnersRows),
        'web_rows_count' => count($webRows),
        'partners_header_index' => $partnersData['headerIndex'] ?? null,
        'web_header_index' => $webData['headerIndex'] ?? null,
        'partners_header_normalized' => $partnersData['normalizedHeaderRow'] ?? [],
        'web_header_normalized' => $webData['normalizedHeaderRow'] ?? [],
    ] : null,
];

// Persist the response payload in session so the UI can be restored after a page refresh.
// Store a compact payload keyed by submission hash.
if (empty($_SESSION['excel_compare_recent_payloads'])) {
    $_SESSION['excel_compare_recent_payloads'] = [];
}
// Save only the essential parts to avoid storing temporary debug arrays.
$_SESSION['excel_compare_recent_payloads'][$submissionHash] = [
    'id' => $submissionHash,
    'mode' => $mode,
    'date' => $_POST['batch_date'] ?? null,
    'payload' => [
        'success' => $response['success'],
        'allMatched' => $response['allMatched'],
        'matchedCount' => $response['matchedCount'],
        'unmatchedCount' => $response['unmatchedCount'],
        'partners_count' => $response['partners_count'],
        'web_count' => $response['web_count'],
        'rows' => $response['rows'],
        'parsedHeaders' => $response['parsedHeaders'],
    ],
];

// Trim to last 20
if (count($_SESSION['excel_compare_recent_payloads']) > 20) {
    // keep the most recent 20 by preserving insertion order
    $_SESSION['excel_compare_recent_payloads'] = array_slice($_SESSION['excel_compare_recent_payloads'], -20, 20, true);
}

echo json_encode($response);
exit;
