<?php
// WORLD INTERNATIONAL COMMUNICATIONS Reconciliation View Modal
// Displays per-day partner vs web rows (reference, principal, commission, date)
?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/moneygram-view/moneygram-recon-view-modal.css">

<div class="moneygram-recon-modal" id="moneygramReconViewModal" style="display:none;" role="dialog" aria-modal="true" aria-label="WORLD INTERNATIONAL COMMUNICATIONS Reconciliation Details">
    <div class="moneygram-recon-modal__panel">
        <div class="moneygram-recon-modal__head">
            <h3>MONEYGRAM Reconciliation Details</h3>
             <button type="button" class="moneygram-recon-modal__close" data-action="close-moneygram-recon" aria-label="Close">CLOSE</button>
        </div>

        <div class="moneygram-recon-modal__top">
            <div class="moneygram-recon-modal__summary-wrap">
                <p class="moneygram-recon-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0 | Duplicates: 0</p>
            </div>

            <div class="moneygram-recon-modal__controls">
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Currency: <span class="select-wrap"><select class="custom-select" data-role="resultCurrency"><option value="all">All</option><option value="PHP">PHP</option><option value="USD">USD</option></select></span></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="matched">Match Only</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
                <button id="moneygramLockAllMatchedBtn" class="moneygram-lock-all-btn" type="button">LOCK MATCHED TRANSACTIONS</button>
                <button id="moneygramExportExcelBtn" class="moneygram-lock-all-btn" type="button">Export to Excel</button>
            </div>

        </div>

        <div class="moneygram-recon-modal__tables moneygram-recon-modal__tables--combined" data-role="globalScroll">
            <section class="moneygram-recon-modal__combined-section">
                <div class="moneygram-section-header moneygram-section-header--combined">
                    <div>
                        <h4>Partners Data</h4>
                        <div class="moneygram-section-metrics">
                            <div class="moneygram-volume" data-role="partnersVolume">Volume: 0</div>
                            <div class="moneygram-principal" data-role="partnersPrincipalPhp">Principal: PHP: 0.00 USD: 0.00</div>
                            <div class="moneygram-principal" data-role="partnersPrincipalUsd" style="display:none;"></div>
                        </div>
                    </div>
                    <div>
                        <h4>KPX Web Data</h4>
                        <div class="moneygram-section-metrics">
                            <div class="moneygram-volume" data-role="webVolume">Volume: 0</div>
                            <div class="moneygram-principal" data-role="webPrincipalPhp">Principal: PHP: 0.00 USD: 0.00</div>
                            <div class="moneygram-principal" data-role="webPrincipalUsd" style="display:none;"></div>
                        </div>
                    </div>
                </div>
                <div class="moneygram-table-shell moneygram-table-shell--combined">
                    <div class="moneygram-table-body-scroll" data-role="partnersScroll">
                        <table class="moneygram-table moneygram-table--combined">
                            <colgroup>
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-commission">
                                <col class="moneygram-col-currency">
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-kptn">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-currency">
                                <col class="moneygram-col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th colspan="5">PARTNERS DATA</th>
                                    <th colspan="5">KPX WEB DATA</th>
                                    <th class="moneygram-status-header moneygram-status-header--group"></th>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference ID</th>
                                    <th>Amount</th>
                                    <th>Commission</th>
                                    <th>CURRENCY</th>
                                    <th>Date</th>
                                    <th>KPTN</th>
                                    <th>CCREF NO</th>
                                    <th>Amount</th>
                                    <th>CURRENCY</th>
                                    <th class="moneygram-status-header">Status</th>
                                </tr>
                            </thead>
                            <tbody data-role="partnersBody"></tbody>
                        </table>
                    </div>
                    <div class="moneygram-table-body-scroll moneygram-table-body-scroll--compat" data-role="webScroll" aria-hidden="true">
                        <table class="moneygram-table moneygram-table--web moneygram-table--body">
                            <tbody data-role="webBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="moneygram-recon-modal__loading" style="display:none;" aria-hidden="true">
            <div class="moneygram-recon-modal__loader">Loading…</div>
        </div>

    </div>
</div>

