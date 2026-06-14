<?php
// partner-data-report.php
// Fetch filtered transactions from partner data tables and normalize rows for the reports UI.

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function resolve_partner_table(string $partner): string
{
    $normalized = strtoupper(trim($partner));
    if ($normalized === 'WIC' || $normalized === 'WORLDCOM INTERNATIONAL COMMUNICATIONS') {
        return 'wic_partner_data';
    }
    if ($normalized === 'MBTC' || $normalized === 'METROBANK HEAD OFFICE') {
        return 'mbtc_partner_data';
    }
    if ($normalized === 'RCBC' || $normalized === 'RIZAL COMMERCIAL BANKING CORPORATION') {
        return 'rcbc_partner_data';
    }
    if (
        $normalized === 'SKYBRIDGE' ||
        $normalized === 'SKYBRIDGEPAYMENTINC' ||
        $normalized === 'SKYBRIDGE PAYMENT INC.' ||
        $normalized === 'SKYBRIDGE PAYMENT INC'
    ) {
        return 'skybridgepaymentinc_partner_data';
    }
    $normalized = strtoupper(trim($partner));
    if ($normalized === 'MONEYGRAM' || $normalized === 'MONEYGRAM') {
        return 'moneygram_partner_data';
    }
    throw new RuntimeException('Unsupported partner for partner data reports: ' . $partner);
}

function first_existing_column(array $candidates, array $existing): ?string
{
    foreach ($candidates as $candidate) {
        if (isset($existing[$candidate])) {
            return $candidate;
        }
    }
    return null;
}

function normalize_row_for_report(array $row): array
{
    $pick = static function (array $keys) use ($row) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }
        return '';
    };

    $dateClaimed = $pick(['date_claimed', 'date', 'cover_date']);
    $ccref = $pick(['ccref_no', 'transaction_id', 'reference_no', 'ref_no', 'payout_id']);
    $controlSeries = $pick(['control_series_no', 'transaction_id', 'reference_no', 'ref_no', 'payout_id']);
    $currency = $pick(['currency', 'coin', 'crc_code']);
    $amount = $pick(['amount', 'in_php', 'php', 'usd', 'total_payable', 'bene_amt']);
    $sender = $pick(['sender_name', 'remitter_name']);
    $beneficiary = $pick(['beneficiary_receiver', 'beneficiary_name']);

    $partnerDate = $pick(['date', 'date_claimed', 'cover_date']);
    $partnerTime = $pick(['time']);
    $referenceNo = $pick(['reference_no', 'ref_no', 'transaction_id', 'payout_id']);
    $rtsTracerNo = $pick(['rts_tracer_no']);
    $provider = $pick(['provider']);
    $beneficiaryName = $pick(['beneficiary_name', 'beneficiary_receiver']);
    $remitterName = $pick(['remitter_name', 'sender_name']);
    $phpAmount = $pick(['php']);
    $usdAmount = $pick(['usd']);
    $inPhpAmount = $pick(['in_php', 'amount']);

    return [
        'no' => $pick(['no', 'id']),
        'control_series_no' => (string)$controlSeries,
        'date_claimed' => (string)$dateClaimed,
        'ccref_no' => (string)$ccref,
        'currency' => (string)$currency,
        'amount' => $amount,
        'sender_name' => (string)$sender,
        'beneficiary_receiver' => (string)$beneficiary,
        'operator' => (string)$pick(['operator']),
        'branch' => (string)$pick(['branch']),
        // Keep explicit partner fields so the UI can match Metrobank/partner viewer column format.
        'partner_date' => (string)$partnerDate,
        'partner_time' => (string)$partnerTime,
        'partner_reference_no' => (string)$referenceNo,
        'partner_rts_tracer_no' => (string)$rtsTracerNo,
        'partner_provider' => (string)$provider,
        'partner_beneficiary_name' => (string)$beneficiaryName,
        'partner_remitter_name' => (string)$remitterName,
        'partner_php' => $phpAmount,
        'partner_usd' => $usdAmount,
        'partner_in_php' => $inPhpAmount,
    ];
}

