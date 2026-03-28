<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId   = isset($_POST['product_id'])   ? (int)$_POST['product_id']   : 0;
$attributeId = isset($_POST['attribute_id']) ? (int)$_POST['attribute_id'] : 0;
$languageId  = isset($_POST['language_id'])  ? (int)$_POST['language_id']  : 0;
$text        = isset($_POST['text'])         ? (string)$_POST['text']      : '';

if ($productId <= 0 || $attributeId <= 0 || !in_array($languageId, array(1, 2))) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні параметри'));
    exit;
}

// Upsert master value in Papir
$textEsc = Database::escape('Papir', $text);
$r = Database::query('Papir',
    "INSERT INTO product_attribute_value (product_id, attribute_id, language_id, site_id, text)
     VALUES ({$productId}, {$attributeId}, {$languageId}, 0, '{$textEsc}')
     ON DUPLICATE KEY UPDATE text = '{$textEsc}'");

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
    exit;
}

// Cascade to off + mff
$sitesR = Database::fetchAll('Papir', "SELECT site_id, db_alias FROM sites WHERE status = 1");
if ($sitesR['ok']) {
    foreach ($sitesR['rows'] as $site) {
        $siteId  = (int)$site['site_id'];
        $dbAlias = $site['db_alias'];

        // OC product_id
        $ps = Database::fetchRow('Papir',
            "SELECT site_product_id FROM product_site WHERE product_id = {$productId} AND site_id = {$siteId} LIMIT 1");
        if (!$ps['ok'] || empty($ps['row'])) continue;
        $ocProdId = (int)$ps['row']['site_product_id'];
        if ($ocProdId <= 0) continue;

        // OC attribute_id
        $asm = Database::fetchRow('Papir',
            "SELECT site_attribute_id FROM attribute_site_mapping WHERE attribute_id = {$attributeId} AND site_id = {$siteId} LIMIT 1");
        if (!$asm['ok'] || empty($asm['row'])) continue;
        $ocAttrId = (int)$asm['row']['site_attribute_id'];

        // OC language_id
        $sl = Database::fetchRow('Papir',
            "SELECT site_lang_id FROM site_languages WHERE site_id = {$siteId} AND language_id = {$languageId} LIMIT 1");
        if (!$sl['ok'] || empty($sl['row'])) continue;
        $ocLangId = (int)$sl['row']['site_lang_id'];

        $textEscSite = Database::escape($dbAlias, $text);
        Database::query($dbAlias,
            "INSERT INTO oc_product_attribute (product_id, attribute_id, language_id, text)
             VALUES ({$ocProdId}, {$ocAttrId}, {$ocLangId}, '{$textEscSite}')
             ON DUPLICATE KEY UPDATE text = '{$textEscSite}'");
    }
}

echo json_encode(array('ok' => true));
