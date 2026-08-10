<?php
require_once __DIR__ . '/../../../../config/db.php';
$maintenanceUnlockPartners = [];
try {
    $maintenanceUnlockPdo = masterDataConnection();
    $maintenanceUnlockStmt = $maintenanceUnlockPdo->query("SELECT DISTINCT partner_name FROM corpo_partner_masterfile WHERE partner_name IS NOT NULL AND partner_name <> '' ORDER BY partner_name ASC");
    $maintenanceUnlockPartners = $maintenanceUnlockStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $maintenanceUnlockPartners = [];
}
?>
<style>
    #maintenanceDataUnlockSection.maintenance-unlock-loading,
    #maintenanceDataUnlockSection.maintenance-unlock-loading * {
        cursor: wait !important;
    }
    #maintenanceDataUnlockSection.maintenance-unlock-loading input:disabled {
        color: #94a3b8;
        background: #f1f5f9;
        opacity: .75;
    }
    #maintenanceLockProcess:disabled,
    #maintenanceLockExportPdf:disabled {
        opacity: .55;
        pointer-events: none;
        box-shadow: none;
    }
    #maintenanceLockExportPdf {
        color: #dc3545;
        background: #fff;
        border: 1px solid #dc3545;
        border-radius: 999px;
        box-shadow: none;
    }
    #maintenanceLockExportPdf:hover:not(:disabled) {
        color: #b91c2c;
        background: #fff1f2;
        border-color: #b91c2c;
    }
    #maintenanceDataUnlockSection .locked-dates-table tbody tr {
        transition: background-color .15s ease, box-shadow .15s ease;
    }
    #maintenanceDataUnlockSection .locked-dates-table tbody tr:hover {
        background: #f1f7ff;
        box-shadow: inset 3px 0 0 #dc3545;
    }
    #maintenanceDataUnlockSection .locked-dates-table-wrap {
        max-height: min(62vh, 560px);
        overflow: auto;
    }
    #maintenanceDataUnlockSection .locked-dates-table thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background: #f8fafc;
        box-shadow: 0 1px 0 rgba(15, 23, 42, .10);
    }
