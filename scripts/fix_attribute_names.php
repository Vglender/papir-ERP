<?php
/**
 * Исправление названий атрибутов:
 * 1. HTML-entities (&#39; → ', &quot; → " и т.д.)
 * 2. Украинский текст в русском слоте → русский перевод
 * 3. Русский текст в украинском слоте → украинский перевод
 * 4. Каскад на сайты (off/mff) через CascadeHelper
 */
require_once __DIR__ . '/../modules/attributes/attributes_bootstrap.php';

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

// ───────────────────────────────────────────────────────────────────────────
// ТАБЛИЦА ИСПРАВЛЕНИЙ: attribute_id => [uk => '...', ru => '...']
// Если поле не указано — оно не меняется (только декодируются HTML-entities)
// ───────────────────────────────────────────────────────────────────────────
$fixes = array(

    // ── HTML entities в UK + RU уже правильный ────────────────────────────
    // (только decode entity — ru оставляем)
    108  => array('uk' => "Об'єм/вага"),
    231  => array('uk' => "Реєстр пам'яті"),
    301  => array('uk' => "Об'єм фарби одного виду"),
    402  => array('uk' => "М'якість"),
    414  => array('uk' => "Об'єм кошика"),
    592  => array('uk' => "Об'єм, л"),
    621  => array('uk' => "Функція пам'яті"),
    631  => array('uk' => 'Клавіші "00" та "000"'),
    643  => array('uk' => "Збереження та виклик з пам'яті змінних"),
    646  => array('uk' => "Функція пам'яті останнього результату (ANS)"),
    690  => array('uk' => "З'ємна ручка"),
    791  => array('uk' => "Роз'єми"),
    // HTML entity в UK + Ukrainian в RU
    719  => array('uk' => "Збереження та виклик з пам'яті змінних",
                  'ru' => "Сохранение и вызов из памяти переменных"),

    // ── Ukrainian в RU → перевод на русский ───────────────────────────────
    685  => array('ru' => "Антипригарное покрытие"),
    686  => array('ru' => "Арочный механизм папки открывается на 180 градусов."),
    696  => array('ru' => "Вместимость"),
    729  => array('ru' => "Возможность работы с угловыми величинами (DEG, RAD, GRAD)"),
    730  => array('ru' => "Вычисление процентов"),
    761  => array('ru' => "Класс средства"),
    770  => array('ru' => "Назначение типа белья"),
    774  => array('ru' => "Класс косметики"),
    802  => array('ru' => "Ширина торца"),
    811  => array('ru' => "Форма корпуса"),
    827  => array('ru' => "Тип зажима"),
    877  => array('ru' => "Изменение знака значения"),
    887  => array('ru' => "Тип элемента питания"),
    895  => array('ru' => "Декоративная металлическая накладка"),
    896  => array('ru' => "Функция коррекции"),
    900  => array('ru' => "Количество функций"),
    905  => array('ru' => "Физические константы"),
    908  => array('ru' => "Возраст"),
    909  => array('ru' => "Кол-во лист."),
    912  => array('ru' => "Наличие антистеплера"),
    913  => array('ru' => "Формат (размер, см)"),
    917  => array('ru' => "Издательство"),
    918  => array('ru' => "Код производителя"),
    920  => array('ru' => "Язык"),
    921  => array('ru' => "Год издания"),
    922  => array('ru' => "Размер упаковки"),
    923  => array('ru' => "Серия издательства"),
    926  => array('ru' => "Тип товара"),
    932  => array('ru' => "Материал к учебнику"),
    935  => array('ru' => "Иллюстратор (художник)"),
    936  => array('ru' => "Иллюстрации"),
    943  => array('ru' => "Обложка"),
    944  => array('ru' => "Бумага"),
    946  => array('ru' => "Подарочное издание"),
    948  => array('ru' => "Год"),
    949  => array('ru' => "Рекомендуемый возраст"),
    955  => array('ru' => "Базовая ед. измер."),
    956  => array('ru' => "Группа"),
    957  => array('ru' => "Материал страницы"),
    958  => array('ru' => "Нанесение"),
    959  => array('ru' => "Размер"),
    963  => array('ru' => "Линовка"),
    965  => array('ru' => "Вырубной алфавит"),
    966  => array('ru' => "Цвет форзацев"),
    967  => array('ru' => "Скругление углов блока"),
    968  => array('ru' => "Название материала обложки"),
    969  => array('ru' => "Разметка блока"),
    972  => array('ru' => "Цвет резинки"),
    973  => array('ru' => "Цвет окраса среза блока"),
    974  => array('ru' => "Цвет каптала"),
    975  => array('ru' => "Цвет ляссе"),
    976  => array('ru' => "Нанесение рекомендованное"),
    977  => array('ru' => "Плотность бумаги блока"),
    980  => array('ru' => "Вес брутто коробки, кг"),
    981  => array('uk' => "Об'єм коробки, м3",  'ru' => "Объём коробки, м3"),
    983  => array('ru' => "Толщина корешка"),
    984  => array('ru' => "Перфорация углов"),
    986  => array('ru' => "Диаметр пишущего узла (шарика)"),
    987  => array('ru' => "Количество разрезанных листов"),
    988  => array('ru' => "Формат реза"),
    989  => array('ru' => "Тип реза"),
    990  => array('ru' => "Диапазон диаметра пружин"),
    991  => array('ru' => "Максимальный формат документа"),
    992  => array('ru' => "Тип брошюровальника"),
    699  => array('ru' => "Крышка"),
    735  => array('ru' => "Вычисления со скобками"),

    // ── Русский в UK → украинский перевод ────────────────────────────────
    997  => array('uk' => "Кріплення файлів"),
    999  => array('uk' => "Нарізка на ваш розмір"),
    1000 => array('uk' => "Нанесення зображення"),
    1001 => array('uk' => "Термін постачання"),
    1002 => array('uk' => "Товщина, мм",      'ru' => "Толщина, мм"),
    1003 => array('uk' => "Формат/розмір, см"),
    1004 => array('uk' => "Формат/розмір листів"),
);

