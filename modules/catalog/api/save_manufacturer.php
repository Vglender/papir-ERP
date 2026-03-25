<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId      = isset($_POST['product_id'])      ? (int)$_POST['product_id']      : 0;
$manufacturerId = isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : 0;

if (!$productId) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// Get product's id_off, id_mf
$product = Database::fetchRow('Papir',
    "SELECT product_id, id_off, id_mf FROM product_papir WHERE product_id = {$productId}"
);
if (!$product['ok'] || empty($product['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Product not found'));
    exit;
}

$idOff = (int)$product['row']['id_off'];
$idMf  = (int)$product['row']['id_mf'];

$manufacturerName = null;
$offId            = 0;
$mffId            = 0;

if ($manufacturerId > 0) {
    $mfr = Database::fetchRow('Papir',
        "SELECT manufacturer_id, name, off_id, mff_id FROM manufacturers WHERE manufacturer_id = {$manufacturerId}"
    );
    if (!$mfr['ok'] || empty($mfr['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Manufacturer not found'));
        exit;
    }
    $manufacturerName = $mfr['row']['name'];
    $offId            = (int)$mfr['row']['off_id'];
    $mffId            = (int)$mfr['row']['mff_id'];
}

// ── Update Papir.product_papir ────────────────────────────────────────────
$r = Database::update('Papir', 'product_papir',
    array('manufacturer_id' => $manufacturerId > 0 ? $manufacturerId : 0),
    array('product_id' => $productId)
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to save'));
    exit;
}

// ── Update off.oc_product ─────────────────────────────────────────────────
if ($idOff > 0) {
    Database::update('off', 'oc_product',
        array('manufacturer_id' => $offId > 0 ? $offId : 0),
        array('product_id' => $idOff)
    );
}

// ── Update mff.oc_product ─────────────────────────────────────────────────
if ($idMf > 0 && $mffId > 0) {
    Database::update('mff', 'oc_product',
        array('manufacturer_id' => $mffId),
        array('product_id' => $idMf)
    );
}

echo json_encode(array(
    'ok'                => true,
    'manufacturer_id'   => $manufacturerId > 0 ? $manufacturerId : null,
    'manufacturer_name' => $manufacturerName,
));
