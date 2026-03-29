<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id        = isset($_POST['id'])        ? (int)$_POST['id']               : 0;
$direction = isset($_POST['direction']) ? trim($_POST['direction'])        : '';
$moment    = isset($_POST['moment'])    ? trim($_POST['moment'])           : '';
$docNumber = isset($_POST['doc_number'])? trim($_POST['doc_number'])       : '';
$sum       = isset($_POST['sum'])       ? (float)str_replace(',', '.', $_POST['sum']) : 0.0;
$cpId      = isset($_POST['cp_id'])     ? (int)$_POST['cp_id']            : 0;
$purpose   = isset($_POST['payment_purpose']) ? trim($_POST['payment_purpose']) : '';
$desc      = isset($_POST['description'])     ? trim($_POST['description'])     : '';
$isMoving  = !empty($_POST['is_moving']) ? 1 : 0;

if (!in_array($direction, array('in', 'out'))) {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть напрям'));
    exit;
}
if ($sum <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Сума має бути > 0'));
    exit;
}
if ($moment === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть дату'));
    exit;
}

$momentTs = strtotime($moment);
if ($momentTs === false) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний формат дати'));
    exit;
}
$momentDt = date('Y-m-d H:i:s', $momentTs);

$data = array(
    'direction'       => $direction,
    'moment'          => $momentDt,
    'doc_number'      => $docNumber !== '' ? $docNumber : null,
    'sum'             => round($sum, 2),
    'payment_purpose' => $purpose !== '' ? $purpose : null,
    'description'     => $desc !== '' ? $desc : null,
    'is_moving'       => $isMoving,
    'cp_id'           => $cpId > 0 ? $cpId : null,
);

if ($id > 0) {
    $r = Database::update('Papir', 'finance_bank', $data, array('id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }

    $cpName = '';
    if ($cpId > 0) {
        $cpRow = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id = " . $cpId . " LIMIT 1");
        if ($cpRow['ok'] && $cpRow['row']) {
            $cpName = $cpRow['row']['name'];
        }
    }

    echo json_encode(array('ok' => true, 'id' => $id, 'cp_name' => $cpName));
} else {
    $data['source']    = 'manual';
    $data['is_posted'] = 1;
    $r = Database::insert('Papir', 'finance_bank', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => $r['insert_id']));
}