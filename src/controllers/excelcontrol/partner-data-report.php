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
        foreach ($existing as $column => $_) {
            if (strcasecmp((string)$column, (string)$candidate) === 0) {
                return (string)$column;
            }
        }
    }
    return null;
}

function quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function numeric_sql_expr(string $column): string
{
    $quoted = quote_identifier($column);
    return 'ABS(CAST(REPLACE(REPLACE(REPLACE(COALESCE(' . $quoted . ', 0), ",", ""), "₱", ""), "$", "") AS DECIMAL(18, 2)))';
}

function moneygram_transaction_type_prefix(string $type): string
{
    $normalized = strtolower(trim($type));
    if ($normalized === 'payout-cancelled' || $normalized === 'rrc') {
        return 'RRC';
    }
    if ($normalized === 'sendout-cancelled' || $normalized === 'rsn') {
        return 'RSN';
    }
    if ($normalized === 'payout' || $normalized === 'rec' || $normalized === 'receive') {
        return 'REC';
    }
    if ($normalized === 'sendout' || $normalized === 'send' || $normalized === 'sen') {
        return 'SEN';
    }
    return '';
}

function moneygram_transaction_type_codes(string $type): array
{
    $prefix = moneygram_transaction_type_prefix($type);
    if ($prefix !== '') {
        return [$prefix];
    }
    if (trim($type) === '') {
        return ['REC', 'SEN', 'RRC', 'RSN'];
    }
    return [];
}

