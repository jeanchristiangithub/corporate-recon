<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function branchStatusPostingRespond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isAuthenticated()) {
    branchStatusPostingRespond(401, ['success' => false, 'error' => 'Your session has expired. Please log in again.']);
}

if (strcasecmp(trim((string) ($_SESSION['user']['role'] ?? '')), 'Admin') !== 0) {
    branchStatusPostingRespond(403, ['success' => false, 'error' => 'Administrator access is required.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    branchStatusPostingRespond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

verifyCsrfOrFail();

$postedDateTimeValue = trim((string)($_POST['posted_datetime'] ?? ''));
$postedDateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $postedDateTimeValue);
$dateErrors = DateTimeImmutable::getLastErrors();
$hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);

if (!$postedDateTime || $hasDateErrors || $postedDateTime->format('Y-m-d\TH:i') !== $postedDateTimeValue) {
    branchStatusPostingRespond(422, ['success' => false, 'error' => 'A valid posting date and time is required.']);
}

$postedBy = trim((string)($_SESSION['user']['id_number'] ?? ''));
if ($postedBy === '') {
    branchStatusPostingRespond(422, ['success' => false, 'error' => 'The posting user could not be identified.']);
}

$databaseDateTime = $postedDateTime->format('Y-m-d H:i:00');

try {
    $masterStatement = masterDataConnection()->query(
        "SELECT
            bp.branch_id,
            bp.code,
            bp.ml_matic_branch_name,
            bp.branch_name AS branch_name_description,
            kbm.branch_name AS kpx_branch_name,
            bp.area,
            bp.corporate_name,
            bp.mainzone,
            bp.zone,
            bp.region_code,
            rm.region_description,
            bp.gl_region,
            bp.ml_matic_region,
            bp.ml_matic_status
         FROM branch_profile bp
         LEFT JOIN kpx_branch_masterfile kbm
            ON TRIM(kbm.branch_id) = TRIM(bp.branch_id)
         LEFT JOIN region_masterfile rm
            ON UPPER(TRIM(rm.region_code)) = UPPER(TRIM(bp.region_code))
         ORDER BY bp.id"
    );
    $masterRows = $masterStatement->fetchAll(PDO::FETCH_ASSOC);

    if ($masterRows === []) {
        branchStatusPostingRespond(422, ['success' => false, 'error' => 'No branch status records are available to post.']);
    }

    $historyConnection = fileRecDbConnection();
    $historyConnection->beginTransaction();

    $duplicateStatement = $historyConnection->prepare(
        'SELECT 1
         FROM corporate_branch_status_history
         WHERE posted_date = DATE_SUB(?, INTERVAL 1 MONTH)
         LIMIT 1'
    );
    $duplicateStatement->execute([$databaseDateTime]);
    if ($duplicateStatement->fetchColumn() !== false) {
        $historyConnection->rollBack();
        branchStatusPostingRespond(409, ['success' => false, 'error' => 'Branch statuses have already been posted for this date and time.']);
    }

    $insertStatement = $historyConnection->prepare(
        'INSERT INTO corporate_branch_status_history (
            posted_date,
            mbp_branch_id,
            mbp_code,
            mbp_mlmatic_branch_name,
            mbp_branch_name_description,
            mkpxbm_branch_name,
            mbp_area,
            mbp_corporate_name,
            mbp_mainzone,
            mbp_zone,
            mbp_region_code,
            mrm_region_description,
            mbp_gl_region,
            mbp_mlmatic_region,
            mbp_mlmatic_status,
            posted_at,
            posted_by
        ) VALUES (DATE_SUB(?, INTERVAL 1 MONTH), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($masterRows as $row) {
        $insertStatement->execute([
            $databaseDateTime,
            $row['branch_id'],
            $row['code'],
            $row['ml_matic_branch_name'],
            $row['branch_name_description'],
            $row['kpx_branch_name'],
            $row['area'],
            $row['corporate_name'],
            $row['mainzone'],
            $row['zone'],
            $row['region_code'],
            $row['region_description'],
            $row['gl_region'],
            $row['ml_matic_region'],
            $row['ml_matic_status'],
            $databaseDateTime,
            $postedBy,
        ]);
    }

    $historyConnection->commit();
    branchStatusPostingRespond(201, [
        'success' => true,
        'inserted_count' => count($masterRows),
        'posted_datetime' => $databaseDateTime,
    ]);
} catch (Throwable $exception) {
    if (isset($historyConnection) && $historyConnection instanceof PDO && $historyConnection->inTransaction()) {
        $historyConnection->rollBack();
    }
    branchStatusPostingRespond(500, ['success' => false, 'error' => 'Unable to post branch status records.']);
}
