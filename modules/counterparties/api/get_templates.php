<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$channel = isset($_GET['channel']) ? trim($_GET['channel']) : '';
$context = isset($_GET['context']) ? trim($_GET['context']) : '';

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if ($channel && !in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

$allowedCtx = array('any', 'order', 'ttn');
if ($context && !in_array($context, $allowedCtx)) {
    $context = '';
}

$chatRepo  = new ChatRepository();
$templates = $chatRepo->getTemplates($channel ?: null, $context ?: null);

$result = array();
foreach ($templates as $t) {
    $result[] = array(
        'id'         => (int)$t['id'],
        'title'      => $t['title'],
        'body'       => $t['body'],
        'channels'   => $t['channels'],
        'context'    => $t['context'],
        'sort_order' => (int)$t['sort_order'],
        'status'     => (int)$t['status'],
    );
}

echo json_encode(array('ok' => true, 'templates' => $result));
