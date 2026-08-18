<?php

/**
 * MoneyGram partner-upload matching add-on.
 *
 * Statuses: 1 = matched, 2 = mismatch/not found, 3 = duplicate of an
 * already-matched partner row. Matching uses reference + amount + the date
 * selected by the MoneyGram transaction type.
 */

function moneygramPartnerMatchAmount($value): string
{
    $text = trim((string)$value);
    $negative = preg_match('/^\(.*\)$/', $text) === 1;
    $text = str_replace([',', ' '], '', trim($text, "() \t\n\r\0\x0B"));
    $number = is_numeric($text) ? (float)$text : 0.0;
    if ($negative) $number = -abs($number);
    return number_format(abs($number), 2, '.', '');
}

function moneygramPartnerMatchDate($value): string
{
    $text = trim((string)$value);
    if ($text === '') return '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $matches)) return $matches[1];
    $timestamp = strtotime($text);
    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
}

function moneygramPartnerMatchKey(string $referenceId, $amount, $date): string
{
    return strtoupper(trim($referenceId)) . "\x1F"
        . moneygramPartnerMatchAmount($amount) . "\x1F"
        . moneygramPartnerMatchDate($date);
}

function moneygramPartnerWebDateForType(array $webRow, string $tranType): string
{
    $tranType = strtoupper(trim($tranType));
    $cancelled = trim((string)($webRow['date_cancelled'] ?? ''));
    $claimed = trim((string)($webRow['date_claimed'] ?? ''));
    $sent = trim((string)($webRow['date_send'] ?? ''));

    if ($tranType === 'REC' && $cancelled === '') return moneygramPartnerMatchDate($claimed);
    if ($tranType === 'SEN' && $cancelled === '') return moneygramPartnerMatchDate($sent);
    if ($tranType === 'RRC' && $cancelled !== '' && $claimed !== '') return moneygramPartnerMatchDate($cancelled);
    if (($tranType === 'RSN' || $tranType === 'REF') && $cancelled !== '' && $sent !== '') {
        return moneygramPartnerMatchDate($cancelled);
    }
    return '';
}

/**
 * Adds match_status/is_data_locked to mapped partner rows and returns the KPX
 * row IDs that must be promoted from mismatch to matched in the same transaction.
 */
