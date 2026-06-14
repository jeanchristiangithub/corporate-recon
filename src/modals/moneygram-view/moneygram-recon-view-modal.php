<?php
// WORLD INTERNATIONAL COMMUNICATIONS Reconciliation View Modal
// Displays per-day partner vs web rows (reference, principal, commission, date)
?>
<link rel="stylesheet" href="/autorecon/src/modals/moneygram-view/moneygram-recon-view-modal.css">

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
                <button id="moneygramLockAllMatchedBtn" class="moneygram-lock-all-btn" type="button">LOCK MATCHED TRANSACTIONS</button>
                <label class="cmp-control-search"><input data-role="resultSearch" type="search" placeholder="Search"></label>
                <label class="cmp-control-filter">Show: <span class="select-wrap"><select class="custom-select" data-role="resultFilter"><option value="all">All</option><option value="matched">Match Only</option><option value="mismatch">Mismatch Only</option><option value="duplicates">Duplicates Only</option></select></span></label>
            </div>

        </div>

        <div class="moneygram-recon-modal__tables" data-role="globalScroll">
            <section>
                <div class="moneygram-section-header">
                <h4>Partners Data</h4>
                    <div class="moneygram-section-metrics">
                        <div class="moneygram-volume" data-role="partnersVolume">Volume: 0</div>
                        <div class="moneygram-principal" data-role="partnersPrincipalPhp">Principal PHP: 0.00 pesos</div>
                        <div class="moneygram-principal" data-role="partnersPrincipalUsd">Principal USD: 0.00</div>
                    </div>
                </div>
                <div class="moneygram-table-shell moneygram-table-shell--partners">
                    <table class="moneygram-table moneygram-table--partners moneygram-table--head">
                        <colgroup>
                            <col class="moneygram-col-date">
                            <col class="moneygram-col-ref">
                            <col class="moneygram-col-amount">
                            <col class="moneygram-col-currency">
                        </colgroup>
                        <thead data-role="partnersHead">
                            <tr>
                                <th>Date</th>
                                <th>Reference ID</th>
                                <th>Amount</th>
                                <th>CURRENCY</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="moneygram-table-body-scroll" data-role="partnersScroll">
                        <table class="moneygram-table moneygram-table--partners moneygram-table--body">
                            <colgroup>
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-currency">
                            </colgroup>
                            <tbody data-role="partnersBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section>
                <div class="moneygram-section-header">
                <h4>KPX Web Data</h4>
                    <div class="moneygram-section-metrics">
                        <div class="moneygram-volume" data-role="webVolume">Volume: 0</div>
                        <div class="moneygram-principal" data-role="webPrincipalPhp">Principal PHP: 0.00 pesos</div>
                        <div class="moneygram-principal" data-role="webPrincipalUsd">Principal USD: 0.00</div>
                    </div>
                </div>
                <div class="moneygram-table-shell moneygram-table-shell--web">
                    <table class="moneygram-table moneygram-table--web moneygram-table--head">
                        <colgroup>
                            <col class="moneygram-col-date">
                            <col class="moneygram-col-ref">
                            <col class="moneygram-col-amount">
                            <col class="moneygram-col-currency">
                        </colgroup>
                        <thead data-role="webHead">
                            <tr>
                                <th>Date</th>
                                <th>CCREF NO</th>
                                <th>Amount</th>
                                <th>CURRENCY</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="moneygram-table-body-scroll" data-role="webScroll">
                        <table class="moneygram-table moneygram-table--web moneygram-table--body">
                            <colgroup>
                                <col class="moneygram-col-date">
                                <col class="moneygram-col-ref">
                                <col class="moneygram-col-amount">
                                <col class="moneygram-col-currency">
                            </colgroup>
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

    modal.dataset.scrollSyncBound = 'true';
})();
</script>

