<?php
/**
 * Migration: populate category_seo from off + mff site data
 * and fallback from Papir.category_description legacy fields.
 */
require_once '/var/www/papir/modules/database/database.php';

$ok = 0;
$skip = 0;
$errors = array();

// Fetch all categories
$catRes = Database::fetchAll('Papir',
    "SELECT category_id, category_off, category_mf FROM categoria ORDER BY category_id"
);
if (!$catRes['ok']) {
    echo "ERROR: Cannot fetch categoria\n";
    exit(1);
}
$cats = $catRes['rows'];
echo "Total categories: " . count($cats) . "\n";

function esc($db, $v) {
    return Database::escape($db, $v);
}

function insertSeo($categoryId, $siteId, $langId, $metaTitle, $metaDesc, $seoH1, $seoUrl) {
    $r = Database::query('Papir',
        "INSERT IGNORE INTO category_seo
            (category_id, site_id, language_id, meta_title, meta_description, seo_h1, seo_url)
         VALUES (
            " . (int)$categoryId . ",
            " . (int)$siteId . ",
            " . (int)$langId . ",
            '" . esc('Papir', $metaTitle) . "',
            '" . esc('Papir', $metaDesc) . "',
            '" . esc('Papir', $seoH1) . "',
            '" . esc('Papir', $seoUrl) . "'
         )"
    );
    return $r;
}

foreach ($cats as $cat) {
    $categoryId = (int)$cat['category_id'];
    $offCatId   = (int)$cat['category_off'];
    $mffCatId   = (int)$cat['category_mf'];

    // ── OFF (site_id=1) ────────────────────────────────────────────────
    if ($offCatId > 0) {
        // UA: off lang_id=4 → our language_id=1
        $offUa = Database::fetchRow('off',
            "SELECT meta_title, meta_description, meta_h1
             FROM oc_category_description
             WHERE category_id = {$offCatId} AND language_id = 4"
        );
        $metaTitleUa = '';
        $metaDescUa  = '';
        $seoH1Ua     = '';
        if ($offUa['ok'] && !empty($offUa['row'])) {
            $metaTitleUa = (string)$offUa['row']['meta_title'];
            $metaDescUa  = (string)$offUa['row']['meta_description'];
            $seoH1Ua     = (string)$offUa['row']['meta_h1'];
        }

        // RU: off lang_id=1 → our language_id=2
        $offRu = Database::fetchRow('off',
            "SELECT meta_title, meta_description, meta_h1
             FROM oc_category_description
             WHERE category_id = {$offCatId} AND language_id = 1"
        );
        $metaTitleRu = '';
        $metaDescRu  = '';
        $seoH1Ru     = '';
        if ($offRu['ok'] && !empty($offRu['row'])) {
            $metaTitleRu = (string)$offRu['row']['meta_title'];
            $metaDescRu  = (string)$offRu['row']['meta_description'];
            $seoH1Ru     = (string)$offRu['row']['meta_h1'];
        }

        // Slug from off.oc_url_alias (same for both languages)
        $offSlug = Database::fetchRow('off',
            "SELECT keyword FROM oc_url_alias WHERE query = 'category_id=" . $offCatId . "'"
        );
        $seoUrlOff = '';
        if ($offSlug['ok'] && !empty($offSlug['row'])) {
            $seoUrlOff = (string)$offSlug['row']['keyword'];
        }

        // Insert for lang=1 (UK)
        $r = insertSeo($categoryId, 1, 1, $metaTitleUa, $metaDescUa, $seoH1Ua, $seoUrlOff);
        if ($r['ok']) $ok++; else { $errors[] = "off uk cat $categoryId"; }

        // Insert for lang=2 (RU)
        $r = insertSeo($categoryId, 1, 2, $metaTitleRu, $metaDescRu, $seoH1Ru, $seoUrlOff);
        if ($r['ok']) $ok++; else { $errors[] = "off ru cat $categoryId"; }
    }

    // ── MFF (site_id=2) ───────────────────────────────────────────────
    if ($mffCatId > 0) {
        // UA: mff lang_id=2 → our language_id=1
        $mffUa = Database::fetchRow('mff',
            "SELECT meta_title, meta_description
             FROM oc_category_description
             WHERE category_id = {$mffCatId} AND language_id = 2"
        );
        $metaTitleUaMff = '';
        $metaDescUaMff  = '';
        if ($mffUa['ok'] && !empty($mffUa['row'])) {
            $metaTitleUaMff = (string)$mffUa['row']['meta_title'];
            $metaDescUaMff  = (string)$mffUa['row']['meta_description'];
        }

        // RU: mff lang_id=1 → our language_id=2
        $mffRu = Database::fetchRow('mff',
            "SELECT meta_title, meta_description
             FROM oc_category_description
             WHERE category_id = {$mffCatId} AND language_id = 1"
        );
        $metaTitleRuMff = '';
        $metaDescRuMff  = '';
        if ($mffRu['ok'] && !empty($mffRu['row'])) {
            $metaTitleRuMff = (string)$mffRu['row']['meta_title'];
            $metaDescRuMff  = (string)$mffRu['row']['meta_description'];
        }

        // Slug UA: mff.oc_seo_url lang_id=2 → our language_id=1
        $mffSlugUa = Database::fetchRow('mff',
            "SELECT keyword FROM oc_seo_url
             WHERE query = 'category_id=" . $mffCatId . "' AND language_id = 2
             LIMIT 1"
        );
        $seoUrlMffUa = '';
        if ($mffSlugUa['ok'] && !empty($mffSlugUa['row'])) {
            $seoUrlMffUa = (string)$mffSlugUa['row']['keyword'];
        }

        // Slug RU: mff.oc_seo_url lang_id=1 → our language_id=2
        $mffSlugRu = Database::fetchRow('mff',
            "SELECT keyword FROM oc_seo_url
             WHERE query = 'category_id=" . $mffCatId . "' AND language_id = 1
             LIMIT 1"
        );
        $seoUrlMffRu = '';
        if ($mffSlugRu['ok'] && !empty($mffSlugRu['row'])) {
            $seoUrlMffRu = (string)$mffSlugRu['row']['keyword'];
        }

        // Insert for lang=1 (UK)
        $r = insertSeo($categoryId, 2, 1, $metaTitleUaMff, $metaDescUaMff, '', $seoUrlMffUa);
        if ($r['ok']) $ok++; else { $errors[] = "mff uk cat $categoryId"; }

        // Insert for lang=2 (RU)
        $r = insertSeo($categoryId, 2, 2, $metaTitleRuMff, $metaDescRuMff, '', $seoUrlMffRu);
        if ($r['ok']) $ok++; else { $errors[] = "mff ru cat $categoryId"; }
    }
}

