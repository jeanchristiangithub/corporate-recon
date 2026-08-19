<?php

declare(strict_types=1);

require_once __DIR__ . '/daycard-locks-common.php';
require_once __DIR__ . '/../excelcontrol/moneygram/moneygram-partner-match.php';

reconDaycardLocksBoot();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!reconDaycardLocksIsAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = reconDaycardLocksReadPayload();
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partnername'] ?? ($payload['partner'] ?? '')));
$transactionDate = reconDaycardLocksNormalizeDate((string) ($payload['transaction_date'] ?? ($payload['date'] ?? '')));

if ($partner === '' || $transactionDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partnername or transaction_date']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $unlockedBy = trim((string) ($_SESSION['user']['id_number'] ?? ''));
    if ($unlockedBy === '') {
        $unlockedBy = reconDaycardLocksUsername();
    }
    $updated = 0;

    if (reconLockedReconciliationDatesTableExists($pdo)) {
        $updated += reconLockedReconciliationDatesUnlock($pdo, $partner, [$transactionDate], $unlockedBy);
        if ($partner === 'MONEYGRAM') {
            moneygramUnlockMatchedDates(
                $pdo,
                [$transactionDate],
                $unlockedBy
            );
        }
    }

    $stmtDaycard = $pdo->prepare(
        'UPDATE recon_daycard_locks
         SET is_locked = 0,
             unlocked_by = :unlocked_by,
             unlocked_at = NOW(),
             updated_at = CURRENT_TIMESTAMP
         WHERE corporate_partner = :partner
           AND recon_date = :recon_date
           AND is_locked = 1'
    );
    $stmtDaycard->execute([
        ':unlocked_by' => $unlockedBy,
        ':partner' => $partner,
        ':recon_date' => $transactionDate,
    ]);
    $updated += $stmtDaycard->rowCount();

    try {
        $stmtRows = $pdo->prepare(
            'DELETE FROM recon_row_locks
             WHERE corporate_partner = :partner
               AND recon_date = :recon_date'
        );
        $stmtRows->execute([
            ':partner' => $partner,
            ':recon_date' => $transactionDate,
        ]);
    } catch (Throwable $e) {
        // Row locks are optional and may not exist in older installs.
    }

    echo json_encode([
        'success' => true,
        'partnername' => $partner,
        'transaction_date' => $transactionDate,
        'status' => 'unlocked',
        'updated' => $updated,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
