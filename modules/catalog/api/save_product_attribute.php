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

// Cascade to all active sites
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();
$productSites = $sync->getProductSites($productId);

foreach ($productSites as $ps) {
    $siteId   = (int)$ps['site_id'];
    $ocProdId = (int)$ps['site_product_id'];
    if ($ocProdId <= 0) continue;

    // OC attribute_id
    $asm = Database::fetchRow('Papir',
        "SELECT site_attribute_id FROM attribute_site_mapping WHERE attribute_id = {$attributeId} AND site_id = {$siteId} LIMIT 1");
    if (!$asm['ok'] || empty($asm['row'])) continue;
    $ocAttrId = (int)$asm['row']['site_attribute_id'];

    // OC language_id
    $langMap = $sync->getSiteLanguages($siteId);
    $ocLangId = isset($langMap[$languageId]) ? $langMap[$languageId] : 0;
    if ($ocLangId <= 0) continue;

    $sync->productAttributes($siteId, $ocProdId, array(
        array('attribute_id' => $ocAttrId, 'language_id' => $ocLangId, 'text' => $text)
    ));
}

echo json_encode(array('ok' => true));
