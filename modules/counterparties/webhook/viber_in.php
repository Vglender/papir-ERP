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

$action  = isset($post['action'])   ? trim($post['action'])   : '';
$phone   = isset($post['phone'])    ? trim($post['phone'])    : '';
$message = isset($post['message'])  ? trim($post['message'])  : '';
$sender  = isset($post['sender'])   ? trim($post['sender'])   : '';
$dt      = isset($post['datetime']) ? trim($post['datetime']) : date('Y-m-d H:i:s');

// Accept only viber 2way action
if ($action !== 'viber/2way' || !$phone || !$message) {
    echo json_encode(array('ok' => true)); // always 200 to Alpha SMS
    exit;
}

$chatRepo = new ChatRepository();

// Find counterparty by phone
$counterpartyId = $chatRepo->findCounterpartyByPhone($phone);

// Save message (even if counterparty not found — phone stored, can link later)
$chatRepo->saveMessage(array(
    'counterparty_id' => $counterpartyId > 0 ? $counterpartyId : 0,
    'channel'         => 'viber',
    'direction'       => 'in',
    'status'          => 'read', // incoming = already received
    'phone'           => AlphaSmsService::normalizePhone($phone),
    'body'            => $message,
    'read_at'         => null,   // null = unread, mark when manager opens chat
));

echo json_encode(array('ok' => true));
