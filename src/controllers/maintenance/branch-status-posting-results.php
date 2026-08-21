<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');
bootSecureSession();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (strcasecmp(trim((string) ($_SESSION['user']['role'] ?? '')), 'Admin') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

try {
    $statement = masterDataConnection()->query(
        "SELECT
            branch_id,
            code AS branch_code,
            COALESCE(NULLIF(TRIM(ml_matic_branch_name), ''), NULLIF(TRIM(branch_name), ''), '') AS branch_name,
            area,
            gl_region AS region_description,
            ml_matic_status AS status
         FROM branch_profile
         ORDER BY branch_name, branch_id"
    );

    echo json_encode([
        'success' => true,
        'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load branch status records.']);
}
