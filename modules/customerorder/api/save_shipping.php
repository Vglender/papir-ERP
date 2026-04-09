<?php
/**
 * POST /customerorder/api/save_shipping
 * Зберегти/оновити дані доставки замовлення.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid JSON'));
    exit;
}

$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

// Перевірити чи замовлення існує
$rOrder = Database::fetchRow('Papir',
    "SELECT id, counterparty_id FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}

$s = function($key, $max = 255) use ($input) {
    $v = isset($input[$key]) ? trim((string)$input[$key]) : '';
    return $v === '' ? null : mb_substr($v, 0, $max, 'UTF-8');
};

$data = array(
    'recipient_first_name'  => $s('recipient_first_name', 64),
    'recipient_last_name'   => $s('recipient_last_name', 64),
    'recipient_middle_name' => $s('recipient_middle_name', 64),
    'recipient_phone'       => $s('recipient_phone', 32),
    'city_name'             => $s('city_name', 128),
    'branch_name'           => $s('branch_name', 255),
    'np_warehouse_ref'      => $s('np_warehouse_ref', 64),
    'street'                => $s('street', 128),
    'house'                 => $s('house', 128),
    'flat'                  => $s('flat', 128),
    'postcode'              => $s('postcode', 10),
    'delivery_code'         => $s('delivery_code', 32),
    'delivery_method_name'  => $s('delivery_method_name', 128),
    'no_call'               => !empty($input['no_call']) ? 1 : 0,
    'comment'               => $s('comment', 1000),
    'counterparty_id'       => $rOrder['row']['counterparty_id'] ? (int)$rOrder['row']['counterparty_id'] : null,
);

// Upsert
$rExist = Database::fetchRow('Papir',
    "SELECT id FROM customerorder_shipping WHERE customerorder_id = {$orderId} LIMIT 1");

if ($rExist['ok'] && !empty($rExist['row'])) {
    $r = Database::update('Papir', 'customerorder_shipping', $data, array('id' => (int)$rExist['row']['id']));
} else {
    $data['customerorder_id'] = $orderId;
    $data['source'] = 'manual';
    $r = Database::insert('Papir', 'customerorder_shipping', $data);
}

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => $r['error']));
    exit;
}

echo json_encode(array('ok' => true));