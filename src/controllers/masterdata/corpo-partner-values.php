<?php
// Returns distinct partner_name values for reports autocomplete.
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    $pdo = masterDataConnection();

    $sql = 'SELECT DISTINCT partner_name AS val FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND TRIM(partner_name) <> ""';
    $params = [];

    if ($q !== '') {
        $sql .= ' AND LOWER(partner_name) LIKE ?';
        $params[] = '%' . strtolower($q) . '%';
    }

    $sql .= ' ORDER BY partner_name ASC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $values = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $v = is_null($row) ? '' : trim((string)$row);
            if ($v !== '') {
                $values[] = $v;
            }
        }
    }

    $values = array_values(array_unique($values));

    echo json_encode(['success' => true, 'values' => $values]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
