<?php
/**
 * Category migration script
 *
 * 1. Insert 25 missing off categories into Papir.categoria (with explicit IDs
 *    to fix orphaned product_papir.categoria_id references 1083-1096)
 * 2. Copy images from off.oc_category.image → Papir.categoria.image
 * 3. Generate Ukrainian SEO fields (meta_title, meta_description, seo_h1, seo_url)
 *    and write to Papir.category_description (lang=2) + off.oc_category_description (lang=4)
 *
 * Usage: php scripts/migrate_categories.php [--dry-run]
 */

require_once __DIR__ . '/../modules/database/database.php';

$dryRun = in_array('--dry-run', $argv);
if ($dryRun) {
    echo "=== DRY RUN MODE ===\n\n";
}

// ── Step 1: Insert missing categories ───────────────────────────────────────

echo "=== STEP 1: Insert missing Papir categories ===\n";

// off_id → [papir_id, parent_papir_id, off_sort, name_ru, name_uk]
// papir IDs 1083-1096 align with existing orphan references in product_papir
// 1085 = off 829 inserted as parent BEFORE its children 1086-1089
$newCategories = array(
    // papir_id  off_id  parent_papir  sort  name_ru                                     name_uk
    array(1083, 827,   15,    24, 'Таймери',                           'Таймери'),
    array(1084, 828,   44,     1, 'Медицинские бланки',                'Медичні бланки'),
    array(1085, 829,    0,    16, 'ТОВАРЫ ДЛЯ ОБУЧЕНИЯ, РАЗВИТИЯ ДЕТЕЙ', 'ТОВАРИ ДЛЯ НАВЧАННЯ, РОЗВИТКУ ДІТЕЙ'),
    array(1086, 830, 1085,    3, 'Книги детские',                     'Книжки для дітей'),
    array(1087, 831, 1085,    4, 'Наборы для обучения',               'Набори для навчання'),
    array(1088, 832, 1085,    6, 'Дидактические материалы',           'Дидактичні матеріали'),
    array(1089, 833, 1085,    1, 'Игры обучающие',                    'Ігри навчальні'),
    array(1090, 834,    0,   15, 'ИГРЫ',                              'ІГРАШКИ'),
    array(1091, 835, 1090,    1, 'Конструкторы',                      'Конструктори'),
    array(1092, 836, 1090,    0, 'Настольные игры',                   'Настільні ігри'),
    array(1093, 837,   26,    0, 'Алмазные мозаики',                  'Алмазні мозаїки'),
    array(1094, 838,    0,   17, 'КНИГИ',                             'КНИЖКИ'),
    array(1095, 839,   44,    0, 'Воинский учет',                     'Воїнський облік'),
    array(1096, 840,   44,    0, 'Нотариат',                          'Нотаріат'),
    // Additional off-only categories (no orphan references)
    array(1097,  32,    3,    4, 'Пишущие принадлежности',            'Письмове приладдя'),
    array(1098, 116,    4,    1, 'Блокноты',                          'Блокноти'),
    array(1099, 190, 1097,    5, 'Ластики',                           'Ластики'),
    array(1100, 145,    7,    9, 'Линейки',                           'Лінійки'),
    array(1101,  48,   12,    7, 'Посуда',                            'Посуд'),
    array(1102, 552,   12,    2, 'Салфетки',                          'Серветки'),
    array(1103, 203,   43,   14, 'Аксессуары',                        'Аксесуари'),
    array(1104, 548,   43,    2, 'Коврики',                           'Килимки'),
    array(1105, 320,   19,    0, 'Пакеты',                            'Пакети'),
    array(1106, 550,   37,    3, 'Карандаши',                         'Олівці'),
    array(1107, 540,  141,    1, 'Бумага цветная',                    'Папір кольоровий'),
);

$inserted = 0;
$skipped  = 0;

foreach ($newCategories as $row) {
    list($papirId, $offId, $parentPapir, $sort, $nameRu, $nameUk) = $row;

    // Check if already exists
    $exists = Database::fetchRow('Papir',
        "SELECT category_id FROM categoria WHERE category_id = {$papirId}"
    );
    if ($exists['ok'] && !empty($exists['row'])) {
        echo "  SKIP: Papir #{$papirId} (off #{$offId}) already exists\n";
        $skipped++;
        continue;
    }

    echo "  INSERT: Papir #{$papirId} = off #{$offId} — {$nameUk} (parent={$parentPapir})\n";

    if (!$dryRun) {
        // Insert categoria
        $sql = "INSERT INTO categoria
                    (category_id, category_off, parent_id, sort_order, status)
                VALUES
                    ({$papirId}, {$offId}, {$parentPapir}, {$sort}, 1)";
        $r = Database::query('Papir', $sql);
        if (!$r['ok']) {
            echo "  ERROR inserting categoria #{$papirId}: check logs\n";
            continue;
        }

        // Insert category_description RU (lang=1)
        $nameRuEsc = Database::escape('Papir', $nameRu);
        Database::query('Papir',
            "INSERT IGNORE INTO category_description
                 (category_id, language_id, name)
             VALUES ({$papirId}, 1, '{$nameRuEsc}')"
        );

        // Insert category_description UK (lang=2)
        $nameUkEsc = Database::escape('Papir', $nameUk);
        Database::query('Papir',
            "INSERT IGNORE INTO category_description
                 (category_id, language_id, name)
             VALUES ({$papirId}, 2, '{$nameUkEsc}')"
        );
    }
    $inserted++;
}

echo "  Done: {$inserted} inserted, {$skipped} skipped\n\n";

