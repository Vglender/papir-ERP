<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../modules/database/database.php';
require_once __DIR__ . '/../../../src/lib_stock_update.php';

$start = microtime(true);

// 1. Копируем ms.stock_ -> Papir.product_stock (только DB, без API МойСклад)
Database::query('Papir', "DELETE FROM `product_stock`");
$copyResult = Database::query('Papir',
    "INSERT INTO `product_stock` (model,sku,externalCode,quantity,reserve,inTransit,stock,price,salePrice,stockDays,id_ms,date,outcome,name)
     SELECT model,sku,externalCode,quantity,reserve,inTransit,stock,price,salePrice,stockDays,id_ms,date,outcome,name FROM ms.stock_"
);
$stockRows = $copyResult['ok'] ? (int)$copyResult['affected_rows'] : 0;

// 2. Синхронизируем виртуальные остатки (ms.virtual -> price_supplier_items, Производство)
$virtual = syncVirtualStock();

// 3. Синхронизируем остатки Склада (product_stock -> price_supplier_items, Склад)
$warehouse = syncWarehouseStock();

// 4. Пересчитываем quantity в product_papir
$qty = recalcQuantity();

// Для quantity: MySQL возвращает 0 если значения не изменились,
// считаем количество записей с заполненным quantity
$qtyCount = Database::fetchRow('Papir', "SELECT COUNT(*) as cnt FROM product_papir WHERE quantity > 0 AND status = 1");
$qtyTotal = $qtyCount['ok'] ? (int)$qtyCount['row']['cnt'] : 0;

echo json_encode(array(
    'ok'               => true,
    'stock_rows'       => $stockRows,
    'virtual_synced'   => $virtual,
    'quantity_updated' => $qtyTotal,
    'time'             => round(microtime(true) - $start, 2)
));
