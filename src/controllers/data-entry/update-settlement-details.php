<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function settlementUpdateRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function settlementUpdateText(mixed $value, string $label, bool $required, int $maxLength): ?string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    if (mb_strlen($text) > $maxLength) {
        throw new InvalidArgumentException($label . ' is too long.');
    }
    return $text;
}

function settlementUpdateDate(mixed $value, string $label, bool $required): ?string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if (!$date || $date->format('Y-m-d') !== $text) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $text;
}

function settlementUpdateCents(mixed $value, string $label, bool $required): ?int
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return null;
    }
    if (!preg_match('/^-?\d{1,16}(?:\.\d{1,2})?$/', $text)) {
        throw new InvalidArgumentException($label . ' must be a number with up to two decimal places.');
    }
    $negative = str_starts_with($text, '-');
    $unsigned = ltrim($text, '-');
    [$whole, $decimal] = array_pad(explode('.', $unsigned, 2), 2, '');
    $cents = ((int)$whole * 100) + (int)str_pad($decimal, 2, '0');
    return $negative ? -$cents : $cents;
}

function settlementUpdateDecimal(?int $cents): ?string
{
    if ($cents === null) {
        return null;
    }
    $sign = $cents < 0 ? '-' : '';
    $absolute = abs($cents);
    return $sign . intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
}

function settlementUploadedDocumentsForRow(int $rowId): array
{
    $key = 'supporting_documents_' . $rowId;
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
        return [];
    }

    $upload = $_FILES[$key];
    $names = is_array($upload['name'] ?? null) ? $upload['name'] : [$upload['name'] ?? ''];
    $temporaryNames = is_array($upload['tmp_name'] ?? null) ? $upload['tmp_name'] : [$upload['tmp_name'] ?? ''];
    $errors = is_array($upload['error'] ?? null) ? $upload['error'] : [$upload['error'] ?? UPLOAD_ERR_NO_FILE];
    $sizes = is_array($upload['size'] ?? null) ? $upload['size'] : [$upload['size'] ?? 0];
    $documents = [];

    foreach ($names as $index => $name) {
        $error = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('A supporting document could not be uploaded.');
        }

        $temporaryName = (string)($temporaryNames[$index] ?? '');
        $size = (int)($sizes[$index] ?? 0);
        if ($temporaryName === '' || !is_uploaded_file($temporaryName)) {
            throw new InvalidArgumentException('An invalid supporting-document upload was received.');
        }
        if ($size <= 0 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('Each supporting document must be between 1 byte and 25 MB.');
        }

        $safeOriginalName = trim(basename(str_replace('\\', '/', (string)$name)));
        $safeOriginalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeOriginalName) ?? '';
        if ($safeOriginalName === '' || mb_strlen($safeOriginalName) > 545) {
            throw new InvalidArgumentException('A supporting document has an invalid filename.');
        }
        $extension = strtolower((string)pathinfo($safeOriginalName, PATHINFO_EXTENSION));
        $filename = (string)pathinfo($safeOriginalName, PATHINFO_FILENAME);
        if ($filename === '') {
            $filename = $safeOriginalName;
        }
        if (mb_strlen($filename) > 500 || mb_strlen($extension) > 45) {
            throw new InvalidArgumentException('A supporting-document filename or extension is too long.');
        }

        $hash = hash_file('sha256', $temporaryName);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Unable to hash a supporting document.');
        }
        $documents[] = [
            'filename' => $filename,
            'extension' => $extension,
            'temporary_name' => $temporaryName,
            'hash' => $hash,
        ];
    }

    return $documents;
}

