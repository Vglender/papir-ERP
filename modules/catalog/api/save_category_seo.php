<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$categoryId    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
$siteId        = isset($_POST['site_id'])     ? (int)$_POST['site_id']     : 0;
$siteStatus    = isset($_POST['status'])      ? (int)$_POST['status']      : 0;
$siteSortOrder = isset($_POST['sort_order'])  ? (int)$_POST['sort_order']  : 0;

if ($categoryId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'category_id and site_id required'));
    exit;
}

require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';

function esc($db, $v) { return Database::escape($db, $v); }

$sync = new SiteSyncService();
$siteCatId = $sync->getSiteCategoryId($categoryId, $siteId);

// Load all languages
$langsRes = Database::fetchAll('Papir', "SELECT language_id, code FROM languages ORDER BY sort_order, language_id");
if (!$langsRes['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Cannot load languages'));
    exit;
}
$languages = $langsRes['rows'];

$cascadeDescriptions = array();
$cascadeSeoUrls      = array();

foreach ($languages as $lang) {
    $langId   = (int)$lang['language_id'];
    $langCode = (string)$lang['code']; // 'uk' or 'ru'

    $catName     = isset($_POST['cat_name_'         . $langId]) ? trim($_POST['cat_name_'         . $langId]) : '';
    $metaTitle   = isset($_POST['meta_title_'       . $langId]) ? trim($_POST['meta_title_'       . $langId]) : '';
    $metaDesc    = isset($_POST['meta_description_' . $langId]) ? trim($_POST['meta_description_' . $langId]) : '';
    $description = isset($_POST['description_'     . $langId]) ? trim($_POST['description_'     . $langId]) : '';
    $seoH1       = isset($_POST['seo_h1_'           . $langId]) ? trim($_POST['seo_h1_'           . $langId]) : '';
    $seoUrl      = isset($_POST['seo_url_'          . $langId]) ? trim($_POST['seo_url_'          . $langId]) : '';

    // 1. Upsert category_seo (all languages stored in Papir regardless of site link)
    Database::query('Papir',
        "INSERT INTO category_seo
            (category_id, site_id, language_id, meta_title, meta_description, description, seo_h1, seo_url, cat_name)
         VALUES (
            {$categoryId}, {$siteId}, {$langId},
            '" . esc('Papir', $metaTitle) . "',
            '" . esc('Papir', $metaDesc) . "',
            '" . esc('Papir', $description) . "',
            '" . esc('Papir', $seoH1) . "',
            '" . esc('Papir', $seoUrl) . "',
            '" . esc('Papir', $catName) . "'
         )
         ON DUPLICATE KEY UPDATE
            meta_title       = '" . esc('Papir', $metaTitle) . "',
            meta_description = '" . esc('Papir', $metaDesc) . "',
            description      = '" . esc('Papir', $description) . "',
            seo_h1           = '" . esc('Papir', $seoH1) . "',
            seo_url          = '" . esc('Papir', $seoUrl) . "',
            cat_name         = '" . esc('Papir', $catName) . "'"
    );

    // 2. Skip cascade if no site link
    if ($siteCatId <= 0) {
        continue;
    }

    // 3. Get site_lang_id for this language
    $slRes = Database::fetchRow('Papir',
        "SELECT site_lang_id FROM site_languages WHERE site_id = {$siteId} AND language_id = {$langId}"
    );
    if (!$slRes['ok'] || empty($slRes['row'])) {
        continue;
    }
    $siteLangId = (int)$slRes['row']['site_lang_id'];

    // 4. Collect descriptions and SEO URLs for batch cascade
    $cascadeDescriptions[] = array(
        'language_id'      => $siteLangId,
        'name'             => $catName,
        'description'      => $description,
        'meta_title'       => $metaTitle,
        'meta_description' => $metaDesc,
        'meta_h1'          => $seoH1,
    );
    if ($seoUrl !== '') {
        $cascadeSeoUrls[] = array(
            'keyword'     => $seoUrl,
            'language_id' => $siteLangId,
            'store_id'    => 0,
        );
    }
}

// 5. Cascade to site via SiteSyncService
if ($siteCatId > 0) {
    $sync->categoryUpdate($siteId, $siteCatId,
        array('status' => $siteStatus, 'sort_order' => $siteSortOrder),
        $cascadeDescriptions,
        $cascadeSeoUrls
    );
}

echo json_encode(array('ok' => true));
