<?php
/**
 * Incoming Viber webhook from Alpha SMS (2way).
 * Alpha SMS sends POST with: action, sender, phone, message, datetime
 * Configure webhook URL in Alpha SMS cabinet:
 *   https://papir.officetorg.com.ua/counterparties/webhook/viber_in
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$input  = file_get_contents('php://input');
$post   = array();

// Alpha SMS sends either JSON body or form-encoded
if ($input) {
    $decoded = json_decode($input, true);
    if (is_array($decoded)) {
        $post = $decoded;
    }
}
if (empty($post)) {
    $post = $_POST;
}

$action   = isset($post['action'])    ? trim($post['action'])    : '';
$phone    = isset($post['phone'])     ? trim($post['phone'])     : '';
$message  = isset($post['message'])  ? trim($post['message'])   : '';
$sender   = isset($post['sender'])   ? trim($post['sender'])    : '';
$dt       = isset($post['datetime']) ? trim($post['datetime'])  : date('Y-m-d H:i:s');
// Alpha SMS may use different field names for media in incoming messages
$mediaUrl = '';
foreach (array('media', 'image', 'file', 'media_url', 'file_url') as $_f) {
    if (!empty($post[$_f])) { $mediaUrl = trim($post[$_f]); break; }
}

// Log all incoming requests for debugging
$_logLine = date('Y-m-d H:i:s') . ' action=' . $action . ' phone=' . $phone . ' msg=' . $message . ' post=' . json_encode($post) . "\n";
error_log('[viber_in] ' . $_logLine);
@file_put_contents('/tmp/viber_in.log', $_logLine, FILE_APPEND);

if ($action !== 'viber/2way' || !$phone) {
    echo json_encode(array('ok' => true));
    exit;
}

// Client sent something (text or possibly an attachment Alpha SMS doesn't forward as text)
// Note: real delivery reports have action='' and are already filtered above.
// Do NOT filter by sender='OfficeTorg' here — Alpha SMS sets our own sender name even on incoming messages.
if (!$message && !$mediaUrl) {
    $message = '[📷 Медіа-повідомлення]';
}

$chatRepo = new ChatRepository();
$leadRepo = new LeadRepository();

$normalizedPhone = AlphaSmsService::normalizePhone($phone);
$messageBody     = $message ? $message : ($mediaUrl ? '[фото]' : '');

// External ID for deduplication with poll_viber_replies
// Format: vh_{last9digits}_{YmdHi} — minute-level bucket prevents cross-save duplicates
$phone9  = AlphaSmsService::phoneLast9($normalizedPhone);
$dtTs    = $dt ? @strtotime($dt) : time();
$extId   = 'vh_' . $phone9 . '_' . date('YmdHi', $dtTs ? $dtTs : time());

// Dedup 1: by webhook external_id (prevents webhook retries)
$dupCheck = Database::exists('Papir', 'cp_messages', array(
    'external_id' => $extId, 'channel' => 'viber', 'direction' => 'in',
));
if ($dupCheck['ok'] && $dupCheck['exists']) {
    echo json_encode(array('ok' => true));
    exit;
}

// Dedup 2: content-based — catches messages already saved by poll_viber_replies.php
// (poll uses a different external_id format, so external_id check above won't catch them)
if ($messageBody !== '') {
    $bodyEsc  = Database::escape('Papir', $messageBody);
    $phoneEsc2 = Database::escape('Papir', $normalizedPhone);
    $contentDup = Database::fetchRow('Papir',
        "SELECT id FROM cp_messages
         WHERE channel = 'viber' AND direction = 'in'
           AND phone = '{$phoneEsc2}'
           AND body  = '{$bodyEsc}'
           AND created_at >= NOW() - INTERVAL 15 MINUTE
         LIMIT 1");
    if ($contentDup['ok'] && !empty($contentDup['row'])) {
        echo json_encode(array('ok' => true));
        exit;
    }
}

// ── Перевірка блок-ліста спамерів ────────────────────────────────────────────
$phoneEsc = Database::escape('Papir', $normalizedPhone);
$spamChk  = Database::fetchRow('Papir',
    "SELECT id FROM spam_senders WHERE channel='viber' AND identifier='{$phoneEsc}' LIMIT 1");
if ($spamChk['ok'] && !empty($spamChk['row'])) {
    echo json_encode(array('ok' => true));
    exit;
}

// 1. Try to find known counterparty
$counterpartyId = $chatRepo->findCounterpartyByPhone($phone);

if ($counterpartyId > 0) {
    $chatRepo->saveMessage(array(
        'counterparty_id' => $counterpartyId,
        'channel'         => 'viber',
        'direction'       => 'in',
        'status'          => 'delivered',
        'phone'           => $normalizedPhone,
        'body'            => $messageBody,
        'media_url'       => $mediaUrl ? $mediaUrl : null,
        'external_id'     => $extId,
        'read_at'         => null,
    ));
} else {
    $leadId = $leadRepo->findByPhone($phone);
    if (!$leadId) {
        $leadId = $leadRepo->create(array(
            'source'       => 'viber',
            'display_name' => $normalizedPhone,
            'phone'        => $normalizedPhone,
        ));
    }
    if ($leadId) {
        $leadRepo->saveMessage($leadId, array(
            'channel'     => 'viber',
            'direction'   => 'in',
            'status'      => 'delivered',
            'phone'       => $normalizedPhone,
            'body'        => $messageBody,
            'media_url'   => $mediaUrl ? $mediaUrl : null,
            'external_id' => $extId,
            'read_at'     => null,
        ));
    }
}

echo json_encode(array('ok' => true));
