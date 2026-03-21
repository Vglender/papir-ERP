<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (!$productId) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// ── Markups & strategy → product_papir ────────────────────────────────────

$ppData = array();

$saleMarkup = isset($_POST['sale_markup_percent']) ? trim($_POST['sale_markup_percent']) : '';
if ($saleMarkup !== '') {
    $ppData['sale_markup_percent'] = is_numeric($saleMarkup) ? (float)$saleMarkup : null;
} else {
    $ppData['sale_markup_percent'] = null;
}

$wholesaleMarkup = isset($_POST['wholesale_markup_percent']) ? trim($_POST['wholesale_markup_percent']) : '';
if ($wholesaleMarkup !== '') {
    $ppData['wholesale_markup_percent'] = is_numeric($wholesaleMarkup) ? (float)$wholesaleMarkup : null;
} else {
    $ppData['wholesale_markup_percent'] = null;
}

$dealerMarkup = isset($_POST['dealer_markup_percent']) ? trim($_POST['dealer_markup_percent']) : '';
if ($dealerMarkup !== '') {
    $ppData['dealer_markup_percent'] = is_numeric($dealerMarkup) ? (float)$dealerMarkup : null;
} else {
    $ppData['dealer_markup_percent'] = null;
}

$discountStrategyId = isset($_POST['discount_strategy_id']) ? trim($_POST['discount_strategy_id']) : '';
$ppData['discount_strategy_id'] = ($discountStrategyId !== '' && is_numeric($discountStrategyId)) ? (int)$discountStrategyId : null;

$discountStrategyManual = isset($_POST['discount_strategy_manual']) ? (int)$_POST['discount_strategy_manual'] : 0;
$ppData['discount_strategy_manual'] = $discountStrategyManual ? 1 : 0;

$isLocked = isset($_POST['is_locked']) ? (int)$_POST['is_locked'] : 0;

// ── Manual overrides → product_price_settings ─────────────────────────────

$ppsData = array();

// manual_cost
$manualCostEnabled = isset($_POST['manual_cost_enabled']) ? (int)$_POST['manual_cost_enabled'] : 0;
$ppsData['manual_cost_enabled'] = $manualCostEnabled ? 1 : 0;
$ppsData['manual_cost']         = $manualCostEnabled ? (float)(isset($_POST['manual_cost']) ? $_POST['manual_cost'] : 0) : null;

// manual_price
$manualPriceEnabled = isset($_POST['manual_price_enabled']) ? (int)$_POST['manual_price_enabled'] : 0;
$ppsData['manual_price_enabled'] = $manualPriceEnabled ? 1 : 0;
$ppsData['manual_price']         = $manualPriceEnabled ? (float)(isset($_POST['manual_price']) ? $_POST['manual_price'] : 0) : null;

// manual_wholesale
$manualWholesaleEnabled = isset($_POST['manual_wholesale_enabled']) ? (int)$_POST['manual_wholesale_enabled'] : 0;
$ppsData['manual_wholesale_enabled'] = $manualWholesaleEnabled ? 1 : 0;
$ppsData['manual_wholesale_price']   = $manualWholesaleEnabled ? (float)(isset($_POST['manual_wholesale_price']) ? $_POST['manual_wholesale_price'] : 0) : null;

// manual_dealer
$manualDealerEnabled = isset($_POST['manual_dealer_enabled']) ? (int)$_POST['manual_dealer_enabled'] : 0;
$ppsData['manual_dealer_enabled'] = $manualDealerEnabled ? 1 : 0;
$ppsData['manual_dealer_price']   = $manualDealerEnabled ? (float)(isset($_POST['manual_dealer_price']) ? $_POST['manual_dealer_price'] : 0) : null;

// manual_rrp
$manualRrpEnabled = isset($_POST['manual_rrp_enabled']) ? (int)$_POST['manual_rrp_enabled'] : 0;
$ppsData['manual_rrp_enabled'] = $manualRrpEnabled ? 1 : 0;
$ppsData['manual_rrp']         = $manualRrpEnabled ? (float)(isset($_POST['manual_rrp']) ? $_POST['manual_rrp'] : 0) : null;

// custom discount percents
$customSmall  = isset($_POST['custom_small_discount_percent'])  ? trim($_POST['custom_small_discount_percent'])  : '';
$customMedium = isset($_POST['custom_medium_discount_percent']) ? trim($_POST['custom_medium_discount_percent']) : '';
$customLarge  = isset($_POST['custom_large_discount_percent'])  ? trim($_POST['custom_large_discount_percent'])  : '';

$ppsData['custom_small_discount_percent']  = ($customSmall  !== '' && is_numeric($customSmall))  ? (float)$customSmall  : null;
$ppsData['custom_medium_discount_percent'] = ($customMedium !== '' && is_numeric($customMedium)) ? (float)$customMedium : null;
$ppsData['custom_large_discount_percent']  = ($customLarge  !== '' && is_numeric($customLarge))  ? (float)$customLarge  : null;

// is_locked goes into product_price_settings
$ppsData['is_locked'] = $isLocked ? 1 : 0;

// ── Save ──────────────────────────────────────────────────────────────────

$productRepo = new ProductPriceRepository();

$r1 = $productRepo->savePrices($productId, $ppData);
if (!$r1['ok']) {
    $errMsg = isset($r1['error']) ? $r1['error'] : 'Failed to update product_papir';
    echo json_encode(array('ok' => false, 'error' => $errMsg));
    exit;
}

$r2 = $productRepo->saveSettings($productId, $ppsData);
if (!$r2['ok']) {
    $errMsg = isset($r2['error']) ? $r2['error'] : 'Failed to update product_price_settings';
    echo json_encode(array('ok' => false, 'error' => $errMsg));
    exit;
}

echo json_encode(array('ok' => true));
