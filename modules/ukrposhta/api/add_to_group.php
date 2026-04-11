<?php
/**
 * Attach a TTN to a registry (shipment_group).
 * If group_uuid is passed — use that specific registry, else auto-select the
 * last open registry of matching type (creating a new one if none exists).
 *
 * POST JSON: { barcode?, shipment_uuid?, group_uuid? }
 */
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

$barcode      = isset($input['barcode'])       ? trim($input['barcode'])       : '';
$shipmentUuid = isset($input['shipment_uuid']) ? trim($input['shipment_uuid']) : '';
$groupUuid    = isset($input['group_uuid'])    ? trim($input['group_uuid'])    : '';

if (!$shipmentUuid && $barcode) {
    $ttn = \Papir\Crm\UpTtnRepository::getByBarcode($barcode);
    if (!$ttn) { echo json_encode(array('ok' => false, 'error' => 'TTN not found')); exit; }
    $shipmentUuid = $ttn['uuid'];
}

if (!$shipmentUuid) {
    echo json_encode(array('ok' => false, 'error' => 'shipment_uuid or barcode required')); exit;
}

if ($groupUuid) {
    echo json_encode(\Papir\Crm\GroupService::addShipment($groupUuid, $shipmentUuid));
    exit;
}

// Auto-pick current open registry
echo json_encode(\Papir\Crm\GroupService::addToOrCreate($barcode ?: \Papir\Crm\UpTtnRepository::getByUuid($shipmentUuid)['barcode']));