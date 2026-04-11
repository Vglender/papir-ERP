<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
// order_id may be 0 → standalone TTN without order context
$result = \Papir\Crm\TtnService::getFormPrefill($orderId);
echo json_encode($result);