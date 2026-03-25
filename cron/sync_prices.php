<?php

/**
 * Cron: повний перерахунок цін і вигрузка на сайти та в МойСклад.
 *
 * Ланцюг:
 *   1. syncProductStatuses   — синхронізація статусів по активних прайсах
 *   2. DiscountProfileBuilder — перерахунок цін + дисконтів для кожного товару
 *   3. OpenCartPriceExport   — вигрузка цін + дисконтів + quantity в off і mff
 *   4. MoySkladPriceExport   — вигрузка цін в МойСклад (~15 req/s)
 *
 * Запуск:
 *   php /var/www/papir/cron/sync_prices.php
 *
 * Cron (раз на добу о 01:00):
 *   0 1 * * * php /var/www/papir/cron/sync_prices.php >> /var/log/papir/sync_prices.log 2>&1
 */

define('CRON_MODE', true);

function logLine($text, $type = 'info')
{
    echo '[' . strtoupper($type) . '] ' . $text . PHP_EOL;
}

require_once __DIR__ . '/../modules/prices/prices_bootstrap.php';
require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';

$start = microtime(true);

echo '[' . date('Y-m-d H:i:s') . '] === START PRICE SYNC ===' . PHP_EOL;

// ── Phase 1: Recalculate all prices ─────────────────────────────────────────

logLine('Phase 1: Recalculating prices...', 'info');

$itemRepo    = new PricelistItemRepository();
$productRepo = new ProductPriceRepository();

$itemRepo->syncProductStatuses();
logLine('Product statuses synced', 'info');

$engine  = PriceEngine::create($itemRepo);
$builder = new DiscountProfileBuilder(
    $engine,
    $productRepo,
    new DiscountStrategyRepository(),
    new QuantityStrategyRepository(),
    new ProductPackageRepository(),
    new ProductDiscountProfileRepository(),
    new GlobalSettingsRepository()
);

$offset    = 0;
$batchSize = 100;
$processed = 0;
$calcErrors = 0;

do {
    $page = $productRepo->getList(array(), 'product_id', 'asc', $offset, $batchSize);
    if (!$page['ok'] || empty($page['rows'])) {
        break;
    }

    foreach ($page['rows'] as $row) {
        $result = $builder->build((int)$row['product_id']);
        if ($result['ok']) {
            $processed++;
        } else {
            $calcErrors++;
        }
    }

    $offset += count($page['rows']);
} while (count($page['rows']) === $batchSize);

logLine('Phase 1 done: processed=' . $processed . ' errors=' . $calcErrors, 'success');

// ── Phase 2: Push to sites (off + mff) ──────────────────────────────────────

logLine('Phase 2: Pushing to sites...', 'info');

$ocExporter = new OpenCartPriceExport();
$offset     = 0;
$batchSize  = 50;
$pushOff    = 0;
$pushMff    = 0;

do {
    $batchResult = Database::fetchAll('Papir',
        "SELECT p.product_id, p.product_article, p.id_off, p.id_mf, p.id_ms,
                p.price_purchase, p.price_sale, p.price_wholesale, p.price_dealer, p.quantity,
                p.link_off, p.links_mf, p.links_prom,
                dp.qty_1, dp.price_1, dp.qty_2, dp.price_2, dp.qty_3, dp.price_3
         FROM product_papir p
         LEFT JOIN product_discount_profile dp ON dp.product_id = p.product_id
         WHERE p.status = 1 AND p.price_sale > 0
         ORDER BY p.product_id ASC
         LIMIT " . $batchSize . " OFFSET " . $offset
    );

    if (!$batchResult['ok'] || empty($batchResult['rows'])) {
        break;
    }

    $rows = $batchResult['rows'];

    $rOff = $ocExporter->pushBatch('off', $rows, 'id_off');
    $pushOff += $rOff['pushed'];

    $rMff = $ocExporter->pushBatch('mff', $rows, 'id_mf');
    $pushMff += $rMff['pushed'];

    $offset += count($rows);
} while (count($rows) === $batchSize);

logLine('Phase 2 done: off=' . $pushOff . ' mff=' . $pushMff, 'success');

// ── Phase 3: Push to MoySklad ────────────────────────────────────────────────

logLine('Phase 3: Pushing to MoySklad (~15 req/s)...', 'info');

$msExporter = new MoySkladPriceExport(new MoySkladApi());
$offset     = 0;
$batchSize  = 50;
$pushMs     = 0;

do {
    $batchResult = Database::fetchAll('Papir',
        "SELECT p.product_id, p.product_article, p.id_off, p.id_mf, p.id_ms,
                p.price_purchase, p.price_sale, p.price_wholesale, p.price_dealer, p.quantity,
                p.link_off, p.links_mf, p.links_prom,
                dp.qty_1, dp.price_1, dp.qty_2, dp.price_2, dp.qty_3, dp.price_3
         FROM product_papir p
         LEFT JOIN product_discount_profile dp ON dp.product_id = p.product_id
         WHERE p.status = 1 AND p.price_sale > 0
         ORDER BY p.product_id ASC
         LIMIT " . $batchSize . " OFFSET " . $offset
    );

    if (!$batchResult['ok'] || empty($batchResult['rows'])) {
        break;
    }

    $rows = $batchResult['rows'];

    $rMs = $msExporter->pushBatch($rows);
    $pushMs += $rMs['pushed'];

    $offset += count($rows);
    logLine('MoySklad pushed: ' . $pushMs, 'progress');
} while (count($rows) === $batchSize);

logLine('Phase 3 done: ms=' . $pushMs, 'success');

// ────────────────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . '] Done.'
    . ' recalculated=' . $processed
    . ' off=' . $pushOff
    . ' mff=' . $pushMff
    . ' ms=' . $pushMs
    . ' time=' . $elapsed . 's'
    . PHP_EOL;
