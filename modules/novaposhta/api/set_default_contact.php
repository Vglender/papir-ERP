<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef  = isset($_POST['sender_ref'])  ? trim($_POST['sender_ref'])  : '';
$contactRef = isset($_POST['contact_ref']) ? trim($_POST['contact_ref']) : '';

if (!$senderRef || !$contactRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref and contact_ref required'));
    exit;
}

\Papir\Crm\SenderRepository::setDefaultContact($senderRef, $contactRef);
echo json_encode(array('ok' => true));
