<?php

require_once __DIR__ . '/../prices_bootstrap.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';

header('Content-Type: application/json; charset=utf-8');

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (!$productId) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

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

if (!$calcResult['ok']) {
    echo json_encode($calcResult);
    exit;
}

// ── Cascade: push recalculated prices + discounts to sites and MoySklad ──

$pushRow = Database::fetchRow('Papir',
    "SELECT p.product_id, p.product_article, p.id_off, p.id_mf, p.id_ms,
            p.price_purchase, p.price_sale, p.price_wholesale, p.price_dealer, p.quantity,
            IF(ps_off.seo_url != '' AND ps_off.seo_url IS NOT NULL, CONCAT('https://officetorg.com.ua/', ps_off.seo_url), '') AS link_off,
            IF(ps_mff.seo_url != '' AND ps_mff.seo_url IS NOT NULL, CONCAT('https://menufolder.com.ua/', ps_mff.seo_url), '') AS links_mf,
            p.links_prom,
            dp.qty_1, dp.price_1, dp.qty_2, dp.price_2, dp.qty_3, dp.price_3
     FROM product_papir p
     LEFT JOIN product_discount_profile dp ON dp.product_id = p.product_id
     LEFT JOIN product_seo ps_off ON ps_off.product_id = p.product_id AND ps_off.site_id = 1 AND ps_off.language_id = 1
     LEFT JOIN product_seo ps_mff ON ps_mff.product_id = p.product_id AND ps_mff.site_id = 2 AND ps_mff.language_id = 1
     WHERE p.product_id = " . $productId . " LIMIT 1"
);

$calcResult['pushed'] = false;
$calcResult['push_error'] = null;

if ($pushRow['ok'] && !empty($pushRow['row'])) {
    $pushRows = array($pushRow['row']);

    $ocExporter = new OpenCartPriceExport();
    $ocExporter->pushBatch('off', $pushRows, 'id_off');
    $ocExporter->pushBatch('mff', $pushRows, 'id_mf');

    $msExporter = new MoySkladPriceExport(new MoySkladApi());
    $msExporter->pushBatch($pushRows);

    $calcResult['pushed'] = true;
} else {
    $calcResult['push_error'] = 'product row not found after recalculate';
}

echo json_encode($calcResult);
