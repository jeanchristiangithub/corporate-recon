<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function originPartnerSettlementLogsRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isAuthenticated()) {
    originPartnerSettlementLogsRespond(401, [
        'success' => false,
        'message' => 'Your session has expired. Please log in again.',
    ]);
}

$partnerName = trim((string)($_GET['partner'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));

if ($partnerName === '') {
    originPartnerSettlementLogsRespond(422, [
        'success' => false,
        'message' => 'Corporate Partner is required.',
    ]);
}
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    originPartnerSettlementLogsRespond(422, [
        'success' => false,
        'message' => 'A valid Month is required.',
    ]);
}

try {
    $startDate = new DateTimeImmutable($month . '-01');
    $endDate = $startDate->modify('first day of next month');
    $pdo = fileRecDbConnection();
    $statement = $pdo->prepare(
        'SELECT logs.id, logs.partner_name, logs.account_number, logs.agent_name, logs.legacy_id,
                logs.tran_date, logs.settled_date, logs.transaction_id, logs.reference_id,
                logs.product, logs.tran_type, logs.orig_cntry, logs.rcv_cntry, logs.fx_rate_trn,
                logs.fx_date_trn, logs.margin, logs.base_tran_amt, logs.fee_tran_amt,
                logs.fx_rev_share_tran_amt, logs.comm_tran_amt, logs.total_tran_amt,
                logs.settlement_currency, logs.transaction_currency, logs.created_at,
                logs.created_by, logs.updated_at, logs.updated_by, logs.modified_at,
                logs.modified_by,
                current.account_number AS modified_account_number,
                current.agent_name AS modified_agent_name,
                current.legacy_id AS modified_legacy_id,
                current.tran_date AS modified_tran_date,
                current.transaction_id AS modified_transaction_id,
                current.reference_id AS modified_reference_id,
                current.product AS modified_product,
                current.tran_type AS modified_tran_type,
                current.orig_cntry AS modified_orig_cntry,
                current.rcv_cntry AS modified_rcv_cntry,
                current.fx_rate_trn AS modified_fx_rate_trn,
                current.fx_date_trn AS modified_fx_date_trn,
                current.margin AS modified_margin,
                current.base_tran_amt AS modified_base_tran_amt,
                current.fee_tran_amt AS modified_fee_tran_amt,
                current.fx_rev_share_tran_amt AS modified_fx_rev_share_tran_amt,
                current.comm_tran_amt AS modified_comm_tran_amt,
                current.total_tran_amt AS modified_total_tran_amt,
                current.settlement_currency AS modified_settlement_currency,
                current.transaction_currency AS modified_transaction_currency,
                current.created_at AS modified_created_at,
                current.created_by AS modified_created_by,
                current.updated_at AS modified_updated_at,
                current.updated_by AS modified_updated_by,
                current.modified_at AS modified_modified_at,
                current.modified_by AS modified_modified_by
         FROM origin_partner_settlement_datarows_logs logs
         LEFT JOIN partner_settlement_data current
           ON current.id = logs.psd_datarows_id
         WHERE logs.partner_name = ?
           AND (
                (logs.tran_date >= ? AND logs.tran_date < ?)
                OR
                (logs.settled_date >= ? AND logs.settled_date < ?)
           )
         ORDER BY logs.id DESC'
    );
    $statement->execute([
        $partnerName,
        $startDate->format('Y-m-d'),
        $endDate->format('Y-m-d'),
        $startDate->format('Y-m-d'),
        $endDate->format('Y-m-d'),
    ]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $userFields = [
        'created_by',
        'updated_by',
        'modified_by',
        'modified_created_by',
        'modified_updated_by',
        'modified_modified_by',
    ];
    $userIds = [];
    foreach ($rows as $row) {
        foreach ($userFields as $field) {
            $userId = trim((string)($row[$field] ?? ''));
            if ($userId !== '') {
                $userIds[$userId] = true;
            }
        }
    }

    if ($userIds !== []) {
        $ids = array_keys($userIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $userStatement = $pdo->prepare(
            "SELECT id_number,
                    TRIM(CONCAT_WS(' ',
                        NULLIF(TRIM(firstname), ''),
                        NULLIF(TRIM(middlename), ''),
                        NULLIF(TRIM(lastname), '')
                    )) AS full_name
             FROM users
             WHERE id_number IN ($placeholders)"
        );
        $userStatement->execute($ids);
        $userNames = [];
        foreach ($userStatement->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $idNumber = trim((string)($user['id_number'] ?? ''));
            $fullName = trim((string)($user['full_name'] ?? ''));
            if ($idNumber !== '' && $fullName !== '') {
                $userNames[$idNumber] = $fullName;
            }
        }

        foreach ($rows as &$row) {
            foreach ($userFields as $field) {
                $userId = trim((string)($row[$field] ?? ''));
                if ($userId !== '' && isset($userNames[$userId])) {
                    $row[$field] = $userNames[$userId];
                }
            }
        }
        unset($row);
    }

    originPartnerSettlementLogsRespond(200, [
        'success' => true,
        'rows' => $rows,
    ]);
} catch (Throwable $exception) {
    error_log('Unable to load original partner settlement logs: ' . $exception->getMessage());
    originPartnerSettlementLogsRespond(500, [
        'success' => false,
        'message' => 'Unable to load original settlement records.',
    ]);
}
