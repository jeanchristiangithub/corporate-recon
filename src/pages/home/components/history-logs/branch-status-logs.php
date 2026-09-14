<section id="branchStatusLogsSection" class="branch-status-logs-section" aria-label="Branch Status Logs" style="display:none; padding:1rem">
    <h2 class="branch-status-logs-title">Branch Status Logs</h2>

    <form id="branchStatusLogsFilterForm" class="branch-status-logs-filter-card">
        <label class="branch-status-logs-field" for="branchStatusLogsSearch">
            <span>Search</span>
            <div class="branch-status-logs-autocomplete">
                <input
                    id="branchStatusLogsSearch"
                    name="branch"
                    type="search"
                    placeholder="Enter Branch ID or Branch Name"
                    autocomplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls="branchStatusLogsSuggestions"
                    aria-expanded="false"
                >
                <ul id="branchStatusLogsSuggestions" role="listbox" hidden></ul>
            </div>
        </label>

        <div class="branch-status-logs-actions">
            <button id="branchStatusLogsDisplay" class="branch-status-logs-button branch-status-logs-button--primary" type="submit" disabled>
                Display
            </button>
            <button id="branchStatusLogsDisplayAll" class="branch-status-logs-button branch-status-logs-button--secondary" type="button">
                Display All
            </button>
        </div>
    </form>

    <div id="branchStatusLogsLoadingStatus" class="branch-status-logs-loading-status" role="status" aria-live="polite" hidden>
        Loading display for Branch status logs...
    </div>

    <div id="branchStatusLogsDropdownFilters" class="branch-status-logs-dropdown-filters" aria-label="Branch location filters" hidden>
        <label class="branch-status-logs-select-field" for="branchStatusLogsMainzone">
            <span>Mainzone</span>
            <select id="branchStatusLogsMainzone" name="mainzone">
                <option value="">Select Mainzone</option>
            </select>
        </label>

        <label class="branch-status-logs-select-field" for="branchStatusLogsZone">
            <span>Zone</span>
            <select id="branchStatusLogsZone" name="zone">
                <option value="">Select Zone</option>
            </select>
        </label>

        <label class="branch-status-logs-select-field" for="branchStatusLogsRegion">
            <span>Region</span>
            <select id="branchStatusLogsRegion" name="region">
                <option value="">Select Region</option>
            </select>
        </label>
    </div>

    <div id="branchStatusLogsCountCard" class="branch-status-logs-count-card" aria-live="polite" hidden>
        <span>Branch Count</span>
        <strong id="branchStatusLogsCount">0</strong>
    </div>

    <section id="branchStatusLogsTableCard" class="branch-status-logs-table-card" aria-label="Branch status log results" hidden>
        <div class="branch-status-logs-table-wrap">
            <table id="branchStatusLogsTable" class="branch-status-logs-table">
                <thead>
                    <tr>
                        <th scope="col">Branch ID</th>
                        <th scope="col">Branch Name</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="branchStatusLogsTableBody">
                    <tr class="branch-status-logs-empty-row">
                        <td colspan="3">Use the filters above to display branch status logs.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</section>

