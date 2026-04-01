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

\Papir\Crm\SenderRepository::setDefaultAddress($senderRef, $addressRef);
echo json_encode(array('ok' => true));
