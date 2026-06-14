<?php
// wic-insert-lib.php
// Reusable insertion helper for WIC web and partner payloads

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/wic-helper.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class WicInsert {
    private $pdo;

    public function __construct(){
        if($this->pdo instanceof PDO) return;
        // use shared fileRecDbConnection() helper from config/db.php
        $this->pdo = fileRecDbConnection();
    }

    public function insertWebData(string $company, array $payloads): array{
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO wic_web_data (partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = $pdo->prepare($sql);

        foreach($payloads as $pl){
            $filename = isset($pl['filename']) ? $pl['filename'] : '';
            $dateStr = isset($pl['dateStr']) ? $pl['dateStr'] : '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach($rows as $r){
                $no = $r['NO'] ?? '';
                $control = $r['CONTROL SERIES NO'] ?? '';
                $rawDate = isset($r['DATE CLAIMED']) ? $r['DATE CLAIMED'] : $dateStr;
                $date_claimed = '';
                if($rawDate !== null && $rawDate !== ''){
                    if(is_numeric($rawDate)){
                        try{ $dt = ExcelDate::excelToDateTimeObject((float)$rawDate); $date_claimed = $dt->format('Y-m-d H:i:s'); }catch(Throwable $e){ $ts = (int)$rawDate; if($ts>0) $date_claimed = date('Y-m-d H:i:s', $ts); }
                    } else {
                        $ts = strtotime((string)$rawDate);
                        if($ts !== false){ $date_claimed = date('Y-m-d H:i:s', $ts); }
                        else { $dt = DateTime::createFromFormat('F d, Y', (string)$rawDate); if($dt instanceof DateTime){ $date_claimed = $dt->format('Y-m-d H:i:s'); } else { $date_claimed = (string)$rawDate; } }
                    }
                } else { $date_claimed = ''; }

                $kptn = $r['KPTN'] ?? '';
                $ccref = $r['CCREF NO'] ?? '';
                $currency = $r['CURRENCY'] ?? '';
                $amount = wic_normalize_amount($r['AMOUNT'] ?? '');
                $ctc = $r['CTC'] ?? '';
                $ctp = $r['CTP'] ?? '';
                $sender = $r['SENDER NAME'] ?? '';
                $sender_country = $r['SENDER COUNTRY'] ?? '';
                $benef = $r['BENEFICIARY/RECEIVER'] ?? '';
                $receiver_kyc = $r['RECEIVER KYC'] ?? '';
                $receiver_phone = $r['RECEIVER PHONE'] ?? '';
                $operator = $r['OPERATOR'] ?? '';
                $branch = $r['BRANCH'] ?? '';
                $remote_operator = $r['REMOTE OPERATOR'] ?? '';
                $remote_branch = $r['REMOTE BRANCH'] ?? '';

                $stmt->execute([$company, $no, $control, $date_claimed, $kptn, $ccref, $currency, $amount, $ctc, $ctp, $sender, $sender_country, $benef, $receiver_kyc, $receiver_phone, $operator, $branch, $remote_operator, $remote_branch, $now, $now]);
                $inserted++;
            }
        }

        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted];
    }

    public function insertPartnerData(string $company, array $payloads): array{
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        // Detect table columns for flexible insertion. If the table uses the simplified
        // schema (date, transaction_id, amount, coin), insert accordingly. Otherwise
        // fall back to the legacy partner schema.
        $colsRes = $pdo->query("SHOW COLUMNS FROM wic_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $existingCols = array_map(function($c){ return strtolower($c['Field']); }, $colsRes);

        $isSimple = in_array('transaction_id', $existingCols) && in_array('amount', $existingCols) && in_array('coin', $existingCols);

        foreach($payloads as $pl){
            $coverRaw = isset($pl['coverDate']) ? $pl['coverDate'] : null;
            $cover = null;
            if($coverRaw !== null && $coverRaw !== ''){
                if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $coverRaw)){
                    $cover = $coverRaw;
                } else {
                    $ts = strtotime((string)$coverRaw);
                    if($ts !== false) $cover = date('Y-m-d', $ts);
                }
            }
            $dateStr = isset($pl['dateStr']) ? $pl['dateStr'] : '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];

            if($isSimple){
                // prepare statement for simple insert
                $insertSql = 'INSERT INTO wic_partner_data (';
                $insertCols = [];
                $insertCols[] = 'date';
                if(in_array('transaction_id', $existingCols)) $insertCols[] = 'transaction_id';
                if(in_array('amount', $existingCols)) $insertCols[] = 'amount';
                if(in_array('coin', $existingCols)) $insertCols[] = 'coin';
                if(in_array('created_at', $existingCols)) $insertCols[] = 'created_at';
                $insertSql .= implode(',', $insertCols) . ') VALUES (' . rtrim(str_repeat('?,', count($insertCols)), ',') . ')';
                $insStmt = $pdo->prepare($insertSql);

                foreach($rows as $r){
                    // map values from row - prefer lower-case keys provided by CSV parser
                    $dateRaw = $r['date'] ?? $r['Date'] ?? $dateStr;
                    $tx = $r['transaction_id'] ?? $r['Reference No.'] ?? $r['Transaction Id'] ?? $r['TransactionId'] ?? '';
                    $amtRaw = $r['amount'] ?? $r['in PHP'] ?? $r['PHP'] ?? '';
                    $amt = wic_normalize_amount($amtRaw);
                    $coin = $r['coin'] ?? '';
                    $dateVal = null;
                    if($dateRaw !== null && $dateRaw !== ''){
                        $ts = strtotime((string)$dateRaw);
                        if($ts !== false) $dateVal = date('Y-m-d', $ts);
                    }
                    $params = [];
                    $params[] = $dateVal;
                    if(in_array('transaction_id', $existingCols)) $params[] = $tx;
                    if(in_array('amount', $existingCols)) $params[] = $amt;
                    if(in_array('coin', $existingCols)) $params[] = $coin;
                    if(in_array('created_at', $existingCols)) $params[] = $now;
                    $insStmt->execute($params);
                    $inserted += $insStmt->rowCount();
                }
            } else {
                // legacy insert path
                $sql = 'INSERT INTO wic_partner_data (partnerName, `date`, cover_date, `time`, reference_no, rts_tracer_no, provider, beneficiary_name, remitter_name, `php`, `usd`, in_php, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = $pdo->prepare($sql);
                foreach($rows as $r){
                    $dateRaw = isset($r['Date']) ? $r['Date'] : $dateStr;
                    $timeRaw = isset($r['Time']) ? $r['Time'] : '';
                    $normalized = wic_partner_parse_date_time($dateRaw, $timeRaw);
                    if($normalized !== null){
                        try{ $dtObj = new DateTime($normalized); $date = $dtObj->format('Y-m-d'); $time = $dtObj->format('H:i:s'); }
                        catch(Throwable $e){ $date = null; $time = null; }
                    } else {
                        $date = null; $time = null;
                        $ts = strtotime((string)$dateRaw);
                        if($ts !== false){ $date = date('Y-m-d', $ts); }
                        if($timeRaw !== null && $timeRaw !== ''){ $tTs = strtotime((string)$timeRaw); if($tTs !== false) $time = date('H:i:s', $tTs); }
                    }
                    $reference = isset($r['Reference No.']) ? $r['Reference No.'] : '';
                    $rts = isset($r['RTS Tracer No.']) ? $r['RTS Tracer No.'] : '';
                    $provider = isset($r['Provider']) ? $r['Provider'] : '';
                    $beneficiary = isset($r['Beneficiary Name']) ? $r['Beneficiary Name'] : '';
                    $remitter = isset($r['Remitter Name']) ? $r['Remitter Name'] : '';
                    $phpRaw = isset($r['PHP']) ? $r['PHP'] : '';
                    $usdRaw = isset($r['USD']) ? $r['USD'] : '';
                    $inPhpRaw = isset($r['in PHP']) ? $r['in PHP'] : '';
                    $php = wic_partner_normalize_currency($phpRaw);
                    $usd = wic_partner_normalize_currency($usdRaw);
                    $in_php = wic_partner_normalize_currency($inPhpRaw);

                    $stmt->execute([$company, $date, $cover, $time, $reference, $rts, $provider, $beneficiary, $remitter, $php, $usd, $in_php, $now, $now]);
                    $inserted += $stmt->rowCount();
                }
            }
        }

        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted];
    }
}

