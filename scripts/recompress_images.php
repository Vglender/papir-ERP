<?php
/**
 * Recompress Oversized Images
 *
 * Обробляє файли з image_audit_results.json (секція oversized):
 *   - Resize до max 1200px (якщо більше)
 *   - Зберігає як JPEG 85%
 *   - Пропускає якщо результат більший за оригінал (напр. PNG лінійна графіка)
 *   - Замінює файл на місці (оригінальне ім'я)
 *
 * Використання:
 *   php scripts/recompress_images.php            — обробити всі oversized
 *   php scripts/recompress_images.php --dry-run  — показати статистику без змін
 *
 * Після завершення: запустіть image_audit.php щоб оновити звіт
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

define('IMAGE_ROOT', '/var/www/menufold/data/www/officetorg.com.ua/image/');
define('REPORT_FILE', __DIR__ . '/image_audit_results.json');
define('MAX_DIM',     1200);
define('JPEG_QUALITY', 85);

$dryRun = in_array('--dry-run', $argv);

function out($msg) { echo $msg . "\n"; flush(); }

if (!file_exists(REPORT_FILE)) {
    out("ERROR: Run image_audit.php first to generate " . REPORT_FILE);
    exit(1);
}

$data      = json_decode(file_get_contents(REPORT_FILE), true);
$oversized = $data['oversized'];

out("=== Recompress Oversized Images ===");
out("Started:  " . date('Y-m-d H:i:s'));
out("Mode:     " . ($dryRun ? 'DRY RUN (no changes)' : 'LIVE'));
out("Files:    " . count($oversized));
out("");

$processed  = 0;
$skipped    = 0;  // output would be larger
$errors     = 0;
$savedBytes = 0;
$total      = count($oversized);

foreach ($oversized as $i => $f) {
    $abs = IMAGE_ROOT . $f['path'];
    if (!file_exists($abs)) {
        $errors++;
        continue;
    }

    $origSize = filesize($abs);
    $info     = @getimagesize($abs);
    if (!$info) { $errors++; continue; }

    $mime  = $info['mime'];
    $origW = (int)$info[0];
    $origH = (int)$info[1];

    // Load source
    $src = null;
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($abs); break;
        case 'image/png':  $src = @imagecreatefrompng($abs);  break;
        case 'image/webp': $src = @imagecreatefromwebp($abs); break;
        case 'image/gif':  $src = @imagecreatefromgif($abs);  break;
    }
    if (!$src) { $errors++; continue; }

    // Resize if needed
    $newW = $origW;
    $newH = $origH;
    if ($origW > MAX_DIM || $origH > MAX_DIM) {
        if ($origW >= $origH) {
            $newW = MAX_DIM;
            $newH = (int)round($origH * MAX_DIM / $origW);
        } else {
            $newH = MAX_DIM;
            $newW = (int)round($origW * MAX_DIM / $origH);
        }
        $dst = imagecreatetruecolor($newW, $newH);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);
        $src = $dst;
    }

    // Measure output size before writing
    ob_start();
    imagejpeg($src, null, JPEG_QUALITY);
    $newData = ob_get_clean();
    $newSize = strlen($newData);

    if ($newSize >= $origSize) {
        // Output would be larger or equal — skip
        imagedestroy($src);
        $skipped++;
        continue;
    }

    $saved = $origSize - $newSize;
    $savedBytes += $saved;

    if (!$dryRun) {
        // Rename original extension to .jpg if needed (PNG→JPG)
        $newAbs = preg_replace('/\.(png|webp|gif)$/i', '.jpg', $abs);
        file_put_contents($newAbs, $newData);
        // If extension changed, remove old file
        if ($newAbs !== $abs) {
            @unlink($abs);
        }
    }

    imagedestroy($src);
    $processed++;

    if (($i + 1) % 500 === 0) {
        out(sprintf("  [%d/%d] processed=%d skipped=%d errors=%d saved=%.1fMB",
            $i+1, $total, $processed, $skipped, $errors,
            $savedBytes / 1024 / 1024));
    }
}

$savedMB = round($savedBytes / 1024 / 1024, 1);

out("");
out("=== Results ===");
out("  Processed (reduced):  {$processed}");
out("  Skipped (no benefit): {$skipped}");
out("  Errors:               {$errors}");
out("  Space saved:          {$savedMB} MB");
if ($dryRun) out("  (DRY RUN — no files changed)");
out("Finished: " . date('Y-m-d H:i:s'));
