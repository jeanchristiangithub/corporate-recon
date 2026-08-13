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
$partner = trim((string)($_GET['partner'] ?? ''));
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$pageSize = filter_var($_GET['page_size'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 10;

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

foreach (['Start Date' => $startDate, 'End Date' => $endDate] as $label => $dateValue) {
    if ($dateValue !== '') {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dateValue) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => "Invalid {$label} filter."]);
            exit;
        }
    }
}

if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'End Date must be on or after Start Date.']);
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

    if ($partner !== '') {
        $where[] = 'TRIM(l.partner_name) = :partner';
        $params[':partner'] = $partner;
    }

    if ($startDate !== '') {
        $where[] = 'l.uploaded_date >= :start_date';
        $params[':start_date'] = $startDate;
    }

    if ($endDate !== '') {
        $where[] = 'l.uploaded_date < :end_date_exclusive';
        $params[':end_date_exclusive'] = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d');
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

    if ($source === 'kpx_web_data') {
        $linkedDataJoin = "INNER JOIN (
            SELECT DISTINCT ufl_file_log_id
            FROM ml_web_data
            WHERE ufl_file_log_id IS NOT NULL
        ) linked_logs ON linked_logs.ufl_file_log_id = l.id";
    } elseif ($state === 'transactional') {
        $linkedDataJoin = "INNER JOIN (
            SELECT DISTINCT ufl_file_log_id
            FROM moneygram_partner_data
            WHERE ufl_file_log_id IS NOT NULL
        ) linked_logs ON linked_logs.ufl_file_log_id = l.id";
    } elseif ($state === 'settlement') {
        $linkedDataJoin = "INNER JOIN (
            SELECT DISTINCT ufl_file_log_id
            FROM partner_settlement_data
            WHERE ufl_file_log_id IS NOT NULL
        ) linked_logs ON linked_logs.ufl_file_log_id = l.id";
    } else {
        $linkedDataJoin = "INNER JOIN (
            SELECT ufl_file_log_id FROM moneygram_partner_data WHERE ufl_file_log_id IS NOT NULL
            UNION
            SELECT ufl_file_log_id FROM partner_settlement_data WHERE ufl_file_log_id IS NOT NULL
        ) linked_logs ON linked_logs.ufl_file_log_id = l.id";
    }

    $fromAndWhere = "
        FROM uploaded_file_logs l
        {$linkedDataJoin}
        LEFT JOIN users u
            ON u.id_number COLLATE utf8mb4_unicode_ci
             = l.uploaded_by COLLATE utf8mb4_unicode_ci
        WHERE " . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromAndWhere}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;

    $sql = "
        SELECT
            l.id,
            l.uploaded_date,
            l.filename,
            l.filename_ext,
            l.partner_name,
            l.uploaded_by,
            l.kpxweb_data_status,
            l.has_overwrite,
            COALESCE(
                NULLIF(CONCAT_WS(' ',
                    NULLIF(TRIM(u.firstname), ''),
                    NULLIF(TRIM(u.middlename), ''),
                    NULLIF(TRIM(u.lastname), '')
                ), ''),
                l.uploaded_by
            ) AS uploader_name
        {$fromAndWhere}
        ORDER BY l.uploaded_date DESC, l.id DESC
        LIMIT {$pageSize} OFFSET {$offset}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['has_linked_data'] = 1;
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('[uploaded-file-logs] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load uploaded file logs.']);
}
