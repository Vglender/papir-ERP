<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$id      = isset($_GET['id'])      ? (int)$_GET['id']              : 0;
$leadId  = isset($_GET['lead_id']) ? (int)$_GET['lead_id']          : 0;
$channel = isset($_GET['channel']) ? trim($_GET['channel'])         : '';
$limit   = isset($_GET['limit'])   ? min(200, (int)$_GET['limit']) : 60;

if ($id <= 0 && $leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id or lead_id required'));
    exit;
}

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if ($channel && !in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

$chatRepo = new ChatRepository();
$messages = array();

if ($leadId > 0) {
    // Lead messages
    $leadRepo = new LeadRepository();
    $messages = $leadRepo->getMessages($leadId, $channel ?: null, $limit);
    if ($channel) {
        $leadRepo->markRead($leadId, $channel);
    }
} else {
    // Counterparty messages
    // For telegram channel: also include messages matched by telegram_chat_id
    if ($channel === 'telegram') {
        $tgR = Database::fetchRow('Papir',
            "SELECT telegram_chat_id FROM counterparty WHERE id = {$id} AND telegram_chat_id IS NOT NULL LIMIT 1");
        if ($tgR['ok'] && !empty($tgR['row']['telegram_chat_id'])) {
            $tgChatIdEsc = Database::escape('Papir', $tgR['row']['telegram_chat_id']);
            Database::query('Papir',
                "UPDATE cp_messages SET counterparty_id = {$id}
                 WHERE channel='telegram' AND phone='{$tgChatIdEsc}' AND counterparty_id=0");
        }
    }
    $messages = $chatRepo->getMessages($id, $channel ?: null, $limit);
    if ($channel) {
        $chatRepo->markRead($id, $channel);
    }
}

// Format for JS
$result = array();
foreach ($messages as $msg) {
    $result[] = array(
        'id'            => (int)$msg['id'],
        'channel'       => $msg['channel'],
        'direction'     => $msg['direction'],
        'operator_name' => $msg['operator_name'] ? $msg['operator_name'] : null,
        'status'        => $msg['status'],
        'body'          => $msg['body'],
        'media_url'     => $msg['media_url'],
        'phone'         => $msg['phone'],
        'created_at'    => $msg['created_at'],
        'read_at'       => $msg['read_at'],
        'scheduled_at'  => $msg['scheduled_at'],
        'assigned_to'   => $msg['assigned_to'] ? (int)$msg['assigned_to'] : null,
        'reply_to_id'   => $msg['reply_to_id'] ? (int)$msg['reply_to_id'] : null,
        'reply_to_body' => isset($msg['reply_to_body']) ? $msg['reply_to_body'] : null,
    );
}

echo json_encode(array('ok' => true, 'messages' => $result));
