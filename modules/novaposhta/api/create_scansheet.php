<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef    = isset($_POST['sender_ref'])     ? trim($_POST['sender_ref'])     : '';
$ttnRefsRaw   = isset($_POST['ttn_refs'])       ? $_POST['ttn_refs']            : '';
$scanSheetRef = isset($_POST['scan_sheet_ref']) ? trim($_POST['scan_sheet_ref']): null;

if (!$senderRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}

// ttn_refs can be JSON array or comma-separated list of NP refs
if (is_array($ttnRefsRaw)) {
    $ttnRefs = $ttnRefsRaw;
} else {
    $decoded = json_decode($ttnRefsRaw, true);
    $ttnRefs = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $ttnRefsRaw)));
}

if (empty($ttnRefs)) {
    echo json_encode(array('ok' => false, 'error' => 'ttn_refs required'));
    exit;
}

$result = \Papir\Crm\ScanSheetService::addDocuments($senderRef, $ttnRefs, $scanSheetRef ?: null);
echo json_encode($result);