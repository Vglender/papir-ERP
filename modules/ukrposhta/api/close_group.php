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

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$uuid = isset($input['group_uuid']) ? trim($input['group_uuid']) : '';
if (!$uuid) { echo json_encode(array('ok' => false, 'error' => 'group_uuid required')); exit; }

echo json_encode(\Papir\Crm\GroupService::close($uuid));