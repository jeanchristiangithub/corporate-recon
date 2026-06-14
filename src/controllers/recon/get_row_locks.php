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
$date = reconDaycardLocksNormalizeDate((string) ($_GET['date'] ?? ''));

if ($partner === '' || $date === '') {
    echo json_encode(['success' => true, 'locks' => []]);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    // ensure table exists (no-op if already present)
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

    $sql = 'SELECT ref FROM recon_row_locks WHERE corporate_partner = :partner AND recon_date = :recon_date AND is_locked = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':partner' => $partner, ':recon_date' => $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    echo json_encode(['success' => true, 'locks' => array_values($rows)]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
