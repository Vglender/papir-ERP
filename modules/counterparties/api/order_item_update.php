<?php
/**
 * POST /counterparties/api/order_item_update
 * Updates a single order item and recalculates order totals.
 * Returns updated order totals.
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

$result = $service->updateItem($itemId, $_POST, 1);
if (!$result['ok']) {
    echo json_encode($result);
    exit;
}

// Return updated order totals so frontend can refresh footer without full reload
$orderId = (int)$result['order_id'];
$rTotals = \Database::fetchRow('Papir',
    "SELECT sum_items, sum_discount, sum_vat, sum_total
     FROM customerorder WHERE id = {$orderId} LIMIT 1");

$totals = ($rTotals['ok'] && $rTotals['row']) ? $rTotals['row'] : array();

echo json_encode(array(
    'ok'     => true,
    'totals' => $totals,
));