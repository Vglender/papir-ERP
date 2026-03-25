<?php
/**
 * Image Audit Utility
 *
 * Знаходить:
 *   1. Orphans      — файли на диску, що не прив'язані до жодного об'єкта в БД
 *   2. Broken       — записи в БД, де файл відсутній на диску
 *   3. Duplicates   — однакові файли (по MD5, тільки referenced)
 *   4. Oversized    — filesize > SIZE_THRESHOLD або розміри > DIM_MAX
 *   5. Undersized   — будь-який вимір < DIM_MIN
 *
 * Використання:
 *   php scripts/image_audit.php                 — аналіз, зберегти звіт
 *   php scripts/image_audit.php --delete-orphans — також видалити сиріт
 *
 * Звіт: scripts/image_audit_results.json
 * Виключає: image/cache/, image/ocfilter/
 */

set_time_limit(0);
ini_set('memory_limit', '1G');

define('IMAGE_ROOT',     '/var/www/menufold/data/www/officetorg.com.ua/image/');
define('REPORT_FILE',    __DIR__ . '/image_audit_results.json');
define('SIZE_THRESHOLD', 500 * 1024);  // 500 KB
define('DIM_MAX',        2000);         // px — oversized dimensions
define('DIM_MIN',        50);           // px — undersized

// Directories to skip entirely during scan (relative to IMAGE_ROOT)
$SKIP_DIRS = array('cache', 'ocfilter');

// Directories whose files are NEVER deleted as orphans (site system files)
$PROTECTED_DIRS = array('catalog/logo', 'catalog/Лого', 'catalog/Logo');

$deleteOrphans = in_array('--delete-orphans', $argv);
$fixBroken     = in_array('--fix-broken', $argv);

require_once __DIR__ . '/../modules/database/database.php';

// ─── helpers ─────────────────────────────────────────────────────────────────

function out($msg) { echo $msg . "\n"; flush(); }
function sec($s)   { return round(microtime(true) - $s, 1); }
function normPath($p) { return ltrim(strtolower(trim((string)$p)), '/'); }

// ─── 1. Collect DB references ────────────────────────────────────────────────

out("=== Image Audit ===");
out("Started: " . date('Y-m-d H:i:s'));
out("");
out("[1/5] Collecting DB references...");
$t = microtime(true);

$dbRefs = array(); // normPath => true  (just a lookup set)

function addRefs($db, $sql) {
    global $dbRefs;
    $r = Database::fetchAll($db, $sql);
    if (!$r['ok']) return;
    foreach ($r['rows'] as $row) {
        $v = normPath(reset($row));
        if ($v !== '') $dbRefs[$v] = true;
    }
}

addRefs('Papir', "SELECT image FROM product_papir   WHERE image IS NOT NULL AND image != ''");
addRefs('Papir', "SELECT image FROM `image`          WHERE image IS NOT NULL AND image != ''");
addRefs('Papir', "SELECT image FROM categoria        WHERE image IS NOT NULL AND image != ''");
addRefs('Papir', "SELECT image FROM category_images  WHERE image IS NOT NULL AND image != ''");
addRefs('Papir', "SELECT image FROM manufacturers    WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_product        WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_product_image  WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_category       WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_manufacturer   WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_banner_image    WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_revblog_images  WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_blog_category   WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT image FROM oc_sticker         WHERE image IS NOT NULL AND image != ''");
addRefs('off',   "SELECT \`value\` FROM oc_setting WHERE \`value\` LIKE 'catalog/%' OR \`value\` LIKE 'image/%'");
addRefs('mff',   "SELECT image FROM oc_product        WHERE image IS NOT NULL AND image != ''");
addRefs('mff',   "SELECT image FROM oc_product_image  WHERE image IS NOT NULL AND image != ''");
addRefs('mff',   "SELECT image FROM oc_category       WHERE image IS NOT NULL AND image != ''");
addRefs('mff',   "SELECT image FROM oc_manufacturer   WHERE image IS NOT NULL AND image != ''");

$totalDbRefs = count($dbRefs);
out("  Found {$totalDbRefs} unique DB references  (" . sec($t) . "s)");

// ─── 2. Scan disk + compute orphans/broken/size/dims ─────────────────────────

out("[2/5] Scanning disk (excluding cache)...");
$t = microtime(true);

