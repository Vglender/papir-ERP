<?php
/**
 * Наповнює Papir.product_seo даними з OpenCart (off + mff):
 *   — seo_url    з oc_url_alias (off) / oc_seo_url (mff)
 *   — meta_title, meta_description, meta_keyword, meta_h1/seo_h1, tag
 *     з oc_product_description
 *
 * site_id=1 (off), site_id=2 (mff)
 * language_id=1 (UK), language_id=2 (RU) — Papir IDs
 *
 * site_languages mapping (Papir):
 *   off: uk→4, ru→1
 *   mff: uk→2, ru→1
 *
 * php scripts/migrate_product_seo.php          — dry-run
 * php scripts/migrate_product_seo.php --apply  — застосувати
 */

set_time_limit(0);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../modules/database/database.php';

$apply = in_array('--apply', $argv);
function out($msg) { echo $msg . "\n"; }
function sec($s)   { return round(microtime(true) - $s, 1); }

out($apply ? "=== APPLY ===" : "=== DRY RUN (pass --apply to save) ===");
out("");

// ─── site_languages mapping ───────────────────────────────────────────────
// off site_id=1: uk(papir lang 1) → off lang 4, ru(papir lang 2) → off lang 1
// mff site_id=2: uk(papir lang 1) → mff lang 2, ru(papir lang 2) → mff lang 1
$siteLangMap = array(
    1 => array(1 => 4, 2 => 1),  // off: papir_lang => site_lang
    2 => array(1 => 2, 2 => 1),  // mff
);

$totalInserted = 0;
$totalUpdated  = 0;
$totalErrors   = 0;

