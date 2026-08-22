<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/db.php';

if (!isAuthenticated()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$requestData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$settlementId = filter_var($requestData['settlement_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$documentId = filter_var($requestData['document_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($settlementId === false) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Invalid settlement row.']);
    exit;
}

try {
    $pdo = fileRecDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfOrFail();
        header('Content-Type: application/json; charset=utf-8');
        if ($documentId === false || (string)($_POST['action'] ?? '') !== 'delete') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid supporting document.']);
            exit;
        }

        $pdo->beginTransaction();
        $selectDocument = $pdo->prepare(
            'SELECT filehash_path
             FROM uploaded_documentation_file_logs
             WHERE id = ? AND psd_datarows_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $selectDocument->execute([$documentId, $settlementId]);
        $relativePath = trim((string)($selectDocument->fetchColumn() ?: ''));
        if ($relativePath === '') {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Supporting document not found.']);
            exit;
        }
        $deleteDocument = $pdo->prepare(
            'DELETE FROM uploaded_documentation_file_logs WHERE id = ? AND psd_datarows_id = ?'
        );
        $deleteDocument->execute([$documentId, $settlementId]);
        $referenceCount = $pdo->prepare(
            'SELECT COUNT(*) FROM uploaded_documentation_file_logs WHERE filehash_path = ?'
        );
        $referenceCount->execute([$relativePath]);
        $mayDeleteStoredFile = (int)$referenceCount->fetchColumn() === 0;
        $pdo->commit();

        if ($mayDeleteStoredFile
            && preg_match('#^sha256/[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{64}(?:\.[A-Za-z0-9_-]{1,45})?$#', str_replace('\\', '/', $relativePath))) {
            $storageRoot = env(
                'SUPPORTING_DOCUMENT_STORAGE_PATH',
                dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'corporate-recon-storage' . DIRECTORY_SEPARATOR . 'supporting-documents'
            );
            $absolutePath = rtrim((string)$storageRoot, '/\\') . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        echo json_encode(['success' => true, 'deleted_id' => (int)$documentId]);
        exit;
    }

    if ($documentId !== false) {
        $statement = $pdo->prepare(
            'SELECT filename, filename_ext, filehash_path
             FROM uploaded_documentation_file_logs
             WHERE id = ? AND psd_datarows_id = ?
             LIMIT 1'
        );
        $statement->execute([$documentId, $settlementId]);
        $document = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            http_response_code(404);
            exit('Supporting document not found.');
        }

        $relativePath = str_replace('\\', '/', trim((string)($document['filehash_path'] ?? '')));
        if (!preg_match('#^sha256/[a-f0-9]{2}/[a-f0-9]{2}/[a-f0-9]{64}(?:\.[A-Za-z0-9_-]{1,45})?$#', $relativePath)) {
            throw new RuntimeException('Invalid supporting-document path.');
        }
        $storageRoot = env(
            'SUPPORTING_DOCUMENT_STORAGE_PATH',
            dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'corporate-recon-storage' . DIRECTORY_SEPARATOR . 'supporting-documents'
        );
        $absolutePath = rtrim((string)$storageRoot, '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            http_response_code(404);
            exit('Supporting document file not found.');
        }

        $downloadName = trim((string)($document['filename'] ?? 'document'));
        $extension = trim((string)($document['filename_ext'] ?? ''));
        if ($extension !== '') {
            $downloadName .= '.' . $extension;
        }
        $downloadName = str_replace(["\r", "\n", '"'], ['', '', "'"], $downloadName);
        $mimeType = 'application/octet-stream';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($absolutePath);
            if (is_string($detectedMime) && $detectedMime !== '') {
                $mimeType = $detectedMime;
            }
        }
        $inlinePdf = (string)($_GET['view'] ?? '') === '1' && strtolower($extension) === 'pdf';
        if ($inlinePdf) {
            $mimeType = 'application/pdf';
        }
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: ' . ($inlinePdf ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($downloadName));
        header('X-Content-Type-Options: nosniff');
        readfile($absolutePath);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    $statement = $pdo->prepare(
        "SELECT d.id, d.filename, d.filename_ext, d.uploaded_date, d.uploaded_by,
                TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.firstname), ''), NULLIF(TRIM(u.middlename), ''), NULLIF(TRIM(u.lastname), ''))) AS uploaded_by_name
         FROM uploaded_documentation_file_logs d
         LEFT JOIN users u
           ON CONVERT(u.id_number USING utf8mb4) COLLATE utf8mb4_unicode_ci
            = CONVERT(d.uploaded_by USING utf8mb4) COLLATE utf8mb4_unicode_ci
         WHERE d.psd_datarows_id = ?
         ORDER BY d.uploaded_date DESC, d.id DESC"
    );
    $statement->execute([$settlementId]);
    echo json_encode([
        'success' => true,
        'documents' => $statement->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unable to load supporting documents.']);
}
