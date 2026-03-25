<?php
/**
 * Одноразовая синхронизация атрибутов Papir → off / mff.
 *
 * Синхронизирует:
 * 1. Названия атрибутов (product_attribute_description → oc_attribute_description)
 * 2. Значения атрибутов товаров (product_attribute_value → oc_product_attribute)
 *
 * Запуск: php scripts/sync_attributes_to_sites.php
 * Опции: php scripts/sync_attributes_to_sites.php --names-only
 *        php scripts/sync_attributes_to_sites.php --values-only
 */

require_once __DIR__ . '/../modules/database/database.php';

$namesOnly  = in_array('--names-only',  $argv);
$valuesOnly = in_array('--values-only', $argv);
if (!$namesOnly && !$valuesOnly) { $namesOnly = true; $valuesOnly = true; }

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

// Загрузить сайты и маппинг языков
$sitesRes = Database::fetchAll('Papir', "SELECT site_id, code, db_alias FROM sites WHERE status=1 ORDER BY sort_order");
$sites = $sitesRes['ok'] ? $sitesRes['rows'] : array();

$langMapRes = Database::fetchAll('Papir', "SELECT site_id, language_id, site_lang_id FROM site_languages");
$langMap = array(); // site_id → [ papir_lang_id => site_lang_id ]
if ($langMapRes['ok']) {
    foreach ($langMapRes['rows'] as $l) {
        $sid = (int)$l['site_id'];
        if (!isset($langMap[$sid])) $langMap[$sid] = array();
        $langMap[$sid][(int)$l['language_id']] = (int)$l['site_lang_id'];
    }
}

out("Сайтів: " . count($sites));

// ── 1. Назви атрибутів ────────────────────────────────────────────────────────

if ($namesOnly) {
    out("=== Синхронізація назв атрибутів ===");

    $attrsRes = Database::fetchAll('Papir',
        "SELECT asm.attribute_id, asm.site_id, asm.site_attribute_id,
                pad.language_id, pad.attribute_name
         FROM attribute_site_mapping asm
         JOIN product_attribute_description pad ON pad.attribute_id = asm.attribute_id
         ORDER BY asm.site_id, asm.attribute_id"
    );

    $updated = 0; $skipped = 0;
    foreach ($attrsRes['rows'] as $row) {
        $siteId      = (int)$row['site_id'];
        $siteAttrId  = (int)$row['site_attribute_id'];
        $papirLangId = (int)$row['language_id'];
        $name        = (string)$row['attribute_name'];

        if (!isset($langMap[$siteId][$papirLangId])) { $skipped++; continue; }
        $siteLangId = $langMap[$siteId][$papirLangId];

        $db = '';
        foreach ($sites as $s) { if ((int)$s['site_id'] === $siteId) { $db = $s['db_alias']; break; } }
        if (!$db) { $skipped++; continue; }

        $nameEsc = Database::escape($db, $name);
        $r = Database::query($db,
            "UPDATE oc_attribute_description
             SET name = '{$nameEsc}'
             WHERE attribute_id = {$siteAttrId} AND language_id = {$siteLangId}"
        );
        if ($r['ok'] && $r['affected_rows'] > 0) $updated++;
    }
    out("  Оновлено рядків: {$updated}, пропущено: {$skipped}");
}

// ── 2. Значення атрибутів товарів ─────────────────────────────────────────────

if ($valuesOnly) {
    out("=== Синхронізація значень атрибутів товарів ===");
    out("  (оновлює text в oc_product_attribute де Papir-значення відрізняється)");

    foreach ($sites as $site) {
        $siteId = (int)$site['site_id'];
        $db     = (string)$site['db_alias'];
        if (!$db) continue;

        out("  Сайт: {$site['code']} ({$db})");

        // Для кожної пари (papir_lang_id → site_lang_id) оновлюємо значення
        if (!isset($langMap[$siteId])) continue;

        foreach ($langMap[$siteId] as $papirLangId => $siteLangId) {
            out("    Мова: Papir[{$papirLangId}] → site[{$siteLangId}]");

            // Масовий UPDATE через JOIN:
            // Papir product_id → product_site → site product_id
            // Papir attribute_id → attribute_site_mapping → site attribute_id
            $r = Database::query($db,
                "UPDATE oc_product_attribute opa
                 JOIN (
                     SELECT ps.site_product_id AS site_prod_id,
                            asm.site_attribute_id,
                            pav.text AS papir_text
                     FROM Papir.product_attribute_value pav
                     JOIN Papir.product_site ps
                          ON ps.product_id = pav.product_id AND ps.site_id = {$siteId}
                     JOIN Papir.attribute_site_mapping asm
                          ON asm.attribute_id = pav.attribute_id AND asm.site_id = {$siteId}
                     WHERE pav.language_id = {$papirLangId}
                       AND pav.site_id = 0
                       AND pav.text IS NOT NULL
                       AND TRIM(pav.text) != ''
                 ) src ON src.site_prod_id      = opa.product_id
                       AND src.site_attribute_id = opa.attribute_id
                       AND opa.language_id        = {$siteLangId}
                 SET opa.text = src.papir_text
                 WHERE opa.text != src.papir_text"
            );
            $cnt = $r['ok'] ? $r['affected_rows'] : 0;
            out("      Оновлено: {$cnt} значень");
        }
    }
}

out("=== ГОТОВО ===");
