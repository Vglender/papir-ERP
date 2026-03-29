<?php
/**
 * POST /counterparties/api/order_item_delete
 * Deletes a single order item and recalculates order totals.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';
require_once __DIR__ . '/../../customerorder/customerorder_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
if ($itemId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'item_id required'));
    exit;
}

$repository = new CustomerOrderRepository();
$service    = new CustomerOrderService($repository);

$result = $service->removeItem($itemId, 1);
echo json_encode($result);
