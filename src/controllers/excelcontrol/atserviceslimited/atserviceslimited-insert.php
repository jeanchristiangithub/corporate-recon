<?php
require_once __DIR__ . '/atserviceslimited-insert-lib.php';
require_once __DIR__ . '/../../../config/db.php';

header('Content-Type: application/json');
$action = $_POST['action'] ?? null;
$company = $_POST['company'] ?? '';
$payload = $_POST['payload'] ?? null;

$insert = new AtServicesLimitedInsert();

try{
    if($action === 'check'){
        echo json_encode(['success'=>true,'message'=>'check not implemented']);
        exit;
    }
    if($action === 'insert_web'){
        $pl = json_decode($payload, true);
        $res = $insert->insertWebData($company, $pl);
        echo json_encode($res);
        exit;
    }
    if($action === 'insert_partner'){
        $pl = json_decode($payload, true);
        $res = $insert->insertPartnerData($company, $pl);
        echo json_encode($res);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'invalid_action']);
}catch(Throwable $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
