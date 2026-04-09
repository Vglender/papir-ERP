<?php

/**
 * Cron: оновлення кількості товарів на сайтах.
 *
 * Запускати через 5 хвилин після sync_stock.php,
 * який формує product_papir.quantity з ms.stock_ та price_supplier_items.
 *
 * Що робить:
 *   Читає product_papir.quantity (status=1) → оновлює oc_product.quantity
 *   в off (offtorg) та mff.
 *
 * Запуск:
 *   php /var/www/papir/cron/sync_quantity.php
 *
 * Cron (щогодини о :05, 7-22 — одразу після sync_stock.php):
 *   5 7-22 * * * php /var/www/papir/cron/sync_quantity.php >> /var/log/papir/sync_quantity.log 2>&1
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/integrations/opencart2/SiteSyncService.php';

$start = microtime(true);

echo '[' . date('Y-m-d H:i:s') . '] === START QUANTITY SYNC ===' . PHP_EOL;

$result = Database::fetchAll('Papir',
    "SELECT ps.site_id, ps.site_product_id, pp.quantity
     FROM product_site ps
     JOIN product_papir pp ON pp.product_id = ps.product_id
     WHERE pp.status = 1 AND ps.status = 1"
);

if (!$result['ok']) {
    echo '[ERROR] Failed to fetch products' . PHP_EOL;
    exit(1);
}

echo '[INFO] Product-site links: ' . count($result['rows']) . PHP_EOL;

// Group by site_id
$bySite = array();
foreach ($result['rows'] as $row) {
    $siteId = (int)$row['site_id'];
    if (!isset($bySite[$siteId])) $bySite[$siteId] = array();
    $bySite[$siteId][] = array(
        'product_id' => (int)$row['site_product_id'],
        'quantity'   => (int)$row['quantity'],
    );
}

$sync = new SiteSyncService();

foreach ($bySite as $siteId => $items) {
    $site = $sync->getSite($siteId);
    $name = $site ? $site['name'] : "site_{$siteId}";
    $r = $sync->batchQuantity($siteId, $items);
    echo '[INFO] ' . $name . ': ' . ($r['ok'] ? $r['updated'] : 'ERROR') . '/' . count($items) . ' updated' . PHP_EOL;
}

$elapsed = round(microtime(true) - $start, 2);
echo '[' . date('Y-m-d H:i:s') . '] Done. time=' . $elapsed . 's' . PHP_EOL;
