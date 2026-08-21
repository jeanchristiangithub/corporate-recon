<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function branchStatusHistoryRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    branchStatusHistoryRespond(401, ['success' => false, 'error' => 'Your session has expired. Please log in again.']);
}

$month = trim((string)($_GET['month'] ?? ''));
if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    branchStatusHistoryRespond(422, ['success' => false, 'error' => 'A valid month is required.']);
}

$postedAtValue = trim((string)($_GET['posted_at'] ?? ''));

try {
    $connection = fileRecDbConnection();

    if ($postedAtValue !== '') {
        $postedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $postedAtValue);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
        if (!$postedAt || $hasDateErrors || $postedAt->format('Y-m-d H:i:s') !== $postedAtValue) {
            branchStatusHistoryRespond(422, ['success' => false, 'error' => 'The posting date and time is invalid.']);
        }

        $statement = $connection->prepare(
            'SELECT
                mbp_branch_id AS branch_id,
                mbp_code AS branch_code,
                COALESCE(
                    NULLIF(TRIM(mbp_mlmatic_branch_name), \'\'),
                    NULLIF(TRIM(mbp_branch_name_description), \'\'),
                    \'\'
                ) AS branch_name,
                mbp_area AS area,
                mbp_gl_region AS region_description,
                mbp_mlmatic_status AS status
             FROM corporate_branch_status_history
             WHERE posted_at >= ?
               AND posted_at < DATE_ADD(?, INTERVAL 1 MINUTE)
             ORDER BY branch_name, mbp_branch_id'
        );
        $statement->execute([$postedAtValue, $postedAtValue]);

        branchStatusHistoryRespond(200, ['success' => true, 'rows' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $historySql = "SELECT
                       DATE_FORMAT(MIN(h.posted_at), '%Y-%m-%d %H:%i:00') AS posted_at,
                       MIN(h.posted_date) AS posted_date,
                       COALESCE(
                           NULLIF(MAX(TRIM(CONCAT_WS(' ',
                               NULLIF(TRIM(u.firstname), ''),
                               NULLIF(TRIM(u.middlename), ''),
                               NULLIF(TRIM(u.lastname), '')
                           ))), ''),
                           h.posted_by
                       ) AS posted_by,
                       COUNT(*) AS record_count
                   FROM corporate_branch_status_history h
                   LEFT JOIN users u
                     ON TRIM(u.id_number) COLLATE utf8mb4_unicode_ci
                        = TRIM(h.posted_by) COLLATE utf8mb4_unicode_ci";
    $historyParameters = [];

    if ($month !== '') {
        $monthStart = $month . '-01 00:00:00';
        $nextMonth = (new DateTimeImmutable($month . '-01'))->modify('first day of next month')->format('Y-m-d 00:00:00');
        $historySql .= ' WHERE h.posted_at >= ? AND h.posted_at < ?';
        $historyParameters = [$monthStart, $nextMonth];
    }

    $historySql .= " GROUP BY DATE_FORMAT(h.posted_at, '%Y-%m-%d %H:%i'), h.posted_by
                     ORDER BY posted_at DESC";
    $statement = $connection->prepare($historySql);
    $statement->execute($historyParameters);

    branchStatusHistoryRespond(200, ['success' => true, 'groups' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $exception) {
    branchStatusHistoryRespond(500, ['success' => false, 'error' => 'Unable to load posted branch status data.']);
}
