<?php

/**
 * Cron: повне оновлення залишків з МойСклад.
 *
 * Ланцюг:
 *   1. МойСклад API → ms.stock_
 *   2. ms.stock_   → Papir.product_stock
 *   3. product_stock → price_supplier_items (поставщик "Склад")
 *   4. recalcQuantity → product_papir.quantity (SUM всіх поставщиків)
 *
 * Запуск:
 *   php /var/www/papir/cron/sync_stock.php
 *
 * Cron (кожні 4 години):
 *   0 * /4 * * * php /var/www/papir/cron/sync_stock.php >> /var/log/papir/sync_stock.log 2>&1
 */

require_once __DIR__ . '/../modules/integrations/AppRegistry.php';
AppRegistry::guard('moysklad');

define('CRON_MODE', true);

require_once __DIR__ . '/../modules/database/database.php';

// Перевизначаємо logLine до включення lib_stock_update.php
// щоб замість HTML виводився чистий текст у лог
function logLine($text, $type = 'info')
{
    echo '[' . strtoupper($type) . '] ' . $text . PHP_EOL;
}

require_once __DIR__ . '/../src/lib_stock_update.php';

$start = microtime(true);

// 1-2. МойСклад API → ms.stock_ → Papir.product_stock
$stockResult = updateStockFromMs(false);

$rows = isset($stockResult['rows']) ? (int)$stockResult['rows'] : 0;
$sum  = isset($stockResult['sum'])  ? $stockResult['sum']       : '0';

// 3. product_stock → price_supplier_items (Склад)
$warehouse = syncWarehouseStock();

// 4. ms.virtual → price_supplier_items (Виробництво)
$virtual = syncVirtualStock();

// 5. product_papir.quantity = SUM(price_supplier_items.stock) всіх поставщиків
$qty = recalcQuantity();

$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . '] Done.'
    . ' stock_rows=' . $rows
    . ' sum=' . $sum
    . ' warehouse=' . $warehouse
    . ' virtual=' . $virtual
    . ' qty=' . $qty
    . ' time=' . $elapsed . 's'
    . PHP_EOL;
