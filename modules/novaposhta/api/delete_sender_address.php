<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef  = isset($_POST['sender_ref'])  ? trim($_POST['sender_ref'])  : '';
$addressRef = isset($_POST['address_ref']) ? trim($_POST['address_ref']) : '';

if (!$senderRef || !$addressRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref and address_ref required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'Sender or API key not found'));
    exit;
}

$np = new \Papir\Crm\NovaPoshta($sender['api']);
$r  = $np->call('Address', 'delete', array(
    'Ref'             => $addressRef,
    'CounterpartyRef' => $senderRef,
));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => $r['error'] ?: 'NP API error'));
    exit;
}

$ea = \Database::escape('Papir', $addressRef);
\Database::query('Papir', "DELETE FROM np_sender_address WHERE Ref = '{$ea}'");

echo json_encode(array(
    'ok'        => true,
    'addresses' => \Papir\Crm\SenderRepository::getAddresses($senderRef),
));
