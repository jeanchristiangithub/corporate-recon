<?php
// WORLD INTERNATIONAL COMMUNICATIONS Recon View Modal
// Displays per-day partner vs web rows (reference, principal, commission, date)
?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/wic-view/wic-recon-view-modal.css">

<div class="wic-recon-modal" id="wicReconViewModal" style="display:none;" role="dialog" aria-modal="true" aria-label="WORLD INTERNATIONAL COMMUNICATIONS Recon Details">
    <div class="wic-recon-modal__panel">
        <div class="wic-recon-modal__head">
            <h3>WORLDCOM INTERNATIONAL COMMUNICATIONS Recon Details</h3>
             <button type="button" class="wic-recon-modal__close" data-action="close-wic-recon" aria-label="Close">CLOSE</button>
        </div>

        <div class="wic-recon-modal__top">
            <div class="wic-recon-modal__summary-wrap">
                <p class="wic-recon-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0</p>
            </div>

            <div class="wic-recon-modal__controls">
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="matched">Match Only</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
                <button id="wicLockAllMatchedBtn" class="wic-lock-all-btn" type="button">LOCK MATCHED TRANSACTIONS</button>
            </div>

        </div>

        <div class="wic-recon-modal__tables" data-role="globalScroll">
            <section>
                <div class="wic-section-header">
                    <h4>Partners Data <span data-role="partnersCount" class="comparison-count">(0)</span></h4>
                    <div class="wic-section-metrics">
                        <div class="wic-volume" data-role="partnersVolume">Volume: 0</div>
                        <div class="wic-principal" data-role="partnersPrincipal">Principal: PHP: 0.00 USD: 0.00</div>
                    </div>
                </div>
                <div class="wic-table-shell wic-table-shell--partners">
                    <table class="wic-table wic-table--partners wic-table--head">
                        <colgroup>
                            <col class="wic-col-date">
                            <col class="wic-col-transaction">
                            <col class="wic-col-amount">
                            <col class="wic-col-coin">
                        </colgroup>
                        <thead data-role="partnersHead">
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Coin</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="wic-table-body-scroll" data-role="partnersScroll">
                        <table class="wic-table wic-table--partners wic-table--body">
                            <colgroup>
                                <col class="wic-col-date">
                                <col class="wic-col-transaction">
                                <col class="wic-col-amount">
                                <col class="wic-col-coin">
                            </colgroup>
                            <tbody data-role="partnersBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section>
                <div class="wic-section-header">
                    <h4>KPX Web Data <span data-role="webCount" class="comparison-count">(0)</span></h4>
                    <div class="wic-section-metrics">
                        <div class="wic-volume" data-role="webVolume">Volume: 0</div>
                        <div class="wic-principal" data-role="webPrincipal">Principal: PHP: 0.00 USD: 0.00</div>
                    </div>
                </div>
                <div class="wic-table-shell wic-table-shell--web">
                    <table class="wic-table wic-table--web wic-table--head">
                        <colgroup>
                            <col class="wic-col-web-date">
                            <col class="wic-col-kptn">
                            <col class="wic-col-ccref">
                            <col class="wic-col-web-amount">
                            <col class="wic-col-currency">
                        </colgroup>
                        <thead data-role="webHead">
                            <tr>
                                <th>Date</th>
                                <th>KPTN</th>
                                <th>CCREF NO</th>
                                <th>Amount</th>
                                <th>CURRENCY</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="wic-table-body-scroll" data-role="webScroll">
                        <table class="wic-table wic-table--web wic-table--body">
                            <colgroup>
                                <col class="wic-col-web-date">
                                <col class="wic-col-kptn">
                                <col class="wic-col-ccref">
                                <col class="wic-col-web-amount">
                                <col class="wic-col-currency">
                            </colgroup>
                            <tbody data-role="webBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="wic-recon-modal__loading" style="display:none;" aria-hidden="true">
            <div class="wic-recon-modal__loader">Loading…</div>
        </div>

    </div>
</div>

