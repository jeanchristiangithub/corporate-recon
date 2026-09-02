<?php

/**
 * MoneyGram partner-upload matching add-on.
 *
 * Statuses: 1 = matched, 2 = mismatch/not found, 3 = duplicate of an
 * already-matched partner row or an already matched-and-locked KPX web row.
 * Matching uses reference + amount + the date selected by the MoneyGram
 * transaction type.
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

function moneygramPartnerWebValuesMatchDuplicate(array $webRow, string $referenceId, $amount, $tranDate): bool
{
    if (strtoupper(trim((string)($webRow['ccref_no'] ?? ''))) !== strtoupper(trim($referenceId))) {
        return false;
    }
    if (moneygramPartnerMatchAmount($webRow['amount'] ?? 0) !== moneygramPartnerMatchAmount($amount)) {
        return false;
    }

    $partnerDate = moneygramPartnerMatchDate($tranDate);
    if ($partnerDate === '') return false;
    foreach (['date_claimed', 'date_send', 'date_cancelled'] as $dateColumn) {
        if (moneygramPartnerMatchDate($webRow[$dateColumn] ?? '') === $partnerDate) return true;
    }
    return false;
}

function moneygramPartnerLockedWebMatchesDuplicate(array $webRow, string $referenceId, $amount, $tranDate): bool
{
    return (int)($webRow['match_status'] ?? 0) === 1
        && (string)($webRow['is_data_locked'] ?? '0') === '1'
        && moneygramPartnerWebValuesMatchDuplicate($webRow, $referenceId, $amount, $tranDate);
}

/**
 * Adds match_status/is_data_locked to mapped partner rows and returns the KPX
 * row IDs that must be promoted from mismatch to matched in the same transaction.
 */
