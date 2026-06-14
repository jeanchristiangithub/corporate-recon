<?php
// bdo-insert.php
// Handles duplicate checks, deletes and inserts for BDO web data upload flow.
// Mirrors mbtc-insert.php — adapted for BDO (BDO UNIBANK).
//
// NOTE: The main upload flow in webdata-section.php uses the unified endpoint
// ml-web-data-insert.php (ml_web_data table). This file provides the same
// actions against the partner-specific bdo_web_data table for standalone use.

require_once __DIR__ . '/bdo-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Read raw JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

try {

    $action = isset($data['action']) ? $data['action'] : '';

    // ── check ──────────────────────────────────────────────────────────────────
    // Duplicate detection: match ccref_no + date_claimed against bdo_web_data.
    // Tries three strategies in order:
    //   1. Exact DATETIME match using normalized date_claimed value.
    //   2. DATE-only match (date_claimed contains time component that may differ).
    //   3. Loose ccref_no-only match as a last resort.
    if ($action === 'check') {
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) {
            echo json_encode(['success' => true, 'duplicates' => []]);
            exit;
        }

        $pdo     = fileRecDbConnection();
        $results = [];
        $seen    = [];

        foreach ($pairs as $p) {
            $cc      = isset($p['ccref_no'])     ? trim((string) $p['ccref_no'])     : '';
            $rawDate = isset($p['date_claimed'])  ? $p['date_claimed']                : '';
            if ($cc === '') continue;

            // Strategy 1 — exact normalized DATETIME
            $norm = bdo_parse_date_claimed($rawDate);
            if ($norm !== null) {
                $stmt = $pdo->prepare(
                    'SELECT ccref_no, date_claimed, COUNT(*) AS cnt
                       FROM bdo_web_data
                      WHERE ccref_no = ? AND date_claimed = ?
                      GROUP BY ccref_no, date_claimed'
                );
                $stmt->execute([$cc, $norm]);
                $r = $stmt->fetch();
                if ($r && isset($r['cnt']) && (int) $r['cnt'] > 0) {
                    $key = $r['ccref_no'] . '|' . $r['date_claimed'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[]  = ['ccref_no' => $r['ccref_no'], 'date_claimed' => $r['date_claimed'], 'cnt' => (int) $r['cnt']];
                    }
                    continue;
                }
            }

            // Strategy 2 — DATE-only match
            $ts = strtotime((string) $rawDate);
            if ($ts !== false) {
                $dateOnly = date('Y-m-d', $ts);
                $stmt2    = $pdo->prepare(
                    'SELECT ccref_no, date_claimed, COUNT(*) AS cnt
                       FROM bdo_web_data
                      WHERE ccref_no = ? AND DATE(date_claimed) = ?
                      GROUP BY ccref_no, date_claimed'
                );
                $stmt2->execute([$cc, $dateOnly]);
                $r2 = $stmt2->fetchAll();
                foreach ($r2 as $ra) {
                    if (isset($ra['cnt']) && (int) $ra['cnt'] > 0) {
                        $key = $ra['ccref_no'] . '|' . $ra['date_claimed'];
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $results[]  = ['ccref_no' => $ra['ccref_no'], 'date_claimed' => $ra['date_claimed'], 'cnt' => (int) $ra['cnt']];
                        }
                    }
                }
                if (!empty($r2)) continue;
            }

            // Strategy 3 — ccref_no only (loose fallback)
            $stmt3 = $pdo->prepare(
                'SELECT ccref_no, date_claimed, COUNT(*) AS cnt
                   FROM bdo_web_data
                  WHERE ccref_no = ?
                  GROUP BY ccref_no, date_claimed'
            );
            $stmt3->execute([$cc]);
            $r3 = $stmt3->fetchAll();
            foreach ($r3 as $ra) {
                if (isset($ra['cnt']) && (int) $ra['cnt'] > 0) {
                    $key = $ra['ccref_no'] . '|' . $ra['date_claimed'];
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $results[]  = ['ccref_no' => $ra['ccref_no'], 'date_claimed' => $ra['date_claimed'], 'cnt' => (int) $ra['cnt']];
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'duplicates' => $results]);
        exit;
    }

    // ── delete ─────────────────────────────────────────────────────────────────
    // Remove duplicate records identified by (ccref_no, date_claimed) pairs.
    if ($action === 'delete') {
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        if (count($pairs) === 0) {
            echo json_encode(['success' => true, 'deleted' => 0]);
            exit;
        }

        $pdo = fileRecDbConnection();
        $cnt = 0;
        foreach (array_chunk($pairs, 5000) as $chunk) {
            $place  = [];
            $params = [];
            foreach ($chunk as $p) {
                $place[]  = '(?,?)';
                $params[] = $p['ccref_no'];
                $params[] = $p['date_claimed'];
            }
            $sql  = 'DELETE FROM bdo_web_data WHERE (ccref_no, date_claimed) IN (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cnt += $stmt->rowCount();
        }

        echo json_encode(['success' => true, 'deleted' => $cnt]);
        exit;
    }

    // ── insert_web ─────────────────────────────────────────────────────────────
    // Insert extracted web-data payloads into bdo_web_data.
    if ($action === 'insert_web') {
        $company  = isset($data['company'])  ? $data['company']  : 'BDO';
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $ins = new BdoInsert();
        $res = $ins->insertWebData($company, $payloads);
        echo json_encode($res);
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
