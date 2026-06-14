<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/csrf.php';

bootSecureSession();

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (preg_match('#^(.*?)/src/#', $scriptName, $matches)) {
    $baseUrl = $matches[1];
} else {
    $baseUrl = rtrim(dirname($scriptName), '/');
}
$baseUrl = $baseUrl === '/' ? '' : $baseUrl;
$landingBaseHref = ($baseUrl !== '' ? $baseUrl : '') . '/src/pages/index/';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorecon | File System Reconciliation</title>
    <base href="<?= htmlspecialchars($landingBaseHref, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/logo4.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/logo4.png">
    <link rel="stylesheet" href="./index.css">
</head>
<body>
<div class="page-shell">
    <?php include __DIR__ . '/components/index-header.php'; ?>

    <main>
        <?php include __DIR__ . '/components/hero.php'; ?>
    </main>
</div>

<?php include __DIR__ . '/../../modals/login-modal/login-modal.php'; ?>
        <?php
        // Construction modal (shows when a non-public role logs in and the page is under construction)
        $constructionMessage = $_SESSION['construction_modal'] ?? '';
        unset($_SESSION['construction_modal']);
        if ($constructionMessage !== ''): ?>
            <div class="construction-modal is-open" role="dialog" aria-modal="true" aria-label="Role notice">
                <div class="construction-modal__card">
                    <p><?= htmlspecialchars($constructionMessage, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="../../config/logout-handler.php" class="material-btn material-btn--primary">Okay</a>
                </div>
            </div>
        <?php endif; ?>
<?php include __DIR__ . '/../../modals/password-modal/newlogin-reset-pass.php'; ?>
<script>
    window.autoreconLoginError = <?= json_encode($loginError, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<script src="./index.js"></script>
</body>
</html>
