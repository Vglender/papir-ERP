<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId   = isset($_POST['ttn_id'])   ? (int)$_POST['ttn_id']   : 0;
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if (!$ttnId) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

// Verify TTN exists
$rTtn = \Database::fetchRow('Papir', "SELECT id FROM ttn_novaposhta WHERE id = {$ttnId} LIMIT 1");
if (!$rTtn['ok'] || !$rTtn['row']) {
    echo json_encode(array('ok' => false, 'error' => 'TTN not found'));
    exit;
}

// Verify order exists (if provided)
$orderNum = null;
if ($orderId > 0) {
    $rOrder = \Database::fetchRow('Papir', "SELECT id FROM customerorder WHERE id = {$orderId} LIMIT 1");
    if (!$rOrder['ok'] || !$rOrder['row']) {
        echo json_encode(array('ok' => false, 'error' => 'Замовлення #' . $orderId . ' не знайдено'));
        exit;
    }
    $orderNum = $orderId;
}

// Оновлюємо customerorder_id в ttn_novaposhta
\Database::update('Papir', 'ttn_novaposhta',
    array('customerorder_id' => $orderId > 0 ? $orderId : null),
    array('id' => $ttnId)
);

// Видаляємо попередній document_link якщо є
\Database::query('Papir',
    "DELETE FROM document_link WHERE from_type='ttn_np' AND from_id={$ttnId} AND to_type='customerorder'"
);

// Створюємо новий document_link (якщо прив'язуємо до заказу)
if ($orderId > 0) {
    \Database::insert('Papir', 'document_link', array(
        'from_type' => 'ttn_np',
        'from_id'   => $ttnId,
        'to_type'   => 'customerorder',
        'to_id'     => $orderId,
        'link_type' => 'shipment',
    ));
}

echo json_encode(array('ok' => true, 'order_id' => $orderNum));
