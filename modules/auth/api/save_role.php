<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';




if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

if (!\Papir\Crm\AuthService::isAdmin()) {
    echo json_encode(array('ok' => false, 'error' => 'Недостатньо прав'));
    exit;
}

$roleId      = isset($_POST['role_id'])     ? (int)$_POST['role_id']         : 0;
$name        = isset($_POST['name'])        ? trim($_POST['name'])            : '';
$description = isset($_POST['description']) ? trim($_POST['description'])     : '';
$isAdmin     = !empty($_POST['is_admin'])   ? 1                               : 0;

if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть назву ролі'));
    exit;
}

$data = array('name' => $name, 'description' => $description, 'is_admin' => $isAdmin);

if ($roleId > 0) {
    $r = \Database::update('Papir', 'auth_roles', $data, array('role_id' => $roleId));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження (можливо, назва вже існує)'));
        exit;
    }
    echo json_encode(array('ok' => true, 'role_id' => $roleId));
} else {
    $r = \Database::insert('Papir', 'auth_roles', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення (можливо, назва вже існує)'));
        exit;
    }
    echo json_encode(array('ok' => true, 'role_id' => $r['insert_id']));
}
