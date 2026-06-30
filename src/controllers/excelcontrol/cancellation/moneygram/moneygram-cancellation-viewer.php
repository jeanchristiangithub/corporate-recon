<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/db.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

header('Content-Type: text/html; charset=UTF-8');

$dateClaimedSystemHeaders = [
    'NO.',
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
    'BRANCH ID',
    'AREA',
    'REGION CODE',
    'REMOTE OPERATOR',
    'REMOTE BRANCH',
    'OTHER DETAILS',
];

$dateSendSystemHeaders = [
    'NO.',
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
    'BRANCH ID',
    'AREA',
    'REGION CODE',
    'REMOTE OPERATOR',
    'REMOTE BRANCH',
    'OTHER DETAILS',
];

function mgCancellationHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function mgCancellationCellValue($value): string
{
    if ($value === null) return '';
    if ($value instanceof DateTimeInterface) return $value->format('Y-m-d H:i:s');
    if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
    if (is_float($value) || is_int($value)) return (string)$value;
    return trim((string)$value);
}

function mgCancellationFormatMaybeDate($value): string
{
    $text = mgCancellationCellValue($value);
    if ($text === '') return '';
    if (is_numeric($text)) {
        try {
            return ExcelDate::excelToDateTimeObject((float)$text)->format('Y-m-d');
        } catch (Throwable $e) {
            return $text;
        }
    }
    return $text;
}

function mgCancellationRenderMessage(string $message, string $type = 'notice'): void
{
    $class = $type === 'error' ? 'mg-cancellation-viewer__message--error' : '';
    echo '<div class="mg-cancellation-viewer">';
    echo '<div class="mg-cancellation-viewer__message ' . $class . '">' . mgCancellationHtml($message) . '</div>';
    echo '</div>';
}

function mgCancellationDetectCsvDelimiter(string $path): string
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

function mgCancellationLoadSpreadsheet(string $path, string $originalName): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        $reader = new CsvReader();
        $reader->setDelimiter(mgCancellationDetectCsvDelimiter($path));
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        if (method_exists($reader, 'setInputEncoding')) {
            $reader->setInputEncoding('UTF-8');
        }
        return $reader->load($path);
    }

    return IOFactory::load($path);
}

