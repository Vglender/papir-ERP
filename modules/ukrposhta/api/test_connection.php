<?php
/**
 * Test Ukrposhta API tokens read from integration_settings.
 * Response: { ok, ecom: {ok, status, message}, tracking: {ok, status, message} }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено')); exit;
}

$api = \Papir\Crm\UkrposhtaApi::getDefault();
if (!$api) {
    echo json_encode(array('ok' => false, 'error' => 'Токени не налаштовані')); exit;
}

// 1. ecom — list shipments by default sender
$senderUuid = \Papir\Crm\UpDefaults::senderUuid();
$ecomResult = array('ok' => false, 'status' => 0, 'message' => '');
$r = $api->listShipmentsBySender($senderUuid);
$ecomResult['status']  = isset($r['http']) ? $r['http'] : 0;
$ecomResult['ok']      = !empty($r['ok']);
$ecomResult['message'] = !empty($r['ok']) ? 'Токен валідний' : (isset($r['error']) ? $r['error'] : 'Помилка');

// 2. tracking — ping a random barcode (fine if returns "not found")
$trackingResult = array('ok' => false, 'status' => 0, 'message' => '');
$r = $api->trackOne('0000000000000');
$trackingResult['status']  = isset($r['http']) ? $r['http'] : 0;
// Tracking endpoint responds 200 with "Shipment not found" body → still a valid auth check
$trackingResult['ok']      = ($trackingResult['status'] >= 200 && $trackingResult['status'] < 500);
$trackingResult['message'] = $trackingResult['ok'] ? 'Авторизація OK' : (isset($r['error']) ? $r['error'] : 'Помилка');

echo json_encode(array(
    'ok'       => $ecomResult['ok'] && $trackingResult['ok'],
    'ecom'     => $ecomResult,
    'tracking' => $trackingResult,
));