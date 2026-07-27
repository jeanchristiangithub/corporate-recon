<?php
require_once __DIR__ . '/../../../../../config/session.php';
require_once __DIR__ . '/../../../../../config/db.php';

bootSecureSession();

$payload = isset($_POST['payload']) ? (string)$_POST['payload'] : '';
$decoded = json_decode($payload, true);
$rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
$partnerName = trim((string)($decoded['partner'] ?? 'MONEYGRAM'));
if ($partnerName === '') {
    $partnerName = 'MONEYGRAM';
}
$startDate = trim((string)($decoded['startDate'] ?? ''));
$endDate = trim((string)($decoded['endDate'] ?? ''));
$dateForFilename = static function (string $date): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
};
$startDateForFilename = $dateForFilename($startDate);
$endDateForFilename = $dateForFilename($endDate);
$filenameDateLabel = date('Y-m-d');
if ($startDateForFilename !== '' && $endDateForFilename !== '') {
    $filenameDateLabel = $startDateForFilename === $endDateForFilename
        ? $startDateForFilename
        : 'from-' . $startDateForFilename . '-to-' . $endDateForFilename;
} elseif ($startDateForFilename !== '') {
    $filenameDateLabel = $startDateForFilename;
} elseif ($endDateForFilename !== '') {
    $filenameDateLabel = $endDateForFilename;
}
$runDateTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F d, Y H:i:s A');
$sessionUser = isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
$generatedBy = '';
$idNumber = trim((string)($sessionUser['id_number'] ?? ''));
if ($idNumber !== '') {
    try {
        $stmt = userDbConnection()->prepare(
            "SELECT CONCAT_WS(' ',
                NULLIF(TRIM(firstname), ''),
                NULLIF(TRIM(middlename), ''),
                NULLIF(TRIM(lastname), '')
            ) AS full_name
            FROM filerecondb.users
            WHERE id_number = :id_number
            LIMIT 1"
        );
        $stmt->bindValue(':id_number', $idNumber, PDO::PARAM_STR);
        $stmt->execute();
        $generatedBy = trim((string)($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $generatedBy = '';
    }
}
if ($generatedBy === '') {
    $generatedBy = trim(implode(' ', array_filter([
        trim((string)($sessionUser['firstname'] ?? '')),
        trim((string)($sessionUser['middlename'] ?? '')),
        trim((string)($sessionUser['lastname'] ?? '')),
    ])));
}
if ($generatedBy === '') {
    $generatedBy = trim((string)($sessionUser['username'] ?? ''));
}

$remarkLabels = [
    'Maybe New Branch',
    'PARTNER DATA REFERENCE ID not found in KPX WEB Report',
    'KPX WEB DATA CCREF NO not found in Partners Report',
    'PARTNER Data: REFERENCE ID not found in KPX Report',
    'KPX Data: CCREF NO not found in Partners Report',
];

$remarkDisplayLabels = [
    'Maybe New Branch' => 'Maybe New Branch, Contact CAD System Administrator to verify Branch ID',
    'PARTNER DATA REFERENCE ID not found in KPX WEB Report' => 'PARTNER Data: REFERENCE ID not found in KPX Report',
    'KPX WEB DATA CCREF NO not found in Partners Report' => 'KPX Data: CCREF NO not found in Partners Report',
];

$remarkItems = static function ($remark) use ($remarkLabels, $remarkDisplayLabels): array {
    $raw = trim((string)$remark);
    if ($raw === '') {
        return [];
    }
    $items = [];
    foreach ($remarkLabels as $label) {
        if (strpos($raw, $label) !== false) {
            $items[] = $remarkDisplayLabels[$label] ?? $label;
        }
    }
    if (strpos($raw, 'Legacy ID not yet registered. Contact System Administrator') !== false && !$items) {
        return [];
    }
    return $items ?: [$raw];
};

$pdfText = static function ($value): string {
    $text = preg_replace('/\s+/u', ' ', trim((string)($value ?? '')));
    $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    return $text;
};

$wrapText = static function ($value, float $width, int $fontSize) use ($pdfText): array {
    $text = preg_replace('/\s+/u', ' ', trim((string)($value ?? '')));
    if ($text === '') {
        return [''];
    }
    $maxChars = max(5, (int)floor(($width - 10) / ($fontSize * 0.62)));
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate) <= $maxChars) {
            $line = $candidate;
            continue;
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        while (mb_strlen($word) > $maxChars) {
            $lines[] = mb_substr($word, 0, $maxChars);
            $word = mb_substr($word, $maxChars);
        }
        $line = $word;
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return array_map($pdfText, $lines ?: ['']);
};

