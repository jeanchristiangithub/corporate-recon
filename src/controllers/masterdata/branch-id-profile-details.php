<?php
// Returns branch_name, area, region, zone, mainzone for a given branch_id (used by reports autofill)
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $branchId = isset($_GET['branch_id']) ? trim((string)$_GET['branch_id']) : '';
    if ($branchId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing branch_id']);
        exit;
    }

    $pdo = masterDataConnection();
    $sql = 'SELECT branch_name, area, region, zone, mainzone FROM branch_profile WHERE TRIM(branch_id) = TRIM(?) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    echo json_encode(['success' => true, 'data' => [
        'branch_name' => $row['branch_name'] ?? '',
        'area' => $row['area'] ?? '',
        'region' => $row['region'] ?? '',
        'zone' => $row['zone'] ?? '',
        'mainzone' => $row['mainzone'] ?? ''
    ]]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
