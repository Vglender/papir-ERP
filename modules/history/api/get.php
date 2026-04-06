<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';
require_once __DIR__ . '/../../../modules/shared/DocumentHistory.php';

$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id']           : 0;
$page = isset($_GET['page']) ? (int)$_GET['page']         : 1;

$allowed = array(
    'customerorder', 'demand', 'supply', 'payment',
    'finance_bank', 'finance_cash', 'ttn_novaposhta',
);

if (!in_array($type, $allowed, true) || $id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні параметри'));
    exit;
}

$result = DocumentHistory::getPage($type, $id, $page, 10);
echo json_encode(array('ok' => true) + $result);