try {
    $partner = isset($_GET['partner']) ? trim((string)$_GET['partner']) : '';
    $startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10000;

    if ($partner === '') {
        echo json_encode(['success' => false, 'error' => 'Corporate partner is required']);
        exit;
    }

    if ($page < 1) {
        $page = 1;
    }
    if ($perPage < 1 || $perPage > 10000) {
        $perPage = 10000;
    }

    $table = resolve_partner_table($partner);
    $pdo = fileRecDbConnection();

    $colRows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($colRows as $col) {
        $existing[(string)$col['Field']] = true;
    }

    $partnerCol = first_existing_column(['partnerName', 'partner_name'], $existing);
    $dateCol = first_existing_column(['date_claimed', 'date', 'cover_date'], $existing);
    // For Moneygram, prefer explicit tran_date if present
    if ($table === 'moneygram_partner_data' && isset($existing['tran_date'])) {
        $dateCol = 'tran_date';
    }
    $orderCol = first_existing_column(['created_at', 'updated_at', 'date_claimed', 'date', 'id'], $existing) ?: 'id';

    $whereParts = [];
    $params = [];

    if ($partnerCol !== null) {
        $whereParts[] = $partnerCol . ' = ?';
        $params[] = $partner;
    }

    if ($dateCol !== null && $startDate !== '') {
        $whereParts[] = 'DATE(' . $dateCol . ') >= ?';
        $params[] = $startDate;
    }
    if ($dateCol !== null && $endDate !== '') {
        $whereParts[] = 'DATE(' . $dateCol . ') <= ?';
        $params[] = $endDate;
    }

    // Optional additional filters (apply only if corresponding columns exist)
    $branchFilter = isset($_GET['branch']) ? trim((string)$_GET['branch']) : '';
    if ($branchFilter !== '') {
        if (isset($existing['branch_name'])) {
            $whereParts[] = 'LOWER(COALESCE(branch_name, "")) LIKE ?';
            $params[] = '%' . strtolower($branchFilter) . '%';
        } elseif (isset($existing['branch'])) {
            $whereParts[] = 'LOWER(COALESCE(branch, "")) LIKE ?';
            $params[] = '%' . strtolower($branchFilter) . '%';
        }
    }

    $legacyFilter = isset($_GET['legacy_id']) ? trim((string)$_GET['legacy_id']) : '';
    if ($legacyFilter !== '') {
        if (isset($existing['legacy_id'])) {
            $whereParts[] = 'legacy_id = ?';
            $params[] = $legacyFilter;
        }
    }

    $agentFilter = isset($_GET['agent_name']) ? trim((string)$_GET['agent_name']) : '';
    if ($agentFilter !== '') {
        if (isset($existing['agent_name'])) {
            $whereParts[] = 'LOWER(COALESCE(agent_name, "")) LIKE ?';
            $params[] = '%' . strtolower($agentFilter) . '%';
        }
    }

    $typeFilter = isset($_GET['tran_type']) ? trim((string)$_GET['tran_type']) : '';
    if ($typeFilter !== '') {
        if (isset($existing['tran_type'])) {
            $whereParts[] = 'tran_type = ?';
            $params[] = $typeFilter;
        }
    }

    $whereSql = '';
    if (!empty($whereParts)) {
        $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
    }

    $countSql = 'SELECT COUNT(*) FROM ' . $table . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT * FROM ' . $table . $whereSql . ' ORDER BY ' . $orderCol . ' DESC LIMIT ? OFFSET ?';
    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($rawRows as $index => $row) {
        $normalized = normalize_row_for_report($row);
        if ($normalized['no'] === '' || $normalized['no'] === null) {
            $normalized['no'] = (string)($offset + $index + 1);
        }
        $rows[] = $normalized;
    }

    $moneygramPhpTotal = 0.0;
    $moneygramUsdTotal = 0.0;
    if ($table === 'moneygram_partner_data') {
        $totalsSql = 'SELECT '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "PHP" THEN COALESCE(total_tran_amt, 0) ELSE 0 END), 0) AS php_total, '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(settlement_currency)) = "USD" THEN COALESCE(total_tran_amt, 0) ELSE 0 END), 0) AS usd_total '
            . 'FROM ' . $table . $whereSql;
        $totalsStmt = $pdo->prepare($totalsSql);
        $totalsStmt->execute($params);
        $totalsRow = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $moneygramPhpTotal = isset($totalsRow['php_total']) ? (float)$totalsRow['php_total'] : 0.0;
        $moneygramUsdTotal = isset($totalsRow['usd_total']) ? (float)$totalsRow['usd_total'] : 0.0;
    }

    // For MONEYGRAM, extract only the 8 required display columns in defined order
    $moneygramRows = [];
    if ($table === 'moneygram_partner_data') {
        $mgCols = ['transaction_id', 'reference_id', 'tran_date', 'tran_type', 'base_tran_amt', 'total_tran_amt', 'settlement_currency', 'agent_name', 'legacy_id'];
        foreach ($rawRows as $row) {
            $mgRow = [];
            foreach ($mgCols as $col) {
                $mgRow[$col] = array_key_exists($col, $row) ? $row[$col] : '';
            }
            $moneygramRows[] = $mgRow;
        }
    }

    echo json_encode([
        'success' => true,
        'count' => $totalCount,
        'page_count' => count($rows),
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'partner' => $partner,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'source_table' => $table,
        'columns' => array_values(array_map(function($c){ return (string)$c['Field']; }, $colRows)),
        'raw_rows' => $rawRows,
        'rows' => $rows,
        'moneygram_rows' => $moneygramRows,
        'moneygram_php_total' => $moneygramPhpTotal,
        'moneygram_usd_total' => $moneygramUsdTotal,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
