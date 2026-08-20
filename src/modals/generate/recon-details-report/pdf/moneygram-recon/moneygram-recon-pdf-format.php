<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../../config/session.php';
require_once __DIR__ . '/../../../../../config/db.php';
require_once __DIR__ . '/../../../../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

bootSecureSession();

$decoded = json_decode((string)($_POST['payload'] ?? ''), true);
$rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
$partnerName = trim((string)($decoded['partner'] ?? 'MONEYGRAM')) ?: 'MONEYGRAM';
$startDate = trim((string)($decoded['startDate'] ?? ''));
$endDate = trim((string)($decoded['endDate'] ?? ''));

$validDate = static fn(string $date): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
$startFileDate = $validDate($startDate);
$endFileDate = $validDate($endDate);
$filenameDateLabel = date('Y-m-d');
if ($startFileDate !== '' && $endFileDate !== '') {
    $filenameDateLabel = $startFileDate === $endFileDate ? $startFileDate : "from-{$startFileDate}-to-{$endFileDate}";
} elseif ($startFileDate !== '' || $endFileDate !== '') {
    $filenameDateLabel = $startFileDate ?: $endFileDate;
}

$sessionUser = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$generatedBy = '';
$idNumber = trim((string)($sessionUser['id_number'] ?? ''));
if ($idNumber !== '') {
    try {
        $stmt = userDbConnection()->prepare(
            "SELECT CONCAT_WS(' ', NULLIF(TRIM(firstname), ''), NULLIF(TRIM(middlename), ''), NULLIF(TRIM(lastname), ''))
             FROM filerecondb.users WHERE id_number = :id_number LIMIT 1"
        );
        $stmt->execute([':id_number' => $idNumber]);
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
$generatedBy = $generatedBy ?: trim((string)($sessionUser['username'] ?? '')) ?: 'SYSTEM USER';
$generatedDate = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('F d, Y h:i:s A');

$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$remarkLabels = [
    'Maybe New Branch' => 'Contact CAD System Administrator to verify Branch ID',
    'PARTNER DATA REFERENCE ID not found in KPX WEB Report' => 'PARTNER Data: REFERENCE ID not found in KPX Report',
    'KPX WEB DATA CCREF NO not found in Partners Report' => 'KPX Data: CCREF NO not found in Partners Report',
    'PARTNER Data: REFERENCE ID not found in KPX Report' => 'PARTNER Data: REFERENCE ID not found in KPX Report',
    'KPX Data: CCREF NO not found in Partners Report' => 'KPX Data: CCREF NO not found in Partners Report',
];
$formatRemarks = static function ($remark) use ($remarkLabels, $escape): string {
    $raw = trim((string)$remark);
    if ($raw === '') return '';
    $items = [];
    foreach ($remarkLabels as $needle => $display) {
        if (strpos($raw, $needle) !== false && !in_array($display, $items, true)) $items[] = $display;
    }
    if (!$items && strpos($raw, 'Legacy ID not yet registered. Contact System Administrator') !== false) return '';
    if (!$items) $items[] = $raw;
    return '<ul>' . implode('', array_map(static fn($item): string => '<li>' . $escape($item) . '</li>', $items)) . '</ul>';
};

$logoPath = realpath(__DIR__ . '/../../../../../assets/ml.png');
$logoUri = '';
if ($logoPath && is_readable($logoPath)) {
    $logoUri = 'data:image/png;base64,' . base64_encode((string)file_get_contents($logoPath));
}

$bodyRows = '';
if (!$rows) {
    $bodyRows = '<tr><td colspan="7" class="empty">No error detected</td></tr>';
} else {
    foreach ($rows as $row) {
        $bodyRows .= '<tr>'
            . '<td>' . $escape($row['transactionDate'] ?? '') . '</td>'
            . '<td>' . $escape($row['partnerReferenceId'] ?? '') . '</td>'
            . '<td>' . $escape($row['partnerAccountName'] ?? '') . '</td>'
            . '<td>' . $escape($row['webCcrefNo'] ?? '') . '</td>'
            . '<td>' . $escape($row['branchId'] ?? '') . '</td>'
            . '<td>' . $escape($row['branchName'] ?? '') . '</td>'
            . '<td class="remarks">' . $formatRemarks($row['remarks'] ?? '') . '</td>'
            . '</tr>';
    }
}

$html = '<!doctype html><html><head><meta charset="UTF-8"><style>
@page { margin: 34px 42px 42px; }
* { box-sizing: border-box; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; font-size: 9px; }
.header { text-align: center; }
.logo { width: 330px; max-height: 86px; object-fit: contain; margin: 8px auto 24px; }
.division { margin: 0 0 8px; font-size: 20px; font-weight: bold; }
h1 { margin: 0 0 17px; font-size: 14px; }
h2 { margin: 0 0 24px; font-size: 13px; }
table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
.report th, .report td { border: 1px solid #000; padding: 6px 5px; text-align: center; vertical-align: middle; overflow-wrap: anywhere; }
.report thead { display: table-header-group; }
.report th { font-size: 8px; font-weight: bold; line-height: 1.15; }
.report td { font-size: 8px; line-height: 1.25; }
.report tr { page-break-inside: avoid; }
.report .remarks { text-align: left; }
.report ul { margin: 0; padding-left: 13px; }
.report li { margin: 0 0 2px; }
.empty { height: 36px; text-align: center !important; }
.meta { margin-top: 54px; border-collapse: collapse; font-size: 9px; }
.meta th { width: 105px; padding: 2px 12px 2px 5px; text-align: left; }
.meta td { padding: 2px 5px; }
</style></head><body>
<div class="header">'
    . ($logoUri !== '' ? '<img class="logo" src="' . $logoUri . '" alt="M Lhuillier">' : '')
    . '<div class="division">CENTRAL ACCOUNTING DIVISION</div><h1>INCIDENT REPORT</h1><h2>' . $escape(strtoupper($partnerName)) . '</h2></div>
<table class="report"><colgroup>
<col style="width:15%"><col style="width:13%"><col style="width:16%"><col style="width:12%"><col style="width:10%"><col style="width:14%"><col style="width:20%">
</colgroup><thead><tr>
<th rowspan="2">TRANSACTION<br>DATE</th><th colspan="2">PARTNER DATA</th><th colspan="3">KPX WEB DATA</th><th rowspan="2">REMARKS</th>
</tr><tr><th>REFERENCE ID</th><th>ACCOUNT NAME</th><th>CCREF NO</th><th>BRANCH ID</th><th>BRANCH NAME</th></tr></thead>
<tbody>' . $bodyRows . '</tbody></table>
<table class="meta"><tr><th>Generated By:</th><td>' . $escape(strtoupper($generatedBy)) . '</td></tr>
<tr><th>Generated Date:</th><td>' . $escape($generatedDate) . '</td></tr></table>
</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->setPaper('letter', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();
$dompdf->stream("MONEYGRAM-ERROR-MONITORING-REPORT[{$filenameDateLabel}].pdf", ['Attachment' => true]);
