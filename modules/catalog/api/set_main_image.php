<?php
/**
 * Set a product image as the main image for a specific site.
 *
 * Logic:
 *  - Reorders product_image_site sort_order so the selected image is first (sort_order=0).
 *  - Calls ProductImageService::syncToSite() which rebuilds oc_product.image + oc_product_image.
 *
 * POST: product_id (int), image_id (int), site_id (int)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId = Request::postInt('product_id', 0);
$imageId   = Request::postInt('image_id', 0);
$siteId    = Request::postInt('site_id', 0);

if ($productId <= 0 || $imageId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

// Verify image belongs to product
$rImg = Database::fetchRow('Papir',
    "SELECT image_id FROM product_image WHERE image_id = {$imageId} AND product_id = {$productId}"
);
if (!$rImg['ok'] || empty($rImg['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Image not found'));
    exit;
}

// Verify image is assigned to this site
$rPis = Database::fetchRow('Papir',
    "SELECT image_id FROM product_image_site WHERE image_id = {$imageId} AND site_id = {$siteId}"
);
if (!$rPis['ok'] || empty($rPis['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Image not assigned to this site'));
    exit;
}

// Get all images for this product/site ordered by sort_order, image_id
$rAll = Database::fetchAll('Papir',
    "SELECT pis.image_id, pis.sort_order
     FROM product_image_site pis
     JOIN product_image pi ON pi.image_id = pis.image_id
     WHERE pi.product_id = {$productId} AND pis.site_id = {$siteId}
     ORDER BY pis.sort_order ASC, pis.image_id ASC"
);
if (!$rAll['ok'] || empty($rAll['rows'])) {
    echo json_encode(array('ok' => false, 'error' => 'No images for this site'));
    exit;
}

$allImages = $rAll['rows'];

// Check if already main (first in order)
if ((int)$allImages[0]['image_id'] === $imageId) {
    // Already main — get current OC path and return
    $siteProd = Database::fetchRow('Papir',
        "SELECT ps.site_product_id, s.db_alias FROM product_site ps JOIN sites s ON s.site_id = ps.site_id
         WHERE ps.product_id = {$productId} AND ps.site_id = {$siteId} LIMIT 1"
    );
    $mainPath = '';
    if ($siteProd['ok'] && !empty($siteProd['row'])) {
        $ocId = (int)$siteProd['row']['site_product_id'];
        $db   = $siteProd['row']['db_alias'];
        $rOc  = Database::fetchRow($db, "SELECT `image` FROM `oc_product` WHERE `product_id` = {$ocId}");
        if ($rOc['ok'] && !empty($rOc['row'])) $mainPath = $rOc['row']['image'];
    }
    echo json_encode(array('ok' => true, 'action' => 'already_main', 'main_path' => $mainPath));
    exit;
}

// Remove target from list and prepend it
$ordered = array();
$ordered[] = $imageId;
foreach ($allImages as $row) {
    if ((int)$row['image_id'] !== $imageId) {
        $ordered[] = (int)$row['image_id'];
    }
}

// Reassign sort_orders 0, 1, 2, ...
foreach ($ordered as $newSort => $imgId) {
    Database::query('Papir',
        "UPDATE product_image_site SET sort_order = {$newSort}
         WHERE image_id = {$imgId} AND site_id = {$siteId}"
    );
}

// Sync to OC site
$service = new ProductImageService();
$service->syncToSite($productId, $siteId);

// Return new main image path
$siteProd2 = Database::fetchRow('Papir',
    "SELECT ps.site_product_id, s.db_alias FROM product_site ps JOIN sites s ON s.site_id = ps.site_id
     WHERE ps.product_id = {$productId} AND ps.site_id = {$siteId} LIMIT 1"
);
$newMainPath = '';
if ($siteProd2['ok'] && !empty($siteProd2['row'])) {
    $ocId2 = (int)$siteProd2['row']['site_product_id'];
    $db2   = $siteProd2['row']['db_alias'];
    $rOc2  = Database::fetchRow($db2, "SELECT `image` FROM `oc_product` WHERE `product_id` = {$ocId2}");
    if ($rOc2['ok'] && !empty($rOc2['row'])) $newMainPath = $rOc2['row']['image'];
}

echo json_encode(array('ok' => true, 'action' => 'updated', 'main_path' => $newMainPath));