// ── Применяем исправления ─────────────────────────────────────────────────

$total = 0;
$cascaded = 0;

foreach ($fixes as $attrId => $names) {
    $attrId = (int)$attrId;

    // Читаем текущие значения
    $cur = Database::fetchAll('Papir',
        "SELECT language_id, attribute_name FROM product_attribute_description
         WHERE attribute_id = {$attrId}"
    );
    if (!$cur['ok']) { out("  ERROR reading attr {$attrId}"); continue; }

    $current = array();
    foreach ($cur['rows'] as $row) {
        $current[(int)$row['language_id']] = $row['attribute_name'];
    }

    $newUk = isset($names['uk']) ? $names['uk'] : (isset($current[2]) ? html_entity_decode($current[2], ENT_QUOTES|ENT_HTML5, 'UTF-8') : null);
    $newRu = isset($names['ru']) ? $names['ru'] : (isset($current[1]) ? html_entity_decode($current[1], ENT_QUOTES|ENT_HTML5, 'UTF-8') : null);

    $changed = array();

    if ($newUk !== null) {
        $oldUk = isset($current[2]) ? $current[2] : '';
        if ($newUk !== $oldUk) {
            $esc = Database::escape('Papir', $newUk);
            Database::query('Papir',
                "INSERT INTO product_attribute_description (attribute_id, language_id, attribute_name)
                 VALUES ({$attrId}, 2, '{$esc}')
                 ON DUPLICATE KEY UPDATE attribute_name = '{$esc}'"
            );
            $changed[2] = $newUk;
        }
    }

    if ($newRu !== null) {
        $oldRu = isset($current[1]) ? $current[1] : '';
        if ($newRu !== $oldRu) {
            $esc = Database::escape('Papir', $newRu);
            Database::query('Papir',
                "INSERT INTO product_attribute_description (attribute_id, language_id, attribute_name)
                 VALUES ({$attrId}, 1, '{$esc}')
                 ON DUPLICATE KEY UPDATE attribute_name = '{$esc}'"
            );
            $changed[1] = $newRu;
        }
    }

    if (!empty($changed)) {
        $total++;
        // Также decode entities в незатронутом слоте (если не менялся)
        $cascadeNames = array();
        if (isset($newUk)) $cascadeNames[2] = $newUk;
        if (isset($newRu)) $cascadeNames[1] = $newRu;

        AttributeCascadeHelper::cascadeAttributeName($attrId, $cascadeNames);
        $cascaded++;

        $displayUk = isset($cascadeNames[2]) ? $cascadeNames[2] : (isset($current[2]) ? $current[2] : '');
        $displayRu = isset($cascadeNames[1]) ? $cascadeNames[1] : (isset($current[1]) ? $current[1] : '');
        out("  #{$attrId}: UK={$displayUk} | RU={$displayRu}");
    }
}

out("=== ГОТОВО: обновлено {$total} атрибутов, каскадировано {$cascaded} ===");