// ═══════════════════════════════════════════════════════════════════════════
// Функція: обробити один сайт
// ═══════════════════════════════════════════════════════════════════════════
function processSite($siteId, $dbAlias, $idField, $urlTable, $langMap, $apply) {
    global $totalInserted, $totalUpdated, $totalErrors;

    out("── site_id={$siteId} ({$dbAlias}) ─────────────────────────────────────────");
    $t = microtime(true);

    // ── 1. Завантажити всі товари Papir з відповідним id на цьому сайті
    $rProds = Database::fetchAll('Papir',
        "SELECT product_id, {$idField} AS site_prod_id
         FROM product_papir
         WHERE {$idField} IS NOT NULL AND {$idField} > 0"
    );
    if (!$rProds['ok']) { out("  ERROR: cannot load products"); return; }

    $prodMap = array(); // site_prod_id => product_id (Papir)
    foreach ($rProds['rows'] as $r) {
        $prodMap[(int)$r['site_prod_id']] = (int)$r['product_id'];
    }
    out("  Products with {$idField}: " . count($prodMap));

    // ── 2. SEO URLs
    $seoUrls = array(); // site_prod_id => keyword
    if ($urlTable === 'oc_url_alias') {
        $rUrls = Database::fetchAll($dbAlias,
            "SELECT REPLACE(query,'product_id=','') AS prod_id, keyword
             FROM oc_url_alias WHERE query LIKE 'product_id=%'"
        );
    } else {
        // oc_seo_url — беремо обидві мови
        $rUrls = Database::fetchAll($dbAlias,
            "SELECT REPLACE(query,'product_id=','') AS prod_id, language_id, keyword
             FROM oc_seo_url WHERE query LIKE 'product_id=%' AND store_id=0"
        );
    }
    if ($rUrls['ok']) {
        if ($urlTable === 'oc_url_alias') {
            foreach ($rUrls['rows'] as $r) {
                $seoUrls[(int)$r['prod_id']] = $r['keyword'];
            }
        } else {
            // mff: зберігаємо per language
            foreach ($rUrls['rows'] as $r) {
                $pid  = (int)$r['prod_id'];
                $lang = (int)$r['language_id'];
                if (!isset($seoUrls[$pid])) $seoUrls[$pid] = array();
                $seoUrls[$pid][$lang] = $r['keyword'];
            }
        }
    }

    // ── 3. product_description (meta + tags)
    $rDesc = Database::fetchAll($dbAlias,
        "SELECT product_id, language_id,
                meta_title, meta_description, meta_keyword,
                " . ($dbAlias === 'off' ? "meta_h1" : "''") . " AS meta_h1,
                tag
         FROM oc_product_description
         WHERE product_id IN (" . implode(',', array_keys($prodMap)) . ")"
    );
    $desc = array(); // site_prod_id => lang => fields
    if ($rDesc['ok']) {
        foreach ($rDesc['rows'] as $r) {
            $pid  = (int)$r['product_id'];
            $lang = (int)$r['language_id'];
            if (!isset($desc[$pid])) $desc[$pid] = array();
            $desc[$pid][$lang] = $r;
        }
    }

    // ── 4. Upsert product_seo per Papir language
    $processed = 0;
    foreach ($langMap as $papirLang => $siteLang) {
        foreach ($prodMap as $siteProdId => $papirProdId) {

            // seo_url
            if ($urlTable === 'oc_url_alias') {
                $seoUrl = isset($seoUrls[$siteProdId]) ? $seoUrls[$siteProdId] : '';
            } else {
                $seoUrl = isset($seoUrls[$siteProdId][$siteLang]) ? $seoUrls[$siteProdId][$siteLang] : '';
            }

            // meta fields
            $d = isset($desc[$siteProdId][$siteLang]) ? $desc[$siteProdId][$siteLang] : array();
            $metaTitle  = isset($d['meta_title'])       ? $d['meta_title']       : '';
            $metaDesc   = isset($d['meta_description']) ? $d['meta_description'] : '';
            $metaKw     = isset($d['meta_keyword'])     ? $d['meta_keyword']     : '';
            $seoH1      = isset($d['meta_h1'])          ? $d['meta_h1']          : '';
            $tag        = isset($d['tag'])               ? $d['tag']               : '';

            // skip if all empty
            if ($seoUrl === '' && $metaTitle === '' && $metaDesc === '' && $metaKw === '' && $seoH1 === '' && $tag === '') {
                continue;
            }

            if (!$apply) { $processed++; continue; }

            $now = date('Y-m-d H:i:s');
            $data = array(
                'product_id'       => $papirProdId,
                'site_id'          => $siteId,
                'language_id'      => $papirLang,
                'seo_url'          => $seoUrl,
                'seo_h1'           => $seoH1,
                'meta_title'       => $metaTitle,
                'meta_description' => $metaDesc,
                'meta_keyword'     => $metaKw,
                'tag'              => $tag,
                'updated_at'       => $now,
            );

            // upsert
            $chk = Database::fetchRow('Papir',
                "SELECT seo_id FROM product_seo
                 WHERE product_id={$papirProdId} AND site_id={$siteId} AND language_id={$papirLang}"
            );
            if (!$chk['ok']) { $totalErrors++; continue; }

            if (!empty($chk['row'])) {
                $r = Database::update('Papir', 'product_seo', $data,
                    array('seo_id' => (int)$chk['row']['seo_id'])
                );
                if ($r['ok']) $totalUpdated++; else $totalErrors++;
            } else {
                $data['created_at'] = $now;
                $r = Database::insert('Papir', 'product_seo', $data);
                if ($r['ok']) $totalInserted++; else $totalErrors++;
            }

            $processed++;
            if ($processed % 200 === 0) out("    processed {$processed}...");
        }
    }

    if (!$apply) out("  Would process: ~{$processed} records");
    out("  Done  (" . sec($t) . "s)");
    out("");
}

// ── off (site_id=1, id_off, oc_url_alias, uk=4/ru=1) ──────────────────────
processSite(1, 'off', 'id_off', 'oc_url_alias', $siteLangMap[1], $apply);

// ── mff (site_id=2, id_mf,  oc_seo_url,   uk=2/ru=1) ──────────────────────
processSite(2, 'mff', 'id_mf',  'oc_seo_url',   $siteLangMap[2], $apply);

out("─────────────────────────────────────────────────────────────────────");
if ($apply) {
    out("Total: inserted={$totalInserted}, updated={$totalUpdated}, errors={$totalErrors}");
    out("Done.");
} else {
    out("Run with --apply to save.");
}
