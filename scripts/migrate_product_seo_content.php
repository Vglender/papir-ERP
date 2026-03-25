<?php
/**
 * Заповнює product_seo.name / short_description / description
 * з oc_product_description відповідних сайтів.
 *
 * site_id=1 (off): language mapping uk(1)→4, ru(2)→1
 * site_id=2 (mff): language mapping uk(1)→2, ru(2)→1
 *
 * php scripts/migrate_product_seo_content.php          — dry-run
 * php scripts/migrate_product_seo_content.php --apply  — застосувати
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../modules/database/database.php';

$apply = in_array('--apply', $argv);
function out($msg) { echo $msg . "\n"; }

out($apply ? "=== APPLY ===" : "=== DRY RUN ===");
out("");

$sites = array(
    array('site_id' => 1, 'db' => 'off', 'id_field' => 'id_off', 'lang_map' => array(1 => 4, 2 => 1)),
    array('site_id' => 2, 'db' => 'mff', 'id_field' => 'id_mf',  'lang_map' => array(1 => 2, 2 => 1)),
);

$totalUpdated = 0;
$totalErrors  = 0;

foreach ($sites as $site) {
    $siteId   = $site['site_id'];
    $db       = $site['db'];
    $idField  = $site['id_field'];
    $langMap  = $site['lang_map'];

    out("── site_id={$siteId} ({$db}) ─────────────────────────────────────────");
    $t = microtime(true);

    // Папір: product_id → site_prod_id
    $rProds = Database::fetchAll('Papir',
        "SELECT product_id, {$idField} AS site_prod_id
         FROM product_papir WHERE {$idField} IS NOT NULL AND {$idField} > 0"
    );
    if (!$rProds['ok']) { out("  ERROR"); continue; }
    $prodMap = array(); // site_prod_id => product_id
    foreach ($rProds['rows'] as $r) {
        $prodMap[(int)$r['site_prod_id']] = (int)$r['product_id'];
    }

    // oc_product_description
    $rDesc = Database::fetchAll($db,
        "SELECT product_id, language_id, name, description, tag AS short_description
         FROM oc_product_description
         WHERE product_id IN (" . implode(',', array_keys($prodMap)) . ")"
    );

    // off має окреме поле short_description?
    $hasShortDesc = false;
    if ($db === 'off') {
        $chk = Database::fetchRow($db, "SHOW COLUMNS FROM oc_product_description LIKE 'short_description'");
        $hasShortDesc = ($chk['ok'] && !empty($chk['row']));
    } elseif ($db === 'mff') {
        $chk = Database::fetchRow($db, "SHOW COLUMNS FROM oc_product_description LIKE 'short_description'");
        $hasShortDesc = ($chk['ok'] && !empty($chk['row']));
    }

    if ($hasShortDesc) {
        $rDesc = Database::fetchAll($db,
            "SELECT product_id, language_id, name, description, short_description
             FROM oc_product_description
             WHERE product_id IN (" . implode(',', array_keys($prodMap)) . ")"
        );
    }

    // index: site_prod_id => site_lang_id => fields
    $descIdx = array();
    if ($rDesc['ok']) {
        foreach ($rDesc['rows'] as $r) {
            $pid  = (int)$r['product_id'];
            $lang = (int)$r['language_id'];
            $descIdx[$pid][$lang] = $r;
        }
    }

    $processed = 0;
    foreach ($langMap as $papirLang => $siteLang) {
        foreach ($prodMap as $siteProdId => $papirProdId) {
            $d = isset($descIdx[$siteProdId][$siteLang]) ? $descIdx[$siteProdId][$siteLang] : null;
            if (!$d) continue;

            $name       = isset($d['name'])              ? (string)$d['name']              : '';
            $desc       = isset($d['description'])       ? (string)$d['description']       : '';
            $shortDesc  = isset($d['short_description']) ? (string)$d['short_description'] : '';

            if ($name === '' && $desc === '' && $shortDesc === '') continue;

            $processed++;
            if (!$apply) continue;

            $r = Database::update('Papir', 'product_seo',
                array(
                    'name'              => $name,
                    'description'       => $desc,
                    'short_description' => $shortDesc,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ),
                array('product_id' => $papirProdId, 'site_id' => $siteId, 'language_id' => $papirLang)
            );
            if ($r['ok']) $totalUpdated++; else $totalErrors++;
        }
    }

    $elapsed = round(microtime(true) - $t, 1);
    out("  " . ($apply ? "Updated" : "Would update") . ": {$processed}  ({$elapsed}s)");
}

out("");
out($apply
    ? "Done. updated={$totalUpdated}, errors={$totalErrors}"
    : "Run with --apply to save.");
