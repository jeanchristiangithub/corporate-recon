<?php
// mbtc-recon.php
// Returns per-day recon summary for MBTC (used by the UI)

// use shared DB helpers
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// will use fileRecDbConnection() from config/db.php

try{
    $startDateInput = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $endDateInput = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    // Backward compatibility: allow month/year callers to continue working.
    if($startDateInput === '' && $endDateInput === ''){
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
        if($month >= 1 && $month <= 12 && $year >= 2000){
            $startDateInput = sprintf('%04d-%02d-01', $year, $month);
            $endDateInput = date('Y-m-t', strtotime($startDateInput));
        }
    }

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput)){
        echo json_encode(['success'=>false,'error'=>'Invalid start/end date']);
        exit;
    }

    $startDtObj = DateTime::createFromFormat('Y-m-d', $startDateInput);
    $endDtObj = DateTime::createFromFormat('Y-m-d', $endDateInput);
    if(!$startDtObj || !$endDtObj){
        echo json_encode(['success'=>false,'error'=>'Invalid start/end date']);
        exit;
    }

    $startDate = $startDtObj->format('Y-m-d');
    $endDate = $endDtObj->format('Y-m-d');
    if($startDate > $endDate){
        echo json_encode(['success'=>false,'error'=>'Start date cannot be greater than end date']);
        exit;
    }

    $partnerNameInput = isset($_GET['partnerName']) ? trim((string)$_GET['partnerName']) : '';
    $partnerNameUpper = strtoupper($partnerNameInput);
    $mbtcAliases = ['MBTC', 'METROBANK HEAD OFFICE'];
    if($partnerNameUpper === '' || in_array($partnerNameUpper, $mbtcAliases, true)){
        $partnerNameList = $mbtcAliases;
        $partnerNameLabel = 'METROBANK HEAD OFFICE';
    } else {
        $partnerNameList = [$partnerNameUpper];
        $partnerNameLabel = $partnerNameInput;
    }
    $partnerInPlaceholders = implode(',', array_fill(0, count($partnerNameList), '?'));

    $dateKeys = [];
    $cursor = clone $startDtObj;
    while($cursor->format('Y-m-d') <= $endDate){
        $dateKeys[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

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
                $code = $e->getCode();
                if(strpos($e->getMessage(),'Unknown column') === false && $code !== '42S22'){
                    throw $e;
                }
            }
        }
        return [];
    };

    $sqlsPart = [
        'SELECT reference_no, COUNT(*) AS cnt, SUM(COALESCE(`php`,0)) AS partner_principal, SUM(COALESCE(in_php,0)) AS partner_commission FROM mbtc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ? GROUP BY reference_no',
    ];
    $sqlsWeb = [
        // consolidated web data source with partner filter
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY ccref_no",
        "SELECT cc_ref AS ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY cc_ref",
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN ($partnerInPlaceholders) GROUP BY ccref_no",
        // fallback if schema uses partner_name
        "SELECT ccref_no, COUNT(*) AS cnt, SUM(COALESCE(amount,0)) AS web_amount, SUM(COALESCE(ctp,0)) AS web_ctp FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN ($partnerInPlaceholders) GROUP BY ccref_no",
    ];

    $detail = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $reqDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;
    $reqDate = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
    if($reqDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate)) $reqDate = '';

    for($idx=0;$idx<count($dateKeys);$idx++){
        $d = $idx + 1;
        $dt = $dateKeys[$idx];

        // fetch partner aggregates (try exact date first, fallback to LIKE)
        $likeParam = '%' . $dt . '%';
        $parts = $tryQuery($sqlsPart, [$dt, $likeParam]);
        $partnersByRef = [];
        foreach($parts as $p){
            $rawRef = isset($p['reference_no']) ? (string)$p['reference_no'] : '';
            $key = trim($rawRef);
            if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
            if ($key === '') continue;
            $p['__ref_raw'] = $rawRef;
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
                $w = $webByRef[$ref];
                $pAmt = (float)$pdata['partner_principal'];
                $wAmt = (float)$w['web_amount'];
                $pCtp = (float)$pdata['partner_commission'];
                $wCtp = (float)$w['web_ctp'];
                $matchedPrincipal += $pAmt;
                $matchedWebAmount += $wAmt;
                $matchedCommission += $pCtp;
                $matchedWebCtp += $wCtp;
                $matchedCount++;
                if(abs($pAmt - $wAmt) > 0.01 || abs($pCtp - $wCtp) > 0.01){
                    $mismatchFound = true;
                    $mismatched_refs[] = ['ref'=>$pdata['__ref_raw'] ?? $ref,'partner_principal'=>$pAmt,'web_amount'=>$wAmt,'partner_commission'=>$pCtp,'web_ctp'=>$wCtp];
                }
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
            'commission' => round($matchedCommission,2),
            // include web-side totals so UI can render partner / web columns in the Cover PH view
            'web_principal' => round($matchedWebAmount,2),
            'web_commission' => round($matchedWebCtp,2),
            // per-day totals for amount-based metrics
            'total_partner_amount' => round($totalPartnerAmount,2),
            'total_web_amount' => round($totalWebAmount,2),
            // variance per day: total partner amount - total web amount
            'variance' => round($totalPartnerAmount - $totalWebAmount,2),
            'vol' => $matchedCount,
            'tooltip' => $tooltip,
            'missing_web_refs' => array_values($missing_web_refs),
            'missing_partner_refs' => array_values($missing_partner_refs),
            'mismatches' => $mismatched_refs,
            'duplicates' => $duplicate_refs,
        ];

        // if detail requested for this specific day, include row-level matches/unmatched
        if($detail && (($reqDate !== '' && $reqDate === $dt) || ($reqDate === '' && $reqDay && $reqDay === $d))){
            // For detail view, fetch full partner/web rows (not just aggregates) so UI can display all extracted columns
            $rows = [];
            // fetch full partner rows for this date
            $sqlFullPart = ['SELECT * FROM mbtc_partner_data WHERE DATE(cover_date) = ? OR cover_date LIKE ?'];
            $sqlFullWeb = [
                "SELECT * FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partnerName IN ($partnerInPlaceholders)",
                "SELECT * FROM ml_web_data WHERE (DATE(date) = ? OR date LIKE ?) AND partnerName IN ($partnerInPlaceholders)",
                "SELECT * FROM ml_web_data WHERE (DATE(date_claimed) = ? OR date_claimed LIKE ?) AND partner_name IN ($partnerInPlaceholders)",
            ];
            $fullParts = $tryQuery($sqlFullPart, [$dt, $likeParam]);
            $fullWebs = $tryQuery($sqlFullWeb, array_merge([$dt, $likeParam], $partnerNameList));

            // map by normalized ref
            $partsMap = [];
            foreach($fullParts as $p){
                $rawRef = isset($p['reference_no']) ? (string)$p['reference_no'] : '';
                $key = trim($rawRef);
                if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $partsMap[$key][] = $p; // allow duplicates
            }
            $websMap = [];
            foreach($fullWebs as $w){
                $rawRef = isset($w['ccref_no']) ? (string)$w['ccref_no'] : '';
                $key = trim($rawRef);
                if (function_exists('mb_strtoupper')) $key = mb_strtoupper($key); else $key = strtoupper($key);
                if($key === '') continue;
                $websMap[$key][] = $w;
            }

            // union keys
            $allKeys = array_unique(array_merge(array_keys($partsMap), array_keys($websMap)));
            $matchedCount = 0;
            foreach($allKeys as $key){
                $pEntries = $partsMap[$key] ?? [];
                $wEntries = $websMap[$key] ?? [];

                // handle multiple entries: pair them by index where possible; otherwise create rows for each side
                $max = max(count($pEntries), count($wEntries));
                for($i=0;$i<$max;$i++){
                    $p = $pEntries[$i] ?? null;
                    $w = $wEntries[$i] ?? null;
                    $rawRef = $p['reference_no'] ?? ($w['ccref_no'] ?? $key);
                    $row = ['ref' => $rawRef];

                    // include partner columns with partner_ prefix
                    if($p){
                        foreach($p as $col => $val){
                            $row['partner_'.$col] = $val;
                        }
                        // keep numeric aggregates for compatibility
                        $row['partner_principal'] = isset($p['php']) ? (float)$p['php'] : (isset($p['partner_principal']) ? (float)$p['partner_principal'] : 0.0);
                        $row['partner_commission'] = isset($p['in_php']) ? (float)$p['in_php'] : (isset($p['partner_commission']) ? (float)$p['partner_commission'] : 0.0);
                    } else {
                        $row['partner_principal'] = 0.0;
                        $row['partner_commission'] = 0.0;
                    }

                    // include web columns with web_ prefix
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

    echo json_encode(['success'=>true,'start_date'=>$startDate,'end_date'=>$endDate,'days'=>$days]);
    exit;

}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