echo "Inserted: $ok rows\n";
if (!empty($errors)) {
    echo "Errors: " . count($errors) . "\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
}

// ── Step 3: Backfill seo_h1 + seo_url for OFF site from old Papir.category_description ──
echo "\nBackfilling seo_h1 and seo_url from Papir.category_description (lang=2) for OFF rows...\n";
$cdRes = Database::fetchAll('Papir',
    "SELECT category_id, seo_h1, seo_url FROM category_description WHERE language_id = 2"
);
$backfillOk = 0;
if ($cdRes['ok']) {
    foreach ($cdRes['rows'] as $row) {
        $catId  = (int)$row['category_id'];
        $h1     = (string)$row['seo_h1'];
        $slug   = (string)$row['seo_url'];

        if ($h1 !== '' || $slug !== '') {
            // Update category_seo for site_id=1 (OFF), language_id=1 (UK) where fields are null/empty
            Database::query('Papir',
                "UPDATE category_seo
                 SET
                   seo_h1  = CASE WHEN (seo_h1  IS NULL OR seo_h1 = '')  AND '" . esc('Papir', $h1)   . "' != '' THEN '" . esc('Papir', $h1)   . "' ELSE seo_h1  END,
                   seo_url = CASE WHEN (seo_url IS NULL OR seo_url = '') AND '" . esc('Papir', $slug) . "' != '' THEN '" . esc('Papir', $slug) . "' ELSE seo_url END
                 WHERE category_id = {$catId} AND site_id = 1 AND language_id = 1"
            );
            $backfillOk++;
        }
    }
}
echo "Backfill processed: $backfillOk categories\n";
echo "Migration complete.\n";
