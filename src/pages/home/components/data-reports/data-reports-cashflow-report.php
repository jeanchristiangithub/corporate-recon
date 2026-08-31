<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../config/csrf.php';

$cashFlowReportPartners = [];
$cashFlowReportPartnerBanks = [];

try {
    $statement = masterDataConnection()->query(
        "SELECT cpm.partner_name,
                bt.bank_abbreviation,
                bt.settled_online_check
         FROM corpo_partner_masterfile cpm
         LEFT JOIN bank_table bt ON bt.id = cpm.mbt_bank_id
         WHERE cpm.partner_name IS NOT NULL AND cpm.partner_name <> ''
         ORDER BY cpm.partner_name"
    );

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $partnerName = trim((string) ($record['partner_name'] ?? ''));
        if ($partnerName !== '') {
            if (!in_array($partnerName, $cashFlowReportPartners, true)) {
                $cashFlowReportPartners[] = $partnerName;
            }

            $bankAbbreviation = trim((string) ($record['bank_abbreviation'] ?? ''));
            $settlementMode = trim((string) ($record['settled_online_check'] ?? ''));
            $bankLabel = implode(' - ', array_values(array_filter(
                [$bankAbbreviation, $settlementMode],
                static fn(string $value): bool => $value !== ''
            )));
            if ($bankLabel !== '') {
                $cashFlowReportPartnerBanks[strtoupper($partnerName)] = $bankLabel;
            }
        }
    }
} catch (Throwable $exception) {
    $cashFlowReportPartners = [];
    $cashFlowReportPartnerBanks = [];
}
?>

