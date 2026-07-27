<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$logId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$logId || $logId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid uploaded file log.']);
    exit;
}

$columnsByStatus = [
    'PO' => [
        ['label' => 'CONTROL SERIES NO', 'field' => 'control_series_no'],
        ['label' => 'DATE CLAIMED', 'field' => 'date_claimed'],
        ['label' => 'KPTN', 'field' => 'kptn'],
        ['label' => 'CCREF NO', 'field' => 'ccref_no'],
        ['label' => 'CURRENCY', 'field' => 'currency'],
        ['label' => 'AMOUNT', 'field' => 'amount'],
        ['label' => 'CTC', 'field' => 'ctc'],
        ['label' => 'CTP', 'field' => 'ctp'],
        ['label' => 'SENDER NAME', 'field' => 'sender_name'],
        ['label' => 'SENDER COUNTRY', 'field' => 'sender_country'],
        ['label' => 'BENEFICIARY/RECEIVER', 'field' => 'beneficiary_receiver'],
        ['label' => 'RECEIVER KYC', 'field' => 'receiver_kyc'],
        ['label' => 'RECEIVER PHONE', 'field' => 'receiver_phone'],
        ['label' => 'OPERATOR', 'field' => 'operator'],
        ['label' => 'BRANCH', 'field' => 'branch'],
        ['label' => 'REMOTE OPERATOR', 'field' => 'remote_operator'],
        ['label' => 'REMOTE BRANCH', 'field' => 'remote_branch'],
    ],
    'SO' => [
        ['label' => 'CONTROL SERIES NO', 'field' => 'control_series_no'],
        ['label' => 'DATE SEND', 'field' => 'date_send'],
        ['label' => 'KPTN', 'field' => 'kptn'],
        ['label' => 'CCREF NO', 'field' => 'ccref_no'],
        ['label' => 'CURRENCY', 'field' => 'currency'],
        ['label' => 'AMOUNT', 'field' => 'amount'],
        ['label' => 'CHARGE', 'field' => 'charge'],
        ['label' => 'SENDER NAME', 'field' => 'sender_name'],
        ['label' => 'RECEIVER COUNTRY', 'field' => 'sender_country'],
        ['label' => 'RECEIVER NAME', 'field' => 'receiver_name'],
        ['label' => 'RECEIVER PHONE', 'field' => 'receiver_phone'],
        ['label' => 'OPERATOR', 'field' => 'operator'],
        ['label' => 'BRANCH', 'field' => 'branch'],
        ['label' => 'REMOTE OPERATOR', 'field' => 'remote_operator'],
        ['label' => 'REMOTE BRANCH', 'field' => 'remote_branch'],
    ],
    'POC' => [
        ['label' => 'CONTROL SERIES NO', 'field' => 'control_series_no'],
        ['label' => 'DATE CANCELLED', 'field' => 'date_cancelled'],
        ['label' => 'DATE CLAIMED', 'field' => 'date_send'],
        ['label' => 'KPTN', 'field' => 'kptn'],
        ['label' => 'CCREF NO', 'field' => 'ccref_no'],
        ['label' => 'CURRENCY', 'field' => 'currency'],
        ['label' => 'AMOUNT', 'field' => 'amount'],
        ['label' => 'CTC', 'field' => 'ctc'],
        ['label' => 'CTP', 'field' => 'ctp'],
        ['label' => 'SENDER NAME', 'field' => 'sender_name'],
        ['label' => 'SENDER COUNTRY', 'field' => 'sender_country'],
        ['label' => 'BENEFICIARY NAME', 'field' => 'beneficiary_receiver'],
        ['label' => 'RECEIVER NAME', 'field' => 'receiver_name'],
        ['label' => 'RECEIVER PHONE', 'field' => 'receiver_phone'],
        ['label' => 'OPERATOR', 'field' => 'operator'],
        ['label' => 'BRANCH', 'field' => 'branch'],
        ['label' => 'REMOTE OPERATOR', 'field' => 'remote_operator'],
        ['label' => 'REMOTE BRANCH', 'field' => 'remote_branch'],
        ['label' => 'OTHER DETAILS', 'field' => 'other_details'],
    ],
    'SOC' => [
        ['label' => 'CONTROL SERIES NO', 'field' => 'control_series_no'],
        ['label' => 'DATE CANCELLED', 'field' => 'date_cancelled'],
        ['label' => 'DATE SEND', 'field' => 'date_send'],
        ['label' => 'KPTN', 'field' => 'kptn'],
        ['label' => 'CCREF NO', 'field' => 'ccref_no'],
        ['label' => 'CURRENCY', 'field' => 'currency'],
        ['label' => 'AMOUNT', 'field' => 'amount'],
        ['label' => 'CHARGE', 'field' => 'charge'],
        ['label' => 'SENDER NAME', 'field' => 'sender_name'],
        ['label' => 'RECEIVER NAME', 'field' => 'receiver_name'],
        ['label' => 'RECEIVER PHONE', 'field' => 'receiver_phone'],
        ['label' => 'OPERATOR', 'field' => 'operator'],
        ['label' => 'BRANCH', 'field' => 'branch'],
        ['label' => 'REMOTE OPERATOR', 'field' => 'remote_operator'],
        ['label' => 'REMOTE BRANCH', 'field' => 'remote_branch'],
        ['label' => 'OTHER DETAILS', 'field' => 'other_details'],
    ],
    'TD' => [
        ['label' => 'TRAN DATE', 'field' => 'tran_date'],
        ['label' => 'ACCOUNT NUMBER', 'field' => 'account_number'],
        ['label' => 'AGENT NAME', 'field' => 'agent_name'],
        ['label' => 'LEGACY ID', 'field' => 'legacy_id'],
        ['label' => 'TRANSACTION ID', 'field' => 'transaction_id'],
        ['label' => 'REFERENCE ID', 'field' => 'reference_id'],
        ['label' => 'PRODUCT', 'field' => 'product'],
        ['label' => 'TRAN TYPE', 'field' => 'tran_type'],
        ['label' => 'ORIG CNTRY', 'field' => 'orig_cntry'],
        ['label' => 'RCV CNTRY', 'field' => 'rcv_cntry'],
        ['label' => 'FX RATE TRN', 'field' => 'fx_rate_trn'],
        ['label' => 'FX DATE TRN', 'field' => 'fx_date_trn'],
        ['label' => 'MARGIN', 'field' => 'margin'],
        ['label' => 'BASE TRAN AMT', 'field' => 'base_tran_amt'],
        ['label' => 'FEE TRAN AMT', 'field' => 'fee_tran_amt'],
        ['label' => 'FX REV SHARE TRAN AMT', 'field' => 'fx_rev_share_tran_amt'],
        ['label' => 'COMM TRAN AMT', 'field' => 'comm_tran_amt'],
        ['label' => 'TOTAL TRAN AMT', 'field' => 'total_tran_amt'],
        ['label' => 'SETTLEMENT CURRENCY', 'field' => 'settlement_currency'],
        ['label' => 'TRANSACTION CURRENCY', 'field' => 'transaction_currency'],
    ],
    'SD' => [
        ['label' => 'TRAN DATE', 'field' => 'tran_date'],
        ['label' => 'SETTLED DATE', 'field' => 'settled_date'],
        ['label' => 'PARTNER NAME', 'field' => 'partner_name'],
        ['label' => 'ACCOUNT NUMBER', 'field' => 'account_number'],
        ['label' => 'AGENT NAME', 'field' => 'agent_name'],
        ['label' => 'LEGACY ID', 'field' => 'legacy_id'],
        ['label' => 'TRANSACTION ID', 'field' => 'transaction_id'],
        ['label' => 'REFERENCE ID', 'field' => 'reference_id'],
        ['label' => 'PRODUCT', 'field' => 'product'],
        ['label' => 'TRAN TYPE', 'field' => 'tran_type'],
        ['label' => 'ORIG CNTRY', 'field' => 'orig_cntry'],
        ['label' => 'RCV CNTRY', 'field' => 'rcv_cntry'],
        ['label' => 'FX RATE TRN', 'field' => 'fx_rate_trn'],
        ['label' => 'BASE TRAN AMT', 'field' => 'base_tran_amt'],
        ['label' => 'FEE TRAN AMT', 'field' => 'fee_tran_amt'],
        ['label' => 'FX REV SHARE TRAN AMT', 'field' => 'fx_rev_share_tran_amt'],
        ['label' => 'COMM TRAN AMT', 'field' => 'comm_tran_amt'],
        ['label' => 'TOTAL TRAN AMT', 'field' => 'total_tran_amt'],
        ['label' => 'SETTLEMENT CURRENCY', 'field' => 'settlement_currency'],
        ['label' => 'TRANSACTION CURRENCY', 'field' => 'transaction_currency'],
    ],
];

