<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/moneygram/moneygram-partner-match.php';
require_once __DIR__ . '/../recon/daycard-locks-common.php';

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

$payload = json_decode((string)($_POST['payload'] ?? ''), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload.']);
    exit;
}

$rows = $payload['rows'] ?? [];
$overwrite = !empty($payload['overwrite']);
$originalFilename = trim(basename((string)($payload['filename'] ?? '')));
if (!is_array($rows) || count($rows) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No rows to upload.']);
    exit;
}

$insertColumns = [
    'partner_id',
    'partnerName',
    'control_series_no',
    'date_cancelled',
    'date_claimed',
    'date_send',
    'kptn',
    'ccref_no',
    'currency',
    'amount',
    'ctc',
    'ctp',
    'charge',
    'sender_name',
    'sender_country',
    'beneficiary_receiver',
    'receiver_kyc',
    'receiver_phone',
    'operator',
    'branch_id',
    'branch',
    'remote_operator',
    'remote_branch_id',
    'remote_branch',
    'created_at',
    'updated_at',
    'uploaded_by',
    'mainzone',
    'zone',
    'area',
    'region',
    'region_code',
    'receiver_country',
    'receiver_name',
    'data_status',
    'match_status',
    'other_details',
    'ufl_file_log_id',
    'is_data_locked',
];

function kpxSaveValue(array $row, string $key): mixed
{
    if (!array_key_exists($key, $row)) return null;
    $value = $row[$key];
    if ($value === '') return null;
    return $value;
}

function kpxNormalizedAmount(mixed $value): string
{
    $number = str_replace(',', '', trim((string)$value));
    return number_format(is_numeric($number) ? (float)$number : 0.0, 2, '.', '');
}

function kpxDuplicateSignature(array $row): string
{
    $ccref = trim((string)($row['ccref_no'] ?? ''));
    $dateCancelled = kpxNormalizedDateOnly($row['date_cancelled'] ?? null);
    $dateClaimed = kpxNormalizedDateOnly($row['date_claimed'] ?? null);
    $dateSend = kpxNormalizedDateOnly($row['date_send'] ?? null);
    $parts = [$ccref];

    if ($dateCancelled !== '') {
        $parts[] = 'DATE_CANCELLED';
        $parts[] = $dateCancelled;
        if ($dateClaimed !== '') {
            $parts[] = 'DATE_CLAIMED';
            $parts[] = $dateClaimed;
        } else {
            $parts[] = 'DATE_SEND';
            $parts[] = $dateSend;
        }
    } elseif ($dateClaimed !== '') {
        $parts[] = 'DATE_CLAIMED';
        $parts[] = $dateClaimed;
    } else {
        $parts[] = 'DATE_SEND';
        $parts[] = $dateSend;
    }
    return implode("\x1F", $parts);
}

function kpxNormalizedDateOnly(mixed $value): string
{
    $text = trim((string)$value);
    if ($text === '') return '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $matches)) return $matches[1];
    $timestamp = strtotime($text);
    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
}

function kpxExpectedPartnerMatch(array $row): array
{
    $dateCancelled = kpxNormalizedDateOnly($row['date_cancelled'] ?? null);
    $dateClaimed = kpxNormalizedDateOnly($row['date_claimed'] ?? null);
    $dateSend = kpxNormalizedDateOnly($row['date_send'] ?? null);

    if ($dateCancelled !== '' && $dateClaimed !== '') return ['date' => $dateCancelled, 'types' => ['RRC']];
    if ($dateCancelled !== '' && $dateSend !== '') return ['date' => $dateCancelled, 'types' => ['RSN', 'REF']];
    if ($dateClaimed !== '') return ['date' => $dateClaimed, 'types' => ['REC']];
    if ($dateSend !== '') return ['date' => $dateSend, 'types' => ['SEN']];
    return ['date' => '', 'types' => []];
}

