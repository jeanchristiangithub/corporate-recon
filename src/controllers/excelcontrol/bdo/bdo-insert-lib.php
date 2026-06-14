<?php
// bdo-insert-lib.php
// Reusable insertion helper for BDO web data payloads.
// Mirrors mbtc-insert-lib.php — adapted for BDO (BDO UNIBANK).
// Inserts into the partner-specific bdo_web_data table.

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/bdo-helper.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BdoInsert
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        // Shared fileRecDbConnection() from config/db.php
        $this->pdo = fileRecDbConnection();
    }

    /**
     * Insert extracted web-data rows into bdo_web_data.
     *
     * @param  string $company  Partner name stored in the partnerName column.
     * @param  array  $payloads Array of payload objects: { filename, dateStr, rows[] }
     * @return array            { success: bool, inserted: int }
     */
    public function insertWebData(string $company, array $payloads): array
    {
        $pdo      = $this->pdo;
        $inserted = 0;
        $now      = date('Y-m-d H:i:s');

        // Column order mirrors ml_web_data / mbtc_web_data for consistency
        $sql  = 'INSERT INTO bdo_web_data (
                    partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no,
                    currency, amount, ctc, ctp, sender_name, sender_country,
                    beneficiary_receiver, receiver_kyc, receiver_phone,
                    operator, branch, remote_operator, remote_branch,
                    created_at, updated_at
                 ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);

        foreach ($payloads as $pl) {
            $dateStr = isset($pl['dateStr']) ? $pl['dateStr'] : '';
            $rows    = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];

            foreach ($rows as $r) {
                // Normalize DATE CLAIMED: prefer row-level value, fall back to file-level dateStr
                $rawDate = isset($r['DATE CLAIMED']) && $r['DATE CLAIMED'] !== '' ? $r['DATE CLAIMED'] : $dateStr;
                $date_claimed = '';
                if ($rawDate !== null && $rawDate !== '') {
                    if (is_numeric($rawDate)) {
                        try {
                            $dt = ExcelDate::excelToDateTimeObject((float) $rawDate);
                            $date_claimed = $dt->format('Y-m-d H:i:s');
                        } catch (Throwable $e) {
                            $ts = (int) $rawDate;
                            if ($ts > 0) $date_claimed = date('Y-m-d H:i:s', $ts);
                        }
                    } else {
                        $ts = strtotime((string) $rawDate);
                        if ($ts !== false) {
                            $date_claimed = date('Y-m-d H:i:s', $ts);
                        } else {
                            $dt = DateTime::createFromFormat('F d, Y', (string) $rawDate);
                            if ($dt instanceof DateTime) {
                                $date_claimed = $dt->format('Y-m-d H:i:s');
                            } else {
                                $date_claimed = (string) $rawDate;
                            }
                        }
                    }
                }

                $stmt->execute([
                    $company,
                    $r['NO']                    ?? '',
                    $r['CONTROL SERIES NO']     ?? '',
                    $date_claimed,
                    $r['KPTN']                  ?? '',
                    $r['CCREF NO']              ?? '',
                    $r['CURRENCY']              ?? '',
                    bdo_normalize_amount($r['AMOUNT'] ?? ''),
                    $r['CTC']                   ?? '',
                    $r['CTP']                   ?? '',
                    $r['SENDER NAME']           ?? '',
                    $r['SENDER COUNTRY']        ?? '',
                    $r['BENEFICIARY/RECEIVER']  ?? '',
                    $r['RECEIVER KYC']          ?? '',
                    $r['RECEIVER PHONE']        ?? '',
                    $r['OPERATOR']              ?? '',
                    $r['BRANCH']                ?? '',
                    $r['REMOTE OPERATOR']       ?? '',
                    $r['REMOTE BRANCH']         ?? '',
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
