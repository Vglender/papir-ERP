<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../prices_bootstrap.php';

$pricelistId = (int)Request::postInt('pricelist_id', 0);
if ($pricelistId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id required'));
    exit;
}

$rawSku   = Request::postString('raw_sku',   '');
$rawName  = Request::postString('raw_name',  '');
$priceCost = Request::postString('price_cost', '');
$priceRrp  = Request::postString('price_rrp',  '');
$stockVal  = Request::postString('stock',      '');

$data = array(
    'pricelist_id' => $pricelistId,
    'raw_sku'      => $rawSku !== '' ? $rawSku : null,
    'raw_model'    => null,
    'raw_name'     => $rawName !== '' ? $rawName : null,
    'price_cost'   => $priceCost !== '' ? (float)$priceCost : null,
    'price_rrp'    => $priceRrp  !== '' ? (float)$priceRrp  : null,
    'currency'     => 'UAH',
    'stock'        => $stockVal !== '' ? (int)$stockVal : null,
    'synced_at'    => date('Y-m-d H:i:s'),
);

$r = Database::insert('Papir', 'price_supplier_items', $data);
echo json_encode(array('ok' => (bool)$r['ok'], 'id' => isset($r['id']) ? (int)$r['id'] : 0));
