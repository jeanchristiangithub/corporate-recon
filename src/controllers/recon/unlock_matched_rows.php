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

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$partner = reconDaycardLocksNormalizePartner((string) ($payload['partner'] ?? ''));
$date = reconDaycardLocksNormalizeDate((string) ($payload['date'] ?? ''));
$refs = is_array($payload['refs']) ? $payload['refs'] : [];
$dates = isset($payload['dates']) && is_array($payload['dates']) ? $payload['dates'] : [];
$dates = reconDaycardLocksNormalizeDateList($dates);

if ($partner === '' || $date === '' || empty($refs) || empty($dates)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partner, date, refs, or matched dates']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    // delete matching row locks
    $chunks = array_chunk($refs, 500);
    $deleted = 0;
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = 'DELETE FROM recon_row_locks WHERE corporate_partner = ? AND recon_date = ? AND ref IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$partner, $date], array_map('strval', $chunk));
        $stmt->execute($params);
        $deleted += $stmt->rowCount();
    }

    if (!empty($dates)) {
        try {
            $unlockedBy = reconDaycardLocksUsername();
            reconLockedReconciliationDatesUnlock($pdo, $partner, $dates, $unlockedBy);
        } catch (Throwable $e) {
            // Keep row-unlock operation successful even if optional date table is unavailable.
        }
    }

    echo json_encode([
        'success' => true,
        'partner' => $partner,
        'date' => $date,
        'deleted' => $deleted,
        'unlocked_dates' => $dates,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
