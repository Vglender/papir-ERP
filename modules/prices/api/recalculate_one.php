<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (!$productId) {
    echo json_encode(['ok' => false, 'error' => 'product_id required']);
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

echo json_encode($builder->build($productId));
