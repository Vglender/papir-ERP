<?php
/**
 * GET /customerorder/api/get_last_shipping?counterparty_id=X
 * Повертає дані доставки з останнього замовлення контрагента.
 * Використовується для автозаповнення при ручному створенні замовлення.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

$cpId = isset($_GET['counterparty_id']) ? (int)$_GET['counterparty_id'] : 0;
if ($cpId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'counterparty_id required'));
    exit;
}

$r = Database::fetchRow('Papir',
    "SELECT cs.*
     FROM customerorder_shipping cs
     JOIN customerorder co ON co.id = cs.customerorder_id
     WHERE cs.counterparty_id = {$cpId}
       AND co.deleted_at IS NULL
     ORDER BY co.id DESC
     LIMIT 1");

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => $r['error']));
    exit;
}

if (empty($r['row'])) {
    echo json_encode(array('ok' => true, 'shipping' => null));
    exit;
}

$s = $r['row'];
echo json_encode(array('ok' => true, 'shipping' => array(
    'recipient_first_name'  => $s['recipient_first_name'],
    'recipient_last_name'   => $s['recipient_last_name'],
    'recipient_middle_name' => $s['recipient_middle_name'],
    'recipient_phone'       => $s['recipient_phone'],
    'city_name'             => $s['city_name'],
    'branch_name'           => $s['branch_name'],
    'np_warehouse_ref'      => $s['np_warehouse_ref'],
    'street'                => $s['street'],
    'house'                 => $s['house'],
    'flat'                  => $s['flat'],
    'postcode'              => $s['postcode'],
    'delivery_code'         => $s['delivery_code'],
    'delivery_method_name'  => $s['delivery_method_name'],
    'no_call'               => (int)$s['no_call'],
)), JSON_UNESCAPED_UNICODE);