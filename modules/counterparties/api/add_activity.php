<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id      = isset($_POST['id'])      ? (int)$_POST['id']             : 0;
$content = isset($_POST['content']) ? trim((string)$_POST['content']): '';

if (!$id || $content === '') {
    echo json_encode(array('ok' => false, 'error' => 'id and content required'));
    exit;
}

$repo = new CounterpartyRepository();
$cp   = $repo->getById($id);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

$r = Database::insert('Papir', 'counterparty_activity', array(
    'counterparty_id' => $id,
    'type'            => 'note',
    'content'         => $content,
    'created_at'      => date('Y-m-d H:i:s'),
));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array(
    'ok'         => true,
    'id'         => (int)$r['insert_id'],
    'content'    => $content,
    'created_at' => date('Y-m-d H:i:s'),
));
