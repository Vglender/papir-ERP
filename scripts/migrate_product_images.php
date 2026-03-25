<?php
/**
 * Migrate product images to product_image + product_image_site
 *
 * Що робить:
 *   1. Створює таблиці product_image, product_image_site (якщо не існують)
 *   2. Для кожного товару збирає фото з усіх джерел (Papir + off + mff)
 *   3. Дедуплікує по шляху, нормалізує sort_order
 *   4. Заповнює product_image (мастер) і product_image_site (per-site)
 *   5. Оновлює product_papir.image кешем першого фото
 *
 * Ідемпотентний: безпечно запускати повторно (INSERT IGNORE).
 *
 * Використання:
 *   php scripts/migrate_product_images.php             -- dry-run (показати без запису)
 *   php scripts/migrate_product_images.php --execute   -- реальний запис у БД
 *   php scripts/migrate_product_images.php --execute --offset=5000 --limit=1000
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../modules/database/database.php';

$execute  = in_array('--execute', $argv);
$offset   = 0;
$limit    = 0; // 0 = all
foreach ($argv as $arg) {
    if (preg_match('/^--offset=(\d+)$/', $arg, $m)) $offset = (int)$m[1];
    if (preg_match('/^--limit=(\d+)$/', $arg, $m))  $limit  = (int)$m[1];
}

function out($msg) { echo $msg . "\n"; flush(); }
function sec($s)   { return round(microtime(true) - $s, 1); }

out("=== Migrate Product Images ===");
out("Mode:    " . ($execute ? "EXECUTE" : "DRY-RUN (pass --execute to write)"));
out("Started: " . date('Y-m-d H:i:s'));
out("");

// ─── Step 1: Create tables ────────────────────────────────────────────────────

out("[1/6] Creating tables (if not exist)...");

$createProductImage = "
CREATE TABLE IF NOT EXISTS `product_image` (
    `image_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`  INT UNSIGNED NOT NULL,
    `path`        VARCHAR(256)  NOT NULL DEFAULT '',
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `date_added`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`image_id`),
    UNIQUE KEY `uq_product_path` (`product_id`, `path`(200)),
    KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createProductImageSite = "
CREATE TABLE IF NOT EXISTS `product_image_site` (
    `image_id`    INT UNSIGNED NOT NULL,
    `site_id`     TINYINT UNSIGNED NOT NULL,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    PRIMARY KEY (`image_id`, `site_id`),
    KEY `idx_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($execute) {
    $r = Database::query('Papir', $createProductImage);
    if (!$r['ok']) { out("ERROR creating product_image: "); exit(1); }
    $r = Database::query('Papir', $createProductImageSite);
    if (!$r['ok']) { out("ERROR creating product_image_site"); exit(1); }
    out("  Tables ready.");
} else {
    out("  [dry-run] Would create product_image + product_image_site");
}

// ─── Step 2: Load all Papir products ─────────────────────────────────────────

out("[2/6] Loading Papir products...");
$t = microtime(true);

$limitSql = $limit > 0 ? " LIMIT {$offset}, {$limit}" : ($offset > 0 ? " LIMIT {$offset}, 999999999" : "");

$r = Database::fetchAll('Papir',
    "SELECT product_id, id_off, id_mf, image
     FROM product_papir
     WHERE id_off > 0 OR id_mf > 0
     ORDER BY product_id" . $limitSql
);

$products = array();
$byIdOff  = array();
$byIdMf   = array();
foreach ($r['rows'] as $row) {
    $pid = (int)$row['product_id'];
    $products[$pid] = array(
        'id_off'      => (int)$row['id_off'],
        'id_mf'       => (int)$row['id_mf'],
        'papir_main'  => trim((string)$row['image']),
        'papir_extra' => array(), // path => sort_order
        'off_main'    => '',
        'off_extra'   => array(), // path => sort_order
        'mff_main'    => '',
        'mff_extra'   => array(),
    );
    if ((int)$row['id_off'] > 0) $byIdOff[(int)$row['id_off']] = $pid;
    if ((int)$row['id_mf']  > 0) $byIdMf[(int)$row['id_mf']]  = $pid;
}
out("  Products: " . count($products) . "  (" . sec($t) . "s)");

// ─── Step 3: Load Papir.image extra ──────────────────────────────────────────

out("[3/6] Loading Papir.image...");
$t = microtime(true);

$pidList = implode(',', array_keys($products));
if ($pidList !== '') {
    $r = Database::fetchAll('Papir',
        "SELECT product_id, image, sort_order
         FROM `image`
         WHERE product_id IN ({$pidList})
           AND image IS NOT NULL AND image != ''
         ORDER BY sort_order ASC, product_image_id ASC"
    );
    foreach ($r['rows'] as $row) {
        $pid  = (int)$row['product_id'];
        $path = trim((string)$row['image']);
        if (!isset($products[$pid]) || $path === '') continue;
        if ($path === $products[$pid]['papir_main']) continue; // skip dup of main
        $products[$pid]['papir_extra'][$path] = (int)$row['sort_order'];
    }
    out("  Loaded " . count($r['rows']) . " rows  (" . sec($t) . "s)");
}

// ─── Step 4: Load off images ─────────────────────────────────────────────────

out("[4/6] Loading off images...");
$t = microtime(true);

$idOffList = array_keys($byIdOff);
if (!empty($idOffList)) {
    $idOffSql = implode(',', $idOffList);

    $rMain = Database::fetchAll('off',
        "SELECT product_id, image FROM oc_product
         WHERE product_id IN ({$idOffSql})
           AND image IS NOT NULL AND image != ''"
    );
    foreach ($rMain['rows'] as $row) {
        $pid = isset($byIdOff[(int)$row['product_id']]) ? $byIdOff[(int)$row['product_id']] : 0;
        if (!$pid) continue;
        $products[$pid]['off_main'] = trim((string)$row['image']);
    }

    $rExtra = Database::fetchAll('off',
        "SELECT product_id, image, sort_order FROM oc_product_image
         WHERE product_id IN ({$idOffSql})
           AND image IS NOT NULL AND image != ''
         ORDER BY sort_order ASC, product_image_id ASC"
    );
    foreach ($rExtra['rows'] as $row) {
        $pid  = isset($byIdOff[(int)$row['product_id']]) ? $byIdOff[(int)$row['product_id']] : 0;
        $path = trim((string)$row['image']);
        if (!$pid || $path === '') continue;
        if ($path === $products[$pid]['off_main']) continue;
        $products[$pid]['off_extra'][$path] = (int)$row['sort_order'];
    }
    out("  main: " . count($rMain['rows']) . ", extra: " . count($rExtra['rows']) . "  (" . sec($t) . "s)");
}

// ─── Step 5: Build unified image list per product ────────────────────────────

out("[5/6] Building unified image lists...");
$t = microtime(true);

$SITE_OFF = 1;
$SITE_MFF = 2;

$totalImages     = 0;
$totalSiteRows   = 0;
$totalUpdMain    = 0;

// Batch insert buffers
$imgInserts      = array();
$siteInserts     = array();
$mainUpdates     = array(); // product_id => new_main_path

$imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';

foreach ($products as $pid => $p) {
    // Build ordered path list for off (most complete source)
    // off: main=sort0, extra sorted by off sort_order
    $offPaths = array(); // path => off_sort
    if ($p['off_main'] !== '') $offPaths[$p['off_main']] = 0;
    foreach ($p['off_extra'] as $path => $sort) {
        if (!isset($offPaths[$path])) $offPaths[$path] = $sort;
    }

    // Build Papir path list
    $papirPaths = array(); // path => papir_sort
    if ($p['papir_main'] !== '') $papirPaths[$p['papir_main']] = 0;
    foreach ($p['papir_extra'] as $path => $sort) {
        if (!isset($papirPaths[$path])) $papirPaths[$path] = $sort;
    }

    // Union: start from off (priority), add papir-exclusive paths
    $allPaths = $offPaths;
    foreach ($papirPaths as $path => $sort) {
        if (!isset($allPaths[$path])) $allPaths[$path] = $sort + 1000; // papir-exclusive at end
    }

    if (empty($allPaths)) continue;

    // Sort by sort_order value, then reassign clean sequential order
    asort($allPaths);
    $seq = 0;
    $firstPath = '';
    foreach ($allPaths as $path => $rawSort) {
        // Skip if file doesn't exist on disk
        if (!file_exists($imageBase . ltrim($path, '/'))) continue;

        if ($firstPath === '') $firstPath = $path;

        $imgInserts[] = array(
            'product_id' => $pid,
            'path'       => $path,
            'sort_order' => $seq,
        );

        // Which sites have this path?
        if (isset($offPaths[$path])) {
            $siteInserts[] = array(
                'path'       => $path,  // placeholder, replaced with image_id after insert
                'product_id' => $pid,
                'site_id'    => $SITE_OFF,
                'sort_order' => $seq,
            );
        }
        // mff currently has 0 images — skip for now (will be populated on first push)

        $seq++;
        $totalImages++;
    }

    // Track main image update
    if ($firstPath !== '' && $firstPath !== $p['papir_main']) {
        $mainUpdates[$pid] = $firstPath;
        $totalUpdMain++;
    }
    $totalSiteRows += $seq; // approximate
}

out("  Images to insert: {$totalImages}");
out("  Main updates:     {$totalUpdMain}");
out("  (" . sec($t) . "s)");

// ─── Step 6: Write to DB ─────────────────────────────────────────────────────

out("[6/6] Writing to DB...");
$t = microtime(true);

if (!$execute) {
    out("  [dry-run] Would insert ~{$totalImages} product_image rows");
    out("  [dry-run] Would insert site rows for off");
    out("  [dry-run] Would update {$totalUpdMain} product_papir.image values");
    out("  Pass --execute to apply.");
} else {
    $BATCH = 500;

    // 6a. Insert product_image in batches
    $inserted = 0;
    $chunks   = array_chunk($imgInserts, $BATCH);
    foreach ($chunks as $chunk) {
        $vals = array();
        foreach ($chunk as $img) {
            $path = Database::escape('Papir', $img['path']);
            $vals[] = "({$img['product_id']}, '{$path}', {$img['sort_order']})";
        }
        $sql = "INSERT IGNORE INTO `product_image` (product_id, path, sort_order) VALUES " . implode(',', $vals);
        $r   = Database::query('Papir', $sql);
        if ($r['ok']) $inserted += (int)$r['affected_rows'];

        if ($inserted % 5000 === 0 && $inserted > 0) out("  inserted {$inserted} image rows...");
    }
    out("  product_image inserted: {$inserted}");

    // 6b. Load image_id map: (product_id, path) => image_id
    out("  Loading image_id map...");
    $pidList2 = implode(',', array_keys($products));
    $rMap = Database::fetchAll('Papir',
        "SELECT image_id, product_id, path FROM product_image WHERE product_id IN ({$pidList2})"
    );
    $imageIdMap = array(); // "product_id:path" => image_id
    foreach ($rMap['rows'] as $row) {
        $key = $row['product_id'] . ':' . $row['path'];
        $imageIdMap[$key] = (int)$row['image_id'];
    }
    out("  Loaded " . count($imageIdMap) . " image_id mappings");

    // 6c. Insert product_image_site
    $siteInserted = 0;
    $siteVals     = array();
    foreach ($siteInserts as $si) {
        $key      = $si['product_id'] . ':' . $si['path'];
        $imageId  = isset($imageIdMap[$key]) ? $imageIdMap[$key] : 0;
        if (!$imageId) continue;
        $siteVals[] = "({$imageId}, {$si['site_id']}, {$si['sort_order']})";
        if (count($siteVals) >= $BATCH) {
            $sql = "INSERT IGNORE INTO `product_image_site` (image_id, site_id, sort_order) VALUES " . implode(',', $siteVals);
            $r   = Database::query('Papir', $sql);
            if ($r['ok']) $siteInserted += (int)$r['affected_rows'];
            $siteVals = array();
        }
    }
    if (!empty($siteVals)) {
        $sql = "INSERT IGNORE INTO `product_image_site` (image_id, site_id, sort_order) VALUES " . implode(',', $siteVals);
        $r   = Database::query('Papir', $sql);
        if ($r['ok']) $siteInserted += (int)$r['affected_rows'];
    }
    out("  product_image_site inserted: {$siteInserted}");

    // 6d. Update product_papir.image cache
    $updCount = 0;
    foreach ($mainUpdates as $pid => $path) {
        $escaped = Database::escape('Papir', $path);
        $r = Database::query('Papir',
            "UPDATE product_papir SET image = '{$escaped}' WHERE product_id = {$pid}"
        );
        if ($r['ok']) $updCount++;
    }
    out("  product_papir.image updated: {$updCount}");
}

out("");
out("=== Done ===");
out("  Images:     {$totalImages}");
out("  Site rows:  {$totalSiteRows} (approx)");
out("  Main fixes: {$totalUpdMain}");
out("Finished: " . date('Y-m-d H:i:s'));
