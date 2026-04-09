<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref']) ? trim($_POST['sender_ref']) : '';
$orgId     = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : 0;

if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$r = \Database::update('Papir', 'np_sender', array('organization_id' => $orgId ?: null), array('Ref' => $senderRef));
echo json_encode(array('ok' => $r['ok']));