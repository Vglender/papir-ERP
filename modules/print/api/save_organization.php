<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$repo = new OrganizationRepository();
$data = array(
    'id'             => isset($_POST['id'])             ? (int)$_POST['id']           : 0,
    'name'           => isset($_POST['name'])           ? $_POST['name']              : '',
    'short_name'     => isset($_POST['short_name'])     ? $_POST['short_name']        : '',
    'alias'          => isset($_POST['alias'])          ? $_POST['alias']             : '',
    'code'           => isset($_POST['code'])           ? $_POST['code']              : '',
    'okpo'           => isset($_POST['okpo'])           ? $_POST['okpo']              : '',
    'inn'            => isset($_POST['inn'])            ? $_POST['inn']               : '',
    'vat_number'     => isset($_POST['vat_number'])     ? $_POST['vat_number']        : '',
    'legal_address'  => isset($_POST['legal_address'])  ? $_POST['legal_address']     : '',
    'actual_address' => isset($_POST['actual_address']) ? $_POST['actual_address']    : '',
    'director_name'  => isset($_POST['director_name'])  ? $_POST['director_name']     : '',
    'director_title' => isset($_POST['director_title']) ? $_POST['director_title']    : '',
    'phone'          => isset($_POST['phone'])          ? $_POST['phone']             : '',
    'email'          => isset($_POST['email'])          ? $_POST['email']             : '',
    'website'        => isset($_POST['website'])        ? $_POST['website']           : '',
    'description'    => isset($_POST['description'])   ? $_POST['description']       : '',
    'status'         => isset($_POST['status'])        ? (int)$_POST['status']       : 1,
);

if (empty(trim($data['name']))) {
    echo json_encode(array('ok' => false, 'error' => 'Назва обовʼязкова'));
    exit;
}

$result = $repo->save($data);
echo json_encode($result);