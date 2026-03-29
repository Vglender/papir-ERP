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
    exit;
}

$data = json_decode(base64_decode($pubsub['message']['data']), true);
if (!$data || empty($data['emailAddress'])) {
    http_response_code(204);
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
$client->setScopes(array('https://www.googleapis.com/auth/gmail.readonly'));
$client->setAccessType('offline');
$client->setAccessToken($tokenData);

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
    if ($newHistoryId) { file_put_contents($historyIdPath, $newHistoryId); }
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
    if ($newHistoryId) { file_put_contents($historyIdPath, $newHistoryId); }
    http_response_code(204);
    exit;
}

file_put_contents($historyIdPath, $newHistoryId);

$histories = $historyList->getHistory();
if (empty($histories)) {
    http_response_code(204);
    exit;
}

// Collect unique message IDs
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
$leadRepo = new LeadRepository();

foreach (array_keys($msgIds) as $msgId) {
    try {
        $message = $gmail->users_messages->get('me', $msgId, array('format' => 'full'));
    } catch (Exception $e) {
        continue;
    }

    $headers = array();
    $payload = $message->getPayload();
    if ($payload) {
        foreach ($payload->getHeaders() as $h) {
            $headers[strtolower($h->getName())] = $h->getValue();
        }
    }

    $fromRaw   = isset($headers['from'])    ? $headers['from']    : '';
    $subject   = isset($headers['subject']) ? $headers['subject'] : '(без теми)';
    $fromEmail = _gmail_extract_email($fromRaw);
    $fromName  = _gmail_extract_name($fromRaw);

    // Extract plain text body
    $bodyText = _gmail_get_body($payload);
    if (!$bodyText) { $bodyText = '(порожній лист)'; }

    // Extract attachments (download and save to CRM storage)
    $attachments = _gmail_get_attachments($gmail, $msgId, $payload);

    // ── Route to counterparty or lead ──────────────────────────────────────────

    $counterpartyId = _gmail_find_counterparty($fromEmail);

    if ($counterpartyId > 0) {
        // Known counterparty — save main message
        $chatRepo->saveMessage(array(
            'counterparty_id' => $counterpartyId,
            'channel'         => 'email',
            'direction'       => 'in',
            'status'          => 'delivered',
            'email_addr'      => $fromEmail,
            'subject'         => $subject,
            'body'            => ($fromName ? "[{$fromName}] " : '') . $bodyText,
            'external_id'     => $msgId,
            'read_at'         => null,
        ));
        // Save each attachment as a separate message linked to the same conversation
        foreach ($attachments as $att) {
            $chatRepo->saveMessage(array(
                'counterparty_id' => $counterpartyId,
                'channel'         => 'email',
                'direction'       => 'in',
                'status'          => 'delivered',
                'email_addr'      => $fromEmail,
                'subject'         => $subject,
                'body'            => '📎 ' . $att['name'],
                'media_url'       => $att['url'],
                'external_id'     => $msgId . '_' . $att['name'],
                'read_at'         => null,
            ));
        }
    } else {
        // Unknown sender — find or create lead
        $leadId = $leadRepo->findByEmail($fromEmail);

        if (!$leadId) {
            $displayName = $fromName ? $fromName : $fromEmail;
            $leadId = $leadRepo->create(array(
                'source'       => 'email',
                'display_name' => $displayName,
                'email'        => $fromEmail,
            ));
        }

        if ($leadId) {
            $leadRepo->saveMessage($leadId, array(
                'channel'     => 'email',
                'direction'   => 'in',
                'status'      => 'delivered',
                'email_addr'  => $fromEmail,
                'subject'     => $subject,
                'body'        => ($fromName ? "[{$fromName}] " : '') . $bodyText,
                'external_id' => $msgId,
                'read_at'     => null,
            ));
            foreach ($attachments as $att) {
                $leadRepo->saveMessage($leadId, array(
                    'channel'     => 'email',
                    'direction'   => 'in',
                    'status'      => 'delivered',
                    'email_addr'  => $fromEmail,
                    'subject'     => $subject,
                    'body'        => '📎 ' . $att['name'],
                    'media_url'   => $att['url'],
                    'external_id' => $msgId . '_' . $att['name'],
                    'read_at'     => null,
                ));
            }
        }
    }
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

    $mime = $payload->getMimeType();

    // Single-part with data
    $body = $payload->getBody();
    if ($body && $body->getData() && $mime === 'text/plain') {
        return trim(base64_decode(strtr($body->getData(), '-_', '+/')));
    }

    $parts = $payload->getParts();
    if (!$parts) return '';

    $plain = '';
    foreach ($parts as $part) {
        $partMime = $part->getMimeType();
        if ($partMime === 'text/plain') {
            $b = $part->getBody();
            if ($b && $b->getData()) {
                $plain = trim(base64_decode(strtr($b->getData(), '-_', '+/')));
            }
        } elseif (strpos($partMime, 'multipart/') === 0) {
            $nested = _gmail_get_body($part);
            if ($nested && !$plain) { $plain = $nested; }
        }
    }
    return $plain;
}

