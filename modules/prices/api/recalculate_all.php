<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(60);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit  = isset($_POST['limit'])  ? (int)$_POST['limit']  : 100;
$limit  = max(10, min(200, $limit));

$itemRepo    = new PricelistItemRepository();

// При первом батче — синхронизируем статусы товаров по активным прайсам
if ($offset === 0) {
    $itemRepo->syncProductStatuses();
}

$productRepo = new ProductPriceRepository();
$total       = $productRepo->countList(array());

$engine  = PriceEngine::create($itemRepo);
$builder  = new DiscountProfileBuilder(
    $engine,
    $productRepo,
    new DiscountStrategyRepository(),
    new QuantityStrategyRepository(),
    new ProductPackageRepository(),
    new ProductDiscountProfileRepository(),
    new GlobalSettingsRepository()
);

$page = $productRepo->getList(array(), 'product_id', 'asc', $offset, $limit);

if (!$page['ok'] || empty($page['rows'])) {
    echo json_encode(array(
        'ok'          => true,
        'processed'   => 0,
        'errors'      => 0,
        'total'       => $total,
        'next_offset' => null,
    ));
    exit;
}

$processed = 0;
$errors    = 0;

foreach ($page['rows'] as $row) {
    $result = $builder->build((int)$row['product_id']);
    if ($result['ok']) {
        $processed++;
    } else {
        $errors++;
    }
}

$nextOffset = $offset + count($page['rows']);
if ($nextOffset >= $total) {
    $nextOffset = null;
}

echo json_encode(array(
    'ok'          => true,
    'processed'   => $processed,
    'errors'      => $errors,
    'total'       => $total,
    'next_offset' => $nextOffset,
));
