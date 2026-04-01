<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../moysklad_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : '';
$action     = isset($_POST['action'])      ? trim($_POST['action'])      : '';
$url        = isset($_POST['url'])         ? trim($_POST['url'])         : '';

if (!$entityType || !$action || !$url) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type, action, url required'));
    exit;
}

$validActions = array('CREATE', 'UPDATE', 'DELETE');
if (!in_array(strtoupper($action), $validActions, true)) {
    echo json_encode(array('ok' => false, 'error' => 'action must be CREATE|UPDATE|DELETE'));
    exit;
}

$ms     = new MoySkladApi();
$result = $ms->webhookCreate($entityType, $action, $url);

if (!empty($result['errors'])) {
    echo json_encode(array('ok' => false, 'error' => 'МС API error', 'details' => $result['errors']));
    exit;
}

echo json_encode(array('ok' => true, 'webhook' => $result));