function kpxPartnerMatchSignature(string $referenceId, mixed $amount, mixed $date, string $transactionType): string
{
    $dateText = kpxNormalizedDateOnly($date);
    $normalizedAmount = kpxNormalizedAmount($amount);
    if ((float)$normalizedAmount < 0) {
        $normalizedAmount = number_format(abs((float)$normalizedAmount), 2, '.', '');
    }
    return trim($referenceId) . "\x1F" . $normalizedAmount . "\x1F" . $dateText
        . "\x1F" . strtoupper(trim($transactionType));
}

function kpxLoadPartnerMatches(PDO $pdo, array $rows): array
{
    $referenceIds = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (strtoupper(trim((string)($row['partnerName'] ?? ''))) !== 'MONEYGRAM') continue;
        $referenceId = trim((string)($row['ccref_no'] ?? ''));
        if ($referenceId !== '') $referenceIds[$referenceId] = true;
    }

    $partnerIdsBySignature = [];
    foreach (array_chunk(array_keys($referenceIds), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, reference_id, base_amt, tran_date, tran_type FROM moneygram_partner_data '
            . 'WHERE reference_id IN (' . $placeholders . ') '
            . "AND COALESCE(match_status, 0) = 1 AND COALESCE(is_data_locked, '0') = '0'"
        );
        $stmt->execute($chunk);
        while ($partnerRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signature = kpxPartnerMatchSignature(
                (string)($partnerRow['reference_id'] ?? ''),
                $partnerRow['base_amt'] ?? 0,
                $partnerRow['tran_date'] ?? null,
                (string)($partnerRow['tran_type'] ?? '')
            );
            $partnerId = (int)($partnerRow['id'] ?? 0);
            if ($partnerId > 0) $partnerIdsBySignature[$signature][$partnerId] = true;
        }
    }

    $matches = [];
    foreach ($rows as $row) {
        if (!is_array($row)
            || strtoupper(trim((string)($row['partnerName'] ?? ''))) !== 'MONEYGRAM') {
            $matches[] = [];
            continue;
        }
        $expected = kpxExpectedPartnerMatch($row);
        $matchedIds = [];
        foreach ($expected['types'] as $transactionType) {
            $signature = kpxPartnerMatchSignature(
                (string)($row['ccref_no'] ?? ''),
                $row['amount'] ?? 0,
                $expected['date'],
                $transactionType
            );
            foreach (array_keys($partnerIdsBySignature[$signature] ?? []) as $partnerId) {
                $matchedIds[(int)$partnerId] = true;
            }
        }
        $matches[] = array_map('intval', array_keys($matchedIds));
    }
    return $matches;
}

function kpxLoadDuplicateRows(PDO $pdo, array $rows): array
{
    $ccrefs = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $ccref = trim((string)($row['ccref_no'] ?? ''));
        if ($ccref !== '') $ccrefs[$ccref] = true;
    }

    $rowBySignature = [];
    foreach (array_chunk(array_keys($ccrefs), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, ccref_no, amount, data_status, date_cancelled, date_claimed, date_send, match_status, is_data_locked '
            . 'FROM ml_web_data WHERE ccref_no IN (' . $placeholders . ')'
        );
        $stmt->execute($chunk);
        while ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signature = kpxDuplicateSignature($existing);
            $existingMatchStatus = (int)($existing['match_status'] ?? 0);
            $existingIsLocked = (string)($existing['is_data_locked'] ?? '0') === '1';
            $selectedMatchStatus = (int)($rowBySignature[$signature]['match_status'] ?? 0);
            $selectedIsLocked = (string)($rowBySignature[$signature]['is_data_locked'] ?? '0') === '1';
            if (!isset($rowBySignature[$signature])
                || ($existingMatchStatus === 1 && $selectedMatchStatus !== 1)
                || ($existingMatchStatus === 1 && $existingIsLocked && !$selectedIsLocked)) {
                $rowBySignature[$signature] = [
                    'id' => (int)$existing['id'],
                    'match_status' => $existingMatchStatus,
                    'is_data_locked' => (string)($existing['is_data_locked'] ?? '0'),
                ];
            }
        }
    }

    $matches = [];
    foreach ($rows as $row) {
        $matches[] = is_array($row)
            ? ($rowBySignature[kpxDuplicateSignature($row)] ?? ['id' => 0, 'match_status' => 0, 'is_data_locked' => '0'])
            : ['id' => 0, 'match_status' => 0, 'is_data_locked' => '0'];
    }
    return $matches;
}

