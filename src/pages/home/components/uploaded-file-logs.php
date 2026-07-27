<section id="uploadedFileLogsSection" class="uploaded-file-logs-section" aria-label="Uploaded File Logs" style="display:none; padding:1rem">
    <div class="uploaded-file-logs-panel">
        <div class="uploaded-file-logs-header">
            <h2>Uploaded File Logs</h2>
        </div>

        <form class="uploaded-file-logs-filters" aria-label="Uploaded file log filters">
            <fieldset class="uploaded-file-logs-group">
                <!-- <legend>Data Source</legend> -->
                <div class="uploaded-file-logs-options">
                    <label class="uploaded-file-logs-radio">
                        <input type="radio" name="uploaded_file_log_source" value="kpx_web_data" checked>
                        <span>KPX Web Data</span>
                    </label>
                    <label class="uploaded-file-logs-radio">
                        <input type="radio" name="uploaded_file_log_source" value="partner_data">
                        <span>Partner Data</span>
                    </label>
                </div>
            </fieldset>

            <div class="uploaded-file-logs-state-card" data-log-source-card="kpx_web_data">
                <fieldset class="uploaded-file-logs-group uploaded-file-logs-group--state">
                    <!-- <legend>KPX Web Data State</legend> -->
                    <div class="uploaded-file-logs-state-toolbar">
                        <div class="uploaded-file-logs-options uploaded-file-logs-options--state uploaded-file-logs-tabs"
                             role="tablist"
                             aria-label="KPX Web Data state">
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="true">
                                <input type="radio" name="uploaded_file_log_state" value="all" checked>
                                <span>ALL</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="uploaded_file_log_state" value="payout">
                                <span>PAYOUT</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="uploaded_file_log_state" value="sendout">
                                <span>SENDOUT</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="uploaded_file_log_state" value="payout_cancel">
                                <span>PAYOUT CANCELLATION</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="uploaded_file_log_state" value="sendout_cancel">
                                <span>SENDOUT CANCELLATION</span>
                            </label>
                        </div>
                        <div class="uploaded-file-logs-filter-tools">
                            <label class="uploaded-file-logs-field">
                                <span>Date:</span>
                                <input type="month" id="uploadedFileLogsMonth" name="uploaded_file_log_month">
                            </label>
                            <label class="uploaded-file-logs-field uploaded-file-logs-field--search">
                                <span>Search:</span>
                                <input type="search" id="uploadedFileLogsSearch" name="uploaded_file_log_search" placeholder="Search files">
                            </label>
                            <button type="button" id="uploadedFileLogsClearBtn" class="uploaded-file-logs-clear-btn">Clear</button>
                        </div>
                    </div>
                </fieldset>

                <div class="uploaded-file-logs-table-card">
                    <div class="uploaded-file-logs-table-header">
                        <!-- <h3 id="uploadedFileLogsTableTitle">PAYOUT Uploaded Files</h3> -->
                    </div>
                    <div class="uploaded-file-logs-table-wrap">
                        <table class="uploaded-file-logs-table">
                            <thead>
                                <tr>
                                    <th>Uploaded Date</th>
                                    <th>Filename</th>
                                    <th>File Extension</th>
                                    <th>Partner Name</th>
                                    <th>Uploaded By</th>
                                    <th>Remark</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="uploadedFileLogsTableBody">
                                <tr>
                                    <td colspan="7" class="uploaded-file-logs-empty">No PAYOUT uploaded files found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="uploaded-file-logs-state-card" data-log-source-card="partner_data" hidden>
                <fieldset class="uploaded-file-logs-group uploaded-file-logs-group--state">
                    <div class="uploaded-file-logs-state-toolbar">
                        <div class="uploaded-file-logs-options uploaded-file-logs-options--state uploaded-file-logs-tabs"
                             role="tablist"
                             aria-label="Partner Data type">
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="true">
                                <input type="radio" name="partner_uploaded_file_log_state" value="all" checked>
                                <span>ALL</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="partner_uploaded_file_log_state" value="transactional">
                                <span>TRANSACTIONAL</span>
                            </label>
                            <label class="uploaded-file-logs-radio uploaded-file-logs-tab" role="tab" aria-selected="false">
                                <input type="radio" name="partner_uploaded_file_log_state" value="settlement">
                                <span>SETTLEMENT</span>
                            </label>
                        </div>
                        <div class="uploaded-file-logs-filter-tools">
                            <label class="uploaded-file-logs-field">
                                <span>Date:</span>
                                <input type="month" id="partnerUploadedFileLogsMonth" name="partner_uploaded_file_log_month">
                            </label>
                            <label class="uploaded-file-logs-field uploaded-file-logs-field--search">
                                <span>Search:</span>
                                <input type="search" id="partnerUploadedFileLogsSearch" name="partner_uploaded_file_log_search" placeholder="Search files">
                            </label>
                            <button type="button" id="partnerUploadedFileLogsClearBtn" class="uploaded-file-logs-clear-btn">Clear</button>
                        </div>
                    </div>
                </fieldset>

                <div class="uploaded-file-logs-table-card">
                    <div class="uploaded-file-logs-table-header">
                        <!-- <h3 id="partnerUploadedFileLogsTableTitle">PAYOUT Uploaded Files</h3> -->
                    </div>
                    <div class="uploaded-file-logs-table-wrap">
                        <table class="uploaded-file-logs-table">
                            <thead>
                                <tr>
                                    <th>Uploaded Date</th>
                                    <th>Filename</th>
                                    <th>File Extension</th>
                                    <th>Partner Name</th>
                                    <th>Uploaded By</th>
                                    <th>Remark</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partnerUploadedFileLogsTableBody">
                                <tr>
                                    <td colspan="7" class="uploaded-file-logs-empty">No Partner Data uploaded files found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="uploadedFileLogsViewModal"
         class="uploaded-file-logs-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="uploadedFileLogsViewModalTitle"
         hidden>
        <div class="uploaded-file-logs-modal-dialog">
            <div class="uploaded-file-logs-modal-header">
                <h3 id="uploadedFileLogsViewModalTitle">Uploaded File Details</h3>
                <button type="button"
                        id="uploadedFileLogsViewModalClose"
                        class="uploaded-file-logs-modal-close"
                        aria-label="Close">
                    <span class="material-icons" aria-hidden="true">close</span>
                </button>
            </div>
            <div class="uploaded-file-logs-modal-body">
                <dl class="uploaded-file-logs-details">
                    <div class="uploaded-file-logs-detail-row">
                        <dt>Partner Name:</dt>
                        <dd id="uploadedFileLogsDetailPartner">—</dd>
                    </div>
                    <div class="uploaded-file-logs-detail-row">
                        <dt>Filename:</dt>
                        <dd id="uploadedFileLogsDetailFilename">—</dd>
                    </div>
                    <div class="uploaded-file-logs-detail-row">
                        <dt>Remark:</dt>
                        <dd>
                            <span id="uploadedFileLogsDetailRemark"
                                  class="uploaded-file-logs-remark uploaded-file-logs-remark--success">Success</span>
                        </dd>
                    </div>
                    <div class="uploaded-file-logs-detail-row">
                        <dt>Uploaded Date:</dt>
                        <dd id="uploadedFileLogsDetailDate">—</dd>
                    </div>
                    <div class="uploaded-file-logs-detail-row">
                        <dt>Uploaded By:</dt>
                        <dd id="uploadedFileLogsDetailUploader">—</dd>
                    </div>
                </dl>

                <section class="uploaded-file-logs-records" aria-labelledby="uploadedFileLogsRecordsTitle">
                    <div class="uploaded-file-logs-records-toolbar">
                        <h4 id="uploadedFileLogsRecordsTitle">Uploaded Records</h4>
                        <label class="uploaded-file-logs-records-search">
                            <span>Search:</span>
                            <input type="search"
                                   id="uploadedFileLogsRecordsSearch"
                                   placeholder="Search records"
                                   autocomplete="off">
                        </label>
                    </div>
                    <div class="uploaded-file-logs-records-wrap">
                        <table class="uploaded-file-logs-records-table">
                            <thead id="uploadedFileLogsRecordsHead"></thead>
                            <tbody id="uploadedFileLogsRecordsBody">
                                <tr>
                                    <td class="uploaded-file-logs-records-message">Select a file log to view its records.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceRadios = document.querySelectorAll('input[name="uploaded_file_log_source"]');
    const stateRadios = document.querySelectorAll('input[name="uploaded_file_log_state"]');
    const partnerStateRadios = document.querySelectorAll('input[name="partner_uploaded_file_log_state"]');
    const sourceCards = document.querySelectorAll('[data-log-source-card]');
    const tableTitle = document.getElementById('uploadedFileLogsTableTitle');
    const tableBody = document.getElementById('uploadedFileLogsTableBody');
    const partnerTableBody = document.getElementById('partnerUploadedFileLogsTableBody');
    const monthField = document.getElementById('uploadedFileLogsMonth');
    const searchField = document.getElementById('uploadedFileLogsSearch');
    const clearButton = document.getElementById('uploadedFileLogsClearBtn');
    const partnerMonthField = document.getElementById('partnerUploadedFileLogsMonth');
    const partnerSearchField = document.getElementById('partnerUploadedFileLogsSearch');
    const partnerClearButton = document.getElementById('partnerUploadedFileLogsClearBtn');
    const viewModal = document.getElementById('uploadedFileLogsViewModal');
    const viewModalClose = document.getElementById('uploadedFileLogsViewModalClose');
    const detailPartner = document.getElementById('uploadedFileLogsDetailPartner');
    const detailFilename = document.getElementById('uploadedFileLogsDetailFilename');
    const detailRemark = document.getElementById('uploadedFileLogsDetailRemark');
    const detailDate = document.getElementById('uploadedFileLogsDetailDate');
    const detailUploader = document.getElementById('uploadedFileLogsDetailUploader');
    const recordsHead = document.getElementById('uploadedFileLogsRecordsHead');
    const recordsBody = document.getElementById('uploadedFileLogsRecordsBody');
    const recordsSearch = document.getElementById('uploadedFileLogsRecordsSearch');
    let activeRequest = null;
    let activeDetailRequest = null;
    let searchTimer = null;
    let recordsSearchTimer = null;
    let viewModalTrigger = null;
    let loadedRecordColumns = [];
    let loadedRecordRows = [];

    const stateLabels = {
        all: 'ALL',
        payout: 'PAYOUT',
        sendout: 'SENDOUT',
        payout_cancel: 'PAYOUT CANCELLATION',
        sendout_cancel: 'SENDOUT CANCELLATION',
        transactional: 'TRANSACTIONAL',
        settlement: 'SETTLEMENT'
    };

    if (!sourceRadios.length || !sourceCards.length) {
        return;
    }

    function getSelectedStateLabel(radioName) {
        const selectedState = document.querySelector('input[name="' + radioName + '"]:checked');
        return stateLabels[selectedState ? selectedState.value : 'all'] || 'ALL';
    }

    function updateTableState(radioName, activeTableTitle) {
        const stateLabel = getSelectedStateLabel(radioName);
        const radioGroup = document.querySelectorAll('input[name="' + radioName + '"]');

        radioGroup.forEach(function (radio) {
            const tab = radio.closest('[role="tab"]');
            if (tab) {
                tab.setAttribute('aria-selected', radio.checked ? 'true' : 'false');
            }
        });

        if (activeTableTitle) {
            activeTableTitle.textContent = stateLabel + ' Uploaded Files';
        }
    }

    function appendCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
        row.appendChild(cell);
    }

    function formatUploadedDate(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) {
            return value;
        }

        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            day: '2-digit',
            year: 'numeric'
        }).format(date);
    }

    function appendViewAction(row, item) {
        const cell = document.createElement('td');
        const hasLinkedData = String(item.has_linked_data || '') === '1';

        if (!hasLinkedData) {
            row.appendChild(cell);
            return;
        }

        const button = document.createElement('button');
        const icon = document.createElement('span');

        button.type = 'button';
        button.className = 'uploaded-file-logs-view-btn';
        button.dataset.logId = String(item.id || '');
        button.dataset.partnerName = String(item.partner_name || '');
        button.dataset.filename = String(item.filename || '');
        button.dataset.remark = String(item.has_overwrite || '') === '1' ? 'Overwritten' : 'Success';
        button.dataset.uploadedDate = formatUploadedDate(item.uploaded_date);
        button.dataset.uploadedBy = String(item.uploader_name || '');
        button.title = 'View';
        button.setAttribute('aria-label', 'View ' + String(item.filename || 'uploaded file'));

        icon.className = 'material-icons-outlined';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = 'visibility';

        button.appendChild(icon);
        cell.appendChild(button);
        row.appendChild(cell);
    }

    function appendRemark(row, item) {
        const cell = document.createElement('td');
        const badge = document.createElement('span');
        const hasLinkedData = String(item.has_linked_data || '') === '1';
        const isOverwritten = String(item.has_overwrite || '') === '1';
        let remark = 'Success';
        let remarkClass = 'uploaded-file-logs-remark--success';

        if (!hasLinkedData) {
            remark = 'Deleted';
            remarkClass = 'uploaded-file-logs-remark--deleted';
        } else if (isOverwritten) {
            remark = 'Overwritten';
            remarkClass = 'uploaded-file-logs-remark--overwritten';
        }

        badge.className = 'uploaded-file-logs-remark ' + remarkClass;
        badge.textContent = remark;

        cell.appendChild(badge);
        row.appendChild(cell);
    }

    function renderRecordsMessage(message, isError) {
        if (!recordsHead || !recordsBody) {
            return;
        }

        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.className = 'uploaded-file-logs-records-message' +
            (isError ? ' uploaded-file-logs-error' : '');
        cell.textContent = message;
        row.appendChild(cell);
        recordsHead.replaceChildren();
        recordsBody.replaceChildren(row);
    }

    function formatRecordValue(field, value) {
        const monetaryFields = ['amount', 'ctc', 'ctp', 'charge'];
        if (!monetaryFields.includes(field) || value === null || value === undefined || value === '') {
            return value;
        }

        const number = Number(String(value).replace(/,/g, ''));
        if (!Number.isFinite(number)) {
            return value;
        }

        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(number);
    }

    function renderRecordTable(columns, rows) {
        if (!recordsHead || !recordsBody) {
            return;
        }

        const headingRow = document.createElement('tr');
        columns.forEach(function (column) {
            const heading = document.createElement('th');
            heading.scope = 'col';
            heading.textContent = column.label;
            headingRow.appendChild(heading);
        });
        recordsHead.replaceChildren(headingRow);
        recordsBody.replaceChildren();

        if (!rows.length) {
            const emptyRow = document.createElement('tr');
            const emptyCell = document.createElement('td');
            emptyCell.colSpan = columns.length;
            emptyCell.className = 'uploaded-file-logs-records-message';
            emptyCell.textContent = recordsSearch && recordsSearch.value.trim() !== ''
                ? 'No matching records found.'
                : 'No linked records found.';
            emptyRow.appendChild(emptyCell);
            recordsBody.appendChild(emptyRow);
            return;
        }

        rows.forEach(function (record) {
            const row = document.createElement('tr');
            columns.forEach(function (column) {
                appendCell(row, formatRecordValue(column.field, record[column.field]));
            });
            recordsBody.appendChild(row);
        });
    }

    function filterUploadedFileRecords() {
        const query = recordsSearch ? recordsSearch.value.trim().toLocaleLowerCase() : '';
        if (query === '') {
            renderRecordTable(loadedRecordColumns, loadedRecordRows);
            return;
        }

        const filteredRows = loadedRecordRows.filter(function (record) {
            return loadedRecordColumns.some(function (column) {
                return String(record[column.field] ?? '').toLocaleLowerCase().includes(query);
            });
        });
        renderRecordTable(loadedRecordColumns, filteredRows);
    }

    async function loadUploadedFileRecords(logId) {
        if (activeDetailRequest) {
            activeDetailRequest.abort();
        }
        activeDetailRequest = new AbortController();
        renderRecordsMessage('Loading uploaded records…', false);

        try {
            const url = window.autoreconUrl('src/controllers/uploaded-file-log-details.php') +
                '?' + new URLSearchParams({ id: logId }).toString();
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: activeDetailRequest.signal
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to load the uploaded file records.');
            }

            loadedRecordColumns = Array.isArray(result.columns) ? result.columns : [];
            loadedRecordRows = Array.isArray(result.rows) ? result.rows : [];
            filterUploadedFileRecords();
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderRecordsMessage(error.message || 'Unable to load the uploaded file records.', true);
            }
        }
    }

    function openViewModal(button) {
        if (!viewModal) {
            return;
        }

        const setDetail = function (element, value) {
            if (element) {
                element.textContent = value || '—';
            }
        };

        setDetail(detailPartner, button.dataset.partnerName);
        setDetail(detailFilename, button.dataset.filename);
        setDetail(detailRemark, button.dataset.remark);
        if (detailRemark) {
            const isOverwritten = button.dataset.remark === 'Overwritten';
            detailRemark.classList.toggle('uploaded-file-logs-remark--overwritten', isOverwritten);
            detailRemark.classList.toggle('uploaded-file-logs-remark--success', !isOverwritten);
        }
        setDetail(detailDate, button.dataset.uploadedDate);
        setDetail(detailUploader, button.dataset.uploadedBy);
        window.clearTimeout(recordsSearchTimer);
        loadedRecordColumns = [];
        loadedRecordRows = [];
        if (recordsSearch) {
            recordsSearch.value = '';
        }

        viewModalTrigger = button;
        viewModal.hidden = false;
        document.body.classList.add('uploaded-file-logs-modal-open');
        loadUploadedFileRecords(button.dataset.logId);

        if (viewModalClose) {
            viewModalClose.focus();
        }
    }

    function closeViewModal() {
        if (!viewModal || viewModal.hidden) {
            return;
        }

        viewModal.hidden = true;
        document.body.classList.remove('uploaded-file-logs-modal-open');
        if (activeDetailRequest) {
            activeDetailRequest.abort();
            activeDetailRequest = null;
        }

        if (viewModalTrigger && document.body.contains(viewModalTrigger)) {
            viewModalTrigger.focus();
        }
        viewModalTrigger = null;
    }

    function renderRows(targetBody, rows, emptyLabel) {
        targetBody.replaceChildren();

        if (!rows.length) {
            const emptyRow = document.createElement('tr');
            const emptyCell = document.createElement('td');
            emptyCell.colSpan = 7;
            emptyCell.className = 'uploaded-file-logs-empty';
            emptyCell.textContent = 'No ' + emptyLabel + ' uploaded files found.';
            emptyRow.appendChild(emptyCell);
            targetBody.appendChild(emptyRow);
            return;
        }

        rows.forEach(function (item) {
            const row = document.createElement('tr');
            appendCell(row, formatUploadedDate(item.uploaded_date));
            appendCell(row, item.filename);
            appendCell(row, item.filename_ext);
            appendCell(row, item.partner_name);
            appendCell(row, item.uploader_name);
            appendRemark(row, item);
            appendViewAction(row, item);
            targetBody.appendChild(row);
        });
    }

    function renderMessage(targetBody, message, isError) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 7;
        cell.className = 'uploaded-file-logs-empty' + (isError ? ' uploaded-file-logs-error' : '');
        cell.textContent = message;
        row.appendChild(cell);
        targetBody.replaceChildren(row);
    }

    async function loadUploadedFileLogs() {
        const selectedSource = document.querySelector('input[name="uploaded_file_log_source"]:checked');
        const source = selectedSource ? selectedSource.value : 'kpx_web_data';
        const isPartner = source === 'partner_data';
        const targetBody = isPartner ? partnerTableBody : tableBody;
        const selectedState = document.querySelector('input[name="uploaded_file_log_state"]:checked');
        const selectedPartnerState = document.querySelector('input[name="partner_uploaded_file_log_state"]:checked');
        const state = isPartner
            ? (selectedPartnerState ? selectedPartnerState.value : 'all')
            : (selectedState ? selectedState.value : 'all');
        const month = isPartner ? partnerMonthField : monthField;
        const search = isPartner ? partnerSearchField : searchField;

        if (!targetBody) {
            return;
        }

        if (activeRequest) {
            activeRequest.abort();
        }
        activeRequest = new AbortController();
        renderMessage(targetBody, 'Loading uploaded files…', false);

        const params = new URLSearchParams({
            source: source,
            state: state,
            month: month ? month.value : '',
            search: search ? search.value.trim() : ''
        });

        try {
            const response = await fetch(
                window.autoreconUrl('src/controllers/uploaded-file-logs.php') + '?' + params.toString(),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: activeRequest.signal
                }
            );
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to load uploaded file logs.');
            }

            const emptyLabel = isPartner
                ? getSelectedStateLabel('partner_uploaded_file_log_state')
                : getSelectedStateLabel('uploaded_file_log_state');
            renderRows(targetBody, Array.isArray(result.rows) ? result.rows : [], emptyLabel);
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderMessage(targetBody, error.message || 'Unable to load uploaded file logs.', true);
            }
        }
    }

    function updateSourceVisibility() {
        const selectedSource = document.querySelector('input[name="uploaded_file_log_source"]:checked');
        const selectedSourceValue = selectedSource ? selectedSource.value : 'kpx_web_data';

        sourceCards.forEach(function (card) {
            card.hidden = card.getAttribute('data-log-source-card') !== selectedSourceValue;
        });

        loadUploadedFileLogs();
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', updateSourceVisibility);
    });

    stateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTableState('uploaded_file_log_state', tableTitle);
            loadUploadedFileLogs();
        });
    });

    partnerStateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTableState('partner_uploaded_file_log_state', null);
            loadUploadedFileLogs();
        });
    });

    function queueSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(loadUploadedFileLogs, 250);
    }

    [monthField, partnerMonthField].forEach(function (field) {
        if (field) {
            field.addEventListener('change', loadUploadedFileLogs);
        }
    });

    [searchField, partnerSearchField].forEach(function (field) {
        if (field) {
            field.addEventListener('input', queueSearch);
        }
    });

    [tableBody, partnerTableBody].forEach(function (body) {
        if (body) {
            body.addEventListener('click', function (event) {
                const button = event.target.closest('.uploaded-file-logs-view-btn');
                if (button) {
                    openViewModal(button);
                }
            });
        }
    });

    if (viewModalClose) {
        viewModalClose.addEventListener('click', closeViewModal);
    }

    if (viewModal) {
        viewModal.addEventListener('click', function (event) {
            if (event.target === viewModal) {
                closeViewModal();
            }
        });
    }

    if (recordsSearch) {
        recordsSearch.addEventListener('input', function () {
            window.clearTimeout(recordsSearchTimer);
            recordsSearchTimer = window.setTimeout(filterUploadedFileRecords, 150);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && viewModal && !viewModal.hidden) {
            closeViewModal();
        }
    });

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (monthField) {
                monthField.value = '';
            }

            if (searchField) {
                searchField.value = '';
            }
            loadUploadedFileLogs();
        });
    }

    if (partnerClearButton) {
        partnerClearButton.addEventListener('click', function () {
            if (partnerMonthField) {
                partnerMonthField.value = '';
            }

            if (partnerSearchField) {
                partnerSearchField.value = '';
            }
            loadUploadedFileLogs();
        });
    }

    updateTableState('uploaded_file_log_state', tableTitle);
    updateSourceVisibility();
});
</script>