<!-- Styles are kept in wic-recon-view-modal.css to keep markup clean -->
<div id="wicWarningModal" class="wic-warning-modal" style="display:none;">
    <div class="wic-warning-modal__overlay"></div>
    <div class="wic-warning-modal__dialog" role="dialog" aria-modal="true" aria-label="Unsecured Matched Transactions Warning">
        <div class="wic-warning-modal__icon" aria-hidden="true">&#9888;</div>
        <h3 class="wic-warning-modal__title">Unsecured Matched Transactions Detected</h3>
        <p class="wic-warning-modal__message">There are matched transactions that are still unlocked.<br>Please lock them before closing to preserve reconciliation integrity.</p>
        <div class="wic-warning-modal__footer">
            <button type="button" class="wic-warning-modal__cancel">Cancel</button>
            <button type="button" class="wic-warning-modal__close-anyway">Close Anyway</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('wicReconViewModal');
    if (!modal || modal.dataset.scrollSyncBound === 'true') {
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
<script>
(function(){
    const modal = document.getElementById('wicReconViewModal');
    if(!modal || modal.dataset.lockLogicBound === 'true') return;

    const lockBtn = modal.querySelector('#wicLockAllMatchedBtn');
    const LOCK_LABEL = 'LOCK MATCHED TRANSACTIONS';
    const UNLOCK_LABEL = 'UNLOCK MATCHED TRANSACTIONS';

    function matchedRows(){
        return Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row'))
            .filter(tr => tr.getAttribute('data-role') !== 'date-separator' && !tr.classList.contains('dup-row'));
    }

    function collectMatchedRefs(){
        const refs = new Set();
        matchedRows().forEach(tr => {
            const ref = String((tr.dataset && tr.dataset.ref) || (tr.cells[1] && tr.cells[1].textContent) || '').trim();
            if(ref) refs.add(ref);
        });
        return Array.from(refs);
    }

    function collectMatchedDates(){
        const dates = new Set();
        matchedRows().forEach(tr => {
            const raw = String((tr.dataset && tr.dataset.isoDate) || modal.dataset.reconDate || '').trim();
            if(raw) dates.add(raw);
        });
        return Array.from(dates);
    }

    function markRowsLocked(refs, locked){
        const refSet = new Set((refs || []).map(ref => String(ref || '').trim()).filter(Boolean));
        matchedRows().forEach(tr => {
            const ref = String((tr.dataset && tr.dataset.ref) || '').trim();
            if(refSet.size && !refSet.has(ref)) return;
            tr.classList.toggle('locked-row', !!locked);
            tr.classList.toggle('is-locked-row', !!locked);
        });
    }

    async function fetchRowLocks(){
        if(modal.dataset.maintenanceAuthoritativeStatus === 'unlocked'){
            markRowsLocked([], false);
            return;
        }
        const partner = modal.dataset.partnerName || '';
        const date = modal.dataset.reconDate || '';
        if(!partner || !date) return;
        try{
            const url = window.autoreconBaseUrl + '/src/controllers/recon/get_row_locks.php?partner=' + encodeURIComponent(partner) + '&date=' + encodeURIComponent(date);
            const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
            if(!res || !res.ok) return;
            const json = await res.json();
            if(modal.dataset.maintenanceAuthoritativeStatus === 'unlocked'){
                markRowsLocked([], false);
                return;
            }
            const locks = Array.isArray(json && json.locks) ? json.locks : [];
            if(locks.length) markRowsLocked(locks, true);
        }catch(e){ console.warn('Failed to fetch WIC row locks', e); }
    }

    async function fetchActiveLockStatus(){
        if(!lockBtn) return;
        const partner = modal.dataset.partnerName || '';
        const dates = collectMatchedDates();
        if(!partner || !dates.length){
            lockBtn.disabled = false;
            lockBtn.textContent = LOCK_LABEL;
            return;
        }
        try{
            const res = await fetch(window.autoreconBaseUrl + '/src/controllers/recon/get_active_locked_dates.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ partner: partner, dates: dates })
            });
            if(!res || !res.ok) return;
            const json = await res.json();
            lockBtn.disabled = false;
            lockBtn.textContent = json && json.has_active_locks ? UNLOCK_LABEL : LOCK_LABEL;
            lockBtn.style.display = json && json.has_active_locks && modal.dataset.maintenanceUnlockOnly !== 'true' ? 'none' : '';
        }catch(e){ console.warn('Failed to fetch WIC lock status', e); }
    }

    if(lockBtn){
        lockBtn.addEventListener('click', async function(){
            if(typeof IS_ADMIN !== 'undefined' && !IS_ADMIN){
                await (window.showAlertModal ? showAlertModal('You are not authorized to lock transactions.') : Promise.resolve());
                return;
            }
            const refs = collectMatchedRefs();
            const dates = collectMatchedDates();
            if(!refs.length || !dates.length){
                await (window.showAlertModal ? showAlertModal('No matched rows to lock/unlock.') : Promise.resolve());
                return;
            }
            const mode = String(lockBtn.textContent || '').trim() === UNLOCK_LABEL ? 'unlock' : 'lock';
            const ok = await (window.showConfirmModal
                ? showConfirmModal(mode === 'lock' ? 'Lock matched transactions?' : 'Unlock matched transactions?', {
                    title: mode === 'lock' ? 'Confirm Lock' : 'Confirm Unlock',
                    confirmText: mode === 'lock' ? 'Lock' : 'Unlock',
                    cancelText: 'Cancel',
                    hideText: true,
                    icon: 'question'
                })
                : Promise.resolve(window.confirm ? window.confirm(mode === 'lock' ? 'Lock matched transactions?' : 'Unlock matched transactions?') : false));
            if(!ok) return;

            const partner = modal.dataset.partnerName || '';
            const date = modal.dataset.reconDate || dates[0] || '';
            const endpoint = mode === 'lock' ? window.autoreconBaseUrl + '/src/controllers/recon/lock_matched_rows.php' : window.autoreconBaseUrl + '/src/controllers/recon/unlock_matched_rows.php';
            try{
                lockBtn.disabled = true;
                lockBtn.textContent = mode === 'lock' ? 'Locking...' : 'Unlocking...';
                const res = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ partner: partner, date: date, refs: refs, dates: dates })
                });
                const json = await res.json().catch(() => null);
                if(!res || !res.ok || !(json && json.success)){
                    await (window.showAlertModal ? showAlertModal('Failed to save lock state.') : Promise.resolve());
                    lockBtn.disabled = false;
                    lockBtn.textContent = mode === 'lock' ? LOCK_LABEL : UNLOCK_LABEL;
                    return;
                }
                markRowsLocked(refs, mode === 'lock');
                lockBtn.disabled = false;
                lockBtn.textContent = mode === 'lock' ? UNLOCK_LABEL : LOCK_LABEL;
                lockBtn.style.display = mode === 'lock' ? 'none' : '';
                if(window.showSuccessToast){
                    showSuccessToast(mode === 'lock' ? 'Matched transactions locked successfully.' : 'Matched transactions unlocked successfully.');
                }
                fetchActiveLockStatus();
            }catch(e){
                console.error('Error saving WIC lock state', e);
                await (window.showAlertModal ? showAlertModal('Failed to contact lock service.') : Promise.resolve());
                lockBtn.disabled = false;
                lockBtn.textContent = mode === 'lock' ? LOCK_LABEL : UNLOCK_LABEL;
            }
        });
    }

    const mo = new MutationObserver((mutations) => {
        if(!mutations.some(m => m.attributeName === 'data-recon-date' || m.attributeName === 'data-lock-ready')) return;
        if(lockBtn){
            lockBtn.disabled = false;
            lockBtn.textContent = LOCK_LABEL;
        }
        fetchRowLocks();
        fetchActiveLockStatus();
    });
    mo.observe(modal, { attributes: true });
    modal.dataset.lockLogicBound = 'true';
})();
</script>
<script>
(function(){
    const modal = document.getElementById('wicReconViewModal');
    const warningModal = document.getElementById('wicWarningModal');
    if(!modal || !warningModal || modal.dataset.closeWarningBound === 'true') return;

    const cancelBtn = warningModal.querySelector('.wic-warning-modal__cancel');
    const closeAnywayBtn = warningModal.querySelector('.wic-warning-modal__close-anyway');
    const closeBtn = modal.querySelector('[data-action="close-wic-recon"]');

    function getUnlockedMatchedRows(){
        return Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row'))
            .filter(tr => tr.getAttribute('data-role') !== 'date-separator')
            .filter(tr => !tr.classList.contains('dup-row'))
            .filter(tr => !tr.classList.contains('locked-row') && !tr.classList.contains('is-locked-row'));
    }

    function hideWarning(){
        warningModal.style.display = 'none';
    }

    function forceClose(){
        hideWarning();
        modal._wicForceClosing = true;
        const loadingEl = modal.querySelector('.wic-recon-modal__loading');
        if(loadingEl) loadingEl.style.display = 'none';
        const searchEl = modal.querySelector('[data-role="resultSearch"]');
        if(searchEl) searchEl.value = '';
        const filterEl = modal.querySelector('[data-role="resultFilter"]');
        if(filterEl) filterEl.value = 'all';
        modal.style.display = 'none';
        try{ document.body.style.overflow = ''; }catch(e){}
        setTimeout(function(){ modal._wicForceClosing = false; }, 300);
    }

    if(cancelBtn){
        cancelBtn.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            hideWarning();
        });
    }

    if(closeAnywayBtn){
        closeAnywayBtn.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            forceClose();
        });
    }

    if(closeBtn){
        closeBtn.addEventListener('click', function(event){
            if(modal._wicForceClosing) return;
            if(modal.dataset.maintenanceUnlockOnly === 'true') return;
            const unlockedMatched = getUnlockedMatchedRows();
            if(unlockedMatched.length === 0) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            warningModal.style.display = 'flex';
        }, true);
    }

    modal.dataset.closeWarningBound = 'true';
})();
</script>
