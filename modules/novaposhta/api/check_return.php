<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$ttnId = isset($_GET['ttn_id']) ? (int)$_GET['ttn_id'] : 0;
if ($ttnId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$result = \Papir\Crm\ReturnService::checkPossibility($ttnId);
echo json_encode($result);