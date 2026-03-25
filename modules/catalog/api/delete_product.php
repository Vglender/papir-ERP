<?php
/**
 * Delete one or more products with full cascade.
 *
 * Cascade order (reverse of creation):
 *  Per site (product_site):
 *    1. oc_product_image       — extra images
 *    2. oc_product_description — all lang descriptions
 *    3. oc_product_discount    — qty discounts
 *    4. oc_product_special     — action/special prices
 *    5. oc_product_to_category
 *    6. oc_product_to_store
 *    7. oc_product_to_layout
 *    8. oc_product_attribute
 *    9. oc_product_related     — both directions
 *   10. oc_url_alias WHERE query = 'product_id={id}'
 *   11. oc_product             — the product row
 *  Physical files:
 *   12. local disk + mff FTP (MffFtpSync)
 *  Papir tables:
 *   13. product_image_site
 *   14. product_image
 *   15. product_site
 *   16. product_seo
 *   17. product_discount_profile
 *   18. product_attribute_value
 *   19. product_price_settings
 *   20. product_package
 *   21. action_prices / action_products
 *   22. price_supplier_items   — set product_id = NULL (unlink, keep pricelist row)
 *   23. product_description
 *   24. product_papir
 *
 * POST: product_ids (comma-separated int list)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$rawIds = isset($_POST['product_ids']) ? trim($_POST['product_ids']) : '';
if ($rawIds === '') {
    echo json_encode(array('ok' => false, 'error' => 'product_ids required'));
    exit;
}

$productIds = array();
foreach (explode(',', $rawIds) as $v) {
    $id = (int)trim($v);
    if ($id > 0) $productIds[] = $id;
}
$productIds = array_unique($productIds);

if (empty($productIds)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid product_ids'));
    exit;
}

// ── Load sites map ────────────────────────────────────────────────────────────
$rSites = Database::fetchAll('Papir', "SELECT site_id, db_alias, code FROM sites WHERE status = 1");
$sitesMap = array();
if ($rSites['ok']) {
    foreach ($rSites['rows'] as $s) {
        $sitesMap[(int)$s['site_id']] = array('db' => $s['db_alias'], 'code' => $s['code']);
    }
}

$errors = array();
$deleted = 0;

foreach ($productIds as $productId) {
    $err = _deleteOne($productId, $sitesMap);
    if ($err === null) {
        $deleted++;
    } else {
        $errors[] = array('product_id' => $productId, 'error' => $err);
    }
}

echo json_encode(array(
    'ok'      => count($errors) === 0,
    'deleted' => $deleted,
    'errors'  => $errors,
));

// ─────────────────────────────────────────────────────────────────────────────

function _deleteOne($productId, $sitesMap)
{
    $productId = (int)$productId;

    // Verify product exists
    $rProd = Database::fetchRow('Papir',
        "SELECT product_id FROM product_papir WHERE product_id = {$productId}"
    );
    if (!$rProd['ok'] || empty($rProd['row'])) {
        return 'Product not found';
    }

    // ── 1–11: Per-site OpenCart cascade ──────────────────────────────────────
    $rPs = Database::fetchAll('Papir',
        "SELECT site_id, site_product_id FROM product_site WHERE product_id = {$productId}"
    );
    $hasMff = false;

    if ($rPs['ok']) {
        foreach ($rPs['rows'] as $ps) {
            $siteId        = (int)$ps['site_id'];
            $siteProductId = (int)$ps['site_product_id'];
            if ($siteProductId <= 0 || !isset($sitesMap[$siteId])) continue;

            $db = $sitesMap[$siteId]['db'];
            if ($sitesMap[$siteId]['code'] === 'mff') $hasMff = true;

            _deleteOcProduct($db, $siteProductId);

            // Invalidate OC seo_pro cache for off site
            if ($sitesMap[$siteId]['code'] === 'off') {
                _invalidateSeoPro();
            }
        }
    }

    // ── 12: Physical image files ──────────────────────────────────────────────
    $rImgs = Database::fetchAll('Papir',
        "SELECT path FROM product_image WHERE product_id = {$productId}"
    );
    $imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';

    if ($rImgs['ok']) {
        $ftp = $hasMff ? new MffFtpSync() : null;
        foreach ($rImgs['rows'] as $img) {
            $path = (string)$img['path'];
            if ($path === '') continue;
            $full = $imageBase . ltrim($path, '/');
            if (file_exists($full)) @unlink($full);
            if ($ftp) $ftp->delete($path);
        }
        if ($ftp) $ftp->disconnect();
    }

    // ── 13–24: Papir cascade ─────────────────────────────────────────────────

    // product_image_site (via product_image)
    $rImgIds = Database::fetchAll('Papir',
        "SELECT image_id FROM product_image WHERE product_id = {$productId}"
    );
    if ($rImgIds['ok'] && !empty($rImgIds['rows'])) {
        $imgIdList = implode(',', array_map(function($r) { return (int)$r['image_id']; }, $rImgIds['rows']));
        Database::query('Papir', "DELETE FROM product_image_site WHERE image_id IN ({$imgIdList})");
    }

    Database::query('Papir', "DELETE FROM product_image           WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_site            WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_seo             WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_discount_profile WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_attribute_value WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_price_settings  WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_package         WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM action_prices           WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM action_products         WHERE product_id = {$productId}");

    // Unlink from pricelist items (keep row, clear product match)
    Database::query('Papir',
        "UPDATE price_supplier_items SET product_id = NULL, match_type = 'unmatched'
         WHERE product_id = {$productId}"
    );

    Database::query('Papir', "DELETE FROM product_description WHERE product_id = {$productId}");
    Database::query('Papir', "DELETE FROM product_papir      WHERE product_id = {$productId}");

    return null;
}

function _deleteOcProduct($db, $siteProductId)
{
    $id = (int)$siteProductId;

    Database::query($db, "DELETE FROM oc_product_image      WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_description WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_discount   WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_special    WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_to_category WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_to_store   WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_to_layout  WHERE product_id = {$id}");
    Database::query($db, "DELETE FROM oc_product_attribute  WHERE product_id = {$id}");
    Database::query($db,
        "DELETE FROM oc_product_related WHERE product_id = {$id} OR related_id = {$id}"
    );
    Database::query($db,
        "DELETE FROM oc_url_alias WHERE query = 'product_id={$id}'"
    );
    Database::query($db, "DELETE FROM oc_product WHERE product_id = {$id}");
}

function _invalidateSeoPro()
{
    $dir = '/var/www/menufold/data/www/officetorg.com.ua/system/storage/cache/';
    foreach (glob($dir . 'cache.seo_pro.*') as $file)        @unlink($file);
    foreach (glob($dir . 'cache.product.seopath.*') as $file) @unlink($file);
}
