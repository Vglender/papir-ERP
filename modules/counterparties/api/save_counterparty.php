<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$repo = new CounterpartyRepository();

$id   = isset($_POST['id'])   ? (int)$_POST['id'] : 0;
$type = isset($_POST['type']) ? trim($_POST['type']) : 'company';

$allowedTypes = array('company', 'fop', 'person', 'department', 'other');
if (!in_array($type, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний тип'));
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва обовʼязкова'));
    exit;
}

$data = array(
    'type'        => $type,
    'name'        => $name,
    'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
    'group_id'    => isset($_POST['group_id'])     ? (int)$_POST['group_id']    : 0,
    'group_is_head'=> isset($_POST['group_is_head']) ? 1 : 0,
    // company/fop fields
    'short_name'    => isset($_POST['short_name'])    ? $_POST['short_name']    : '',
    'full_name'     => isset($_POST['full_name'])      ? $_POST['full_name']      : '',
    'company_type'  => isset($_POST['company_type'])  ? $_POST['company_type']  : '',
    'okpo'          => isset($_POST['okpo'])           ? $_POST['okpo']           : '',
    'inn'           => isset($_POST['inn'])            ? $_POST['inn']            : '',
    'vat_number'    => isset($_POST['vat_number'])     ? $_POST['vat_number']     : '',
    'iban'          => isset($_POST['iban'])           ? $_POST['iban']           : '',
    'bank_name'     => isset($_POST['bank_name'])      ? $_POST['bank_name']      : '',
    'mfo'           => isset($_POST['mfo'])            ? $_POST['mfo']            : '',
    'legal_address' => isset($_POST['legal_address'])  ? $_POST['legal_address']  : '',
    'actual_address'=> isset($_POST['actual_address']) ? $_POST['actual_address'] : '',
    'phone'         => AlphaSmsService::normalizePhoneLoose(isset($_POST['phone'])     ? $_POST['phone']     : ''),
    'email'         => isset($_POST['email'])          ? $_POST['email']          : '',
    'website'       => isset($_POST['website'])        ? $_POST['website']        : '',
    'notes'         => isset($_POST['notes'])          ? $_POST['notes']          : '',
    // person fields
    'last_name'     => isset($_POST['last_name'])      ? $_POST['last_name']      : '',
    'first_name'    => isset($_POST['first_name'])     ? $_POST['first_name']     : '',
    'middle_name'   => isset($_POST['middle_name'])    ? $_POST['middle_name']    : '',
    'phone_alt'     => AlphaSmsService::normalizePhoneLoose(isset($_POST['phone_alt']) ? $_POST['phone_alt'] : ''),
    'birth_date'    => isset($_POST['birth_date'])     ? $_POST['birth_date']     : '',
    'position_name' => isset($_POST['position_name'])  ? $_POST['position_name']  : '',
    'telegram'      => isset($_POST['telegram'])       ? $_POST['telegram']       : '',
    'viber'         => isset($_POST['viber'])          ? $_POST['viber']          : '',
);

if ($id > 0) {
    $ok = $repo->update($id, $data);
    if (!$ok) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => $id));
} else {
    $newId = $repo->create($data);
    if (!$newId) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => $newId));
}
