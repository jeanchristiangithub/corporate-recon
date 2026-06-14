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

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partner'] ?? ''));
$dates = isset($payload['dates']) && is_array($payload['dates']) ? $payload['dates'] : [];
$normalizedDates = reconDaycardLocksNormalizeDateList($dates);

if ($partner === '' || empty($normalizedDates)) {
    echo json_encode(['success' => true, 'blocked' => false, 'locked_dates' => []]);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $lockedDates = reconDaycardLocksFindLockedDates($pdo, $partner, $normalizedDates);

    echo json_encode([
        'success' => true,
        'blocked' => !empty($lockedDates),
        'locked_dates' => $lockedDates,
        'message' => !empty($lockedDates)
            ? 'Upload Blocked. Some transaction dates are already locked by reconciliation.'
            : '',
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
