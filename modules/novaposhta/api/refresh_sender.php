<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef = isset($_POST['sender_ref']) ? trim($_POST['sender_ref']) : '';
if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

$sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
if (!$sender) {
    echo json_encode(array('ok' => false, 'error' => 'Sender not found'));
    exit;
}

$apiKey = $sender['api'];
if (!$apiKey) {
    echo json_encode(array('ok' => false, 'error' => 'No API key for this sender'));
    exit;
}

$np   = new \Papir\Crm\NovaPoshta($apiKey);
$log  = array();

// ── Step 1: Refresh sender (Counterparty) info ────────────────────────────
// getCounterparties returns organization-level counterparties (Ref = org ref).
// np_sender.Ref = contact person ref; np_sender.Counterparty = org ref.
// These are different — match by EDRPOU or CounterpartyFullName, or just take
// the first result (each API key belongs to exactly one organization).
$r = $np->call('Counterparty', 'getCounterparties', array(
    'CounterpartyProperty' => 'Sender',
    'Page' => 1,
));
if ($r['ok'] && !empty($r['data'])) {
    // Take the first (and usually only) organization counterparty for this API key
    $cp  = $r['data'][0];
    $upd = array();
    // Counterparty = org ref returned by getCounterparties (use as Sender in InternetDocument)
    if (!empty($cp['Ref']))               $upd['Counterparty']      = $cp['Ref'];
    if (!empty($cp['Description']))       $upd['CounterpartyFullName'] = $cp['Description'];
    if (!empty($cp['FirstName']))         $upd['FirstName']          = $cp['FirstName'];
    if (!empty($cp['LastName']))          $upd['LastName']           = $cp['LastName'];
    if (!empty($cp['MiddleName']))        $upd['MiddleName']         = $cp['MiddleName'];
    if (!empty($cp['EDRPOU']))            $upd['EDRPOU']             = $cp['EDRPOU'];
    if (!empty($cp['CounterpartyType'])) $upd['CounterpartyType']   = $cp['CounterpartyType'];
    if (!empty($upd)) {
        \Database::update('Papir', 'np_sender', $upd, array('Ref' => $senderRef));
    }
    $log[] = 'Counterparty org ref updated: ' . (isset($cp['Ref']) ? $cp['Ref'] : '?');
} else {
    $log[] = 'Counterparty fetch skipped: ' . ($r['error'] ?: 'no data');
}

// ── Step 2: Addresses ────────────────────────────────────────────────────
$ra = $np->call('Counterparty', 'getCounterpartyAddresses', array(
    'Ref'           => $senderRef,
    'ContragenType' => 'Sender',
));
if ($ra['ok'] && !empty($ra['data'])) {
    foreach ($ra['data'] as $addr) {
        \Papir\Crm\SenderRepository::upsertAddress($senderRef, $addr);
    }
    // Set default if none is set
    $existing = \Papir\Crm\SenderRepository::getDefaultAddress($senderRef);
    if (!$existing && !empty($ra['data'][0]['Ref'])) {
        \Papir\Crm\SenderRepository::setDefaultAddress($senderRef, $ra['data'][0]['Ref']);
    }
    $log[] = 'Addresses: ' . count($ra['data']) . ' synced';
} else {
    $log[] = 'Addresses fetch skipped: ' . ($ra['error'] ?: 'no data');
}

// ── Step 3: Contact persons ───────────────────────────────────────────────
$rcp = $np->call('Counterparty', 'getCounterpartyContactPersons', array(
    'Ref' => $senderRef,
));
if ($rcp['ok'] && !empty($rcp['data'])) {
    foreach ($rcp['data'] as $cp) {
        if (empty($cp['Ref'])) continue;
        \Database::upsertOne('Papir', 'np_sender_contact_persons', array(
            'Ref'        => $cp['Ref'],
            'sender_ref' => $senderRef,
            'full_name'  => isset($cp['Description']) ? $cp['Description'] : '',
            'phone'      => isset($cp['Phones'])      ? $cp['Phones']      : '',
            'updated_at' => date('Y-m-d H:i:s'),
        ), array('Ref'));
    }
    $log[] = 'Contacts: ' . count($rcp['data']) . ' synced';
} else {
    $log[] = 'Contacts fetch skipped: ' . ($rcp['error'] ?: 'no data');
}

// ── Return updated data ───────────────────────────────────────────────────
$updatedSender            = \Papir\Crm\SenderRepository::getByRef($senderRef);
$updatedSender['contacts']  = \Papir\Crm\SenderRepository::getContacts($senderRef);
$updatedSender['addresses'] = \Papir\Crm\SenderRepository::getAddresses($senderRef);

echo json_encode(array(
    'ok'     => true,
    'log'    => $log,
    'sender' => $updatedSender,
));