<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function csrfToken(): string
{
    bootSecureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verifyCsrfOrFail(): void
{
    bootSecureSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!is_string($sent) || !is_string($stored) || $stored === '' || !hash_equals($stored, $sent)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}