<!-- In-app action confirmation modal + toast (used instead of alert/confirm) -->
<div id="moneygramActionModal" class="moneygram-action-modal" style="display:none;">
    <div class="moneygram-action-modal__overlay"></div>
    <div class="moneygram-action-modal__dialog" role="dialog" aria-modal="true" aria-label="Action Confirmation">
        <h4 id="moneygramActionModalTitle">Confirm</h4>
        <div id="moneygramActionModalBody" class="moneygram-action-modal__body">Are you sure?</div>
        <div class="moneygram-action-modal__footer">
            <button type="button" class="moneygram-action-cancel">Cancel</button>
            <button type="button" class="moneygram-action-confirm">OK</button>
        </div>
    </div>
</div>

<div id="moneygramActionToast" class="moneygram-action-toast" style="display:none;">Action completed</div>

<script>
// Simple in-app confirm/alert/toast helpers that return Promises
window.showConfirmModal = function(message, opts){
    return new Promise(resolve => {
        const modal = document.getElementById('moneygramActionModal');
        if(!modal) return resolve(window.confirm ? window.confirm(message) : false);
        const titleEl = modal.querySelector('#moneygramActionModalTitle');
        const bodyEl = modal.querySelector('#moneygramActionModalBody');
        const btnCancel = modal.querySelector('.moneygram-action-cancel');
        const btnConfirm = modal.querySelector('.moneygram-action-confirm');

        const title = (opts && opts.title) ? opts.title : (String(message).toLowerCase().includes('unlock') ? 'Confirm Unlock' : (String(message).toLowerCase().includes('lock') ? 'Confirm Lock' : 'Confirm'));
        const confirmText = (opts && opts.confirmText) ? opts.confirmText : (String(message).toLowerCase().includes('unlock') ? 'Unlock Now' : 'Lock Now');

        titleEl.textContent = title;
        bodyEl.textContent = message;
        btnConfirm.textContent = confirmText;

        function cleanup(){
            modal.style.display = 'none';
            btnCancel.style.display = '';
            btnConfirm.textContent = (opts && opts.confirmText) ? opts.confirmText : 'OK';
            btnCancel.removeEventListener('click', onCancel);
            btnConfirm.removeEventListener('click', onConfirm);
        }
        function onCancel(){ cleanup(); resolve(false); }
        function onConfirm(){ cleanup(); resolve(true); }

        btnCancel.addEventListener('click', onCancel);
        btnConfirm.addEventListener('click', onConfirm);
        modal.style.display = 'flex';
    });
};

window.showAlertModal = function(message, opts){
    return new Promise(resolve => {
        const modal = document.getElementById('moneygramActionModal');
        if(!modal) { alert(message); return resolve(); }
        const titleEl = modal.querySelector('#moneygramActionModalTitle');
        const bodyEl = modal.querySelector('#moneygramActionModalBody');
        const btnCancel = modal.querySelector('.moneygram-action-cancel');
        const btnConfirm = modal.querySelector('.moneygram-action-confirm');

        titleEl.textContent = (opts && opts.title) ? opts.title : 'Notice';
        bodyEl.textContent = message;
        btnConfirm.textContent = (opts && opts.confirmText) ? opts.confirmText : 'OK';

        function cleanup(){
            modal.style.display = 'none';
            btnCancel.style.display = '';
            btnConfirm.textContent = (opts && opts.confirmText) ? opts.confirmText : 'OK';
            btnCancel.removeEventListener('click', onCancel);
            btnConfirm.removeEventListener('click', onConfirm);
        }
        function onCancel(){ cleanup(); resolve(); }
        function onConfirm(){ cleanup(); resolve(); }

        btnCancel.style.display = 'none';
        btnConfirm.addEventListener('click', onConfirm);
        modal.style.display = 'flex';
    });
};

window.showSuccessToast = function(message, timeout){
    const t = document.getElementById('moneygramActionToast');
    if(!t){ if(window.alert) window.alert(message); return; }
    t.textContent = message;
    t.style.display = 'block';
    t.style.opacity = '1';
    clearTimeout(t._hideTimeout);
    t._hideTimeout = setTimeout(()=>{ t.style.transition = 'opacity .3s ease'; t.style.opacity = '0'; setTimeout(()=>{ t.style.display = 'none'; },350); }, timeout || 2500);
};
</script>

