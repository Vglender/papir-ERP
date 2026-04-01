<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId = isset($_POST['ttn_id']) ? (int)$_POST['ttn_id'] : 0;
if ($ttnId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$result = \Papir\Crm\TrackingService::trackOne($ttnId);
echo json_encode($result);