// ── Step 2: Copy images from off.oc_category.image ──────────────────────────

echo "=== STEP 2: Copy images from off → Papir.categoria.image ===\n";

$allCats = Database::fetchAll('Papir',
    "SELECT c.category_id, c.category_off, c.image, oc.image as off_image
     FROM categoria c
     LEFT JOIN menufold_offtorg.oc_category oc ON oc.category_id = c.category_off
     WHERE (c.image IS NULL OR c.image = '')
       AND oc.image IS NOT NULL AND oc.image != ''"
);

$imgUpdated = 0;
if ($allCats['ok'] && !empty($allCats['rows'])) {
    foreach ($allCats['rows'] as $cat) {
        echo "  img #{$cat['category_id']}: {$cat['off_image']}\n";
        if (!$dryRun) {
            Database::update('Papir', 'categoria',
                array('image' => $cat['off_image']),
                array('category_id' => (int)$cat['category_id'])
            );
        }
        $imgUpdated++;
    }
}
echo "  Done: {$imgUpdated} images copied\n\n";

// ── Step 3: SEO fields for all categories ───────────────────────────────────

echo "=== STEP 3: Generate Ukrainian SEO fields ===\n";

function translit($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $map = array(
        'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d',
        'е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i',
        'ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n',
        'о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
        'ь'=>'','ю'=>'yu','я'=>'ya',
        'a'=>'a','b'=>'b','c'=>'c','d'=>'d','e'=>'e','f'=>'f',
        'g'=>'g','h'=>'h','i'=>'i','j'=>'j','k'=>'k','l'=>'l',
        'm'=>'m','n'=>'n','o'=>'o','p'=>'p','q'=>'q','r'=>'r',
        's'=>'s','t'=>'t','u'=>'u','v'=>'v','w'=>'w','x'=>'x',
        'y'=>'y','z'=>'z',
    );
    $result = '';
    $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($chars as $ch) {
        if (isset($map[$ch])) {
            $result .= $map[$ch];
        } elseif (ctype_digit($ch)) {
            $result .= $ch;
        } else {
            $result .= '-';
        }
    }
    // Clean up multiple dashes, trim
    $result = preg_replace('/-+/', '-', $result);
    $result = trim($result, '-');
    return $result;
}

function ucfirstUkr($str) {
    // Uppercase first letter of Ukrainian string
    $first = mb_strtoupper(mb_substr($str, 0, 1, 'UTF-8'), 'UTF-8');
    return $first . mb_substr($str, 1, null, 'UTF-8');
}

// Get all categories with their Ukrainian names
$cats = Database::fetchAll('Papir',
    "SELECT cd2.category_id, cd2.name as name_uk, cd2.meta_title, cd2.seo_url,
            cd1.meta_title as ru_meta_title, cd1.meta_description as ru_meta_desc
     FROM category_description cd2
     LEFT JOIN category_description cd1 ON cd1.category_id = cd2.category_id AND cd1.language_id = 1
     WHERE cd2.language_id = 2"
);

$seoUpdated = 0;
$seoSkipped = 0;

if ($cats['ok'] && !empty($cats['rows'])) {
    foreach ($cats['rows'] as $cat) {
        $name = trim($cat['name_uk']);
        if (empty($name)) continue;

        // Skip if SEO already filled
        if (!empty($cat['seo_url']) && !empty($cat['meta_title'])) {
            $seoSkipped++;
            continue;
        }

        $namePretty = ucfirstUkr($name);
        $slug       = translit($name);
        $metaTitle  = $namePretty . ' купити в Україні — Офіс Торг';
        $metaDesc   = $namePretty . ' — великий вибір за вигідними цінами. '
                    . 'Купити зі складу у Києві та Дніпрі з доставкою по всій Україні.';
        $seoH1      = $namePretty;

        $catId = (int)$cat['category_id'];

        echo "  SEO #{$catId}: [{$slug}] {$metaTitle}\n";

        if (!$dryRun) {
            $titleEsc = Database::escape('Papir', $metaTitle);
            $descEsc  = Database::escape('Papir', $metaDesc);
            $h1Esc    = Database::escape('Papir', $seoH1);
            $slugEsc  = Database::escape('Papir', $slug);

            Database::query('Papir',
                "UPDATE category_description
                 SET meta_title='{$titleEsc}',
                     meta_description='{$descEsc}',
                     seo_h1='{$h1Esc}',
                     seo_url='{$slugEsc}'
                 WHERE category_id={$catId} AND language_id=2"
            );

            // Also update off.oc_category_description lang=4 (Ukrainian in off)
            $r = Database::fetchRow('off',
                "SELECT cd.category_id FROM oc_category_description cd
                 JOIN Papir.categoria c ON c.category_off = cd.category_id
                 WHERE c.category_id = {$catId} AND cd.language_id = 4"
            );
            if ($r['ok'] && !empty($r['row'])) {
                $offCatId = (int)$r['row']['category_id'];
                Database::query('off',
                    "UPDATE oc_category_description
                     SET name='{$h1Esc}',
                         meta_title='{$titleEsc}',
                         meta_description='{$descEsc}',
                         meta_h1='{$h1Esc}'
                     WHERE category_id={$offCatId} AND language_id=4"
                );
            }
            // For off lang=4 that doesn't exist yet (new categories), INSERT it
            // (existing ones updated above)
        }
        $seoUpdated++;
    }
}
echo "  Done: {$seoUpdated} updated, {$seoSkipped} already had SEO\n\n";

echo "=== Migration complete ===\n";
echo "Run without --dry-run to apply changes.\n";
