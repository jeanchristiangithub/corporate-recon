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
$partnerRaw = (string) ($payload['partner'] ?? ($_GET['partner'] ?? ''));
$datesRaw = isset($payload['dates']) && is_array($payload['dates']) ? $payload['dates'] : [];
if (empty($datesRaw) && !empty($_GET['date'])) {
    $datesRaw[] = (string) $_GET['date'];
}

$partner = reconDaycardLocksNormalizePartner($partnerRaw);
$dates = reconDaycardLocksNormalizeDateList($datesRaw);

if ($partner === '' || empty($dates)) {
    echo json_encode(['success' => true, 'has_active_locks' => false, 'active_locked_dates' => []]);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $activeLocked = [];
        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $sql = 'SELECT transaction_date FROM locked_reconciliation_dates
                        WHERE corporate_partner = ?
                            AND transaction_date IN (' . $placeholders . ')
                            AND locked_at IS NOT NULL
                            AND unlocked_at IS NULL
                        ORDER BY transaction_date ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$partner], $dates));
        $activeLocked = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo json_encode([
        'success' => true,
        'has_active_locks' => !empty($activeLocked),
        'active_locked_dates' => $activeLocked,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
