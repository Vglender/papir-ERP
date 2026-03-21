<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../prices_bootstrap.php';

$pricelistId = (int)Request::postInt('pricelist_id', 0);
if ($pricelistId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id required'));
    exit;
}

$row = Database::fetchRow('Papir',
    "SELECT allow_manual_edit FROM price_supplier_pricelists WHERE id = " . $pricelistId . " LIMIT 1"
);
if (!$row['ok'] || empty($row['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'not found'));
    exit;
}

$current = (int)$row['row']['allow_manual_edit'];
$newVal  = $current ? 0 : 1;

$r = Database::update('Papir', 'price_supplier_pricelists',
    array('allow_manual_edit' => $newVal),
    array('id' => $pricelistId)
);

echo json_encode(array('ok' => (bool)$r['ok'], 'allow_manual_edit' => $newVal));
