<?php
$profileUser = $_SESSION['user'] ?? [];
$firstName = trim((string) ($profileUser['firstname'] ?? ''));
$lastName = trim((string) ($profileUser['lastname'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);
if ($fullName === '') {
    $fullName = (string) ($profileUser['username'] ?? 'User');
}

$role = (string) ($profileUser['role'] ?? 'Public');
$lastLogin = trim((string) ($_SESSION['last_login_at'] ?? ''));
$lastLoginTimestamp = $lastLogin !== '' ? strtotime($lastLogin) : false;
$lastLoginDisplay = $lastLoginTimestamp !== false ? date('F j, Y h:i:s A', $lastLoginTimestamp) : 'Not available';
?>

<div class="profile-settings">
    <h2 class="profile-settings__title">Profile Settings</h2>

    <div class="profile-settings__tabs" role="radiogroup" aria-label="Profile settings tabs">
        <label class="profile-settings__tab">
            <input type="radio" name="profile_settings_tab" value="profile" checked>
            <span>Your Profile</span>
        </label>
        <label class="profile-settings__tab">
            <input type="radio" name="profile_settings_tab" value="account">
            <span>Account</span>
        </label>
    </div>

    <div class="profile-settings__body-card" data-profile-settings-body>
        <div class="profile-settings__panel is-active" data-profile-settings-panel="profile">
            <section class="profile-settings__info-card" aria-labelledby="profileAccountInformationTitle">
                <h3 id="profileAccountInformationTitle">Account Information</h3>
                <dl class="profile-settings__details">
                    <div>
                        <dt>Full Name:</dt>
                        <dd><?= htmlspecialchars(strtoupper($fullName), ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div>
                        <dt>Access Level:</dt>
                        <dd><span class="profile-settings__role-badge"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></span></dd>
                    </div>
                    <div>
                        <dt>Status:</dt>
                        <dd><span class="profile-settings__status"><span aria-hidden="true"></span>Online</span></dd>
                    </div>
                </dl>
            </section>

            <section class="profile-settings__info-card" aria-labelledby="profileSystemOverviewTitle">
                <h3 id="profileSystemOverviewTitle">System Overview</h3>
                <div class="profile-settings__login-box">
                    Last Login: <?= htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </section>
        </div>

        <div class="profile-settings__panel" data-profile-settings-panel="account" hidden></div>
    </div>
</div>

<script>
(function(){
    const settings = document.querySelector('.profile-settings');
    if (!settings) return;

    const tabs = settings.querySelectorAll('input[name="profile_settings_tab"]');
    const panels = settings.querySelectorAll('[data-profile-settings-panel]');
    if (!tabs.length || !panels.length) return;

    function showPanel(value) {
        panels.forEach(function(panel) {
            const isActive = panel.getAttribute('data-profile-settings-panel') === value;
            panel.hidden = !isActive;
            panel.classList.toggle('is-active', isActive);
        });
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('change', function() {
            if (tab.checked) showPanel(tab.value);
        });
    });
})();
</script>
