<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/../controllers/usercontroller.php';
require_once __DIR__ . '/middleware.php';

bootSecureSession();
verifyCsrfOrFail();
requireAuth();
// Only Admin should be able to add users
$role = $_SESSION['user']['role'] ?? '';
if (strcasecmp((string) $role, 'Admin') !== 0) {
    $_SESSION['user_create_error'] = 'Only Admin users can add accounts.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$firstname = trim((string) ($_POST['firstname'] ?? ''));
$lastname = trim((string) ($_POST['lastname'] ?? ''));
$role = trim((string) ($_POST['role'] ?? 'Public'));

// Use server-side default password for new users (readonly in the UI)
$defaultPassword = 'Mlinc1234';

if ($idNumber === '' || $username === '') {
    $_SESSION['user_create_error'] = 'Please fill required fields.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

$uc = new UserController();
if ($uc->userExistsByUsername($username)) {
    $_SESSION['user_create_error'] = 'This account ' . $username . ' already exists.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

if ($uc->userExistsByIdNumber($idNumber)) {
    $_SESSION['user_create_error'] = 'User with that ID already exists.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

$passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$ok = $uc->createUser([
    'id_number' => $idNumber,
    'username' => $username,
    'firstname' => $firstname,
    'lastname' => $lastname,
    'role' => $role,
    'password_hash' => $passwordHash,
]);

if (! $ok) {
    $_SESSION['user_create_error'] = 'Failed to create user.';
    header('Location: ../pages/home/home.php?section=users');
    exit;
}

$_SESSION['user_create_success'] = 'User created successfully.';
header('Location: ../pages/home/home.php?section=users');
exit;
