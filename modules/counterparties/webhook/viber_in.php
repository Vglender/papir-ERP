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

// Empty message with no media = delivery report for our outbound message (Alpha SMS limitation)
// We try to detect by checking if $sender matches our alpha name — skip delivery reports
$isDeliveryReport = (!$message && !$mediaUrl && $sender === 'OfficeTorg');
if ($isDeliveryReport) {
    echo json_encode(array('ok' => true));
    exit;
}

// Client sent something (text or possibly an attachment Alpha SMS doesn't forward)
if (!$message && !$mediaUrl) {
    $message = '[📷 Медіа-повідомлення]';
}

$chatRepo = new ChatRepository();
$leadRepo = new LeadRepository();

$normalizedPhone = AlphaSmsService::normalizePhone($phone);
$messageBody     = $message ? $message : ($mediaUrl ? '[фото]' : '');

// 1. Try to find known counterparty
$counterpartyId = $chatRepo->findCounterpartyByPhone($phone);

if ($counterpartyId > 0) {
    // Known counterparty — save to their history
    $chatRepo->saveMessage(array(
        'counterparty_id' => $counterpartyId,
        'channel'         => 'viber',
        'direction'       => 'in',
        'status'          => 'read',
        'phone'           => $normalizedPhone,
        'body'            => $messageBody,
        'media_url'       => $mediaUrl ? $mediaUrl : null,
        'read_at'         => null,
    ));
} else {
    // Unknown number — find or create a lead
    $leadId = $leadRepo->findByPhone($phone);

    if (!$leadId) {
        // New contact — create lead with display_name from phone
        $leadId = $leadRepo->create(array(
            'source'       => 'viber',
            'display_name' => $normalizedPhone,
            'phone'        => $normalizedPhone,
        ));
    }

    if ($leadId) {
        $leadRepo->saveMessage($leadId, array(
            'channel'   => 'viber',
            'direction' => 'in',
            'status'    => 'read',
            'phone'     => $normalizedPhone,
            'body'      => $messageBody,
            'media_url' => $mediaUrl ? $mediaUrl : null,
            'read_at'   => null,
        ));
    }
}

echo json_encode(array('ok' => true));
