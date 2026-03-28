<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id    = isset($_POST['id'])    ? (int)$_POST['id']          : 0;
$title = isset($_POST['title']) ? trim($_POST['title'])       : '';
$body  = isset($_POST['body'])  ? trim($_POST['body'])        : '';

if (!$title || !$body) {
    echo json_encode(array('ok' => false, 'error' => 'title і body обовʼязкові'));
    exit;
}

// Build channels list from checkboxes: channels[]=viber&channels[]=sms
$allowedCh = array('viber', 'sms', 'email', 'telegram', 'note');
$channels  = array();
if (isset($_POST['channels']) && is_array($_POST['channels'])) {
    foreach ($_POST['channels'] as $ch) {
        if (in_array($ch, $allowedCh)) {
            $channels[] = $ch;
        }
    }
}
if (empty($channels)) {
    $channels = array('viber', 'sms');
}

$data = array(
    'id'         => $id,
    'title'      => $title,
    'body'       => $body,
    'channels'   => implode(',', $channels),
    'sort_order' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
    'status'     => isset($_POST['status'])     ? (int)(bool)$_POST['status'] : 1,
);

$chatRepo  = new ChatRepository();
$savedId   = $chatRepo->saveTemplate($data);

if (!$savedId) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => $savedId));
