<?php
/**
 * Toggle product_site.status for a product on a specific site.
 * Cascades to oc_product.status on the target site.
 *
 * POST: product_id, site_id, enabled (1|0)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId = Request::postInt('product_id', 0);
$siteId    = Request::postInt('site_id', 0);
$enabled   = Request::postInt('enabled', 0) ? 1 : 0;

if ($productId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

// Cannot enable on site if BK is inactive
if ($enabled === 1) {
    $bkRow = Database::fetchRow('Papir',
        "SELECT status FROM product_papir WHERE product_id = {$productId} LIMIT 1"
    );
    if (!$bkRow['ok'] || empty($bkRow['row']) || (int)$bkRow['row']['status'] !== 1) {
        echo json_encode(array('ok' => false, 'error' => 'Нельзя включить на сайте: товар неактивен в БК'));
        exit;
    }
}

// Get site_product_id for cascade
$psRow = Database::fetchRow('Papir',
    "SELECT site_product_id FROM product_site
     WHERE product_id = {$productId} AND site_id = {$siteId} LIMIT 1"
);
if (!$psRow['ok'] || empty($psRow['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Нет записи product_site'));
    exit;
}
$siteProductId = (int)$psRow['row']['site_product_id'];

// Update product_site
$r = Database::query('Papir',
    "UPDATE product_site SET status = {$enabled}
     WHERE product_id = {$productId} AND site_id = {$siteId}"
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

// Cascade to external site oc_product
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();
$sync->productUpdate($siteId, $siteProductId, array('status' => $enabled));

echo json_encode(array('ok' => true, 'enabled' => $enabled));
