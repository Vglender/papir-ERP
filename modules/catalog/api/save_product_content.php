<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId  = isset($_POST['product_id'])  ? (int)$_POST['product_id']  : 0;
$siteId     = isset($_POST['site_id'])     ? (int)$_POST['site_id']     : 0;
$languageId = isset($_POST['language_id']) ? (int)$_POST['language_id'] : 0;
$fieldsRaw  = isset($_POST['fields'])      ? trim($_POST['fields'])      : '';

if ($productId <= 0 || $siteId <= 0 || $languageId <= 0 || $fieldsRaw === '') {
    echo json_encode(array('ok' => false, 'error' => 'product_id, site_id, language_id, fields required'));
    exit;
}

$fields = json_decode($fieldsRaw, true);
if (!is_array($fields)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid fields JSON'));
    exit;
}

$description     = isset($fields['description'])     ? trim($fields['description'])     : null;
$metaTitle       = isset($fields['meta_title'])       ? trim($fields['meta_title'])       : null;
$metaDescription = isset($fields['meta_description']) ? trim($fields['meta_description']) : null;

if ($description === null && $metaTitle === null && $metaDescription === null) {
    echo json_encode(array('ok' => false, 'error' => 'No fields to save'));
    exit;
}

// 1. Save description to product_description (Papir) — site-independent
if ($description !== null) {
    $existsR = Database::exists('Papir', 'product_description',
        array('product_id' => $productId, 'language_id' => $languageId));
    if ($existsR['ok'] && $existsR['exists']) {
        Database::update('Papir', 'product_description',
            array('description' => $description),
            array('product_id' => $productId, 'language_id' => $languageId)
        );
    } else {
        Database::insert('Papir', 'product_description',
            array('product_id' => $productId, 'language_id' => $languageId, 'description' => $description)
        );
    }
}

// 2. Upsert meta fields into product_seo (Papir) — site+language specific
$seoUpdate = array();
if ($metaTitle !== null)       $seoUpdate['meta_title']       = $metaTitle;
if ($metaDescription !== null) $seoUpdate['meta_description'] = $metaDescription;

if (!empty($seoUpdate)) {
    $seoKey = array('product_id' => $productId, 'site_id' => $siteId, 'language_id' => $languageId);
    $seoExistsR = Database::exists('Papir', 'product_seo', $seoKey);
    if ($seoExistsR['ok'] && $seoExistsR['exists']) {
        Database::update('Papir', 'product_seo', $seoUpdate, $seoKey);
    } else {
        Database::insert('Papir', 'product_seo', array_merge($seoKey, $seoUpdate));
    }
}

// 3. Cascade to oc_product_description in off/mff
$siteR = Database::fetchRow('Papir', "SELECT db_alias FROM sites WHERE site_id = {$siteId} AND status = 1");
if (!$siteR['ok'] || empty($siteR['row'])) {
    echo json_encode(array('ok' => true, 'warning' => 'Site not found, saved to Papir only'));
    exit;
}
$dbAlias = (string)$siteR['row']['db_alias'];

$psR = Database::fetchRow('Papir',
    "SELECT site_product_id FROM product_site WHERE product_id = {$productId} AND site_id = {$siteId}"
);
if (!$psR['ok'] || empty($psR['row'])) {
    echo json_encode(array('ok' => true, 'warning' => 'Product not linked to site, saved to Papir only'));
    exit;
}
$siteProductId = (int)$psR['row']['site_product_id'];

$slR = Database::fetchRow('Papir',
    "SELECT site_lang_id FROM site_languages WHERE site_id = {$siteId} AND language_id = {$languageId}"
);
if (!$slR['ok'] || empty($slR['row'])) {
    echo json_encode(array('ok' => true, 'warning' => 'Language mapping not found, saved to Papir only'));
    exit;
}
$siteLangId = (int)$slR['row']['site_lang_id'];

$ocData = array();
if ($description !== null)     $ocData['description']      = $description;
if ($metaTitle !== null)       $ocData['meta_title']       = $metaTitle;
if ($metaDescription !== null) $ocData['meta_description'] = $metaDescription;

if (!empty($ocData)) {
    Database::update($dbAlias, 'oc_product_description', $ocData,
        array('product_id' => $siteProductId, 'language_id' => $siteLangId)
    );
}

echo json_encode(array('ok' => true));
