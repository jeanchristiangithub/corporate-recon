<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $status = trim((string) ($_GET['status'] ?? ''));
    $mainzone = trim((string) ($_GET['mainzone'] ?? ''));
    $zone = trim((string) ($_GET['zone'] ?? ''));
    $regionCode = trim((string) ($_GET['region'] ?? ''));
    $branchId = trim((string) ($_GET['branch_id'] ?? ''));
    $month = trim((string) ($_GET['month'] ?? ''));

    if ($status === '') {
        throw new InvalidArgumentException('Branch Status is required.');
    }
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        throw new InvalidArgumentException('A valid Month is required.');
    }
    $monthStart = $month . '-01';
    $nextMonthStart = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
    $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');

    $isTboStatus = strcasecmp($status, 'TBO') === 0;
    if ($isTboStatus) {
        $sql = "SELECT DISTINCT
                h.posted_date AS payroll_date,
                h.mbp_mainzone AS mainzone,
                h.mbp_branch_id AS branch_id,
                h.mbp_code AS code,
                COALESCE(
                    NULLIF(TRIM(h.mbp_mlmatic_branch_name), ''),
                    NULLIF(TRIM(h.mkpxbm_branch_name), ''),
                    NULLIF(TRIM(h.mbp_branch_name_description), ''),
                    ''
                ) AS branch_name,
                h.mbp_gl_region AS region_description,
                h.mbp_mlmatic_region AS ml_matic_region,
                h.mbp_mlmatic_status AS ml_matic_status,
                h.posted_at AS posted_date
            FROM filerecondb.corporate_branch_status_history h
            WHERE h.posted_date >= ?
              AND h.posted_date < ?
              AND TRIM(UPPER(h.mbp_mlmatic_status)) = TRIM(UPPER(?))";
        $parameters = [$monthStart, $nextMonthStart, $status];
        $mainzoneColumn = 'h.mbp_mainzone';
        $zoneColumn = 'h.mbp_zone';
        $regionColumn = 'h.mbp_region_code';
    } else {
        $sql = "SELECT DISTINCT
                e.payroll_date,
                e.mainzone,
                h.mbp_branch_id AS branch_id,
                h.mbp_code AS code,
                COALESCE(
                    NULLIF(TRIM(h.mbp_mlmatic_branch_name), ''),
                    NULLIF(TRIM(h.mkpxbm_branch_name), ''),
                    NULLIF(TRIM(h.mbp_branch_name_description), ''),
                    ''
                ) AS branch_name,
                h.mbp_gl_region AS region_description,
                e.ml_matic_region,
                h.mbp_mlmatic_status AS ml_matic_status,
                e.posted_date
            FROM edi.payroll_edi_report e
            LEFT JOIN filerecondb.corporate_branch_status_history h
                ON TRIM(e.branch_code) = TRIM(h.mbp_code)
                AND TRIM(UPPER(e.ml_matic_region)) = TRIM(UPPER(h.mbp_mlmatic_region))
                AND h.posted_date >= ?
                AND h.posted_date < ?
            WHERE DATE(e.payroll_date) = ?
              AND TRIM(UPPER(h.mbp_mlmatic_status)) = TRIM(UPPER(?))
              AND TRIM(LOWER(e.description)) = 'payroll'";
        $parameters = [$monthStart, $nextMonthStart, $monthEnd, $status];
        $mainzoneColumn = 'e.mainzone';
        $zoneColumn = 'e.zone';
        $regionColumn = 'e.region_code';
    }

    if ($mainzone !== '') {
        $sql .= " AND TRIM(UPPER({$mainzoneColumn})) = TRIM(UPPER(?))";
        $parameters[] = $mainzone;
    }

    $isShowroomRegion = $zone === '' && in_array(strtoupper($regionCode), ['LZN', 'NCR', 'VIS', 'MIN'], true);
    if (strcasecmp($zone, 'Showroom') === 0 || $isShowroomRegion) {
        $sql .= " AND UPPER(TRIM(COALESCE(
            NULLIF(h.mbp_mlmatic_branch_name, ''),
            NULLIF(h.mkpxbm_branch_name, ''),
            h.mbp_branch_name_description,
            ''
        ))) LIKE '%SHOWROOM%'";
        if ($regionCode !== '') {
            $sql .= " AND TRIM(UPPER({$zoneColumn})) = TRIM(UPPER(?))";
            $parameters[] = $regionCode;
        }
    } else {
        if ($zone !== '') {
            $sql .= " AND TRIM(UPPER({$zoneColumn})) = TRIM(UPPER(?))";
            $parameters[] = $zone;
        }
        if ($regionCode !== '') {
            $sql .= " AND TRIM(UPPER({$regionColumn})) = TRIM(UPPER(?))";
            $parameters[] = $regionCode;
        }
    }

    if ($branchId !== '') {
        $sql .= ' AND TRIM(h.mbp_branch_id) = TRIM(?)';
        $parameters[] = $branchId;
    }

    $sql .= ' ORDER BY branch_name, h.mbp_branch_id';
    $statement = masterDataConnection()->prepare($sql);
    $statement->execute($parameters);

    $branchRows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $amountExpression = static function (string $column): string {
        return 'ABS(CAST(REPLACE(REPLACE(REPLACE(COALESCE(mpd.`' . $column
            . '`, 0), ",", ""), "PHP", ""), "$", "") AS DECIMAL(18, 2)))';
    };
    $baseAmount = $amountExpression('base_amt');
    $commissionAmount = $amountExpression('comm_amt');
    $fxShareAmount = $amountExpression('fx_rev_share_amt');
    $matchDate = "DATE(CASE
        WHEN mwd.date_cancelled IS NOT NULL AND TRIM(CAST(mwd.date_cancelled AS CHAR)) <> '' THEN mwd.date_cancelled
        WHEN mwd.date_claimed IS NOT NULL AND TRIM(CAST(mwd.date_claimed AS CHAR)) <> '' THEN mwd.date_claimed
        WHEN mwd.date_send IS NOT NULL AND TRIM(CAST(mwd.date_send AS CHAR)) <> '' THEN mwd.date_send
        ELSE NULL END)";

    $metricsSql = "SELECT
            TRIM(mpd.branch_id) AS branch_id,
            UPPER(TRIM(mpd.settlement_currency)) AS currency,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'REC' THEN 1
                     WHEN UPPER(TRIM(mpd.tran_type)) = 'RRC' THEN -1 ELSE 0 END) AS payout_count,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'REC' THEN {$baseAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) = 'RRC' THEN -{$baseAmount} ELSE 0 END) AS payout_principal,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'REC' THEN {$commissionAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) = 'RRC' THEN -{$commissionAmount} ELSE 0 END) AS payout_charge,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'REC' THEN {$fxShareAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) = 'RRC' THEN -{$fxShareAmount} ELSE 0 END) AS payout_fx_share,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'SEN' THEN 1
                     WHEN UPPER(TRIM(mpd.tran_type)) IN ('RSN', 'REF') THEN -1 ELSE 0 END) AS sendout_count,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'SEN' THEN {$baseAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) IN ('RSN', 'REF') THEN -{$baseAmount} ELSE 0 END) AS sendout_principal,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'SEN' THEN {$commissionAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) IN ('RSN', 'REF') THEN -{$commissionAmount} ELSE 0 END) AS sendout_charge,
            SUM(CASE WHEN UPPER(TRIM(mpd.tran_type)) = 'SEN' THEN {$fxShareAmount}
                     WHEN UPPER(TRIM(mpd.tran_type)) IN ('RSN', 'REF') THEN -{$fxShareAmount} ELSE 0 END) AS sendout_fx_share
        FROM moneygram_partner_data mpd
        WHERE DATE(mpd.tran_date) BETWEEN ? AND ?
          AND UPPER(TRIM(mpd.settlement_currency)) IN ('PHP', 'USD')
          AND UPPER(TRIM(mpd.tran_type)) IN ('REC', 'RRC', 'SEN', 'RSN', 'REF')
          AND EXISTS (
              SELECT 1
              FROM ml_web_data mwd
              WHERE TRIM(mwd.ccref_no) COLLATE utf8mb4_unicode_ci
                    = TRIM(mpd.reference_id) COLLATE utf8mb4_unicode_ci
                AND {$matchDate} = DATE(mpd.tran_date)
                AND UPPER(TRIM(COALESCE(mwd.partnerName, ''))) = 'MONEYGRAM'
          )
        GROUP BY TRIM(mpd.branch_id), UPPER(TRIM(mpd.settlement_currency))";
    $metricsStatement = fileRecDbConnection()->prepare($metricsSql);
    $metricsStatement->execute([$monthStart, $monthEnd]);

    $metricsByBranch = [];
    foreach ($metricsStatement->fetchAll(PDO::FETCH_ASSOC) as $metricRow) {
        $metricBranchId = trim((string) ($metricRow['branch_id'] ?? ''));
        $currency = strtoupper(trim((string) ($metricRow['currency'] ?? '')));
        if ($metricBranchId !== '' && in_array($currency, ['PHP', 'USD'], true)) {
            $metricsByBranch[$metricBranchId][$currency] = $metricRow;
        }
    }

    foreach ($branchRows as &$branchRow) {
        $metricBranchId = trim((string) ($branchRow['branch_id'] ?? ''));
        $branchRow['metrics'] = $metricsByBranch[$metricBranchId] ?? [];
    }
    unset($branchRow);

    echo json_encode(['success' => true, 'rows' => $branchRows]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to generate the EDI report.']);
}
