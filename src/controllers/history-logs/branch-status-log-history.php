<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function branchStatusLogHistoryRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    branchStatusLogHistoryRespond(401, [
        'success' => false,
        'error' => 'Your session has expired. Please log in again.',
    ]);
}

$branchId = trim((string) ($_GET['branch_id'] ?? ''));
$branchName = trim((string) ($_GET['branch_name'] ?? ''));
$presentPostedAt = trim((string) ($_GET['posted_at'] ?? ''));

if (($branchId === '' && $branchName === '') || mb_strlen($branchId) > 100 || mb_strlen($branchName) > 255) {
    branchStatusLogHistoryRespond(422, [
        'success' => false,
        'error' => 'A valid Branch ID or Branch Name is required.',
    ]);
}

$postedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $presentPostedAt);
$dateErrors = DateTimeImmutable::getLastErrors();
$hasDateErrors = is_array($dateErrors)
    && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
if (!$postedAt || $hasDateErrors || $postedAt->format('Y-m-d H:i:s') !== $presentPostedAt) {
    branchStatusLogHistoryRespond(422, [
        'success' => false,
        'error' => 'A valid Present Data Posted Date is required.',
    ]);
}

try {
    $connection = fileRecDbConnection();
    $resolvedBranchNameSql = "COALESCE(
        NULLIF(TRIM(h.mbp_mlmatic_branch_name), ''),
        NULLIF(TRIM(h.mkpxbm_branch_name), ''),
        NULLIF(TRIM(h.mbp_branch_name_description), ''),
        ''
    )";
    if ($branchId !== '') {
        $branchFilterSql = "COALESCE(TRIM(h.mbp_branch_id), '') = ?";
        $parameters = [$branchId];
    } else {
        $branchFilterSql = "COALESCE(TRIM(h.mbp_branch_id), '') = ''
            AND {$resolvedBranchNameSql} = ?";
        $parameters = [$branchName];
    }
    $parameters[] = $presentPostedAt;

    $statement = $connection->prepare(
        "SELECT
            h.posted_at,
            COALESCE(TRIM(h.mbp_branch_id), '') AS branch_id,
            TRIM(h.mbp_code) AS bos_code,
            {$resolvedBranchNameSql} AS branch_name,
            TRIM(h.mbp_area) AS area,
            TRIM(h.mbp_corporate_name) AS corporate_name,
            TRIM(h.mbp_mainzone) AS mainzone,
            TRIM(h.mbp_zone) AS zone,
            TRIM(h.mrm_region_description) AS region_name_1,
            TRIM(h.mbp_mlmatic_region) AS region_name_2,
            TRIM(h.mbp_mlmatic_status) AS branch_status,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ',
                    NULLIF(TRIM(u.firstname), ''),
                    NULLIF(TRIM(u.middlename), ''),
                    NULLIF(TRIM(u.lastname), '')
                )), ''),
                NULLIF(TRIM(h.posted_by), ''),
                ''
            ) AS posted_by
         FROM filerecondb.corporate_branch_status_history h
         LEFT JOIN filerecondb.users u
           ON TRIM(u.id_number) COLLATE utf8mb4_unicode_ci
              = TRIM(h.posted_by) COLLATE utf8mb4_unicode_ci
         WHERE {$branchFilterSql}
           AND h.posted_at <> ?
         ORDER BY h.posted_at DESC, h.id DESC"
    );
    $statement->execute($parameters);

    branchStatusLogHistoryRespond(200, [
        'success' => true,
        'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $exception) {
    branchStatusLogHistoryRespond(500, [
        'success' => false,
        'error' => 'Unable to load recorded branch history data.',
    ]);
}
