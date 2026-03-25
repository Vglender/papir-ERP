<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$categoryId    = isset($_POST['category_id'])    ? (int)$_POST['category_id']    : 0;
$siteId        = isset($_POST['site_id'])         ? (int)$_POST['site_id']         : 0;
$siteCategoryId = isset($_POST['site_category_id']) ? (int)$_POST['site_category_id'] : 0;
$action        = isset($_POST['action'])          ? trim($_POST['action'])          : 'save'; // save | clear

if ($categoryId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Невалідні параметри'));
    exit;
}

// Get site info
$siteRes = Database::fetchRow('Papir', "SELECT * FROM sites WHERE site_id={$siteId} AND status=1");
if (!$siteRes['ok'] || empty($siteRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Сайт не знайдено'));
    exit;
}
$site = $siteRes['row'];

if ($action === 'clear') {
    // Remove mapping
    Database::query('Papir',
        "DELETE FROM category_site_mapping WHERE category_id={$categoryId} AND site_id={$siteId}"
    );
    // Also clear legacy column
    if ($site['code'] === 'off') {
        Database::update('Papir', 'categoria', array('category_off' => null), array('category_id' => $categoryId));
    } elseif ($site['code'] === 'mff') {
        Database::update('Papir', 'categoria', array('category_mf'  => null), array('category_id' => $categoryId));
    }
    echo json_encode(array('ok' => true, 'action' => 'cleared'));
    exit;
}

// Save mapping
if ($siteCategoryId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Оберіть категорію сайту'));
    exit;
}

// Verify site category exists
$dbAlias = $site['db_alias'];
$checkRes = Database::fetchRow($dbAlias,
    "SELECT category_id FROM oc_category WHERE category_id={$siteCategoryId} AND status=1"
);
if (!$checkRes['ok'] || empty($checkRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Категорія сайту не знайдена'));
    exit;
}

// Upsert mapping
Database::query('Papir',
    "INSERT INTO category_site_mapping (category_id, site_id, site_category_id)
     VALUES ({$categoryId}, {$siteId}, {$siteCategoryId})
     ON DUPLICATE KEY UPDATE site_category_id={$siteCategoryId}, updated_at=NOW()"
);

// Update legacy columns for backward compatibility
if ($site['code'] === 'off') {
    Database::update('Papir', 'categoria',
        array('category_off' => $siteCategoryId),
        array('category_id'  => $categoryId)
    );
} elseif ($site['code'] === 'mff') {
    Database::update('Papir', 'categoria',
        array('category_mf' => $siteCategoryId),
        array('category_id' => $categoryId)
    );
}

echo json_encode(array('ok' => true, 'site_category_id' => $siteCategoryId));
