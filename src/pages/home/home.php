<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/usercontroller.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/csrf.php';
bootSecureSession();
requireAuth();
requireAdminRoleOrShowConstruction();

$appBasePath = autoreconBasePath();
$appBaseUrl = $appBasePath === '' ? '' : $appBasePath;

$constructionMessage = $_SESSION['construction_modal'] ?? '';
unset($_SESSION['construction_modal']);

$profilePhotoMessage = $_SESSION['profile_photo_success'] ?? '';
$profilePhotoError = $_SESSION['profile_photo_error'] ?? '';
unset($_SESSION['profile_photo_success'], $_SESSION['profile_photo_error']);

$currentUserId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($_SESSION['user']['id_number'] ?? ''));
$profilePhotoUrl = '';
if ($currentUserId !== '') {
    $profilePhotoDir = dirname(__DIR__, 3) . '/uploads/profile-photos';
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $photoExtension) {
        $profilePhotoPath = $profilePhotoDir . '/' . $currentUserId . '.' . $photoExtension;
        if (is_file($profilePhotoPath)) {
            $profilePhotoUrl = $appBaseUrl . '/uploads/profile-photos/' . rawurlencode($currentUserId . '.' . $photoExtension) . '?v=' . filemtime($profilePhotoPath);
            break;
        }
    }
}
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/home.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/header.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/sidebar.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/webdata-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/webdata-cancellation-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/recon-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/reconreport-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/partnerdata-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/maintenance-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/uploaded-file-logs.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/modals/comparisonresult/view-result-modal.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/modals/debug/error-debug-modal.css">
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/assets/12.png">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/assets/12.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.autoreconBaseUrl = <?= json_encode($appBaseUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        window.autoreconUrl = function (path) {
            return window.autoreconBaseUrl + '/' + String(path || '').replace(/^\/+/, '');
        };
    </script>
    <style>.swal2-container{z-index:200500!important}</style>

</head>
<body>
<?php include __DIR__ . '/components/header.php'; ?>

