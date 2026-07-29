<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../../config/db.php';
require_once __DIR__ . '/../../../../../config/csrf.php';

$dataEntrySettlementPartners = [];
try {
    $statement = masterDataConnection()->query(
        "SELECT DISTINCT partner_name
         FROM corpo_partner_masterfile
         WHERE partner_name IS NOT NULL AND partner_name <> ''
         ORDER BY partner_name"
    );

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $partnerName) {
        $partnerName = trim((string)$partnerName);
        if ($partnerName !== '') {
            $dataEntrySettlementPartners[] = $partnerName;
        }
    }
} catch (Throwable $exception) {
    $dataEntrySettlementPartners = [];
}
?>
<section id="dataEntrySettlementDetailSection" class="data-entry-settlement-detail-section" aria-label="Data Entry Settlement Detail" style="display:none; padding:1rem">
    <h2 class="data-entry-settlement-title">Settlement Detail - Data Entry</h2>
    <p class="data-entry-settlement-subtitle">Settlement transactions for the selected corporate partner and month.</p>

    <form id="dataEntrySettlementFilters" class="data-entry-settlement-filters" novalidate>
        <label class="data-entry-settlement-field data-entry-settlement-field--partner">
            <span>Corporate Partner</span>
            <div class="data-entry-settlement-autocomplete">
                <input
                    id="dataEntrySettlementPartner"
                    type="text"
                    placeholder="Select corporate partner"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="dataEntrySettlementPartnerSuggestions"
                    aria-expanded="false"
                    required
                >
                <ul id="dataEntrySettlementPartnerSuggestions" role="listbox" hidden></ul>
            </div>
            <small id="dataEntrySettlementPartnerError" class="data-entry-settlement-error" aria-live="polite"></small>
        </label>

        <label class="data-entry-settlement-field data-entry-settlement-field--month">
            <span>Month</span>
            <input id="dataEntrySettlementMonth" type="month" required>
            <small id="dataEntrySettlementMonthError" class="data-entry-settlement-error" aria-live="polite"></small>
        </label>

        <button id="dataEntrySettlementGenerate" class="data-entry-settlement-generate" type="submit">Generate</button>

        <button id="dataEntrySettlementUpdateChanges" class="data-entry-settlement-update-changes" type="button" hidden disabled>Modified Changes (0)</button>

        <label id="dataEntrySettlementSearchField" class="data-entry-settlement-field data-entry-settlement-field--search" hidden>
            <span>Search</span>
            <input id="dataEntrySettlementSearch" type="search" placeholder="Search table" autocomplete="off">
        </label>
    </form>

    <div id="dataEntrySettlementTableCard" class="data-entry-settlement-table-card" hidden>
        <div class="data-entry-settlement-table-wrap">
            <table id="dataEntrySettlementTable">
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
                <tbody id="dataEntrySettlementTableBody">
                    <tr class="data-entry-settlement-empty-row">
                        <td colspan="21">Select a corporate partner and month, then click Generate.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="dataEntrySettlementEditModal" class="data-entry-settlement-modal" role="dialog" aria-modal="true" aria-labelledby="dataEntrySettlementEditModalTitle" hidden>
        <div class="data-entry-settlement-modal-dialog">
            <header class="data-entry-settlement-modal-header">
                <h3 id="dataEntrySettlementEditModalTitle">Edit Settlement Detail</h3>
                <button id="dataEntrySettlementEditModalClose" class="data-entry-settlement-modal-close" type="button" aria-label="Close">
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </header>
            <div class="data-entry-settlement-modal-body">
                <input id="dataEntrySettlementEditId" type="hidden">
                <dl class="data-entry-settlement-upload-details">
                    <div>
                        <dt>Partner Name:</dt>
                        <dd id="dataEntrySettlementEditPartner">—</dd>
                    </div>
                    <div>
                        <dt>Filename:</dt>
                        <dd id="dataEntrySettlementEditFilename">—</dd>
                    </div>
                    <div>
                        <dt>Remark:</dt>
                        <dd><span id="dataEntrySettlementEditRemark" class="data-entry-settlement-remark">Success</span></dd>
                    </div>
                    <div>
                        <dt id="dataEntrySettlementEditDateLabel">Uploaded Date:</dt>
                        <dd id="dataEntrySettlementEditUploadedDate">—</dd>
                    </div>
                    <div>
                        <dt id="dataEntrySettlementEditUserLabel">Uploaded By:</dt>
                        <dd id="dataEntrySettlementEditUploadedBy">—</dd>
                    </div>
                </dl>

                <div class="data-entry-settlement-edit-divider"></div>

                <form id="dataEntrySettlementEditForm" class="data-entry-settlement-edit-form">
                    <label>
                        <span>Account Number</span>
                        <input id="dataEntryEditAccountNumber" name="account_number" type="text">
                    </label>
                    <label>
                        <span>Agent Name <em>*</em></span>
                        <input id="dataEntryEditAgentName" name="agent_name" type="text" required>
                    </label>
                    <label>
                        <span>Legacy ID</span>
                        <input id="dataEntryEditLegacyId" name="legacy_id" type="text">
                    </label>
                    <label>
                        <span>Tran Date <em>*</em></span>
                        <input id="dataEntryEditTranDate" name="tran_date" type="date" required>
                    </label>
                    <label>
                        <span>Settled Date</span>
                        <input id="dataEntryEditSettledDate" name="settled_date" type="date" disabled>
                    </label>
                    <label>
                        <span>Transaction ID</span>
                        <input id="dataEntryEditTransactionId" name="transaction_id" type="text">
                    </label>
                    <label>
                        <span>Reference ID <em>*</em></span>
                        <input id="dataEntryEditReferenceId" name="reference_id" type="text" required>
                    </label>
                    <label>
                        <span>Product</span>
                        <input id="dataEntryEditProduct" name="product" type="text">
                    </label>
                    <label>
                        <span>Tran Type <em>*</em></span>
                        <select id="dataEntryEditTranType" name="tran_type" required>
                            <option value="">Select transaction type</option>
                            <option value="REC">REC</option>
                            <option value="RRC">RRC</option>
                            <option value="SEN">SEN</option>
                            <option value="RSN">RSN</option>
                            <option value="REF">REF</option>
                        </select>
                    </label>
                    <label>
                        <span>Orig Cntry</span>
                        <input id="dataEntryEditOrigCntry" name="orig_cntry" type="text">
                    </label>
                    <label>
                        <span>Rcv Cntry</span>
                        <input id="dataEntryEditRcvCntry" name="rcv_cntry" type="text">
                    </label>
                    <label>
                        <span>Fx Rate Trn</span>
                        <input id="dataEntryEditFxRateTrn" name="fx_rate_trn" type="text" inputmode="decimal">
                    </label>
                    <label>
                        <span>Fx Date Trn</span>
                        <input id="dataEntryEditFxDateTrn" name="fx_date_trn" type="date">
                    </label>
                    <label>
                        <span>Margin</span>
                        <input id="dataEntryEditMargin" name="margin" type="text" inputmode="decimal">
                    </label>
                    <label>
                        <span>Base Tran Amt <em>*</em></span>
                        <input id="dataEntryEditBaseTranAmt" name="base_tran_amt" type="text" inputmode="decimal" required>
                    </label>
                    <label>
                        <span>Fee Tran Amt <em>*</em></span>
                        <input id="dataEntryEditFeeTranAmt" name="fee_tran_amt" type="text" inputmode="decimal" required>
                    </label>
                    <label>
                        <span>Fx Rev Share Tran Amt <em>*</em></span>
                        <input id="dataEntryEditFxRevShareTranAmt" name="fx_rev_share_tran_amt" type="text" inputmode="decimal" required>
                    </label>
                    <label>
                        <span>Comm Tran Amt <em>*</em></span>
                        <input id="dataEntryEditCommTranAmt" name="comm_tran_amt" type="text" inputmode="decimal" required>
                    </label>
                    <label>
                        <span>Total Tran Amt</span>
                        <input id="dataEntryEditTotalTranAmt" name="total_tran_amt" type="text" inputmode="decimal" disabled>
                    </label>
                    <label>
                        <span>Settlement Currency <em>*</em></span>
                        <input id="dataEntryEditSettlementCurrency" name="settlement_currency" type="text" required>
                    </label>
                    <label>
                        <span>Transaction Currency <em>*</em></span>
                        <input id="dataEntryEditTransactionCurrency" name="transaction_currency" type="text" required>
                    </label>

                    <div class="data-entry-settlement-edit-actions">
                        <button class="data-entry-settlement-submit" type="submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const root = document.getElementById('dataEntrySettlementDetailSection');
        if (!root || root.dataset.initialized === '1') return;
        root.dataset.initialized = '1';

        const form = document.getElementById('dataEntrySettlementFilters');
        const partnerInput = document.getElementById('dataEntrySettlementPartner');
        const monthInput = document.getElementById('dataEntrySettlementMonth');
        const suggestions = document.getElementById('dataEntrySettlementPartnerSuggestions');
        const partnerError = document.getElementById('dataEntrySettlementPartnerError');
        const monthError = document.getElementById('dataEntrySettlementMonthError');
        const generateButton = document.getElementById('dataEntrySettlementGenerate');
        const searchField = document.getElementById('dataEntrySettlementSearchField');
        const searchInput = document.getElementById('dataEntrySettlementSearch');
        const updateChangesButton = document.getElementById('dataEntrySettlementUpdateChanges');
        const tableCard = document.getElementById('dataEntrySettlementTableCard');
        const tableBody = document.getElementById('dataEntrySettlementTableBody');
        const editModal = document.getElementById('dataEntrySettlementEditModal');
        const editModalClose = document.getElementById('dataEntrySettlementEditModalClose');
        const editId = document.getElementById('dataEntrySettlementEditId');
        const editPartner = document.getElementById('dataEntrySettlementEditPartner');
        const editFilename = document.getElementById('dataEntrySettlementEditFilename');
        const editRemark = document.getElementById('dataEntrySettlementEditRemark');
        const editDateLabel = document.getElementById('dataEntrySettlementEditDateLabel');
        const editUploadedDate = document.getElementById('dataEntrySettlementEditUploadedDate');
        const editUserLabel = document.getElementById('dataEntrySettlementEditUserLabel');
        const editUploadedBy = document.getElementById('dataEntrySettlementEditUploadedBy');
        const editForm = document.getElementById('dataEntrySettlementEditForm');
        const editFields = {
            account_number: document.getElementById('dataEntryEditAccountNumber'),
            agent_name: document.getElementById('dataEntryEditAgentName'),
            legacy_id: document.getElementById('dataEntryEditLegacyId'),
            tran_date: document.getElementById('dataEntryEditTranDate'),
            settled_date: document.getElementById('dataEntryEditSettledDate'),
            transaction_id: document.getElementById('dataEntryEditTransactionId'),
            reference_id: document.getElementById('dataEntryEditReferenceId'),
            product: document.getElementById('dataEntryEditProduct'),
            tran_type: document.getElementById('dataEntryEditTranType'),
            orig_cntry: document.getElementById('dataEntryEditOrigCntry'),
            rcv_cntry: document.getElementById('dataEntryEditRcvCntry'),
            fx_rate_trn: document.getElementById('dataEntryEditFxRateTrn'),
            fx_date_trn: document.getElementById('dataEntryEditFxDateTrn'),
            margin: document.getElementById('dataEntryEditMargin'),
            base_tran_amt: document.getElementById('dataEntryEditBaseTranAmt'),
            fee_tran_amt: document.getElementById('dataEntryEditFeeTranAmt'),
            fx_rev_share_tran_amt: document.getElementById('dataEntryEditFxRevShareTranAmt'),
            comm_tran_amt: document.getElementById('dataEntryEditCommTranAmt'),
            total_tran_amt: document.getElementById('dataEntryEditTotalTranAmt'),
            settlement_currency: document.getElementById('dataEntryEditSettlementCurrency'),
            transaction_currency: document.getElementById('dataEntryEditTransactionCurrency')
        };
        const decimalFieldNames = [
            'fx_rate_trn', 'margin', 'base_tran_amt', 'fee_tran_amt',
            'fx_rev_share_tran_amt', 'comm_tran_amt', 'total_tran_amt'
        ];
        const amountFieldNames = [
            'base_tran_amt', 'fee_tran_amt', 'fx_rev_share_tran_amt', 'comm_tran_amt'
        ];
        const partners = <?= json_encode($dataEntrySettlementPartners, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const csrfToken = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const columns = [
            'account_number', 'agent_name', 'legacy_id', 'tran_date', 'transaction_id',
            'reference_id', 'product', 'tran_type', 'orig_cntry', 'rcv_cntry',
            'fx_rate_trn', 'fx_date_trn', 'margin', 'base_tran_amt', 'fee_tran_amt',
            'fx_rev_share_tran_amt', 'comm_tran_amt', 'total_tran_amt',
            'settlement_currency', 'transaction_currency'
        ];
        let loadedRows = [];
        const modifiedRows = new Map();
        let editTrigger = null;
        let allowPageUnload = false;
        let pendingWarningOpen = false;

        function normalize(value) {
            return String(value || '').trim().toLocaleLowerCase();
        }

        function exactPartner() {
            const value = normalize(partnerInput.value);
            return partners.find(name => normalize(name) === value) || '';
        }

        function closeSuggestions() {
            suggestions.hidden = true;
            suggestions.innerHTML = '';
            partnerInput.setAttribute('aria-expanded', 'false');
        }

        function selectPartner(name) {
            partnerInput.value = name;
            partnerInput.classList.remove('is-invalid');
            partnerError.textContent = '';
            closeSuggestions();
        }

        function renderSuggestions() {
            const query = normalize(partnerInput.value);
            const matches = partners.filter(name => !query || normalize(name).includes(query));
            suggestions.innerHTML = '';

            if (!matches.length) {
                const empty = document.createElement('li');
                empty.className = 'is-empty';
                empty.textContent = 'No corporate partner found';
                suggestions.appendChild(empty);
            } else {
                matches.forEach(name => {
                    const option = document.createElement('li');
                    option.setAttribute('role', 'option');
                    option.tabIndex = -1;
                    option.textContent = name;
                    option.addEventListener('mousedown', event => {
                        event.preventDefault();
                        selectPartner(name);
                    });
                    suggestions.appendChild(option);
                });
            }

            suggestions.hidden = false;
            partnerInput.setAttribute('aria-expanded', 'true');
        }

        function validate() {
            const selectedPartner = exactPartner();
            const hasValidMonth = /^\d{4}-(0[1-9]|1[0-2])$/.test(monthInput.value);

            partnerError.textContent = selectedPartner ? '' : 'Please select a valid corporate partner.';
            monthError.textContent = hasValidMonth ? '' : 'Please select a month.';
            partnerInput.classList.toggle('is-invalid', !selectedPartner);
            monthInput.classList.toggle('is-invalid', !hasValidMonth);

            return selectedPartner && hasValidMonth
                ? { partner: selectedPartner, month: monthInput.value }
                : null;
        }

        function renderMessage(message, className) {
            tableBody.innerHTML = '';
            const row = document.createElement('tr');
            row.className = className || 'data-entry-settlement-empty-row';
            const cell = document.createElement('td');
            cell.colSpan = columns.length + 1;
            cell.textContent = message;
            row.appendChild(cell);
            tableBody.appendChild(row);
        }

        function isEmptyCell(value) {
            return value === null || value === undefined || String(value).trim() === '';
        }

        function displayValue(value) {
            return isEmptyCell(value) ? '—' : String(value);
        }

        function formatUploadedDate(value) {
            const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (!match) return displayValue(value);
            const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
            return new Intl.DateTimeFormat('en-US', {
                month: 'long',
                day: '2-digit',
                year: 'numeric'
            }).format(date);
        }

        function dateInputValue(value) {
            const match = String(value || '').match(/^\d{4}-\d{2}-\d{2}/);
            return match ? match[0] : '';
        }

        function decimalInputValue(value) {
            if (isEmptyCell(value)) return '';
            const number = Number(value);
            return Number.isFinite(number) ? number.toFixed(2) : '';
        }

        function calculateTotal() {
            const total = amountFieldNames.reduce((sum, fieldName) => {
                const value = Number(editFields[fieldName].value);
                return sum + (Number.isFinite(value) ? value : 0);
            }, 0);
            editFields.total_tran_amt.value = total.toFixed(2);
        }

        function applyTranTypeSigns() {
            const tranType = editFields.tran_type.value;
            const forcedNegativeFields = new Set(
                tranType === 'RSN'
                    ? ['base_tran_amt', 'fee_tran_amt']
                    : (tranType === 'REF' ? ['base_tran_amt'] : [])
            );

            ['base_tran_amt', 'fee_tran_amt'].forEach(fieldName => {
                const input = editFields[fieldName];
                const unsignedValue = input.value.replace(/^-/, '');
                input.value = forcedNegativeFields.has(fieldName)
                    ? '-' + unsignedValue
                    : unsignedValue;
                input.setCustomValidity(
                    input.value === '' || input.value === '-'
                        ? (input.required ? 'Enter a valid amount.' : '')
                        : ''
                );
            });
            calculateTotal();
        }

        function populateEditFields(rowData) {
            Object.entries(editFields).forEach(([fieldName, input]) => {
                input.setCustomValidity('');
                const value = rowData[fieldName];
                if (fieldName === 'tran_date' || fieldName === 'settled_date' || fieldName === 'fx_date_trn') {
                    input.value = dateInputValue(value);
                } else if (decimalFieldNames.includes(fieldName)) {
                    input.value = decimalInputValue(value);
                } else if (fieldName === 'tran_type') {
                    input.value = isEmptyCell(value) ? '' : String(value).trim().toUpperCase();
                } else {
                    input.value = isEmptyCell(value) ? '' : String(value);
                }
            });
            applyTranTypeSigns();
            calculateTotal();
        }

        function closeEditModal() {
            editModal.hidden = true;
            document.body.classList.remove('data-entry-settlement-modal-open');
            if (editTrigger) editTrigger.focus();
            editTrigger = null;
        }

        function updateModifiedChangesButton() {
            const count = modifiedRows.size;
            updateChangesButton.textContent = 'Modified Changes (' + count + ')';
            updateChangesButton.disabled = count === 0;
        }

        async function showPendingModificationWarning() {
            if (!modifiedRows.size || pendingWarningOpen) return;
            pendingWarningOpen = true;
            const count = modifiedRows.size;

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    icon: 'warning',
                    title: 'Unsaved Changes Detected',
                    html:
                        '<p class="data-entry-pending-message">You still have modified data waiting to be applied.<br>Reloading this page will discard the pending changes.</p>' +
                        '<div class="data-entry-pending-summary"><strong>Settlement Detail:</strong> ' +
                        count + ' modified ' + (count === 1 ? 'row' : 'rows') + '</div>',
                    showCancelButton: true,
                    confirmButtonText: 'OK',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#e5242a',
                    cancelButtonColor: '#eef2f7',
                    customClass: { cancelButton: 'data-entry-pending-cancel' },
                    reverseButtons: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                pendingWarningOpen = false;
                if (result.isConfirmed) {
                    allowPageUnload = true;
                    window.location.reload();
                }
                return;
            }

            pendingWarningOpen = false;
            if (window.confirm('Reloading this page will discard ' + count + ' modified row(s). Continue?')) {
                allowPageUnload = true;
                window.location.reload();
            }
        }

        function openEditModal(rowData, trigger) {
            editTrigger = trigger;
            editId.value = String(rowData.id || '');
            editPartner.textContent = displayValue(rowData.partner_name);
            editFilename.textContent = displayValue(rowData.upload_filename);
            const modified = !isEmptyCell(rowData.modified_at) && !isEmptyCell(rowData.modified_by);
            const overwritten = String(rowData.has_overwrite || '') === '1';
            const activityDate = modified
                ? rowData.modified_at
                : (overwritten ? rowData.updated_at : rowData.created_at);
            editDateLabel.textContent = modified ? 'Modified Date:' : (overwritten ? 'Updated Date:' : 'Uploaded Date:');
            editUserLabel.textContent = modified ? 'Modified By:' : (overwritten ? 'Updated By:' : 'Uploaded By:');
            editUploadedDate.textContent = formatUploadedDate(activityDate);
            editUploadedBy.textContent = displayValue(rowData.uploaded_by_name || rowData.activity_user_id);
            editRemark.textContent = modified ? 'Modified' : (overwritten ? 'Overwritten' : 'Success');
            editRemark.classList.toggle('is-modified', modified);
            editRemark.classList.toggle('is-overwritten', !modified && overwritten);
            populateEditFields(rowData);
            editModal.hidden = false;
            document.body.classList.add('data-entry-settlement-modal-open');
            editModalClose.focus();
        }

        function renderRows(rows) {
            tableBody.innerHTML = '';
            if (!rows.length) {
                renderMessage('No settlement records were found.');
                return;
            }

            const fragment = document.createDocumentFragment();
            rows.forEach(rowData => {
                const row = document.createElement('tr');
                columns.forEach(column => {
                    const cell = document.createElement('td');
                    const value = rowData[column];
                    if (isEmptyCell(value)) {
                        cell.className = 'is-empty-system-cell';
                        cell.setAttribute('aria-label', 'Empty system value');
                    } else {
                        cell.textContent = String(value);
                    }
                    row.appendChild(cell);
                });

                const actionCell = document.createElement('td');
                actionCell.className = 'data-entry-settlement-action-cell';
                const editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'data-entry-settlement-edit';
                editButton.textContent = 'Edit';
                editButton.dataset.id = String(rowData.id || '');
                editButton.disabled = !editButton.dataset.id;
                editButton.addEventListener('click', () => {
                    openEditModal(rowData, editButton);
                    root.dispatchEvent(new CustomEvent('settlementdetail:edit', {
                        bubbles: true,
                        detail: { id: editButton.dataset.id, row: rowData }
                    }));
                });
                actionCell.appendChild(editButton);
                row.appendChild(actionCell);
                fragment.appendChild(row);
            });
            tableBody.appendChild(fragment);
        }

        function filterLoadedRows() {
            const query = normalize(searchInput.value);
            if (!query) {
                renderRows(loadedRows);
                return;
            }

            const filteredRows = loadedRows.filter(rowData =>
                columns.some(column => normalize(rowData[column]).includes(query))
            );

            if (!filteredRows.length) {
                renderMessage('No settlement records match your search.');
                return;
            }
            renderRows(filteredRows);
        }

        async function loadSettlementRows(filters) {
            modifiedRows.clear();
            updateModifiedChangesButton();
            searchField.hidden = false;
            updateChangesButton.hidden = false;
            tableCard.hidden = false;
            generateButton.disabled = true;
            generateButton.textContent = 'Loading...';
            renderMessage('Loading settlement records...', 'data-entry-settlement-loading-row');

            try {
                const query = new URLSearchParams({
                    partner: filters.partner,
                    month: filters.month
                });
                const response = await fetch(
                    window.autoreconUrl('src/controllers/data-entry/settlement-detail-results.php') + '?' + query.toString(),
                    { headers: { Accept: 'application/json' } }
                );
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload || !payload.success) {
                    throw new Error(payload && payload.message ? payload.message : 'Unable to load settlement details.');
                }
                loadedRows = Array.isArray(payload.rows) ? payload.rows : [];
                filterLoadedRows();
            } catch (error) {
                loadedRows = [];
                renderMessage(error instanceof Error ? error.message : 'Unable to load settlement details.');
            } finally {
                generateButton.disabled = false;
                generateButton.textContent = 'Generate';
            }
        }

        partnerInput.addEventListener('input', () => {
            partnerError.textContent = '';
            partnerInput.classList.remove('is-invalid');
            renderSuggestions();
        });
        partnerInput.addEventListener('focus', renderSuggestions);
        partnerInput.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeSuggestions();
            if (event.key === 'Enter' && !suggestions.hidden) {
                const firstOption = suggestions.querySelector('[role="option"]');
                if (firstOption) {
                    event.preventDefault();
                    selectPartner(firstOption.textContent || '');
                }
            }
        });
        monthInput.addEventListener('input', () => {
            monthError.textContent = '';
            monthInput.classList.remove('is-invalid');
        });
        searchInput.addEventListener('input', filterLoadedRows);
        amountFieldNames.forEach(fieldName => {
            editFields[fieldName].addEventListener('input', calculateTotal);
        });
        editFields.tran_type.addEventListener('change', applyTranTypeSigns);
        decimalFieldNames.forEach(fieldName => {
            const input = editFields[fieldName];
            if (input.disabled) return;
            input.addEventListener('input', () => {
                const original = input.value;
                const tranType = editFields.tran_type.value;
                const forceNegative =
                    (tranType === 'RSN' && ['base_tran_amt', 'fee_tran_amt'].includes(fieldName))
                    || (tranType === 'REF' && fieldName === 'base_tran_amt');
                const isNegative = forceNegative || original.trim().startsWith('-');
                const cleaned = original.replace(/[^\d.]/g, '');
                const parts = cleaned.split('.');
                const integerPart = parts.shift() || '';
                const decimalPart = parts.join('').slice(0, 2);
                input.value = (isNegative ? '-' : '')
                    + integerPart
                    + (cleaned.includes('.') ? '.' + decimalPart : '');
                input.setCustomValidity(
                    input.value === '' || /^-?\d+(?:\.\d{0,2})?$/.test(input.value)
                        ? ''
                        : 'Enter a valid number with up to two decimal places.'
                );
                if (amountFieldNames.includes(fieldName)) calculateTotal();
            });
            input.addEventListener('blur', () => {
                if (input.value === '') return;
                const value = Number(input.value);
                if (Number.isFinite(value)) input.value = value.toFixed(2);
            });
        });
        editForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!editForm.reportValidity()) return;

            const values = {};
            Object.entries(editFields).forEach(([fieldName, input]) => {
                values[fieldName] = input.value;
            });
            root.dispatchEvent(new CustomEvent('settlementdetail:submit', {
                bubbles: true,
                detail: { id: editId.value, values: values }
            }));
            modifiedRows.set(editId.value, { id: editId.value, values: values });
            const loadedRowIndex = loadedRows.findIndex(row => String(row.id || '') === editId.value);
            if (loadedRowIndex >= 0) {
                loadedRows[loadedRowIndex] = Object.assign({}, loadedRows[loadedRowIndex], values);
            }
            updateModifiedChangesButton();
            closeEditModal();
            filterLoadedRows();
        });
        updateChangesButton.addEventListener('click', async () => {
            const count = modifiedRows.size;
            if (!count) return;

            let confirmed = false;
            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    title: 'Confirm Modified Changes',
                    text: 'Apply changes to ' + count + ' modified ' + (count === 1 ? 'row' : 'rows') + '?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, apply changes',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#df3547',
                    reverseButtons: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    allowEnterKey: false
                });
                confirmed = result.isConfirmed;
            } else {
                confirmed = window.confirm(
                    'Apply changes to ' + count + ' modified ' + (count === 1 ? 'row' : 'rows') + '?'
                );
            }
            if (!confirmed) return;

            const rows = Array.from(modifiedRows.values());
            updateChangesButton.disabled = true;
            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire({
                    title: 'Applying Modified Changes',
                    text: 'Archiving existing data and updating settlement rows...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => window.Swal.showLoading()
                });
            }

            try {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('partner', exactPartner());
                formData.append('payload', JSON.stringify({ rows: rows }));
                const response = await fetch(
                    window.autoreconUrl('src/controllers/data-entry/update-settlement-details.php'),
                    { method: 'POST', body: formData, headers: { Accept: 'application/json' } }
                );
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload || !payload.success) {
                    throw new Error(payload && payload.message ? payload.message : 'Unable to apply modified settlement changes.');
                }

                root.dispatchEvent(new CustomEvent('settlementdetail:updatechanges', {
                    bubbles: true,
                    detail: { rows: rows, result: payload }
                }));
                modifiedRows.clear();
                allowPageUnload = true;
                updateModifiedChangesButton();
                await loadSettlementRows({ partner: exactPartner(), month: monthInput.value });
                allowPageUnload = false;

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    await window.Swal.fire({
                        title: 'Changes Applied',
                        text: payload.updated_count + ' settlement ' + (payload.updated_count === 1 ? 'row was' : 'rows were') + ' updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#df3547'
                    });
                }
            } catch (error) {
                updateModifiedChangesButton();
                const message = error instanceof Error ? error.message : 'Unable to apply modified settlement changes.';
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    await window.Swal.fire({
                        title: 'Update Failed',
                        text: message,
                        icon: 'error',
                        confirmButtonColor: '#df3547'
                    });
                } else {
                    window.alert(message);
                }
            }
        });
        window.addEventListener('beforeunload', event => {
            if (allowPageUnload || modifiedRows.size === 0) return;
            event.preventDefault();
            event.returnValue = '';
        });
        document.addEventListener('keydown', event => {
            const reloadShortcut = event.key === 'F5'
                || ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'r');
            if (!reloadShortcut || modifiedRows.size === 0) return;
            event.preventDefault();
            showPendingModificationWarning();
        });
        editModalClose.addEventListener('click', closeEditModal);
        editModal.addEventListener('mousedown', event => {
            if (event.target === editModal) closeEditModal();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !editModal.hidden) closeEditModal();
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('.data-entry-settlement-autocomplete')) closeSuggestions();
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const filters = validate();
            if (!filters) return;

            await loadSettlementRows(filters);
            root.dispatchEvent(new CustomEvent('settlementdetail:generate', {
                bubbles: true,
                detail: filters
            }));
        });
    }());
    </script>
</section>
