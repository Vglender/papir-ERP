<?php
/**
 * Incoming Telegram webhook.
 * Register URL in Telegram:
 *   https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://papir.officetorg.com.ua/counterparties/webhook/telegram_in
 *
 * Or use the helper endpoint:
 *   GET /counterparties/webhook/telegram_set
 */
require_once __DIR__ . '/../counterparties_bootstrap.php';

// Always respond 200 to Telegram immediately
header('Content-Type: application/json; charset=utf-8');

$input  = file_get_contents('php://input');
$update = $input ? json_decode($input, true) : null;

if (!is_array($update)) {
    echo json_encode(array('ok' => true));
    exit;
}

// Only handle regular text messages (skip edits, channel posts, etc.)
if (empty($update['message'])) {
    echo json_encode(array('ok' => true));
    exit;
}

$msg    = $update['message'];
$text   = isset($msg['text'])           ? trim($msg['text'])                    : '';
$chatId = isset($msg['chat']['id'])     ? (string)$msg['chat']['id']            : '';
$from   = isset($msg['from'])           ? $msg['from']                          : array();

// Skip empty messages (stickers, photos without caption, etc.)
if (!$text || !$chatId) {
    echo json_encode(array('ok' => true));
    exit;
}

// Build sender name for reference
$fromName = '';
if (!empty($from['first_name'])) $fromName .= $from['first_name'];
if (!empty($from['last_name']))  $fromName .= ' ' . $from['last_name'];
$fromName = trim($fromName);
if (!$fromName && !empty($from['username'])) $fromName = '@' . $from['username'];

$chatRepo = new ChatRepository();

// Find counterparty: first by counterparty.telegram_chat_id field, then by message history
$tgEsc = Database::escape('Papir', $chatId);
$cpRow = Database::fetchRow('Papir',
    "SELECT id FROM counterparty WHERE telegram_chat_id = '{$tgEsc}' AND status = 1 LIMIT 1");
if ($cpRow['ok'] && !empty($cpRow['row'])) {
    $counterpartyId = (int)$cpRow['row']['id'];
} else {
    $counterpartyId = $chatRepo->findCounterpartyByTelegramChatId($chatId);
}

// Save message
$chatRepo->saveMessage(array(
    'counterparty_id' => $counterpartyId > 0 ? $counterpartyId : 0,
    'channel'         => 'telegram',
    'direction'       => 'in',
    'status'          => 'read',    // delivered by Telegram
    'phone'           => $chatId,   // re-use phone field for telegram_chat_id
    'body'            => ($fromName ? "[{$fromName}] " : '') . $text,
    'read_at'         => null,      // null = unread until manager opens chat
));

// If counterparty found, back-link any previously orphaned messages from same chat_id
if ($counterpartyId > 0) {
    Database::query('Papir',
        "UPDATE cp_messages SET counterparty_id = {$counterpartyId}
         WHERE channel = 'telegram' AND phone = '{$tgEsc}' AND counterparty_id = 0");
}

echo json_encode(array('ok' => true));
