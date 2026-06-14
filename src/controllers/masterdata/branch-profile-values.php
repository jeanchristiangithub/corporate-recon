<?php
// Returns distinct values for branch_profile columns (used by reports autocomplete)
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $col = isset($_GET['column']) ? trim((string)$_GET['column']) : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $mainzoneFilter = isset($_GET['mainzone']) ? trim((string)$_GET['mainzone']) : '';
    $zoneFilter = isset($_GET['zone']) ? trim((string)$_GET['zone']) : '';
    $regionFilter = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    $branchNameFilter = isset($_GET['branch_name']) ? trim((string)$_GET['branch_name']) : '';

    $allowed = [
        'mainzone' => 'mainzone',
        'zone' => 'zone',
        'region' => 'region',
        'area' => 'area',
        'branch_name' => 'branch_name',
        'branch_id' => 'branch_id'
    ];

    if (!isset($allowed[$col])) {
        echo json_encode(['success' => false, 'error' => 'Invalid column']);
        exit;
    }

    $column = $allowed[$col];

    $pdo = masterDataConnection();
    // Use parameterized LIKE for case-insensitive partial match
    $sql = "SELECT DISTINCT `" . str_replace('`','', $column) . "` AS val FROM branch_profile WHERE `" . str_replace('`','', $column) . "` IS NOT NULL AND TRIM(`" . str_replace('`','', $column) . "`) <> ''";
    $params = [];
    if ($mainzoneFilter !== '') {
        // filter by mainzone exact match (case-insensitive)
        $sql .= ' AND TRIM(LOWER(`mainzone`)) = TRIM(LOWER(?))';
        $params[] = $mainzoneFilter;
    }
    if ($zoneFilter !== '') {
        $sql .= ' AND TRIM(LOWER(`zone`)) = TRIM(LOWER(?))';
        $params[] = $zoneFilter;
    }
    if ($regionFilter !== '') {
        $sql .= ' AND TRIM(LOWER(`region`)) = TRIM(LOWER(?))';
        $params[] = $regionFilter;
    }
    if ($branchNameFilter !== '') {
        $sql .= ' AND TRIM(LOWER(`branch_name`)) = TRIM(LOWER(?))';
        $params[] = $branchNameFilter;
    }
    if ($q !== '') {
        $sql .= ' AND LOWER(`' . $column . '`) LIKE ?';
        $params[] = '%' . strtolower($q) . '%';
    }
    $sql .= ' ORDER BY `' . $column . '` ASC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $vals = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $v = is_null($r) ? '' : (string)$r;
            if ($v !== '') $vals[] = $v;
        }
    }

    // remove duplicates just in case
    $vals = array_values(array_unique($vals));

    echo json_encode(['success' => true, 'values' => $vals]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