try {
    $pdo = fileRecDbConnection();

    $logStmt = $pdo->prepare(
        'SELECT id, kpxweb_data_status FROM uploaded_file_logs WHERE id = :id LIMIT 1'
    );
    $logStmt->execute([':id' => $logId]);
    $log = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Uploaded file log not found.']);
        exit;
    }

    $statuses = array_values(array_filter(array_map(
        static fn(string $status): string => strtoupper(trim($status)),
        explode(',', (string)($log['kpxweb_data_status'] ?? ''))
    )));
    $status = $statuses[0] ?? '';

    if (!isset($columnsByStatus[$status])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'This file status has no supported record layout.']);
        exit;
    }

    $columns = $columnsByStatus[$status];
    $fields = array_values(array_unique(array_column($columns, 'field')));
    $selectFields = implode(', ', array_map(
        static fn(string $field): string => '`' . $field . '`',
        $fields
    ));
    $recordTable = match ($status) {
        'TD' => 'moneygram_partner_data',
        'SD' => 'partner_settlement_data',
        default => 'ml_web_data',
    };

    $recordStmt = $pdo->prepare(
        "SELECT {$selectFields}
         FROM {$recordTable}
         WHERE ufl_file_log_id = :log_id
         ORDER BY id ASC"
    );
    $recordStmt->execute([':log_id' => $logId]);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'columns' => $columns,
        'rows' => $recordStmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('[uploaded-file-log-details] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load the uploaded file records.']);
}
