<?php
/**
 * Bulk toggle product_papir.status (BK active/inactive) for multiple products.
 * When disabling: cascades status=0 to product_site + oc_product on each site.
 * When enabling:  only changes BK status, sites stay as-is.
 *
 * POST: product_ids (comma-separated Papir product_ids), enabled (1|0)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$enabled    = Request::postInt('enabled', 0) ? 1 : 0;
$rawIds     = isset($_POST['product_ids']) ? trim($_POST['product_ids']) : '';

if ($rawIds === '') {
    echo json_encode(array('ok' => false, 'error' => 'No product_ids'));
    exit;
}

// Parse and validate product_ids
$productIds = array();
foreach (explode(',', $rawIds) as $part) {
    $pid = (int)trim($part);
    if ($pid > 0) {
        $productIds[] = $pid;
    }
}

if (empty($productIds)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid product_ids'));
    exit;
}

$idList    = implode(',', $productIds);
$processed = 0;
$errors    = array();

// -- Update BK status in batch --
$r = Database::query('Papir',
    "UPDATE product_papir SET status = {$enabled} WHERE product_id IN ({$idList})"
);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error updating product_papir'));
    exit;
}
$processed = (int)$r['affected_rows'];

// -- If disabling: cascade to product_site + oc_product per site --
if ($enabled === 0) {
    // Load all site mappings for these products
    $psAll = Database::fetchAll('Papir',
        "SELECT ps.product_id, ps.site_id, ps.site_product_id
         FROM product_site ps
         JOIN sites s ON s.site_id = ps.site_id AND s.status = 1
         WHERE ps.product_id IN ({$idList})"
    );

    if ($psAll['ok'] && !empty($psAll['rows'])) {
        Database::query('Papir',
            "UPDATE product_site SET status = 0 WHERE product_id IN ({$idList})"
        );

        // Group by site_id for batch updates via SiteSyncService
        require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
        $sync = new SiteSyncService();
        $bySite = array();
        foreach ($psAll['rows'] as $ps) {
            $sid  = (int)$ps['site_id'];
            $spid = (int)$ps['site_product_id'];
            if ($spid > 0) {
                if (!isset($bySite[$sid])) $bySite[$sid] = array();
                $bySite[$sid][] = array('product_id' => $spid, 'quantity' => 0);
            }
        }

        // Use batchQuantity with status=0 via productUpdate per site
        foreach ($bySite as $sid => $items) {
            foreach ($items as $item) {
                $sync->productUpdate($sid, $item['product_id'], array('status' => 0));
            }
        }
    }
}

echo json_encode(array(
    'ok'        => true,
    'enabled'   => $enabled,
    'processed' => $processed,
    'errors'    => $errors,
));
