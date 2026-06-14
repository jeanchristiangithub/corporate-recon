<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

function reconDaycardLocksBoot(): void
{
    bootSecureSession();
}

function reconDaycardLocksEnsureTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS recon_daycard_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            corporate_partner VARCHAR(100) NOT NULL,
            recon_date DATE NOT NULL,
            is_locked TINYINT(1) NOT NULL DEFAULT 1,
            locked_by VARCHAR(100) NULL,
            locked_at DATETIME NULL,
            unlocked_by VARCHAR(100) NULL,
            unlocked_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_partner_date (corporate_partner, recon_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function reconLockedReconciliationDatesEnsureTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS locked_reconciliation_dates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            corporate_partner VARCHAR(100) NOT NULL,
            transaction_date DATE NOT NULL,
            locked_by VARCHAR(100) NULL,
            locked_at DATETIME NULL,
            unlocked_by VARCHAR(100) NULL,
            unlocked_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_partner_txn_date (corporate_partner, transaction_date),
            INDEX idx_partner_unlock_status (corporate_partner, unlocked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    
    // Ensure audit columns exist (for existing tables)
    try {
        $pdo->exec("ALTER TABLE locked_reconciliation_dates ADD COLUMN unlocked_by VARCHAR(100) NULL AFTER locked_at");
    } catch (Throwable $e) {
        // Column may already exist
    }
    try {
        $pdo->exec("ALTER TABLE locked_reconciliation_dates ADD COLUMN unlocked_at DATETIME NULL AFTER unlocked_by");
    } catch (Throwable $e) {
        // Column may already exist
    }
    try {
        $pdo->exec("ALTER TABLE locked_reconciliation_dates ADD INDEX idx_partner_unlock_status (corporate_partner, unlocked_at)");
    } catch (Throwable $e) {
        // Index may already exist
    }
}

function reconDaycardLocksDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = fileRecDbConnection();
    reconDaycardLocksEnsureTable($pdo);

    // Best effort only: do not break lock/unlock flows if CREATE TABLE is restricted.
    try {
        reconLockedReconciliationDatesEnsureTable($pdo);
    } catch (Throwable $e) {
        // noop
    }

    return $pdo;
}

function reconLockedReconciliationDatesTableExists(PDO $pdo): bool
{
    static $exists = null;

    if (is_bool($exists)) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'locked_reconciliation_dates'");
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }

    return $exists;
}

function reconDaycardLocksIsAdmin(): bool
{
    // Allow both Admin and Public roles to perform lock/unlock actions.
    if (empty($_SESSION['user']['role'])) {
        return false;
    }
    $role = (string) $_SESSION['user']['role'];
    return (strcasecmp($role, 'Admin') === 0) || (strcasecmp($role, 'Public') === 0);
}

function reconDaycardLocksUsername(): string
{
    $username = (string) ($_SESSION['user']['username'] ?? ($_SESSION['user']['firstname'] ?? ''));
    return trim($username);
}

function reconDaycardLocksNormalizePartner(string $partner): string
{
    return strtoupper(trim($partner));
}

function reconDaycardLocksNormalizeDate(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }

    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $date);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function reconDaycardLocksNormalizeDateList(array $dates): array
{
    $normalized = [];

    foreach ($dates as $date) {
        if (!is_scalar($date)) {
            continue;
        }

        $value = reconDaycardLocksNormalizeDate((string) $date);
        if ($value !== '') {
            $normalized[$value] = true;
        }
    }

    $values = array_keys($normalized);
    sort($values);

    return $values;
}

