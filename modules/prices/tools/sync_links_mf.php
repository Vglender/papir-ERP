<?php
/**
 * Одноразовый перенос ссылок из menufold_mff в Papir.product_papir.links_mf
 *
 * Логика:
 *   menufold_mff.oc_product.model  = Papir.product_papir.id_off  (ключ связи)
 *   URL = 'https://menufolder.com.ua/' + oc_seo_url.keyword
 *         где oc_seo_url.query = 'product_id=' + oc_product.product_id
 *
 * Запуск: php sync_links_mf.php  (или через браузер если есть доступ к /prices/tools/)
 */

require_once __DIR__ . '/../../../modules/database/database.php';

$dryRun = isset($argv[1]) && $argv[1] === '--dry-run';

echo "=== Sync links_mf ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN (не пишем в БД)" : "LIVE (пишем в Papir)") . PHP_EOL . PHP_EOL;

// ── 1. Загружаем товары из menufold_mff ────────────────────────────────────
echo "Загружаем oc_product из menufold_mff..." . PHP_EOL;

$productsResult = Database::fetchAll('mff',
    "SELECT `product_id`, `model`
     FROM `oc_product`
     WHERE `model` != '' AND `model` IS NOT NULL"
);

if (!$productsResult['ok'] || empty($productsResult['rows'])) {
    echo "Ошибка: не удалось загрузить товары из mff." . PHP_EOL;
    exit(1);
}

// productMap[product_id] = model (id_off в Papir)
$productMap = array();
foreach ($productsResult['rows'] as $row) {
    $pid   = (int)$row['product_id'];
    $model = (int)$row['model'];
    if ($pid > 0 && $model > 0) {
        $productMap[$pid] = $model;
    }
}

echo "Загружено товаров: " . count($productMap) . PHP_EOL;

// ── 2. Загружаем SEO URL из menufold_mff ──────────────────────────────────
echo "Загружаем oc_seo_url из menufold_mff..." . PHP_EOL;

$seoResult = Database::fetchAll('mff',
    "SELECT `query`, `keyword`
     FROM `oc_seo_url`
     WHERE `query` LIKE 'product_id=%' AND `keyword` != ''"
);

if (!$seoResult['ok'] || empty($seoResult['rows'])) {
    echo "Ошибка: не удалось загрузить SEO URL из mff." . PHP_EOL;
    exit(1);
}

// seoMap[product_id] = keyword
$seoMap = array();
foreach ($seoResult['rows'] as $row) {
    // query вида "product_id=1234"
    $matches = array();
    if (preg_match('/^product_id=(\d+)$/', $row['query'], $matches)) {
        $pid = (int)$matches[1];
        $seoMap[$pid] = $row['keyword'];
    }
}

echo "SEO URL загружено: " . count($seoMap) . PHP_EOL . PHP_EOL;

// ── 3. Собираем данные для обновления ─────────────────────────────────────
$baseUrl  = 'https://menufolder.com.ua/';
$toUpdate = array();  // [ [id_off, url], ... ]
$skipped  = array();

foreach ($productMap as $mffProductId => $idOff) {
    if (!isset($seoMap[$mffProductId])) {
        $skipped[] = array('mff_product_id' => $mffProductId, 'id_off' => $idOff, 'reason' => 'no SEO URL');
        // id_mf обновляем даже без SEO URL
        $toUpdate[] = array(
            'id_off'   => $idOff,
            'id_mf'    => $mffProductId,
            'url'      => null,
        );
        continue;
    }

    $keyword = $seoMap[$mffProductId];
    $url     = $baseUrl . $keyword;

    $toUpdate[] = array(
        'id_off' => $idOff,
        'id_mf'  => $mffProductId,
        'url'    => $url,
    );
}

echo "Готово к обновлению: " . count($toUpdate) . PHP_EOL;
echo "Из них без SEO URL (только id_mf): " . count($skipped) . PHP_EOL . PHP_EOL;

if (empty($toUpdate)) {
    echo "Нечего обновлять." . PHP_EOL;
    exit(0);
}

// ── 4. Обновляем Papir.product_papir.links_mf ─────────────────────────────
if ($dryRun) {
    echo "--- DRY RUN: первые 10 записей ---" . PHP_EOL;
    foreach (array_slice($toUpdate, 0, 10) as $item) {
        $urlStr = $item['url'] !== null ? $item['url'] : '(нет SEO URL)';
        echo "  id_off=" . $item['id_off'] . " id_mf=" . $item['id_mf'] . " -> " . $urlStr . PHP_EOL;
    }
    echo PHP_EOL . "Итого будет обновлено: " . count($toUpdate) . " записей." . PHP_EOL;
    exit(0);
}

echo "Обновляем Papir.product_papir.links_mf..." . PHP_EOL;

$updated = 0;
$notFound = 0;

foreach ($toUpdate as $item) {
    $idOff = (int)$item['id_off'];
    $idMf  = (int)$item['id_mf'];

    if ($item['url'] !== null) {
        $url = Database::escape('Papir', $item['url']);
        $setSql = "`id_mf` = " . $idMf . ", `links_mf` = '" . $url . "'";
    } else {
        $setSql = "`id_mf` = " . $idMf;
    }

    $result = Database::query('Papir',
        "UPDATE `product_papir`
         SET " . $setSql . "
         WHERE `id_off` = " . $idOff
    );

    if ($result['ok']) {
        $affected = isset($result['affected_rows']) ? (int)$result['affected_rows'] : 0;
        if ($affected > 0) {
            $updated++;
        } else {
            $notFound++;
        }
    }
}

echo PHP_EOL . "=== Результат ===" . PHP_EOL;
echo "Обновлено:       " . $updated . PHP_EOL;
echo "Не найдено в Papir: " . $notFound . PHP_EOL;
echo "Пропущено:       " . count($skipped) . PHP_EOL;

if (!empty($skipped) && count($skipped) <= 20) {
    echo PHP_EOL . "Пропущенные товары:" . PHP_EOL;
    foreach ($skipped as $s) {
        echo "  mff_product_id=" . $s['mff_product_id'] . " id_off=" . $s['id_off'] . " (" . $s['reason'] . ")" . PHP_EOL;
    }
}

echo PHP_EOL . "Готово." . PHP_EOL;
