<?php
/**
 * POST /counterparties/webhook/website_form
 * Incoming contact form from website (officetorg.com.ua or menufolder.com.ua)
 *
 * Expected JSON body or POST fields:
 *   name        — visitor name (optional)
 *   phone       — phone number (optional)
 *   email       — email address (optional)
 *   message     — message text
 *   source_page — page URL where form was submitted (optional)
 *   site        — 'off' | 'mff' (optional, default 'off')
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

// Accept JSON or form POST
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
if (is_array($json)) {
    $data = $json;
} else {
    $data = $_POST;
}

$name       = isset($data['name'])        ? trim($data['name'])        : '';
$phone      = isset($data['phone'])       ? trim($data['phone'])       : '';
$email      = isset($data['email'])       ? trim($data['email'])       : '';
$message    = isset($data['message'])     ? trim($data['message'])     : '';
$sourcePage = isset($data['source_page']) ? trim($data['source_page']) : '';

if ($message === '') {
    echo json_encode(array('ok' => false, 'error' => 'message required'));
    exit;
}

// ── Try to find existing counterparty ────────────────────────────────────────

$chatRepo = new ChatRepository();
$cpId     = 0;

if ($phone !== '') {
    $cpId = $chatRepo->findCounterpartyByPhone($phone);
}
if ($cpId === 0 && $email !== '') {
    // Search by email
    $emailEsc = Database::escape('Papir', strtolower($email));
    $r = Database::fetchRow('Papir',
        "SELECT c.id FROM counterparty c
         LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
         LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
         WHERE c.status = 1 AND (LOWER(cc.email) = '{$emailEsc}' OR LOWER(cp.email) = '{$emailEsc}')
         LIMIT 1");
    if ($r['ok'] && $r['row']) $cpId = (int)$r['row']['id'];
}

// ── Try to find existing lead ─────────────────────────────────────────────────

$leadRepo = new LeadRepository();
$leadId   = 0;

if ($cpId === 0) {
    if ($phone !== '') {
        $leadId = $leadRepo->findByPhone($phone);
    }
    if ($leadId === 0 && $email !== '') {
        $leadId = $leadRepo->findByEmail($email);
    }
}

// ── Route message ─────────────────────────────────────────────────────────────

$msgData = array(
    'channel'   => 'note',
    'direction' => 'in',
    'status'    => 'sent',
    'body'      => ($sourcePage ? "[{$sourcePage}]\n" : '') . $message,
    'phone'     => $phone ? $phone : null,
    'email_addr'=> $email ? $email : null,
);

if ($cpId > 0) {
    // Known counterparty
    $chatRepo->saveMessage(array_merge($msgData, array('counterparty_id' => $cpId)));
    echo json_encode(array('ok' => true, 'routed_to' => 'counterparty', 'counterparty_id' => $cpId));
    exit;
}

if ($leadId > 0) {
    // Existing lead — append message
    $leadRepo->saveMessage($leadId, $msgData);
    echo json_encode(array('ok' => true, 'routed_to' => 'lead', 'lead_id' => $leadId));
    exit;
}

// ── Create new lead ───────────────────────────────────────────────────────────

$displayName = $name !== '' ? $name : ($email !== '' ? $email : ($phone !== '' ? $phone : null));
$newLeadId   = $leadRepo->create(array(
    'source'       => 'website',
    'source_ref'   => $sourcePage,
    'display_name' => $displayName,
    'phone'        => $phone ? $phone : null,
    'email'        => $email ? $email : null,
));

if ($newLeadId > 0) {
    $leadRepo->saveMessage($newLeadId, $msgData);
    echo json_encode(array('ok' => true, 'routed_to' => 'new_lead', 'lead_id' => $newLeadId));
} else {
    echo json_encode(array('ok' => false, 'error' => 'failed to create lead'));
}
