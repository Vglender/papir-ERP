<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$plId = isset($_POST['pricelist_id']) ? (int)$_POST['pricelist_id'] : 0;
if ($plId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id required'));
    exit;
}

$pricelistRepo = new PricelistRepository();
$pricelist = $pricelistRepo->getById($plId);
if (!$pricelist) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

$newActive = $pricelist['is_active'] ? 0 : 1;
Database::update('Papir', 'price_supplier_pricelists', array('is_active' => $newActive), array('id' => $plId));

// При деактивации/активации обновляем статусы товаров
$itemRepo = new PricelistItemRepository();
$itemRepo->syncProductStatuses();

echo json_encode(array('ok' => true, 'is_active' => $newActive));
