<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../moysklad_api.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$webhookId = isset($_POST['webhook_id']) ? trim($_POST['webhook_id']) : '';

if (!$webhookId) {
    echo json_encode(array('ok' => false, 'error' => 'webhook_id required'));
    exit;
}

// Базовая валидация UUID
if (!preg_match('/^[0-9a-f\-]{36}$/i', $webhookId)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid webhook_id format'));
    exit;
}

$ms  = new MoySkladApi();
$ok  = $ms->webhookDelete($webhookId);

echo json_encode(array('ok' => $ok, 'webhook_id' => $webhookId));