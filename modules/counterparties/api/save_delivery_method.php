<?php
/**
 * POST /counterparties/api/save_delivery_method
 * Set delivery method on an order.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}
if (!\Papir\Crm\AuthService::isLoggedIn()) {
    echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
    exit;
}

$orderId          = isset($_POST['order_id'])           ? (int)$_POST['order_id']           : 0;
$deliveryMethodId = isset($_POST['delivery_method_id']) ? (int)$_POST['delivery_method_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid order_id'));
    exit;
}

// Validate delivery_method_id (0 = clear, >0 = set)
if ($deliveryMethodId > 0) {
    $rDm = \Database::fetchRow('Papir',
        "SELECT id FROM delivery_method WHERE id={$deliveryMethodId} AND status=1");
    if (!$rDm['ok'] || empty($rDm['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Invalid delivery_method_id'));
        exit;
    }
}

$val = $deliveryMethodId > 0 ? $deliveryMethodId : null;
$r = \Database::update('Papir', 'customerorder',
    array('delivery_method_id' => $val),
    array('id' => $orderId)
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));