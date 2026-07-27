<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) throw new InvalidArgumentException('Invalid settlement ID.');
    $pdo = fileRecDbConnection();
    $columns = $pdo->query('SHOW COLUMNS FROM partner_settlement_data')->fetchAll(PDO::FETCH_COLUMN);
    $accountColumn = in_array('account_number', $columns, true) ? 'account_number' : (in_array('accout_number', $columns, true) ? 'accout_number' : null);
    if ($accountColumn === null) throw new RuntimeException('The settlement account-number column was not found.');
    $stmt = $pdo->prepare('SELECT id, partner_id, partner_name, `' . $accountColumn . '` AS account_number, agent_name, legacy_id, tran_date, transaction_id, reference_id, product, tran_type, orig_cntry, rcv_cntry, fx_rate_trn, fx_date_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt, total_tran_amt, settlement_currency, transaction_currency, created_at, created_by, updated_at, updated_by FROM partner_settlement_data WHERE id = ? LIMIT 1');
    $stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Settlement details not found.']); exit; }
    $uploaderId = trim((string)($row['updated_by'] ?: $row['created_by']));
    $row['uploaded_by_name'] = '';
    if ($uploaderId !== '') {
        try {
            $userStmt = userDbConnection()->prepare(
                "SELECT CONCAT_WS(' ', NULLIF(TRIM(firstname), ''), NULLIF(TRIM(middlename), ''), NULLIF(TRIM(lastname), '')) FROM users WHERE id_number = ? LIMIT 1"
            );
            $userStmt->execute([$uploaderId]);
            $row['uploaded_by_name'] = trim((string)($userStmt->fetchColumn() ?: ''));
        } catch (Throwable $ignored) {
            $row['uploaded_by_name'] = '';
        }
    }
    echo json_encode(['success'=>true,'data'=>$row], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) { http_response_code($e instanceof InvalidArgumentException?422:500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
