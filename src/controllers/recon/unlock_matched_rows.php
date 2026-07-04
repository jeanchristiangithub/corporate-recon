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
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS recon_row_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            corporate_partner VARCHAR(100) NOT NULL,
            recon_date DATE NOT NULL,
            ref VARCHAR(255) NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            locked_by VARCHAR(100) NULL,
            locked_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_row_lock (corporate_partner, recon_date, ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $reconDates = reconDaycardLocksNormalizeDateList(array_merge([$date], $dates));
    // delete matching row locks
    $chunks = array_chunk($refs, 500);
    $deleted = 0;
    foreach ($chunks as $chunk) {
        $datePlaceholders = implode(',', array_fill(0, count($reconDates), '?'));
        $refPlaceholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = 'DELETE FROM recon_row_locks WHERE corporate_partner = ? AND recon_date IN (' . $datePlaceholders . ') AND ref IN (' . $refPlaceholders . ')';
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$partner], $reconDates, array_map('strval', $chunk));
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
        'row_lock_dates' => $reconDates,
        'unlocked_dates' => $dates,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
