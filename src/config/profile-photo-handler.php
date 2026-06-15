<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

bootSecureSession();
verifyCsrfOrFail();

$redirectToHome = '../pages/home/home.php';

if (!isAuthenticated()) {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectToHome);
    exit;
}

$user = currentUser();
$idNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($user['id_number'] ?? ''));

if ($idNumber === '') {
    $_SESSION['profile_photo_error'] = 'Unable to identify the current user.';
    header('Location: ' . $redirectToHome);
    exit;
}

$uploadDir = dirname(__DIR__, 2) . '/uploads/profile-photos';
$action = (string)($_POST['profile_photo_action'] ?? 'upload');

if ($action === 'default') {
    $removedPhoto = false;

    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $oldExtension) {
        $oldPath = $uploadDir . '/' . $idNumber . '.' . $oldExtension;
        if (is_file($oldPath)) {
            $removedPhoto = @unlink($oldPath) || $removedPhoto;
        }
    }

    $_SESSION['profile_photo_success'] = $removedPhoto
        ? 'Profile photo reset to default.'
        : 'Profile photo is already using the default.';
    header('Location: ' . $redirectToHome);
    exit;
}

if (empty($_FILES['profile_photo']) || !is_array($_FILES['profile_photo'])) {
    $_SESSION['profile_photo_error'] = 'Please choose a photo to upload.';
    header('Location: ' . $redirectToHome);
    exit;
}

$file = $_FILES['profile_photo'];
$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($error !== UPLOAD_ERR_OK) {
    $_SESSION['profile_photo_error'] = 'Photo upload failed. Please try again.';
    header('Location: ' . $redirectToHome);
    exit;
}

$tmpName = (string)($file['tmp_name'] ?? '');
$size = (int)($file['size'] ?? 0);

if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    $_SESSION['profile_photo_error'] = 'Invalid uploaded file.';
    header('Location: ' . $redirectToHome);
    exit;
}

if ($size <= 0 || $size > 2 * 1024 * 1024) {
    $_SESSION['profile_photo_error'] = 'Photo must be 2 MB or smaller.';
    header('Location: ' . $redirectToHome);
    exit;
}

$imageInfo = @getimagesize($tmpName);
$mime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
$extensionsByMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!isset($extensionsByMime[$mime])) {
    $_SESSION['profile_photo_error'] = 'Please upload a JPG, PNG, WEBP, or GIF photo.';
    header('Location: ' . $redirectToHome);
    exit;
}

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    $_SESSION['profile_photo_error'] = 'Unable to prepare profile photo storage.';
    header('Location: ' . $redirectToHome);
    exit;
}

$extension = $extensionsByMime[$mime];
$targetPath = $uploadDir . '/' . $idNumber . '.' . $extension;

foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $oldExtension) {
    $oldPath = $uploadDir . '/' . $idNumber . '.' . $oldExtension;
    if ($oldPath !== $targetPath && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

if (!move_uploaded_file($tmpName, $targetPath)) {
    $_SESSION['profile_photo_error'] = 'Unable to save profile photo.';
    header('Location: ' . $redirectToHome);
    exit;
}

@chmod($targetPath, 0664);

$_SESSION['profile_photo_success'] = 'Profile photo updated.';
header('Location: ' . $redirectToHome);
exit;
