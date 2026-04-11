<?php
/**
 * Public API: фото товару для модалки в клієнтському порталі.
 *
 * GET: c (short_code), product_id
 * Response: { ok: true, product: {name, sku}, photos: ["https://..."] }
 *
 * Валідація:
 *   - short_code → order_id (через ClientPortalService)
 *   - product_id має бути в позиціях цього замовлення
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/src/Database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../ClientPortalService.php';

$dbConfigs = require __DIR__ . '/../../database/config/databases.php';
Database::init($dbConfigs);

AppRegistry::boot();
if (!AppRegistry::isActive('client_portal')) {
    echo json_encode(array('ok' => false, 'error' => 'inactive'));
    exit;
}

$code      = isset($_GET['c']) ? (string)$_GET['c'] : '';
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($code === '' || $productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'invalid params'));
    exit;
}

$orderId = ClientPortalService::resolveByCode($code);
if (!$orderId) {
    echo json_encode(array('ok' => false, 'error' => 'not found'));
    exit;
}

// Гарантуємо, що цей product належить до цього замовлення
$r = Database::fetchRow('Papir',
    "SELECT 1 FROM customerorder_item
     WHERE customerorder_id = {$orderId} AND product_id = {$productId} LIMIT 1");
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'not in order'));
    exit;
}

// Інфо про товар — назва з product_description (UA або RU), SKU з product_papir.
// Fallback: назва з customerorder_item.product_name.
$rProd = Database::fetchRow('Papir',
    "SELECT pp.product_article,
            COALESCE(NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), ci.product_name) AS name
     FROM product_papir pp
     LEFT JOIN product_description pd_uk ON pd_uk.product_id = pp.product_id AND pd_uk.language_id = 2
     LEFT JOIN product_description pd_ru ON pd_ru.product_id = pp.product_id AND pd_ru.language_id = 1
     LEFT JOIN customerorder_item   ci   ON ci.product_id = pp.product_id AND ci.customerorder_id = {$orderId}
     WHERE pp.product_id = {$productId} LIMIT 1");
$product = array('name' => '', 'sku' => '');
if ($rProd['ok'] && !empty($rProd['row'])) {
    $product['name'] = $rProd['row']['name'];
    $product['sku']  = $rProd['row']['product_article'];
}

// Фото
$rImgs = Database::fetchAll('Papir',
    "SELECT path FROM product_image
     WHERE product_id = {$productId}
     ORDER BY sort_order ASC, image_id ASC");

$base   = 'https://officetorg.com.ua/image/';
$photos = array();
if ($rImgs['ok'] && !empty($rImgs['rows'])) {
    foreach ($rImgs['rows'] as $img) {
        if (!empty($img['path'])) $photos[] = $base . $img['path'];
    }
}

echo json_encode(array(
    'ok'      => true,
    'product' => $product,
    'photos'  => $photos,
));