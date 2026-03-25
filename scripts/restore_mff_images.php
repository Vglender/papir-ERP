<?php
/**
 * Restore mff product images
 *
 * Mirrors site_id=1 (off) image assignments to site_id=2 (mff)
 * for all products that have id_mf > 0 in product_papir.
 * Then calls syncToSite() for each affected product to push images
 * into mff.oc_product and mff.oc_product_image.
 *
 * Usage:
 *   php scripts/restore_mff_images.php          -- dry-run
 *   php scripts/restore_mff_images.php --execute -- write to DB
 */

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/shared/ProductImageService.php';

$execute = in_array('--execute', $argv);

function out($msg) { echo $msg . "\n"; flush(); }

out("=== Restore mff Product Images ===");
out("Mode:    " . ($execute ? "EXECUTE" : "DRY-RUN (pass --execute to write)"));
out("Started: " . date('Y-m-d H:i:s'));
out("");

// 1. Find products with images on site 1 that also have id_mf > 0
$r = Database::fetchAll('Papir',
    "SELECT pi.image_id, pi.product_id, pis.sort_order
     FROM product_image pi
     JOIN product_image_site pis ON pis.image_id = pi.image_id AND pis.site_id = 1
     JOIN product_papir pp ON pp.product_id = pi.product_id AND pp.id_mf > 0
     ORDER BY pi.product_id, pis.sort_order ASC, pi.image_id ASC"
);

if (!$r['ok'] || empty($r['rows'])) {
    out("No images to restore.");
    exit(0);
}

out("Found " . count($r['rows']) . " image-site assignments to mirror to mff");

// Group by product
$byProduct = array(); // product_id => [image_id => sort_order, ...]
foreach ($r['rows'] as $row) {
    $pid = (int)$row['product_id'];
    if (!isset($byProduct[$pid])) $byProduct[$pid] = array();
    $byProduct[$pid][(int)$row['image_id']] = (int)$row['sort_order'];
}

out("Products to update: " . count($byProduct));
out("");

$inserted = 0;
$synced   = 0;
$errors   = array();

$svc = new ProductImageService();

foreach ($byProduct as $productId => $images) {
    // Insert site_id=2 assignments (INSERT IGNORE = skip if already exists)
    foreach ($images as $imageId => $sortOrder) {
        if ($execute) {
            $res = Database::query('Papir',
                "INSERT IGNORE INTO product_image_site (image_id, site_id, sort_order)
                 VALUES ({$imageId}, 2, {$sortOrder})"
            );
            if ($res['ok'] && (int)$res['affected_rows'] > 0) {
                $inserted++;
            }
        } else {
            $inserted++;
        }
    }

    // Sync to mff
    if ($execute) {
        $svc->syncToSite($productId, 2);
    }
    $synced++;

    if ($synced % 50 === 0) {
        out("  Synced {$synced}/" . count($byProduct) . " products...");
    }
}

out("");
out("=== Done ===");
out("Image-site rows " . ($execute ? "inserted" : "would insert") . ": {$inserted}");
out("Products " . ($execute ? "synced" : "would sync") . " to mff: {$synced}");
if (!empty($errors)) {
    out("Errors: " . count($errors));
    foreach ($errors as $e) out("  " . $e);
}
out("Finished: " . date('Y-m-d H:i:s'));
