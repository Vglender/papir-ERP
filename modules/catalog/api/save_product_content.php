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
$dbAlias  = (string)$siteR['row']['db_alias'];
$siteCode = (string)$siteR['row']['code'];

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

// 4. Cascade name/description/meta to oc_product_description
$ocDescUpdate = array();
if ($name !== null)            $ocDescUpdate['name']             = $name;
if ($description !== null)     $ocDescUpdate['description']      = $description;
if ($metaTitle !== null)       $ocDescUpdate['meta_title']       = $metaTitle;
if ($metaDescription !== null) $ocDescUpdate['meta_description'] = $metaDescription;

if (!empty($ocDescUpdate)) {
    Database::update($dbAlias, 'oc_product_description', $ocDescUpdate,
        array('product_id' => $siteProductId, 'language_id' => $siteLangId)
    );
}

// 5. Cascade seo_url to OC
if ($seoUrl !== null) {
    if ($siteCode === 'off') {
        // off uses oc_url_alias — no language dimension, use UK slug (language_id=2)
        if ($languageId === 2) {
            $escSlug = Database::escape('off', $seoUrl);
            Database::query('off',
                "INSERT INTO oc_url_alias (query, keyword)
                 VALUES ('product_id={$siteProductId}', '{$escSlug}')
                 ON DUPLICATE KEY UPDATE keyword='{$escSlug}'"
            );
        }
    } elseif ($siteCode === 'mff') {
        // mff uses oc_seo_url with store_id + language_id
        $escSlug = Database::escape('mff', $seoUrl);
        Database::query('mff',
            "INSERT INTO oc_seo_url (store_id, language_id, query, keyword)
             VALUES (0, {$siteLangId}, 'product_id={$siteProductId}', '{$escSlug}')
             ON DUPLICATE KEY UPDATE keyword='{$escSlug}'"
        );
    }
}

echo json_encode(array('ok' => true));
