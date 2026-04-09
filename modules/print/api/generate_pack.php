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

if ($demandId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

$result = PackGenerator::generate($demandId, $profileId);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
