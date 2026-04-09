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

$name            = array_key_exists('name',             $fields) ? trim($fields['name'])             : null;
$description     = array_key_exists('description',      $fields) ? trim($fields['description'])      : null;
$metaTitle       = array_key_exists('meta_title',       $fields) ? trim($fields['meta_title'])       : null;
$metaDescription = array_key_exists('meta_description', $fields) ? trim($fields['meta_description']) : null;
$seoH1           = array_key_exists('seo_h1',           $fields) ? trim($fields['seo_h1'])           : null;
$seoUrl          = array_key_exists('seo_url',          $fields) ? trim($fields['seo_url'])          : null;

if ($name === null && $description === null && $metaTitle === null &&
    $metaDescription === null && $seoH1 === null && $seoUrl === null) {
    echo json_encode(array('ok' => false, 'error' => 'No fields to save'));
    exit;
}

// 1. Save name/description to product_description (Papir) — site-independent
$pdUpdate = array();
if ($name !== null)        $pdUpdate['name']        = $name;
if ($description !== null) $pdUpdate['description'] = $description;

if (!empty($pdUpdate)) {
    $existsR = Database::exists('Papir', 'product_description',
        array('product_id' => $productId, 'language_id' => $languageId));
    if ($existsR['ok'] && $existsR['exists']) {
        Database::update('Papir', 'product_description', $pdUpdate,
            array('product_id' => $productId, 'language_id' => $languageId)
        );
    } else {
        Database::insert('Papir', 'product_description',
            array_merge(array('product_id' => $productId, 'language_id' => $languageId), $pdUpdate)
        );
    }
}

// 2. Upsert meta/seo fields into product_seo (Papir) — site+language specific
$seoUpdate = array();
if ($metaTitle !== null)       $seoUpdate['meta_title']       = $metaTitle;
if ($metaDescription !== null) $seoUpdate['meta_description'] = $metaDescription;
if ($seoH1 !== null)           $seoUpdate['seo_h1']           = $seoH1;
if ($seoUrl !== null)          $seoUpdate['seo_url']          = $seoUrl;

if (!empty($seoUpdate)) {
    $seoKey = array('product_id' => $productId, 'site_id' => $siteId, 'language_id' => $languageId);
    $seoExistsR = Database::exists('Papir', 'product_seo', $seoKey);
    if ($seoExistsR['ok'] && $seoExistsR['exists']) {
        Database::update('Papir', 'product_seo', $seoUpdate, $seoKey);
    } else {
        Database::insert('Papir', 'product_seo', array_merge($seoKey, $seoUpdate));
    }
}

// 3. Resolve site DB alias, site_product_id, site_lang_id
$siteR = Database::fetchRow('Papir', "SELECT db_alias, code FROM sites WHERE site_id = {$siteId} AND status = 1");
if (!$siteR['ok'] || empty($siteR['row'])) {
    echo json_encode(array('ok' => true, 'warning' => 'Site not found, saved to Papir only'));
    exit;
}
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();

$siteProductId = $sync->getSiteProductId($productId, $siteId);
if ($siteProductId <= 0) {
    echo json_encode(array('ok' => true, 'warning' => 'Product not linked to site, saved to Papir only'));
    exit;
}

$langMap = $sync->getSiteLanguages($siteId);
$siteLangId = isset($langMap[$languageId]) ? $langMap[$languageId] : 0;
if ($siteLangId <= 0) {
    echo json_encode(array('ok' => true, 'warning' => 'Language mapping not found, saved to Papir only'));
    exit;
}

// 4. Cascade name/description/meta + SEO URL via SiteSyncService
$descriptions = array();
$descFields = array('language_id' => $siteLangId);
if ($name !== null)            $descFields['name']             = $name;
if ($description !== null)     $descFields['description']      = $description;
if ($metaTitle !== null)       $descFields['meta_title']       = $metaTitle;
if ($metaDescription !== null) $descFields['meta_description'] = $metaDescription;
if (count($descFields) > 1) {
    $descriptions[] = $descFields;
}

$seoUrls = array();
if ($seoUrl !== null && $seoUrl !== '') {
    $seoUrls[] = array('keyword' => $seoUrl, 'language_id' => $siteLangId, 'store_id' => 0);
}

$sync->productSeo($siteId, $siteProductId, $descriptions, $seoUrls);

echo json_encode(array('ok' => true));