global $SKIP_DIRS;
$extensions = array('jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1, 'gif' => 1);

$totalFiles  = 0;
$orphans     = array();
$oversized   = array();
$undersized  = array();
$seenOnDisk  = array(); // normPath => true (for broken detection)

// Also collect file sizes for duplicate pre-grouping
$sizeGroups = array(); // filesize => [normPath, ...]

$dirIter = new RecursiveDirectoryIterator(IMAGE_ROOT,
    RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
$filter  = new RecursiveCallbackFilterIterator($dirIter, function($current, $key, $iterator) use ($SKIP_DIRS) {
    if ($iterator->hasChildren()) {
        $name = $current->getFilename();
        return !in_array($name, $SKIP_DIRS);
    }
    return true;
});
$iter = new RecursiveIteratorIterator($filter);

foreach ($iter as $fileInfo) {
    if (!$fileInfo->isFile()) continue;
    $ext = strtolower($fileInfo->getExtension());
    if (!isset($extensions[$ext])) continue;

    $abs  = $fileInfo->getPathname();
    $rel  = substr($abs, strlen(IMAGE_ROOT));
    $norm = normPath($rel);

    $totalFiles++;
    $seenOnDisk[$norm] = true;

    // Orphan?
    $isOrphan = !isset($dbRefs[$norm]);
    if ($isOrphan) {
        // Skip protected directories — never delete system files like logo/favicon
        $protected = false;
        foreach ($PROTECTED_DIRS as $pdir) {
            if (strpos($norm, strtolower($pdir) . '/') === 0 || $norm === strtolower($pdir)) {
                $protected = true;
                break;
            }
        }
        if (!$protected) {
            // Store original absolute path so deletion works regardless of case/encoding
            $orphans[$norm] = $abs;
        }
    }

    // Size + dims check (for referenced files; skip orphans to save time)
    if (!$isOrphan) {
        $size = @filesize($abs);
        if ($size !== false) {
            // Group by size for duplicate detection later
            if (!isset($sizeGroups[$size])) $sizeGroups[$size] = array();
            $sizeGroups[$size][] = $norm;
        }

        $flags = array();
        if ($size !== false && $size > SIZE_THRESHOLD) {
            $flags[] = 'filesize_' . round($size / 1024) . 'KB';
        }
        $info = @getimagesize($abs);
        if ($info !== false) {
            $w = (int)$info[0];
            $h = (int)$info[1];
            if ($w > DIM_MAX || $h > DIM_MAX) $flags[] = 'dims_' . $w . 'x' . $h;
            if ($w < DIM_MIN || $h < DIM_MIN) $flags[] = 'tiny_' . $w  . 'x' . $h;
        }
        if (!empty($flags)) {
            $entry = array(
                'path'    => $norm,
                'size_kb' => $size !== false ? round($size / 1024) : null,
                'dims'    => $info !== false ? $info[0] . 'x' . $info[1] : null,
                'flags'   => $flags,
            );
            if (strpos(implode(',', $flags), 'tiny_') !== false) {
                $undersized[] = $entry;
            } else {
                $oversized[] = $entry;
            }
        }
    }

    if ($totalFiles % 10000 === 0) {
        out("    scanned {$totalFiles}...");
    }
}

out("  Total files: {$totalFiles}  (" . sec($t) . "s)");

// ─── 3. Broken references ────────────────────────────────────────────────────

out("[3/5] Finding broken DB references...");
$t = microtime(true);

$broken = array();
foreach ($dbRefs as $norm => $dummy) {
    if (!isset($seenOnDisk[$norm])) {
        $broken[] = $norm;
    }
}
unset($seenOnDisk); // free memory

out("  Orphans: " . count($orphans) . "  (use --delete-orphans to remove)");
out("  Broken:  " . count($broken) . "  (" . sec($t) . "s)");

// ─── 4. Duplicates (MD5 of same-size files) ──────────────────────────────────

out("[4/5] Finding duplicates (referenced files, same-size pre-filter)...");
$t = microtime(true);

// Only hash groups with >1 file (same filesize = candidates)
$dupGroups = array();
$hashMap   = array(); // md5 => [normPath, ...]
$hashed    = 0;

foreach ($sizeGroups as $size => $paths) {
    if (count($paths) < 2) continue; // unique size → can't be duplicate
    foreach ($paths as $norm) {
        $abs = IMAGE_ROOT . $norm;
        $md5 = @md5_file($abs);
        if ($md5 === false) continue;
        if (!isset($hashMap[$md5])) $hashMap[$md5] = array();
        $hashMap[$md5][] = $norm;
        $hashed++;
    }
}

foreach ($hashMap as $md5 => $paths) {
    if (count($paths) > 1) {
        $dupGroups[] = array(
            'md5'   => $md5,
            'count' => count($paths),
            'files' => $paths,
        );
    }
}
unset($sizeGroups, $hashMap);

usort($dupGroups, function($a, $b) { return $b['count'] - $a['count']; });

out("  Duplicate groups: " . count($dupGroups) . "  (hashed {$hashed} candidates)  (" . sec($t) . "s)");
out("  Oversized:  " . count($oversized));
out("  Undersized: " . count($undersized));

// ─── 5. Delete orphans (if requested) ────────────────────────────────────────

$deleted      = 0;
$deleteErrors = array();
if ($deleteOrphans && !empty($orphans)) {
    out("");
    out("[--delete-orphans] Deleting " . count($orphans) . " orphan files...");
    foreach ($orphans as $norm => $abs) {
        if (@unlink($abs)) {
            $deleted++;
        } else {
            $deleteErrors[] = $norm;
        }
        if ($deleted % 5000 === 0 && $deleted > 0) {
            out("    deleted {$deleted}...");
        }
    }
    out("  Deleted: {$deleted},  errors: " . count($deleteErrors));
}

// ─── 6. Fix broken DB references (if requested) ──────────────────────────────

$brokenFixed = 0;
if ($fixBroken && !empty($broken)) {
    out("");
    out("[--fix-broken] Removing " . count($broken) . " broken references from DB...");

    // Build escaped IN-lists per DB (batch instead of per-row queries)
    $inPapir = array();
    $inOff   = array();
    $inMff   = array();
    foreach ($broken as $norm) {
        $inPapir[] = "'" . Database::escape('Papir', $norm) . "'";
        $inOff[]   = "'" . Database::escape('off',   $norm) . "'";
        $inMff[]   = "'" . Database::escape('mff',   $norm) . "'";
    }

    // Process in chunks of 500 to avoid too-long SQL
    $chunks = array_chunk($inPapir, 500);
    $chunksOff = array_chunk($inOff, 500);
    $chunksMff = array_chunk($inMff, 500);

    foreach ($chunks as $i => $chunk) {
        $listPapir = implode(',', $chunk);
        $listOff   = implode(',', $chunksOff[$i]);
        $listMff   = implode(',', $chunksMff[$i]);

        $r = Database::query('Papir', "DELETE FROM `image` WHERE `image` IN ({$listPapir})");
        if ($r['ok']) $brokenFixed += (int)$r['affected_rows'];

        Database::query('Papir', "UPDATE `product_papir` SET `image` = NULL WHERE `image` IN ({$listPapir})");

        $r = Database::query('off', "DELETE FROM `oc_product_image` WHERE `image` IN ({$listOff})");
        if ($r['ok']) $brokenFixed += (int)$r['affected_rows'];

        Database::query('off', "UPDATE `oc_product` SET `image` = NULL WHERE `image` IN ({$listOff})");

        $r = Database::query('mff', "DELETE FROM `oc_product_image` WHERE `image` IN ({$listMff})");
        if ($r['ok']) $brokenFixed += (int)$r['affected_rows'];

        Database::query('mff', "UPDATE `oc_product` SET `image` = NULL WHERE `image` IN ({$listMff})");

        out("  chunk " . ($i + 1) . "/" . count($chunks) . " done");
    }

    out("  Fixed: {$brokenFixed} rows removed across Papir/off/mff");
}

// ─── Report ──────────────────────────────────────────────────────────────────

// Sanitize array of strings: replace invalid UTF-8 sequences
function sanitizeStrings($arr) {
    $out = array();
    foreach ($arr as $k => $v) {
        if (is_string($v)) {
            $out[$k] = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
        } elseif (is_array($v)) {
            $out[$k] = sanitizeStrings($v);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// Write report in sections to a temp file, then rename
$tmpFile = REPORT_FILE . '.tmp';
$fh = fopen($tmpFile, 'w');
fwrite($fh, "{\n");
fwrite($fh, '"generated_at":' . json_encode(date('Y-m-d H:i:s')) . ",\n");
fwrite($fh, '"summary":' . json_encode(array(
    'total_files_on_disk' => $totalFiles,
    'total_db_refs'       => $totalDbRefs,
    'orphans'             => count($orphans),
    'broken'              => count($broken),
    'duplicate_groups'    => count($dupGroups),
    'oversized'           => count($oversized),
    'undersized'          => count($undersized),
    'orphans_deleted'     => $deleted,
    'broken_fixed'        => $brokenFixed,
), $flags) . ",\n");
fwrite($fh, '"thresholds":' . json_encode(array(
    'filesize_max_kb' => round(SIZE_THRESHOLD / 1024),
    'dim_max_px'      => DIM_MAX,
    'dim_min_px'      => DIM_MIN,
), $flags) . ",\n");
fwrite($fh, '"orphans":'    . json_encode(sanitizeStrings(array_keys($orphans)), $flags) . ",\n");
fwrite($fh, '"broken":'     . json_encode(sanitizeStrings($broken),     $flags) . ",\n");
fwrite($fh, '"duplicates":' . json_encode(sanitizeStrings($dupGroups),  $flags) . ",\n");
fwrite($fh, '"oversized":'  . json_encode(sanitizeStrings($oversized),  $flags) . ",\n");
fwrite($fh, '"undersized":' . json_encode(sanitizeStrings($undersized), $flags) . ",\n");
fwrite($fh, '"delete_errors":' . json_encode(sanitizeStrings($deleteErrors), $flags) . "\n");
fwrite($fh, "}\n");
fclose($fh);
rename($tmpFile, REPORT_FILE);

out("");
out("=== Summary ===");
out("  Files on disk:     {$totalFiles}");
out("  DB references:     {$totalDbRefs}");
out("  Orphans:           " . count($orphans));
out("  Broken refs:       " . count($broken));
out("  Duplicate groups:  " . count($dupGroups));
out("  Oversized:         " . count($oversized) . "  (>" . round(SIZE_THRESHOLD/1024) . "KB or >" . DIM_MAX . "px)");
out("  Undersized:        " . count($undersized) . "  (<" . DIM_MIN . "px)");
if ($deleteOrphans) out("  Orphans deleted:   {$deleted}");
if ($fixBroken)     out("  Broken fixed:      {$brokenFixed}");
out("");
out("Report saved: " . REPORT_FILE);
out("Finished: " . date('Y-m-d H:i:s'));
