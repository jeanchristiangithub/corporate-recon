<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/db.php';

bootSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

$sentToken = (string)($_POST['csrf_token'] ?? '');
$storedToken = (string)($_SESSION['csrf_token'] ?? '');
if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

$payload = json_decode((string)($_POST['payload'] ?? ''), true);
$originalFilename = trim(basename((string)($payload['filename'] ?? '')));
$filename = trim((string)pathinfo($originalFilename, PATHINFO_FILENAME));
$filenameExtension = strtolower(trim((string)pathinfo($originalFilename, PATHINFO_EXTENSION)));
$partnerId = trim((string)($payload['partner_id'] ?? ''));
$partnerName = trim((string)($payload['partner_name'] ?? ''));
$uploadedBy = trim((string)($_SESSION['user']['id_number'] ?? ''));

if ($filename === '' || $filenameExtension === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The uploaded filename or extension is missing.']);
    exit;
}
if ($partnerId !== '1' || stripos($partnerName, 'moneygram') === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This upload workflow is available only for MoneyGram partner ID 1.']);
    exit;
}

try {
    $pdo = fileRecDbConnection();
    $pdo->beginTransaction();

    $find = $pdo->prepare(
        'SELECT id FROM uploaded_file_logs
         WHERE filename = ? AND partner_id = ?
         ORDER BY id DESC LIMIT 1 FOR UPDATE'
    );
    $find->execute([$filename, $partnerId]);
    $existingId = $find->fetchColumn();
    $overwritten = $existingId !== false;

    if ($overwritten) {
        $fileLogId = (int)$existingId;
        $update = $pdo->prepare("UPDATE uploaded_file_logs SET has_overwrite = '1' WHERE id = ?");
        $update->execute([$fileLogId]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO uploaded_file_logs
             (uploaded_date, filename, filename_ext, partner_id, partner_name, uploaded_by, has_overwrite, kpxweb_data_status)
             VALUES (NOW(), ?, ?, ?, ?, ?, \'0\', \'SD\')'
        );
        $insert->execute([
            $filename,
            $filenameExtension,
            $partnerId,
            $partnerName,
            $uploadedBy !== '' ? $uploadedBy : null,
        ]);
        $fileLogId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'file_log_id' => $fileLogId,
        'has_overwrite' => $overwritten ? '1' : '0',
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[settlement-file-log] ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to create or update the settlement upload log.']);
}
