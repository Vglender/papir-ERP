<?php
/**
 * Attributes data migration:
 * 1. Create attribute groups with auto-classification
 * 2. Assign existing Papir attributes to groups
 * 3. Migrate attribute_off → attribute_site_mapping (site_id=1)
 * 4. Import mff attributes — map to Papir where names match, create new otherwise
 *
 * Run once: php modules/attributes/migrations/002_migrate_data.php
 */

require_once __DIR__ . '/../../../modules/database/database.php';

$log = array();
function out($msg) {
    global $log;
    $log[] = $msg;
    echo $msg . "\n";
}

// ── 1. Группы атрибутов ───────────────────────────────────────────────────────

$groups = array(
    array(
        'uk' => 'Основні характеристики',
        'ru' => 'Основные характеристики',
        'sort' => 10,
        'keywords' => array('тип', 'вид товар', 'торгова марка', 'торговая марка', 'країна', 'страна',
            'стать', 'пол', 'штрих', 'серія', 'серия', 'особлив', 'особенност', 'призначен',
            'назначен', 'isbn', 'автор', 'видавництво', 'видавництв', 'мова', 'язык',
            'код виробника', 'код производ', 'тип товар', 'вид упаков'),
    ),
    array(
        'uk' => 'Розміри та формат',
        'ru' => 'Размеры и формат',
        'sort' => 20,
        'keywords' => array('формат', 'ширина', 'висота', 'высота', 'довжина', 'длина', 'товщина',
            'толщина', 'розмір', 'размер', 'діаметр', 'диаметр', 'діагональ',
            'глибина', 'глубина', 'об\'єм', 'объем', 'місткість', 'емкость',
            'ємність'),
    ),
    array(
        'uk' => 'Папір та поліграфія',
        'ru' => 'Бумага и полиграфия',
        'sort' => 30,
        'keywords' => array('щільність', 'плотность', 'листів', 'листов', 'блок', 'офсет',
            'білизна', 'белизна', 'кількість аркуш', 'кол. лист', 'кол листов',
            'кількість арк', 'стандарт упаков', 'серія видавн'),
    ),
    array(
        'uk' => 'Матеріал та колір',
        'ru' => 'Материал и цвет',
        'sort' => 40,
        'keywords' => array('матеріал', 'материал', 'колір', 'цвет', 'покриття', 'покрытие',
            'колір чорнил', 'цвет чернил', 'колір картон', 'цвет картон',
            'кешуван', 'кешировк', 'ламінац'),
    ),
    array(
        'uk' => 'Упаковка',
        'ru' => 'Упаковка',
        'sort' => 50,
        'keywords' => array('упаковк', 'пакет', 'фасовк', 'кількість в уп', 'кол-во в уп',
            'розмір упак', 'размер упак', 'стандарт уп'),
    ),
    array(
        'uk' => 'Вага та фізичні параметри',
        'ru' => 'Вес и физические параметры',
        'sort' => 60,
        'keywords' => array('вага', 'вес', 'потужність', 'мощность', 'напруга', 'напряжение',
            'температур', 'заряд', 'швидкість', 'скорость'),
    ),
    array(
        'uk' => 'Інше',
        'ru' => 'Прочее',
        'sort' => 99,
        'keywords' => array(), // fallback
    ),
);

// Insert groups
$groupIdMap = array(); // index → group_id
out("=== Создание групп ===");
foreach ($groups as $idx => $g) {
    $r = Database::insert('Papir', 'attribute_group', array('sort_order' => $g['sort'], 'status' => 1));
    if (!$r['ok']) { out("ERROR inserting group: " . $g['uk']); continue; }
    $gid = $r['insert_id'];
    $groupIdMap[$idx] = $gid;
    Database::insert('Papir', 'attribute_group_description', array('group_id' => $gid, 'language_id' => 2, 'name' => $g['uk']));
    Database::insert('Papir', 'attribute_group_description', array('group_id' => $gid, 'language_id' => 1, 'name' => $g['ru']));
    out("  Группа [{$gid}]: {$g['uk']}");
}

$fallbackGroupId = $groupIdMap[count($groups) - 1]; // "Інше"

// ── 2. Назначить группы атрибутам ────────────────────────────────────────────

