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
        <h2 id="logout-modal-title">Confirm Logout</h2>
        <p>Are you sure you want to logout?</p>
        <div class="logout-modal__actions">
            <button type="button" id="logout-cancel" class="material-btn">Cancel</button>
            <button type="button" id="logout-confirm" class="material-btn primary">Logout</button>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('logout-modal');
    var confirmBtn = document.getElementById('logout-confirm');
    var cancelBtn = document.getElementById('logout-cancel');
    var overlay = modal && modal.querySelector('.logout-modal__overlay');
    var targetUrl = null;

    function showModal(url){
        targetUrl = url || null;
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
        showModal(href);
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function(){
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
