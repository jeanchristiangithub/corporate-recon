<?php
require_once __DIR__ . '/../../../config/db.php';

$settlementPartners = [];
try {
    $settlementPdo = masterDataConnection();
    $settlementPartners = $settlementPdo->query(
        "SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND TRIM(partner_name) <> '' ORDER BY partner_name"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $settlementPartners = [];
}

$settlementAgentNames = [];
try {
    $settlementPdo = fileRecDbConnection();
    $settlementAgentNames = $settlementPdo->query(
        "SELECT DISTINCT TRIM(agent_name) FROM partner_settlement_data WHERE agent_name IS NOT NULL AND TRIM(agent_name) <> '' ORDER BY TRIM(agent_name)"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $settlementAgentNames = [];
}
?>
<div class="rps-content">
    <style>
        .rps-content {
            color: #1f2937
        }

        .rps-content * {
            box-sizing: border-box
        }

        .rps-filter {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) 160px 14px 160px 156px 214px 200px 200px max-content;
            gap: .5rem;
            align-items: flex-end;
            padding: .75rem;
            background: #fff;
            border: 1px solid #e6eef6;
            border-radius: 8px
        }

        .rps-field {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            font-size: .75rem;
            color: #6b7280;
            min-width: 0
        }

        .rps-field-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 18px;
            white-space: nowrap
        }

        .rps-field--partner {
            min-width: 0
        }

        .rps-autocomplete {
            position: relative;
            width: 100%
        }

        .rps-field input,
        .rps-field select {
            width: 100%;
            height: 38px;
            padding: 8px 10px;
            border: 1px solid #e6eef6;
            border-radius: 6px;
            background: #fff;
            font: inherit;
            font-size: .9rem;
            color: #111
        }

        .rps-autocomplete input {
            width: 100%
        }

        .rps-suggestions {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1000;
            max-height: 260px;
            overflow-y: auto;
            margin: 0;
            padding: 4px 0;
            list-style: none;
            background: #fff;
            border: 1px solid #e6eef6;
            border-radius: 6px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
            scrollbar-color: #8b8b8b #f1f3f5
        }

        .rps-suggestions[hidden] {
            display: none
        }

        .rps-suggestion {
            padding: 9px 10px;
            color: #111827;
            font-size: .9rem;
            cursor: pointer;
            white-space: normal
        }

        .rps-suggestion:hover,
        .rps-suggestion.is-active {
            background: #f3f4f6
        }

        .rps-actions {
            display: flex;
            gap: .5rem;
            align-items: flex-end;
            white-space: nowrap
        }

        .rps-date-separator {
            align-self: end;
            padding-bottom: 9px;
            text-align: center
        }

        .rps-btn {
            height: 38px;
            padding: 0 16px;
            border: 0;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer
        }

        @media (max-width: 1550px) {
            .rps-filter {
                display: flex;
                flex-wrap: wrap;
                gap: .75rem
            }

            .rps-field--partner {
                flex: 1 1 320px
            }

            .rps-field:not(.rps-field--partner) {
                flex: 0 1 190px
            }
        }

        .rps-btn--view {
            background: #df3345;
            color: #fff
        }

        .rps-btn--export {
            background: #198754;
            color: #fff
        }

        .rps-btn--clear {
            background: #eee;
            color: #222
        }

        .rps-btn:disabled {
            opacity: .6;
            cursor: wait
        }

        .rps-required {
            color: #df3345;
            font-weight: 700
        }

        .rps-results {
            display: none;
            margin-top: 1.5rem
        }

        .rps-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            gap: .75rem
        }

        .rps-summary {
            padding: 1rem;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            background: #fff
        }

        .rps-summary h5 {
            text-align: center;
            color: #df3345;
            text-transform: uppercase
        }

        .rps-summary dt {
            margin-top: 1.25rem;
            color: #df3345;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase
        }

        .rps-summary dd {
            margin: .35rem 0 0;
            font-size: .9rem;
            font-weight: 700
        }

        .rps-summary .php {
            color: #065f46
        }

        .rps-summary .usd {
            color: #1e3a8a
        }

        .rps-main {
            min-width: 0
        }

        .rps-table-wrap {
            height: calc(100vh - 235px);
            min-height: 280px;
            overflow: auto;
            border: 1px solid #e6eef6;
            border-radius: 8px;
            scrollbar-color: #df3345 #f1f3f5
        }

        .rps-table {
            width: 100%;
            min-width: 1500px;
            border-collapse: collapse;
            font-size: .65rem
        }

        .rps-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: .6rem .7rem;
            background: #f9fafb;
            color: #6b7280;
            text-align: left;
            white-space: nowrap
        }

        .rps-table td {
            padding: .7rem;
            border-bottom: 1px solid #f0f0f0;
            white-space: nowrap
        }

        .rps-table td.num,
        .rps-table th.num {
            text-align: right;
            font-family: monospace
        }

        .rps-view {
            padding: 2px 10px;
            border: 1px solid #df3345;
            border-radius: 999px;
            background: #fff;
            color: #df3345;
            font-weight: 700;
            cursor: pointer
        }

        .rps-view:hover {
            background: #df3345;
            color: #fff
        }

        .rps-pagination {
            display: none;
            justify-content: space-between;
            align-items: center;
            margin-top: .8rem;
            color: #6b7280;
            font-size: .85rem
        }

        .rps-modal {
            position: fixed;
            inset: 0;
            z-index: 12000;
            display: none;
            align-items: center;
            justify-content: center
        }

        .rps-modal__shade {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .5)
        }

        .rps-modal__dialog {
            position: relative;
            width: min(1140px, 96vw);
            max-height: 88vh;
            overflow: hidden;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 18px 44px rgba(15, 23, 42, .24)
        }

        .rps-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 18px;
            background: #df3345;
            color: #fff
        }

        .rps-modal__head h4 {
            margin: 0;
            font-size: 1.2rem
        }

        .rps-close {
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 2rem;
            cursor: pointer
        }

        .rps-modal__body {
            padding: 14px 28px 26px;
            overflow: auto
        }

        .rps-detail-title {
            padding-bottom: 7px;
            border-bottom: 1px solid #d8dee7;
            font-weight: 700
        }

        .rps-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 44px;
            align-items: start
        }

        .rps-detail-list {
            display: grid;
            grid-template-columns: 205px minmax(0, 1fr);
            grid-auto-rows: max-content;
            align-self: start;
            align-content: start;
            gap: 6px 18px
        }

        .rps-detail-list dt {
            font-weight: 700;
            white-space: nowrap
        }

        .rps-detail-list dd {
            margin: 0;
            color: #696b70;
            min-width: 0;
            overflow-wrap: anywhere
        }

        .rps-detail-list .rps-detail-spacer {
            min-height: 10px;
        }

        .rps-amounts {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-top: 25px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
            text-align: center
        }

        .rps-amounts strong {
            display: block
        }

        .rps-amounts span {
            display: block;
            margin-top: 5px;
            color: #df3345;
            font-size: 1.35rem;
            font-weight: 700
        }

        @media(max-width:900px) {
            .rps-layout {
                grid-template-columns: 1fr
            }

            .rps-detail-grid,
            .rps-amounts {
                grid-template-columns: 1fr
            }

            .rps-table-wrap {
                height: 55vh
            }
        }
    </style>

    <h3 style="margin:0 0 .5rem;font-size:1.125rem">Partner Settlement Details</h3>
    <form id="rpsForm" class="rps-filter">
        <label class="rps-field rps-field--partner"><span class="rps-field-label">CORPORATE PARTNER <span class="rps-required">*</span></span>
            <span class="rps-autocomplete">
                <input id="rpsPartner" autocomplete="off" placeholder="Search corporate partner" aria-autocomplete="list" aria-controls="rpsPartnerSuggestions" aria-expanded="false">
                <ul id="rpsPartnerSuggestions" class="rps-suggestions" role="listbox" hidden></ul>
            </span>
        </label>
        <label class="rps-field"><span class="rps-field-label">Start date <span class="rps-required">*</span></span><input id="rpsStart" type="date"></label>
        <span class="rps-date-separator" aria-hidden="true">&mdash;</span>
        <label class="rps-field"><span class="rps-field-label">End date <span class="rps-required">*</span></span><input id="rpsEnd" type="date"></label>
        <label class="rps-field"><span class="rps-field-label">CURRENCY <span class="rps-required">*</span></span><select id="rpsCurrency" required>
                <option value="">Select Currency</option>
                <option>PHP</option>
                <option>USD</option>
            </select></label>
        <label class="rps-field"><span class="rps-field-label">TRANSACTION TYPE <span class="rps-required">*</span></span><select id="rpsType" required>
                <option value="">Select Transaction Type</option>
                <option value="REC">PAYOUT</option>
                <option value="RRC">PAYOUT CANCELLED</option>
                <option value="SEN">SENDOUT</option>
                <option value="RSN">SENDOUT CANCELLED</option>
            </select></label>
        <label class="rps-field"><span class="rps-field-label">AGENT NAME</span>
            <span class="rps-autocomplete">
                <input id="rpsAgentName" type="text" autocomplete="off" placeholder="Enter Agent Name" aria-autocomplete="list" aria-controls="rpsAgentSuggestions" aria-expanded="false">
                <ul id="rpsAgentSuggestions" class="rps-suggestions" role="listbox" hidden></ul>
            </span>
        </label>
        <label class="rps-field"><span class="rps-field-label">REFERENCE ID</span>
            <input id="rpsReferenceId" type="text" inputmode="numeric" autocomplete="off" placeholder="Enter Reference ID">
        </label>
        <div class="rps-actions"><button id="rpsView" class="rps-btn rps-btn--view" type="submit">View transactions</button><button id="rpsExport" class="rps-btn rps-btn--export" type="button" hidden>Export to Excel</button><button id="rpsClear" class="rps-btn rps-btn--clear" type="button" hidden>Clear</button></div>
    </form>

    <section id="rpsResults" class="rps-results">
        <div class="rps-layout">
            <aside class="rps-summary">
                <h5>Filter Results</h5>
                <dl>
                    <dt>Corporate Partner:</dt>
                    <dd id="rpsSumPartner">-</dd>
                    <dt>Transaction Date:</dt>
                    <dd id="rpsSumDate">-</dd>
                    <dt>Currency:</dt>
                    <dd id="rpsSumCurrency">ALL</dd>
                    <dt>Transaction Type:</dt>
                    <dd id="rpsSumType">ALL</dd>
                    <dt>Volume:</dt>
                    <dd id="rpsSumVolume">0</dd>
                    <dt>Principal:</dt>
                    <dd id="rpsSumPrincipalPhp" class="php">PHP: 0.00</dd>
                    <dd id="rpsSumPrincipalUsd" class="usd">USD: 0.00</dd>
                    <dt>Commission:</dt>
                    <dd id="rpsSumCommPhp" class="php">PHP: 0.00</dd>
                    <dd id="rpsSumCommUsd" class="usd">USD: 0.00</dd>
                </dl>
            </aside>
            <div class="rps-main">
                <div class="rps-table-wrap">
                    <table class="rps-table">
                        <thead>
                            <tr>
                                <th>Tran Date</th>
                                <th>Agent Name</th>
                                <th>Legacy ID</th>
                                <th>Account Number</th>
                                <th>Reference ID</th>
                                <th>Tran Type</th>
                                <th class="num">Tran Fx Rate</th>
                                <th class="num">Fx Rev Share Amt</th>
                                <th>Settlement Currency</th>
                                <th class="num">Base Amt</th>
                                <th class="num">Comm Amt</th>
                                <th>Orig Cntry</th>
                                <th>Rcv Cntry</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="rpsBody"></tbody>
                    </table>
                </div>
                <div id="rpsPagination" class="rps-pagination"><span id="rpsPageInfo"></span>
                    <div><button id="rpsPrev" class="rps-btn rps-btn--clear" type="button">Previous</button> <button id="rpsNext" class="rps-btn rps-btn--clear" type="button">Next</button></div>
                </div>
            </div>
        </div>
    </section>

    <div id="rpsModal" class="rps-modal" aria-hidden="true">
        <div class="rps-modal__shade" data-rps-close></div>
        <div class="rps-modal__dialog" role="dialog" aria-modal="true" aria-label="Settlement Details">
            <div class="rps-modal__head">
                <h4 id="rpsModalTitle">Partner Transaction Details</h4><button class="rps-close" type="button" data-rps-close aria-label="Close">&times;</button>
            </div>
            <div id="rpsModalBody" class="rps-modal__body"></div>
        </div>
    </div>

    <script>
        (() => {
            const $ = id => document.getElementById(id),
                form = $('rpsForm'),
                start = $('rpsStart'),
                end = $('rpsEnd'),
                body = $('rpsBody'),
                results = $('rpsResults'),
                exportBtn = $('rpsExport'),
                partnerInput = $('rpsPartner'),
                partnerList = $('rpsPartnerSuggestions'),
                agentInput = $('rpsAgentName'),
                agentList = $('rpsAgentSuggestions'),
                referenceInput = $('rpsReferenceId');
            const partners = <?= json_encode(array_values($settlementPartners), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const agentNames = <?= json_encode(array_values($settlementAgentNames), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            let state = null,
                last = null;
            const base = () => window.autoreconBaseUrl !== undefined ? window.autoreconBaseUrl : ('/' + location.pathname.split('/').filter(Boolean)[0]);
            const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [c]));
            const num = v => {
                const n = Number(String(v ?? '').replace(/,/g, ''));
                return Number.isFinite(n) ? Math.abs(n).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) : ''
            };
            const date = v => {
                const m = String(v ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
                return m ? new Date(+m[1], +m[2] - 1, +m[3]).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: '2-digit'
                }) : String(v ?? '')
            };
            const datetime = v => {
                if (!v) return '-';
                const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?/);
                if (!m) return date(v);
                const d = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0));
                return d.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            };
            let activePartner = -1;

            function closePartners() {
                partnerList.hidden = true;
                partnerList.innerHTML = '';
                partnerInput.setAttribute('aria-expanded', 'false');
                activePartner = -1
            }

            function selectPartner(value) {
                partnerInput.value = value;
                closePartners();
                partnerInput.dispatchEvent(new Event('change', {
                    bubbles: true
                }))
            }

            function renderPartners() {
                const query = partnerInput.value.trim().toLowerCase();
                if (!query) {
                    closePartners();
                    return
                }
                const matches = partners.filter(value => String(value).toLowerCase().includes(query)).slice(0, 100);
                partnerList.innerHTML = '';
                if (!matches.length) {
                    closePartners();
                    return
                }
                matches.forEach(value => {
                    const item = document.createElement('li');
                    item.className = 'rps-suggestion';
                    item.role = 'option';
                    item.textContent = value;
                    item.addEventListener('mousedown', event => {
                        event.preventDefault();
                        selectPartner(value)
                    });
                    partnerList.appendChild(item)
                });
                partnerList.hidden = false;
                partnerInput.setAttribute('aria-expanded', 'true');
                activePartner = -1
            }
            partnerInput.addEventListener('input', renderPartners);
            partnerInput.addEventListener('keydown', event => {
                const items = [...partnerList.children];
                if (event.key === 'Escape') {
                    closePartners();
                    return
                }
                if (!items.length) return;
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    activePartner = event.key === 'ArrowDown' ? (activePartner + 1) % items.length : (activePartner <= 0 ? items.length - 1 : activePartner - 1);
                    items.forEach((item, index) => item.classList.toggle('is-active', index === activePartner));
                    items[activePartner].scrollIntoView({
                        block: 'nearest'
                    })
                } else if (event.key === 'Enter' && activePartner >= 0) {
                    event.preventDefault();
                    selectPartner(items[activePartner].textContent)
                }
            });
            document.addEventListener('mousedown', event => {
                if (!partnerInput.closest('.rps-autocomplete').contains(event.target)) closePartners()
            });

            function attachAgentAutocomplete() {
                let active = -1;
                const close = () => {
                    agentList.hidden = true;
                    agentList.innerHTML = '';
                    agentInput.setAttribute('aria-expanded', 'false');
                    active = -1
                };
                const select = value => {
                    agentInput.value = value;
                    close();
                    agentInput.dispatchEvent(new Event('change', { bubbles: true }))
                };
                const render = () => {
                    const query = agentInput.value.trim().toLowerCase();
                    if (!query) return close();
                    const starts = agentNames.filter(value => String(value).toLowerCase().startsWith(query));
                    const contains = agentNames.filter(value => !String(value).toLowerCase().startsWith(query) && String(value).toLowerCase().includes(query));
                    const matches = [...starts, ...contains].slice(0, 100);
                    agentList.innerHTML = '';
                    if (!matches.length) return close();
                    matches.forEach(value => {
                        const item = document.createElement('li');
                        item.className = 'rps-suggestion';
                        item.role = 'option';
                        item.textContent = value;
                        item.addEventListener('mousedown', event => {
                            event.preventDefault();
                            select(value)
                        });
                        agentList.appendChild(item)
                    });
                    agentList.hidden = false;
                    agentInput.setAttribute('aria-expanded', 'true');
                    active = -1
                };
                agentInput.addEventListener('input', render);
                agentInput.addEventListener('focus', render);
                agentInput.addEventListener('keydown', event => {
                    const items = [...agentList.children];
                    if (event.key === 'Escape') return close();
                    if (!items.length) return;
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        active = event.key === 'ArrowDown' ? (active + 1) % items.length : (active <= 0 ? items.length - 1 : active - 1);
                        items.forEach((item, index) => item.classList.toggle('is-active', index === active));
                        items[active].scrollIntoView({ block: 'nearest' })
                    } else if (event.key === 'Enter' && active >= 0) {
                        event.preventDefault();
                        select(items[active].textContent)
                    }
                });
                document.addEventListener('mousedown', event => {
                    if (!agentInput.closest('.rps-autocomplete').contains(event.target)) close()
                })
            }
            attachAgentAutocomplete();
            start.addEventListener('change', () => {
                if (start.value) end.value = start.value
            });

            function notify(msg) {
                window.Swal ? Swal.fire({
                    text: msg,
                    icon: 'warning',
                    confirmButtonColor: '#dc3545',
                    heightAuto: false
                }) : alert(msg)
            }

            function rowHtml(r) {
                return `<tr><td>${esc(date(r.tran_date))}</td><td>${esc(r.agent_name)}</td><td>${esc(r.legacy_id)}</td><td>${esc(r.account_number)}</td><td>${esc(r.reference_id)}</td><td>${esc(r.tran_type)}</td><td class="num">${esc(r.fx_rate_trn)}</td><td class="num">${esc(num(r.fx_rev_share_tran_amt))}</td><td>${esc(r.transaction_currency)}</td><td class="num">${esc(num(r.base_tran_amt))}</td><td class="num">${esc(num(r.comm_tran_amt))}</td><td>${esc(r.orig_cntry)}</td><td>${esc(r.rcv_cntry)}</td><td><button class="rps-view" type="button" data-id="${esc(r.id)}">View</button></td></tr>`
            }

            function updateSummary(d) {
                $('rpsSumPartner').textContent = d.partner || '-';
                const summaryStart = state.start || d.start_date || '';
                const summaryEnd = state.end || d.end_date || summaryStart;
                $('rpsSumDate').textContent = summaryStart ? (summaryStart === summaryEnd ? date(summaryStart) : `${date(summaryStart)} to ${date(summaryEnd)}`) : '-';
                $('rpsSumCurrency').textContent = state.currency || 'ALL';
                $('rpsSumType').textContent = state.type || 'ALL';
                $('rpsSumVolume').textContent = Number(d.count || 0).toLocaleString();
                for (const [id, key, prefix] of [
                        ['rpsSumPrincipalPhp', 'principal_php', 'PHP'],
                        ['rpsSumPrincipalUsd', 'principal_usd', 'USD'],
                        ['rpsSumCommPhp', 'commission_php', 'PHP'],
                        ['rpsSumCommUsd', 'commission_usd', 'USD']
                    ]) {
                    const total = $(id);
                    total.textContent = `${prefix}: ${num(d.totals?.[key] || 0)}`;
                    total.style.display = state.currency && state.currency !== prefix ? 'none' : ''
                }
            }
            async function load(page = 1) {
                $('rpsView').disabled = true;
                $('rpsView').textContent = 'Loading...';
                try {
                    const q = new URLSearchParams({
                        ...state,
                        page: String(page),
                        per_page: '500'
                    });
                    const res = await fetch(`${base()}/src/controllers/excelcontrol/partner-settlement-report.php?${q}`);
                    const d = await res.json();
                    if (!res.ok || !d.success) throw new Error(d.error || 'Unable to load settlement data');
                    last = d;
                    body.innerHTML = d.rows.length ? d.rows.map(rowHtml).join('') : '<tr><td colspan="14" style="padding:1rem;text-align:center;color:#9ca3af">No transactions found</td></tr>';
                    updateSummary(d);
                    results.style.display = 'block';
                    exportBtn.hidden = !d.rows.length;
                    $('rpsClear').hidden = false;
                    const from = d.count ? ((d.page - 1) * d.per_page) + 1 : 0,
                        to = Math.min(d.page * d.per_page, d.count);
                    $('rpsPageInfo').textContent = `Showing ${from.toLocaleString()} to ${to.toLocaleString()} of ${Number(d.count).toLocaleString()} transactions (Page ${d.page} of ${d.total_pages})`;
                    $('rpsPrev').disabled = d.page <= 1;
                    $('rpsNext').disabled = d.page >= d.total_pages;
                    $('rpsPagination').style.display = d.total_pages > 1 ? 'flex' : 'none'
                } catch (e) {
                    notify(e.message)
                } finally {
                    $('rpsView').disabled = false;
                    $('rpsView').textContent = 'View transactions'
                }
            }
            form.addEventListener('submit', e => {
                e.preventDefault();
                const partner = $('rpsPartner').value.trim();
                const referenceId = referenceInput.value.trim();
                const agentName = agentInput.value.trim();
                const currency = $('rpsCurrency').value;
                const transactionType = $('rpsType').value;
                const hasDirectSearch = referenceId !== '' || agentName !== '';
                if (!currency || !transactionType) return notify('Currency and Transaction Type are required.');
                if (!hasDirectSearch && (!partner || !start.value || !end.value)) return notify('Corporate Partner, Start date, and End date are required.');
                if (!hasDirectSearch && ((start.value && !end.value) || (!start.value && end.value))) return notify('Please provide both Start date and End date.');
                if (start.value && end.value && start.value > end.value) return notify('Start date cannot be later than End date.');
                state = {
                    partner,
                    start_date: start.value,
                    end_date: end.value,
                    currency,
                    type: transactionType,
                    agent_name: agentName,
                    reference_id: referenceId,
                    start: start.value,
                    end: end.value
                };
                load()
            });

            function clearResults() {
                results.style.display = 'none';
                body.innerHTML = '';
                last = state = null;
                exportBtn.hidden = true;
                $('rpsClear').hidden = true
            }

            $('rpsClear').addEventListener('click', () => {
                form.reset();
                clearResults()
            });
            $('rpsPrev').onclick = () => last && load(last.page - 1);
            $('rpsNext').onclick = () => last && load(last.page + 1);

            function detailHtml(d) {
                const val = k => d[k] === null || d[k] === undefined || String(d[k]).trim() === '' ? '-' : d[k],
                    pair = (label, value) => `<dt>${esc(label)}:</dt><dd>${esc(value)}</dd>`,
                    uploadedDate = val('updated_at') !== '-' ? val('updated_at') : val('created_at'),
                    uploaderId = val('updated_by') !== '-' ? val('updated_by') : val('created_by'),
                    uploadedBy = val('uploaded_by_name') !== '-' ? val('uploaded_by_name') : uploaderId,
                    currency = String(val('transaction_currency')).toUpperCase(),
                    sign = currency === 'PHP' ? '₱' : currency === 'USD' ? '$' : '';
                const amount = (label, key) => `<div><strong>${esc(label)}</strong><span>${sign?sign+' ':''}${esc(num(val(key)))}</span></div>`;
                const spacer = '<dt class="rps-detail-spacer" aria-hidden="true"></dt><dd class="rps-detail-spacer" aria-hidden="true"></dd>';
                const transactionLeft = pair('Transaction Date', date(val('tran_date'))) + pair('Transaction ID', val('transaction_id')) + pair('Reference ID', val('reference_id')) + pair('Product Type', val('product')) + pair('Transaction Type', val('tran_type')) + spacer;
                const transactionRight = pair('Account Number', val('account_number')) + pair('Agent Name', val('agent_name')) + pair('Legacy ID', val('legacy_id')) + pair('Original Country', val('orig_cntry')) + pair('Receiver Country', val('rcv_cntry')) + pair('Settlement Currency', val('settlement_currency')) + pair('Transaction Currency', val('transaction_currency')) + spacer + pair('Uploaded By', uploadedBy) + pair('Uploaded Date', datetime(uploadedDate));
                const forex = pair('Forex Date', date(val('fx_date_trn'))) + pair('Transaction Forex Rate', val('fx_rate_trn')) + pair('Margin', val('margin')) + pair('Fee Amount', num(val('fee_tran_amt')));
                return `<div class="rps-detail-title">ⓘ Transaction Information</div><div class="rps-detail-grid"><dl class="rps-detail-list">${transactionLeft}${forex}</dl><dl class="rps-detail-list">${transactionRight}</dl></div><div class="rps-amounts">${amount('Principal Amount','base_tran_amt')}${amount('Forex Revenue Share Amount','fx_rev_share_tran_amt')}${amount('Commission Amount','comm_tran_amt')}${amount('Total Transaction Amount','total_tran_amt')}</div>`
            }

            function updateDetailTitle(transactionType = '') {
                const labels = { REC: 'PAYOUT', RRC: 'PAYOUT CANCELLED', SEN: 'SENDOUT', RSN: 'SENDOUT CANCELLED', REF: 'SENDOUT CANCELLED' };
                const label = labels[String(transactionType).trim().toUpperCase()];
                const titleText = `Partner Transaction Details${label ? ` - (${label})` : ''}`;
                $('rpsModalTitle').textContent = titleText;
                const dialog = $('rpsModal').querySelector('.rps-modal__dialog');
                if (dialog) dialog.setAttribute('aria-label', titleText)
            }
            body.addEventListener('click', async e => {
                const b = e.target.closest('.rps-view');
                if (!b) return;
                const modal = $('rpsModal');
                $('rpsModalBody').textContent = 'Loading...';
                updateDetailTitle();
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
                try {
                    const res = await fetch(`${base()}/src/controllers/recon/partner-settlement-details.php?id=${encodeURIComponent(b.dataset.id)}`),
                        j = await res.json();
                    if (!res.ok || !j.success) throw new Error(j.error || 'Details not found');
                    updateDetailTitle(j.data.tran_type);
                    $('rpsModalBody').innerHTML = detailHtml(j.data)
                } catch (err) {
                    $('rpsModalBody').textContent = err.message
                }
            });
            document.querySelectorAll('[data-rps-close]').forEach(x => x.onclick = () => {
                $('rpsModal').style.display = 'none';
                $('rpsModal').setAttribute('aria-hidden', 'true')
            });
            exportBtn.onclick = () => {
                if (!state) return;
                const q = new URLSearchParams({
                    ...state,
                    export: '1'
                });
                location.href = `${base()}/src/controllers/excelcontrol/partner-settlement-report.php?${q}`
            };
        })();
    </script>
</div>
