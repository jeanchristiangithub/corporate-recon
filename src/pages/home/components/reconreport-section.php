<?php
// Recon Report logic concept:
// The page renders the table shell, then JavaScript requests detailed rows from the
// existing reconciliation controllers and displays them in this section.
require_once __DIR__ . '/../../../config/db.php';

$reconReportPartners = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT partner_id, partner_name FROM masterdata.corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $seenPartnerKeys = [];
    foreach ($rows as $row) {
        $partnerId = trim((string)($row['partner_id'] ?? ''));
        $partnerName = trim((string)($row['partner_name'] ?? ''));
        if ($partnerName === '') {
            continue;
        }
        $key = strtoupper($partnerId . '|' . $partnerName);
        if (isset($seenPartnerKeys[$key])) {
            continue;
        }
        $seenPartnerKeys[$key] = true;
        $reconReportPartners[] = [
            'id' => $partnerId,
            'name' => $partnerName,
            'label' => ($partnerId !== '' ? $partnerId . ' - ' : '') . $partnerName,
        ];
    }
} catch (Throwable $e) {
    $reconReportPartners = [];
}

$reconReportControllerMap = [
    'MONEYGRAM' => 'moneygram-recon.php',
    'METROBANK HEAD OFFICE' => 'mbtc-recon.php',
    'SKYBRIDGE PAYMENT INC.' => 'skybridgepaymentinc-recon.php',
    'WORLD INTERNATIONAL COMMUNICATIONS' => 'wic-recon.php',
    'WORLDCOM INTERNATIONAL COMMUNICATIONS' => 'wic-recon.php',
    'RCBC' => 'rcbc-recon.php',
];
?>
<section class="recon-report-section" aria-labelledby="reconReportTitle">
    <div class="recon-report-inner">
        <h2 id="reconReportTitle" class="recon-report-title">Reconciliation Report</h2>

        <form class="recon-report-toolbar" id="reconReportFilterForm" action="#" method="get">
            <label class="recon-report-field" for="reconReportCorporatePartner">
                <span>Corporate Partner</span>
                <div class="recon-report-autocomplete">
                    <input
                        id="reconReportCorporatePartner"
                        name="corporate_partner"
                        type="text"
                        placeholder="Corporate partner"
                        autocomplete="off"
                    >
                    <ul class="recon-report-autocomplete-list" id="reconReportCorporatePartnerSuggestions" role="listbox" hidden></ul>
                </div>
            </label>

            <label class="recon-report-field recon-report-field--date" for="reconReportStartDate">
                <span>Start Date</span>
                <input id="reconReportStartDate" name="start_date" type="date">
            </label>

            <label class="recon-report-field recon-report-field--date" for="reconReportEndDate">
                <span>End Date</span>
                <input id="reconReportEndDate" name="end_date" type="date">
            </label>

            <label class="recon-report-field recon-report-field--select" for="reconReportCurrency">
                <span>Currency</span>
                <select id="reconReportCurrency" name="currency">
                    <option value="">All</option>
                    <option value="PHP">PHP</option>
                    <option value="USD">USD</option>
                </select>
            </label>

            <label class="recon-report-field recon-report-field--select" for="reconReportStatus">
                <span>Status</span>
                <select id="reconReportStatus" name="status">
                    <option value="">All</option>
                    <option value="matched">Matched</option>
                    <option value="mismatch">Mismatch</option>
                    <option value="duplicate">Duplicate</option>
                </select>
            </label>

            <div class="recon-report-actions" aria-label="Recon report actions">
                <button class="recon-report-button recon-report-button--primary" id="reconReportGenerateBtn" type="submit">Generate</button>
                <button class="recon-report-button recon-report-button--success" id="reconReportExportBtn" type="button">Export to Excel</button>
                <button class="recon-report-button recon-report-button--secondary" id="reconReportClearBtn" type="reset">Clear</button>
            </div>
        </form>

        <div class="recon-report-type-tabs" id="reconReportTypeTabs" role="tablist" aria-label="Recon report type" hidden>
            <button class="recon-report-type-tab is-active" type="button" data-report-type="payout" role="tab" aria-selected="true">Payout</button>
            <button class="recon-report-type-tab" type="button" data-report-type="payout-cancelled" role="tab" aria-selected="false">Payout Cancelled</button>
            <button class="recon-report-type-tab" type="button" data-report-type="sendout" role="tab" aria-selected="false">Sendout</button>
            <button class="recon-report-type-tab" type="button" data-report-type="sendout-cancelled" role="tab" aria-selected="false">Sendout Cancelled</button>
            <span class="recon-report-status-legend" aria-label="Red dot means with mismatch or duplicate">
                <span>LEGEND:</span>
                <span class="recon-report-status-legend-dot" aria-hidden="true"></span>
                <span>W/ Mismatch and Duplicate</span>
            </span>
            <label class="recon-report-field recon-report-tab-search" for="reconReportSearch">
                <span>Search</span>
                <input
                    id="reconReportSearch"
                    name="search"
                    type="search"
                    form="reconReportFilterForm"
                    placeholder="Search by Reference ID or CCREF No."
                    autocomplete="off"
                >
            </label>
        </div>

        <div class="recon-report-table-card">
            <div class="recon-report-summary" aria-label="Recon report summary totals">
                <div class="recon-report-summary-group">
                    <span class="recon-report-summary-title">Partners Data</span>
                    <span>Volume: <strong id="reconReportPartnerVolume">0</strong></span>
                    <span>Principal: <strong id="reconReportPartnerPrincipalPhp">₱0.00 / $0.00</strong><strong id="reconReportPartnerPrincipalUsd"></strong></span>
                    <span>Commission: <strong id="reconReportPartnerCommissionPhp">₱0.00 / $0.00</strong><strong id="reconReportPartnerCommissionUsd"></strong></span>
                </div>

                <div class="recon-report-summary-group">
                    <span class="recon-report-summary-title">KPX Web Data</span>
                    <span>Volume: <strong id="reconReportWebVolume">0</strong></span>
                    <span>Principal: <strong id="reconReportWebPrincipalPhp">₱0.00 / $0.00</strong><strong id="reconReportWebPrincipalUsd"></strong></span>
                </div>
            </div>

            <div class="recon-report-table-scroll">
                <table class="recon-report-table" id="reconReportTable">
                    <colgroup>
                        <col class="recon-report-col--date">
                        <col class="recon-report-col--reference">
                        <col class="recon-report-col--amount">
                        <col class="recon-report-col--commission">
                        <col class="recon-report-col--currency">
                        <col class="recon-report-col--transaction-type">
                        <col class="recon-report-col--date">
                        <col class="recon-report-col--kptn">
                        <col class="recon-report-col--reference">
                        <col class="recon-report-col--amount">
                        <col class="recon-report-col--currency">
                        <col class="recon-report-col--status">
                        <col class="recon-report-col--status">
                        <col class="recon-report-col--remarks">
                    </colgroup>
                    <thead>
                        <tr>
                            <th colspan="6" scope="colgroup">Partners Data</th>
                            <th colspan="5" scope="colgroup">KPX Web Data</th>
                            <th rowspan="2" scope="col">Status</th>
                            <th rowspan="2" scope="col">Record Status</th>
                            <th rowspan="2" scope="col">Remarks</th>
                        </tr>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Reference ID</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Commission</th>
                            <th scope="col">Currency</th>
                            <th scope="col">Transaction Type</th>
                            <th scope="col">Date</th>
                            <th scope="col">KPTN</th>
                            <th scope="col">CCREF NO</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Currency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="recon-report-empty-row">
                            <td colspan="14">No recon report data generated yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const RECON_REPORT_CONTROLLER_MAP = <?= json_encode($reconReportControllerMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const RECON_REPORT_PARTNERS = <?= json_encode($reconReportPartners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const form = document.getElementById('reconReportFilterForm');
    const partnerInput = document.getElementById('reconReportCorporatePartner');
    const partnerSuggestions = document.getElementById('reconReportCorporatePartnerSuggestions');
    const startDateInput = document.getElementById('reconReportStartDate');
    const endDateInput = document.getElementById('reconReportEndDate');
    const currencySelect = document.getElementById('reconReportCurrency');
    const statusSelect = document.getElementById('reconReportStatus');
    const searchInput = document.getElementById('reconReportSearch');
    const exportBtn = document.getElementById('reconReportExportBtn');
    const clearBtn = document.getElementById('reconReportClearBtn');
    const typeTabs = document.getElementById('reconReportTypeTabs');
    const table = document.getElementById('reconReportTable');
    const tbody = table ? table.querySelector('tbody') : null;

    const summary = {
        partnerVolume: document.getElementById('reconReportPartnerVolume'),
        partnerPrincipalPhp: document.getElementById('reconReportPartnerPrincipalPhp'),
        partnerPrincipalUsd: document.getElementById('reconReportPartnerPrincipalUsd'),
        partnerCommissionPhp: document.getElementById('reconReportPartnerCommissionPhp'),
        partnerCommissionUsd: document.getElementById('reconReportPartnerCommissionUsd'),
        webVolume: document.getElementById('reconReportWebVolume'),
        webPrincipalPhp: document.getElementById('reconReportWebPrincipalPhp'),
        webPrincipalUsd: document.getElementById('reconReportWebPrincipalUsd')
    };

    let reportRows = [];

    function attachTypeTabs() {
        if (!typeTabs) return;
        const tabs = Array.from(typeTabs.querySelectorAll('.recon-report-type-tab'));
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (item) {
                    const isSelected = item === tab;
                    item.classList.toggle('is-active', isSelected);
                    item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                });
                applyFilters();
            });
        });
    }

    function activeReportType() {
        if (!typeTabs || typeTabs.hidden) return 'payout';
        const activeTab = typeTabs.querySelector('.recon-report-type-tab.is-active');
        return String(activeTab && activeTab.dataset.reportType || 'payout');
    }

    function rowHasReportType(row, reportType) {
        const partnerType = String(row && row.partner_report_type || '');
        const webTypes = Array.isArray(row && row.web_report_types)
            ? row.web_report_types
            : String(row && row.web_report_types || '').split('|').filter(Boolean);
        const hasPartnerSide = String(row && row.partner_reference_id || '').trim() !== ''
            || toNumber(row && row.partner_amount)
            || toNumber(row && row.partner_commission);
        const hasWebSide = String(row && row.web_ccref_no || '').trim() !== ''
            || toNumber(row && row.web_amount);
        const partnerMatchesType = !hasPartnerSide || !partnerType || partnerType === reportType;
        const webMatchesType = !hasWebSide || !webTypes.length || webTypes.indexOf(reportType) !== -1;

        // Keep tab indicators consistent with the rows that applyFilters()
        // can actually display. A type found on only one populated side is
        // not enough when the other side belongs to a different report tab.
        return partnerMatchesType && webMatchesType;
    }

    function updateReportTypeIndicators(rows) {
        if (!typeTabs) return;
        const reportData = Array.isArray(rows) ? rows : [];
        const selectedCurrency = normalizeKey(currencySelect && currencySelect.value);
        const selectedStatus = normalizeStatus(statusSelect && statusSelect.value);

        Array.from(typeTabs.querySelectorAll('.recon-report-type-tab')).forEach(function (tab) {
            const reportType = String(tab.dataset.reportType || '');
            const hasIndicator = reportData.some(function (row) {
                if (!rowHasReportType(row, reportType)) return false;
                const partnerCurrency = normalizeKey(row.partner_currency);
                const webCurrency = normalizeKey(row.web_currency);
                if (selectedCurrency && selectedCurrency !== 'ALL'
                    && partnerCurrency.indexOf(selectedCurrency) === -1
                    && webCurrency.indexOf(selectedCurrency) === -1) return false;
                if (selectedStatus && row.data_status !== selectedStatus) return false;
                return row.data_status === 'mismatch' || row.data_status === 'duplicate';
            });
            tab.classList.toggle('has-result-indicator', hasIndicator);
        });
    }

    function resetReportTypeTabs() {
        if (!typeTabs) return;
        const tabs = Array.from(typeTabs.querySelectorAll('.recon-report-type-tab'));
        tabs.forEach(function (tab, index) {
            const isSelected = index === 0;
            tab.classList.toggle('is-active', isSelected);
            tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    }

    function normalizeKey(value) {
        return String(value || '').trim().toUpperCase();
    }

    function partnerSearchText(partner) {
        return [
            partner && partner.id,
            partner && partner.name,
            partner && partner.label
        ].join(' ').toLowerCase();
    }

    function resolvePartner(inputValue) {
        const value = String(inputValue || '').trim();
        const key = normalizeKey(value);
        if (!key) return null;
        return RECON_REPORT_PARTNERS.find(function (partner) {
            return normalizeKey(partner.id) === key
                || normalizeKey(partner.name) === key
                || normalizeKey(partner.label) === key;
        }) || null;
    }

    function selectedPartnerName() {
        const resolved = resolvePartner(partnerInput && partnerInput.value);
        return resolved ? resolved.name : String(partnerInput && partnerInput.value || '').trim();
    }

    function attachPartnerAutocomplete() {
        if (!partnerInput || !partnerSuggestions) return;

        let activeIndex = -1;

        function closeSuggestions() {
            partnerSuggestions.hidden = true;
            partnerSuggestions.innerHTML = '';
            activeIndex = -1;
        }

        function selectPartner(partner) {
            if (!partner) return;
            partnerInput.value = partner.name;
            partnerInput.dataset.partnerId = partner.id || '';
            partnerInput.dataset.partnerName = partner.name || '';
            closeSuggestions();
        }

        function matchingPartners() {
            const query = String(partnerInput.value || '').trim().toLowerCase();
            if (!query) return RECON_REPORT_PARTNERS.slice(0, 12);
            return RECON_REPORT_PARTNERS.filter(function (partner) {
                return partnerSearchText(partner).indexOf(query) !== -1;
            }).slice(0, 12);
        }

        function setActive(items, nextIndex) {
            items.forEach(function (item) {
                item.classList.remove('is-active');
            });
            activeIndex = nextIndex;
            if (items[activeIndex]) {
                items[activeIndex].classList.add('is-active');
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        function renderSuggestions() {
            const matches = matchingPartners();
            partnerSuggestions.innerHTML = '';
            if (!matches.length) {
                const item = document.createElement('li');
                item.className = 'recon-report-autocomplete-item recon-report-autocomplete-item--empty';
                item.setAttribute('role', 'option');
                item.innerHTML = '<span>No partner found</span><small>Search by partner ID or partner name</small>';
                partnerSuggestions.appendChild(item);
                partnerSuggestions.hidden = false;
                return;
            }

            matches.forEach(function (partner) {
                const item = document.createElement('li');
                item.className = 'recon-report-autocomplete-item';
                item.setAttribute('role', 'option');
                item.innerHTML = '<span>' + escapeHtml(partner.name) + '</span><small>' + escapeHtml(partner.id || 'No Partner ID') + '</small>';
                item.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    selectPartner(partner);
                });
                partnerSuggestions.appendChild(item);
            });

            partnerSuggestions.hidden = false;
            activeIndex = -1;
        }

        partnerInput.addEventListener('input', function () {
            partnerInput.dataset.partnerId = '';
            partnerInput.dataset.partnerName = '';
            renderSuggestions();
        });
        partnerInput.addEventListener('focus', renderSuggestions);
        partnerInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                const resolved = resolvePartner(partnerInput.value);
                if (resolved && normalizeKey(partnerInput.value) === normalizeKey(resolved.id)) {
                    selectPartner(resolved);
                } else {
                    closeSuggestions();
                }
            }, 120);
        });
        partnerInput.addEventListener('keydown', function (event) {
            const items = Array.from(partnerSuggestions.querySelectorAll('.recon-report-autocomplete-item'));
            if (partnerSuggestions.hidden || !items.length) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(items, activeIndex < items.length - 1 ? activeIndex + 1 : 0);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(items, activeIndex > 0 ? activeIndex - 1 : items.length - 1);
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            } else if (event.key === 'Escape') {
                closeSuggestions();
            }
        });

        document.addEventListener('click', function (event) {
            if (!partnerInput.closest('.recon-report-autocomplete').contains(event.target)) {
                closeSuggestions();
            }
        });
    }

    function attachDateAutofill() {
        if (!startDateInput || !endDateInput) return;
        let autoFilledEndDate = '';
        let endDateEditedManually = false;

        function syncEndDateFromStart() {
            if (!startDateInput.value) return;
            if (!endDateEditedManually || !endDateInput.value || endDateInput.value === autoFilledEndDate) {
                endDateInput.value = startDateInput.value;
                autoFilledEndDate = startDateInput.value;
                endDateEditedManually = false;
            }
        }

        ['input', 'change', 'keyup', 'blur', 'mouseup'].forEach(function (eventName) {
            startDateInput.addEventListener(eventName, syncEndDateFromStart);
        });
        endDateInput.addEventListener('input', function () {
            if (endDateInput.value !== autoFilledEndDate) {
                endDateEditedManually = true;
                autoFilledEndDate = '';
            }
        });
        endDateInput.addEventListener('change', function () {
            if (endDateInput.value !== autoFilledEndDate) {
                endDateEditedManually = true;
                autoFilledEndDate = '';
            }
        });
    }

    function normalizeStatus(value) {
        const key = normalizeKey(value);
        if (key === 'DUPLICATE' || key === 'DUPLICATES') return 'duplicate';
        if (key === 'MISMATCH' || key === 'MISSMATCH' || key === 'NOT MATCHED') return 'mismatch';
        if (key === 'MATCHED' || key === 'MATCH') return 'matched';
        return '';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function toNumber(value) {
        const normalized = String(value ?? '').replace(/,/g, '').trim();
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value) {
        const amount = toNumber(value);
        if (!amount) return '';
        return Math.abs(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function currencyMoney(value, currency) {
        const symbol = normalizeKey(currency).indexOf('USD') !== -1 ? '$' : '₱';
        return symbol + Math.abs(toNumber(value)).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function summaryMoneyText(phpValue, usdValue) {
        const currency = normalizeKey(currencySelect && currencySelect.value);
        if (currency === 'PHP') return currencyMoney(phpValue, 'PHP');
        if (currency === 'USD') return currencyMoney(usdValue, 'USD');
        return currencyMoney(phpValue, 'PHP') + ' / ' + currencyMoney(usdValue, 'USD');
    }

    function formatDate(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return raw;
        return parsed.toLocaleDateString('en-US', {
            month: 'long',
            day: '2-digit',
            year: 'numeric'
        });
    }

    function normalizeIsoDate(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const directDate = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (directDate) return directDate[1] + '-' + directDate[2] + '-' + directDate[3];
        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return raw.slice(0, 10);
        return parsed.getFullYear() + '-'
            + String(parsed.getMonth() + 1).padStart(2, '0') + '-'
            + String(parsed.getDate()).padStart(2, '0');
    }

    function formatLongDate(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return raw;
        return parsed.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function resolveController(partnerName) {
        const partnerKey = normalizeKey(partnerName);
        if (RECON_REPORT_CONTROLLER_MAP[partnerKey]) return RECON_REPORT_CONTROLLER_MAP[partnerKey];
        if (partnerKey.indexOf('MONEYGRAM') !== -1) return 'moneygram-recon.php';
        if (partnerKey.indexOf('METROBANK') !== -1 || partnerKey.indexOf('MBTC') !== -1) return 'mbtc-recon.php';
        if (partnerKey.indexOf('SKYBRIDGE') !== -1) return 'skybridgepaymentinc-recon.php';
        if (partnerKey.indexOf('WORLD') !== -1 || partnerKey.indexOf('WIC') !== -1) return 'wic-recon.php';
        if (partnerKey.indexOf('RCBC') !== -1) return 'rcbc-recon.php';
        return 'mbtc-recon.php';
    }

    function reportEndpoint(partnerName, startDate, endDate) {
        const controller = resolveController(partnerName);
        const baseUrl = String(window.autoreconBaseUrl || '').replace(/\/$/, '');
        const url = new URL(baseUrl + '/src/controllers/recon/' + controller, window.location.origin);
        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);
        url.searchParams.set('partnerName', partnerName);
        url.searchParams.set('detail', '1');
        url.searchParams.set('range_detail', '1');
        return url.toString();
    }

    function readPartnerRef(row) {
        return row.partner_reference_id || row.partner_reference_no || row.partner_ref || row.partner_ref_no || row.partner_tx || row.tx || '';
    }

    function readWebRef(row) {
        return row.web_ccref_no || row.web_cc_ref || row.web_ccref || row.web_ref || row.web_reference_no || row.ccref_no || '';
    }

    function rowHasPartnerData(row) {
        if (String(readPartnerRef(row)).trim()) return true;
        if (toNumber(row.partner_principal || row.partner_amount || row.partner_base_amt || 0)) return true;
        if (toNumber(row.partner_commission || row.partner_comm_amt || row.partner_comm_tran_amt || row.partner_fee_tran_amt || 0)) return true;
        return Object.keys(row || {}).some(function (key) {
            return key.indexOf('partner_') === 0 && String(row[key] ?? '').trim() !== '' && String(row[key]) !== '0';
        });
    }

    function rowHasWebData(row) {
        if (String(readWebRef(row)).trim()) return true;
        if (String(readWebKptn(row)).trim()) return true;
        if (toNumber(row.web_amount || row.web_amt || row.amount || 0)) return true;
        return Object.keys(row || {}).some(function (key) {
            return key.indexOf('web_') === 0 && String(row[key] ?? '').trim() !== '' && String(row[key]) !== '0';
        });
    }

    function readWebKptn(row) {
        return row.web_kptn || row.kptn || row.web_kpx_transaction_no || '';
    }

    function readPartnerCurrency(row) {
        return row.partner_currency || row.partner_coin || row.partner_settlement_currency || row.partner_transaction_currency || row.partner_base_cncy || '';
    }

    function readPartnerTransactionType(row) {
        if (normalizeKey(selectedPartnerName()).indexOf('MONEYGRAM') === -1) return '';
        return row.partner_tran_type || row.partner_transaction_type || row.tran_type || '';
    }

    function readWebCurrency(row) {
        return row.web_currency || row.web_ccy || row.web_currency_code || '';
    }

    function readRecordStatus(row) {
        return row.web_record_status || row.web_status || row.partner_record_status || row.partner_status || row.record_status || '';
    }

    function readStatusNumber(value) {
        const parsed = Number.parseInt(String(value ?? '').trim(), 10);
        return Number.isNaN(parsed) ? null : parsed;
    }

    function moneygramRowState(partnerRecord, webRecord) {
        const partnerMatchStatus = partnerRecord ? partnerRecord.matchStatus : null;
        const webMatchStatus = webRecord ? webRecord.matchStatus : null;
        const partnerLocked = partnerRecord ? partnerRecord.isDataLocked : null;
        const webLocked = webRecord ? webRecord.isDataLocked : null;

        if (partnerRecord && webRecord
            && partnerMatchStatus === 1 && webMatchStatus === 1
            && partnerLocked === 1 && webLocked === 1) {
            return { dataStatus: 'matched', recordLocked: true };
        }

        if (partnerRecord && webRecord
            && partnerMatchStatus === 1 && webMatchStatus === 1
            && partnerLocked === 0 && webLocked === 0) {
            return { dataStatus: 'matched', recordLocked: false };
        }

        if ((partnerRecord && partnerMatchStatus === 2 && partnerLocked === 0)
            || (webRecord && webMatchStatus === 2 && webLocked === 0)) {
            return { dataStatus: 'mismatch', recordLocked: false };
        }

        if ((partnerRecord && partnerMatchStatus === 3 && partnerLocked === 0)
            || (webRecord && webMatchStatus === 3 && webLocked === 0)) {
            return { dataStatus: 'duplicate', recordLocked: false };
        }

        return null;
    }

    function partnerReportType(row) {
        if (row.partner_report_type) return String(row.partner_report_type);
        const tranType = normalizeKey(row.partner_tran_type || row.partner_transaction_type || row.tran_type || '');
        if (tranType === 'REC') return 'payout';
        if (tranType === 'SEN') return 'sendout';
        if (tranType === 'RRC') return 'payout-cancelled';
        if (tranType === 'RSN' || tranType === 'REF') return 'sendout-cancelled';
        return '';
    }

    function webReportTypes(row) {
        if (row.web_report_type) return [String(row.web_report_type)];
        const cancelled = String(row.web_date_cancelled || row.web_date_cancellation || row.date_cancelled || row.date_cancellation || '').trim() !== '';
        const hasDateClaimed = String(row.web_date_claimed || row.date_claimed || '').trim() !== '';
        const hasDateSend = String(row.web_date_send || row.date_send || '').trim() !== '';
        const types = [];

        if (hasDateClaimed) types.push(cancelled ? 'payout-cancelled' : 'payout');
        if (hasDateSend) types.push(cancelled ? 'sendout-cancelled' : 'sendout');
        return types;
    }

    function rowStatus(row, day) {
        const explicit = normalizeStatus(row.data_status || row.status || row.recon_status || '');
        if (explicit) return explicit;

        const ref = normalizeKey(row.ref || readPartnerRef(row) || readWebRef(row));
        const duplicates = Array.isArray(day && day.duplicates) ? day.duplicates : [];
        if (duplicates.some(function (item) { return normalizeKey(item && item.ref) === ref; })) {
            return 'duplicate';
        }

        const hasPartner = !!String(readPartnerRef(row)).trim();
        const hasWeb = !!String(readWebRef(row)).trim();
        const partnerAmount = toNumber(row.partner_principal || row.partner_amount || row.partner_base_amt || 0);
        const webAmount = toNumber(row.web_amount || row.web_amt || row.amount || 0);
        const partnerCommission = toNumber(row.partner_commission || row.partner_comm_amt || row.partner_comm_tran_amt || 0);
        const webCommission = toNumber(row.web_ctp || row.web_commission || row.web_charge || 0);

        if (!hasPartner || !hasWeb) return 'mismatch';
        if (Math.abs(Math.abs(partnerAmount) - Math.abs(webAmount)) >= 0.01) return 'mismatch';
        if ((partnerCommission || webCommission) && Math.abs(Math.abs(partnerCommission) - Math.abs(webCommission)) >= 0.01) return 'mismatch';
        return 'matched';
    }

    function isDuplicateReference(ref, day) {
        const key = normalizeKey(ref);
        if (!key) return false;
        const duplicates = Array.isArray(day && day.duplicates) ? day.duplicates : [];
        return duplicates.some(function (item) {
            return normalizeKey(item && item.ref) === key;
        });
    }

    function matchKey(ref, dateValue) {
        const refKey = normalizeKey(ref);
        const dateKey = normalizeIsoDate(dateValue);
        return refKey && dateKey ? refKey + '|' + dateKey : '';
    }

    function duplicateCombinationKey(dateValue, referenceValue, amountValue) {
        const dateKey = normalizeIsoDate(dateValue);
        const referenceKey = normalizeKey(referenceValue);
        if (!dateKey || !referenceKey) return '';
        return dateKey + '|' + referenceKey + '|' + Math.abs(toNumber(amountValue)).toFixed(2);
    }

    function applyCompositeDuplicateStatuses(rows) {
        const partnerCounts = new Map();
        const webCounts = new Map();

        rows.forEach(function (row) {
            const partnerKey = duplicateCombinationKey(row.partner_date, row.partner_reference_id, row.partner_amount);
            const webKey = duplicateCombinationKey(row.web_date, row.web_ccref_no, row.web_amount);
            if (partnerKey) partnerCounts.set(partnerKey, (partnerCounts.get(partnerKey) || 0) + 1);
            if (webKey) webCounts.set(webKey, (webCounts.get(webKey) || 0) + 1);
        });

        rows.forEach(function (row) {
            const partnerKey = duplicateCombinationKey(row.partner_date, row.partner_reference_id, row.partner_amount);
            const webKey = duplicateCombinationKey(row.web_date, row.web_ccref_no, row.web_amount);
            const isPartnerDuplicate = partnerKey && (partnerCounts.get(partnerKey) || 0) > 1;
            const isWebDuplicate = webKey && (webCounts.get(webKey) || 0) > 1;
            const hasPartner = String(row.partner_reference_id || '').trim() !== '';
            const hasWeb = String(row.web_ccref_no || '').trim() !== '';

            if (hasPartner && hasWeb) {
                row.data_status = 'matched';
            } else if ((hasPartner && isPartnerDuplicate) || (hasWeb && isWebDuplicate)) {
                row.data_status = 'duplicate';
            } else {
                row.data_status = 'mismatch';
            }
        });

        return rows;
    }

    function flattenControllerPayload(payload) {
        const days = Array.isArray(payload && payload.days) ? payload.days : [];
        const flatRows = [];
        const isMoneyGram = normalizeKey(selectedPartnerName()).indexOf('MONEYGRAM') !== -1;

        days.forEach(function (day) {
            const detailRows = Array.isArray(day.rows) ? day.rows : [];
            const partnerRecords = [];
            const webRecords = [];

            detailRows.forEach(function (row) {
                const hasPartner = rowHasPartnerData(row);
                const hasWeb = rowHasWebData(row);
                const partnerRef = readPartnerRef(row) || (hasPartner ? (row.ref || '') : '');
                const webRef = readWebRef(row) || (hasWeb ? (row.ref || '') : '');
                const partnerDate = hasPartner ? (row.partner_tran_date || row.partner_cover_date || row.partner_date || row.partner_date_claimed || row.__mbtc_date || day.date || '') : '';
                const webDate = hasWeb ? (row.web_report_date || row.web_date_claimed || row.web_date_send || row.web_tran_date || row.web_date || row.__mbtc_date || day.date || '') : '';
                const remarks = String(row.remarks || '').trim();

                if (hasPartner) {
                    partnerRecords.push({
                        date: partnerDate,
                        reference_id: partnerRef,
                        amount: row.partner_principal || row.partner_amount || row.partner_base_amt || 0,
                        commission: row.partner_commission || row.partner_comm_amt || row.partner_comm_tran_amt || row.partner_fee_tran_amt || 0,
                        currency: readPartnerCurrency(row) || 'PHP',
                        transactionType: readPartnerTransactionType(row),
                        reportType: partnerReportType(row),
                        matchStatus: readStatusNumber(row.partner_match_status ?? row.match_status),
                        isDataLocked: readStatusNumber(row.partner_is_data_locked ?? row.is_data_locked),
                        recordStatus: readRecordStatus(row),
                        remarks: remarks,
                        duplicate: isDuplicateReference(partnerRef, day),
                        insertIndex: partnerRecords.length
                    });
                }

                if (hasWeb) {
                    webRecords.push({
                        date: webDate,
                        cancelled: String(row.web_date_cancelled || row.web_date_cancellation || '').trim() !== '',
                        kptn: readWebKptn(row),
                        ccref_no: webRef,
                        amount: row.web_amount || row.web_amt || row.amount || 0,
                        currency: readWebCurrency(row) || 'PHP',
                        commission: row.web_ctp || row.web_commission || row.web_charge || 0,
                        reportTypes: webReportTypes(row),
                        matchStatus: readStatusNumber(row.web_match_status ?? row.match_status),
                        isDataLocked: readStatusNumber(row.web_is_data_locked ?? row.is_data_locked),
                        recordStatus: readRecordStatus(row),
                        remarks: remarks,
                        duplicate: isDuplicateReference(webRef, day),
                        insertIndex: webRecords.length
                    });
                }
            });

            const webByKey = new Map();
            webRecords.forEach(function (webRecord) {
                const key = matchKey(webRecord.ccref_no, webRecord.date);
                if (!key) return;
                if (!webByKey.has(key)) webByKey.set(key, []);
                webByKey.get(key).push(webRecord);
            });

            const usedWeb = new Set();
            partnerRecords.forEach(function (partnerRecord) {
                const key = matchKey(partnerRecord.reference_id, partnerRecord.date);
                const webList = key && webByKey.has(key) ? webByKey.get(key) : [];
                let webRecord = webList.find(function (candidate) {
                    if (usedWeb.has(candidate)) return false;
                    if (isMoneyGram && partnerRecord.matchStatus === 3) return false;
                    if (isMoneyGram) return true;
                    const partnerType = String(partnerRecord.reportType || '');
                    const webTypes = Array.isArray(candidate.reportTypes) ? candidate.reportTypes : [];
                    const reportTypeMatches = !partnerType || !webTypes.length || webTypes.indexOf(partnerType) !== -1;
                    const amountMatches = Math.abs(Math.abs(toNumber(partnerRecord.amount)) - Math.abs(toNumber(candidate.amount))) < 0.01;
                    const partnerCurrency = normalizeKey(partnerRecord.currency);
                    const webCurrency = normalizeKey(candidate.currency);
                    const currencyMatches = !partnerCurrency || !webCurrency || partnerCurrency === webCurrency;
                    return reportTypeMatches && amountMatches && currencyMatches;
                }) || null;

                if (webRecord) {
                    usedWeb.add(webRecord);
                }

                const isDuplicate = partnerRecord.duplicate || (webRecord && webRecord.duplicate);
                const dataStatus = isDuplicate
                    ? 'duplicate'
                    : (!webRecord ? 'mismatch' : 'matched');
                const moneygramState = isMoneyGram ? moneygramRowState(partnerRecord, webRecord) : null;
                flatRows.push({
                    partner_date: partnerRecord.date,
                    partner_reference_id: partnerRecord.reference_id,
                    partner_amount: partnerRecord.amount,
                    partner_commission: partnerRecord.commission,
                    partner_currency: partnerRecord.currency,
                    partner_transaction_type: partnerRecord.transactionType,
                    partner_report_type: partnerRecord.reportType,
                    web_date: webRecord ? webRecord.date : '',
                    web_kptn: webRecord ? webRecord.kptn : '',
                    web_ccref_no: webRecord ? webRecord.ccref_no : '',
                    web_amount: webRecord ? webRecord.amount : 0,
                    web_currency: webRecord ? webRecord.currency : '',
                    web_commission: webRecord ? webRecord.commission : 0,
                    web_report_types: webRecord ? webRecord.reportTypes : [],
                    record_status: (webRecord && webRecord.recordStatus) || partnerRecord.recordStatus || '',
                    // Persisted MoneyGram match/lock state is authoritative;
                    // calculated duplicate detection is only the fallback.
                    data_status: moneygramState
                        ? moneygramState.dataStatus
                        : (isDuplicate ? 'duplicate' : dataStatus),
                    record_locked: moneygramState ? moneygramState.recordLocked : null,
                    remarks: (webRecord && webRecord.remarks) || partnerRecord.remarks || ''
                });
            });

            webRecords.forEach(function (webRecord) {
                if (usedWeb.has(webRecord)) return;
                const moneygramState = isMoneyGram ? moneygramRowState(null, webRecord) : null;
                flatRows.push({
                    partner_date: '',
                    partner_reference_id: '',
                    partner_amount: 0,
                    partner_commission: 0,
                    partner_currency: '',
                    partner_transaction_type: '',
                    partner_report_type: '',
                    web_date: webRecord.date,
                    web_kptn: webRecord.kptn,
                    web_ccref_no: webRecord.ccref_no,
                    web_amount: webRecord.amount,
                    web_currency: webRecord.currency,
                    web_commission: webRecord.commission,
                    web_report_types: webRecord.reportTypes,
                    record_status: webRecord.recordStatus,
                    data_status: moneygramState
                        ? moneygramState.dataStatus
                        : (webRecord.duplicate ? 'duplicate' : 'mismatch'),
                    record_locked: moneygramState ? moneygramState.recordLocked : null,
                    remarks: webRecord.remarks || ''
                });
            });
        });

        return isMoneyGram ? flatRows : applyCompositeDuplicateStatuses(flatRows);
    }

    function setEmptyRow(message) {
        if (!tbody) return;
        tbody.innerHTML = '<tr class="recon-report-empty-row"><td colspan="14">' + escapeHtml(message || 'No recon report data generated yet.') + '</td></tr>';
        updateExportButtonVisibility();
    }

    function updateExportButtonVisibility() {
        if (!exportBtn) return;
        const hasVisibleRows = visibleRows().length > 0;
        exportBtn.hidden = !hasVisibleRows;
    }

    function statusLabel(status) {
        if (status === 'matched') return 'Matched';
        if (status === 'duplicate') return 'Duplicate';
        return 'Mismatch';
    }

    function recordStatusHtml(row) {
        if (row.record_locked === true) {
            return '<span class="recon-report-lock-icon recon-report-lock-icon--locked" title="Locked" aria-label="Locked">'
                + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm296.5-223.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>'
                + '</span>';
        }
        if (row.record_locked === false) {
            const statusClass = row.data_status === 'duplicate'
                ? 'recon-report-lock-icon--duplicate'
                : 'recon-report-lock-icon--mismatch';
            return '<span class="recon-report-lock-icon recon-report-lock-icon--unlocked ' + statusClass + '" title="Unlocked" aria-label="Unlocked">'
                + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M536.5-303.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h280v-80q0-83 58.5-141.5T720-920q83 0 141.5 58.5T920-720h-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80h120q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Z"/></svg>'
                + '</span>';
        }
        return escapeHtml(row.record_status || '');
    }

    function rowReportDate(row) {
        return normalizeIsoDate(row.partner_date || row.web_date || '');
    }

    function createDateSeparatorRow(dateValue) {
        const tr = document.createElement('tr');
        tr.className = 'recon-report-date-row';
        tr.dataset.role = 'date-separator';
        tr.dataset.date = normalizeIsoDate(dateValue);
        tr.innerHTML = '<td colspan="14">' + escapeHtml(formatLongDate(dateValue)) + '</td>';
        return tr;
    }

    function renderRows(rows) {
        if (!tbody) return;
        if (!rows.length) {
            setEmptyRow('No Data Found');
            return;
        }

        const orderedRows = rows.slice();
        if (!normalizeStatus(statusSelect && statusSelect.value)) {
            orderedRows.sort(function (left, right) {
                const leftPriority = left.data_status === 'mismatch' || left.data_status === 'duplicate' ? 0 : 1;
                const rightPriority = right.data_status === 'mismatch' || right.data_status === 'duplicate' ? 0 : 1;
                return leftPriority - rightPriority;
            });
        }

        const fragment = document.createDocumentFragment();
        const showDateSeparators = normalizeIsoDate(startDateInput && startDateInput.value) !== normalizeIsoDate(endDateInput && endDateInput.value);
        let currentDate = '';
        orderedRows.forEach(function (row) {
            const reportDate = rowReportDate(row);
            if (showDateSeparators && reportDate && reportDate !== currentDate) {
                currentDate = reportDate;
                fragment.appendChild(createDateSeparatorRow(reportDate));
            }

            const tr = document.createElement('tr');
            tr.dataset.status = row.data_status || '';
            tr.dataset.partnerCurrency = normalizeKey(row.partner_currency);
            tr.dataset.webCurrency = normalizeKey(row.web_currency);
            tr.dataset.partnerReportType = row.partner_report_type || '';
            tr.dataset.webReportTypes = Array.isArray(row.web_report_types) ? row.web_report_types.join('|') : String(row.web_report_types || '');
            tr.dataset.partnerAmount = String(toNumber(row.partner_amount));
            tr.dataset.partnerCommission = String(toNumber(row.partner_commission));
            tr.dataset.webAmount = String(toNumber(row.web_amount));
            tr.dataset.webCommission = String(toNumber(row.web_commission));
            tr.dataset.reportDate = reportDate;
            tr.dataset.search = [
                row.partner_reference_id,
                row.web_ccref_no
            ].join(' ').toLowerCase();
            tr.className = 'recon-report-result-row recon-report-result-row--' + (row.data_status || 'mismatch');
            tr.innerHTML = ''
                + '<td>' + escapeHtml(formatDate(row.partner_date)) + '</td>'
                + '<td>' + escapeHtml(row.partner_reference_id) + '</td>'
                + '<td>' + escapeHtml(money(row.partner_amount)) + '</td>'
                + '<td>' + escapeHtml(money(row.partner_commission)) + '</td>'
                + '<td>' + escapeHtml(row.partner_currency) + '</td>'
                + '<td>' + escapeHtml(row.partner_transaction_type || '') + '</td>'
                + '<td>' + escapeHtml(formatDate(row.web_date)) + '</td>'
                + '<td>' + escapeHtml(row.web_kptn) + '</td>'
                + '<td>' + escapeHtml(row.web_ccref_no) + '</td>'
                + '<td>' + escapeHtml(money(row.web_amount)) + '</td>'
                + '<td>' + escapeHtml(row.web_currency) + '</td>'
                + '<td>' + escapeHtml(statusLabel(row.data_status)) + '</td>'
                + '<td class="recon-report-record-status-cell">' + recordStatusHtml(row) + '</td>'
                + '<td>' + escapeHtml(row.remarks || '') + '</td>';
            fragment.appendChild(tr);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
        updateExportButtonVisibility();
    }

    function visibleRows() {
        return Array.from(tbody ? tbody.querySelectorAll('tr.recon-report-result-row') : []).filter(function (row) {
            return row.style.display !== 'none';
        });
    }

    function updateDateSeparatorVisibility() {
        if (!tbody) return;
        Array.from(tbody.querySelectorAll('tr.recon-report-date-row')).forEach(function (separator) {
            let nextRow = separator.nextElementSibling;
            let hasVisibleRows = false;

            while (nextRow && !nextRow.classList.contains('recon-report-date-row')) {
                if (nextRow.classList.contains('recon-report-result-row') && nextRow.style.display !== 'none') {
                    hasVisibleRows = true;
                    break;
                }
                nextRow = nextRow.nextElementSibling;
            }

            separator.style.display = hasVisibleRows ? '' : 'none';
        });
    }

    function updateSummary() {
        const rows = visibleRows();
        const totals = {
            partnerVolume: 0,
            webVolume: 0,
            partnerPrincipalPhp: 0,
            partnerPrincipalUsd: 0,
            partnerCommissionPhp: 0,
            partnerCommissionUsd: 0,
            webPrincipalPhp: 0,
            webPrincipalUsd: 0
        };

        rows.forEach(function (tr) {
            const cells = tr.cells;
            const pCurrency = normalizeKey(cells[4] ? cells[4].textContent : '');
            const wCurrency = normalizeKey(cells[10] ? cells[10].textContent : '');
            const pAmount = toNumber(tr.dataset.partnerAmount);
            const pCommission = toNumber(tr.dataset.partnerCommission);
            const wAmount = toNumber(tr.dataset.webAmount);

            if ((cells[1] && cells[1].textContent.trim()) || pAmount || pCommission) {
                totals.partnerVolume++;
                if (pCurrency.indexOf('USD') !== -1) {
                    totals.partnerPrincipalUsd += pAmount;
                    totals.partnerCommissionUsd += pCommission;
                } else {
                    totals.partnerPrincipalPhp += pAmount;
                    totals.partnerCommissionPhp += pCommission;
                }
            }

            if ((cells[8] && cells[8].textContent.trim()) || wAmount) {
                totals.webVolume++;
                if (wCurrency.indexOf('USD') !== -1) totals.webPrincipalUsd += wAmount;
                else totals.webPrincipalPhp += wAmount;
            }
        });

        if (summary.partnerVolume) summary.partnerVolume.textContent = totals.partnerVolume.toLocaleString();
        if (summary.webVolume) summary.webVolume.textContent = totals.webVolume.toLocaleString();
        if (summary.partnerPrincipalPhp) summary.partnerPrincipalPhp.textContent = summaryMoneyText(totals.partnerPrincipalPhp, totals.partnerPrincipalUsd);
        if (summary.partnerPrincipalUsd) summary.partnerPrincipalUsd.textContent = '';
        if (summary.partnerCommissionPhp) summary.partnerCommissionPhp.textContent = summaryMoneyText(totals.partnerCommissionPhp, totals.partnerCommissionUsd);
        if (summary.partnerCommissionUsd) summary.partnerCommissionUsd.textContent = '';
        if (summary.webPrincipalPhp) summary.webPrincipalPhp.textContent = summaryMoneyText(totals.webPrincipalPhp, totals.webPrincipalUsd);
        if (summary.webPrincipalUsd) summary.webPrincipalUsd.textContent = '';
    }

    function applyFilters() {
        const query = String(searchInput && searchInput.value || '').trim().toLowerCase();
        const currency = normalizeKey(currencySelect && currencySelect.value);
        const status = normalizeStatus(statusSelect && statusSelect.value);
        const reportType = activeReportType();
        let visibleCount = 0;

        Array.from(tbody ? tbody.querySelectorAll('tr.recon-report-result-row') : []).forEach(function (row) {
            let show = true;
            const hasPartnerSide = String(row.cells[1] && row.cells[1].textContent || '').trim() !== '' || toNumber(row.dataset.partnerAmount) || toNumber(row.dataset.partnerCommission);
            const hasWebSide = String(row.cells[8] && row.cells[8].textContent || '').trim() !== '' || toNumber(row.dataset.webAmount);
            const partnerType = String(row.dataset.partnerReportType || '');
            const webTypes = String(row.dataset.webReportTypes || '');
            const partnerMatchesType = !hasPartnerSide || !partnerType || partnerType === reportType;
            const webMatchesType = !hasWebSide || !webTypes || webTypes.split('|').indexOf(reportType) !== -1;
            const isPartnerDuplicateForTab = String(row.dataset.status || '') === 'duplicate'
                && hasPartnerSide
                && partnerType === reportType;

            if (reportType) show = show && (isPartnerDuplicateForTab || (partnerMatchesType && webMatchesType));
            if (query && String(row.dataset.search || '').indexOf(query) === -1) show = false;
            if (currency && currency !== 'ALL') {
                show = show && (String(row.dataset.partnerCurrency || '').indexOf(currency) !== -1 || String(row.dataset.webCurrency || '').indexOf(currency) !== -1);
            }
            if (status) show = show && String(row.dataset.status || '') === status;
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        updateDateSeparatorVisibility();
        updateSummary();
        updateExportButtonVisibility();
    }

    async function generateReport() {
        const partnerName = selectedPartnerName();
        const startDate = String(startDateInput && startDateInput.value || '').trim();
        const endDate = String(endDateInput && endDateInput.value || '').trim();

        if (!partnerName || !startDate || !endDate) {
            alert('Please select Corporate Partner, Start Date, and End Date.');
            return;
        }
        if (startDate > endDate) {
            alert('Start Date cannot be greater than End Date.');
            return;
        }

        const generateBtn = document.getElementById('reconReportGenerateBtn');
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.textContent = 'Generating...';
        }
        setEmptyRow('Loading recon report data...');

        try {
            const response = await fetch(reportEndpoint(partnerName, startDate, endDate), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const payload = await response.json().catch(function () { return null; });
            if (!response.ok || !payload || payload.success === false) {
                throw new Error((payload && (payload.error || payload.message)) || 'Failed to load recon report data.');
            }

            reportRows = flattenControllerPayload(payload);
            updateReportTypeIndicators(reportRows);
            renderRows(reportRows);
            applyFilters();
            if (typeTabs) typeTabs.hidden = false;
        } catch (error) {
            console.error('Recon report generation failed', error);
            reportRows = [];
            setEmptyRow(error.message || 'Failed to load recon report data.');
            updateSummary();
        } finally {
            if (generateBtn) {
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate';
            }
        }
    }

    function clearReport() {
        reportRows = [];
        updateReportTypeIndicators([]);
        resetReportTypeTabs();
        if (typeTabs) typeTabs.hidden = true;
        setEmptyRow('No recon report data generated yet.');
        updateSummary();
    }

    function moneygramReconExcelEndpoint(partnerName, startDate, endDate) {
        const baseUrl = String(window.autoreconBaseUrl || '').replace(/\/$/, '');
        const url = new URL(baseUrl + '/src/modals/generate/recon-details-report/excel/moneygram-recon/moneygram-recon-format.php', window.location.origin);
        const status = normalizeStatus(statusSelect && statusSelect.value) || 'all';
        const currency = normalizeKey(currencySelect && currencySelect.value) || 'ALL';

        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);
        url.searchParams.set('partnerName', partnerName || 'MONEYGRAM');
        url.searchParams.set('filter', status === 'duplicate' ? 'duplicates' : status);
        url.searchParams.set('currency', currency === 'ALL' ? 'all' : currency);
        url.searchParams.set('report_type', activeReportType());

        return url.toString();
    }

    function exportExcelReport() {
        const partnerName = selectedPartnerName();
        const startDate = String(startDateInput && startDateInput.value || '').trim();
        const endDate = String(endDateInput && endDateInput.value || '').trim();

        if (!partnerName || !startDate || !endDate) {
            alert('Please select Corporate Partner, Start Date, and End Date.');
            return;
        }
        if (startDate > endDate) {
            alert('Start Date cannot be greater than End Date.');
            return;
        }

        window.location.href = moneygramReconExcelEndpoint(partnerName, startDate, endDate);
    }

    if (!form || !tbody) return;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        generateReport();
    });
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (currencySelect) currencySelect.addEventListener('change', function () {
        updateReportTypeIndicators(reportRows);
        applyFilters();
    });
    if (statusSelect) statusSelect.addEventListener('change', function () {
        updateReportTypeIndicators(reportRows);
        if (reportRows.length) renderRows(reportRows);
        applyFilters();
    });
    if (clearBtn) clearBtn.addEventListener('click', function () {
        window.setTimeout(clearReport, 0);
    });
    if (exportBtn) exportBtn.addEventListener('click', exportExcelReport);
    attachPartnerAutocomplete();
    attachDateAutofill();
    attachTypeTabs();
    clearReport();
})();
</script>
