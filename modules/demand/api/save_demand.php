<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../demand_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id          = isset($_POST['id'])          ? (int)$_POST['id']                     : 0;
$status      = isset($_POST['status'])      ? trim($_POST['status'])                : null;
$description = isset($_POST['description']) ? trim($_POST['description'])           : null;
$applicable  = isset($_POST['applicable'])  ? (int)(bool)$_POST['applicable']      : null;
$moment      = isset($_POST['moment'])      ? trim($_POST['moment'])               : null;

if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$repo = new DemandRepository();
$r    = $repo->getById($id);
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Demand not found'));
    exit;
}

$allowed_statuses = array('new','assembling','assembled','shipped','arrived','transfer','robot');

$upd = array('sync_state' => 'changed');
if ($status !== null && in_array($status, $allowed_statuses)) {
    $upd['status'] = $status;
}
if ($description !== null) {
    $upd['description'] = $description !== '' ? $description : null;
}
if ($applicable !== null) {
    $upd['applicable'] = $applicable;
}
if ($moment !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $moment)) {
    $upd['moment'] = strlen($moment) === 10 ? $moment . ' 00:00:00' : substr($moment, 0, 19);
}

Database::update('Papir', 'demand', $upd, array('id' => $id));

// Push to МС
$sync   = new DemandMsSync();
$result = $sync->push($id);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

// Return fresh data
$fresh = $repo->getById($id);
echo json_encode(array(
    'ok'     => true,
    'demand' => $fresh['ok'] ? $fresh['row'] : array(),
));