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
        "SELECT ps.product_id, ps.site_id, ps.site_product_id, s.db_alias
         FROM product_site ps
         JOIN sites s ON s.site_id = ps.site_id
         WHERE ps.product_id IN ({$idList})"
    );

    if ($psAll['ok'] && !empty($psAll['rows'])) {
        // Disable all product_site entries in one query
        Database::query('Papir',
            "UPDATE product_site SET status = 0 WHERE product_id IN ({$idList})"
        );

        // Group site_product_ids by db_alias for batch oc_product updates
        $byDb = array();
        foreach ($psAll['rows'] as $ps) {
            $db  = $ps['db_alias'];
            $spid = (int)$ps['site_product_id'];
            if ($spid > 0) {
                if (!isset($byDb[$db])) {
                    $byDb[$db] = array();
                }
                $byDb[$db][] = $spid;
            }
        }

        foreach ($byDb as $db => $spIds) {
            $spList = implode(',', $spIds);
            $rc = Database::query($db,
                "UPDATE oc_product SET status = 0 WHERE product_id IN ({$spList})"
            );
            if (!$rc['ok']) {
                $errors[] = 'DB error cascading to ' . $db;
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
