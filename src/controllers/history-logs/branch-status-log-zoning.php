<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function branchStatusLogZoningRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    branchStatusLogZoningRespond(401, [
        'success' => false,
        'error' => 'Your session has expired. Please log in again.',
    ]);
}

try {
    $connection = fileRecDbConnection();

    $mainzones = $connection->query(
        "SELECT DISTINCT TRIM(mbp_mainzone) AS value
         FROM filerecondb.corporate_branch_status_history
         WHERE NULLIF(TRIM(mbp_mainzone), '') IS NOT NULL
         ORDER BY value"
    )->fetchAll(PDO::FETCH_COLUMN);

    $zones = $connection->query(
        "SELECT DISTINCT
            TRIM(mbp_mainzone) AS mainzone,
            TRIM(mbp_zone) AS value
         FROM filerecondb.corporate_branch_status_history
         WHERE NULLIF(TRIM(mbp_mainzone), '') IS NOT NULL
           AND NULLIF(TRIM(mbp_zone), '') IS NOT NULL
         ORDER BY mainzone, value"
    )->fetchAll(PDO::FETCH_ASSOC);

    $regions = $connection->query(
        "WITH ranked_regions AS (
            SELECT
                TRIM(mbp_mainzone) AS mainzone,
                TRIM(mbp_zone) AS zone,
                TRIM(mbp_region_code) AS value,
                TRIM(mrm_region_description) AS label,
                ROW_NUMBER() OVER (
                    PARTITION BY TRIM(mbp_mainzone), TRIM(mbp_zone), TRIM(mbp_region_code)
                    ORDER BY posted_at DESC, id DESC
                ) AS region_rank
            FROM filerecondb.corporate_branch_status_history
            WHERE NULLIF(TRIM(mbp_mainzone), '') IS NOT NULL
              AND NULLIF(TRIM(mbp_zone), '') IS NOT NULL
              AND NULLIF(TRIM(mbp_region_code), '') IS NOT NULL
              AND NULLIF(TRIM(mrm_region_description), '') IS NOT NULL
         )
         SELECT mainzone, zone, value, label
         FROM ranked_regions
         WHERE region_rank = 1
         ORDER BY mainzone, zone, label, value"
    )->fetchAll(PDO::FETCH_ASSOC);

    branchStatusLogZoningRespond(200, [
        'success' => true,
        'mainzones' => $mainzones,
        'zones' => $zones,
        'regions' => $regions,
    ]);
} catch (Throwable $exception) {
    branchStatusLogZoningRespond(500, [
        'success' => false,
        'error' => 'Unable to load branch zoning filters.',
    ]);
}
