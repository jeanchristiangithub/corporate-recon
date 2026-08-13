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

            <div class="uploaded-file-logs-process-card" aria-label="Uploaded file log processing filters">
                <label class="uploaded-file-logs-process-field uploaded-file-logs-process-field--partner">
                    <span>Corporate Partner</span>
                    <div class="uploaded-file-logs-partner-autocomplete">
                        <input type="text"
                               id="uploadedFileLogsPartner"
                               role="combobox"
                               aria-autocomplete="list"
                               aria-controls="uploadedFileLogsPartnerList"
                               aria-expanded="false"
                               placeholder="Select corporate partner"
                               autocomplete="off">
                        <ul id="uploadedFileLogsPartnerList"
                            class="uploaded-file-logs-partner-list"
                            role="listbox"
                            hidden></ul>
                    </div>
                </label>
                <label class="uploaded-file-logs-process-field">
                    <span>Start Date</span>
                    <input type="date" id="uploadedFileLogsStartDate" value="<?= date('Y-m-d') ?>">
                </label>
                <label class="uploaded-file-logs-process-field">
                    <span>End Date</span>
                    <input type="date" id="uploadedFileLogsEndDate" value="<?= date('Y-m-d') ?>">
                </label>
                <button type="button" id="uploadedFileLogsProcessBtn" class="uploaded-file-logs-process-btn">Process</button>
            </div>

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
                            <label class="uploaded-file-logs-field uploaded-file-logs-field--search">
                                <span>Search:</span>
                                <input type="search" id="uploadedFileLogsSearch" name="uploaded_file_log_search" placeholder="Search files">
                            </label>
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
                    <div id="uploadedFileLogsPagination" class="uploaded-file-logs-pagination" hidden></div>
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
                            <label class="uploaded-file-logs-field uploaded-file-logs-field--search">
                                <span>Search:</span>
                                <input type="search" id="partnerUploadedFileLogsSearch" name="partner_uploaded_file_log_search" placeholder="Search files">
                            </label>
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
                    <div id="partnerUploadedFileLogsPagination" class="uploaded-file-logs-pagination" hidden></div>
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
    const pagination = document.getElementById('uploadedFileLogsPagination');
    const partnerPagination = document.getElementById('partnerUploadedFileLogsPagination');
    const searchField = document.getElementById('uploadedFileLogsSearch');
    const partnerSearchField = document.getElementById('partnerUploadedFileLogsSearch');
    const processPartnerField = document.getElementById('uploadedFileLogsPartner');
    const processPartnerList = document.getElementById('uploadedFileLogsPartnerList');
    const processStartDate = document.getElementById('uploadedFileLogsStartDate');
    const processEndDate = document.getElementById('uploadedFileLogsEndDate');
    const processButton = document.getElementById('uploadedFileLogsProcessBtn');
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
    let activePartnerRequest = null;
    let searchTimer = null;
    let recordsSearchTimer = null;
    let partnerLookupTimer = null;
    let viewModalTrigger = null;
    let loadedRecordColumns = [];
    let loadedRecordRows = [];
    let corporatePartnerOptions = [];
    let activePartnerIndex = -1;
    let appliedProcessFilters = {
        partner: '',
        startDate: '',
        endDate: ''
    };

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
        const match = String(value || '').match(
            /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/
        );
        if (!match) {
            return value;
        }

        const date = new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4] || 0),
            Number(match[5] || 0),
            Number(match[6] || 0)
        );
        const formattedDate = new Intl.DateTimeFormat('en-US', {
            month: 'long',
            day: '2-digit',
            year: 'numeric'
        }).format(date);
        const hours = date.getHours();
        const meridiem = hours >= 12 ? 'PM' : 'AM';
        const twelveHour = hours % 12 || 12;
        const formattedTime = [twelveHour, date.getMinutes(), date.getSeconds()]
            .map(function (part) { return String(part).padStart(2, '0'); })
            .join(':');

        return formattedDate + ' ' + formattedTime + ' ' + meridiem;
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

    function paginationPageList(currentPage, totalPages) {
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, function (_, index) { return index + 1; });
        }

        const pages = [1];
        const rangeStart = Math.max(2, currentPage - 1);
        const rangeEnd = Math.min(totalPages - 1, currentPage + 1);
        if (rangeStart > 2) {
            pages.push('ellipsis-start');
        }
        for (let page = rangeStart; page <= rangeEnd; page += 1) {
            pages.push(page);
        }
        if (rangeEnd < totalPages - 1) {
            pages.push('ellipsis-end');
        }
        pages.push(totalPages);
        return pages;
    }

    function renderLogPagination(target, metadata) {
        if (!target) {
            return;
        }

        const total = Number(metadata.total || 0);
        const currentPage = Number(metadata.page || 1);
        const pageSize = Number(metadata.page_size || 10);
        const totalPages = Number(metadata.total_pages || 1);
        target.replaceChildren();

        if (total === 0) {
            target.hidden = true;
            return;
        }

        const start = ((currentPage - 1) * pageSize) + 1;
        const end = Math.min(currentPage * pageSize, total);
        const summary = document.createElement('span');
        summary.className = 'uploaded-file-logs-pagination-summary';
        summary.textContent = 'Showing ' + start.toLocaleString() + '–' + end.toLocaleString() +
            ' of ' + total.toLocaleString();

        const controls = document.createElement('div');
        controls.className = 'uploaded-file-logs-pagination-controls';

        function addButton(label, page, disabled, isCurrent) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'uploaded-file-logs-page-btn' + (isCurrent ? ' is-current' : '');
            button.textContent = label;
            button.dataset.page = String(page);
            button.disabled = disabled;
            if (isCurrent) {
                button.setAttribute('aria-current', 'page');
            }
            controls.appendChild(button);
        }

        addButton('Previous', currentPage - 1, currentPage <= 1, false);
        paginationPageList(currentPage, totalPages).forEach(function (page) {
            if (typeof page !== 'number') {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'uploaded-file-logs-pagination-ellipsis';
                ellipsis.textContent = '…';
                controls.appendChild(ellipsis);
                return;
            }
            addButton(String(page), page, false, page === currentPage);
        });
        addButton('Next', currentPage + 1, currentPage >= totalPages, false);

        target.append(summary, controls);
        target.hidden = false;
    }

    async function loadUploadedFileLogs(requestedPage) {
        const selectedSource = document.querySelector('input[name="uploaded_file_log_source"]:checked');
        const source = selectedSource ? selectedSource.value : 'kpx_web_data';
        const isPartner = source === 'partner_data';
        const targetBody = isPartner ? partnerTableBody : tableBody;
        const selectedState = document.querySelector('input[name="uploaded_file_log_state"]:checked');
        const selectedPartnerState = document.querySelector('input[name="partner_uploaded_file_log_state"]:checked');
        const state = isPartner
            ? (selectedPartnerState ? selectedPartnerState.value : 'all')
            : (selectedState ? selectedState.value : 'all');
        const search = isPartner ? partnerSearchField : searchField;
        const targetPagination = isPartner ? partnerPagination : pagination;
        const page = Number.isInteger(Number(requestedPage)) && Number(requestedPage) > 0
            ? Number(requestedPage)
            : 1;

        if (!targetBody) {
            return;
        }

        if (activeRequest) {
            activeRequest.abort();
        }
        activeRequest = new AbortController();
        renderMessage(targetBody, 'Loading uploaded files…', false);
        if (targetPagination) {
            targetPagination.hidden = true;
        }

        const params = new URLSearchParams({
            source: source,
            state: state,
            search: search ? search.value.trim() : '',
            partner: appliedProcessFilters.partner,
            start_date: appliedProcessFilters.startDate,
            end_date: appliedProcessFilters.endDate,
            page: String(page),
            page_size: '10'
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
            renderLogPagination(targetPagination, result.pagination || {});
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

    async function loadCorporatePartners(query) {
        if (!processPartnerList) {
            return;
        }

        if (activePartnerRequest) {
            activePartnerRequest.abort();
        }
        activePartnerRequest = new AbortController();

        try {
            const params = new URLSearchParams();
            if (query) {
                params.set('q', query);
            }
            const queryString = params.toString();
            const response = await fetch(
                window.autoreconUrl('src/controllers/masterdata/corpo-partner-values.php') +
                    (queryString ? '?' + queryString : ''),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: activePartnerRequest.signal
                }
            );
            const result = await response.json();
            if (!response.ok || !result.success || !Array.isArray(result.values)) {
                return;
            }

            corporatePartnerOptions = result.values.map(function (partner) {
                return String(partner || '').trim();
            }).filter(Boolean);
            renderCorporatePartnerSuggestions();
        } catch (error) {
            // The filter remains usable as a text field if suggestions cannot be loaded.
        }
    }

    function renderCorporatePartnerSuggestions() {
        if (!processPartnerList || !processPartnerField) {
            return;
        }

        activePartnerIndex = -1;
        processPartnerList.replaceChildren();
        const fragment = document.createDocumentFragment();

        if (!corporatePartnerOptions.length) {
            const empty = document.createElement('li');
            empty.className = 'uploaded-file-logs-partner-empty';
            empty.textContent = 'No corporate partner found';
            fragment.appendChild(empty);
        } else {
            corporatePartnerOptions.forEach(function (partner, index) {
                const option = document.createElement('li');
                option.id = 'uploadedFileLogsPartnerOption' + index;
                option.dataset.partner = partner;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = partner;
                fragment.appendChild(option);
            });
        }

        processPartnerList.appendChild(fragment);
        if (document.activeElement === processPartnerField) {
            processPartnerList.hidden = false;
            processPartnerField.setAttribute('aria-expanded', 'true');
        }
    }

    function closeCorporatePartnerSuggestions() {
        if (!processPartnerList || !processPartnerField) {
            return;
        }
        processPartnerList.hidden = true;
        processPartnerField.setAttribute('aria-expanded', 'false');
        processPartnerField.removeAttribute('aria-activedescendant');
        activePartnerIndex = -1;
    }

    function moveCorporatePartnerSuggestion(direction) {
        const options = Array.from(processPartnerList.querySelectorAll('[data-partner]'));
        if (!options.length) {
            return;
        }

        activePartnerIndex = (activePartnerIndex + direction + options.length) % options.length;
        options.forEach(function (option, index) {
            const isActive = index === activePartnerIndex;
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        const activeOption = options[activePartnerIndex];
        processPartnerField.setAttribute('aria-activedescendant', activeOption.id);
        activeOption.scrollIntoView({ block: 'nearest' });
    }

    function selectCorporatePartnerSuggestion(option) {
        if (!option || !processPartnerField) {
            return;
        }
        processPartnerField.value = option.dataset.partner || '';
        closeCorporatePartnerSuggestions();
    }

    function processUploadedFileLogFilters() {
        const startDate = processStartDate ? processStartDate.value : '';
        const endDate = processEndDate ? processEndDate.value : '';

        if (startDate && endDate && startDate > endDate) {
            processEndDate.setCustomValidity('End Date must be on or after Start Date.');
            processEndDate.reportValidity();
            return;
        }

        if (processEndDate) {
            processEndDate.setCustomValidity('');
        }
        appliedProcessFilters = {
            partner: processPartnerField ? processPartnerField.value.trim() : '',
            startDate: startDate,
            endDate: endDate
        };
        loadUploadedFileLogs();
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', updateSourceVisibility);
    });

    if (processButton) {
        processButton.addEventListener('click', processUploadedFileLogFilters);
    }

    [processStartDate, processEndDate].forEach(function (field) {
        if (field) {
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    processUploadedFileLogFilters();
                }
            });
        }
    });

    if (processPartnerField) {
        processPartnerField.addEventListener('input', function () {
            window.clearTimeout(partnerLookupTimer);
            partnerLookupTimer = window.setTimeout(function () {
                loadCorporatePartners(processPartnerField.value.trim());
            }, 150);
        });
        processPartnerField.addEventListener('focus', function () {
            if (corporatePartnerOptions.length) {
                renderCorporatePartnerSuggestions();
            } else {
                loadCorporatePartners(processPartnerField.value.trim());
            }
        });
        processPartnerField.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (processPartnerList.hidden) {
                    renderCorporatePartnerSuggestions();
                }
                moveCorporatePartnerSuggestion(event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const options = processPartnerList.querySelectorAll('[data-partner]');
                if (!processPartnerList.hidden && activePartnerIndex >= 0 && options[activePartnerIndex]) {
                    selectCorporatePartnerSuggestion(options[activePartnerIndex]);
                } else {
                    closeCorporatePartnerSuggestions();
                    processUploadedFileLogFilters();
                }
                return;
            }
            if (event.key === 'Escape') {
                closeCorporatePartnerSuggestions();
            }
        });
    }

    if (processPartnerList) {
        processPartnerList.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });
        processPartnerList.addEventListener('click', function (event) {
            selectCorporatePartnerSuggestion(event.target.closest('[data-partner]'));
        });
    }

    document.addEventListener('click', function (event) {
        if (processPartnerField && processPartnerList &&
            !event.target.closest('.uploaded-file-logs-partner-autocomplete')) {
            closeCorporatePartnerSuggestions();
        }
    });

    if (processStartDate && processEndDate) {
        processEndDate.min = processStartDate.value;
        processStartDate.addEventListener('change', function () {
            processEndDate.min = processStartDate.value;
            if (processStartDate.value) {
                processEndDate.value = processStartDate.value;
            }
            processEndDate.setCustomValidity('');
        });
        processEndDate.addEventListener('change', function () {
            processEndDate.setCustomValidity('');
        });
    }

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

    [pagination, partnerPagination].forEach(function (paginationElement) {
        if (paginationElement) {
            paginationElement.addEventListener('click', function (event) {
                const button = event.target.closest('[data-page]');
                if (!button || button.disabled) {
                    return;
                }
                loadUploadedFileLogs(Number(button.dataset.page));
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

    updateTableState('uploaded_file_log_state', tableTitle);
    loadCorporatePartners('');
    updateSourceVisibility();
});
</script>
