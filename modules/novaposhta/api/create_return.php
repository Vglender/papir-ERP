<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId = isset($_POST['ttn_id']) ? (int)$_POST['ttn_id'] : 0;
if ($ttnId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$params = array(
    'payer_type'         => isset($_POST['payer_type'])         ? trim($_POST['payer_type'])         : 'Recipient',
    'payment_method'     => isset($_POST['payment_method'])     ? trim($_POST['payment_method'])     : 'Cash',
    'return_address_ref' => isset($_POST['return_address_ref']) ? trim($_POST['return_address_ref']) : '',
    'service_type'       => isset($_POST['service_type'])       ? trim($_POST['service_type'])       : 'WarehouseWarehouse',
    'return_reason_ref'  => isset($_POST['return_reason_ref'])  ? trim($_POST['return_reason_ref'])  : '',
    'subtype_reason_ref' => isset($_POST['subtype_reason_ref']) ? trim($_POST['subtype_reason_ref']) : '',
);

$result = \Papir\Crm\ReturnService::create($ttnId, $params);
echo json_encode($result);