function mgCancellationBranchIdFromControlSeries(string $controlSeriesNo): string
{
    $controlSeriesNo = trim($controlSeriesNo);
    if ($controlSeriesNo === '') return '';

    if (preg_match('/^PPO([^-]{1,4})/i', $controlSeriesNo, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/^[A-Z]+([0-9]{1,4})(?:-|$)/i', $controlSeriesNo, $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function mgCancellationBranchProfile(string $branchId): array
{
    static $cache = [];
    static $stmt = null;
    static $dbAvailable = true;

    $branchId = trim($branchId);
    if ($branchId === '') return ['area' => '', 'region_code' => ''];
    if (array_key_exists($branchId, $cache)) return $cache[$branchId];
    if (!$dbAvailable) {
        $cache[$branchId] = ['area' => '', 'region_code' => ''];
        return $cache[$branchId];
    }

    try {
        if (!$stmt) {
            $pdo = masterDataConnection();
            $stmt = $pdo->prepare('SELECT area, region_code FROM branch_profile WHERE TRIM(branch_id) = TRIM(?) LIMIT 1');
        }
        $stmt->execute([$branchId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        $cache[$branchId] = [
            'area' => trim((string)($profile['area'] ?? '')),
            'region_code' => trim((string)($profile['region_code'] ?? '')),
        ];
        return $cache[$branchId];
    } catch (Throwable $e) {
        $dbAvailable = false;
        $cache[$branchId] = ['area' => '', 'region_code' => ''];
        return $cache[$branchId];
    }
}

function mgCancellationReadMappedRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $rowIndex, array $sourceMap): array
{
    $record = [];
    $hasValue = false;

    foreach ($sourceMap as $header => $columnLetter) {
        $rawValue = $sheet->getCell($columnLetter . $rowIndex)->getCalculatedValue();
        $value = ($header === 'DATE CANCELLED' || $header === 'DATE CLAIMED' || $header === 'DATE SEND')
            ? mgCancellationFormatMaybeDate($rawValue)
            : mgCancellationCellValue($rawValue);

        if ($value !== '') $hasValue = true;
        $record[$header] = $value;
    }

    return ['record' => $record, 'has_value' => $hasValue];
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    mgCancellationRenderMessage('No file provided.', 'error');
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    mgCancellationRenderMessage('Upload failed. Error code: ' . (string)($file['error'] ?? 'unknown'), 'error');
    exit;
}

$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    mgCancellationRenderMessage('Uploaded file is not available.', 'error');
    exit;
}

try {
    $spreadsheet = mgCancellationLoadSpreadsheet($tmpName, (string)($file['name'] ?? ''));
    $sheet = $spreadsheet->getActiveSheet();
    $detectedHeader = strtoupper(trim(mgCancellationCellValue($sheet->getCell('D4')->getCalculatedValue())));

    if ($detectedHeader === 'DATE CLAIMED') {
        $systemHeaders = $dateClaimedSystemHeaders;
        $sourceMap = [
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
        ];
    } elseif ($detectedHeader === 'DATE SEND') {
        $systemHeaders = $dateSendSystemHeaders;
        $sourceMap = [
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
        ];
    } else {
        $found = $detectedHeader !== '' ? $detectedHeader : 'blank';
        mgCancellationRenderMessage("Unsupported cancellation file. Expected DATE CLAIMED or DATE SEND at cell D4, found {$found}.", 'error');
        exit;
    }

    $dataStartRow = 5;
    $highestRow = $sheet->getHighestDataRow();
    $rows = [];

    for ($rowIndex = $dataStartRow; $rowIndex <= $highestRow; $rowIndex++) {
        $mappedRow = mgCancellationReadMappedRow($sheet, $rowIndex, $sourceMap);
        $row = $mappedRow['record'];
        $hasValue = (bool)$mappedRow['has_value'];

        if (!$hasValue) break;

        $controlSeriesNo = (string)($row['CONTROL SERIES NO'] ?? '');
        $branchId = mgCancellationBranchIdFromControlSeries($controlSeriesNo);
        $branchProfile = mgCancellationBranchProfile($branchId);

        // Database-ready variables for the later insert flow.
        $record = [
            'no' => (string)(count($rows) + 1),
            'control_series_no' => $controlSeriesNo,
            'date_cancelled' => (string)($row['DATE CANCELLED'] ?? ''),
            'date_claimed' => (string)($row['DATE CLAIMED'] ?? ''),
            'date_send' => (string)($row['DATE SEND'] ?? ''),
            'kptn' => (string)($row['KPTN'] ?? ''),
            'ccref_no' => (string)($row['CCREF NO'] ?? ''),
            'currency' => (string)($row['CURRENCY'] ?? ''),
            'amount' => (string)($row['AMOUNT'] ?? ''),
            'ctc' => (string)($row['CTC'] ?? ''),
            'ctp' => (string)($row['CTP'] ?? ''),
            'charge' => (string)($row['CHARGE'] ?? ''),
            'sender_name' => (string)($row['SENDER NAME'] ?? ''),
            'sender_country' => (string)($row['SENDER COUNTRY'] ?? ''),
            'beneficiary_name' => (string)($row['BENEFICIARY NAME'] ?? ''),
            'receiver_name' => (string)($row['RECEIVER NAME'] ?? ''),
            'receiver_phone' => (string)($row['RECEIVER PHONE'] ?? ''),
            'operator' => (string)($row['OPERATOR'] ?? ''),
            'branch' => (string)($row['BRANCH'] ?? ''),
            'branch_id' => $branchId,
            'area' => (string)($branchProfile['area'] ?? ''),
            'region_code' => (string)($branchProfile['region_code'] ?? ''),
            'remote_operator' => (string)($row['REMOTE OPERATOR'] ?? ''),
            'remote_branch' => (string)($row['REMOTE BRANCH'] ?? ''),
            'other_details' => (string)($row['OTHER DETAILS'] ?? ''),
        ];

        $row['NO.'] = $record['no'];
        $row['BRANCH ID'] = $record['branch_id'];
        $row['AREA'] = $record['area'];
        $row['REGION CODE'] = $record['region_code'];
        $rows[] = $row;
    }
} catch (Throwable $e) {
    mgCancellationRenderMessage('Failed to read file: ' . $e->getMessage(), 'error');
    exit;
}
?>
<div class="mg-cancellation-viewer">
    <div class="mg-cancellation-viewer__container">
        <div class="mg-cancellation-viewer__table-wrap">
            <table class="mg-cancellation-viewer__table">
                <thead>
                    <tr>
                        <?php foreach ($systemHeaders as $header): ?>
                            <th><?= mgCancellationHtml($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?= count($systemHeaders) ?>">No data rows found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($systemHeaders as $header): ?>
                                    <td><?= mgCancellationHtml((string)($row[$header] ?? '')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