function build_partner_totals(PDO $pdo, string $table, string $whereSql, array $params, array $existing): array
{
    $zeroTotals = [
        'php_total' => 0.0,
        'usd_total' => 0.0,
        'php_commission_total' => 0.0,
        'usd_commission_total' => 0.0,
    ];

    if ($table === 'moneygram_partner_data') {
        $baseExpr = numeric_sql_expr('base_amt');
        if (isset($existing['comm_amt']) && isset($existing['comm_tran_amt'])) {
            $commissionExpr = 'ABS(COALESCE(' . numeric_sql_expr('comm_amt') . ', ' . numeric_sql_expr('comm_tran_amt') . ', 0))';
        } elseif (isset($existing['comm_amt'])) {
            $commissionExpr = numeric_sql_expr('comm_amt');
        } elseif (isset($existing['comm_tran_amt'])) {
            $commissionExpr = numeric_sql_expr('comm_tran_amt');
        } else {
            $commissionExpr = '0';
        }
        $currencyCol = isset($existing['settlement_currency']) ? quote_identifier('settlement_currency') : '"PHP"';
        $sql = 'SELECT '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(' . $currencyCol . ')) = "PHP" THEN ' . $baseExpr . ' ELSE 0 END), 0) AS php_total, '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(' . $currencyCol . ')) = "USD" THEN ' . $baseExpr . ' ELSE 0 END), 0) AS usd_total, '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(' . $currencyCol . ')) = "PHP" THEN ' . $commissionExpr . ' ELSE 0 END), 0) AS php_commission_total, '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(' . $currencyCol . ')) = "USD" THEN ' . $commissionExpr . ' ELSE 0 END), 0) AS usd_commission_total '
            . 'FROM ' . $table . $whereSql;
    } elseif (isset($existing['php']) || isset($existing['usd'])) {
        $phpExpr = isset($existing['php']) ? numeric_sql_expr('php') : '0';
        $usdExpr = isset($existing['usd']) ? numeric_sql_expr('usd') : '0';
        $commissionPhpExpr = isset($existing['in_php']) ? numeric_sql_expr('in_php') : '0';
        $sql = 'SELECT '
            . 'COALESCE(SUM(' . $phpExpr . '), 0) AS php_total, '
            . 'COALESCE(SUM(' . $usdExpr . '), 0) AS usd_total, '
            . 'COALESCE(SUM(' . $commissionPhpExpr . '), 0) AS php_commission_total, '
            . '0 AS usd_commission_total '
            . 'FROM ' . $table . $whereSql;
    } else {
        $amountCol = first_existing_column(['amount', 'total_payable', 'bene_amt', 'in_php'], $existing);
        if ($amountCol === null) {
            return $zeroTotals;
        }
        $amountExpr = numeric_sql_expr($amountCol);
        $currencyCol = first_existing_column(['currency', 'coin', 'crc_code', 'settlement_currency'], $existing);
        $currencyExpr = $currencyCol !== null ? 'UPPER(TRIM(' . quote_identifier($currencyCol) . '))' : '"PHP"';
        $commissionCol = first_existing_column(['commission', 'comm_amt', 'comm_tran_amt', 'agent_commission', 'agent_commission_in_php'], $existing);
        $commissionExpr = $commissionCol !== null ? numeric_sql_expr($commissionCol) : '0';
        $sql = 'SELECT '
            . 'COALESCE(SUM(CASE WHEN ' . $currencyExpr . ' = "PHP" THEN ' . $amountExpr . ' ELSE 0 END), 0) AS php_total, '
            . 'COALESCE(SUM(CASE WHEN ' . $currencyExpr . ' = "USD" THEN ' . $amountExpr . ' ELSE 0 END), 0) AS usd_total, '
            . 'COALESCE(SUM(CASE WHEN ' . $currencyExpr . ' = "PHP" THEN ' . $commissionExpr . ' ELSE 0 END), 0) AS php_commission_total, '
            . 'COALESCE(SUM(CASE WHEN ' . $currencyExpr . ' = "USD" THEN ' . $commissionExpr . ' ELSE 0 END), 0) AS usd_commission_total '
            . 'FROM ' . $table . $whereSql;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'php_total' => isset($row['php_total']) ? (float)$row['php_total'] : 0.0,
        'usd_total' => isset($row['usd_total']) ? (float)$row['usd_total'] : 0.0,
        'php_commission_total' => isset($row['php_commission_total']) ? (float)$row['php_commission_total'] : 0.0,
        'usd_commission_total' => isset($row['usd_commission_total']) ? (float)$row['usd_commission_total'] : 0.0,
    ];
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
	$referenceIdFilter = trim((string)($_GET['reference_id'] ?? ''));
	$agentFilter = trim((string)($_GET['agent_name'] ?? ''));
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10000;

	// A reference-only lookup is a MONEYGRAM transaction search.
	if ($partner === '' && ($referenceIdFilter !== '' || $agentFilter !== '')) {
		$partner = 'MONEYGRAM';
	}

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

	if ($agentFilter !== '') {
        if (isset($existing['agent_name'])) {
            $whereParts[] = 'LOWER(COALESCE(agent_name, "")) LIKE ?';
            $params[] = '%' . strtolower($agentFilter) . '%';
        }
    }

    $typeFilter = isset($_GET['type']) ? trim((string)$_GET['type']) : (isset($_GET['tran_type']) ? trim((string)$_GET['tran_type']) : '');
    if (isset($existing['tran_type'])) {
        if ($table === 'moneygram_partner_data') {
            $moneygramTypeCodes = moneygram_transaction_type_codes($typeFilter);
            if (!empty($moneygramTypeCodes)) {
                $placeholders = implode(',', array_fill(0, count($moneygramTypeCodes), '?'));
                $whereParts[] = 'UPPER(TRIM(tran_type)) IN (' . $placeholders . ')';
                foreach ($moneygramTypeCodes as $code) {
                    $params[] = $code;
                }
            }
        } elseif ($typeFilter !== '') {
                $whereParts[] = 'tran_type = ?';
                $params[] = $typeFilter;
        }
    }

    $currencyFilter = strtoupper(trim((string)($_GET['settlement_currency'] ?? '')));
    $currencyCol = first_existing_column(['settlement_currency', 'currency', 'coin', 'crc_code'], $existing);
    if (in_array($currencyFilter, ['PHP', 'USD'], true) && $currencyCol !== null) {
        $whereParts[] = 'UPPER(TRIM(' . quote_identifier($currencyCol) . ')) = ?';
        $params[] = $currencyFilter;
    }

	if ($referenceIdFilter !== '' && isset($existing['reference_id'])) {
        $whereParts[] = 'TRIM(' . quote_identifier('reference_id') . ') = ?';
        $params[] = $referenceIdFilter;
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

    $partnerTotals = build_partner_totals($pdo, $table, $whereSql, $params, $existing);
    $moneygramPhpTotal = $partnerTotals['php_total'];
    $moneygramUsdTotal = $partnerTotals['usd_total'];
    $moneygramCommissionPhpTotal = $partnerTotals['php_commission_total'];
    $moneygramCommissionUsdTotal = $partnerTotals['usd_commission_total'];

    // For MONEYGRAM, extract the report display columns in defined order.
    $moneygramRows = [];
    if ($table === 'moneygram_partner_data') {
        $pickMoneygram = static function (array $row, array $keys) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                    return $row[$key];
                }
            }
            return '';
        };

        foreach ($rawRows as $row) {
            $moneygramRows[] = [
                'id' => $pickMoneygram($row, ['id']),
                'tran_date' => $pickMoneygram($row, ['tran_date']),
                'agent_name' => $pickMoneygram($row, ['agent_name']),
                'legacy_id' => $pickMoneygram($row, ['legacy_id']),
                'account_number' => $pickMoneygram($row, ['account_number']),
                'reference_id' => $pickMoneygram($row, ['reference_id']),
                'product' => $pickMoneygram($row, ['product']),
                'tran_type' => $pickMoneygram($row, ['tran_type']),
                'tran_fx_rate' => $pickMoneygram($row, ['tran_fx_rate', 'fx_rate_trn']),
                'fx_rev_share_amt' => $pickMoneygram($row, ['fx_rev_share_amt', 'fx_rev_share_tran_amt']),
                'base_amt' => $pickMoneygram($row, ['base_amt']),
                'comm_amt' => $pickMoneygram($row, ['comm_amt', 'comm_tran_amt']),
                'settlement_currency' => $pickMoneygram($row, ['settlement_currency']),
                'orig_cntry' => $pickMoneygram($row, ['orig_cntry']),
                'rcv_cntry' => $pickMoneygram($row, ['rcv_cntry']),
            ];
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
        'php_total' => $partnerTotals['php_total'],
        'usd_total' => $partnerTotals['usd_total'],
        'php_commission_total' => $partnerTotals['php_commission_total'],
        'usd_commission_total' => $partnerTotals['usd_commission_total'],
        'moneygram_php_total' => $moneygramPhpTotal,
        'moneygram_usd_total' => $moneygramUsdTotal,
        'moneygram_commission_php_total' => $moneygramCommissionPhpTotal,
        'moneygram_commission_usd_total' => $moneygramCommissionUsdTotal,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
