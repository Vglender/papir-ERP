<?php

class StockUpdater
{
    /**
     * Run stock update from MoySklad.
     *
     * @param callable|null $log  Callback: function($message, $type = 'info')
     * @return array
     */
    public function update($log = null)
    {
        // Require lib_stock_update if not already loaded
        require_once __DIR__ . '/../../../src/lib_stock_update.php';

        // 1. MS API → ms.stock_ → Papir.product_stock
        $result = updateStockFromMs(true);

        if ($log !== null && is_callable($log)) {
            if (!empty($result)) {
                $rows = isset($result['rows']) ? (int)$result['rows'] : 0;
                $sum  = isset($result['sum'])  ? $result['sum']       : '0';
                $time = isset($result['time']) ? $result['time']      : '0';
                call_user_func($log, 'Stock updated: ' . $rows . ' rows, sum=' . $sum . ', time=' . $time . 's', 'success');
            }
        }

        // 2. product_stock → price_supplier_items (Склад)
        $warehouse = syncWarehouseStock();
        if ($log !== null && is_callable($log)) {
            call_user_func($log, 'Warehouse synced: ' . $warehouse . ' items', 'info');
        }

        // 3. ms.virtual → price_supplier_items (Виробництво)
        $virtual = syncVirtualStock();
        if ($log !== null && is_callable($log)) {
            call_user_func($log, 'Virtual stock synced: ' . $virtual . ' items', 'info');
        }

        // 4. product_papir.quantity = SUM(price_supplier_items.stock)
        $qty = recalcQuantity();
        if ($log !== null && is_callable($log)) {
            call_user_func($log, 'Quantity recalculated: ' . $qty . ' products', 'success');
        }

        $result['warehouse'] = $warehouse;
        $result['virtual']   = $virtual;
        $result['qty']       = $qty;

        return $result;
    }
}
