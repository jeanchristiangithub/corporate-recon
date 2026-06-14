<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/usercontroller.php';

bootSecureSession();
verifyCsrfOrFail();

$redirectToUsers = '../pages/home/home.php?section=users';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectToUsers);
    exit;
}

require_once __DIR__ . '/middleware.php';
requireAuth();

$role = $_SESSION['user']['role'] ?? '';
if (strcasecmp((string) $role, 'Admin') !== 0) {
    $_SESSION['user_delete_error'] = 'Only Admin can delete users.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$idNumber = trim((string) ($_POST['id_number'] ?? ''));
if ($idNumber === '') {
    $_SESSION['user_delete_error'] = 'Invalid user identifier.';
    header('Location: ' . $redirectToUsers);
    exit;
}

// Prevent deleting yourself
$current = (string) ($_SESSION['user']['id_number'] ?? '');
if ($current !== '' && $current === $idNumber) {
    $_SESSION['user_delete_error'] = 'You cannot delete the currently signed-in account.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$uc = new UserController();
$ok = $uc->deleteUserByIdNumber($idNumber);
if (! $ok) {
    $_SESSION['user_delete_error'] = 'Failed to delete user.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$_SESSION['user_delete_success'] = 'User deleted successfully.';
header('Location: ' . $redirectToUsers);
exit;
