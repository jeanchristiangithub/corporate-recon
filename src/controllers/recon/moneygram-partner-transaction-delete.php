<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '{}', true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid transaction ID.']);
        exit;
    }

    $pdo = fileRecDbConnection();
    $stmt = $pdo->prepare('DELETE FROM moneygram_partner_data WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() < 1) {
        echo json_encode(['success' => false, 'error' => 'Transaction details not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'deleted_id' => $id]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete transaction.']);
    exit;
}
