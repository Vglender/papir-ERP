<?php
/**
 * GET /novaposhta/api/get_ttn_detail?ttn_id=X
 * Returns full TTN record + sender addresses for modal.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$ttnId = isset($_GET['ttn_id']) ? (int)$_GET['ttn_id'] : 0;
if (!$ttnId) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$ttn = \Papir\Crm\TtnRepository::getById($ttnId);
if (!$ttn) {
    echo json_encode(array('ok' => false, 'error' => 'TTN not found'));
    exit;
}

// is_editable: only draft (state_define=1) and not yet printed
$ttn['is_editable'] = ((int)$ttn['state_define'] === 1 && !(int)$ttn['is_printed']);

// can_duplicate: needs city_recipient_ref stored OR has int_doc_number (we can try NP API)
$ttn['can_duplicate'] = !empty($ttn['int_doc_number']) || !empty($ttn['city_recipient_ref']);

// All senders for sender select in edit mode
$senders = \Papir\Crm\SenderRepository::getAll();

// Sender addresses for this sender
$senderAddresses = \Papir\Crm\SenderRepository::getAddresses($ttn['sender_ref']);

// Scan sheets for this sender (open ones, for "add to registry" button)
$openSheet = null;
if ($ttn['sender_ref']) {
    $eSenderRef = \Database::escape('Papir', $ttn['sender_ref']);
    $rSheet = \Database::fetchRow('Papir',
        "SELECT Ref, Number FROM np_scan_sheets
         WHERE sender_ref = '{$eSenderRef}' AND status = 'open'
         ORDER BY DateTime DESC LIMIT 1");
    if ($rSheet['ok'] && $rSheet['row']) {
        $openSheet = $rSheet['row'];
    }
}

echo json_encode(array(
    'ok'              => true,
    'ttn'             => $ttn,
    'senders'         => $senders,
    'sender_addresses'=> $senderAddresses,
    'open_sheet'      => $openSheet,
));