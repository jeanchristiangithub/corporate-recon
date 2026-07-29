<?php
$originDataLogPartners = [];

try {
    require_once __DIR__ . '/../../../../../config/db.php';
    $originDataLogPartnerQuery = masterDataConnection()->query(
        "SELECT DISTINCT partner_name
         FROM corpo_partner_masterfile
         WHERE partner_name IS NOT NULL AND partner_name <> ''
         ORDER BY partner_name"
    );
    $originDataLogPartners = array_values(array_filter(array_map(
        static fn ($partnerName): string => trim((string)$partnerName),
        $originDataLogPartnerQuery->fetchAll(PDO::FETCH_COLUMN)
    )));
} catch (Throwable $exception) {
    $originDataLogPartners = [];
}
?>
<section id="originDataLogsPartnerSection" aria-label="Origin Data Logs Partner" style="display:none; padding:1rem">
    <h2>Origin Data Logs - Partner</h2>

    <fieldset class="origin-data-logs-partner-type">
        <legend class="sr-only">Partner data log type</legend>

        <label class="origin-data-logs-partner-option is-unavailable">
            <input
                type="radio"
                name="originDataLogsPartnerType"
                value="transactional"
                disabled
                aria-describedby="originDataLogsTransactionalAvailability"
            >
            <span>Transactional</span>
            <span id="originDataLogsTransactionalAvailability" class="sr-only">Not available</span>
        </label>

        <label class="origin-data-logs-partner-option">
            <input
                type="radio"
                name="originDataLogsPartnerType"
                value="settlement"
                checked
            >
            <span>Settlement</span>
        </label>
    </fieldset>

    <form id="originDataLogsSettlementFilters" class="origin-data-logs-filter-card" novalidate>
        <label class="origin-data-logs-filter-field origin-data-logs-filter-field--partner">
            <span>Corporate Partner</span>
            <div class="origin-data-logs-autocomplete">
                <input
                    id="originDataLogsSettlementPartner"
                    type="text"
                    placeholder="Select corporate partner"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="originDataLogsSettlementPartnerSuggestions"
                    aria-expanded="false"
                    required
                >
                <ul id="originDataLogsSettlementPartnerSuggestions" role="listbox" hidden></ul>
            </div>
        </label>

        <label class="origin-data-logs-filter-field origin-data-logs-filter-field--month">
            <span>Month</span>
            <input id="originDataLogsSettlementMonth" type="month" required>
        </label>

        <button class="origin-data-logs-generate" type="submit">Generate</button>
    </form>

    <div id="originDataLogsSettlementTableCard" class="origin-data-logs-table-card">
        <div class="origin-data-logs-table-wrap">
            <table class="origin-data-logs-table">
                <thead>
                    <tr>
                        <th scope="col">Account Number</th>
                        <th scope="col">Agent Name</th>
                        <th scope="col">Legacy ID</th>
                        <th scope="col">Tran Date</th>
                        <th scope="col">Transaction ID</th>
                        <th scope="col">Reference ID</th>
                        <th scope="col">Product</th>
                        <th scope="col">Tran Type</th>
                        <th scope="col">Orig Cntry</th>
                        <th scope="col">Rcv Cntry</th>
                        <th scope="col">FX Rate trn</th>
                        <th scope="col">FX Date trn</th>
                        <th scope="col">Margin</th>
                        <th scope="col">Base Tran Amt</th>
                        <th scope="col">Fee Tran Amt</th>
                        <th scope="col">Fx Rev Share Tran Amt</th>
                        <th scope="col">Comm Tran Amt</th>
                        <th scope="col">Total Tran Amt</th>
                        <th scope="col">Settlement Currency</th>
                        <th scope="col">Transaction Currency</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="originDataLogsSettlementTableBody">
                    <tr class="origin-data-logs-empty-row">
                        <td colspan="21">Select a corporate partner and month, then click Generate.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div
        id="originDataLogsSettlementViewModal"
        class="origin-data-logs-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="originDataLogsSettlementViewTitle"
        hidden
    >
        <div class="origin-data-logs-modal__dialog">
            <header class="origin-data-logs-modal__header">
                <div>
                    <h3 id="originDataLogsSettlementViewTitle">Original Settlement Details</h3>
                </div>
                <button
                    id="originDataLogsSettlementViewClose"
                    class="origin-data-logs-modal__close"
                    type="button"
                    aria-label="Close settlement details"
                >
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </header>
            <div class="origin-data-logs-modal__body">
                <table class="origin-data-logs-modal__comparison">
                    <thead>
                        <tr>
                            <th scope="col">FIELD</th>
                            <th scope="col">ORIGIN</th>
                            <th scope="col">MODIFIED</th>
                        </tr>
                    </thead>
                    <tbody id="originDataLogsSettlementViewDetails"></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<style>
    #originDataLogsPartnerSection .origin-data-logs-partner-type {
        display: flex;
        gap: 0.5rem;
        margin: 1rem 0 0;
        padding: 0;
        border: 0;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-card {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
        margin-top: 1rem;
        padding: 1rem;
        border: 1px solid #dbe4ee;
        border-radius: 0.5rem;
        background: #fff;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        color: #374151;
        font-size: 0.8125rem;
        font-weight: 700;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field--partner {
        width: min(100%, 35rem);
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field--month {
        width: 11rem;
    }

    #originDataLogsPartnerSection .origin-data-logs-autocomplete {
        position: relative;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field input {
        width: 100%;
        height: 2.5rem;
        box-sizing: border-box;
        padding: 0.5rem 0.75rem;
        border: 1px solid #dbe4ee;
        border-radius: 0.5rem;
        background: #fff;
        color: #1f2937;
        font: inherit;
        font-weight: 400;
        outline: none;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field input:focus {
        border-color: #f43f5e;
        box-shadow: 0 0 0 3px #ffe4e6;
    }

    #originDataLogsPartnerSection #originDataLogsSettlementPartnerSuggestions {
        position: absolute;
        z-index: 20;
        top: calc(100% + 0.25rem);
        right: 0;
        left: 0;
        max-height: 16rem;
        margin: 0;
        padding: 0.375rem 0;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        background: #fff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
        list-style: none;
    }

    #originDataLogsPartnerSection #originDataLogsSettlementPartnerSuggestions li {
        padding: 0.625rem 0.75rem;
        color: #0f2747;
        font-size: 0.875rem;
        font-weight: 400;
        cursor: pointer;
    }

    #originDataLogsPartnerSection #originDataLogsSettlementPartnerSuggestions li:hover,
    #originDataLogsPartnerSection #originDataLogsSettlementPartnerSuggestions li.is-active {
        background: #f3f6fa;
    }

    #originDataLogsPartnerSection .origin-data-logs-generate {
        min-height: 2.5rem;
        padding: 0.5rem 1rem;
        border: 0;
        border-radius: 0.5rem;
        background: #e52f47;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }

    #originDataLogsPartnerSection .origin-data-logs-generate:hover {
        background: #c9233a;
    }

    #originDataLogsPartnerSection .origin-data-logs-generate:disabled {
        cursor: wait;
        opacity: 0.7;
    }

    #originDataLogsPartnerSection .origin-data-logs-table-card {
        margin-top: 1rem;
        overflow: hidden;
        border: 1px solid #dbe4ee;
        border-radius: 0.625rem;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }

    #originDataLogsPartnerSection .origin-data-logs-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    #originDataLogsPartnerSection .origin-data-logs-table {
        width: max-content;
        min-width: 100%;
        border-collapse: collapse;
        color: #0f2747;
        font-size: 0.8125rem;
    }

    #originDataLogsPartnerSection .origin-data-logs-table th,
    #originDataLogsPartnerSection .origin-data-logs-table td {
        min-width: 7.25rem;
        height: 3.5rem;
        box-sizing: border-box;
        padding: 0.625rem 0.75rem;
        border-right: 1px solid #e5ebf2;
        border-bottom: 1px solid #e5ebf2;
        text-align: left;
        white-space: nowrap;
    }

    #originDataLogsPartnerSection .origin-data-logs-table th {
        height: 2.625rem;
        background: #f8fafc;
        color: #071a34;
        font-weight: 700;
    }

    #originDataLogsPartnerSection .origin-data-logs-table th:last-child,
    #originDataLogsPartnerSection .origin-data-logs-table td:last-child {
        position: sticky;
        right: 0;
        min-width: 7rem;
        border-right: 0;
        background: #fff;
    }

    #originDataLogsPartnerSection .origin-data-logs-table th:last-child {
        background: #f8fafc;
    }

    #originDataLogsPartnerSection .origin-data-logs-table tr:last-child td {
        border-bottom: 0;
    }

    #originDataLogsPartnerSection .origin-data-logs-table .origin-data-logs-empty-row td {
        height: 5rem;
        color: #64748b;
        text-align: center;
    }

    #originDataLogsPartnerSection .origin-data-logs-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        border: 1px solid #f43f5e;
        border-radius: 0.45rem;
        background: #fff;
        color: #e11d48;
        cursor: pointer;
    }

    #originDataLogsPartnerSection .origin-data-logs-view:hover {
        background: #fff1f2;
    }

    #originDataLogsPartnerSection .origin-data-logs-view .material-icons {
        font-size: 1.25rem;
    }

    .origin-data-logs-modal[hidden] {
        display: none;
    }

    .origin-data-logs-modal {
        position: fixed;
        z-index: 100000;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.55);
    }

    .origin-data-logs-modal__dialog {
        display: flex;
        flex-direction: column;
        width: min(58rem, 100%);
        height: auto;
        max-height: calc(100dvh - 2rem);
        box-sizing: border-box;
        overflow: hidden;
        border-radius: 0.75rem;
        background: #fff;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
    }

    .origin-data-logs-modal__header {
        position: relative;
        z-index: 2;
        flex: 0 0 auto;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #c9233a;
        background: #e52f47;
    }

    .origin-data-logs-modal__header h3 {
        margin: 0;
    }

    .origin-data-logs-modal__header h3 {
        color: #fff;
        font-size: 1.125rem;
    }

    .origin-data-logs-modal__close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: #fff;
        cursor: pointer;
    }

    .origin-data-logs-modal__close:hover {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }

    .origin-data-logs-modal__body {
        flex: 1 1 auto;
        min-height: 0;
        padding: 1.25rem;
        overflow-y: auto;
    }

    .origin-data-logs-modal__comparison {
        width: 100%;
        border-collapse: collapse;
        color: #172033;
        font-size: 0.875rem;
    }

    .origin-data-logs-modal__comparison th,
    .origin-data-logs-modal__comparison td {
        padding: 0.55rem 0.75rem;
        border: 1px solid #cbd8e6;
        text-align: left;
        vertical-align: top;
        overflow-wrap: anywhere;
    }

    .origin-data-logs-modal__comparison thead th {
        background: #f1f5f9;
        color: #071a34;
        font-weight: 700;
        text-align: center;
    }

    .origin-data-logs-modal__comparison thead th:first-child,
    .origin-data-logs-modal__comparison tbody th {
        width: 32%;
    }

    .origin-data-logs-modal__comparison tbody th {
        background: #f8fafc;
        font-weight: 700;
    }

    .origin-data-logs-modal__comparison td {
        width: 34%;
    }

    .origin-data-logs-modal__comparison td.is-changed {
        background: #fff7ed;
        color: #9a3412;
        font-weight: 600;
    }

    #originDataLogsPartnerSection .origin-data-logs-filter-field input.is-invalid {
        border-color: #f43f5e;
        box-shadow: 0 0 0 3px #ffe4e6;
    }

    @media (max-width: 760px) {
        #originDataLogsPartnerSection .origin-data-logs-filter-card {
            align-items: stretch;
            flex-direction: column;
        }

        #originDataLogsPartnerSection .origin-data-logs-filter-field--partner,
        #originDataLogsPartnerSection .origin-data-logs-filter-field--month {
            width: 100%;
        }

    }

    #originDataLogsPartnerSection .origin-data-logs-partner-option {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-height: 2.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        background: #fff;
        color: #1f2937;
        font-weight: 600;
        cursor: pointer;
    }

    #originDataLogsPartnerSection .origin-data-logs-partner-option:has(input:checked) {
        border-color: #fb7185;
        box-shadow: 0 0 0 3px #ffe4e6;
        color: #e11d48;
    }

    #originDataLogsPartnerSection .origin-data-logs-partner-option input {
        width: 1rem;
        height: 1rem;
        margin: 0;
        accent-color: #f43f5e;
    }

    #originDataLogsPartnerSection .origin-data-logs-partner-option.is-unavailable {
        cursor: not-allowed;
        opacity: 0.55;
    }

    #originDataLogsPartnerSection .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>