<section id="cashFlowReportSection" class="cash-flow-report-section" aria-label="Cash Flow Report" style="display:none; padding:1rem">
    <h2 class="cash-flow-report-title">Cash Flow Report</h2>

    <form id="cashFlowReportFilters" class="cash-flow-report-filters" novalidate>
        <label class="cash-flow-report-field cash-flow-report-field--partner">
            <span>Corporate Partner</span>
            <div class="cash-flow-report-autocomplete">
                <input
                    id="cashFlowReportPartner"
                    name="partner"
                    type="text"
                    placeholder="Select corporate partner"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="cashFlowReportPartnerSuggestions"
                    aria-expanded="false"
                >
                <ul id="cashFlowReportPartnerSuggestions" role="listbox" hidden>
                    <?php foreach ($cashFlowReportPartners as $partnerName): ?>
                        <li
                            role="option"
                            tabindex="-1"
                            data-value="<?= htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8') ?>"
                        ><?= htmlspecialchars($partnerName, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </label>

        <label class="cash-flow-report-field cash-flow-report-field--month">
            <span>Month</span>
            <input id="cashFlowReportMonth" name="month" type="month" required>
        </label>

        <button id="cashFlowReportGenerate" class="cash-flow-report-generate" type="submit">
            Generate
        </button>

    </form>

    <div id="cashFlowReportStatus" class="cash-flow-report-status" role="status" aria-live="polite" hidden></div>

    <div id="cashFlowReportResultsLayout" class="cash-flow-report-results-layout" hidden>
        <aside class="cash-flow-report-results-filter" aria-labelledby="cashFlowReportFilterResultsTitle">
            <h3 id="cashFlowReportFilterResultsTitle">Filter Results</h3>
            <dl>
                <div>
                    <dt>Corporate Partner:</dt>
                    <dd id="cashFlowReportResultPartner">—</dd>
                </div>
                <div>
                    <dt>Transaction Date:</dt>
                    <dd id="cashFlowReportResultMonth">—</dd>
                </div>
            </dl>

            <dl class="cash-flow-report-balance-summary">
                <div>
                    <dt>Volume</dt>
                    <dd id="cashFlowReportVolume">—</dd>
                </div>
                <div class="cash-flow-report-forwarded-row">
                    <dt>
                        <span class="cash-flow-report-forwarded-label">Ending Balance</span>
                        <i><span id="cashFlowReportForwardedDate">—</span></i>
                    </dt>
                    <dd id="cashFlowReportBeginningBalance" class="cash-flow-report-currency-value"><span class="cash-flow-report-currency-sign">₱</span><span>—</span></dd>
                </div>
                <div>
                    <dt>Less: Transactions</dt>
                    <dd id="cashFlowReportTransactions" class="cash-flow-report-currency-value"><span class="cash-flow-report-currency-sign">₱</span><span>—</span></dd>
                </div>
                <div>
                    <dt>Add: Adjustment</dt>
                    <dd id="cashFlowReportAdjustment" class="cash-flow-report-currency-value"><span class="cash-flow-report-currency-sign">₱</span><span>—</span></dd>
                </div>
                <div>
                    <dt>Deposits</dt>
                    <dd id="cashFlowReportDeposits" class="cash-flow-report-currency-value"><span class="cash-flow-report-currency-sign">₱</span><span>—</span></dd>
                </div>
                <div>
                    <dt class="cash-flow-report-running-balance-label">Running Balance</dt>
                    <dd id="cashFlowReportRunningBalance" class="cash-flow-report-currency-value"><span class="cash-flow-report-currency-sign">₱</span><span>—</span></dd>
                </div>
            </dl>
        </aside>

        <div class="cash-flow-report-results-content">
            <div class="cash-flow-report-currency-tabs" role="tablist" aria-label="Report currency">
                <button
                    id="cashFlowReportPhpTab"
                    class="cash-flow-report-currency-tab is-active"
                    type="button"
                    role="tab"
                    aria-selected="true"
                    aria-controls="cashFlowReportPhpPanel"
                    data-currency="PHP"
                >PHP</button>
                <button
                    id="cashFlowReportUsdTab"
                    class="cash-flow-report-currency-tab"
                    type="button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="cashFlowReportUsdPanel"
                    data-currency="USD"
                >USD</button>

                <button id="cashFlowReportExportExcel" class="cash-flow-report-export-excel" type="button">
                    Export To
                </button>
            </div>

            <?php foreach (['PHP', 'USD'] as $currency): ?>
                <?php $currencyLower = strtolower($currency); ?>
                <?php $currencyName = ucfirst($currencyLower); ?>
                <div
                    id="cashFlowReport<?= $currencyName ?>Panel"
                    class="cash-flow-report-tab-panel"
                    role="tabpanel"
                    aria-labelledby="cashFlowReport<?= $currencyName ?>Tab"
                    <?= $currency === 'USD' ? 'hidden' : '' ?>
                >
                    <div class="cash-flow-report-table-wrap">
                        <table class="cash-flow-report-table">
                            <thead>
                                <tr>
                                    <th scope="col" rowspan="3">Date</th>
                                    <th scope="colgroup" colspan="8">Partner Settlement Data</th>
                                    <th class="cash-flow-report-bank-deposit-header" scope="col" rowspan="3">
                                        Bank Deposit
                                        <span class="cash-flow-report-bank-label">(—)</span>
                                        <span class="cash-flow-report-bank-account" data-currency="<?= $currencyLower ?>">—</span>
                                    </th>
                                    <th scope="col" rowspan="3">Running Balance</th>
                                    <th scope="col" rowspan="3">Action</th>
                                </tr>
                                <tr>
                                    <th scope="col" rowspan="2">Volume</th>
                                    <th scope="colgroup" colspan="2">Payout / Payout Cancelled</th>
                                    <th scope="colgroup" colspan="3">Sendout / Sendout Cancelled</th>
                                    <th scope="col" rowspan="2">Adjustment / Refund</th>
                                    <th class="cash-flow-report-net-settlement-header" scope="col" rowspan="2">Net Transaction Amount for Settlement</th>
                                </tr>
                                <tr>
                                    <th scope="col">Principal</th>
                                    <th scope="col">Commission</th>
                                    <th scope="col">Principal</th>
                                    <th scope="col">Charge</th>
                                    <th scope="col">Commission</th>
                                </tr>
                            </thead>
                            <tbody id="cashFlowReport<?= $currency ?>TableBody">
                                <tr class="cash-flow-report-empty-row">
                                    <td colspan="12">Select a corporate partner and month, then click Generate.</td>
                                </tr>
                            </tbody>
                            <tfoot id="cashFlowReport<?= $currency ?>TableFoot">
                                <tr>
                                    <th scope="row">Grand Total:</th>
                                    <td data-total="volume">—</td>
                                    <td data-total="payout-principal">—</td>
                                    <td data-total="payout-commission">—</td>
                                    <td data-total="sendout-principal">—</td>
                                    <td data-total="sendout-charge">—</td>
                                    <td data-total="sendout-commission">—</td>
                                    <td data-total="adjustment">—</td>
                                    <td data-total="net-transaction">—</td>
                                    <td data-total="deposit">—</td>
                                    <td data-total="running">—</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
(() => {
    const input = document.getElementById('cashFlowReportPartner');
    const list = document.getElementById('cashFlowReportPartnerSuggestions');
    if (!input || !list) return;

    const options = Array.from(list.querySelectorAll('[role="option"]'));
    const partnerBankLabels = <?= json_encode(
        $cashFlowReportPartnerBanks,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const bankDepositHeaders = Array.from(
        document.querySelectorAll('.cash-flow-report-bank-label')
    );
    const bankAccountHeaders = Array.from(
        document.querySelectorAll('.cash-flow-report-bank-account')
    );
    let activeIndex = -1;

    const updateBankDepositHeaders = () => {
        const partnerKey = input.value.trim().toLocaleUpperCase();
        const bankLabel = partnerBankLabels[partnerKey] || '—';
        bankDepositHeaders.forEach((headerLabel) => {
            headerLabel.textContent = `(${bankLabel})`;
        });
        updateBankAccountHeaders({});
    };

    const updateBankAccountHeaders = (accounts = {}) => {
        bankAccountHeaders.forEach((header) => {
            const currency = header.dataset.currency || '';
            header.textContent = String(accounts[currency] || '').trim() || '—';
        });
    };

    const visibleOptions = () => options.filter((option) => !option.hidden);

    const setActiveOption = (index) => {
        const visible = visibleOptions();
        visible.forEach((option) => option.classList.remove('is-active'));
        activeIndex = index < 0 || !visible.length
            ? -1
            : (index + visible.length) % visible.length;

        if (activeIndex >= 0) {
            visible[activeIndex].classList.add('is-active');
            visible[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    const openList = () => {
        list.hidden = visibleOptions().length === 0;
        input.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
    };

    const closeList = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        setActiveOption(-1);
    };

    const filterOptions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        options.forEach((option) => {
            option.hidden = query !== '' &&
                !option.dataset.value.toLocaleLowerCase().includes(query);
        });
        activeIndex = -1;
        openList();
    };

    const selectOption = (option) => {
        input.value = option.dataset.value;
        updateBankDepositHeaders();
        closeList();
        input.focus();
    };

    input.addEventListener('focus', filterOptions);
    input.addEventListener('input', () => {
        filterOptions();
        updateBankDepositHeaders();
    });
    input.addEventListener('keydown', (event) => {
        const visible = visibleOptions();

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openList();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            openList();
            setActiveOption(activeIndex - 1);
        } else if (event.key === 'Enter' && !list.hidden && activeIndex >= 0) {
            event.preventDefault();
            selectOption(visible[activeIndex]);
        } else if (event.key === 'Escape') {
            closeList();
        }
    });

    list.addEventListener('mousedown', (event) => {
        const option = event.target.closest('[role="option"]');
        if (!option) return;
        event.preventDefault();
        selectOption(option);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.cash-flow-report-autocomplete')) closeList();
    });

    const currencyTabs = Array.from(
        document.querySelectorAll('.cash-flow-report-currency-tab')
    );
    const currencySigns = Array.from(
        document.querySelectorAll('.cash-flow-report-currency-sign')
    );
    const beginningBalanceValue = document.querySelector(
        '#cashFlowReportBeginningBalance span:last-child'
    );
    const runningBalanceValue = document.querySelector(
        '#cashFlowReportRunningBalance span:last-child'
    );
    const volumeValue = document.getElementById('cashFlowReportVolume');
    const transactionsValue = document.querySelector(
        '#cashFlowReportTransactions span:last-child'
    );
    const depositsValue = document.querySelector(
        '#cashFlowReportDeposits span:last-child'
    );
    const balanceSummaries = {
        PHP: { volume: null, beginning: null, transactions: null, deposits: null, running: null },
        USD: { volume: null, beginning: null, transactions: null, deposits: null, running: null }
    };

    const updateSummaryAmountColor = (element, value) => {
        const amountContainer = element?.closest('dd') || element;
        amountContainer?.classList.toggle(
            'cash-flow-report-summary-negative',
            value !== null && Number(value) < 0
        );
    };

    const updateBalanceSummary = (currency) => {
        const summary = balanceSummaries[currency] || {};
        if (beginningBalanceValue) {
            beginningBalanceValue.textContent = summary.beginning === null
                ? '—'
                : formatAmount(summary.beginning);
            updateSummaryAmountColor(beginningBalanceValue, summary.beginning);
        }
        if (volumeValue) {
            volumeValue.textContent = summary.volume === null
                ? '—'
                : formatCount(summary.volume);
            updateSummaryAmountColor(volumeValue, summary.volume);
        }
        if (transactionsValue) {
            transactionsValue.textContent = summary.transactions === null
                ? '—'
                : formatAmount(summary.transactions);
            updateSummaryAmountColor(transactionsValue, summary.transactions);
        }
        if (depositsValue) {
            depositsValue.textContent = summary.deposits === null
                ? '—'
                : formatAmount(summary.deposits);
            updateSummaryAmountColor(depositsValue, summary.deposits);
        }
        if (runningBalanceValue) {
            runningBalanceValue.textContent = summary.running === null
                ? '—'
                : formatAmount(summary.running);
            updateSummaryAmountColor(runningBalanceValue, summary.running);
        }
    };

    currencyTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            currencyTabs.forEach((item) => {
                const isActive = item === tab;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            const sign = tab.dataset.currency === 'USD' ? '$' : '₱';
            currencySigns.forEach((currencySign) => {
                currencySign.textContent = sign;
            });
            updateBalanceSummary(tab.dataset.currency || 'PHP');

            document.querySelectorAll('.cash-flow-report-tab-panel').forEach((panel) => {
                panel.hidden = panel.id !== tab.getAttribute('aria-controls');
            });
        });
    });

    const filters = document.getElementById('cashFlowReportFilters');
    const monthInput = document.getElementById('cashFlowReportMonth');
    const resultPartner = document.getElementById('cashFlowReportResultPartner');
    const resultMonth = document.getElementById('cashFlowReportResultMonth');
    const forwardedDate = document.getElementById('cashFlowReportForwardedDate');
    const generateButton = document.getElementById('cashFlowReportGenerate');
    const reportEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/excelcontrol/summary-report.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const cashFlowEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/data-reports/cashflow-report.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const remarksSaveEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/data-reports/cashflow-remarks-save.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const cashFlowExportEndpoint = <?= json_encode(
        (string) ($appBaseUrl ?? '') . '/src/controllers/data-reports/cashflow-export.php',
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const cashFlowCsrfToken = <?= json_encode(
        csrfToken(),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    let temporaryRunningBalances = [];
    let temporaryCommissionAmounts = [];

    const formatReportDate = (value) => {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return '—';
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        const calendarDate = new Intl.DateTimeFormat('en-US', {
            month: 'long',
            day: '2-digit',
            year: 'numeric'
        }).format(date);
        const weekday = new Intl.DateTimeFormat('en-US', {
            weekday: 'long'
        }).format(date);
        return date.getDay() === 0 || date.getDay() === 6
            ? `${calendarDate}\n${weekday}`
            : calendarDate;
    };

    const previousMonthSameDay = (value) => {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return '—';
        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            year: 'numeric'
        }).format(new Date(parts[0], parts[1] - 2, parts[2]));
    };

    const previousMonthSameDayValue = (value) => {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return '';
        const date = new Date(parts[0], parts[1] - 2, parts[2]);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const formatCount = (value) => new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 0
    }).format(Number(value || 0));

    const formatAmount = (value) => new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0));

    const appendFormattedDate = (cell, value) => {
        const [calendarDate, weekday] = String(value || '—').split('\n');
        cell.append(document.createTextNode(calendarDate || '—'));
        if (weekday) {
            cell.append(document.createTextNode('\n'));
            const weekdayLabel = document.createElement('strong');
            weekdayLabel.className = 'cash-flow-report-weekday';
            weekdayLabel.textContent = weekday;
            cell.appendChild(weekdayLabel);
        }
    };

    const temporaryForwardedBalance = (currency, reportMonth = monthInput?.value || '') => {
        const monthValue = reportMonth;
        const [year, month] = monthValue.split('-').map(Number);
        if (!year || !month) return null;

        const forwarded = new Date(year, month - 1, 0);
        const forwardedDateValue = [
            forwarded.getFullYear(),
            String(forwarded.getMonth() + 1).padStart(2, '0'),
            String(forwarded.getDate()).padStart(2, '0')
        ].join('-');
        const account = String(latestCashFlowAccounts[currency.toLowerCase()] || '')
            .replace(/[^A-Za-z0-9]/g, '');
        const override = temporaryRunningBalances.find((item) =>
            String(item.currency || '').toUpperCase() === currency.toUpperCase()
            && String(item.date || '') === forwardedDateValue
            && String(item.account_number || '').replace(/[^A-Za-z0-9]/g, '') === account
        );

        return override && Number.isFinite(Number(override.amount))
            ? Number(override.amount)
            : null;
    };

    const temporaryCommissionAmount = (currency, reportMonth = monthInput?.value || '') => {
        const monthValue = reportMonth;
        const [year, month] = monthValue.split('-').map(Number);
        if (!year || !month) return null;

        const commissionMonth = new Date(year, month - 2, 1);
        const commissionMonthValue = [
            commissionMonth.getFullYear(),
            String(commissionMonth.getMonth() + 1).padStart(2, '0')
        ].join('-');
        const override = temporaryCommissionAmounts.find((item) =>
            String(item.currency || '').toUpperCase() === currency.toUpperCase()
            && String(item.date || '').slice(0, 7) === commissionMonthValue
        );

        return override && Number.isFinite(Number(override.amount))
            ? Number(override.amount)
            : null;
    };

    const resultsLayout = document.getElementById('cashFlowReportResultsLayout');
    const reportStatus = document.getElementById('cashFlowReportStatus');
    const exportExcelButton = document.getElementById('cashFlowReportExportExcel');
    let latestCashFlowAccounts = { php: '', usd: '' };
    const endingBalanceStorageKey = 'cashFlowReportEndingBalancesV1';
    let endingBalanceCache = {};
    try {
        endingBalanceCache = JSON.parse(
            sessionStorage.getItem(endingBalanceStorageKey) || '{}'
        );
    } catch (error) {
        endingBalanceCache = {};
    }

    const endingBalanceKey = (partner, month, currency) => [
        String(partner || '').trim().toUpperCase(),
        String(month || ''),
        String(currency || '').toUpperCase()
    ].join('|');

    const previousMonthValue = (monthValue) => {
        const [year, month] = String(monthValue || '').split('-').map(Number);
        if (!year || !month) return '';
        const previous = new Date(year, month - 2, 1);
        return `${previous.getFullYear()}-${String(previous.getMonth() + 1).padStart(2, '0')}`;
    };

    const cachedPreviousEndingBalance = (currency) => {
        const key = endingBalanceKey(
            input.value,
            previousMonthValue(monthInput?.value),
            currency
        );
        const value = endingBalanceCache[key];
        return Number.isFinite(Number(value)) ? Number(value) : null;
    };

    const rememberEndingBalance = (currency, value) => {
        if (!Number.isFinite(Number(value)) || !monthInput?.value) return;
        const key = endingBalanceKey(input.value, monthInput.value, currency);
        endingBalanceCache[key] = Number(value);
        try {
            sessionStorage.setItem(
                endingBalanceStorageKey,
                JSON.stringify(endingBalanceCache)
            );
        } catch (error) {
            // The in-memory cache still keeps month-to-month continuity.
        }
    };

    const updateRemarkAppearance = (select) => {
        const isValid = select.value === 'VALID';
        select.classList.toggle('is-valid', isValid);
        select.classList.toggle('is-not-valid', !isValid);
    };

    const updateRemarkSaveIcon = (select, state) => {
        const icon = select.nextElementSibling;
        if (icon?.classList.contains('cash-flow-report-status-icon')) {
            icon.className = `cash-flow-report-status-icon is-${state}`;
            icon.textContent = state === 'saved' ? '✓' : (state === 'error' ? '×' : '');
            icon.title = state === 'saving'
                ? 'Saving status'
                : (state === 'saved' ? 'Status saved' : 'Status was not saved');
        }
    };

    const saveRemark = async (select) => {
        const transactionDate = select.dataset.tranDate || '';
        const currency = (select.dataset.currency || '').toLowerCase();
        const previousValue = select.dataset.initialValue || 'NOT VALID';
        if (!transactionDate || !['php', 'usd'].includes(currency)) return;

        select.disabled = true;
        select.setAttribute('aria-busy', 'true');
        updateRemarkSaveIcon(select, 'saving');
        try {
            const formData = new FormData();
            formData.append('csrf_token', cashFlowCsrfToken);
            formData.append('partner', input.value.trim());
            formData.append('changes', JSON.stringify([{
                tran_date: transactionDate,
                currency,
                remarks: select.value === 'VALID' ? 'valid' : 'not-valid'
            }]));

            const response = await fetch(remarksSaveEndpoint, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const responseText = await response.text();
            let data = null;
            try {
                data = JSON.parse(responseText);
            } catch (error) {
                data = null;
            }
            if (!response.ok || !data?.success) {
                throw new Error(data?.message || responseText || 'Unable to save status.');
            }
            select.dataset.initialValue = select.value;
            updateRemarkSaveIcon(select, 'saved');
        } catch (error) {
            select.value = previousValue;
            updateRemarkAppearance(select);
            updateRemarkSaveIcon(select, 'error');
            const message = error instanceof Error ? error.message : 'Unable to save status.';
            if (window.Swal) {
                await window.Swal.fire({
                    icon: 'error',
                    title: 'Unable to Save Status',
                    text: message,
                    confirmButtonText: 'Okay',
                    confirmButtonColor: '#ed2947'
                });
            } else {
                window.alert(message);
            }
        } finally {
            select.disabled = false;
            select.removeAttribute('aria-busy');
        }
    };

    const addRemarksDropdown = (
        cell,
        savedRemark = 'not-valid',
        transactionDate = '',
        currency = 'PHP'
    ) => {
        cell.classList.add('cash-flow-report-remarks-cell');
        const select = document.createElement('select');
        const initialValue = savedRemark === 'valid' ? 'VALID' : 'NOT VALID';
        select.className = `cash-flow-report-remarks ${initialValue === 'VALID' ? 'is-valid' : 'is-not-valid'}`;
        select.setAttribute('aria-label', 'Status');
        select.dataset.tranDate = transactionDate;
        select.dataset.currency = currency.toUpperCase();

        [['NOT VALID', 'NOT YET VALIDATED'], ['VALID', 'VALIDATED']].forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.appendChild(option);
        });

        select.value = initialValue;
        select.dataset.initialValue = initialValue;
        select.addEventListener('change', async () => {
            updateRemarkAppearance(select);
            await saveRemark(select);
        });
        const statusIcon = document.createElement('span');
        statusIcon.className = 'cash-flow-report-status-icon';
        statusIcon.setAttribute('aria-hidden', 'true');
        cell.append(select, statusIcon);
        updateRemarkAppearance(select);
    };

    const exportNumber = (value) => {
        const normalized = String(value || '').replace(/,/g, '').trim();
        return normalized === '' || normalized.includes('—') ? null : Number(normalized);
    };

    const collectExportRows = (currency) => {
        const body = document.getElementById(`cashFlowReport${currency}TableBody`);
        if (!body) return [];
        return Array.from(body.rows).filter(
            (row) => !row.classList.contains('cash-flow-report-empty-row')
                && !row.classList.contains('cash-flow-report-forwarded-table-row')
        ).map((row) => {
            const cells = Array.from(row.cells);
            const commission = row.classList.contains('cash-flow-report-commission-row');
            const remarksSelect = cells[commission ? 6 : 11]?.querySelector(
                '.cash-flow-report-remarks'
            );
            const principal = commission
                ? exportNumber(cells[1]?.textContent)
                : exportNumber(row.dataset.settlementAmount);
            return {
                commission,
                date: cells[0]?.textContent.trim() || '',
                volume: commission ? 0 : exportNumber(cells[1]?.textContent),
                principal,
                payout_principal: commission ? null : exportNumber(cells[2]?.textContent),
                payout_commission: commission
                    ? exportNumber(cells[1]?.textContent)
                    : exportNumber(cells[3]?.textContent),
                sendout_principal: commission ? null : exportNumber(cells[4]?.textContent),
                sendout_charge: commission ? null : exportNumber(cells[5]?.textContent),
                sendout_commission: commission ? null : exportNumber(cells[6]?.textContent),
                adjustment: commission ? null : exportNumber(cells[7]?.textContent),
                net_transaction_amount: commission
                    ? exportNumber(cells[3]?.textContent)
                    : exportNumber(cells[8]?.textContent),
                deposit: commission ? null : exportNumber(cells[9]?.textContent),
                running: exportNumber(cells[commission ? 5 : 10]?.textContent),
                remarks: remarksSelect?.value || 'NOT VALID'
            };
        });
    };

    const submitCashFlowExport = (format) => {
        const partner = input.value.trim();
        const month = monthInput?.value || '';
        if (!partner || !month || resultsLayout?.hidden) return;

        const reports = {};
        ['PHP', 'USD'].forEach((currency) => {
            reports[currency] = {
                rows: collectExportRows(currency),
                forwarded_date: forwardedDate?.textContent || '',
                summary: {
                    ...balanceSummaries[currency],
                    adjustment: 0
                }
            };
        });
        const payload = {
            partner,
            month,
            bank_label: partnerBankLabels[partner.toLocaleUpperCase()] || '',
            accounts: latestCashFlowAccounts,
            reports
        };

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = cashFlowExportEndpoint;
        form.style.display = 'none';
        [['csrf_token', cashFlowCsrfToken], ['payload', JSON.stringify(payload)], ['format', format]].forEach(
            ([name, value]) => {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value;
                form.appendChild(field);
            }
        );
        document.body.appendChild(form);
        form.submit();
        window.setTimeout(() => form.remove(), 1000);
    };

    exportExcelButton?.addEventListener('click', async () => {
        if (!input.value.trim() || !monthInput?.value || resultsLayout?.hidden) return;

        if (!window.Swal) {
            submitCashFlowExport('xls');
            return;
        }

        const choice = await window.Swal.fire({
            title: 'Export To',
            text: 'Choose the file format.',
            icon: 'question',
            showDenyButton: true,
            confirmButtonText: 'Excel Format',
            denyButtonText: 'PDF Format',
            confirmButtonColor: '#17803d',
            denyButtonColor: '#ed2947'
        });

        if (choice.isConfirmed) submitCashFlowExport('xls');
        if (choice.isDenied) submitCashFlowExport('pdf');
    });

    const updateGrandTotal = (currency, totals = null) => {
        const foot = document.getElementById(`cashFlowReport${currency}TableFoot`);
        if (!foot) return;
        const values = totals || {};
        const amountKeys = [
            'payout-principal',
            'payout-commission',
            'sendout-principal',
            'sendout-charge',
            'sendout-commission',
            'adjustment',
            'net-transaction',
            'deposit',
            'running'
        ];
        const volumeCell = foot.querySelector('[data-total="volume"]');
        if (volumeCell) {
            volumeCell.textContent = totals ? formatCount(values.volume) : '—';
        }
        amountKeys.forEach((key) => {
            const cell = foot.querySelector(`[data-total="${key}"]`);
            if (cell) {
                cell.textContent = totals ? formatAmount(values[key]) : '—';
                cell.classList.toggle(
                    'cash-flow-report-negative-balance',
                    Boolean(totals) && Number(values[key]) < 0
                );
            }
        });
    };

    const replaceTableMessage = (currency, message) => {
        const body = document.getElementById(`cashFlowReport${currency}TableBody`);
        if (!body) return;
        const row = body.insertRow();
        const cell = row.insertCell();
        body.replaceChildren(row);
        row.className = 'cash-flow-report-empty-row';
        cell.colSpan = 12;
        cell.textContent = message;
        updateGrandTotal(currency);
    };

    const renderSettlementRows = (
        currency,
        report,
        dailyDeposits = {},
        beginningBalance = 0,
        commissionReport = {},
        monthlyCommissionFxTotal = 0,
        dailyRemarks = {},
        commissionRemarks = {},
        previousMonthDeposits = {},
        previousMonthBeginningBalance = 0,
        priorCommissionReport = {},
        priorCommissionFxTotal = 0,
        resolvedForwardedBalance = null
    ) => {
        const body = document.getElementById(`cashFlowReport${currency}TableBody`);
        if (!body) return;
        const rows = Array.isArray(report?.rows) ? report.rows : [];
        const commissionRows = Array.isArray(commissionReport?.rows) ? commissionReport.rows : [];
        const calculateCommissionTotal = (sourceReport, nullTypeFxTotal) => {
            const sourceRows = Array.isArray(sourceReport?.rows) ? sourceReport.rows : [];
            return sourceRows.reduce((total, commissionItem) => {
                const payout = commissionItem?.payout || {};
                const sendout = commissionItem?.sendout || {};
                return total
                    + Number(payout.fx || 0)
                    + Number(payout.commission || 0)
                    + Number(sendout.fx || 0);
            }, 0) - Number(nullTypeFxTotal || 0);
        };
        let monthlyCommissionAmount = calculateCommissionTotal(
            commissionReport,
            monthlyCommissionFxTotal
        );
        const temporaryCommission = temporaryCommissionAmount(currency);
        if (temporaryCommission !== null) {
            monthlyCommissionAmount = temporaryCommission;
        }
        const calculatedPriorCommissionAmount = calculateCommissionTotal(
            priorCommissionReport,
            priorCommissionFxTotal
        );
        const storedPriorCommissionAmount = temporaryCommissionAmount(
            currency,
            previousMonthValue(monthInput?.value)
        );
        const priorCommissionAmount = storedPriorCommissionAmount !== null
            ? storedPriorCommissionAmount
            : calculatedPriorCommissionAmount;
        const priorMonthStoredEndingBalance = temporaryForwardedBalance(
            currency,
            previousMonthValue(monthInput?.value)
        );
        let forwardedBeginningBalance = commissionRows.length
            ? commissionRows.reduce((running, previousItem) => {
                const previousPrincipal = Number(previousItem?.settlement_amount || 0);
                const previousDeposit = Object.prototype.hasOwnProperty.call(
                    previousMonthDeposits,
                    previousItem?.date
                ) ? Number(previousMonthDeposits[previousItem.date] || 0) : 0;
                return running - previousPrincipal + previousDeposit;
            }, priorMonthStoredEndingBalance !== null
                ? priorMonthStoredEndingBalance
                : Number(previousMonthBeginningBalance || 0)) - priorCommissionAmount
            : Number(beginningBalance || 0);
        const temporaryBalance = temporaryForwardedBalance(currency);
        if (temporaryBalance !== null) {
            forwardedBeginningBalance = temporaryBalance;
        } else if (!commissionRows.length) {
            const cachedEndingBalance = cachedPreviousEndingBalance(currency);
            if (cachedEndingBalance !== null) {
                forwardedBeginningBalance = cachedEndingBalance;
            }
        }
        if (Number.isFinite(Number(resolvedForwardedBalance))) {
            forwardedBeginningBalance = Number(resolvedForwardedBalance);
        }
        const totalVolume = rows.reduce(
            (total, item) => total + Number(item?.settlement_volume || 0),
            0
        );
        const totalPrincipal = rows.reduce(
            (total, item) => total + Number(item?.settlement_amount || 0),
            0
        ) + monthlyCommissionAmount;
        const totalPayoutPrincipal = rows.reduce(
            (total, item) => total + Number(item?.payout?.principal || 0),
            0
        );
        const totalSendoutPrincipal = rows.reduce(
            (total, item) => total + Number(item?.sendout?.principal || 0),
            0
        );
        const totalSendoutCharge = rows.reduce(
            (total, item) => total + Number(item?.sendout?.fee || 0),
            0
        );
        const totalSendoutCommission = rows.reduce(
            (total, item) => total + Number(item?.sendout?.commission || 0),
            0
        );
        const totalDeposits = Object.values(dailyDeposits || {}).reduce(
            (total, amount) => total + Number(amount || 0),
            0
        );
        let runningBalance = forwardedBeginningBalance;
        body.replaceChildren();

        if (!rows.length) {
            replaceTableMessage(currency, 'No settlement data found for the selected filters.');
            return;
        }

        const forwardedRow = body.insertRow();
        forwardedRow.className = 'cash-flow-report-forwarded-table-row';
        const forwardedDateCell = forwardedRow.insertCell();
        forwardedDateCell.className = 'cash-flow-report-forwarded-date-cell';
        // The Ending Balance row only needs the calendar date. Weekend labels
        // remain visible on the regular daily transaction rows.
        forwardedDateCell.textContent = String(forwardedDate?.textContent || '—')
            .split('\n')[0];

        const forwardedLabelCell = forwardedRow.insertCell();
        forwardedLabelCell.className = 'cash-flow-report-forwarded-label-cell';
        forwardedLabelCell.colSpan = 9;
        forwardedLabelCell.textContent = '(Ending Balance)';

        const forwardedAmountCell = forwardedRow.insertCell();
        forwardedAmountCell.className = 'cash-flow-report-forwarded-amount-cell';
        forwardedAmountCell.textContent = formatAmount(forwardedBeginningBalance);
        if (forwardedBeginningBalance < 0) {
            forwardedAmountCell.classList.add('cash-flow-report-negative-balance');
        }

        const forwardedStatusCell = forwardedRow.insertCell();
        forwardedStatusCell.className = 'cash-flow-report-forwarded-status-cell';
        forwardedStatusCell.textContent = '';

        const commissionAnchor = rows.find(
            (item) => Number(String(item?.date || '').slice(-2)) === 10
        );
        const commissionAnchorDate = String(commissionAnchor?.date || '');
        let commissionInsertionDate = commissionAnchorDate;
        if (commissionAnchorDate) {
            const [anchorYear, anchorMonth, anchorDay] = commissionAnchorDate
                .split('-')
                .map(Number);
            const anchorDate = new Date(anchorYear, anchorMonth - 1, anchorDay);
            if (anchorDate.getDay() === 6) {
                anchorDate.setDate(anchorDate.getDate() + 1);
                commissionInsertionDate = [
                    anchorDate.getFullYear(),
                    String(anchorDate.getMonth() + 1).padStart(2, '0'),
                    String(anchorDate.getDate()).padStart(2, '0')
                ].join('-');
            }
        }

        rows.forEach((item) => {
            const principal = Number(item?.settlement_amount || 0);
            const payout = item?.payout || {};
            const sendout = item?.sendout || {};
            const adjustment = 0;
            const deposit = Object.prototype.hasOwnProperty.call(dailyDeposits, item?.date)
                ? Number(dailyDeposits[item.date] || 0)
                : 0;
            runningBalance = runningBalance - principal + adjustment + deposit;
            const row = body.insertRow();
            row.dataset.settlementAmount = String(principal);
            const values = [
                formatReportDate(item?.date),
                formatCount(item?.settlement_volume),
                formatAmount(payout.principal),
                '—',
                formatAmount(sendout.principal),
                formatAmount(sendout.fee),
                formatAmount(sendout.commission),
                '—',
                formatAmount(principal),
                Object.prototype.hasOwnProperty.call(dailyDeposits, item?.date)
                    ? formatAmount(deposit)
                    : '—',
                formatAmount(runningBalance),
                '—'
            ];
            values.forEach((value, columnIndex) => {
                const cell = row.insertCell();
                if (columnIndex === 11) {
                    addRemarksDropdown(cell, dailyRemarks[item.date], item.date, currency);
                } else if (columnIndex === 0) {
                    appendFormattedDate(cell, value);
                } else {
                    cell.textContent = value;
                }
                if (columnIndex === 10 && runningBalance < 0) {
                    cell.classList.add('cash-flow-report-negative-balance');
                }
            });

            if (String(item?.date || '') === commissionInsertionDate) {
                const commissionRow = body.insertRow();
                commissionRow.className = 'cash-flow-report-commission-row';

                const commissionCell = commissionRow.insertCell();
                commissionCell.colSpan = 3;
                commissionCell.textContent = `${previousMonthSameDay(commissionAnchorDate)} Commission`;
                const commissionDate = previousMonthSameDayValue(commissionAnchorDate);

                const commissionAmountCell = commissionRow.insertCell();
                commissionAmountCell.textContent = formatAmount(monthlyCommissionAmount);
                const commissionEmptyCell = commissionRow.insertCell();
                commissionEmptyCell.colSpan = 4;
                commissionEmptyCell.textContent = '';
                commissionRow.insertCell().textContent = formatAmount(monthlyCommissionAmount);
                commissionRow.insertCell().textContent = '';
                runningBalance -= monthlyCommissionAmount;
                const commissionRunningBalanceCell = commissionRow.insertCell();
                commissionRunningBalanceCell.textContent = formatAmount(runningBalance);
                if (runningBalance < 0) {
                    commissionRunningBalanceCell.classList.add('cash-flow-report-negative-balance');
                }
                const commissionRemarksCell = commissionRow.insertCell();
                addRemarksDropdown(
                    commissionRemarksCell,
                    commissionRemarks[commissionDate],
                    commissionDate,
                    currency
                );
            }
        });

        balanceSummaries[currency] = {
            volume: totalVolume,
            beginning: forwardedBeginningBalance,
            transactions: totalPrincipal,
            deposits: totalDeposits,
            running: runningBalance
        };
        updateGrandTotal(currency, {
            volume: totalVolume,
            'payout-principal': totalPayoutPrincipal,
            'payout-commission': monthlyCommissionAmount,
            'sendout-principal': totalSendoutPrincipal,
            'sendout-charge': totalSendoutCharge,
            'sendout-commission': totalSendoutCommission,
            adjustment: 0,
            'net-transaction': totalPrincipal,
            deposit: totalDeposits,
            running: runningBalance
        });
        rememberEndingBalance(currency, runningBalance);
        const selectedTab = document.querySelector(
            '.cash-flow-report-currency-tab[aria-selected="true"]'
        );
        if ((selectedTab?.dataset.currency || 'PHP') === currency) {
            updateBalanceSummary(currency);
        }
    };

    filters?.addEventListener('submit', async (event) => {
        event.preventDefault();
        updateBankDepositHeaders();

        if (resultPartner) {
            resultPartner.textContent = input.value.trim() || '—';
        }

        if (resultMonth) {
            const monthValue = monthInput?.value || '';
            if (!monthValue) {
                resultMonth.textContent = '—';
                if (forwardedDate) forwardedDate.textContent = '—';
            } else {
                const [year, month] = monthValue.split('-').map(Number);
                const dateFormatter = new Intl.DateTimeFormat('en-US', {
                    month: 'long',
                    day: '2-digit',
                    year: 'numeric'
                });
                const monthYearFormatter = new Intl.DateTimeFormat('en-US', {
                    month: 'long',
                    year: 'numeric'
                });

                resultMonth.textContent = monthYearFormatter.format(
                    new Date(year, month - 1, 1)
                );

                if (forwardedDate) {
                    const forwardedDateValue = new Date(year, month - 1, 0);
                    forwardedDate.textContent = dateFormatter.format(forwardedDateValue);
                }
            }
        }

        const partner = input.value.trim();
        const monthValue = monthInput?.value || '';
        if (!partner || !monthValue) return;

        if (resultsLayout) resultsLayout.hidden = true;
        if (reportStatus) {
            reportStatus.hidden = false;
            reportStatus.classList.remove('is-error');
            reportStatus.textContent = `Loading ${partner} Cash Flow report...`;
        }

        const [year, month] = monthValue.split('-').map(Number);
        const startDate = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDay = String(new Date(year, month, 0).getDate()).padStart(2, '0');
        const endDate = `${year}-${String(month).padStart(2, '0')}-${lastDay}`;
        const previousMonthDate = new Date(year, month - 2, 1);
        const previousYear = previousMonthDate.getFullYear();
        const previousMonth = previousMonthDate.getMonth() + 1;
        const previousMonthNumber = String(previousMonth).padStart(2, '0');
        const previousStartDate = `${previousYear}-${previousMonthNumber}-01`;
        const previousLastDay = String(
            new Date(previousYear, previousMonth, 0).getDate()
        ).padStart(2, '0');
        const previousEndDate = `${previousYear}-${previousMonthNumber}-${previousLastDay}`;
        const priorMonthDate = new Date(year, month - 3, 1);
        const priorYear = priorMonthDate.getFullYear();
        const priorMonth = priorMonthDate.getMonth() + 1;
        const priorMonthNumber = String(priorMonth).padStart(2, '0');
        const priorStartDate = `${priorYear}-${priorMonthNumber}-01`;
        const priorLastDay = String(new Date(priorYear, priorMonth, 0).getDate()).padStart(2, '0');
        const priorEndDate = `${priorYear}-${priorMonthNumber}-${priorLastDay}`;

        replaceTableMessage('PHP', 'Loading settlement data…');
        replaceTableMessage('USD', 'Loading settlement data…');
        if (generateButton) {
            generateButton.disabled = true;
            generateButton.setAttribute('aria-busy', 'true');
            generateButton.textContent = 'Loading...';
        }

        try {
            const params = new URLSearchParams({
                partner,
                start_date: startDate,
                end_date: endDate,
                cashflow_only: '1'
            });
            const previousParams = new URLSearchParams({
                partner,
                start_date: previousStartDate,
                end_date: previousEndDate,
                cashflow_only: '1'
            });
            const priorParams = new URLSearchParams({
                partner,
                start_date: priorStartDate,
                end_date: priorEndDate,
                cashflow_only: '1'
            });
            const priorCashFlowParams = new URLSearchParams(priorParams);
            priorCashFlowParams.set('commission_only', '1');
            const requestOptions = {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            };
            const [
                settlementResponse,
                cashFlowResponse,
                previousSettlementResponse,
                previousCashFlowResponse,
                priorSettlementResponse,
                priorCashFlowResponse
            ] = await Promise.all([
                fetch(`${reportEndpoint}?${params.toString()}`, requestOptions),
                fetch(`${cashFlowEndpoint}?${params.toString()}`, requestOptions),
                fetch(`${reportEndpoint}?${previousParams.toString()}`, requestOptions),
                fetch(`${cashFlowEndpoint}?${previousParams.toString()}`, requestOptions),
                fetch(`${reportEndpoint}?${priorParams.toString()}`, requestOptions),
                fetch(`${cashFlowEndpoint}?${priorCashFlowParams.toString()}`, requestOptions)
            ]);
            const [
                data,
                cashFlowData,
                previousData,
                previousCashFlowData,
                priorData,
                priorCashFlowData
            ] = await Promise.all([
                settlementResponse.json(),
                cashFlowResponse.json(),
                previousSettlementResponse.json(),
                previousCashFlowResponse.json(),
                priorSettlementResponse.json(),
                priorCashFlowResponse.json()
            ]);
            if (!settlementResponse.ok || !data?.success) {
                throw new Error(data?.error || 'Unable to load settlement data.');
            }
            if (!cashFlowResponse.ok || !cashFlowData?.success) {
                throw new Error(cashFlowData?.error || 'Unable to load Cash Flow bank deposits.');
            }
            latestCashFlowAccounts = cashFlowData.accounts || { php: '', usd: '' };
            temporaryRunningBalances = [
                ...(Array.isArray(cashFlowData.ending_balances)
                    ? cashFlowData.ending_balances
                    : []),
                ...(Array.isArray(previousCashFlowData.ending_balances)
                    ? previousCashFlowData.ending_balances
                    : [])
            ];
            temporaryCommissionAmounts = [
                ...(Array.isArray(previousCashFlowData.monthly_commissions)
                    ? previousCashFlowData.monthly_commissions
                    : []),
                ...(Array.isArray(priorCashFlowData.monthly_commissions)
                    ? priorCashFlowData.monthly_commissions
                    : [])
            ];
            updateBankAccountHeaders(latestCashFlowAccounts);
            if (!previousSettlementResponse.ok || !previousData?.success) {
                throw new Error(previousData?.error || 'Unable to load previous-month settlement data.');
            }
            if (!previousCashFlowResponse.ok || !previousCashFlowData?.success) {
                throw new Error(previousCashFlowData?.error || 'Unable to load previous-month commission data.');
            }
            if (!priorSettlementResponse.ok || !priorData?.success) {
                throw new Error(priorData?.error || 'Unable to load prior-month settlement data.');
            }
            if (!priorCashFlowResponse.ok || !priorCashFlowData?.success) {
                throw new Error(priorCashFlowData?.error || 'Unable to load prior-month commission data.');
            }

            const resolvedForwardedBalances = { PHP: null, USD: null };
            const latestStoredBalances = Array.isArray(cashFlowData.latest_ending_balances)
                ? cashFlowData.latest_ending_balances
                : [];
            const storedSeeds = ['PHP', 'USD'].map((currency) => {
                const record = latestStoredBalances.find((item) =>
                    String(item?.currency || '').toUpperCase() === currency
                    && /^\d{4}-\d{2}-\d{2}$/.test(String(item?.date || ''))
                    && Number.isFinite(Number(item?.amount))
                );
                return record ? { ...record, currency } : null;
            }).filter(Boolean);

            if (storedSeeds.length) {
                const historyStartDate = storedSeeds
                    .map((seed) => `${String(seed.date).slice(0, 7)}-01`)
                    .sort()[0];
                const historyParams = new URLSearchParams({
                    partner,
                    start_date: historyStartDate,
                    end_date: previousEndDate,
                    cashflow_only: '1'
                });
                const [historySettlementResponse, historyCashFlowResponse] = await Promise.all([
                    fetch(`${reportEndpoint}?${historyParams.toString()}`, requestOptions),
                    fetch(`${cashFlowEndpoint}?${historyParams.toString()}`, requestOptions)
                ]);
                const [historyData, historyCashFlowData] = await Promise.all([
                    historySettlementResponse.json(),
                    historyCashFlowResponse.json()
                ]);
                if (!historySettlementResponse.ok || !historyData?.success) {
                    throw new Error(historyData?.error || 'Unable to calculate prior Ending Balance.');
                }
                if (!historyCashFlowResponse.ok || !historyCashFlowData?.success) {
                    throw new Error(historyCashFlowData?.error || 'Unable to calculate prior Ending Balance.');
                }

                storedSeeds.forEach((seed) => {
                    const currencyKey = seed.currency.toLowerCase();
                    const rows = Array.isArray(historyData.settlement_reports?.[currencyKey]?.rows)
                        ? historyData.settlement_reports[currencyKey].rows
                        : [];
                    const transactionTotal = rows.reduce((total, item) => {
                        const date = String(item?.date || '');
                        return date > seed.date && date <= previousEndDate
                            ? total + Number(item?.settlement_amount || 0)
                            : total;
                    }, 0);
                    const deposits = historyCashFlowData.deposits?.[currencyKey] || {};
                    const depositTotal = Object.entries(deposits).reduce(
                        (total, [date, amount]) => date > seed.date && date <= previousEndDate
                            ? total + Number(amount || 0)
                            : total,
                        0
                    );
                    const commissionStartMonth = String(seed.date).slice(0, 7);
                    const commissionCutoffMonth = previousStartDate.slice(0, 7);
                    const commissionAmountsByMonth = {};
                    rows.forEach((item) => {
                        const itemMonth = String(item?.date || '').slice(0, 7);
                        if (itemMonth < commissionStartMonth || itemMonth >= commissionCutoffMonth) {
                            return;
                        }
                        const payout = item?.payout || {};
                        const sendout = item?.sendout || {};
                        commissionAmountsByMonth[itemMonth] = Number(
                            commissionAmountsByMonth[itemMonth] || 0
                        ) + Number(payout.fx || 0)
                            + Number(payout.commission || 0)
                            + Number(sendout.fx || 0);
                    });
                    Object.entries(historyCashFlowData.commission_fx?.[currencyKey] || {})
                        .forEach(([date, amount]) => {
                            const itemMonth = String(date).slice(0, 7);
                            if (itemMonth < commissionStartMonth || itemMonth >= commissionCutoffMonth) {
                                return;
                            }
                            commissionAmountsByMonth[itemMonth] = Number(
                                commissionAmountsByMonth[itemMonth] || 0
                            ) - Number(amount || 0);
                        });
                    (
                        Array.isArray(historyCashFlowData.monthly_commissions)
                            ? historyCashFlowData.monthly_commissions
                            : []
                    ).forEach((item) => {
                        const itemCurrency = String(item?.currency || '').toUpperCase();
                        const itemMonth = String(item?.date || '').slice(0, 7);
                        if (itemCurrency === seed.currency
                            && itemMonth >= commissionStartMonth
                            && itemMonth < commissionCutoffMonth) {
                            commissionAmountsByMonth[itemMonth] = Number(item?.amount || 0);
                        }
                    });
                    const commissionTotal = Object.values(commissionAmountsByMonth)
                        .reduce((total, amount) => total + Number(amount || 0), 0);
                    resolvedForwardedBalances[seed.currency] = Number(seed.amount)
                        - transactionTotal
                        + depositTotal
                        - commissionTotal;
                });
            }

            renderSettlementRows(
                'PHP',
                data.settlement_reports?.php,
                cashFlowData.deposits?.php,
                cashFlowData.beginning_balances?.php,
                previousData.settlement_reports?.php,
                previousCashFlowData.commission_fx_totals?.php,
                cashFlowData.remarks?.php,
                previousCashFlowData.remarks?.php,
                previousCashFlowData.deposits?.php,
                previousCashFlowData.beginning_balances?.php,
                priorData.settlement_reports?.php,
                priorCashFlowData.commission_fx_totals?.php,
                resolvedForwardedBalances.PHP
            );
            renderSettlementRows(
                'USD',
                data.settlement_reports?.usd,
                cashFlowData.deposits?.usd,
                cashFlowData.beginning_balances?.usd,
                previousData.settlement_reports?.usd,
                previousCashFlowData.commission_fx_totals?.usd,
                cashFlowData.remarks?.usd,
                previousCashFlowData.remarks?.usd,
                previousCashFlowData.deposits?.usd,
                previousCashFlowData.beginning_balances?.usd,
                priorData.settlement_reports?.usd,
                priorCashFlowData.commission_fx_totals?.usd,
                resolvedForwardedBalances.USD
            );
            if (reportStatus) reportStatus.hidden = true;
            if (resultsLayout) resultsLayout.hidden = false;
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to load settlement data.';
            replaceTableMessage('PHP', message);
            replaceTableMessage('USD', message);
            if (reportStatus) {
                reportStatus.hidden = false;
                reportStatus.classList.add('is-error');
                reportStatus.textContent = message;
            }
        } finally {
            if (generateButton) {
                generateButton.disabled = false;
                generateButton.removeAttribute('aria-busy');
                generateButton.textContent = 'Generate';
            }
        }
    });
})();
</script>
