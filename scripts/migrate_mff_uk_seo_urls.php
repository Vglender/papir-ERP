<?php
/**
 * Генерує Ukrainian SEO URL для категорій mff:
 *   — бере seo_url з off (Ukrainian, language_id=1 в Papir)
 *   — зберігає в Papir.category_seo (site_id=2 mff, language_id=1 UK)
 *   — зберігає в mff.oc_seo_url   (language_id=2 UK, query=category_id=X)
 *
 * Використання:
 *   php scripts/migrate_mff_uk_seo_urls.php          — dry-run (показати що буде)
 *   php scripts/migrate_mff_uk_seo_urls.php --apply  — застосувати
 */

set_time_limit(0);
require_once __DIR__ . '/../modules/database/database.php';

$apply = in_array('--apply', $argv);

function out($msg) { echo $msg . "\n"; }

out($apply ? "=== APPLY ===" : "=== DRY RUN (use --apply to save) ===");
out("");

// ─── 1. Отримати категорії mff без Ukrainian URL ─────────────────────────────
$sql = "
    SELECT
        csm.category_id,
        csm.site_category_id          AS mff_cat_id,
        cs_off_uk.seo_url             AS off_uk_url,
        COALESCE(cs_mff_uk.seo_url,'') AS mff_uk_url_existing,
        cs_mff_uk.seo_id              AS mff_seo_id
    FROM category_site_mapping csm
    JOIN sites s_mff ON s_mff.site_id = csm.site_id AND s_mff.code = 'mff'
    -- off Ukrainian URL
    LEFT JOIN category_seo cs_off_uk
        ON cs_off_uk.category_id = csm.category_id
       AND cs_off_uk.site_id = (SELECT site_id FROM sites WHERE code='off')
       AND cs_off_uk.language_id = 1
       AND cs_off_uk.seo_url != ''
    -- existing mff Ukrainian URL
    LEFT JOIN category_seo cs_mff_uk
        ON cs_mff_uk.category_id = csm.category_id
       AND cs_mff_uk.site_id = csm.site_id
       AND cs_mff_uk.language_id = 1
    WHERE (cs_mff_uk.seo_url IS NULL OR cs_mff_uk.seo_url = '')
      AND cs_off_uk.seo_url IS NOT NULL
    ORDER BY csm.category_id
";

$r = Database::fetchAll('Papir', $sql);
if (!$r['ok']) { out("ERROR: cannot fetch categories"); exit(1); }

$rows = $r['rows'];
out("Categories to update: " . count($rows));
out("");

$updated  = 0;
$inserted = 0;
$mffOk    = 0;
$mffErr   = 0;

foreach ($rows as $row) {
    $categoryId = (int)$row['category_id'];
    $mffCatId   = (int)$row['mff_cat_id'];
    $slug       = (string)$row['off_uk_url'];
    $seoId      = $row['mff_seo_id'] ? (int)$row['mff_seo_id'] : null;

    out("  cat_id={$categoryId} mff_cat={$mffCatId}  slug={$slug}");

    if (!$apply) continue;

    // ── Papir.category_seo ───────────────────────────────────────────────────
    if ($seoId) {
        // row exists but seo_url is empty — update
        $r2 = Database::update('Papir', 'category_seo',
            array('seo_url' => $slug, 'updated_at' => date('Y-m-d H:i:s')),
            array('seo_id' => $seoId)
        );
        if ($r2['ok']) $updated++; else out("    ERROR: Papir update seo_id={$seoId}");
    } else {
        // row doesn't exist — insert
        $r2 = Database::insert('Papir', 'category_seo', array(
            'category_id' => $categoryId,
            'site_id'     => 2,  // mff
            'language_id' => 1,  // Ukrainian in Papir
            'seo_url'     => $slug,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ));
        if ($r2['ok']) $inserted++; else out("    ERROR: Papir insert cat_id={$categoryId}");
    }

    // ── mff.oc_seo_url  (language_id=2 = Ukrainian in mff) ──────────────────
    $query   = 'category_id=' . $mffCatId;
    $escaped = Database::escape('mff', $slug);
    $escapedQ = Database::escape('mff', $query);

    // Check if row already exists
    $chk = Database::fetchRow('mff',
        "SELECT seo_url_id FROM oc_seo_url
         WHERE query = '{$escapedQ}' AND language_id = 2 AND store_id = 0"
    );

    if ($chk['ok'] && !empty($chk['row'])) {
        $mffId = (int)$chk['row']['seo_url_id'];
        $r3 = Database::query('mff',
            "UPDATE oc_seo_url SET keyword = '{$escaped}'
             WHERE seo_url_id = {$mffId}"
        );
    } else {
        $r3 = Database::query('mff',
            "INSERT INTO oc_seo_url (store_id, language_id, query, keyword)
             VALUES (0, 2, '{$escapedQ}', '{$escaped}')"
        );
    }

    if ($r3['ok']) $mffOk++; else { out("    ERROR: mff oc_seo_url cat={$mffCatId}"); $mffErr++; }
}

out("");
if ($apply) {
    out("Papir category_seo: updated={$updated}, inserted={$inserted}");
    out("mff oc_seo_url:      ok={$mffOk}, errors={$mffErr}");
    out("Done.");
} else {
    out("Run with --apply to apply changes.");
}