<script>
(() => {
    const partners = <?= json_encode(
        $originDataLogPartners,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const form = document.getElementById('originDataLogsSettlementFilters');
    const input = document.getElementById('originDataLogsSettlementPartner');
    const month = document.getElementById('originDataLogsSettlementMonth');
    const suggestions = document.getElementById('originDataLogsSettlementPartnerSuggestions');
    const generateButton = form && form.querySelector('.origin-data-logs-generate');
    const tableBody = document.getElementById('originDataLogsSettlementTableBody');
    const viewModal = document.getElementById('originDataLogsSettlementViewModal');
    const viewModalClose = document.getElementById('originDataLogsSettlementViewClose');
    const viewModalDetails = document.getElementById('originDataLogsSettlementViewDetails');

    if (!form || !input || !month || !suggestions || !generateButton || !tableBody ||
        !viewModal || !viewModalClose || !viewModalDetails) return;

    // Escape the page content's stacking context so the modal stays above the fixed app header.
    document.body.appendChild(viewModal);

    let visiblePartners = [];
    let activeIndex = -1;
    const tableColumns = [
        'account_number', 'agent_name', 'legacy_id', 'tran_date', 'transaction_id',
        'reference_id', 'product', 'tran_type', 'orig_cntry', 'rcv_cntry',
        'fx_rate_trn', 'fx_date_trn', 'margin', 'base_tran_amt', 'fee_tran_amt',
        'fx_rev_share_tran_amt', 'comm_tran_amt', 'total_tran_amt',
        'settlement_currency', 'transaction_currency'
    ];
    const detailFields = [
        ['account_number', 'Account Number'],
        ['agent_name', 'Agent Name'],
        ['legacy_id', 'Legacy ID'],
        ['tran_date', 'Tran Date'],
        ['transaction_id', 'Transaction ID'],
        ['reference_id', 'Reference ID'],
        ['product', 'Product'],
        ['tran_type', 'Tran Type'],
        ['orig_cntry', 'Orig Cntry'],
        ['rcv_cntry', 'Rcv Cntry'],
        ['fx_rate_trn', 'FX Rate trn'],
        ['fx_date_trn', 'FX Date trn'],
        ['margin', 'Margin'],
        ['base_tran_amt', 'Base Tran Amt'],
        ['fee_tran_amt', 'Fee Tran Amt'],
        ['fx_rev_share_tran_amt', 'Fx Rev Share Tran Amt'],
        ['comm_tran_amt', 'Comm Tran Amt'],
        ['total_tran_amt', 'Total Tran Amt'],
        ['settlement_currency', 'Settlement Currency'],
        ['transaction_currency', 'Transaction Currency'],
        ['created_at', 'Uploaded Date'],
        ['created_by', 'Uploaded By'],
        ['updated_at', 'Updated Date'],
        ['updated_by', 'Updated By'],
        ['modified_at', 'Modified Date'],
        ['modified_by', 'Modified By']
    ];
    let viewTrigger = null;

    const closeSuggestions = () => {
        suggestions.hidden = true;
        suggestions.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };

    const choosePartner = partner => {
        input.value = partner;
        input.classList.remove('is-invalid');
        closeSuggestions();
    };

    const renderSuggestions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        visiblePartners = partners
            .filter(partner => partner.toLocaleLowerCase().includes(query))
            .slice(0, 100);

        suggestions.innerHTML = '';
        activeIndex = -1;

        visiblePartners.forEach(partner => {
            const option = document.createElement('li');
            option.textContent = partner;
            option.setAttribute('role', 'option');
            option.addEventListener('mousedown', event => {
                event.preventDefault();
                choosePartner(partner);
            });
            suggestions.appendChild(option);
        });

        suggestions.hidden = visiblePartners.length === 0;
        input.setAttribute('aria-expanded', visiblePartners.length ? 'true' : 'false');
    };

    const setActiveOption = index => {
        const options = Array.from(suggestions.children);
        options.forEach(option => option.classList.remove('is-active'));
        activeIndex = index;
        if (options[activeIndex]) {
            options[activeIndex].classList.add('is-active');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    input.addEventListener('focus', renderSuggestions);
    input.addEventListener('input', () => {
        input.classList.remove('is-invalid');
        renderSuggestions();
    });
    month.addEventListener('change', () => month.classList.remove('is-invalid'));
    input.addEventListener('keydown', event => {
        if (suggestions.hidden && event.key === 'ArrowDown') renderSuggestions();
        if (event.key === 'ArrowDown' && visiblePartners.length) {
            event.preventDefault();
            setActiveOption((activeIndex + 1) % visiblePartners.length);
        } else if (event.key === 'ArrowUp' && visiblePartners.length) {
            event.preventDefault();
            setActiveOption((activeIndex - 1 + visiblePartners.length) % visiblePartners.length);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            choosePartner(visiblePartners[activeIndex]);
        } else if (event.key === 'Escape') {
            closeSuggestions();
        }
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.origin-data-logs-autocomplete')) closeSuggestions();
    });

    const renderMessage = message => {
        tableBody.innerHTML = '';
        const row = document.createElement('tr');
        row.className = 'origin-data-logs-empty-row';
        const cell = document.createElement('td');
        cell.colSpan = tableColumns.length + 1;
        cell.textContent = message;
        row.appendChild(cell);
        tableBody.appendChild(row);
    };

    const closeViewModal = () => {
        viewModal.hidden = true;
        document.body.style.removeProperty('overflow');
        if (viewTrigger) viewTrigger.focus();
        viewTrigger = null;
    };

    const openViewModal = (rowData, trigger) => {
        viewTrigger = trigger;
        viewModalDetails.innerHTML = '';

        detailFields.forEach(([field, label]) => {
            const row = document.createElement('tr');
            const fieldHeader = document.createElement('th');
            const originCell = document.createElement('td');
            const modifiedCell = document.createElement('td');
            const originValue = rowData[field] ?? '';
            const modifiedValue = rowData[`modified_${field}`] ?? '';

            fieldHeader.scope = 'row';
            fieldHeader.textContent = `${label}:`;
            originCell.textContent = originValue === '' ? '—' : originValue;
            modifiedCell.textContent = modifiedValue === '' ? '—' : modifiedValue;
            modifiedCell.classList.toggle(
                'is-changed',
                String(originValue ?? '') !== String(modifiedValue ?? '')
            );

            row.append(fieldHeader, originCell, modifiedCell);
            viewModalDetails.appendChild(row);
        });

        viewModal.hidden = false;
        document.body.style.overflow = 'hidden';
        viewModalClose.focus();
    };

    const renderRows = rows => {
        if (!rows.length) {
            renderMessage('No original settlement records found for the selected filters.');
            return;
        }

        tableBody.innerHTML = '';
        const fragment = document.createDocumentFragment();
        rows.forEach(rowData => {
            const row = document.createElement('tr');
            tableColumns.forEach(column => {
                const cell = document.createElement('td');
                cell.textContent = rowData[column] ?? '';
                row.appendChild(cell);
            });

            const actionCell = document.createElement('td');
            const viewButton = document.createElement('button');
            viewButton.type = 'button';
            viewButton.className = 'origin-data-logs-view';
            viewButton.dataset.recordId = rowData.id ?? '';
            viewButton.setAttribute('aria-label', `View settlement record ${rowData.reference_id || ''}`.trim());

            const viewIcon = document.createElement('span');
            viewIcon.className = 'material-icons';
            viewIcon.setAttribute('aria-hidden', 'true');
            viewIcon.textContent = 'visibility';
            viewButton.appendChild(viewIcon);
            viewButton.addEventListener('click', () => openViewModal(rowData, viewButton));
            actionCell.appendChild(viewButton);
            row.appendChild(actionCell);
            fragment.appendChild(row);
        });
        tableBody.appendChild(fragment);
    };

    viewModalClose.addEventListener('click', closeViewModal);
    viewModal.addEventListener('mousedown', event => {
        if (event.target === viewModal) closeViewModal();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !viewModal.hidden) closeViewModal();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const validPartner = partners.includes(input.value.trim());
        input.classList.toggle('is-invalid', !validPartner);
        month.classList.toggle('is-invalid', !month.value);
        if (!validPartner) {
            input.focus();
            return;
        }
        if (!month.value) {
            month.focus();
            return;
        }

        generateButton.disabled = true;
        generateButton.textContent = 'Loading...';
        renderMessage('Loading settlement records...');

        try {
            const query = new URLSearchParams({
                partner: input.value.trim(),
                month: month.value
            });
            const endpoint = typeof window.autoreconUrl === 'function'
                ? window.autoreconUrl('src/controllers/history-logs/origin-partner-settlement-logs.php')
                : 'src/controllers/history-logs/origin-partner-settlement-logs.php';
            const response = await fetch(`${endpoint}?${query.toString()}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
                throw new Error(payload && payload.message
                    ? payload.message
                    : 'Unable to load settlement records.');
            }
            renderRows(Array.isArray(payload.rows) ? payload.rows : []);
        } catch (error) {
            renderMessage(error instanceof Error ? error.message : 'Unable to load settlement records.');
        } finally {
            generateButton.disabled = false;
            generateButton.textContent = 'Generate';
        }
    });
})();
</script>