if (!isAuthenticated()) {
    settlementUpdateRespond(401, ['success' => false, 'message' => 'Your session has expired. Please log in again.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    settlementUpdateRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

verifyCsrfOrFail();

$partner = trim((string)($_POST['partner'] ?? ''));
$payload = json_decode((string)($_POST['payload'] ?? ''), true);
$rows = is_array($payload) ? ($payload['rows'] ?? null) : null;
$userId = trim((string)($_SESSION['user']['id_number'] ?? ''));

if ($partner === '' || !is_array($rows) || $rows === [] || $userId === '') {
    settlementUpdateRespond(422, ['success' => false, 'message' => 'Partner, modified rows, and user identity are required.']);
}
if (count($rows) > 500) {
    settlementUpdateRespond(422, ['success' => false, 'message' => 'A maximum of 500 rows can be modified at once.']);
}

try {
    $pdo = fileRecDbConnection();
    $pdo->beginTransaction();
    $storageRoot = env(
        'SUPPORTING_DOCUMENT_STORAGE_PATH',
        dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'corporate-recon-storage' . DIRECTORY_SEPARATOR . 'supporting-documents'
    );
    $storageRoot = rtrim((string)$storageRoot, '/\\');
    if ($storageRoot === '') {
        throw new RuntimeException('Supporting-document storage is not configured.');
    }
    $createdDocumentPaths = [];

    $selectRow = $pdo->prepare(
        'SELECT *
         FROM partner_settlement_data
         WHERE id = ? AND partner_name = ?
         LIMIT 1
         FOR UPDATE'
    );
    $archiveRow = $pdo->prepare(
        'INSERT INTO origin_partner_settlement_datarows_logs
        (partner_id, partner_name, account_number, agent_name, legacy_id, tran_date, settled_date,
         transaction_id, reference_id, product, tran_type, orig_cntry, rcv_cntry, fx_rate_trn,
         fx_date_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt,
         total_tran_amt, settlement_currency, transaction_currency, created_at, created_by,
         updated_at, updated_by, modified_at, modified_by, psd_datarows_id, ufl_file_log_id)
        SELECT partner_id, partner_name, account_number, agent_name, legacy_id, tran_date, settled_date,
               transaction_id, reference_id, product, tran_type, orig_cntry, rcv_cntry, fx_rate_trn,
               fx_date_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt,
               total_tran_amt, settlement_currency, transaction_currency, created_at, created_by,
               updated_at, updated_by, modified_at, modified_by, id, ufl_file_log_id
        FROM partner_settlement_data
        WHERE id = ? AND partner_name = ?'
    );
    $archiveExists = $pdo->prepare(
        'SELECT 1
         FROM origin_partner_settlement_datarows_logs
         WHERE psd_datarows_id = ?
         LIMIT 1'
    );
    $updateRow = $pdo->prepare(
        'UPDATE partner_settlement_data
         SET account_number = ?, agent_name = ?, legacy_id = ?, tran_date = ?, settled_date = ?, transaction_id = ?,
             reference_id = ?, product = ?, tran_type = ?, orig_cntry = ?, rcv_cntry = ?,
             fx_rate_trn = ?, fx_date_trn = ?, margin = ?, base_tran_amt = ?, fee_tran_amt = ?,
             fx_rev_share_tran_amt = ?, comm_tran_amt = ?, total_tran_amt = ?,
             settlement_currency = ?, transaction_currency = ?, modified_at = NOW(), modified_by = ?
         WHERE id = ? AND partner_name = ?'
    );
    $insertDocumentLog = $pdo->prepare(
        'INSERT INTO uploaded_documentation_file_logs
        (uploaded_date, filename, filename_ext, partner_id, partner_name, filehash_path,
         uploaded_by, ufl_file_log_id, psd_datarows_id)
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $updatedIds = [];
    $archivedCount = 0;
    $archiveSkippedCount = 0;
    $uploadedDocumentCount = 0;
    $totalDocumentCount = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Invalid modified row payload.');
        }
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $values = $row['values'] ?? null;
        if ($id === false || !is_array($values)) {
            throw new InvalidArgumentException('Each modified row requires a valid settlement ID and values.');
        }

        $documents = settlementUploadedDocumentsForRow((int)$id);
        $totalDocumentCount += count($documents);
        if ($totalDocumentCount > 50) {
            throw new InvalidArgumentException('A maximum of 50 supporting documents can be uploaded at once.');
        }

        $selectRow->execute([$id, $partner]);
        $existingRow = $selectRow->fetch(PDO::FETCH_ASSOC);
        if (!$existingRow) {
            throw new RuntimeException('Settlement row ' . $id . ' was not found for the selected partner.');
        }

        $existingSettledDate = trim((string)($existingRow['settled_date'] ?? ''));
        $settledDate = $existingSettledDate !== ''
            ? $existingSettledDate
            : settlementUpdateDate($values['settled_date'] ?? null, 'Settled Date', true);

        $tranType = strtoupper((string)settlementUpdateText($values['tran_type'] ?? null, 'Tran Type', true, 100));
        if (!in_array($tranType, ['REC', 'RRC', 'SEN', 'RSN', 'REF'], true)) {
            throw new InvalidArgumentException('Tran Type is invalid.');
        }

        $baseCents = settlementUpdateCents($values['base_tran_amt'] ?? null, 'Base Tran Amt', true);
        $feeCents = settlementUpdateCents($values['fee_tran_amt'] ?? null, 'Fee Tran Amt', true);
        $fxShareCents = settlementUpdateCents($values['fx_rev_share_tran_amt'] ?? null, 'Fx Rev Share Tran Amt', true);
        $commissionCents = settlementUpdateCents($values['comm_tran_amt'] ?? null, 'Comm Tran Amt', true);
        $forcedNegativeFields = match ($tranType) {
            'REC' => ['base', 'fx_share', 'commission'],
            'SEN' => ['fx_share', 'commission'],
            'RSN' => ['base', 'fee'],
            'REF' => ['base'],
            default => [],
        };
        if (in_array('base', $forcedNegativeFields, true)) {
            $baseCents = -abs((int)$baseCents);
        }
        if (in_array('fee', $forcedNegativeFields, true)) {
            $feeCents = -abs((int)$feeCents);
        }
        if (in_array('fx_share', $forcedNegativeFields, true)) {
            $fxShareCents = -abs((int)$fxShareCents);
        }
        if (in_array('commission', $forcedNegativeFields, true)) {
            $commissionCents = -abs((int)$commissionCents);
        }
        $totalCents = (int)$baseCents + (int)$feeCents + (int)$fxShareCents + (int)$commissionCents;
        $settlementCurrency = strtoupper((string)settlementUpdateText(
            $values['settlement_currency'] ?? null,
            'Settlement Currency',
            true,
            45
        ));
        $transactionCurrency = strtoupper((string)settlementUpdateText(
            $values['transaction_currency'] ?? null,
            'Transaction Currency',
            true,
            45
        ));
        if (!in_array($settlementCurrency, ['PHP', 'USD'], true)
            || !in_array($transactionCurrency, ['PHP', 'USD'], true)) {
            throw new InvalidArgumentException('Settlement Currency and Transaction Currency must be PHP or USD.');
        }

        $parameters = [
            settlementUpdateText($values['account_number'] ?? null, 'Account Number', false, 100),
            settlementUpdateText($values['agent_name'] ?? null, 'Agent Name', true, 255),
            settlementUpdateText($values['legacy_id'] ?? null, 'Legacy ID', false, 100),
            settlementUpdateDate($values['tran_date'] ?? null, 'Tran Date', true),
            $settledDate,
            settlementUpdateText($values['transaction_id'] ?? null, 'Transaction ID', false, 100),
            settlementUpdateText($values['reference_id'] ?? null, 'Reference ID', true, 100),
            settlementUpdateText($values['product'] ?? null, 'Product', false, 45),
            $tranType,
            settlementUpdateText($values['orig_cntry'] ?? null, 'Orig Cntry', false, 100),
            settlementUpdateText($values['rcv_cntry'] ?? null, 'Rcv Cntry', false, 100),
            settlementUpdateDecimal(settlementUpdateCents($values['fx_rate_trn'] ?? null, 'Fx Rate Trn', false)),
            settlementUpdateDate($values['fx_date_trn'] ?? null, 'Fx Date Trn', false),
            settlementUpdateDecimal(settlementUpdateCents($values['margin'] ?? null, 'Margin', false)),
            settlementUpdateDecimal($baseCents),
            settlementUpdateDecimal($feeCents),
            settlementUpdateDecimal($fxShareCents),
            settlementUpdateDecimal($commissionCents),
            settlementUpdateDecimal($totalCents),
            $settlementCurrency,
            $transactionCurrency,
            $userId,
            $id,
            $partner,
        ];

        $archiveExists->execute([$id]);
        if ($archiveExists->fetchColumn() === false) {
            $archiveRow->execute([$id, $partner]);
            if ($archiveRow->rowCount() !== 1) {
                throw new RuntimeException('Unable to archive settlement row ' . $id . '.');
            }
            $archivedCount++;
        } else {
            $archiveSkippedCount++;
        }
        $updateRow->execute($parameters);

        foreach ($documents as $document) {
            $relativeDirectory = 'sha256/' . substr($document['hash'], 0, 2) . '/' . substr($document['hash'], 2, 2);
            $absoluteDirectory = $storageRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
            if (!is_dir($absoluteDirectory)
                && !mkdir($absoluteDirectory, 0750, true)
                && !is_dir($absoluteDirectory)) {
                throw new RuntimeException('Unable to prepare supporting-document storage.');
            }

            $storedFilename = $document['hash'] . ($document['extension'] !== '' ? '.' . $document['extension'] : '');
            $relativePath = $relativeDirectory . '/' . $storedFilename;
            $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storedFilename;
            if (!is_file($absolutePath)) {
                if (!move_uploaded_file($document['temporary_name'], $absolutePath)) {
                    throw new RuntimeException('Unable to store a supporting document.');
                }
                $createdDocumentPaths[] = $absolutePath;
            }

            $insertDocumentLog->execute([
                $document['filename'],
                $document['extension'] !== '' ? $document['extension'] : null,
                $existingRow['partner_id'] ?? null,
                $existingRow['partner_name'] ?? $partner,
                $relativePath,
                $userId,
                $existingRow['ufl_file_log_id'] ?? null,
                $id,
            ]);
            $uploadedDocumentCount++;
        }
        $updatedIds[] = (int)$id;
    }

    $pdo->commit();
    settlementUpdateRespond(200, [
        'success' => true,
        'updated_count' => count($updatedIds),
        'updated_ids' => $updatedIds,
        'archived_count' => $archivedCount,
        'archive_skipped_count' => $archiveSkippedCount,
        'uploaded_document_count' => $uploadedDocumentCount,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($createdDocumentPaths) && is_array($createdDocumentPaths)) {
        foreach ($createdDocumentPaths as $createdDocumentPath) {
            if (is_string($createdDocumentPath) && is_file($createdDocumentPath)) {
                @unlink($createdDocumentPath);
            }
        }
    }
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    settlementUpdateRespond($status, [
        'success' => false,
        'message' => $status === 422 ? $exception->getMessage() : 'Unable to apply modified settlement changes.',
    ]);
}