out("\n=== Назначение групп атрибутам ===");
$attrs = Database::fetchAll('Papir',
    "SELECT pa.attribute_id, pad.attribute_name
     FROM product_attribute pa
     LEFT JOIN product_attribute_description pad ON pad.attribute_id = pa.attribute_id AND pad.language_id = 1"
);
$assigned = 0;
$fallback = 0;
foreach ($attrs['rows'] as $attr) {
    $aid  = (int)$attr['attribute_id'];
    $name = mb_strtolower(trim((string)$attr['attribute_name']), 'UTF-8');
    $foundGroup = null;

    foreach ($groups as $idx => $g) {
        if (empty($g['keywords'])) continue;
        foreach ($g['keywords'] as $kw) {
            if (mb_strpos($name, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
                $foundGroup = $groupIdMap[$idx];
                break 2;
            }
        }
    }

    if (!$foundGroup) {
        $foundGroup = $fallbackGroupId;
        $fallback++;
    } else {
        $assigned++;
    }

    Database::query('Papir', "UPDATE product_attribute SET group_id = {$foundGroup} WHERE attribute_id = {$aid}");
}
out("  Назначено в группы: {$assigned}, в 'Інше': {$fallback}");

// ── 3. Мигрировать attribute_off → attribute_site_mapping (site_id=1) ─────────

out("\n=== Миграция attribute_off → attribute_site_mapping ===");
$r = Database::query('Papir',
    "INSERT IGNORE INTO attribute_site_mapping (attribute_id, site_id, site_attribute_id)
     SELECT attribute_id, 1, attribute_off
     FROM product_attribute
     WHERE attribute_off IS NOT NULL AND attribute_off > 0"
);
out("  Перенесено записей: " . $r['affected_rows']);

// ── 4. Импорт mff атрибутов ───────────────────────────────────────────────────

out("\n=== Импорт mff атрибутов ===");

$mffAttrs = Database::fetchAll('mff',
    "SELECT a.attribute_id as mff_attr_id, ad.name as name_ru
     FROM oc_attribute a
     LEFT JOIN oc_attribute_description ad ON ad.attribute_id = a.attribute_id AND ad.language_id = 1"
);

// Загрузить все Papir атрибуты по RU-имени для поиска совпадений
$papirByNameRu = array();
$papirNames = Database::fetchAll('Papir',
    "SELECT pa.attribute_id, pad.attribute_name FROM product_attribute pa
     JOIN product_attribute_description pad ON pad.attribute_id = pa.attribute_id AND pad.language_id = 1"
);
foreach ($papirNames['rows'] as $p) {
    $key = mb_strtolower(trim((string)$p['attribute_name']), 'UTF-8');
    $papirByNameRu[$key] = (int)$p['attribute_id'];
}

$mapped = 0;
$created = 0;
foreach ($mffAttrs['rows'] as $mff) {
    $mffId  = (int)$mff['mff_attr_id'];
    $nameRu = trim((string)$mff['name_ru']);
    $nameKey = mb_strtolower($nameRu, 'UTF-8');

    // Проверяем — уже замаплен?
    $existing = Database::fetchRow('Papir',
        "SELECT id FROM attribute_site_mapping WHERE site_id = 2 AND site_attribute_id = {$mffId}"
    );
    if ($existing['ok'] && !empty($existing['row'])) {
        continue; // уже есть
    }

    // Ищем точное совпадение по RU-имени
    if (isset($papirByNameRu[$nameKey])) {
        $papirAttrId = $papirByNameRu[$nameKey];
        Database::insert('Papir', 'attribute_site_mapping', array(
            'attribute_id'      => $papirAttrId,
            'site_id'           => 2,
            'site_attribute_id' => $mffId,
        ));
        out("  MAP: mff[{$mffId}] '{$nameRu}' → Papir[{$papirAttrId}]");
        $mapped++;
    } else {
        // Создаём новый атрибут в Papir
        $newAttr = Database::insert('Papir', 'product_attribute', array(
            'group_id'   => $fallbackGroupId,
            'sort_order' => 0,
            'status'     => 1,
        ));
        if (!$newAttr['ok']) { out("  ERROR creating attr for mff[{$mffId}] '{$nameRu}'"); continue; }
        $newId = $newAttr['insert_id'];

        Database::insert('Papir', 'product_attribute_description', array(
            'attribute_id'   => $newId,
            'language_id'    => 1,
            'attribute_name' => $nameRu,
        ));
        // UA — пока то же RU (нет перевода в mff)
        Database::insert('Papir', 'product_attribute_description', array(
            'attribute_id'   => $newId,
            'language_id'    => 2,
            'attribute_name' => $nameRu,
        ));
        Database::insert('Papir', 'attribute_site_mapping', array(
            'attribute_id'      => $newId,
            'site_id'           => 2,
            'site_attribute_id' => $mffId,
        ));
        // Добавляем в словарь для следующих итераций
        $papirByNameRu[$nameKey] = $newId;
        out("  NEW: Papir[{$newId}] '{$nameRu}' ← mff[{$mffId}]");
        $created++;
    }
}
out("  Замаплено: {$mapped}, Создано новых: {$created}");

out("\n=== ГОТОВО ===");
foreach ($log as $l) {} // already printed
