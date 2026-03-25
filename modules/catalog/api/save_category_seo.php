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

function esc($db, $v) { return Database::escape($db, $v); }

// Load site info
$siteRes = Database::fetchRow('Papir', "SELECT site_id, code, url, db_alias FROM sites WHERE site_id = {$siteId}");
if (!$siteRes['ok'] || empty($siteRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Site not found'));
    exit;
}
$site     = $siteRes['row'];
$siteCode = (string)$site['code'];

// Load category cascade targets
$catRes = Database::fetchRow('Papir',
    "SELECT category_off, category_mf FROM categoria WHERE category_id = {$categoryId}"
);
if (!$catRes['ok'] || empty($catRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Category not found'));
    exit;
}
$siteCatId = 0;
if ($siteCode === 'off') {
    $siteCatId = (int)$catRes['row']['category_off'];
} elseif ($siteCode === 'mff') {
    $siteCatId = (int)$catRes['row']['category_mf'];
}

// Load all languages
$langsRes = Database::fetchAll('Papir', "SELECT language_id, code FROM languages ORDER BY sort_order, language_id");
if (!$langsRes['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Cannot load languages'));
    exit;
}
$languages = $langsRes['rows'];

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

    // 4. Cascade to oc_category_description (name always included)
    if ($siteCode === 'off') {
        Database::query('off',
            "UPDATE oc_category_description
             SET name='" . esc('off', $catName) . "',
                 description='" . esc('off', $description) . "',
                 meta_title='" . esc('off', $metaTitle) . "',
                 meta_description='" . esc('off', $metaDesc) . "',
                 meta_h1='" . esc('off', $seoH1) . "'
             WHERE category_id = {$siteCatId} AND language_id = {$siteLangId}"
        );
        // seo_url → oc_url_alias (no language dimension — UK slug only)
        if ($langCode === 'uk') {
            Database::query('off',
                "INSERT INTO oc_url_alias (query, keyword)
                 VALUES ('category_id={$siteCatId}', '" . esc('off', $seoUrl) . "')
                 ON DUPLICATE KEY UPDATE keyword='" . esc('off', $seoUrl) . "'"
            );
        }
    } elseif ($siteCode === 'mff') {
        Database::query('mff',
            "UPDATE oc_category_description
             SET name='" . esc('mff', $catName) . "',
                 description='" . esc('mff', $description) . "',
                 meta_title='" . esc('mff', $metaTitle) . "',
                 meta_description='" . esc('mff', $metaDesc) . "'
             WHERE category_id = {$siteCatId} AND language_id = {$siteLangId}"
        );
        // seo_url → mff.oc_seo_url (has language dimension — cascade for both languages)
        if ($seoUrl !== '') {
            Database::query('mff',
                "INSERT INTO oc_seo_url (store_id, language_id, query, keyword)
                 VALUES (0, {$siteLangId}, 'category_id={$siteCatId}', '" . esc('mff', $seoUrl) . "')
                 ON DUPLICATE KEY UPDATE keyword='" . esc('mff', $seoUrl) . "'"
            );
        }
    }
}

// 5. Cascade status/sort_order to oc_category
if ($siteCatId > 0) {
    if ($siteCode === 'off') {
        Database::update('off', 'oc_category',
            array('status' => $siteStatus, 'sort_order' => $siteSortOrder),
            array('category_id' => $siteCatId)
        );
    } elseif ($siteCode === 'mff') {
        Database::update('mff', 'oc_category',
            array('status' => $siteStatus, 'sort_order' => $siteSortOrder),
            array('category_id' => $siteCatId)
        );
    }
}

echo json_encode(array('ok' => true));
