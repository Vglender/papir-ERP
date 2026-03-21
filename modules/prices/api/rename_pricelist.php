<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../prices_bootstrap.php';

$plId = Request::postInt('pricelist_id', 0);
$name = trim(Request::postString('name', ''));

if ($plId <= 0 || $name === '') {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id and name required'));
    exit;
}

$r = Database::update('Papir', 'price_supplier_pricelists',
    array('name' => $name),
    array('id' => $plId)
);

echo json_encode(array('ok' => (bool)$r['ok']));
