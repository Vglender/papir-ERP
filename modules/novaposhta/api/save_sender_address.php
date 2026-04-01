<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref'])  ? trim($_POST['sender_ref'])  : '';
$streetRef = isset($_POST['street_ref'])  ? trim($_POST['street_ref'])  : '';
$building  = isset($_POST['building'])    ? trim($_POST['building'])    : '';
$flat      = isset($_POST['flat'])        ? trim($_POST['flat'])        : '';
$cityRef   = isset($_POST['city_ref'])    ? trim($_POST['city_ref'])    : '';
$cityName  = isset($_POST['city_name'])   ? trim($_POST['city_name'])   : '';

if (!$senderRef || !$streetRef || !$building) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref, street_ref, building required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);
$r  = $np->call('Address', 'save', array(
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

\Papir\Crm\SenderRepository::upsertAddress($senderRef, array(
    'Ref'             => $addrRef,
    'Description'     => $description,
    'CityRef'         => $cityRef,
    'CityDescription' => $cityName,
    'StreetRef'       => $streetRef,
));

$existing = \Papir\Crm\SenderRepository::getDefaultAddress($senderRef);
if (!$existing) {
    \Papir\Crm\SenderRepository::setDefaultAddress($senderRef, $addrRef);
}

echo json_encode(array(
    'ok'        => true,
    'addresses' => \Papir\Crm\SenderRepository::getAddresses($senderRef),
));
