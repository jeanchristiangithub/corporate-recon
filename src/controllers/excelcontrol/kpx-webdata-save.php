<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

bootSecureSession();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sentToken = (string)($_POST['csrf_token'] ?? '');
$storedToken = (string)($_SESSION['csrf_token'] ?? '');
if ($storedToken !== '' && !hash_equals($storedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$payload = json_decode((string)($_POST['payload'] ?? ''), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload.']);
    exit;
}

$rows = $payload['rows'] ?? [];
$overwrite = !empty($payload['overwrite']);
if (!is_array($rows) || count($rows) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No rows to upload.']);
    exit;
}

$insertColumns = [
    'partner_id',
    'partnerName',
    'control_series_no',
    'date_cancelled',
    'date_claimed',
    'date_send',
    'kptn',
    'ccref_no',
    'currency',
    'amount',
    'ctc',
    'ctp',
    'charge',
    'sender_name',
    'sender_country',
    'beneficiary_receiver',
    'receiver_kyc',
    'receiver_phone',
    'operator',
    'branch_id',
    'branch',
    'remote_operator',
    'remote_branch_id',
    'remote_branch',
    'created_at',
    'updated_at',
    'uploaded_by',
    'mainzone',
    'zone',
    'area',
    'region',
    'region_code',
    'receiver_country',
    'receiver_name',
    'data_status',
    'other_details',
    'is_data_locked',
];

function kpxSaveValue(array $row, string $key): mixed
{
    if (!array_key_exists($key, $row)) return null;
    $value = $row[$key];
    if ($value === '') return null;
    return $value;
}

function kpxDuplicateWhere(array $row): array
{
    $status = strtoupper(trim((string)($row['data_status'] ?? '')));
    $where = ['TRIM(COALESCE(ccref_no, "")) = TRIM(?)', 'CAST(COALESCE(amount, 0) AS DECIMAL(18,2)) = CAST(? AS DECIMAL(18,2))'];
    $params = [
        trim((string)($row['ccref_no'] ?? '')),
        trim((string)($row['amount'] ?? '0')),
    ];

    if ($status === 'PO') {
        $where[] = 'TRIM(COALESCE(date_claimed, "")) = TRIM(?)';
        $params[] = trim((string)($row['date_claimed'] ?? ''));
    } elseif ($status === 'SO') {
        $where[] = 'TRIM(COALESCE(date_send, "")) = TRIM(?)';
        $params[] = trim((string)($row['date_send'] ?? ''));
    } elseif ($status === 'POC') {
        $where[] = 'TRIM(COALESCE(date_cancelled, "")) = TRIM(?)';
        $where[] = 'TRIM(COALESCE(date_claimed, "")) = TRIM(?)';
        $params[] = trim((string)($row['date_cancelled'] ?? ''));
        $params[] = trim((string)($row['date_claimed'] ?? ''));
    } elseif ($status === 'SOC') {
        $where[] = 'TRIM(COALESCE(date_cancelled, "")) = TRIM(?)';
        $where[] = 'TRIM(COALESCE(date_send, "")) = TRIM(?)';
        $params[] = trim((string)($row['date_cancelled'] ?? ''));
        $params[] = trim((string)($row['date_send'] ?? ''));
    } else {
        $where[] = 'TRIM(COALESCE(data_status, "")) = TRIM(?)';
        $params[] = $status;
    }

    return [$where, $params];
}

try {
    $pdo = fileRecDbConnection();
    $columnRows = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
    $availableColumns = [];
    foreach ($columnRows as $columnRow) {
        $availableColumns[(string)$columnRow['Field']] = true;
    }
    $columns = array_values(array_filter($insertColumns, static fn($column) => isset($availableColumns[$column])));
    if (empty($columns)) {
        throw new RuntimeException('No compatible ml_web_data columns found.');
    }

    $duplicateIds = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        [$where, $params] = kpxDuplicateWhere($row);
        $stmt = $pdo->prepare('SELECT id FROM ml_web_data WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $duplicateIds[] = (int)$id;
        }
    }

    if (!$overwrite && count($duplicateIds) > 0) {
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'duplicateCount' => count($duplicateIds),
            'message' => 'Data with the same CCREF NO and date already exists. Do you want to overwrite the existing data?',
        ]);
        exit;
    }

    $quotedColumns = array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '`', $columns);
    $insertSql = 'INSERT INTO ml_web_data (' . implode(',', $quotedColumns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
    $insertStmt = $pdo->prepare($insertSql);
    $updateColumns = array_values(array_filter($columns, static fn($column) => $column !== 'created_at'));
    $updateSql = 'UPDATE ml_web_data SET ' . implode(',', array_map(static fn($column) => '`' . str_replace('`', '``', $column) . '` = ?', $updateColumns)) . ' WHERE id = ?';
    $updateStmt = $pdo->prepare($updateSql);

    $inserted = 0;
    $updated = 0;
    $pdo->beginTransaction();
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        [$where, $params] = kpxDuplicateWhere($row);
        $stmt = $pdo->prepare('SELECT id FROM ml_web_data WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        if ($id !== false && $overwrite) {
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $updateColumns);
            $values[] = (int)$id;
            $updateStmt->execute($values);
            $updated++;
        } elseif ($id === false) {
            $values = array_map(static fn($column) => kpxSaveValue($row, $column), $columns);
            $insertStmt->execute($values);
            $inserted++;
        }
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload failed.']);
}
