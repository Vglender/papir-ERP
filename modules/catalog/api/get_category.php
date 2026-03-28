<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($categoryId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$r = Database::fetchRow('Papir',
    "SELECT c.category_id, c.parent_id, c.status, c.sort_order,
            c.image, c.category_off, c.category_mf,
            p.name as parent_name
     FROM categoria c
     LEFT JOIN category_description p ON p.category_id = c.parent_id AND p.language_id = 2
     WHERE c.category_id = {$categoryId}"
);
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Категорію не знайдено'));
    exit;
}
$cat = $r['row'];

$rUa = Database::fetchRow('Papir',
    "SELECT name, name_full, description_full FROM category_description WHERE category_id = {$categoryId} AND language_id = 2"
);
$rRu = Database::fetchRow('Papir',
    "SELECT name, description_full FROM category_description WHERE category_id = {$categoryId} AND language_id = 1"
);

$cat['ua'] = ($rUa['ok'] && !empty($rUa['row'])) ? $rUa['row'] : array();
$cat['ru'] = ($rRu['ok'] && !empty($rRu['row'])) ? $rRu['row'] : array();

// Children count
$chRes = Database::fetchRow('Papir',
    "SELECT COUNT(*) as cnt FROM categoria WHERE parent_id = {$categoryId}"
);
$cat['children_count'] = ($chRes['ok'] && !empty($chRes['row'])) ? (int)$chRes['row']['cnt'] : 0;

// Load all active sites, mark which are mapped for this category
$sitesRes = Database::fetchAll('Papir',
    "SELECT s.site_id, s.name, s.code, s.url, s.db_alias,
            COALESCE(csm.site_category_id, 0) AS site_category_id,
            IF(csm.site_category_id IS NOT NULL, 1, 0) AS mapped
     FROM sites s
     LEFT JOIN category_site_mapping csm ON csm.site_id = s.site_id AND csm.category_id = {$categoryId}
     WHERE s.status = 1
     ORDER BY s.sort_order, s.site_id"
);
$sites = ($sitesRes['ok']) ? $sitesRes['rows'] : array();

// Index site_category_id by site_id for later use
$siteCategoryIds = array();
foreach ($sites as $s) {
    $siteCategoryIds[(int)$s['site_id']] = (int)$s['site_category_id'];
}

// Load languages
$langsRes = Database::fetchAll('Papir', "SELECT language_id, code, name FROM languages ORDER BY sort_order, language_id");
$languages = ($langsRes['ok']) ? $langsRes['rows'] : array();

// Load SEO rows for this category (only for mapped sites)
$seoRes = Database::fetchAll('Papir',
    "SELECT site_id, language_id, meta_title, meta_description, description, seo_h1, seo_url, cat_name
     FROM category_seo
     WHERE category_id = {$categoryId}"
);
$seoRows = ($seoRes['ok']) ? $seoRes['rows'] : array();

// Build ancestor chain for cat_url
$ancestorIds = array($categoryId);
$current = (int)$cat['parent_id'];
$maxDepth = 20;
while ($current > 0 && $maxDepth-- > 0) {
    $ancestorIds[] = $current;
    $ancestorRes = Database::fetchRow('Papir', "SELECT parent_id FROM categoria WHERE category_id = {$current}");
    if ($ancestorRes['ok'] && !empty($ancestorRes['row'])) {
        $current = (int)$ancestorRes['row']['parent_id'];
    } else {
        break;
    }
}
$ancestorIds = array_reverse($ancestorIds);

// Build seo structure indexed by site_id => language_id => data
$seoMap = array();
foreach ($sites as $site) {
    $sid = (int)$site['site_id'];
    $seoMap[$sid] = array();
    foreach ($languages as $lang) {
        $lid = (int)$lang['language_id'];
        $seoMap[$sid][$lid] = array(
            'meta_title'       => '',
            'meta_description' => '',
            'description'      => '',
            'seo_h1'           => '',
            'seo_url'          => '',
            'cat_url'          => '',
            'cat_name'         => '',
        );
    }
}

