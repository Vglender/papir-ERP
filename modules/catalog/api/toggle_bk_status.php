<?php
/**
 * Toggle product_papir.status (BK active/inactive).
 * When disabling: cascades status=0 to all product_site entries and oc_product on each site.
 * When enabling: only changes BK status, sites stay as-is (must be enabled manually).
 *
 * POST: product_id, enabled (1|0)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId = Request::postInt('product_id', 0);
$enabled   = Request::postInt('enabled', 0) ? 1 : 0;

if ($productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid product_id'));
    exit;
}

// Update BK status
$r = Database::query('Papir',
    "UPDATE product_papir SET status = {$enabled} WHERE product_id = {$productId}"
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

$siteStatuses = array();

// If disabling — cascade to all sites
if ($enabled === 0) {
    // Load all site mappings
    $psAll = Database::fetchAll('Papir',
        "SELECT ps.site_id, ps.site_product_id, s.db_alias
         FROM product_site ps
         JOIN sites s ON s.site_id = ps.site_id
         WHERE ps.product_id = {$productId}"
    );
    if ($psAll['ok'] && !empty($psAll['rows'])) {
        foreach ($psAll['rows'] as $ps) {
            $siteId        = (int)$ps['site_id'];
            $siteProductId = (int)$ps['site_product_id'];
            $db            = $ps['db_alias'];
            // Disable in product_site
            Database::query('Papir',
                "UPDATE product_site SET status = 0
                 WHERE product_id = {$productId} AND site_id = {$siteId}"
            );
            // Cascade to oc_product
            Database::query($db,
                "UPDATE oc_product SET status = 0 WHERE product_id = {$siteProductId}"
            );
            $siteStatuses[$siteId] = 0;
        }
    }
}

echo json_encode(array('ok' => true, 'enabled' => $enabled, 'site_statuses' => $siteStatuses));
