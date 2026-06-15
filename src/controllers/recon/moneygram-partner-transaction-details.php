<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid transaction ID.']);
        exit;
    }

    $pdo = fileRecDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM moneygram_partner_data WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Transaction details not found.']);
        exit;
    }

    $priorityFields = [
        'id',
        'account_number',
        'agent_name',
        'legacy_id',
        'branch_id',
        'tran_date',
        'transaction_date',
        'transaction_id',
        'reference_id',
        'product',
        'tran_type',
        'orig_cntry',
        'rcv_cntry',
        'base_tran_amt',
        'fee_tran_amt',
        'comm_tran_amt',
        'total_tran_amt',
        'settlement_currency',
        'transaction_currency',
    ];

    $data = [];
    foreach ($priorityFields as $field) {
        $data[$field] = array_key_exists($field, $row) ? $row[$field] : '';
    }
    foreach ($row as $field => $value) {
        if (!array_key_exists((string)$field, $data)) {
            $data[(string)$field] = $value;
        }
    }

    echo json_encode(['success' => true, 'data' => $data]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load transaction details.']);
    exit;
}
