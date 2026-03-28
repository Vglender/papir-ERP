<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId     = isset($_POST['product_id'])      ? (int)$_POST['product_id']       : 0;
$weight        = isset($_POST['weight'])          ? (float)$_POST['weight']         : 0.0;
$weightClassId = isset($_POST['weight_class_id']) ? (int)$_POST['weight_class_id']  : 0;
$length        = isset($_POST['length'])          ? (float)$_POST['length']         : 0.0;
$width         = isset($_POST['width'])           ? (float)$_POST['width']          : 0.0;
$height        = isset($_POST['height'])          ? (float)$_POST['height']         : 0.0;
$lengthClassId = isset($_POST['length_class_id']) ? (int)$_POST['length_class_id']  : 0;

if ($productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// Validate class IDs
$wc = Database::fetchRow('Papir', "SELECT weight_class_id FROM weight_class WHERE weight_class_id = {$weightClassId} LIMIT 1");
if (!$wc['ok'] || empty($wc['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний weight_class_id'));
    exit;
}
$lc = Database::fetchRow('Papir', "SELECT length_class_id FROM length_class WHERE length_class_id = {$lengthClassId} LIMIT 1");
if (!$lc['ok'] || empty($lc['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний length_class_id'));
    exit;
}

// Save to Papir
$upd = Database::update('Papir', 'product_papir', array(
    'weight'          => $weight,
    'weight_class_id' => $weightClassId,
    'length'          => $length,
    'width'           => $width,
    'height'          => $height,
    'length_class_id' => $lengthClassId,
), array('product_id' => $productId));

if (!$upd['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
    exit;
}

// Cascade to off + mff
$sitesR = Database::fetchAll('Papir', "SELECT site_id, db_alias FROM sites WHERE status = 1");
if ($sitesR['ok']) {
    foreach ($sitesR['rows'] as $site) {
        $siteId  = (int)$site['site_id'];
        $dbAlias = $site['db_alias'];

        $ps = Database::fetchRow('Papir',
            "SELECT site_product_id FROM product_site WHERE product_id = {$productId} AND site_id = {$siteId} LIMIT 1");
        if (!$ps['ok'] || empty($ps['row'])) continue;
        $ocProdId = (int)$ps['row']['site_product_id'];
        if ($ocProdId <= 0) continue;

        $wcMap = Database::fetchRow('Papir',
            "SELECT site_weight_class_id FROM weight_class_site_mapping WHERE weight_class_id = {$weightClassId} AND site_id = {$siteId} LIMIT 1");
        $ocWc = ($wcMap['ok'] && !empty($wcMap['row'])) ? (int)$wcMap['row']['site_weight_class_id'] : $weightClassId;

        $lcMap = Database::fetchRow('Papir',
            "SELECT site_length_class_id FROM length_class_site_mapping WHERE length_class_id = {$lengthClassId} AND site_id = {$siteId} LIMIT 1");
        $ocLc = ($lcMap['ok'] && !empty($lcMap['row'])) ? (int)$lcMap['row']['site_length_class_id'] : $lengthClassId;

        Database::query($dbAlias,
            "UPDATE oc_product SET
                weight          = '{$weight}',
                weight_class_id = {$ocWc},
                length          = '{$length}',
                width           = '{$width}',
                height          = '{$height}',
                length_class_id = {$ocLc}
             WHERE product_id = {$ocProdId}");
    }
}

echo json_encode(array('ok' => true));
