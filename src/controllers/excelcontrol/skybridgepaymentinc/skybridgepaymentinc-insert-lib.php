<?php
// skybridgepaymentinc-insert-lib.php
// Reusable insertion helper for SkyBridgePaymentInc web and partner payloads

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/skybridgepaymentinc-helper.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SkyBridgePaymentIncInsert {
    private $pdo;

    public function __construct(){
        if($this->pdo instanceof PDO) return;
        $this->pdo = fileRecDbConnection();
    }

    public function insertWebData(string $company, array $payloads): array{
        $pdo = $this->pdo;
        $pdo->beginTransaction();
        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO skybridgepaymentinc_web_data (partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
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
                $amount = skybridgepaymentinc_normalize_amount($r['AMOUNT'] ?? '');
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
        $colsRes = $pdo->query("SHOW COLUMNS FROM skybridgepaymentinc_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $existingCols = array_map(function($c){ return strtolower($c['Field']); }, $colsRes);

        $normalizeKey = function($value){
            $normalized = strtolower((string)$value);
            $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);
            return trim((string)$normalized, '_');
        };

        $getRaw = function(array $row, array $aliases, $default = null) use ($normalizeKey){
            foreach($aliases as $alias){
                if(array_key_exists($alias, $row) && $row[$alias] !== null && $row[$alias] !== '') return $row[$alias];
            }

            $normalizedRow = [];
            foreach($row as $key => $value){
                if($value === null || $value === '') continue;
                $normalizedKey = $normalizeKey($key);
                if($normalizedKey !== '' && !array_key_exists($normalizedKey, $normalizedRow)) $normalizedRow[$normalizedKey] = $value;
            }

            foreach($aliases as $alias){
                $normalizedAlias = $normalizeKey($alias);
                if($normalizedAlias !== '' && array_key_exists($normalizedAlias, $normalizedRow)) return $normalizedRow[$normalizedAlias];
            }

            return $default;
        };

        $normalizeDateOnly = function($raw){
            if($raw === null || $raw === '') return null;
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$raw)) return (string)$raw;
            $ts = strtotime((string)$raw);
            return $ts !== false ? date('Y-m-d', $ts) : null;
        };

        $extractDateTime = function(array $row, $dateFallback = '') use ($getRaw){
            $dateRaw = $getRaw($row, ['date', 'transaction_date', 'transaction date', 'date_claimed', 'date claimed', 'cover_date', 'cover date', 'payout_date', 'payout date'], $dateFallback);
            $timeRaw = $getRaw($row, ['time', 'transaction_time', 'transaction time', 'payout_time', 'payout time'], null);
            $normalized = skybridgepaymentinc_partner_parse_date_time($dateRaw, $timeRaw);
            if($normalized !== null){
                try{
                    $dtObj = new DateTime($normalized);
                    return [$dtObj->format('Y-m-d'), $dtObj->format('H:i:s')];
                }catch(Throwable $e){}
            }

            $date = null;
            $time = null;
            if($dateRaw !== null && $dateRaw !== ''){
                $ts = strtotime((string)$dateRaw);
                if($ts !== false) $date = date('Y-m-d', $ts);
            }
            if($timeRaw !== null && $timeRaw !== ''){
                $tTs = strtotime((string)$timeRaw);
                if($tTs !== false) $time = date('H:i:s', $tTs);
            }

            return [$date, $time];
        };

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
            foreach($rows as $r){
                if(!is_array($r)) continue;

                $insertCols = [];
                foreach($existingCols as $col){
                    if($col === 'id') continue;
                    $insertCols[] = $col;
                }
                if(empty($insertCols)) continue;

                $mappedValues = [];
                foreach($insertCols as $col){
                    switch($col){
                        case 'partnername':
                            $mappedValues[] = $getRaw($r, ['partnerName', 'partnername'], $company);
                            break;
                        case 'date':
                            $parts = $extractDateTime($r, $dateStr);
                            $mappedValues[] = $parts[0];
                            break;
                        case 'cover_date':
                            $mappedValues[] = $cover ?: $normalizeDateOnly($getRaw($r, ['cover_date', 'cover date', 'date', 'transaction_date', 'transaction date'], $dateStr));
                            break;
                        case 'time':
                            $parts = $extractDateTime($r, $dateStr);
                            $mappedValues[] = $parts[1];
                            break;
                        case 'reference_no':
                            $mappedValues[] = $getRaw($r, ['Reference No.', 'Reference No', 'reference_no', 'reference no', 'reference', 'ref_no', 'transaction_id', 'transaction id', 'control_series', 'control series', 'control_series_no'], '');
                            break;
                        case 'transaction_id':
                            $mappedValues[] = $getRaw($r, ['transaction_id', 'transaction id', 'Reference No.', 'Reference No', 'reference_no', 'ref_no'], '');
                            break;
                        case 'rts_tracer_no':
                            $mappedValues[] = $getRaw($r, ['RTS Tracer No.', 'RTS Tracer No', 'rts_tracer_no', 'rts tracer no', 'tracer_no', 'tracer no'], '');
                            break;
                        case 'provider':
                            $mappedValues[] = $getRaw($r, ['Provider', 'provider', 'principal', 'service_provider', 'service provider'], '');
                            break;
                        case 'beneficiary_name':
                            $mappedValues[] = $getRaw($r, ['Beneficiary Name', 'beneficiary_name', 'beneficiary name', 'beneficiary', 'receiver_name', 'receiver name', 'beneficiary_receiver'], '');
                            break;
                        case 'remitter_name':
                            $mappedValues[] = $getRaw($r, ['Remitter Name', 'remitter_name', 'remitter name', 'remitter', 'sender_name', 'sender name', 'sender'], '');
                            break;
                        case 'php':
                            $mappedValues[] = skybridgepaymentinc_partner_normalize_currency($getRaw($r, ['PHP', 'php', 'payout_amount_php', 'payout amount php', 'amount', 'Amount']));
                            break;
                        case 'usd':
                            $mappedValues[] = skybridgepaymentinc_partner_normalize_currency($getRaw($r, ['USD', 'usd', 'gross_usd', 'gross usd', 'foreign_amount', 'foreign amount']));
                            break;
                        case 'in_php':
                            $mappedValues[] = skybridgepaymentinc_partner_normalize_currency($getRaw($r, ['in PHP', 'in_php', 'commission', 'Commission', 'service_fee', 'service fee', 'charge', 'charges']));
                            break;
                        case 'amount':
                            $mappedValues[] = skybridgepaymentinc_partner_normalize_currency($getRaw($r, ['amount', 'Amount', 'PHP', 'php', 'in PHP', 'in_php']));
                            break;
                        case 'coin':
                        case 'currency':
                            $mappedValues[] = $getRaw($r, ['coin', 'Coin', 'currency', 'Currency', 'currency_code', 'currency code', 'crc_code'], '');
                            break;
                        case 'created_at':
                        case 'updated_at':
                            $mappedValues[] = $now;
                            break;
                        default:
                            $mappedValues[] = $getRaw($r, [$col, strtoupper($col), ucwords(str_replace('_', ' ', $col))], null);
                            break;
                    }
                }

                $quotedCols = array_map(function($col){ return '`' . $col . '`'; }, $insertCols);
                $insertSql = 'INSERT INTO skybridgepaymentinc_partner_data (' . implode(',', $quotedCols) . ') VALUES (' . rtrim(str_repeat('?,', count($insertCols)), ',') . ')';
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($mappedValues);
                $inserted += $stmt->rowCount();
            }
        }

        $pdo->commit();
        return ['success'=>true,'inserted'=>$inserted];
    }
}
