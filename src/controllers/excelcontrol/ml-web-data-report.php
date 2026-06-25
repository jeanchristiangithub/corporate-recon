<?php
// ml-web-data-report.php
// Fetch filtered transactions from ml_web_data table
// Used by reports section to display web data transactions

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $partner = isset($_GET['partner']) ? trim((string)$_GET['partner']) : '';
    $start_date = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
    // additional filters
    $mainzone = isset($_GET['mainzone']) ? trim((string)$_GET['mainzone']) : '';
    $zone = isset($_GET['zone']) ? trim((string)$_GET['zone']) : '';
    $region = isset($_GET['region']) ? trim((string)$_GET['region']) : '';
    $area = isset($_GET['area']) ? trim((string)$_GET['area']) : '';
    $branch_name = isset($_GET['branch_name']) ? trim((string)$_GET['branch_name']) : '';
    $branch_id = isset($_GET['branch_id']) ? trim((string)$_GET['branch_id']) : '';
    $currency = strtoupper(trim((string)($_GET['currency'] ?? '')));

    // Treat 'ALL' (case-insensitive) as empty / no-filter
    if (strcasecmp($mainzone, 'ALL') === 0) $mainzone = '';
    if (strcasecmp($zone, 'ALL') === 0) $zone = '';
    if (strcasecmp($region, 'ALL') === 0) $region = '';
    if (strcasecmp($area, 'ALL') === 0) $area = '';
    if (strcasecmp($branch_name, 'ALL') === 0) $branch_name = '';
    if (strcasecmp($branch_id, 'ALL') === 0) $branch_id = '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10000;

    if ($page < 1) {
        $page = 1;
    }

    if ($perPage < 1 || $perPage > 10000) {
        $perPage = 10000;
    }
    
    if ($partner === '') {
        echo json_encode(['success' => false, 'error' => 'Corporate partner is required']);
        exit;
    }

    // Enforce that both start_date and end_date are provided
    if ($start_date === '' || $end_date === '') {
        echo json_encode(['success' => false, 'error' => 'Start date and End date are required']);
        exit;
    }
    
    $pdo = fileRecDbConnection();

    $availableColumns = [];
    try {
        $columnRows = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columnRows as $columnRow) {
            $field = strtolower((string)($columnRow['Field'] ?? ''));
            if ($field !== '') {
                $availableColumns[$field] = true;
            }
        }
    } catch (Throwable $e) {
        $availableColumns = [];
    }

    $branchNameColumn = isset($availableColumns['branch_name']) ? 'branch_name' : (isset($availableColumns['branch']) ? 'branch' : null);

    // Read optional type filter (all / payout / sendout)
    $type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';

    // Base filter by partner name
    $whereSql = ' FROM ml_web_data WHERE partnerName = ?';
    $params = [$partner];
    
    // Decide which date column to use for filtering/ordering and for the UI date column.
    $dateColumn = 'date_claimed';
    if (strtolower($type) === 'sendout' && isset($availableColumns['date_send'])) {
        $dateColumn = 'date_send';
    }
    $dateLabel = $dateColumn === 'date_send' ? 'Date Send' : 'Date Claimed';

    // Add date filters if provided (using chosen date column)
    if ($start_date !== '') {
        $whereSql .= ' AND DATE(' . $dateColumn . ') >= ?';
        $params[] = $start_date;
    }
    
    if ($end_date !== '') {
        $whereSql .= ' AND DATE(' . $dateColumn . ') <= ?';
        $params[] = $end_date;
    }

    // initialize totals to zero; will compute after applying all filters
    $php_total = 0.0;
    $usd_total = 0.0;
    $charge_total = 0.0;
    $php_commission_total = 0.0;
    $usd_commission_total = 0.0;

    // Apply transaction-type specific filters based on presence of date_send
    // PAYOUT: date_send IS NULL OR date_send = ''
    // SENDOUT: date_send IS NOT NULL AND date_send != ''
    if (isset($availableColumns['date_send'])) {
        if (strtolower($type) === 'payout') {
            $whereSql .= ' AND (date_send IS NULL OR TRIM(COALESCE(date_send, "")) = "")';
        } elseif (strtolower($type) === 'sendout') {
            $whereSql .= ' AND (date_send IS NOT NULL AND TRIM(COALESCE(date_send, "")) != "")';
        }
    }

    // Apply ml_web_data filters directly so table, count, and CSV all reflect the same data set.
    if ($mainzone !== '' && isset($availableColumns['mainzone'])) {
        $whereSql .= ' AND TRIM(COALESCE(mainzone, "")) = TRIM(?)';
        $params[] = $mainzone;
    }

    if ($zone !== '' && isset($availableColumns['zone'])) {
        $whereSql .= ' AND TRIM(COALESCE(zone, "")) = TRIM(?)';
        $params[] = $zone;
    }

    if ($region !== '' && isset($availableColumns['region'])) {
        $whereSql .= ' AND TRIM(COALESCE(region, "")) = TRIM(?)';
        $params[] = $region;
    }

    if ($area !== '' && isset($availableColumns['area'])) {
        $whereSql .= ' AND TRIM(COALESCE(area, "")) = TRIM(?)';
        $params[] = $area;
    }

    if ($branch_name !== '' && $branchNameColumn !== null) {
        $whereSql .= ' AND LOWER(COALESCE(`' . $branchNameColumn . '`, "")) LIKE ?';
        $params[] = '%' . strtolower($branch_name) . '%';
    }

    if ($branch_id !== '' && isset($availableColumns['branch_id'])) {
        $whereSql .= ' AND LOWER(COALESCE(branch_id, "")) LIKE ?';
        $params[] = '%' . strtolower($branch_id) . '%';
    }

    if (in_array($currency, ['PHP', 'USD'], true) && isset($availableColumns['currency'])) {
        $whereSql .= ' AND UPPER(TRIM(currency)) = ?';
        $params[] = $currency;
    }

    // Compute currency totals for the fully-built WHERE clause (use same params)
    try {
        $totalsSelect = ''
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "PHP" THEN COALESCE(amount,0) ELSE 0 END), 0) AS php_total, '
            . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "USD" THEN COALESCE(amount,0) ELSE 0 END), 0) AS usd_total';

        if (isset($availableColumns['ctp'])) {
            $totalsSelect .= ', '
                . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "PHP" THEN COALESCE(ctp,0) ELSE 0 END), 0) AS php_commission_total, '
                . 'COALESCE(SUM(CASE WHEN UPPER(TRIM(currency)) = "USD" THEN COALESCE(ctp,0) ELSE 0 END), 0) AS usd_commission_total';
        } else {
            $totalsSelect .= ', 0 AS php_commission_total, 0 AS usd_commission_total';
        }

        if (isset($availableColumns['charge'])) {
            // Sum charge treating blank strings as 0
            $totalsSelect .= ', COALESCE(SUM(CASE WHEN TRIM(COALESCE(charge, "")) = "" THEN 0 ELSE charge END), 0) AS charge_total';
        }

        $totalsSql = 'SELECT ' . $totalsSelect . $whereSql;
        $totStmt = $pdo->prepare($totalsSql);
        $totStmt->execute($params);
        $totRow = $totStmt->fetch(PDO::FETCH_ASSOC);
        $php_total = isset($totRow['php_total']) ? (float)$totRow['php_total'] : 0.0;
        $usd_total = isset($totRow['usd_total']) ? (float)$totRow['usd_total'] : 0.0;
        $php_commission_total = isset($totRow['php_commission_total']) ? (float)$totRow['php_commission_total'] : 0.0;
        $usd_commission_total = isset($totRow['usd_commission_total']) ? (float)$totRow['usd_commission_total'] : 0.0;
        if (isset($totRow['charge_total'])) {
            $charge_total = (float)$totRow['charge_total'];
        } else {
            $charge_total = 0.0;
        }
    } catch (Throwable $e) {
        $php_total = 0.0;
        $usd_total = 0.0;
        $charge_total = 0.0;
        $php_commission_total = 0.0;
        $usd_commission_total = 0.0;
    }

    $countSql = 'SELECT COUNT(*)' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;
    
    // Order by chosen date column descending (newest first). Select both date_claimed and date_send when available.
    $selectCols = 'id, partner_id, partnerName, `no`, control_series_no, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at';
    if (isset($availableColumns['branch_id'])) {
        $selectCols = 'branch_id, ' . $selectCols;
    }

    // When reporting SENDOUT, include `charge` column if present so frontend can display it.
    if (strtolower($type) === 'sendout' && isset($availableColumns['charge'])) {
        // append charge so it's available in result rows
        $selectCols = 'charge, ' . $selectCols;
    }
    if (isset($availableColumns['date_claimed'])) $selectCols = 'date_claimed, ' . $selectCols;
    if (isset($availableColumns['date_send'])) $selectCols = 'date_send, ' . $selectCols;

    $sql = 'SELECT ' . $selectCols
        . $whereSql
        . ' ORDER BY ' . $dateColumn . ' DESC LIMIT ? OFFSET ?';
    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['report_date'] = isset($row[$dateColumn]) ? $row[$dateColumn] : null;
    }
    unset($row);
    
    echo json_encode([
        'success' => true,
        'count' => $totalCount,
        'page_count' => count($rows),
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'partner' => $partner,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'type' => $type,
        'date_column' => $dateColumn,
        'date_label' => $dateLabel,
        'currency_filter' => $currency,
        'rows' => $rows,
        'php_total' => $php_total,
        'usd_total' => $usd_total,
        'charge_total' => $charge_total,
        'php_commission_total' => $php_commission_total,
        'usd_commission_total' => $usd_commission_total
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
