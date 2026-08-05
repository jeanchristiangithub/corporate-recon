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

$partner = reconDaycardLocksNormalizePartner((string) ($_GET['partnername'] ?? ($_GET['partner'] ?? '')));
$lockedDatesOnly = strtolower(trim((string) ($_GET['source'] ?? ''))) === 'locked_reconciliation_dates';

try {
    $pdo = reconDaycardLocksDb();
    $locked = [];

    if (reconLockedReconciliationDatesTableExists($pdo)) {
        $sql = 'SELECT corporate_partner AS partnername, transaction_date, :status AS status
                FROM locked_reconciliation_dates
                WHERE locked_at IS NOT NULL
                  AND unlocked_at IS NULL';
        $params = [':status' => 'locked'];

        if ($partner !== '') {
            $sql .= ' AND corporate_partner = :partner';
            $params[':partner'] = $partner;
        }

        $sql .= ' ORDER BY corporate_partner ASC, transaction_date ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $partnerName = reconDaycardLocksNormalizePartner((string) ($row['partnername'] ?? ''));
            $date = reconDaycardLocksNormalizeDate((string) ($row['transaction_date'] ?? ''));
            if ($partnerName === '' || $date === '') {
                continue;
            }
            $locked[$partnerName . '|' . $date] = [
                'partnername' => $partnerName,
                'transaction_date' => $date,
                'status' => 'locked',
            ];
        }
    }

    if (!$lockedDatesOnly) {
        $sqlDaycard = 'SELECT corporate_partner AS partnername, recon_date AS transaction_date, :status AS status
                   FROM recon_daycard_locks
                   WHERE is_locked = 1';
        $paramsDaycard = [':status' => 'locked'];
        if ($partner !== '') {
            $sqlDaycard .= ' AND corporate_partner = :partner';
            $paramsDaycard[':partner'] = $partner;
        }
        $sqlDaycard .= ' ORDER BY corporate_partner ASC, recon_date ASC';

        $stmtDaycard = $pdo->prepare($sqlDaycard);
        $stmtDaycard->execute($paramsDaycard);
        foreach ($stmtDaycard->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $partnerName = reconDaycardLocksNormalizePartner((string) ($row['partnername'] ?? ''));
            $date = reconDaycardLocksNormalizeDate((string) ($row['transaction_date'] ?? ''));
            if ($partnerName === '' || $date === '') {
                continue;
            }
            $locked[$partnerName . '|' . $date] = [
                'partnername' => $partnerName,
                'transaction_date' => $date,
                'status' => 'locked',
            ];
        }
    }

    $lockedDates = array_values($locked);
    usort($lockedDates, static function (array $a, array $b): int {
        $partnerCompare = strcmp((string) $a['partnername'], (string) $b['partnername']);
        if ($partnerCompare !== 0) {
            return $partnerCompare;
        }
        return strcmp((string) $a['transaction_date'], (string) $b['transaction_date']);
    });

    echo json_encode(['success' => true, 'locked_dates' => $lockedDates]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
