<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Convert PHP warnings/notices to exceptions so we can return clean JSON
$prevErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
	throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
});

ob_start();
try {
	bootSecureSession();
	// avoid calling requireAuth() because it may redirect/exit and break JSON response
	// instead perform a safe session-based check and return JSON if unauthenticated
	$userId = $_SESSION['user']['id_number'] ?? null;
	if (empty($userId)) {
		// discard any incidental output and return explicit JSON
		ob_end_clean();
		restore_error_handler();
		http_response_code(401);
		echo json_encode(['success' => false, 'message' => 'Not authenticated']);
		exit;
	}

	// Clear recent submissions used by the test controller
	$_SESSION['excel_compare_recent'] = [];
	// mark the time we cleared so immediate re-uploads get a short grace window
	$_SESSION['excel_compare_recent_cleared_at'] = time();

	// discard any incidental output and return explicit JSON
	ob_end_clean();
	restore_error_handler();
	error_log('[clear-recent] cleared excel_compare_recent for user=' . ($userId ?? 'unknown'));
	echo json_encode(['success' => true, 'message' => 'Cleared excel_compare_recent']);
	exit;
} catch (\Throwable $e) {
	// remove any buffered HTML/whitespace and return JSON error
	if (ob_get_length() !== false) {
		@ob_end_clean();
	}
	restore_error_handler();
	// log details for debugging
	error_log('[clear-recent] exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
	http_response_code(500);
	$msg = strip_tags((string) $e->getMessage());
	echo json_encode(['success' => false, 'message' => 'Server error clearing recent: ' . $msg]);
	exit;
}
