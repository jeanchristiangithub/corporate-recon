<?php

declare(strict_types=1);

require_once __DIR__ . '/daycard-locks-common.php';

reconDaycardLocksBoot();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$partner = reconDaycardLocksNormalizePartner((string) ($_GET['partnername'] ?? ($_GET['partner'] ?? '')));
$transactionDate = reconDaycardLocksNormalizeDate((string) ($_GET['transaction_date'] ?? ($_GET['date'] ?? '')));

if ($partner === '' || $transactionDate === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing partnername or transaction_date']);
    exit;
}

$wicPartners = ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS'];
$mbtcPartners = ['MBTC', 'METROBANK HEAD OFFICE'];
if ($partner !== 'MONEYGRAM' && !in_array($partner, $wicPartners, true) && !in_array($partner, $mbtcPartners, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reconciliation details are currently available for MONEYGRAM, WORLDCOM INTERNATIONAL COMMUNICATIONS, and METROBANK HEAD OFFICE only.']);
    exit;
}

if ($partner === 'MONEYGRAM') {
    $_GET = ['start_date' => $transactionDate, 'end_date' => $transactionDate, 'partnerName' => $partner, 'detail' => '1', 'range_detail' => '1'];
    require __DIR__ . '/moneygram-recon.php';
    exit;
}

if (in_array($partner, $mbtcPartners, true)) {
    $_GET = ['start_date' => $transactionDate, 'end_date' => $transactionDate, 'date' => $transactionDate, 'day' => (string) ((int) substr($transactionDate, 8, 2)), 'partnerName' => $partner, 'detail' => '1'];
    require __DIR__ . '/mbtc-recon.php';
    exit;
}

$dateParts = explode('-', $transactionDate);
$_GET = ['month' => $dateParts[1] ?? '', 'year' => $dateParts[0] ?? '', 'day' => (string) ((int) ($dateParts[2] ?? 0)), 'partnerName' => $partner, 'detail' => '1'];
require __DIR__ . '/wic-recon.php';
