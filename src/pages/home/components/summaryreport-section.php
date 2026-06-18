<?php
require_once __DIR__ . '/../../../config/db.php';

$partners = [];
try {
    $pdo = masterDataConnection();
    $stmt = $pdo->query("SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (is_array($rows) && count($rows) > 0) {
        $partners = array_values(array_unique(array_map('strval', $rows)));
    }
} catch (Throwable $e) {
    $partners = [];
}

$partnerInputChars = 28;
foreach ($partners as $partner) {
    $partnerInputChars = max($partnerInputChars, strlen((string) $partner));
}
$partnerInputChars = min($partnerInputChars, 90);
?>
<div class="summary-report-content" style="--summary-partner-ch: <?= (int) $partnerInputChars ?>;">
    <style>
        .summary-report-content {
            padding: 0 .25rem .25rem;
            margin-top: -.75rem;
        }

        .summary-report-content .summary-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .55rem;
            flex-wrap: wrap;
        }

        .summary-report-content h3 {
            margin: 0 0 .25rem;
            color: #1f2937;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .summary-report-content p {
            margin: 0;
            color: #6b7280;
            font-size: .9rem;
        }

        .summary-report-content .summary-form {
            background: #fff;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            padding: .75rem;
            display: grid;
            grid-template-columns: auto auto auto;
            gap: .75rem;
            align-items: flex-end;
            justify-content: start;
            margin-bottom: 1rem;
        }

        .summary-report-content .summary-field {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            color: #6b7280;
            font-size: .75rem;
            font-weight: 700;
        }

        .summary-report-content .summary-input,
        .summary-report-content .summary-partner-input {
            height: 38px;
            border: 1px solid #e6eef6;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            font-size: .92rem;
            padding: 0 .65rem;
            min-width: 18ch;
        }

        .summary-report-content .autocomplete-field {
            position: relative;
            width: 100%;
        }

        .summary-report-content .summary-partner-input {
            width: min(calc((var(--summary-partner-ch) * 1ch) + 3rem), 72vw);
            min-width: 0;
            box-sizing: border-box;
            outline: none;
        }

        .summary-report-content .autocomplete-list {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            min-width: 100%;
            max-height: 260px;
            overflow-y: auto;
            margin: 0;
            padding: 4px 0;
            list-style: none;
            background: #fff;
            border: 1px solid #e6eef6;
            border-radius: 6px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            box-sizing: border-box;
            z-index: 50;
        }

        .summary-report-content .autocomplete-item {
            padding: 8px 10px;
            font-size: .9rem;
            font-weight: 400;
            color: #1f2937;
            cursor: pointer;
        }

        .summary-report-content .autocomplete-item:hover,
        .summary-report-content .autocomplete-item.is-active {
            background: #f3f4f6;
        }

        .summary-report-content .summary-button {
            height: 38px;
            border: 1px solid #dc3545;
            background: #dc3545;
            color: #fff;
            border-radius: 6px;
            padding: 0 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        .summary-report-content .summary-button:disabled {
            opacity: .65;
            cursor: wait;
        }

        .summary-report-content .mg-cover {
            display: none;
            margin-top: .75rem;
            background: #fff;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            overflow: hidden;
        }

        .summary-report-content .mg-cover.is-visible {
            display: block;
        }

        .summary-report-content .mg-cover__wrap {
            overflow: auto;
            max-height: 68vh;
        }

        .summary-report-content .mg-cover table {
            border-collapse: collapse;
            min-width: 1780px;
            width: max-content;
            font-size: .72rem;
        }

        .summary-report-content .mg-cover th,
        .summary-report-content .mg-cover td {
            border: 1px solid #dbe5f1;
            padding: .38rem .45rem;
            white-space: nowrap;
            text-align: right;
            color: #111827;
        }

        .summary-report-content .mg-cover th {
            background: #eaf0f7;
            font-weight: 700;
            text-align: center;
        }

        .summary-report-content .mg-cover td:first-child,
        .summary-report-content .mg-cover th:first-child {
            text-align: left;
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 2;
        }

        .summary-report-content .mg-cover thead th:first-child {
            background: #eaf0f7;
            z-index: 3;
        }

        .summary-report-content .mg-cover__title-row td {
            background: #fff;
            font-size: .95rem;
            font-weight: 800;
            text-align: left;
        }

        .summary-report-content .mg-cover__sub-row td {
            background: #fff;
            text-align: left;
            font-weight: 600;
        }

        .summary-report-content .mg-cover__currency-row td {
            background: #fff;
            font-weight: 800;
            text-align: left;
        }

        .summary-report-content .mg-cover__title-row td:first-child,
        .summary-report-content .mg-cover__sub-row td:first-child,
        .summary-report-content .mg-cover__currency-row td:first-child {
            position: static;
        }

        .summary-report-content .mg-cover__total td {
            background: #f8fafc;
            font-weight: 800;
        }

        .summary-report-content .mg-cover__message {
            display: none;
            margin-top: .75rem;
            padding: .75rem 1rem;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            background: #fff;
            color: #6b7280;
            font-size: .9rem;
        }

        .summary-report-content .mg-cover__message.is-visible {
            display: block;
        }

        .summary-report-content .wic-cover-tabs,
        .summary-report-content .moneygram-cover-tabs {
            display: none;
            gap: .35rem;
            margin: .75rem 0 -.25rem;
        }

        .summary-report-content .wic-cover-tabs.is-visible,
        .summary-report-content .moneygram-cover-tabs.is-visible {
            display: flex;
        }

        .summary-report-content .wic-cover-tab,
        .summary-report-content .moneygram-cover-tab {
            border: 1px solid #dbe5f1;
            background: #fff;
            color: #374151;
            border-radius: 6px 6px 0 0;
            padding: .45rem .85rem;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
        }

        .summary-report-content .wic-cover-tab.is-active,
        .summary-report-content .moneygram-cover-tab.is-active {
            background: #eaf0f7;
            color: #111827;
            border-bottom-color: #eaf0f7;
        }

        @media (max-width: 560px) {
            .summary-report-content .summary-form {
                grid-template-columns: 1fr;
            }

            .summary-report-content .summary-input,
            .summary-report-content .summary-partner-input,
            .summary-report-content .summary-button {
                width: 100%;
                min-width: 0;
            }
        }
    </style>

    <div class="summary-heading">
        <div>
            <h3>Summary Report</h3>
            <p>Daily partner-vs-web reconciliation summary in the same cover-sheet format as the uploaded Excel files.</p>
        </div>
    </div>

    <form id="summaryReportForm" class="summary-form">
        <label for="summaryPartner" class="summary-field">
            Corporate Partner
            <div class="autocomplete-field">
                <input id="summaryPartner" name="partner" class="summary-partner-input" placeholder="Search corporate partner" autocomplete="off">
                <ul class="autocomplete-list" id="summaryPartnerSuggestions" role="listbox" hidden></ul>
            </div>
        </label>
        <label class="summary-field">
            Month
            <input id="summaryMonth" class="summary-input" type="month">
        </label>
        <button id="summarySubmit" class="summary-button" type="submit">View Report</button>
    </form>

    <div id="moneygramCoverMessage" class="mg-cover__message"></div>

    <div id="moneygramCoverTabs" class="moneygram-cover-tabs" role="tablist" aria-label="MoneyGram cover type">
        <button class="moneygram-cover-tab is-active" type="button" data-moneygram-cover="payout" data-moneygram-currency="php" role="tab" aria-selected="true">Payout PHP</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="payout" data-moneygram-currency="usd" role="tab" aria-selected="false">Payout USD</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="sendout" data-moneygram-currency="php" role="tab" aria-selected="false">Sendout PHP</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="sendout" data-moneygram-currency="usd" role="tab" aria-selected="false">Sendout USD</button>
    </div>

    <div id="wicCoverTabs" class="wic-cover-tabs" role="tablist" aria-label="WorldCom cover currency">
        <button class="wic-cover-tab is-active" type="button" data-wic-currency="php" role="tab" aria-selected="true">WIC PHP</button>
        <button class="wic-cover-tab" type="button" data-wic-currency="usd" role="tab" aria-selected="false">WIC USD</button>
    </div>

    <div id="moneygramCover" class="mg-cover" aria-live="polite">
        <div class="mg-cover__wrap">
            <table aria-label="Corporate partner cover report">
                <tbody id="moneygramCoverBody"></tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function(){
    const form = document.getElementById('summaryReportForm');
    const partnerEl = document.getElementById('summaryPartner');
    const monthEl = document.getElementById('summaryMonth');
    const submitEl = document.getElementById('summarySubmit');
    const moneygramCover = document.getElementById('moneygramCover');
    const moneygramCoverBody = document.getElementById('moneygramCoverBody');
    const moneygramCoverMessage = document.getElementById('moneygramCoverMessage');
    const moneygramCoverTabs = document.getElementById('moneygramCoverTabs');
    const moneygramCoverTabButtons = moneygramCoverTabs ? Array.from(moneygramCoverTabs.querySelectorAll('.moneygram-cover-tab')) : [];
    const wicCoverTabs = document.getElementById('wicCoverTabs');
    const wicCoverTabButtons = wicCoverTabs ? Array.from(wicCoverTabs.querySelectorAll('.wic-cover-tab')) : [];
    const partners = <?= json_encode($partners) ?>;
    let currentMoneygramData = null;
    let currentMoneygramCover = 'payout';
    let currentMoneygramCurrency = 'php';
    let currentWicData = null;
    let currentWicCurrency = 'php';

    if (!form || !partnerEl || !monthEl) return;

    const now = new Date();
    monthEl.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

    function attachPartnerAutocomplete(inputEl, suggestions){
        const container = inputEl ? inputEl.closest('.autocomplete-field') : null;
        const list = container ? container.querySelector('.autocomplete-list') : null;
        if(!inputEl || !container || !list) return;

        let activeIndex = -1;

        function normalize(value){
            return String(value || '').trim().toLowerCase();
        }

        function getMatches(value){
            const query = normalize(value);
            const options = Array.from(new Set((suggestions || []).map(item => String(item || '').trim()).filter(Boolean)));
            if(!query) return options.slice(0, 8);

            const startsWith = [];
            const contains = [];
            options.forEach(option => {
                const normalizedOption = normalize(option);
                if(normalizedOption.startsWith(query)) startsWith.push(option);
                else if(normalizedOption.includes(query)) contains.push(option);
            });

            return startsWith.concat(contains).slice(0, 8);
        }

        function closeSuggestions(){
            list.hidden = true;
            list.innerHTML = '';
            activeIndex = -1;
        }

        function applyActiveItem(items){
            items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
        }

        function selectSuggestion(value){
            inputEl.value = value;
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
            closeSuggestions();
            inputEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function renderSuggestions(){
            const matches = getMatches(inputEl.value);
            if(matches.length === 0){
                closeSuggestions();
                return;
            }

            list.innerHTML = '';
            matches.forEach((match, index) => {
                const item = document.createElement('li');
                item.className = 'autocomplete-item';
                item.setAttribute('role', 'option');
                item.textContent = match;
                item.addEventListener('mousedown', function(event){
                    event.preventDefault();
                    selectSuggestion(match);
                });
                item.addEventListener('mouseenter', function(){
                    activeIndex = index;
                    applyActiveItem(Array.from(list.children));
                });
                list.appendChild(item);
            });
            activeIndex = -1;
            list.hidden = false;
        }

        inputEl.addEventListener('input', renderSuggestions);
        inputEl.addEventListener('focus', renderSuggestions);
        inputEl.addEventListener('keydown', function(event){
            const items = Array.from(list.querySelectorAll('.autocomplete-item'));
            if(list.hidden || items.length === 0) return;

            if(event.key === 'ArrowDown'){
                event.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                applyActiveItem(items);
            } else if(event.key === 'ArrowUp'){
                event.preventDefault();
                activeIndex = activeIndex <= 0 ? items.length - 1 : activeIndex - 1;
                applyActiveItem(items);
            } else if(event.key === 'Enter'){
                if(activeIndex >= 0 && activeIndex < items.length){
                    event.preventDefault();
                    selectSuggestion(items[activeIndex].textContent || '');
                }
            } else if(event.key === 'Escape'){
                closeSuggestions();
            }
        });

        document.addEventListener('click', function(event){
            if(!container.contains(event.target)) closeSuggestions();
        });
    }

    attachPartnerAutocomplete(partnerEl, partners);

    const numberFormat = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const countFormat = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });
    const dateFormat = new Intl.DateTimeFormat('en-PH', { month: 'long', day: '2-digit', year: 'numeric' });

    function normalizePartner(value) {
        return String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '');
    }

    function monthRange(value) {
        const parts = String(value || '').split('-');
        const year = Number(parts[0]);
        const month = Number(parts[1]);
        if (!year || !month) return null;
        const start = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        const end = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        return { start, end };
    }

    function fmtDate(value) {
        if (!value) return '';
        return dateFormat.format(new Date(`${value}T00:00:00`));
    }

    function fmtNumericDate(value) {
        if (!value) return '';
        const parts = String(value || '').split('-');
        if (parts.length !== 3) return value;
        const year = parts[0];
        const month = parts[1];
        const day = parts[2];
        return `${month}-${day}-${year}`;
    }

    function fmtMonthTitle(startDate) {
        if (!startDate) return '';
        const date = new Date(`${startDate}T00:00:00`);
        return `For the Month of ${date.toLocaleString('en-US', { month: 'long' }).toUpperCase()} ${date.getFullYear()}`;
    }

    function fmtCount(value) {
        const number = Number(value || 0);
        return number === 0 ? '' : countFormat.format(number);
    }

    function fmtMoney(value) {
        const number = Number(value || 0);
        return Math.abs(number) < 0.005 ? '' : numberFormat.format(number);
    }

    function amountGroup(row, key) {
        return row && row[key] ? row[key] : { volume: 0, principal: 0, commission: 0 };
    }

    function td(value, className) {
        const cls = className ? ` class="${className}"` : '';
        return `<td${cls}>${value == null ? '' : value}</td>`;
    }

    function tdSpan(value, colspan, className) {
        const cls = className ? ` class="${className}"` : '';
        return `<td colspan="${colspan}"${cls}>${value == null ? '' : value}</td>`;
    }

    function groupHeaders(groups) {
        return '<tr>' + groups.map(group => `<th colspan="${group.span}">${group.label}</th>`).join('') + '</tr>';
    }

    function columnHeaders(labels) {
        return '<tr>' + labels.map(label => `<th>${label}</th>`).join('') + '</tr>';
    }

    function payoutRow(row) {
        const partner = amountGroup(row, 'partner');
        const web = amountGroup(row, 'web');
        const netWeb = amountGroup(row, 'net_web');
        const variance = amountGroup(row, 'variance');
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td('') + td('') + td('') + td('')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td('')
            + td('') + td('') + td('') + td('')
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function payoutTotalRow(totals) {
        const partner = totals.partner || {};
        const web = totals.web || {};
        const netWeb = totals.net_web || {};
        const variance = totals.variance || {};
        return '<tr class="mg-cover__total">'
            + td('Grand total')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td('') + td('') + td('') + td('')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td('')
            + td('') + td('') + td('') + td('')
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function sendoutSettlement(sendoutSource, payoutSource) {
        const sendoutPartner = amountGroup(sendoutSource, 'partner');
        const sendoutVariance = amountGroup(sendoutSource, 'variance');
        const payoutPartner = amountGroup(payoutSource, 'partner');
        const count = Number(payoutPartner.volume || 0) + Number(sendoutPartner.volume || 0);
        const amount = Number(payoutPartner.principal || 0) - Number(sendoutPartner.principal || 0) + Number(sendoutVariance.commission || 0);
        return { count, amount };
    }

    function sendoutRow(row, payoutRowForDate) {
        const partner = amountGroup(row, 'partner');
        const refund = amountGroup(row, 'refund');
        const netPartner = row && row.net_partner ? row.net_partner : partner;
        const web = amountGroup(row, 'web');
        const cancelled = amountGroup(row, 'cancelled');
        const netWeb = amountGroup(row, 'net_web');
        const variance = amountGroup(row, 'variance');
        const settlement = sendoutSettlement(row, payoutRowForDate || {});
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(refund.volume)) + td(fmtMoney(refund.principal)) + td(fmtMoney(refund.fx)) + td(fmtMoney(refund.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission)) + td(fmtMoney(web.commission))
            + td(fmtCount(settlement.count)) + td(fmtMoney(-settlement.amount)) + td(fmtMoney(settlement.amount))
            + '</tr>';
    }

    function sendoutTotalRow(totals, payoutTotals) {
        const partner = totals.partner || {};
        const refund = totals.refund || {};
        const netPartner = totals.net_partner || partner;
        const web = totals.web || {};
        const cancelled = totals.cancelled || {};
        const netWeb = totals.net_web || {};
        const variance = totals.variance || {};
        const settlement = sendoutSettlement({ partner, variance }, { partner: (payoutTotals || {}).partner || {} });
        return '<tr class="mg-cover__total">'
            + td('Grand total')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(refund.volume)) + td(fmtMoney(refund.principal)) + td(fmtMoney(refund.fx)) + td(fmtMoney(refund.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission)) + td(fmtMoney(web.commission))
            + td(fmtCount(settlement.count)) + td(fmtMoney(-settlement.amount)) + td(fmtMoney(settlement.amount))
            + '</tr>';
    }

    function showMoneygramTabs(show) {
        if (!moneygramCoverTabs) return;
        moneygramCoverTabs.classList.toggle('is-visible', Boolean(show));
    }

    function setActiveMoneygramTab(cover, currency) {
        currentMoneygramCover = cover === 'sendout' ? 'sendout' : 'payout';
        currentMoneygramCurrency = currency === 'usd' ? 'usd' : 'php';
        moneygramCoverTabButtons.forEach(button => {
            const isActive = button.dataset.moneygramCover === currentMoneygramCover
                && (button.dataset.moneygramCurrency || 'php') === currentMoneygramCurrency;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function renderMoneygramCover(data, cover, currency) {
        const selectedCurrency = currency === 'usd' ? 'usd' : 'php';
        const selectedCover = cover === 'sendout' ? 'sendout' : 'payout';
        const reportsKey = selectedCover === 'sendout' ? 'sendout_reports' : 'currency_reports';
        const selectedReport = data && data[reportsKey] && data[reportsKey][selectedCurrency]
            ? data[reportsKey][selectedCurrency]
            : data;
        const rows = Array.isArray(selectedReport.rows) ? selectedReport.rows : [];
        const totals = selectedReport.totals || {};
        const reportStart = data.start_date || '';
        const currencyLabel = selectedCurrency.toUpperCase() + ' Trxns';
        showWicTabs(false);
        const payoutGroups = [
            { label: '', span: 1 },
            { label: 'MONEYGRAM', span: 4 },
            { label: 'RRC', span: 4 },
            { label: 'NET TRXNS(MONEYGRAM)', span: 3 },
            { label: 'KPX', span: 3 },
            { label: 'CANCELLED', span: 4 },
            { label: 'KPX NET', span: 3 },
            { label: 'MONEYGRAM VS. KPX VARIANCE', span: 3 }
        ];
        const payoutLabels = [
            'Date', 'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'Commission',
            'Count', 'Principal', 'CHARGE',
            'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'Commission',
            'Count', 'Principal', 'Commission'
        ];
        const sendoutGroups = [
            { label: '', span: 1 },
            { label: selectedCurrency === 'usd' ? 'GROSS' : 'MONEYGRAM', span: 4 },
            { label: 'REFUND', span: 4 },
            { label: 'NET TRXNS(MONEYGRAM)', span: 3 },
            { label: 'KPX', span: 3 },
            { label: 'CANCELLED', span: 4 },
            { label: 'KPX NET', span: 3 },
            { label: 'MONEYGRAM VS. KPX VARIANCE', span: 4 },
            { label: 'MONEYGRAM SETTLEMENT', span: 3 }
        ];
        const sendoutLabels = [
            'Date', 'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'Commission',
            'Count', 'Principal', 'CHARGE',
            'Count', 'Principal', 'FX', 'Commission',
            'Count', 'Principal', 'Commission',
            'Count', 'Principal', 'Commission', 'fee',
            'Count', 'Amount', 'Variance'
        ];

        if (selectedCover === 'sendout') {
            const payoutReport = data && data.currency_reports && data.currency_reports[selectedCurrency]
                ? data.currency_reports[selectedCurrency]
                : {};
            const payoutRowsByDate = {};
            (Array.isArray(payoutReport.rows) ? payoutReport.rows : []).forEach(payoutRowForDate => {
                if (payoutRowForDate && payoutRowForDate.date) payoutRowsByDate[payoutRowForDate.date] = payoutRowForDate;
            });
            moneygramCoverBody.innerHTML = [
                `<tr class="mg-cover__title-row">${tdSpan('Moneygram Sendout', 29)}</tr>`,
                `<tr class="mg-cover__sub-row">${tdSpan(fmtNumericDate(reportStart), 29)}</tr>`,
                `<tr class="mg-cover__currency-row">${tdSpan(currencyLabel, 29)}</tr>`,
                groupHeaders(sendoutGroups),
                columnHeaders(sendoutLabels),
                rows.map(row => sendoutRow(row, payoutRowsByDate[row.date])).join(''),
                sendoutTotalRow(totals, payoutReport.totals || {})
            ].join('');
        } else {
            moneygramCoverBody.innerHTML = [
                `<tr class="mg-cover__title-row">${tdSpan('Moneygram Payout', 25)}</tr>`,
                `<tr class="mg-cover__sub-row">${tdSpan(fmtNumericDate(reportStart), 25)}</tr>`,
                `<tr class="mg-cover__currency-row">${tdSpan(currencyLabel, 25)}</tr>`,
                groupHeaders(payoutGroups),
                columnHeaders(payoutLabels),
                rows.map(payoutRow).join(''),
                payoutTotalRow(totals)
            ].join('');
        }

        moneygramCover.classList.add('is-visible');
        showMoneygramTabs(true);
        setActiveMoneygramTab(selectedCover, selectedCurrency);
        moneygramCoverMessage.classList.remove('is-visible');
        moneygramCoverMessage.textContent = '';
    }

    function mbtcRow(row) {
        const partner = amountGroup(row, 'partner');
        const web = amountGroup(row, 'web');
        const duplicates = amountGroup(row, 'duplicates');
        const netWeb = amountGroup(row, 'net_web');
        const variance = amountGroup(row, 'variance');
        const deposit = mbtcDepositWebValues(netWeb, row.deposit || {});
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(duplicates.volume)) + td(fmtMoney(duplicates.principal)) + td(fmtMoney(duplicates.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + td(fmtMoney(deposit.debit)) + td(fmtMoney(deposit.credit)) + td(fmtMoney(deposit.variance))
            + td(fmtMoney(deposit.commissionShare)) + td(fmtMoney(deposit.commissionNet))
            + '</tr>';
    }

    function mbtcDepositWebValues(source, deposit) {
        const principal = Number(source.principal || 0);
        const commission = Number(source.commission || 0);
        const commissionShare = commission / 56;
        const commissionNet = commission - commissionShare;
        const debit = Number((deposit || {}).debit || 0);
        const credit = Number((deposit || {}).credit || 0);
        return {
            debit,
            credit,
            variance: debit + credit - principal - commissionNet,
            commissionShare,
            commissionNet
        };
    }

    function mbtcTotalRow(totals) {
        const partner = totals.partner || {};
        const web = totals.web || {};
        const duplicates = totals.duplicates || {};
        const netWeb = totals.net_web || {};
        const variance = totals.variance || {};
        const deposit = mbtcDepositWebValues(netWeb, totals.deposit || {});
        return '<tr class="mg-cover__total">'
            + td('TOTAL')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(duplicates.volume)) + td(fmtMoney(duplicates.principal)) + td(fmtMoney(duplicates.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + td(fmtMoney(deposit.debit)) + td(fmtMoney(deposit.credit)) + td(fmtMoney(deposit.variance))
            + td(fmtMoney(deposit.commissionShare)) + td(fmtMoney(deposit.commissionNet))
            + '</tr>';
    }

    function renderMbtcCover(data) {
        const rows = Array.isArray(data.rows) ? data.rows : [];
        const totals = data.totals || {};
        showMoneygramTabs(false);
        showWicTabs(false);
        const groups = [
            { label: '', span: 1 },
            { label: 'MBTC', span: 3 },
            { label: 'WEB KPX', span: 3 },
            { label: 'DUPLICATE TRXNS', span: 3 },
            { label: 'NET WEB REPORT', span: 3 },
            { label: 'PARTNER VS. WEB', span: 3 },
            { label: 'DEPOSIT VS. WEB', span: 3 },
            { label: '', span: 2 }
        ];
        const labels = [
            'Date',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION',
            'DEBIT', 'CREDIT', 'VARIANCE',
            '', ''
        ];

        moneygramCoverBody.innerHTML = [
            `<tr class="mg-cover__title-row">${tdSpan('MBTC', 21)}</tr>`,
            `<tr class="mg-cover__sub-row">${tdSpan(fmtMonthTitle(data.start_date), 21)}</tr>`,
            `<tr>${tdSpan('', 21)}</tr>`,
            groupHeaders(groups),
            columnHeaders(labels),
            rows.map(mbtcRow).join(''),
            mbtcTotalRow(totals)
        ].join('');

        moneygramCover.classList.add('is-visible');
        moneygramCoverMessage.classList.remove('is-visible');
        moneygramCoverMessage.textContent = '';
    }

    function wicRow(row) {
        const partner = amountGroup(row, 'partner');
        const web = amountGroup(row, 'web');
        const netWeb = amountGroup(row, 'net_web');
        const variance = amountGroup(row, 'variance');
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function wicTotalRow(totals) {
        const partner = totals.partner || {};
        const web = totals.web || {};
        const netWeb = totals.net_web || {};
        const variance = totals.variance || {};
        return '<tr class="mg-cover__total">'
            + td('TOTAL')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(netWeb.volume)) + td(fmtMoney(netWeb.principal)) + td(fmtMoney(netWeb.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function showWicTabs(show) {
        if (!wicCoverTabs) return;
        wicCoverTabs.classList.toggle('is-visible', Boolean(show));
    }

    function setActiveWicTab(currency) {
        currentWicCurrency = currency === 'usd' ? 'usd' : 'php';
        wicCoverTabButtons.forEach(button => {
            const isActive = button.dataset.wicCurrency === currentWicCurrency;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function getWicCurrencyReport(data, currency) {
        const reports = data && data.currency_reports ? data.currency_reports : {};
        return reports && reports[currency] ? reports[currency] : data;
    }

    function renderWicCover(data, currency) {
        const selectedCurrency = currency === 'usd' ? 'usd' : 'php';
        const report = getWicCurrencyReport(data, selectedCurrency);
        const rows = Array.isArray(report.rows) ? report.rows : [];
        const totals = report.totals || {};
        const title = selectedCurrency === 'usd' ? 'WIC USD' : 'WIC PHP';
        const groups = [
            { label: '', span: 1 },
            { label: title, span: 2 },
            { label: 'WEB KPX', span: 3 },
            { label: 'NET WEB REPORT', span: 3 },
            { label: 'PARTNER VS. WEB', span: 3 }
        ];
        const labels = [
            'Date',
            'Vol', 'Principal',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION',
            'Vol', 'Principal', 'COMMISSION'
        ];

        moneygramCoverBody.innerHTML = [
            `<tr class="mg-cover__title-row">${tdSpan(title, 12)}</tr>`,
            `<tr class="mg-cover__sub-row">${tdSpan(fmtMonthTitle(data.start_date), 12)}</tr>`,
            `<tr>${tdSpan('', 12)}</tr>`,
            groupHeaders(groups),
            columnHeaders(labels),
            rows.map(wicRow).join(''),
            wicTotalRow(totals)
        ].join('');

        moneygramCover.classList.add('is-visible');
        showMoneygramTabs(false);
        showWicTabs(true);
        setActiveWicTab(selectedCurrency);
        moneygramCoverMessage.classList.remove('is-visible');
        moneygramCoverMessage.textContent = '';
    }

    async function loadSummaryReport() {
        const selectedPartner = partnerEl.value;
        const selectedKey = normalizePartner(selectedPartner);
        const isMoneygram = selectedKey === 'MONEYGRAM';
        const isMbtc = selectedKey === 'MBTC' || selectedKey === 'METROBANKHEADOFFICE';
        const isWic = selectedKey === 'WIC' || selectedKey === 'WORLDCOMINTERNATIONALCOMMUNICATIONS';

        if (!isMoneygram && !isMbtc && !isWic) {
            moneygramCover.classList.remove('is-visible');
            showMoneygramTabs(false);
            showWicTabs(false);
            currentMoneygramData = null;
            currentWicData = null;
            moneygramCoverMessage.textContent = 'Cover format is available for MONEYGRAM, METROBANK HEAD OFFICE, and WORLDCOM INTERNATIONAL COMMUNICATIONS.';
            moneygramCoverMessage.classList.add('is-visible');
            return;
        }

        const range = monthRange(monthEl.value);
        if (!range) return;

        setLoading(true);
        moneygramCover.classList.remove('is-visible');
        showMoneygramTabs(false);
        showWicTabs(false);
        moneygramCoverMessage.textContent = `Loading ${isMoneygram ? 'MoneyGram' : (isMbtc ? 'Metrobank Head Office' : 'WorldCom International Communications')} cover format...`;
        moneygramCoverMessage.classList.add('is-visible');

        try {
            const params = new URLSearchParams({
                partner: isMoneygram ? 'MONEYGRAM' : (isMbtc ? 'METROBANK HEAD OFFICE' : 'WORLDCOM INTERNATIONAL COMMUNICATIONS'),
                start_date: range.start,
                end_date: range.end
            });
            const response = await fetch(`../../controllers/excelcontrol/summary-report.php?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (!data || !data.success) {
                throw new Error(data && data.error ? data.error : 'Unable to load cover format.');
            }
            if (isMoneygram) {
                currentMoneygramData = data;
                currentWicData = null;
                renderMoneygramCover(data, currentMoneygramCover, currentMoneygramCurrency);
            } else if (isMbtc) {
                currentMoneygramData = null;
                currentWicData = null;
                renderMbtcCover(data);
            } else {
                currentMoneygramData = null;
                currentWicData = data;
                renderWicCover(data, currentWicCurrency);
            }
        } catch (error) {
            moneygramCover.classList.remove('is-visible');
            showMoneygramTabs(false);
            showWicTabs(false);
            currentMoneygramData = null;
            currentWicData = null;
            moneygramCoverMessage.textContent = String(error.message || error);
            moneygramCoverMessage.classList.add('is-visible');
        } finally {
            setLoading(false);
        }
    }

    function setLoading(isLoading) {
        if (submitEl) {
            submitEl.disabled = isLoading;
            submitEl.textContent = isLoading ? 'Loading...' : 'View Report';
        }
    }

    form.addEventListener('submit', function(event){
        event.preventDefault();
        loadSummaryReport();
    });

    moneygramCoverTabButtons.forEach(button => {
        button.addEventListener('click', function(){
            const cover = button.dataset.moneygramCover === 'sendout' ? 'sendout' : 'payout';
            const currency = button.dataset.moneygramCurrency === 'usd' ? 'usd' : 'php';
            if (!currentMoneygramData) return;
            renderMoneygramCover(currentMoneygramData, cover, currency);
        });
    });

    wicCoverTabButtons.forEach(button => {
        button.addEventListener('click', function(){
            const currency = button.dataset.wicCurrency === 'usd' ? 'usd' : 'php';
            if (!currentWicData) return;
            renderWicCover(currentWicData, currency);
        });
    });

})();
</script>
