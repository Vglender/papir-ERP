<?php
/**
 * POST /counterparties/api/create_from_lead
 * Create new counterparty from lead data, then merge
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$leadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
$type   = isset($_POST['type'])    ? trim($_POST['type'])    : 'person';
$name   = isset($_POST['name'])    ? trim($_POST['name'])    : '';

if ($leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'lead_id required'));
    exit;
}
if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'name required'));
    exit;
}
if (!in_array($type, array('company', 'fop', 'person', 'other'))) {
    $type = 'person';
}

$leadRepo = new LeadRepository();
$lead     = $leadRepo->getById($leadId);
if (!$lead) {
    echo json_encode(array('ok' => false, 'error' => 'lead not found'));
    exit;
}

// Build counterparty data from lead
$cpData = array(
    'type'  => $type,
    'name'  => $name,
    'phone' => $lead['phone'] ? $lead['phone'] : '',
    'email' => $lead['email'] ? $lead['email'] : '',
);

// For person: try to split name into parts
if ($type === 'person') {
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        $cpData['last_name']  = $parts[0];
        $cpData['first_name'] = $parts[1];
        $cpData['middle_name']= isset($parts[2]) ? $parts[2] : '';
    }
}

$cpRepo = new CounterpartyRepository();
$cpId   = $cpRepo->create($cpData);

if ($cpId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'failed to create counterparty'));
    exit;
}

// Copy telegram_chat_id to counterparty
if (!empty($lead['telegram_chat_id'])) {
    Database::update('Papir', 'counterparty',
        array('telegram_chat_id' => $lead['telegram_chat_id']),
        array('id' => $cpId));
}

// Merge lead → counterparty
$leadRepo->merge($leadId, $cpId);

$cp = $cpRepo->getById($cpId);

echo json_encode(array(
    'ok'               => true,
    'counterparty_id'  => $cpId,
    'counterparty_name'=> $cp ? $cp['name'] : $name,
));