<!-- Styles are kept in moneygram-recon-view-modal.css to keep markup clean -->
<script>
(function () {
    const modal = document.getElementById('moneygramReconViewModal');
    if (!modal || modal.dataset.scrollSyncBound === 'true') {
        return;
    }

    if (modal.querySelector('.moneygram-table--combined')) {
        modal.dataset.scrollSyncBound = 'true';
        return;
    }

    const partnersScroll = modal.querySelector('[data-role="partnersScroll"]');
    const webScroll = modal.querySelector('[data-role="webScroll"]');
    if (!partnersScroll || !webScroll) {
        return;
    }

    let syncingSource = null;

    const syncScroll = function (source, target) {
        if (syncingSource && syncingSource !== source) {
            return;
        }

        syncingSource = source;
        target.scrollTop = source.scrollTop;

        window.requestAnimationFrame(function () {
            if (syncingSource === source) {
                syncingSource = null;
            }
        });
    };

    partnersScroll.addEventListener('scroll', function () {
        syncScroll(partnersScroll, webScroll);
    }, { passive: true });

    webScroll.addEventListener('scroll', function () {
        syncScroll(webScroll, partnersScroll);
    }, { passive: true });

    partnersScroll.addEventListener('wheel', function (event) {
        if (!event.deltaY) {
            return;
        }

        event.preventDefault();
        webScroll.scrollTop += event.deltaY;
    }, { passive: false });

    modal.dataset.scrollSyncBound = 'true';
})();
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="moneygramPartnerDetailModal" class="moneygram-partner-detail-modal" style="display:none;">
    <div class="moneygram-partner-detail-modal__overlay"></div>
    <div class="moneygram-partner-detail-modal__dialog" role="dialog" aria-modal="true" aria-label="MONEYGRAM Partner Transaction Details">
        <div class="moneygram-partner-detail-modal__header">
            <h3>MONEYGRAM Partner Transaction Details</h3>
        </div>
        <div class="moneygram-partner-detail-modal__body" data-role="moneygramPartnerDetailBody">
            Loading...
        </div>
        <div class="moneygram-partner-detail-modal__footer">
            <button type="button" class="moneygram-partner-detail-modal__close" data-action="close-moneygram-partner-detail">Close</button>
        </div>
    </div>
</div>

<!-- Warning Modal for Unsecured Matched Transactions -->
<div id="moneygramWarningModal" class="moneygram-warning-modal" style="display:none;">
    <div class="moneygram-warning-modal__overlay"></div>
    <div class="moneygram-warning-modal__dialog" role="dialog" aria-modal="true" aria-label="Unsecured Matched Transactions Warning">
        <div class="moneygram-warning-modal__icon">⚠️</div>
        <h3 class="moneygram-warning-modal__title">Unsecured Matched Transactions Detected</h3>
        <p class="moneygram-warning-modal__message">There are matched transactions that are still unlocked.<br>Please lock them before closing to preserve reconciliation integrity.</p>
        <div class="moneygram-warning-modal__footer">
            <button type="button" class="moneygram-warning-modal__cancel">Cancel</button>
            <button type="button" class="moneygram-warning-modal__close-anyway">Close Anyway</button>
        </div>
    </div>
</div>

<script>
// SweetAlert helpers that preserve the existing Promise-based call sites.
window.showConfirmModal = function(message, opts){
    const title = (opts && opts.title) ? opts.title : (String(message).toLowerCase().includes('unlock') ? 'Confirm Unlock' : (String(message).toLowerCase().includes('lock') ? 'Confirm Lock' : 'Confirm'));
    const confirmText = (opts && opts.confirmText) ? opts.confirmText : (String(message).toLowerCase().includes('unlock') ? 'Unlock' : 'Lock');

    if(!window.Swal){
        return Promise.resolve(window.confirm ? window.confirm(message) : false);
    }

    return Swal.fire({
        title: title,
        text: (opts && opts.hideText) ? '' : message,
        icon: (opts && opts.icon) ? opts.icon : 'question',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: (opts && opts.cancelText) ? opts.cancelText : 'Cancel',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        customClass: {
            popup: 'moneygram-swal-popup',
            confirmButton: 'moneygram-swal-confirm',
            cancelButton: 'moneygram-swal-cancel'
        }
    }).then(result => !!result.isConfirmed);
};

