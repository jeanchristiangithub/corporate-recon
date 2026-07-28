<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function summary_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function summary_first_column(array $candidates, array $existing): ?string
{
    foreach ($candidates as $candidate) {
        foreach ($existing as $column => $_) {
            if (strcasecmp((string) $column, (string) $candidate) === 0) {
                return (string) $column;
            }
        }
    }

    return null;
}

function summary_numeric_expr(?string $column): string
{
    if ($column === null) {
        return '0';
    }

    $quoted = summary_quote_identifier($column);
    return 'ABS(CAST(REPLACE(REPLACE(REPLACE(COALESCE(' . $quoted . ', 0), ",", ""), "PHP", ""), "$", "") AS DECIMAL(18, 2)))';
}

function summary_normalized_partner(string $partner): string
{
    $partner = strtoupper(trim($partner));
    $partner = preg_replace('/[^A-Z0-9]+/', '', $partner) ?? $partner;
    return $partner;
}

function summary_partner_aliases(string $partner): array
{
    $normalized = summary_normalized_partner($partner);

    if ($normalized === '' || $normalized === 'MBTC' || $normalized === 'METROBANKHEADOFFICE') {
        return ['MBTC', 'METROBANK HEAD OFFICE'];
    }
    if ($normalized === 'MONEYGRAM') {
        return ['MONEYGRAM'];
    }
    if ($normalized === 'WIC' || $normalized === 'WORLDCOMINTERNATIONALCOMMUNICATIONS') {
        return ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS'];
    }
    if ($normalized === 'RCBC' || $normalized === 'RIZALCOMMERCIALBANKINGCORPORATION') {
        return ['RCBC', 'RIZAL COMMERCIAL BANKING CORPORATION'];
    }
    if ($normalized === 'SKYBRIDGE' || $normalized === 'SKYBRIDGEPAYMENTINC') {
        return ['SKYBRIDGE', 'SKYBRIDGE PAYMENT INC.', 'SKYBRIDGE PAYMENT INC'];
    }

    return [$partner];
}

function summary_partner_table(PDO $pdo, string $partner): string
{
    $normalized = summary_normalized_partner($partner);
    $known = [
        'MBTC' => 'mbtc_partner_data',
        'METROBANKHEADOFFICE' => 'mbtc_partner_data',
        'MONEYGRAM' => 'moneygram_partner_data',
        'WIC' => 'wic_partner_data',
        'WORLDCOMINTERNATIONALCOMMUNICATIONS' => 'wic_partner_data',
        'RCBC' => 'rcbc_partner_data',
        'RIZALCOMMERCIALBANKINGCORPORATION' => 'rcbc_partner_data',
        'SKYBRIDGE' => 'skybridgepaymentinc_partner_data',
        'SKYBRIDGEPAYMENTINC' => 'skybridgepaymentinc_partner_data',
    ];

    if (isset($known[$normalized])) {
        return $known[$normalized];
    }

    $fallback = strtolower($normalized) . '_partner_data';
    $stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $stmt->execute([$fallback]);
    if ($stmt->fetchColumn() !== false) {
        return $fallback;
    }

    throw new RuntimeException('Unsupported partner for summary report: ' . $partner);
}

