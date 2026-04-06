<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$targetCpId  = isset($_POST['id'])             ? (int)$_POST['id']           : 0;
$channel     = isset($_POST['channel'])        ? trim($_POST['channel'])      : '';
$fwdMsgId    = isset($_POST['forward_msg_id']) ? (int)$_POST['forward_msg_id'] : 0;

if ($targetCpId <= 0 || $fwdMsgId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id і forward_msg_id обовʼязкові'));
    exit;
}

$allowed = array('viber', 'sms', 'email', 'telegram', 'note');
if (!in_array($channel, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid channel'));
    exit;
}

// Fetch original message + author
$rOrig = Database::fetchRow('Papir',
    "SELECT m.body, m.media_url, m.direction, m.operator_name, m.counterparty_id,
            c.name AS cp_name
     FROM cp_messages m
     LEFT JOIN counterparty c ON c.id = m.counterparty_id
     WHERE m.id = {$fwdMsgId} LIMIT 1");
if (!$rOrig['ok'] || empty($rOrig['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Повідомлення не знайдено'));
    exit;
}
$origBody     = $rOrig['row']['body'];
$origMediaUrl = $rOrig['row']['media_url'];
$origAuthor   = ($rOrig['row']['direction'] === 'out')
    ? ($rOrig['row']['operator_name'] ? $rOrig['row']['operator_name'] : 'Оператор')
    : ($rOrig['row']['cp_name']       ? $rOrig['row']['cp_name']       : 'Клієнт');

// Verify target counterparty exists
$rCp = Database::fetchRow('Papir',
    "SELECT id FROM counterparty WHERE id = {$targetCpId} AND status = 1 LIMIT 1");
if (!$rCp['ok'] || empty($rCp['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Контрагента не знайдено'));
    exit;
}

$chatRepo = new ChatRepository();

// Note channel: just save internally without external delivery
if ($channel === 'note') {
    $chatRepo->saveMessage(array(
        'counterparty_id' => $targetCpId,
        'channel'         => 'note',
        'direction'       => 'out',
        'status'          => 'sent',
        'body'            => '↩ ' . $origAuthor . ': ' . $origBody,
        'media_url'       => $origMediaUrl ? $origMediaUrl : null,
    ));
    echo json_encode(array('ok' => true));
    exit;
}

// For Viber/SMS — fetch target phone (stored in counterparty_contact or counterparty_person)
$rPhone = Database::fetchRow('Papir',
    "SELECT COALESCE(cc.phone, cp.phone) AS phone
     FROM counterparty c
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     WHERE c.id = {$targetCpId}
     LIMIT 1");
$phone = ($rPhone['ok'] && !empty($rPhone['row'])) ? trim($rPhone['row']['phone']) : '';
if (!$phone) {
    echo json_encode(array('ok' => false, 'error' => 'У контрагента немає телефону'));
    exit;
}
$phone = AlphaSmsService::normalizePhone($phone);
if (!$phone) {
    echo json_encode(array('ok' => false, 'error' => 'Невалідний телефон контрагента'));
    exit;
}

$fwdBody = '↩ ' . $origAuthor . ': ' . $origBody;
$externalId = null;
$status     = 'pending';

if ($channel === 'viber') {
    $result = AlphaSmsService::sendViber($phone, $fwdBody);
    if ($result['ok']) {
        $externalId = isset($result['msg_id']) ? (string)$result['msg_id'] : null;
        $status     = 'sent';
    } else {
        echo json_encode(array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Помилка Viber'));
        exit;
    }
} elseif ($channel === 'sms') {
    $result = AlphaSmsService::sendSms($phone, $fwdBody);
    if ($result['ok']) {
        $externalId = isset($result['msg_id']) ? (string)$result['msg_id'] : null;
        $status     = 'sent';
    } else {
        echo json_encode(array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Помилка SMS'));
        exit;
    }
} else {
    echo json_encode(array('ok' => false, 'error' => 'Пересилання через цей канал не підтримується'));
    exit;
}

$chatRepo->saveMessage(array(
    'counterparty_id' => $targetCpId,
    'channel'         => $channel,
    'direction'       => 'out',
    'status'          => $status,
    'phone'           => $phone,
    'body'            => $fwdBody,
    'media_url'       => $origMediaUrl ? $origMediaUrl : null,
    'external_id'     => $externalId,
));

echo json_encode(array('ok' => true));