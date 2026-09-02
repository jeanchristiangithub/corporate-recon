<?php

declare(strict_types=1);

require_once __DIR__ . '/daycard-locks-common.php';
require_once __DIR__ . '/../excelcontrol/moneygram/moneygram-partner-match.php';

reconDaycardLocksBoot();
header('Content-Type: application/json; charset=utf-8');

$role = (string) ($_SESSION['user']['role'] ?? '');
if (strcasecmp($role, 'Public') !== 0 && strcasecmp($role, 'Admin') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only Public or Admin users may lock reconciliation dates.']);
    exit;
}

$payload = reconDaycardLocksReadPayload();
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partnername'] ?? ($payload['partner'] ?? '')));
$date = reconDaycardLocksNormalizeDate((string) ($payload['transaction_date'] ?? ($payload['date'] ?? '')));

if ($partner === '' || $date === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partnername or transaction_date']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    if ($partner === 'MONEYGRAM') {
        $nextDate = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
        $stmtLockable = $pdo->prepare(
            'SELECT 1
             FROM moneygram_partner_data
             WHERE tran_date >= :start_date
               AND tran_date < :end_date
             LIMIT 1'
        );
        $stmtLockable->execute([':start_date' => $date, ':end_date' => $nextDate]);
        $lockability = $stmtLockable->fetchColumn() !== false
            ? ['ok' => true]
            : ['ok' => false, 'message' => "Cannot lock this day card.\n\nNo Partner Data were found for this transaction date."];
    } else {
        $lockability = reconDaycardLocksCanLockDay($pdo, $partner, $date);
    }
    if (empty($lockability['ok'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => (string) ($lockability['message'] ?? 'This reconciliation date cannot be locked.')]);
        exit;
    }

    $lockedBy = trim((string) ($_SESSION['user']['id_number'] ?? ''));
    if ($lockedBy === '') {
        $lockedBy = reconDaycardLocksUsername();
    }
    $updated = reconLockedReconciliationDatesUpsert($pdo, $partner, [$date], $lockedBy);
    if ($partner === 'MONEYGRAM') {
        moneygramLockMatchedDates($pdo, [$date]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO recon_daycard_locks (corporate_partner, recon_date, is_locked, locked_by, locked_at, unlocked_by, unlocked_at)
         VALUES (:partner, :recon_date, 1, :locked_by, NOW(), NULL, NULL)
         ON DUPLICATE KEY UPDATE is_locked = 1, locked_by = VALUES(locked_by), locked_at = NOW(), unlocked_by = NULL, unlocked_at = NULL'
    );
    $stmt->execute([':partner' => $partner, ':recon_date' => $date, ':locked_by' => $lockedBy]);

    echo json_encode(['success' => true, 'partnername' => $partner, 'transaction_date' => $date, 'status' => 'locked', 'updated' => $updated]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
