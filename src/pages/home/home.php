<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/usercontroller.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/csrf.php';
bootSecureSession();
requireAuth();
requireAdminRoleOrShowConstruction();

$currentRole = (string) ($_SESSION['user']['role'] ?? '');
$isAdmin = strcasecmp($currentRole, 'Admin') === 0;
$showMaintenanceMenu = strcasecmp($currentRole, 'Admin') === 0
    || strcasecmp($currentRole, 'Public') === 0;
$isPrimaryAdmin = isPrimaryAdminUser();
$canManageUsers = $isPrimaryAdmin;

// A full page request should only build the section being visited. Previously every
// hidden section ran its PHP and database queries on every sidebar navigation.
$requestedSection = strtolower(trim((string) ($_GET['section'] ?? 'dashboard')));
$allowedSections = [
    'dashboard', 'workspace', 'webdata', 'webdatacancellation', 'kpxwebdataver2',
    'partnerdata', 'settlementdaily', 'settlementendmonth',
    'dataentrysettlementdetail', 'reportswebdata', 'partnerdatareportdaily',
    'partnerdatareportsettlement', 'summaryreport', 'reconreport',
    'cashflowreport', 'edireport', 'profilesettings', 'maintenancedataunlock',
    'uploadedfilelogs', 'origindatalogspartner', 'branchstatuslogs', 'recon',
];
if ($isAdmin) {
    $allowedSections[] = 'branchstatusposting';
}
if ($canManageUsers) {
    $allowedSections[] = 'users';
}
if ($isPrimaryAdmin) {
    $allowedSections[] = 'maintenance';
}
$activeSection = in_array($requestedSection, $allowedSections, true)
    ? $requestedSection
    : 'dashboard';

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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/home.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/header.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/sidebar.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/webdata-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/data-upload/kpx-webdata-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/data-upload/settlement-detail/daily/settlementdaily-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/data-entry/settlement-detail/data-entry-settlement-detail-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/webdata-cancellation-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/recon-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/reconreport-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/partnerdata-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/maintenance-section.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/maintenance/branch-status-posting.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/uploaded-file-logs.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/profile-settings/user-profile-settings.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/data-reports/data-reports-cashflow-report.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($appBaseUrl, ENT_QUOTES, 'UTF-8') ?>/src/pages/home/components/data-reports/data-reports-edi-report.css">
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
                <button type="button" data-avatar-action="profile-settings" role="menuitem">Profile Settings</button>
                <!-- <button type="button" data-avatar-action="show" role="menuitem">Show</button>
                <button type="button" data-avatar-action="change" role="menuitem">Change</button>
                <button type="button" data-avatar-action="default" role="menuitem">Default</button> -->
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
                    <!-- <li><a href="#" id="navWebData" data-show="webdataSection">
                        <span class="icon material-icons" aria-hidden="true">web</span>
                        <span class="label">KPX Web Data</span>
                    </a></li> -->
                    <!-- <li><a href="#" id="navWebDataCancellation" class="nav-subitem--compact" data-show="webdataCancellationSection">
                        <span class="icon material-icons" aria-hidden="true">cancel</span>
                        <span class="label">KPX Web Cancellation</span>
                    </a></li> -->
                    <li><a href="#" id="navKpxWebDataVer2" data-show="kpxWebDataVer2Section">
                        <span class="icon material-icons" aria-hidden="true">web_asset</span>
                        <span class="label">KPX Web Data</span>
                    </a></li>
                    <li><a href="#" id="navPartnerData" data-show="partnerdataSection">
                        <span class="icon material-icons" aria-hidden="true">people</span>
                        <span class="label">Partner Data</span>
                    </a></li>
                    <li class="nav-subgroup">
                        <button class="nav-subgroup-toggle" type="button" aria-expanded="false" aria-controls="settlementDetailMenu">
                            <span class="icon material-icons" aria-hidden="true">payments</span>
                            <span class="label">Settlement Detail</span>
                            <span class="chev material-icons" aria-hidden="true">expand_more</span>
                        </button>
                        <ul id="settlementDetailMenu" class="nav-subgroup-menu" style="display:none;">
                            <li><a href="#" id="navSettlementDaily" data-show="settlementDailySection">
                                <span class="icon material-icons" aria-hidden="true">today</span>
                                <span class="label">Per Daily</span>
                            </a></li>
                            <li><a href="#" id="navSettlementEndMonth" data-show="settlementEndMonthSection">
                                <span class="icon material-icons" aria-hidden="true">event</span>
                                <span class="label">End Month</span>
                            </a></li>
                        </ul>
                    </li>
                   
                </ul>
            </li>

            <li class="nav-group">
                <button class="nav-group-toggle" aria-expanded="false" aria-controls="dataEntryMenu">
                    <span class="icon material-icons" aria-hidden="true">edit_note</span>
                    <span class="label">Data Entry</span>
                    <span class="chev material-icons" aria-hidden="true">expand_more</span>
                </button>
                <ul id="dataEntryMenu" class="nav-group-menu" style="display:none;">
                    <li><a href="#" id="navDataEntrySettlementDetail" data-show="dataEntrySettlementDetailSection">
                        <span class="icon material-icons" aria-hidden="true">payments</span>
                        <span class="label">Settlement Detail</span>
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
                        <li class="nav-subgroup">
                            <button class="nav-subgroup-toggle" type="button" aria-expanded="false" aria-controls="partnerDataReportMenu">
                                <span class="icon material-icons" aria-hidden="true">groups</span>
                                <span class="label">Partner Data Report</span>
                                <span class="chev material-icons" aria-hidden="true">expand_more</span>
                            </button>
                            <ul id="partnerDataReportMenu" class="nav-subgroup-menu" style="display:none;">
                                <li><a href="#" id="navReportsPartnerDataDaily" data-show="reportsPartnerDataSection">
                                    <span class="icon material-icons" aria-hidden="true">today</span>
                                    <span class="label">Daily</span>
                                </a></li>
                                <li><a href="#" id="navReportsPartnerDataSettlement" data-show="reportsPartnerDataSettlementSection">
                                    <span class="icon material-icons" aria-hidden="true">payments</span>
                                    <span class="label">Settlement</span>
                                </a></li>
                            </ul>
                        </li>
                        <li><a href="#" id="navSummaryReport" data-show="summaryReportSection">
                            <span class="icon material-icons" aria-hidden="true">summarize</span>
                            <span class="label">Summary Report</span>
                        </a></li>
                        <li><a href="#" id="navReconReport" data-show="reconReportSection">
                            <span class="icon material-icons" aria-hidden="true">receipt_long</span>
                            <span class="label">Recon Report</span>
                        </a></li>
                        <li><a href="#" id="navCashFlowReport" data-show="cashFlowReportSection">
                            <span class="icon material-icons" aria-hidden="true">account_balance_wallet</span>
                            <span class="label">Cash Flow Report</span>
                        </a></li>
                        <li><a href="#" id="navEdiReport" data-show="ediReportSection">
                            <span class="icon material-icons" aria-hidden="true">description</span>
                            <span class="label">EDI Report</span>
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
                <span class="label">Data Reconciliation</span>
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
                        <li class="nav-subgroup">
                            <button class="nav-subgroup-toggle" type="button" aria-expanded="false" aria-controls="originDataLogsMenu">
                                <span class="icon material-icons" aria-hidden="true">source</span>
                                <span class="label">Origin Data Logs</span>
                                <span class="chev material-icons" aria-hidden="true">expand_more</span>
                            </button>
                            <ul id="originDataLogsMenu" class="nav-subgroup-menu" style="display:none;">
                                <li><a href="#" id="navOriginDataLogsPartner" data-show="originDataLogsPartnerSection">
                                    <span class="icon material-icons" aria-hidden="true">groups</span>
                                    <span class="label">Partner</span>
                                </a></li>
                            </ul>
                        </li>
                        <li><a href="#" id="navBranchStatusLogs" data-show="branchStatusLogsSection">
                            <span class="icon material-icons" aria-hidden="true">fact_check</span>
                            <span class="label">Branch Status Logs</span>
                        </a></li>
                    </ul>
                </li>
            <?php if ($showMaintenanceMenu): ?>
                <li class="nav-group">
                    <button class="nav-group-toggle" aria-expanded="false" aria-controls="maintenanceMenu">
                        <span class="icon material-icons" aria-hidden="true">build</span>
                        <span class="label">Maintenance</span>
                        <span class="chev material-icons" aria-hidden="true">expand_more</span>
                    </button>
                    <ul id="maintenanceMenu" class="nav-group-menu" style="display:none;">
                        <li><a href="#" id="navDataUnlock" data-show="maintenanceDataUnlockSection">
                            <span class="icon material-icons" aria-hidden="true">lock_open</span>
                            <span class="label">Transaction Lock</span>
                        </a></li>
                        <?php if ($isAdmin): ?>
                            <li><a href="#" id="navBranchStatusPosting" data-show="branchStatusPostingSection">
                                <span class="icon material-icons" aria-hidden="true">published_with_changes</span>
                                <span class="label">Branch Status Posting</span>
                            </a></li>
                        <?php endif; ?>
                        <?php if ($isPrimaryAdmin): ?>
                            <!-- <li><a href="#" id="navMaintenance" data-show="maintenanceSection">
                                <span class="icon material-icons" aria-hidden="true">handshake</span>
                                <span class="label">Partner Legacy ID</span>
                            </a></li> -->
                        <?php endif; ?>
                        <?php if ($canManageUsers): ?>
                            <li><a href="#" id="navUsers" data-show="usersSection">
                                <span class="icon material-icons" aria-hidden="true">manage_accounts</span>
                                <span class="label">Users</span>
                            </a></li>
                        <?php endif; ?>
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
    <?php if ($activeSection === 'workspace' || $activeSection === 'recon' || $activeSection === 'maintenancedataunlock'): ?>
        <?php include __DIR__ . '/components/recon-section.php'; ?>
    <?php endif; ?>

    <?php if ($activeSection === 'dashboard'): ?>
        <section id="dashboardSection" class="dashboard-section" aria-label="Dashboard" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/dashboard.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($canManageUsers && $activeSection === 'users'): ?>
        <section id="usersSection" class="users-section" aria-label="Users" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/all-users.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'reportswebdata'): ?>
        <section id="reportsWebDataSection" class="reports-webdata-section" aria-label="ML Web Data Report" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/reportswebdata-section.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'partnerdatareportdaily'): ?>
        <section id="reportsPartnerDataSection" class="reports-partnerdata-section" aria-label="Partner Data Report" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/reportspartnerdata-section.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'partnerdatareportsettlement'): ?>
        <section id="reportsPartnerDataSettlementSection" class="reports-partnerdata-settlement-section" aria-label="Partner Data Report Settlement" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/reportspartnersettlement-section.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'summaryreport'): ?>
        <section id="summaryReportSection" class="summary-report-section" aria-label="Summary Report" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/summaryreport-section.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'reconreport'): ?>
        <section id="reconReportSection" class="recon-report-section" aria-label="Recon Report" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/reconreport-section.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'profilesettings'): ?>
        <section id="profileSettingsSection" class="profile-settings-section" aria-label="Profile Settings" style="display:none; padding:1rem">
            <?php include __DIR__ . '/components/profile-settings/user-profile-settings.php'; ?>
        </section>
    <?php endif; ?>

    <?php if ($activeSection === 'webdata') include __DIR__ . '/components/webdata-section.php'; ?>
    <?php if ($activeSection === 'webdatacancellation') include __DIR__ . '/components/webdata-cancellation-section.php'; ?>
    <?php if ($activeSection === 'kpxwebdataver2') include __DIR__ . '/components/data-upload/kpx-webdata-section.php'; ?>
    <?php if ($activeSection === 'settlementdaily') include __DIR__ . '/components/data-upload/settlement-detail/daily/settlementdaily-section.php'; ?>
    <?php if ($activeSection === 'settlementendmonth') include __DIR__ . '/components/data-upload/settlement-detail/end-month/settlementendmonth-section.php'; ?>
    <?php if ($activeSection === 'dataentrysettlementdetail') include __DIR__ . '/components/data-entry/settlement-detail/data-entry-settlement-detail-section.php'; ?>
    <?php if ($activeSection === 'partnerdata') include __DIR__ . '/components/partnerdata-section.php'; ?>
    <?php if ($activeSection === 'uploadedfilelogs') include __DIR__ . '/components/uploaded-file-logs.php'; ?>
    <?php if ($activeSection === 'origindatalogspartner') include __DIR__ . '/components/history-logs/origin-data-logs/origin-data-logs-partner.php'; ?>
    <?php if ($activeSection === 'branchstatuslogs') include __DIR__ . '/components/history-logs/branch-status-logs.php'; ?>
    <?php if ($activeSection === 'cashflowreport') include __DIR__ . '/components/data-reports/data-reports-cashflow-report.php'; ?>
    <?php if ($activeSection === 'edireport') include __DIR__ . '/components/data-reports/data-reports-edi-report.php'; ?>
    <?php if ($activeSection === 'maintenancedataunlock') include __DIR__ . '/components/maintenance/maintenance-transaction-lock.php'; ?>
    <?php if ($isAdmin && $activeSection === 'branchstatusposting') include __DIR__ . '/components/maintenance/branch-status-posting.php'; ?>
    <?php if ($isPrimaryAdmin && $activeSection === 'maintenance'): ?>
        <?php include __DIR__ . '/components/maintenance-section.php'; ?>
    <?php endif; ?>
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

    window.showHomeSection = function(sectionId) {
        try {
            const target = sectionId ? document.getElementById(sectionId) : null;
            if (!target) return false;
            hideAllSectionTargets();
            target.style.display = 'block';
            target.classList.add('is-visible');
            setActiveNavFor(sectionId);
            setTimeout(function(){
                try { target.scrollIntoView({behavior:'auto', block:'start'}); } catch (e) {}
            }, 40);
            return true;
        } catch (e) {
            return false;
        }
    };

    function setActiveNavFor(sectionId){
        try{
            const links = document.querySelectorAll('.sidebar-nav a');
            links.forEach(a=> a.classList.remove('is-active'));
            if(!sectionId) return;
            const activeLink = document.querySelector('.sidebar-nav a[data-show="'+sectionId+'"]');
            if(activeLink) {
                activeLink.classList.add('is-active');

                const subgroup = activeLink.closest('.nav-subgroup');
                if(subgroup){
                    subgroup.classList.add('is-open');
                    const subgroupToggle = subgroup.querySelector('.nav-subgroup-toggle');
                    const subgroupMenu = subgroup.querySelector('.nav-subgroup-menu');
                    if(subgroupToggle) subgroupToggle.setAttribute('aria-expanded', 'true');
                    if(subgroupMenu) subgroupMenu.style.display = 'flex';
                }

                const group = activeLink.closest('.nav-group');
                if(group){
                    group.classList.add('is-open');
                    const groupToggle = group.querySelector(':scope > .nav-group-toggle');
                    const groupMenu = group.querySelector(':scope > .nav-group-menu');
                    if(groupToggle) groupToggle.setAttribute('aria-expanded', 'true');
                    if(groupMenu) groupMenu.style.display = 'flex';
                }
            }
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
                <?php if ($canManageUsers): ?>
                users: 'usersSection',
                <?php endif; ?>
                webdata: 'webdataSection',
                webdatacancellation: 'webdataCancellationSection',
                kpxwebdataver2: 'kpxWebDataVer2Section',
                reportswebdata: 'reportsWebDataSection',
                settlementdaily: 'settlementDailySection',
                settlementendmonth: 'settlementEndMonthSection',
                dataentrysettlementdetail: 'dataEntrySettlementDetailSection',
                origindatalogspartner: 'originDataLogsPartnerSection',
                branchstatuslogs: 'branchStatusLogsSection',
                partnerdata: 'partnerdataSection',
                partnerdatareportdaily: 'reportsPartnerDataSection',
                partnerdatareportsettlement: 'reportsPartnerDataSettlementSection',
                cashflowreport: 'cashFlowReportSection',
                edireport: 'ediReportSection',
                summaryreport: 'summaryReportSection',
                reconreport: 'reconReportSection',
                profilesettings: 'profileSettingsSection',
                maintenancedataunlock: 'maintenanceDataUnlockSection',
                branchstatusposting: 'branchStatusPostingSection',
                <?php if ($isPrimaryAdmin): ?>
                maintenance: 'maintenanceSection',
                <?php endif; ?>
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

    // list of nav ids that are under maintenance
    const underMaintenanceNavIds = [
        // 'navUploadedFileLogs',
        'navWebDataCancellation',
        // 'navDataEntrySettlementDetail',
        // 'navEdiReport',
        'navBranchStatusLogs',
        'navMaintenance',
        //'navBranchStatusPosting'
    ];

    const sectionRoutes = {
        dashboardSection: 'dashboard',
        homeSection: 'workspace',
        usersSection: 'users',
        webdataSection: 'webdata',
        webdataCancellationSection: 'webdatacancellation',
        kpxWebDataVer2Section: 'kpxwebdataver2',
        partnerdataSection: 'partnerdata',
        settlementDailySection: 'settlementdaily',
        settlementEndMonthSection: 'settlementendmonth',
        dataEntrySettlementDetailSection: 'dataentrysettlementdetail',
        reportsWebDataSection: 'reportswebdata',
        reportsPartnerDataSection: 'partnerdatareportdaily',
        reportsPartnerDataSettlementSection: 'partnerdatareportsettlement',
        summaryReportSection: 'summaryreport',
        reconReportSection: 'reconreport',
        cashFlowReportSection: 'cashflowreport',
        ediReportSection: 'edireport',
        maintenanceDataUnlockSection: 'maintenancedataunlock',
        branchStatusPostingSection: 'branchstatusposting',
        maintenanceSection: 'maintenance',
        uploadedFileLogsSection: 'uploadedfilelogs',
        originDataLogsPartnerSection: 'origindatalogspartner',
        branchStatusLogsSection: 'branchstatuslogs'
    };

    function getSectionUrl(sectionId){
        const route = sectionRoutes[sectionId];
        if(!route) return '';
        const url = new URL(window.location.href);
        url.searchParams.set('section', route);
        url.hash = '';
        return url.toString();
    }

    // wire nav links
    ['navHome','navWorkspace','navUsers','navWebData','navWebDataCancellation','navKpxWebDataVer2','navPartnerData','navSettlementDaily','navSettlementEndMonth','navDataEntrySettlementDetail','navReportsWebData','navReportsPartnerDataDaily','navReportsPartnerDataSettlement','navSummaryReport','navReconReport','navCashFlowReport','navEdiReport','navReconTool','navDataUnlock','navBranchStatusPosting','navMaintenance','navUploadedFileLogs','navOriginDataLogsPartner','navBranchStatusLogs'].forEach(function(id){
        const navEl = document.getElementById(id);
        if (!navEl) return;
        navEl.addEventListener('click', function(e){
            try {
                e.preventDefault();
                if (underMaintenanceNavIds.includes(id)) {
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
                const sectionUrl = getSectionUrl(targetId);
                if (sectionUrl) {
                    window.location.href = sectionUrl;
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

    // Handle second-level sidebar menus.
    try {
        document.querySelectorAll('.nav-subgroup-toggle').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const subgroup = btn.closest('.nav-subgroup');
                const menu = subgroup ? subgroup.querySelector('.nav-subgroup-menu') : null;
                if (!subgroup || !menu) return;
                const open = !subgroup.classList.contains('is-open');
                subgroup.classList.toggle('is-open', open);
                menu.style.display = open ? 'flex' : 'none';
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    } catch (e) { /* ignore */ }

    try {
        const tooltip = document.createElement('div');
        tooltip.className = 'sidebar-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        document.body.appendChild(tooltip);

        function getTooltipText(item) {
            const label = item ? item.querySelector('.label') : null;
            return label ? label.textContent.trim() : '';
        }

        function showSidebarTooltip(item) {
            if (!item || sb.classList.contains('is-open')) return;
            const text = getTooltipText(item);
            if (!text) return;
            const rect = item.getBoundingClientRect();
            tooltip.textContent = text;
            tooltip.classList.add('is-visible');
            tooltip.style.left = (rect.right + 10) + 'px';
            tooltip.style.top = (rect.top + (rect.height / 2)) + 'px';
        }

        function hideSidebarTooltip() {
            tooltip.classList.remove('is-visible');
        }

        sb.querySelectorAll('.sidebar-nav a, .sidebar-nav .nav-group-toggle, .sidebar-nav .nav-subgroup-toggle').forEach(function(item) {
            item.addEventListener('mouseenter', function(){ showSidebarTooltip(item); });
            item.addEventListener('focus', function(){ showSidebarTooltip(item); });
            item.addEventListener('mouseleave', hideSidebarTooltip);
            item.addEventListener('blur', hideSidebarTooltip);
        });

        sb.addEventListener('transitionend', function(){
            if (sb.classList.contains('is-open')) hideSidebarTooltip();
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

        if (action === 'profile-settings') {
            const url = new URL(window.location.href);
            url.searchParams.set('section', 'profilesettings');
            url.hash = '';
            window.location.href = url.toString();
            return;
        }

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
