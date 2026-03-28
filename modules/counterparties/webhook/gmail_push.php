<?php
/**
 * Gmail Pub/Sub push webhook.
 * Google sends a POST with base64-encoded notification when new mail arrives.
 */
require_once __DIR__ . '/../counterparties_bootstrap.php';
require '/var/sqript/vendor/autoload.php';

header('Content-Type: application/json');

$tokenPath     = __DIR__ . '/../storage/gmail_token.json';
$historyIdPath = __DIR__ . '/../storage/gmail_history_id.txt';

// Parse Pub/Sub push message
$input  = file_get_contents('php://input');
$pubsub = $input ? json_decode($input, true) : null;

if (!$pubsub || empty($pubsub['message']['data'])) {
    http_response_code(204);
    echo json_encode(array('ok' => true));
    exit;
}

$data = json_decode(base64_decode($pubsub['message']['data']), true);
if (!$data || empty($data['emailAddress'])) {
    http_response_code(204);
    echo json_encode(array('ok' => true));
    exit;
}

// Load token
if (!file_exists($tokenPath)) {
    http_response_code(204);
    exit;
}
$tokenData = json_decode(file_get_contents($tokenPath), true);

$client = new Google\Client();
$client->setAuthConfig('/var/sqript/Merchant/credentials.json');
$client->setScopes(['https://www.googleapis.com/auth/gmail.readonly']);
$client->setAccessType('offline');
$client->setAccessToken($tokenData);

// Refresh token if expired
if ($client->isAccessTokenExpired()) {
    if (!empty($tokenData['refresh_token'])) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($tokenData['refresh_token']);
        if (!isset($newToken['error'])) {
            $newToken['refresh_token'] = $tokenData['refresh_token'];
            file_put_contents($tokenPath, json_encode($newToken));
            $client->setAccessToken($newToken);
        }
    }
}

$gmail = new Google\Service\Gmail($client);

// Get history since last known historyId
$lastHistoryId = file_exists($historyIdPath) ? trim(file_get_contents($historyIdPath)) : null;
$newHistoryId  = isset($data['historyId']) ? $data['historyId'] : null;

if (!$lastHistoryId || !$newHistoryId) {
    if ($newHistoryId) {
        file_put_contents($historyIdPath, $newHistoryId);
    }
    http_response_code(204);
    exit;
}

try {
    $historyList = $gmail->users_history->listUsersHistory('me', array(
        'startHistoryId' => $lastHistoryId,
        'historyTypes'   => array('messageAdded'),
        'labelId'        => 'INBOX',
    ));
} catch (Exception $e) {
    // historyId too old — reset
    if ($newHistoryId) { file_put_contents($historyIdPath, $newHistoryId); }
    http_response_code(204);
    exit;
}

// Save latest historyId
file_put_contents($historyIdPath, $newHistoryId);

$histories = $historyList->getHistory();
if (empty($histories)) {
    http_response_code(204);
    exit;
}

// Collect unique message IDs added
$msgIds = array();
foreach ($histories as $history) {
    $added = $history->getMessagesAdded();
    if ($added) {
        foreach ($added as $item) {
            $msgIds[$item->getMessage()->getId()] = true;
        }
    }
}

$chatRepo = new ChatRepository();

foreach (array_keys($msgIds) as $msgId) {
    try {
        $message = $gmail->users_messages->get('me', $msgId, array('format' => 'full'));
    } catch (Exception $e) {
        continue;
    }

    $headers  = array();
    $payload  = $message->getPayload();
    if ($payload) {
        foreach ($payload->getHeaders() as $h) {
            $headers[strtolower($h->getName())] = $h->getValue();
        }
    }

    $fromRaw  = isset($headers['from'])    ? $headers['from']    : '';
    $subject  = isset($headers['subject']) ? $headers['subject'] : '(без теми)';
    $fromEmail = _gmail_extract_email($fromRaw);
    $fromName  = _gmail_extract_name($fromRaw);

    // Extract plain text body
    $body = _gmail_get_body($payload);
    if (!$body) { $body = '(порожній лист)'; }

    // Find counterparty by email
    $counterpartyId = _gmail_find_counterparty($fromEmail);

    $chatRepo->saveMessage(array(
        'counterparty_id' => $counterpartyId,
        'channel'         => 'email',
        'direction'       => 'in',
        'status'          => 'delivered',
        'email_addr'      => $fromEmail,
        'subject'         => $subject,
        'body'            => ($fromName ? "[{$fromName}] " : '') . $body,
        'external_id'     => $msgId,
        'read_at'         => null,
    ));
}

http_response_code(204);
exit;

// ── Helpers ───────────────────────────────────────────────────────────────────

function _gmail_extract_email($from)
{
    if (preg_match('/<([^>]+)>/', $from, $m)) return strtolower(trim($m[1]));
    return strtolower(trim($from));
}

function _gmail_extract_name($from)
{
    if (preg_match('/^"?([^"<]+)"?\s*</', $from, $m)) return trim($m[1], '"');
    return '';
}

function _gmail_get_body($payload)
{
    if (!$payload) return '';

    // Check direct body (single-part)
    $body = $payload->getBody();
    if ($body && $body->getData()) {
        return trim(base64_decode(strtr($body->getData(), '-_', '+/')));
    }

    // Multipart: prefer text/plain
    $parts = $payload->getParts();
    if ($parts) {
        $plain = '';
        foreach ($parts as $part) {
            $mime = $part->getMimeType();
            if ($mime === 'text/plain') {
                $b = $part->getBody();
                if ($b && $b->getData()) {
                    $plain = trim(base64_decode(strtr($b->getData(), '-_', '+/')));
                }
            } elseif ($mime === 'multipart/alternative' || $mime === 'multipart/mixed') {
                // nested parts
                $nested = _gmail_get_body($part);
                if ($nested && !$plain) { $plain = $nested; }
            }
        }
        return $plain;
    }

    return '';
}

function _gmail_find_counterparty($email)
{
    if (!$email) return 0;
    $esc = Database::escape('Papir', strtolower($email));
    $r   = Database::fetchRow('Papir',
        "SELECT c.id FROM counterparty c
         LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
         LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
         WHERE c.status = 1
           AND (LOWER(cc.email) = '{$esc}' OR LOWER(cp.email) = '{$esc}')
         LIMIT 1"
    );
    return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['id'] : 0;
}
