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

if (!is_array($update) || empty($update['message'])) {
    echo json_encode(array('ok' => true));
    exit;
}

$msg    = $update['message'];
$chatId = isset($msg['chat']['id']) ? (string)$msg['chat']['id'] : '';
$from   = isset($msg['from'])       ? $msg['from']               : array();

if (!$chatId) {
    echo json_encode(array('ok' => true));
    exit;
}

// Build sender name
$fromName = '';
if (!empty($from['first_name'])) $fromName .= $from['first_name'];
if (!empty($from['last_name']))  $fromName .= ' ' . $from['last_name'];
$fromName = trim($fromName);
if (!$fromName && !empty($from['username'])) $fromName = '@' . $from['username'];

// ── Extract text and media ────────────────────────────────────────────────────

$text     = isset($msg['text'])    ? trim($msg['text'])    : '';
$caption  = isset($msg['caption']) ? trim($msg['caption']) : '';
$mediaUrl = null;

// Body text: text message OR caption accompanying a photo/document
$bodyText = $text ? $text : $caption;

// Photo (Telegram sends array of sizes — take the largest, last in array)
if (!empty($msg['photo']) && is_array($msg['photo'])) {
    $photo    = end($msg['photo']);
    $mediaUrl = TelegramBotService::downloadAndSaveFile($photo['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[фото]' : '[фото — не вдалося завантажити]';
}
// Document (image sent as file, or other file types)
elseif (!empty($msg['document'])) {
    $docName  = isset($msg['document']['file_name']) ? $msg['document']['file_name'] : 'файл';
    $mediaUrl = TelegramBotService::downloadAndSaveFile($msg['document']['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[файл: ' . $docName . ']' : '[файл: ' . $docName . ' — не вдалося завантажити]';
}
// Voice message
elseif (!empty($msg['voice'])) {
    $mediaUrl = TelegramBotService::downloadAndSaveFile($msg['voice']['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[голосове повідомлення]' : '[голосове — не вдалося завантажити]';
}
// Audio file
elseif (!empty($msg['audio'])) {
    $mediaUrl = TelegramBotService::downloadAndSaveFile($msg['audio']['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[аудіо]' : '[аудіо — не вдалося завантажити]';
}
// Video
elseif (!empty($msg['video'])) {
    $mediaUrl = TelegramBotService::downloadAndSaveFile($msg['video']['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[відео]' : '[відео — не вдалося завантажити]';
}
// Video note (круговое видео)
elseif (!empty($msg['video_note'])) {
    $mediaUrl = TelegramBotService::downloadAndSaveFile($msg['video_note']['file_id']);
    if (!$bodyText) $bodyText = $mediaUrl ? '[відео-повідомлення]' : '[відео — не вдалося завантажити]';
}
// Sticker — save emoji/name as text, no file
elseif (!empty($msg['sticker'])) {
    $bodyText = '[стікер' . (!empty($msg['sticker']['emoji']) ? ' ' . $msg['sticker']['emoji'] . ']' : ']');
}

// Skip only if truly nothing — no text, no media, no media-type indicator
if (!$bodyText && !$mediaUrl) {
    echo json_encode(array('ok' => true));
    exit;
}

$messageBody = $bodyText;

// ── Route to counterparty or lead ────────────────────────────────────────────

$chatRepo = new ChatRepository();
$leadRepo = new LeadRepository();
$tgEsc    = Database::escape('Papir', $chatId);

// ── Перевірка блок-ліста спамерів ────────────────────────────────────────────
$spamChk = Database::fetchRow('Papir',
    "SELECT id FROM spam_senders WHERE channel='telegram' AND identifier='{$tgEsc}' LIMIT 1");
if ($spamChk['ok'] && !empty($spamChk['row'])) {
    echo json_encode(array('ok' => true));
    exit;
}

// 1. Try to find known counterparty
$cpRow = Database::fetchRow('Papir',
    "SELECT id FROM counterparty WHERE telegram_chat_id = '{$tgEsc}' AND status = 1 LIMIT 1");
if ($cpRow['ok'] && !empty($cpRow['row'])) {
    $counterpartyId = (int)$cpRow['row']['id'];
} else {
    $counterpartyId = $chatRepo->findCounterpartyByTelegramChatId($chatId);
}

if ($counterpartyId > 0) {
    $chatRepo->saveMessage(array(
        'counterparty_id' => $counterpartyId,
        'channel'         => 'telegram',
        'direction'       => 'in',
        'status'          => 'delivered',
        'phone'           => $chatId,
        'body'            => $messageBody,
        'media_url'       => $mediaUrl,
        'read_at'         => null,
    ));
    // Back-link any orphaned messages from same chat_id
    Database::query('Papir',
        "UPDATE cp_messages SET counterparty_id = {$counterpartyId}
         WHERE channel = 'telegram' AND phone = '{$tgEsc}' AND counterparty_id = 0 AND lead_id IS NULL");
} else {
    // Unknown user — find or create a lead
    $leadId = $leadRepo->findByTelegramChatId($chatId);

    if (!$leadId) {
        $displayName = $fromName ? $fromName : $chatId;
        $leadId = $leadRepo->create(array(
            'source'           => 'telegram',
            'display_name'     => $displayName,
            'telegram_chat_id' => $chatId,
        ));
    }

    if ($leadId) {
        $leadRepo->saveMessage($leadId, array(
            'channel'   => 'telegram',
            'direction' => 'in',
            'status'    => 'delivered',
            'phone'     => $chatId,
            'body'      => $messageBody,
            'media_url' => $mediaUrl,
            'read_at'   => null,
        ));
    }
}

echo json_encode(array('ok' => true));
