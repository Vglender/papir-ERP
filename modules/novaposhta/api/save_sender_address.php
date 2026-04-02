<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef   = isset($_POST['sender_ref'])    ? trim($_POST['sender_ref'])    : '';
$addressType = isset($_POST['address_type'])  ? trim($_POST['address_type'])  : 'street';
$cityRef     = isset($_POST['city_ref'])      ? trim($_POST['city_ref'])      : '';
$cityName    = isset($_POST['city_name'])     ? trim($_POST['city_name'])     : '';

// street-specific
$streetRef   = isset($_POST['street_ref'])    ? trim($_POST['street_ref'])    : '';
$building    = isset($_POST['building'])      ? trim($_POST['building'])      : '';
$flat        = isset($_POST['flat'])          ? trim($_POST['flat'])          : '';

// warehouse-specific
$warehouseRef  = isset($_POST['warehouse_ref'])  ? trim($_POST['warehouse_ref'])  : '';
$warehouseName = isset($_POST['warehouse_name']) ? trim($_POST['warehouse_name']) : '';

if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

if ($addressType === 'warehouse') {
    if (!$warehouseRef) {
        echo json_encode(array('ok' => false, 'error' => 'warehouse_ref required'));
        exit;
    }
} else {
    if (!$streetRef || !$building) {
        echo json_encode(array('ok' => false, 'error' => 'street_ref and building required'));
        exit;
    }
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);

if ($addressType === 'warehouse') {
    // For warehouse pickup, the warehouse Ref IS the SenderAddress in TTN creation.
    // No need to call Address.save — just store locally.
    $addrRef     = $warehouseRef;
    $description = $warehouseName;
} else {
    $r = $np->call('Address', 'save', array(
        'CounterpartyRef' => $senderRef,
        'StreetRef'       => $streetRef,
        'BuildingNumber'  => $building,
        'Flat'            => $flat,
        'Note'            => '',
    ));

    if (!$r['ok'] || empty($r['data'][0]['Ref'])) {
        echo json_encode(array('ok' => false, 'error' => $r['error'] ?: 'NP API error'));
        exit;
    }

    $addrRef     = $r['data'][0]['Ref'];
    $description = isset($r['data'][0]['Description']) ? $r['data'][0]['Description'] : '';
}

$addrData = array(
    'sender_ref'      => $senderRef,
    'Ref'             => $addrRef,
    'Description'     => $description,
    'CityRef'         => $cityRef ?: null,
    'CityDescription' => $cityName ?: null,
    'address_type'    => $addressType,
);

if ($addressType === 'warehouse') {
    $addrData['WarehouseRef'] = $warehouseRef;
} else {
    $addrData['StreetRef'] = $streetRef;
}

\Papir\Crm\SenderRepository::upsertAddress($senderRef, $addrData);

$existing = \Papir\Crm\SenderRepository::getDefaultAddress($senderRef);
if (!$existing) {
    \Papir\Crm\SenderRepository::setDefaultAddress($senderRef, $addrRef);
}

echo json_encode(array(
    'ok'        => true,
    'addresses' => \Papir\Crm\SenderRepository::getAddresses($senderRef),
));