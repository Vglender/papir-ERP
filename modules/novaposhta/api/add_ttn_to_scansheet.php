<?php
/**
 * POST /novaposhta/api/add_ttn_to_scansheet
 * Adds a single TTN to an open scan sheet (or creates a new one).
 *
 * POST: ttn_id
 *       scan_sheet_ref  (optional — if empty, uses the latest open sheet or creates new)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ttnId       = isset($_POST['ttn_id'])        ? (int)$_POST['ttn_id']               : 0;
$targetSheet = isset($_POST['scan_sheet_ref'])? trim($_POST['scan_sheet_ref'])        : '';

if (!$ttnId) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_id required'));
    exit;
}

$ttn = \Papir\Crm\TtnRepository::getById($ttnId);
if (!$ttn) {
    echo json_encode(array('ok' => false, 'error' => 'TTN not found'));
    exit;
}
if (!$ttn['int_doc_number']) {
    echo json_encode(array('ok' => false, 'error' => 'ТТН не має номера ЕН — неможливо додати до реєстру'));
    exit;
}

// Resolve scan sheet ref: use provided, else find open, else null (create new)
if (!$targetSheet) {
    $eSenderRef = \Database::escape('Papir', $ttn['sender_ref']);
    $rSheet = \Database::fetchRow('Papir',
        "SELECT Ref FROM np_scan_sheets
         WHERE sender_ref = '{$eSenderRef}' AND status = 'open'
         ORDER BY DateTime DESC LIMIT 1");
    if ($rSheet['ok'] && $rSheet['row']) {
        $targetSheet = $rSheet['row']['Ref'];
    }
}

$result = \Papir\Crm\ScanSheetService::addDocuments(
    $ttn['sender_ref'],
    array($ttn['ref']),
    $targetSheet ?: null
);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

echo json_encode(array(
    'ok'             => true,
    'scan_sheet_ref' => isset($result['scan_sheet_ref']) ? $result['scan_sheet_ref'] : $targetSheet,
    'created_new'    => !$targetSheet,
));