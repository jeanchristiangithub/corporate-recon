<?php
// Returns zone and mainzone for a given region (used by reports autofill)
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $region = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    if ($region === '') {
        echo json_encode(['success' => false, 'error' => 'Missing region']);
        exit;
    }

    $pdo = masterDataConnection();
    $sql = 'SELECT zone, mainzone FROM branch_profile WHERE TRIM(LOWER(region)) = TRIM(LOWER(?)) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$region]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => ['zone' => $row['zone'] ?? '', 'mainzone' => $row['mainzone'] ?? '']]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
