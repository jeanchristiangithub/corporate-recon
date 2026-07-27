<?php

require_once __DIR__ . '/../../config/session.php';

/**
 * Return the authenticated uploader after confirming the account still exists.
 * The identity is intentionally read from the server-side session, never input JSON.
 */
function partnerUploadAuthenticatedIdNumber(PDO $pdo): string
{
    bootSecureSession();

    $idNumber = trim((string)($_SESSION['user']['id_number'] ?? ''));
    if ($idNumber === '') {
        throw new RuntimeException('Your login session has expired. Please log in again.');
    }

    $stmt = $pdo->prepare('SELECT id_number FROM users WHERE id_number = ? LIMIT 1');
    $stmt->execute([$idNumber]);
    $verifiedIdNumber = trim((string)($stmt->fetchColumn() ?: ''));
    if ($verifiedIdNumber === '') {
        throw new RuntimeException('The logged-in user could not be verified.');
    }

    return $verifiedIdNumber;
}
