<?php
/**
 * Одноразовый перенос ссылок из menufold_offtorg в Papir.product_papir.link_off
 *
 * Логика:
 *   Papir.product_papir.id_off  = menufold_offtorg.oc_product.product_id  (ключ связи)
 *   URL = 'https://officetorg.com.ua/' + oc_seo_url.keyword
 *         где oc_seo_url.query = 'product_id=' + product_id
 *
 * Обновляет ТОЛЬКО товары, у которых link_off ещё не заполнена.
 *
 * Запуск: php sync_links_off.php [--dry-run] [--force]
 *   --dry-run  не пишем в БД, показываем первые 10 примеров
 *   --force    обновить даже те, у кого link_off уже есть
 */

require_once __DIR__ . '/../../../modules/database/database.php';

$dryRun = in_array('--dry-run', $argv);
$force  = in_array('--force',   $argv);

echo "=== Sync link_off (Офисторг) ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN (не пишем в БД)" : "LIVE (пишем в Papir)") . PHP_EOL;
echo "Force: " . ($force ? "ДА (перезаписываем существующие)" : "НЕТ (только пустые)") . PHP_EOL . PHP_EOL;

// ── 1. Загружаем SEO URL из menufold_offtorg ────────────────────────────────
// База на localhost — можно одним запросом через JOIN
echo "Загружаем SEO URL из menufold_offtorg..." . PHP_EOL;

$seoResult = Database::fetchAll('offtorg',
    "SELECT p.product_id, s.keyword
     FROM `oc_product` p
     INNER JOIN `oc_url_alias` s
         ON s.query = CONCAT('product_id=', p.product_id)
     WHERE s.keyword != '' AND s.keyword IS NOT NULL"
);

if (!$seoResult['ok'] || empty($seoResult['rows'])) {
    echo "Ошибка: не удалось загрузить SEO URL из menufold_offtorg." . PHP_EOL;
    exit(1);
}

// seoMap[product_id] = keyword
$seoMap = array();
foreach ($seoResult['rows'] as $row) {
    $pid = (int)$row['product_id'];
    if ($pid > 0) {
        $seoMap[$pid] = $row['keyword'];
    }
}

echo "SEO URL загружено: " . count($seoMap) . PHP_EOL . PHP_EOL;

// ── 2. Загружаем товары из Papir, у которых нужно обновить link_off ─────────
echo "Загружаем товары из Papir..." . PHP_EOL;

$whereEmpty = $force ? "1=1" : "(link_off IS NULL OR link_off = '')";

$papirResult = Database::fetchAll('Papir',
    "SELECT product_id, id_off
     FROM `product_papir`
     WHERE id_off > 0 AND " . $whereEmpty
);

if (!$papirResult['ok']) {
    echo "Ошибка: не удалось загрузить товары из Papir." . PHP_EOL;
    exit(1);
}

echo "Товаров к обработке: " . count($papirResult['rows']) . PHP_EOL . PHP_EOL;

// ── 3. Собираем данные для обновления ───────────────────────────────────────
$baseUrl  = 'https://officetorg.com.ua/';
$toUpdate = array();
$skipped  = array();

foreach ($papirResult['rows'] as $row) {
    $idOff = (int)$row['id_off'];

    if (!isset($seoMap[$idOff])) {
        $skipped[] = $idOff;
        continue;
    }

    $toUpdate[] = array(
        'product_id' => (int)$row['product_id'],
        'id_off'     => $idOff,
        'url'        => $baseUrl . $seoMap[$idOff],
    );
}

echo "Готово к обновлению: " . count($toUpdate) . PHP_EOL;
echo "Пропущено (нет SEO URL в offtorg): " . count($skipped) . PHP_EOL . PHP_EOL;

if (empty($toUpdate)) {
    echo "Нечего обновлять." . PHP_EOL;
    exit(0);
}

// ── 4. Обновляем Papir.product_papir.link_off ────────────────────────────────
if ($dryRun) {
    echo "--- DRY RUN: первые 10 записей ---" . PHP_EOL;
    foreach (array_slice($toUpdate, 0, 10) as $item) {
        echo "  id_off=" . $item['id_off'] . " -> " . $item['url'] . PHP_EOL;
    }
    echo PHP_EOL . "Итого будет обновлено: " . count($toUpdate) . " записей." . PHP_EOL;
    exit(0);
}

echo "Обновляем Papir.product_papir.link_off..." . PHP_EOL;

$updated  = 0;
$failed   = 0;

foreach ($toUpdate as $item) {
    $url = Database::escape('Papir', $item['url']);

    $result = Database::query('Papir',
        "UPDATE `product_papir`
         SET `link_off` = '" . $url . "'
         WHERE `product_id` = " . $item['product_id']
    );

    if ($result['ok']) {
        $affected = isset($result['affected_rows']) ? (int)$result['affected_rows'] : 0;
        if ($affected > 0) {
            $updated++;
        }
    } else {
        $failed++;
    }
}

echo PHP_EOL . "=== Результат ===" . PHP_EOL;
echo "Обновлено:                     " . $updated . PHP_EOL;
echo "Без изменений (уже такой URL): " . (count($toUpdate) - $updated - $failed) . PHP_EOL;
echo "Ошибок запроса:                " . $failed . PHP_EOL;
echo "Нет SEO URL в offtorg:         " . count($skipped) . PHP_EOL;

echo PHP_EOL . "Готово." . PHP_EOL;
