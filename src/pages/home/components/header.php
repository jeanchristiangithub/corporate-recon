<header class="home-header">
    <div class="home-header__brand">
        <img src="../../assets/logo1.png" alt="logo" class="home-header__logo" onerror="this.onerror=null;this.src='../../assets/logo1.png'">
        <div class="home-header__brand-meta">
            <span class="home-header__company">M LHUILLIER FINANCIAL SERVICES, INC.</span>
            <span class="home-header__page">Home</span>
        </div>
    </div>

    <div class="home-header__actions">
        <img src="../../assets/logo1.png" alt="logo" class="home-header__logo-alt" onerror="this.onerror=null;this.src='../../assets/logo1.png'">
    </div>
</header>

<!-- Logout confirmation modal -->
<div id="logout-modal" class="logout-modal" aria-hidden="true" role="dialog" aria-labelledby="logout-modal-title">
    <div class="logout-modal__overlay" data-close></div>
    <div class="logout-modal__dialog" role="document">
        <div id="logout-modal-icon" class="logout-modal__icon" aria-hidden="true">
            <span class="material-icons">warning_amber</span>
        </div>
        <h2 id="logout-modal-title">Confirm Logout</h2>
        <!-- <p id="logout-modal-message">Are you sure you want to logout?</p> -->
        <div id="logout-pending-summary" class="logout-modal__summary" hidden></div>
        <p id="logout-modal-question" class="logout-modal__question" hidden>Do you want to continue?</p>
        <div class="logout-modal__actions">
            <button type="button" id="logout-cancel" class="material-btn">Cancel</button>
            <button type="button" id="logout-confirm" class="material-btn primary">Logout</button>
        </div>
    </div>
</div>