/**
 * Add-on lookup for the explicit MoneyGram re-upload workflow. An unlocked
 * prior match is identified only by CCREF and its transaction-type dates;
 * amount and other editable values must not prevent the overwrite prompt.
 */
function kpxLoadUnlockedOverwriteRows(PDO $pdo, array $rows): array
{
    $ccrefs = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (strtoupper(trim((string)($row['partnerName'] ?? ''))) !== 'MONEYGRAM') continue;
        $ccref = trim((string)($row['ccref_no'] ?? ''));
        if ($ccref !== '') $ccrefs[$ccref] = true;
    }

    $rowBySignature = [];
    foreach (array_chunk(array_keys($ccrefs), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, ccref_no, date_cancelled, date_claimed, date_send, match_status, is_data_locked '
            . 'FROM ml_web_data '
            . "WHERE UPPER(TRIM(COALESCE(partnerName, ''))) = 'MONEYGRAM' "
            . 'AND ccref_no IN (' . $placeholders . ') '
            . "AND COALESCE(match_status, 0) = 1 AND COALESCE(is_data_locked, '0') = '0' "
            . 'ORDER BY id ASC'
        );
        $stmt->execute($chunk);
        while ($existing = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signature = kpxDuplicateSignature($existing);
            if (!isset($rowBySignature[$signature])) {
                $rowBySignature[$signature] = [
                    'id' => (int)$existing['id'],
                    'match_status' => 1,
                    'is_data_locked' => '0',
                ];
            }
        }
    }

    $matches = [];
    foreach ($rows as $row) {
        $matches[] = is_array($row)
            ? ($rowBySignature[kpxDuplicateSignature($row)] ?? ['id' => 0, 'match_status' => 0, 'is_data_locked' => '0'])
            : ['id' => 0, 'match_status' => 0, 'is_data_locked' => '0'];
    }
    return $matches;
}

function kpxFileLogMetadata(array $rows, string $originalFilename): array
{
    $firstRow = [];
    $statuses = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (empty($firstRow)) $firstRow = $row;
        $status = strtoupper(trim((string)($row['data_status'] ?? '')));
        if ($status !== '') $statuses[$status] = true;
    }

    $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    $filename = pathinfo($originalFilename, PATHINFO_FILENAME);
    return [
        'filename' => trim($filename),
        'filename_ext' => trim($extension),
        'partner_id' => trim((string)($firstRow['partner_id'] ?? '')),
        'partner_name' => trim((string)($firstRow['partnerName'] ?? '')),
        'kpxweb_data_status' => implode(',', array_keys($statuses)),
    ];
}

