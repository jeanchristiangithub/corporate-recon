<?php
// japanremitfinance-normalize-check.php
// Normalization and duplicate check endpoints (thin wrapper using helper functions)

require_once __DIR__ . '/japanremitfinance-helper.php';
require_once __DIR__ . '/japanremitfinance-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

try{
    $action = isset($data['action']) ? $data['action'] : '';
    if($action === 'normalize_pairs'){
        $pairs = isset($data['pairs']) && is_array($data['pairs']) ? $data['pairs'] : [];
        $out = japanremitfinance_normalize_pairs($pairs);
        echo json_encode(['success'=>true,'pairs'=>$out]); exit;
    }
    if($action === 'build_pairs_web'){
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $pairs = japanremitfinance_build_pairs_from_payloads($payloads);
        echo json_encode(['success'=>true,'pairs'=>$pairs]); exit;
    }
    if($action === 'build_pairs_partner'){
        $payloads = isset($data['payloads']) && is_array($data['payloads']) ? $data['payloads'] : [];
        $pairs = japanremitfinance_partner_build_pairs_from_payloads($payloads);
        echo json_encode(['success'=>true,'pairs'=>$pairs]); exit;
    }

    echo json_encode(['success'=>false,'error'=>'Invalid action']); exit;

}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
