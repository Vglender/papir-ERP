<?php
/**
 * GET /counterparties/api/poll_viber_replies?id=CP_ID
 * Polls Alpha SMS API for replies to our sent Viber messages.
 * Returns count of new messages saved.
 *
 * Mechanism: Alpha SMS stores replies per msg_id of outbound message.
 * We call type=status for each sent message and read replies[].
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$counterpartyId = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$leadId         = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

if ($counterpartyId <= 0 && $leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

// Get sent Viber messages with external_id (msg_id from Alpha SMS) from last 48h
if ($counterpartyId > 0) {
    $where = "counterparty_id = {$counterpartyId}";
} else {
    $where = "lead_id = {$leadId}";
}

$r = Database::fetchAll('Papir',
    "SELECT id, external_id, phone, created_at
     FROM cp_messages
     WHERE {$where}
       AND channel = 'viber'
       AND direction = 'out'
       AND external_id IS NOT NULL
       AND external_id != ''
       AND created_at >= NOW() - INTERVAL 48 HOUR
     ORDER BY id DESC
     LIMIT 20");

if (!$r['ok'] || empty($r['rows'])) {
    echo json_encode(array('ok' => true, 'new' => 0));
    exit;
}

$apiKey  = 'b91da24ccc1696f137169368f81e4033da5deffd';
$apiUrl  = 'https://alphasms.com.ua/api/json.php';
$chatRepo = new ChatRepository();
$newCount = 0;

foreach ($r['rows'] as $sent) {
    $msgId = $sent['external_id'];

    // Call Alpha SMS to get replies for this msg_id
    $payload = json_encode(array(
        'auth' => $apiKey,
        'data' => array(array('type' => 'status', 'msg_id' => $msgId)),
    ));

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
    ));
    $resp = curl_exec($ch);
    curl_close($ch);

    if (!$resp) continue;
    $data = json_decode($resp, true);
    if (!isset($data['success']) || !$data['success']) continue;
    if (empty($data['data'])) continue;

    foreach ($data['data'] as $entry) {
        if (empty($entry['success'])) continue;

        // ── Update delivery status of the outbound message ────────────────────
        if (!empty($entry['data']['status'])) {
            $alphaStatus = strtolower(trim($entry['data']['status']));
            $newStatus   = null;
            if ($alphaStatus === 'delivered') {
                $newStatus = 'delivered';
            } elseif (in_array($alphaStatus, array('seen', 'read', 'opened'))) {
                $newStatus = 'read';
            } elseif (in_array($alphaStatus, array('failed', 'error', 'undelivered', 'rejected'))) {
                $newStatus = 'failed';
            }
            if ($newStatus && !in_array($newStatus, array('sent', 'pending'))) {
                // Only upgrade: pending→sent→delivered→read, never downgrade
                $statusOrder = array('pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => -1);
                $curR = Database::fetchRow('Papir',
                    "SELECT status FROM cp_messages WHERE id = " . (int)$sent['id'] . " LIMIT 1");
                $curStatus = ($curR['ok'] && $curR['row']) ? $curR['row']['status'] : 'sent';
                $canUpgrade = isset($statusOrder[$curStatus]) && isset($statusOrder[$newStatus])
                    && $statusOrder[$newStatus] > $statusOrder[$curStatus];
                if ($canUpgrade || $newStatus === 'failed') {
                    Database::update('Papir', 'cp_messages',
                        array('status' => $newStatus),
                        array('id' => (int)$sent['id']));
                }
            }
        }

        if (empty($entry['data']['replies'])) continue;

        foreach ($entry['data']['replies'] as $repl) {
            $replDt  = isset($repl['datetime']) ? $repl['datetime'] : null;
            $replTs  = $replDt ? date('Y-m-d H:i:s', strtotime($replDt)) : date('Y-m-d H:i:s');
            $body     = null;
            $mediaUrl = null;

            if (isset($repl['message']) && $repl['message'] !== '') {
                $body = mb_strimwidth($repl['message'], 0, 1000, '...');
            } elseif (isset($repl['media'])) {
                $mediaUrl = isset($repl['media']['url'])      ? $repl['media']['url']      : null;
                $body     = isset($repl['media']['filename']) ? $repl['media']['filename'] : '[медіа]';
            }

            if (!$body && !$mediaUrl) continue;

            // Deduplication: skip if already stored by external_id
            $exists = Database::exists('Papir', 'cp_messages', array(
                'channel'     => 'viber',
                'direction'   => 'in',
                'external_id' => $msgId . '_reply_' . $replTs,
            ));
            if ($exists['ok'] && $exists['exists']) continue;

            // Also skip if webhook already saved same body+phone within last 10 min
            if ($body) {
                $bodyEsc  = Database::escape('Papir', $body);
                $phoneEsc = Database::escape('Papir', $sent['phone']);
                $dupCheck = Database::fetchRow('Papir',
                    "SELECT id FROM cp_messages
                     WHERE channel = 'viber' AND direction = 'in'
                       AND phone = '{$phoneEsc}'
                       AND body  = '{$bodyEsc}'
                       AND created_at >= NOW() - INTERVAL 10 MINUTE
                     LIMIT 1");
                if ($dupCheck['ok'] && $dupCheck['row']) continue;
            }

            // Resolve phone from the sent message
            $phone = $sent['phone']
                ? $sent['phone']
                : (isset($data['data'][0]['phone']) ? $data['data'][0]['phone'] : null);

            $row = array(
                'channel'     => 'viber',
                'direction'   => 'in',
                'status'      => 'delivered',
                'phone'       => $phone,
                'body'        => $body ? $body : '[медіа]',
                'media_url'   => $mediaUrl,
                'external_id' => $msgId . '_reply_' . $replTs,
                'read_at'     => null,
            );

            if ($counterpartyId > 0) {
                $row['counterparty_id'] = $counterpartyId;
                $chatRepo->saveMessage($row);
            } else {
                $leadRepo = new LeadRepository();
                $leadRepo->saveMessage($leadId, $row);
            }

            $newCount++;
        }
    }
}

echo json_encode(array('ok' => true, 'new' => $newCount));
