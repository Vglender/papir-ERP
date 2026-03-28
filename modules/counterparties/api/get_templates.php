<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$channel = isset($_GET['channel']) ? trim($_GET['channel']) : '';

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if ($channel && !in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

$chatRepo  = new ChatRepository();
$templates = $chatRepo->getTemplates($channel ?: null);

$result = array();
foreach ($templates as $t) {
    $result[] = array(
        'id'         => (int)$t['id'],
        'title'      => $t['title'],
        'body'       => $t['body'],
        'channels'   => $t['channels'],
        'sort_order' => (int)$t['sort_order'],
        'status'     => (int)$t['status'],
    );
}

echo json_encode(array('ok' => true, 'templates' => $result));
