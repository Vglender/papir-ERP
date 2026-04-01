<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref']) ? trim($_POST['sender_ref']) : '';
if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender) {
    echo json_encode(array('ok' => false, 'error' => 'Sender not found'));
    exit;
}

\Database::query('Papir', "UPDATE np_sender SET is_default = 0");
\Database::query('Papir',
    "UPDATE np_sender SET is_default = 1 WHERE Ref = '" . \Database::escape('Papir', $senderRef) . "'");

echo json_encode(array('ok' => true));