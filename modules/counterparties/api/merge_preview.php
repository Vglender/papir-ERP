<?php
/**
 * GET /counterparties/api/merge_preview?lead_id=X&counterparty_id=Y
 *
 * Compares lead contact fields against counterparty fields.
 * Returns:
 *   conflicts    — fields where BOTH sides have non-empty but DIFFERENT values (user must choose)
 *   supplements  — fields where lead has data but counterparty is empty (will be auto-applied)
 *   lead         — lead summary for display
 *   counterparty — counterparty summary for display
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$leadId         = isset($_GET['lead_id'])         ? (int)$_GET['lead_id']         : 0;
$counterpartyId = isset($_GET['counterparty_id']) ? (int)$_GET['counterparty_id'] : 0;

if ($leadId <= 0 || $counterpartyId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'lead_id and counterparty_id required'));
    exit;
}

$leadRepo = new LeadRepository();
$cpRepo   = new CounterpartyRepository();

$lead = $leadRepo->getById($leadId);
if (!$lead) {
    echo json_encode(array('ok' => false, 'error' => 'lead not found'));
    exit;
}

$cp = $cpRepo->getById($counterpartyId);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'counterparty not found'));
    exit;
}

// Resolve counterparty's primary phone and email (person takes priority over company)
$cpPhone    = $cp['person_phone']    ? $cp['person_phone']    : $cp['company_phone'];
$cpEmail    = $cp['person_email']    ? $cp['person_email']    : $cp['company_email'];
$cpTelegram = $cp['telegram_chat_id'];

$leadPhone    = $lead['phone'];
$leadEmail    = $lead['email'];
$leadTelegram = $lead['telegram_chat_id'];

// ── Compare fields ────────────────────────────────────────────────────────────

$conflicts   = array();  // both non-empty, values differ
$supplements = array();  // lead has value, counterparty is empty (auto-apply)

$fields = array(
    'phone'            => array('label' => 'Телефон',  'lead' => $leadPhone,    'existing' => $cpPhone),
    'email'            => array('label' => 'Email',    'lead' => $leadEmail,    'existing' => $cpEmail),
    'telegram_chat_id' => array('label' => 'Telegram', 'lead' => $leadTelegram, 'existing' => $cpTelegram),
);

foreach ($fields as $field => $vals) {
    $hasLead     = !empty($vals['lead']);
    $hasExisting = !empty($vals['existing']);

    if (!$hasLead) {
        // Lead has nothing → no action needed
        continue;
    }

    if (!$hasExisting) {
        // Lead has data, counterparty is empty → supplement (auto-apply lead value)
        $supplements[] = array(
            'field'    => $field,
            'label'    => $vals['label'],
            'value'    => $vals['lead'],
        );
        continue;
    }

    // Both have values — check if they differ
    $leadVal     = strtolower(preg_replace('/\D/', '', $vals['lead']));    // normalize for compare
    $existingVal = strtolower(preg_replace('/\D/', '', $vals['existing']));

    // For email/telegram — compare as-is (lowercase)
    if ($field === 'email' || $field === 'telegram_chat_id') {
        $leadVal     = strtolower(trim($vals['lead']));
        $existingVal = strtolower(trim($vals['existing']));
    }

    if ($leadVal !== $existingVal) {
        $conflicts[] = array(
            'field'    => $field,
            'label'    => $vals['label'],
            'lead'     => $vals['lead'],
            'existing' => $vals['existing'],
        );
    }
    // Equal → no action needed
}

echo json_encode(array(
    'ok'          => true,
    'conflicts'   => $conflicts,
    'supplements' => $supplements,
    'lead'        => array(
        'id'           => (int)$lead['id'],
        'display_name' => $lead['display_name'],
        'source'       => $lead['source'],
        'source_label' => LeadRepository::sourceLabel($lead['source']),
    ),
    'counterparty' => array(
        'id'   => (int)$cp['id'],
        'name' => $cp['name'],
        'type' => $cp['type'],
    ),
));
