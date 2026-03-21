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
if (!$pricelistRepo->getById($plId)) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

// Удаляем строки прайса
Database::query('Papir', "DELETE FROM `price_supplier_items` WHERE `pricelist_id` = $plId");

// Удаляем сам прайс
$pricelistRepo->delete($plId);

// Обновляем статусы товаров
$itemRepo = new PricelistItemRepository();
$itemRepo->syncProductStatuses();

echo json_encode(array('ok' => true));
