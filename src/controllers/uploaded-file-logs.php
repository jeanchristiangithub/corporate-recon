<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$source = (string)($_GET['source'] ?? 'kpx_web_data');
$state = (string)($_GET['state'] ?? 'all');
$month = trim((string)($_GET['month'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

$statusByState = [
    'all' => null,
    'payout' => 'PO',
    'sendout' => 'SO',
    'payout_cancel' => 'POC',
    'sendout_cancel' => 'SOC',
];
$partnerStates = ['all', 'transactional', 'settlement'];

if (!in_array($source, ['kpx_web_data', 'partner_data'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid data source.']);
    exit;
}

if ($source === 'kpx_web_data' && !array_key_exists($state, $statusByState)) {
    $state = 'all';
}

if ($source === 'partner_data' && !in_array($state, $partnerStates, true)) {
    $state = 'all';
}

if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid month filter.']);
    exit;
}

try {
    $pdo = fileRecDbConnection();
    $where = [];
    $params = [];

    if ($source === 'kpx_web_data') {
        $where[] = "(
            FIND_IN_SET('PO', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
            OR FIND_IN_SET('SO', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
            OR FIND_IN_SET('POC', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
            OR FIND_IN_SET('SOC', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
        )";
        if ($statusByState[$state] !== null) {
            $where[] = "FIND_IN_SET(:status, REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0";
            $params[':status'] = $statusByState[$state];
        }
    } elseif ($state === 'transactional') {
        $where[] = "FIND_IN_SET('TD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0";
    } elseif ($state === 'settlement') {
        $where[] = "FIND_IN_SET('SD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0";
    } elseif ($state === 'all') {
        $where[] = "(
            FIND_IN_SET('TD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
            OR FIND_IN_SET('SD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0
        )";
    }

    if ($month !== '') {
        $monthStart = DateTimeImmutable::createFromFormat('!Y-m', $month);
        if (!$monthStart) {
            throw new RuntimeException('Unable to read the selected month.');
        }

        $where[] = 'l.uploaded_date >= :month_start AND l.uploaded_date < :month_end';
        $params[':month_start'] = $monthStart->format('Y-m-d');
        $params[':month_end'] = $monthStart->modify('+1 month')->format('Y-m-d');
    }

    if ($search !== '') {
        $where[] = "(
            l.filename LIKE :search_filename
            OR l.filename_ext LIKE :search_extension
            OR l.partner_name LIKE :search_partner
            OR l.uploaded_by LIKE :search_uploader_id
            OR CONCAT_WS(' ',
                NULLIF(TRIM(u.firstname), ''),
                NULLIF(TRIM(u.middlename), ''),
                NULLIF(TRIM(u.lastname), '')
            ) LIKE :search_uploader_name
        )";
        $searchValue = '%' . $search . '%';
        $params[':search_filename'] = $searchValue;
        $params[':search_extension'] = $searchValue;
        $params[':search_partner'] = $searchValue;
        $params[':search_uploader_id'] = $searchValue;
        $params[':search_uploader_name'] = $searchValue;
    }

    if ($source === 'partner_data' && $state === 'transactional') {
        $linkedDataExists = "EXISTS(
            SELECT 1
            FROM moneygram_partner_data linked_data
            WHERE linked_data.ufl_file_log_id = l.id
        )";
    } elseif ($source === 'partner_data' && $state === 'settlement') {
        $linkedDataExists = "EXISTS(
            SELECT 1
            FROM partner_settlement_data linked_data
            WHERE linked_data.ufl_file_log_id = l.id
        )";
    } elseif ($source === 'partner_data' && $state === 'all') {
        $linkedDataExists = "CASE
            WHEN FIND_IN_SET('SD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0 THEN EXISTS(
                SELECT 1
                FROM partner_settlement_data linked_data
                WHERE linked_data.ufl_file_log_id = l.id
            )
            WHEN FIND_IN_SET('TD', REPLACE(COALESCE(l.kpxweb_data_status, ''), ' ', '')) > 0 THEN EXISTS(
                SELECT 1
                FROM moneygram_partner_data linked_data
                WHERE linked_data.ufl_file_log_id = l.id
            )
            ELSE 0
        END";
    } else {
        $linkedDataExists = "EXISTS(
            SELECT 1
            FROM ml_web_data linked_data
            WHERE linked_data.ufl_file_log_id = l.id
        )";
    }

    $sql = "
        SELECT
            l.id,
            l.uploaded_date,
            l.filename,
            l.filename_ext,
            l.partner_name,
            l.uploaded_by,
            l.has_overwrite,
            {$linkedDataExists} AS has_linked_data,
            COALESCE(
                NULLIF(CONCAT_WS(' ',
                    NULLIF(TRIM(u.firstname), ''),
                    NULLIF(TRIM(u.middlename), ''),
                    NULLIF(TRIM(u.lastname), '')
                ), ''),
                l.uploaded_by
            ) AS uploader_name
        FROM uploaded_file_logs l
        LEFT JOIN users u
            ON u.id_number COLLATE utf8mb4_unicode_ci
             = l.uploaded_by COLLATE utf8mb4_unicode_ci
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.uploaded_date DESC, l.id DESC
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('[uploaded-file-logs] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load uploaded file logs.']);
}