function _gmail_get_attachments($gmail, $msgId, $payload)
{
    $attachments = array();
    if (!$payload) return $attachments;

    $parts = $payload->getParts();
    if (!$parts) return $attachments;

    $dir    = '/var/www/menufold/data/www/officetorg.com.ua/image/crm/messages/';
    $baseUrl = 'https://officetorg.com.ua/image/crm/messages/';

    foreach ($parts as $part) {
        $partMime = $part->getMimeType();

        // Recurse into nested multipart
        if (strpos($partMime, 'multipart/') === 0) {
            $nested = _gmail_get_attachments($gmail, $msgId, $part);
            $attachments = array_merge($attachments, $nested);
            continue;
        }

        // Skip text parts (body, html)
        if ($partMime === 'text/plain' || $partMime === 'text/html') {
            continue;
        }

        // Check for attachment: must have filename.
        // Use getFilename() first — Google API parses both Content-Disposition and Content-Type name=
        $filename = '';
        if (method_exists($part, 'getFilename') && $part->getFilename()) {
            $filename = $part->getFilename();
        }
        // Fallback: parse headers manually (Content-Disposition and Content-Type)
        if (!$filename) {
            foreach ($part->getHeaders() as $h) {
                $hVal = $h->getValue();
                // filename= or filename*= (RFC2231)
                if (preg_match('/(?:filename\*?)\s*=\s*(?:UTF-8\'\')?["\']?([^"\';\s]+)["\']?/i', $hVal, $m)) {
                    $candidate = rawurldecode(trim($m[1]));
                    if ($candidate) { $filename = $candidate; break; }
                }
                // Content-Type: name=
                if (preg_match('/name\s*=\s*["\']?([^"\';\s]+)["\']?/i', $hVal, $m)) {
                    $candidate = trim($m[1]);
                    if ($candidate) { $filename = $candidate; break; }
                }
            }
        }

        if (!$filename) continue;

        // Get attachment data
        $body       = $part->getBody();
        $attachId   = $body ? $body->getAttachmentId() : null;
        $data       = $body ? $body->getData() : null;

        if ($attachId) {
            // Large attachment: fetch separately
            try {
                $att  = $gmail->users_messages_attachments->get('me', $msgId, $attachId);
                $data = $att->getData();
            } catch (Exception $e) {
                continue;
            }
        }

        if (!$data) continue;

        $fileData = base64_decode(strtr($data, '-_', '+/'));
        if (!$fileData) continue;

        // Save file with sanitized name
        $safeExt  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $saveName = date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8)
                  . ($safeExt ? '.' . $safeExt : '');
        if (@file_put_contents($dir . $saveName, $fileData) === false) continue;

        $attachments[] = array(
            'name' => $filename,
            'url'  => $baseUrl . $saveName,
        );
    }

    return $attachments;
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
