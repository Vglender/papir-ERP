<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$counterpartyId = isset($_POST['id'])      ? (int)$_POST['id']         : 0;
$channel        = isset($_POST['channel']) ? trim($_POST['channel'])    : '';
$body           = isset($_POST['body'])    ? trim($_POST['body'])       : '';
$subject        = isset($_POST['subject']) ? trim($_POST['subject'])    : 'Повідомлення від Papir CRM';

if ($counterpartyId <= 0 || !$body) {
    echo json_encode(array('ok' => false, 'error' => 'id і body обовʼязкові'));
    exit;
}

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if (!in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

// Get counterparty phone
$repo = new CounterpartyRepository();
$cp   = $repo->getById($counterpartyId);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'Контрагента не знайдено'));
    exit;
}

$phone = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
$email = $cp['company_email'] ? $cp['company_email'] : $cp['person_email'];

$chatRepo   = new ChatRepository();
$externalId = null;
$status     = 'sent';

// ── Send via channel ──────────────────────────────────────────────────────────
if ($channel === 'viber' || $channel === 'sms') {
    if (!$phone) {
        echo json_encode(array('ok' => false, 'error' => 'Не вказано номер телефону контрагента'));
        exit;
    }

    if ($channel === 'viber') {
        $result = AlphaSmsService::sendViber($phone, $body);
    } else {
        $result = AlphaSmsService::sendSms($phone, $body);
    }

    if (!$result['ok']) {
        echo json_encode(array('ok' => false, 'error' => $result['error']));
        exit;
    }
    $externalId = isset($result['msg_id']) ? (string)$result['msg_id'] : null;

} elseif ($channel === 'note') {
    // Internal note — no external send
    $status = 'sent';

} elseif ($channel === 'email') {
    if (!$email) {
        echo json_encode(array('ok' => false, 'error' => 'Не вказано email контрагента'));
        exit;
    }
    $toName = $cp['name'] ? $cp['name'] : '';
    $result = GmailSmtpService::send($email, $toName, $subject, $body);
    if (!$result['ok']) {
        echo json_encode(array('ok' => false, 'error' => $result['error']));
        exit;
    }

} elseif ($channel === 'telegram') {
    $tgChatId = $chatRepo->getTelegramChatId($counterpartyId);
    if (!$tgChatId) {
        echo json_encode(array('ok' => false, 'error' => 'Клієнт ще не ініціював діалог у Telegram'));
        exit;
    }
    $result = TelegramBotService::sendMessage($tgChatId, $body);
    if (!$result['ok']) {
        echo json_encode(array('ok' => false, 'error' => $result['error']));
        exit;
    }
}

// ── Save message ─────────────────────────────────────────────────────────────
// Determine phone/identifier to store
$savePhone = null;
if ($channel === 'viber' || $channel === 'sms') {
    $savePhone = $phone ? AlphaSmsService::normalizePhone($phone) : null;
} elseif ($channel === 'telegram') {
    $savePhone = isset($tgChatId) ? $tgChatId : null;
}

$msgId = $chatRepo->saveMessage(array(
    'counterparty_id' => $counterpartyId,
    'channel'         => $channel,
    'direction'       => 'out',
    'status'          => $status,
    'phone'           => $savePhone,
    'email_addr'      => ($channel === 'email') ? $email : null,
    'subject'         => ($channel === 'email') ? $subject : null,
    'body'            => $body,
    'external_id'     => $externalId,
));

if (!$msgId) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
    exit;
}

$now = date('Y-m-d H:i:s');
echo json_encode(array(
    'ok'      => true,
    'message' => array(
        'id'         => $msgId,
        'channel'    => $channel,
        'direction'  => 'out',
        'status'     => $status,
        'body'       => $body,
        'created_at' => $now,
        'read_at'    => $now,
    ),
));
