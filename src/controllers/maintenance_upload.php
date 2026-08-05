<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

bootSecureSession();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isPrimaryAdminUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (empty($_FILES['files'])) {
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit;
}

function maintenanceNormalizeHeader(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[\s_\-]+/', ' ', $value);

    return trim((string) $value);
}

function maintenanceNormalizeBranchName(string $value): string
{
    $value = strtoupper(trim($value));
    $value = str_replace(["\u{2013}", "\u{2014}", "\u{2012}", "\u{2212}"], '-', $value);
    $value = str_replace(['’', '‘', '`', "'"], '', $value);
    $value = str_replace(['.', ',', '(', ')', '[', ']', '{', '}', ':', ';', '/', '\\', '+', '*', '&', '#', '@', '!', '?', '"'], ' ', $value);
    $value = preg_replace('/\s*-\s*/u', ' - ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim((string) $value);

    $prefixPatterns = [
        '/^M\s*\.?\s*L\s*HUILLIER(?:\s+[A-Z]+)*\s*[-–—]?\s*/u',
        '/^MLHUILLIER(?:\s+[A-Z]+)*\s*[-–—]?\s*/u',
        '/^M\s*LHUILLIER(?:\s+[A-Z]+)*\s*[-–—]?\s*/u',
        '/^ML\s*HUILLIER(?:\s+[A-Z]+)*\s*[-–—]?\s*/u',
    ];

    foreach ($prefixPatterns as $pattern) {
        $updated = preg_replace($pattern, 'ML ', $value, 1);
        if (is_string($updated) && $updated !== $value) {
            $value = $updated;
            break;
        }
    }

    $value = preg_replace('/\s+/', ' ', $value);

    return trim((string) $value);
}

function maintenanceNormalizeBranchNameLoose(string $value): string
{
    $value = maintenanceNormalizeBranchName($value);
    $value = preg_replace('/[^A-Z0-9 ]+/u', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim((string) $value);
}

function maintenanceNormalizeBranchNameCompact(string $value): string
{
    return preg_replace('/\s+/', '', maintenanceNormalizeBranchNameLoose($value)) ?? '';
}

function maintenanceBranchLookupIndex(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [
        'rows' => [],
        'normalized' => [],
        'compact' => [],
    ];

    try {
        $masterPdo = masterDataConnection();
        $stmt = $masterPdo->prepare('SELECT branch_id, branch_name FROM branch_profile WHERE branch_id IS NOT NULL AND TRIM(branch_id) <> ""');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $branchId = trim((string) ($row['branch_id'] ?? ''));
            $branchName = trim((string) ($row['branch_name'] ?? ''));
            if ($branchId === '' || $branchName === '') {
                continue;
            }

            $normalized = maintenanceNormalizeBranchNameLoose($branchName);
            $compact = maintenanceNormalizeBranchNameCompact($branchName);
            $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $numericTokens = [];
            foreach ($words as $word) {
                if (preg_match('/^\d+$/', $word)) {
                    $numericTokens[] = $word;
                }
            }

            $cache['rows'][] = [
                'branch_id' => $branchId,
                'branch_name' => $branchName,
                'normalized' => $normalized,
                'compact' => $compact,
                'words' => $words,
                'numeric_tokens' => $numericTokens,
            ];

            if ($normalized !== '' && !isset($cache['normalized'][$normalized])) {
                $cache['normalized'][$normalized] = [];
            }

            if ($compact !== '' && !isset($cache['compact'][$compact])) {
                $cache['compact'][$compact] = [];
            }

            if ($normalized !== '') {
                $cache['normalized'][$normalized][] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                ];
            }

            if ($compact !== '') {
                $cache['compact'][$compact][] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branchName,
                ];
            }
        }
    } catch (Throwable $e) {
        $cache['rows'] = [];
    }

    return $cache;
}

function maintenanceNormalizeAgentNameForMatch(string $value): string
{
    $value = maintenanceNormalizeBranchNameLoose($value);
    $value = preg_replace('/\bML\s+/', 'ML ', $value, 1) ?? $value;

    return trim((string) $value);
}

function maintenanceTokenizeBranchWords(string $value): array
{
    $normalized = maintenanceNormalizeBranchNameLoose($value);
    if ($normalized === '') {
        return [];
    }

    $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopWords = ['ML', 'M', 'LHUILLIER', 'LHUILLER', 'PHILIPPINES'];
    $filtered = [];

    foreach ($words as $word) {
        if (in_array($word, $stopWords, true)) {
            continue;
        }
        $filtered[] = $word;
    }

    return array_values($filtered);
}

function maintenanceExtractNumericTokens(array $words): array
{
    $tokens = [];
    foreach ($words as $word) {
        if (preg_match('/^\d+$/', (string) $word)) {
            $tokens[] = (string) $word;
        }
    }

    return array_values(array_unique($tokens));
}

