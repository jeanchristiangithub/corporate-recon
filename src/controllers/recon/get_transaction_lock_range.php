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

    $rows = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        $dateValue = $date->format('Y-m-d');
        $hasData = isset($availableDates[$dateValue]);
        $rows[] = [
            'partnername' => $partner,
            'transaction_date' => $dateValue,
            'status' => !$hasData ? 'no_data' : (isset($lockedDates[$dateValue]) ? 'locked' : 'unlocked'),
            'has_data' => $hasData,
        ];
    }

    echo json_encode(['success' => true, 'rows' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
