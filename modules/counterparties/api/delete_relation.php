<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'id обовʼязковий'));
    exit;
}

$repo = new CounterpartyRepository();
$ok = $repo->deleteRelation($id);
echo json_encode(array('ok' => $ok));
