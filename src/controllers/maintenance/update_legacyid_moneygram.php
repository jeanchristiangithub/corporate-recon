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

$payload = json_decode((string)file_get_contents('php://input'), true);
if(!is_array($payload)){
    $payload = [];
}

$normalizeDate = static function($value): string {
    $value = trim((string)$value);
    if($value === ''){
        return '';
    }

    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if(!$dt || $dt->format('Y-m-d') !== $value){
        return '';
    }

    return $value;
};

$startDate = $normalizeDate($payload['start_date'] ?? '');
$endDate = $normalizeDate($payload['end_date'] ?? '');

if($startDate === '' || $endDate === ''){
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Duration date is required.']);
    exit;
}

if($startDate > $endDate){
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Start Date cannot be greater than End Date.']);
    exit;
}

try{
    $filePdo = fileRecDbConnection();
    $masterPdo = masterDataConnection();

    // Fetch distinct branch_id -> legacy_id pairs for the selected transaction date range.
    // Use an aggregate (MAX) on legacy_id so the query is compatible with
    // sql_mode=ONLY_FULL_GROUP_BY and returns a deterministic value per branch.
    $sql = "SELECT branch_id, MAX(legacy_id) AS legacy_id
            FROM moneygram_partner_data
            WHERE legacy_id IS NOT NULL
              AND TRIM(legacy_id) <> ''
              AND tran_date IS NOT NULL
              AND DATE(tran_date) BETWEEN :start_date AND :end_date
            GROUP BY branch_id";
    $stmt = $filePdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($rows)){
        echo json_encode(['success' => true, 'rows_processed' => 0, 'rows_updated' => 0, 'start_date' => $startDate, 'end_date' => $endDate]);
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

    echo json_encode(['success' => true, 'rows_processed' => count($rows), 'rows_updated' => $rowsUpdated, 'start_date' => $startDate, 'end_date' => $endDate]);
    exit;
}catch(Throwable $e){
    try{ if(isset($masterPdo) && $masterPdo->inTransaction()) $masterPdo->rollBack(); }catch(Throwable $_){}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
