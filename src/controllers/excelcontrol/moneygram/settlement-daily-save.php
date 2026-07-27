<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';

bootSecureSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}
$sentToken = (string)($_POST['csrf_token'] ?? '');
$storedToken = (string)($_SESSION['csrf_token'] ?? '');
if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$payload = json_decode((string)($_POST['payload'] ?? ''), true);
$rows = is_array($payload) && isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
$partnerId = trim((string)($payload['partner_id'] ?? ''));
$partnerName = trim((string)($payload['partner_name'] ?? ''));
$uploadMode = (string)($payload['upload_mode'] ?? 'daily');
$fileLogId = filter_var($payload['file_log_id'] ?? null, FILTER_VALIDATE_INT);
if ($partnerId !== '1' || stripos($partnerName, 'moneygram') === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This upload workflow is available only for MoneyGram partner ID 1.']);
    exit;
}
if ($rows === [] || count($rows) > 750) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Each upload batch must contain between 1 and 750 rows.']);
    exit;
}
if ($fileLogId === false || $fileLogId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid uploaded file log ID is required.']);
    exit;
}

function settlementSaveText(mixed $value): ?string
{
    $text = trim((string)($value ?? ''));
    return $text === '' ? null : $text;
}
function settlementSaveNumber(mixed $value): ?float
{
    if ($value === null) return null;
    $text = trim((string)$value);
    if ($text === '') return null;
    $negative = preg_match('/^\(.*\)$/', $text) === 1;
    $text = str_replace([',', ' '], '', trim($text, "() \t\n\r\0\x0B"));
    if (!is_numeric($text)) return null;
    $number = (float)$text;
    return $negative ? -abs($number) : $number;
}
function settlementSaveDate(mixed $value): ?string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') return null;
    $timestamp = strtotime($text);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function settlementSubsetHasValue(string $field, mixed $value): bool
{
    if (in_array($field, ['fx_rate_trn', 'tran_fx_rate', 'margin', 'base_tran_amt', 'base_amt', 'fee_tran_amt', 'fx_rev_share_tran_amt', 'fx_rev_share_amt', 'comm_tran_amt', 'comm_amt', 'total_tran_amt'], true)) {
        return $value !== null && abs((float)$value) >= 0.0000001;
    }
    return trim((string)($value ?? '')) !== '';
}

function settlementTranTypesMatch(mixed $excelValue, mixed $databaseValue): bool
{
    return strcasecmp(trim((string)($excelValue ?? '')), trim((string)($databaseValue ?? ''))) === 0;
}

/** @return array<string,mixed>|null */
function settlementFindMatchedRow(PDO $pdo, string $table, array $item, ?string $partnerId = null): ?array
{
    if (!in_array($table, ['moneygram_partner_data', 'partner_settlement_data'], true)) return null;
    $partnerSql = $partnerId === null ? '' : ' AND partner_id = ?';
    $select = 'SELECT * FROM `' . $table . '` WHERE reference_id = ? AND tran_date = ?' . $partnerSql . ' ORDER BY id DESC LIMIT 1';
    $statement = $pdo->prepare($select);
    $parameters = [$item['reference_id'], $item['tran_date']];
    if ($partnerId !== null) $parameters[] = $partnerId;
    $statement->execute($parameters);
    $matched = $statement->fetch(PDO::FETCH_ASSOC);
    if (is_array($matched) && settlementTranTypesMatch($item['tran_type'] ?? null, $matched['tran_type'] ?? null)) return $matched;

    foreach (['base_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt'] as $field) {
        if (!settlementSubsetHasValue($field, $item[$field] ?? null)) continue;
        $select = 'SELECT * FROM `' . $table . '` WHERE reference_id = ? AND ABS(`' . $field . '`) = ?' . $partnerSql . ' ORDER BY id DESC LIMIT 1';
        $statement = $pdo->prepare($select);
        $parameters = [$item['reference_id'], abs((float)$item[$field])];
        if ($partnerId !== null) $parameters[] = $partnerId;
        $statement->execute($parameters);
        $matched = $statement->fetch(PDO::FETCH_ASSOC);
        if (is_array($matched) && settlementTranTypesMatch($item['tran_type'] ?? null, $matched['tran_type'] ?? null)) return $matched;
    }
    return null;
}

