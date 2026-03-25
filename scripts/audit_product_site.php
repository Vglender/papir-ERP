<?php
/**
 * Product-Site Audit
 *
 * Находит:
 *   1. Товары на сайтах (off/mff) которых НЕТ в product_site → "orphans"
 *      — пытается найти совпадение в Papir по model/article
 *   2. Записи в product_site (и product_papir.id_off/id_mf) для товаров
 *      которых уже НЕТ на сайте → "stale"
 *
 * Использование:
 *   php scripts/audit_product_site.php              -- аудит, сохранить отчёт
 *   php scripts/audit_product_site.php --import-mff -- также занести mff-сироты в Papir
 *   php scripts/audit_product_site.php --import-off -- также занести off-сироты в Papir
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../modules/database/database.php';

$importMff = in_array('--import-mff', $argv);
$importOff = in_array('--import-off', $argv);

define('REPORT_FILE', __DIR__ . '/audit_product_site_results.json');

function out($msg) { echo $msg . "\n"; flush(); }

out("=== Product-Site Audit ===");
out("Started: " . date('Y-m-d H:i:s'));
out("");

// ── 1. Load Papir products (for matching orphans) ────────────────────────────

out("[1/5] Loading Papir catalog...");

$r = Database::fetchAll('Papir',
    "SELECT pp.product_id, pp.id_off, pp.id_mf, pp.product_article,
            pd.name
     FROM product_papir pp
     LEFT JOIN product_description pd ON pd.product_id=pp.product_id AND pd.language_id=1"
);

$papirById     = array(); // product_id   => row
$papirByIdOff  = array(); // id_off        => product_id
$papirByIdMf   = array(); // id_mf         => product_id
$papirByModel  = array(); // model(id_off as string) => product_id

foreach ($r['rows'] as $row) {
    $pid = (int)$row['product_id'];
    $papirById[$pid]   = $row;
    if ((int)$row['id_off'] > 0) {
        $papirByIdOff[(int)$row['id_off']] = $pid;
        $papirByModel[(string)(int)$row['id_off']] = $pid;
    }
    if ((int)$row['id_mf']  > 0) $papirByIdMf[(int)$row['id_mf']]  = $pid;

    // Also index by article
    $art = trim((string)$row['product_article']);
    if ($art !== '') $papirByModel[strtolower($art)] = $pid;
}
out("  Papir products: " . count($papirById));

// ── 2. Load product_site ─────────────────────────────────────────────────────

out("[2/5] Loading product_site...");

$psOff = array(); // site_product_id => true
$psMff = array();

$rps = Database::fetchAll('Papir', "SELECT site_id, site_product_id FROM product_site");
foreach ($rps['rows'] as $row) {
    if ((int)$row['site_id'] === 1) $psOff[(int)$row['site_product_id']] = true;
    if ((int)$row['site_id'] === 2) $psMff[(int)$row['site_product_id']] = true;
}
out("  product_site off: " . count($psOff) . ", mff: " . count($psMff));

// ── 3. Audit off ─────────────────────────────────────────────────────────────

out("[3/5] Auditing off (officetorg.com.ua)...");

$rOff = Database::fetchAll('off',
    "SELECT p.product_id, p.model, p.status, pd.name
     FROM oc_product p
     LEFT JOIN oc_product_description pd ON pd.product_id=p.product_id AND pd.language_id=1
     WHERE p.status=1
     ORDER BY p.product_id"
);

$orphansOff = array(); // active off products not in product_site
$staleOff   = array(); // product_site off entries not in off.oc_product

foreach ($rOff['rows'] as $row) {
    $offId = (int)$row['product_id'];
    if (isset($psOff[$offId])) continue; // already in product_site

    // Try to match in Papir
    $model     = trim((string)$row['model']);
    $papirMatch = null;
    if (isset($papirByIdOff[$offId]))                   $papirMatch = $papirByIdOff[$offId];
    elseif ($model !== '' && isset($papirByModel[$model])) $papirMatch = $papirByModel[$model];
    elseif ($model !== '' && isset($papirByModel[strtolower($model)])) $papirMatch = $papirByModel[strtolower($model)];

    $orphansOff[] = array(
        'site_product_id' => $offId,
        'model'           => $model,
        'name'            => (string)$row['name'],
        'papir_match'     => $papirMatch,
    );
}

// Stale: product_site has off products that don't exist in off anymore
$offAllIds = array();
$rOffAll = Database::fetchAll('off', "SELECT product_id FROM oc_product");
foreach ($rOffAll['rows'] as $row) $offAllIds[(int)$row['product_id']] = true;
foreach (array_keys($psOff) as $offId) {
    if (!isset($offAllIds[$offId])) {
        $staleOff[] = $offId;
    }
}

out("  Orphan active off products (not in product_site): " . count($orphansOff));
out("  Stale product_site off entries:                   " . count($staleOff));

// ── 4. Audit mff ─────────────────────────────────────────────────────────────

out("[4/5] Auditing mff (menufolder.com.ua)...");

$rMff = Database::fetchAll('mff',
    "SELECT p.product_id, p.model, p.status, pd.name
     FROM oc_product p
     LEFT JOIN oc_product_description pd ON pd.product_id=p.product_id AND pd.language_id=1
     WHERE p.status=1
     ORDER BY p.product_id"
);

$orphansMff = array();
$staleMff   = array();

foreach ($rMff['rows'] as $row) {
    $mffId = (int)$row['product_id'];
    if (isset($psMff[$mffId])) continue;

    $model      = trim((string)$row['model']);
    $papirMatch = null;
    if (isset($papirByIdMf[$mffId])) $papirMatch = $papirByIdMf[$mffId];
    elseif ($model !== '') {
        // model on mff is typically id_off (the off product_id as string)
        if (isset($papirByModel[$model])) $papirMatch = $papirByModel[$model];
        if (!$papirMatch && isset($papirByModel[strtolower($model)])) $papirMatch = $papirByModel[strtolower($model)];
    }

    $orphansMff[] = array(
        'site_product_id' => $mffId,
        'model'           => $model,
        'name'            => (string)$row['name'],
        'papir_match'     => $papirMatch,
    );
}

$mffAllIds = array();
$rMffAll = Database::fetchAll('mff', "SELECT product_id FROM oc_product");
foreach ($rMffAll['rows'] as $row) $mffAllIds[(int)$row['product_id']] = true;
foreach (array_keys($psMff) as $mffId) {
    if (!isset($mffAllIds[$mffId])) {
        $staleMff[] = $mffId;
    }
}

out("  Orphan active mff products (not in product_site): " . count($orphansMff));
out("  Stale product_site mff entries:                   " . count($staleMff));

// ── 5. Summary ───────────────────────────────────────────────────────────────

out("[5/5] Summary...");

$offMatchable    = count(array_filter($orphansOff, function($o) { return $o['papir_match'] !== null; }));
$offNotMatchable = count($orphansOff) - $offMatchable;
$mffMatchable    = count(array_filter($orphansMff, function($o) { return $o['papir_match'] !== null; }));
$mffNotMatchable = count($orphansMff) - $mffMatchable;

out("");
out("=== Results ===");
out("");
out("OFF orphans: " . count($orphansOff));
out("  → can match to existing Papir product: {$offMatchable}");
out("  → need new Papir entry:                {$offNotMatchable}");
out("  Stale (in product_site but gone from off): " . count($staleOff));
out("");
out("MFF orphans: " . count($orphansMff));
out("  → can match to existing Papir product: {$mffMatchable}");
out("  → need new Papir entry:                {$mffNotMatchable}");
out("  Stale (in product_site but gone from mff): " . count($staleMff));

// Sample mff orphans for review
if (!empty($orphansMff)) {
    out("");
    out("MFF orphans (first 20):");
    foreach (array_slice($orphansMff, 0, 20) as $o) {
        $match = $o['papir_match'] ? " → Papir #{$o['papir_match']}" : " [NO MATCH]";
        out("  mff#{$o['site_product_id']} model={$o['model']} | {$o['name']}{$match}");
    }
}

// ── 6. Import mff orphans into Papir (if requested) ──────────────────────────

$importedMff = 0;
if ($importMff && !empty($orphansMff)) {
    out("");
    out("[--import-mff] Importing " . count($orphansMff) . " mff orphans into Papir...");

    // Get next available product_id
    $maxPidRes = Database::fetchRow('Papir', "SELECT MAX(product_id) AS m FROM product_papir");
    $nextPid   = (int)$maxPidRes['row']['m'] + 1;

    foreach ($orphansMff as $o) {
        $mffId  = (int)$o['site_product_id'];
        $model  = Database::escape('Papir', $o['model']);

        if ($o['papir_match'] !== null) {
            // Already in Papir — just add product_site entry
            $pid = (int)$o['papir_match'];
            Database::query('Papir',
                "INSERT IGNORE INTO product_site (product_id, site_id, site_product_id, status)
                 VALUES ({$pid}, 2, {$mffId}, 1)"
            );
            // Also update product_papir.id_mf if missing
            Database::query('Papir',
                "UPDATE product_papir SET id_mf={$mffId} WHERE product_id={$pid} AND (id_mf IS NULL OR id_mf=0)"
            );
            $importedMff++;
        } else {
            // Not in Papir — create minimal product_papir entry with explicit product_id
            $safeName = Database::escape('Papir', $o['name']);
            $pid      = $nextPid++;
            $ins = Database::query('Papir',
                "INSERT INTO product_papir (product_id, id_mf, product_article, status)
                 VALUES ({$pid}, {$mffId}, '{$model}', 1)"
            );
            if ($ins['ok']) {
                // Save name in product_description
                if ($safeName !== '') {
                    Database::query('Papir',
                        "INSERT INTO product_description (product_id, language_id, name)
                         VALUES ({$pid}, 1, '{$safeName}')
                         ON DUPLICATE KEY UPDATE name='{$safeName}'"
                    );
                }
                Database::query('Papir',
                    "INSERT IGNORE INTO product_site (product_id, site_id, site_product_id, status)
                     VALUES ({$pid}, 2, {$mffId}, 1)"
                );
                $importedMff++;
            } else {
                out("  ERROR creating Papir entry for mff#{$mffId}: " . $o['name']);
            }
        }
    }
    out("  Imported: {$importedMff}");
}

// ── Save report ──────────────────────────────────────────────────────────────

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$fh    = fopen(REPORT_FILE . '.tmp', 'w');
fwrite($fh, json_encode(array(
    'generated_at'     => date('Y-m-d H:i:s'),
    'summary' => array(
        'off_orphans'         => count($orphansOff),
        'off_matchable'       => $offMatchable,
        'off_not_matchable'   => $offNotMatchable,
        'off_stale'           => count($staleOff),
        'mff_orphans'         => count($orphansMff),
        'mff_matchable'       => $mffMatchable,
        'mff_not_matchable'   => $mffNotMatchable,
        'mff_stale'           => count($staleMff),
        'imported_mff'        => $importedMff,
    ),
    'mff_orphans' => $orphansMff,
    'off_orphans' => array_slice($orphansOff, 0, 500), // first 500 to keep file manageable
    'off_stale'   => $staleOff,
    'mff_stale'   => $staleMff,
), $flags));
fclose($fh);
rename(REPORT_FILE . '.tmp', REPORT_FILE);

out("");
out("Report: " . REPORT_FILE);
out("Finished: " . date('Y-m-d H:i:s'));
