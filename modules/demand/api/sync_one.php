<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../demand_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$sync   = new DemandMsSync();
$result = $sync->push($id);

echo json_encode($result);