function summary_columns(PDO $pdo, string $table): array
{
    $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    foreach ($rows as $row) {
        $field = (string) ($row['Field'] ?? '');
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}

function summary_currency_column(array $columns, bool $isWeb): ?string
{
    return $isWeb
        ? summary_first_column(['currency', 'web_currency', 'web_ccy', 'web_currency_code'], $columns)
        : summary_first_column(['coin', 'currency', 'settlement_currency', 'transaction_currency', 'partner_coin'], $columns);
}

function summary_fetch_daily(PDO $pdo, string $table, array $columns, array $aliases, string $startDate, string $endDate, bool $isWeb, ?string $currency = null, array $options = []): array
{
    $dateCandidates = isset($options['date_candidates']) && is_array($options['date_candidates']) ? $options['date_candidates'] : null;
    $amountCandidates = isset($options['amount_candidates']) && is_array($options['amount_candidates']) ? $options['amount_candidates'] : null;
    $commissionCandidates = isset($options['commission_candidates']) && is_array($options['commission_candidates']) ? $options['commission_candidates'] : null;
    $fxCandidates = isset($options['fx_candidates']) && is_array($options['fx_candidates']) ? $options['fx_candidates'] : null;
    $feeCandidates = isset($options['fee_candidates']) && is_array($options['fee_candidates']) ? $options['fee_candidates'] : null;
    $emptyCandidates = isset($options['require_empty_candidates']) && is_array($options['require_empty_candidates'])
        ? $options['require_empty_candidates']
        : [];

    $dateCol = $dateCandidates !== null
        ? summary_first_column($dateCandidates, $columns)
        : ($isWeb
        ? summary_first_column(['date_claimed', 'date'], $columns)
        : summary_first_column(['tran_date', 'cover_date', 'date_claimed', 'date', 'trans_date', 'withdraw_time', 'fx_date_trn'], $columns));

    if ($dateCol === null) {
        return [];
    }

    if ($isWeb) {
        $amountCol = summary_first_column($amountCandidates ?? ['amount', 'php', 'in_php'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['ctp', 'charge', 'commission', 'in_php'], $columns);
    } elseif ($table === 'moneygram_partner_data') {
        $amountCol = summary_first_column($amountCandidates ?? ['base_amt', 'base_tran_amt', 'total_tran_amt', 'amount'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['comm_amt', 'comm_tran_amt', 'fee_tran_amt', 'commission'], $columns);
        $fxCol = summary_first_column($fxCandidates ?? ['fx_rev_share_amt', 'fx_rev_share_tran_amt'], $columns);
    } else {
        $amountCol = summary_first_column($amountCandidates ?? ['php', 'amount', 'total_payable', 'bene_amt', 'in_php'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['in_php', 'commission', 'comm_amt', 'comm_tran_amt', 'agent_commission'], $columns);
    }
    $fxCol = $fxCol ?? summary_first_column($fxCandidates ?? [], $columns);
    $feeCol = $table === 'moneygram_partner_data'
        ? summary_first_column($feeCandidates ?? ['fee_tran_amt'], $columns)
        : null;

    $whereParts = ['DATE(' . summary_quote_identifier($dateCol) . ') BETWEEN ? AND ?'];
    $params = [$startDate, $endDate];

    $partnerCol = summary_first_column(['partnerName', 'partner_name', 'corporate_partner'], $columns);
    if ($partnerCol !== null) {
        $placeholders = implode(',', array_fill(0, count($aliases), '?'));
        $whereParts[] = 'UPPER(TRIM(' . summary_quote_identifier($partnerCol) . ')) IN (' . $placeholders . ')';
        foreach ($aliases as $alias) {
            $params[] = strtoupper(trim($alias));
        }
    }

    $currency = strtoupper(trim((string) $currency));
    $currencyCol = $currency !== '' ? summary_currency_column($columns, $isWeb) : null;
    if ($currency !== '' && $currencyCol !== null) {
        $whereParts[] = 'UPPER(TRIM(' . summary_quote_identifier($currencyCol) . ')) = ?';
        $params[] = $currency;
    }

    foreach ($emptyCandidates as $emptyCandidate) {
        $emptyColumn = summary_first_column([(string) $emptyCandidate], $columns);
        if ($emptyColumn === null) {
            continue;
        }
        $quotedEmptyColumn = summary_quote_identifier($emptyColumn);
        $whereParts[] = '(' . $quotedEmptyColumn . ' IS NULL OR TRIM(CAST(' . $quotedEmptyColumn . ' AS CHAR)) = "")';
    }

    foreach (($options['where'] ?? []) as $extraWhere) {
        if (!is_array($extraWhere) || empty($extraWhere['sql'])) {
            continue;
        }
        $whereParts[] = (string) $extraWhere['sql'];
        foreach (($extraWhere['params'] ?? []) as $param) {
            $params[] = $param;
        }
    }

    $sql = 'SELECT DATE(' . summary_quote_identifier($dateCol) . ') AS report_date, '
        . 'COUNT(*) AS volume, '
        . 'COALESCE(SUM(' . summary_numeric_expr($amountCol) . '), 0) AS principal, '
        . 'COALESCE(SUM(' . summary_numeric_expr($commissionCol) . '), 0) AS commission, '
        . 'COALESCE(SUM(' . summary_numeric_expr($fxCol) . '), 0) AS fx, '
        . 'COALESCE(SUM(' . summary_numeric_expr($feeCol) . '), 0) AS fee '
        . 'FROM ' . $table . ' WHERE ' . implode(' AND ', $whereParts)
        . ' GROUP BY DATE(' . summary_quote_identifier($dateCol) . ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $daily = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = (string) ($row['report_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $daily[$date] = [
            'volume' => (int) ($row['volume'] ?? 0),
            'principal' => (float) ($row['principal'] ?? 0),
            'commission' => (float) ($row['commission'] ?? 0),
            'fx' => (float) ($row['fx'] ?? 0),
            'fee' => (float) ($row['fee'] ?? 0),
        ];
    }

    return $daily;
}

function summary_fetch_duplicates(PDO $pdo, string $table, array $columns, array $aliases, string $startDate, string $endDate, bool $isWeb, ?string $currency = null, array $options = []): array
{
    $dateCandidates = isset($options['date_candidates']) && is_array($options['date_candidates']) ? $options['date_candidates'] : null;
    $amountCandidates = isset($options['amount_candidates']) && is_array($options['amount_candidates']) ? $options['amount_candidates'] : null;
    $commissionCandidates = isset($options['commission_candidates']) && is_array($options['commission_candidates']) ? $options['commission_candidates'] : null;

    $dateCol = $dateCandidates !== null
        ? summary_first_column($dateCandidates, $columns)
        : ($isWeb
        ? summary_first_column(['date_claimed', 'date'], $columns)
        : summary_first_column(['tran_date', 'cover_date', 'date_claimed', 'date', 'trans_date', 'withdraw_time', 'fx_date_trn'], $columns));

    $refCol = $isWeb
        ? summary_first_column(['ccref_no', 'cc_ref', 'reference_no', 'transaction_id'], $columns)
        : summary_first_column(['reference_no', 'reference_id', 'transaction_id', 'ref_no', 'payout_id', 'control_no'], $columns);

    if ($dateCol === null || $refCol === null) {
        return [];
    }

    if ($isWeb) {
        $amountCol = summary_first_column($amountCandidates ?? ['amount', 'php', 'in_php'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['ctp', 'charge', 'commission', 'in_php'], $columns);
    } elseif ($table === 'moneygram_partner_data') {
        $amountCol = summary_first_column($amountCandidates ?? ['base_amt', 'base_tran_amt', 'total_tran_amt', 'amount'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['comm_amt', 'comm_tran_amt', 'fee_tran_amt', 'commission'], $columns);
    } else {
        $amountCol = summary_first_column($amountCandidates ?? ['php', 'amount', 'total_payable', 'bene_amt', 'in_php'], $columns);
        $commissionCol = summary_first_column($commissionCandidates ?? ['in_php', 'commission', 'comm_amt', 'comm_tran_amt', 'agent_commission'], $columns);
    }

    $whereParts = ['DATE(' . summary_quote_identifier($dateCol) . ') BETWEEN ? AND ?'];
    $params = [$startDate, $endDate];

    $partnerCol = summary_first_column(['partnerName', 'partner_name', 'corporate_partner'], $columns);
    if ($partnerCol !== null) {
        $placeholders = implode(',', array_fill(0, count($aliases), '?'));
        $whereParts[] = 'UPPER(TRIM(' . summary_quote_identifier($partnerCol) . ')) IN (' . $placeholders . ')';
        foreach ($aliases as $alias) {
            $params[] = strtoupper(trim($alias));
        }
    }

    $currency = strtoupper(trim((string) $currency));
    $currencyCol = $currency !== '' ? summary_currency_column($columns, $isWeb) : null;
    if ($currency !== '' && $currencyCol !== null) {
        $whereParts[] = 'UPPER(TRIM(' . summary_quote_identifier($currencyCol) . ')) = ?';
        $params[] = $currency;
    }

    foreach (($options['where'] ?? []) as $extraWhere) {
        if (!is_array($extraWhere) || empty($extraWhere['sql'])) {
            continue;
        }
        $whereParts[] = (string) $extraWhere['sql'];
        foreach (($extraWhere['params'] ?? []) as $param) {
            $params[] = $param;
        }
    }

    $inner = 'SELECT DATE(' . summary_quote_identifier($dateCol) . ') AS report_date, '
        . summary_quote_identifier($refCol) . ' AS ref_key, '
        . 'COUNT(*) AS duplicate_count, '
        . 'SUM(' . summary_numeric_expr($amountCol) . ') AS duplicate_principal, '
        . 'SUM(' . summary_numeric_expr($commissionCol) . ') AS duplicate_commission '
        . 'FROM ' . $table . ' WHERE ' . implode(' AND ', $whereParts)
        . ' GROUP BY DATE(' . summary_quote_identifier($dateCol) . '), ' . summary_quote_identifier($refCol)
        . ' HAVING COUNT(*) > 1';

    $sql = 'SELECT report_date, '
        . 'COALESCE(SUM(duplicate_count), 0) AS volume, '
        . 'COALESCE(SUM(duplicate_principal), 0) AS principal, '
        . 'COALESCE(SUM(duplicate_commission), 0) AS commission '
        . 'FROM (' . $inner . ') d GROUP BY report_date';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $daily = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = (string) ($row['report_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $daily[$date] = [
            'volume' => (int) ($row['volume'] ?? 0),
            'principal' => (float) ($row['principal'] ?? 0),
            'commission' => (float) ($row['commission'] ?? 0),
        ];
    }

    return $daily;
}

function summary_empty_amounts(): array
{
    return ['volume' => 0, 'principal' => 0.0, 'commission' => 0.0, 'fx' => 0.0, 'fee' => 0.0];
}

function summary_add_amounts(array $left, array $right): array
{
    return [
        'volume' => (int) ($left['volume'] ?? 0) + (int) ($right['volume'] ?? 0),
        'principal' => (float) ($left['principal'] ?? 0) + (float) ($right['principal'] ?? 0),
        'commission' => (float) ($left['commission'] ?? 0) + (float) ($right['commission'] ?? 0),
        'fx' => (float) ($left['fx'] ?? 0) + (float) ($right['fx'] ?? 0),
        'fee' => (float) ($left['fee'] ?? 0) + (float) ($right['fee'] ?? 0),
    ];
}

function summary_subtract_amounts(array $left, array $right): array
{
    return [
        'volume' => (int) ($left['volume'] ?? 0) - (int) ($right['volume'] ?? 0),
        'principal' => (float) ($left['principal'] ?? 0) - (float) ($right['principal'] ?? 0),
        'commission' => (float) ($left['commission'] ?? 0) - (float) ($right['commission'] ?? 0),
        'fx' => (float) ($left['fx'] ?? 0) - (float) ($right['fx'] ?? 0),
        'fee' => (float) ($left['fee'] ?? 0) - (float) ($right['fee'] ?? 0),
    ];
}

function summary_column_equals_where(array $columns, array $candidates, string $value): array
{
    $column = summary_first_column($candidates, $columns);
    if ($column === null) {
        return [];
    }

    return [[
        'sql' => 'UPPER(TRIM(' . summary_quote_identifier($column) . ')) = ?',
        'params' => [strtoupper(trim($value))],
    ]];
}

function summary_column_is_nullish_where(array $columns, array $candidates): array
{
    $column = summary_first_column($candidates, $columns);
    if ($column === null) {
        return [];
    }

    $quoted = summary_quote_identifier($column);
    return [[
        'sql' => '(' . $quoted . ' IS NULL OR TRIM(CAST(' . $quoted . ' AS CHAR)) = "")',
        'params' => [],
    ]];
}

function summary_column_is_not_nullish_where(array $columns, array $candidates): array
{
    $column = summary_first_column($candidates, $columns);
    if ($column === null) {
        return [];
    }

    $quoted = summary_quote_identifier($column);
    return [[
        'sql' => '(' . $quoted . ' IS NOT NULL AND TRIM(CAST(' . $quoted . ' AS CHAR)) <> "")',
        'params' => [],
    ]];
}

function summary_moneygram_settlement_readiness(PDO $pdo, string $startDate, string $endDate, string $partner): array
{
    $partnerDates = [];
    $stmt = $pdo->prepare(
        'SELECT DISTINCT DATE(tran_date) AS report_date'
        . ' FROM moneygram_partner_data'
        . ' WHERE tran_date BETWEEN ? AND ?'
    );
    $stmt->execute([$startDate, $endDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $partnerDates[(string) $date] = true;
    }

    $settlementDates = [];
    $stmt = $pdo->prepare(
        'SELECT DISTINCT DATE(tran_date) AS report_date'
        . ' FROM partner_settlement_data'
        . ' WHERE tran_date BETWEEN ? AND ?'
        . ' AND UPPER(TRIM(partner_name)) = ?'
    );
    $stmt->execute([$startDate, $endDate, strtoupper(trim($partner))]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $settlementDates[(string) $date] = true;
    }

    $lockedDates = [];
    $stmt = $pdo->prepare(
        'SELECT DISTINCT transaction_date'
        . ' FROM locked_reconciliation_dates'
        . ' WHERE transaction_date BETWEEN ? AND ?'
        . ' AND UPPER(TRIM(corporate_partner)) = ?'
    );
    $stmt->execute([$startDate, $endDate, strtoupper(trim($partner))]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $lockedDates[(string) $date] = true;
    }

    $readiness = [];
    $cursor = DateTime::createFromFormat('!Y-m-d', $startDate);
    $lastDate = DateTime::createFromFormat('!Y-m-d', $endDate);
    if (!$cursor || !$lastDate) {
        throw new RuntimeException('Invalid Settlement readiness date range.');
    }
    while ($cursor <= $lastDate) {
        $date = $cursor->format('Y-m-d');
        $hasPartnerData = isset($partnerDates[$date]);
        $hasSettlementData = isset($settlementDates[$date]);
        $isLocked = isset($lockedDates[$date]);
        $message = '';
        if (!$isLocked && !$hasPartnerData && !$hasSettlementData) {
            $message = 'Need to Upload Partner Data and Settlement Data and Lock Data first to see result.';
        } elseif (!$isLocked && !$hasPartnerData) {
            $message = 'Need to Upload Partner Data and Lock Data first to see result.';
        } elseif (!$isLocked && !$hasSettlementData) {
            $message = 'Need to Upload Settlement Data and Lock Data first to see result.';
        } elseif (!$isLocked) {
            $message = 'Need to Lock Data first to see result.';
        } elseif (!$hasSettlementData) {
            $message = 'Need to Upload Settlement Data first to see result.';
        }
        $readiness[$date] = [
            'has_partner_data' => $hasPartnerData,
            'has_settlement_data' => $hasSettlementData,
            'is_locked' => $isLocked,
            'message' => $message,
        ];
        $cursor->modify('+1 day');
    }

    return $readiness;
}

function summary_moneygram_cover_readiness(PDO $pdo, string $startDate, string $endDate, string $partner, string $cover): array
{
    $partnerDates = [];
    $stmt = $pdo->prepare(
        'SELECT DISTINCT DATE(tran_date) AS report_date'
        . ' FROM moneygram_partner_data'
        . ' WHERE tran_date BETWEEN ? AND ?'
    );
    $stmt->execute([$startDate, $endDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $partnerDates[(string) $date] = true;
    }

    $isSendout = strtolower($cover) === 'sendout';
    $primaryDateColumn = $isSendout ? 'date_send' : 'date_claimed';
    $additionalCondition = $isSendout
        ? 'date_send IS NOT NULL'
        : '(date_send IS NULL OR TRIM(CAST(date_send AS CHAR)) = "") AND date_claimed IS NOT NULL';
    $webDates = [];
    $sql = 'SELECT DISTINCT report_date FROM ('
        . ' SELECT DATE(' . summary_quote_identifier($primaryDateColumn) . ') AS report_date'
        . ' FROM ml_web_data'
        . ' WHERE ' . summary_quote_identifier($primaryDateColumn) . ' BETWEEN ? AND ?'
        . ' AND UPPER(TRIM(partnerName)) = ?'
        . ' UNION'
        . ' SELECT DATE(date_cancelled) AS report_date'
        . ' FROM ml_web_data'
        . ' WHERE date_cancelled BETWEEN ? AND ?'
        . ' AND ' . $additionalCondition
        . ' AND UPPER(TRIM(partnerName)) = ?'
        . ') web_dates WHERE report_date IS NOT NULL';
    $stmt = $pdo->prepare($sql);
    $normalizedPartner = strtoupper(trim($partner));
    $stmt->execute([
        $startDate,
        $endDate,
        $normalizedPartner,
        $startDate,
        $endDate,
        $normalizedPartner,
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $webDates[(string) $date] = true;
    }

    $lockedDates = [];
    $stmt = $pdo->prepare(
        'SELECT DISTINCT transaction_date'
        . ' FROM locked_reconciliation_dates'
        . ' WHERE transaction_date BETWEEN ? AND ?'
        . ' AND UPPER(TRIM(corporate_partner)) = ?'
    );
    $stmt->execute([$startDate, $endDate, $normalizedPartner]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        $lockedDates[(string) $date] = true;
    }

    $readiness = [];
    $cursor = DateTime::createFromFormat('!Y-m-d', $startDate);
    $lastDate = DateTime::createFromFormat('!Y-m-d', $endDate);
    if (!$cursor || !$lastDate) {
        throw new RuntimeException('Invalid MoneyGram cover readiness date range.');
    }
    while ($cursor <= $lastDate) {
        $date = $cursor->format('Y-m-d');
        $hasPartnerData = isset($partnerDates[$date]);
        $hasWebData = isset($webDates[$date]);
        $isLocked = isset($lockedDates[$date]);
        $message = '';
        if (!$isLocked && !$hasPartnerData && !$hasWebData) {
            $message = 'Need to Upload Partner Data, KPX Web Data and Lock Data first to see result.';
        } elseif (!$isLocked && !$hasPartnerData) {
            $message = 'Need to Upload Partner Data and Lock Data first to see result.';
        } elseif (!$isLocked && !$hasWebData) {
            $message = 'Need to Upload KPX Web Data and Lock Data first to see result.';
        } elseif (!$isLocked) {
            $message = 'Need to Lock Data first to see result.';
        } elseif (!$hasPartnerData) {
            $message = 'Need to Upload Partner Data first to see result.';
        } elseif (!$hasWebData) {
            $message = 'Need to Upload KPX Web Data first to see result.';
        }
        $readiness[$date] = [
            'has_partner_data' => $hasPartnerData,
            'has_kpx_web_data' => $hasWebData,
            'is_locked' => $isLocked,
            'message' => $message,
        ];
        $cursor->modify('+1 day');
    }

    return $readiness;
}

function summary_fetch_moneygram_settlement_report(PDO $pdo, string $startDate, string $endDate, string $currency, array $readiness = []): array
{
    $amountFields = [
        'principal' => 'base_tran_amt',
        'fee' => 'fee_tran_amt',
        'fx' => 'fx_rev_share_tran_amt',
        'commission' => 'comm_tran_amt',
    ];
    $reportDateExpression = 'DATE(CASE'
        . ' WHEN psd.settled_date BETWEEN ? AND ? THEN psd.settled_date'
        . ' ELSE psd.tran_date END)';
    $selects = [
        $reportDateExpression . ' AS report_date',
        "SUM(CASE WHEN UPPER(TRIM(psd.tran_type)) = 'REC' THEN 1 WHEN UPPER(TRIM(psd.tran_type)) = 'RRC' THEN -1 ELSE 0 END) AS payout_volume",
        "SUM(CASE WHEN UPPER(TRIM(psd.tran_type)) = 'SEN' THEN 1 WHEN UPPER(TRIM(psd.tran_type)) IN ('RSN', 'REF') THEN -1 ELSE 0 END) AS sendout_volume",
    ];
    foreach ($amountFields as $key => $column) {
        $qualifiedColumn = 'psd.' . summary_quote_identifier($column);
        $numeric = 'ABS(CAST(REPLACE(REPLACE(REPLACE(COALESCE(' . $qualifiedColumn . ', 0), ",", ""), "PHP", ""), "$", "") AS DECIMAL(18, 2)))';
        $selects[] = "SUM(CASE WHEN UPPER(TRIM(psd.tran_type)) = 'REC' THEN {$numeric} WHEN UPPER(TRIM(psd.tran_type)) = 'RRC' THEN -{$numeric} ELSE 0 END) AS payout_{$key}";
        $selects[] = "SUM(CASE WHEN UPPER(TRIM(psd.tran_type)) = 'SEN' THEN {$numeric} WHEN UPPER(TRIM(psd.tran_type)) IN ('RSN', 'REF') THEN -{$numeric} ELSE 0 END) AS sendout_{$key}";
    }

    $sql = 'SELECT ' . implode(', ', $selects)
        . ' FROM partner_settlement_data psd'
        . ' LEFT JOIN moneygram_partner_data mpd'
        . ' ON psd.tran_date = mpd.tran_date'
        . ' AND psd.reference_id = mpd.reference_id'
        . ' AND psd.tran_type = mpd.tran_type'
        . ' WHERE (psd.tran_date BETWEEN ? AND ? OR psd.settled_date BETWEEN ? AND ?)'
        . ' AND (mpd.reference_id IS NOT NULL OR psd.settled_date BETWEEN ? AND ?)'
        . ' AND UPPER(TRIM(psd.transaction_currency)) = ?'
        . " AND UPPER(TRIM(psd.tran_type)) IN ('REC', 'RRC', 'SEN', 'RSN', 'REF')"
        . ' GROUP BY report_date ORDER BY report_date';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $startDate,
        $endDate,
        $startDate,
        $endDate,
        $startDate,
        $endDate,
        $startDate,
        $endDate,
        strtoupper(trim($currency)),
    ]);

    $dailyRows = [];
    $totals = [
        'payout' => summary_empty_amounts(),
        'sendout' => summary_empty_amounts(),
        'settlement_volume' => 0,
        'settlement_amount' => 0.0,
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $payout = ['volume' => (int) ($record['payout_volume'] ?? 0)];
        $sendout = ['volume' => (int) ($record['sendout_volume'] ?? 0)];
        foreach (array_keys($amountFields) as $key) {
            $payout[$key] = (float) ($record['payout_' . $key] ?? 0);
            $sendout[$key] = (float) ($record['sendout_' . $key] ?? 0);
        }
        $settlementAmount = $payout['principal']
            - $sendout['principal']
            - $sendout['fee']
            + $sendout['commission'];
        $settlementVolume = $payout['volume'] + $sendout['volume'];
        $row = [
            'date' => (string) ($record['report_date'] ?? ''),
            'payout' => $payout,
            'sendout' => $sendout,
            'settlement_volume' => $settlementVolume,
            'settlement_amount' => $settlementAmount,
        ];
        if ($row['date'] !== '') {
            $dailyRows[$row['date']] = $row;
        }
    }

    $rows = [];
    $cursor = DateTime::createFromFormat('!Y-m-d', $startDate);
    $lastDate = DateTime::createFromFormat('!Y-m-d', $endDate);
    if (!$cursor || !$lastDate) {
        throw new RuntimeException('Invalid Settlement report date range.');
    }
    while ($cursor <= $lastDate) {
        $date = $cursor->format('Y-m-d');
        $row = $dailyRows[$date] ?? [
            'date' => $date,
            'payout' => summary_empty_amounts(),
            'sendout' => summary_empty_amounts(),
            'settlement_volume' => 0,
            'settlement_amount' => 0.0,
        ];
        $row['readiness'] = $readiness[$date] ?? [
            'has_partner_data' => false,
            'has_settlement_data' => false,
            'is_locked' => false,
            'message' => '',
        ];
        $row['status_message'] = (string) ($row['readiness']['message'] ?? '');
        if ($row['status_message'] !== '') {
            $row['payout'] = summary_empty_amounts();
            $row['sendout'] = summary_empty_amounts();
            $row['settlement_volume'] = 0;
            $row['settlement_amount'] = 0.0;
        } else {
            $totals['payout'] = summary_add_amounts($totals['payout'], $row['payout']);
            $totals['sendout'] = summary_add_amounts($totals['sendout'], $row['sendout']);
            $totals['settlement_volume'] += (int) $row['settlement_volume'];
            $totals['settlement_amount'] += (float) $row['settlement_amount'];
        }
        $rows[] = $row;
        $cursor->modify('+1 day');
    }

    return [
        'partner' => 'MONEYGRAM',
        'currency' => strtoupper(trim($currency)),
        'rows' => $rows,
        'totals' => $totals,
    ];
}

function summary_column_in_where(array $columns, array $candidates, array $values): array
{
    $column = summary_first_column($candidates, $columns);
    $normalizedValues = array_values(array_filter(array_map(
        static fn ($value): string => strtoupper(trim((string) $value)),
        $values
    ), static fn (string $value): bool => $value !== ''));

    if ($column === null || count($normalizedValues) === 0) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($normalizedValues), '?'));
    return [[
        'sql' => 'UPPER(TRIM(' . summary_quote_identifier($column) . ')) IN (' . $placeholders . ')',
        'params' => $normalizedValues,
    ]];
}

function summary_web_has_no_cancellation_where(array $columns, string $table): array
{
    $cancelledCol = summary_first_column(['date_cancelled', 'date_cancellation'], $columns);
    $referenceColumns = [];
    foreach (['ccref_no', 'kptn', 'control_series_no'] as $candidate) {
        $column = summary_first_column([$candidate], $columns);
        if ($column !== null) {
            $referenceColumns[] = $column;
        }
    }

    if ($cancelledCol === null || count($referenceColumns) === 0) {
        return summary_column_is_nullish_where($columns, ['date_cancelled', 'date_cancellation']);
    }

    $quotedTable = summary_quote_identifier($table);
    $quotedCancelled = summary_quote_identifier($cancelledCol);
    $exclusions = [];
    foreach ($referenceColumns as $referenceColumn) {
        $quotedReference = summary_quote_identifier($referenceColumn);
        $exclusions[] = '(TRIM(COALESCE(' . $quotedTable . '.' . $quotedReference . ', "")) = ""'
            . ' OR ' . $quotedTable . '.' . $quotedReference . ' NOT IN (SELECT cancelled_match.' . $quotedReference
            . ' FROM ' . $quotedTable . ' cancelled_match'
            . ' WHERE cancelled_match.' . $quotedCancelled . ' IS NOT NULL'
            . ' AND TRIM(CAST(cancelled_match.' . $quotedCancelled . ' AS CHAR)) <> ""'
            . ' AND TRIM(COALESCE(cancelled_match.' . $quotedReference . ', "")) <> ""))';
    }

    return [[
        'sql' => '(' . $quotedTable . '.' . $quotedCancelled . ' IS NULL OR TRIM(CAST(' . $quotedTable . '.' . $quotedCancelled . ' AS CHAR)) = "")'
            . ' AND ' . implode(' AND ', $exclusions),
        'params' => [],
    ]];
}

function summary_merge_where(array ...$groups): array
{
    $merged = [];
    foreach ($groups as $group) {
        foreach ($group as $where) {
            $merged[] = $where;
        }
    }

    return $merged;
}

function summary_build_rows_and_totals(DateTime $startObj, string $endDate, array $partnerDaily, array $webDaily, array $duplicateDaily, array $partnerCancelledDaily = [], array $webCancelledDaily = [], bool $varianceFromNetPartnerAndWeb = false, array $readiness = []): array
{
    $rows = [];
    $totals = [
        'partner' => summary_empty_amounts(),
        'partner_cancelled' => summary_empty_amounts(),
        'net_partner' => summary_empty_amounts(),
        'web' => summary_empty_amounts(),
        'cancelled' => summary_empty_amounts(),
        'duplicates' => summary_empty_amounts(),
        'net_web' => summary_empty_amounts(),
        'variance' => summary_empty_amounts(),
        'deposit' => ['debit' => 0.0, 'credit' => 0.0, 'variance' => 0.0],
    ];

    $cursor = clone $startObj;
    while ($cursor->format('Y-m-d') <= $endDate) {
        $date = $cursor->format('Y-m-d');
        $partnerAmounts = $partnerDaily[$date] ?? summary_empty_amounts();
        $partnerCancelledAmounts = $partnerCancelledDaily[$date] ?? summary_empty_amounts();
        $netPartnerAmounts = summary_subtract_amounts($partnerAmounts, $partnerCancelledAmounts);
        $webAmounts = $webDaily[$date] ?? summary_empty_amounts();
        $cancelledAmounts = $webCancelledDaily[$date] ?? summary_empty_amounts();
        $duplicateAmounts = $duplicateDaily[$date] ?? summary_empty_amounts();
        $netWeb = summary_subtract_amounts(summary_subtract_amounts($webAmounts, $cancelledAmounts), $duplicateAmounts);
        $variance = $varianceFromNetPartnerAndWeb
            ? summary_subtract_amounts($netPartnerAmounts, $webAmounts)
            : summary_subtract_amounts($partnerAmounts, $netWeb);
        if ($varianceFromNetPartnerAndWeb) {
            $variance['commission'] = $netPartnerAmounts['commission'];
        }
        $depositVariance = 0 - ((float) $netWeb['principal'] + (float) $netWeb['commission']);

        $row = [
            'date' => $date,
            'partner' => $partnerAmounts,
            'partner_cancelled' => $partnerCancelledAmounts,
            'refund' => $partnerCancelledAmounts,
            'net_partner' => $netPartnerAmounts,
            'web' => $webAmounts,
            'cancelled' => $cancelledAmounts,
            'duplicates' => $duplicateAmounts,
            'net_web' => $netWeb,
            'variance' => $variance,
            'deposit' => ['debit' => 0.0, 'credit' => 0.0, 'variance' => $depositVariance],
        ];
        if (isset($readiness[$date])) {
            $row['readiness'] = $readiness[$date];
            $row['status_message'] = (string) ($readiness[$date]['message'] ?? '');
        }
        if (!empty($row['status_message'])) {
            foreach (['partner', 'partner_cancelled', 'refund', 'net_partner', 'web', 'cancelled', 'duplicates', 'net_web', 'variance'] as $key) {
                $row[$key] = summary_empty_amounts();
            }
            $row['deposit'] = ['debit' => 0.0, 'credit' => 0.0, 'variance' => 0.0];
        }

        foreach (['partner', 'partner_cancelled', 'net_partner', 'web', 'cancelled', 'duplicates', 'net_web', 'variance'] as $key) {
            $totals[$key] = summary_add_amounts($totals[$key], $row[$key]);
        }
        $totals['deposit']['variance'] += (float) $row['deposit']['variance'];
        $rows[] = $row;

        $cursor->modify('+1 day');
    }

    return ['rows' => $rows, 'totals' => $totals];
}

try {
    $partner = trim((string) ($_GET['partner'] ?? 'MBTC'));
    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));

    if ($partner === '') {
        $partner = 'MBTC';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo json_encode(['success' => false, 'error' => 'Start date and end date are required.']);
        exit;
    }

    $startObj = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObj = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$startObj || !$endObj || $startObj->format('Y-m-d') !== $startDate || $endObj->format('Y-m-d') !== $endDate) {
        echo json_encode(['success' => false, 'error' => 'Invalid date range.']);
        exit;
    }
    if ($startDate > $endDate) {
        echo json_encode(['success' => false, 'error' => 'Start date cannot be greater than end date.']);
        exit;
    }

    $pdo = fileRecDbConnection();
    $aliases = summary_partner_aliases($partner);
    $partnerTable = summary_partner_table($pdo, $partner);
    $partnerColumns = summary_columns($pdo, $partnerTable);
    $webColumns = summary_columns($pdo, 'ml_web_data');

    $normalizedPartner = summary_normalized_partner($partner);
    $isWic = $normalizedPartner === 'WIC' || $normalizedPartner === 'WORLDCOMINTERNATIONALCOMMUNICATIONS';
    $isMoneygram = $normalizedPartner === 'MONEYGRAM';

    if ($isMoneygram) {
        $currencyReports = [];
        $sendoutReports = [];
        $settlementReports = [];
        $payoutPartnerWhere = summary_column_equals_where($partnerColumns, ['tran_type', 'transaction_type'], 'REC');
        $payoutCancelledPartnerWhere = summary_column_equals_where($partnerColumns, ['tran_type', 'transaction_type'], 'RRC');
        $sendoutPartnerWhere = summary_column_equals_where($partnerColumns, ['tran_type', 'transaction_type'], 'SEN');
        $sendoutCancelledPartnerWhere = summary_column_in_where($partnerColumns, ['tran_type', 'transaction_type'], ['RSN', 'REF']);
        $payoutWebWhere = summary_column_is_nullish_where($webColumns, ['date_send']);
        $sendoutWebWhere = summary_column_is_not_nullish_where($webColumns, ['date_send']);
        $webCancellationWhere = summary_column_is_not_nullish_where($webColumns, ['date_cancelled', 'date_cancellation']);
        $kpxCancellationCandidates = ['date_cancelled', 'date_cancellation'];
        $webNotCancelledWhere = summary_column_is_nullish_where($webColumns, $kpxCancellationCandidates);
        $settlementReadiness = summary_moneygram_settlement_readiness($pdo, $startDate, $endDate, 'MONEYGRAM');
        $payoutReadiness = summary_moneygram_cover_readiness($pdo, $startDate, $endDate, 'MONEYGRAM', 'payout');
        $sendoutReadiness = summary_moneygram_cover_readiness($pdo, $startDate, $endDate, 'MONEYGRAM', 'sendout');

        foreach (['PHP', 'USD'] as $currencyCode) {
            $payoutPartnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false, $currencyCode, [
                'where' => $payoutPartnerWhere,
            ]);
            $payoutCancelledPartnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false, $currencyCode, [
                'where' => $payoutCancelledPartnerWhere,
            ]);
            $payoutWebDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_claimed', 'date'],
                'require_empty_candidates' => $kpxCancellationCandidates,
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['ctp', 'commission', 'in_php'],
                'where' => $payoutWebWhere,
            ]);
            $payoutDuplicateDaily = summary_fetch_duplicates($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_claimed', 'date'],
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['ctp', 'commission', 'in_php'],
                'where' => summary_merge_where($payoutWebWhere, $webNotCancelledWhere),
            ]);
            $payoutWebCancelledDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_cancelled', 'date_cancellation'],
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['ctp', 'commission', 'in_php'],
                'where' => summary_merge_where(
                    $webCancellationWhere,
                    $payoutWebWhere,
                    summary_column_is_not_nullish_where($webColumns, ['date_claimed'])
                ),
            ]);
            $currencyReports[strtolower($currencyCode)] = summary_build_rows_and_totals(
                $startObj,
                $endDate,
                $payoutPartnerDaily,
                $payoutWebDaily,
                $payoutDuplicateDaily,
                $payoutCancelledPartnerDaily,
                $payoutWebCancelledDaily,
                true,
                $payoutReadiness
            );
            $currencyReports[strtolower($currencyCode)]['currency'] = $currencyCode;

            $sendoutPartnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false, $currencyCode, [
                'where' => $sendoutPartnerWhere,
            ]);
            $sendoutCancelledPartnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false, $currencyCode, [
                'where' => $sendoutCancelledPartnerWhere,
            ]);
            $sendoutWebDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_send', 'date_claimed', 'date'],
                'require_empty_candidates' => $kpxCancellationCandidates,
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['charge', 'ctp', 'commission', 'in_php'],
                'where' => $sendoutWebWhere,
            ]);
            $sendoutDuplicateDaily = summary_fetch_duplicates($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_send', 'date_claimed', 'date'],
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['charge', 'ctp', 'commission', 'in_php'],
                'where' => summary_merge_where($sendoutWebWhere, $webNotCancelledWhere),
            ]);
            $sendoutWebCancelledDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode, [
                'date_candidates' => ['date_cancelled', 'date_cancellation'],
                'amount_candidates' => ['amount', 'php', 'in_php'],
                'commission_candidates' => ['charge', 'ctp', 'commission', 'in_php'],
                'where' => summary_merge_where($webCancellationWhere, $sendoutWebWhere),
            ]);
            $sendoutReports[strtolower($currencyCode)] = summary_build_rows_and_totals(
                $startObj,
                $endDate,
                $sendoutPartnerDaily,
                $sendoutWebDaily,
                $sendoutDuplicateDaily,
                $sendoutCancelledPartnerDaily,
                $sendoutWebCancelledDaily,
                true,
                $sendoutReadiness
            );
            $sendoutReports[strtolower($currencyCode)]['currency'] = $currencyCode;
            $settlementReports[strtolower($currencyCode)] = summary_fetch_moneygram_settlement_report(
                $pdo,
                $startDate,
                $endDate,
                $currencyCode,
                $settlementReadiness
            );
        }

        $rows = $currencyReports['php']['rows'];
        $totals = $currencyReports['php']['totals'];
    } elseif ($isWic) {
        $currencyReports = [];
        foreach (['PHP', 'USD'] as $currencyCode) {
            $partnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false, $currencyCode);
            $webDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode);
            $duplicateDaily = summary_fetch_duplicates($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true, $currencyCode);
            $currencyReports[strtolower($currencyCode)] = summary_build_rows_and_totals($startObj, $endDate, $partnerDaily, $webDaily, $duplicateDaily);
            $currencyReports[strtolower($currencyCode)]['currency'] = $currencyCode;
        }

        $rows = $currencyReports['php']['rows'];
        $totals = $currencyReports['php']['totals'];
        $sendoutReports = null;
        $settlementReports = null;
    } else {
        $partnerDaily = summary_fetch_daily($pdo, $partnerTable, $partnerColumns, $aliases, $startDate, $endDate, false);
        $webDaily = summary_fetch_daily($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true);
        $duplicateDaily = summary_fetch_duplicates($pdo, 'ml_web_data', $webColumns, $aliases, $startDate, $endDate, true);
        $builtReport = summary_build_rows_and_totals($startObj, $endDate, $partnerDaily, $webDaily, $duplicateDaily);
        $rows = $builtReport['rows'];
        $totals = $builtReport['totals'];
        $currencyReports = null;
        $sendoutReports = null;
        $settlementReports = null;
    }

    $response = [
        'success' => true,
        'partner' => $aliases[0] ?? $partner,
        'partner_input' => $partner,
        'partner_aliases' => $aliases,
        'partner_table' => $partnerTable,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'rows' => $rows,
        'totals' => $totals,
    ];

    if ($currencyReports !== null) {
        $response['currency_reports'] = $currencyReports;
    }
    if ($sendoutReports !== null) {
        $response['sendout_reports'] = $sendoutReports;
    }
    if ($settlementReports !== null) {
        $response['settlement_reports'] = $settlementReports;
    }

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
