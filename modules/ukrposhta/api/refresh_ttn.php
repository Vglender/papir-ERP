<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}

$ttnId = isset($_POST['ttn_id']) ? (int)$_POST['ttn_id'] : 0;
if ($ttnId <= 0) { echo json_encode(array('ok' => false, 'error' => 'ttn_id required')); exit; }

echo json_encode(\Papir\Crm\TtnService::refresh($ttnId));
