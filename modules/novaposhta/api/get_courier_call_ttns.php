<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$callId = isset($_GET['call_id']) ? (int)$_GET['call_id'] : 0;
if (!$callId) {
    echo json_encode(array('ok' => false, 'error' => 'call_id required'));
    exit;
}

$ttns = \Papir\Crm\CourierCallRepository::getTtns($callId);
echo json_encode(array('ok' => true, 'ttns' => $ttns));