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

        .summary-report-content .summary-button--export {
            border-color: #198754;
            background: #198754;
        }

        .summary-report-content .summary-button--export:disabled {
            cursor: not-allowed;
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
            vertical-align: middle;
        }

        .summary-report-content .mg-cover tbody tr:nth-child(1) th {
            position: sticky;
            top: 0;
            z-index: 4;
        }

        .summary-report-content .mg-cover tbody tr:nth-child(2) th {
            position: sticky;
            top: 30px;
            z-index: 4;
        }

        .summary-report-content .mg-cover tbody tr:nth-child(3) th {
            position: sticky;
            top: 60px;
            z-index: 4;
        }

        .summary-report-content .mg-cover td:first-child,
        .summary-report-content .mg-cover tbody tr:nth-child(1) th:first-child {
            text-align: center;
            position: sticky;
            left: 0;
            background: #eaf0f7;
            z-index: 7;
        }

        .summary-report-content .mg-cover td:first-child {
            text-align: left;
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 2;
        }

        .summary-report-content .mg-cover__heading {
            display: none;
            flex: 1 1 auto;
            padding: .35rem 1rem;
            background: #fff;
            text-align: center;
            min-width: 220px;
        }

        .summary-report-content .mg-cover__heading.is-visible {
            display: block;
        }

        .summary-report-content .mg-cover__title {
            margin: 0 0 .45rem;
            color: #111827;
            font-size: .95rem;
            font-weight: 800;
        }

        .summary-report-content .mg-cover__currency {
            color: #111827;
            font-size: .78rem;
            font-weight: 800;
        }

        .summary-report-content .mg-cover__total td {
            background: #f8fafc;
            font-weight: 800;
            position: sticky;
            bottom: 0;
            z-index: 4;
        }

        .summary-report-content .mg-cover__total td:first-child {
            z-index: 6;
        }

        .summary-report-content .mg-cover tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)):hover td {
            filter: brightness(0.96);
        }

        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(1) th:nth-child(2),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(2) th:nth-child(-n+3),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(3) th:nth-child(-n+12),
        .summary-report-content .mg-cover.is-moneygram tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)) td:nth-child(n+2):nth-child(-n+13) {
            background: #f8bac1;
        }

        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(1) th:nth-child(3),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(2) th:nth-child(n+4):nth-child(-n+5),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(3) th:nth-child(n+13):nth-child(-n+19),
        .summary-report-content .mg-cover.is-moneygram tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)) td:nth-child(n+14):nth-child(-n+20) {
            background: #f9cbd0;
        }

        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(1) th:nth-child(4),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(2) th:nth-child(6),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(3) th:nth-child(n+20),
        .summary-report-content .mg-cover.is-moneygram tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)) td:nth-child(n+21) {
            background: #fbdce0;
        }

        .summary-report-content .mg-cover.is-moneygram.is-moneygram-sendout tbody tr:nth-child(1) th:nth-child(4),
        .summary-report-content .mg-cover.is-moneygram.is-moneygram-sendout tbody tr:nth-child(2) th:nth-child(6),
        .summary-report-content .mg-cover.is-moneygram.is-moneygram-sendout tbody tr:nth-child(3) th:nth-child(n+20):nth-child(-n+23),
        .summary-report-content .mg-cover.is-moneygram.is-moneygram-sendout tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)) td:nth-child(n+21):nth-child(-n+24) {
            background: #fad2d7;
        }

        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(1) th:nth-child(5),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(2) th:nth-child(7),
        .summary-report-content .mg-cover.is-moneygram tbody tr:nth-child(3) th:nth-child(n+24),
        .summary-report-content .mg-cover.is-moneygram tbody tr:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)) td:nth-child(n+25) {
            background: #fbdce0;
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
            align-items: center;
            width: 100%;
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

        .summary-report-content .summary-export-spacer {
            flex: 0 0 auto;
        }

        .summary-report-content .summary-download-section {
            display: none;
            margin: .75rem 0 0;
            justify-content: flex-end;
        }

        .summary-report-content .summary-export-host {
            display: none;
            margin: .75rem 0 -.25rem;
            justify-content: flex-end;
        }

        .summary-report-content .summary-export-host.is-visible {
            display: flex;
        }

        .summary-report-content .summary-download-section.is-visible {
            display: flex;
        }

        .summary-report-content .summary-download-link {
            color: #198754;
            font-size: .86rem;
            font-weight: 700;
            text-decoration: none;
        }

        .summary-report-content .summary-download-link:hover {
            text-decoration: underline;
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

            .summary-report-content .summary-export-spacer {
                display: none;
            }

            .summary-report-content .mg-cover__heading {
                order: 2;
                flex-basis: 100%;
                min-width: 0;
            }
        }
    </style>

    <div class="summary-heading">
        <div>
            <h3>Reconciliation and Variance Summary Report</h3>
            <!-- <p>Daily partner-vs-web reconciliation summary in the same cover-sheet format as the uploaded Excel files.</p> -->
        </div>
    </div>

    <form id="summaryReportForm" class="summary-form">
        <label for="summaryPartner" class="summary-field">
            Corporate Partner
            <div class="autocomplete-field">
                <input id="summaryPartner" name="partner" class="summary-partner-input" placeholder="Select or Type here..." autocomplete="off">
                <ul class="autocomplete-list" id="summaryPartnerSuggestions" role="listbox" hidden></ul>
            </div>
        </label>
        <label class="summary-field">
            Month
            <input id="summaryMonth" class="summary-input" type="month">
        </label>
        <button id="summarySubmit" class="summary-button" type="submit">Generate</button>
    </form>

    <div id="moneygramCoverMessage" class="mg-cover__message"></div>

    <div id="moneygramCoverTabs" class="moneygram-cover-tabs" role="tablist" aria-label="MoneyGram cover type">
        <button class="moneygram-cover-tab is-active" type="button" data-moneygram-cover="payout" data-moneygram-currency="php" role="tab" aria-selected="true">Payout PHP</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="payout" data-moneygram-currency="usd" role="tab" aria-selected="false">Payout USD</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="sendout" data-moneygram-currency="php" role="tab" aria-selected="false">Sendout PHP</button>
        <button class="moneygram-cover-tab" type="button" data-moneygram-cover="sendout" data-moneygram-currency="usd" role="tab" aria-selected="false">Sendout USD</button>
        <div id="moneygramCoverHeading" class="mg-cover__heading">
            <div id="moneygramCoverTitle" class="mg-cover__title"></div>
            <div id="moneygramCoverCurrency" class="mg-cover__currency"></div>
        </div>
        <span class="summary-export-spacer" aria-hidden="true"></span>
        <button id="summaryExportExcel" class="summary-button summary-button--export" type="button" disabled>Export to Excel</button>
    </div>
    <div>
        
    </div>

    <div id="summaryDownloadSection" class="summary-download-section" aria-live="polite">
        <a id="summaryDownloadLink" class="summary-download-link" href="#" download hidden>Download Excel file</a>
    </div>

    <div id="wicCoverTabs" class="wic-cover-tabs" role="tablist" aria-label="WorldCom cover currency">
        <button class="wic-cover-tab is-active" type="button" data-wic-currency="php" role="tab" aria-selected="true">WIC PHP</button>
        <button class="wic-cover-tab" type="button" data-wic-currency="usd" role="tab" aria-selected="false">WIC USD</button>
    </div>

    <div id="summaryExportHost" class="summary-export-host"></div>

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
    const exportExcelEl = document.getElementById('summaryExportExcel');
    const downloadSectionEl = document.getElementById('summaryDownloadSection');
    const downloadLinkEl = document.getElementById('summaryDownloadLink');
    const exportHostEl = document.getElementById('summaryExportHost');
    const moneygramCover = document.getElementById('moneygramCover');
    const moneygramCoverHeading = document.getElementById('moneygramCoverHeading');
    const moneygramCoverTitle = document.getElementById('moneygramCoverTitle');
    const moneygramCoverCurrency = document.getElementById('moneygramCoverCurrency');
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
    let currentReportTitle = 'Summary Report';
    let currentDownloadUrl = '';

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
        return '<tr>' + groups.map(group => {
            const colspan = group.span && group.span > 1 ? ` colspan="${group.span}"` : '';
            const rowspan = group.rowspan && group.rowspan > 1 ? ` rowspan="${group.rowspan}"` : '';
            return `<th${colspan}${rowspan}>${group.label}</th>`;
        }).join('') + '</tr>';
    }

    function columnHeaders(labels) {
        return '<tr>' + labels.map(label => `<th>${label}</th>`).join('') + '</tr>';
    }

    function setCoverHeading(title, currency) {
        if (!moneygramCoverHeading || !moneygramCoverTitle || !moneygramCoverCurrency) return;
        const hasHeading = Boolean(title || currency);
        moneygramCoverTitle.textContent = title || '';
        moneygramCoverCurrency.textContent = currency || '';
        moneygramCoverHeading.classList.toggle('is-visible', hasHeading);
    }

    function netPartnerRevShare(partner, cancelled) {
        return Number((partner || {}).fx || 0) - Number((cancelled || {}).fx || 0);
    }

    function kpxWebAmounts(row) {
        // row.web is already restricted by the controller to records whose
        // date_cancelled/date_cancellation value is empty.
        return amountGroup(row, 'web');
    }

    function payoutRow(row) {
        const partner = amountGroup(row, 'partner');
        const cancelled = amountGroup(row, 'partner_cancelled');
        const netPartner = amountGroup(row, 'net_partner');
        const web = kpxWebAmounts(row);
        const webCancelled = amountGroup(row, 'cancelled');
        const variance = amountGroup(row, 'variance');
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartner.fx)) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td('')
            + td(fmtCount(webCancelled.volume)) + td(fmtMoney(webCancelled.principal)) + td(fmtMoney(webCancelled.fx)) + td(fmtMoney(webCancelled.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function payoutTotalRow(totals) {
        const partner = totals.partner || {};
        const cancelled = totals.partner_cancelled || {};
        const netPartner = totals.net_partner || {};
        const web = totals.web || {};
        const webCancelled = totals.cancelled || {};
        const variance = totals.variance || {};
        return '<tr class="mg-cover__total">'
            + td('Grand total')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartner.fx)) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td('')
            + td(fmtCount(webCancelled.volume)) + td(fmtMoney(webCancelled.principal)) + td(fmtMoney(webCancelled.fx)) + td(fmtMoney(webCancelled.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(variance.commission))
            + '</tr>';
    }

    function sendoutSettlement(sendoutSource, payoutSource) {
        const sendoutPartner = amountGroup(sendoutSource, 'partner');
        const sendoutCancelled = amountGroup(sendoutSource, 'partner_cancelled');
        const sendoutNetPartner = amountGroup(sendoutSource, 'net_partner');
        const payoutPartner = amountGroup(payoutSource, 'partner');
        const payoutNetPartner = amountGroup(payoutSource, 'net_partner');
        const count = Number(payoutPartner.volume || 0) + Number(sendoutPartner.volume || 0);
        const amount = Number(sendoutPartner.principal || 0)
            + Number(sendoutPartner.fee || 0)
            - Number(sendoutNetPartner.commission || 0)
            - Number(payoutNetPartner.principal || 0)
            - Number(sendoutCancelled.principal || 0);
        return { count, amount, variance: -amount };
    }

    function sendoutRow(row, payoutRowForDate) {
        const partner = amountGroup(row, 'partner');
        const refund = amountGroup(row, 'partner_cancelled');
        const netPartner = row && row.net_partner ? row.net_partner : partner;
        const web = kpxWebAmounts(row);
        const cancelled = amountGroup(row, 'cancelled');
        const variance = amountGroup(row, 'variance');
        const settlement = sendoutSettlement(row, payoutRowForDate || {});
        const varianceComm = Number(netPartner.commission || 0) - Number(web.commission || 0);
        const varianceFee = Number(partner.fee || 0);
        return '<tr>'
            + td(fmtDate(row.date))
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(refund.volume)) + td(fmtMoney(refund.principal)) + td(fmtMoney(refund.fx)) + td(fmtMoney(refund.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartnerRevShare(partner, refund))) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(varianceComm)) + td(fmtMoney(varianceFee))
            + td(fmtCount(settlement.count)) + td(fmtMoney(settlement.amount)) + td(fmtMoney(settlement.variance))
            + '</tr>';
    }

    function sendoutTotalRow(totals, payoutTotals) {
        const partner = totals.partner || {};
        const refund = totals.partner_cancelled || {};
        const netPartner = totals.net_partner || partner;
        const web = totals.web || {};
        const cancelled = totals.cancelled || {};
        const variance = totals.variance || {};
        const settlement = sendoutSettlement(
            { partner, partner_cancelled: refund, net_partner: netPartner },
            payoutTotals || {}
        );
        const varianceComm = Number(netPartner.commission || 0) - Number(web.commission || 0);
        const varianceFee = Number(partner.fee || 0);
        return '<tr class="mg-cover__total">'
            + td('Grand total')
            + td(fmtCount(partner.volume)) + td(fmtMoney(partner.principal)) + td(fmtMoney(partner.fx)) + td(fmtMoney(partner.commission))
            + td(fmtCount(refund.volume)) + td(fmtMoney(refund.principal)) + td(fmtMoney(refund.fx)) + td(fmtMoney(refund.commission))
            + td(fmtCount(netPartner.volume)) + td(fmtMoney(netPartner.principal)) + td(fmtMoney(netPartnerRevShare(partner, refund))) + td(fmtMoney(netPartner.commission))
            + td(fmtCount(web.volume)) + td(fmtMoney(web.principal)) + td(fmtMoney(web.commission))
            + td(fmtCount(cancelled.volume)) + td(fmtMoney(cancelled.principal)) + td(fmtMoney(cancelled.fx)) + td(fmtMoney(cancelled.commission))
            + td(fmtCount(variance.volume)) + td(fmtMoney(variance.principal)) + td(fmtMoney(varianceComm)) + td(fmtMoney(varianceFee))
            + td(fmtCount(settlement.count)) + td(fmtMoney(settlement.amount)) + td(fmtMoney(settlement.variance))
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
        moneygramCover.classList.add('is-moneygram');
        const selectedCurrency = currency === 'usd' ? 'usd' : 'php';
        const selectedCover = cover === 'sendout' ? 'sendout' : 'payout';
        moneygramCover.classList.toggle('is-moneygram-sendout', selectedCover === 'sendout');
        const reportsKey = selectedCover === 'sendout' ? 'sendout_reports' : 'currency_reports';
        const selectedReport = data && data[reportsKey] && data[reportsKey][selectedCurrency]
            ? data[reportsKey][selectedCurrency]
            : data;
        const rows = Array.isArray(selectedReport.rows) ? selectedReport.rows : [];
        const totals = selectedReport.totals || {};
        const reportStart = data.start_date || '';
        const currencyLabel = 'Currency ' + selectedCurrency.toUpperCase();
        const partnerLabel = String(partnerEl && partnerEl.value ? partnerEl.value : 'MONEYGRAM').trim() || 'MONEYGRAM';
        showWicTabs(false);
        const payoutSections = [
            { label: 'Date', span: 1, rowspan: 3 },
            { label: 'Partner Data', span: 12 },
            { label: 'KPX Web Data', span: 7 },
            { label: 'VARIANCE', span: 3 }
        ];
        const payoutGroups = [
            { label: partnerLabel, span: 4 },
            { label: 'CANCELLED', span: 4 },
            { label: 'NET', span: 4 },
            { label: 'KPX', span: 3 },
            { label: 'CANCELLED', span: 4 },
            { label: `${partnerLabel} vs KPX WEB`, span: 3 }
        ];
        const payoutLabels = [
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'CHARGE',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Comm'
        ];
        const sendoutSections = [
            { label: 'Date', span: 1, rowspan: 3 },
            { label: 'Partner Data', span: 12 },
            { label: 'KPX Web Data', span: 7 },
            { label: 'VARIANCE', span: 4 },
            { label: `${partnerLabel} SETTLEMENT`, span: 3, rowspan: 2 }
        ];
        const sendoutGroups = [
            { label: selectedCurrency === 'usd' ? 'GROSS' : partnerLabel, span: 4 },
            { label: 'CANCELLED', span: 4 },
            { label: 'NET', span: 4 },
            { label: 'KPX', span: 3 },
            { label: 'CANCELLED', span: 4 },
            { label: `${partnerLabel} vs KPX WEB`, span: 4 }
        ];
        const sendoutLabels = [
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'CHARGE',
            'Volume', 'Principal', 'Rev Share', 'Comm',
            'Volume', 'Principal', 'Comm', 'fee',
            'Volume', 'Amount', 'Variance'
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
                groupHeaders(sendoutSections),
                groupHeaders(sendoutGroups),
                columnHeaders(sendoutLabels),
                rows.map(row => sendoutRow(row, payoutRowsByDate[row.date])).join(''),
                sendoutTotalRow(totals, payoutReport.totals || {})
            ].join('');
            setCoverHeading('Moneygram Sendout', currencyLabel);
        } else {
            moneygramCoverBody.innerHTML = [
                groupHeaders(payoutSections),
                groupHeaders(payoutGroups),
                columnHeaders(payoutLabels),
                rows.map(payoutRow).join(''),
                payoutTotalRow(totals)
            ].join('');
            setCoverHeading('Moneygram Payout', currencyLabel);
        }

        moneygramCover.classList.add('is-visible');
        placeExportButton(moneygramCoverTabs);
        setExportReady(true);
        showMoneygramTabs(true);
        setActiveMoneygramTab(selectedCover, selectedCurrency);
        currentReportTitle = `${selectedCover === 'sendout' ? 'MoneyGram Sendout' : 'MoneyGram Payout'} ${selectedCurrency.toUpperCase()}`;
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
        moneygramCover.classList.remove('is-moneygram');
        const rows = Array.isArray(data.rows) ? data.rows : [];
        const totals = data.totals || {};
        setCoverHeading('', '');
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
        placeExportButton(exportHostEl);
        setExportReady(true);
        currentReportTitle = 'Metrobank Head Office';
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
        moneygramCover.classList.remove('is-moneygram');
        const selectedCurrency = currency === 'usd' ? 'usd' : 'php';
        const report = getWicCurrencyReport(data, selectedCurrency);
        const rows = Array.isArray(report.rows) ? report.rows : [];
        const totals = report.totals || {};
        const title = selectedCurrency === 'usd' ? 'WIC USD' : 'WIC PHP';
        setCoverHeading('', '');
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
        placeExportButton(wicCoverTabs);
        setExportReady(true);
        showMoneygramTabs(false);
        showWicTabs(true);
        setActiveWicTab(selectedCurrency);
        currentReportTitle = `WIC ${selectedCurrency.toUpperCase()}`;
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
            setExportReady(false);
            placeExportButton(null);
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
        setExportReady(false);
        placeExportButton(null);
        clearDownloadSection();
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
            setExportReady(false);
            placeExportButton(null);
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

    function setExportReady(isReady) {
        if (exportExcelEl) {
            exportExcelEl.disabled = !isReady;
        }
    }

    function placeExportButton(container) {
        if (exportHostEl) {
            exportHostEl.classList.toggle('is-visible', container === exportHostEl);
        }
        if (!exportExcelEl) return;
        if (!container) {
            exportExcelEl.disabled = true;
            return;
        }
        if (container === moneygramCoverTabs || container === wicCoverTabs) {
            let spacer = container.querySelector('.summary-export-spacer');
            if (!spacer) {
                spacer = document.createElement('span');
                spacer.className = 'summary-export-spacer';
                spacer.setAttribute('aria-hidden', 'true');
                container.appendChild(spacer);
            }
        }
        container.appendChild(exportExcelEl);
    }

    function clearDownloadSection() {
        if (currentDownloadUrl) {
            URL.revokeObjectURL(currentDownloadUrl);
            currentDownloadUrl = '';
        }
        if (downloadLinkEl) {
            downloadLinkEl.href = '#';
            downloadLinkEl.hidden = true;
        }
        if (downloadSectionEl) {
            downloadSectionEl.classList.remove('is-visible');
        }
    }

    function getFilenameFromDisposition(disposition) {
        const selectedKey = normalizePartner(partnerEl.value);
        const prefix = selectedKey === 'MONEYGRAM'
            ? 'MONEYGRAM'
            : ((selectedKey === 'MBTC' || selectedKey === 'METROBANKHEADOFFICE') ? 'MBTC' : 'WIC');
        const fallback = `${prefix}_SUMMARY_REPORT_${monthEl.value || 'report'}.xlsx`;
        const match = String(disposition || '').match(/filename="?([^"]+)"?/i);
        return match && match[1] ? match[1] : fallback;
    }

    function summaryExportEndpoint(selectedKey) {
        if (selectedKey === 'MONEYGRAM') {
            return '../../modals/generate/summary-report/excel/moneygram-cover/moneygram-excel-format.php';
        }
        if (selectedKey === 'MBTC' || selectedKey === 'METROBANKHEADOFFICE') {
            return '../../modals/generate/summary-report/excel/mbtc-cover/mbtc-excel-format.php';
        }
        if (selectedKey === 'WIC' || selectedKey === 'WORLDCOMINTERNATIONALCOMMUNICATIONS') {
            return '../../modals/generate/summary-report/excel/wic-cover/wic-excel-format.php';
        }
        return '';
    }

    async function exportCurrentReportToExcel() {
        const selectedKey = normalizePartner(partnerEl.value);
        const endpoint = summaryExportEndpoint(selectedKey);
        if (!endpoint) {
            moneygramCoverMessage.textContent = 'Excel export is available for MoneyGram, Metrobank Head Office, and WorldCom International Communications.';
            moneygramCoverMessage.classList.add('is-visible');
            return;
        }

        if (!moneygramCover || !moneygramCover.classList.contains('is-visible') || !moneygramCoverBody || !moneygramCoverBody.children.length) {
            moneygramCoverMessage.textContent = 'Please view a report before exporting.';
            moneygramCoverMessage.classList.add('is-visible');
            setExportReady(false);
            return;
        }

        clearDownloadSection();
        exportExcelEl.disabled = true;
        exportExcelEl.textContent = 'Preparing...';

        try {
            const params = new URLSearchParams({ month: monthEl.value });
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                credentials: 'same-origin'
            });
            if (!response.ok) {
                let message = 'Unable to prepare Excel file.';
                try {
                    const errorData = await response.json();
                    if (errorData && errorData.error) message = errorData.error;
                } catch (_) {}
                throw new Error(message);
            }

            const blob = await response.blob();
            currentDownloadUrl = URL.createObjectURL(blob);
            const filename = getFilenameFromDisposition(response.headers.get('Content-Disposition'));
            if (downloadLinkEl) {
                downloadLinkEl.href = currentDownloadUrl;
                downloadLinkEl.download = filename;
                downloadLinkEl.hidden = false;
                downloadLinkEl.click();
            }
            if (downloadSectionEl) {
                downloadSectionEl.classList.add('is-visible');
            }
        } catch (error) {
            clearDownloadSection();
            moneygramCoverMessage.textContent = String(error.message || error);
            moneygramCoverMessage.classList.add('is-visible');
        } finally {
            exportExcelEl.textContent = 'Export to Excel';
            setExportReady(true);
        }
    }

    form.addEventListener('submit', function(event){
        event.preventDefault();
        loadSummaryReport();
    });

    if (exportExcelEl) {
        exportExcelEl.addEventListener('click', exportCurrentReportToExcel);
    }

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
