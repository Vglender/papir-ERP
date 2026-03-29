<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$counterpartyId = isset($_POST['id'])        ? (int)$_POST['id']         : 0;
$leadId         = isset($_POST['lead_id'])  ? (int)$_POST['lead_id']    : 0;
$channel        = isset($_POST['channel'])  ? trim($_POST['channel'])    : '';
$body           = isset($_POST['body'])     ? trim($_POST['body'])       : '';
$subject        = isset($_POST['subject'])  ? trim($_POST['subject'])    : 'Повідомлення від Papir CRM';
$mediaUrl       = isset($_POST['media_url'])? trim($_POST['media_url'])  : '';

if (($counterpartyId <= 0 && $leadId <= 0) || (!$body && !$mediaUrl)) {
    echo json_encode(array('ok' => false, 'error' => 'id або lead_id і body/media_url обовʼязкові'));
    exit;
}

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if (!in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

// ── Resolve contact info (counterparty or lead) ───────────────────────────────
$cp    = null;
$lead  = null;
$phone = null;
$email = null;

if ($leadId > 0) {
    $leadRepo = new LeadRepository();
    $lead     = $leadRepo->getById($leadId);
    if (!$lead) {
        echo json_encode(array('ok' => false, 'error' => 'Ліда не знайдено'));
        exit;
    }
    $phone = $lead['phone'];
    $email = $lead['email'];
} else {
    $repo = new CounterpartyRepository();
    $cp   = $repo->getById($counterpartyId);
    if (!$cp) {
        echo json_encode(array('ok' => false, 'error' => 'Контрагента не знайдено'));
        exit;
    }
    $phone = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
    $email = $cp['company_email'] ? $cp['company_email'] : $cp['person_email'];
}

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
        // If there's an image, send via Viber image message; text goes as caption
        if ($mediaUrl && preg_match('/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $mediaUrl)) {
            $caption = ($body && $body !== '[файл]') ? $body : '';
            $result  = AlphaSmsService::sendViberImage($phone, $mediaUrl, $caption);
        } else {
            $result = AlphaSmsService::sendViber($phone, $body);
        }
    } else {
        $result = AlphaSmsService::sendSms($phone, $body);
    }

    if (!$result['ok']) {
        // Save failed message so operator sees delivery failure in chat
        $failPhone = AlphaSmsService::normalizePhone($phone);
        if ($counterpartyId > 0) {
            $chatRepo->saveMessage(array(
                'counterparty_id' => $counterpartyId,
                'channel'         => $channel,
                'direction'       => 'out',
                'status'          => 'failed',
                'phone'           => $failPhone,
                'body'            => $body,
                'media_url'       => $mediaUrl ? $mediaUrl : null,
                'external_id'     => null,
            ));
        } else {
            $leadRepo->saveMessage($leadId, array(
                'channel'     => $channel,
                'direction'   => 'out',
                'status'      => 'failed',
                'phone'       => $failPhone,
                'body'        => $body,
                'media_url'   => $mediaUrl ? $mediaUrl : null,
                'external_id' => null,
            ));
        }
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
    if ($leadId > 0) {
        $tgChatId = $lead['telegram_chat_id'];
    } else {
        $tgChatId = $chatRepo->getTelegramChatId($counterpartyId);
    }
    if (!$tgChatId) {
        echo json_encode(array('ok' => false, 'error' => 'Клієнт ще не ініціював діалог у Telegram'));
        exit;
    }
    if ($mediaUrl && preg_match('/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $mediaUrl)) {
        // Send photo; text becomes the caption (combined in one message)
        $caption = ($body && $body !== '[файл]') ? $body : '';
        $result  = TelegramBotService::sendPhoto($tgChatId, $mediaUrl, $caption);
        // Caption already sent with photo — don't save separate text body in DB
        if ($result['ok'] && $caption) {
            $body = '[фото] ' . $caption;
        } elseif ($result['ok']) {
            $body = '[фото]';
        }
    } else {
        $result = TelegramBotService::sendMessage($tgChatId, $body);
    }
    if (!$result['ok']) {
        echo json_encode(array('ok' => false, 'error' => $result['error']));
        exit;
    }
}

// ── Save message ─────────────────────────────────────────────────────────────
$savePhone = null;
if ($channel === 'viber' || $channel === 'sms') {
    $savePhone = $phone ? AlphaSmsService::normalizePhone($phone) : null;
} elseif ($channel === 'telegram') {
    $savePhone = isset($tgChatId) ? $tgChatId : null;
}

if ($leadId > 0) {
    $msgId = $leadRepo->saveMessage($leadId, array(
        'channel'     => $channel,
        'direction'   => 'out',
        'status'      => $status,
        'phone'       => $savePhone,
        'email_addr'  => ($channel === 'email') ? $email : null,
        'subject'     => ($channel === 'email') ? $subject : null,
        'body'        => $body,
        'media_url'   => $mediaUrl ? $mediaUrl : null,
        'external_id' => $externalId,
    ));
} else {
    $msgId = $chatRepo->saveMessage(array(
        'counterparty_id' => $counterpartyId,
        'channel'         => $channel,
        'direction'       => 'out',
        'status'          => $status,
        'phone'           => $savePhone,
        'email_addr'      => ($channel === 'email') ? $email : null,
        'subject'         => ($channel === 'email') ? $subject : null,
        'body'            => $body,
        'media_url'       => $mediaUrl ? $mediaUrl : null,
        'external_id'     => $externalId,
    ));
}

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
        'media_url'  => $mediaUrl ? $mediaUrl : null,
        'created_at' => $now,
        'read_at'    => $now,
    ),
));
