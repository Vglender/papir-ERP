<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef    = isset($_POST['sender_ref'])        ? trim($_POST['sender_ref'])        : '';
$cpRef        = isset($_POST['counterparty_ref'])  ? trim($_POST['counterparty_ref'])  : '';
$contactRef   = isset($_POST['contact_ref'])       ? trim($_POST['contact_ref'])       : '';
$addressRef   = isset($_POST['address_ref'])       ? trim($_POST['address_ref'])       : '';
$addressDesc  = isset($_POST['address_desc'])      ? trim($_POST['address_desc'])      : '';
$deliveryDate = isset($_POST['delivery_date'])     ? trim($_POST['delivery_date'])     : '';
$timeInterval = isset($_POST['time_interval'])     ? trim($_POST['time_interval'])     : '';
$timeStart    = isset($_POST['time_start'])        ? trim($_POST['time_start'])        : '';
$timeEnd      = isset($_POST['time_end'])          ? trim($_POST['time_end'])          : '';
$weight       = isset($_POST['planned_weight'])    ? trim($_POST['planned_weight'])    : '';

if (!$senderRef || !$contactRef || !$addressRef || !$deliveryDate || !$timeInterval || !$weight) {
    echo json_encode(array('ok' => false, 'error' => 'Всі поля обов\'язкові'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);

$r = $np->call('CarCallGeneral', 'saveCourierCall', array(
    'ContactSenderRef'    => $contactRef,
    'PreferredDeliveryDate' => $deliveryDate,
    'PlanedWeight'        => (string)$weight,
    'TimeInterval'        => $timeInterval,
    'CounterpartySender'  => $cpRef ?: $senderRef,
    'AddressSenderRef'    => $addressRef,
));

if (!$r['ok'] || empty($r['data'][0]['Barcode'])) {
    echo json_encode(array('ok' => false, 'error' => $r['error'] ?: 'NP API error'));
    exit;
}

$barcode = $r['data'][0]['Barcode'];

\Papir\Crm\CourierCallRepository::upsert(array(
    'Barcode'                => $barcode,
    'sender_ref'             => $senderRef,
    'counterparty_sender_ref'=> $cpRef ?: null,
    'contact_sender_ref'     => $contactRef,
    'address_sender_ref'     => $addressRef,
    'preferred_delivery_date'=> $deliveryDate,
    'time_interval'          => $timeInterval,
    'time_interval_start'    => $timeStart ?: null,
    'time_interval_end'      => $timeEnd ?: null,
    'planned_weight'         => (float)$weight,
    'status'                 => 'pending',
));

echo json_encode(array('ok' => true, 'barcode' => $barcode));