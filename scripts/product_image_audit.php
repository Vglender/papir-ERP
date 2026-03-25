<?php
/**
 * Product Image Audit
 *
 * Порівнює зображення товарів між Papir.image, off.oc_product_image, mff.oc_product_image.
 * Показує розбіжності: де на сайтах більше фото ніж у Papir.
 *
 * Використання:
 *   php scripts/product_image_audit.php              — аналіз, зберегти звіт
 *   php scripts/product_image_audit.php --sample=20  — вивести N прикладів розбіжностей
 *
 * Звіт: scripts/product_image_audit_results.json
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../modules/database/database.php';

define('REPORT_FILE', __DIR__ . '/product_image_audit_results.json');

$sampleCount = 10;
foreach ($argv as $arg) {
    if (preg_match('/^--sample=(\d+)$/', $arg, $m)) $sampleCount = (int)$m[1];
}

function out($msg) { echo $msg . "\n"; flush(); }
function sec($s)   { return round(microtime(true) - $s, 1); }

out("=== Product Image Audit ===");
out("Started: " . date('Y-m-d H:i:s'));
out("");

// ─── 1. Load Papir main images ────────────────────────────────────────────────

out("[1/5] Loading Papir.product_papir main images...");
$t = microtime(true);

$r = Database::fetchAll('Papir',
    "SELECT product_id, id_off, id_mf, image
     FROM product_papir
     WHERE id_off > 0 OR id_mf > 0"
);

$products = array(); // product_id => [id_off, id_mf, papir_main, papir_extra[], off_main, off_extra[], mff_main, mff_extra[]]
$byIdOff  = array(); // id_off => product_id
$byIdMf   = array(); // id_mf  => product_id

foreach ($r['rows'] as $row) {
    $pid = (int)$row['product_id'];
    $products[$pid] = array(
        'product_id'  => $pid,
        'id_off'      => (int)$row['id_off'],
        'id_mf'       => (int)$row['id_mf'],
        'papir_main'  => trim((string)$row['image']),
        'papir_extra' => array(),
        'off_main'    => '',
        'off_extra'   => array(),
        'mff_main'    => '',
        'mff_extra'   => array(),
    );
    if ((int)$row['id_off'] > 0) $byIdOff[(int)$row['id_off']] = $pid;
    if ((int)$row['id_mf']  > 0) $byIdMf[(int)$row['id_mf']]  = $pid;
}

out("  Products: " . count($products) . "  (" . sec($t) . "s)");

// ─── 2. Load Papir.image extra ────────────────────────────────────────────────

out("[2/5] Loading Papir.image additional photos...");
$t = microtime(true);

$r = Database::fetchAll('Papir',
    "SELECT product_id, image FROM `image`
     WHERE image IS NOT NULL AND image != ''
     ORDER BY sort_order ASC, product_image_id ASC"
);

foreach ($r['rows'] as $row) {
    $pid  = (int)$row['product_id'];
    $path = trim((string)$row['image']);
    if (!isset($products[$pid]) || $path === '') continue;
    // skip if same as main (duplicate)
    if ($path !== $products[$pid]['papir_main']) {
        $products[$pid]['papir_extra'][] = $path;
    }
}

out("  Extra image rows: " . count($r['rows']) . "  (" . sec($t) . "s)");

// ─── 3. Load off images ───────────────────────────────────────────────────────

out("[3/5] Loading off.oc_product images...");
$t = microtime(true);

// Main images
$rMain = Database::fetchAll('off',
    "SELECT product_id, image FROM oc_product
     WHERE image IS NOT NULL AND image != '' AND product_id > 0"
);
foreach ($rMain['rows'] as $row) {
    $idOff = (int)$row['product_id'];
    if (!isset($byIdOff[$idOff])) continue;
    $products[$byIdOff[$idOff]]['off_main'] = trim((string)$row['image']);
}

// Extra images
$rExtra = Database::fetchAll('off',
    "SELECT product_id, image FROM oc_product_image
     WHERE image IS NOT NULL AND image != ''
     ORDER BY sort_order ASC, product_image_id ASC"
);
foreach ($rExtra['rows'] as $row) {
    $idOff = (int)$row['product_id'];
    if (!isset($byIdOff[$idOff])) continue;
    $pid  = $byIdOff[$idOff];
    $path = trim((string)$row['image']);
    if ($path !== '' && $path !== $products[$pid]['off_main']) {
        $products[$pid]['off_extra'][] = $path;
    }
}

out("  off main: " . count($rMain['rows']) . ", extra: " . count($rExtra['rows']) . "  (" . sec($t) . "s)");

// ─── 4. Load mff images ───────────────────────────────────────────────────────

out("[4/5] Loading mff.oc_product images...");
$t = microtime(true);

$rMain = Database::fetchAll('mff',
    "SELECT product_id, image FROM oc_product
     WHERE image IS NOT NULL AND image != '' AND product_id > 0"
);
foreach ($rMain['rows'] as $row) {
    $idMf = (int)$row['product_id'];
    if (!isset($byIdMf[$idMf])) continue;
    $products[$byIdMf[$idMf]]['mff_main'] = trim((string)$row['image']);
}

$rExtra = Database::fetchAll('mff',
    "SELECT product_id, image FROM oc_product_image
     WHERE image IS NOT NULL AND image != ''
     ORDER BY sort_order ASC, product_image_id ASC"
);
foreach ($rExtra['rows'] as $row) {
    $idMf = (int)$row['product_id'];
    if (!isset($byIdMf[$idMf])) continue;
    $pid  = $byIdMf[$idMf];
    $path = trim((string)$row['image']);
    if ($path !== '' && $path !== $products[$pid]['mff_main']) {
        $products[$pid]['mff_extra'][] = $path;
    }
}

out("  mff main: " . count($rMain['rows']) . ", extra: " . count($rExtra['rows']) . "  (" . sec($t) . "s)");

// ─── 5. Analyse discrepancies ─────────────────────────────────────────────────

out("[5/5] Analysing discrepancies...");
$t = microtime(true);

$stats = array(
    'total_products'            => count($products),
    'papir_has_main'            => 0,
    'off_has_main'              => 0,
    'mff_has_main'              => 0,
    // off vs papir
    'off_more_than_papir'       => 0,  // off total > papir total
    'off_has_exclusive'         => 0,  // off has paths not in papir at all
    'papir_has_exclusive'       => 0,  // papir has paths not in off at all
    // mff vs papir
    'mff_more_than_papir'       => 0,
    'mff_has_exclusive'         => 0,
    'papir_mff_exclusive'       => 0,
    // general
    'in_sync'                   => 0,  // all 3 identical
    'no_images_anywhere'        => 0,
);

$discrepancies = array(); // for report + sample output

foreach ($products as $pid => $p) {
    $papirAll = array_unique(array_filter(array_merge(
        $p['papir_main'] !== '' ? array($p['papir_main']) : array(),
        $p['papir_extra']
    )));
    $offAll   = array_unique(array_filter(array_merge(
        $p['off_main'] !== '' ? array($p['off_main']) : array(),
        $p['off_extra']
    )));
    $mffAll   = array_unique(array_filter(array_merge(
        $p['mff_main'] !== '' ? array($p['mff_main']) : array(),
        $p['mff_extra']
    )));

    if ($p['papir_main'] !== '') $stats['papir_has_main']++;
    if ($p['off_main']   !== '') $stats['off_has_main']++;
    if ($p['mff_main']   !== '') $stats['mff_has_main']++;

    if (empty($papirAll) && empty($offAll) && empty($mffAll)) {
        $stats['no_images_anywhere']++;
        continue;
    }

    $papirSet = array_flip($papirAll);
    $offSet   = array_flip($offAll);
    $mffSet   = array_flip($mffAll);

    // off vs papir
    $offExclusive   = array_values(array_diff($offAll,   $papirAll)); // in off, not in papir
    $papirExcOff    = array_values(array_diff($papirAll, $offAll));   // in papir, not in off

    // mff vs papir
    $mffExclusive   = array_values(array_diff($mffAll,   $papirAll));
    $papirExcMff    = array_values(array_diff($papirAll, $mffAll));

    if (count($offAll)  > count($papirAll)) $stats['off_more_than_papir']++;
    if (count($mffAll)  > count($papirAll)) $stats['mff_more_than_papir']++;
    if (!empty($offExclusive))  $stats['off_has_exclusive']++;
    if (!empty($papirExcOff))   $stats['papir_has_exclusive']++;
    if (!empty($mffExclusive))  $stats['mff_has_exclusive']++;
    if (!empty($papirExcMff))   $stats['papir_mff_exclusive']++;

    $inSync = empty($offExclusive) && empty($papirExcOff)
           && empty($mffExclusive) && empty($papirExcMff)
           && count($papirAll) === count($offAll)
           && count($papirAll) === count($mffAll);
    if ($inSync) {
        $stats['in_sync']++;
    } else {
        $discrepancies[] = array(
            'product_id'      => $pid,
            'id_off'          => $p['id_off'],
            'id_mf'           => $p['id_mf'],
            'papir_count'     => count($papirAll),
            'off_count'       => count($offAll),
            'mff_count'       => count($mffAll),
            'off_exclusive'   => $offExclusive,   // в off но не в Papir
            'papir_excl_off'  => $papirExcOff,    // в Papir но не в off
            'mff_exclusive'   => $mffExclusive,   // в mff но не в Papir
            'papir_excl_mff'  => $papirExcMff,    // в Papir но не в mff
        );
    }
}

$stats['out_of_sync'] = count($discrepancies);

out("  Done  (" . sec($t) . "s)");
out("");

// ─── Summary ──────────────────────────────────────────────────────────────────

out("=== Summary ===");
out("  Total products:                {$stats['total_products']}");
out("  No images anywhere:            {$stats['no_images_anywhere']}");
out("  In sync (all 3 match):         {$stats['in_sync']}");
out("  Out of sync:                   {$stats['out_of_sync']}");
out("");
out("  Has main image — Papir:        {$stats['papir_has_main']}");
out("  Has main image — off:          {$stats['off_has_main']}");
out("  Has main image — mff:          {$stats['mff_has_main']}");
out("");
out("  off has MORE photos than Papir: {$stats['off_more_than_papir']}");
out("  off has EXCLUSIVE paths:        {$stats['off_has_exclusive']}  (photos not in Papir at all)");
out("  Papir has paths not in off:     {$stats['papir_has_exclusive']}");
out("");
out("  mff has MORE photos than Papir: {$stats['mff_more_than_papir']}");
out("  mff has EXCLUSIVE paths:        {$stats['mff_has_exclusive']}  (photos not in Papir at all)");
out("  Papir has paths not in mff:     {$stats['papir_mff_exclusive']}");

// ─── Sample discrepancies ─────────────────────────────────────────────────────

if ($sampleCount > 0 && !empty($discrepancies)) {
    out("");
    out("=== Sample discrepancies (first {$sampleCount}) ===");
    // Sort by most exclusive off paths first to show interesting cases
    usort($discrepancies, function($a, $b) {
        return count($b['off_exclusive']) - count($a['off_exclusive']);
    });
    $shown = array_slice($discrepancies, 0, $sampleCount);
    foreach ($shown as $d) {
        out("  product_id={$d['product_id']} id_off={$d['id_off']}");
        out("    Papir:{$d['papir_count']}  off:{$d['off_count']}  mff:{$d['mff_count']}");
        if (!empty($d['off_exclusive'])) {
            out("    off EXCLUSIVE (" . count($d['off_exclusive']) . "):");
            foreach (array_slice($d['off_exclusive'], 0, 3) as $path) out("      + " . $path);
        }
        if (!empty($d['papir_excl_off'])) {
            out("    Papir not in off (" . count($d['papir_excl_off']) . "):");
            foreach (array_slice($d['papir_excl_off'], 0, 3) as $path) out("      - " . $path);
        }
        out("    https://papir.officetorg.com.ua/catalog?selected={$d['id_off']}");
    }
}

// ─── Save report ──────────────────────────────────────────────────────────────

out("");
out("Saving report...");

$flags   = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$tmpFile = REPORT_FILE . '.tmp';
$fh      = fopen($tmpFile, 'w');
fwrite($fh, "{\n");
fwrite($fh, '"generated_at":' . json_encode(date('Y-m-d H:i:s')) . ",\n");
fwrite($fh, '"summary":'      . json_encode($stats,           $flags) . ",\n");
fwrite($fh, '"discrepancies":' . json_encode($discrepancies,  $flags) . "\n");
fwrite($fh, "}\n");
fclose($fh);
rename($tmpFile, REPORT_FILE);

out("Report saved: " . REPORT_FILE);
out("Finished: " . date('Y-m-d H:i:s'));
