<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$senderRef    = isset($_GET['sender_ref'])       ? trim($_GET['sender_ref'])       : '';
$addressRef   = isset($_GET['address_ref'])      ? trim($_GET['address_ref'])      : '';
$cpRef        = isset($_GET['counterparty_ref']) ? trim($_GET['counterparty_ref']) : '';
$deliveryDate = isset($_GET['delivery_date'])    ? trim($_GET['delivery_date'])    : '';

if (!$senderRef || !$deliveryDate) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref and delivery_date required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);

$props = array('DeliveryDate' => $deliveryDate);
if ($cpRef)      $props['CounterpartySender'] = $cpRef;
if ($addressRef) $props['SenderAddressRef']   = $addressRef;

$r = $np->call('CarCallGeneral', 'getCarCallTimeIntervals', $props);

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => $r['error']));
    exit;
}

echo json_encode(array('ok' => true, 'intervals' => $r['data']));