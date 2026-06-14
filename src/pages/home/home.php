<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/usercontroller.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/csrf.php';
bootSecureSession();
requireAuth();
requireAdminRoleOrShowConstruction();

$constructionMessage = $_SESSION['construction_modal'] ?? '';
unset($_SESSION['construction_modal']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoRecon | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="./home.css">
    <link rel="stylesheet" href="./components/header.css">
    <link rel="stylesheet" href="./components/recon-section.css">
    <link rel="stylesheet" href="./components/sidebar.css">
    <link rel="stylesheet" href="./components/webdata-section.css">
    <link rel="stylesheet" href="./components/home-section.css">
    <link rel="stylesheet" href="./components/partnerdata-section.css">
    <link rel="stylesheet" href="./components/maintenance-section.css">
    <link rel="stylesheet" href="../../modals/comparisonresult/view-result-modal.css">
    <link rel="stylesheet" href="../../modals/debug/error-debug-modal.css">
        <link rel="icon" type="image/png" href="../../assets/logo4.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/logo4.png">

</head>
<body>
<?php include __DIR__ . '/components/header.php'; ?>

<aside id="appSidebar" class="app-sidebar" aria-hidden="true">
    <button id="sidebarClose" class="sidebar-close" aria-label="Close sidebar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
    <div class="sidebar-user" role="region" aria-label="User">
        <div class="avatar" aria-hidden="true">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zM4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z"/></svg>
        </div>
        <div class="meta">
             <div class="name"><?= htmlspecialchars(strtoupper((string) ($_SESSION['user']['username'] ?? ($_SESSION['user']['firstname'] ?? 'User'))), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="role"><?= htmlspecialchars((string) ($_SESSION['user']['role'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <nav class="sidebar-nav" role="navigation" aria-label="Main">
        <ul>
            <li><a href="#" id="navHome" data-show="dashboardSection">
                <span class="icon material-icons" aria-hidden="true">home</span>
                <span class="label">Home</span>
            </a></li>
         
           
            <li class="nav-group">
                <button class="nav-group-toggle" aria-expanded="false" aria-controls="dataUploadMenu">
                    <span class="icon material-icons" aria-hidden="true">folder</span>
                    <span class="label">Data Upload</span>
                    <span class="chev material-icons" aria-hidden="true">expand_more</span>
                </button>
                <ul id="dataUploadMenu" class="nav-group-menu" style="display:none;">
                    <li><a href="#" id="navWebData" data-show="webdataSection">
                        <span class="icon material-icons" aria-hidden="true">web</span>
                        <span class="label">KPX Web Data</span>
                    </a></li>
                    <li><a href="#" id="navPartnerData" data-show="partnerdataSection">
                        <span class="icon material-icons" aria-hidden="true">people</span>
                        <span class="label">Partner Data</span>
                    </a></li>
                   
                </ul>
            </li>
                
                <li class="nav-group">
                    <button class="nav-group-toggle" aria-expanded="false" aria-controls="reportsMenu">
                        <span class="icon material-icons" aria-hidden="true">insert_chart</span>
                        <span class="label">Data Reports</span>
                        <span class="chev material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <ul id="reportsMenu" class="nav-group-menu" style="display:none;">
                        <li><a href="#" id="navReportsWebData" data-show="reportsWebDataSection">
                            <span class="icon material-icons" aria-hidden="true">insights</span>
                            <span class="label">Web Data Report</span>
                        </a></li>
                        <li><a href="#" id="navReportsPartnerData" data-show="reportsPartnerDataSection">
                            <span class="icon material-icons" aria-hidden="true">groups</span>
                            <span class="label">Partner Data Report</span>
                        </a></li>
                    </ul>
                </li>

                 <li class="nav-group">
                    <button class="nav-group-toggle" aria-expanded="false" aria-controls="reconciliationMenu">
                        <span class="icon material-icons" aria-hidden="true">sync_alt</span>
                        <span class="label">Reconciliation</span>
                        <span class="chev material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <ul id="reconciliationMenu" class="nav-group-menu" style="display:none;">
                           <li><a href="#" id="navWorkspace" data-show="homeSection">
                <span class="icon material-icons" aria-hidden="true">compare_arrows</span>
                <span class="label">Process Recon</span>
            </a></li>
                    </ul>
                </li>
            <?php if (isset($_SESSION['user']['role']) && strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0): ?>
                     <li><a href="#" id="navMaintenance" data-show="maintenanceSection">
                <span class="icon material-icons" aria-hidden="true">build</span>
                <span class="label">Maintenance</span>
            </a></li>
            <?php endif; ?>

          <?php if (isset($_SESSION['user']['role']) && strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0): ?>
            <li><a href="#" id="navUsers" data-show="usersSection">
                <span class="icon material-icons" aria-hidden="true">manage_accounts</span>
                <span class="label">Users</span>
            </a></li>
            <?php endif; ?>
            <li class="logout"><a href="../../config/logout-handler.php" class="home-logout">
                <span class="icon material-icons" aria-hidden="true">logout</span>
                <span class="label">Logout</span>
            </a></li>
        </ul>
    </nav>
</aside>
<main class="home-main">
    <?php include __DIR__ . '/components/home-section.php'; ?>

    <section id="dashboardSection" class="dashboard-section" aria-label="Dashboard" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/dashboard.php'; ?>
    </section>

    <section id="usersSection" class="users-section" aria-label="Users" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/all-users.php'; ?>
    </section>

    <section id="reportsWebDataSection" class="reports-webdata-section" aria-label="ML Web Data Report" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/reportswebdata-section.php'; ?>
    </section>

    <section id="reportsPartnerDataSection" class="reports-partnerdata-section" aria-label="Partner Data Report" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/reportspartnerdata-section.php'; ?>
    </section>

    <?php include __DIR__ . '/components/webdata-section.php'; ?>
    <?php include __DIR__ . '/components/partnerdata-section.php'; ?>
    <?php include __DIR__ . '/components/maintenance-section.php'; ?>
    <?php include __DIR__ . '/components/recon-section.php'; ?>
</main>

    <?php include __DIR__ . '/../../modals/comparisonresult/view-result-modal.php'; ?>
    <?php include __DIR__ . '/../../modals/debug/error-debug-modal.php'; ?>
  

<?php // Password-reset modal is handled on the Index page. Home should not include it.
      // Keep this file free of the reset modal to avoid showing it after redirecting to Home.
?>

<?php if ($constructionMessage !== ''): ?>
    <div class="construction-modal is-open" role="dialog" aria-modal="true" aria-label="Role notice">
        <div class="construction-modal__card">
            <p><?= htmlspecialchars($constructionMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="../../config/logout-handler.php" class="material-btn material-btn--primary">Okay</a>
        </div>
    </div>
<?php endif; ?>
<script>
(function(){
    const sb = document.getElementById('appSidebar');
    const closeBtn = document.getElementById('sidebarClose');
    const main = document.querySelector('main.home-main');
    const header = document.querySelector('.home-header');
    if (!sb) return;

    function openSidebar() {
        sb.classList.add('is-open');
        sb.setAttribute('aria-hidden', 'false');
        if (main) main.style.marginLeft = sb.offsetWidth + 'px';
        if (typeof updateHeaderPosition === 'function') updateHeaderPosition();
    }

    function closeSidebar() {
        sb.classList.remove('is-open');
        sb.setAttribute('aria-hidden', 'true');
        if (main) main.style.marginLeft = '';
        if (typeof updateHeaderPosition === 'function') updateHeaderPosition();
    }

    // close when clicking outside sidebar
    document.addEventListener('click', function(e){
        if (!sb.classList.contains('is-open')) return;
        if (sb.contains(e.target)) return;
        closeSidebar();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e){
            e.stopPropagation();
            closeSidebar();
        });
    }

    function updateHeaderPosition(){
        try{
            if (!header) return;
            const left = sb && sb.classList.contains('is-open') ? sb.offsetWidth : 64;
            header.style.left = left + 'px';
            // ensure right edge flush
            header.style.right = '0';
            // set main padding to header height so content scrolls under header
            if (main) main.style.paddingTop = header.offsetHeight + 'px';
            // set CSS variable for sticky elements to align under header
            try { document.documentElement.style.setProperty('--header-offset', header.offsetHeight + 'px'); } catch(e){}
        }catch(e){}
    }

    // update header position on resize
    window.addEventListener('resize', function(){ try{ if (typeof updateHeaderPosition==='function') updateHeaderPosition(); }catch(e){} });

    // Auto-open/close behavior on hover-capable devices
    try {
        const canHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
        if (canHover) {
            let hoverCloseTimer = null;
            const hoverDelay = 700; // ms
            sb.addEventListener('mouseenter', function(){
                if (hoverCloseTimer) { clearTimeout(hoverCloseTimer); hoverCloseTimer = null; }
                if (!sb.classList.contains('is-open')) {
                    openSidebar();
                }
            });
            sb.addEventListener('mouseleave', function(){
                if (hoverCloseTimer) clearTimeout(hoverCloseTimer);
                hoverCloseTimer = setTimeout(function(){
                    const activeInSidebar = document.activeElement && sb.contains(document.activeElement);
                    if (!activeInSidebar) closeSidebar();
                }, hoverDelay);
            });
        }
    } catch (e) { /* ignore */ }

    function hideAllSectionTargets(){
        try{
            const all = document.querySelectorAll('[id$="Section"]');
            all.forEach(s => { if (s && s.style) { s.style.display = 'none'; s.classList.remove('is-visible'); } });
        }catch(e){}
    }

    function setActiveNavFor(sectionId){
        try{
            const links = document.querySelectorAll('.sidebar-nav a');
            links.forEach(a=> a.classList.remove('is-active'));
            if(!sectionId) return;
            const activeLink = document.querySelector('.sidebar-nav a[data-show="'+sectionId+'"]');
            if(activeLink) activeLink.classList.add('is-active');
        }catch(e){ }
    }

    function resolveInitialSection(){
        try {
            const params = new URLSearchParams(window.location.search || '');
            const section = (params.get('section') || '').toLowerCase();
            const allowed = {
                dashboard: 'dashboardSection',
                workspace: 'homeSection',
                users: 'usersSection',
                webdata: 'webdataSection',
                partnerdata: 'partnerdataSection',
                maintenance: 'maintenanceSection',
                recon: 'reconSection'
            };
            return allowed[section] || 'dashboardSection';
        } catch (e) {
            return 'dashboardSection';
        }
    }

    // initial state: hide all sections then show the requested section (defaults to dashboard)
    try{
        hideAllSectionTargets();
        const startSectionId = resolveInitialSection();
        const start = document.getElementById(startSectionId) || document.getElementById('dashboardSection');
        if (start) { start.style.display = 'block'; start.classList.add('is-visible'); }
        setActiveNavFor(start ? start.id : 'dashboardSection');
        if (typeof updateHeaderPosition === 'function') updateHeaderPosition();
    }catch(e){}

    // wire nav links
    ['navHome','navWorkspace','navUsers','navWebData','navPartnerData','navReportsWebData','navReportsPartnerData','navReconTool','navMaintenance'].forEach(function(id){
        const navEl = document.getElementById(id);
        if (!navEl) return;
        navEl.addEventListener('click', function(e){
            try {
                e.preventDefault();
                if (!sb.classList.contains('is-open')) openSidebar();
                const targetId = navEl.dataset.show;
                const target = targetId ? document.getElementById(targetId) : null;
                if (target) {
                    hideAllSectionTargets();
                    target.style.display = 'block';
                    target.classList.add('is-visible');
                    setActiveNavFor(targetId);
                    const first = target.querySelector('input,button,a,select,textarea');
                    if (first) {
                        try { first.focus({preventScroll:true}); } catch (e) { first.focus(); }
                    }
                    setTimeout(() => {
                        try { target.scrollIntoView({behavior:'smooth', block:'start'}); } catch (e) {}
                    }, 40);
                }
            } catch (err) {
                console.error('[sidebar] nav click handler error', err);
            }
        });
    });

    // delegate clicks inside sidebar to support clicking on nested SVGs/spans
    sb.addEventListener('click', function(ev){
        const a = ev.target.closest && ev.target.closest('a[data-show]');
        if (a && a.dataset && a.dataset.show) {
            a.click();
            ev.preventDefault();
        }
    });

    // handle nav group toggles (e.g., Data Upload)
    try {
        document.querySelectorAll('.nav-group-toggle').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const li = btn.closest('.nav-group');
                if (!li) return;
                const menu = li.querySelector('.nav-group-menu');
                const open = li.classList.toggle('is-open');
                if (menu) menu.style.display = open ? 'block' : 'none';
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    } catch (e) { /* ignore */ }
})();
</script>

</body>
</html>
