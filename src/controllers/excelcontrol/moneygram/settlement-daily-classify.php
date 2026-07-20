<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';

bootSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

$sentToken = (string)($_POST['csrf_token'] ?? '');
$storedToken = (string)($_SESSION['csrf_token'] ?? '');
if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$payload = json_decode((string)($_POST['payload'] ?? ''), true);
$pairs = is_array($payload) && isset($payload['pairs']) && is_array($payload['pairs']) ? $payload['pairs'] : [];
try {
    $pdo = fileRecDbConnection();
    $validPairs = [];
    $results = [];
    foreach ($pairs as $pair) {
        $index = filter_var($pair['index'] ?? null, FILTER_VALIDATE_INT);
        $referenceId = trim((string)($pair['reference_id'] ?? ''));
        $tranDate = trim((string)($pair['tran_date'] ?? ''));
        if ($index === false || $index < 0 || $referenceId === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tranDate)) {
            if ($index !== false && $index >= 0) $results[(string)$index] = ['exists' => false, 'database' => null];
            continue;
        }

        $key = $referenceId . "\0" . $tranDate;
        $validPairs[$key]['reference_id'] = $referenceId;
        $validPairs[$key]['tran_date'] = $tranDate;
        $validPairs[$key]['indexes'][] = $index;
    }

    $recordsByKey = [];
    if ($validPairs !== []) {
        $clauses = [];
        $parameters = [];
        foreach ($validPairs as $pair) {
            $clauses[] = '(reference_id = ? AND tran_date = ?)';
            $parameters[] = $pair['reference_id'];
            $parameters[] = $pair['tran_date'];
        }
        $summaryStatement = $pdo->prepare(
            'SELECT reference_id, tran_date, COUNT(*) AS match_count, MAX(id) AS selected_id
             FROM moneygram_partner_data WHERE ' . implode(' OR ', $clauses) . '
             GROUP BY reference_id, tran_date'
        );
        $summaryStatement->execute($parameters);
        $summaries = $summaryStatement->fetchAll(PDO::FETCH_ASSOC);
        $selectedIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['selected_id'] ?? 0), $summaries)));
        $dataById = [];
        if ($selectedIds !== []) {
            $idPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $dataStatement = $pdo->prepare(
                'SELECT id, fx_rate_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt
                 FROM moneygram_partner_data WHERE id IN (' . $idPlaceholders . ')'
            );
            $dataStatement->execute($selectedIds);
            foreach ($dataStatement->fetchAll(PDO::FETCH_ASSOC) as $dataRow) $dataById[(int)$dataRow['id']] = $dataRow;
        }
        foreach ($summaries as $summary) {
            $key = (string)$summary['reference_id'] . "\0" . (string)$summary['tran_date'];
            $database = $dataById[(int)$summary['selected_id']] ?? null;
            if (is_array($database)) unset($database['id']);
            $recordsByKey[$key] = ['exists' => (int)$summary['match_count'] > 0, 'database' => $database];
        }
    }
    foreach ($validPairs as $key => $pair) {
        $record = $recordsByKey[$key] ?? ['exists' => false, 'database' => null];
        foreach ($pair['indexes'] as $index) $results[(string)$index] = $record;
    }

    echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $exception) {
    error_log('[settlement-daily-classify] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to check MoneyGram settlement records.']);
}
