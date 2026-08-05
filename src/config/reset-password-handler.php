<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/../controllers/usercontroller.php';

bootSecureSession();
verifyCsrfOrFail();
requireAuth();

// Only Admin can reset other users' passwords
if (!isPrimaryAdminUser()) {
    $_SESSION['user_reset_error'] = 'Only the primary Admin can reset passwords.';
    header('Location: ../pages/home/home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/home/home.php');
    exit;
}

$idNumber = trim((string)($_POST['id_number'] ?? ''));
if ($idNumber === '') {
    $_SESSION['user_reset_error'] = 'Missing user identifier.';
    header('Location: ../pages/home/home.php');
    exit;
}

$uc = new UserController();
if (! $uc->userExistsByIdNumber($idNumber)) {
    $_SESSION['user_reset_error'] = 'User not found.';
    header('Location: ../pages/home/home.php');
    exit;
}

$user = $uc->findByIdNumber($idNumber);
$username = is_array($user) && !empty($user['username']) ? (string)$user['username'] : $idNumber;

$defaultPassword = 'Mlinc1234';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$ok = $uc->updatePasswordAndMarkLog($idNumber, $hash);

if ($ok) {
    $_SESSION['user_reset_success'] = 'The Password of ' . $username . ' has been reset to default.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

$_SESSION['user_reset_error'] = 'Failed to reset password.';
header('Location: ../pages/home/home.php?section=users');
exit;
