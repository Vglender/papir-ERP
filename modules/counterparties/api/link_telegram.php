<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$counterpartyId = isset($_POST['id'])      ? (int)$_POST['id']           : 0;
$chatId         = isset($_POST['chat_id']) ? trim($_POST['chat_id'])      : '';

if ($counterpartyId <= 0 || $chatId === '') {
    echo json_encode(array('ok' => false, 'error' => 'id та chat_id обовʼязкові'));
    exit;
}

// Save telegram_chat_id on counterparty
$r = Database::update('Papir', 'counterparty',
    array('telegram_chat_id' => $chatId),
    array('id' => $counterpartyId)
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

// Back-link orphaned messages from this chat_id
$esc = Database::escape('Papir', $chatId);
Database::query('Papir',
    "UPDATE cp_messages SET counterparty_id = {$counterpartyId}
     WHERE channel = 'telegram' AND phone = '{$esc}' AND counterparty_id = 0"
);

echo json_encode(array('ok' => true));
