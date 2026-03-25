<?php
/**
 * Генерує Ukrainian SEO URL для всіх категорій і товарів mff:
 *
 * Категорії:
 *   — джерело: off Ukrainian slug (з Papir.category_seo де site=off, lang=UK)
 *   — fallback: Russian slug з mff.oc_seo_url (language_id=1)
 *   — зберігає в: mff.oc_seo_url (lang=2) + Papir.category_seo (site=2, lang=1)
 *
 * Товари:
 *   — джерело: off Ukrainian slug (з off.oc_url_alias для товарів зі спільним id_off)
 *   — fallback: Russian slug з mff.oc_seo_url (language_id=1)
 *   — зберігає тільки в: mff.oc_seo_url (lang=2)
 *
 * Використання:
 *   php scripts/migrate_mff_uk_seo_all.php          — dry-run
 *   php scripts/migrate_mff_uk_seo_all.php --apply  — застосувати
 */

set_time_limit(0);
ini_set('memory_limit', '256M');
require_once __DIR__ . '/../modules/database/database.php';

$apply   = in_array('--apply', $argv);
$verbose = in_array('--verbose', $argv);

function out($msg) { echo $msg . "\n"; }
function sec($s)   { return round(microtime(true) - $s, 1); }

out($apply ? "=== APPLY ===" : "=== DRY RUN (pass --apply to save) ===");
out("");

// ─── helpers ─────────────────────────────────────────────────────────────────

function upsertMffSeoUrl($query, $keyword, $apply) {
    $escaped  = Database::escape('mff', $keyword);
    $escapedQ = Database::escape('mff', $query);

    // check existing
    $chk = Database::fetchRow('mff',
        "SELECT seo_url_id FROM oc_seo_url
         WHERE store_id=0 AND language_id=2 AND query='{$escapedQ}'"
    );
    if (!$chk['ok']) return false;

    if (!$apply) return true;

    if (!empty($chk['row'])) {
        $id = (int)$chk['row']['seo_url_id'];
        $r  = Database::query('mff',
            "UPDATE oc_seo_url SET keyword='{$escaped}' WHERE seo_url_id={$id}"
        );
    } else {
        // ensure keyword unique in store
        $kchk = Database::fetchRow('mff',
            "SELECT seo_url_id FROM oc_seo_url WHERE store_id=0 AND keyword='{$escaped}' LIMIT 1"
        );
        if (!empty($kchk['row'])) {
            // keyword already taken — append suffix
            $escaped = Database::escape('mff', $keyword . '-uk');
        }
        $r = Database::query('mff',
            "INSERT INTO oc_seo_url (store_id, language_id, query, keyword)
             VALUES (0, 2, '{$escapedQ}', '{$escaped}')"
        );
    }
    return $r['ok'];
}

function upsertPapirCategorySeo($categoryId, $seoUrl, $apply) {
    // site_id=2 (mff), language_id=1 (UK in Papir)
    $chk = Database::fetchRow('Papir',
        "SELECT seo_id FROM category_seo
         WHERE category_id={$categoryId} AND site_id=2 AND language_id=1"
    );
    if (!$chk['ok']) return false;
    if (!$apply) return true;

    $escaped = Database::escape('Papir', $seoUrl);
    $now     = date('Y-m-d H:i:s');

    if (!empty($chk['row'])) {
        $id = (int)$chk['row']['seo_id'];
        $r  = Database::update('Papir', 'category_seo',
            array('seo_url' => $seoUrl, 'updated_at' => $now),
            array('seo_id'  => $id)
        );
    } else {
        $r = Database::insert('Papir', 'category_seo', array(
            'category_id' => $categoryId,
            'site_id'     => 2,
            'language_id' => 1,
            'seo_url'     => $seoUrl,
            'created_at'  => $now,
            'updated_at'  => $now,
        ));
    }
    return $r['ok'];
}

// ═══════════════════════════════════════════════════════════════════════════
// 1. КАТЕГОРІЇ
// ═══════════════════════════════════════════════════════════════════════════
out("── CATEGORIES ──────────────────────────────────────────────────────────");
$t = microtime(true);