window.showAlertModal = function(message, opts){
    if(!window.Swal){
        if(window.alert) window.alert(message);
        return Promise.resolve();
    }

    return Swal.fire({
        title: (opts && opts.title) ? opts.title : 'Notice',
        text: message,
        icon: (opts && opts.icon) ? opts.icon : 'info',
        confirmButtonText: (opts && opts.confirmText) ? opts.confirmText : 'OK',
        confirmButtonColor: '#dc3545',
        customClass: {
            popup: 'moneygram-swal-popup',
            confirmButton: 'moneygram-swal-confirm'
        }
    }).then(() => undefined);
};

window.showSuccessToast = function(message, timeout){
    if(!window.Swal){
        if(window.alert) window.alert(message);
        return;
    }

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: timeout || 1250,
        timerProgressBar: true
    });
};
</script>

<script>
(function(){
    const modal = document.getElementById('moneygramReconViewModal');
    if(!modal) return;

    const lockBtn = modal.querySelector('#moneygramLockAllMatchedBtn');
    const exportExcelBtn = modal.querySelector('#moneygramExportExcelBtn');
    const LOCK_LABEL = 'LOCK MATCHED TRANSACTIONS';
    const UNLOCK_LABEL = 'UNLOCK MATCHED TRANSACTIONS';

    if(exportExcelBtn && exportExcelBtn.dataset.bound !== 'true'){
        exportExcelBtn.dataset.bound = 'true';
        exportExcelBtn.addEventListener('click', function(e){
            e.preventDefault();
            const startEl = document.getElementById('hsStartDate');
            const endEl = document.getElementById('hsEndDate');
            const companyEl = document.getElementById('hsCompany');
            const currencyEl = modal.querySelector('[data-role="resultCurrency"]');
            const filterEl = modal.querySelector('[data-role="resultFilter"]');
            const startDate = startEl ? String(startEl.value || '').trim() : '';
            const endDate = endEl ? String(endEl.value || '').trim() : '';
            const partnerName = companyEl && String(companyEl.value || '').trim() ? String(companyEl.value || '').trim() : 'MONEYGRAM';
            const currency = currencyEl ? String(currencyEl.value || 'all').trim() : 'all';
            const filter = filterEl ? String(filterEl.value || 'all').trim() : 'all';

            if(!startDate || !endDate){
                if(window.showAlertModal){
                    window.showAlertModal('Please select start and end date before exporting.', { title: 'Missing Date', icon: 'warning' });
                } else {
                    alert('Please select start and end date before exporting.');
                }
                return;
            }

            const baseUrl = window.autoreconBaseUrl || '';
            const url = baseUrl + '/src/modals/generate/recon-details-report/excel/moneygram-recon/moneygram-recon-format.php'
                + '?start_date=' + encodeURIComponent(startDate)
                + '&end_date=' + encodeURIComponent(endDate)
                + '&partnerName=' + encodeURIComponent(partnerName)
                + '&currency=' + encodeURIComponent(currency)
                + '&filter=' + encodeURIComponent(filter);
            window.location.href = url;
        });
    }
    
    // Setup warning modal button handlers ONCE (not repeatedly)
    const warningModal = document.getElementById('moneygramWarningModal');
    if(warningModal){
        const warningCancelBtn = warningModal.querySelector('.moneygram-warning-modal__cancel');
        const warningCloseBtn = warningModal.querySelector('.moneygram-warning-modal__close-anyway');
        
        let isWarningModalActive = false;
        let pendingClose = false;
        
        // Cancel button: close warning only
        if(warningCancelBtn){
            warningCancelBtn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                if(!isWarningModalActive) return;
                warningModal.style.display = 'none';
                isWarningModalActive = false;
                pendingClose = false;
            });
        }
        
        // Close Anyway button: close warning AND main modal
        if(warningCloseBtn){
            warningCloseBtn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                if(!isWarningModalActive) return;
                
                // Hide warning modal first
                warningModal.style.display = 'none';
                isWarningModalActive = false;
                pendingClose = false;
                
                // Close main modal cleanly - bypass any existing onclick handlers
                setTimeout(function(){
                    // Set flag to indicate we're handling close via warning
                    modal._bypassingWarning = true;
                    modal._forceClosing = true;
                    
                    modal.style.display = 'none';
                    try{ document.body.style.overflow = ''; }catch(ex){}
                    
                    // Clear any search/filter state
                    const searchEl = modal.querySelector('[data-role="resultSearch"]');
                    if(searchEl) searchEl.value = '';
                    const currencyEl = modal.querySelector('[data-role="resultCurrency"]');
                    if(currencyEl) currencyEl.value = 'all';
                    const filterEl = modal.querySelector('[data-role="resultFilter"]');
                    if(filterEl) filterEl.value = 'all';
                    
                    // Clear loading state if any
                    const loadingEl = modal.querySelector('.moneygram-recon-modal__loading');
                    if(loadingEl) loadingEl.style.display = 'none';
                    
                    // Prevent any code from reopening the modal for the next 500ms
                    setTimeout(function(){ 
                        modal._bypassingWarning = false; 
                        modal._forceClosing = false;
                    }, 500);
                }, 100);
            });
        }
        
        // Store state reference on modal for close button handler
        modal._warningState = { isActive: () => isWarningModalActive, setActive: (v) => { isWarningModalActive = v; } };
    }
    
    function collectMatchedRefs(){
        if(modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs)){
            return Array.from(new Set(modal._moneygramVirtual.pairs
                .filter(pair => !pair.isMismatch && !pair.isDuplicate && String(pair.partnerRef || '').trim())
                .map(pair => String(pair.partnerRef || '').trim())));
        }
        const refs = new Set();
        // only collect refs from partner-side matched rows (those render the check icon)
        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
            const r = (tr.dataset && tr.dataset.ref) ? String(tr.dataset.ref).trim() : (tr.cells[1] && tr.cells[1].textContent ? tr.cells[1].textContent.trim() : '');
            if(r) refs.add(r);
        });
        return Array.from(refs);
    }

    function collectMatchedDates(){
        if(modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs)){
            const dates = new Set();
            modal._moneygramVirtual.pairs.forEach(pair => {
                if(pair.isMismatch || pair.isDuplicate) return;
                const raw = String(pair.pairDateIso || '').trim();
                if(raw) dates.add(raw);
            });
            return Array.from(dates);
        }
        const dates = new Set();
        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
            const isoDate = (tr.dataset && tr.dataset.isoDate) ? String(tr.dataset.isoDate).trim() : '';
            const firstCellText = tr.cells[0] && tr.cells[0].textContent ? tr.cells[0].textContent.trim() : '';
            const raw = isoDate || firstCellText;
            if(!raw) return;
            const parsed = new Date(raw);
            if(isNaN(parsed.getTime())) return;
            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            dates.add(y + '-' + m + '-' + d);
        });
        return Array.from(dates);
    }

    async function fetchRowLocks(){
        if(modal.dataset.lockedView === 'true') return;
        const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
        const date = modal.dataset.reconDate || '';
        if(!partner || !date) return;
        try{
            const url = window.autoreconBaseUrl + '/src/controllers/recon/get_row_locks.php?partner=' + encodeURIComponent(partner) + '&date=' + encodeURIComponent(date);
            const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
            if(!res || !res.ok) return;
            const json = await res.json();
            const locks = Array.isArray(json && json.locks) ? json.locks : [];
            const lockSet = new Set(locks.map(ref => String(ref || '').trim()).filter(Boolean));
            if(modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs) && lockSet.size){
                modal._moneygramVirtual.pairs.forEach(pair => {
                    if(lockSet.has(String(pair.partnerRef || '').trim())){
                        pair.locked = true;
                    }
                });
                if(typeof modal._moneygramVirtual.render === 'function') modal._moneygramVirtual.render();
            }
            // mark partner rows that match these refs in the currently rendered slice
            if(lockSet.size){
                Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
                    if(lockSet.has(String(tr.dataset.ref || '').trim())){
                        tr.classList.add('locked-row');
                        tr.classList.add('is-locked-row');
                    }
                });
            }
            if(locks.length && lockBtn){ lockBtn.disabled = false; }
        }catch(e){ console.warn('Failed to fetch row locks', e); }
    }

    async function fetchActiveLockStatus(){
        if(modal.dataset.lockedView === 'true'){
            if(lockBtn){
                lockBtn.disabled = false;
                lockBtn.textContent = UNLOCK_LABEL;
            }
            return;
        }
        const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
        const dates = collectMatchedDates();
        if(!partner || !dates.length) return;
        try{
            const url = window.autoreconBaseUrl + '/src/controllers/recon/get_active_locked_dates.php';
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ partner: partner, dates: dates })
            });
            if(!res || !res.ok) return;
            const json = await res.json();
            const hasActiveLocks = json && json.has_active_locks;
            if(lockBtn){
                lockBtn.disabled = false;
                lockBtn.textContent = hasActiveLocks ? UNLOCK_LABEL : LOCK_LABEL;
            }
        }catch(e){ console.warn('Failed to fetch active lock status', e); }
    }

    // Observe reconDate attribute to know when modal is opened/assigned
    const mo = new MutationObserver(muts => {
        for(const m of muts){
            if(m.attributeName === 'data-recon-date' || m.attributeName === 'data-virtual-ready'){
                if(modal.dataset.lockedView === 'true'){
                    if(lockBtn){
                        lockBtn.disabled = false;
                        lockBtn.textContent = UNLOCK_LABEL;
                    }
                    continue;
                }
                fetchRowLocks();
                fetchActiveLockStatus();
            }
        }
    });
    mo.observe(modal, { attributes: true });

    if(lockBtn){
        lockBtn.addEventListener('click', async function(){
            if(typeof IS_ADMIN !== 'undefined' && !IS_ADMIN){ await (window.showAlertModal ? showAlertModal('You are not authorized to lock transactions.') : Promise.resolve()); return; }
            const refs = collectMatchedRefs();
            const dates = collectMatchedDates();
            if(!refs || refs.length === 0){ await (window.showAlertModal ? showAlertModal('No matched rows to lock/unlock.') : Promise.resolve()); return; }
            // determine mode from button text
            const mode = String(lockBtn.textContent || '').trim() === UNLOCK_LABEL ? 'unlock' : 'lock';
            const confirmMsg = mode === 'lock' ? 'Lock matched transactions?' : 'Unlock matched transactions?';
            const ok = await (window.showConfirmModal ? showConfirmModal(confirmMsg, { title: mode === 'lock' ? 'Confirm Lock' : 'Confirm Unlock', confirmText: mode === 'lock' ? 'Lock' : 'Unlock', cancelText: 'Cancel', hideText: true, icon: 'question' }) : Promise.resolve(confirm(confirmMsg)));
            if(!ok) return;
            const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
            const date = modal.dataset.reconDate || '';
            try{
                lockBtn.disabled = true; lockBtn.textContent = (mode === 'lock' ? 'Locking…' : 'Unlocking…');
                const endpoint = (mode === 'lock') ? window.autoreconBaseUrl + '/src/controllers/recon/lock_matched_rows.php' : window.autoreconBaseUrl + '/src/controllers/recon/unlock_matched_rows.php';
                const res = await fetch(endpoint, {
                    method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ partner: partner, date: date, refs: refs, dates: dates })
                });
                if(!res || !res.ok){
                    const txt = await (res ? res.text() : Promise.resolve(''));
                    console.warn('Lock/unlock request failed', res && res.status, txt);
                    await (window.showAlertModal ? showAlertModal('Server error while saving lock state.') : Promise.resolve());
                    lockBtn.disabled = false; lockBtn.textContent = (mode === 'lock' ? LOCK_LABEL : UNLOCK_LABEL);
                    return;
                }
                const json = await res.json();
                if(json && json.success){
                    if(mode === 'lock'){
                        if(modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs)){
                            modal._moneygramVirtual.pairs.forEach(pair => {
                                if(refs.indexOf(String(pair.partnerRef || '').trim()) !== -1) pair.locked = true;
                            });
                            if(typeof modal._moneygramVirtual.render === 'function') modal._moneygramVirtual.render();
                        }
                        // mark partner matched rows as locked
                        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
                            const ref = String(tr.dataset.ref || '').trim();
                            if(ref && refs.indexOf(ref) !== -1){
                                tr.classList.add('locked-row');
                                tr.classList.add('is-locked-row');
                            }
                        });
                        lockBtn.textContent = UNLOCK_LABEL;
                        lockBtn.disabled = false;
                        window.showSuccessToast && showSuccessToast('Matched transactions locked successfully.');
                    } else {
                        if(modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs)){
                            modal._moneygramVirtual.pairs.forEach(pair => {
                                if(refs.indexOf(String(pair.partnerRef || '').trim()) !== -1) pair.locked = false;
                            });
                            if(typeof modal._moneygramVirtual.render === 'function') modal._moneygramVirtual.render();
                        }
                        // unlock: remove locked-row class from partner matched rows
                        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row.locked-row')).forEach(tr => {
                            const ref = String(tr.dataset.ref || '').trim();
                            if(ref && refs.indexOf(ref) !== -1){
                                tr.classList.remove('locked-row');
                                tr.classList.remove('is-locked-row');
                            }
                        });
                        lockBtn.textContent = LOCK_LABEL;
                        lockBtn.disabled = false;
                        window.showSuccessToast && showSuccessToast('Matched transactions unlocked successfully.');
                    }
                    fetchActiveLockStatus();
                } else {
                    console.warn('Lock/unlock service error', json);
                    await (window.showAlertModal ? showAlertModal('Failed to save lock state.') : Promise.resolve());
                    lockBtn.disabled = false; lockBtn.textContent = (mode === 'lock' ? LOCK_LABEL : UNLOCK_LABEL);
                }
            }catch(e){ console.error('Error locking/unlocking matched rows', e); await (window.showAlertModal ? showAlertModal('Failed to contact lock service.') : Promise.resolve()); lockBtn.disabled = false; lockBtn.textContent = (mode === 'lock' ? LOCK_LABEL : UNLOCK_LABEL); }
        });
    }

    // Handle close button with warning for unlocked matched transactions
    const closeBtn = modal.querySelector('[data-action="close-moneygram-recon"]');
    if(closeBtn && !closeBtn._moneygramWarningBound){
        closeBtn._moneygramWarningBound = true;
        
        // Store original onclick if it exists so we can prevent it during warning modal
        const originalOnclick = closeBtn.onclick;
        
        closeBtn.addEventListener('click', function(e){
            // Collect matched rows that are NOT locked
            const unlockedMatched = modal._moneygramVirtual && Array.isArray(modal._moneygramVirtual.pairs)
                ? modal._moneygramVirtual.pairs.filter(pair => !pair.isMismatch && !pair.isDuplicate && !pair.locked)
                : Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row:not(.locked-row)'));
            
            // If no matched rows exist or all are locked, allow normal close flow
            if(unlockedMatched.length === 0){
                // Clear any override and allow the click to proceed
                return;
            }
            
            // Prevent default and stop propagation for warning modal case
            e.preventDefault();
            e.stopImmediatePropagation();
            
            // Show warning modal instead of closing
            if(warningModal && modal._warningState){
                modal._warningState.setActive(true);
                warningModal.style.display = 'flex';
            }
        }, true); // Use capture phase to intercept before other handlers
        
        // Override onclick to prevent it from running during warning modal display
        closeBtn.onclick = function(e) {
            // If warning modal is active or we're force-closing, don't allow the onclick to run
            if(modal._bypassingWarning || modal._forceClosing || (modal._warningState && modal._warningState.isActive())) {
                return false;
            }
            // Otherwise, if there's an original onclick, call it (for normal close case)
            if(originalOnclick) {
                return originalOnclick.call(this, e);
            }
            return true;
        };
    }
    
    // Protect against modal being shown during force close window
    if(!modal._closeProtectorBound){
        modal._closeProtectorBound = true;
        const displayObserver = new MutationObserver(function(mutations){
            for(const m of mutations){
                if(m.attributeName === 'style'){
                    // If the modal is being shown but we're in force-closing mode, hide it immediately
                    if(modal._forceClosing && modal.style.display !== 'none'){
                        modal.style.display = 'none';
                    }
                }
            }
        });
        displayObserver.observe(modal, { attributes: true, attributeFilter: ['style'] });
    }
})();
</script>
