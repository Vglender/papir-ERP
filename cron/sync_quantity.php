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

$start = microtime(true);

echo '[' . date('Y-m-d H:i:s') . '] === START QUANTITY SYNC ===' . PHP_EOL;

$result = Database::fetchAll('Papir',
    "SELECT id_off, id_mf, quantity FROM product_papir WHERE status = 1"
);

if (!$result['ok']) {
    echo '[ERROR] Failed to fetch products' . PHP_EOL;
    exit(1);
}

$rows = $result['rows'];

echo '[INFO] Products: ' . count($rows) . PHP_EOL;

$offTotal   = 0;
$offChanged = 0;
$mffTotal   = 0;
$mffChanged = 0;

foreach ($rows as $row) {
    $quantity = (int)$row['quantity'];

    if (!empty($row['id_off'])) {
        $offTotal++;
        $r = Database::update('off', 'oc_product',
            array('quantity' => $quantity),
            array('product_id' => (int)$row['id_off'])
        );
        if ($r['ok'] && isset($r['affected_rows']) && $r['affected_rows'] > 0) {
            $offChanged++;
        }
    }

    if (!empty($row['id_mf'])) {
        $mffTotal++;
        $r = Database::update('mff', 'oc_product',
            array('quantity' => $quantity),
            array('product_id' => (int)$row['id_mf'])
        );
        if ($r['ok'] && isset($r['affected_rows']) && $r['affected_rows'] > 0) {
            $mffChanged++;
        }
    }
}

$elapsed = round(microtime(true) - $start, 2);

echo '[INFO] off: ' . $offChanged . '/' . $offTotal . ' changed' . PHP_EOL;
echo '[INFO] mff: ' . $mffChanged . '/' . $mffTotal . ' changed' . PHP_EOL;
echo '[' . date('Y-m-d H:i:s') . '] Done.'
    . ' off=' . $offChanged . '/' . $offTotal
    . ' mff=' . $mffChanged . '/' . $mffTotal
    . ' time=' . $elapsed . 's'
    . PHP_EOL;
