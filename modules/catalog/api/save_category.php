<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$categoryId  = isset($_POST['category_id'])  ? (int)$_POST['category_id']       : 0;
$status      = isset($_POST['status'])       ? (int)$_POST['status']            : 0;
$sortOrder   = isset($_POST['sort_order'])   ? (int)$_POST['sort_order']        : 0;
$nameUa      = isset($_POST['name_ua'])      ? trim($_POST['name_ua'])          : '';
$nameRu      = isset($_POST['name_ru'])      ? trim($_POST['name_ru'])          : '';
$descUa      = isset($_POST['desc_ua'])      ? (string)$_POST['desc_ua']        : '';
$descRu      = isset($_POST['desc_ru'])      ? (string)$_POST['desc_ru']        : '';

if ($categoryId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'category_id required'));
    exit;
}
if ($nameUa === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва (UA) обов\'язкова'));
    exit;
}

// Get current cascade targets
$catRes = Database::fetchRow('Papir',
    "SELECT category_off, category_mf FROM categoria WHERE category_id = {$categoryId}"
);
if (!$catRes['ok'] || empty($catRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Категорію не знайдено'));
    exit;
}
$offCatId = (int)$catRes['row']['category_off'];
$mffCatId = (int)$catRes['row']['category_mf'];

function esc($db, $v) { return Database::escape($db, $v); }

// 1. Update categoria
Database::update('Papir', 'categoria',
    array('status' => $status, 'sort_order' => $sortOrder),
    array('category_id' => $categoryId)
);

// 2. Upsert category_description lang=1 (RU)
Database::query('Papir',
    "INSERT INTO category_description (category_id, language_id, name, description_full)
     VALUES ({$categoryId}, 1, '" . esc('Papir', $nameRu) . "', '" . esc('Papir', $descRu) . "')
     ON DUPLICATE KEY UPDATE
       name='" . esc('Papir', $nameRu) . "',
       description_full='" . esc('Papir', $descRu) . "'"
);

// 3. Upsert category_description lang=2 (UA)
Database::query('Papir',
    "INSERT INTO category_description (category_id, language_id, name, description_full)
     VALUES ({$categoryId}, 2, '" . esc('Papir', $nameUa) . "', '" . esc('Papir', $descUa) . "')
     ON DUPLICATE KEY UPDATE
       name='" . esc('Papir', $nameUa) . "',
       description_full='" . esc('Papir', $descUa) . "'"
);

// 4. Cascade names + description → off
if ($offCatId > 0) {
    Database::query('off',
        "UPDATE oc_category_description SET
           name='"        . esc('off', $nameRu) . "',
           description='" . esc('off', $descRu) . "'
         WHERE category_id = {$offCatId} AND language_id = 1"
    );
    Database::query('off',
        "UPDATE oc_category_description SET
           name='"        . esc('off', $nameUa) . "',
           description='" . esc('off', $descUa) . "'
         WHERE category_id = {$offCatId} AND language_id = 4"
    );
}

// 5. Cascade names + description → mff
if ($mffCatId > 0) {
    Database::query('mff',
        "UPDATE oc_category_description SET
           name='"        . esc('mff', $nameRu) . "',
           description='" . esc('mff', $descRu) . "'
         WHERE category_id = {$mffCatId} AND language_id = 1"
    );
    Database::query('mff',
        "UPDATE oc_category_description SET
           name='"        . esc('mff', $nameUa) . "',
           description='" . esc('mff', $descUa) . "'
         WHERE category_id = {$mffCatId} AND language_id = 2"
    );
}

echo json_encode(array('ok' => true, 'name' => $nameUa));