function moneygramClassifyPartnerUploadRows(PDO $pdo, array &$rows, array $knownDuplicatePairs = []): array
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

    $existingPartnerDateKeys = [];
    foreach ($knownDuplicatePairs as $pair) {
        if (!is_array($pair)) continue;
        $referenceId = trim((string)($pair['reference_id'] ?? ($pair['reference_no'] ?? ($pair['transaction_id'] ?? ''))));
        $tranDate = moneygramPartnerMatchDate($pair['tran_date'] ?? ($pair['date'] ?? ''));
        if ($referenceId !== '' && $tranDate !== '') {
            $existingPartnerDateKeys[strtoupper($referenceId) . "\x1F" . $tranDate] = true;
        }
    }
    $webRowsByReference = [];
    foreach (array_chunk(array_values($referenceIds), 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        $partnerStmt = $pdo->prepare(
            'SELECT reference_id, tran_date '
            . 'FROM moneygram_partner_data WHERE reference_id IN (' . $placeholders . ')'
        );
        $partnerStmt->execute($chunk);
        foreach ($partnerStmt->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            $referenceKey = strtoupper(trim((string)$existing['reference_id']));
            $dateKey = moneygramPartnerMatchDate($existing['tran_date'] ?? '');
            if ($referenceKey !== '' && $dateKey !== '') {
                $existingPartnerDateKeys[$referenceKey . "\x1F" . $dateKey] = true;
            }
        }

        $webStmt = $pdo->prepare(
            'SELECT id, ccref_no, amount, date_cancelled, date_claimed, date_send, match_status, is_data_locked '
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
        $dateDuplicateKey = strtoupper($referenceId) . "\x1F" . $tranDate;

        $wantedKey = moneygramPartnerMatchKey($referenceId, $row['base_amt'] ?? ($row['base_tran_amt'] ?? 0), $tranDate);
        $hasLockedWebMatch = false;
        foreach ($webRowsByReference[strtoupper($referenceId)] ?? [] as $webRow) {
            if (moneygramPartnerLockedWebMatchesDuplicate(
                $webRow,
                $referenceId,
                $row['base_amt'] ?? ($row['base_tran_amt'] ?? 0),
                $tranDate
            )) {
                $hasLockedWebMatch = true;
                break;
            }
        }
        if ($hasLockedWebMatch) {
            $row['match_status'] = 3;
            $row['is_data_locked'] = '0';
            continue;
        }

        $matchedWeb = null;
        foreach ($webRowsByReference[strtoupper($referenceId)] ?? [] as $webRow) {
            $webId = (int)($webRow['id'] ?? 0);
            if ($webId <= 0 || isset($usedWebIds[$webId])) continue;
            // A locked status-1 KPX row belongs to an earlier match and cannot
            // be reused as a new match in this upload.
            if ((int)($webRow['match_status'] ?? 0) === 1 && (string)($webRow['is_data_locked'] ?? '0') === '1') continue;
            // Maintenance Unlock changes the prior KPX match to 1/0. It is
            // eligible to match the replacement Partner row and return both
            // sides to 1/1.
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
            $promoteWebIds[] = (int)$matchedWeb['id'];
        } elseif ($referenceId !== '' && $tranDate !== '' && isset($existingPartnerDateKeys[$dateDuplicateKey])) {
            // No KPX match was available, but this reference/date existed
            // before overwrite (or still exists in Partner Data).
            $row['match_status'] = 3;
            $row['is_data_locked'] = '0';
        } else {
            $row['match_status'] = 2;
            $row['is_data_locked'] = '0';
        }
    }
    unset($row);

    // A reversal row may be encountered before the row that consumes its KPX
    // record in this same batch. Revisit mismatches after all matches are known.
    foreach ($rows as &$row) {
        if ((int)($row['match_status'] ?? 0) !== 2) continue;
        $referenceId = trim((string)($row['reference_id'] ?? ''));
        if ($referenceId === '') continue;
        foreach ($webRowsByReference[strtoupper($referenceId)] ?? [] as $webRow) {
            $webId = (int)($webRow['id'] ?? 0);
            $alreadyConsumed = isset($usedWebIds[$webId])
                || ((int)($webRow['match_status'] ?? 0) === 1
                    && (string)($webRow['is_data_locked'] ?? '0') === '1');
            if (!$alreadyConsumed) continue;
            if (moneygramPartnerWebValuesMatchDuplicate(
                $webRow,
                $referenceId,
                $row['base_amt'] ?? ($row['base_tran_amt'] ?? 0),
                $row['tran_date'] ?? ''
            )) {
                $row['match_status'] = 3;
                $row['is_data_locked'] = '0';
                break;
            }
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
            . 'WHERE id IN (' . $placeholders . ') '
            . "AND NOT (match_status = 1 AND COALESCE(is_data_locked, '0') = '1')"
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

function moneygramLockMatchedDates(PDO $pdo, array $dates): void
{
    $normalizedDates = [];
    foreach ($dates as $date) {
        $normalized = moneygramPartnerMatchDate($date);
        if ($normalized !== '') $normalizedDates[$normalized] = true;
    }
    $dates = array_keys($normalizedDates);
    if ($dates === []) return;

    foreach (array_chunk($dates, 500) as $chunk) {
        $dateRanges = static function (string $column, array $values): array {
            $conditions = [];
            $params = [];
            foreach ($values as $value) {
                $conditions[] = "(`{$column}` >= ? AND `{$column}` < DATE_ADD(?, INTERVAL 1 DAY))";
                $params[] = $value;
                $params[] = $value;
            }
            return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
        };
        $cancelledRange = $dateRanges('date_cancelled', $chunk);
        $claimedRange = $dateRanges('date_claimed', $chunk);
        $sentRange = $dateRanges('date_send', $chunk);
        $partnerRange = $dateRanges('tran_date', $chunk);

        $webStmt = $pdo->prepare(
            "UPDATE ml_web_data SET is_data_locked = '1' "
            . "WHERE partnerName = 'MONEYGRAM' "
            . "AND match_status = 1 "
            . "AND ((date_cancelled IS NOT NULL AND {$cancelledRange['sql']}) "
            . "OR (date_cancelled IS NULL AND date_claimed IS NOT NULL AND {$claimedRange['sql']}) "
            . "OR (date_cancelled IS NULL AND date_claimed IS NULL AND {$sentRange['sql']}))"
        );
        $webStmt->execute(array_merge($cancelledRange['params'], $claimedRange['params'], $sentRange['params']));

        $partnerStmt = $pdo->prepare(
            "UPDATE moneygram_partner_data SET is_data_locked = '1' "
            . "WHERE match_status = 1 AND {$partnerRange['sql']}"
        );
        $partnerStmt->execute($partnerRange['params']);
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

        $dateRanges = static function (string $column, array $values): array {
            $conditions = [];
            $params = [];
            foreach ($values as $value) {
                $conditions[] = "(`{$column}` >= ? AND `{$column}` < DATE_ADD(?, INTERVAL 1 DAY))";
                $params[] = $value;
                $params[] = $value;
            }
            return ['sql' => '(' . implode(' OR ', $conditions) . ')', 'params' => $params];
        };
        $cancelledRange = $dateRanges('date_cancelled', $chunk);
        $claimedRange = $dateRanges('date_claimed', $chunk);
        $sentRange = $dateRanges('date_send', $chunk);
        $partnerRange = $dateRanges('tran_date', $chunk);

        try {
            $lockStmt = $pdo->prepare(
                'UPDATE locked_reconciliation_dates '
                . 'SET locked_by = NULL, locked_at = NULL, unlocked_by = ?, unlocked_at = NOW(), updated_at = NOW() '
                . "WHERE corporate_partner = 'MONEYGRAM' AND transaction_date IN ($placeholders)"
            );
            $lockStmt->execute(array_merge([trim($unlockedBy)], $chunk));
        } catch (Throwable $e) {
            // This audit table is optional on older installations. Row-level
            // MoneyGram locks below are still authoritative for re-upload.
        }

        $webStmt = $pdo->prepare(
            "UPDATE ml_web_data SET is_data_locked = '0' "
            . "WHERE partnerName = 'MONEYGRAM' "
            . "AND ((date_cancelled IS NOT NULL AND {$cancelledRange['sql']}) "
            . "OR (date_cancelled IS NULL AND date_claimed IS NOT NULL AND {$claimedRange['sql']}) "
            . "OR (date_cancelled IS NULL AND date_claimed IS NULL AND {$sentRange['sql']}))"
        );
        $webStmt->execute(array_merge($cancelledRange['params'], $claimedRange['params'], $sentRange['params']));

        $partnerStmt = $pdo->prepare(
            "UPDATE moneygram_partner_data SET is_data_locked = '0' "
            . "WHERE {$partnerRange['sql']}"
        );
        $partnerStmt->execute($partnerRange['params']);
    }
}
