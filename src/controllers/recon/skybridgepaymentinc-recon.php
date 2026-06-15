<?php
// skybridgepaymentinc-recon.php
// Returns per-day recon summary for SKYBRIDGE PAYMENT INC. (used by the UI)

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try{
    $startDateInput = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
    $endDateInput = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

    if($startDateInput === '' && $endDateInput === ''){
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
        if($month >= 1 && $month <= 12 && $year >= 2000){
            $startDateInput = sprintf('%04d-%02d-01', $year, $month);
            $endDateInput = date('Y-m-t', strtotime($startDateInput));
        }
    }

    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput)){
        echo json_encode(['success' => false, 'error' => 'Invalid start/end date']);
        exit;
    }

    $startDtObj = DateTime::createFromFormat('Y-m-d', $startDateInput);
    $endDtObj = DateTime::createFromFormat('Y-m-d', $endDateInput);
    if(!$startDtObj || !$endDtObj){
        echo json_encode(['success' => false, 'error' => 'Invalid start/end date']);
        exit;
    }

    $startDate = $startDtObj->format('Y-m-d');
    $endDate = $endDtObj->format('Y-m-d');
    if($startDate > $endDate){
        echo json_encode(['success' => false, 'error' => 'Start date cannot be greater than end date']);
        exit;
    }

    $partnerNameInput = isset($_GET['partnerName']) ? trim((string)$_GET['partnerName']) : '';
    $partnerNameUpper = strtoupper($partnerNameInput);
    $skybridgeAliases = ['SKYBRIDGE', 'SKYBRIDGEPAYMENTINC', 'SKYBRIDGE PAYMENT INC.', 'SKYBRIDGEPAYMENTINC CORPORATE'];
    if($partnerNameUpper === '' || in_array($partnerNameUpper, $skybridgeAliases, true)){
        $partnerNameList = $skybridgeAliases;
        $partnerNameLabel = 'SKYBRIDGE PAYMENT INC.';
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

    $tryQuery = function(array $sqls, array $params) use ($pdo){
        foreach($sqls as $sql){
            try{
                $statement = $pdo->prepare($sql);
                $statement->execute($params);
                return $statement->fetchAll();
            }catch(PDOException $e){
                $code = $e->getCode();
                if(strpos($e->getMessage(), 'Unknown column') === false && $code !== '42S22'){
                    throw $e;
                }
            }
        }
        return [];
    };

    $detail = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
    $rangeDetail = isset($_GET['range_detail']) ? (int)$_GET['range_detail'] : 0;
    $reqDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;
    $reqDate = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
    if($reqDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate)){
        $reqDate = '';
    }

    $normalizeKey = function($value){
        $key = trim((string)$value);
        if($key === '') return '';
        return function_exists('mb_strtoupper') ? mb_strtoupper($key) : strtoupper($key);
    };
    $normalizeDate = function($value){
        $raw = trim((string)$value);
        if($raw === '') return '';
        $ts = strtotime($raw);
        if($ts === false) return '';
        return date('Y-m-d', $ts);
    };
    $normalizeCurrency = function($value) use ($normalizeKey){
        return $normalizeKey($value);
    };
    $amountsEqual = function($left, $right){
        return abs(abs((float)$left) - abs((float)$right)) < 0.00001;
    };
    $dateDiffDays = function($left, $right){
        $leftTs = strtotime((string)$left);
        $rightTs = strtotime((string)$right);
        if($leftTs === false || $rightTs === false) return PHP_INT_MAX;
        return (int)round(($rightTs - $leftTs) / 86400);
    };

    $expandedStartObj = clone $startDtObj;
    $expandedStartObj->modify('-1 day');
    $expandedEndObj = clone $endDtObj;
    $expandedEndObj->modify('+1 day');
    $expandedStartDate = $expandedStartObj->format('Y-m-d');
    $expandedEndDate = $expandedEndObj->format('Y-m-d');

    $sqlRangePart = [
        'SELECT *, control_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(trans_date) BETWEEN ? AND ?',
        'SELECT *, control_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(withdraw_time) BETWEEN ? AND ?',
        'SELECT *, control_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(`date`) BETWEEN ? AND ?',
        'SELECT *, control_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(cover_date) BETWEEN ? AND ?',
        'SELECT *, reference_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(`date`) BETWEEN ? AND ?',
        'SELECT *, reference_no AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(cover_date) BETWEEN ? AND ?',
        'SELECT *, transaction_id AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(`date`) BETWEEN ? AND ?',
        'SELECT *, transaction_id AS skybridge_ref FROM skybridgepaymentinc_partner_data WHERE DATE(cover_date) BETWEEN ? AND ?',
    ];
    $sqlRangeWeb = [
        "SELECT * FROM ml_web_data WHERE DATE(date_claimed) BETWEEN ? AND ? AND partnerName IN ($partnerInPlaceholders)",
        "SELECT *, cc_ref AS ccref_no FROM ml_web_data WHERE DATE(date_claimed) BETWEEN ? AND ? AND partnerName IN ($partnerInPlaceholders)",
        "SELECT * FROM ml_web_data WHERE DATE(date) BETWEEN ? AND ? AND partnerName IN ($partnerInPlaceholders)",
        "SELECT * FROM ml_web_data WHERE DATE(date_claimed) BETWEEN ? AND ? AND partner_name IN ($partnerInPlaceholders)",
        "SELECT *, cc_ref AS ccref_no FROM ml_web_data WHERE DATE(date_claimed) BETWEEN ? AND ? AND partner_name IN ($partnerInPlaceholders)",
    ];

    $partnerRowsRaw = $tryQuery($sqlRangePart, [$startDate, $endDate]);
    $webRowsRaw = $tryQuery($sqlRangeWeb, array_merge([$expandedStartDate, $expandedEndDate], $partnerNameList));
    $webRowsRaw = array_values(array_filter($webRowsRaw, function($row) use ($partnerNameList, $normalizeKey){
        $rowPartner = $row['partnerName'] ?? ($row['partner_name'] ?? ($row['corporate_partner'] ?? ($row['corporatePartner'] ?? '')));
        $rowPartnerKey = $normalizeKey($rowPartner);
        if($rowPartnerKey === '') return true;
        return in_array($rowPartnerKey, $partnerNameList, true);
    }));

    $partnerRows = [];
    foreach($partnerRowsRaw as $index => $row){
        $dateOnly = $normalizeDate($row['trans_date'] ?? ($row['withdraw_time'] ?? ($row['date'] ?? ($row['cover_date'] ?? ($row['transaction_date'] ?? '')))));
        if($dateOnly === '' || $dateOnly < $startDate || $dateOnly > $endDate) continue;

        $rawRef = (string)($row['skybridge_ref'] ?? ($row['control_no'] ?? ($row['reference_no'] ?? ($row['transaction_id'] ?? ''))));
        $ref = $normalizeKey($rawRef);
        if($ref === '') continue;
        $partnerAmount = 0.0;
        foreach(['amount', 'php', 'partner_amount', 'payout_amount_php'] as $amountColumn){
            if(isset($row[$amountColumn]) && trim((string)$row[$amountColumn]) !== ''){
                $partnerAmount = abs((float)str_replace(',', '', (string)$row[$amountColumn]));
                break;
            }
        }

        $partnerRows[] = [
            'index' => $index,
            'date' => $dateOnly,
            'ref' => $ref,
            'raw_ref' => $rawRef,
            'amount' => $partnerAmount,
            'commission' => isset($row['in_php']) ? (float)$row['in_php'] : (isset($row['commission']) ? (float)$row['commission'] : 0.0),
            'currency' => $normalizeCurrency($row['currency'] ?? ($row['coin'] ?? 'PHP')),
            'raw' => $row,
        ];
    }

    $webRows = [];
    foreach($webRowsRaw as $index => $row){
        $dateOnly = $normalizeDate($row['date_claimed'] ?? ($row['date'] ?? ''));
        if($dateOnly === '' || $dateOnly < $expandedStartDate || $dateOnly > $expandedEndDate) continue;

        $rawRef = (string)($row['ccref_no'] ?? ($row['cc_ref'] ?? ''));
        $ref = $normalizeKey($rawRef);
        if($ref === '') continue;

        $webRows[] = [
            'index' => $index,
            'date' => $dateOnly,
            'ref' => $ref,
            'raw_ref' => $rawRef,
            'kptn' => (string)($row['kptn'] ?? ''),
            'amount' => isset($row['amount']) ? (float)$row['amount'] : (isset($row['web_amount']) ? (float)$row['web_amount'] : 0.0),
            'ctp' => isset($row['ctp']) ? (float)$row['ctp'] : (isset($row['web_ctp']) ? (float)$row['web_ctp'] : 0.0),
            'currency' => $normalizeCurrency($row['currency'] ?? ''),
            'raw' => $row,
        ];
    }

    usort($partnerRows, function($left, $right){
        if($left['date'] !== $right['date']) return strcmp($left['date'], $right['date']);
        return $left['index'] <=> $right['index'];
    });
    usort($webRows, function($left, $right){
        if($left['date'] !== $right['date']) return strcmp($left['date'], $right['date']);
        return $left['index'] <=> $right['index'];
    });

    $webIndexesByRef = [];
    foreach($webRows as $webIndex => $webRow){
        if(!isset($webIndexesByRef[$webRow['ref']])) $webIndexesByRef[$webRow['ref']] = [];
        $webIndexesByRef[$webRow['ref']][] = $webIndex;
    }

    $pairRecordsByDay = [];
    foreach($dateKeys as $dateKey){
        $pairRecordsByDay[$dateKey] = [];
    }

    $usedWebIndexes = [];
    foreach($partnerRows as $partnerRow){
        $groupDate = $partnerRow['date'];
        $bestWebIndex = null;
        $bestDateDistance = PHP_INT_MAX;
        $bestWebDate = '';

        foreach(($webIndexesByRef[$partnerRow['ref']] ?? []) as $candidateIndex){
            if(isset($usedWebIndexes[$candidateIndex])) continue;

            $webRow = $webRows[$candidateIndex];
            if($webRow['date'] < $startDate || $webRow['date'] > $endDate) continue;
            $dateDistance = abs($dateDiffDays($partnerRow['date'], $webRow['date']));
            if($dateDistance > 1) continue;
            if(!$amountsEqual($partnerRow['amount'], $webRow['amount'])) continue;
            if($partnerRow['currency'] !== '' && $webRow['currency'] !== '' && $partnerRow['currency'] !== $webRow['currency']) continue;

            if(
                $bestWebIndex === null ||
                $dateDistance < $bestDateDistance ||
                ($dateDistance === $bestDateDistance && strcmp($webRow['date'], $bestWebDate) < 0)
            ){
                $bestWebIndex = $candidateIndex;
                $bestDateDistance = $dateDistance;
                $bestWebDate = $webRow['date'];
            }
        }

        $pairRecordsByDay[$groupDate][] = [
            'partner' => $partnerRow,
            'web' => null,
            'sortIndex' => $partnerRow['index'],
        ];

        if($bestWebIndex !== null){
            $usedWebIndexes[$bestWebIndex] = true;
            $lastIndex = count($pairRecordsByDay[$groupDate]) - 1;
            $pairRecordsByDay[$groupDate][$lastIndex]['web'] = $webRows[$bestWebIndex];
        }
    }

    foreach($webRows as $webIndex => $webRow){
        if(isset($usedWebIndexes[$webIndex])) continue;

        $groupDate = $webRow['date'];
        if($groupDate < $startDate || $groupDate > $endDate) continue;

        $pairRecordsByDay[$groupDate][] = [
            'partner' => null,
            'web' => $webRow,
            'sortIndex' => 1000000 + $webRow['index'],
        ];
    }

    $days = [];
    for($idx = 0; $idx < count($dateKeys); $idx++){
        $d = $idx + 1;
        $dt = $dateKeys[$idx];
        $dayPairs = $pairRecordsByDay[$dt] ?? [];
        usort($dayPairs, function($left, $right){
            return ($left['sortIndex'] ?? 0) <=> ($right['sortIndex'] ?? 0);
        });

        $status = 'white';
        $tooltip = '';
        $matchedPrincipal = 0.0;
        $matchedCommission = 0.0;
        $matchedWebAmount = 0.0;
        $matchedWebCtp = 0.0;
        $matchedCount = 0;
        $totalPartnerAmount = 0.0;
        $totalWebAmount = 0.0;
        $missing_web_refs = [];
        $missing_partner_refs = [];
        $mismatched_refs = [];
        $duplicate_refs = [];
        $hasPartner = false;
        $hasWeb = false;
        $partnerRefCounts = [];
        $webRefCounts = [];

        foreach($dayPairs as $pair){
            $partner = $pair['partner'] ?? null;
            $web = $pair['web'] ?? null;

            if($partner){
                $hasPartner = true;
                $totalPartnerAmount += (float)$partner['amount'];
                if($partner['ref'] !== ''){
                    if(!isset($partnerRefCounts[$partner['ref']])) $partnerRefCounts[$partner['ref']] = ['count' => 0, 'raw' => $partner['raw_ref']];
                    $partnerRefCounts[$partner['ref']]['count']++;
                }
            }
            if($web){
                $hasWeb = true;
                $totalWebAmount += (float)$web['amount'];
                if($web['ref'] !== ''){
                    if(!isset($webRefCounts[$web['ref']])) $webRefCounts[$web['ref']] = ['count' => 0, 'raw' => $web['raw_ref']];
                    $webRefCounts[$web['ref']]['count']++;
                }
            }
            if($partner && $web){
                $matchedCount++;
                $matchedPrincipal += (float)$partner['amount'];
                $matchedCommission += (float)$partner['commission'];
                $matchedWebAmount += (float)$web['amount'];
                $matchedWebCtp += (float)$web['ctp'];
            } elseif($partner) {
                $missing_web_refs[] = $partner['raw_ref'];
            } elseif($web) {
                $missing_partner_refs[] = $web['raw_ref'];
            }
        }

        foreach($partnerRefCounts as $refInfo){
            if(($refInfo['count'] ?? 0) > 1){
                $duplicate_refs[] = ['type' => 'partner', 'ref' => $refInfo['raw'], 'count' => (int)$refInfo['count']];
            }
        }
        foreach($webRefCounts as $refInfo){
            if(($refInfo['count'] ?? 0) > 1){
                $duplicate_refs[] = ['type' => 'web', 'ref' => $refInfo['raw'], 'count' => (int)$refInfo['count']];
            }
        }

        $unmatchedCount = count($missing_web_refs) + count($missing_partner_refs) + count($mismatched_refs);
        $hasDuplicate = count($duplicate_refs) > 0;

        if($hasDuplicate){
            $status = 'yellow';
        } elseif($hasPartner && $hasWeb){
            $status = $unmatchedCount > 0 ? 'red' : 'green';
        } elseif($hasPartner || $hasWeb){
            $status = 'white';
        }

        if($status === 'red'){
            $tooltip = 'Mismatch detected';
        } elseif($status === 'white'){
            $tooltip = 'No matching data uploaded for this date';
        } elseif($status === 'green'){
            $tooltip = 'Matched / Reconciled';
        } elseif($status === 'yellow'){
            $tooltip = 'Duplicate Control No/CCREF detected';
        }

        $dayPayload = [
            'day' => $d,
            'date' => $dt,
            'status' => $status,
            'partner' => $partnerNameLabel,
            'principal' => round($matchedPrincipal, 2),
            'commission' => round($matchedCommission, 2),
            'web_principal' => round($matchedWebAmount, 2),
            'web_commission' => round($matchedWebCtp, 2),
            'total_partner_amount' => round($totalPartnerAmount, 2),
            'total_web_amount' => round($totalWebAmount, 2),
            'variance' => round($totalPartnerAmount - $totalWebAmount, 2),
            'vol' => $matchedCount,
            'tooltip' => $tooltip,
            'missing_web_refs' => array_values($missing_web_refs),
            'missing_partner_refs' => array_values($missing_partner_refs),
            'mismatches' => $mismatched_refs,
            'duplicates' => $duplicate_refs,
        ];

        if($detail && ($rangeDetail || (($reqDate !== '' && $reqDate === $dt) || ($reqDate === '' && $reqDay && $reqDay === $d)))){
            $rows = [];
            foreach($dayPairs as $pair){
                $partner = $pair['partner'] ?? null;
                $web = $pair['web'] ?? null;
                $rawRef = $partner['raw_ref'] ?? ($web['raw_ref'] ?? '');
                $row = ['ref' => $rawRef];

                if($partner){
                    foreach(($partner['raw'] ?? []) as $col => $val){
                        $row['partner_'.$col] = $val;
                    }
                    $row['partner_control_no'] = $partner['raw_ref'];
                    $row['partner_principal'] = (float)$partner['amount'];
                    $row['partner_commission'] = (float)$partner['commission'];
                } else {
                    $row['partner_control_no'] = '';
                    $row['partner_principal'] = 0.0;
                    $row['partner_commission'] = 0.0;
                }

                if($web){
                    foreach(($web['raw'] ?? []) as $col => $val){
                        $row['web_'.$col] = $val;
                    }
                    $row['web_kptn'] = (string)($web['kptn'] ?? '');
                    $row['web_amount'] = (float)$web['amount'];
                    $row['web_ctp'] = (float)$web['ctp'];
                } else {
                    $row['web_kptn'] = '';
                    $row['web_amount'] = 0.0;
                    $row['web_ctp'] = 0.0;
                }

                $row['is_cross_date_match'] = ($partner && $web && $partner['date'] !== $web['date']);
                $rows[] = $row;
            }

            $dayPayload['rows'] = $rows;
            $dayPayload['matchedCount'] = $matchedCount;
            $dayPayload['unmatchedCount'] = $unmatchedCount;
        }

        $days[] = $dayPayload;
    }

    echo json_encode(['success' => true, 'start_date' => $startDate, 'end_date' => $endDate, 'days' => $days]);
    exit;

}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
