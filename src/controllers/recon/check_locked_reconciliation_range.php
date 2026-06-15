<?php

declare(strict_types=1);

require_once __DIR__ . '/daycard-locks-common.php';

reconDaycardLocksBoot();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = reconDaycardLocksReadPayload();
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partnername'] ?? ($payload['partner'] ?? ($_GET['partnername'] ?? ($_GET['partner'] ?? '')))));
$startDate = reconDaycardLocksNormalizeDate((string) ($payload['start_date'] ?? ($_GET['start_date'] ?? '')));
$endDate = reconDaycardLocksNormalizeDate((string) ($payload['end_date'] ?? ($_GET['end_date'] ?? '')));

if ($partner === '' || $startDate === '' || $endDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'locked' => false, 'locked_dates' => [], 'error' => 'Missing partner or date range.']);
    exit;
}

if ($startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'locked' => false, 'locked_dates' => [], 'error' => 'Start date cannot be greater than end date.']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $locked = [];

    $stmt = $pdo->prepare(
        'SELECT recon_date
         FROM recon_daycard_locks
         WHERE corporate_partner = :partner
           AND recon_date BETWEEN :start_date AND :end_date
           AND is_locked = 1'
    );
    $stmt->execute([
        ':partner' => $partner,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $date) {
        $normalized = reconDaycardLocksNormalizeDate((string) $date);
        if ($normalized !== '') {
            $locked[$normalized] = true;
        }
    }

    if (reconLockedReconciliationDatesTableExists($pdo)) {
        $stmtLockedDates = $pdo->prepare(
            'SELECT transaction_date
             FROM locked_reconciliation_dates
             WHERE corporate_partner = :partner
               AND transaction_date BETWEEN :start_date AND :end_date
               AND locked_at IS NOT NULL
               AND unlocked_at IS NULL'
        );
        $stmtLockedDates->execute([
            ':partner' => $partner,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        foreach ($stmtLockedDates->fetchAll(PDO::FETCH_COLUMN, 0) as $date) {
            $normalized = reconDaycardLocksNormalizeDate((string) $date);
            if ($normalized !== '') {
                $locked[$normalized] = true;
            }
        }
    }

    $lockedDates = array_keys($locked);
    sort($lockedDates);

    if (!empty($lockedDates)) {
        echo json_encode([
            'success' => false,
            'locked' => true,
            'locked_dates' => $lockedDates,
            'message' => 'Selected date is already locked.',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'locked' => false, 'locked_dates' => []]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'locked' => false, 'locked_dates' => [], 'error' => $e->getMessage()]);
    exit;
}
