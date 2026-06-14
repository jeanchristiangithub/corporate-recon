<?php
// rcbc-insert.php
// Handles duplicate checks, deletes and inserts for RCBC upload flow

require_once __DIR__ . '/rcbc-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// use shared fileRecDbConnection() from config/db.php

try{
    $action = isset($data['action']) ? $data['action'] : '';
    if($action === 'check'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'duplicates'=>[]]); exit; }
        $pdo = fileRecDbConnection();
        $results = [];
        $seen = [];
        foreach($pairs as $p){
            $cc = isset($p['ccref_no']) ? trim((string)$p['ccref_no']) : '';
            $rawDate = isset($p['date_claimed']) ? $p['date_claimed'] : '';
            if($cc === '') continue;

            $norm = rcbc_parse_date_claimed($rawDate);
            if($norm !== null){
                $stmt = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM rcbc_web_data WHERE ccref_no = ? AND date_claimed = ? GROUP BY ccref_no, date_claimed');
                $stmt->execute([$cc, $norm]);
                $r = $stmt->fetch();
                if($r && isset($r['cnt']) && (int)$r['cnt'] > 0){
                    $key = $r['ccref_no'].'|'.$r['date_claimed'];
                    if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['ccref_no'=>$r['ccref_no'], 'date_claimed'=>$r['date_claimed'], 'cnt'=>(int)$r['cnt']]; }
                    continue;
                }
            }

            $ts = strtotime((string)$rawDate);
            if($ts !== false){
                $dateOnly = date('Y-m-d', $ts);
                $stmt2 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM rcbc_web_data WHERE ccref_no = ? AND DATE(date_claimed) = ? GROUP BY ccref_no, date_claimed');
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

            $stmt3 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM rcbc_web_data WHERE ccref_no = ? GROUP BY ccref_no, date_claimed');
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
            $sql = 'DELETE FROM rcbc_web_data WHERE (ccref_no, date_claimed) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    if($action === 'check_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'duplicates'=>[]]); exit; }
        $pdo = fileRecDbConnection();
        // detect identifying/date columns across multiple partner schemas
        $cols = $pdo->query("SHOW COLUMNS FROM rcbc_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_map(function($c){ return strtolower($c['Field']); }, $cols);
        $idCol = null;
        foreach(['transaction_id','reference_no','ref_no','payout_id'] as $candidate){
            if(in_array($candidate, $fields, true)){ $idCol = $candidate; break; }
        }
        if($idCol === null){ echo json_encode(['success'=>false,'error'=>'No supported RCBC ID column found in rcbc_partner_data']); exit; }
        $dateCol = null;
        foreach(['date','cover_date','date_claimed'] as $candidate){
            if(in_array($candidate, $fields, true)){ $dateCol = $candidate; break; }
        }

        $results = [];
        $seen = [];
        foreach($pairs as $p){
            $ref = '';
            if(isset($p['transaction_id'])) $ref = trim((string)$p['transaction_id']);
            if($ref === '' && isset($p['reference_no'])) $ref = trim((string)$p['reference_no']);
            if($ref === '' && isset($p['ref_no'])) $ref = trim((string)$p['ref_no']);
            if($ref === '' && isset($p['payout_id'])) $ref = trim((string)$p['payout_id']);
            $rawDate = isset($p['date']) ? $p['date'] : '';
            if($ref === '') continue;

            $dateOnly = null;
            if($rawDate !== null && $rawDate !== ''){
                if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)){
                    $dateOnly = $rawDate;
                } else {
                    $ts = strtotime((string)$rawDate);
                    if($ts !== false) $dateOnly = date('Y-m-d', $ts);
                }
            }

            if($dateOnly !== null && $dateCol !== null){
                $stmt = $pdo->prepare("SELECT {$idCol} as idcol, {$dateCol} as dcol, COUNT(*) as cnt FROM rcbc_partner_data WHERE {$idCol} = ? AND DATE({$dateCol}) = ? GROUP BY {$idCol}, {$dateCol}");
                $stmt->execute([$ref, $dateOnly]);
                $r = $stmt->fetchAll();
                foreach($r as $ra){
                    if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                        $key = $ra['idcol'].'|'.($ra['dcol'] ?? '');
                        if(!isset($seen[$key])){ $seen[$key]=true; $entry = ['date'=>($ra['dcol'] ?? null), 'cnt'=>(int)$ra['cnt']]; $entry[$idCol] = $ra['idcol']; $results[] = $entry; }
                    }
                }
                if(!empty($r)) continue;
            }

            if($dateCol !== null){
                $stmt3 = $pdo->prepare("SELECT {$idCol} as idcol, {$dateCol} as dcol, COUNT(*) as cnt FROM rcbc_partner_data WHERE {$idCol} = ? GROUP BY {$idCol}, {$dateCol}");
            } else {
                $stmt3 = $pdo->prepare("SELECT {$idCol} as idcol, NULL as dcol, COUNT(*) as cnt FROM rcbc_partner_data WHERE {$idCol} = ? GROUP BY {$idCol}");
            }
            $stmt3->execute([$ref]);
            $r3 = $stmt3->fetchAll();
            foreach($r3 as $ra){
                if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                    $key = $ra['idcol'].'|'.($ra['dcol'] ?? '');
                    if(!isset($seen[$key])){ $seen[$key]=true; $entry = ['date'=>($ra['dcol'] ?? null), 'cnt'=>(int)$ra['cnt']]; $entry[$idCol] = $ra['idcol']; $results[] = $entry; }
                }
            }
        }
        echo json_encode(['success'=>true,'duplicates'=>$results]); exit;
    }

    if($action === 'delete_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }
        $pdo = fileRecDbConnection();
        // detect delete columns across multiple partner schemas
        $cols = $pdo->query("SHOW COLUMNS FROM rcbc_partner_data")->fetchAll(PDO::FETCH_ASSOC);
        $fields = array_map(function($c){ return strtolower($c['Field']); }, $cols);
        $idCol = null;
        foreach(['transaction_id','reference_no','ref_no','payout_id'] as $candidate){
            if(in_array($candidate, $fields, true)){ $idCol = $candidate; break; }
        }
        if($idCol === null){ echo json_encode(['success'=>false,'error'=>'No supported RCBC ID column found in rcbc_partner_data']); exit; }
        $dateCol = null;
        foreach(['date','cover_date','date_claimed'] as $candidate){
            if(in_array($candidate, $fields, true)){ $dateCol = $candidate; break; }
        }

        $validPairs = [];
        foreach($pairs as $p){
            $val = '';
            if(isset($p['transaction_id'])) $val = $p['transaction_id'];
            if($val === '' && isset($p['reference_no'])) $val = $p['reference_no'];
            if($val === '' && isset($p['ref_no'])) $val = $p['ref_no'];
            if($val === '' && isset($p['payout_id'])) $val = $p['payout_id'];
            $date = isset($p['date']) ? $p['date'] : null;
            if($val === '') continue;
            $validPairs[] = [$val, $date];
        }
        if(empty($validPairs)){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }
        $cnt = 0;
        foreach(array_chunk($validPairs, 5000) as $chunk){
            if($dateCol !== null){
                $place = [];
                $params = [];
                foreach($chunk as $pair){
                    if($pair[1] === null || $pair[1] === '') continue;
                    $place[] = '(?,?)';
                    $params[] = $pair[0];
                    $params[] = $pair[1];
                }
                if(empty($place)) continue;
                $sql = "DELETE FROM rcbc_partner_data WHERE ({$idCol}, {$dateCol}) IN (" . implode(',', $place) . ')';
            } else {
                $place = [];
                $params = [];
                foreach($chunk as $pair){
                    $place[] = '?';
                    $params[] = $pair[0];
                }
                $sql = "DELETE FROM rcbc_partner_data WHERE {$idCol} IN (" . implode(',', $place) . ')';
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    if($action === 'insert_web'){
        $company = isset($data['company']) ? $data['company'] : 'RCBC';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new RcbcInsert();
        $res = $ins->insertWebData($company, $payloads);
        echo json_encode($res); exit;
    }

    if($action === 'insert_partner'){
        $company = isset($data['company']) ? $data['company'] : 'RCBC';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new RcbcInsert();
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

