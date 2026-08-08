<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$partner = trim((string)($_GET['partner'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));

if ($partner === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Corporate Partner is required.']);
    exit;
}

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid month is required.']);
    exit;
}

try {
    $startDate = new DateTimeImmutable($month . '-01');
    $endDate = $startDate->modify('first day of next month');
    $pdo = fileRecDbConnection();

    $availableColumns = $pdo->query('SHOW COLUMNS FROM partner_settlement_data')->fetchAll(PDO::FETCH_COLUMN);
    $accountColumn = in_array('account_number', $availableColumns, true)
        ? 'account_number'
        : (in_array('accout_number', $availableColumns, true) ? 'accout_number' : null);

    if ($accountColumn === null) {
        throw new RuntimeException('The settlement account-number column was not found.');
    }

    $columns = [
        $accountColumn => 'account_number',
        'agent_name' => 'agent_name',
        'legacy_id' => 'legacy_id',
        'tran_date' => 'tran_date',
        'transaction_id' => 'transaction_id',
        'reference_id' => 'reference_id',
        'product' => 'product',
        'tran_type' => 'tran_type',
        'orig_cntry' => 'orig_cntry',
        'rcv_cntry' => 'rcv_cntry',
        'fx_rate_trn' => 'fx_rate_trn',
        'fx_date_trn' => 'fx_date_trn',
        'margin' => 'margin',
        'base_tran_amt' => 'base_tran_amt',
        'fee_tran_amt' => 'fee_tran_amt',
        'fx_rev_share_tran_amt' => 'fx_rev_share_tran_amt',
        'comm_tran_amt' => 'comm_tran_amt',
        'total_tran_amt' => 'total_tran_amt',
        'settlement_currency' => 'settlement_currency',
        'transaction_currency' => 'transaction_currency',
    ];

    foreach (array_keys($columns) as $column) {
        if (!in_array($column, $availableColumns, true)) {
            throw new RuntimeException('Required settlement column not found: ' . $column);
        }
    }

    $selectColumns = ['psd.`id`'];
    $emptyConditions = [];
    foreach ($columns as $column => $alias) {
        $selectColumns[] = sprintf('psd.`%s` AS `%s`', $column, $alias);
        $emptyConditions[] = sprintf("psd.`%s` IS NULL OR TRIM(CAST(psd.`%s` AS CHAR)) = ''", $column, $column);
    }
    $selectColumns[] = 'psd.`partner_name`';
    $selectColumns[] = 'psd.`settled_date`';
    $selectColumns[] = 'psd.`created_at`';
    $selectColumns[] = 'psd.`created_by`';
    $selectColumns[] = 'psd.`updated_at`';
    $selectColumns[] = 'psd.`updated_by`';
    $selectColumns[] = 'psd.`modified_at`';
    $selectColumns[] = 'psd.`modified_by`';
    $selectColumns[] = 'ufl.`filename` AS `upload_filename`';
    $selectColumns[] = 'ufl.`uploaded_date`';
    $selectColumns[] = 'ufl.`has_overwrite`';
    $activityUserExpression = "CASE
        WHEN psd.modified_at IS NOT NULL AND NULLIF(TRIM(psd.modified_by), '') IS NOT NULL THEN psd.modified_by
        WHEN COALESCE(ufl.has_overwrite, 0) = 1 THEN psd.updated_by
        ELSE psd.created_by
    END";
    $selectColumns[] = $activityUserExpression . ' AS `activity_user_id`';
    $selectColumns[] = "TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.firstname), ''), NULLIF(TRIM(u.middlename), ''), NULLIF(TRIM(u.lastname), ''))) AS `uploaded_by_name`";

    $sql = sprintf(
        "SELECT %s
         FROM partner_settlement_data psd
         LEFT JOIN uploaded_file_logs ufl ON ufl.id = psd.ufl_file_log_id
         LEFT JOIN users u
           ON CONVERT(u.id_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            = CONVERT(" . $activityUserExpression . " USING utf8mb4) COLLATE utf8mb4_unicode_ci
         WHERE psd.partner_name = :partner
           AND (
                (psd.settled_date >= :start_date AND psd.settled_date < :end_date)
                OR (
                    psd.settled_date IS NULL
                    AND (psd.reference_id IS NULL OR TRIM(psd.reference_id) = '')
                    AND (psd.tran_type IS NULL OR TRIM(psd.tran_type) = '')
                    AND psd.tran_date >= :fallback_start_date
                    AND psd.tran_date < :fallback_end_date
                )
           )
           AND (
                (%s)
                OR (
                    (psd.reference_id IS NULL OR TRIM(psd.reference_id) = '')
                    AND (psd.tran_type IS NULL OR TRIM(psd.tran_type) = '')
                )
                OR (
                    psd.modified_at IS NOT NULL
                    AND TRIM(CAST(psd.modified_at AS CHAR)) <> ''
                    AND psd.modified_by IS NOT NULL
                    AND TRIM(psd.modified_by) <> ''
                )
           )
         ORDER BY psd.tran_date ASC, psd.reference_id ASC, psd.transaction_id ASC, psd.id ASC",
        implode(', ', $selectColumns),
        implode(' OR ', $emptyConditions)
    );

    $statement = $pdo->prepare($sql);
    $statement->execute([
        ':partner' => $partner,
        ':start_date' => $startDate->format('Y-m-d'),
        ':end_date' => $endDate->format('Y-m-d'),
        ':fallback_start_date' => $startDate->format('Y-m-d'),
        ':fallback_end_date' => $endDate->format('Y-m-d'),
    ]);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'count' => count($rows),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load settlement details.',
    ]);
}
