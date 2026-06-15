<?php
// moneygram-insert.php
// Handles duplicate checks, deletes and inserts for MONEYGRAM upload flow

require_once __DIR__ . '/moneygram-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// read raw json
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// use shared fileRecDbConnection() from config/db.php

// helpers are provided by moneygram-helper.php (moneygram_parse_date_claimed, moneygram_normalize_amount, ...)

try{
    $action = isset($data['action']) ? $data['action'] : '';
    if($action === 'check'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'duplicates'=>[]]); exit; }
        // Normalize incoming dates and perform tolerant checks:
        // 1) exact DATETIME match using normalized value
        // 2) if normalization fails, try DATE(date_claimed) = parsed-date-only
        // 3) as a last resort, return any rows that match ccref_no (loose match)
        $pdo = fileRecDbConnection();
        $results = [];
        $seen = [];
        foreach($pairs as $p){
            $cc = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
            $rawDate = isset($p['date_claimed']) ? $p['date_claimed'] : '';
            if($cc === '') continue;

            // 1) try exact normalized datetime
            $norm = moneygram_parse_date_claimed($rawDate);
            if($norm !== null){
                $stmt = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM moneygram_web_data WHERE ccref_no = ? AND date_claimed = ? GROUP BY ccref_no, date_claimed');
                $stmt->execute([$cc, $norm]);
                $r = $stmt->fetch();
                if($r && isset($r['cnt']) && (int)$r['cnt'] > 0){
                    $key = $r['ccref_no'].'|'.$r['date_claimed'];
                    if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['ccref_no'=>$r['ccref_no'], 'date_claimed'=>$r['date_claimed'], 'cnt'=>(int)$r['cnt']]; }
                    continue;
                }
            }

            // 2) try date-only match if rawDate is parseable to a date
            $ts = strtotime((string)$rawDate);
            if($ts !== false){
                $dateOnly = date('Y-m-d', $ts);
                $stmt2 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM moneygram_web_data WHERE ccref_no = ? AND DATE(date_claimed) = ? GROUP BY ccref_no, date_claimed');
                $stmt2->execute([$cc, $dateOnly]);
                $r2 = $stmt2->fetchAll();
                foreach($r2 as $ra){
                    if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                        $key = $ra['ccref_no'].'|'.$ra['date_claimed'];
                        if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['ccref_no'=>$ra['ccref_no'], 'date_claimed'=>$ra['date_claimed'], 'cnt'=>(int)$ra['cnt']]; }
                    }
                }
                if(!empty($r2)) continue;
            }

            // 3) last resort: return any rows that match ccref_no (loose match)
            $stmt3 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM moneygram_web_data WHERE ccref_no = ? GROUP BY ccref_no, date_claimed');
            $stmt3->execute([$cc]);
            $r3 = $stmt3->fetchAll();
            foreach($r3 as $ra){
                if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                    $key = $ra['ccref_no'].'|'.$ra['date_claimed'];
                    if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['ccref_no'=>$ra['ccref_no'], 'date_claimed'=>$ra['date_claimed'], 'cnt'=>(int)$ra['cnt']]; }
                }
            }
        }
        echo json_encode(['success'=>true,'duplicates'=>$results]); exit;
    }

    if($action === 'delete'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }
        $pdo = fileRecDbConnection();
        $cnt = 0;
        foreach(array_chunk($pairs, 5000) as $chunk){
            $place = [];
            $params = [];
            foreach($chunk as $p){
                $place[] = '(?,?)';
                $params[] = $p['ccref_no'];
                $params[] = $p['date_claimed'];
            }
            $sql = 'DELETE FROM moneygram_web_data WHERE (ccref_no, date_claimed) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    // partner duplicate check (supports modern moneygram schema: transaction_id + tran_date,
    // and legacy schema: reference_no + date)
    if($action === 'check_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'duplicates'=>[]]); exit; }
        $pdo = fileRecDbConnection();

        $cols = $pdo->query("SHOW COLUMNS FROM moneygram_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_map(function($c){ return strtolower($c['Field']); }, $cols);

        $idCol = null;
        foreach(['transaction_id','reference_id','reference_no'] as $candidate){
            if(in_array($candidate, $fields, true)){ $idCol = $candidate; break; }
        }
        if($idCol === null){ echo json_encode(['success'=>false,'error'=>'No supported ID column found in moneygram_partner_data']); exit; }

        $dateCol = null;
        foreach(['tran_date','date'] as $candidate){
            if(in_array($candidate, $fields, true)){ $dateCol = $candidate; break; }
        }

        $results = [];
        $seen = [];
        $datePairs = [];
        $looseIds = [];
        foreach($pairs as $p){
            $idVal = '';
            if(isset($p['transaction_id'])) $idVal = trim((string)$p['transaction_id']);
            if($idVal === '' && isset($p['reference_id'])) $idVal = trim((string)$p['reference_id']);
            if($idVal === '' && isset($p['reference_no'])) $idVal = trim((string)$p['reference_no']);
            $rawDate = isset($p['tran_date']) ? $p['tran_date'] : (isset($p['date']) ? $p['date'] : '');
            if($idVal === '') continue;

            // if client provided a date-only string (YYYY-MM-DD), compare by DATE(date) = ?
            $dateOnly = null;
            if($rawDate !== null && $rawDate !== ''){
                // accept already-normalized YYYY-MM-DD or parseable values
                if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)){
                    $dateOnly = $rawDate;
                } else {
                    $ts = strtotime((string)$rawDate);
                    if($ts !== false) $dateOnly = date('Y-m-d', $ts);
                }
            }

            if($dateOnly !== null && $dateCol !== null){
                $datePairs[$idVal . '|' . $dateOnly] = [$idVal, $dateOnly];
            } else {
                $looseIds[$idVal] = true;
            }
        }

        $matchedDatePairs = [];
        if($dateCol !== null && !empty($datePairs)){
            foreach(array_chunk(array_values($datePairs), 500) as $chunk){
                $where = [];
                $params = [];
                foreach($chunk as $pair){
                    $where[] = "({$idCol} = ? AND DATE({$dateCol}) = ?)";
                    $params[] = $pair[0];
                    $params[] = $pair[1];
                }
                $sql = "SELECT {$idCol} as idcol, {$dateCol} as dcol, COUNT(*) as cnt FROM moneygram_partner_data WHERE " . implode(' OR ', $where) . " GROUP BY {$idCol}, {$dateCol}";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach($stmt->fetchAll() as $ra){
                    if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                        $dateOnly = '';
                        if($ra['dcol'] !== null && $ra['dcol'] !== ''){
                            $ts = strtotime((string)$ra['dcol']);
                            $dateOnly = $ts !== false ? date('Y-m-d', $ts) : substr((string)$ra['dcol'], 0, 10);
                        }
                        $matchedDatePairs[$ra['idcol'] . '|' . $dateOnly] = true;
                        $key = $ra['idcol'].'|'.$ra['dcol'];
                        if(!isset($seen[$key])){
                            $seen[$key]=true;
                            $entry = ['cnt'=>(int)$ra['cnt'], 'date'=>$ra['dcol'], 'tran_date'=>$ra['dcol']];
                            $entry[$idCol] = $ra['idcol'];
                            $results[] = $entry;
                        }
                    }
                }
            }
        }

        foreach($datePairs as $pairKey => $pair){
            if(!isset($matchedDatePairs[$pairKey])){
                $looseIds[$pair[0]] = true;
            }
        }

        if(!empty($looseIds)){
            foreach(array_chunk(array_keys($looseIds), 1000) as $chunk){
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                if($dateCol !== null){
                    $sql = "SELECT {$idCol} as idcol, {$dateCol} as dcol, COUNT(*) as cnt FROM moneygram_partner_data WHERE {$idCol} IN ({$placeholders}) GROUP BY {$idCol}, {$dateCol}";
                } else {
                    $sql = "SELECT {$idCol} as idcol, NULL as dcol, COUNT(*) as cnt FROM moneygram_partner_data WHERE {$idCol} IN ({$placeholders}) GROUP BY {$idCol}";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($chunk);
                foreach($stmt->fetchAll() as $ra){
                    if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                        $key = $ra['idcol'].'|'.$ra['dcol'];
                        if(!isset($seen[$key])){
                            $seen[$key]=true;
                            $entry = ['cnt'=>(int)$ra['cnt'], 'date'=>$ra['dcol'], 'tran_date'=>$ra['dcol']];
                            $entry[$idCol] = $ra['idcol'];
                            $results[] = $entry;
                        }
                    }
                }
            }
        }
        echo json_encode(['success'=>true,'duplicates'=>$results]); exit;
    }

    // partner delete (supports modern moneygram schema and legacy fallback)
    if($action === 'delete_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }
        $pdo = fileRecDbConnection();

        $cols = $pdo->query("SHOW COLUMNS FROM moneygram_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_map(function($c){ return strtolower($c['Field']); }, $cols);

        $idCol = null;
        foreach(['transaction_id','reference_id','reference_no'] as $candidate){
            if(in_array($candidate, $fields, true)){ $idCol = $candidate; break; }
        }
        if($idCol === null){ echo json_encode(['success'=>false,'error'=>'No supported ID column found in moneygram_partner_data']); exit; }

        $dateCol = null;
        foreach(['tran_date','date'] as $candidate){
            if(in_array($candidate, $fields, true)){ $dateCol = $candidate; break; }
        }

        $validPairs = [];
        foreach($pairs as $p){
            $idVal = '';
            if(isset($p['transaction_id'])) $idVal = trim((string)$p['transaction_id']);
            if($idVal === '' && isset($p['reference_id'])) $idVal = trim((string)$p['reference_id']);
            if($idVal === '' && isset($p['reference_no'])) $idVal = trim((string)$p['reference_no']);
            $dateVal = isset($p['tran_date']) ? $p['tran_date'] : (isset($p['date']) ? $p['date'] : null);
            if($idVal === '') continue;
            $validPairs[] = [$idVal, $dateVal];
        }
        if(empty($validPairs)){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }

        $cnt = 0;
        foreach(array_chunk($validPairs, 5000) as $chunk){
            $place = [];
            $params = [];
            if($dateCol !== null){
                foreach($chunk as $pair){
                    if($pair[1] === null || $pair[1] === '') continue;
                    $place[] = '(?,?)';
                    $params[] = $pair[0];
                    $params[] = $pair[1];
                }
                if(empty($place)) continue;
                $sql = "DELETE FROM moneygram_partner_data WHERE ({$idCol}, {$dateCol}) IN (" . implode(',', $place) . ')';
            } else {
                foreach($chunk as $pair){
                    $place[] = '?';
                    $params[] = $pair[0];
                }
                $sql = "DELETE FROM moneygram_partner_data WHERE {$idCol} IN (" . implode(',', $place) . ')';
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    if($action === 'precheck_partner_legacy'){
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new MoneygramInsert();
        $res = $ins->precheckPartnerLegacyIds($payloads);
        echo json_encode($res); exit;
    }

    if($action === 'insert_web'){
        $company = isset($data['company']) ? $data['company'] : 'MONEYGRAM';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new MoneygramInsert();
        $res = $ins->insertWebData($company, $payloads);
        echo json_encode($res); exit;
    }

    if($action === 'insert_partner'){
        $company = isset($data['company']) ? $data['company'] : 'MONEYGRAM';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new MoneygramInsert();
        $res = $ins->insertPartnerData($company, $payloads);
        echo json_encode($res); exit;
    }

    echo json_encode(['success'=>false,'error'=>'Invalid action']); exit;

}catch(Throwable $e){
    if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