function kpxResolveFileLogId(PDO $pdo, array $log): int
{
    $findStmt = $pdo->prepare(
        'SELECT id FROM uploaded_file_logs '
        . 'WHERE filename = ? AND partner_id = ? '
        . 'ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $findStmt->execute([$log['filename'], $log['partner_id']]);
    $existingId = $findStmt->fetchColumn();

    if ($existingId !== false) {
        $fileLogId = (int)$existingId;
        $updateStmt = $pdo->prepare(
            "UPDATE uploaded_file_logs SET has_overwrite = '1', uploaded_date = NOW() WHERE id = ?"
        );
        $updateStmt->execute([$fileLogId]);
        return $fileLogId;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO uploaded_file_logs '
        . '(uploaded_date, filename, filename_ext, partner_id, partner_name, uploaded_by, has_overwrite, kpxweb_data_status) '
        . "VALUES (NOW(), ?, ?, ?, ?, ?, '0', ?)"
    );
    $insertStmt->execute([
        $log['filename'],
        $log['filename_ext'],
        $log['partner_id'] !== '' ? $log['partner_id'] : null,
        $log['partner_name'] !== '' ? $log['partner_name'] : null,
        trim((string)($_SESSION['user']['id_number'] ?? '')) ?: null,
        $log['kpxweb_data_status'] !== '' ? $log['kpxweb_data_status'] : null,
    ]);
    return (int)$pdo->lastInsertId();
}

try {
    $pdo = fileRecDbConnection();
    $moneygramRowsPresent = false;
    foreach ($rows as $row) {
        if (is_array($row) && strtoupper(trim((string)($row['partnerName'] ?? ''))) === 'MONEYGRAM') {
            $moneygramRowsPresent = true;
            break;
        }
    }
    $columnRows = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
    $availableColumns = [];
    foreach ($columnRows as $columnRow) {
        $availableColumns[(string)$columnRow['Field']] = true;
    }
    $columns = array_values(array_filter(
        $insertColumns,
        static fn($column) => isset($availableColumns[$column])
            && ($column !== 'match_status' || $moneygramRowsPresent)
    ));
    if (empty($columns)) {
        throw new RuntimeException('No compatible ml_web_data columns found.');
    }
    if ($moneygramRowsPresent
        && (!isset($availableColumns['match_status']) || !isset($availableColumns['is_data_locked']))) {
        throw new RuntimeException('The ml_web_data matching columns are missing.');
    }

    $existingRowByUploadRow = kpxLoadDuplicateRows($pdo, $rows);
    $unlockedOverwriteRowByUploadRow = kpxLoadUnlockedOverwriteRows($pdo, $rows);
    // Presence is checked for the rematch workflow, but it must not promote
    // a new KPX row; Partner Data re-upload owns the later 1 / 1 transition.
    $partnerIdsByUploadRow = kpxLoadPartnerMatches($pdo, $rows);
    if ($moneygramRowsPresent) {
        $moneygramColumns = $pdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_ASSOC);
        $moneygramAvailableColumns = [];
        foreach ($moneygramColumns as $columnRow) {
            $moneygramAvailableColumns[(string)$columnRow['Field']] = true;
        }
        if (!isset($moneygramAvailableColumns['match_status']) || !isset($moneygramAvailableColumns['is_data_locked'])) {
            throw new RuntimeException('The moneygram_partner_data matching columns are missing.');
        }
    }
    $overwriteCandidateIds = [];
    foreach ($existingRowByUploadRow as $rowIndex => $existingRow) {
        $unlockedOverwriteRow = $unlockedOverwriteRowByUploadRow[$rowIndex] ?? ['id' => 0];
        $id = (int)($existingRow['id'] ?? 0);
        $matchStatus = (int)($existingRow['match_status'] ?? 0);
        $isDataLocked = (string)($existingRow['is_data_locked'] ?? '0') === '1';
        $isMoneygram = is_array($rows[$rowIndex] ?? null)
            && strtoupper(trim((string)($rows[$rowIndex]['partnerName'] ?? ''))) === 'MONEYGRAM';
        if (($isMoneygram && (int)($unlockedOverwriteRow['id'] ?? 0) > 0)
            || (!$isMoneygram && $id > 0)) {
            $overwriteCandidateIds[] = $id;
        }
    }

    if (!$overwrite && count($overwriteCandidateIds) > 0) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'duplicateCount' => count($overwriteCandidateIds),
            'message' => 'Data with the same CCREF NO and date already exists. Do you want to overwrite the existing data?',
        ]);
        exit;
    }

    $quotedColumns = array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $columns);
    $insertSql = 'INSERT INTO ml_web_data (' . implode(',', $quotedColumns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
    $insertStmt = $pdo->prepare($insertSql);
    $updateColumns = array_values(array_filter($columns, static fn($column) => $column !== 'created_at'));
    $updateSql = 'UPDATE ml_web_data SET ' . implode(',', array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '` = ?', $updateColumns)) . ' WHERE id = ?';
    $updateStmt = $pdo->prepare($updateSql);
    $matchPartnerStmt = $pdo->prepare(
        "UPDATE moneygram_partner_data SET match_status = 1, is_data_locked = '1', updated_at = NOW() WHERE id = ?"
    );
    $duplicatePartnerStmt = $pdo->prepare(
        "UPDATE moneygram_partner_data SET match_status = 3, is_data_locked = '0', updated_at = NOW() WHERE id = ?"
    );
    $inserted = 0;
    $updated = 0;
    $pdo->beginTransaction();
    $log = kpxFileLogMetadata($rows, $originalFilename);
    if ($log['filename'] === '' || $log['filename_ext'] === '') {
        throw new RuntimeException('The uploaded filename or extension is missing.');
    }
    $fileLogId = kpxResolveFileLogId($pdo, $log);
    if ($fileLogId <= 0) {
        throw new RuntimeException('Unable to create or update the uploaded file log.');
    }

    $matchedLockDates = [];
    foreach ($rows as $rowIndex => $row) {
        if (!is_array($row)) continue;
        $row['ufl_file_log_id'] = $fileLogId;
        $existingRow = $existingRowByUploadRow[$rowIndex] ?? ['id' => 0, 'match_status' => 0, 'is_data_locked' => '0'];
        $id = (int)($existingRow['id'] ?? 0);
        $existingMatchStatus = (int)($existingRow['match_status'] ?? 0);
        $existingIsLocked = (string)($existingRow['is_data_locked'] ?? '0') === '1';
        $isMoneygram = strtoupper(trim((string)($row['partnerName'] ?? ''))) === 'MONEYGRAM';
        $partnerMatchIds = array_values(array_unique(array_map('intval', $partnerIdsByUploadRow[$rowIndex] ?? [])));
        $hasPartnerMatch = $isMoneygram && !empty($partnerMatchIds);
        $matchedKpxRow = false;
        $unlockedOverwriteRow = $unlockedOverwriteRowByUploadRow[$rowIndex] ?? ['id' => 0];
        $unlockedOverwriteId = (int)($unlockedOverwriteRow['id'] ?? 0);

        if ($isMoneygram && $overwrite && $unlockedOverwriteId > 0) {
            // An explicitly unlocked prior match becomes a mismatch after its
            // contents are overwritten. A later Partner upload owns rematching.
            $row['match_status'] = 2;
            $row['is_data_locked'] = 0;
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $updateColumns);
            $values[] = $unlockedOverwriteId;
            $updateStmt->execute($values);
            $updated++;
        } elseif ($isMoneygram && $id > 0 && !($existingMatchStatus === 1 && !$existingIsLocked && $overwrite)) {
            // Any existing KPX occurrence is an insert-side duplicate. The one
            // exception is the explicitly confirmed overwrite of an unlocked
            // prior match (match_status 1 / is_data_locked 0).
            $row['match_status'] = 3;
            $row['is_data_locked'] = 0;
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $columns);
            $insertStmt->execute($values);
            $inserted++;
        } elseif ($id > 0 && $overwrite) {
            if ($isMoneygram) {
                $row['match_status'] = $hasPartnerMatch ? 1 : 2;
                $row['is_data_locked'] = $hasPartnerMatch ? 1 : 0;
                $matchedKpxRow = $hasPartnerMatch;
            }
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $updateColumns);
            $values[] = $id;
            $updateStmt->execute($values);
            $updated++;
        } elseif ($id <= 0) {
            if ($isMoneygram) {
                $row['match_status'] = $hasPartnerMatch ? 1 : 2;
                $row['is_data_locked'] = $hasPartnerMatch ? 1 : 0;
                $matchedKpxRow = $hasPartnerMatch;
            }
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $columns);
            $insertStmt->execute($values);
            $inserted++;
        }

        if ($matchedKpxRow) {
            $matchPartnerStmt->execute([$partnerMatchIds[0]]);
            foreach (array_slice($partnerMatchIds, 1) as $duplicatePartnerId) {
                $duplicatePartnerStmt->execute([$duplicatePartnerId]);
            }
            $expectedMatch = kpxExpectedPartnerMatch($row);
            if ($expectedMatch['date'] !== '') {
                $matchedLockDates[$expectedMatch['date']] = true;
            }
        }
    }
    moneygramUpsertMatchedLockDates(
        $pdo,
        array_keys($matchedLockDates),
        trim((string)($_SESSION['user']['id_number'] ?? ''))
    );
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'fileLogId' => $fileLogId,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    error_log('[kpx-webdata-save] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Upload failed. Please check the server error log.']);
}
