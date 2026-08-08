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

    $updatedIds = [];
    $archivedCount = 0;
    $archiveSkippedCount = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Invalid modified row payload.');
        }
        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $values = $row['values'] ?? null;
        if ($id === false || !is_array($values)) {
            throw new InvalidArgumentException('Each modified row requires a valid settlement ID and values.');
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
        $updatedIds[] = (int)$id;
    }

    $pdo->commit();
    settlementUpdateRespond(200, [
        'success' => true,
        'updated_count' => count($updatedIds),
        'updated_ids' => $updatedIds,
        'archived_count' => $archivedCount,
        'archive_skipped_count' => $archiveSkippedCount,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    settlementUpdateRespond($status, [
        'success' => false,
        'message' => $status === 422 ? $exception->getMessage() : 'Unable to apply modified settlement changes.',
    ]);
}
