<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId  = isset($_POST['product_id'])  ? (int)$_POST['product_id']  : 0;
$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

if ($productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// category_id=0 means "clear"
if ($categoryId < 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid category_id'));
    exit;
}

// Get product info (id_off, id_mf)
$prodRes = Database::fetchRow('Papir',
    "SELECT product_id, id_off, id_mf FROM product_papir WHERE product_id = {$productId}"
);
if (!$prodRes['ok'] || empty($prodRes['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Товар не знайдено'));
    exit;
}
$prod  = $prodRes['row'];
$idOff = (int)$prod['id_off'];
$idMf  = (int)$prod['id_mf'];

// Get category info (category_off, category_mf, name)
$catName   = '';
$offCatId  = 0;
$mffCatId  = 0;

if ($categoryId > 0) {
    $catRes = Database::fetchRow('Papir',
        "SELECT c.category_off, c.category_mf, cd.name
         FROM categoria c
         LEFT JOIN category_description cd ON cd.category_id=c.category_id AND cd.language_id=2
         WHERE c.category_id = {$categoryId}"
    );
    if (!$catRes['ok'] || empty($catRes['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Категорію не знайдено'));
        exit;
    }
    $offCatId = (int)$catRes['row']['category_off'];
    $mffCatId = (int)$catRes['row']['category_mf'];
    $catName  = (string)$catRes['row']['name'];
}

// 1. Update Papir
Database::update('Papir', 'product_papir',
    array('categoria_id' => $categoryId > 0 ? $categoryId : null),
    array('product_id'   => $productId)
);

// 2. Cascade → off.oc_product_to_category
if ($idOff > 0) {
    if ($offCatId > 0) {
        Database::query('off',
            "DELETE FROM oc_product_to_category
             WHERE product_id={$idOff} AND main_category=1"
        );
        Database::query('off',
            "INSERT INTO oc_product_to_category (product_id, category_id, main_category)
             VALUES ({$idOff}, {$offCatId}, 1)
             ON DUPLICATE KEY UPDATE main_category=1"
        );
    } elseif ($categoryId === 0) {
        Database::query('off',
            "DELETE FROM oc_product_to_category
             WHERE product_id={$idOff} AND main_category=1"
        );
    }
}

// 3. Cascade → mff.oc_product_to_category
if ($idMf > 0 && $mffCatId > 0) {
    Database::query('mff',
        "DELETE FROM oc_product_to_category
         WHERE product_id={$idMf} AND main_category=1"
    );
    Database::query('mff',
        "INSERT INTO oc_product_to_category (product_id, category_id, main_category)
         VALUES ({$idMf}, {$mffCatId}, 1)
         ON DUPLICATE KEY UPDATE main_category=1"
    );
}

echo json_encode(array(
    'ok'          => true,
    'category_id' => $categoryId,
    'name'        => $catName,
));