$pageWidth = 612.0;
$pageHeight = 792.0;
$margin = 18.0;
$usableWidth = $pageWidth - ($margin * 2);
$columns = [
    ['label' => 'TRANSACTION DATE', 'key' => 'transactionDate', 'width' => 78.0],
    ['label' => 'REFERENCE ID', 'key' => 'partnerReferenceId', 'width' => 75.0],
    ['label' => 'ACCOUNT NAME', 'key' => 'partnerAccountName', 'width' => 105.0],
    ['label' => 'CCREF NO', 'key' => 'webCcrefNo', 'width' => 75.0],
    ['label' => 'BRANCH ID', 'key' => 'branchId', 'width' => 58.0],
    ['label' => 'BRANCH NAME', 'key' => 'branchName', 'width' => 95.0],
    ['label' => 'REMARKS', 'key' => 'remarks', 'width' => $usableWidth - 486.0],
];

$fontSize = 8;
$headerFontSize = 8;
$lineHeight = 10.0;
$cellPaddingX = 5.0;
$cellPaddingY = 6.0;
$headerHeight = 34.0;
$reportHeaderHeight = 72.0;
$bottomLimit = $margin;
$pages = [];

$drawText = static function (float $x, float $y, string $text, int $size = 8, string $font = 'F1'): string {
    return "BT /{$font} {$size} Tf 1 0 0 1 " . number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . " Tm ({$text}) Tj ET\n";
};

$drawRect = static function (float $x, float $y, float $w, float $h): string {
    return number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' ' . number_format($w, 2, '.', '') . ' ' . number_format($h, 2, '.', '') . " re S\n";
};

$drawFilledRect = static function (float $x, float $y, float $w, float $h): string {
    return number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '') . ' ' . number_format($w, 2, '.', '') . ' ' . number_format($h, 2, '.', '') . " re f\n";
};

$estimateTextWidth = static function (string $text, int $fontSize): float {
    $width = 0.0;
    $chars = str_split($text);
    foreach ($chars as $char) {
        if ($char === ' ') {
            $width += $fontSize * 0.28;
        } elseif (preg_match('/[ilI1|]/', $char)) {
            $width += $fontSize * 0.24;
        } elseif (preg_match('/[MW]/', $char)) {
            $width += $fontSize * 0.78;
        } else {
            $width += $fontSize * 0.56;
        }
    }
    return $width;
};

$newPage = static function (bool $withReportHeader = false) use (&$pages, $margin, $pageHeight, $columns, $headerHeight, $reportHeaderHeight, $drawRect, $drawText, $headerFontSize, $cellPaddingX, $pdfText, $wrapText, $estimateTextWidth, $partnerName, $runDateTime, $generatedBy): array {
    $content = "0 G 0 g 0.6 w\n";
    if ($withReportHeader) {
        $topY = $pageHeight - $margin - 10;
        $content .= $drawText($margin, $topY, 'Error Detection Monitoring', 11, 'F2');
        $content .= $drawText($margin, $topY - 14, 'Partner: ' . $pdfText($partnerName), 10, 'F2');
        $content .= $drawText($margin, $topY - 28, 'Run Date & Time: ' . $pdfText($runDateTime), 10, 'F2');
        $content .= $drawText($margin, $topY - 42, 'Generated By: ' . $pdfText($generatedBy), 10, 'F2');
    }
    $x = $margin;
    $y = $pageHeight - $margin - $headerHeight - ($withReportHeader ? $reportHeaderHeight : 0);
    $halfHeader = $headerHeight / 2;
    $centerText = static function (string $label, float $cellX, float $cellY, float $cellWidth, float $cellHeight) use ($drawText, $headerFontSize, $cellPaddingX, $estimateTextWidth, $pdfText): string {
        if ($label === 'TRANSACTION DATE') {
            $lines = ['TRANSACTION', 'DATE'];
            $lineHeight = $headerFontSize + 2;
            $startY = $cellY + (($cellHeight + ($lineHeight * count($lines))) / 2) - $headerFontSize - 1;
            $output = '';
            foreach ($lines as $index => $line) {
                $lineWidth = $estimateTextWidth($line, $headerFontSize);
                $textX = $cellX + max($cellPaddingX, min($cellWidth - $cellPaddingX - $lineWidth, ($cellWidth - $lineWidth) / 2));
                $output .= $drawText($textX, $startY - ($index * $lineHeight), $pdfText($line), $headerFontSize, 'F2');
            }
            return $output;
        }
        $line = $pdfText($label);
        $labelWidth = $estimateTextWidth($label, $headerFontSize);
        $textX = $cellX + max($cellPaddingX, min($cellWidth - $cellPaddingX - $labelWidth, ($cellWidth - $labelWidth) / 2));
        $textY = $cellY + (($cellHeight - $headerFontSize) / 2) + 1;
        return $drawText($textX, $textY, $line, $headerFontSize, 'F2');
    };

    $content .= $drawRect($x, $y, $columns[0]['width'], $headerHeight);
    $content .= $centerText($columns[0]['label'], $x, $y, $columns[0]['width'], $headerHeight);
    $x += $columns[0]['width'];

    $partnerWidth = $columns[1]['width'] + $columns[2]['width'];
    $content .= $drawRect($x, $y + $halfHeader, $partnerWidth, $halfHeader);
    $content .= $centerText('PARTNER DATA', $x, $y + $halfHeader, $partnerWidth, $halfHeader);
    $childX = $x;
    for ($i = 1; $i <= 2; $i++) {
        $content .= $drawRect($childX, $y, $columns[$i]['width'], $halfHeader);
        $content .= $centerText($columns[$i]['label'], $childX, $y, $columns[$i]['width'], $halfHeader);
        $childX += $columns[$i]['width'];
    }
    $x += $partnerWidth;

    $webWidth = $columns[3]['width'] + $columns[4]['width'] + $columns[5]['width'];
    $content .= $drawRect($x, $y + $halfHeader, $webWidth, $halfHeader);
    $content .= $centerText('KPX WEB DATA', $x, $y + $halfHeader, $webWidth, $halfHeader);
    $childX = $x;
    for ($i = 3; $i <= 5; $i++) {
        $content .= $drawRect($childX, $y, $columns[$i]['width'], $halfHeader);
        $content .= $centerText($columns[$i]['label'], $childX, $y, $columns[$i]['width'], $halfHeader);
        $childX += $columns[$i]['width'];
    }
    $x += $webWidth;

    $content .= $drawRect($x, $y, $columns[6]['width'], $headerHeight);
    $content .= $centerText($columns[6]['label'], $x, $y, $columns[6]['width'], $headerHeight);
    $pages[] = $content;
    return [count($pages) - 1, $y];
};

