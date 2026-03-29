<?php
/**
 * GET /counterparties/api/get_lead_detail?id=X
 * Returns lead data + suggested matches for workspace
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$leadRepo = new LeadRepository();
$lead     = $leadRepo->getById($id);
if (!$lead) {
    echo json_encode(array('ok' => false, 'error' => 'not found'));
    exit;
}

// Auto-suggest counterparty matches
$matches = $leadRepo->findMatches($lead);

// Mark as working if still new
if ($lead['status'] === 'new') {
    $leadRepo->setWorking($id);
    $lead['status'] = 'working';
}

// Build initials/avatar text
$name = $lead['display_name'] ? $lead['display_name'] : LeadRepository::sourceLabel($lead['source']);
$words = preg_split('/\s+/', $name);
if (count($words) >= 2) {
    $initials = mb_strtoupper(
        mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'),
        'UTF-8'
    );
} else {
    $initials = mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8');
}

// Unread counts per channel
$unreadRaw = Database::fetchAll('Papir',
    "SELECT channel, COUNT(*) AS cnt FROM cp_messages
     WHERE lead_id = {$id} AND direction = 'in' AND read_at IS NULL
     GROUP BY channel");
$unreadByChannel = array('viber' => 0, 'sms' => 0, 'email' => 0, 'telegram' => 0, 'note' => 0);
if ($unreadRaw['ok']) {
    foreach ($unreadRaw['rows'] as $row) {
        if (isset($unreadByChannel[$row['channel']])) {
            $unreadByChannel[$row['channel']] = (int)$row['cnt'];
        }
    }
}

echo json_encode(array(
    'ok'   => true,
    'lead' => array(
        'id'               => (int)$lead['id'],
        'source'           => $lead['source'],
        'source_label'     => LeadRepository::sourceLabel($lead['source']),
        'source_icon'      => LeadRepository::sourceIcon($lead['source']),
        'source_ref'       => $lead['source_ref'],
        'display_name'     => $lead['display_name'],
        'name'             => $name,
        'initials'         => $initials,
        'phone'            => $lead['phone'],
        'email'            => $lead['email'],
        'telegram_chat_id' => $lead['telegram_chat_id'],
        'status'           => $lead['status'],
        'created_at'       => $lead['created_at'],
    ),
    'matches'           => $matches,
    'unread_by_channel' => $unreadByChannel,
));