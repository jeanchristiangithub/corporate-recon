<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $mainzone = trim((string) ($_GET['mainzone'] ?? ''));
    $zone = trim((string) ($_GET['zone'] ?? ''));

    if ($mainzone === '') {
        echo json_encode(['success' => true, 'regions' => []]);
        exit;
    }

    $sql = 'SELECT DISTINCT region_description, region_code
         FROM region_masterfile
         WHERE TRIM(UPPER(mainzone)) = TRIM(UPPER(?))
           AND region_description IS NOT NULL
           AND TRIM(region_description) <> \'\'';
    $parameters = [$mainzone];

    if ($zone !== '') {
        $sql .= ' AND TRIM(UPPER(zone_code)) = TRIM(UPPER(?))';
        $parameters[] = $zone;
    } else {
        $sql .= " AND UPPER(TRIM(COALESCE(region_code, ''))) NOT IN
            ('MANCOMM1', 'MANCOMM2', 'VISMINSUP', 'LNCRSUP')";
    }

    $sql .= ' ORDER BY region_description';
    $statement = masterDataConnection()->prepare($sql);
    $statement->execute($parameters);

    $regions = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $description = trim((string) ($row['region_description'] ?? ''));
        if ($description === '') {
            continue;
        }

        $regions[] = [
            'description' => $description,
            'code' => trim((string) ($row['region_code'] ?? '')),
        ];
    }

    echo json_encode(['success' => true, 'regions' => $regions]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load region options.']);
}
