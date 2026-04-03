<?php
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

$orderId         = isset($_POST['order_id'])          ? (int)$_POST['order_id']          : 0;
$paymentMethodId = isset($_POST['payment_method_id']) ? (int)$_POST['payment_method_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid order_id'));
    exit;
}

if ($paymentMethodId > 0) {
    $rPm = \Database::fetchRow('Papir',
        "SELECT id FROM payment_method WHERE id={$paymentMethodId} AND status=1");
    if (!$rPm['ok'] || empty($rPm['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Invalid payment_method_id'));
        exit;
    }
}

$val = $paymentMethodId > 0 ? $paymentMethodId : null;
$r = \Database::update('Papir', 'customerorder',
    array('payment_method_id' => $val),
    array('id' => $orderId)
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));
