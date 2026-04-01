<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$senderRef    = isset($_POST['sender_ref'])     ? trim($_POST['sender_ref'])     : '';
$scanSheetRef = isset($_POST['scan_sheet_ref']) ? trim($_POST['scan_sheet_ref']) : '';

if (!$senderRef || !$scanSheetRef) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref and scan_sheet_ref required'));
    exit;
}

$result = \Papir\Crm\ScanSheetService::delete($senderRef, $scanSheetRef);
echo json_encode($result);