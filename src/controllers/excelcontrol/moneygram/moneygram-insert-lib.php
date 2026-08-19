<?php
// moneygram-insert-lib.php
// Reusable insertion helper for MONEYGRAM web and partner payloads

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../partner-upload-user.php';
require_once __DIR__ . '/moneygram-helper.php';
require_once __DIR__ . '/moneygram-partner-match.php';
require_once __DIR__ . '/../../recon/daycard-locks-common.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MoneygramInsert {
    private $pdo;

    private static array $branchLookupCache = [];

    private static function normalizeReferenceId($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', '', $value);

        return trim((string) $value);
    }

    private function lookupBranchIdByReferenceId(string $referenceId): string
    {
        $referenceId = self::normalizeReferenceId($referenceId);
        if ($referenceId === '') {
            return '';
        }

        if (array_key_exists($referenceId, self::$branchLookupCache)) {
            return (string) self::$branchLookupCache[$referenceId];
        }

        $searchSql = [
            'SELECT branch_id FROM ml_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" ORDER BY id DESC LIMIT 1',
            'SELECT branch_id FROM ml_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" ORDER BY date_send DESC LIMIT 1',
            'SELECT branch_id FROM ml_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" ORDER BY date_claimed DESC LIMIT 1',
            'SELECT branch_id FROM ml_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" LIMIT 1',
            'SELECT branch_id FROM moneygram_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" ORDER BY id DESC LIMIT 1',
            'SELECT branch_id FROM moneygram_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" ORDER BY date_claimed DESC LIMIT 1',
            'SELECT branch_id FROM moneygram_web_data WHERE ccref_no = ? AND branch_id IS NOT NULL AND TRIM(branch_id) <> "" LIMIT 1',
        ];

        $branchId = '';
        foreach ($searchSql as $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$referenceId]);
                $candidate = trim((string) $stmt->fetchColumn());
                if ($candidate !== '') {
                    $branchId = $candidate;
                    break;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        self::$branchLookupCache[$referenceId] = $branchId;

        return $branchId;
    }

    private function prefetchBranchIdsByReferenceIds(array $referenceIds): void
    {
        $pending = [];
        foreach ($referenceIds as $referenceId) {
            $referenceId = self::normalizeReferenceId($referenceId);
            if ($referenceId !== '' && !array_key_exists($referenceId, self::$branchLookupCache)) {
                $pending[$referenceId] = true;
            }
        }

        if (empty($pending)) {
            return;
        }

        foreach (array_keys($pending) as $referenceId) {
            self::$branchLookupCache[$referenceId] = '';
        }

        $lookups = [
            ['table' => 'ml_web_data', 'order' => 'id'],
            ['table' => 'ml_web_data', 'order' => 'date_send'],
            ['table' => 'ml_web_data', 'order' => 'date_claimed'],
            ['table' => 'ml_web_data', 'order' => null],
            ['table' => 'moneygram_web_data', 'order' => 'id'],
            ['table' => 'moneygram_web_data', 'order' => 'date_claimed'],
            ['table' => 'moneygram_web_data', 'order' => null],
        ];

        foreach ($lookups as $lookup) {
            $remaining = array_keys(array_filter($pending, function ($unused, $referenceId) {
                return self::$branchLookupCache[$referenceId] === '';
            }, ARRAY_FILTER_USE_BOTH));

            if (empty($remaining)) {
                break;
            }

            foreach (array_chunk($remaining, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $table = $lookup['table'];
                $order = $lookup['order'];

                try {
                    if ($order !== null) {
                        $sql = "SELECT src.ccref_no, src.branch_id
                                FROM {$table} src
                                INNER JOIN (
                                    SELECT ccref_no, MAX({$order}) AS max_order
                                    FROM {$table}
                                    WHERE ccref_no IN ({$placeholders})
                                      AND branch_id IS NOT NULL
                                      AND TRIM(branch_id) <> ''
                                    GROUP BY ccref_no
                                ) picked
                                  ON picked.ccref_no = src.ccref_no
                                 AND picked.max_order = src.{$order}
                                WHERE src.branch_id IS NOT NULL
                                  AND TRIM(src.branch_id) <> ''";
                    } else {
                        $sql = "SELECT ccref_no, branch_id
                                FROM {$table}
                                WHERE ccref_no IN ({$placeholders})
                                  AND branch_id IS NOT NULL
                                  AND TRIM(branch_id) <> ''
                                GROUP BY ccref_no, branch_id";
                    }

                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($chunk);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $referenceId = self::normalizeReferenceId((string) ($row['ccref_no'] ?? ''));
                        $branchId = trim((string) ($row['branch_id'] ?? ''));
                        if ($referenceId !== '' && $branchId !== '' && self::$branchLookupCache[$referenceId] === '') {
                            self::$branchLookupCache[$referenceId] = $branchId;
                        }
                    }
                } catch (Throwable $e) {
                    continue;
                }
            }
        }
    }

    private static function pickPartnerRowValue(array $row, array $aliases, $fallback = null)
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $k = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $key))));
                $normalized[$k] = $value;
            }
        }

        foreach ($aliases as $alias) {
            $a = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $alias))));
            if (array_key_exists($a, $normalized)) {
                return $normalized[$a];
            }
        }

        return $fallback;
    }

    public function precheckPartnerLegacyIds(array $payloads): array
    {
        $referenceIds = [];
        $branchIds = [];

        foreach ($payloads as $pl) {
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }

                $branchId = trim((string) self::pickPartnerRowValue($r, ['branch_id', 'branch id'], ''));
                if ($branchId !== '') {
                    $branchIds[$branchId] = true;
                    continue;
                }

                $referenceId = self::normalizeReferenceId((string) self::pickPartnerRowValue($r, ['reference_id', 'reference id', 'reference_no', 'reference no', 'reference'], ''));
                if ($referenceId !== '') {
                    $referenceIds[$referenceId] = true;
                }
            }
        }

        $this->prefetchBranchIdsByReferenceIds(array_keys($referenceIds));
        foreach (array_keys($referenceIds) as $referenceId) {
            $branchId = $this->lookupBranchIdByReferenceId($referenceId);
            if ($branchId !== '') {
                $branchIds[$branchId] = true;
            }
        }

        $branchIdList = array_values(array_filter(array_keys($branchIds), function ($branchId) {
            return trim((string) $branchId) !== '';
        }));

        if (empty($branchIdList)) {
            return [
                'success' => true,
                'has_missing_legacy' => false,
                'has_new_branch' => false,
                'missing_branch_ids' => [],
                'missing_branches' => [],
                'new_branch_ids' => [],
                'new_branches' => [],
                'checked_branch_ids' => [],
            ];
        }

        $profileByBranchId = [];
        $masterPdo = masterDataConnection();
        foreach (array_chunk($branchIdList, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $masterPdo->prepare("SELECT branch_id, branch_name, legacyid_moneygram FROM branch_profile WHERE branch_id IN ({$placeholders})");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $branchId = trim((string) ($row['branch_id'] ?? ''));
                if ($branchId === '') {
                    continue;
                }
                $profileByBranchId[$branchId] = [
                    'branch_name' => trim((string) ($row['branch_name'] ?? '')),
                    'legacyid_moneygram' => trim((string) ($row['legacyid_moneygram'] ?? '')),
                ];
            }
        }

        $missing = [];
        $missingBranches = [];
        $newBranchIds = [];
        $newBranches = [];
        foreach ($branchIdList as $branchId) {
            if (!array_key_exists($branchId, $profileByBranchId)) {
                $newBranchIds[] = $branchId;
                $newBranches[] = [
                    'branch_id' => $branchId,
                    'branch_name' => '',
                ];
                continue;
            }

            if ($profileByBranchId[$branchId]['legacyid_moneygram'] === '') {
                $missing[] = $branchId;
                $missingBranches[] = [
                    'branch_id' => $branchId,
                    'branch_name' => $profileByBranchId[$branchId]['branch_name'],
                ];
            }
        }

        return [
            'success' => true,
            'has_missing_legacy' => !empty($missing),
            'has_new_branch' => !empty($newBranchIds),
            'missing_branch_ids' => $missing,
            'missing_branches' => $missingBranches,
            'new_branch_ids' => $newBranchIds,
            'new_branches' => $newBranches,
            'checked_branch_ids' => $branchIdList,
        ];
    }

    public function __construct(){
        if($this->pdo instanceof PDO) return;
        // use shared fileRecDbConnection() helper from config/db.php
        $this->pdo = fileRecDbConnection();
    }

    public function insertWebData(string $company, array $payloads): array{
        $pdo = $this->pdo;
        $footerKeywords = ['TOTAL COUNT','TOTAL AMOUNT','TOTAL CHARGE','GRAND TOTAL','SUMMARY','SUBTOTAL'];

        $rowContainsFooter = static function(array $row, array $footerKeywords){
            foreach($row as $v){
                if($v === null || $v === '') continue;
                $up = strtoupper((string)$v);
                foreach($footerKeywords as $kw){ if(strpos($up, $kw) !== false) return true; }
            }
            return false;
        };

        $lockedDates = [];
        foreach($payloads as $pl){
            $dateStr = isset($pl['dateStr']) ? (string) $pl['dateStr'] : '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach($rows as $r){
                if(!is_array($r)) continue;
                if($rowContainsFooter($r, $footerKeywords)) continue;

                $rawDate = '';
                foreach(['DATE CLAIMED','DATE SEND','DATE','TRAN DATE','TRANSACTION DATE'] as $candidate){
                    if(isset($r[$candidate]) && trim((string)$r[$candidate]) !== ''){
                        $rawDate = $r[$candidate];
                        break;
                    }
                }
                if($rawDate === '') $rawDate = $dateStr;

                $normalized = moneygram_parse_date_claimed($rawDate);
                $dateOnly = reconDaycardLocksNormalizeDate((string) ($normalized !== null ? $normalized : $rawDate));
                if($dateOnly !== ''){
                    $lockedDates[$dateOnly] = true;
                }
            }
        }

        $blockedDates = reconDaycardLocksFindLockedDates($pdo, $company, array_keys($lockedDates));
        if(!empty($blockedDates)){
            return [
                'success' => false,
                'error' => reconDaycardLocksFormatBlockedUploadMessage($company, $blockedDates),
                'errorCode' => 'daycard_locked',
                'blocked_dates' => $blockedDates,
                'inserted' => 0,
            ];
        }

        $pdo->beginTransaction();
        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO moneygram_web_data (partnerName, `no`, control_series_no, date_claimed, kptn, ccref_no, currency, amount, ctc, ctp, sender_name, sender_country, beneficiary_receiver, receiver_kyc, receiver_phone, operator, branch, remote_operator, remote_branch, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $stmt = $pdo->prepare($sql);

        foreach($payloads as $pl){
            $filename = isset($pl['filename']) ? $pl['filename'] : '';
            $dateStr = isset($pl['dateStr']) ? $pl['dateStr'] : '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach($rows as $r){
                if(!is_array($r)) continue;
                if($rowContainsFooter($r, $footerKeywords)) continue;

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
                $amount = moneygram_normalize_amount($r['AMOUNT'] ?? '');
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

    public function insertPartnerData(string $company, array $payloads, string $partnerId = '', array $duplicatePairs = []): array{
        $pdo = $this->pdo;
        $uploadedBy = partnerUploadAuthenticatedIdNumber($pdo);
        $partnerId = trim($partnerId);
        if($partnerId === ''){
            throw new RuntimeException('MoneyGram Partner ID is required.');
        }
        $partnerStmt = masterDataConnection()->prepare(
            'SELECT partner_name FROM corpo_partner_masterfile WHERE partner_id = ? LIMIT 1'
        );
        $partnerStmt->execute([$partnerId]);
        $verifiedPartnerName = trim((string)($partnerStmt->fetchColumn() ?: ''));
        if(strtoupper($verifiedPartnerName) !== 'MONEYGRAM' || strtoupper(trim($company)) !== 'MONEYGRAM'){
            throw new RuntimeException('The selected Partner ID does not belong to MoneyGram.');
        }
        $now = date('Y-m-d H:i:s');
        $inserted = 0;
        $errorDetails = [];
        $rowsToInsert = [];

        $footerKeywords = ['TOTAL COUNT','TOTAL AMOUNT','TOTAL CHARGE','GRAND TOTAL','SUMMARY','SUBTOTAL'];
        $rowContainsFooter = static function(array $row, array $footerKeywords){
            foreach($row as $v){
                if($v === null || $v === '') continue;
                $up = strtoupper((string)$v);
                foreach($footerKeywords as $kw){
                    if(strpos($up, $kw) !== false) return true;
                }
            }
            return false;
        };

        $columnsInfo = $pdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_ASSOC);
        $availableColumns = [];
        foreach($columnsInfo as $info){
            $availableColumns[strtolower((string)$info['Field'])] = (string)$info['Field'];
        }

        $targetColumns = [
            'account_number','agent_name','legacy_id','tran_date','transaction_id','reference_id','branch_id','product','tran_type',
            'orig_cntry','rcv_cntry','fx_rate_trn','fx_date_trn','margin','base_tran_amt','fee_tran_amt',
            'fx_rev_share_tran_amt','comm_tran_amt','total_tran_amt','settlement_currency','transaction_currency',
            'tran_fx_rate','fx_rev_share_amt','base_amt','comm_amt','match_status','is_data_locked'
        ];

        $numericColumns = [
            'fx_rate_trn','margin','base_tran_amt','fee_tran_amt','fx_rev_share_tran_amt','comm_tran_amt','total_tran_amt',
            'tran_fx_rate','fx_rev_share_amt','base_amt','comm_amt'
        ];

        $dateColumns = ['tran_date','fx_date_trn'];

        $pickValue = function(array $row, array $aliases, $fallback = null){
            return self::pickPartnerRowValue($row, $aliases, $fallback);
        };

        $normalizeText = static function($value){
            if($value === null) return '';
            return trim((string)$value);
        };

        $normalizeNumeric = static function($value, bool &$ok){
            $ok = true;
            if($value === null) return 0;
            $v = trim((string)$value);
            if($v === '') return 0;

            $v = preg_replace('/[^0-9,\.\-() ]+/', '', $v);
            $neg = false;
            if(preg_match('/^\(.*\)$/', $v)){
                $neg = true;
                $v = trim($v, "() ");
            }
            if(strpos($v, ',') !== false && strpos($v, '.') !== false){
                $v = str_replace(',', '', $v);
            } elseif(strpos($v, ',') !== false) {
                $v = str_replace(',', '.', $v);
            }
            $v = str_replace(' ', '', $v);
            $v = preg_replace('/[^0-9.\-]/', '', $v);
            if($v === '' || !is_numeric($v)){
                $ok = false;
                return 0;
            }
            $num = (float)$v;
            if($neg) $num *= -1;
            return $num;
        };

        $normalizeDate = static function($value, bool &$ok){
            $ok = true;
            if($value === null) return null;
            $v = trim((string)$value);
            if($v === '') return null;

            if(is_numeric($v)){
                try{
                    $dt = ExcelDate::excelToDateTimeObject((float)$v);
                    return $dt->format('Y-m-d');
                } catch(Throwable $e){}
            }

            $ts = strtotime($v);
            if($ts !== false) return date('Y-m-d', $ts);

            $ok = false;
            return null;
        };

        $isBlankRow = static function(array $row){
            foreach($row as $v){
                if(trim((string)$v) !== '') return false;
            }
            return true;
        };

        $rowIndex = 0;
        foreach($payloads as $payloadIndex => $pl){
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            foreach($rows as $r){
                $rowIndex++;
                if(!is_array($r) || $isBlankRow($r)){
                    continue;
                }
                // skip footer/summary rows if present in payload
                if($rowContainsFooter($r, $footerKeywords)) continue;

                $mapped = [
                    'account_number' => $normalizeText($pickValue($r, ['account_number','account number','account no','account no.','acct no','acct no.'])),
                    'agent_name' => $normalizeText($pickValue($r, ['agent_name','agent name'])),
                    'legacy_id' => $normalizeText($pickValue($r, ['legacy_id','legacy id'])),
                    'tran_date' => $pickValue($r, ['tran_date','tran date','transaction_date','transaction date','date']),
                    'transaction_id' => $normalizeText($pickValue($r, ['transaction_id','transaction id','tran_id','tran id'])),
                    'reference_id' => $normalizeText($pickValue($r, ['reference_id','reference id','reference_no','reference no','reference'])),
                    'branch_id' => '',
                    'product' => $normalizeText($pickValue($r, ['product'])),
                    'tran_type' => $normalizeText($pickValue($r, ['tran_type','tran type','transaction_type','transaction type'])),
                    'orig_cntry' => $normalizeText($pickValue($r, ['orig_cntry','orig cntry','orig_country','origin country','origin'])),
                    'rcv_cntry' => $normalizeText($pickValue($r, ['rcv_cntry','rcv cntry','receive country','receiver country','receiving country'])),
                    'fx_rate_trn' => $pickValue($r, ['fx_rate_trn','fx rate trn','tran_fx_rate','tran fx rate','fx_rate','fx rate']),
                    'fx_date_trn' => $pickValue($r, ['fx_date_trn','fx date trn','fx_date','fx date']),
                    'margin' => $pickValue($r, ['margin']),
                    'base_tran_amt' => $pickValue($r, ['base_tran_amt','base tran amt','base_amt','base amt','base amount']),
                    'fee_tran_amt' => $pickValue($r, ['fee_tran_amt','fee tran amt','fee_amt','fee amt','fee amount']),
                    'fx_rev_share_tran_amt' => $pickValue($r, ['fx_rev_share_tran_amt','fx rev share tran amt','fx_rev_share_amt','fx rev share amt','fx rev share amount']),
                    'comm_tran_amt' => $pickValue($r, ['comm_tran_amt','comm tran amt','comm_amt','comm amt','commission amount','commission']),
                    'total_tran_amt' => $pickValue($r, ['total_tran_amt','total tran amt','total amount']),
                    'settlement_currency' => $normalizeText($pickValue($r, ['settlement_currency','settlement currency'])),
                    'transaction_currency' => $normalizeText($pickValue($r, ['transaction_currency','transaction currency','currency'])),
                    'tran_fx_rate' => $pickValue($r, ['tran_fx_rate','tran fx rate','fx_rate_trn','fx rate trn','fx_rate','fx rate']),
                    'fx_rev_share_amt' => $pickValue($r, ['fx_rev_share_amt','fx rev share amt','fx_rev_share_tran_amt','fx rev share tran amt','fx rev share amount']),
                    'base_amt' => $pickValue($r, ['base_amt','base amt','base_tran_amt','base tran amt','base amount']),
                    'comm_amt' => $pickValue($r, ['comm_amt','comm amt','comm_tran_amt','comm tran amt','commission amount','commission'])
                ];
                $mapped['_payload_index'] = $payloadIndex;

                $rowErrors = [];

                foreach($dateColumns as $col){
                    $okDate = true;
                    $mapped[$col] = $normalizeDate($mapped[$col], $okDate);
                    if(!$okDate){
                        $rowErrors[] = ['row'=>$rowIndex, 'reason'=>'Invalid date for ' . $col];
                    }
                }

                foreach($numericColumns as $col){
                    $okNum = true;
                    $mapped[$col] = $normalizeNumeric($mapped[$col], $okNum);
                    if(!$okNum){
                        $rowErrors[] = ['row'=>$rowIndex, 'reason'=>'Invalid numeric value for ' . $col];
                    }
                }

                if(!empty($rowErrors)){
                    $errorDetails = array_merge($errorDetails, $rowErrors);
                    continue;
                }

                $rowsToInsert[] = $mapped;
            }
        }

        if(!empty($errorDetails)){
            return [
                'success' => false,
                'error' => 'Validation failed for ' . count($errorDetails) . ' row(s).',
                'inserted' => 0,
                'error_count' => count($errorDetails),
                'error_details' => array_slice($errorDetails, 0, 50)
            ];
        }

        if(empty($rowsToInsert)){
            return ['success'=>true,'inserted'=>0,'skipped_blank_rows'=>true];
        }

        $referenceIds = [];
        foreach($rowsToInsert as $mapped){
            $referenceIds[] = (string) ($mapped['reference_id'] ?? '');
        }
        $this->prefetchBranchIdsByReferenceIds($referenceIds);
        foreach($rowsToInsert as &$mapped){
            $mapped['branch_id'] = $this->lookupBranchIdByReferenceId((string) ($mapped['reference_id'] ?? ''));
        }
        unset($mapped);

        $lockedDates = [];
        foreach($rowsToInsert as $mapped){
            $dateOnly = reconDaycardLocksNormalizeDate((string) ($mapped['tran_date'] ?? ''));
            if($dateOnly !== ''){
                $lockedDates[$dateOnly] = true;
            }
        }

        $blockedDates = reconDaycardLocksFindLockedDates($pdo, $company, array_keys($lockedDates));
        if(!empty($blockedDates)){
            return [
                'success' => false,
                'error' => reconDaycardLocksFormatBlockedUploadMessage($company, $blockedDates),
                'errorCode' => 'daycard_locked',
                'blocked_dates' => $blockedDates,
                'inserted' => 0,
            ];
        }

        $insertColumns = [];
        foreach($targetColumns as $col){
            if(isset($availableColumns[$col])) $insertColumns[] = $availableColumns[$col];
        }
        if(isset($availableColumns['partnername'])) $insertColumns[] = $availableColumns['partnername'];
        if(isset($availableColumns['created_at'])) $insertColumns[] = $availableColumns['created_at'];
        if(isset($availableColumns['updated_at'])) $insertColumns[] = $availableColumns['updated_at'];
        if(isset($availableColumns['uploaded_by'])) $insertColumns[] = $availableColumns['uploaded_by'];
        if(isset($availableColumns['ufl_file_log_id'])) $insertColumns[] = $availableColumns['ufl_file_log_id'];

        if(empty($insertColumns)){
            return ['success'=>false,'error'=>'No compatible columns found in moneygram_partner_data','inserted'=>0];
        }

        $quotedCols = array_map(function($c){ return '`' . str_replace('`', '``', $c) . '`'; }, $insertColumns);
        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $sql = 'INSERT INTO moneygram_partner_data (' . implode(',', $quotedCols) . ') VALUES (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        try{
            $matchedWebIds = moneygramClassifyPartnerUploadRows($pdo, $rowsToInsert, $duplicatePairs);
            moneygramPromoteMatchedWebRows($pdo, $matchedWebIds);
            $matchedTypesByDate = [];
            foreach($rowsToInsert as $matchedRow){
                if((int)($matchedRow['match_status'] ?? 0) !== 1) continue;
                $matchedDate = moneygramPartnerMatchDate($matchedRow['tran_date'] ?? '');
                $matchedType = strtoupper(trim((string)($matchedRow['tran_type'] ?? '')));
                if($matchedDate === '') continue;
                if($matchedType === 'REC') $matchedTypesByDate[$matchedDate]['payout'] = true;
                elseif($matchedType === 'SEN') $matchedTypesByDate[$matchedDate]['sendout'] = true;
                elseif($matchedType === 'RRC') $matchedTypesByDate[$matchedDate]['payout_cancelled'] = true;
                elseif($matchedType === 'RSN' || $matchedType === 'REF') $matchedTypesByDate[$matchedDate]['sendout_cancelled'] = true;
            }
            $matchedLockDates = [];
            $requiredMatchedTypes = ['payout', 'sendout', 'payout_cancelled', 'sendout_cancelled'];
            foreach($matchedTypesByDate as $matchedDate => $matchedTypes){
                $hasAllMatchedTypes = true;
                foreach($requiredMatchedTypes as $requiredMatchedType){
                    if(empty($matchedTypes[$requiredMatchedType])){
                        $hasAllMatchedTypes = false;
                        break;
                    }
                }
                if($hasAllMatchedTypes) $matchedLockDates[] = $matchedDate;
            }
            moneygramUpsertMatchedLockDates($pdo, $matchedLockDates, $uploadedBy);
            $fileLogIds = [];
            $logStmt = $pdo->prepare(
                'INSERT INTO uploaded_file_logs '
                . '(uploaded_date, filename, filename_ext, partner_id, partner_name, uploaded_by, has_overwrite, kpxweb_data_status) '
                . 'VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)'
            );
            $findLogStmt = $pdo->prepare(
                'SELECT id FROM uploaded_file_logs '
                . 'WHERE filename = ? AND partner_id = ? '
                . 'ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $markOverwriteStmt = $pdo->prepare(
                "UPDATE uploaded_file_logs SET has_overwrite = '1', uploaded_date = NOW() WHERE id = ?"
            );
            foreach($payloads as $payloadIndex => $payload){
                $originalFilename = trim(basename((string)($payload['filename'] ?? '')));
                $filename = trim((string)pathinfo($originalFilename, PATHINFO_FILENAME));
                $filenameExt = strtolower(trim((string)pathinfo($originalFilename, PATHINFO_EXTENSION)));
                if($filename === '' || $filenameExt === ''){
                    throw new RuntimeException('The MoneyGram uploaded filename or extension is missing.');
                }

                $findLogStmt->execute([$filename, $partnerId]);
                $existingFileLogId = $findLogStmt->fetchColumn();
                if($existingFileLogId !== false){
                    $fileLogIds[$payloadIndex] = (int)$existingFileLogId;
                    $markOverwriteStmt->execute([$fileLogIds[$payloadIndex]]);
                } else {
                    $logStmt->execute([
                        $filename,
                        $filenameExt,
                        $partnerId,
                        trim($company),
                        $uploadedBy,
                        '0',
                        'TD',
                    ]);
                    $fileLogIds[$payloadIndex] = (int)$pdo->lastInsertId();
                }
                if($fileLogIds[$payloadIndex] <= 0){
                    throw new RuntimeException('Unable to create the MoneyGram uploaded file log.');
                }
            }

            foreach($rowsToInsert as $mapped){
                $values = [];
                foreach($insertColumns as $col){
                    $colLower = strtolower($col);
                    if(array_key_exists($colLower, $mapped)){
                        $values[] = $mapped[$colLower];
                    } elseif($colLower === 'partnername'){
                        $values[] = trim($company);
                    } elseif($colLower === 'created_at' || $colLower === 'updated_at'){
                        $values[] = $now;
                    } elseif($colLower === 'uploaded_by'){
                        $values[] = $uploadedBy;
                    } elseif($colLower === 'ufl_file_log_id'){
                        $values[] = $fileLogIds[$mapped['_payload_index']] ?? null;
                    } else {
                        $values[] = null;
                    }
                }

                $stmt->execute($values);
                $inserted += $stmt->rowCount();
            }

            $pdo->commit();
            return [
                'success'=>true,
                'inserted'=>$inserted,
                'file_log_ids'=>array_values($fileLogIds),
                'error_count'=>0,
                'error_details'=>[]
            ];
        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            return [
                'success'=>false,
                'error'=>'Insert failed and transaction was rolled back: ' . $e->getMessage(),
                'inserted'=>0,
                'error_count'=>1,
                'error_details'=>[['row'=>null,'reason'=>$e->getMessage()]]
            ];
        }
    }
}
