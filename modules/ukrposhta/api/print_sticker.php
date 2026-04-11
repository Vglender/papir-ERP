<?php
/**
 * GET /ukrposhta/api/print_sticker?ttn_id={id}
 *
 * If the TTN has a stored label URL (created at TTN creation time), redirect to it.
 * Otherwise download the sticker PDF from Ukrposhta on demand and stream it.
 */
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}

$ttnId = isset($_GET['ttn_id']) ? (int)$_GET['ttn_id'] : 0;
if (!$ttnId) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'ttn_id required')); exit;
}

$ttn = \Papir\Crm\UpTtnRepository::getById($ttnId);
if (!$ttn) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'TTN not found')); exit;
}
if (!$ttn['barcode']) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'No barcode yet')); exit;
}

// 1. Cached label exists → redirect
if (!empty($ttn['label']) && strpos($ttn['label'], 'http') === 0) {
    header('Location: ' . $ttn['label']);
    exit;
}

// 2. Download from API
$api = \Papir\Crm\UkrposhtaApi::getDefault();
if (!$api) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => 'API not configured')); exit;
}

$label = \Papir\Crm\TtnService::downloadSticker($api, $ttn['barcode'], $ttn['lifecycle_statusDate']);
if ($label) {
    \Papir\Crm\UpTtnRepository::updateById($ttnId, array('label' => $label));
    header('Location: ' . $label);
    exit;
}

// 3. Fallback — stream raw PDF inline
$r = $api->getSticker($ttn['barcode']);
if (!$r['ok']) {
    header('Content-Type: application/json'); echo json_encode(array('ok' => false, 'error' => $r['error'])); exit;
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="up_' . $ttn['barcode'] . '.pdf"');
echo $r['raw'];