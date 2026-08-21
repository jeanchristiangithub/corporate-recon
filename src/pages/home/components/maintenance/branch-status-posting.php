<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../config/csrf.php';
?>
<section id="branchStatusPostingSection" class="branch-status-posting-section" aria-label="Branch Status Posting" style="display:none; padding:1rem">
    <h2 class="branch-status-posting-title">Branch Status Posting</h2>

    <form class="branch-status-posting-filter-card">
        <label class="branch-status-posting-field" for="branchStatusPostingDateTime">
            <span>Date and Time</span>
            <input id="branchStatusPostingDateTime" name="date_time" type="datetime-local" disabled>
        </label>

        <button class="branch-status-posting-generate" type="button">Generate</button>
    </form>

    <section class="branch-status-posting-results-card" aria-label="Branch status posting results">
        <div class="branch-status-posting-panel branch-status-posting-table-panel">
            <div class="branch-status-posting-panel-header">
                <div class="branch-status-posting-tabs" role="tablist" aria-label="Filter branch status">
                    <button class="branch-status-posting-tab is-active" type="button" role="tab" aria-selected="true" data-status="all">
                        <span>All</span><strong data-status-count="all">0</strong>
                    </button>
                    <button class="branch-status-posting-tab" type="button" role="tab" aria-selected="false" data-status="tbo">
                        <span>TBO</span><strong data-status-count="tbo">0</strong>
                    </button>
                    <button class="branch-status-posting-tab" type="button" role="tab" aria-selected="false" data-status="active">
                        <span>Active</span><strong data-status-count="active">0</strong>
                    </button>
                    <button class="branch-status-posting-tab" type="button" role="tab" aria-selected="false" data-status="pending">
                        <span>Pending</span><strong data-status-count="pending">0</strong>
                    </button>
                    <button class="branch-status-posting-tab" type="button" role="tab" aria-selected="false" data-status="inactive">
                        <span>Inactive</span><strong data-status-count="inactive">0</strong>
                    </button>
                    <button class="branch-status-posting-tab" type="button" role="tab" aria-selected="false" data-status="unknown">
                        <span>Unknown</span><strong data-status-count="unknown">0</strong>
                    </button>
                </div>
                <div class="branch-status-posting-actions">
                    <button id="branchStatusPostingPost" class="branch-status-posting-post" type="button" disabled>Post</button>
                    <button id="branchStatusPostingClear" class="branch-status-posting-clear" type="button" hidden>Clear</button>
                </div>
            </div>

            <div class="branch-status-posting-table-wrap">
                <table id="branchStatusPostingTable">
                    <thead>
                        <tr>
                            <th scope="col">Branch ID</th>
                            <th scope="col">Branch Code</th>
                            <th scope="col">Branch Name</th>
                            <th scope="col">Area</th>
                            <th scope="col">Region Description</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="branch-status-posting-empty-row">
                            <td colspan="6">Click Generate to display branch status records.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="branch-status-posting-panel branch-status-posting-history-panel" aria-labelledby="branchStatusPostingHistoryTitle">
            <div class="branch-status-posting-panel-header">
                <h3 id="branchStatusPostingHistoryTitle">Posted Data</h3>
                <div class="branch-status-posting-history-filter">
                    <label class="branch-status-posting-history-month" for="branchStatusPostingHistoryMonth">
                        <span>Month</span>
                        <input id="branchStatusPostingHistoryMonth" type="month" aria-label="Filter posted data by month">
                    </label>
                    <button id="branchStatusPostingHistoryMonthClear" class="branch-status-posting-history-month-clear" type="button" aria-label="Clear month filter" title="Clear month filter" hidden>
                        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor" aria-hidden="true"><path d="M120-40v-280q0-83 58.5-141.5T320-520h40v-320q0-33 23.5-56.5T440-920h80q33 0 56.5 23.5T600-840v320h40q83 0 141.5 58.5T840-320v280H120Zm80-80h80v-120q0-17 11.5-28.5T320-280q17 0 28.5 11.5T360-240v120h80v-120q0-17 11.5-28.5T480-280q17 0 28.5 11.5T520-240v120h80v-120q0-17 11.5-28.5T640-280q17 0 28.5 11.5T680-240v120h80v-200q0-50-35-85t-85-35H320q-50 0-85 35t-35 85v200Zm320-400v-320h-80v320h80Zm0 0h-80 80Z"/></svg>
                    </button>
                </div>
            </div>

            <div id="branchStatusPostingHistory" class="branch-status-posting-history-list">
                <p class="branch-status-posting-empty-history">No posted data available.</p>
            </div>
            <nav id="branchStatusPostingHistoryPagination" class="branch-status-posting-history-pagination" aria-label="Posted data pages"></nav>
        </aside>
    </section>