[$pageIndex, $currentY] = $newPage(true);

if (!$rows) {
    $rows = [[
        'transactionDate' => '',
        'legacyId' => '',
        'partnerReferenceId' => '',
        'partnerAccountName' => '',
        'webCcrefNo' => '',
        'branchId' => '',
        'branchName' => '',
        'remarks' => 'No data detected',
    ]];
}

foreach ($rows as $row) {
    $cellLines = [];
    $rowHeight = 0.0;
    foreach ($columns as $column) {
        if ($column['key'] === 'remarks') {
            $items = $remarkItems($row['remarks'] ?? '');
            $lines = [];
            foreach ($items as $item) {
                $wrapped = $wrapText($item, $column['width'] - 10, $fontSize);
                foreach ($wrapped as $lineIndex => $line) {
                    $lines[] = ['text' => $line, 'bullet' => $lineIndex === 0];
                }
            }
            $cellLines[] = $lines ?: [['text' => '', 'bullet' => false]];
            $rowHeight = max($rowHeight, count($lines ?: [1]) * $lineHeight + ($cellPaddingY * 2));
            continue;
        }

        $rawValue = (string)($row[$column['key']] ?? '');
        $wrappedLines = $column['key'] === 'partnerReferenceId' || $column['key'] === 'webCcrefNo'
            ? [$pdfText($rawValue)]
            : $wrapText($rawValue, $column['width'], $fontSize);
        $lines = array_map(static fn($line) => ['text' => $line, 'bullet' => false], $wrappedLines);
        $cellLines[] = $lines;
        $rowHeight = max($rowHeight, count($lines) * $lineHeight + ($cellPaddingY * 2));
    }

    if (($currentY - $rowHeight) < $bottomLimit) {
        [$pageIndex, $currentY] = $newPage(false);
    }

    $currentY -= $rowHeight;
    $x = $margin;
    foreach ($columns as $columnIndex => $column) {
        $pages[$pageIndex] .= $drawRect($x, $currentY, $column['width'], $rowHeight);
        $textY = $currentY + $rowHeight - $cellPaddingY - $fontSize;
        foreach ($cellLines[$columnIndex] as $line) {
            $textX = $x + $cellPaddingX;
            if (!empty($line['bullet'])) {
                $pages[$pageIndex] .= $drawFilledRect($textX, $textY + 2.5, 2.2, 2.2);
                $textX += 8;
            } elseif ($column['key'] === 'remarks') {
                $textX += 8;
            } else {
                $plainLine = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $line['text']);
                $lineWidth = $estimateTextWidth($plainLine, $fontSize);
                $textX = $x + max($cellPaddingX, min($column['width'] - $cellPaddingX - $lineWidth, ($column['width'] - $lineWidth) / 2));
            }
            $pages[$pageIndex] .= $drawText($textX, $textY, $line['text'], $fontSize, 'F1');
            $textY -= $lineHeight;
        }
        $x += $column['width'];
    }
}

if (($currentY - 18) < $bottomLimit) {
    [$pageIndex, $currentY] = $newPage(false);
}
$pages[$pageIndex] .= $drawText($margin, $currentY - 14, 'VOLUME: ' . count($rows), 10, 'F2');

$objects = [];
$objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[] = null;
$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

$pageObjectNumbers = [];
foreach ($pages as $content) {
    $streamNumber = count($objects) + 1;
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    $pageNumber = count($objects) + 1;
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$streamNumber} 0 R >>";
    $pageObjectNumbers[] = $pageNumber;
}

$kids = implode(' ', array_map(static fn($num) => "{$num} 0 R", $pageObjectNumbers));
$objects[1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjectNumbers) . " >>";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $index => $object) {
    $offsets[$index + 1] = strlen($pdf);
    $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
}

$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="MONEYGRAM-ERROR-DETECTED-MONITORING[' . $filenameDateLabel . '].pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
