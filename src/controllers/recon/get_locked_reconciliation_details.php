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
$transactionDate = reconDaycardLocksNormalizeDate((string) ($payload['transaction_date'] ?? ($payload['date'] ?? ($_GET['transaction_date'] ?? ($_GET['date'] ?? '')))));

if ($partner === '' || $transactionDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partnername or transaction_date']);
    exit;
}

$wicPartners = ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS'];
$mbtcPartners = ['MBTC', 'METROBANK HEAD OFFICE'];
if ($partner !== 'MONEYGRAM' && !in_array($partner, $wicPartners, true) && !in_array($partner, $mbtcPartners, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Locked details view is currently available for MONEYGRAM, WORLDCOM INTERNATIONAL COMMUNICATIONS, and METROBANK HEAD OFFICE only.']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $locked = false;

    if (reconLockedReconciliationDatesTableExists($pdo)) {
        $stmtLockedDates = $pdo->prepare(
            'SELECT COUNT(*)
             FROM locked_reconciliation_dates
             WHERE corporate_partner = :partner
               AND transaction_date = :transaction_date
               AND locked_at IS NOT NULL
               AND unlocked_at IS NULL'
        );
        $stmtLockedDates->execute([
            ':partner' => $partner,
            ':transaction_date' => $transactionDate,
        ]);
        $locked = ((int) $stmtLockedDates->fetchColumn()) > 0;
    }

    if (!$locked) {
        $stmtDaycard = $pdo->prepare(
            'SELECT COUNT(*)
             FROM recon_daycard_locks
             WHERE corporate_partner = :partner
               AND recon_date = :recon_date
               AND is_locked = 1'
        );
        $stmtDaycard->execute([
            ':partner' => $partner,
            ':recon_date' => $transactionDate,
        ]);
        $locked = ((int) $stmtDaycard->fetchColumn()) > 0;
    }

    if (!$locked) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Locked reconciliation date not found.']);
        exit;
    }

    // The details payload is read-only because its active lock was verified above.
    // Expose that server decision explicitly so the client never offers row unlocking here.
    header('X-Reconciliation-Locked-View: 1');

    if ($partner === 'MONEYGRAM') {
        $_GET = [
            'start_date' => $transactionDate,
            'end_date' => $transactionDate,
            'partnerName' => $partner,
            'detail' => '1',
            'range_detail' => '1',
            'maintenance_reference_lookup' => '1',
        ];

        require __DIR__ . '/moneygram-recon.php';
        exit;
    }

    if (in_array($partner, $mbtcPartners, true)) {
        $_GET = [
            'start_date' => $transactionDate,
            'end_date' => $transactionDate,
            'date' => $transactionDate,
            'day' => (string) ((int) substr($transactionDate, 8, 2)),
            'partnerName' => $partner,
            'detail' => '1',
        ];

        require __DIR__ . '/mbtc-recon.php';
        exit;
    }

    $dateParts = explode('-', $transactionDate);
    $_GET = [
        'month' => $dateParts[1] ?? '',
        'year' => $dateParts[0] ?? '',
        'day' => (string) ((int) ($dateParts[2] ?? 0)),
        'partnerName' => $partner,
        'detail' => '1',
    ];

    require __DIR__ . '/wic-recon.php';
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
