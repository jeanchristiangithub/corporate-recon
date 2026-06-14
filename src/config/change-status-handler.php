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
    $_SESSION['user_status_error'] = 'Only Admin can change user status.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
if ($idNumber === '' || ($status !== 'active' && $status !== 'inactive')) {
    $_SESSION['user_status_error'] = 'Invalid request.';
    header('Location: ' . $redirectToUsers);
    exit;
}

// Prevent deactivating yourself
$current = (string) ($_SESSION['user']['id_number'] ?? '');
if ($current !== '' && $current === $idNumber && $status === 'inactive') {
    $_SESSION['user_status_error'] = 'You cannot deactivate the currently signed-in account.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$uc = new UserController();
if (! $uc->userExistsByIdNumber($idNumber)) {
    $_SESSION['user_status_error'] = 'User not found.';
    header('Location: ' . $redirectToUsers);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $db = userDbConnection();
    $stmt = $db->prepare('INSERT INTO userlogs (id_number, datemodified, status) VALUES (:id_number, NOW(), :status)');
    $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $ok = $stmt->execute();
} catch (Throwable $e) {
    $ok = false;
}

if (! $ok) {
    $_SESSION['user_status_error'] = 'Failed to update user status.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$_SESSION['user_status_success'] = 'User status updated.';
header('Location: ' . $redirectToUsers);
exit;
