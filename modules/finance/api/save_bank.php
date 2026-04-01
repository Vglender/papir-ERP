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
$isMoving       = !empty($_POST['is_moving']) ? 1 : 0;
$expCategoryId  = isset($_POST['expense_category_id']) ? (int)$_POST['expense_category_id'] : 0;

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
    'is_moving'           => $isMoving,
    'cp_id'               => $cpId > 0 ? $cpId : null,
    'expense_category_id' => ($direction === 'out' && $expCategoryId > 0) ? $expCategoryId : null,
);

require_once __DIR__ . '/finance_ms_sync.php';

if ($id > 0) {
    // Читаем текущую запись для id_ms, organization_ms, is_posted
    $curRow = Database::fetchRow('Papir',
        "SELECT id_ms, organization_ms, is_posted, agent_ms_type FROM finance_bank WHERE id = {$id} LIMIT 1"
    );
    $existingIdMs = ($curRow['ok'] && $curRow['row']) ? (string)$curRow['row']['id_ms']          : '';
    $orgMs        = ($curRow['ok'] && $curRow['row']) ? (string)$curRow['row']['organization_ms'] : '';
    $isPosted     = ($curRow['ok'] && $curRow['row']) ? (int)$curRow['row']['is_posted']          : 1;
    $agentMsType  = ($curRow['ok'] && $curRow['row']) ? (string)$curRow['row']['agent_ms_type']   : '';

    $r = Database::update('Papir', 'finance_bank', $data, array('id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }

    $cpName = '';
    if ($cpId > 0) {
        $cpRow = Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id = {$cpId} LIMIT 1");
        if ($cpRow['ok'] && $cpRow['row']) $cpName = $cpRow['row']['name'];
    }

    // Синхронизировать в МС
    $msSync = finance_ms_push($id,
        array(
            'direction'          => $direction,
            'moment'             => $momentDt,
            'sum'                => round($sum, 2),
            'doc_number'         => $docNumber,
            'description'        => $desc,
            'payment_purpose'    => $purpose,
            'cp_id'              => $cpId,
            'expense_category_id'=> $expCategoryId,
            'is_posted'          => $isPosted,
            'organization_ms'    => $orgMs,
            'agent_ms_type'      => $agentMsType,
        ),
        $existingIdMs
    );

    $resp = array('ok' => true, 'id' => $id, 'cp_name' => $cpName);
    if (!$msSync['ok']) {
        $resp['ms_error'] = $msSync['error'];
    }
    echo json_encode($resp);

} else {
    $data['source']    = 'manual';
    $data['is_posted'] = 1;
    $r = Database::insert('Papir', 'finance_bank', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення'));
        exit;
    }
    $localId = (int)$r['insert_id'];

    // Синхронизировать в МС (CREATE — id_ms ещё нет)
    $msSync = finance_ms_push($localId,
        array(
            'direction'          => $direction,
            'moment'             => $momentDt,
            'sum'                => round($sum, 2),
            'doc_number'         => $docNumber,
            'description'        => $desc,
            'payment_purpose'    => $purpose,
            'cp_id'              => $cpId,
            'expense_category_id'=> $expCategoryId,
            'is_posted'          => 1,
            'organization_ms'    => '',
        ),
        ''
    );

    $resp = array('ok' => true, 'id' => $localId);
    if (!empty($msSync['id_ms'])) {
        $resp['id_ms'] = $msSync['id_ms'];
    }
    if (!$msSync['ok']) {
        $resp['ms_error'] = $msSync['error'];
    }
    echo json_encode($resp);
}