<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/usercontroller.php';

bootSecureSession();
verifyCsrfOrFail();

$basePath = preg_replace('#/src/.*$#', '', str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))) ?: '';
$indexRedirect = ($basePath !== '' ? $basePath : '') . '/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $indexRedirect);
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Username and password are required.';
    header('Location: ' . $indexRedirect);
    exit;
}

$controller = new UserController();
$user = $controller->findByUsername($username);

if (!$user || !password_verify($password, (string) $user['password'])) {
    $_SESSION['login_error'] = 'Invalid credentials.';
    header('Location: ' . $indexRedirect);
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id_number' => $user['id_number'],
    'username' => $user['username'],
    'firstname' => $user['firstname'],
    'lastname' => $user['lastname'],
    'role' => $user['role'],
];

$latestLog = $controller->latestUserLogByIdNumber((string) $user['id_number']);
$needsPasswordReset = false;
$_SESSION['last_login_at'] = (string) ($latestLog['datemodified'] ?? '');

if ($latestLog) {
    $dateModified = $latestLog['datemodified'] ?? null;
    $status = (string) ($latestLog['status'] ?? '');
    $needsPasswordReset = ($dateModified === null || $dateModified === '') || strcasecmp($status, 'reset') === 0;
}

// Block login for users marked as inactive in the latest user log
if ($latestLog) {
    $latestStatus = (string) ($latestLog['status'] ?? '');
    if (strcasecmp($latestStatus, 'inactive') === 0) {
        // Do not allow sign-in
        session_regenerate_id(true);
        $_SESSION['login_error'] = 'This account has been deactivated. Contact an administrator.';
        header('Location: ' . $indexRedirect);
        exit;
    }
}

// If the account is still using the default admin-created password, force reset
$defaultPassword = 'Mlinc1234';
if (!$needsPasswordReset && password_verify($defaultPassword, (string)$user['password'])) {
    $needsPasswordReset = true;
}

$_SESSION['force_password_reset'] = $needsPasswordReset;

$role = $user['role'] ?? '';

// If the user must reset their password, send them back to the index so the reset modal appears
if ($needsPasswordReset) {
    $_SESSION['force_password_reset'] = true;
    header('Location: ' . $indexRedirect);
    exit;
}

// Allow Admin and Public roles to proceed to the Home page
$isAdmin = strcasecmp((string) $role, 'Admin') === 0;
$isPublic = strcasecmp((string) $role, 'Public') === 0;

if (! $isAdmin && ! $isPublic) {
    $_SESSION['construction_modal'] = sprintf('%s page is under construction', (string) $role ?: 'This');
    header('Location: ' . $indexRedirect);
    exit;
}

$controller->markLoginAndEnsureLog((string) $user['id_number']);

// Default: go to home for Admin and Public users
header('Location: ../pages/home/home.php');
exit;
