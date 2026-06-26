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
                        <div class="uploaded-file-logs-options uploaded-file-logs-options--state">
                            <label class="uploaded-file-logs-radio">
                                <input type="radio" name="uploaded_file_log_state" value="payout" checked>
                                <span>PAYOUT</span>
                            </label>
                            <label class="uploaded-file-logs-radio">
                                <input type="radio" name="uploaded_file_log_state" value="sendout">
                                <span>SENDOUT</span>
                            </label>
                            <label class="uploaded-file-logs-radio">
                                <input type="radio" name="uploaded_file_log_state" value="payout_cancel">
                                <span>PAYOUT CANCELLATION</span>
                            </label>
                            <label class="uploaded-file-logs-radio">
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="uploadedFileLogsTableBody">
                                <tr>
                                    <td colspan="6" class="uploaded-file-logs-empty">No PAYOUT uploaded files found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="uploaded-file-logs-state-card" data-log-source-card="partner_data" hidden>
                <fieldset class="uploaded-file-logs-group uploaded-file-logs-group--state">
                    <div class="uploaded-file-logs-state-toolbar">
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="partnerUploadedFileLogsTableBody">
                                <tr>
                                    <td colspan="6" class="uploaded-file-logs-empty">No Partner Data uploaded files found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sourceRadios = document.querySelectorAll('input[name="uploaded_file_log_source"]');
    const stateRadios = document.querySelectorAll('input[name="uploaded_file_log_state"]');
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

    const stateLabels = {
        payout: 'PAYOUT',
        sendout: 'SENDOUT',
        payout_cancel: 'PAYOUT CANCELLATION',
        sendout_cancel: 'SENDOUT CANCELLATION'
    };

    if (!sourceRadios.length || !sourceCards.length) {
        return;
    }

    function getSelectedStateLabel(radioName) {
        const selectedState = document.querySelector('input[name="' + radioName + '"]:checked');
        return stateLabels[selectedState ? selectedState.value : 'payout'] || 'PAYOUT';
    }

    function updateTableState(radioName, activeTableTitle, activeTableBody) {
        const stateLabel = getSelectedStateLabel(radioName);

        if (activeTableTitle) {
            activeTableTitle.textContent = stateLabel + ' Uploaded Files';
        }

        if (activeTableBody) {
            activeTableBody.innerHTML = '<tr><td colspan="6" class="uploaded-file-logs-empty">No ' + stateLabel + ' uploaded files found.</td></tr>';
        }
    }

    function updateSourceVisibility() {
        const selectedSource = document.querySelector('input[name="uploaded_file_log_source"]:checked');
        const selectedSourceValue = selectedSource ? selectedSource.value : 'kpx_web_data';

        sourceCards.forEach(function (card) {
            card.hidden = card.getAttribute('data-log-source-card') !== selectedSourceValue;
        });
    }

    sourceRadios.forEach(function (radio) {
        radio.addEventListener('change', updateSourceVisibility);
    });

    stateRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            updateTableState('uploaded_file_log_state', tableTitle, tableBody);
        });
    });

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (monthField) {
                monthField.value = '';
            }

            if (searchField) {
                searchField.value = '';
            }
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
        });
    }

    updateTableState('uploaded_file_log_state', tableTitle, tableBody);
    if (partnerTableBody) {
        partnerTableBody.innerHTML = '<tr><td colspan="6" class="uploaded-file-logs-empty">No Partner Data uploaded files found.</td></tr>';
    }
    updateSourceVisibility();
});
</script>
