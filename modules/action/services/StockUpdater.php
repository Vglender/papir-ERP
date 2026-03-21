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

        $result = updateStockFromMs(true);

        if ($log !== null && is_callable($log)) {
            if (!empty($result)) {
                $rows = isset($result['rows']) ? (int)$result['rows'] : 0;
                $sum  = isset($result['sum'])  ? $result['sum']       : '0';
                $time = isset($result['time']) ? $result['time']      : '0';
                call_user_func($log, 'Stock updated: ' . $rows . ' rows, sum=' . $sum . ', time=' . $time . 's', 'success');
            }
        }

        return isset($result) ? $result : array('ok' => true);
    }
}
