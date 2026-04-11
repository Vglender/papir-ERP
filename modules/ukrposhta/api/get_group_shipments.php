<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}

$uuid = isset($_GET['group_uuid']) ? trim($_GET['group_uuid']) : '';
if (!$uuid) { echo json_encode(array('ok' => false, 'error' => 'group_uuid required')); exit; }

$rows = \Papir\Crm\UpGroupRepository::getShipments($uuid);
echo json_encode(array('ok' => true, 'rows' => $rows), JSON_UNESCAPED_UNICODE);