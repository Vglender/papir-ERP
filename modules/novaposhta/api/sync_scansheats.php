<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef  = isset($_POST['sender_ref'])   ? trim($_POST['sender_ref'])   : '';
$allSenders = isset($_POST['all_senders'])  ? (bool)$_POST['all_senders'] : false;

if ($allSenders) {
    $senders = \Papir\Crm\SenderRepository::getAll();
    $total   = 0;
    foreach ($senders as $s) {
        $r = \Papir\Crm\ScanSheetService::syncList($s['Ref']);
        if ($r['ok']) $total += $r['count'];
    }
    echo json_encode(array('ok' => true, 'count' => $total));
    exit;
}

if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$result = \Papir\Crm\ScanSheetService::syncList($senderRef);
echo json_encode($result);