<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $partner = trim((string) ($_GET['partner'] ?? ''));
    $startDate = trim((string) ($_GET['start_date'] ?? ''));
    $endDate = trim((string) ($_GET['end_date'] ?? ''));
    $commissionOnly = filter_var(
        $_GET['commission_only'] ?? false,
        FILTER_VALIDATE_BOOL
    );

    if ($partner === '') {
        throw new InvalidArgumentException('Corporate Partner is required.');
    }

    foreach ([$startDate, $endDate] as $date) {
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('A valid report month is required.');
        }
    }
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('The report date range is invalid.');
    }

    $accounts = ['php' => '', 'usd' => ''];
    $deposits = ['php' => [], 'usd' => []];
    $beginningBalances = ['php' => 0.0, 'usd' => 0.0];
    $commissionFx = ['php' => [], 'usd' => []];
    $commissionFxTotals = ['php' => 0.0, 'usd' => 0.0];
    $remarks = ['php' => [], 'usd' => []];

    if (!$commissionOnly) {
        $partnerStatement = masterDataConnection()->prepare(
            'SELECT php_account_no, usd_account_no
             FROM corpo_partner_masterfile
             WHERE UPPER(TRIM(partner_name)) = UPPER(?)
             LIMIT 1'
        );
        $partnerStatement->execute([$partner]);
        $partnerAccounts = $partnerStatement->fetch(PDO::FETCH_ASSOC);
        if (!$partnerAccounts) {
            throw new RuntimeException('The selected Corporate Partner was not found.');
        }

        $accounts = [
            'php' => trim((string) ($partnerAccounts['php_account_no'] ?? '')),
            'usd' => trim((string) ($partnerAccounts['usd_account_no'] ?? '')),
        ];
        $queryAccounts = array_values(array_unique(array_filter(
            $accounts,
            static fn(string $account): bool => $account !== ''
        )));
    }

    if (!$commissionOnly && $queryAccounts !== []) {
        $placeholders = implode(', ', array_fill(0, count($queryAccounts), '?'));
        $depositStatement = vbReconDbConnection()->prepare(
            'SELECT transaction_date, account_number, SUM(COALESCE(deposits, 0)) AS deposit_total
             FROM bank_transactions
             WHERE account_number IN (' . $placeholders . ')
               AND transaction_date BETWEEN ? AND ?
             GROUP BY transaction_date, account_number
             ORDER BY transaction_date'
        );
        $depositStatement->execute([...$queryAccounts, $startDate, $endDate]);

        foreach ($depositStatement->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $date = (string) ($record['transaction_date'] ?? '');
            $accountNumber = trim((string) ($record['account_number'] ?? ''));
            $amount = (float) ($record['deposit_total'] ?? 0);

            foreach ($accounts as $currency => $configuredAccount) {
                if ($configuredAccount !== '' && $configuredAccount === $accountNumber) {
                    $deposits[$currency][$date] = $amount;
                }
            }
        }

        $beginningBalanceStatement = vbReconDbConnection()->prepare(
            'SELECT running_balance
             FROM bank_transactions
             WHERE account_number = ?
               AND transaction_date = DATE_SUB(?, INTERVAL 1 DAY)
             ORDER BY transaction_date DESC, id DESC
             LIMIT 1'
        );
        foreach ($accounts as $currency => $accountNumber) {
            if ($accountNumber === '') {
                continue;
            }
            $beginningBalanceStatement->execute([$accountNumber, $startDate]);
            $runningBalance = $beginningBalanceStatement->fetchColumn();
            if ($runningBalance !== false && $runningBalance !== null) {
                $beginningBalances[$currency] = (float) $runningBalance;
            }
        }
    }

    $commissionStatement = fileRecDbConnection()->prepare(
        'SELECT tran_date,
                UPPER(TRIM(COALESCE(NULLIF(transaction_currency, \'\'), settlement_currency))) AS currency_code,
                SUM(COALESCE(fx_rev_share_tran_amt, 0)) AS fx_total
         FROM partner_settlement_data
         WHERE UPPER(TRIM(partner_name)) = UPPER(?)
           AND tran_date BETWEEN ? AND ?
           AND tran_type IS NULL
           AND UPPER(TRIM(COALESCE(NULLIF(transaction_currency, \'\'), settlement_currency))) IN (\'PHP\', \'USD\')
         GROUP BY tran_date, UPPER(TRIM(COALESCE(NULLIF(transaction_currency, \'\'), settlement_currency)))
         ORDER BY tran_date'
    );
    $commissionStatement->execute([$partner, $startDate, $endDate]);
    foreach ($commissionStatement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $currency = strtolower(trim((string) ($record['currency_code'] ?? '')));
        $date = (string) ($record['tran_date'] ?? '');
        if (isset($commissionFx[$currency]) && $date !== '') {
            $amount = (float) ($record['fx_total'] ?? 0);
            $commissionFx[$currency][$date] = $amount;
            $commissionFxTotals[$currency] += $amount;
        }
    }

    if (!$commissionOnly) {
        $remarksStatement = fileRecDbConnection()->prepare(
            'SELECT tran_date, php_remarks, usd_remarks
             FROM partner_cashflow_remark_tag
             WHERE UPPER(TRIM(partner_name)) = UPPER(?)
               AND tran_date BETWEEN ? AND ?
             ORDER BY id'
        );
        $remarksStatement->execute([$partner, $startDate, $endDate]);
        foreach ($remarksStatement->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $date = (string) ($record['tran_date'] ?? '');
            foreach (['php', 'usd'] as $currency) {
                $remark = trim((string) ($record[$currency . '_remarks'] ?? ''));
                if ($date !== '' && in_array($remark, ['valid', 'not-valid'], true)) {
                    $remarks[$currency][$date] = $remark;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'partner' => $partner,
        'accounts' => $accounts,
        'deposits' => $deposits,
        'beginning_balances' => $beginningBalances,
        'commission_fx' => $commissionFx,
        'commission_fx_totals' => $commissionFxTotals,
        'remarks' => $remarks,
    ], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('[cashflow-report] ' . $exception->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to load Cash Flow bank deposits.']);
}
