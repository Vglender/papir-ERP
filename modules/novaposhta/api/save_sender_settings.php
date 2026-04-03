<?php
/**
 * POST /novaposhta/api/save_sender_settings
 * Save editable sender settings (use_payment_control, is_default).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref']) ? trim($_POST['sender_ref']) : '';
if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender) {
    echo json_encode(array('ok' => false, 'error' => 'Sender not found'));
    exit;
}

$upd = array();

if (isset($_POST['use_payment_control'])) {
    $upd['use_payment_control'] = (int)$_POST['use_payment_control'] ? 1 : 0;
}
if (isset($_POST['default_description'])) {
    $upd['default_description'] = trim($_POST['default_description']) ?: null;
}

$validIntervals = array(
    'CityPickingTimeInterval1',
    'CityPickingTimeInterval2',
    'CityPickingTimeInterval3',
    'CityPickingTimeInterval4',
    'CityPickingTimeInterval5',
    'CityPickingTimeInterval6',
    'CityPickingTimeInterval7',
    'CityPickingTimeInterval8',
    'CityPickingTimeInterval9',
    'CityPickingTimeInterval10',
);
if (isset($_POST['courier_call_interval']) && in_array($_POST['courier_call_interval'], $validIntervals)) {
    $upd['courier_call_interval'] = $_POST['courier_call_interval'];
}
if (isset($_POST['courier_call_planned_weight'])) {
    $w = (float)$_POST['courier_call_planned_weight'];
    if ($w > 0) $upd['courier_call_planned_weight'] = $w;
}

if (empty($upd)) {
    echo json_encode(array('ok' => true, 'message' => 'Nothing to update'));
    exit;
}

$r = \Database::update('Papir', 'np_sender', $upd, array('Ref' => $senderRef));
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));