<!-- Pending upload reload confirmation modal -->
<div id="reload-pending-modal" class="logout-modal logout-modal--pending" aria-hidden="true" role="dialog" aria-labelledby="reload-pending-title">
    <div class="logout-modal__overlay" data-close></div>
    <div class="logout-modal__dialog" role="document">
        <div class="logout-modal__icon" aria-hidden="true">
            <span class="material-icons">warning_amber</span>
        </div>
        <h2 id="reload-pending-title">Pending Upload Detected</h2>
        <p id="reload-pending-message">You still have file(s) waiting to be uploaded.<br>Reloading this page will discard the pending upload.</p>
        <div id="reload-pending-summary" class="logout-modal__summary" hidden></div>
        <div class="logout-modal__actions">
            <button type="button" id="reload-pending-cancel" class="material-btn">Cancel</button>
            <button type="button" id="reload-pending-confirm" class="material-btn primary">OK</button>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('logout-modal');
    var confirmBtn = document.getElementById('logout-confirm');
    var cancelBtn = document.getElementById('logout-cancel');
    var titleEl = document.getElementById('logout-modal-title');
    var messageEl = document.getElementById('logout-modal-message');
    var summaryEl = document.getElementById('logout-pending-summary');
    var questionEl = document.getElementById('logout-modal-question');
    var overlay = modal && modal.querySelector('.logout-modal__overlay');
    var targetUrl = null;
    var hasPendingLogout = false;

    function getPendingUploadState(){
        var registry = window.AutoReconUploadPending || {};
        var rows = [];
        var total = 0;

        Object.keys(registry).forEach(function(key){
            var uploader = registry[key];
            if(!uploader || typeof uploader.count !== 'function') return;
            var count = Number(uploader.count() || 0);
            if(count <= 0) return;
            total += count;
            rows.push({
                label: uploader.label || key,
                count: count
            });
        });

        return {
            hasPending: total > 0,
            total: total,
            rows: rows
        };
    }

    window.checkPendingUploadsBeforeLogout = function(){
        return getPendingUploadState().hasPending;
    };

    window.getAutoReconPendingUploadState = getPendingUploadState;

    function clearPendingUploadQueues(){
        var registry = window.AutoReconUploadPending || {};
        Object.keys(registry).forEach(function(key){
            var uploader = registry[key];
            if(uploader && typeof uploader.clear === 'function') uploader.clear();
        });
    }

    window.clearAutoReconPendingUploadQueues = clearPendingUploadQueues;

    function renderModalContent(state){
        hasPendingLogout = !!(state && state.hasPending);
        if(!titleEl || !messageEl || !summaryEl || !questionEl) return;

        if(hasPendingLogout){
            titleEl.textContent = 'Pending Upload Detected';
            messageEl.innerHTML = 'You still have file(s) waiting to be uploaded.<br>Logging out now will discard those pending uploads.';
            summaryEl.innerHTML = state.rows.map(function(row){
                return '<div><strong>' + row.label + ':</strong> ' + row.count + ' pending file' + (row.count > 1 ? 's' : '') + '</div>';
            }).join('');
            summaryEl.hidden = false;
            questionEl.hidden = false;
            modal.classList.add('logout-modal--pending');
        } else {
            titleEl.textContent = 'Confirm Logout';
            messageEl.textContent = 'Are you sure you want to logout?';
            summaryEl.innerHTML = '';
            summaryEl.hidden = true;
            questionEl.hidden = true;
            modal.classList.remove('logout-modal--pending');
        }
    }

    function showModal(url, state){
        targetUrl = url || null;
        renderModalContent(state || { hasPending: false, rows: [] });
        if (modal) modal.setAttribute('aria-hidden','false');
        if (confirmBtn) try { confirmBtn.focus(); } catch (e) {}
    }

    function hideModal(){
        if (modal) modal.setAttribute('aria-hidden','true');
        targetUrl = null;
    }

    // Event delegation: handle clicks on any element with .home-logout (header or sidebar)
    document.addEventListener('click', function(e){
        var el = e.target && e.target.closest ? e.target.closest('.home-logout') : null;
        if (!el) return;
        e.preventDefault();
        var href = el.getAttribute('href');
        var state = getPendingUploadState();
        showModal(href, state);
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function(){
            if(hasPendingLogout){
                clearPendingUploadQueues();
            }
            if(targetUrl){
                window.location.href = targetUrl;
            }
        });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', function(){ hideModal(); });
    if (overlay) overlay.addEventListener('click', function(){ hideModal(); });

    document.addEventListener('keydown', function(e){
        if (!modal) return;
        if(modal.getAttribute('aria-hidden') === 'false'){
            if(e.key === 'Escape') hideModal();
            if(e.key === 'Enter' && confirmBtn) confirmBtn.click();
        }
    });
})();
</script>
<script>
(function(){
    var modal = document.getElementById('reload-pending-modal');
    var confirmBtn = document.getElementById('reload-pending-confirm');
    var cancelBtn = document.getElementById('reload-pending-cancel');
    var summaryEl = document.getElementById('reload-pending-summary');
    var overlay = modal && modal.querySelector('.logout-modal__overlay');
    var allowPendingReload = false;

    function getPendingUploadState(){
        if(typeof window.getAutoReconPendingUploadState === 'function'){
            return window.getAutoReconPendingUploadState();
        }
        var registry = window.AutoReconUploadPending || {};
        var rows = [];
        var total = 0;

        Object.keys(registry).forEach(function(key){
            var uploader = registry[key];
            if(!uploader || typeof uploader.count !== 'function') return;
            var count = Number(uploader.count() || 0);
            if(count <= 0) return;
            total += count;
            rows.push({
                label: uploader.label || key,
                count: count
            });
        });

        return {
            hasPending: total > 0,
            total: total,
            rows: rows
        };
    }

    function renderSummary(state){
        if(!summaryEl) return;
        var rows = state && state.rows ? state.rows : [];
        summaryEl.innerHTML = rows.map(function(row){
            return '<div><strong>' + row.label + ':</strong> ' + row.count + ' pending file' + (row.count > 1 ? 's' : '') + '</div>';
        }).join('');
        summaryEl.hidden = rows.length === 0;
    }

    function showModal(state){
        if(!modal) return;
        renderSummary(state);
        modal.setAttribute('aria-hidden', 'false');
        if(cancelBtn) try { cancelBtn.focus(); } catch(e) {}
    }

    function hideModal(){
        if(modal) modal.setAttribute('aria-hidden', 'true');
    }

    function isKeyboardReload(event){
        var key = event.key || '';
        var normalizedKey = key.toLowerCase();
        return key === 'F5' || (event.ctrlKey && normalizedKey === 'r');
    }

    document.addEventListener('keydown', function(event){
        if(!isKeyboardReload(event)) return;

        var state = getPendingUploadState();
        if(!state.hasPending) return;

        event.preventDefault();
        event.stopPropagation();
        showModal(state);
    }, true);

    window.addEventListener('beforeunload', function(event){
        if(allowPendingReload) return;

        var state = getPendingUploadState();
        if(!state.hasPending) return;

        event.preventDefault();
        event.returnValue = '';
        return '';
    });

    if(confirmBtn){
        confirmBtn.addEventListener('click', function(){
            allowPendingReload = true;
            if(typeof window.clearAutoReconPendingUploadQueues === 'function'){
                window.clearAutoReconPendingUploadQueues();
            }
            window.location.reload();
        });
    }

    if(cancelBtn) cancelBtn.addEventListener('click', hideModal);
    if(overlay) overlay.addEventListener('click', hideModal);

    document.addEventListener('keydown', function(event){
        if(!modal || modal.getAttribute('aria-hidden') !== 'false') return;
        if(event.key === 'Escape'){
            event.preventDefault();
            hideModal();
        }
        if(event.key === 'Enter' && confirmBtn){
            event.preventDefault();
            confirmBtn.click();
        }
    });
})();
</script>
