<?php
// rcbc-insert-lib.php
// Reusable insertion helper for RCBC web and partner payloads

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/rcbc-helper.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RcbcInsert {
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
        $sql = 'INSERT INTO rcbc_web_data (partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
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
                $amount = rcbc_normalize_amount($r['AMOUNT'] ?? '');
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
        // Detect table columns for flexible insertion.
        $colsRes = $pdo->query("SHOW COLUMNS FROM rcbc_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $existingCols = array_map(function($c){ return strtolower($c['Field']); }, $colsRes);

        $isLegacy = in_array('reference_no', $existingCols) && in_array('php', $existingCols) && in_array('in_php', $existingCols);

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

            if(!$isLegacy){
                $getRaw = function(array $r, array $keys, $default = null){
                    foreach($keys as $k){
                        if(array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') return $r[$k];
                    }
                    return $default;
                };

                $normalizeDate = function($raw) use ($dateStr){
                    $v = ($raw === null || $raw === '') ? $dateStr : $raw;
                    if($v === null || $v === '') return null;
                    $ts = strtotime((string)$v);
                    return $ts !== false ? date('Y-m-d', $ts) : null;
                };

                $mapValue = function(string $col, array $r) use ($getRaw, $normalizeDate, $now, $company){
                    switch($col){
                        case 'payout_id': return $getRaw($r, ['payout_id','transaction_id','Transaction Id','TransactionId','Reference No.','reference_no','ref_no'], '');
                        case 'transaction_id': return $getRaw($r, ['transaction_id','payout_id','Transaction Id','TransactionId','Reference No.','reference_no','ref_no'], '');
                        case 'ref_no': return $getRaw($r, ['ref_no','reference_no','Reference No.','transaction_id','payout_id'], '');
                        case 'reference_no': return $getRaw($r, ['reference_no','Reference No.','ref_no','transaction_id','payout_id'], '');
                        case 'date': return $normalizeDate($getRaw($r, ['date','Date']));
                        case 'cover_date': return $normalizeDate($getRaw($r, ['cover_date','Cover Date']));
                        case 'time':
                            $t = $getRaw($r, ['time','Time'], '');
                            if($t === '') return null;
                            $ts = strtotime((string)$t);
                            return $ts !== false ? date('H:i:s', $ts) : null;
                        case 'amount': return rcbc_normalize_amount($getRaw($r, ['amount','AMOUNT','in PHP','PHP','bene_amt','total payable','total_payable']));
                        case 'bene_amt': return rcbc_normalize_amount($getRaw($r, ['bene_amt','amount','AMOUNT','in PHP','PHP']));
                        case 'bene_proceed': return rcbc_normalize_amount($getRaw($r, ['bene_proceed','bene proceed','amount','AMOUNT']));
                        case 'payout_fee': return rcbc_normalize_amount($getRaw($r, ['payout_fee','payout fee']));
                        case 'total_payable': return rcbc_normalize_amount($getRaw($r, ['total_payable','total payable','amount','AMOUNT']));
                        case 'php': return rcbc_partner_normalize_currency($getRaw($r, ['PHP','php','amount','in PHP']));
                        case 'usd': return rcbc_partner_normalize_currency($getRaw($r, ['USD','usd']));
                        case 'in_php': return rcbc_partner_normalize_currency($getRaw($r, ['in PHP','in_php','amount','PHP']));
                        case 'coin': return $getRaw($r, ['coin','Coin','currency','CURRENCY','crc_code'], '');
                        case 'currency': return $getRaw($r, ['currency','CURRENCY','coin','Coin','crc_code'], '');
                        case 'partnername': return $getRaw($r, ['partnerName','partnername'], $company);
                        case 'created_at': return $now;
                        case 'updated_at': return $now;
                        default:
                            return $getRaw($r, [$col, strtoupper($col), ucwords(str_replace('_',' ', $col))], null);
                    }
                };

                foreach($rows as $r){
                    if(!is_array($r)) continue;
                    $insertCols = [];
                    foreach($existingCols as $col){
                        if(in_array($col, ['id'], true)) continue;
                        $insertCols[] = $col;
                    }
                    if(empty($insertCols)) continue;

                    $params = [];
                    foreach($insertCols as $col){
                        $params[] = $mapValue($col, $r);
                    }

                    $insertSql = 'INSERT INTO rcbc_partner_data (' . implode(',', $insertCols) . ') VALUES (' . rtrim(str_repeat('?,', count($insertCols)), ',') . ')';
                    $insStmt = $pdo->prepare($insertSql);
                    $insStmt->execute($params);
                    $inserted += $insStmt->rowCount();
                }
            } else {
                // legacy insert path
                $sql = 'INSERT INTO rcbc_partner_data (partnerName, `date`, cover_date, `time`, reference_no, rts_tracer_no, provider, beneficiary_name, remitter_name, `php`, `usd`, in_php, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = $pdo->prepare($sql);
                foreach($rows as $r){
                    $dateRaw = isset($r['Date']) ? $r['Date'] : $dateStr;
                    $timeRaw = isset($r['Time']) ? $r['Time'] : '';
                    $normalized = rcbc_partner_parse_date_time($dateRaw, $timeRaw);
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
                    $php = rcbc_partner_normalize_currency($phpRaw);
                    $usd = rcbc_partner_normalize_currency($usdRaw);
                    $in_php = rcbc_partner_normalize_currency($inPhpRaw);

                    $stmt->execute([$company, $date, $cover, $time, $reference, $rts, $provider, $beneficiary, $remitter, $php, $usd, $in_php, $now, $now]);
                    $inserted += $stmt->rowCount();
                }
            }
        }

        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted];
    }
}