// Fill from DB rows
foreach ($seoRows as $row) {
    $sid = (int)$row['site_id'];
    $lid = (int)$row['language_id'];
    if (isset($seoMap[$sid][$lid])) {
        $seoMap[$sid][$lid]['meta_title']       = (string)$row['meta_title'];
        $seoMap[$sid][$lid]['meta_description'] = (string)$row['meta_description'];
        $seoMap[$sid][$lid]['description']      = (string)$row['description'];
        $seoMap[$sid][$lid]['seo_h1']           = (string)$row['seo_h1'];
        $seoMap[$sid][$lid]['seo_url']          = (string)$row['seo_url'];
        $seoMap[$sid][$lid]['cat_name']         = (string)$row['cat_name'];
    }
}

// Compute cat_url (UK = language_id=1 only) for each site
foreach ($sites as $site) {
    $sid     = (int)$site['site_id'];
    $siteUrl = rtrim((string)$site['url'], '/');

    if (empty($ancestorIds)) {
        continue;
    }
    $idList = implode(',', $ancestorIds);
    $slugRes = Database::fetchAll('Papir',
        "SELECT category_id, seo_url FROM category_seo
         WHERE site_id = {$sid} AND language_id = 1 AND category_id IN ({$idList})"
    );
    $slugMap = array();
    if ($slugRes['ok']) {
        foreach ($slugRes['rows'] as $sr) {
            $slugMap[(int)$sr['category_id']] = (string)$sr['seo_url'];
        }
    }

    $parts = array();
    $valid = true;
    foreach ($ancestorIds as $aid) {
        $slug = isset($slugMap[$aid]) ? $slugMap[$aid] : '';
        if ($slug === '') {
            $valid = false;
            break;
        }
        $parts[] = $slug;
    }

    $catUrl = '';
    if ($valid && !empty($parts)) {
        $catUrl = $siteUrl . '/' . implode('/', $parts);
    }

    if (isset($seoMap[$sid][1])) {
        $seoMap[$sid][1]['cat_url'] = $catUrl;
    }
    if (isset($seoMap[$sid][2])) {
        $seoMap[$sid][2]['cat_url'] = '';
    }
}

// Build site_settings: status and sort_order from each site's oc_category
// Uses category_site_mapping (via $siteCategoryIds) instead of legacy category_off/category_mf
$siteSettings = array();
foreach ($sites as $site) {
    $sid       = (int)$site['site_id'];
    $dbAlias   = (string)$site['db_alias'];
    $siteCatId = isset($siteCategoryIds[$sid]) ? $siteCategoryIds[$sid] : 0;

    $siteSettings[$sid] = array('status' => 0, 'sort_order' => 0);
    if ($siteCatId > 0 && $dbAlias) {
        $scRes = Database::fetchRow($dbAlias,
            "SELECT status, sort_order FROM oc_category WHERE category_id = {$siteCatId}"
        );
        if ($scRes['ok'] && !empty($scRes['row'])) {
            $siteSettings[$sid]['status']     = (int)$scRes['row']['status'];
            $siteSettings[$sid]['sort_order'] = (int)$scRes['row']['sort_order'];
        }
    }
}

$cat['seo']              = $seoMap;
$cat['sites']            = $sites;           // только замапленные сайты
$cat['site_category_ids'] = $siteCategoryIds; // category_id на каждом сайте
$cat['languages']        = $languages;
$cat['site_settings']    = $siteSettings;

// Load category images
$imgsRes = Database::fetchAll('Papir',
    "SELECT image_id, image FROM category_images WHERE category_id = {$categoryId} ORDER BY sort_order, image_id"
);
$images = array();
if ($imgsRes['ok']) {
    foreach ($imgsRes['rows'] as $img) {
        $images[] = array(
            'image_id' => (int)$img['image_id'],
            'image'    => (string)$img['image'],
            'url'      => 'https://officetorg.com.ua/image/' . ltrim((string)$img['image'], '/'),
        );
    }
}
$cat['images'] = $images;

echo json_encode(array('ok' => true, 'data' => $cat));
