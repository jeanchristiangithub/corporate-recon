<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/csrf.php';
// Start output buffering early to capture any accidental output from included files
ob_start();

header('Content-Type: application/json; charset=utf-8');
bootSecureSession();
requireAuth();

// Manual CSRF check so we can return JSON on failure
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($stored) || $stored === '' || !hash_equals($stored, $sent)) {
        // clean any buffered output before returning JSON
        $buf = ob_get_clean();
        if ($buf !== '') {
            error_log('[clear-section] unexpected output before CSRF failure: ' . $buf);
        }
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

// Only allow POST for clearing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Clear recent compare session data
$_SESSION['excel_compare_recent'] = [];
$_SESSION['excel_compare_recent_cleared_at'] = time();
// remove stored payloads if present
if (!empty($_SESSION['excel_compare_recent_payloads'])) {
    $_SESSION['excel_compare_recent_payloads'] = [];
}

// capture and log any accidental output before returning JSON
$buf = ob_get_clean();
if ($buf !== '') {
    error_log('[clear-section] unexpected output before success response: ' . $buf);
}

error_log('[clear-section] user ' . ($_SESSION['user']['id_number'] ?? 'unknown') . ' cleared compare session');

echo json_encode(['success' => true, 'message' => 'Sessions cleared']);
exit;
