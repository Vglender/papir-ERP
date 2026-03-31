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

$empId      = isset($_POST['emp_id'])       ? (int)$_POST['emp_id']             : 0;
$fullName   = isset($_POST['full_name'])    ? trim($_POST['full_name'])          : '';
$posName    = isset($_POST['position_name'])? trim($_POST['position_name'])      : '';
$phone      = isset($_POST['phone'])        ? trim($_POST['phone'])              : '';
$email      = isset($_POST['email'])        ? strtolower(trim($_POST['email']))  : '';
$status     = isset($_POST['status'])       ? (int)$_POST['status']             : 1;

if ($fullName === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть ПІБ'));
    exit;
}

$data = array(
    'full_name'     => $fullName,
    'position_name' => $posName !== '' ? $posName : null,
    'phone'         => $phone   !== '' ? $phone   : null,
    'email'         => $email   !== '' ? $email   : null,
    'status'        => $status ? 1 : 0,
);

if ($empId > 0) {
    $r = \Database::update('Papir', 'employee', $data, array('id' => $empId));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }
    \Papir\Crm\AuthService::log('edit', 'employee', $empId);
    echo json_encode(array('ok' => true, 'emp_id' => $empId));
} else {
    $data['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
    $r = \Database::insert('Papir', 'employee', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення'));
        exit;
    }
    $newId = $r['insert_id'];
    \Papir\Crm\AuthService::log('create', 'employee', $newId, $fullName);
    echo json_encode(array('ok' => true, 'emp_id' => $newId));
}