<?php
/**
 * Image Cache Warmer
 *
 * 1. Очищає image/cache/ (опціонально)
 * 2. Генерує thumbnails для активних товарів і категорій
 *
 * Використання:
 *   php scripts/warm_image_cache.php                    — тільки генерація
 *   php scripts/warm_image_cache.php --clear-first      — очистка + генерація
 *   php scripts/warm_image_cache.php --dry-run          — показати скільки файлів без змін
 *
 * Розміри: беруться з реально існуючих файлів в cache (ті що OpenCart використовує)
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

define('IMAGE_ROOT',  '/var/www/menufold/data/www/officetorg.com.ua/image/');
define('CACHE_ROOT',  IMAGE_ROOT . 'cache/');

// Sizes used by the theme (found by analyzing existing cache)
$PRODUCT_SIZES  = array(
    array(228, 228),
    array(400, 400),
    array(800, 800),
    array(74,  74),
    array(50,  50),
);
$CATEGORY_SIZES = array(
    array(60,  60),
    array(228, 228),
    array(300, 300),
);

$clearFirst = in_array('--clear-first', $argv);
$dryRun     = in_array('--dry-run',     $argv);

require_once __DIR__ . '/../modules/database/database.php';

function out($msg) { echo $msg . "\n"; flush(); }
function sec($s)   { return round(microtime(true) - $s, 1); }

out("=== Image Cache Warmer ===");
out("Started: " . date('Y-m-d H:i:s'));
out("Mode:    " . ($dryRun ? 'DRY RUN' : ($clearFirst ? 'CLEAR + GENERATE' : 'GENERATE ONLY')));
out("");

// ─── 1. Clear cache ───────────────────────────────────────────────────────────

if ($clearFirst && !$dryRun) {
    out("[1] Clearing cache...");
    $t = microtime(true);
    $deleted = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_ROOT, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if ($f->isFile()) {
            @unlink($f->getPathname());
            $deleted++;
            if ($deleted % 50000 === 0) out("    deleted {$deleted}...");
        }
    }
    out("  Deleted {$deleted} cached files  (" . sec($t) . "s)");
} else {
    out("[1] Skipping cache clear (use --clear-first to enable)");
}

// ─── 2. Collect images to warm ───────────────────────────────────────────────

out("[2] Loading active products and categories...");
$t = microtime(true);

// Active products: from Papir (status=1) joined to off image
$prodRes = Database::fetchAll('Papir',
    "SELECT pp.id_off, op.image as main_image
     FROM product_papir pp
     JOIN menufold_offtorg.oc_product op ON op.product_id = pp.id_off
     WHERE pp.status = 1
       AND op.image IS NOT NULL AND op.image != ''"
);
$products = ($prodRes['ok']) ? $prodRes['rows'] : array();

// Also get gallery images for active products
$idOffs = array();
foreach ($products as $p) $idOffs[] = (int)$p['id_off'];
$galleryImages = array(); // id_off => [image, ...]
if (!empty($idOffs)) {
    $inList = implode(',', $idOffs);
    $galRes = Database::fetchAll('off',
        "SELECT product_id, image FROM oc_product_image
         WHERE product_id IN ({$inList}) AND image IS NOT NULL AND image != ''
         ORDER BY sort_order"
    );
    if ($galRes['ok']) {
        foreach ($galRes['rows'] as $row) {
            $pid = (int)$row['product_id'];
            if (!isset($galleryImages[$pid])) $galleryImages[$pid] = array();
            $galleryImages[$pid][] = $row['image'];
        }
    }
}

// Active categories
$catRes = Database::fetchAll('off',
    "SELECT category_id, image FROM oc_category
     WHERE status = 1 AND image IS NOT NULL AND image != ''"
);
$categories = ($catRes['ok']) ? $catRes['rows'] : array();

out("  Products:   " . count($products) . " (with gallery images for each)");
out("  Categories: " . count($categories) . "  (" . sec($t) . "s)");

// ─── 3. Generate thumbnails ───────────────────────────────────────────────────

out("[3] Generating thumbnails...");

$generated = 0;
$skipped   = 0;  // already exists
$errors    = 0;
$t         = microtime(true);

/**
 * Generate one thumbnail and save to cache.
 * Matches OpenCart resize behavior: proportional fit, white background.
 */
function makeThumbnail($relImage, $w, $h, $dryRun) {
    global $generated, $skipped, $errors;

    $srcAbs  = IMAGE_ROOT . $relImage;
    if (!file_exists($srcAbs)) { $errors++; return; }

    // Cache path: cache/{path_without_ext}-WxH.ext
    $ext      = strtolower(pathinfo($relImage, PATHINFO_EXTENSION));
    $base     = substr($relImage, 0, strrpos($relImage, '.'));
    $cacheRel = $base . '-' . $w . 'x' . $h . '.' . $ext;
    $cacheAbs = CACHE_ROOT . $cacheRel;

    if (file_exists($cacheAbs)) { $skipped++; return; }
    if ($dryRun) { $generated++; return; }

    // Ensure dir exists
    $dir = dirname($cacheAbs);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Load
    $info = @getimagesize($srcAbs);
    if (!$info) { $errors++; return; }
    $mime  = $info['mime'];
    $origW = (int)$info[0];
    $origH = (int)$info[1];

    $src = null;
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($srcAbs); break;
        case 'image/png':  $src = @imagecreatefrompng($srcAbs);  break;
        case 'image/webp': $src = @imagecreatefromwebp($srcAbs); break;
        case 'image/gif':  $src = @imagecreatefromgif($srcAbs);  break;
    }
    if (!$src) { $errors++; return; }

    // Proportional fit within WxH
    $scale = min($w / $origW, $h / $origH);
    $fitW  = (int)round($origW * $scale);
    $fitH  = (int)round($origH * $scale);
    $offX  = (int)floor(($w - $fitW) / 2);
    $offY  = (int)floor(($h - $fitH) / 2);

    $canvas = imagecreatetruecolor($w, $h);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $src, $offX, $offY, 0, 0, $fitW, $fitH, $origW, $origH);
    imagedestroy($src);

    imagejpeg($canvas, $cacheAbs, 85);
    imagedestroy($canvas);
    $generated++;
}

// Products
$total    = count($products);
$done     = 0;
global $PRODUCT_SIZES;
foreach ($products as $p) {
    $images = array($p['main_image']);
    $pid    = (int)$p['id_off'];
    if (isset($galleryImages[$pid])) {
        $images = array_merge($images, $galleryImages[$pid]);
    }
    foreach ($images as $img) {
        foreach ($PRODUCT_SIZES as $size) {
            makeThumbnail($img, $size[0], $size[1], $dryRun);
        }
    }
    $done++;
    if ($done % 500 === 0) {
        out(sprintf("  products %d/%d — generated=%d skipped=%d errors=%d  (%.1fs)",
            $done, $total, $generated, $skipped, $errors, sec($t)));
    }
}

// Categories
global $CATEGORY_SIZES;
foreach ($categories as $c) {
    foreach ($CATEGORY_SIZES as $size) {
        makeThumbnail($c['image'], $size[0], $size[1], $dryRun);
    }
}

out("");
out("=== Results ===");
out("  Generated: {$generated}");
out("  Skipped (already cached): {$skipped}");
out("  Errors:    {$errors}");
if ($dryRun) out("  (DRY RUN — no files written)");
out("Finished: " . date('Y-m-d H:i:s'));
