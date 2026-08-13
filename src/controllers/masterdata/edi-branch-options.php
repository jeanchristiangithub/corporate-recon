<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $status = trim((string) ($_GET['status'] ?? ''));
    $mainzone = trim((string) ($_GET['mainzone'] ?? ''));
    $zone = trim((string) ($_GET['zone'] ?? ''));
    $regionCode = trim((string) ($_GET['region'] ?? ''));

    if ($status === '') {
        echo json_encode(['success' => true, 'branches' => []]);
        exit;
    }

    $sql = "SELECT DISTINCT branch_id, branch_name
            FROM branch_profile
            WHERE branch_name IS NOT NULL
              AND TRIM(branch_name) <> ''
              AND TRIM(UPPER(ml_matic_status)) = TRIM(UPPER(?))";
    $parameters = [$status];

    if ($mainzone !== '') {
        $sql .= ' AND TRIM(UPPER(mainzone)) = TRIM(UPPER(?))';
        $parameters[] = $mainzone;
    }

    $isShowroomRegion = $zone === '' && in_array(strtoupper($regionCode), ['LZN', 'NCR', 'VIS', 'MIN'], true);
    if (strcasecmp($zone, 'Showroom') === 0 || $isShowroomRegion) {
        $sql .= " AND TRIM(UPPER(branch_type)) = 'SHOWROOM'";
        if ($regionCode !== '') {
            $sql .= ' AND TRIM(UPPER(zone)) = TRIM(UPPER(?))';
            $parameters[] = $regionCode;
        }
    } else {
        if ($zone !== '') {
            $sql .= ' AND TRIM(UPPER(zone)) = TRIM(UPPER(?))';
            $parameters[] = $zone;
        }
        if ($regionCode !== '') {
            $sql .= ' AND TRIM(UPPER(region_code)) = TRIM(UPPER(?))';
            $parameters[] = $regionCode;
        }
    }

    $sql .= ' ORDER BY branch_name';
    $statement = masterDataConnection()->prepare($sql);
    $statement->execute($parameters);

    $branches = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $branchName = trim((string) ($row['branch_name'] ?? ''));
        $branchId = trim((string) ($row['branch_id'] ?? ''));
        if ($branchName === '' || $branchId === '') {
            continue;
        }
        $branches[] = ['name' => $branchName, 'id' => $branchId];
    }

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load branch options.']);
}
