<?php
/**
 * Міграція полів вага / штрих-код з атрибутів → product_papir
 * + злиття дублів атрибутів штрих-коду (961 → 266)
 * + каскад видалення атрибутів з сайтів після міграції
 */
require_once __DIR__ . '/../modules/attributes/attributes_bootstrap.php';

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

// ── 1. Злиття атрибутів штрих-коду: 961 → 266 ────────────────────────────

out('=== 1. Злиття штрих-код атрибутів 961 → 266 ===');
$r = AttributeRepository::merge(961, 266);
out($r['ok'] ? '  OK: 961 → 266 злито' : '  ERROR: ' . $r['error']);

// ── 2. Міграція штрих-коду: атрибут 266 → product_papir.ean ──────────────

out('=== 2. Міграція штрих-коду → product_papir.ean ===');

$r = Database::query('Papir',
    "UPDATE product_papir pp
     JOIN (
         SELECT product_id, MAX(text) AS barcode
         FROM product_attribute_value
         WHERE attribute_id = 266 AND site_id = 0 AND language_id = 1
           AND TRIM(text) != ''
         GROUP BY product_id
     ) src ON src.product_id = pp.product_id
     SET pp.ean = src.barcode
     WHERE (pp.ean IS NULL OR pp.ean = '')"
);
out('  Оновлено ean: ' . ($r['ok'] ? $r['affected_rows'] : 'ERROR'));

// ── 3. Міграція ваги: атрибут 776 "Вага" → product_papir.weight ──────────

out('=== 3. Міграція ваги → product_papir.weight ===');

// Атрибут 776 "Вага" має значення типу "150", "1200 г", "0.5 кг"
// Витягуємо числову частину і конвертуємо в грами
$weightRows = Database::fetchAll('Papir',
    "SELECT pav.product_id, pav.text
     FROM product_attribute_value pav
     JOIN product_papir pp ON pp.product_id = pav.product_id
     WHERE pav.attribute_id = 776 AND pav.site_id = 0 AND pav.language_id = 1
       AND TRIM(pav.text) != ''
       AND (pp.weight IS NULL OR pp.weight = 0)"
);

$weightMigrated = 0;
if ($weightRows['ok']) {
    foreach ($weightRows['rows'] as $row) {
        $pid  = (int)$row['product_id'];
        $text = trim($row['text']);

        // Парсимо: число + можлива одиниця (г, кг, g, kg)
        if (!preg_match('/^([\d.,]+)\s*(кг|kg|г|g|гр|gr)?$/iu', $text, $m)) continue;

        $val  = (float)str_replace(',', '.', $m[1]);
        $unit = isset($m[2]) ? mb_strtolower(trim($m[2]), 'UTF-8') : 'г';

        // Конвертація до грамів
        if (in_array($unit, array('кг', 'kg'))) {
            $val  *= 1000;
            $classId = 1; // кг
        } else {
            $classId = 2; // г
        }

        if ($val <= 0) continue;

        Database::update('Papir', 'product_papir',
            array('weight' => $val, 'weight_class_id' => $classId),
            array('product_id' => $pid)
        );
        $weightMigrated++;
    }
}
out("  Перенесено ваг: {$weightMigrated}");

// ── 4. Каскадне видалення атрибута штрих-коду з сайтів ───────────────────

out('=== 4. Видалення атрибута Штрих-код (266) з сайтів ===');

// Отримуємо site_attribute_id для 266
$mappings = Database::fetchAll('Papir',
    "SELECT site_id, site_attribute_id FROM attribute_site_mapping WHERE attribute_id = 266"
);

$sites = Database::fetchAll('Papir', "SELECT site_id, db_alias FROM sites WHERE status=1");
$siteAliases = array();
foreach ($sites['rows'] as $s) {
    $siteAliases[(int)$s['site_id']] = $s['db_alias'];
}

foreach ($mappings['rows'] as $m) {
    $siteId     = (int)$m['site_id'];
    $siteAttrId = (int)$m['site_attribute_id'];
    $db = isset($siteAliases[$siteId]) ? $siteAliases[$siteId] : '';
    if (!$db) continue;

    // Видаляємо всі значення товарів для цього атрибута
    $r1 = Database::query($db,
        "DELETE FROM oc_product_attribute WHERE attribute_id = {$siteAttrId}"
    );
    // Видаляємо опис і сам атрибут
    Database::query($db, "DELETE FROM oc_attribute_description WHERE attribute_id = {$siteAttrId}");
    Database::query($db, "DELETE FROM oc_attribute WHERE attribute_id = {$siteAttrId}");

    out("  site_id={$siteId} ({$db}): видалено " . ($r1['ok'] ? $r1['affected_rows'] : 'ERROR') . " рядків oc_product_attribute");
}

// Видаляємо з Papir
Database::query('Papir', "DELETE FROM attribute_site_mapping WHERE attribute_id = 266");
Database::query('Papir', "DELETE FROM product_attribute_value WHERE attribute_id = 266");
Database::query('Papir', "DELETE FROM product_attribute_description WHERE attribute_id = 266");
Database::query('Papir', "DELETE FROM product_attribute WHERE attribute_id = 266");
out('  Papir: атрибут 266 видалено');

// ── 5. Каскадне видалення атрибутів ваги з сайтів ────────────────────────

out('=== 5. Видалення атрибутів ваги (776, 4, 875, 160) з сайтів ===');

$weightAttrIds = array(776, 4, 875, 160);

foreach ($weightAttrIds as $attrId) {
    // Спочатку cascade на сайти
    $map = Database::fetchAll('Papir',
        "SELECT site_id, site_attribute_id FROM attribute_site_mapping WHERE attribute_id = {$attrId}"
    );
    foreach ($map['rows'] as $m) {
        $siteId     = (int)$m['site_id'];
        $siteAttrId = (int)$m['site_attribute_id'];
        $db = isset($siteAliases[$siteId]) ? $siteAliases[$siteId] : '';
        if (!$db) continue;
        Database::query($db, "DELETE FROM oc_product_attribute WHERE attribute_id = {$siteAttrId}");
        Database::query($db, "DELETE FROM oc_attribute_description WHERE attribute_id = {$siteAttrId}");
        Database::query($db, "DELETE FROM oc_attribute WHERE attribute_id = {$siteAttrId}");
    }
    // Видаляємо з Papir
    Database::query('Papir', "DELETE FROM attribute_site_mapping WHERE attribute_id = {$attrId}");
    Database::query('Papir', "DELETE FROM product_attribute_value WHERE attribute_id = {$attrId}");
    Database::query('Papir', "DELETE FROM product_attribute_description WHERE attribute_id = {$attrId}");
    Database::query('Papir', "DELETE FROM product_attribute WHERE attribute_id = {$attrId}");
    out("  Атрибут #{$attrId} видалено з Papir і сайтів");
}

// ── Підсумок ──────────────────────────────────────────────────────────────

out('');
out('=== ПІДСУМОК ===');
$eanCount    = Database::fetchRow('Papir', "SELECT COUNT(*) AS c FROM product_papir WHERE ean IS NOT NULL AND ean != ''");
$weightCount = Database::fetchRow('Papir', "SELECT COUNT(*) AS c FROM product_papir WHERE weight > 0");
out('  product_papir.ean заповнено: '    . ($eanCount['ok']    ? $eanCount['row']['c']    : '?') . ' товарів');
out('  product_papir.weight заповнено: ' . ($weightCount['ok'] ? $weightCount['row']['c'] : '?') . ' товарів');
out('=== ГОТОВО ===');
