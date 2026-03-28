<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId   = isset($_POST['product_id'])   ? (int)$_POST['product_id']   : 0;
$attributeId = isset($_POST['attribute_id']) ? (int)$_POST['attribute_id'] : 0;

if ($productId <= 0 || $attributeId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні параметри'));
    exit;
}

// Delete all language variants from Papir
Database::query('Papir',
    "DELETE FROM product_attribute_value
     WHERE product_id = {$productId} AND attribute_id = {$attributeId} AND site_id = 0");

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

        $asm = Database::fetchRow('Papir',
            "SELECT site_attribute_id FROM attribute_site_mapping WHERE attribute_id = {$attributeId} AND site_id = {$siteId} LIMIT 1");
        if (!$asm['ok'] || empty($asm['row'])) continue;
        $ocAttrId = (int)$asm['row']['site_attribute_id'];

        Database::query($dbAlias,
            "DELETE FROM oc_product_attribute
             WHERE product_id = {$ocProdId} AND attribute_id = {$ocAttrId}");
    }
}

echo json_encode(array('ok' => true));
