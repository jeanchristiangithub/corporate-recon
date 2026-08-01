<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';

$cashFlowReportPartners = [];

try {
    $statement = masterDataConnection()->query(
        "SELECT DISTINCT partner_name
         FROM corpo_partner_masterfile
         WHERE partner_name IS NOT NULL AND partner_name <> ''
         ORDER BY partner_name"
    );

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $partnerName) {
        $partnerName = trim((string) $partnerName);
        if ($partnerName !== '') {
            $cashFlowReportPartners[] = $partnerName;
        }
    }
} catch (Throwable $exception) {
    $cashFlowReportPartners = [];
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

    <div class="cash-flow-report-results-layout">
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
                <div>
                    <dt>
                        Beginning Balance
                        <i>Forwarded <span id="cashFlowReportForwardedDate">—</span></i>
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
                                    <th scope="col" rowspan="2">Date</th>
                                    <th scope="colgroup" colspan="3">Partner Settlement Data</th>
                                    <th scope="col" rowspan="2">Bank Deposit (BPI)</th>
                                    <th scope="col" rowspan="2">Running Balance</th>
                                    <th scope="col" rowspan="2">Remarks</th>
                                </tr>
                                <tr>
                                    <th scope="col">Volume</th>
                                    <th scope="col">Principal</th>
                                    <th scope="col">Adjustment / Refund</th>
                                </tr>
                            </thead>
                            <tbody id="cashFlowReport<?= $currency ?>TableBody">
                                <tr class="cash-flow-report-empty-row">
                                    <td colspan="7">Select a corporate partner and month, then click Generate.</td>
                                </tr>
                            </tbody>
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
    let activeIndex = -1;

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
        closeList();
        input.focus();
    };

    input.addEventListener('focus', filterOptions);
    input.addEventListener('input', filterOptions);
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

    filters?.addEventListener('submit', (event) => {
        event.preventDefault();

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
                    day: 'numeric',
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
                    forwardedDate.textContent = dateFormatter.format(
                        new Date(year, month - 1, 0)
                    );
                }
            }
        }
    });
})();
</script>