<div id="branchStatusLogsDetailsModal" class="branch-status-logs-modal" hidden>
    <div class="branch-status-logs-modal__backdrop" data-branch-status-modal-close></div>
    <section
        class="branch-status-logs-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="branchStatusLogsDetailsTitle"
    >
        <header class="branch-status-logs-modal__header">
            <h3 id="branchStatusLogsDetailsTitle">Branch Status History Details</h3>
            <button
                id="branchStatusLogsDetailsClose"
                class="branch-status-logs-modal__close"
                type="button"
                aria-label="Close branch status history details"
                title="Close"
            >
                <span class="material-icons" aria-hidden="true">close</span>
            </button>
        </header>

        <div class="branch-status-logs-modal__body">
            <section class="branch-status-logs-modal__section" aria-labelledby="branchStatusLogsPresentDataTitle">
                <h4 id="branchStatusLogsPresentDataTitle">Present Data</h4>
                <dl class="branch-status-logs-modal__details">
                    <div>
                        <dt>Posted Date</dt>
                        <dd id="branchStatusLogsDetailsPostedDate">—</dd>
                    </div>
                    <div>
                        <dt>Branch Name</dt>
                        <dd id="branchStatusLogsDetailsBranchName">—</dd>
                    </div>
                    <div>
                        <dt>Branch ID</dt>
                        <dd id="branchStatusLogsDetailsBranchId">—</dd>
                    </div>
                    <div>
                        <dt>BOS Code</dt>
                        <dd id="branchStatusLogsDetailsBosCode">—</dd>
                    </div>
                    <div>
                        <dt>Branch Status</dt>
                        <dd id="branchStatusLogsDetailsBranchStatus">—</dd>
                    </div>
                    <div>
                        <dt>Corporate Name</dt>
                        <dd id="branchStatusLogsDetailsCorporateName">—</dd>
                    </div>
                    <div>
                        <dt>Mainzone</dt>
                        <dd id="branchStatusLogsDetailsMainzone">—</dd>
                    </div>
                    <div>
                        <dt>Posted By</dt>
                        <dd id="branchStatusLogsDetailsPostedBy">—</dd>
                    </div>
                    <div>
                        <dt>Zone</dt>
                        <dd id="branchStatusLogsDetailsZone">—</dd>
                    </div>
                    <div>
                        <dt>Region Name 1</dt>
                        <dd id="branchStatusLogsDetailsRegionName1">—</dd>
                    </div>
                    <div>
                        <dt>Region Name 2</dt>
                        <dd id="branchStatusLogsDetailsRegionName2">—</dd>
                    </div>
                    <div>
                        <dt>Area</dt>
                        <dd id="branchStatusLogsDetailsArea">—</dd>
                    </div>
                </dl>
            </section>

            <section class="branch-status-logs-modal__section" aria-labelledby="branchStatusLogsRecordedHistoryTitle">
                <h4 id="branchStatusLogsRecordedHistoryTitle">Recorded History Data</h4>
                <div class="branch-status-logs-modal__table-wrap">
                    <table class="branch-status-logs-modal__history-table">
                        <thead>
                            <tr>
                                <th scope="col">Posted Date</th>
                                <th scope="col">Branch ID</th>
                                <th scope="col">BOS Code</th>
                                <th scope="col">Branch Name</th>
                                <th scope="col">Area</th>
                                <th scope="col">Corporate Name</th>
                                <th scope="col">Mainzone</th>
                                <th scope="col">Zone</th>
                                <th scope="col">Region Name 1</th>
                                <th scope="col">Region Name 2</th>
                                <th scope="col">Branch Status</th>
                                <th scope="col">Posted By</th>
                            </tr>
                        </thead>
                        <tbody id="branchStatusLogsHistoryTableBody">
                            <tr class="branch-status-logs-modal__empty-row">
                                <td colspan="12">Recorded history data will be displayed here.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</div>