<script>
(function(){
    const modal = document.getElementById('moneygramReconViewModal');
    if(!modal) return;

    const lockBtn = modal.querySelector('#moneygramLockAllMatchedBtn');
    const LOCK_LABEL = 'LOCK MATCHED TRANSACTIONS';
    const UNLOCK_LABEL = 'UNLOCK MATCHED TRANSACTIONS';
    function collectMatchedRefs(){
        const refs = new Set();
        // only collect refs from partner-side matched rows (those render the check icon)
        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
            const r = (tr.dataset && tr.dataset.ref) ? String(tr.dataset.ref).trim() : (tr.cells[1] && tr.cells[1].textContent ? tr.cells[1].textContent.trim() : '');
            if(r) refs.add(r);
        });
        return Array.from(refs);
    }

    function collectMatchedDates(){
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
        const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
        const date = modal.dataset.reconDate || '';
        if(!partner || !date) return;
        try{
            const url = location.origin + '/autorecon/src/controllers/recon/get_row_locks.php?partner=' + encodeURIComponent(partner) + '&date=' + encodeURIComponent(date);
            const res = await fetch(url, { method: 'GET', credentials: 'same-origin' });
            if(!res || !res.ok) return;
            const json = await res.json();
            const locks = Array.isArray(json && json.locks) ? json.locks : [];
            locks.forEach(ref => {
                // mark partner and web rows that match this ref
                // only mark partner-side matched rows (do not add lock icon to web rows)
                Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
                    if(String(tr.dataset.ref || '').trim() === String(ref || '').trim()){
                        tr.classList.add('locked-row');
                    }
                });
            });
            if(locks.length && lockBtn){ lockBtn.disabled = false; }
        }catch(e){ console.warn('Failed to fetch row locks', e); }
    }

    async function fetchActiveLockStatus(){
        const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
        const dates = collectMatchedDates();
        if(!partner || !dates.length) return;
        try{
            const url = location.origin + '/autorecon/src/controllers/recon/get_active_locked_dates.php';
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
            if(m.attributeName === 'data-recon-date'){
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
            const confirmMsg = mode === 'lock' ? 'Are you sure you want to lock all matched transactions?\n\nLocked rows cannot be modified until unlocked.' : 'Are you sure you want to unlock all matched transactions?';
            const ok = await (window.showConfirmModal ? showConfirmModal(confirmMsg, { title: mode === 'lock' ? 'Confirm Lock' : 'Confirm Unlock', confirmText: mode === 'lock' ? 'Lock Now' : 'Unlock Now' }) : Promise.resolve(confirm(confirmMsg)));
            if(!ok) return;
            const partner = modal.dataset.partnerName || (document.getElementById('hsCompany') && document.getElementById('hsCompany').value) || '';
            const date = modal.dataset.reconDate || '';
            try{
                lockBtn.disabled = true; lockBtn.textContent = (mode === 'lock' ? 'Locking…' : 'Unlocking…');
                const endpoint = (mode === 'lock') ? '/autorecon/src/controllers/recon/lock_matched_rows.php' : '/autorecon/src/controllers/recon/unlock_matched_rows.php';
                const res = await fetch(location.origin + endpoint, {
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
                        // mark partner matched rows as locked
                        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row')).forEach(tr => {
                            const ref = String(tr.dataset.ref || '').trim();
                            if(ref && refs.indexOf(ref) !== -1){ tr.classList.add('locked-row'); }
                        });
                        lockBtn.textContent = UNLOCK_LABEL;
                        lockBtn.disabled = false;
                        window.showSuccessToast && showSuccessToast('Matched transactions locked successfully.');
                    } else {
                        // unlock: remove locked-row class from partner matched rows
                        Array.from(modal.querySelectorAll('[data-role="partnersBody"] tr.matched-row.locked-row')).forEach(tr => {
                            const ref = String(tr.dataset.ref || '').trim();
                            if(ref && refs.indexOf(ref) !== -1){ tr.classList.remove('locked-row'); }
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
})();
</script>