<?php
// wic-recon.php
// Returns per-day recon summary for WORLD INTERNATIONAL COMMUNICATIONS (used by the UI)

// use shared DB helpers
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// will use fileRecDbConnection() from config/db.php

try{
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
    if($month < 1 || $month > 12 || $year < 2000){ echo json_encode(['success'=>false,'error'=>'Invalid month/year']); exit; }

    $partnerNameInput = isset($_GET['partnerName']) ? trim((string)$_GET['partnerName']) : '';
    $partnerNameUpper = strtoupper($partnerNameInput);
    $wicAliases = ['WIC', 'WORLDCOM INTERNATIONAL COMMUNICATIONS', 'WORLD INTERNATIONAL COMMUNICATIONS'];
    if($partnerNameUpper === '' || in_array($partnerNameUpper, $wicAliases, true)){
        $partnerNameList = $wicAliases;
        $partnerNameLabel = 'WORLDCOM INTERNATIONAL COMMUNICATIONS';
    } else {
        $partnerNameList = [$partnerNameUpper];
        $partnerNameLabel = $partnerNameInput;
    }
    $partnerInPlaceholders = implode(',', array_fill(0, count($partnerNameList), '?'));

    $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    $pdo = fileRecDbConnection();

    $days = [];

    // Helper: try multiple SQL variants until one succeeds (handles slight schema differences)
    $tryQuery = function(array $sqls, array $params) use ($pdo){
        foreach($sqls as $sql){
            try{
                $s = $pdo->prepare($sql);
                $s->execute($params);
                return $s->fetchAll();
            }catch(PDOException $e){
                // try next variant on unknown-column errors, rethrow otherwise
                $code = $e->getCode();
                if(strpos($e->getMessage(),'Unknown column') === false && $code !== '42S22'){
                    throw $e;
                }
                // otherwise continue to next SQL variant
            }
        }
        return [];
    };

    // Prepare SQL variants for partner aggregates targeting the simplified partner schema:
    // transaction_id, date, amount, coin
    // Try USD-excluding variants first (if currency/coin columns exist), then fall back to original queries.
    $sqlsPart = [
        // exclude rows where partner coin/currency indicates USD (likely column names)
        "SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE (DATE(`date`) = ? OR `date` LIKE ?) AND UPPER(COALESCE(coin,'')) <> 'USD' GROUP BY transaction_id",
        "SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE (DATE(cover_date) = ? OR cover_date LIKE ?) AND UPPER(COALESCE(coin,'')) <> 'USD' GROUP BY transaction_id",
        // alternate coin column names
        "SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE (DATE(`date`) = ? OR `date` LIKE ?) AND UPPER(COALESCE(partner_coin,'')) <> 'USD' GROUP BY transaction_id",
        "SELECT reference_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE (DATE(`date`) = ? OR `date` LIKE ?) AND UPPER(COALESCE(partner_coin,'')) <> 'USD' GROUP BY reference_no",
        "SELECT ref_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE (DATE(`date`) = ? OR `date` LIKE ?) AND UPPER(COALESCE(currency,'')) <> 'USD' GROUP BY ref_no",
        // fall back to queries without currency filter (older schemas)
        'SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY transaction_id',
        'SELECT transaction_id AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY transaction_id',
        // fallbacks to older column names if present
        'SELECT reference_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY reference_no',
        'SELECT ref_no AS tx, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS partner_amount FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ? GROUP BY ref_no',
    ];
    $sqlsWeb = [
        // use consolidated ml_web_data, filtered by partnerName aliases for WIC
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) AND UPPER(COALESCE(currency,'')) <> 'USD' GROUP BY ccref_no",
        "SELECT cc_ref AS ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) AND UPPER(COALESCE(currency,'')) <> 'USD' GROUP BY cc_ref",
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN ($partnerInPlaceholders) AND UPPER(COALESCE(currency,'')) <> 'USD' GROUP BY ccref_no",
        // fallback if partner column is partner_name in older/variant schemas
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN ($partnerInPlaceholders) AND UPPER(COALESCE(currency,'')) <> 'USD' GROUP BY ccref_no",
        // alternate currency/coin column guesses
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) AND UPPER(COALESCE(web_currency,'')) <> 'USD' GROUP BY ccref_no",
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) AND UPPER(COALESCE(coin,'')) <> 'USD' GROUP BY ccref_no",
        // fall back to queries without currency filter
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY ccref_no",
        "SELECT cc_ref AS ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY cc_ref",
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY ccref_no",
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN ($partnerInPlaceholders) GROUP BY ccref_no",
    ];

    $detail = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $reqDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;

    for($d=1;$d<=$daysInMonth;$d++){
        $dt = sprintf('%04d-%02d-%02d', $year, $month, $d);

        // fetch partner aggregates (try exact date first, fallback to LIKE)
        $likeParam = '%' . $dt . '%';
        $parts = $tryQuery($sqlsPart, [$dt, $likeParam]);
        $partnersByRef = [];
        foreach($parts as $p){
            // use transaction id (tx) as key when available
            $rawRef = isset($p['tx']) ? (string)$p['tx'] : (isset($p['reference_no']) ? (string)$p['reference_no'] : '');
            $key = trim($rawRef);
            if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
            if ($key === '') continue;
            $p['__ref_raw'] = $rawRef;
            // ensure consistent numeric alias used downstream
            if(isset($p['partner_amount'])) $p['partner_principal'] = $p['partner_amount'];
            $partnersByRef[$key] = $p;
        }

        // fetch web aggregates
        $webs = $tryQuery($sqlsWeb, array_merge([$dt, $likeParam], $partnerNameList));
        $webByRef = [];
        foreach($webs as $w){
            $rawRef = isset($w['ccref_no']) ? (string)$w['ccref_no'] : '';
            $key = trim($rawRef);
            if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
            if ($key === '') continue;
            $w['__ref_raw'] = $rawRef;
            $webByRef[$key] = $w;
        }

        // determine status
        $status = 'white';
        $tooltip = '';
        $hasPartner = count($partnersByRef) > 0;
        $hasWeb = count($webByRef) > 0;

        // detect duplicates
        $hasDuplicate = false;
        foreach($partnersByRef as $pr){ if((int)$pr['cnt'] > 1) { $hasDuplicate = true; break; } }
        if(!$hasDuplicate){ foreach($webByRef as $wr){ if((int)$wr['cnt'] > 1) { $hasDuplicate = true; break; } } }
        if($hasDuplicate) $status = 'yellow';

        // detect mismatches / missing and build diagnostics
        $mismatchFound = false;
        $missingFound = false;
        $matchedPrincipal = 0.0;
        $matchedCommission = 0.0;
        $matchedWebAmount = 0.0;
        $matchedWebCtp = 0.0;
        $matchedCount = 0;

        // per-day totals across all rows/refs (used for variance computation)
        $totalPartnerAmount = 0.0;
        $totalWebAmount = 0.0;

        foreach($partnersByRef as $pdata){
            // partner_principal is mapped above from partner_amount
            $totalPartnerAmount += (float)($pdata['partner_principal'] ?? 0);
        }
        foreach($webByRef as $wdata){
            $totalWebAmount += (float)($wdata['web_amount'] ?? 0);
        }

        $missing_web_refs = []; // partner refs not found in web
        $missing_partner_refs = []; // web refs not found in partner
        $mismatched_refs = []; // refs with amount/commission mismatches
        $duplicate_refs = []; // refs that appear multiple times

        // detect partner duplicates
        foreach($partnersByRef as $ref => $pdata){ if((int)$pdata['cnt'] > 1) $duplicate_refs[] = ['type'=>'partner','ref'=>$pdata['__ref_raw'] ?? $ref,'count'=>(int)$pdata['cnt']]; }
        // detect web duplicates
        foreach($webByRef as $ref => $wdata){ if((int)$wdata['cnt'] > 1) $duplicate_refs[] = ['type'=>'web','ref'=>$wdata['__ref_raw'] ?? $ref,'count'=>(int)$wdata['cnt']]; }

        // iterate partner refs to find matches and mismatches
        foreach($partnersByRef as $ref => $pdata){
            if(isset($webByRef[$ref])){
                // Match solely by transaction id / CCREF (key equality). Do not treat amount differences as mismatches.
                $w = $webByRef[$ref];
                $pAmt = (float)$pdata['partner_principal'];
                $wAmt = (float)$w['web_amount'];
                // WORLD INTERNATIONAL COMMUNICATIONS partner has no separate commission column in simplified schema; use web ctp as commission metric
                $pCtp = 0.0;
                $wCtp = (float)$w['web_ctp'];
                $matchedPrincipal += $pAmt;
                $matchedWebAmount += $wAmt;
                $matchedCommission += $wCtp; // report commission based on web ctp
                $matchedWebCtp += $wCtp;
                $matchedCount++;
                // Do not mark mismatches based on amount/ctp differences; matching is determined by transaction id only.
            } else {
                $missingFound = true;
                $missing_web_refs[] = $pdata['__ref_raw'] ?? $ref;
            }
        }

        // web refs without partner counterparts
        foreach($webByRef as $ref => $wdata){ if(!isset($partnersByRef[$ref])){ $missingFound = true; $missing_partner_refs[] = $wdata['__ref_raw'] ?? $ref; } }

        if($status !== 'yellow'){
            if($hasPartner && !$hasWeb){ $status = 'white'; }
            else if($hasPartner && $hasWeb){
                if($missingFound || $mismatchFound) $status = 'red'; else $status = 'green';
            } else if(!$hasPartner && $hasWeb){ $status = 'white'; }
            else $status = 'white';
        }

        if($status === 'red'){
            $tooltip = 'Mismatch detected';
        } elseif($status === 'white'){
            $tooltip = 'No matching data uploaded for this date';
        } elseif($status === 'green'){
            $tooltip = 'Matched / Reconciled';
        } elseif($status === 'yellow'){
            $tooltip = 'Duplicate Reference/CCREF detected';
        }

        // build basic day payload
        $dayPayload = [
            'day' => $d,
            'date' => $dt,
            'status' => $status,
            'partner' => $partnerNameLabel,
            'principal' => round($matchedPrincipal,2),
            // show commission based on web-side CTP as partner has no commission column in simplified schema
            'commission' => round($matchedWebCtp,2),
            'web_principal' => round($matchedWebAmount,2),
            'web_commission' => round($matchedWebCtp,2),
            'total_partner_amount' => round($totalPartnerAmount,2),
            'total_web_amount' => round($totalWebAmount,2),
            'variance' => round($totalPartnerAmount - $totalWebAmount,2),
            'vol' => $matchedCount,
            'tooltip' => $tooltip,
            'missing_web_refs' => array_values($missing_web_refs),
            'missing_partner_refs' => array_values($missing_partner_refs),
            'mismatches' => $mismatched_refs,
            'duplicates' => $duplicate_refs,
        ];

        // if detail requested for this specific day, include row-level matches/unmatched
        if($detail && $reqDay && $reqDay === $d){
            $rows = [];
            // Full row variants - prefer `date`/`transaction_id` simplified schema but allow fallbacks
            $sqlFullPartVariants = [
                'SELECT * FROM wic_partner_data WHERE DATE(`date`) = ? OR `date` LIKE ?',
                'SELECT * FROM wic_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ?',
                'SELECT * FROM wic_partner_data WHERE DATE(date) = ? OR date LIKE ?',
            ];
            $sqlFullWebVariants = [
                "SELECT * FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders)",
                "SELECT * FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN ($partnerInPlaceholders)",
                "SELECT * FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN ($partnerInPlaceholders)",
            ];
            $fullParts = $tryQuery($sqlFullPartVariants, [$dt, $likeParam]);
            $fullWebs = $tryQuery($sqlFullWebVariants, array_merge([$dt, $likeParam], $partnerNameList));

            $partsMap = [];
            foreach($fullParts as $p){
                // prefer transaction_id, fallback to reference_no/ref_no
                $rawRef = isset($p['transaction_id']) ? (string)$p['transaction_id'] : (isset($p['reference_no']) ? (string)$p['reference_no'] : (isset($p['ref_no']) ? (string)$p['ref_no'] : ''));
                $key = trim($rawRef);
                if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $partsMap[$key][] = $p;
            }
            $websMap = [];
            foreach($fullWebs as $w){
                // prefer ccref_no, fallback to cc_ref
                $rawRef = isset($w['ccref_no']) ? (string)$w['ccref_no'] : (isset($w['cc_ref']) ? (string)$w['cc_ref'] : '');
                $key = trim($rawRef);
                if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $websMap[$key][] = $w;
            }

            $allKeys = array_unique(array_merge(array_keys($partsMap), array_keys($websMap)));
            $matchedCount = 0;
            foreach($allKeys as $key){
                $pEntries = $partsMap[$key] ?? [];
                $wEntries = $websMap[$key] ?? [];

                $max = max(count($pEntries), count($wEntries));
                for($i=0;$i<$max;$i++){
                    $p = $pEntries[$i] ?? null;
                    $w = $wEntries[$i] ?? null;
                    $rawRef = $p['reference_no'] ?? ($w['ccref_no'] ?? $key);
                    $row = ['ref' => $rawRef];

                    if($p){
                        foreach($p as $col => $val){
                            $row['partner_'.$col] = $val;
                        }
                        // map simplified partner amount and transaction id
                        $row['partner_principal'] = isset($p['amount']) ? (float)$p['amount'] : (isset($p['partner_principal']) ? (float)$p['partner_principal'] : 0.0);
                        $row['partner_transaction_id'] = isset($p['transaction_id']) ? $p['transaction_id'] : (isset($p['reference_no']) ? $p['reference_no'] : '');
                        $row['partner_coin'] = isset($p['coin']) ? $p['coin'] : '';
                    } else {
                        $row['partner_principal'] = 0.0;
                        $row['partner_transaction_id'] = '';
                        $row['partner_coin'] = '';
                    }

                    if($w){
                        foreach($w as $col => $val){
                            $row['web_'.$col] = $val;
                        }
                        $row['web_amount'] = isset($w['amount']) ? (float)$w['amount'] : (isset($w['web_amount']) ? (float)$w['web_amount'] : 0.0);
                        $row['web_ctp'] = isset($w['ctp']) ? (float)$w['ctp'] : (isset($w['web_ctp']) ? (float)$w['web_ctp'] : 0.0);
                    } else {
                        $row['web_amount'] = 0.0;
                        $row['web_ctp'] = 0.0;
                    }

                    if($p && $w) $matchedCount++;
                    $rows[] = $row;
                }
            }

            $dayPayload['rows'] = $rows;
            $dayPayload['matchedCount'] = $matchedCount;
            $dayPayload['unmatchedCount'] = count($missing_web_refs) + count($missing_partner_refs) + count($mismatched_refs);
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
