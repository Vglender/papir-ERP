<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$categoryId       = isset($_POST['category_id'])        ? (int)$_POST['category_id']        : 0;
$siteId           = isset($_POST['site_id'])            ? (int)$_POST['site_id']            : 0;
$parentSiteCatId  = isset($_POST['parent_site_cat_id']) ? (int)$_POST['parent_site_cat_id'] : 0;

if ($categoryId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'category_id and site_id required'));
    exit;
}

// Load site info
$siteRes = Database::fetchRow('Papir', "SELECT * FROM sites WHERE site_id = {$siteId} AND status = 1");
if (!$siteRes['ok'] || empty($siteRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Сайт не знайдено'));
    exit;
}
$site     = $siteRes['row'];
$siteCode = (string)$site['code'];
$dbAlias  = (string)$site['db_alias'];

// Check not already mapped
$existsR = Database::exists('Papir', 'category_site_mapping',
    array('category_id' => $categoryId, 'site_id' => $siteId));
if ($existsR['ok'] && $existsR['exists']) {
    echo json_encode(array('ok' => false, 'error' => 'Категорія вже є на цьому сайті'));
    exit;
}

// Load Papir category + names
$catRes = Database::fetchRow('Papir',
    "SELECT c.category_id, c.parent_id,
            d_uk.name AS name_uk, d_ru.name AS name_ru
     FROM categoria c
     LEFT JOIN category_description d_uk ON d_uk.category_id = c.category_id AND d_uk.language_id = 2
     LEFT JOIN category_description d_ru ON d_ru.category_id = c.category_id AND d_ru.language_id = 1
     WHERE c.category_id = {$categoryId}"
);
if (!$catRes['ok'] || empty($catRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Категорію не знайдено'));
    exit;
}
$cat = $catRes['row'];

// Validate parent_site_cat_id if provided (0 = root is always valid)
if ($parentSiteCatId > 0) {
    $parentCheckR = Database::fetchRow($dbAlias,
        "SELECT category_id FROM oc_category WHERE category_id = {$parentSiteCatId}"
    );
    if (!$parentCheckR['ok'] || empty($parentCheckR['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Батьківська категорія на сайті не знайдена'));
        exit;
    }
}

// Load site languages
$langsRes = Database::fetchAll('Papir',
    "SELECT sl.language_id, sl.site_lang_id, l.code
     FROM site_languages sl
     JOIN languages l ON l.language_id = sl.language_id
     WHERE sl.site_id = {$siteId}
     ORDER BY l.sort_order"
);
if (!$langsRes['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка завантаження мов'));
    exit;
}
$siteLangs = $langsRes['rows'];

function esc($db, $v) { return Database::escape($db, $v); }

// Build oc_category_path: take parent's existing path + parent itself
// This mirrors how OpenCart builds paths: path of parent + [parent_id, new_id]
$siteAncestorIds = array();
if ($parentSiteCatId > 0) {
    $pathRes = Database::fetchAll($dbAlias,
        "SELECT path_id FROM oc_category_path
         WHERE category_id = {$parentSiteCatId}
         ORDER BY level ASC"
    );
    if ($pathRes['ok']) {
        foreach ($pathRes['rows'] as $pr) {
            $siteAncestorIds[] = (int)$pr['path_id'];
        }
    }
    // parent itself is already in path_res (last entry), so nothing extra needed
}

// INSERT oc_category
$now = date('Y-m-d H:i:s');
if ($siteCode === 'off') {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $insR = Database::query('off',
        "INSERT INTO oc_category (parent_id, top, `column`, sort_order, status, noindex, date_added, date_modified, uuid)
         VALUES ({$parentSiteCatId}, 0, 1, 0, 0, 0, '{$now}', '{$now}', '" . esc('off', $uuid) . "')"
    );
} elseif ($siteCode === 'mff') {
    $insR = Database::query('mff',
        "INSERT INTO oc_category (parent_id, top, `column`, sort_order, status, date_added, date_modified)
         VALUES ({$parentSiteCatId}, 0, 1, 0, 0, '{$now}', '{$now}')"
    );
} else {
    echo json_encode(array('ok' => false, 'error' => 'Непідтримуваний сайт: ' . $siteCode));
    exit;
}

if (!$insR['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка створення категорії на сайті'));
    exit;
}

// Get new category_id on site
$newIdR = Database::fetchRow($dbAlias, "SELECT LAST_INSERT_ID() AS new_id");
if (!$newIdR['ok'] || empty($newIdR['row']) || (int)$newIdR['row']['new_id'] === 0) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось отримати новий category_id'));
    exit;
}
$newSiteCatId = (int)$newIdR['row']['new_id'];

// INSERT oc_category_description for each language
foreach ($siteLangs as $sl) {
    $papirLangId = (int)$sl['language_id'];
    $siteLangId  = (int)$sl['site_lang_id'];
    $name = ($papirLangId === 2)
        ? (string)$cat['name_uk']
        : (string)$cat['name_ru'];
    if ($name === '') {
        $name = (string)$cat['name_uk'];
    }
    Database::query($dbAlias,
        "INSERT INTO oc_category_description (category_id, language_id, name, description, meta_title, meta_description, meta_keyword)
         VALUES ({$newSiteCatId}, {$siteLangId}, '" . esc($dbAlias, $name) . "', '', '', '', '')"
    );
}

// INSERT oc_category_to_store (store_id=0)
Database::query($dbAlias,
    "INSERT IGNORE INTO oc_category_to_store (category_id, store_id) VALUES ({$newSiteCatId}, 0)"
);

// INSERT oc_category_path (ancestors from root + self)
$pathLevel = 0;
foreach ($siteAncestorIds as $pathCatId) {
    Database::query($dbAlias,
        "INSERT IGNORE INTO oc_category_path (category_id, path_id, level)
         VALUES ({$newSiteCatId}, {$pathCatId}, {$pathLevel})"
    );
    $pathLevel++;
}
// Self entry
Database::query($dbAlias,
    "INSERT IGNORE INTO oc_category_path (category_id, path_id, level)
     VALUES ({$newSiteCatId}, {$newSiteCatId}, {$pathLevel})"
);

// INSERT category_site_mapping
Database::query('Papir',
    "INSERT INTO category_site_mapping (category_id, site_id, site_category_id)
     VALUES ({$categoryId}, {$siteId}, {$newSiteCatId})
     ON DUPLICATE KEY UPDATE site_category_id = {$newSiteCatId}"
);

// Update legacy columns
if ($siteCode === 'off') {
    Database::update('Papir', 'categoria',
        array('category_off' => $newSiteCatId),
        array('category_id'  => $categoryId)
    );
} elseif ($siteCode === 'mff') {
    Database::update('Papir', 'categoria',
        array('category_mf' => $newSiteCatId),
        array('category_id' => $categoryId)
    );
}

echo json_encode(array('ok' => true, 'site_category_id' => $newSiteCatId));
