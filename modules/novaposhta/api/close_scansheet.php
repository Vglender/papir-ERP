<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$scanSheetRef = isset($_POST['scan_sheet_ref']) ? trim($_POST['scan_sheet_ref']) : '';
if (!$scanSheetRef) {
    echo json_encode(array('ok' => false, 'error' => 'scan_sheet_ref required'));
    exit;
}

$ss = \Papir\Crm\ScanSheetRepository::getByRef($scanSheetRef);
if (!$ss) {
    echo json_encode(array('ok' => false, 'error' => 'Реєстр не знайдено'));
    exit;
}

if ($ss['status'] === 'closed') {
    echo json_encode(array('ok' => true, 'message' => 'Реєстр вже закритий'));
    exit;
}

// Close in NP by triggering print URL (marks as Printed=1 = closed)
$sender = \Papir\Crm\SenderRepository::getByRef($ss['sender_ref']);
if (!$sender || !$sender['api']) {
    echo json_encode(array('ok' => false, 'error' => 'API ключ відправника не знайдено'));
    exit;
}

$printUrl = 'https://my.novaposhta.ua/scanSheet/printScanSheet'
    . '/refs[]/' . $scanSheetRef
    . '/type/pdf'
    . '/apiKey/' . $sender['api'];

$ch = curl_init($printUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$isPdf = (strpos($body, '%PDF') === 0);
if ($httpCode !== 200 || !$isPdf) {
    echo json_encode(array('ok' => false, 'error' => 'НП не повернула PDF — реєстр не закрито (HTTP ' . $httpCode . ')'));
    exit;
}

// Update local status
\Papir\Crm\ScanSheetRepository::save(array(
    'Ref'     => $scanSheetRef,
    'status'  => 'closed',
    'printed' => 1,
));

echo json_encode(array('ok' => true));