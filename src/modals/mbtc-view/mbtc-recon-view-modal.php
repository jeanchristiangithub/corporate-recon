<?php
// METROBANK HEAD OFFICE Recon View Modal
// Displays per-day partner vs web rows (reference, principal, commission, date)
?>
<link rel="stylesheet" href="<?= htmlspecialchars((string)($appBaseUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>/src/modals/mbtc-view/mbtc-recon-view-modal.css">

<div class="mbtc-recon-modal" id="mbtcReconViewModal" style="display:none;" role="dialog" aria-modal="true" aria-label="METROBANK HEAD OFFICE Recon Details">
    <div class="mbtc-recon-modal__panel">
        <div class="mbtc-recon-modal__head">
            <h3>METROBANK HEAD OFFICE Recon Details</h3>
            <button type="button" class="mbtc-recon-modal__close" data-action="close-mbtc-recon" aria-label="Close">CLOSE</button>
        </div>

        <div class="mbtc-recon-modal__top">
            <div class="mbtc-recon-modal__summary-wrap">
                <p class="mbtc-recon-modal__summary" data-role="summary">Matched: 0 | Not Matched: 0 | Duplicates: 0</p>
            </div>

            <div class="mbtc-recon-modal__controls">
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="matched">Match Only</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
                <button id="mbtcLockAllMatchedBtn" class="mbtc-lock-all-btn" type="button">LOCK MATCHED TRANSACTIONS</button>
            </div>

        </div>

        <div class="mbtc-recon-modal__tables" data-role="globalScroll">
            <section>
                <div class="mbtc-section-header">
                    <h4>Partners Data <span data-role="partnersCount" class="comparison-count">(0)</span></h4>
                    <div class="mbtc-section-metrics">
                        <div class="mbtc-volume" data-role="partnersVolume">Volume: 0</div>
                        <div class="mbtc-principal" data-role="partnersPrincipal">Principal: 0.00 pesos</div>
                    </div>
                </div>
                <div class="mbtc-table-shell mbtc-table-shell--partners">
                    <table class="mbtc-table mbtc-table--partners mbtc-table--head">
                        <colgroup>
                            <col class="mbtc-col-date">
                            <col class="mbtc-col-ref">
                            <col class="mbtc-col-amount">
                            <col class="mbtc-col-commission">
                        </colgroup>
                        <thead data-role="partnersHead">
                            <tr>
                                <th>Date</th>
                                <th>Reference ID</th>
                                <th>Amount in PHP</th>
                                <th>Commission</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="mbtc-scroll-lock-header" aria-hidden="true">&#128274;</div>
                    <div class="mbtc-table-body-scroll" data-role="partnersScroll">
                        <table class="mbtc-table mbtc-table--partners mbtc-table--body">
                            <colgroup>
                                <col class="mbtc-col-date">
                                <col class="mbtc-col-ref">
                                <col class="mbtc-col-amount">
                                <col class="mbtc-col-commission">
                            </colgroup>
                            <tbody data-role="partnersBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section>
                <div class="mbtc-section-header">
                    <h4>KPX Web Data <span data-role="webCount" class="comparison-count">(0)</span></h4>
                    <div class="mbtc-section-metrics">
                        <div class="mbtc-volume" data-role="webVolume">Volume: 0</div>
                        <div class="mbtc-principal" data-role="webPrincipal">Principal: 0.00 pesos</div>
                    </div>
                </div>
                <div class="mbtc-table-shell mbtc-table-shell--web">
                    <table class="mbtc-table mbtc-table--web mbtc-table--head">
                        <colgroup>
                            <col class="mbtc-col-date">
                            <col class="mbtc-col-kptn">
                            <col class="mbtc-col-ref">
                            <col class="mbtc-col-amount">
                            <col class="mbtc-col-currency">
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
                    <div class="mbtc-table-body-scroll" data-role="webScroll">
                        <table class="mbtc-table mbtc-table--web mbtc-table--body">
                            <colgroup>
                                <col class="mbtc-col-date">
                                <col class="mbtc-col-kptn">
                                <col class="mbtc-col-ref">
                                <col class="mbtc-col-amount">
                                <col class="mbtc-col-currency">
                            </colgroup>
                            <tbody data-role="webBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="mbtc-recon-modal__loading" style="display:none;" aria-hidden="true">
            <div class="mbtc-recon-modal__loader">Loading…</div>
        </div>

    </div>
</div>

<div id="mbtcWarningModal" class="mbtc-warning-modal" style="display:none;">
    <div class="mbtc-warning-modal__overlay"></div>
    <div class="mbtc-warning-modal__dialog" role="dialog" aria-modal="true" aria-label="Unsecured Matched Transactions Warning">
        <div class="mbtc-warning-modal__icon">&#9888;</div>
        <h3 class="mbtc-warning-modal__title">Unsecured Matched Transactions Detected</h3>
        <p class="mbtc-warning-modal__message">There are matched transactions that are still unlocked.<br>Please lock them before closing to preserve reconciliation integrity.</p>
        <div class="mbtc-warning-modal__footer">
            <button type="button" class="mbtc-warning-modal__cancel">Cancel</button>
            <button type="button" class="mbtc-warning-modal__close-anyway">Close Anyway</button>
        </div>
    </div>
</div>

<!-- Styles are kept in mbtc-recon-view-modal.css to keep markup clean -->
<script>
(function () {
    const modal = document.getElementById('mbtcReconViewModal');
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
    const modal = document.getElementById('mbtcReconViewModal');
    if(!modal || modal.dataset.lockLogicBound === 'true') return;

    const lockBtn = modal.querySelector('#mbtcLockAllMatchedBtn');
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
        }catch(e){ console.warn('Failed to fetch MBTC row locks', e); }
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
        }catch(e){ console.warn('Failed to fetch MBTC lock status', e); }
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
                console.error('Error saving MBTC lock state', e);
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
    const modal = document.getElementById('mbtcReconViewModal');
    const warningModal = document.getElementById('mbtcWarningModal');
    if(!modal || !warningModal || modal.dataset.closeWarningBound === 'true') return;

    const cancelBtn = warningModal.querySelector('.mbtc-warning-modal__cancel');
    const closeAnywayBtn = warningModal.querySelector('.mbtc-warning-modal__close-anyway');
    const closeBtn = modal.querySelector('[data-action="close-mbtc-recon"]');

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
        modal._mbtcForceClosing = true;
        const loadingEl = modal.querySelector('.mbtc-recon-modal__loading');
        if(loadingEl) loadingEl.style.display = 'none';
        const searchEl = modal.querySelector('[data-role="resultSearch"]');
        if(searchEl) searchEl.value = '';
        const filterEl = modal.querySelector('[data-role="resultFilter"]');
        if(filterEl) filterEl.value = 'all';
        modal.style.display = 'none';
        try{ document.body.style.overflow = ''; }catch(e){}
        setTimeout(function(){ modal._mbtcForceClosing = false; }, 300);
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
            if(modal._mbtcForceClosing) return;
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
