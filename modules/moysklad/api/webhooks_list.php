<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../moysklad_api.php';

$ms   = new MoySkladApi();
$rows = $ms->webhookList();

echo json_encode(array('ok' => true, 'rows' => $rows, 'count' => count($rows)));
