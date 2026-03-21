<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$supId   = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
$cascade = isset($_POST['cascade'])     ? (int)$_POST['cascade']     : 0;

if ($supId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'supplier_id required'));
    exit;
}

$supplierRepo = new SupplierRepository();
if (!$supplierRepo->getById($supId)) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

$count = $supplierRepo->getPricelistCount($supId);
if ($count > 0 && !$cascade) {
    echo json_encode(array('ok' => false, 'error' => 'has_pricelists', 'count' => $count));
    exit;
}

// Каскадное удаление
if ($count > 0) {
    $listResult = Database::fetchAll('Papir',
        "SELECT id FROM `price_supplier_pricelists` WHERE `supplier_id` = $supId"
    );
    if ($listResult['ok'] && !empty($listResult['rows'])) {
        foreach ($listResult['rows'] as $pl) {
            $plId = (int)$pl['id'];
            Database::query('Papir', "DELETE FROM `price_supplier_items` WHERE `pricelist_id` = $plId");
        }
    }
    Database::query('Papir', "DELETE FROM `price_supplier_pricelists` WHERE `supplier_id` = $supId");
}

$supplierRepo->delete($supId);

// Обновляем статусы товаров
$itemRepo = new PricelistItemRepository();
$itemRepo->syncProductStatuses();

echo json_encode(array('ok' => true));
