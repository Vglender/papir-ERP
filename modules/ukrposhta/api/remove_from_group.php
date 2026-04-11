<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$shipmentUuid = isset($input['shipment_uuid']) ? trim($input['shipment_uuid']) : '';
$barcode      = isset($input['barcode'])       ? trim($input['barcode'])       : '';
if (!$shipmentUuid && $barcode) {
    $ttn = \Papir\Crm\UpTtnRepository::getByBarcode($barcode);
    if ($ttn) $shipmentUuid = $ttn['uuid'];
}
if (!$shipmentUuid) {
    echo json_encode(array('ok' => false, 'error' => 'shipment_uuid or barcode required')); exit;
}

echo json_encode(\Papir\Crm\GroupService::removeShipment($shipmentUuid));