function maintenanceScoreBranchCandidate(array $agentWords, array $branchWords, array $agentNumbers, array $branchNumbers): array
{
    $agentCount = count($agentWords);
    $branchCount = count($branchWords);

    if ($agentCount === 0 || $branchCount === 0) {
        return [0.0, 0, false];
    }

    $agentSet = array_fill_keys($agentWords, true);
    $branchSet = array_fill_keys($branchWords, true);
    $shared = array_intersect_key($agentSet, $branchSet);
    $sharedCount = count($shared);

    $allWordsMatched = $sharedCount > 0 && $sharedCount === min($agentCount, $branchCount);

    $sharedNumbers = 0;
    if (!empty($agentNumbers) && !empty($branchNumbers)) {
        $sharedNumbers = count(array_intersect($agentNumbers, $branchNumbers));
    }

    $coverage = $sharedCount / max($agentCount, $branchCount);
    $precision = $sharedCount / max($branchCount, 1);
    $recall = $sharedCount / max($agentCount, 1);
    $score = ($coverage * 0.45) + ($precision * 0.25) + ($recall * 0.20) + min(0.10, $sharedNumbers * 0.05);

    if ($sharedCount === 0) {
        $score = 0.0;
    }

    return [$score, $sharedCount, $allWordsMatched && ($sharedNumbers > 0 || $sharedCount >= 2 || $agentCount <= 2)];
}

