<?php
/**
 * Sync product images to mff server via FTP.
 *
 * Загружает файлы изображений на mff-сервер (menufolder.com.ua).
 * Только для товаров у которых есть запись в product_site (site_id=2).
 *
 * Использование:
 *   php scripts/sync_images_to_mff.php          -- dry-run (показать без загрузки)
 *   php scripts/sync_images_to_mff.php --execute -- загрузить на mff
 */

set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/shared/MffFtpSync.php';

$execute = in_array('--execute', $argv);

function out($msg) { echo $msg . "\n"; flush(); }

out("=== Sync Product Images to mff ===");
out("Mode:    " . ($execute ? "EXECUTE" : "DRY-RUN (pass --execute to upload)"));
out("Started: " . date('Y-m-d H:i:s'));
out("");

// Load image paths only for products that exist on mff (product_site site_id=2)
$r = Database::fetchAll('Papir',
    "SELECT DISTINCT pi.path
     FROM product_image pi
     JOIN product_site ps ON ps.product_id = pi.product_id AND ps.site_id = 2
     WHERE pi.path IS NOT NULL AND pi.path != ''
     ORDER BY pi.path ASC"
);

if (!$r['ok'] || empty($r['rows'])) {
    out("No images found in product_image.");
    exit(0);
}

$paths     = array();
$localBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';
$missing   = 0;

foreach ($r['rows'] as $row) {
    $relPath = ltrim((string)$row['path'], '/');
    if (!file_exists($localBase . $relPath)) {
        $missing++;
        continue;
    }
    $paths[] = $relPath;
}

out("Images for mff products in DB: " . count($r['rows']));
out("Files found on disk:           " . count($paths));
out("Missing on disk:               " . $missing);
out("");

if (empty($paths)) {
    out("Nothing to sync.");
    exit(0);
}

if (!$execute) {
    out("Would upload " . count($paths) . " files to mff.");
    out("Run with --execute to perform the upload.");
    exit(0);
}

out("");

// Connect FTP
$ftp = new MffFtpSync();

$uploaded = 0;
$errors   = 0;

foreach ($paths as $i => $relPath) {
    $ok = $ftp->upload($relPath);
    if ($ok) {
        $uploaded++;
    } else {
        $errors++;
        out("  ERROR: " . $relPath);
    }

    if (($i + 1) % 50 === 0) {
        out("  Processed " . ($i + 1) . "/" . count($paths) . " (uploaded: {$uploaded}, errors: {$errors})");
    }
}

$ftp->disconnect();

out("");
out("=== Done ===");
out("Uploaded: {$uploaded}");
out("Errors:   {$errors}");
out("Finished: " . date('Y-m-d H:i:s'));
