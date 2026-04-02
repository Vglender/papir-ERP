<?php
/**
 * POST /novaposhta/api/bulk_print_ttns
 * ids    — через кому ttn_id з нашої БД
 * format — 100x100 | a4_6
 *
 * Повертає JSON { ok, urls: ['https://my.novaposhta.ua/...'] }
 * Групує по sender_api → один URL на групу (зазвичай один відправник).
 *
 * URL-формати (номери ЕН через кому):
 *   100×100: /printMarking100x100/orders/{num1,num2,...}/type/pdf/apiKey/{key}/limit/106/page/1
 *   85×85:   /printMarking85x85/orders/{num1,num2,...}/type/pdf8/apiKey/{key}/limit/106/page/1
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ids    = isset($_POST['ids'])    ? trim($_POST['ids'])    : '';
$format = isset($_POST['format']) ? trim($_POST['format']) : '100x100';

if (!$ids) {
    echo json_encode(array('ok' => false, 'error' => 'ids required'));
    exit;
}

$idList = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idList)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid ids'));
    exit;
}

// Fetch TTNs with sender api key
$inSql = implode(',', $idList);
$r = \Database::fetchAll('Papir',
    "SELECT t.id, t.int_doc_number, t.sender_ref, s.api AS sender_api
     FROM ttn_novaposhta t
     LEFT JOIN np_sender s ON s.Ref = t.sender_ref
     WHERE t.id IN ({$inSql}) AND t.deletion_mark = 0");

if (!$r['ok'] || empty($r['rows'])) {
    echo json_encode(array('ok' => false, 'error' => 'ТТН не знайдено'));
    exit;
}

// Group by sender_api, skip TTNs without int_doc_number
$groups = array(); // sender_api => [numbers]
$skipped = array();
foreach ($r['rows'] as $ttn) {
    if (!$ttn['int_doc_number']) {
        $skipped[] = $ttn['id'];
        continue;
    }
    $key = $ttn['sender_api'];
    if (!isset($groups[$key])) $groups[$key] = array();
    $groups[$key][] = $ttn['int_doc_number'];
}

if (empty($groups)) {
    echo json_encode(array('ok' => false, 'error' => 'Жодна ТТН не має номера ЕН'));
    exit;
}

// Build NP URLs
$base = 'https://my.novaposhta.ua/orders/';
if ($format === 'a4_6') {
    $method  = 'printMarking85x85';
    $typePart = '/type/pdf8';
} else {
    $method  = 'printMarking100x100';
    $typePart = '/type/pdf';
}

$urls = array();
foreach ($groups as $apiKey => $numbers) {
    $numStr = implode(',', $numbers);
    $urls[] = $base . $method
            . '/orders/' . $numStr
            . $typePart
            . '/apiKey/' . $apiKey
            . '/limit/106/page/1';
}

// Позначаємо всі як розпечатані
if (!empty($idList)) {
    \Database::query('Papir',
        "UPDATE ttn_novaposhta SET is_printed = 1 WHERE id IN ({$inSql})");
}

echo json_encode(array(
    'ok'      => true,
    'urls'    => $urls,
    'skipped' => $skipped,
));