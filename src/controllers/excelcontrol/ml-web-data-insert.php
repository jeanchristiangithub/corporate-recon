<?php
// ml-web-data-insert.php
// Consolidated handler for all corporate partners' web data uploads
// Stores all partner data in the unified ml_web_data table

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/ml-web-data-helper.php';
require_once __DIR__ . '/../recon/daycard-locks-common.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

header('Content-Type: application/json; charset=utf-8');

reconDaycardLocksBoot();

// read raw json
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

try {
    $action = isset($data['action']) ? $data['action'] : '';
    
    if ($action === 'check') {
        // Duplicate check against ml_web_data table
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) { 
            echo json_encode(['success' => true, 'duplicates' => []]); 
            exit; 
        }
        
        $pdo = fileRecDbConnection();
        $results = [];
        $seen = [];
        
        foreach ($pairs as $p) {
            $partnerName = isset($p['partnerName']) ? trim((string)$p['partnerName']) : '';
            $ccref = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
            $rawDate = isset($p['date_claimed']) ? $p['date_claimed'] : '';
            
            if ($ccref === '' || $partnerName === '') continue;
            
            // 1) Try exact normalized datetime match
            $norm = ml_parse_date_claimed($rawDate);
            if ($norm !== null) {
                $stmt = $pdo->prepare('SELECT partnerName, ccref_no, date_claimed, COUNT(*) as cnt FROM ml_web_data WHERE partnerName = ? AND ccref_no = ? AND date_claimed = ? GROUP BY partnerName, ccref_no, date_claimed');
                $stmt->execute([$partnerName, $ccref, $norm]);
                $r = $stmt->fetch();
                if ($r && isset($r['cnt']) && (int)$r['cnt'] > 0) {
                    $key = $r['partnerName'] . '|' . $r['ccref_no'] . '|' . $r['date_claimed'];
                    if (!isset($seen[$key])) { 
                        $seen[$key] = true; 
                        $results[] = ['partnerName' => $r['partnerName'], 'ccref_no' => $r['ccref_no'], 'date_claimed' => $r['date_claimed'], 'cnt' => (int)$r['cnt']]; 
                    }
                    continue;
                }
            }
            
            // 2) Try date-only match if rawDate is parseable
            $ts = strtotime((string)$rawDate);
            if ($ts !== false) {
                $dateOnly = date('Y-m-d', $ts);
                $stmt2 = $pdo->prepare('SELECT partnerName, ccref_no, date_claimed, COUNT(*) as cnt FROM ml_web_data WHERE partnerName = ? AND ccref_no = ? AND DATE(date_claimed) = ? GROUP BY partnerName, ccref_no, date_claimed');
                $stmt2->execute([$partnerName, $ccref, $dateOnly]);
                $r2 = $stmt2->fetchAll();
                foreach ($r2 as $ra) {
                    if (isset($ra['cnt']) && (int)$ra['cnt'] > 0) {
                        $key = $ra['partnerName'] . '|' . $ra['ccref_no'] . '|' . $ra['date_claimed'];
                        if (!isset($seen[$key])) { 
                            $seen[$key] = true; 
                            $results[] = ['partnerName' => $ra['partnerName'], 'ccref_no' => $ra['ccref_no'], 'date_claimed' => $ra['date_claimed'], 'cnt' => (int)$ra['cnt']]; 
                        }
                    }
                }
                if (!empty($r2)) continue;
            }
            
            // 3) Last resort: any rows matching partner + ccref_no
            $stmt3 = $pdo->prepare('SELECT partnerName, ccref_no, date_claimed, COUNT(*) as cnt FROM ml_web_data WHERE partnerName = ? AND ccref_no = ? GROUP BY partnerName, ccref_no, date_claimed');
            $stmt3->execute([$partnerName, $ccref]);
            $r3 = $stmt3->fetchAll();
            foreach ($r3 as $ra) {
                if (isset($ra['cnt']) && (int)$ra['cnt'] > 0) {
                    $key = $ra['partnerName'] . '|' . $ra['ccref_no'] . '|' . $ra['date_claimed'];
                    if (!isset($seen[$key])) { 
                        $seen[$key] = true; 
                        $results[] = ['partnerName' => $ra['partnerName'], 'ccref_no' => $ra['ccref_no'], 'date_claimed' => $ra['date_claimed'], 'cnt' => (int)$ra['cnt']]; 
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'duplicates' => $results]); 
        exit;
    }

    if ($action === 'check_sendout') {
        // Duplicate check against SENDOUT rows stored in ml_web_data
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) {
            echo json_encode(['success' => true, 'duplicates' => []]);
            exit;
        }

        $pdo = fileRecDbConnection();
        $sendoutColumns = [];
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $colInfo) {
                $field = strtolower((string)($colInfo['Field'] ?? ''));
                if ($field !== '') {
                    $sendoutColumns[$field] = true;
                }
            }
        } catch (Throwable $e) {
            $sendoutColumns = [];
        }

        if (!isset($sendoutColumns['date_send'])) {
            echo json_encode(['success' => true, 'duplicates' => []]);
            exit;
        }

        $results = [];
        $seen = [];

        foreach ($pairs as $p) {
            $ccref = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
            $rawDate = isset($p['date_send']) ? $p['date_send'] : '';
            $pairPartnerName = isset($p['partnerName']) ? trim((string)$p['partnerName']) : '';
            if ($ccref === '') continue;

            // 1) Try exact normalized datetime match
            $norm = ml_parse_date_claimed($rawDate);
            if ($norm !== null) {
                $sql = 'SELECT partnerName, ccref_no, date_send, COUNT(*) as cnt FROM ml_web_data WHERE ccref_no = ? AND date_send = ?';
                $params = [$ccref, $norm];
                if ($pairPartnerName !== '') {
                    $sql .= ' AND partnerName = ?';
                    $params[] = $pairPartnerName;
                }
                $sql .= ' GROUP BY partnerName, ccref_no, date_send';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $r = $stmt->fetch();
                if ($r && isset($r['cnt']) && (int)$r['cnt'] > 0) {
                    $key = ($r['partnerName'] ?? '') . '|' . $r['ccref_no'] . '|' . $r['date_send'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[] = ['partnerName' => $r['partnerName'] ?? $pairPartnerName, 'ccref_no' => $r['ccref_no'], 'date_send' => $r['date_send'], 'cnt' => (int)$r['cnt']];
                    }
                    continue;
                }
            }

            // 2) Try date-only match if rawDate is parseable
            $ts = strtotime((string)$rawDate);
            if ($ts !== false) {
                $dateOnly = date('Y-m-d', $ts);
                $sql = 'SELECT partnerName, ccref_no, date_send, COUNT(*) as cnt FROM ml_web_data WHERE ccref_no = ? AND DATE(date_send) = ?';
                $params = [$ccref, $dateOnly];
                if ($pairPartnerName !== '') {
                    $sql .= ' AND partnerName = ?';
                    $params[] = $pairPartnerName;
                }
                $sql .= ' GROUP BY partnerName, ccref_no, date_send';
                $stmt2 = $pdo->prepare($sql);
                $stmt2->execute($params);
                $r2 = $stmt2->fetchAll();
                foreach ($r2 as $ra) {
                    if (isset($ra['cnt']) && (int)$ra['cnt'] > 0) {
                        $key = ($ra['partnerName'] ?? '') . '|' . $ra['ccref_no'] . '|' . $ra['date_send'];
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $results[] = ['partnerName' => $ra['partnerName'] ?? $pairPartnerName, 'ccref_no' => $ra['ccref_no'], 'date_send' => $ra['date_send'], 'cnt' => (int)$ra['cnt']];
                        }
                    }
                }
                if (!empty($r2)) continue;
            }

            // 3) Last resort: any rows matching ccref_no
            $sql = 'SELECT partnerName, ccref_no, date_send, COUNT(*) as cnt FROM ml_web_data WHERE ccref_no = ?';
            $params = [$ccref];
            if ($pairPartnerName !== '') {
                $sql .= ' AND partnerName = ?';
                $params[] = $pairPartnerName;
            }
            $sql .= ' GROUP BY partnerName, ccref_no, date_send';
            $stmt3 = $pdo->prepare($sql);
            $stmt3->execute($params);
            $r3 = $stmt3->fetchAll();
            foreach ($r3 as $ra) {
                if (isset($ra['cnt']) && (int)$ra['cnt'] > 0) {
                    $key = ($ra['partnerName'] ?? '') . '|' . $ra['ccref_no'] . '|' . $ra['date_send'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[] = ['partnerName' => $ra['partnerName'] ?? $pairPartnerName, 'ccref_no' => $ra['ccref_no'], 'date_send' => $ra['date_send'], 'cnt' => (int)$ra['cnt']];
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'duplicates' => $results]);
        exit;
    }

    if ($action === 'delete_sendout') {
        // Delete duplicate SENDOUT rows from ml_web_data
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) {
            echo json_encode(['success' => true, 'deleted' => 0]);
            exit;
        }

        $pdo = fileRecDbConnection();
        $sendoutColumns = [];
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $colInfo) {
                $field = strtolower((string)($colInfo['Field'] ?? ''));
                if ($field !== '') {
                    $sendoutColumns[$field] = true;
                }
            }
        } catch (Throwable $e) {
            $sendoutColumns = [];
        }

        if (!isset($sendoutColumns['date_send'])) {
            echo json_encode(['success' => true, 'deleted' => 0]);
            exit;
        }

        $cnt = 0;

        foreach ($pairs as $p) {
            $ccref = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
            $dateSend = isset($p['date_send']) ? trim((string)$p['date_send']) : '';
            $pairPartnerName = isset($p['partnerName']) ? trim((string)$p['partnerName']) : '';
            if ($ccref === '' || $dateSend === '') {
                continue;
            }

            $sql = 'DELETE FROM ml_web_data WHERE ccref_no = ? AND date_send = ?';
            $params = [$ccref, $dateSend];
            if ($pairPartnerName !== '') {
                $sql .= ' AND partnerName = ?';
                $params[] = $pairPartnerName;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += (int)$stmt->rowCount();
        }

        echo json_encode(['success' => true, 'deleted' => $cnt]);
        exit;
    }
    
    if ($action === 'delete') {
        // Delete duplicates from ml_web_data
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) { 
            echo json_encode(['success' => true, 'deleted' => 0]); 
            exit; 
        }
        
        $pdo = fileRecDbConnection();
        $cnt = 0;
        
        foreach (array_chunk($pairs, 5000) as $chunk) {
            $place = [];
            $params = [];
            foreach ($chunk as $p) {
                $place[] = '(?,?,?)';
                $params[] = $p['partnerName'];
                $params[] = $p['ccref_no'];
                $params[] = $p['date_claimed'];
            }
            $sql = 'DELETE FROM ml_web_data WHERE (partnerName, ccref_no, date_claimed) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        
        echo json_encode(['success' => true, 'deleted' => $cnt]); 
        exit;
    }
    
    if ($action === 'insert_web') {
        // Insert all partner web data into unified ml_web_data table
        $company = isset($data['company']) ? $data['company'] : 'UNKNOWN';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $partnerName = trim((string)$company);
        $partnerUpper = strtoupper($partnerName);

        $normalizeHeader = static function(string $value): string {
            $value = strtolower(trim($value));
            $value = str_replace(['_', '-', '/'], ' ', $value);
            $value = preg_replace('/\s+/', ' ', $value);
            return trim($value);
        };

        $isBlankRow = static function(array $row): bool {
            foreach ($row as $v) {
                if (trim((string)$v) !== '') return false;
            }
            return true;
        };

        $footerKeywords = ['TOTAL COUNT','TOTAL AMOUNT','TOTAL CHARGE','GRAND TOTAL','SUMMARY','SUBTOTAL'];
        $rowContainsFooter = static function(array $row) use ($footerKeywords): bool {
            foreach ($row as $v) {
                if ($v === null || $v === '') continue;
                $up = strtoupper((string)$v);
                foreach ($footerKeywords as $kw) {
                    if (strpos($up, $kw) !== false) return true;
                }
            }
            return false;
        };

        $toMysqlDateTime = static function($raw): ?string {
            if ($raw === null || $raw === '') return null;
            $s = trim((string)$raw);
            if ($s === '') return null;

            if (preg_match('/^[0-9]+(\.[0-9]+)?$/', $s)) {
                $num = (float)$s;
                try {
                    if ($num > 0 && $num < 100000) {
                        $dt = ExcelDate::excelToDateTimeObject($num);
                        return $dt->format('Y-m-d H:i:s');
                    }
                } catch (Throwable $e) {}

                if ($num > 86400 && $num < 3000000000) {
                    return date('Y-m-d H:i:s', (int)$num);
                }
            }

            $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'm/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y', 'n/j/Y H:i:s', 'n/j/Y H:i', 'n/j/Y', 'm-d-Y H:i:s', 'm-d-Y H:i', 'm-d-Y', 'F d, Y H:i:s', 'F d, Y H:i', 'F d, Y'];
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, $s);
                if ($dt instanceof DateTime) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }

            $ts = strtotime($s);
            if ($ts !== false) return date('Y-m-d H:i:s', $ts);
            return null;
        };

        $detectSendoutPayload = static function(array $payload) use ($normalizeHeader): bool {
            $filename = strtoupper((string)($payload['filename'] ?? ''));
            $titleParts = [
                (string)($payload['title'] ?? ''),
                (string)($payload['reportTitle'] ?? ''),
                (string)($payload['report_title'] ?? ''),
                (string)($payload['sheetName'] ?? ''),
                (string)($payload['sheet_name'] ?? ''),
            ];
            $reportTitle = strtoupper(trim(implode(' ', array_filter($titleParts, static function($value) {
                return trim((string)$value) !== '';
            }))));

            $hasSendoutWord = (strpos($filename, 'SENDOUT') !== false || strpos($filename, 'SEND OUT') !== false || strpos($reportTitle, 'SENDOUT') !== false || strpos($reportTitle, 'SEND OUT') !== false);
            $hasPayoutWord = (strpos($filename, 'PAYOUT') !== false || strpos($filename, 'PAY OUT') !== false);
            $hasTransactionReportAll = (strpos($filename, 'TRANSACTION-REPORT') !== false || strpos($reportTitle, 'TRANSACTION REPORT') !== false || strpos($reportTitle, 'TRANSACTION-REPORT') !== false);

            // Guard against PAYOUT files being routed to SENDOUT.
            if ($hasPayoutWord && !$hasSendoutWord) return false;
            if ($hasSendoutWord) return true;

            $rows = (isset($payload['rows']) && is_array($payload['rows'])) ? $payload['rows'] : [];
            $sampleRow = null;
            foreach ($rows as $row) {
                if (is_array($row) && !empty($row)) { $sampleRow = $row; break; }
            }
            if ($sampleRow === null) return false;

            $keys = [];
            foreach (array_keys($sampleRow) as $key) {
                $keys[$normalizeHeader((string)$key)] = true;
            }

            $hasKeyLike = static function(string $needle) use ($keys): bool {
                foreach ($keys as $k => $_) {
                    if (strpos((string)$k, $needle) !== false) return true;
                }
                return false;
            };

            $hasDateSend = $hasKeyLike('date send');
            $hasControlSeries = $hasKeyLike('control series no');
            $hasReceiverCountry = $hasKeyLike('receiver country');
            $hasCharge = $hasKeyLike('charge');

            if ($hasTransactionReportAll && $hasDateSend && ($hasControlSeries || $hasReceiverCountry || $hasCharge)) {
                return true;
            }

            // Structural fallback: SENDOUT shape must contain DATE SEND plus at least one SENDOUT-specific column.
            return $hasDateSend && ($hasControlSeries || $hasReceiverCountry || $hasCharge);
        };

        $normalizeDecimalString = static function($raw): string {
            $norm = ml_normalize_amount($raw);
            if ($norm === '') return '0.00';
            return number_format((float)$norm, 2, '.', '');
        };

        $buildNormalizedRowMap = static function(array $row) use ($normalizeHeader): array {
            $map = [];
            foreach ($row as $k => $v) {
                if (!is_string($k)) continue;
                $map[$normalizeHeader($k)] = $v;
            }
            return $map;
        };

        $pickValue = static function(array $normalizedRow, array $aliases, $fallback = null) use ($normalizeHeader) {
            foreach ($aliases as $alias) {
                $key = $normalizeHeader((string)$alias);
                if (array_key_exists($key, $normalizedRow)) return $normalizedRow[$key];
            }
            return $fallback;
        };

        // Extract numeric branch_id from CONTROL SERIES NO pattern: <prefix><digits>-...
        $extractBranchIdFromControlSeries = static function($controlSeriesNo): ?string {
            $value = trim((string)$controlSeriesNo);
            if ($value === '') return null;
            if (preg_match('/^[A-Z]+\s*(\d+)\s*-/i', $value, $m)) {
                return trim((string)$m[1]);
            }
            return null;
        };

        $lookupBranchIdByBranchName = static function($branchName): ?string {
            $value = trim((string)$branchName);
            if ($value === '') return null;

            try {
                $masterPdo = masterDataConnection();
                $stmt = $masterPdo->prepare('SELECT branch_id FROM kpx_branch_masterfile WHERE TRIM(LOWER(branch_name)) = TRIM(LOWER(?)) LIMIT 1');
                $stmt->execute([$value]);
                $branchId = $stmt->fetchColumn();
                if ($branchId === false || $branchId === null) return null;

                $branchId = trim((string)$branchId);
                return $branchId !== '' ? $branchId : null;
            } catch (Throwable $e) {
                return null;
            }
        };

        $resolveBranchIdForWebRow = static function($controlSeriesNo, $branchName, $remoteOperator, $remoteBranch) use ($extractBranchIdFromControlSeries, $lookupBranchIdByBranchName): ?string {
            $controlBranchId = $extractBranchIdFromControlSeries($controlSeriesNo);
            $hasRemoteOperatorAndBranch = trim((string)$remoteOperator) !== '' && trim((string)$remoteBranch) !== '';

            if ($hasRemoteOperatorAndBranch) {
                return $lookupBranchIdByBranchName($branchName) ?: $controlBranchId;
            }

            return $controlBranchId;
        };

        $extractLockDates = static function(array $payload) use ($buildNormalizedRowMap, $pickValue, $toMysqlDateTime): array {
            $dates = [];
            $dateStr = isset($payload['dateStr']) ? (string) $payload['dateStr'] : '';
            $rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalizedRow = $buildNormalizedRowMap($row);
                $candidate = $pickValue($normalizedRow, ['date claimed', 'date send', 'date', 'tran date', 'transaction date'], $dateStr);
                $normalized = $toMysqlDateTime($candidate);
                if ($normalized !== null) {
                    $dateOnly = reconDaycardLocksNormalizeDate($normalized);
                    if ($dateOnly !== '') {
                        $dates[$dateOnly] = true;
                    }
                }
            }

            return array_keys($dates);
        };

        $lockedDates = [];
        foreach ($payloads as $payload) {
            foreach ($extractLockDates(is_array($payload) ? $payload : []) as $lockedDate) {
                $lockedDates[$lockedDate] = true;
            }
        }

        if (!empty($lockedDates)) {
            $blockedDates = reconDaycardLocksFindLockedDates(fileRecDbConnection(), $partnerName, array_keys($lockedDates));
            if (!empty($blockedDates)) {
                echo json_encode([
                    'success' => false,
                    'locked' => true,
                    'error' => reconDaycardLocksFormatBlockedUploadMessage($partnerName, $blockedDates),
                    'message' => reconDaycardLocksFormatBlockedUploadMessage($partnerName, $blockedDates),
                    'blocked_dates' => $blockedDates,
                    'errorCode' => 'daycard_locked',
                ]);
                exit;
            }
        }
        
        $pdo = fileRecDbConnection();
        $pdo->beginTransaction();
        
        $inserted = 0;
        $insertedRegular = 0;
        $insertedSendout = 0;
        $moneygramPartnerBranchBackfilled = 0;
        $moneygramBackfilledBranchIds = [];
        $moneygramUploadedBranchIds = [];
        $moneygramComparableBranchIds = [];
        $moneygramMissingLegacyBranches = [];
        $insertedIdsForBranchSync = [];
        $insertedIdsForUploadAudit = [];
        $now = date('Y-m-d H:i:s');
        $uploaded_by = trim((string)($_SESSION['user']['id_number'] ?? ''));
        
        $hasBranchIdColumn = false;
        $mlColumns = [];
        try {
            $mlCols = $pdo->query('SHOW COLUMNS FROM ml_web_data')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mlCols as $colInfo) {
                $field = strtolower((string)($colInfo['Field'] ?? ''));
                if ($field === '') continue;
                $mlColumns[$field] = (string)($colInfo['Field'] ?? $field);
                if ($field === 'branch_id') {
                    $hasBranchIdColumn = true;
                }
            }
        } catch (Throwable $e) {
            $hasBranchIdColumn = false;
            $mlColumns = [];
        }

        $mlInsertTargets = [
            'partner_id', 'partnername', 'no', 'control_series_no', 'branch_id', 'date_claimed', 'date_send', 'kptn',
            'ccref_no', 'currency', 'amount', 'charge', 'ctc', 'ctp', 'sender_name', 'sender_country',
            'beneficiary_receiver', 'receiver_country', 'receiver_name', 'receiver_kyc', 'receiver_phone',
            'operator', 'branch', 'remote_operator', 'remote_branch_id', 'remote_branch', 'uploaded_by',
            'created_at', 'updated_at'
        ];
        $mlInsertColumnKeys = [];
        foreach ($mlInsertTargets as $targetKey) {
            if (isset($mlColumns[$targetKey])) {
                $mlInsertColumnKeys[] = $targetKey;
            }
        }

        if (empty($mlInsertColumnKeys)) {
            throw new RuntimeException('No compatible columns found in ml_web_data');
        }

        $quotedInsertColumns = array_map(static function($key) use ($mlColumns) {
            return '`' . str_replace('`', '``', $mlColumns[$key]) . '`';
        }, $mlInsertColumnKeys);
        $sql = 'INSERT INTO ml_web_data (' . implode(',', $quotedInsertColumns) . ') VALUES (' . implode(',', array_fill(0, count($mlInsertColumnKeys), '?')) . ')';
        $stmt = $pdo->prepare($sql);

        // resolves invalid parameter number
        // Resolve partner_id from master table so we keep relational reference while storing unified rows.
        $partnerId = null;
        if ($partnerName !== '') {
            try {
                $masterPdo = masterDataConnection();
                $partnerStmt = $masterPdo->prepare('SELECT id FROM corpo_partner_masterfile WHERE UPPER(TRIM(partner_name)) = UPPER(TRIM(?)) LIMIT 1');
                $partnerStmt->execute([$partnerName]);
                $resolved = $partnerStmt->fetchColumn();
                if ($resolved !== false && $resolved !== null) {
                    $partnerId = (int)$resolved;
                }
            } catch (Throwable $ignored) {
                $partnerId = null;
            }
        }

        $sendoutInsertColumns = [];
        $sendoutAliases = [
            'no' => ['no'],
            'control_series_no' => ['control series no', 'control_series_no'],
            'date_send' => ['date send', 'date_send', 'date'],
            'kptn' => ['kptn'],
            'ccref_no' => ['ccref no', 'ccref_no'],
            'currency' => ['currency'],
            'amount' => ['amount'],
            'charge' => ['charge'],
            'sender_name' => ['sender name', 'sender_name'],
            'receiver_country' => ['receiver country', 'receiver_country'],
            'receiver_name' => ['receiver name', 'receiver_name', 'beneficiary/receiver', 'beneficiary receiver'],
            'receiver_phone' => ['receiver phone', 'receiver_phone'],
            'operator_name' => ['operator name', 'operator_name', 'operator'],
            'branch_name' => ['branch name', 'branch_name', 'branch'],
            'remote_operator' => ['remote operator', 'remote_operator'],
            'remote_branch_id' => ['remote branch id', 'remote_branch_id', 'remote branch no', 'remote_branch_no'],
            'remote_branch' => ['remote branch', 'remote_branch'],
        ];
        
        foreach ($payloads as $pl) {
            $dateStr = isset($pl['dateStr']) ? $pl['dateStr'] : '';
            $rows = isset($pl['rows']) && is_array($pl['rows']) ? $pl['rows'] : [];
            $isSendout = $detectSendoutPayload($pl);

            if ($isSendout) {
                foreach ($rows as $r) {
                    if (!is_array($r) || $isBlankRow($r) || $rowContainsFooter($r)) continue;

                    $normalizedRow = $buildNormalizedRowMap($r);
                    $controlSeries = trim((string)$pickValue($normalizedRow, $sendoutAliases['control_series_no'], ''));
                    $remoteOperator = trim((string)$pickValue($normalizedRow, $sendoutAliases['remote_operator'], ''));
                    $remoteBranchId = trim((string)$pickValue($normalizedRow, $sendoutAliases['remote_branch_id'], ''));
                    $remoteBranch = trim((string)$pickValue($normalizedRow, $sendoutAliases['remote_branch'], ''));
                    $controlBranchId = $extractBranchIdFromControlSeries($controlSeries);
                    $hasRemoteOperatorAndBranch = $remoteOperator !== '' && $remoteBranch !== '';
                    $branchId = $resolveBranchIdForWebRow($controlSeries, $pickValue($normalizedRow, $sendoutAliases['branch_name'], ''), $remoteOperator, $remoteBranch);
                    if ($hasRemoteOperatorAndBranch && $controlBranchId !== null) {
                        $remoteBranchId = $controlBranchId;
                    }
                    $dateSend = $toMysqlDateTime($pickValue($normalizedRow, $sendoutAliases['date_send'], $dateStr));
                    $mapped = [
                        'partner_id' => $partnerId,
                        'partnername' => $partnerName,
                        'no' => trim((string)$pickValue($normalizedRow, $sendoutAliases['no'], '')),
                        'control_series_no' => $controlSeries,
                        'branch_id' => $branchId,
                        // Keep date_claimed populated for existing reports that still filter on that field.
                        'date_claimed' => $dateSend,
                        'date_send' => $dateSend,
                        'kptn' => trim((string)$pickValue($normalizedRow, $sendoutAliases['kptn'], '')),
                        'ccref_no' => trim((string)$pickValue($normalizedRow, $sendoutAliases['ccref_no'], '')),
                        'currency' => trim((string)$pickValue($normalizedRow, $sendoutAliases['currency'], '')),
                        'amount' => $normalizeDecimalString($pickValue($normalizedRow, $sendoutAliases['amount'], '')),
                        'charge' => $normalizeDecimalString($pickValue($normalizedRow, $sendoutAliases['charge'], '')),
                        'sender_name' => trim((string)$pickValue($normalizedRow, $sendoutAliases['sender_name'], '')),
                        'receiver_country' => trim((string)$pickValue($normalizedRow, $sendoutAliases['receiver_country'], '')),
                        'receiver_name' => trim((string)$pickValue($normalizedRow, $sendoutAliases['receiver_name'], '')),
                        'beneficiary_receiver' => trim((string)$pickValue($normalizedRow, $sendoutAliases['receiver_name'], '')),
                        'receiver_phone' => trim((string)$pickValue($normalizedRow, $sendoutAliases['receiver_phone'], '')),
                        'operator' => trim((string)$pickValue($normalizedRow, $sendoutAliases['operator_name'], '')),
                        'branch' => trim((string)$pickValue($normalizedRow, $sendoutAliases['branch_name'], '')),
                        'remote_operator' => $remoteOperator,
                        'remote_branch_id' => $remoteBranchId,
                        'remote_branch' => $remoteBranch,
                        'uploaded_by' => $uploaded_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $values = [];
                    foreach ($mlInsertColumnKeys as $columnKey) {
                        $values[] = array_key_exists($columnKey, $mapped) ? $mapped[$columnKey] : null;
                    }

                    $stmt->execute($values);
                    $rowCount = (int)$stmt->rowCount();
                    $inserted += $rowCount;
                    $insertedSendout += $rowCount;
                    $insertedId = $rowCount > 0 ? (int)$pdo->lastInsertId() : 0;
                    if ($insertedId > 0) {
                        $insertedIdsForUploadAudit[] = $insertedId;
                    }
                    if ($rowCount > 0 && $hasBranchIdColumn && $branchId !== null && $branchId !== '') {
                        if ($partnerUpper === 'MONEYGRAM') {
                            $moneygramUploadedBranchIds[trim((string)$branchId)] = true;
                        }
                        if ($insertedId > 0) {
                            $insertedIdsForBranchSync[] = $insertedId;
                        }
                    }
                }
                continue;
            }
            
            foreach ($rows as $r) {
                $no = $r['NO'] ?? '';
                $control = $r['CONTROL SERIES NO'] ?? '';
                $rawDate = isset($r['DATE CLAIMED']) ? $r['DATE CLAIMED'] : $dateStr;
                $date_claimed = '';
                
                $date_claimed = $toMysqlDateTime($rawDate);
                
                $kptn = $r['KPTN'] ?? '';
                $ccref = $r['CCREF NO'] ?? '';
                $currency = $r['CURRENCY'] ?? '';
                $amount = ml_normalize_amount($r['AMOUNT'] ?? '');
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
                $remote_branch_id = $r['REMOTE BRANCH ID'] ?? ($r['REMOTE_BRANCH_ID'] ?? ($r['REMOTE BRANCH NO'] ?? ''));
                $remote_branch = $r['REMOTE BRANCH'] ?? '';
                $controlBranchId = $extractBranchIdFromControlSeries($control);
                $hasRemoteOperatorAndBranch = trim((string)$remote_operator) !== '' && trim((string)$remote_branch) !== '';
                $branchId = $resolveBranchIdForWebRow($control, $branch, $remote_operator, $remote_branch);
                if ($hasRemoteOperatorAndBranch && $controlBranchId !== null) {
                    $remote_branch_id = $controlBranchId;
                }

                $mapped = [
                    'partner_id' => $partnerId,
                    'partnername' => $partnerName,
                    'no' => $no,
                    'control_series_no' => $control,
                    'branch_id' => $branchId,
                    'date_claimed' => $date_claimed,
                    'kptn' => $kptn,
                    'ccref_no' => $ccref,
                    'currency' => $currency,
                    'amount' => $amount,
                    'ctc' => $ctc,
                    'ctp' => $ctp,
                    'sender_name' => $sender,
                    'sender_country' => $sender_country,
                    'beneficiary_receiver' => $benef,
                    'receiver_kyc' => $receiver_kyc,
                    'receiver_phone' => $receiver_phone,
                    'operator' => $operator,
                    'branch' => $branch,
                    'remote_operator' => $remote_operator,
                    'remote_branch_id' => $remote_branch_id,
                    'remote_branch' => $remote_branch,
                    'uploaded_by' => $uploaded_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $values = [];
                foreach ($mlInsertColumnKeys as $columnKey) {
                    $values[] = array_key_exists($columnKey, $mapped) ? $mapped[$columnKey] : null;
                }
                $stmt->execute($values);
                $rowCount = (int)$stmt->rowCount();
                $inserted += $rowCount;
                $insertedRegular += $rowCount;
                $insertedId = $rowCount > 0 ? (int)$pdo->lastInsertId() : 0;
                if ($insertedId > 0) {
                    $insertedIdsForUploadAudit[] = $insertedId;
                }
                if ($rowCount > 0 && $hasBranchIdColumn && $branchId !== null && $branchId !== '') {
                    if ($partnerUpper === 'MONEYGRAM') {
                        $moneygramUploadedBranchIds[trim((string)$branchId)] = true;
                    }
                    if ($insertedId > 0) {
                        $insertedIdsForBranchSync[] = $insertedId;
                    }
                }
            }
        }

        // Ensure uploaded_by is written for every inserted row using the current logged-in user's id_number.
        if ($uploaded_by !== '' && isset($mlColumns['uploaded_by']) && !empty($insertedIdsForUploadAudit)) {
            foreach (array_chunk(array_values(array_unique($insertedIdsForUploadAudit)), 1000) as $idChunk) {
                $placeholders = implode(',', array_fill(0, count($idChunk), '?'));
                $updateUploadedBySql = 'UPDATE ml_web_data SET `uploaded_by` = ? WHERE `id` IN (' . $placeholders . ')';
                $updateUploadedByStmt = $pdo->prepare($updateUploadedBySql);
                $updateUploadedByStmt->execute(array_merge([$uploaded_by], $idChunk));
            }
        }

        // After upload, enrich inserted ml_web_data rows from masterdata.branch_profile using branch_id.
        if ($hasBranchIdColumn && !empty($insertedIdsForBranchSync)) {
            $syncTargetColumns = [
                'mainzone' => 'mainzone',
                'zone' => 'zone',
                'area' => 'area',
                'region' => 'region',
                'region_code' => 'region_code',
            ];
            $availableSyncTargets = [];
            foreach ($syncTargetColumns as $targetColumn => $sourceColumn) {
                if (isset($mlColumns[$targetColumn])) {
                    $availableSyncTargets[$targetColumn] = $sourceColumn;
                }
            }

            if (!empty($availableSyncTargets)) {
                try {
                    $masterPdo = masterDataConnection();
                    $branchProfileColumns = [];
                    $branchProfileCols = $masterPdo->query('SHOW COLUMNS FROM branch_profile')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($branchProfileCols as $colInfo) {
                        $field = strtolower((string)($colInfo['Field'] ?? ''));
                        if ($field !== '') {
                            $branchProfileColumns[$field] = true;
                        }
                    }

                    $usableSyncTargets = [];
                    foreach ($availableSyncTargets as $targetColumn => $sourceColumn) {
                        if (isset($branchProfileColumns[$sourceColumn])) {
                            $usableSyncTargets[$targetColumn] = $sourceColumn;
                        }
                    }

                    if (isset($branchProfileColumns['branch_id']) && !empty($usableSyncTargets)) {
                        $fileReconDbName = env('FILERECONDB_NAME', 'filerecondb');
                        $masterDbName = env('MASTERDB_NAME', 'masterdata');
                        $quotedFileReconDbName = '`' . str_replace('`', '``', (string)$fileReconDbName) . '`';
                        $quotedMasterDbName = '`' . str_replace('`', '``', (string)$masterDbName) . '`';

                        $setClauses = [];
                        foreach ($usableSyncTargets as $targetColumn => $sourceColumn) {
                            $quotedColumn = '`' . str_replace('`', '``', $targetColumn) . '`';
                            $quotedSourceColumn = '`' . str_replace('`', '``', $sourceColumn) . '`';
                            $setClauses[] = 'm.' . $quotedColumn . ' = b.' . $quotedSourceColumn;
                        }

                        foreach (array_chunk(array_values(array_unique($insertedIdsForBranchSync)), 1000) as $idChunk) {
                            $placeholders = implode(',', array_fill(0, count($idChunk), '?'));
                            $updateSql = 'UPDATE ' . $quotedFileReconDbName . '.`ml_web_data` m '
                                . 'JOIN ' . $quotedMasterDbName . '.`branch_profile` b ON TRIM(m.`branch_id`) = TRIM(b.`branch_id`) '
                                . 'SET ' . implode(', ', $setClauses) . ' '
                                . 'WHERE m.`id` IN (' . $placeholders . ') '
                                . 'AND m.`branch_id` IS NOT NULL '
                                . 'AND TRIM(m.`branch_id`) <> \'\'';
                            $updateStmt = $pdo->prepare($updateSql);
                            $updateStmt->execute($idChunk);
                        }
                    }
                } catch (Throwable $branchSyncError) {
                    // Keep upload success behavior unchanged if branch profile enrichment cannot run.
                }
            }
        }

        // If MoneyGram Partner Data was uploaded before KPX Web Data, fill its blank branch_id
        // after the matching web rows are inserted.
        if ($hasBranchIdColumn && $partnerUpper === 'MONEYGRAM' && !empty($insertedIdsForBranchSync)) {
            try {
                $partnerColumns = [];
                $partnerCols = $pdo->query('SHOW COLUMNS FROM moneygram_partner_data')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($partnerCols as $colInfo) {
                    $field = strtolower((string)($colInfo['Field'] ?? ''));
                    if ($field !== '') {
                        $partnerColumns[$field] = true;
                    }
                }

                if (isset($partnerColumns['reference_id']) && isset($partnerColumns['branch_id']) && isset($mlColumns['ccref_no'])) {
                    $fileReconDbName = env('FILERECONDB_NAME', 'filerecondb');
                    $quotedFileReconDbName = '`' . str_replace('`', '``', (string)$fileReconDbName) . '`';

                    foreach (array_chunk(array_values(array_unique($insertedIdsForBranchSync)), 1000) as $idChunk) {
                        $placeholders = implode(',', array_fill(0, count($idChunk), '?'));
                        $selectComparableBranchesSql = 'SELECT DISTINCT m.`branch_id` '
                            . 'FROM ' . $quotedFileReconDbName . '.`moneygram_partner_data` p '
                            . 'JOIN ' . $quotedFileReconDbName . '.`ml_web_data` m '
                            . 'ON CONVERT(TRIM(p.`reference_id`) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(m.`ccref_no`) USING utf8mb4) COLLATE utf8mb4_unicode_ci '
                            . 'WHERE m.`id` IN (' . $placeholders . ') '
                            . 'AND m.`branch_id` IS NOT NULL '
                            . 'AND TRIM(m.`branch_id`) <> \'\' '
                            . 'AND p.`reference_id` IS NOT NULL '
                            . 'AND TRIM(p.`reference_id`) <> \'\'';
                        $comparableBranchStmt = $pdo->prepare($selectComparableBranchesSql);
                        $comparableBranchStmt->execute($idChunk);
                        foreach ($comparableBranchStmt->fetchAll(PDO::FETCH_COLUMN) as $branchId) {
                            $branchId = trim((string)$branchId);
                            if ($branchId !== '') {
                                $moneygramComparableBranchIds[$branchId] = true;
                            }
                        }

                        $selectBackfillBranchesSql = 'SELECT DISTINCT m.`branch_id` '
                            . 'FROM ' . $quotedFileReconDbName . '.`moneygram_partner_data` p '
                            . 'JOIN ' . $quotedFileReconDbName . '.`ml_web_data` m '
                            . 'ON CONVERT(TRIM(p.`reference_id`) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(m.`ccref_no`) USING utf8mb4) COLLATE utf8mb4_unicode_ci '
                            . 'WHERE m.`id` IN (' . $placeholders . ') '
                            . 'AND m.`branch_id` IS NOT NULL '
                            . 'AND TRIM(m.`branch_id`) <> \'\' '
                            . 'AND p.`reference_id` IS NOT NULL '
                            . 'AND TRIM(p.`reference_id`) <> \'\' '
                            . 'AND (p.`branch_id` IS NULL OR TRIM(p.`branch_id`) = \'\')';
                        $branchStmt = $pdo->prepare($selectBackfillBranchesSql);
                        $branchStmt->execute($idChunk);
                        foreach ($branchStmt->fetchAll(PDO::FETCH_COLUMN) as $branchId) {
                            $branchId = trim((string)$branchId);
                            if ($branchId !== '') {
                                $moneygramBackfilledBranchIds[$branchId] = true;
                                $moneygramUploadedBranchIds[$branchId] = true;
                            }
                        }

                        $updateSql = 'UPDATE ' . $quotedFileReconDbName . '.`moneygram_partner_data` p '
                            . 'JOIN ' . $quotedFileReconDbName . '.`ml_web_data` m '
                            . 'ON CONVERT(TRIM(p.`reference_id`) USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(TRIM(m.`ccref_no`) USING utf8mb4) COLLATE utf8mb4_unicode_ci '
                            . 'SET p.`branch_id` = m.`branch_id` '
                            . 'WHERE m.`id` IN (' . $placeholders . ') '
                            . 'AND m.`branch_id` IS NOT NULL '
                            . 'AND TRIM(m.`branch_id`) <> \'\' '
                            . 'AND p.`reference_id` IS NOT NULL '
                            . 'AND TRIM(p.`reference_id`) <> \'\' '
                            . 'AND (p.`branch_id` IS NULL OR TRIM(p.`branch_id`) = \'\')';
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute($idChunk);
                        $moneygramPartnerBranchBackfilled += (int)$updateStmt->rowCount();
                    }
                }
            } catch (Throwable $moneygramPartnerBranchSyncError) {
                // Keep upload success behavior unchanged if the partner backfill cannot run.
            }
        }

        // Show the MoneyGram legacy notice only when the uploaded KPX rows have a partner-data comparison.
        $moneygramLegacyCheckBranchIds = !empty($moneygramComparableBranchIds)
            ? $moneygramComparableBranchIds
            : [];

        if (!empty($moneygramLegacyCheckBranchIds)) {
            try {
                $masterPdo = masterDataConnection();
                $branchIdList = array_values(array_keys($moneygramLegacyCheckBranchIds));
                $profileByBranchId = [];
                foreach (array_chunk($branchIdList, 500) as $branchChunk) {
                    $placeholders = implode(',', array_fill(0, count($branchChunk), '?'));
                    $profileStmt = $masterPdo->prepare("SELECT branch_id, branch_name, legacyid_moneygram FROM branch_profile WHERE branch_id IN ({$placeholders})");
                    $profileStmt->execute($branchChunk);
                    foreach ($profileStmt->fetchAll(PDO::FETCH_ASSOC) as $profileRow) {
                        $branchId = trim((string)($profileRow['branch_id'] ?? ''));
                        if ($branchId === '') {
                            continue;
                        }
                        $profileByBranchId[$branchId] = [
                            'branch_name' => trim((string)($profileRow['branch_name'] ?? '')),
                            'legacyid_moneygram' => trim((string)($profileRow['legacyid_moneygram'] ?? '')),
                        ];
                    }
                }

                foreach ($branchIdList as $branchId) {
                    if (!array_key_exists($branchId, $profileByBranchId) || $profileByBranchId[$branchId]['legacyid_moneygram'] === '') {
                        $moneygramMissingLegacyBranches[] = [
                            'branch_id' => $branchId,
                            'branch_name' => $profileByBranchId[$branchId]['branch_name'] ?? '',
                        ];
                    }
                }
            } catch (Throwable $moneygramLegacyCheckError) {
                $moneygramMissingLegacyBranches = [];
            }
        }
        
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'inserted' => $inserted,
            'inserted_regular' => $insertedRegular,
            'inserted_sendout' => $insertedSendout,
            'moneygram_partner_branch_backfilled' => $moneygramPartnerBranchBackfilled,
            'moneygram_has_missing_legacy' => !empty($moneygramMissingLegacyBranches),
            'moneygram_missing_legacy_branches' => $moneygramMissingLegacyBranches
        ]); 
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']); 
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
