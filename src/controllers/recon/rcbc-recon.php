<?php
// rcbc-recon.php
// Returns per-day recon summary for RCBC (used by the UI)

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try{
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
    if($month < 1 || $month > 12 || $year < 2000){
        echo json_encode(['success'=>false,'error'=>'Invalid month/year']);
        exit;
    }

    $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $pdo = fileRecDbConnection();

    $tryQuery = function(array $sqls, array $params) use ($pdo){
        foreach($sqls as $sql){
            try{
                $s = $pdo->prepare($sql);
                $s->execute($params);
                return $s->fetchAll();
            }catch(PDOException $e){
                $code = $e->getCode();
                if(strpos($e->getMessage(),'Unknown column') === false && $code !== '42S22'){
                    throw $e;
                }
            }
        }
        return [];
    };

    // Support both simplified and legacy partner schemas.
    $sqlsPart = [
        'SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount, 0 AS partner_commission FROM rcbc_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY transaction_id',
        'SELECT reference_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS partner_amount, SUM(COALESCE(in_php,0)) AS partner_commission FROM rcbc_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY reference_no',
        'SELECT reference_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS partner_amount, SUM(COALESCE(in_php,0)) AS partner_commission FROM rcbc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY reference_no',
    ];

    $sqlsWeb = [
        'SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM rcbc_web_data WHERE DATE(date_claimed) = ? OR date_claimed LIKE ? GROUP BY ccref_no',
    ];

    $detail = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $reqDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;
    $days = [];

    for($d=1;$d<=$daysInMonth;$d++){
        $dt = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $likeParam = '%' . $dt . '%';

        $parts = $tryQuery($sqlsPart, [$dt, $likeParam]);
        $partnersByRef = [];
        foreach($parts as $p){
            $rawRef = isset($p['tx']) ? (string)$p['tx'] : '';
            $key = trim($rawRef);
            if(function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
            if($key === '') continue;
            $p['__ref_raw'] = $rawRef;
            $partnersByRef[$key] = $p;
        }

        $webs = $tryQuery($sqlsWeb, [$dt, $likeParam]);
        $webByRef = [];
        foreach($webs as $w){
            $rawRef = isset($w['ccref_no']) ? (string)$w['ccref_no'] : '';
            $key = trim($rawRef);
            if(function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
            if($key === '') continue;
            $w['__ref_raw'] = $rawRef;
            $webByRef[$key] = $w;
        }

        $hasPartner = count($partnersByRef) > 0;
        $hasWeb = count($webByRef) > 0;
        $status = 'white';
        $tooltip = '';

        $hasDuplicate = false;
        foreach($partnersByRef as $pr){ if((int)$pr['cnt'] > 1) { $hasDuplicate = true; break; } }
        if(!$hasDuplicate){ foreach($webByRef as $wr){ if((int)$wr['cnt'] > 1) { $hasDuplicate = true; break; } } }
        if($hasDuplicate) $status = 'yellow';

        $mismatchFound = false;
        $missingFound = false;
        $matchedPrincipal = 0.0;
        $matchedCommission = 0.0;
        $matchedWebAmount = 0.0;
        $matchedWebCtp = 0.0;
        $matchedCount = 0;

        $totalPartnerAmount = 0.0;
        $totalWebAmount = 0.0;
        foreach($partnersByRef as $pdata){ $totalPartnerAmount += (float)($pdata['partner_amount'] ?? 0); }
        foreach($webByRef as $wdata){ $totalWebAmount += (float)($wdata['web_amount'] ?? 0); }

        $missing_web_refs = [];
        $missing_partner_refs = [];
        $mismatches = [];
        $duplicates = [];

        foreach($partnersByRef as $ref => $pdata){
            if((int)($pdata['cnt'] ?? 0) > 1) $duplicates[] = ['type'=>'partner','ref'=>$pdata['__ref_raw'] ?? $ref,'count'=>(int)$pdata['cnt']];
        }
        foreach($webByRef as $ref => $wdata){
            if((int)($wdata['cnt'] ?? 0) > 1) $duplicates[] = ['type'=>'web','ref'=>$wdata['__ref_raw'] ?? $ref,'count'=>(int)$wdata['cnt']];
        }

        foreach($partnersByRef as $ref => $pdata){
            if(isset($webByRef[$ref])){
                $w = $webByRef[$ref];
                $pAmt = (float)($pdata['partner_amount'] ?? 0);
                $wAmt = (float)($w['web_amount'] ?? 0);
                $pComm = (float)($pdata['partner_commission'] ?? 0);
                $wCtp = (float)($w['web_ctp'] ?? 0);

                $matchedPrincipal += $pAmt;
                $matchedCommission += $pComm;
                $matchedWebAmount += $wAmt;
                $matchedWebCtp += $wCtp;
                $matchedCount++;

                if(abs($pAmt - $wAmt) > 0.01 || abs($pComm - $wCtp) > 0.01){
                    $mismatchFound = true;
                    $mismatches[] = [
                        'ref' => $pdata['__ref_raw'] ?? $ref,
                        'partner_principal' => $pAmt,
                        'web_amount' => $wAmt,
                        'partner_commission' => $pComm,
                        'web_ctp' => $wCtp,
                    ];
                }
            } else {
                $missingFound = true;
                $missing_web_refs[] = $pdata['__ref_raw'] ?? $ref;
            }
        }

        foreach($webByRef as $ref => $wdata){
            if(!isset($partnersByRef[$ref])){
                $missingFound = true;
                $missing_partner_refs[] = $wdata['__ref_raw'] ?? $ref;
            }
        }

        if($status !== 'yellow'){
            if($hasPartner && $hasWeb){
                $status = ($missingFound || $mismatchFound) ? 'red' : 'green';
            } else {
                $status = 'white';
            }
        }

        if($status === 'red') $tooltip = 'Mismatch detected';
        elseif($status === 'white') $tooltip = 'No matching data uploaded for this date';
        elseif($status === 'green') $tooltip = 'Matched / Reconciled';
        elseif($status === 'yellow') $tooltip = 'Duplicate Reference/CCREF detected';

        $dayPayload = [
            'day' => $d,
            'date' => $dt,
            'status' => $status,
            'partner' => 'RCBC',
            'principal' => round($matchedPrincipal,2),
            'commission' => round($matchedCommission,2),
            'web_principal' => round($matchedWebAmount,2),
            'web_commission' => round($matchedWebCtp,2),
            'total_partner_amount' => round($totalPartnerAmount,2),
            'total_web_amount' => round($totalWebAmount,2),
            'variance' => round($totalPartnerAmount - $totalWebAmount,2),
            'vol' => $matchedCount,
            'tooltip' => $tooltip,
            'missing_web_refs' => array_values($missing_web_refs),
            'missing_partner_refs' => array_values($missing_partner_refs),
            'mismatches' => $mismatches,
            'duplicates' => $duplicates,
        ];

        if($detail && $reqDay && $reqDay === $d){
            $rows = [];
            $fullPartSqls = [
                'SELECT * FROM rcbc_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ?',
                'SELECT * FROM rcbc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ?',
            ];
            $fullWebSqls = [
                'SELECT * FROM rcbc_web_data WHERE DATE(date_claimed) = ? OR date_claimed LIKE ?',
            ];

            $fullParts = $tryQuery($fullPartSqls, [$dt, $likeParam]);
            $fullWebs = $tryQuery($fullWebSqls, [$dt, $likeParam]);

            $partsMap = [];
            foreach($fullParts as $p){
                $rawRef = isset($p['transaction_id']) ? (string)$p['transaction_id'] : (isset($p['reference_no']) ? (string)$p['reference_no'] : '');
                $key = trim($rawRef);
                if(function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $partsMap[$key][] = $p;
            }

            $websMap = [];
            foreach($fullWebs as $w){
                $rawRef = isset($w['ccref_no']) ? (string)$w['ccref_no'] : '';
                $key = trim($rawRef);
                if(function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $websMap[$key][] = $w;
            }

            $allKeys = array_unique(array_merge(array_keys($partsMap), array_keys($websMap)));
            $detailMatchedCount = 0;
            foreach($allKeys as $key){
                $pEntries = $partsMap[$key] ?? [];
                $wEntries = $websMap[$key] ?? [];
                $max = max(count($pEntries), count($wEntries));

                for($i=0;$i<$max;$i++){
                    $p = $pEntries[$i] ?? null;
                    $w = $wEntries[$i] ?? null;
                    $rawRef = $p['reference_no'] ?? ($p['transaction_id'] ?? ($w['ccref_no'] ?? $key));
                    $row = ['ref' => $rawRef];

                    if($p){
                        foreach($p as $col => $val){ $row['partner_'.$col] = $val; }
                        $row['partner_principal'] = isset($p['amount']) ? (float)$p['amount'] : (isset($p['php']) ? (float)$p['php'] : 0.0);
                        $row['partner_commission'] = isset($p['in_php']) ? (float)$p['in_php'] : 0.0;
                    } else {
                        $row['partner_principal'] = 0.0;
                        $row['partner_commission'] = 0.0;
                    }

                    if($w){
                        foreach($w as $col => $val){ $row['web_'.$col] = $val; }
                        $row['web_amount'] = isset($w['amount']) ? (float)$w['amount'] : 0.0;
                        $row['web_ctp'] = isset($w['ctp']) ? (float)$w['ctp'] : 0.0;
                    } else {
                        $row['web_amount'] = 0.0;
                        $row['web_ctp'] = 0.0;
                    }

                    if($p && $w) $detailMatchedCount++;
                    $rows[] = $row;
                }
            }

            $dayPayload['rows'] = $rows;
            $dayPayload['matchedCount'] = $detailMatchedCount;
            $dayPayload['unmatchedCount'] = count($missing_web_refs) + count($missing_partner_refs) + count($mismatches);
        }

        $days[] = $dayPayload;
    }

    echo json_encode(['success'=>true,'days'=>$days]);
    exit;

}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
