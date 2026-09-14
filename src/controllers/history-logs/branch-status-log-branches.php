<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function branchStatusLogBranchesRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    branchStatusLogBranchesRespond(401, [
        'success' => false,
        'error' => 'Your session has expired. Please log in again.',
    ]);
}

$query = trim((string) ($_GET['q'] ?? ''));
$mainzoneFilter = trim((string) ($_GET['mainzone'] ?? ''));
$zoneFilter = trim((string) ($_GET['zone'] ?? ''));
$regionCodeFilter = trim((string) ($_GET['region_code'] ?? ''));
$showAll = filter_var($_GET['all'] ?? false, FILTER_VALIDATE_BOOL);
if (mb_strlen($query) > 150 || mb_strlen($mainzoneFilter) > 100
    || mb_strlen($zoneFilter) > 100 || mb_strlen($regionCodeFilter) > 100) {
    branchStatusLogBranchesRespond(422, [
        'success' => false,
        'error' => 'The search value is too long.',
    ]);
}

try {
    $connection = fileRecDbConnection();
    $sql = "WITH normalized_branches AS (
                SELECT
                    COALESCE(TRIM(mbp_branch_id), '') AS branch_id,
                    COALESCE(
                        NULLIF(TRIM(mbp_mlmatic_branch_name), ''),
                        NULLIF(TRIM(mkpxbm_branch_name), ''),
                        NULLIF(TRIM(mbp_branch_name_description), ''),
                        ''
                    ) AS branch_name,
                    TRIM(mbp_code) AS bos_code,
                    TRIM(mbp_mlmatic_status) AS branch_status,
                    TRIM(mbp_corporate_name) AS corporate_name,
                    TRIM(mbp_mainzone) AS mainzone,
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ',
                            NULLIF(TRIM(u.firstname), ''),
                            NULLIF(TRIM(u.middlename), ''),
                            NULLIF(TRIM(u.lastname), '')
                        )), ''),
                        NULLIF(TRIM(h.posted_by), ''),
                        ''
                    ) AS posted_by,
                    TRIM(mbp_zone) AS zone,
                    COALESCE(TRIM(mbp_region_code), '') AS region_code,
                    COALESCE(
                        NULLIF(TRIM(mrm_region_description), ''),
                        NULLIF(TRIM(mbp_gl_region), ''),
                        NULLIF(TRIM(mbp_region_code), ''),
                        ''
                    ) AS region_name_1,
                    COALESCE(
                        NULLIF(TRIM(mbp_mlmatic_region), ''),
                        NULLIF(TRIM(mrm_region_description), ''),
                        NULLIF(TRIM(mbp_gl_region), ''),
                        NULLIF(TRIM(mbp_region_code), ''),
                        ''
                    ) AS region_name_2,
                    TRIM(mbp_area) AS area,
                    posted_at
                FROM filerecondb.corporate_branch_status_history h
                LEFT JOIN filerecondb.users u
                  ON TRIM(u.id_number) COLLATE utf8mb4_unicode_ci
                     = TRIM(h.posted_by) COLLATE utf8mb4_unicode_ci
            ), merged_branches AS (
                SELECT
                    branch_id,
                    branch_name,
                    bos_code,
                    branch_status,
                    corporate_name,
                    mainzone,
                    posted_by,
                    zone,
                    region_code,
                    region_name_1,
                    region_name_2,
                    area,
                    posted_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY branch_id, branch_name
                        ORDER BY posted_at DESC
                    ) AS pair_rank
                FROM normalized_branches
                WHERE branch_name <> ''
            ), ranked_branches AS (
                SELECT
                    branch_id,
                    branch_name,
                    bos_code,
                    branch_status,
                    corporate_name,
                    mainzone,
                    posted_by,
                    zone,
                    region_code,
                    region_name_1,
                    region_name_2,
                    area,
                    posted_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY CASE
                            WHEN branch_id = '' THEN CONCAT('NAME:', UPPER(branch_name))
                            ELSE CONCAT('ID:', UPPER(branch_id))
                        END
                        ORDER BY posted_at DESC, branch_name ASC
                    ) AS branch_id_rank,
                    ROW_NUMBER() OVER (
                        PARTITION BY branch_name
                        ORDER BY posted_at DESC, branch_id ASC
                    ) AS branch_name_rank
                FROM merged_branches
                WHERE pair_rank = 1
            )
            SELECT
                branch_id,
                branch_name,
                bos_code,
                branch_status,
                corporate_name,
                mainzone,
                posted_by,
                zone,
                region_code,
                region_name_1,
                region_name_2,
                area,
                posted_at
            FROM ranked_branches
            WHERE branch_id_rank = 1
              AND branch_name_rank = 1";
    $parameters = [];

    if ($query !== '') {
        $sql .= ' AND (branch_id LIKE ? OR branch_name LIKE ?)';
        $searchValue = '%' . $query . '%';
        $parameters = [$searchValue, $searchValue];
    }

    if ($mainzoneFilter !== '') {
        $sql .= ' AND mainzone = ?';
        $parameters[] = $mainzoneFilter;
    }
    if ($zoneFilter !== '') {
        $sql .= ' AND zone = ?';
        $parameters[] = $zoneFilter;
    }
    if ($regionCodeFilter !== '') {
        $sql .= ' AND region_code = ?';
        $parameters[] = $regionCodeFilter;
    }

    $sql .= ' ORDER BY branch_name ASC, branch_id ASC';
    if (!$showAll) {
        $sql .= ' LIMIT 100';
    }
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);

    branchStatusLogBranchesRespond(200, [
        'success' => true,
        'branches' => $statement->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $exception) {
    branchStatusLogBranchesRespond(500, [
        'success' => false,
        'error' => 'Unable to load the branch search list.',
    ]);
}
