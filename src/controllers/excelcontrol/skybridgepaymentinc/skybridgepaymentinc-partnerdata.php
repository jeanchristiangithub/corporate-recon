<?php
// skybridgepaymentinc-partnerdata.php
// Extract partner-format Excel files for SkyBridge Payment Inc.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
    exit;
}

$allowedExt = ['xls', 'xlsx', 'xlsm', 'xlsb', 'ods', 'csv'];
$fname = isset($_POST['filename']) ? basename($_POST['filename']) : $file['name'];
$ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/skybridgepaymentinc-helper.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$tmp = $file['tmp_name'];
try {
    $spreadsheet = IOFactory::load($tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow    = $sheet->getHighestRow();
    $highestCol    = $sheet->getHighestColumn();
    $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    // ------------------------------------------------------------------
    // Find the header row: the row with the most non-empty cells within
    // the first 50 rows (generic — no assumed column names)
    // ------------------------------------------------------------------
    $headers   = [];
    $headerRow = 1;
    $maxSearch = min(50, (int)$highestRow);
    $best = -1;

    for ($rr = 1; $rr <= $maxSearch; $rr++) {
        $tmpH = [];
        for ($c = 1; $c <= $highestColIdx; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $raw = (string)$sheet->getCell($colLetter . $rr)->getValue();
            if ($raw !== '') $tmpH[$c] = trim($raw);
        }
        if (count($tmpH) > $best) {
            $best      = count($tmpH);
            $headers   = $tmpH;
            $headerRow = $rr;
        }
        // Early exit: once we have found a row with several non-empty cells
        // then immediately followed by a row with fewer, assume header found.
        if ($rr > 1 && count($tmpH) < $best && $best >= 3) break;
    }

    // Helper: read a cell and convert Excel serial dates/times when appropriate
    $cellVal = function (int $colIdx, int $rowIdx) use ($sheet): string {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        $cell   = $sheet->getCell($letter . $rowIdx);
        $val    = $cell->getValue();
        if ($val === null || $val === '') return '';
        if (is_numeric($val)) {
            $fval = (float)$val;
            // Excel date serial (> 1 means a real date, not a fraction time)
            if ($fval >= 1) {
                try {
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $dt = ExcelDate::excelToDateTimeObject($fval);
                        return $dt->format('Y-m-d H:i:s');
                    }
                } catch (Throwable $e) { /* fall through */ }
            }
            // Time fraction (0 < val < 1)
            if ($fval > 0 && $fval < 1) {
                $totalSec = (int)round($fval * 86400);
                return sprintf('%02d:%02d:%02d', intdiv($totalSec, 3600), intdiv($totalSec % 3600, 60), $totalSec % 60);
            }
        }
        return (string)$val;
    };

    // Heuristically find a column that acts as the "anchor" (non-empty means data row)
    // Prefer columns whose header looks like a reference/date/ID
    $anchorColIdx = null;
    foreach ($headers as $colIdx => $h) {
        $n = strtoupper((string)$h);
        if (strpos($n, 'REFERENCE') !== false || strpos($n, 'DATE') !== false || strpos($n, 'NO') !== false) {
            $anchorColIdx = $colIdx;
            break;
        }
    }
    // Fallback: use the first header column
    if ($anchorColIdx === null && !empty($headers)) {
        $anchorColIdx = array_key_first($headers);
    }

    // ------------------------------------------------------------------
    // Iterate data rows — extract exactly what is in the file
    // ------------------------------------------------------------------
    $rows        = [];
    $dateStr     = '';
    $emptyStreak = 0;

    for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
        // Detect end-of-data: 3 consecutive completely empty rows → stop
        $rowEmpty = true;
        for ($c = 1; $c <= $highestColIdx; $c++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            if ((string)$sheet->getCell($letter . $r)->getValue() !== '') { $rowEmpty = false; break; }
        }
        if ($rowEmpty) { if (++$emptyStreak >= 3) break; continue; }
        $emptyStreak = 0;

        // Skip rows where the anchor column is empty
        if ($anchorColIdx !== null && trim($cellVal($anchorColIdx, $r)) === '') continue;

        $item = [];
        foreach ($headers as $colIdx => $headerLabel) {
            $item[$headerLabel] = $cellVal($colIdx, $r);
        }

        // Populate dateStr from the first date-looking value
        if ($dateStr === '') {
            foreach ($item as $v) {
                if ($v === '') continue;
                $ts = strtotime($v);
                if ($ts !== false && $ts > mktime(0,0,0,1,1,2000)) {
                    $dateStr = date('F d, Y', $ts);
                    break;
                }
            }
        }

        $rows[] = $item;
    }

    $payload = [
        'filename' => $fname,
        'dateStr'  => $dateStr,
        'rows'     => $rows,
    ];

    echo json_encode(['success' => true, 'payload' => $payload]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
