<?php

declare(strict_types=1);

require_once __DIR__ . '/daycard-locks-common.php';

reconDaycardLocksBoot();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$partner = reconDaycardLocksNormalizePartner((string) ($_GET['partnername'] ?? ($_GET['partner'] ?? '')));
$startDate = reconDaycardLocksNormalizeDate((string) ($_GET['start_date'] ?? ''));
$endDate = reconDaycardLocksNormalizeDate((string) ($_GET['end_date'] ?? ''));

if ($partner === '' || $startDate === '' || $endDate === '' || $startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid partner or date range.']);
    exit;
}

$start = new DateTimeImmutable($startDate);
$end = new DateTimeImmutable($endDate);
$dayCount = ((int) $start->diff($end)->days) + 1;
if ($dayCount > 366) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Date range cannot exceed 366 days.']);
    exit;
}

try {
    $pdo = reconDaycardLocksDb();
    $lockedDates = [];
    if (reconLockedReconciliationDatesTableExists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT transaction_date
             FROM locked_reconciliation_dates
             WHERE corporate_partner = :partner
               AND transaction_date BETWEEN :start_date AND :end_date
               AND locked_at IS NOT NULL
               AND unlocked_at IS NULL'
        );
        $stmt->execute([':partner' => $partner, ':start_date' => $startDate, ':end_date' => $endDate]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
            $normalized = reconDaycardLocksNormalizeDate((string) $date);
            if ($normalized !== '') $lockedDates[$normalized] = true;
        }
    }

    $fetchAvailableDates = static function (PDO $pdo, array $queries) use ($startDate, $endDate): array {
        $dates = [];
        foreach ($queries as $query) {
            try {
                $stmt = $pdo->prepare((string) $query['sql']);
                $stmt->execute(array_merge([$startDate, $endDate], (array) ($query['params'] ?? [])));
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
                    $normalized = reconDaycardLocksNormalizeDate((string) $date);
                    if ($normalized !== '') $dates[$normalized] = true;
                }
            } catch (Throwable $e) {
                $message = strtolower((string) $e->getMessage());
                $code = (string) ($e->getCode() ?? '');
                if ($code !== '42S22' && $code !== '42S02' && strpos($message, 'unknown column') === false && strpos($message, "doesn't exist") === false) {
                    throw $e;
                }
            }
        }
        return $dates;
    };

    $partnerQueries = [];
    $webQueries = [];
    switch (reconDaycardLocksPartnerKey($partner)) {
        case 'moneygram':
            foreach (['tran_date', 'date', 'fx_date_trn'] as $column) {
                $partnerQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$column}`) FROM moneygram_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ?"];
            }
            foreach (['date_claimed', 'date'] as $dateColumn) {
                foreach (['partnerName', 'partner_name'] as $partnerColumn) {
                    $webQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$dateColumn}`) FROM ml_web_data WHERE DATE(`{$dateColumn}`) BETWEEN ? AND ? AND `{$partnerColumn}` = ?", 'params' => ['MONEYGRAM']];
                }
            }
            break;
        case 'mbtc':
            $partnerQueries[] = ['sql' => 'SELECT DISTINCT DATE(cover_date) FROM mbtc_partner_data WHERE DATE(cover_date) BETWEEN ? AND ?'];
            foreach (['date_claimed', 'date'] as $dateColumn) {
                foreach (['partnerName', 'partner_name'] as $partnerColumn) {
                    $webQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$dateColumn}`) FROM ml_web_data WHERE DATE(`{$dateColumn}`) BETWEEN ? AND ? AND `{$partnerColumn}` IN (?,?)", 'params' => ['MBTC', 'METROBANK HEAD OFFICE']];
                }
            }
            break;
        case 'wic':
            foreach (['date', 'cover_date'] as $column) {
                $partnerQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$column}`) FROM wic_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ?"];
            }
            foreach (['date_claimed', 'date'] as $dateColumn) {
                foreach (['partnerName', 'partner_name'] as $partnerColumn) {
                    $webQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$dateColumn}`) FROM ml_web_data WHERE DATE(`{$dateColumn}`) BETWEEN ? AND ? AND `{$partnerColumn}` IN (?,?,?)", 'params' => ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS']];
                }
            }
            break;
        case 'rcbc':
            foreach (['date', 'cover_date'] as $column) {
                $partnerQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$column}`) FROM rcbc_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ?"];
            }
            foreach (['date_claimed', 'date'] as $dateColumn) {
                $webQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$dateColumn}`) FROM ml_web_data WHERE DATE(`{$dateColumn}`) BETWEEN ? AND ?"];
            }
            break;
        case 'skybridgepaymentinc':
            foreach (['trans_date', 'withdraw_time', 'date', 'cover_date'] as $column) {
                $partnerQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$column}`) FROM skybridgepaymentinc_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ?"];
            }
            foreach (['date_claimed', 'date'] as $dateColumn) {
                foreach (['partnerName', 'partner_name'] as $partnerColumn) {
                    $webQueries[] = ['sql' => "SELECT DISTINCT DATE(`{$dateColumn}`) FROM ml_web_data WHERE DATE(`{$dateColumn}`) BETWEEN ? AND ? AND `{$partnerColumn}` IN (?,?,?,?)", 'params' => ['SKYBRIDGE', 'SKYBRIDGEPAYMENTINC', 'SKYBRIDGE PAYMENT INC.', 'SKYBRIDGEPAYMENTINC CORPORATE']];
                }
            }
            break;
    }

    $availableDates = $fetchAvailableDates($pdo, $partnerQueries);
    foreach ($fetchAvailableDates($pdo, $webQueries) as $date => $_present) {
        $availableDates[$date] = true;
    }

    $remarkQueries = [];
    switch (reconDaycardLocksPartnerKey($partner)) {
        case 'moneygram':
            foreach (['tran_date', 'date', 'fx_date_trn'] as $column) {
                $remarkQueries[] = "SELECT DATE(`{$column}`) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM moneygram_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ? GROUP BY DATE(`{$column}`)";
            }
            break;
        case 'mbtc':
            $remarkQueries[] = 'SELECT DATE(cover_date) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM mbtc_partner_data WHERE DATE(cover_date) BETWEEN ? AND ? GROUP BY DATE(cover_date)';
            break;
        case 'wic':
            foreach (['date', 'cover_date'] as $column) {
                $remarkQueries[] = "SELECT DATE(`{$column}`) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM wic_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ? GROUP BY DATE(`{$column}`)";
            }
            break;
        case 'rcbc':
            foreach (['date', 'cover_date'] as $column) {
                $remarkQueries[] = "SELECT DATE(`{$column}`) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM rcbc_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ? GROUP BY DATE(`{$column}`)";
            }
            break;
        case 'skybridgepaymentinc':
            foreach (['trans_date', 'withdraw_time', 'date', 'cover_date'] as $column) {
                $remarkQueries[] = "SELECT DATE(`{$column}`) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM skybridgepaymentinc_partner_data WHERE DATE(`{$column}`) BETWEEN ? AND ? GROUP BY DATE(`{$column}`)";
            }
            break;
    }

    $remarksByDate = [];
    foreach ($remarkQueries as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $remarkRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($remarkRows === []) continue;
            foreach ($remarkRows as $remarkRow) {
                $remarkDate = reconDaycardLocksNormalizeDate((string) ($remarkRow['transaction_date'] ?? ''));
                if ($remarkDate === '') continue;
                $remarksByDate[$remarkDate] = [
                    'mismatch_count' => (int) ($remarkRow['mismatch_count'] ?? 0),
                    'duplicate_count' => (int) ($remarkRow['duplicate_count'] ?? 0),
                ];
            }
            break;
        } catch (Throwable $e) {
            $message = strtolower((string) $e->getMessage());
            $code = (string) ($e->getCode() ?? '');
            if ($code !== '42S22' && $code !== '42S02' && strpos($message, 'unknown column') === false && strpos($message, "doesn't exist") === false) {
                throw $e;
            }
        }
    }

    $webRemarkQueries = [];
    $partnerKey = reconDaycardLocksPartnerKey($partner);
    $webPartnerAliases = [];
    if ($partnerKey === 'moneygram') $webPartnerAliases = ['MONEYGRAM'];
    elseif ($partnerKey === 'mbtc') $webPartnerAliases = ['MBTC', 'METROBANK HEAD OFFICE'];
    elseif ($partnerKey === 'wic') $webPartnerAliases = ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS'];
    elseif ($partnerKey === 'skybridgepaymentinc') $webPartnerAliases = ['SKYBRIDGE', 'SKYBRIDGEPAYMENTINC', 'SKYBRIDGE PAYMENT INC.', 'SKYBRIDGEPAYMENTINC CORPORATE'];

    if ($webPartnerAliases !== []) {
        $aliasPlaceholders = implode(',', array_fill(0, count($webPartnerAliases), '?'));
        $dateExpressions = $partnerKey === 'moneygram'
            ? ["CASE WHEN NULLIF(TRIM(CAST(date_cancelled AS CHAR)), '') IS NOT NULL THEN date_cancelled WHEN NULLIF(TRIM(CAST(date_claimed AS CHAR)), '') IS NOT NULL THEN date_claimed ELSE date_send END"]
            : ['date_claimed', 'date'];
        foreach (['partnerName', 'partner_name'] as $partnerColumn) {
            foreach ($dateExpressions as $dateExpression) {
                $webRemarkQueries[] = [
                    'sql' => "SELECT DATE({$dateExpression}) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM ml_web_data WHERE DATE({$dateExpression}) BETWEEN ? AND ? AND `{$partnerColumn}` IN ({$aliasPlaceholders}) GROUP BY DATE({$dateExpression})",
                    'params' => $webPartnerAliases,
                ];
            }
        }
    } elseif ($partnerKey === 'rcbc') {
        foreach (['date_claimed', 'date'] as $dateExpression) {
            $webRemarkQueries[] = [
                'sql' => "SELECT DATE(`{$dateExpression}`) AS transaction_date, SUM(match_status = 2) AS mismatch_count, SUM(match_status = 3) AS duplicate_count FROM ml_web_data WHERE DATE(`{$dateExpression}`) BETWEEN ? AND ? GROUP BY DATE(`{$dateExpression}`)",
                'params' => [],
            ];
        }
    }

    foreach ($webRemarkQueries as $query) {
        try {
            $stmt = $pdo->prepare((string) $query['sql']);
            $stmt->execute(array_merge([$startDate, $endDate], (array) $query['params']));
            $remarkRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($remarkRows === []) continue;
            foreach ($remarkRows as $remarkRow) {
                $remarkDate = reconDaycardLocksNormalizeDate((string) ($remarkRow['transaction_date'] ?? ''));
                if ($remarkDate === '') continue;
                $remarksByDate[$remarkDate] = [
                    'mismatch_count' => max(
                        (int) ($remarksByDate[$remarkDate]['mismatch_count'] ?? 0),
                        (int) ($remarkRow['mismatch_count'] ?? 0)
                    ),
                    'duplicate_count' => max(
                        (int) ($remarksByDate[$remarkDate]['duplicate_count'] ?? 0),
                        (int) ($remarkRow['duplicate_count'] ?? 0)
                    ),
                ];
            }
            break;
        } catch (Throwable $e) {
            $message = strtolower((string) $e->getMessage());
            $code = (string) ($e->getCode() ?? '');
            if ($code !== '42S22' && $code !== '42S02' && strpos($message, 'unknown column') === false && strpos($message, "doesn't exist") === false) {
                throw $e;
            }
        }
    }

    $applyModalRemarks = static function (array $days) use (&$remarksByDate): void {
        foreach ($days as $day) {
            if (!is_array($day)) continue;
            $remarkDate = reconDaycardLocksNormalizeDate((string) ($day['date'] ?? ''));
            if ($remarkDate === '') continue;
            $remarksByDate[$remarkDate] = [
                'mismatch_count' => count((array) ($day['missing_web_refs'] ?? []))
                    + count((array) ($day['missing_partner_refs'] ?? []))
                    + count((array) ($day['mismatches'] ?? [])),
                'duplicate_count' => count((array) ($day['duplicates'] ?? [])),
            ];
        }
    };

    $runReconForRemarks = static function (string $controller, array $parameters, string $returnConstant): array {
        $previousGet = $_GET;
        $_GET = $parameters;
        if (!defined($returnConstant)) define($returnConstant, true);
        try {
            $response = require $controller;
            return is_array($response) ? $response : [];
        } finally {
            $_GET = $previousGet;
        }
    };

    if ($partnerKey === 'moneygram') {
        $response = $runReconForRemarks(
            __DIR__ . '/moneygram-recon.php',
            ['start_date' => $startDate, 'end_date' => $endDate, 'partnerName' => $partner],
            'MONEYGRAM_RECON_RETURN_DATA'
        );
        $applyModalRemarks((array) ($response['days'] ?? []));
    } elseif ($partnerKey === 'mbtc') {
        $response = $runReconForRemarks(
            __DIR__ . '/mbtc-recon.php',
            ['start_date' => $startDate, 'end_date' => $endDate, 'partnerName' => $partner],
            'MBTC_RECON_RETURN_DATA'
        );
        $applyModalRemarks((array) ($response['days'] ?? []));
    } elseif ($partnerKey === 'wic') {
        $monthCursor = new DateTimeImmutable(substr($startDate, 0, 7) . '-01');
        $lastMonth = substr($endDate, 0, 7);
        while ($monthCursor->format('Y-m') <= $lastMonth) {
            $response = $runReconForRemarks(
                __DIR__ . '/wic-recon.php',
                ['month' => $monthCursor->format('m'), 'year' => $monthCursor->format('Y'), 'partnerName' => $partner],
                'WIC_RECON_RETURN_DATA'
            );
            $applyModalRemarks(array_values(array_filter((array) ($response['days'] ?? []), static function ($day) use ($startDate, $endDate): bool {
                $date = reconDaycardLocksNormalizeDate((string) ($day['date'] ?? ''));
                return $date !== '' && $date >= $startDate && $date <= $endDate;
            })));
            $monthCursor = $monthCursor->modify('+1 month');
        }
    }

    $rows = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $dateValue = $date->format('Y-m-d');
        $hasData = isset($availableDates[$dateValue]);
        $rows[] = [
            'partnername' => $partner,
            'transaction_date' => $dateValue,
            'status' => !$hasData ? 'no_data' : (isset($lockedDates[$dateValue]) ? 'locked' : 'unlocked'),
            'has_data' => $hasData,
            'mismatch_count' => (int) ($remarksByDate[$dateValue]['mismatch_count'] ?? 0),
            'duplicate_count' => (int) ($remarksByDate[$dateValue]['duplicate_count'] ?? 0),
        ];
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
