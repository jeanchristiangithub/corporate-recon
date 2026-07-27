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

function settlementDailyAmount(mixed $value): ?float
{
    if ($value === null) return null;
    $text = trim((string)$value);
    if ($text === '') return null;
    $negative = preg_match('/^\(.*\)$/', $text) === 1;
    $text = trim($text, "() \t\n\r\0\x0B");
    $text = str_replace([',', ' '], '', $text);
    if (!is_numeric($text)) return null;
    $number = (float)$text;
    return $negative ? -abs($number) : $number;
}

function settlementDailyMatchKey(string $referenceId, string $tranType, mixed $value, bool $isDate): string
{
    // MoneyGram settlement files can present a database-negative amount as a
    // positive value (and accounting-formatted negatives as `(123.45)`). The
    // Existed Data comparison is therefore based on monetary magnitude after
    // settlementDailyAmount() has normalized the Excel value.
    $normalized = $isDate ? (string)$value : number_format(abs((float)$value), 6, '.', '');
    return $referenceId . "\0" . strtolower(trim($tranType)) . "\0" . $normalized;
}

/** @return array<int,array{exists:bool,database:?array,matched_by:string}> */
function settlementDailyBatchLookup(PDO $pdo, array $candidates, string $column, bool $isDate): array
{
    $allowedColumns = ['tran_date', 'base_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt'];
    if ($candidates === [] || !in_array($column, $allowedColumns, true)) return [];

    $unique = [];
    foreach ($candidates as $index => $candidate) {
        $key = settlementDailyMatchKey($candidate['reference_id'], $candidate['tran_type'], $candidate['value'], $isDate);
        $unique[$key]['reference_id'] = $candidate['reference_id'];
        $unique[$key]['tran_type'] = strtolower(trim($candidate['tran_type']));
        $unique[$key]['value'] = $candidate['value'];
        $unique[$key]['indexes'][] = (int)$index;
    }

    $clauses = [];
    $parameters = [];
    $matchExpression = $isDate ? '`' . $column . '`' : 'ABS(`' . $column . '`)';
    foreach ($unique as $candidate) {
        $clauses[] = '(reference_id = ? AND LOWER(TRIM(COALESCE(tran_type, \'\'))) = ? AND ' . $matchExpression . ' = ?)';
        $parameters[] = $candidate['reference_id'];
        $parameters[] = $candidate['tran_type'];
        $parameters[] = $isDate ? $candidate['value'] : abs((float)$candidate['value']);
    }
    $summaryStatement = $pdo->prepare(
        'SELECT reference_id, LOWER(TRIM(COALESCE(tran_type, \'\'))) AS normalized_tran_type, ' . $matchExpression . ' AS match_value, COUNT(*) AS match_count, MAX(id) AS selected_id
         FROM moneygram_partner_data WHERE ' . implode(' OR ', $clauses) . '
         GROUP BY reference_id, LOWER(TRIM(COALESCE(tran_type, \'\'))), ' . $matchExpression
    );
    $summaryStatement->execute($parameters);
    $summaries = $summaryStatement->fetchAll(PDO::FETCH_ASSOC);

    $selectedIds = array_values(array_unique(array_filter(array_map(
        static fn(array $row): int => (int)($row['selected_id'] ?? 0),
        $summaries
    ))));
    $dataById = [];
    if ($selectedIds !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $dataStatement = $pdo->prepare(
            'SELECT id, tran_date, fx_rate_trn, margin, base_tran_amt, fee_tran_amt, fx_rev_share_tran_amt, comm_tran_amt
             FROM moneygram_partner_data WHERE id IN (' . $placeholders . ')'
        );
        $dataStatement->execute($selectedIds);
        foreach ($dataStatement->fetchAll(PDO::FETCH_ASSOC) as $row) $dataById[(int)$row['id']] = $row;
    }

    $results = [];
    foreach ($summaries as $summary) {
        $key = settlementDailyMatchKey((string)$summary['reference_id'], (string)$summary['normalized_tran_type'], $summary['match_value'], $isDate);
        if (!isset($unique[$key])) continue;
        $database = $dataById[(int)$summary['selected_id']] ?? null;
        if (is_array($database)) unset($database['id']);
        foreach ($unique[$key]['indexes'] as $index) {
            $results[$index] = [
                'exists' => (int)$summary['match_count'] > 0,
                'database' => $database,
                'matched_by' => $column,
            ];
        }
    }
    return $results;
}

try {
    $pdo = fileRecDbConnection();
    $inputRows = [];
    $results = [];
    foreach ($pairs as $pair) {
        $index = filter_var($pair['index'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || $index < 0) continue;
        $referenceId = trim((string)($pair['reference_id'] ?? ''));
        $inputRows[$index] = [
            'reference_id' => $referenceId,
            'tran_date' => trim((string)($pair['tran_date'] ?? '')),
            'tran_type' => trim((string)($pair['tran_type'] ?? '')),
            'base_tran_amt' => settlementDailyAmount($pair['base_tran_amt'] ?? null),
            'fx_rev_share_tran_amt' => settlementDailyAmount($pair['fx_rev_share_tran_amt'] ?? null),
            'comm_tran_amt' => settlementDailyAmount($pair['comm_tran_amt'] ?? null),
        ];
        $results[$index] = ['exists' => false, 'database' => null, 'matched_by' => null];
    }

    $stages = [
        ['column' => 'tran_date', 'date' => true],
        ['column' => 'base_tran_amt', 'date' => false],
        ['column' => 'fx_rev_share_tran_amt', 'date' => false],
        ['column' => 'comm_tran_amt', 'date' => false],
    ];
    foreach ($stages as $stage) {
        $candidates = [];
        foreach ($inputRows as $index => $row) {
            if ($results[$index]['exists'] || $row['reference_id'] === '') continue;
            $value = $row[$stage['column']];
            if ($stage['date']) {
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) continue;
            } elseif ($value === null || abs((float)$value) < 0.0000001) {
                continue;
            }
            $candidates[$index] = ['reference_id' => $row['reference_id'], 'tran_type' => $row['tran_type'], 'value' => $value];
        }
        foreach (settlementDailyBatchLookup($pdo, $candidates, $stage['column'], $stage['date']) as $index => $match) {
            $results[$index] = $match;
        }
    }

    $encodedResults = [];
    foreach ($results as $index => $result) $encodedResults[(string)$index] = $result;
    echo json_encode(['success' => true, 'results' => $encodedResults], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $exception) {
    error_log('[settlement-daily-classify] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to check MoneyGram settlement records.']);
}
