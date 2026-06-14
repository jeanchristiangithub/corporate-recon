<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/change-pass-controller.php';

bootSecureSession();
verifyCsrfOrFail();

$basePath = preg_replace('#/src/.*$#', '', str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))) ?: '';
$indexRedirect = ($basePath !== '' ? $basePath : '') . '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $indexRedirect);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'change_password') {
    header('Location: ' . $indexRedirect);
    exit;
}

$newPassword = (string) ($_POST['new_password'] ?? '');
$idNumber = (string) ($_SESSION['user']['id_number'] ?? '');

if ($idNumber === '') {
    $_SESSION['password_error'] = 'Session expired. Please sign in again.';
    header('Location: ' . $indexRedirect);
    exit;
}

// Do not allow setting the default admin password as the new password
$defaultPassword = 'Mlinc1234';
if (trim($newPassword) === $defaultPassword) {
    $_SESSION['password_error'] = 'New password cannot be the default password.';
    header('Location: ' . $indexRedirect);
    exit;
}

$controller = new ChangePassController();
$ok = $controller->changePassword($idNumber, $newPassword);

if ($ok) {
    $_SESSION['force_password_reset'] = false;
    unset($_SESSION['password_error']);
    header('Location: ../pages/home/home.php');
    exit;
}

$_SESSION['password_error'] = 'Password update failed. Use at least 8 characters and try again.';
header('Location: ' . $indexRedirect);
exit;
