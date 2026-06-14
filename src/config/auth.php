<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function isAuthenticated(): bool
{
    bootSecureSession();
    return !empty($_SESSION['user']);
}

function currentUser(): ?array
{
    bootSecureSession();
    return $_SESSION['user'] ?? null;
}