function reconDaycardLocksFindLockedDates(PDO $pdo, string $partner, array $dates): array
{
    $partner = reconDaycardLocksNormalizePartner($partner);
    $dates = reconDaycardLocksNormalizeDateList($dates);

    if ($partner === '' || empty($dates)) {
        return [];
    }

    $locked = [];
    foreach (array_chunk($dates, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = 'SELECT recon_date
                FROM recon_daycard_locks
                WHERE corporate_partner = ?
                  AND is_locked = 1
                  AND recon_date IN (' . $placeholders . ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$partner], $chunk));

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) as $date) {
            $normalized = reconDaycardLocksNormalizeDate((string) $date);
            if ($normalized !== '') {
                $locked[$normalized] = true;
            }
        }

        if (reconLockedReconciliationDatesTableExists($pdo)) {
            try {
                $sqlLockedDates = 'SELECT transaction_date
                                   FROM locked_reconciliation_dates
                                   WHERE corporate_partner = ?
                                     AND transaction_date IN (' . $placeholders . ')
                                     AND unlocked_at IS NULL';

                $stmtLockedDates = $pdo->prepare($sqlLockedDates);
                $stmtLockedDates->execute(array_merge([$partner], $chunk));

                foreach ($stmtLockedDates->fetchAll(PDO::FETCH_COLUMN, 0) as $date) {
                    $normalized = reconDaycardLocksNormalizeDate((string) $date);
                    if ($normalized !== '') {
                        $locked[$normalized] = true;
                    }
                }
            } catch (Throwable $e) {
                // Ignore optional table/query issues; daycard locks are still authoritative.
            }
        }
    }

    $values = array_keys($locked);
    sort($values);

    return $values;
}