// Всі активні mff категорії з їх RU slug та, якщо є, UK off slug
$sql = "
    SELECT
        csm.site_category_id          AS mff_cat_id,
        csm.category_id               AS papir_cat_id,
        su_ru.keyword                 AS ru_slug,
        su_uk.keyword                 AS existing_uk_slug,
        cs_off.seo_url                AS off_uk_slug
    FROM category_site_mapping csm
    JOIN sites s ON s.site_id = csm.site_id AND s.code = 'mff'
    -- existing mff UK slug
    LEFT JOIN (
        SELECT store_id, language_id, query, keyword
        FROM oc_seo_url@mff_placeholder
    ) su_uk ON su_uk.query = CONCAT('category_id=', csm.site_category_id)
           AND su_uk.language_id = 2 AND su_uk.store_id = 0
    -- existing mff RU slug
    LEFT JOIN (
        SELECT store_id, language_id, query, keyword
        FROM oc_seo_url@mff_placeholder
    ) su_ru ON su_ru.query = CONCAT('category_id=', csm.site_category_id)
           AND su_ru.language_id = 1 AND su_ru.store_id = 0
    -- off UK slug з Papir
    LEFT JOIN category_seo cs_off
        ON cs_off.category_id = csm.category_id
       AND cs_off.site_id = (SELECT site_id FROM sites WHERE code='off')
       AND cs_off.language_id = 1
       AND cs_off.seo_url != ''
    ORDER BY csm.site_category_id
";

// Виконуємо через дві окремі вибірки (mff + Papir) і з'єднуємо в PHP
// ── mff: всі oc_seo_url для категорій
$rMff = Database::fetchAll('mff',
    "SELECT query, language_id, keyword FROM oc_seo_url
     WHERE query LIKE 'category_id=%' AND store_id=0"
);
$mffCatSlugs = array(); // mff_cat_id => [ru=>, uk=>]
if ($rMff['ok']) {
    foreach ($rMff['rows'] as $row) {
        $catId = (int)str_replace('category_id=', '', $row['query']);
        $lang  = (int)$row['language_id'];
        if (!isset($mffCatSlugs[$catId])) $mffCatSlugs[$catId] = array('ru'=>'','uk'=>'');
        if ($lang === 1) $mffCatSlugs[$catId]['ru'] = $row['keyword'];
        if ($lang === 2) $mffCatSlugs[$catId]['uk'] = $row['keyword'];
    }
}

// ── Papir: mappings + off UK slugs
$rPapir = Database::fetchAll('Papir',
    "SELECT csm.site_category_id AS mff_cat_id,
            csm.category_id      AS papir_cat_id,
            cs_off.seo_url       AS off_uk_slug
     FROM category_site_mapping csm
     JOIN sites s ON s.site_id = csm.site_id AND s.code = 'mff'
     LEFT JOIN category_seo cs_off
         ON cs_off.category_id = csm.category_id
        AND cs_off.site_id = (SELECT site_id FROM sites WHERE code='off')
        AND cs_off.language_id = 1
        AND cs_off.seo_url != ''
     ORDER BY csm.site_category_id"
);

$catTotal = 0; $catSkip = 0; $catOk = 0; $catErr = 0;

if ($rPapir['ok']) {
    foreach ($rPapir['rows'] as $row) {
        $mffCatId  = (int)$row['mff_cat_id'];
        $papirCatId = (int)$row['papir_cat_id'];
        $offUkSlug = (string)$row['off_uk_slug'];
        $ruSlug    = isset($mffCatSlugs[$mffCatId]) ? $mffCatSlugs[$mffCatId]['ru'] : '';
        $ukSlug    = isset($mffCatSlugs[$mffCatId]) ? $mffCatSlugs[$mffCatId]['uk'] : '';

        if ($ukSlug !== '') { $catSkip++; continue; } // вже є

        // Джерело: off UK → fallback: RU
        $slug = $offUkSlug !== '' ? $offUkSlug : $ruSlug;
        if ($slug === '') { $catSkip++; continue; }

        $catTotal++;
        if ($verbose) out("  cat mff={$mffCatId} papir={$papirCatId}  slug={$slug}" . ($offUkSlug ? '' : ' [ru-fallback]'));

        $ok1 = upsertMffSeoUrl('category_id=' . $mffCatId, $slug, $apply);
        $ok2 = upsertPapirCategorySeo($papirCatId, $slug, $apply);

        if ($ok1 && $ok2) $catOk++; else $catErr++;
    }
}