/** @return array<string,array<int,array<string,mixed>>> */
function settlementPrefetchRows(PDO $pdo, string $table, array $items, ?string $partnerId = null): array
{
    if (!in_array($table, ['moneygram_partner_data', 'partner_settlement_data'], true)) return [];
    $references = array_values(array_unique(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['reference_id'] ?? '')),
        $items
    ))));
    $rowsByReference = [];
    foreach (array_chunk($references, 750) as $referenceChunk) {
        $placeholders = implode(',', array_fill(0, count($referenceChunk), '?'));
        $sql = 'SELECT * FROM `' . $table . '` WHERE reference_id IN (' . $placeholders . ')';
        $parameters = $referenceChunk;
        if ($partnerId !== null) {
            $sql .= ' AND partner_id = ?';
            $parameters[] = $partnerId;
        }
        $sql .= ' ORDER BY id DESC';
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rowsByReference[(string)$row['reference_id']][] = $row;
        }
    }
    return $rowsByReference;
}

/** @return array<string,mixed>|null */
function settlementMatchPrefetchedRow(array $rowsByReference, array $item): ?array
{
    $candidates = array_values(array_filter(
        $rowsByReference[(string)$item['reference_id']] ?? [],
        static fn(array $candidate): bool => settlementTranTypesMatch($item['tran_type'] ?? null, $candidate['tran_type'] ?? null)
    ));
    foreach ($candidates as $candidate) {
        if ((string)($candidate['tran_date'] ?? '') === (string)$item['tran_date']) return $candidate;
    }
    foreach (['base_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt'] as $field) {
        if (!settlementSubsetHasValue($field, $item[$field] ?? null)) continue;
        $expected = abs((float)$item[$field]);
        foreach ($candidates as $candidate) {
            if (!settlementSubsetHasValue($field, $candidate[$field] ?? null)) continue;
            if (abs(abs((float)$candidate[$field]) - $expected) < 0.005) return $candidate;
        }
    }
    return null;
}

function settlementPatchMissingFields(PDO $pdo, string $table, int $id, array $current, array $values, string $userId): int
{
    if (!in_array($table, ['moneygram_partner_data', 'partner_settlement_data'], true)) return 0;
    $assignments = [];
    $parameters = [];
    foreach ($values as $field => $value) {
        if (settlementSubsetHasValue($field, $current[$field] ?? null) || !settlementSubsetHasValue($field, $value)) continue;
        $assignments[] = '`' . $field . '` = ?';
        $parameters[] = $value;
    }
    if ($assignments === []) return 0;
    $assignments[] = 'updated_at = NOW()';
    if ($table === 'partner_settlement_data') {
        $assignments[] = 'updated_by = ?';
        $parameters[] = $userId;
    }
    $parameters[] = $id;
    $statement = $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $assignments) . ' WHERE id = ?');
    $statement->execute($parameters);
    return $statement->rowCount();
}

