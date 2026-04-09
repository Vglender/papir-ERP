<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../services/PackGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$demandId  = isset($_POST['demand_id'])  ? (int)$_POST['demand_id']  : 0;
$profileId = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;
$packId    = isset($_POST['pack_id'])    ? (int)$_POST['pack_id']    : 0;

// If pack_id given — just queue existing pack
if ($packId > 0) {
    Database::update('Papir', 'print_pack_jobs',
        array('queued' => 1, 'printed_at' => null),
        array('id' => $packId));
    echo json_encode(array('ok' => true, 'pack_id' => $packId));
    exit;
}

if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id or pack_id required'));
    exit;
}

// Generate pack and queue it
$result = PackGenerator::generate($demandId, $profileId);
if (!$result['ok']) {
    echo json_encode($result);
    exit;
}

Database::update('Papir', 'print_pack_jobs',
    array('queued' => 1),
    array('id' => (int)$result['pack_id']));

echo json_encode(array('ok' => true, 'pack_id' => $result['pack_id']));