</style>
<section id="maintenanceDataUnlockSection" class="home-section" aria-label="Transaction Lock" aria-busy="false" style="display:none;">
    <div class="home-section__inner">
        <div class="home-section__header">
            <h2 class="home-section__title">Transaction Lock</h2>
        </div>

        <div id="maintenanceUnlockMessage" class="locked-dates-modal__message" hidden></div>

        <div class="locked-dates-toolbar" style="justify-content:flex-start;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-top:16px;">
            <div style="display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px;">
                <label class="filter" style="flex:0 1 325px;width:min(325px,100%);">
                    <span>Corporate Partner</span>
                    <div class="autocomplete-field" style="width:100%;">
                        <input id="maintenanceUnlockPartner" type="text" role="combobox" aria-autocomplete="list" aria-controls="maintenanceUnlockPartnerSuggestions" aria-expanded="false" placeholder="Select corporate partner" autocomplete="off" style="width:100%;height:36px;padding:7px 10px;border:1px solid rgba(15,23,42,.14);border-radius:7px;box-sizing:border-box;">
                        <ul id="maintenanceUnlockPartnerSuggestions" class="autocomplete-list" role="listbox" hidden></ul>
                    </div>
                </label>
                <label class="filter" style="flex:0 0 142px;">
                    <span>Start Date</span>
                    <input id="maintenanceLockStartDate" type="date" value="<?= date('Y-m-d') ?>" style="width:100%;height:36px;padding:7px 10px;border:1px solid rgba(15,23,42,.14);border-radius:7px;box-sizing:border-box;">
                </label>
                <label class="filter" style="flex:0 0 142px;">
                    <span>End Date</span>
                    <input id="maintenanceLockEndDate" type="date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" style="width:100%;height:36px;padding:7px 10px;border:1px solid rgba(15,23,42,.14);border-radius:7px;box-sizing:border-box;">
                </label>
                <label class="filter" style="flex:0 0 140px;">
                    <span>Status</span>
                    <select id="maintenanceLockStatus" style="width:100%;height:36px;padding:7px 10px;border:1px solid rgba(15,23,42,.14);border-radius:7px;background:#fff;box-sizing:border-box;">
                        <option value="all">All Status</option>
                        <option value="locked">Locked</option>
                        <option value="unlocked">Unlocked</option>
                        <option value="no_data">No Data</option>
                    </select>
                </label>
                <button id="maintenanceLockProcess" type="button" class="material-btn material-btn--primary" style="height:36px;padding:7px 18px;border-radius:999px;">Process</button>
                <button id="maintenanceLockExportPdf" type="button" class="material-btn" disabled style="height:36px;padding:7px 18px;">Export to PDF</button>
            </div>
        </div>

        <div class="locked-dates-table-wrap">
            <table class="locked-dates-table">
                <thead>
                    <tr>
                        <th>Corporate Partner</th>
                        <th>Transaction Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="maintenanceUnlockBody">
                    <tr><td colspan="4" class="locked-dates-empty">Select a corporate partner and date range, then click Process.</td></tr>
                </tbody>
            </table>
        </div>

        <div id="maintenanceUnlockPagination" class="locked-dates-pagination" hidden>
            <button type="button" class="material-btn locked-dates-page-btn" data-unlock-page="previous">Previous</button>
            <span id="maintenanceUnlockPageInfo" class="locked-dates-page-info">Page 1 of 1</span>
            <button type="button" class="material-btn locked-dates-page-btn" data-unlock-page="next">Next</button>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';

    const section = document.getElementById('maintenanceDataUnlockSection');
    const body = document.getElementById('maintenanceUnlockBody');
    const partnerFilter = document.getElementById('maintenanceUnlockPartner');
    const partnerSuggestions = document.getElementById('maintenanceUnlockPartnerSuggestions');
    const startDateFilter = document.getElementById('maintenanceLockStartDate');
    const endDateFilter = document.getElementById('maintenanceLockEndDate');
    const statusFilter = document.getElementById('maintenanceLockStatus');
    const processButton = document.getElementById('maintenanceLockProcess');
    const exportPdfButton = document.getElementById('maintenanceLockExportPdf');
    const message = document.getElementById('maintenanceUnlockMessage');
    const pagination = document.getElementById('maintenanceUnlockPagination');
    const pageInfo = document.getElementById('maintenanceUnlockPageInfo');
    let rows = [];
    let page = 1;
    let loading = false;
    let activePartnerIndex = -1;
    const isAdmin = <?= isset($_SESSION['user']['role']) && strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0 ? 'true' : 'false' ?>;
    const isPublic = <?= isset($_SESSION['user']['role']) && strcasecmp((string) $_SESSION['user']['role'], 'Public') === 0 ? 'true' : 'false' ?>;
    const partnerOptions = <?= json_encode(array_values($maintenanceUnlockPartners), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function baseUrl() {
        return String(window.autoreconBaseUrl || '').replace(/\/$/, '');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeDate(value) {
        const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? match[1] + '-' + match[2] + '-' + match[3] : '';
    }

    function displayDate(value) {
        const date = normalizeDate(value);
        const parts = date.split('-');
        if (parts.length !== 3) return String(value || '');
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const month = monthNames[Number(parts[1]) - 1];
        return month ? month + ' ' + parts[2] + ', ' + parts[0] : String(value || '');
    }

    function setMessage(text, type) {
        const value = String(text || '').trim();
        message.textContent = value;
        message.className = 'locked-dates-modal__message ' + (type === 'success' ? 'is-success' : 'is-error');
        message.hidden = !value;
    }

    function setLoadingState(isLoading) {
        [partnerFilter, startDateFilter, endDateFilter, statusFilter, processButton, exportPdfButton].forEach(function (control) {
            control.disabled = isLoading;
            control.style.cursor = isLoading ? 'wait' : '';
        });
        processButton.textContent = isLoading ? 'Loading...' : 'Process';
        exportPdfButton.disabled = isLoading || filteredRows().length === 0;
        section.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        section.classList.toggle('maintenance-unlock-loading', isLoading);
        if (isLoading) closePartnerSuggestions();
    }

    function clearProcessedResults() {
        rows = [];
        page = 1;
        body.innerHTML = '<tr><td colspan="4" class="locked-dates-empty">Select a corporate partner and date range, then press Enter or click Process.</td></tr>';
        pagination.hidden = true;
        exportPdfButton.disabled = true;
        setMessage('', '');
    }

    function filteredRows() {
        const selectedPartner = String(partnerFilter.value || '').trim().toUpperCase();
        const startDate = normalizeDate(startDateFilter.value || '');
        const endDate = normalizeDate(endDateFilter.value || '');
        const selectedStatus = String(statusFilter.value || 'all').toLowerCase();
        return rows.filter(function (row) {
            const partner = String(row.partnername || row.corporate_partner || '');
            const date = normalizeDate(row.transaction_date || row.recon_date || row.date || '');
            if (selectedPartner && partner.trim().toUpperCase() !== selectedPartner) return false;
            if (startDate && date < startDate) return false;
            if (endDate && date > endDate) return false;
            if (selectedStatus !== 'all' && String(row.status || '').toLowerCase() !== selectedStatus) return false;
            return true;
        });
    }

    function renderPartnerSuggestions() {
        const query = String(partnerFilter.value || '').trim().toLowerCase();
        if (!query) {
            closePartnerSuggestions();
            return;
        }
        activePartnerIndex = -1;
        const matches = partnerOptions.filter(function (partner) {
            return String(partner).toLowerCase().indexOf(query) !== -1;
        });
        partnerSuggestions.innerHTML = matches.length
            ? matches.map(function (partner) {
                return '<li class="autocomplete-item" role="option" data-partner="' + escapeHtml(partner) + '">' + escapeHtml(partner) + '</li>';
            }).join('')
            : '<li class="autocomplete-item autocomplete-empty" aria-disabled="true">No corporate partner found</li>';
        partnerSuggestions.hidden = false;
        partnerFilter.setAttribute('aria-expanded', 'true');
    }

    function closePartnerSuggestions() {
        partnerSuggestions.hidden = true;
        partnerFilter.setAttribute('aria-expanded', 'false');
        activePartnerIndex = -1;
        partnerFilter.removeAttribute('aria-activedescendant');
    }

    function movePartnerSuggestion(direction) {
        const options = Array.from(partnerSuggestions.querySelectorAll('[data-partner]'));
        if (!options.length) return;
        activePartnerIndex = (activePartnerIndex + direction + options.length) % options.length;
        options.forEach(function (option, index) {
            option.classList.toggle('is-active', index === activePartnerIndex);
            option.id = 'maintenanceLockPartnerOption' + index;
        });
        const activeOption = options[activePartnerIndex];
        partnerFilter.setAttribute('aria-activedescendant', activeOption.id);
        activeOption.scrollIntoView({block: 'nearest'});
    }

    function selectPartnerSuggestion(option) {
        if (!option) return;
        partnerFilter.value = option.getAttribute('data-partner') || '';
        closePartnerSuggestions();
        clearProcessedResults();
    }

    function render() {
        const list = filteredRows();
        const monthGroups = [];
        list.forEach(function (row) {
            const date = normalizeDate(row.transaction_date || row.recon_date || row.date || '');
            const monthKey = date.slice(0, 7);
            let group = monthGroups[monthGroups.length - 1];
            if (!group || group.key !== monthKey) {
                group = {key: monthKey, rows: []};
                monthGroups.push(group);
            }
            group.rows.push(row);
        });
        const totalPages = Math.max(1, monthGroups.length);
        page = Math.min(Math.max(1, page), totalPages);

        if (!list.length) {
            body.innerHTML = '<tr><td colspan="4" class="locked-dates-empty">No locked reconciliation dates found.</td></tr>';
            pagination.hidden = true;
            exportPdfButton.disabled = true;
            return;
        }
        exportPdfButton.disabled = loading;

        const currentGroup = monthGroups[page - 1];
        const currentRows = currentGroup ? currentGroup.rows : [];
        body.innerHTML = currentRows.map(function (row) {
            const partner = String(row.partnername || row.corporate_partner || '').trim();
            const date = normalizeDate(row.transaction_date || row.recon_date || row.date || '');
            const status = String(row.status || '').toLowerCase();
            const isLocked = status === 'locked';
            const isNoData = status === 'no_data';
            const actions = isNoData
                ? ''
                : isLocked
                ? '<div class="locked-dates-actions"><button type="button" class="material-btn locked-dates-action-btn locked-dates-view" data-action="maintenance-locked-view">View</button>'
                    + (isAdmin ? '<button type="button" class="material-btn locked-dates-action-btn locked-dates-unlock" data-action="maintenance-unlock">Unlock</button>' : '')
                    + '</div>'
                : '<div class="locked-dates-actions"><button type="button" class="material-btn locked-dates-action-btn locked-dates-view" data-action="maintenance-lock-view">View</button>'
                    + (isPublic ? '<button type="button" class="material-btn locked-dates-action-btn locked-dates-unlock" data-action="maintenance-lock">Lock</button>' : '')
                    + '</div>';
            return '<tr data-partner="' + escapeHtml(partner) + '" data-date="' + escapeHtml(date) + '">' +
                '<td>' + escapeHtml(partner || 'N/A') + '</td>' +
                '<td>' + escapeHtml(displayDate(date)) + '</td>' +
                '<td><span class="locked-dates-status"' + (isNoData ? '' : (isLocked ? ' style="background:#ecfdf5;color:#047857;"' : ' style="background:#fef3c7;color:#b45309;"')) + '>' + (isNoData ? 'No Data' : (isLocked ? 'Locked' : 'Unlocked')) + '</span></td>' +
                '<td>' + actions + '</td>' +
                '</tr>';
        }).join('');

        pagination.hidden = false;
        const monthParts = String(currentGroup && currentGroup.key || '').split('-');
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const monthLabel = monthParts.length === 2 ? (monthNames[Number(monthParts[1]) - 1] || '') + ' ' + monthParts[0] : '';
        pageInfo.textContent = 'Page ' + page + ' of ' + totalPages + ' (' + monthLabel + ', ' + currentRows.length + ' date' + (currentRows.length === 1 ? '' : 's') + ')';
        pagination.querySelector('[data-unlock-page="previous"]').disabled = page <= 1;
        pagination.querySelector('[data-unlock-page="next"]').disabled = page >= totalPages;
    }

    async function load() {
        if (loading) return;
        const partner = String(partnerFilter.value || '').trim();
        const startDate = normalizeDate(startDateFilter.value || '');
        const endDate = normalizeDate(endDateFilter.value || '');
        const validPartner = partnerOptions.some(function (item) { return String(item).trim().toUpperCase() === partner.toUpperCase(); });
        if (!validPartner) {
            setMessage('Select a valid corporate partner.', 'error');
            return;
        }
        if (!startDate || !endDate) {
            setMessage('Select both Start Date and End Date.', 'error');
            return;
        }
        if (startDate > endDate) {
            setMessage('Start Date cannot be later than End Date.', 'error');
            return;
        }
        const rangeDays = Math.floor((new Date(endDate + 'T00:00:00') - new Date(startDate + 'T00:00:00')) / 86400000) + 1;
        if (rangeDays > 366) {
            setMessage('Date range cannot exceed 366 days.', 'error');
            return;
        }
        loading = true;
        setLoadingState(true);
        setMessage('', '');
        body.innerHTML = '<tr><td colspan="4" class="locked-dates-loading">Loading locked dates...</td></tr>';
        pagination.hidden = true;
        try {
            const response = await fetch(baseUrl() + '/src/controllers/recon/get_transaction_lock_range.php?partnername=' + encodeURIComponent(partner) + '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate), {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const data = await response.json();
            if (!response.ok || !data || !data.success) throw new Error((data && (data.error || data.message)) || 'Failed to load locked reconciliation dates.');
            rows = Array.isArray(data.rows) ? data.rows : [];
            page = 1;
            render();
        } catch (error) {
            rows = [];
            render();
            setMessage(error.message || 'Failed to load locked reconciliation dates.', 'error');
        } finally {
            loading = false;
            setLoadingState(false);
        }
    }

    async function confirmUnlock(partner, date) {
        const prompt = 'Unlock ' + partner + ' for ' + displayDate(date) + '?';
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Confirm Unlock', text: prompt, icon: 'warning',
                showCancelButton: true, confirmButtonText: 'Unlock', confirmButtonColor: '#dc3545', heightAuto: false
            });
            return !!result.isConfirmed;
        }
        return window.confirm(prompt);
    }

    async function viewDetails(row) {
        if (typeof window.openTransactionLockReconciliationDetails !== 'function') {
            setMessage('The reconciliation details viewer is unavailable.', 'error');
            return;
        }
        row.dataset.partnername = row.getAttribute('data-partner') || '';
        row.dataset.transactionDate = row.getAttribute('data-date') || '';
        await window.openTransactionLockReconciliationDetails(row, {
            maintenanceButtonMode: isPublic ? 'lock' : 'hidden',
            authoritativeStatus: 'unlocked'
        });
    }

    async function viewLockedDetails(row, button) {
        if (typeof window.openLockedReconciliationDetails !== 'function') {
            setMessage('The locked reconciliation details viewer is unavailable.', 'error');
            return;
        }
        row.dataset.partnername = row.getAttribute('data-partner') || '';
        row.dataset.transactionDate = row.getAttribute('data-date') || '';
        button.setAttribute('data-action', 'view-locked-date-details');
        try {
            await window.openLockedReconciliationDetails(row, {
                maintenanceUnlockOnly: isAdmin,
                maintenanceButtonMode: isAdmin ? 'unlock' : 'hidden'
            });
        } finally {
            button.setAttribute('data-action', 'maintenance-locked-view');
        }
    }

    async function unlock(row, button) {
        const partner = row.getAttribute('data-partner') || '';
        const date = row.getAttribute('data-date') || '';
        if (!partner || !date || !(await confirmUnlock(partner, date))) return;

        button.disabled = true;
        button.textContent = 'Unlocking...';
        setMessage('', '');
        try {
            const response = await fetch(baseUrl() + '/src/controllers/recon/unlock_reconciliation_date.php', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({partnername: partner, transaction_date: date})
            });
            const data = await response.json();
            if (!response.ok || !data || !data.success) throw new Error((data && (data.error || data.message)) || 'Failed to unlock reconciliation date.');
            rows.forEach(function (item) {
                const itemPartner = String(item.partnername || item.corporate_partner || '').trim().toUpperCase();
                const itemDate = normalizeDate(item.transaction_date || item.recon_date || item.date || '');
                if (itemPartner === partner.toUpperCase() && itemDate === date) item.status = 'unlocked';
            });
            render();
            setMessage('Reconciliation date unlocked successfully.', 'success');
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Unlock';
            setMessage(error.message || 'Failed to unlock reconciliation date.', 'error');
        }
    }

    async function lock(row, button) {
        const partner = row.getAttribute('data-partner') || '';
        const date = row.getAttribute('data-date') || '';
        if (!partner || !date) return;
        let confirmed = true;
        const prompt = 'Lock ' + partner + ' for ' + displayDate(date) + '?';
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Confirm Lock', text: prompt, icon: 'question', showCancelButton: true,
                confirmButtonText: 'Lock', confirmButtonColor: '#dc3545', heightAuto: false
            });
            confirmed = !!result.isConfirmed;
        } else {
            confirmed = window.confirm(prompt);
        }
        if (!confirmed) return;

        button.disabled = true;
        button.textContent = 'Locking...';
        setMessage('', '');
        try {
            const response = await fetch(baseUrl() + '/src/controllers/recon/lock_reconciliation_date.php', {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({partnername: partner, transaction_date: date})
            });
            const data = await response.json();
            if (!response.ok || !data || !data.success) throw new Error((data && (data.error || data.message)) || 'Failed to lock reconciliation date.');
            rows.forEach(function (item) {
                const itemPartner = String(item.partnername || item.corporate_partner || '').trim().toUpperCase();
                const itemDate = normalizeDate(item.transaction_date || item.recon_date || item.date || '');
                if (itemPartner === partner.toUpperCase() && itemDate === date) item.status = 'locked';
            });
            render();
            setMessage('Reconciliation date locked successfully.', 'success');
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Lock';
            setMessage(error.message || 'Failed to lock reconciliation date.', 'error');
        }
    }

    async function exportToPdf() {
        const exportRows = filteredRows();
        if (!exportRows.length) {
            setMessage('There are no results to export.', 'error');
            return;
        }
        exportPdfButton.disabled = true;
        exportPdfButton.textContent = 'Exporting...';
        setMessage('', '');
        try {
            const response = await fetch(baseUrl() + '/src/controllers/recon/export_transaction_lock_pdf.php', {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    partner: String(partnerFilter.value || '').trim(),
                    start_date: normalizeDate(startDateFilter.value || ''),
                    end_date: normalizeDate(endDateFilter.value || ''),
                    status_filter: statusFilter.options[statusFilter.selectedIndex].text,
                    rows: exportRows.map(function (row) {
                        return {transaction_date: normalizeDate(row.transaction_date || ''), status: String(row.status || '')};
                    })
                })
            });
            if (!response.ok) throw new Error((await response.text()) || 'Failed to export PDF.');
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'TRANSACTION-LOCK-REPORT-from-' + normalizeDate(startDateFilter.value || '') + '-to-' + normalizeDate(endDateFilter.value || '') + '.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        } catch (error) {
            setMessage(error.message || 'Failed to export PDF.', 'error');
        } finally {
            exportPdfButton.textContent = 'Export to PDF';
            exportPdfButton.disabled = filteredRows().length === 0;
        }
    }

    partnerFilter.addEventListener('input', function () {
        clearProcessedResults();
        renderPartnerSuggestions();
    });
    partnerFilter.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePartnerSuggestions();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (partnerSuggestions.hidden) renderPartnerSuggestions();
            movePartnerSuggestion(event.key === 'ArrowDown' ? 1 : -1);
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!partnerSuggestions.hidden) {
                const options = Array.from(partnerSuggestions.querySelectorAll('[data-partner]'));
                const typedValue = String(partnerFilter.value || '').trim().toUpperCase();
                const exactMatch = options.find(function (option) {
                    return String(option.getAttribute('data-partner') || '').trim().toUpperCase() === typedValue;
                });
                selectPartnerSuggestion(exactMatch || options[activePartnerIndex] || options[0]);
                return;
            }
            load();
        }
    });
    partnerSuggestions.addEventListener('mousedown', function (event) {
        const option = event.target.closest('[data-partner]');
        if (!option) return;
        event.preventDefault();
        selectPartnerSuggestion(option);
    });
    document.addEventListener('mousedown', function (event) {
        if (!event.target.closest('#maintenanceUnlockPartner') && !event.target.closest('#maintenanceUnlockPartnerSuggestions')) closePartnerSuggestions();
    });
    startDateFilter.addEventListener('change', function () {
        const startDate = normalizeDate(startDateFilter.value || '');
        endDateFilter.min = startDate;
        endDateFilter.value = startDate;
        clearProcessedResults();
    });
    endDateFilter.addEventListener('change', clearProcessedResults);
    statusFilter.addEventListener('change', function () {
        if (!rows.length) return;
        page = 1;
        render();
    });
    [startDateFilter, endDateFilter].forEach(function (dateInput) {
        dateInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            load();
        });
    });
    processButton.addEventListener('click', function () {
        load();
    });
    exportPdfButton.addEventListener('click', exportToPdf);
    pagination.addEventListener('click', function (event) {
        const button = event.target.closest('[data-unlock-page]');
        if (!button) return;
        page += button.getAttribute('data-unlock-page') === 'next' ? 1 : -1;
        render();
    });
    body.addEventListener('click', function (event) {
        const lockedViewButton = event.target.closest('[data-action="maintenance-locked-view"]');
        if (lockedViewButton) {
            viewLockedDetails(lockedViewButton.closest('tr'), lockedViewButton);
            return;
        }
        const viewButton = event.target.closest('[data-action="maintenance-lock-view"]');
        if (viewButton) {
            viewDetails(viewButton.closest('tr'));
            return;
        }
        const unlockButton = event.target.closest('[data-action="maintenance-unlock"]');
        if (unlockButton && isAdmin) {
            unlock(unlockButton.closest('tr'), unlockButton);
            return;
        }
        const lockButton = event.target.closest('[data-action="maintenance-lock"]');
        if (lockButton && isPublic) lock(lockButton.closest('tr'), lockButton);
    });
})();
</script>
