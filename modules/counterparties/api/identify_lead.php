<?php
/**
 * POST /counterparties/api/identify_lead
 * Merge lead into existing counterparty
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$leadId         = isset($_POST['lead_id'])         ? (int)$_POST['lead_id']         : 0;
$counterpartyId = isset($_POST['counterparty_id']) ? (int)$_POST['counterparty_id'] : 0;

if ($leadId <= 0 || $counterpartyId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'lead_id and counterparty_id required'));
    exit;
}

$leadRepo = new LeadRepository();
$lead     = $leadRepo->getById($leadId);
if (!$lead) {
    echo json_encode(array('ok' => false, 'error' => 'lead not found'));
    exit;
}

$cpRepo = new CounterpartyRepository();
$cp     = $cpRepo->getById($counterpartyId);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'counterparty not found'));
    exit;
}

// Parse conflict resolutions: resolutions[phone]=existing|lead, etc.
$resolutions = array();
if (!empty($_POST['resolutions']) && is_array($_POST['resolutions'])) {
    $allowed = array('phone', 'email', 'telegram_chat_id');
    foreach ($allowed as $f) {
        if (isset($_POST['resolutions'][$f])) {
            $val = $_POST['resolutions'][$f];
            if ($val === 'existing' || $val === 'lead') {
                $resolutions[$f] = $val;
            }
        }
    }
}

// Parse supplements: supplements[]=phone, supplements[]=email, etc.
$supplements = array();
if (!empty($_POST['supplements']) && is_array($_POST['supplements'])) {
    $allowed = array('phone', 'email', 'telegram_chat_id');
    foreach ($_POST['supplements'] as $f) {
        if (in_array($f, $allowed)) {
            $supplements[] = $f;
        }
    }
}

$ok = $leadRepo->merge($leadId, $counterpartyId, $resolutions, $supplements);

echo json_encode(array(
    'ok'               => $ok,
    'counterparty_id'  => $counterpartyId,
    'counterparty_name'=> $cp['name'],
));