out("  Need update: {$catTotal},  already had UK: {$catSkip},  ok: {$catOk},  errors: {$catErr}  (" . sec($t) . "s)");
out("");

// ═══════════════════════════════════════════════════════════════════════════
// 2. ТОВАРИ
// ═══════════════════════════════════════════════════════════════════════════
out("── PRODUCTS ────────────────────────────────────────────────────────────");
$t = microtime(true);

// mff: всі oc_seo_url для товарів
$rMffProd = Database::fetchAll('mff',
    "SELECT query, language_id, keyword FROM oc_seo_url
     WHERE query LIKE 'product_id=%' AND store_id=0"
);
$mffProdSlugs = array(); // mff_product_id => [ru=>, uk=>]
if ($rMffProd['ok']) {
    foreach ($rMffProd['rows'] as $row) {
        $prodId = (int)str_replace('product_id=', '', $row['query']);
        $lang   = (int)$row['language_id'];
        if (!isset($mffProdSlugs[$prodId])) $mffProdSlugs[$prodId] = array('ru'=>'','uk'=>'');
        if ($lang === 1) $mffProdSlugs[$prodId]['ru'] = $row['keyword'];
        if ($lang === 2) $mffProdSlugs[$prodId]['uk'] = $row['keyword'];
    }
}

// off: Ukrainian URL по id_off
$rOffUrls = Database::fetchAll('off',
    "SELECT REPLACE(query,'product_id=','') AS id_off, keyword
     FROM oc_url_alias WHERE query LIKE 'product_id=%'"
);
$offProdUrls = array(); // id_off => keyword
if ($rOffUrls['ok']) {
    foreach ($rOffUrls['rows'] as $row) {
        $offProdUrls[(int)$row['id_off']] = $row['keyword'];
    }
}

// Papir: id_mf → id_off маппінг
$rMapping = Database::fetchAll('Papir',
    "SELECT id_mf, id_off FROM product_papir WHERE id_mf IS NOT NULL AND id_mf > 0"
);
$mfToOff = array(); // id_mf => id_off
if ($rMapping['ok']) {
    foreach ($rMapping['rows'] as $row) {
        $mfToOff[(int)$row['id_mf']] = (int)$row['id_off'];
    }
}

$prodTotal = 0; $prodSkip = 0; $prodOk = 0; $prodErr = 0;
$srcOff = 0; $srcRu = 0;

foreach ($mffProdSlugs as $mffProdId => $slugs) {
    if ($slugs['uk'] !== '') { $prodSkip++; continue; } // вже є
    if ($slugs['ru'] === '') { $prodSkip++; continue; } // немає RU — пропустити

    $prodTotal++;

    // Джерело: off UK → fallback RU
    $idOff = isset($mfToOff[$mffProdId]) ? $mfToOff[$mffProdId] : 0;
    $slug  = ($idOff > 0 && isset($offProdUrls[$idOff])) ? $offProdUrls[$idOff] : '';
    if ($slug !== '') {
        $srcOff++;
    } else {
        $slug = $slugs['ru'];
        $srcRu++;
    }

    if ($verbose) out("  prod mff={$mffProdId}  slug={$slug}" . ($idOff && isset($offProdUrls[$idOff]) ? '' : ' [ru-fallback]'));

    $ok = upsertMffSeoUrl('product_id=' . $mffProdId, $slug, $apply);
    if ($ok) $prodOk++; else $prodErr++;

    if ($prodTotal % 100 === 0) out("    processed {$prodTotal}...");
}

out("  Need update: {$prodTotal}  (off-src: {$srcOff}, ru-fallback: {$srcRu})");
out("  Already had UK: {$prodSkip},  ok: {$prodOk},  errors: {$prodErr}  (" . sec($t) . "s)");
out("");
out($apply ? "Done." : "Run with --apply to save changes.");
