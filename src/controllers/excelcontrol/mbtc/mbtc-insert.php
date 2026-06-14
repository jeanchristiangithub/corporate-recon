<?php
// mbtc-insert.php
// Handles duplicate checks, deletes and inserts for MBTC upload flow

require_once __DIR__ . '/mbtc-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// read raw json
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

// use shared fileRecDbConnection() from config/db.php

// helpers are provided by mbtc-helper.php (mbtc_parse_date_claimed, mbtc_normalize_amount, ...)

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
            $norm = mbtc_parse_date_claimed($rawDate);
            if($norm !== null){
                $stmt = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM mbtc_web_data WHERE ccref_no = ? AND date_claimed = ? GROUP BY ccref_no, date_claimed');
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
                $stmt2 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM mbtc_web_data WHERE ccref_no = ? AND DATE(date_claimed) = ? GROUP BY ccref_no, date_claimed');
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
            $stmt3 = $pdo->prepare('SELECT ccref_no, date_claimed, COUNT(*) as cnt FROM mbtc_web_data WHERE ccref_no = ? GROUP BY ccref_no, date_claimed');
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
            $sql = 'DELETE FROM mbtc_web_data WHERE (ccref_no, date_claimed) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    // partner duplicate check: reference_no + date against mbtc_partner_data
    if($action === 'check_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'duplicates'=>[]]); exit; }
        $pdo = fileRecDbConnection();
        $results = [];
        $seen = [];
        foreach($pairs as $p){
            $ref = isset($p['reference_no']) ? trim((string)$p['reference_no']) : '';
            $rawDate = isset($p['date']) ? $p['date'] : '';
            if($ref === '') continue;

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

            if($dateOnly !== null){
                $stmt = $pdo->prepare('SELECT reference_no, date, COUNT(*) as cnt FROM mbtc_partner_data WHERE reference_no = ? AND DATE(`date`) = ? GROUP BY reference_no, date');
                $stmt->execute([$ref, $dateOnly]);
                $r = $stmt->fetchAll();
                foreach($r as $ra){
                    if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                        $key = $ra['reference_no'].'|'.$ra['date'];
                        if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['reference_no'=>$ra['reference_no'], 'date'=>$ra['date'], 'cnt'=>(int)$ra['cnt']]; }
                    }
                }
                if(!empty($r)) continue;
            }

            // last resort: any rows matching reference_no
            $stmt3 = $pdo->prepare('SELECT reference_no, date, COUNT(*) as cnt FROM mbtc_partner_data WHERE reference_no = ? GROUP BY reference_no, date');
            $stmt3->execute([$ref]);
            $r3 = $stmt3->fetchAll();
            foreach($r3 as $ra){
                if(isset($ra['cnt']) && (int)$ra['cnt'] > 0){
                    $key = $ra['reference_no'].'|'.$ra['date'];
                    if(!isset($seen[$key])){ $seen[$key]=true; $results[] = ['reference_no'=>$ra['reference_no'], 'date'=>$ra['date'], 'cnt'=>(int)$ra['cnt']]; }
                }
            }
        }
        echo json_encode(['success'=>true,'duplicates'=>$results]); exit;
    }

    // partner delete
    if($action === 'delete_partner'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if(count($pairs) === 0){ echo json_encode(['success'=>true,'deleted'=>0]); exit; }
        $pdo = fileRecDbConnection();
        $cnt = 0;
        foreach(array_chunk($pairs, 5000) as $chunk){
            $place = [];
            $params = [];
            foreach($chunk as $p){
                $place[] = '(?,?)';
                $params[] = $p['reference_no'];
                $params[] = $p['date'];
            }
            $sql = 'DELETE FROM mbtc_partner_data WHERE (reference_no, date) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }
        echo json_encode(['success'=>true,'deleted'=>$cnt]); exit;
    }

    if($action === 'insert_web'){
        $company = isset($data['company']) ? $data['company'] : 'MBTC';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new MbtcInsert();
        $res = $ins->insertWebData($company, $payloads);
        echo json_encode($res); exit;
    }

    if($action === 'insert_partner'){
        $company = isset($data['company']) ? $data['company'] : 'MBTC';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new MbtcInsert();
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
