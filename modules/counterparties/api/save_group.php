<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$repo = new CounterpartyRepository();

$id   = isset($_POST['id'])   ? (int)$_POST['id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва групи обовʼязкова'));
    exit;
}

$data = array(
    'name'        => $name,
    'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
    'status'      => isset($_POST['status']) ? (int)$_POST['status'] : 1,
);

if ($id > 0) {
    $ok = $repo->updateGroup($id, $data);
    echo json_encode(array('ok' => $ok, 'id' => $id));
} else {
    $newId = $repo->createGroup($data);
    if (!$newId) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення групи'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => $newId, 'name' => $name));
}
