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

if (!reconDaycardLocksIsAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = reconDaycardLocksReadPayload();
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partner'] ?? ''));
$date = reconDaycardLocksNormalizeDate((string) ($payload['date'] ?? ''));

if ($partner === '' || $date === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partner or date']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $lockability = reconDaycardLocksCanLockDay($pdo, $partner, $date);
    if (empty($lockability['ok'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'locked' => false,
            'error' => (string) ($lockability['message'] ?? 'Cannot lock empty day card.'),
            'errorCode' => 'daycard_not_lockable',
        ]);
        exit;
    }

    $sql = "INSERT INTO recon_daycard_locks (
                corporate_partner, recon_date, is_locked, locked_by, locked_at, unlocked_by, unlocked_at
            ) VALUES (
                :partner, :recon_date, 1, :locked_by, NOW(), NULL, NULL
            )
            ON DUPLICATE KEY UPDATE
                is_locked = 1,
                locked_by = VALUES(locked_by),
                locked_at = VALUES(locked_at),
                unlocked_by = NULL,
                unlocked_at = NULL";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':partner' => $partner,
        ':recon_date' => $date,
        ':locked_by' => reconDaycardLocksUsername(),
    ]);

    echo json_encode(['success' => true, 'partner' => $partner, 'date' => $date, 'is_locked' => 1]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
