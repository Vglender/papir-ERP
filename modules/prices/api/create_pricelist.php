<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$supplierId = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id']       : 0;
$name       = isset($_POST['name'])        ? trim($_POST['name'])              : '';
$sourceType = isset($_POST['source_type']) ? trim($_POST['source_type'])       : 'google_sheets';

$allowed = array('moy_sklad', 'google_sheets', 'excel', 'xml', 'parser', 'api');

if ($supplierId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'supplier_id required'));
    exit;
}
if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Название не может быть пустым'));
    exit;
}
if (!in_array($sourceType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Неверный тип источника'));
    exit;
}

$supplierRepo = new SupplierRepository();
if (!$supplierRepo->getById($supplierId)) {
    echo json_encode(array('ok' => false, 'error' => 'Поставщик не найден'));
    exit;
}

$pricelistRepo = new PricelistRepository();
$result = $pricelistRepo->create($supplierId, array(
    'name'          => $name,
    'source_type'   => $sourceType,
    'source_config' => null,
    'is_active'     => 1,
));

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'DB error'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => (int)$result['id']));