<script>
(function () {
    const filterForm = document.getElementById('branchStatusLogsFilterForm');
    const searchInput = document.getElementById('branchStatusLogsSearch');
    const suggestions = document.getElementById('branchStatusLogsSuggestions');
    const displayButton = document.getElementById('branchStatusLogsDisplay');
    const displayAllButton = document.getElementById('branchStatusLogsDisplayAll');
    const loadingStatus = document.getElementById('branchStatusLogsLoadingStatus');
    const tableBody = document.getElementById('branchStatusLogsTableBody');
    const mainzoneSelect = document.getElementById('branchStatusLogsMainzone');
    const zoneSelect = document.getElementById('branchStatusLogsZone');
    const regionSelect = document.getElementById('branchStatusLogsRegion');
    const dropdownFilters = document.getElementById('branchStatusLogsDropdownFilters');
    const countCard = document.getElementById('branchStatusLogsCountCard');
    const branchCount = document.getElementById('branchStatusLogsCount');
    const tableCard = document.getElementById('branchStatusLogsTableCard');
    const detailsModal = document.getElementById('branchStatusLogsDetailsModal');
    const detailsCloseButton = document.getElementById('branchStatusLogsDetailsClose');
    const detailsPostedDate = document.getElementById('branchStatusLogsDetailsPostedDate');
    const detailsBranchId = document.getElementById('branchStatusLogsDetailsBranchId');
    const detailsBranchName = document.getElementById('branchStatusLogsDetailsBranchName');
    const detailsBosCode = document.getElementById('branchStatusLogsDetailsBosCode');
    const detailsBranchStatus = document.getElementById('branchStatusLogsDetailsBranchStatus');
    const detailsCorporateName = document.getElementById('branchStatusLogsDetailsCorporateName');
    const detailsMainzone = document.getElementById('branchStatusLogsDetailsMainzone');
    const detailsPostedBy = document.getElementById('branchStatusLogsDetailsPostedBy');
    const detailsZone = document.getElementById('branchStatusLogsDetailsZone');
    const detailsRegionName1 = document.getElementById('branchStatusLogsDetailsRegionName1');
    const detailsRegionName2 = document.getElementById('branchStatusLogsDetailsRegionName2');
    const detailsArea = document.getElementById('branchStatusLogsDetailsArea');
    const historyTableBody = document.getElementById('branchStatusLogsHistoryTableBody');

    if (!filterForm || !searchInput || !suggestions || !displayButton ||
        !displayAllButton || !loadingStatus || !tableBody || !detailsModal ||
        !mainzoneSelect || !zoneSelect || !regionSelect || !dropdownFilters || !countCard ||
        !branchCount || !tableCard ||
        !detailsCloseButton || !detailsPostedDate || !detailsBranchId || !detailsBranchName ||
        !detailsBosCode || !detailsBranchStatus || !detailsCorporateName || !detailsMainzone ||
        !detailsPostedBy ||
        !detailsZone || !detailsRegionName1 || !detailsRegionName2 || !detailsArea ||
        !historyTableBody) return;

    let activeIndex = -1;
    let visibleBranches = [];
    let searchTimer = null;
    let requestController = null;
    let historyRequestController = null;
    let zoningOptions = { mainzones: [], zones: [], regions: [] };
    let modalTrigger = null;

    document.body.appendChild(detailsModal);

    function updateButtonStates() {
        const hasSearchValue = searchInput.value.trim() !== '';
        displayButton.disabled = !hasSearchValue;
        displayAllButton.disabled = hasSearchValue;
    }

    function replaceSelectOptions(select, placeholder, options) {
        select.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = placeholder;
        select.appendChild(defaultOption);

        options.forEach(function (item) {
            const option = document.createElement('option');
            option.value = typeof item === 'string' ? item : item.value;
            option.textContent = typeof item === 'string' ? item : item.label;
            select.appendChild(option);
        });
    }

    function updateZoneOptions() {
        const mainzone = mainzoneSelect.value;
        const zones = zoningOptions.zones
            .filter(function (item) { return !mainzone || item.mainzone === mainzone; })
            .map(function (item) { return item.value; })
            .filter(function (value, index, values) { return values.indexOf(value) === index; });
        replaceSelectOptions(zoneSelect, 'Select Zone', zones);
        updateRegionOptions();
    }

    function updateRegionOptions() {
        const mainzone = mainzoneSelect.value;
        const zone = zoneSelect.value;
        const seenRegions = new Set();
        const regions = zoningOptions.regions.filter(function (item) {
            if (mainzone && item.mainzone !== mainzone) return false;
            if (zone && item.zone !== zone) return false;
            const key = item.value + '\u0000' + item.label;
            if (seenRegions.has(key)) return false;
            seenRegions.add(key);
            return true;
        });
        replaceSelectOptions(regionSelect, 'Select Region', regions);
    }

    async function loadZoningOptions() {
        mainzoneSelect.disabled = true;
        zoneSelect.disabled = true;
        regionSelect.disabled = true;

        try {
            const endpoint = window.autoreconUrl('src/controllers/history-logs/branch-status-log-zoning.php');
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to load branch zoning filters.');
            }

            zoningOptions = {
                mainzones: Array.isArray(payload.mainzones) ? payload.mainzones : [],
                zones: Array.isArray(payload.zones) ? payload.zones : [],
                regions: Array.isArray(payload.regions) ? payload.regions : []
            };
            replaceSelectOptions(mainzoneSelect, 'Select Mainzone', zoningOptions.mainzones);
            updateZoneOptions();
        } catch (error) {
            replaceSelectOptions(mainzoneSelect, 'Unable to load Mainzone', []);
            replaceSelectOptions(zoneSelect, 'Unable to load Zone', []);
            replaceSelectOptions(regionSelect, 'Unable to load Region', []);
        } finally {
            mainzoneSelect.disabled = false;
            zoneSelect.disabled = false;
            regionSelect.disabled = false;
        }
    }

    function closeSuggestions() {
        suggestions.hidden = true;
        suggestions.innerHTML = '';
        searchInput.setAttribute('aria-expanded', 'false');
        searchInput.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function chooseBranch(branch) {
        searchInput.value = branch.branch_id
            ? branch.branch_id + ' — ' + branch.branch_name
            : branch.branch_name;
        searchInput.dataset.branchId = branch.branch_id || '';
        searchInput.dataset.branchName = branch.branch_name;
        updateButtonStates();
        closeSuggestions();
    }

    function setActiveOption(index) {
        const options = Array.from(suggestions.querySelectorAll('[role="option"]:not([aria-disabled="true"])'));
        options.forEach(function (option) { option.classList.remove('is-active'); });
        activeIndex = index;

        if (options[activeIndex]) {
            options[activeIndex].classList.add('is-active');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
            searchInput.setAttribute('aria-activedescendant', options[activeIndex].id);
        }
    }

    function renderSuggestions(branches) {
        visibleBranches = branches;
        suggestions.innerHTML = '';
        activeIndex = -1;

        if (!branches.length) {
            const emptyOption = document.createElement('li');
            emptyOption.className = 'branch-status-logs-suggestion-empty';
            emptyOption.textContent = 'No matching branch found.';
            emptyOption.setAttribute('role', 'option');
            emptyOption.setAttribute('aria-disabled', 'true');
            suggestions.appendChild(emptyOption);
        } else {
            branches.forEach(function (branch, index) {
                const option = document.createElement('li');
                option.id = 'branchStatusLogsSuggestion-' + index;
                option.setAttribute('role', 'option');

                const branchId = document.createElement('strong');
                branchId.textContent = branch.branch_id || 'No Branch ID';
                const branchName = document.createElement('span');
                branchName.textContent = branch.branch_name;
                option.append(branchId, branchName);

                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    chooseBranch(branch);
                });
                suggestions.appendChild(option);
            });
        }

        suggestions.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    }

    async function loadSuggestions() {
        const query = searchInput.value.trim();
        if (!query) {
            closeSuggestions();
            return;
        }

        if (requestController) requestController.abort();
        requestController = new AbortController();

        try {
            const endpoint = window.autoreconUrl('src/controllers/history-logs/branch-status-log-branches.php');
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(query), {
                signal: requestController.signal,
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || 'Search failed.');
            renderSuggestions(Array.isArray(payload.branches) ? payload.branches : []);
        } catch (error) {
            if (error.name !== 'AbortError') closeSuggestions();
        }
    }

    function renderTableRows(branches) {
        tableBody.innerHTML = '';
        branchCount.textContent = new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0
        }).format(branches.length);

        if (!branches.length) {
            const row = document.createElement('tr');
            row.className = 'branch-status-logs-empty-row';
            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.textContent = 'No branch status records found.';
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }

        branches.forEach(function (branch) {
            const row = document.createElement('tr');
            const idCell = document.createElement('td');
            const nameCell = document.createElement('td');
            const actionCell = document.createElement('td');
            const viewButton = document.createElement('button');

            idCell.textContent = branch.branch_id || '—';
            nameCell.textContent = branch.branch_name;
            viewButton.type = 'button';
            viewButton.className = 'branch-status-logs-view-button';
            viewButton.title = 'View Branch status history details';
            viewButton.setAttribute('aria-label', 'View Branch status history details');
            viewButton.dataset.branchId = branch.branch_id || '';
            viewButton.dataset.branchName = branch.branch_name;
            viewButton.dataset.bosCode = branch.bos_code || '';
            viewButton.dataset.branchStatus = branch.branch_status || '';
            viewButton.dataset.corporateName = branch.corporate_name || '';
            viewButton.dataset.mainzone = branch.mainzone || '';
            viewButton.dataset.postedBy = branch.posted_by || '';
            viewButton.dataset.zone = branch.zone || '';
            viewButton.dataset.regionName1 = branch.region_name_1 || '';
            viewButton.dataset.regionName2 = branch.region_name_2 || '';
            viewButton.dataset.area = branch.area || '';
            viewButton.dataset.postedAt = branch.posted_at || '';
            const viewIcon = document.createElement('span');
            viewIcon.className = 'material-icons-outlined';
            viewIcon.setAttribute('aria-hidden', 'true');
            viewIcon.textContent = 'visibility';
            viewButton.appendChild(viewIcon);
            actionCell.appendChild(viewButton);
            row.append(idCell, nameCell, actionCell);
            tableBody.appendChild(row);
        });
    }

    async function displayBranches(showAll, showLoadingCard) {
        closeSuggestions();
        if (showLoadingCard !== false) {
            loadingStatus.hidden = false;
            dropdownFilters.hidden = true;
            countCard.hidden = true;
            tableCard.hidden = true;
        }
        displayButton.disabled = true;
        displayAllButton.disabled = true;
        loadingStatus.textContent = 'Loading display for Branch status logs...';

        const params = new URLSearchParams();
        if (showAll) {
            params.set('all', '1');
        } else {
            const query = searchInput.dataset.branchId
                || searchInput.dataset.branchName
                || searchInput.value.trim();
            if (!query) {
                updateButtonStates();
                loadingStatus.hidden = true;
                return;
            }
            params.set('q', query);
            params.set('all', '1');
        }
        if (mainzoneSelect.value) params.set('mainzone', mainzoneSelect.value);
        if (zoneSelect.value) params.set('zone', zoneSelect.value);
        if (regionSelect.value) params.set('region_code', regionSelect.value);

        try {
            const endpoint = window.autoreconUrl('src/controllers/history-logs/branch-status-log-branches.php');
            const response = await fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to display branch status logs.');
            }

            const branches = Array.isArray(payload.branches) ? payload.branches : [];
            renderTableRows(branches);
            loadingStatus.textContent = branches.length
                ? branches.length + (branches.length === 1 ? ' branch displayed.' : ' branches displayed.')
                : 'No branch status records found.';
        } catch (error) {
            renderTableRows([]);
            loadingStatus.textContent = error.message || 'Unable to display branch status logs.';
        } finally {
            loadingStatus.hidden = true;
            dropdownFilters.hidden = false;
            countCard.hidden = false;
            tableCard.hidden = false;
            updateButtonStates();
        }
    }

    function formatPostedDate(value) {
        const rawValue = String(value || '').trim();
        const match = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        if (!match) return rawValue || '—';

        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const month = months[Number(match[2]) - 1];
        const hour24 = Number(match[4]);
        if (!month || hour24 > 23) return rawValue;

        const hour12 = hour24 % 12 || 12;
        const meridiem = hour24 >= 12 ? 'PM' : 'AM';
        return month + ' ' + match[3] + ', ' + match[1] + ' '
            + String(hour12).padStart(2, '0') + ':' + match[5] + ':' + match[6] + ' ' + meridiem;
    }

    function renderHistoryMessage(message) {
        historyTableBody.innerHTML = '';
        const row = document.createElement('tr');
        row.className = 'branch-status-logs-modal__empty-row';
        const cell = document.createElement('td');
        cell.colSpan = 12;
        cell.textContent = message;
        row.appendChild(cell);
        historyTableBody.appendChild(row);
    }

    function renderHistoryRows(rows) {
        historyTableBody.innerHTML = '';
        if (!rows.length) {
            renderHistoryMessage('No recorded history data found.');
            return;
        }

        const fields = [
            'posted_at', 'branch_id', 'bos_code', 'branch_name', 'area',
            'corporate_name', 'mainzone', 'zone', 'region_name_1',
            'region_name_2', 'branch_status', 'posted_by'
        ];

        rows.forEach(function (historyRow) {
            const row = document.createElement('tr');
            fields.forEach(function (field) {
                const cell = document.createElement('td');
                const value = field === 'posted_at'
                    ? formatPostedDate(historyRow[field])
                    : String(historyRow[field] || '').trim() || '—';
                cell.textContent = value;
                row.appendChild(cell);
            });
            historyTableBody.appendChild(row);
        });
    }

    async function loadRecordedHistory(trigger) {
        if (historyRequestController) historyRequestController.abort();
        historyRequestController = new AbortController();
        renderHistoryMessage('Loading recorded history data...');

        const params = new URLSearchParams({
            branch_id: trigger.dataset.branchId || '',
            branch_name: trigger.dataset.branchName || '',
            posted_at: trigger.dataset.postedAt || ''
        });

        try {
            const endpoint = window.autoreconUrl('src/controllers/history-logs/branch-status-log-history.php');
            const response = await fetch(endpoint + '?' + params.toString(), {
                signal: historyRequestController.signal,
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to load recorded branch history data.');
            }
            renderHistoryRows(Array.isArray(payload.rows) ? payload.rows : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                renderHistoryMessage(error.message || 'Unable to load recorded branch history data.');
            }
        }
    }

    function openDetailsModal(trigger) {
        modalTrigger = trigger;
        detailsPostedDate.textContent = formatPostedDate(trigger.dataset.postedAt);
        detailsBranchId.textContent = trigger.dataset.branchId || '—';
        detailsBranchName.textContent = trigger.dataset.branchName || '—';
        detailsBosCode.textContent = trigger.dataset.bosCode || '—';
        detailsBranchStatus.textContent = trigger.dataset.branchStatus || '—';
        detailsCorporateName.textContent = trigger.dataset.corporateName || '—';
        detailsMainzone.textContent = trigger.dataset.mainzone || '—';
        detailsPostedBy.textContent = trigger.dataset.postedBy || '—';
        detailsZone.textContent = trigger.dataset.zone || '—';
        detailsRegionName1.textContent = trigger.dataset.regionName1 || '—';
        detailsRegionName2.textContent = trigger.dataset.regionName2 || '—';
        detailsArea.textContent = trigger.dataset.area || '—';
        detailsModal.hidden = false;
        document.body.classList.add('branch-status-logs-modal-open');
        detailsCloseButton.focus();
        loadRecordedHistory(trigger);
    }

    function closeDetailsModal() {
        if (detailsModal.hidden) return;
        detailsModal.hidden = true;
        if (historyRequestController) historyRequestController.abort();
        document.body.classList.remove('branch-status-logs-modal-open');
        if (modalTrigger && document.contains(modalTrigger)) modalTrigger.focus();
        modalTrigger = null;
    }

    searchInput.addEventListener('input', function () {
        delete searchInput.dataset.branchId;
        delete searchInput.dataset.branchName;
        updateButtonStates();
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(loadSuggestions, 200);
    });
    searchInput.addEventListener('search', updateButtonStates);
    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown' && visibleBranches.length) {
            event.preventDefault();
            setActiveOption((activeIndex + 1) % visibleBranches.length);
        } else if (event.key === 'ArrowUp' && visibleBranches.length) {
            event.preventDefault();
            setActiveOption((activeIndex - 1 + visibleBranches.length) % visibleBranches.length);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            chooseBranch(visibleBranches[activeIndex]);
        } else if (event.key === 'Escape') {
            closeSuggestions();
        }
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.branch-status-logs-autocomplete')) closeSuggestions();
    });
    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        displayBranches(false, true);
    });
    displayAllButton.addEventListener('click', function () {
        displayBranches(true, true);
    });
    mainzoneSelect.addEventListener('change', function () {
        updateZoneOptions();
        displayBranches(true, false);
    });
    zoneSelect.addEventListener('change', function () {
        updateRegionOptions();
        displayBranches(true, false);
    });
    regionSelect.addEventListener('change', function () {
        displayBranches(true, false);
    });
    tableBody.addEventListener('click', function (event) {
        const viewButton = event.target.closest('.branch-status-logs-view-button');
        if (viewButton) openDetailsModal(viewButton);
    });
    detailsCloseButton.addEventListener('click', closeDetailsModal);
    detailsModal.addEventListener('click', function (event) {
        if (event.target.hasAttribute('data-branch-status-modal-close')) closeDetailsModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !detailsModal.hidden) closeDetailsModal();
    });
    updateButtonStates();
    loadZoningOptions();

}());
</script>
