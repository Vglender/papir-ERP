<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'save';

$repo = new OrganizationRepository();

if ($action === 'delete') {
    $id    = isset($_POST['id'])    ? (int)$_POST['id']    : 0;
    $orgId = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;
    if ($id <= 0 || $orgId <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'id and org_id required'));
        exit;
    }
    $ok = $repo->deleteBankAccount($id, $orgId);
    echo json_encode(array('ok' => $ok));
    exit;
}

$data = array(
    'id'              => isset($_POST['id'])              ? (int)$_POST['id']              : 0,
    'organization_id' => isset($_POST['org_id'])          ? (int)$_POST['org_id']          : 0,
    'account_name'    => isset($_POST['account_name'])    ? $_POST['account_name']         : '',
    'bank_name'       => isset($_POST['bank_name'])       ? $_POST['bank_name']            : '',
    'mfo'             => isset($_POST['mfo'])             ? $_POST['mfo']                  : '',
    'iban'            => isset($_POST['iban'])            ? $_POST['iban']                 : '',
    'currency_code'   => isset($_POST['currency_code'])   ? $_POST['currency_code']        : 'UAH',
    'is_default'      => isset($_POST['is_default'])      ? (int)$_POST['is_default']      : 0,
);

if (empty(trim($data['iban']))) {
    echo json_encode(array('ok' => false, 'error' => 'IBAN обовʼязковий'));
    exit;
}

$result = $repo->saveBankAccount($data);
echo json_encode($result);