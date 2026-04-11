<?php
/**
 * Prefill data for the TTN create / edit modal.
 *
 * GET /ukrposhta/api/get_ttn_form?order_id={id}&ttn_id={id}
 * Returns { ok, data: { recipient, defaults, sum_total, cod_hint }, ttn? }
 *
 * Order's customerorder + counterparty values are used to prefill recipient
 * fields (phone, name, city). ttn_id switches to edit mode — current row is
 * returned separately as `ttn`.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$ttnId   = isset($_GET['ttn_id'])   ? (int)$_GET['ttn_id']   : 0;

$defaults = array(
    'shipment_type' => \Papir\Crm\UpDefaults::shipmentType(),
    'delivery_type' => \Papir\Crm\UpDefaults::deliveryType(),
    'payer'         => \Papir\Crm\UpDefaults::payer(),
    'weight'        => \Papir\Crm\UpDefaults::weight(),
    'length'        => \Papir\Crm\UpDefaults::length(),
    'width'         => \Papir\Crm\UpDefaults::width(),
    'height'        => \Papir\Crm\UpDefaults::height(),
    'description'   => \Papir\Crm\UpDefaults::description(),
);

$recipient = array();
$sumTotal  = 0;
$codHint   = 0;

if ($orderId) {
    $r = \Database::fetchRow('Papir',
        "SELECT co.sum, co.counterparty_id,
                co.shipping_method, co.shipping_postcode, co.shipping_city, co.shipping_street, co.shipping_house, co.shipping_flat,
                co.shipping_first_name, co.shipping_last_name, co.shipping_middle_name, co.shipping_phone, co.shipping_email,
                cp.full_name AS cp_name, cp.phone AS cp_phone, cp.email AS cp_email
         FROM customerorder co
         LEFT JOIN counterparty cp ON cp.id = co.counterparty_id
         WHERE co.id = " . $orderId . " LIMIT 1");
    if ($r['ok'] && !empty($r['row'])) {
        $row = $r['row'];
        $sumTotal = (float)$row['sum'];
        $codHint  = $sumTotal;
        $fullName = trim((string)$row['cp_name']);
        $parts    = preg_split('/\s+/u', $fullName);
        $recipient = array(
            'last_name'   => isset($row['shipping_last_name'])   && $row['shipping_last_name']   !== '' ? $row['shipping_last_name']   : (isset($parts[0]) ? $parts[0] : ''),
            'first_name'  => isset($row['shipping_first_name'])  && $row['shipping_first_name']  !== '' ? $row['shipping_first_name']  : (isset($parts[1]) ? $parts[1] : ''),
            'middle_name' => isset($row['shipping_middle_name']) && $row['shipping_middle_name'] !== '' ? $row['shipping_middle_name'] : (isset($parts[2]) ? $parts[2] : ''),
            'phone'       => $row['shipping_phone'] ?: $row['cp_phone'],
            'email'       => $row['shipping_email'] ?: $row['cp_email'],
            'city_hint'   => $row['shipping_city'],
            'postcode'    => $row['shipping_postcode'],
        );
    }
}

$ttn = null;
if ($ttnId) {
    $ttn = \Papir\Crm\UpTtnRepository::getById($ttnId);
}

echo json_encode(array(
    'ok'   => true,
    'data' => array(
        'recipient' => $recipient,
        'defaults'  => $defaults,
        'sum_total' => $sumTotal,
        'cod_hint'  => $codHint,
    ),
    'ttn'  => $ttn,
), JSON_UNESCAPED_UNICODE);