<aside id="appSidebar" class="app-sidebar" aria-hidden="true">
    <button id="sidebarBurger" class="sidebar-burger" type="button" aria-label="Toggle sidebar menu" aria-expanded="false" aria-controls="appSidebar">
        <span class="material-icons" aria-hidden="true">menu</span>
    </button>
    <div class="sidebar-user" role="region" aria-label="User">
        <form class="sidebar-avatar-form" action="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/config/profile-photo-handler.php" method="post" enctype="multipart/form-data" data-profile-photo-url="<?= htmlspecialchars($profilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>">
            <?= csrfField() ?>
            <button type="button" class="avatar" title="Profile photo options" aria-label="Profile photo options" aria-expanded="false">
                <?php if ($profilePhotoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($profilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Profile photo">
                <?php else: ?>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zM4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6v1H4v-1z"/></svg>
                <?php endif; ?>
            </button>
            <div class="avatar-menu" role="menu" aria-label="Profile photo menu">
                <button type="button" data-avatar-action="show" role="menuitem">Show</button>
                <button type="button" data-avatar-action="change" role="menuitem">Change</button>
                <button type="button" data-avatar-action="default" role="menuitem">Default</button>
            </div>
            <input type="hidden" name="profile_photo_action" value="upload">
            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Change profile photo">
        </form>
        <div class="meta">
             <div class="meta-label">Username:</div>
             <div class="name"><?= htmlspecialchars(strtoupper((string) ($_SESSION['user']['username'] ?? ($_SESSION['user']['firstname'] ?? 'User'))), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="role-row"><span class="role-label">Role:</span> <span class="role"><?= htmlspecialchars((string) ($_SESSION['user']['role'] ?? 'Public'), ENT_QUOTES, 'UTF-8') ?></span></div>
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
                    <li><a href="#" id="navWebDataCancellation" class="nav-subitem--compact" data-show="webdataCancellationSection">
                        <span class="icon material-icons" aria-hidden="true">cancel</span>
                        <span class="label">KPX Web Cancellation</span>
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
                        <li><a href="#" id="navSummaryReport" data-show="summaryReportSection">
                            <span class="icon material-icons" aria-hidden="true">summarize</span>
                            <span class="label">Summary Report</span>
                        </a></li>
                        <li><a href="#" id="navReconReport" data-show="reconReportSection">
                            <span class="icon material-icons" aria-hidden="true">receipt_long</span>
                            <span class="label">Recon Report</span>
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
                <li class="nav-group">
                    <button class="nav-group-toggle" aria-expanded="false" aria-controls="historyLogsMenu">
                        <span class="icon material-icons" aria-hidden="true">history</span>
                        <span class="label">History Logs</span>
                        <span class="chev material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <ul id="historyLogsMenu" class="nav-group-menu" style="display:none;">
                        <li><a href="#" id="navUploadedFileLogs" data-show="uploadedFileLogsSection">
                            <span class="icon material-icons" aria-hidden="true">upload_file</span>
                            <span class="label">Uploaded File Logs</span>
                        </a></li>
                    </ul>
                </li>
            <?php if (isset($_SESSION['user']['role']) && strcasecmp((string) $_SESSION['user']['role'], 'Admin') === 0): ?>
                <li class="nav-group">
                    <button class="nav-group-toggle" aria-expanded="false" aria-controls="maintenanceMenu">
                        <span class="icon material-icons" aria-hidden="true">build</span>
                        <span class="label">Maintenance</span>
                        <span class="chev material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <ul id="maintenanceMenu" class="nav-group-menu" style="display:none;">
                        <li><a href="#" id="navMaintenance" data-show="maintenanceSection">
                            <span class="icon material-icons" aria-hidden="true">handshake</span>
                            <span class="label">Partner Legacy ID</span>
                        </a></li>
                        <li><a href="#" id="navUsers" data-show="usersSection">
                            <span class="icon material-icons" aria-hidden="true">manage_accounts</span>
                            <span class="label">Users</span>
                        </a></li>
                    </ul>
                </li>
            <?php endif; ?>
            <li class="logout"><a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/config/logout-handler.php" class="home-logout">
                <span class="icon material-icons" aria-hidden="true">logout</span>
                <span class="label">Logout</span>
            </a></li>
        </ul>
    </nav>
</aside>
<main class="home-main">
    <?php include __DIR__ . '/components/recon-section.php'; ?>

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

    <section id="summaryReportSection" class="summary-report-section" aria-label="Summary Report" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/summaryreport-section.php'; ?>
    </section>

    <section id="reconReportSection" class="recon-report-section" aria-label="Recon Report" style="display:none; padding:1rem">
        <?php include __DIR__ . '/components/reconreport-section.php'; ?>
    </section>

    <?php include __DIR__ . '/components/webdata-section.php'; ?>
    <?php include __DIR__ . '/components/webdata-cancellation-section.php'; ?>
    <?php include __DIR__ . '/components/partnerdata-section.php'; ?>
    <?php include __DIR__ . '/components/uploaded-file-logs.php'; ?>
    <?php include __DIR__ . '/components/maintenance-section.php'; ?>
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
            <a href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/config/logout-handler.php" class="material-btn material-btn--primary">Okay</a>
        </div>
    </div>
<?php endif; ?>
<script>
(function(){
    const sb = document.getElementById('appSidebar');
    const burgerBtn = document.getElementById('sidebarBurger');
    const main = document.querySelector('main.home-main');
    const header = document.querySelector('.home-header');
    if (!sb) return;

    function openSidebar() {
        sb.classList.add('is-open');
        sb.setAttribute('aria-hidden', 'false');
        if (burgerBtn) {
            burgerBtn.setAttribute('aria-expanded', 'true');
            burgerBtn.setAttribute('aria-label', 'Close sidebar menu');
            const icon = burgerBtn.querySelector('.material-icons');
            if (icon) icon.textContent = 'close';
        }
        if (main) main.style.marginLeft = sb.offsetWidth + 'px';
        if (typeof updateHeaderPosition === 'function') updateHeaderPosition();
    }

    function closeSidebar() {
        sb.classList.remove('is-open');
        sb.setAttribute('aria-hidden', 'true');
        if (burgerBtn) {
            burgerBtn.setAttribute('aria-expanded', 'false');
            burgerBtn.setAttribute('aria-label', 'Toggle sidebar menu');
            const icon = burgerBtn.querySelector('.material-icons');
            if (icon) icon.textContent = 'menu';
        }
        if (main) main.style.marginLeft = '';
        if (typeof updateHeaderPosition === 'function') updateHeaderPosition();
    }

    if (burgerBtn) {
        burgerBtn.addEventListener('click', function(e){
            e.stopPropagation();
            if (sb.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
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

    function collapseSidebarSubmenus(){
        try{
            document.querySelectorAll('.sidebar-nav .nav-group').forEach(function(group){
                group.classList.remove('is-open');
                const toggle = group.querySelector('.nav-group-toggle');
                const menu = group.querySelector('.nav-group-menu');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
                if (menu) menu.style.display = 'none';
            });
        }catch(e){ }
    }

    function dismissUserCreateAlert(){
        try{
            document.querySelectorAll('[data-role="user-create-alert"]').forEach(function(alert){
                alert.remove();
            });
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
                webdatacancellation: 'webdataCancellationSection',
                partnerdata: 'partnerdataSection',
                summaryreport: 'summaryReportSection',
                reconreport: 'reconReportSection',
                maintenance: 'maintenanceSection',
                uploadedfilelogs: 'uploadedFileLogsSection',
                recon: 'homeSection'
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
    ['navHome','navWorkspace','navUsers','navWebData','navWebDataCancellation','navPartnerData','navReportsWebData','navReportsPartnerData','navSummaryReport','navReconReport','navReconTool','navMaintenance','navUploadedFileLogs'].forEach(function(id){
        const navEl = document.getElementById(id);
        if (!navEl) return;
        navEl.addEventListener('click', function(e){
            try {
                e.preventDefault();
                if (id === 'navWebDataCancellation' || id === 'navUploadedFileLogs') {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Under Maintenance',
                            icon: 'info',
                            confirmButtonColor: '#dc3545',
                            heightAuto: false
                        });
                    } else {
                        alert('Under Maintenance');
                    }
                    return;
                }
                if (id === 'navHome') {
                    collapseSidebarSubmenus();
                    dismissUserCreateAlert();
                }
                const targetId = navEl.dataset.show;
                const target = targetId ? document.getElementById(targetId) : null;
                if (target) {
                    hideAllSectionTargets();
                    target.style.display = 'block';
                    target.classList.add('is-visible');
                    setActiveNavFor(targetId);
                    const shouldAutoFocus = targetId !== 'reportsWebDataSection';
                    const first = shouldAutoFocus ? target.querySelector('input,button,a,select,textarea') : null;
                    if (first) {
                        try { first.focus({preventScroll:true}); } catch (e) { first.focus(); }
                    }
                    setTimeout(() => {
                        try { target.scrollIntoView({behavior:'auto', block:'start'}); } catch (e) {}
                    }, 40);
                    if (navEl.closest('.nav-group-menu')) {
                        closeSidebar();
                    }
                }
            } catch (err) {
                console.error('[sidebar] nav click handler error', err);
            }
        });
    });

    // delegate clicks inside sidebar to support clicking on nested SVGs/spans
    sb.addEventListener('click', function(ev){
        if (ev.defaultPrevented) return;
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
                const open = !li.classList.contains('is-open');
                document.querySelectorAll('.sidebar-nav .nav-group').forEach(function(group){
                    if (group === li) return;
                    group.classList.remove('is-open');
                    const groupToggle = group.querySelector('.nav-group-toggle');
                    const groupMenu = group.querySelector('.nav-group-menu');
                    if (groupToggle) groupToggle.setAttribute('aria-expanded', 'false');
                    if (groupMenu) groupMenu.style.display = 'none';
                });
                li.classList.toggle('is-open', open);
                if (menu) menu.style.display = open ? 'block' : 'none';
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    } catch (e) { /* ignore */ }
})();
</script>
<?php if ($profilePhotoMessage !== '' || $profilePhotoError !== ''): ?>
<script>
window.addEventListener('DOMContentLoaded', function(){
    if (!window.Swal) return;
    Swal.fire({
        title: <?= json_encode($profilePhotoError !== '' ? 'Upload Failed' : 'Profile Photo Updated', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        <?php if ($profilePhotoError !== ''): ?>
        text: <?= json_encode($profilePhotoError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        <?php endif; ?>
        icon: <?= json_encode($profilePhotoError !== '' ? 'error' : 'success') ?>,
        confirmButtonColor: '#dc3545',
        heightAuto: false
    });
});
</script>
<?php endif; ?>
<script>
(function(){
    var form = document.querySelector('.sidebar-avatar-form');
    if (!form) return;

    var avatarButton = form.querySelector('.avatar');
    var menu = form.querySelector('.avatar-menu');
    var input = form.querySelector('input[type="file"]');
    var actionInput = form.querySelector('input[name="profile_photo_action"]');
    var profilePhotoUrl = form.getAttribute('data-profile-photo-url') || '';
    var sidebar = form.closest('.app-sidebar');
    if (!avatarButton || !menu || !input) return;

    function closeMenu() {
        form.classList.remove('is-menu-open');
        if (sidebar) sidebar.classList.remove('is-avatar-menu-open');
        avatarButton.setAttribute('aria-expanded', 'false');
    }

    function toggleMenu() {
        var isOpen = form.classList.toggle('is-menu-open');
        if (sidebar) sidebar.classList.toggle('is-avatar-menu-open', isOpen);
        avatarButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    avatarButton.addEventListener('click', function(event){
        event.preventDefault();
        event.stopPropagation();
        toggleMenu();
    });

    menu.addEventListener('click', function(event){
        var actionButton = event.target.closest('[data-avatar-action]');
        if (!actionButton) return;

        var action = actionButton.getAttribute('data-avatar-action');
        closeMenu();

        if (action === 'change') {
            if (actionInput) actionInput.value = 'upload';
            input.click();
            return;
        }

        if (action === 'default') {
            if (actionInput) actionInput.value = 'default';
            form.submit();
            return;
        }

        if (action === 'show') {
            if (profilePhotoUrl && window.Swal) {
                Swal.fire({
                    title: 'Profile Photo',
                    imageUrl: profilePhotoUrl,
                    imageAlt: 'Profile photo',
                    confirmButtonColor: '#dc3545',
                    heightAuto: false
                });
                return;
            }

            if (window.Swal) {
                Swal.fire({
                    title: 'Profile Photo',
                    text: 'No profile photo uploaded.',
                    icon: 'info',
                    confirmButtonColor: '#dc3545',
                    heightAuto: false
                });
            }
        }
    });

    document.addEventListener('click', function(event){
        if (!form.contains(event.target)) {
            closeMenu();
        }
    });

    input.addEventListener('change', function(){
        if (input.files && input.files.length > 0) {
            if (actionInput) actionInput.value = 'upload';
            form.submit();
        }
    });
})();
</script>

</body>
</html>
