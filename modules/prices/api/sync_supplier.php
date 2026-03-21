<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(600);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$pricelistId = isset($_POST['pricelist_id']) ? (int)$_POST['pricelist_id'] : 0;
if ($pricelistId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id required'));
    exit;
}

$pricelistRepo = new PricelistRepository();
$itemRepo      = new PricelistItemRepository();

$pricelist = $pricelistRepo->getById($pricelistId);
if (!$pricelist) {
    echo json_encode(array('ok' => false, 'error' => 'Pricelist not found'));
    exit;
}

switch ($pricelist['source_type']) {
    case 'moy_sklad':
        $syncer = new MoySkladPriceSync(new SupplierRepository(), $pricelistRepo, $itemRepo);
        $result = $syncer->sync($pricelistId);
        break;

    case 'google_sheets':
        $syncer = new GoogleSheetsPriceSync($pricelistRepo, $itemRepo);
        $result = $syncer->sync($pricelistId);
        break;

    default:
        $result = array('ok' => false, 'error' => 'Sync not implemented for: ' . $pricelist['source_type'], 'imported' => 0, 'matched' => 0);
}

if ($result['ok']) {
    // Обновляем статусы товаров (active/inactive) на основе всех активных прайсов
    $itemRepo->syncProductStatuses();

    // Пересчитываем цены для всех сопоставленных товаров этого прайса
    $matchedIds = $itemRepo->getMatchedProductIds($pricelistId);
    $recalculated = 0;
    if (!empty($matchedIds)) {
        $productRepo = new ProductPriceRepository();
        $engine      = PriceEngine::create($itemRepo);
        $builder     = new DiscountProfileBuilder(
            $engine,
            $productRepo,
            new DiscountStrategyRepository(),
            new QuantityStrategyRepository(),
            new ProductPackageRepository(),
            new ProductDiscountProfileRepository(),
            new GlobalSettingsRepository()
        );
        foreach ($matchedIds as $pid) {
            $r = $builder->build($pid);
            if ($r['ok']) $recalculated++;
        }
    }

    $updated = $pricelistRepo->getById($pricelistId);
    $result['items_total']   = $updated ? (int)$updated['items_total']   : 0;
    $result['items_matched'] = $updated ? (int)$updated['items_matched'] : 0;
    $result['recalculated']  = $recalculated;
}

echo json_encode($result);
