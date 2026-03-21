<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../prices_bootstrap.php';

$itemId = (int)Request::postInt('item_id', 0);
if ($itemId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'item_id required'));
    exit;
}

$r = Database::delete('Papir', 'price_supplier_items', array('id' => $itemId));
echo json_encode(array('ok' => (bool)$r['ok']));
