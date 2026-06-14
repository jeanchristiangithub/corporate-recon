<?php
// Returns area and branch_id for a given branch_name (used by reports autofill)
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $branch = isset($_GET['branch_name']) ? trim((string)$_GET['branch_name']) : '';
    if ($branch === '') {
        echo json_encode(['success' => false, 'error' => 'Missing branch_name']);
        exit;
    }

    $pdo = masterDataConnection();
    $sql = 'SELECT area, branch_id FROM branch_profile WHERE TRIM(LOWER(branch_name)) = TRIM(LOWER(?)) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => ['area' => $row['area'] ?? '', 'branch_id' => $row['branch_id'] ?? '']]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
