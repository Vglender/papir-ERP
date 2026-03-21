<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../prices_bootstrap.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../../src/lib_stock_update.php';

$itemId    = (int)Request::postInt('item_id', 0);
$stock     = Request::postString('stock', '');
$priceCost = Request::postString('price_cost', '');
$priceRrp  = Request::postString('price_rrp', '');
$rawName   = Request::postString('raw_name', '');

if ($itemId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'item_id required'));
    exit;
}

// Fetch item to know product_id and pricelist
$itemRow = Database::fetchRow('Papir',
    "SELECT psi.product_id, psi.pricelist_id, pl.supplier_id, ps.is_cost_source
     FROM price_supplier_items psi
     JOIN price_supplier_pricelists pl ON pl.id = psi.pricelist_id
     JOIN price_suppliers ps ON ps.id = pl.supplier_id
     WHERE psi.id = " . $itemId . " LIMIT 1"
);
$productId = ($itemRow['ok'] && !empty($itemRow['row'])) ? (int)$itemRow['row']['product_id'] : 0;

$data = array();
$priceChanged = false;
$stockChanged = false;

if ($stock !== '') {
    $data['stock'] = $stock === 'null' ? null : (int)$stock;
    $stockChanged = true;
}
if ($priceCost !== '') {
    $data['price_cost'] = $priceCost === 'null' ? null : (float)$priceCost;
    $priceChanged = true;
}
if ($priceRrp !== '') {
    $data['price_rrp'] = $priceRrp === 'null' ? null : (float)$priceRrp;
    $priceChanged = true;
}
if ($rawName !== '') {
    $data['raw_name'] = $rawName;
}

if (empty($data)) {
    echo json_encode(array('ok' => false, 'error' => 'nothing to update'));
    exit;
}

$r = Database::update('Papir', 'price_supplier_items', $data, array('id' => $itemId));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'db error'));
    exit;
}

$response = array('ok' => true, 'recalculated' => false, 'action_recalc' => false, 'quantity_updated' => false);

// ── Pre-cascade: sync product_papir.price_rrp from all supplier items for this product
// After saving the item, find the best RRP across ALL active matched supplier items.
// If none has RRP — clear it in product_papir so PriceEngine doesn't apply a stale value.
if ($priceRrp !== '' && $productId > 0) {
    $settingsRow = Database::fetchRow('Papir',
        "SELECT manual_rrp_enabled FROM product_price_settings WHERE product_id = " . $productId . " LIMIT 1"
    );
    $manualRrpEnabled = ($settingsRow['ok'] && !empty($settingsRow['row']))
        ? (int)$settingsRow['row']['manual_rrp_enabled']
        : 0;
    if (!$manualRrpEnabled) {
        // Query effective RRP from all active supplier items (post-save state)
        $rrpRow = Database::fetchRow('Papir',
            "SELECT MAX(psi.price_rrp) AS best_rrp
             FROM price_supplier_items psi
             JOIN price_supplier_pricelists ppl ON ppl.id = psi.pricelist_id
             JOIN price_suppliers ps ON ps.id = ppl.supplier_id
             WHERE psi.product_id = " . $productId . "
               AND psi.match_type != 'ignored'
               AND ps.is_active = 1
               AND ppl.is_active = 1
               AND psi.price_rrp IS NOT NULL AND psi.price_rrp > 0"
        );
        $effectiveRrp = ($rrpRow['ok'] && !empty($rrpRow['row']) && $rrpRow['row']['best_rrp'] !== null)
            ? (float)$rrpRow['row']['best_rrp']
            : null;
        Database::update('Papir', 'product_papir',
            array('price_rrp' => $effectiveRrp),
            array('product_id' => $productId)
        );
    }
}

// ── Cascade 1: recalculate prices when price_cost/rrp changed for any matched product
if ($priceChanged && $productId > 0) {
    $itemRepo = new PricelistItemRepository();
    $engine   = PriceEngine::create($itemRepo);
    $builder  = new DiscountProfileBuilder(
        $engine,
        new ProductPriceRepository(),
        new DiscountStrategyRepository(),
        new QuantityStrategyRepository(),
        new ProductPackageRepository(),
        new ProductDiscountProfileRepository(),
        new GlobalSettingsRepository()
    );
    $calcResult = $builder->build($productId);
    $response['recalculated'] = !empty($calcResult['ok']);

    if (!empty($calcResult['ok'])) {
        $response['price_purchase']  = isset($calcResult['result']['price_purchase'])  ? $calcResult['result']['price_purchase']  : null;
        $response['price_sale']      = isset($calcResult['result']['price_sale'])      ? $calcResult['result']['price_sale']      : null;
        $response['price_wholesale'] = isset($calcResult['result']['price_wholesale']) ? $calcResult['result']['price_wholesale'] : null;
        $response['price_dealer']    = isset($calcResult['result']['price_dealer'])    ? $calcResult['result']['price_dealer']    : null;

        $response['action_recalc'] = !empty($calcResult['action']['action_recalc']);
        if (!empty($calcResult['action']['price_act'])) {
            $response['price_act'] = $calcResult['action']['price_act'];
        }

        // ── Cascade 2: push updated prices to OpenCart sites and MoySklad
        $pushRow = Database::fetchRow('Papir',
            "SELECT p.product_id, p.product_article, p.id_off, p.id_mf, p.id_ms,
                    p.price_purchase, p.price_sale, p.price_wholesale, p.price_dealer, p.quantity,
                    p.link_off, p.links_mf, p.links_prom,
                    dp.qty_1, dp.price_1, dp.qty_2, dp.price_2, dp.qty_3, dp.price_3
             FROM product_papir p
             LEFT JOIN product_discount_profile dp ON dp.product_id = p.product_id
             WHERE p.product_id = " . (int)$productId . " LIMIT 1"
        );
        if ($pushRow['ok'] && !empty($pushRow['row'])) {
            $pushRows = array($pushRow['row']);
            $ocExporter = new OpenCartPriceExport();
            $ocExporter->pushBatch('off', $pushRows, 'id_off');
            $ocExporter->pushBatch('mff', $pushRows, 'id_mf');
            $msExporter = new MoySkladPriceExport(new MoySkladApi());
            $msExporter->pushBatch($pushRows);
            $response['pushed'] = true;
        }
    }
}

// ── Cascade 3: recalculate quantity if stock changed for matched product
if ($stockChanged && $productId > 0) {
    $qSql = "UPDATE product_papir pp
             SET pp.quantity = (
                 COALESCE((SELECT ps.stock FROM product_stock ps WHERE ps.model REGEXP '^[0-9]+\$' AND CAST(ps.model AS UNSIGNED) = pp.id_off LIMIT 1), 0) +
                 COALESCE((SELECT SUM(psi.stock) FROM price_supplier_items psi WHERE psi.product_id = pp.product_id AND psi.stock IS NOT NULL AND psi.stock != '' AND psi.match_type != 'ignored'), 0)
             )
             WHERE pp.product_id = " . $productId;
    $qr = Database::query('Papir', $qSql);
    $response['quantity_updated'] = $qr['ok'];
    if ($qr['ok']) {
        $qRow = Database::fetchRow('Papir', "SELECT quantity FROM product_papir WHERE product_id = " . $productId . " LIMIT 1");
        $response['new_quantity'] = ($qRow['ok'] && !empty($qRow['row'])) ? (float)$qRow['row']['quantity'] : null;
    }
}

echo json_encode($response);
