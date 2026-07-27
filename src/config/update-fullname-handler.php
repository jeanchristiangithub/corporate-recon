<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/db.php';

bootSecureSession();
verifyCsrfOrFail();
requireAuth();

$redirectToUsers = '../pages/home/home.php?section=users';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectToUsers);
    exit;
}

$currentRole = $_SESSION['user']['role'] ?? '';
if (strcasecmp((string) $currentRole, 'Admin') !== 0) {
    $_SESSION['user_update_error'] = 'Only Admin can update users.';
    header('Location: ' . $redirectToUsers);
    exit;
}

$idNumber = trim((string) ($_POST['id_number'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$firstname = trim((string) ($_POST['firstname'] ?? ''));
$lastname = trim((string) ($_POST['lastname'] ?? ''));
$role = trim((string) ($_POST['role'] ?? 'Public'));

if ($idNumber === '' || $username === '') {
    $_SESSION['user_update_error'] = 'Invalid user update request.';
    header('Location: ' . $redirectToUsers);
    exit;
}

if (strcasecmp($role, 'Admin') !== 0) {
    $role = 'Public';
}

try {
    $db = userDbConnection();

    $select = $db->prepare('SELECT id_number, username, firstname, lastname, role FROM users WHERE id_number = ? LIMIT 1');
    $select->execute([$idNumber]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if (!is_array($existing)) {
        $_SESSION['user_update_error'] = 'User not found.';
        header('Location: ' . $redirectToUsers);
        exit;
    }

    $candidateValues = [
        'username' => $username,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'role' => $role,
    ];

    $setClauses = [];
    $params = [];
    foreach ($candidateValues as $column => $value) {
        $currentValue = trim((string) ($existing[$column] ?? ''));
        if ($value === $currentValue) {
            continue;
        }

        $setClauses[] = $column . ' = ?';
        $params[] = $value;
    }

    if ($setClauses !== []) {
        $params[] = $idNumber;
        $updateSql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id_number = ?';
        $update = $db->prepare($updateSql);
        $update->execute($params);
    }

    $_SESSION['user_update_success'] = 'Updated Successfully';
    $_SESSION['user_update_id_number'] = $idNumber;
    header('Location: ' . $redirectToUsers);
    exit;
} catch (Throwable $e) {
    $_SESSION['user_update_error'] = 'Failed to update user.';
    header('Location: ' . $redirectToUsers);
    exit;
}
