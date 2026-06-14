<?php
// bdo-normalize-check.php
// Accepts either `pairs` or `payloads` (JSON body) and returns:
//   - normalized pairs (ccref_no + normalized date_claimed)
//   - duplicate hits found in bdo_web_data
//
// Mirrors mbtc-normalize-check.php — adapted for BDO (BDO UNIBANK).

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/bdo-helper.php';

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

try {

    // Build pairs from payloads or from an explicit pairs array
    $pairs = [];
    if (isset($data['payloads']) && is_array($data['payloads'])) {
        $pairs = bdo_build_pairs_from_payloads($data['payloads']);
    } elseif (isset($data['pairs']) && is_array($data['pairs'])) {
        foreach ($data['pairs'] as $p) {
            $cc  = isset($p['ccref_no']) ? trim((string) $p['ccref_no']) : '';
            $raw = $p['date_claimed'] ?? ($p['raw_date'] ?? '');
            $pairs[] = [
                'ccref_no'     => $cc,
                'raw_date'     => $raw,
                'date_claimed' => bdo_parse_date_claimed($raw),
            ];
        }
    }

    // Return normalized pairs
    $normalized = [];
    foreach ($pairs as $p) {
        $normalized[] = [
            'ccref_no'   => $p['ccref_no'],
            'raw_date'   => $p['raw_date'] ?? null,
            'normalized' => $p['date_claimed'] ?? null,
        ];
    }

    // Perform tolerant duplicate detection against bdo_web_data
    // (same three-strategy approach used in bdo-insert.php)
    $pdo        = fileRecDbConnection();
    $seen       = [];
    $duplicates = [];

    foreach ($normalized as $n) {
        $cc  = $n['ccref_no'];
        $raw = $n['normalized'];
        if ($cc === '') continue;

        // Strategy 1 — exact DATETIME
        if ($raw !== null) {
            $stmt = $pdo->prepare(
                'SELECT ccref_no, date_claimed, COUNT(*) AS cnt
                   FROM bdo_web_data
                  WHERE ccref_no = ? AND date_claimed = ?
                  GROUP BY ccref_no, date_claimed'
            );
            $stmt->execute([$cc, $raw]);
            $r = $stmt->fetch();
            if ($r && isset($r['cnt']) && (int) $r['cnt'] > 0) {
                $key = $r['ccref_no'] . '|' . $r['date_claimed'];
                if (!isset($seen[$key])) { $seen[$key] = true; $duplicates[] = $r; }
                continue;
            }
        }

        // Strategy 2 — DATE-only
        $ts = strtotime((string) ($n['raw_date'] ?? ''));
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
                $key = $ra['ccref_no'] . '|' . $ra['date_claimed'];
                if (!isset($seen[$key])) { $seen[$key] = true; $duplicates[] = $ra; }
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
            $key = $ra['ccref_no'] . '|' . $ra['date_claimed'];
            if (!isset($seen[$key])) { $seen[$key] = true; $duplicates[] = $ra; }
        }
    }

    echo json_encode(['success' => true, 'normalized' => $normalized, 'duplicates' => $duplicates]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
