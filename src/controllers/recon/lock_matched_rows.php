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
    // ensure table exists
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

    $sql = "INSERT INTO recon_row_locks (corporate_partner, recon_date, ref, is_locked, locked_by, locked_at)
            VALUES (:partner, :recon_date, :ref, 1, :locked_by, NOW())
            ON DUPLICATE KEY UPDATE is_locked = VALUES(is_locked), locked_by = VALUES(locked_by), locked_at = VALUES(locked_at)";

    $stmt = $pdo->prepare($sql);
    $lockedBy = trim((string) ($_SESSION['user']['id_number'] ?? ''));
    if ($lockedBy === '') {
        $lockedBy = reconDaycardLocksUsername();
    }

    $pdo->beginTransaction();
    foreach ($refs as $r) {
        $ref = trim((string) $r);
        if ($ref === '') continue;
        $stmt->execute([
            ':partner' => $partner,
            ':recon_date' => $date,
            ':ref' => $ref,
            ':locked_by' => $lockedBy,
        ]);
    }
    $pdo->commit();

    try {
        reconLockedReconciliationDatesUpsert($pdo, $partner, $dates, $lockedBy);
        if ($partner === 'MONEYGRAM') {
            moneygramLockMatchedDates($pdo, $dates);
        }
    } catch (Throwable $e) {
        // Keep row-lock operation successful even if optional date table is unavailable.
    }

    echo json_encode([
        'success' => true,
        'partner' => $partner,
        'date' => $date,
        'locked' => count($refs),
        'locked_dates' => $dates,
    ]);
    exit;
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
