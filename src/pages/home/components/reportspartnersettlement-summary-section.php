<?php
require_once __DIR__ . '/../../../config/db.php';

$settlementSummaryPartners = [];
try {
    $settlementSummaryPdo = masterDataConnection();
    $settlementSummaryPartners = $settlementSummaryPdo->query(
        "SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND TRIM(partner_name) <> '' ORDER BY partner_name"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $settlementSummaryPartners = [];
}
?>
<div class="partner-settlement-summary">
    <style>
        .partner-settlement-summary { color: #1f2937; }
        .partner-settlement-summary h3 { margin: 0 0 .75rem; font-size: 1.125rem; font-weight: 700; }
        .partner-settlement-summary .settlement-filter-card {
            display: grid; grid-template-columns: minmax(320px, 465px) 148px auto; align-items: end;
            justify-content: start; gap: .75rem; margin-bottom: 1rem; padding: .75rem;
            background: #fff; border: 1px solid #e6eef6; border-radius: 8px;
        }
        .partner-settlement-summary .settlement-field {
            display: flex; flex-direction: column; gap: .25rem; color: #6b7280;
            font-size: .75rem; font-weight: 700;
        }
        .partner-settlement-summary .settlement-currency-tabs { display: flex; height: 38px; }
        .partner-settlement-summary .settlement-currency-tab {
            min-width: 70px; padding: 0 .9rem; color: #374151; background: #fff;
            border: 1px solid #dbe5f1; font-size: .86rem; font-weight: 700; cursor: pointer;
        }
        .partner-settlement-summary .settlement-currency-tab:first-child { border-radius: 6px 0 0 6px; }
        .partner-settlement-summary .settlement-currency-tab:last-child {
            margin-left: -1px; border-radius: 0 6px 6px 0;
        }
        .partner-settlement-summary .settlement-currency-tab.is-active {
            position: relative; color: #fff; background: #dc3545; border-color: #dc3545;
        }
        .partner-settlement-summary .settlement-autocomplete { position: relative; min-width: 0; }
        .partner-settlement-summary .settlement-autocomplete .settlement-input { width: 100%; }
        .partner-settlement-summary .settlement-suggestions {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 100;
            max-height: 260px; overflow-y: auto; margin: 0; padding: 4px 0;
            list-style: none; background: #fff; border: 1px solid #e6eef6;
            border-radius: 6px; box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
            scrollbar-color: #8b8b8b #f1f3f5;
        }
        .partner-settlement-summary .settlement-suggestions[hidden] { display: none; }
        .partner-settlement-summary .settlement-suggestion {
            padding: 9px 10px; color: #111827; font-size: .9rem; font-weight: 400;
            cursor: pointer; white-space: normal;
        }
        .partner-settlement-summary .settlement-suggestion:hover,
        .partner-settlement-summary .settlement-suggestion.is-active { background: #f3f4f6; }
        .partner-settlement-summary .settlement-input,
        .partner-settlement-summary .settlement-button {
            height: 38px; box-sizing: border-box; border-radius: 6px; font-size: .92rem;
        }
        .partner-settlement-summary .settlement-input {
            min-width: 18ch; padding: 0 .65rem; color: #111827; background: #fff;
            border: 1px solid #e6eef6;
        }
        .partner-settlement-summary .settlement-button {
            padding: 0 1rem; color: #fff; background: #dc3545; border: 1px solid #dc3545;
            font-weight: 700; cursor: pointer;
        }
        .partner-settlement-summary .settlement-button:disabled { opacity: .65; cursor: wait; }
        .partner-settlement-summary .settlement-message {
            display: none; margin-bottom: .75rem; padding: .75rem 1rem; color: #6b7280;
            background: #fff; border: 1px solid #e6eef6; border-radius: 8px; font-size: .9rem;
        }
        .partner-settlement-summary .settlement-message.is-visible { display: block; }
        .partner-settlement-summary .settlement-result {
            display: none; overflow: hidden; background: #fff;
            border: 1px solid #e6eef6; border-radius: 8px;
        }
        .partner-settlement-summary .settlement-result.is-visible { display: block; }
        .partner-settlement-summary .settlement-result-heading {
            position: relative; min-height: 72px; padding: .75rem 1rem; box-sizing: border-box;
            text-align: center; border-bottom: 1px solid #e6eef6;
        }
        .partner-settlement-summary .settlement-result-title { font-size: .95rem; font-weight: 800; }
        .partner-settlement-summary .settlement-result-heading .settlement-field {
            position: absolute; top: .5rem; left: 1rem; text-align: left;
        }
        .partner-settlement-summary .settlement-export-button {
            position: absolute; top: 1rem; right: 1rem; height: 38px; padding: 0 1rem;
            color: #fff; background: #198754; border: 1px solid #198754;
            border-radius: 6px; font-weight: 700; cursor: pointer;
        }
        .partner-settlement-summary .settlement-table-wrap {
            max-height: 68vh; overflow: auto; scrollbar-color: #dc3545 #f3f4f6;
        }
        .partner-settlement-summary table {
            width: 100%; min-width: 1180px; border-collapse: collapse; font-size: .72rem;
        }
        .partner-settlement-summary th,
        .partner-settlement-summary td {
            padding: .38rem .45rem; color: #111827; background: #fff;
            border: 1px solid #dbe5f1; text-align: right; white-space: nowrap;
        }
        .partner-settlement-summary th { position: sticky; z-index: 3; text-align: center; font-weight: 700; }
        .partner-settlement-summary tbody td:nth-child(1),
        .partner-settlement-summary tbody td:nth-child(2),
        .partner-settlement-summary tbody td:nth-child(7),
        .partner-settlement-summary tbody td:nth-child(12) { text-align: center; }
        .partner-settlement-summary thead tr:first-child th { top: 0; }
        .partner-settlement-summary thead tr:nth-child(2) th { top: 30px; }
        .partner-settlement-summary tbody tr:hover td { background: #f8fafc; }
        .partner-settlement-summary .settlement-total td,
        .partner-settlement-summary .settlement-amount-due td { height: 30px; font-weight: 800; }
        .partner-settlement-summary .settlement-total td { position: sticky; bottom: 30px; z-index: 4; }
        .partner-settlement-summary .settlement-amount-due td { position: sticky; bottom: 0; z-index: 5; }
        .partner-settlement-summary .settlement-amount-due td:first-child,
        .partner-settlement-summary .settlement-amount-due td:nth-child(2) { text-align: right; }
        @media (max-width: 560px) {
            .partner-settlement-summary .settlement-filter-card { grid-template-columns: 1fr; }
            .partner-settlement-summary .settlement-input,
            .partner-settlement-summary .settlement-button { width: 100%; }
            .partner-settlement-summary .settlement-result-heading { padding-top: 4.75rem; min-height: 112px; }
            .partner-settlement-summary .settlement-export-button { top: .5rem; }
        }
    </style>

    <h3>Partner Settlement Summary</h3>
    <form id="partnerSettlementSummaryForm" class="settlement-filter-card">
        <label class="settlement-field"><span>Corporate Partner</span>
            <span class="settlement-autocomplete">
                <input id="partnerSettlementPartner" class="settlement-input" type="text"
                    placeholder="Select or Type here..." autocomplete="off" required
                    aria-autocomplete="list" aria-controls="partnerSettlementPartnerSuggestions" aria-expanded="false">
                <ul id="partnerSettlementPartnerSuggestions" class="settlement-suggestions" role="listbox" hidden></ul>
            </span>
        </label>
        <label class="settlement-field"><span>Month</span>
            <input id="partnerSettlementMonth" class="settlement-input" type="month" required>
        </label>
        <button id="partnerSettlementSubmit" class="settlement-button" type="submit">Generate</button>
    </form>

    <div id="partnerSettlementMessage" class="settlement-message" role="status"></div>
    <div id="partnerSettlementResult" class="settlement-result">
        <div class="settlement-result-heading">
            <div class="settlement-field">
                <div id="partnerSettlementCurrencyTabs" class="settlement-currency-tabs" role="tablist" aria-label="Settlement currency">
                    <button class="settlement-currency-tab is-active" type="button" data-currency="PHP" role="tab" aria-selected="true">PHP</button>
                    <button class="settlement-currency-tab" type="button" data-currency="USD" role="tab" aria-selected="false">USD</button>
                </div>
            </div>
            <div id="partnerSettlementResultTitle" class="settlement-result-title">Settlement</div>
            <button id="partnerSettlementExport" class="settlement-export-button" type="button">Export to Excel</button>
        </div>
        <div class="settlement-table-wrap">
            <table aria-label="MoneyGram settlement summary">
                <thead>
                    <tr>
                        <th rowspan="2">DATE</th><th colspan="5">PAYOUT</th>
                        <th colspan="5">SENDOUT</th><th colspan="2">SETTLEMENT</th>
                    </tr>
                    <tr>
                        <th>VOLUME</th><th>PRINCIPAL</th><th>FEE</th><th>FX REV SHARE</th><th>COMM</th>
                        <th>VOLUME</th><th>PRINCIPAL</th><th>FEE</th><th>FX REV SHARE</th><th>COMM</th>
                        <th>VOLUME</th><th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="partnerSettlementTableBody"></tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    const form = document.getElementById('partnerSettlementSummaryForm');
    const partnerEl = document.getElementById('partnerSettlementPartner');
    const partnerListEl = document.getElementById('partnerSettlementPartnerSuggestions');
    const monthEl = document.getElementById('partnerSettlementMonth');
    const currencyTabsEl = document.getElementById('partnerSettlementCurrencyTabs');
    const currencyTabEls = currencyTabsEl ? Array.from(currencyTabsEl.querySelectorAll('.settlement-currency-tab')) : [];
    const submitEl = document.getElementById('partnerSettlementSubmit');
    const messageEl = document.getElementById('partnerSettlementMessage');
    const resultEl = document.getElementById('partnerSettlementResult');
    const resultTitleEl = document.getElementById('partnerSettlementResultTitle');
    const bodyEl = document.getElementById('partnerSettlementTableBody');
    const exportEl = document.getElementById('partnerSettlementExport');
    if (!form || !partnerEl || !partnerListEl || !monthEl || !currencyTabsEl || !bodyEl) return;

    const partners = <?= json_encode(array_values($settlementSummaryPartners), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let activePartnerIndex = -1;
    let selectedCurrency = 'PHP';
    const reportCache = new Map();
    const excelExportCache = new Map();

    const now = new Date();
    monthEl.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function closePartnerSuggestions() {
        partnerListEl.hidden = true;
        partnerListEl.innerHTML = '';
        partnerEl.setAttribute('aria-expanded', 'false');
        activePartnerIndex = -1;
    }
    function selectPartner(value) {
        partnerEl.value = value;
        closePartnerSuggestions();
    }
    function renderPartnerSuggestions() {
        const query = partnerEl.value.trim().toLowerCase();
        const matches = partners.filter(function (partner) {
            return !query || String(partner).toLowerCase().includes(query);
        });
        partnerListEl.innerHTML = '';
        matches.forEach(function (partner) {
            const item = document.createElement('li');
            item.className = 'settlement-suggestion';
            item.setAttribute('role', 'option');
            item.textContent = partner;
            item.addEventListener('mousedown', function (event) {
                event.preventDefault();
                selectPartner(partner);
            });
            partnerListEl.appendChild(item);
        });
        activePartnerIndex = -1;
        partnerListEl.hidden = matches.length === 0;
        partnerEl.setAttribute('aria-expanded', matches.length ? 'true' : 'false');
    }
    partnerEl.addEventListener('focus', renderPartnerSuggestions);
    partnerEl.addEventListener('input', renderPartnerSuggestions);
    partnerEl.addEventListener('keydown', function (event) {
        const items = Array.from(partnerListEl.querySelectorAll('.settlement-suggestion'));
        if (event.key === 'Escape') return closePartnerSuggestions();
        if (!items.length || partnerListEl.hidden) return;
        if (event.key === 'ArrowDown') activePartnerIndex = Math.min(activePartnerIndex + 1, items.length - 1);
        else if (event.key === 'ArrowUp') activePartnerIndex = Math.max(activePartnerIndex - 1, 0);
        else if (event.key === 'Enter' && activePartnerIndex >= 0) {
            event.preventDefault();
            selectPartner(items[activePartnerIndex].textContent);
            return;
        } else return;
        event.preventDefault();
        items.forEach(function (item, index) { item.classList.toggle('is-active', index === activePartnerIndex); });
        items[activePartnerIndex].scrollIntoView({ block: 'nearest' });
    });
    document.addEventListener('mousedown', function (event) {
        if (!partnerEl.parentElement.contains(event.target)) closePartnerSuggestions();
    });
    function money(value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function count(value) { return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); }
    function dateLabel(value) {
        if (!value) return '';
        const parts = String(value).split('-');
        if (parts.length !== 3) return String(value);
        const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return date.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
    }
    function cell(value) { return `<td>${escapeHtml(value)}</td>`; }
    function group(row, key) { return row && row[key] ? row[key] : {}; }
    function monthRange(month) {
        if (!/^\d{4}-\d{2}$/.test(month)) return null;
        const parts = month.split('-').map(Number);
        const lastDay = new Date(parts[0], parts[1], 0).getDate();
        return { start: `${month}-01`, end: `${month}-${String(lastDay).padStart(2, '0')}` };
    }
    function preloadOtherCurrency(partner, range, month, currency) {
        const otherCurrency = currency === 'PHP' ? 'USD' : 'PHP';
        const cacheKey = `${partner.toUpperCase()}|${month}|${otherCurrency}`;
        if (reportCache.has(cacheKey)) return;
        const params = new URLSearchParams({ partner: partner, start_date: range.start,
            end_date: range.end, report_scope: 'settlement', currency: otherCurrency });
        fetch(`../../controllers/excelcontrol/summary-report.php?${params.toString()}`, {
            headers: { Accept: 'application/json' }, credentials: 'same-origin'
        }).then(function (response) { return response.json(); }).then(function (data) {
            const report = data && data.success && data.settlement_reports
                ? data.settlement_reports[otherCurrency.toLowerCase()]
                : null;
            if (report) reportCache.set(cacheKey, report);
        }).catch(function () {});
    }
    function prepareExcelExport(month) {
        if (excelExportCache.has(month)) return excelExportCache.get(month);
        const request = (async function () {
            const params = new URLSearchParams({ month: month, settlement_only: '1' });
            const response = await fetch(`../../modals/generate/summary-report/excel/moneygram-cover/moneygram-excel-format.php?${params.toString()}`, {
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Unable to generate the Excel file.');
            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^";]+)"?/i);
            return {
                url: URL.createObjectURL(blob),
                filename: match && match[1] ? match[1] : `MONEYGRAM_SETTLEMENT_SUMMARY_REPORT_${month.replace('-', '_')}.xlsx`
            };
        })().catch(function (error) {
            excelExportCache.delete(month);
            throw error;
        });
        excelExportCache.set(month, request);
        return request;
    }

    function render(report, partner, currency) {
        const rows = Array.isArray(report.rows) ? report.rows : [];
        const totals = report.totals || {};
        const payoutTotal = totals.payout || {};
        const sendoutTotal = totals.sendout || {};
        const settlementVolume = Number(totals.settlement_volume || 0);
        const settlementAmount = Number(totals.settlement_amount || 0);
        const details = rows.map(function (row) {
            const payout = group(row, 'payout');
            const sendout = group(row, 'sendout');
            return '<tr>' + cell(dateLabel(row.date))
                + cell(count(payout.volume)) + cell(money(payout.principal)) + cell(money(payout.fee)) + cell(money(payout.fx)) + cell(money(payout.commission))
                + cell(count(sendout.volume)) + cell(money(sendout.principal)) + cell(money(sendout.fee)) + cell(money(sendout.fx)) + cell(money(sendout.commission))
                + cell(count(row.settlement_volume)) + cell(money(row.settlement_amount)) + '</tr>';
        }).join('');

        bodyEl.innerHTML = details + '<tr class="settlement-total">' + cell('GRAND TOTAL:')
            + cell(count(payoutTotal.volume)) + cell(money(payoutTotal.principal)) + cell(money(payoutTotal.fee)) + cell(money(payoutTotal.fx)) + cell(money(payoutTotal.commission))
            + cell(count(sendoutTotal.volume)) + cell(money(sendoutTotal.principal)) + cell(money(sendoutTotal.fee)) + cell(money(sendoutTotal.fx)) + cell(money(sendoutTotal.commission))
            + cell(count(settlementVolume)) + cell(money(settlementAmount)) + '</tr>'
            + `<tr class="settlement-amount-due"><td colspan="12">AMOUNT DUE:</td>${cell(money(settlementAmount))}</tr>`;
        resultTitleEl.textContent = `${partner} Settlement`;
        resultEl.classList.add('is-visible');
        prepareExcelExport(monthEl.value).catch(function () {});
    }
    async function exportExcel() {
        exportEl.disabled = true;
        try {
            const file = await prepareExcelExport(monthEl.value);
            const link = document.createElement('a');
            link.href = file.url;
            link.download = file.filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (error) {
            messageEl.textContent = String(error.message || error);
            messageEl.classList.add('is-visible');
        } finally {
            exportEl.disabled = false;
        }
    }

    async function loadSettlementSummary() {
        const range = monthRange(monthEl.value);
        if (!range) return;
        const partner = partnerEl.value.trim();
        if (!partner) {
            messageEl.textContent = 'Please select a Corporate Partner.';
            messageEl.classList.add('is-visible');
            return;
        }
        const currency = selectedCurrency;
        const cacheKey = `${partner.toUpperCase()}|${monthEl.value}|${currency}`;
        if (reportCache.has(cacheKey)) {
            render(reportCache.get(cacheKey), partner, currency);
            messageEl.classList.remove('is-visible');
            messageEl.textContent = '';
            return;
        }
        submitEl.disabled = true;
        submitEl.textContent = 'Loading...';
        messageEl.textContent = `Loading ${partner} settlement summary...`;
        messageEl.classList.add('is-visible');

        try {
            const params = new URLSearchParams({ partner: partner, start_date: range.start,
                end_date: range.end, report_scope: 'settlement', currency: currency });
            const response = await fetch(`../../controllers/excelcontrol/summary-report.php?${params.toString()}`, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin'
            });
            const data = await response.json();
            if (!response.ok || !data || !data.success) {
                throw new Error(data && data.error ? data.error : 'Unable to load settlement summary.');
            }
            const reports = data.settlement_reports || {};
            if (!reports[currency.toLowerCase()]) {
                throw new Error(`Settlement summary format is not available for ${partner}.`);
            }
            reportCache.set(cacheKey, reports[currency.toLowerCase()]);
            render(reportCache.get(cacheKey), partner, currency);
            preloadOtherCurrency(partner, range, monthEl.value, currency);
            messageEl.classList.remove('is-visible');
            messageEl.textContent = '';
        } catch (error) {
            messageEl.textContent = String(error.message || error);
            messageEl.classList.add('is-visible');
        } finally {
            submitEl.disabled = false;
            submitEl.textContent = 'Generate';
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadSettlementSummary();
    });

    currencyTabEls.forEach(function (tab) {
        tab.addEventListener('click', function () {
            selectedCurrency = tab.dataset.currency === 'USD' ? 'USD' : 'PHP';
            currencyTabEls.forEach(function (item) {
                const active = item === tab;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            if (resultEl.classList.contains('is-visible')) loadSettlementSummary();
        });
    });
    exportEl.addEventListener('click', exportExcel);
})();
</script>
