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

$partner = reconDaycardLocksNormalizePartner((string) ($_GET['partner'] ?? ''));
$startDate = reconDaycardLocksNormalizeDate((string) ($_GET['start_date'] ?? ''));
$endDate = reconDaycardLocksNormalizeDate((string) ($_GET['end_date'] ?? ''));

if ($partner === '' || $startDate === '' || $endDate === '') {
    echo json_encode(['success' => true, 'locks' => []]);
    exit;
}

if ($startDate > $endDate) {
    echo json_encode(['success' => true, 'locks' => []]);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $stmt = $pdo->prepare(
        'SELECT corporate_partner, recon_date, is_locked, locked_by, locked_at, unlocked_by, unlocked_at
         FROM recon_daycard_locks
         WHERE corporate_partner = :partner
           AND recon_date BETWEEN :start_date AND :end_date
         ORDER BY recon_date ASC'
    );
    $stmt->execute([
        ':partner' => $partner,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    echo json_encode(['success' => true, 'locks' => $stmt->fetchAll()]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
