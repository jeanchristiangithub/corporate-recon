<?php
// eec-insert-lib.php

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/eec-helper.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EecInsert {
    private $pdo;

    public function __construct(){
        $this->pdo = fileRecDbConnection();
    }

    public function insertWebData(string $company, array $payloads): array{
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO eec_web_data (partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = $pdo->prepare($sql);
        foreach ($payloads as $pl) {
            $dateStr = $pl['dateStr'] ?? '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach ($rows as $r) {
                $rawDate = isset($r['DATE CLAIMED']) ? $r['DATE CLAIMED'] : $dateStr;
                $date_claimed = '';
                if ($rawDate !== null && $rawDate !== '') {
                    if (is_numeric($rawDate)) {
                        try { $dt = ExcelDate::excelToDateTimeObject((float) $rawDate); $date_claimed = $dt->format('Y-m-d H:i:s'); } catch (Throwable $e) {}
                    }
                    if ($date_claimed === '') {
                        $ts = strtotime((string) $rawDate);
                        if ($ts !== false) $date_claimed = date('Y-m-d H:i:s', $ts);
                        else $date_claimed = (string) $rawDate;
                    }
                }
                $stmt->execute([
                    $company,
                    $r['NO'] ?? '',
                    $r['CONTROL SERIES NO'] ?? '',
                    $date_claimed,
                    $r['KPTN'] ?? '',
                    $r['CCREF NO'] ?? '',
                    $r['CURRENCY'] ?? '',
                    eec_normalize_amount($r['AMOUNT'] ?? ''),
                    $r['CTC'] ?? '',
                    $r['CTP'] ?? '',
                    $r['SENDER NAME'] ?? '',
                    $r['SENDER COUNTRY'] ?? '',
                    $r['BENEFICIARY/RECEIVER'] ?? '',
                    $r['RECEIVER KYC'] ?? '',
                    $r['RECEIVER PHONE'] ?? '',
                    $r['OPERATOR'] ?? '',
                    $r['BRANCH'] ?? '',
                    $r['REMOTE OPERATOR'] ?? '',
                    $r['REMOTE BRANCH'] ?? '',
                    $now,
                    $now,
                ]);
                $inserted++;
            }
        }
        $pdo->commit();
        return ['success' => true, 'inserted' => $inserted];
    }
}