function reconLockedReconciliationDatesUpsert(PDO $pdo, string $partner, array $dates, string $lockedBy = ''): int
{
    $partner = reconDaycardLocksNormalizePartner($partner);
    $dates = reconDaycardLocksNormalizeDateList($dates);

    if ($partner === '' || empty($dates) || !reconLockedReconciliationDatesTableExists($pdo)) {
        return 0;
    }

    $sql = 'INSERT INTO locked_reconciliation_dates (corporate_partner, transaction_date, locked_by, locked_at, unlocked_by, unlocked_at, created_at, updated_at)
            VALUES (:partner, :transaction_date, :locked_by, NOW(), NULL, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                locked_by = VALUES(locked_by),
                locked_at = NOW(),
                unlocked_by = NULL,
                unlocked_at = NULL,
                updated_at = NOW()';
    $stmt = $pdo->prepare($sql);

    $count = 0;
    foreach ($dates as $date) {
        try {
            $stmt->execute([
                ':partner' => $partner,
                ':transaction_date' => $date,
                ':locked_by' => trim($lockedBy),
            ]);
            $count++;
        } catch (Throwable $e) {
            // Keep primary lock flow alive.
            continue;
        }
    }

    return $count;
}

function reconLockedReconciliationDatesUnlock(PDO $pdo, string $partner, array $dates, string $unlockedBy = ''): int
{
    $partner = reconDaycardLocksNormalizePartner($partner);
    $dates = reconDaycardLocksNormalizeDateList($dates);

    if ($partner === '' || empty($dates) || !reconLockedReconciliationDatesTableExists($pdo)) {
        return 0;
    }

    $updated = 0;
    foreach (array_chunk($dates, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = 'UPDATE locked_reconciliation_dates
                SET unlocked_by = ?, unlocked_at = NOW(), updated_at = CURRENT_TIMESTAMP
                WHERE corporate_partner = ?
                  AND transaction_date IN (' . $placeholders . ')
              AND locked_at IS NOT NULL
              AND unlocked_at IS NULL';
        try {
            $stmt = $pdo->prepare($sql);
            $params = array_merge([trim($unlockedBy), $partner], $chunk);
            $stmt->execute($params);
            $updated += $stmt->rowCount();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $updated;
}

function reconLockedReconciliationDatesPruneOrphans(PDO $pdo, string $partner): int
{
    // Date-lock history must be preserved for audit, so no rows are deleted here.
    return 0;
}

function reconLockedReconciliationDatesHasActiveLocks(PDO $pdo, string $partner, array $dates): bool
{
    $partner = reconDaycardLocksNormalizePartner($partner);
    $dates = reconDaycardLocksNormalizeDateList($dates);

    if ($partner === '' || empty($dates) || !reconLockedReconciliationDatesTableExists($pdo)) {
        return false;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $sql = 'SELECT COUNT(*) FROM locked_reconciliation_dates
                WHERE corporate_partner = ?
                  AND transaction_date IN (' . $placeholders . ')
                  AND locked_at IS NOT NULL
                  AND unlocked_at IS NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$partner], $dates));
        $count = (int) $stmt->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function reconDaycardLocksFormatBlockedUploadMessage(string $partner, array $dates): string
{
    $partner = reconDaycardLocksNormalizePartner($partner);
    $dates = reconDaycardLocksNormalizeDateList($dates);

    $message = "Upload blocked.\n";
    $message .= 'Reconciled dates are locked for ' . $partner . ':';
    if (!empty($dates)) {
        $message .= "\n\n" . implode("\n", $dates);
    }
    $message .= "\n\nUnlock before uploading new data.";

    return $message;
}

function reconDaycardLocksPartnerKey(string $partner): string
{
    $partner = reconDaycardLocksNormalizePartner($partner);

    $aliases = [
        'mbtc' => ['MBTC', 'METROBANK HEAD OFFICE'],
        'moneygram' => ['MONEYGRAM'],
        'wic' => ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS'],
        'rcbc' => ['RCBC', 'RIZAL COMMERCIAL BANKING CORPORATION'],
    ];

    foreach ($aliases as $key => $values) {
        if (in_array($partner, $values, true)) {
            return $key;
        }
    }

    return '';
}

function reconDaycardLocksFetchDaySummary(PDO $pdo, string $partner, string $date): array
{
    $partnerKey = reconDaycardLocksPartnerKey($partner);
    $date = reconDaycardLocksNormalizeDate($date);

    if ($partnerKey === '' || $date === '') {
        return [
            'supported' => false,
            'matchedCount' => 0,
            'principal' => 0.0,
            'commission' => 0.0,
            'partnerCount' => 0,
            'webCount' => 0,
        ];
    }

    $likeDate = '%' . $date . '%';
    $partnerRows = [];
    $webRows = [];

    $tryQuery = static function(array $sqls, array $params) use ($pdo): array {
        foreach ($sqls as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $message = strtolower((string) $e->getMessage());
                $code = (string) ($e->getCode() ?? '');
                if (strpos($message, 'unknown column') === false && $code !== '42S22') {
                    throw $e;
                }
            }
        }

        return [];
    };

    switch ($partnerKey) {
        case 'moneygram':
            $partnerRows = $tryQuery([
                'SELECT reference_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(base_tran_amt,0)) AS principal, SUM(COALESCE(comm_tran_amt,0)) AS commission FROM moneygram_partner_data WHERE DATE(tran_date) = ? OR tran_date LIKE ? GROUP BY reference_id',
                'SELECT reference_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(total_tran_amt,0)) AS principal, SUM(COALESCE(comm_tran_amt,0)) AS commission FROM moneygram_partner_data WHERE DATE(tran_date) = ? OR tran_date LIKE ? GROUP BY reference_id',
                'SELECT reference_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(base_tran_amt,0)) AS principal, SUM(COALESCE(fee_tran_amt,0)) AS commission FROM moneygram_partner_data WHERE DATE(tran_date) = ? OR tran_date LIKE ? GROUP BY reference_id',
                'SELECT reference_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(base_tran_amt,0)) AS principal, SUM(COALESCE(comm_tran_amt,0)) AS commission FROM moneygram_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY reference_id',
                'SELECT reference_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(base_tran_amt,0)) AS principal, SUM(COALESCE(comm_tran_amt,0)) AS commission FROM moneygram_partner_data WHERE DATE(fx_date_trn) = ? OR fx_date_trn LIKE ? GROUP BY reference_id',
            ], [$date, $likeDate]);
            $webRows = $tryQuery([
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN (?) GROUP BY ccref_no',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN (?) GROUP BY ccref_no',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN (?) GROUP BY ccref_no',
            ], [$date, $likeDate, 'MONEYGRAM']);
            break;

        case 'mbtc':
            $partnerRows = $tryQuery([
                'SELECT reference_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS principal, SUM(COALESCE(in_php,0)) AS commission FROM mbtc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY reference_no',
            ], [$date, $likeDate]);
            $webRows = $tryQuery([
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN (?,?) GROUP BY ccref_no',
                'SELECT cc_ref AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN (?,?) GROUP BY cc_ref',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN (?,?) GROUP BY ccref_no',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN (?,?) GROUP BY ccref_no',
            ], [$date, $likeDate, 'MBTC', 'METROBANK HEAD OFFICE']);
            break;

        case 'wic':
            $partnerRows = $tryQuery([
                'SELECT transaction_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, 0 AS commission FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY transaction_id',
                'SELECT transaction_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, 0 AS commission FROM wic_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY transaction_id',
                'SELECT reference_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, 0 AS commission FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY reference_no',
                'SELECT ref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, 0 AS commission FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY ref_no',
            ], [$date, $likeDate]);
            $webRows = $tryQuery([
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN (?,?,?) GROUP BY ccref_no',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN (?,?,?) GROUP BY ccref_no',
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN (?,?,?) GROUP BY ccref_no',
            ], [$date, $likeDate, 'WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS']);
            break;

        case 'rcbc':
            $partnerRows = $tryQuery([
                'SELECT transaction_id AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, 0 AS commission FROM rcbc_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY transaction_id',
                'SELECT reference_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS principal, SUM(COALESCE(in_php,0)) AS commission FROM rcbc_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY reference_no',
                'SELECT reference_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS principal, SUM(COALESCE(in_php,0)) AS commission FROM rcbc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY reference_no',
            ], [$date, $likeDate]);
            $webRows = $tryQuery([
                'SELECT ccref_no AS ref, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS principal, SUM(COALESCE(ctp,0)) AS commission FROM ml_web_data WHERE DATE(date_claimed) = ? OR date_claimed LIKE ? GROUP BY ccref_no',
            ], [$date, $likeDate]);
            break;

        default:
            return [
                'supported' => false,
                'matchedCount' => 0,
                'principal' => 0.0,
                'commission' => 0.0,
                'partnerCount' => 0,
                'webCount' => 0,
            ];
    }

    $partnerByRef = [];
    foreach ($partnerRows as $row) {
        $ref = strtoupper(trim((string) ($row['ref'] ?? '')));
        if ($ref === '') {
            continue;
        }
        $partnerByRef[$ref] = $row;
    }

    $webByRef = [];
    foreach ($webRows as $row) {
        $ref = strtoupper(trim((string) ($row['ref'] ?? '')));
        if ($ref === '') {
            continue;
        }
        $webByRef[$ref] = $row;
    }

    $matchedCount = 0;
    $principal = 0.0;
    $commission = 0.0;

    foreach ($partnerByRef as $ref => $row) {
        if (!isset($webByRef[$ref])) {
            continue;
        }

        $matchedCount++;
        $principal += (float) ($row['principal'] ?? 0);
        $commission += (float) ($row['commission'] ?? 0);
    }

    return [
        'supported' => true,
        'matchedCount' => $matchedCount,
        'principal' => $principal,
        'commission' => $commission,
        'partnerCount' => count($partnerByRef),
        'webCount' => count($webByRef),
    ];
}

function reconDaycardLocksCanLockDay(PDO $pdo, string $partner, string $date): array
{
    $stats = reconDaycardLocksFetchDaySummary($pdo, $partner, $date);
    if (!empty($stats['supported']) && ((int) ($stats['matchedCount'] ?? 0) > 0 || abs((float) ($stats['principal'] ?? 0)) > 0.000001 || abs((float) ($stats['commission'] ?? 0)) > 0.000001)) {
        return ['ok' => true, 'stats' => $stats];
    }

    return [
        'ok' => false,
        'stats' => $stats,
        'message' => "Cannot lock this day card.\n\nNo matching web data and partner data found for this cover date.",
    ];
}

function reconDaycardLocksReadPayload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        return is_array($payload) ? $payload : [];
    }

    return $_POST;
}
