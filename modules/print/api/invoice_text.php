<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../PrintContextBuilder.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$orderId) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

$ctx = PrintContextBuilder::build('order', $orderId, 0);
if (empty($ctx)) {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення не знайдено'));
    exit;
}

$inv    = $ctx['invoice'];
$seller = $ctx['seller'];
$buyer  = $ctx['buyer'];
$lines  = isset($ctx['lines']) ? $ctx['lines'] : array();

// ── Format as plain text for Viber / SMS ──────────────────────────────────────

$parts = array();

// Header
$parts[] = '📄 РАХУНОК-ФАКТУРА №' . $inv['number'] . ' від ' . $inv['date'];
$parts[] = '';

// Seller block
$sellerLines = array();
$sellerLines[] = 'Постачальник: ' . $seller['name'];
if (!empty($seller['okpo']))    $sellerLines[] = 'ЄДРПОУ: ' . $seller['okpo'];
if (!empty($seller['iban']))    $sellerLines[] = 'IBAN: ' . $seller['iban'];
if (!empty($seller['bank_name']) || !empty($seller['mfo'])) {
    $bankStr = '';
    if (!empty($seller['bank_name'])) $bankStr .= $seller['bank_name'];
    if (!empty($seller['mfo']))       $bankStr .= ', МФО ' . $seller['mfo'];
    $sellerLines[] = 'Банк: ' . $bankStr;
}
if (!empty($seller['address'])) $sellerLines[] = 'Адреса: ' . $seller['address'];
if (!empty($seller['phone']))   $sellerLines[] = 'Тел: ' . $seller['phone'];
$parts[] = implode("\n", $sellerLines);
$parts[] = '';

// Buyer block
if (!empty($buyer['name'])) {
    $buyerLines = array();
    $buyerLines[] = 'Покупець: ' . $buyer['name'];
    if (!empty($buyer['okpo']))    $buyerLines[] = 'ЄДРПОУ: ' . $buyer['okpo'];
    if (!empty($buyer['address'])) $buyerLines[] = 'Адреса: ' . $buyer['address'];
    $parts[] = implode("\n", $buyerLines);
    $parts[] = '';
}

// Line items
if (!empty($lines)) {
    $itemLines = array();
    foreach ($lines as $it) {
        $itemLines[] = $it['num'] . '. ' . $it['description']
            . ' — ' . $it['qty'] . ' ' . $it['unit']
            . ' × ' . $it['price'] . ' = ' . $it['total'] . ' грн';
    }
    $parts[] = implode("\n", $itemLines);
    $parts[] = '';
}

// Totals
$totalLines = array();
if (!empty($ctx['vat_rate']) && (float)$ctx['vat_rate'] > 0) {
    $totalLines[] = 'Без ПДВ: ' . $ctx['total'] . ' грн';
    $totalLines[] = 'ПДВ ' . $ctx['vat_rate'] . '%: ' . $ctx['vat_amount'] . ' грн';
}
$totalLines[] = '💰 До сплати: ' . $ctx['total_with_vat'] . ' грн';
$parts[] = implode("\n", $totalLines);

// Director sign
if (!empty($seller['director_title']) && !empty($seller['director_name'])) {
    $parts[] = '';
    $parts[] = $seller['director_title'] . ': ' . $seller['director_name'];
}

$text = implode("\n", $parts);

echo json_encode(array('ok' => true, 'text' => $text));