function moneygramClassifyPartnerUploadRows(PDO $pdo, array &$rows): array
{
    $referenceIds = [];
    foreach ($rows as $row) {
        $referenceId = trim((string)($row['reference_id'] ?? ''));
        if ($referenceId !== '') $referenceIds[strtoupper($referenceId)] = $referenceId;
    }
    if ($referenceIds === []) {
        foreach ($rows as &$row) {
            $row['match_status'] = 2;
            $row['is_data_locked'] = '0';
        }
        unset($row);
        return [];
    }

    $matchedPartnerKeys = [];
    $webRowsByReference = [];
    foreach (array_chunk(array_values($referenceIds), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $partnerStmt = $pdo->prepare(
            'SELECT reference_id, tran_date, tran_type FROM moneygram_partner_data '
            . 'WHERE reference_id IN (' . $placeholders . ') AND match_status = 1'
        );
        $partnerStmt->execute($chunk);
        foreach ($partnerStmt->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            $key = strtoupper(trim((string)$existing['reference_id'])) . "\x1F"
                . moneygramPartnerMatchDate($existing['tran_date'] ?? '') . "\x1F"
                . strtoupper(trim((string)($existing['tran_type'] ?? '')));
            $matchedPartnerKeys[$key] = true;
        }

        $webStmt = $pdo->prepare(
            'SELECT id, ccref_no, amount, date_cancelled, date_claimed, date_send, match_status '
            . 'FROM ml_web_data WHERE UPPER(TRIM(COALESCE(partnerName, \'\'))) = \'MONEYGRAM\' '
            . 'AND ccref_no IN (' . $placeholders . ') ORDER BY (match_status = 2) DESC, id ASC'
        );
        $webStmt->execute($chunk);
        foreach ($webStmt->fetchAll(PDO::FETCH_ASSOC) as $webRow) {
            $webRowsByReference[strtoupper(trim((string)$webRow['ccref_no']))][] = $webRow;
        }
    }

    $promoteWebIds = [];
    $usedWebIds = [];
    foreach ($rows as &$row) {
        $referenceId = trim((string)($row['reference_id'] ?? ''));
        $tranDate = moneygramPartnerMatchDate($row['tran_date'] ?? '');
        $tranType = strtoupper(trim((string)($row['tran_type'] ?? '')));
        $duplicateKey = strtoupper($referenceId) . "\x1F" . $tranDate . "\x1F" . $tranType;

        if ($referenceId !== '' && isset($matchedPartnerKeys[$duplicateKey])) {
            $row['match_status'] = 3;
            $row['is_data_locked'] = '0';
            continue;
        }

        $wantedKey = moneygramPartnerMatchKey($referenceId, $row['base_amt'] ?? ($row['base_tran_amt'] ?? 0), $tranDate);
        $matchedWeb = null;
        foreach ($webRowsByReference[strtoupper($referenceId)] ?? [] as $webRow) {
            $webId = (int)($webRow['id'] ?? 0);
            if ($webId <= 0 || isset($usedWebIds[$webId])) continue;
            $webDate = moneygramPartnerWebDateForType($webRow, $tranType);
            if ($webDate === '') continue;
            if (moneygramPartnerMatchKey((string)$webRow['ccref_no'], $webRow['amount'] ?? 0, $webDate) === $wantedKey) {
                $matchedWeb = $webRow;
                $usedWebIds[$webId] = true;
                break;
            }
        }

        if ($matchedWeb !== null) {
            $row['match_status'] = 1;
            $row['is_data_locked'] = '1';
            if ((int)($matchedWeb['match_status'] ?? 0) === 2) $promoteWebIds[] = (int)$matchedWeb['id'];
        } else {
            $row['match_status'] = 2;
            $row['is_data_locked'] = '0';
        }
    }
    unset($row);

    return array_values(array_unique($promoteWebIds));
}

function moneygramPromoteMatchedWebRows(PDO $pdo, array $webIds): void
{
    foreach (array_chunk(array_values(array_unique(array_filter(array_map('intval', $webIds)))), 1000) as $chunk) {
        if ($chunk === []) continue;
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare(
            'UPDATE ml_web_data SET match_status = 1, is_data_locked = \'1\' '
            . 'WHERE id IN (' . $placeholders . ') AND match_status = 2'
        );
        $stmt->execute($chunk);
    }
}

function moneygramUpsertMatchedLockDates(PDO $pdo, array $dates, string $lockedBy): void
{
    $normalizedDates = [];
    foreach ($dates as $date) {
        $normalized = moneygramPartnerMatchDate($date);
        if ($normalized !== '') $normalizedDates[$normalized] = true;
    }
    if ($normalizedDates === []) return;

    $stmt = $pdo->prepare(
        'INSERT INTO locked_reconciliation_dates '
        . '(corporate_partner, transaction_date, locked_by, locked_at, unlocked_by, unlocked_at, created_at, updated_at) '
        . "VALUES ('MONEYGRAM', ?, ?, NOW(), NULL, NULL, NOW(), NOW()) "
        . 'ON DUPLICATE KEY UPDATE locked_by = VALUES(locked_by), locked_at = NOW(), '
        . 'unlocked_by = NULL, unlocked_at = NULL, updated_at = NOW()'
    );
    foreach (array_keys($normalizedDates) as $date) {
        $stmt->execute([$date, trim($lockedBy)]);
    }
}

function moneygramUnlockMatchedDates(PDO $pdo, array $dates, string $unlockedBy): void
{
    $normalizedDates = [];
    foreach ($dates as $date) {
        $normalized = moneygramPartnerMatchDate($date);
        if ($normalized !== '') $normalizedDates[$normalized] = true;
    }
    $dates = array_keys($normalizedDates);
    if ($dates === []) return;

    foreach (array_chunk($dates, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $lockStmt = $pdo->prepare(
            'UPDATE locked_reconciliation_dates '
            . 'SET locked_by = NULL, locked_at = NULL, unlocked_by = ?, unlocked_at = NOW(), updated_at = NOW() '
            . "WHERE corporate_partner = 'MONEYGRAM' AND transaction_date IN ($placeholders)"
        );
        $lockStmt->execute(array_merge([trim($unlockedBy)], $chunk));

        $webDateConditions = [];
        $webParams = [];
        foreach ($chunk as $date) {
            $webDateConditions[] = "((UPPER(TRIM(COALESCE(data_status,''))) = 'PO' AND TRIM(COALESCE(CAST(date_cancelled AS CHAR),'')) = '' AND DATE(date_claimed) = ?)"
                . " OR (UPPER(TRIM(COALESCE(data_status,''))) = 'SO' AND TRIM(COALESCE(CAST(date_cancelled AS CHAR),'')) = '' AND DATE(date_send) = ?)"
                . " OR (UPPER(TRIM(COALESCE(data_status,''))) = 'POC' AND TRIM(COALESCE(CAST(date_cancelled AS CHAR),'')) <> '' AND TRIM(COALESCE(CAST(date_claimed AS CHAR),'')) <> '' AND DATE(date_cancelled) = ?)"
                . " OR (UPPER(TRIM(COALESCE(data_status,''))) = 'SOC' AND TRIM(COALESCE(CAST(date_cancelled AS CHAR),'')) <> '' AND TRIM(COALESCE(CAST(date_send AS CHAR),'')) <> '' AND DATE(date_cancelled) = ?))";
            array_push($webParams, $date, $date, $date, $date);
        }
        $webStmt = $pdo->prepare(
            "UPDATE ml_web_data SET match_status = 2, is_data_locked = '0' "
            . "WHERE UPPER(TRIM(COALESCE(partnerName,''))) = 'MONEYGRAM' "
            . "AND match_status = 1 AND (" . implode(' OR ', $webDateConditions) . ')'
        );
        $webStmt->execute($webParams);

        $partnerStmt = $pdo->prepare(
            "UPDATE moneygram_partner_data SET match_status = 2, is_data_locked = '0' "
            . "WHERE match_status = 1 AND DATE(tran_date) IN ($placeholders)"
        );
        $partnerStmt->execute($chunk);
    }
}