try {
    $pdo = fileRecDbConnection();
    $pdo->beginTransaction();
    $userId = trim((string)($_SESSION['user']['id_number'] ?? ''));

    $fileLogStatement = $pdo->prepare(
        "SELECT id FROM uploaded_file_logs
         WHERE id = ? AND partner_id = ? AND FIND_IN_SET('SD', REPLACE(COALESCE(kpxweb_data_status, ''), ' ', '')) > 0
         LIMIT 1"
    );
    $fileLogStatement->execute([$fileLogId, $partnerId]);
    if ($fileLogStatement->fetchColumn() === false) {
        throw new RuntimeException('The settlement uploaded file log is invalid.');
    }

    $findSettlement = $pdo->prepare(
        'SELECT id FROM partner_settlement_data
         WHERE partner_id = ? AND reference_id = ? AND tran_date = ?
           AND LOWER(TRIM(COALESCE(tran_type, \'\'))) = ?
         ORDER BY id DESC LIMIT 1'
    );
    $insertSettlement = $pdo->prepare(
        'INSERT INTO partner_settlement_data
        (partner_id, partner_name, account_number, agent_name, legacy_id, tran_date, settled_date, transaction_id, reference_id, product, tran_type, orig_cntry, rcv_cntry, fx_rate_trn, fx_date_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt, total_tran_amt, settlement_currency, transaction_currency, ufl_file_log_id, created_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)'
    );
    $updateSettlement = $pdo->prepare(
        'UPDATE partner_settlement_data SET partner_name=?, account_number=?, agent_name=?, legacy_id=?, settled_date=?, transaction_id=?, product=?, tran_type=?, orig_cntry=?, rcv_cntry=?, fx_rate_trn=?, fx_date_trn=?, margin=?, base_tran_amt=?, fee_tran_amt=?, fx_rev_share_tran_amt=?, comm_tran_amt=?, total_tran_amt=?, settlement_currency=?, transaction_currency=?, ufl_file_log_id=?, updated_at=NOW(), updated_by=? WHERE id=?'
    );
    $linkSettlement = $pdo->prepare(
        'UPDATE partner_settlement_data SET ufl_file_log_id = ?, updated_at = NOW(), updated_by = ? WHERE id = ?'
    );

    $prepared = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $item = [
            'account_number' => settlementSaveText($row['account_number'] ?? null),
            'agent_name' => settlementSaveText($row['agent_name'] ?? null),
            'legacy_id' => settlementSaveText($row['legacy_id'] ?? null),
            'tran_date' => settlementSaveDate($row['tran_date'] ?? null),
            'settled_date' => $uploadMode === 'daily' ? settlementSaveDate($row['settled_date'] ?? null) : null,
            'transaction_id' => settlementSaveText($row['transaction_id'] ?? null),
            'reference_id' => settlementSaveText($row['reference_id'] ?? null),
            'product' => settlementSaveText($row['product'] ?? null),
            'tran_type' => settlementSaveText($row['tran_type'] ?? null),
            'orig_cntry' => settlementSaveText($row['orig_cntry'] ?? null),
            'rcv_cntry' => settlementSaveText($row['rcv_cntry'] ?? null),
            'fx_rate_trn' => settlementSaveNumber($row['fx_rate_trn'] ?? null),
            'fx_date_trn' => settlementSaveDate($row['fx_date_trn'] ?? null),
            'margin' => settlementSaveNumber($row['margin'] ?? null),
            'base_tran_amt' => settlementSaveNumber($row['base_tran_amt'] ?? null),
            'fee_tran_amt' => settlementSaveNumber($row['fee_tran_amt'] ?? null),
            'fx_rev_share_tran_amt' => settlementSaveNumber($row['fx_rev_share_tran_amt'] ?? null),
            'comm_tran_amt' => settlementSaveNumber($row['comm_tran_amt'] ?? null),
            'total_tran_amt' => settlementSaveNumber($row['total_tran_amt'] ?? null),
            'settlement_currency' => settlementSaveText($row['settlement_currency'] ?? null),
            'transaction_currency' => settlementSaveText($row['transaction_currency'] ?? null),
            'exists' => !empty($row['exists']),
            'db_tran_date' => settlementSaveDate($row['db_tran_date'] ?? null),
        ];
        if ($item['reference_id'] === null || $item['tran_date'] === null) throw new RuntimeException('Reference ID and Tran Date are required for every row.');
        $prepared[] = $item;
    }

    if ($uploadMode === 'endMonth') {
        $subsetFields = [
            'tran_type', 'orig_cntry', 'rcv_cntry', 'fx_rate_trn', 'fx_date_trn', 'margin',
            'base_tran_amt', 'fee_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt',
            'transaction_currency',
        ];
        $settlementInserted = 0;
        $settlementAmended = 0;
        $moneygramCandidates = settlementPrefetchRows($pdo, 'moneygram_partner_data', $prepared);
        $settlementCandidates = settlementPrefetchRows($pdo, 'partner_settlement_data', $prepared, $partnerId);

        foreach ($prepared as $item) {
            $moneygramRow = settlementMatchPrefetchedRow($moneygramCandidates, $item);
            $settlementRow = settlementMatchPrefetchedRow($settlementCandidates, $item);
            $merged = $item;

            if (is_array($moneygramRow)) {
                foreach ($subsetFields as $field) {
                    $excelValue = $item[$field] ?? null;
                    $databaseValue = $moneygramRow[$field] ?? null;
                    if (!settlementSubsetHasValue($field, $excelValue) && settlementSubsetHasValue($field, $databaseValue)) {
                        $merged[$field] = $databaseValue;
                    }
                }
            }

            if (is_array($settlementRow)) {
                if (trim((string)($settlementRow['ufl_file_log_id'] ?? '')) === '') {
                    $linkSettlement->execute([$fileLogId, $userId, (int)$settlementRow['id']]);
                }
                $settlementPatch = [];
                foreach ($subsetFields as $field) {
                    if (!settlementSubsetHasValue($field, $settlementRow[$field] ?? null) && settlementSubsetHasValue($field, $merged[$field] ?? null)) {
                        $settlementPatch[$field] = $merged[$field];
                    }
                }
                $finalSettlementAmounts = [];
                foreach (['base_tran_amt', 'fee_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt'] as $amountField) {
                    $finalSettlementAmounts[$amountField] = array_key_exists($amountField, $settlementPatch)
                        ? $settlementPatch[$amountField]
                        : ($settlementRow[$amountField] ?? null);
                }
                $settlementPatch['total_tran_amt'] = array_sum(array_map(
                    static fn(mixed $value): float => $value === null ? 0.0 : (float)$value,
                    $finalSettlementAmounts
                ));
                $rowWasAmended = settlementPatchMissingFields(
                    $pdo,
                    'partner_settlement_data',
                    (int)$settlementRow['id'],
                    $settlementRow,
                    $settlementPatch,
                    $userId
                ) > 0;
                $currentTotal = $settlementRow['total_tran_amt'] ?? null;
                if ($currentTotal === null || abs((float)$currentTotal - (float)$settlementPatch['total_tran_amt']) >= 0.005) {
                    $totalStatement = $pdo->prepare('UPDATE partner_settlement_data SET total_tran_amt = ?, updated_at = NOW(), updated_by = ? WHERE id = ?');
                    $totalStatement->execute([$settlementPatch['total_tran_amt'], $userId, (int)$settlementRow['id']]);
                    if ($totalStatement->rowCount() > 0) $rowWasAmended = true;
                }
                if ($rowWasAmended) $settlementAmended++;
            } else {
                $merged['total_tran_amt'] = (float)($merged['base_tran_amt'] ?? 0)
                    + (float)($merged['fee_tran_amt'] ?? 0)
                    + (float)($merged['fx_rev_share_tran_amt'] ?? 0)
                    + (float)($merged['comm_tran_amt'] ?? 0);
                $insertSettlement->execute([$partnerId, $partnerName, $merged['account_number'], $merged['agent_name'], $merged['legacy_id'], $merged['tran_date'], null, $merged['transaction_id'], $merged['reference_id'], $merged['product'], $merged['tran_type'], $merged['orig_cntry'], $merged['rcv_cntry'], $merged['fx_rate_trn'], $merged['fx_date_trn'], $merged['margin'], $merged['base_tran_amt'], $merged['fee_tran_amt'], $merged['fx_rev_share_tran_amt'], $merged['comm_tran_amt'], $merged['total_tran_amt'], $merged['settlement_currency'], $merged['transaction_currency'], $fileLogId, $userId]);
                $settlementInserted++;
                $settlementCandidates[$merged['reference_id']][] = array_merge($merged, ['id' => (int)$pdo->lastInsertId(), 'partner_id' => $partnerId, 'ufl_file_log_id' => $fileLogId]);
            }

        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'settlement_inserted' => $settlementInserted,
            'settlement_updated' => $settlementAmended,
            'settlement_amended' => $settlementAmended,
        ]);
        exit;
    }

    $settlementInserted = 0;
    $settlementUpdated = 0;
    foreach ($prepared as $item) {
        $findSettlement->execute([$partnerId, $item['reference_id'], $item['tran_date'], strtolower(trim((string)($item['tran_type'] ?? '')))]);
        $settlementId = $findSettlement->fetchColumn();
        if ($settlementId !== false) {
            $updateSettlement->execute([$partnerName, $item['account_number'], $item['agent_name'], $item['legacy_id'], $item['settled_date'], $item['transaction_id'], $item['product'], $item['tran_type'], $item['orig_cntry'], $item['rcv_cntry'], $item['fx_rate_trn'], $item['fx_date_trn'], $item['margin'], $item['base_tran_amt'], $item['fee_tran_amt'], $item['fx_rev_share_tran_amt'], $item['comm_tran_amt'], $item['total_tran_amt'], $item['settlement_currency'], $item['transaction_currency'], $fileLogId, $userId, $settlementId]);
            $settlementUpdated++;
        } else {
            $insertSettlement->execute([$partnerId, $partnerName, $item['account_number'], $item['agent_name'], $item['legacy_id'], $item['tran_date'], $item['settled_date'], $item['transaction_id'], $item['reference_id'], $item['product'], $item['tran_type'], $item['orig_cntry'], $item['rcv_cntry'], $item['fx_rate_trn'], $item['fx_date_trn'], $item['margin'], $item['base_tran_amt'], $item['fee_tran_amt'], $item['fx_rev_share_tran_amt'], $item['comm_tran_amt'], $item['total_tran_amt'], $item['settlement_currency'], $item['transaction_currency'], $fileLogId, $userId]);
            $settlementInserted++;
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'settlement_inserted' => $settlementInserted, 'settlement_updated' => $settlementUpdated]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[settlement-daily-save] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $exception instanceof RuntimeException ? $exception->getMessage() : 'Unable to save MoneyGram settlement data.']);
}
