<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function cashflowRemarksRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    cashflowRemarksRespond(401, ['success' => false, 'message' => 'Your session has expired. Please log in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cashflowRemarksRespond(405, ['success' => false, 'message' => 'Method not allowed.']);
}
verifyCsrfOrFail();

$partnerName = trim((string) ($_POST['partner'] ?? ''));
$changes = json_decode((string) ($_POST['changes'] ?? ''), true);
$userId = trim((string) ($_SESSION['user']['id_number'] ?? ''));

if ($partnerName === '' || !is_array($changes) || $changes === [] || $userId === '') {
    cashflowRemarksRespond(422, ['success' => false, 'message' => 'Partner, changed remarks, and user identity are required.']);
}
if (count($changes) > 100) {
    cashflowRemarksRespond(422, ['success' => false, 'message' => 'A maximum of 100 remarks can be saved at once.']);
}

try {
    $partnerStatement = masterDataConnection()->prepare(
        'SELECT partner_id, partner_name
         FROM corpo_partner_masterfile
         WHERE UPPER(TRIM(partner_name)) = UPPER(?)
         LIMIT 1'
    );
    $partnerStatement->execute([$partnerName]);
    $partner = $partnerStatement->fetch(PDO::FETCH_ASSOC);
    if (!$partner) {
        cashflowRemarksRespond(422, ['success' => false, 'message' => 'The selected Corporate Partner was not found.']);
    }

    $validated = [];
    $savedCount = 0;
    foreach ($changes as $change) {
        $date = trim((string) ($change['tran_date'] ?? ''));
        $remarks = trim((string) ($change['remarks'] ?? ''));
        $currency = strtolower(trim((string) ($change['currency'] ?? '')));
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('One of the transaction dates is invalid.');
        }
        if (!in_array($remarks, ['valid', 'not-valid'], true)) {
            throw new InvalidArgumentException('Remarks must be valid or not-valid.');
        }
        if (!in_array($currency, ['php', 'usd'], true)) {
            throw new InvalidArgumentException('The remarks currency must be PHP or USD.');
        }
        $validated[$date][$currency] = $remarks;
        $savedCount++;
    }

    $pdo = fileRecDbConnection();
    $pdo->beginTransaction();
    $exists = $pdo->prepare(
        'SELECT id FROM partner_cashflow_remark_tag
         WHERE partner_name = ? AND tran_date = ?
         LIMIT 1 FOR UPDATE'
    );
    $insert = $pdo->prepare(
        'INSERT INTO partner_cashflow_remark_tag
         (partner_id, partner_name, tran_date, php_remarks, usd_remarks, created_at, created_by)
         VALUES (?, ?, ?, ?, ?, NOW(), ?)'
    );

    foreach ($validated as $date => $currencyRemarks) {
        $exists->execute([(string) $partner['partner_name'], $date]);
        if ($exists->fetchColumn() !== false) {
            $setClauses = [];
            $updateParams = [];
            foreach (['php', 'usd'] as $currency) {
                if (!array_key_exists($currency, $currencyRemarks)) {
                    continue;
                }
                $setClauses[] = $currency . '_remarks = ?';
                $updateParams[] = $currencyRemarks[$currency];
            }
            $setClauses[] = 'modified_at = NOW()';
            $setClauses[] = 'modified_by = ?';
            $updateParams[] = $userId;
            $updateParams[] = (string) $partner['partner_name'];
            $updateParams[] = $date;
            $update = $pdo->prepare(
                'UPDATE partner_cashflow_remark_tag SET ' . implode(', ', $setClauses)
                . ' WHERE partner_name = ? AND tran_date = ?'
            );
            $update->execute($updateParams);
        } else {
            $insert->execute([
                (string) ($partner['partner_id'] ?? ''),
                (string) $partner['partner_name'],
                $date,
                $currencyRemarks['php'] ?? null,
                $currencyRemarks['usd'] ?? null,
                $userId,
            ]);
        }
    }
    $pdo->commit();

    cashflowRemarksRespond(200, ['success' => true, 'saved' => $savedCount]);
} catch (InvalidArgumentException $exception) {
    cashflowRemarksRespond(422, ['success' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[cashflow-remarks-save] ' . $exception->getMessage());
    cashflowRemarksRespond(500, ['success' => false, 'message' => 'Unable to save Cash Flow remarks.']);
}
