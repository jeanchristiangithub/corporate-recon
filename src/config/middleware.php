<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function autoreconIndexUrl(): string
{
    $basePath = autoreconBasePath();

    return ($basePath !== '' ? $basePath : '') . '/index.php';
}

function autoreconBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = preg_replace('#/src/.*$#', '', $scriptName) ?: '';

    return $basePath === '/' ? '' : rtrim($basePath, '/');
}

function requireAuth(): void
{
    bootSecureSession();

    if (empty($_SESSION['user'])) {
        header('Location: ' . autoreconIndexUrl());
        exit;
    }
}

function isPrimaryAdminUser(): bool
{
    bootSecureSession();

    $user = $_SESSION['user'] ?? [];

    return is_array($user)
        && trim((string) ($user['id_number'] ?? '')) === '1'
        && strcasecmp(trim((string) ($user['role'] ?? '')), 'Admin') === 0;
}

function requireAdminRoleOrShowConstruction(): void
{
    bootSecureSession();

    $role = $_SESSION['user']['role'] ?? '';

    // Allow access for Admin and Public roles (case-insensitive)
    $isAdmin = strcasecmp((string) $role, 'Admin') === 0;
    $isPublic = strcasecmp((string) $role, 'Public') === 0;

    if (! $isAdmin && ! $isPublic) {
        $_SESSION['construction_modal'] = sprintf('%s page is under construction', (string) $role ?: 'This');
    }
}
