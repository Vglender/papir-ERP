<?php
/**
 * GET /customerorder/api/get_product_prices?product_id=X
 * Returns all price variants for a product: sale, action, wholesale, dealer, qty discounts.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../customerorder_bootstrap.php';

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// Base prices from product_papir
$rBase = Database::fetchRow('Papir',
    "SELECT price_sale, price_wholesale, price_dealer
     FROM product_papir
     WHERE product_id = {$productId} LIMIT 1");

if (!$rBase['ok'] || empty($rBase['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Product not found'));
    exit;
}
$base = $rBase['row'];

// Qty discounts from product_discount_profile
$rDisc = Database::fetchRow('Papir',
    "SELECT qty_1, discount_percent_1, price_1,
            qty_2, discount_percent_2, price_2,
            qty_3, discount_percent_3, price_3
     FROM product_discount_profile
     WHERE product_id = {$productId} LIMIT 1");
$disc = ($rDisc['ok'] && !empty($rDisc['row'])) ? $rDisc['row'] : array();

// Action price from action_prices
$rAct = Database::fetchRow('Papir',
    "SELECT price_act FROM action_prices WHERE product_id = {$productId} LIMIT 1");
$priceActRow = ($rAct['ok'] && !empty($rAct['row'])) ? $rAct['row'] : array();

// Build qty tiers (only non-null with qty>0)
$qtyTiers = array();
foreach (array(1, 2, 3) as $n) {
    $qty   = isset($disc["qty_{$n}"])              ? (int)$disc["qty_{$n}"]              : 0;
    $price = isset($disc["price_{$n}"])            ? (float)$disc["price_{$n}"]          : 0;
    $pct   = isset($disc["discount_percent_{$n}"]) ? (float)$disc["discount_percent_{$n}"] : 0;
    if ($qty > 0 && $price > 0) {
        $qtyTiers[] = array('qty' => $qty, 'price' => $price, 'discount_percent' => $pct);
    }
}

$priceAct = (!empty($priceActRow['price_act'])) ? (float)$priceActRow['price_act'] : null;

echo json_encode(array(
    'ok'            => true,
    'price_sale'    => $base['price_sale']      !== null ? (float)$base['price_sale']      : null,
    'price_wholesale' => $base['price_wholesale'] !== null ? (float)$base['price_wholesale'] : null,
    'price_dealer'  => $base['price_dealer']    !== null ? (float)$base['price_dealer']    : null,
    'price_act'     => $priceAct,
    'qty_tiers'     => $qtyTiers,
));