function maintenanceResolveBranchMatch(string $agentName): array
{
    static $cache = [];

    $original = trim($agentName);
    if ($original === '') {
        return [
            'branch_id' => '',
            'branch_name' => '',
            'method' => 'empty',
            'confidence' => 0.0,
        ];
    }

    $normalized = maintenanceNormalizeAgentNameForMatch($original);
    $cacheKey = $normalized;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $agentWords = maintenanceTokenizeBranchWords($normalized);
    $agentNumbers = maintenanceExtractNumericTokens($agentWords);
    $branchIndex = maintenanceBranchLookupIndex();

    $best = [
        'branch_id' => '',
        'branch_name' => '',
        'method' => 'none',
        'confidence' => 0.0,
        'matched_words' => 0,
    ];

    if ($normalized !== '' && isset($branchIndex['normalized'][$normalized])) {
        foreach ($branchIndex['normalized'][$normalized] as $candidate) {
            $best = [
                'branch_id' => (string) ($candidate['branch_id'] ?? ''),
                'branch_name' => (string) ($candidate['branch_name'] ?? ''),
                'method' => 'exact_normalized',
                'confidence' => 1.0,
                'matched_words' => count($agentWords),
            ];
            $cache[$cacheKey] = $best;
            return $best;
        }
    }

    $candidates = [];
    foreach ($branchIndex['rows'] as $row) {
        $branchWords = (array) ($row['words'] ?? []);
        $branchNumbers = (array) ($row['numeric_tokens'] ?? []);
        [$score, $matchedWords, $allWordsMatched] = maintenanceScoreBranchCandidate($agentWords, $branchWords, $agentNumbers, $branchNumbers);

        if ($score <= 0.0) {
            continue;
        }

        $branchName = (string) ($row['branch_name'] ?? '');
        $branchId = (string) ($row['branch_id'] ?? '');
        $branchNormalized = (string) ($row['normalized'] ?? '');

        $numericMismatch = false;
        if (!empty($agentNumbers) || !empty($branchNumbers)) {
            if (empty(array_intersect($agentNumbers, $branchNumbers))) {
                $numericMismatch = true;
                $score *= 0.65;
            }
        }

        $partialLike = false;
        if ($branchNormalized !== '' && $normalized !== '') {
            if (stripos($normalized, $branchNormalized) !== false || stripos($branchNormalized, $normalized) !== false) {
                $partialLike = true;
                $score += 0.08;
            }
        }

        $candidates[] = [
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'confidence' => round(min(1.0, $score + ($allWordsMatched ? 0.12 : 0.0)), 4),
            'method' => $allWordsMatched ? 'all_words_matched' : ($partialLike ? 'partial_like' : 'word_overlap'),
            'matched_words' => $matchedWords,
            'numeric_mismatch' => $numericMismatch,
            'branch_words_count' => count($branchWords),
        ];
    }

    if (empty($candidates)) {
        $cache[$cacheKey] = $best;
        error_log(json_encode([
            'type' => 'maintenance_branch_lookup',
            'original_agent_name' => $original,
            'normalized_agent_name' => $normalized,
            'matched_branch_name' => '',
            'branch_id' => '',
            'method' => 'none',
            'confidence' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $best;
    }

    usort($candidates, static function (array $left, array $right): int {
        if (($left['confidence'] ?? 0) === ($right['confidence'] ?? 0)) {
            if (($left['matched_words'] ?? 0) === ($right['matched_words'] ?? 0)) {
                return strcmp((string) ($left['branch_name'] ?? ''), (string) ($right['branch_name'] ?? ''));
            }

            return (($right['matched_words'] ?? 0) <=> ($left['matched_words'] ?? 0));
        }

        return (($right['confidence'] ?? 0) <=> ($left['confidence'] ?? 0));
    });

    $top = $candidates[0];
    $second = $candidates[1] ?? null;
    $topConfidence = (float) ($top['confidence'] ?? 0.0);
    $topMatchedWords = (int) ($top['matched_words'] ?? 0);
    $topBranchName = (string) ($top['branch_name'] ?? '');
    $topMethod = (string) ($top['method'] ?? 'word_overlap');
    $topNumericMismatch = (bool) ($top['numeric_mismatch'] ?? false);

    $isHighConfidence = false;
    if ($topMethod === 'exact_normalized') {
        $isHighConfidence = true;
    } elseif ($topMethod === 'all_words_matched' && $topConfidence >= 0.75 && !$topNumericMismatch) {
        $isHighConfidence = true;
    } elseif ($topConfidence >= 0.88 && !$topNumericMismatch) {
        if ($second === null || (($topConfidence - (float) ($second['confidence'] ?? 0.0)) >= 0.12)) {
            $isHighConfidence = true;
        }
    }

    if ($isHighConfidence) {
        $best = [
            'branch_id' => (string) ($top['branch_id'] ?? ''),
            'branch_name' => $topBranchName,
            'method' => $topMethod,
            'confidence' => $topConfidence,
            'matched_words' => $topMatchedWords,
        ];
    }

    if ($best['branch_id'] === '') {
        error_log(json_encode([
            'type' => 'maintenance_branch_lookup',
            'original_agent_name' => $original,
            'normalized_agent_name' => $normalized,
            'matched_branch_name' => '',
            'branch_id' => '',
            'method' => 'no_high_confidence_match',
            'confidence' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } else {
        error_log(json_encode([
            'type' => 'maintenance_branch_lookup',
            'original_agent_name' => $original,
            'normalized_agent_name' => $normalized,
            'matched_branch_name' => $best['branch_name'],
            'branch_id' => $best['branch_id'],
            'method' => $best['method'],
            'confidence' => $best['confidence'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $cache[$cacheKey] = $best;

    return $best;
}

function maintenanceFindHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $highestColIndex): array
{
    $maxSearchRow = min(50, (int) $sheet->getHighestRow());
    $bestRow = 1;
    $bestHeaders = [];
    $bestScore = -1;

    for ($row = 1; $row <= $maxSearchRow; $row++) {
        $headers = [];
        $score = 0;

        for ($column = 1; $column <= $highestColIndex; $column++) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $cellValue = trim((string) $sheet->getCell($letter . $row)->getValue());
            if ($cellValue === '') {
                continue;
            }

            $headers[$column] = $cellValue;
            $score++;
        }

        $hasAgentName = false;
        foreach ($headers as $headerValue) {
            if (maintenanceNormalizeHeader($headerValue) === 'AGENT NAME') {
                $hasAgentName = true;
                break;
            }
        }

        if ($hasAgentName && $score >= $bestScore) {
            $bestScore = $score;
            $bestRow = $row;
            $bestHeaders = $headers;
        }
    }

    if (empty($bestHeaders)) {
        $bestHeaders = [];
        for ($column = 1; $column <= $highestColIndex; $column++) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $bestHeaders[$column] = trim((string) $sheet->getCell($letter . '1')->getValue());
        }
        $bestRow = 1;
    }

    return [$bestRow, $bestHeaders];
}

function maintenanceSafeFileBase(string $originalName): string
{
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $base);
    $base = trim((string) $base, '._-');

    return $base !== '' ? $base : 'maintenance_file';
}

function maintenanceEnsureDirectory(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create maintenance export directory');
    }
}

$files = $_FILES['files'];
$fileCount = is_array($files['name'] ?? null) ? count($files['name']) : 0;

if ($fileCount === 0) {
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit;
}

$baseDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'maintenance';
$exportsDir = $baseDir . DIRECTORY_SEPARATOR . 'exports';
$batchId = date('Ymd_His') . '_' . bin2hex(random_bytes(5));

try {
    maintenanceEnsureDirectory($exportsDir);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$processedFiles = [];
$failed = 0;

for ($index = 0; $index < $fileCount; $index++) {
    $originalName = (string) ($files['name'][$index] ?? '');
    $tmpName = (string) ($files['tmp_name'][$index] ?? '');
    $uploadError = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['xlsx', 'xls', 'csv', 'txt'];

    if ($originalName === '' || !in_array($extension, $allowedExtensions, true) || $uploadError !== UPLOAD_ERR_OK || !is_uploaded_file($tmpName)) {
        $failed++;
        $processedFiles[] = [
            'name' => $originalName !== '' ? $originalName : ('file_' . ($index + 1)),
            'status' => 'failed',
        ];
        continue;
    }

    try {
        $spreadsheet = IOFactory::load($tmpName);
        $worksheetCount = $spreadsheet->getSheetCount();
        $fileSuccess = false;

        for ($sheetIndex = 0; $sheetIndex < $worksheetCount; $sheetIndex++) {
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $highestRow = (int) $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColIndex = Coordinate::columnIndexFromString($highestColumn);
            [$headerRow, $headers] = maintenanceFindHeaderRow($sheet, $highestColIndex);

            $agentColumn = null;
            $branchColumn = null;
            foreach ($headers as $columnIndex => $headerValue) {
                $normalizedHeader = maintenanceNormalizeHeader((string) $headerValue);
                if ($normalizedHeader === 'AGENT NAME' && $agentColumn === null) {
                    $agentColumn = (int) $columnIndex;
                }
                if ($normalizedHeader === 'BRANCH ID' && $branchColumn === null) {
                    $branchColumn = (int) $columnIndex;
                }
            }

            if ($agentColumn === null) {
                continue;
            }

            if ($branchColumn === null) {
                $branchColumn = $agentColumn + 1;
                $sheet->insertNewColumnBefore(Coordinate::stringFromColumnIndex($branchColumn), 1);
            }

            $branchLetter = Coordinate::stringFromColumnIndex($branchColumn);
            $agentLetter = Coordinate::stringFromColumnIndex($agentColumn);
            $sheet->setCellValue($branchLetter . $headerRow, 'Branch ID');

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $agentName = trim((string) $sheet->getCell($agentLetter . $row)->getValue());
                $branchMatch = maintenanceResolveBranchMatch($agentName);
                $branchId = (string) ($branchMatch['branch_id'] ?? '');
                $sheet->setCellValue($branchLetter . $row, $branchId);
            }

            $fileSuccess = true;
        }

        if (!$fileSuccess) {
            $failed++;
            $processedFiles[] = [
                'name' => $originalName,
                'status' => 'failed',
            ];
            continue;
        }

        $safeBase = maintenanceSafeFileBase($originalName);
        $outputName = $safeBase . '_with_branch_id.xlsx';
        $outputPath = $exportsDir . DIRECTORY_SEPARATOR . $batchId . '_' . $outputName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $processedFiles[] = [
            'name' => $originalName,
            'status' => 'success',
            'download_name' => $outputName,
            'download_path' => '../../../uploads/maintenance/exports/' . $batchId . '_' . $outputName,
            'filesystem_path' => $outputPath,
        ];
    } catch (Throwable $e) {
        $failed++;
        $processedFiles[] = [
            'name' => $originalName,
            'status' => 'failed',
        ];
    }
}

$successFiles = array_values(array_filter($processedFiles, static function (array $file): bool {
    return ($file['status'] ?? '') === 'success';
}));

if (empty($successFiles)) {
    echo json_encode([
        'success' => false,
        'error' => 'No files were processed successfully',
        'uploaded' => 0,
        'failed' => $failed,
        'files' => $processedFiles,
    ]);
    exit;
}

$downloadUrl = null;
$downloadName = null;

if (count($successFiles) === 1) {
    $downloadUrl = $successFiles[0]['download_path'] ?? null;
    $downloadName = $successFiles[0]['download_name'] ?? null;
} else {
    $zipName = 'updated_branchid_files_' . $batchId . '.zip';
    $zipPath = $exportsDir . DIRECTORY_SEPARATOR . $zipName;
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode([
            'success' => false,
            'error' => 'Unable to package exported files',
            'uploaded' => count($successFiles),
            'failed' => $failed,
            'files' => $processedFiles,
        ]);
        exit;
    }

    foreach ($successFiles as $fileInfo) {
        $fullPath = (string) ($fileInfo['filesystem_path'] ?? '');
        if ($fullPath !== '' && is_file($fullPath)) {
            $zip->addFile($fullPath, (string) ($fileInfo['download_name'] ?? basename($fullPath)));
        }
    }

    $zip->close();
    $downloadUrl = '../../../uploads/maintenance/exports/' . $zipName;
    $downloadName = $zipName;
}

echo json_encode([
    'success' => true,
    'uploaded' => count($successFiles),
    'failed' => $failed,
    'files' => $processedFiles,
    'ready_for_export' => true,
    'download_url' => $downloadUrl,
    'download_name' => $downloadName,
    'export_mode' => count($successFiles) === 1 ? 'xlsx' : 'zip',
]);