</section>

<script>
(() => {
    const dateTimeInput = document.getElementById('branchStatusPostingDateTime');
    if (dateTimeInput && !dateTimeInput.value) {
        const now = new Date();
        const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);

        dateTimeInput.value = localDateTime;
    }

    const statusTabs = document.querySelectorAll('.branch-status-posting-tab');
    const tableBody = document.querySelector('#branchStatusPostingTable tbody');
    const emptyRow = tableBody?.querySelector('.branch-status-posting-empty-row');
    const generateButton = document.querySelector('.branch-status-posting-generate');
    const postButton = document.getElementById('branchStatusPostingPost');
    const clearButton = document.getElementById('branchStatusPostingClear');
    const historyMonthInput = document.getElementById('branchStatusPostingHistoryMonth');
    const historyMonthClear = document.getElementById('branchStatusPostingHistoryMonthClear');
    const historyList = document.getElementById('branchStatusPostingHistory');
    const historyPagination = document.getElementById('branchStatusPostingHistoryPagination');
    const csrfToken = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let isViewingHistory = false;
    let historyGroups = [];
    let currentHistoryPage = 1;
    const historyPageSize = 5;

    const updateStatusTable = (selectedStatus = 'all') => {
        if (!tableBody) return;

        const rows = [...tableBody.querySelectorAll('tr[data-status]')];
        const counts = { all: rows.length, tbo: 0, active: 0, pending: 0, inactive: 0, unknown: 0 };

        rows.forEach((row) => {
            const rowStatus = (row.dataset.status || '').toLowerCase() || 'unknown';
            if (Object.hasOwn(counts, rowStatus) && rowStatus !== 'all') {
                counts[rowStatus] += 1;
            }
            row.hidden = selectedStatus !== 'all' && rowStatus !== selectedStatus;
        });

        Object.entries(counts).forEach(([status, count]) => {
            const countElement = document.querySelector(`[data-status-count="${status}"]`);
            if (countElement) countElement.textContent = count.toLocaleString();
        });

        const visibleCount = selectedStatus === 'all' ? counts.all : (counts[selectedStatus] || 0);
        if (emptyRow) {
            emptyRow.hidden = visibleCount > 0;
            emptyRow.querySelector('td').textContent = rows.length === 0
                ? 'Click Generate to display branch status records.'
                : `No ${selectedStatus.toUpperCase()} records found.`;
        }
    };

    statusTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            statusTabs.forEach((item) => {
                const isSelected = item === tab;
                item.classList.toggle('is-active', isSelected);
                item.setAttribute('aria-selected', String(isSelected));
            });
            updateStatusTable(tab.dataset.status || 'all');
        });
    });

    if (tableBody) {
        new MutationObserver(() => {
            const activeTab = document.querySelector('.branch-status-posting-tab.is-active');
            updateStatusTable(activeTab?.dataset.status || 'all');
        }).observe(tableBody, { childList: true });
    }

    const displayValue = (value) => {
        const normalizedValue = String(value ?? '').trim();
        return normalizedValue || '—';
    };

    const renderRows = (rows, allowPosting = true) => {
        if (!tableBody) return;

        tableBody.querySelectorAll('tr[data-status]').forEach((row) => row.remove());

        rows.forEach((record) => {
            const row = document.createElement('tr');
            row.dataset.status = String(record.status ?? '').trim().toLowerCase() || 'unknown';

            [
                record.branch_id,
                record.branch_code,
                record.branch_name,
                record.area,
                record.region_description,
                record.status,
            ].forEach((value) => {
                const cell = document.createElement('td');
                cell.textContent = displayValue(value);
                row.appendChild(cell);
            });

            tableBody.appendChild(row);
        });

        if (postButton) postButton.disabled = !allowPosting || rows.length === 0;
        if (clearButton) clearButton.hidden = !allowPosting || rows.length === 0;
        updateStatusTable(document.querySelector('.branch-status-posting-tab.is-active')?.dataset.status || 'all');
    };

    const formatPostedDateTime = (value) => {
        const parsedDate = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsedDate.getTime())
            ? displayValue(value)
            : parsedDate.toLocaleString([], {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
    };

    const historyEndpoint = () => {
        const baseUrl = String(window.autoreconBaseUrl || '').replace(/\/$/, '');
        return `${baseUrl}/src/controllers/maintenance/branch-status-posting-history.php`;
    };

    const viewPostedGroup = async (postedAt, viewButton) => {
        viewButton.disabled = true;
        try {
            const query = new URLSearchParams({ month: historyMonthInput.value, posted_at: postedAt });
            const response = await fetch(`${historyEndpoint()}?${query}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || 'Unable to view posted data.');
            statusTabs.forEach((tab) => {
                const isDefaultTab = tab.dataset.status === 'all';
                tab.classList.toggle('is-active', isDefaultTab);
                tab.setAttribute('aria-selected', String(isDefaultTab));
            });
            renderRows(Array.isArray(payload.rows) ? payload.rows : [], false);
            updateStatusTable('all');
            if (generateButton) generateButton.disabled = true;
            if (clearButton) clearButton.hidden = true;
            isViewingHistory = true;
            if (postButton) {
                postButton.disabled = false;
                postButton.textContent = 'Clear';
                postButton.classList.add('is-clear');
            }
        } catch (error) {
            window.Swal
                ? await window.Swal.fire({ icon: 'error', title: 'View failed', text: error.message })
                : window.alert(error.message);
        } finally {
            viewButton.disabled = false;
        }
    };

    const renderHistoryPagination = () => {
        if (!historyPagination) return;
        historyPagination.replaceChildren();

        const totalPages = Math.ceil(historyGroups.length / historyPageSize);
        if (totalPages <= 1) return;

        const addPageButton = (label, page, options = {}) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = Boolean(options.disabled);
            button.className = options.active ? 'is-active' : '';
            if (options.active) button.setAttribute('aria-current', 'page');
            button.setAttribute('aria-label', options.label || `Page ${page}`);
            button.addEventListener('click', () => renderHistoryGroups(historyGroups, page));
            historyPagination.appendChild(button);
        };

        addPageButton('<<', 1, {
            disabled: currentHistoryPage === 1,
            label: 'First page',
        });

        addPageButton('‹', currentHistoryPage - 1, {
            disabled: currentHistoryPage === 1,
            label: 'Previous page',
        });

        const firstPage = Math.max(1, Math.min(currentHistoryPage - 2, totalPages - 4));
        const lastPage = Math.min(totalPages, firstPage + 4);
        for (let page = firstPage; page <= lastPage; page += 1) {
            addPageButton(String(page), page, { active: page === currentHistoryPage });
        }

        addPageButton('›', currentHistoryPage + 1, {
            disabled: currentHistoryPage === totalPages,
            label: 'Next page',
        });

        addPageButton('>>', totalPages, {
            disabled: currentHistoryPage === totalPages,
            label: 'Last page',
        });
    };

    const renderHistoryGroups = (groups, page = 1) => {
        if (!historyList) return;
        historyGroups = groups;
        const totalPages = Math.max(1, Math.ceil(historyGroups.length / historyPageSize));
        currentHistoryPage = Math.min(Math.max(1, page), totalPages);
        historyList.replaceChildren();

        if (groups.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'branch-status-posting-empty-history';
            emptyMessage.textContent = 'No posted data available for the selected month.';
            historyList.appendChild(emptyMessage);
            renderHistoryPagination();
            return;
        }

        const pageStart = (currentHistoryPage - 1) * historyPageSize;
        groups.slice(pageStart, pageStart + historyPageSize).forEach((group) => {
            const card = document.createElement('article');
            card.className = 'branch-status-posting-history-card';

            const details = document.createElement('div');
            details.className = 'branch-status-posting-history-details';
            const date = document.createElement('strong');
            date.textContent = formatPostedDateTime(group.posted_at);
            const postedBy = document.createElement('span');
            postedBy.textContent = `Posted by: ${displayValue(group.posted_by)}`;
            const recordCount = document.createElement('span');
            recordCount.textContent = `${Number(group.record_count || 0).toLocaleString()} record(s)`;
            details.append(date, postedBy, recordCount);

            const viewButton = document.createElement('button');
            viewButton.className = 'branch-status-posting-history-view';
            viewButton.type = 'button';
            viewButton.title = 'View posted data';
            viewButton.setAttribute('aria-label', `View posting from ${date.textContent}`);
            viewButton.innerHTML = '<span class="material-icons-outlined" aria-hidden="true">visibility</span><span>View</span>';
            viewButton.addEventListener('click', () => viewPostedGroup(group.posted_at, viewButton));

            card.append(details, viewButton);
            historyList.appendChild(card);
        });

        renderHistoryPagination();
    };

    const loadHistoryGroups = async () => {
        if (!historyList) return;
        historyList.innerHTML = '<p class="branch-status-posting-empty-history">Loading posted data...</p>';
        if (historyPagination) historyPagination.replaceChildren();

        try {
            const query = new URLSearchParams();
            if (historyMonthInput?.value) query.set('month', historyMonthInput.value);
            const response = await fetch(`${historyEndpoint()}?${query}`, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || 'Unable to load posted data.');
            renderHistoryGroups(Array.isArray(payload.groups) ? payload.groups : []);
        } catch (error) {
            historyList.innerHTML = '';
            const errorMessage = document.createElement('p');
            errorMessage.className = 'branch-status-posting-empty-history';
            errorMessage.textContent = error.message || 'Unable to load posted data.';
            historyList.appendChild(errorMessage);
        }
    };

    historyMonthInput?.addEventListener('change', () => {
        if (historyMonthClear) historyMonthClear.hidden = !historyMonthInput.value;
        loadHistoryGroups();
    });
    historyMonthClear?.addEventListener('click', () => {
        historyMonthInput.value = '';
        historyMonthClear.hidden = true;
        loadHistoryGroups();
    });

    generateButton?.addEventListener('click', async () => {
        generateButton.disabled = true;
        generateButton.textContent = 'Generating...';

        try {
            const baseUrl = String(window.autoreconBaseUrl || '').replace(/\/$/, '');
            const response = await fetch(`${baseUrl}/src/controllers/maintenance/branch-status-posting-results.php`, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to load branch status records.');
            }

            statusTabs.forEach((tab) => {
                const isDefaultTab = tab.dataset.status === 'all';
                tab.classList.toggle('is-active', isDefaultTab);
                tab.setAttribute('aria-selected', String(isDefaultTab));
            });
            renderRows(Array.isArray(payload.rows) ? payload.rows : []);
            updateStatusTable('all');
        } catch (error) {
            tableBody?.querySelectorAll('tr[data-status]').forEach((row) => row.remove());
            if (postButton) postButton.disabled = true;
            if (clearButton) clearButton.hidden = true;
            updateStatusTable();
            if (emptyRow) {
                emptyRow.hidden = false;
                emptyRow.querySelector('td').textContent = error.message || 'Unable to load branch status records.';
            }
        } finally {
            generateButton.disabled = tableBody?.querySelectorAll('tr[data-status]').length > 0;
            generateButton.textContent = 'Generate';
        }
    });

    clearButton?.addEventListener('click', () => {
        tableBody?.querySelectorAll('tr[data-status]').forEach((row) => row.remove());
        statusTabs.forEach((tab) => {
            const isDefaultTab = tab.dataset.status === 'all';
            tab.classList.toggle('is-active', isDefaultTab);
            tab.setAttribute('aria-selected', String(isDefaultTab));
        });
        postButton.disabled = true;
        clearButton.hidden = true;
        generateButton.disabled = false;
        updateStatusTable('all');
    });

    postButton?.addEventListener('click', async () => {
        if (isViewingHistory) {
            tableBody?.querySelectorAll('tr[data-status]').forEach((row) => row.remove());
            if (clearButton) clearButton.hidden = true;
            statusTabs.forEach((tab) => {
                const isDefaultTab = tab.dataset.status === 'all';
                tab.classList.toggle('is-active', isDefaultTab);
                tab.setAttribute('aria-selected', String(isDefaultTab));
            });
            isViewingHistory = false;
            generateButton.disabled = false;
            postButton.disabled = true;
            postButton.textContent = 'Post';
            postButton.classList.remove('is-clear');
            updateStatusTable('all');
            return;
        }

        if (!dateTimeInput?.value) return;

        statusTabs.forEach((tab) => {
            const isDefaultTab = tab.dataset.status === 'all';
            tab.classList.toggle('is-active', isDefaultTab);
            tab.setAttribute('aria-selected', String(isDefaultTab));
        });
        updateStatusTable('all');

        postButton.disabled = true;
        postButton.textContent = 'Posting...';

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('posted_datetime', dateTimeInput.value);

            const baseUrl = String(window.autoreconBaseUrl || '').replace(/\/$/, '');
            const response = await fetch(`${baseUrl}/src/controllers/maintenance/branch-status-posting-save.php`, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: { Accept: 'application/json' },
            });
            const responseText = await response.text();
            let payload = {};
            try {
                payload = JSON.parse(responseText);
            } catch (error) {
                payload = {};
            }

            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Unable to post branch status records.');
            }

            const message = `${Number(payload.inserted_count || 0).toLocaleString()} branch status record(s) posted successfully.`;
            if (window.Swal) {
                await window.Swal.fire({
                    icon: 'success',
                    title: 'Posted',
                    text: message,
                    allowOutsideClick: false,
                    allowEnterKey: false,
                    allowEscapeKey: false,
                });
            } else {
                window.alert(message);
            }

            tableBody?.querySelectorAll('tr[data-status]').forEach((row) => row.remove());
            if (clearButton) clearButton.hidden = true;
            generateButton.disabled = false;
            statusTabs.forEach((tab) => {
                const isDefaultTab = tab.dataset.status === 'all';
                tab.classList.toggle('is-active', isDefaultTab);
                tab.setAttribute('aria-selected', String(isDefaultTab));
            });
            updateStatusTable('all');

            if (!historyMonthInput?.value || historyMonthInput.value === dateTimeInput.value.slice(0, 7)) {
                await loadHistoryGroups();
            }
        } catch (error) {
            const message = error.message || 'Unable to post branch status records.';
            if (window.Swal) {
                await window.Swal.fire({ icon: 'error', title: 'Post failed', text: message });
            } else {
                window.alert(message);
            }
        } finally {
            postButton.disabled = tableBody?.querySelectorAll('tr[data-status]').length === 0;
            postButton.textContent = 'Post';
        }
    });

    updateStatusTable();
    loadHistoryGroups();
})();
</script>
