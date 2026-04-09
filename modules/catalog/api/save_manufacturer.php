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

require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';

$manufacturerName = null;

if ($manufacturerId > 0) {
    $mfr = Database::fetchRow('Papir',
        "SELECT manufacturer_id, name FROM manufacturers WHERE manufacturer_id = {$manufacturerId}"
    );
    if (!$mfr['ok'] || empty($mfr['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Manufacturer not found'));
        exit;
    }
    $manufacturerName = $mfr['row']['name'];
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

// ── Cascade → all active sites ────────────────────────────────────────────
$sync = new SiteSyncService();
$productSites = $sync->getProductSites($productId);

// Get site-specific manufacturer IDs from manufacturer_site_mapping or manufacturers table
$siteManufIds = array();
if ($manufacturerId > 0) {
    $mfr2 = Database::fetchRow('Papir',
        "SELECT off_id, mff_id FROM manufacturers WHERE manufacturer_id = {$manufacturerId}");
    if ($mfr2['ok'] && !empty($mfr2['row'])) {
        $siteManufIds[1] = (int)$mfr2['row']['off_id'];  // site_id=1 (off)
        $siteManufIds[2] = (int)$mfr2['row']['mff_id'];  // site_id=2 (mff)
    }
}

foreach ($productSites as $ps) {
    $siteId        = (int)$ps['site_id'];
    $siteProductId = (int)$ps['site_product_id'];
    $siteMfId      = isset($siteManufIds[$siteId]) ? $siteManufIds[$siteId] : 0;
    $sync->productUpdate($siteId, $siteProductId, array('manufacturer_id' => $siteMfId));
}

echo json_encode(array(
    'ok'                => true,
    'manufacturer_id'   => $manufacturerId > 0 ? $manufacturerId : null,
    'manufacturer_name' => $manufacturerName,
));
