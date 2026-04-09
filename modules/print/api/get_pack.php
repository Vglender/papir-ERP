<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../services/PackGenerator.php';

$demandId = isset($_GET['demand_id']) ? (int)$_GET['demand_id'] : 0;

if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

$pack = PackGenerator::getLatest($demandId);

if (!$pack) {
    echo json_encode(array('ok' => true, 'pack' => null));
    exit;
}

$pack['items'] = json_decode($pack['items_json'], true);
unset($pack['items_json']);

echo json_encode(array('ok' => true, 'pack' => $pack), JSON_UNESCAPED_UNICODE);
