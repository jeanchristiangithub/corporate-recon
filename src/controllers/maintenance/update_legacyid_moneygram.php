<?php
// update_legacyid_moneygram.php
// Synchronize legacy_id from filerecondb.moneygram_partner_data -> masterdata.branch_profile.legacyid_moneygram

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/session.php';

header('Content-Type: application/json; charset=utf-8');
bootSecureSession();

if(empty($_SESSION['user'])){
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Restrict to Admin users only for safety
$role = trim((string)($_SESSION['user']['role'] ?? ''));
if(strcasecmp($role, 'Admin') !== 0){
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

try{
    $filePdo = fileRecDbConnection();
    $masterPdo = masterDataConnection();

    // Fetch distinct branch_id -> legacy_id pairs (ignore blank legacy_id)
    // Use an aggregate (MAX) on legacy_id so the query is compatible with
    // sql_mode=ONLY_FULL_GROUP_BY and returns a deterministic value per branch.
    $sql = "SELECT branch_id, MAX(legacy_id) AS legacy_id FROM moneygram_partner_data WHERE legacy_id IS NOT NULL AND TRIM(legacy_id) <> '' GROUP BY branch_id";
    $stmt = $filePdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($rows)){
        echo json_encode(['success' => true, 'rows_processed' => 0, 'rows_updated' => 0]);
        exit;
    }

    $masterPdo->beginTransaction();
    $updateSql = "UPDATE branch_profile bp
                  SET bp.legacyid_moneygram = :legacy
                  WHERE bp.branch_id = :branch_id
                    AND (
                        bp.legacyid_moneygram IS NULL
                        OR bp.legacyid_moneygram = ''
                        OR bp.legacyid_moneygram <> :legacy_cmp
                    )";
    $updateStmt = $masterPdo->prepare($updateSql);

    $rowsUpdated = 0;
    foreach($rows as $r){
        $branchId = trim((string)($r['branch_id'] ?? ''));
        $legacy = trim((string)($r['legacy_id'] ?? ''));
        if($branchId === '' || $legacy === '') continue;

        $updateStmt->execute([
            ':legacy' => $legacy,
            ':legacy_cmp' => $legacy,
            ':branch_id' => $branchId,
        ]);
        $rowsUpdated += $updateStmt->rowCount();
    }

    $masterPdo->commit();

    echo json_encode(['success' => true, 'rows_processed' => count($rows), 'rows_updated' => $rowsUpdated]);
    exit;
}catch(Throwable $e){
    try{ if(isset($masterPdo) && $masterPdo->inTransaction()) $masterPdo->rollBack(); }catch(Throwable $_){}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
