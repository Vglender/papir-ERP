<?php

/**
 * Репозиторий цен поставщиков (price_supplier_prices).
 */
class SupplierPricesRepository
{
    /**
     * Получить лучшую закупочную цену для товара по model (id_off) или sku.
     *
     * @param string|int $model  product_papir.id_off
     * @param string     $sku    артикул
     * @return array  ['price_cost' => float|null, 'price_rrp' => float|null, 'supplier_name' => string]
     */
    public function getBestCostPrice($model, $sku = '')
    {
        $conditions = array();
        $model = (string)$model;
        $sku   = (string)$sku;

        if ($model !== '' && $model !== '0') {
            $escaped      = Database::escape('Papir', $model);
            $conditions[] = "sp.`model` = '$escaped'";
        }
        if ($sku !== '') {
            $escaped      = Database::escape('Papir', $sku);
            $conditions[] = "sp.`sku` = '$escaped'";
        }

        if (empty($conditions)) {
            return array('price_cost' => null, 'price_rrp' => null, 'supplier_name' => '');
        }

        $where = '(' . implode(' OR ', $conditions) . ')';

        $sql = "SELECT sp.`price_cost`, sp.`price_rrp`, s.`name` AS supplier_name
                FROM `price_supplier_prices` sp
                JOIN `price_suppliers` s ON s.`id` = sp.`supplier_id`
                WHERE s.`is_active` = 1
                  AND $where
                  AND sp.`price_cost` IS NOT NULL
                  AND sp.`price_cost` > 0
                ORDER BY s.`is_cost_source` DESC, sp.`price_cost` ASC
                LIMIT 1";

        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            $row = $result['row'];
            return array(
                'price_cost'    => (float)$row['price_cost'],
                'price_rrp'     => isset($row['price_rrp']) && $row['price_rrp'] !== null ? (float)$row['price_rrp'] : null,
                'supplier_name' => $row['supplier_name'],
            );
        }

        // Только RRP без cost
        $sql2   = "SELECT sp.`price_rrp`, s.`name` AS supplier_name
                   FROM `price_supplier_prices` sp
                   JOIN `price_suppliers` s ON s.`id` = sp.`supplier_id`
                   WHERE s.`is_active` = 1
                     AND $where
                     AND sp.`price_rrp` IS NOT NULL AND sp.`price_rrp` > 0
                   LIMIT 1";
        $result2 = Database::fetchRow('Papir', $sql2);

        if ($result2['ok'] && !empty($result2['row'])) {
            return array(
                'price_cost'    => null,
                'price_rrp'     => (float)$result2['row']['price_rrp'],
                'supplier_name' => $result2['row']['supplier_name'],
            );
        }

        return array('price_cost' => null, 'price_rrp' => null, 'supplier_name' => '');
    }

    /**
     * Пакетная замена всех цен одного поставщика.
     *
     * @param int   $supplierId
     * @param array $rows  [['model'=>, 'sku'=>, 'product_name'=>, 'price_cost'=>, 'price_rrp'=>], ...]
     */
    public function replaceAll($supplierId, array $rows)
    {
        $supplierId = (int)$supplierId;
        $now        = date('Y-m-d H:i:s');

        Database::query('Papir', "DELETE FROM `price_supplier_prices` WHERE `supplier_id` = $supplierId");

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $values = array();
            foreach ($chunk as $r) {
                $modelRaw = isset($r['model'])        ? trim((string)$r['model'])        : '';
                $skuRaw   = isset($r['sku'])          ? trim((string)$r['sku'])          : '';
                $nameRaw  = isset($r['product_name']) ? trim((string)$r['product_name']) : '';

                $modelVal = $modelRaw !== '' ? "'" . Database::escape('Papir', $modelRaw) . "'" : 'NULL';
                $skuVal   = $skuRaw   !== '' ? "'" . Database::escape('Papir', $skuRaw)   . "'" : 'NULL';
                $nameVal  = $nameRaw  !== '' ? "'" . Database::escape('Papir', $nameRaw)  . "'" : 'NULL';
                $costVal  = (isset($r['price_cost']) && $r['price_cost'] !== null && $r['price_cost'] !== '') ? "'" . (float)$r['price_cost'] . "'" : 'NULL';
                $rrpVal   = (isset($r['price_rrp'])  && $r['price_rrp']  !== null && $r['price_rrp']  !== '') ? "'" . (float)$r['price_rrp']  . "'" : 'NULL';

                $values[] = "($supplierId, $modelVal, $skuVal, $nameVal, $costVal, $rrpVal, 'UAH', '$now')";
            }

            Database::query('Papir',
                "INSERT INTO `price_supplier_prices`
                 (`supplier_id`,`model`,`sku`,`product_name`,`price_cost`,`price_rrp`,`currency`,`synced_at`)
                 VALUES " . implode(',', $values)
            );
        }
    }

    /**
     * Статистика по поставщику.
     * @return array  ['total' => int, 'with_cost' => int, 'with_rrp' => int, 'last_sync' => string|null]
     */
    public function getStats($supplierId)
    {
        $supplierId = (int)$supplierId;
        $sql        = "SELECT
                           COUNT(*) AS total,
                           SUM(CASE WHEN `price_cost` IS NOT NULL AND `price_cost` > 0 THEN 1 ELSE 0 END) AS with_cost,
                           SUM(CASE WHEN `price_rrp`  IS NOT NULL AND `price_rrp`  > 0 THEN 1 ELSE 0 END) AS with_rrp,
                           MAX(`synced_at`) AS last_sync
                       FROM `price_supplier_prices`
                       WHERE `supplier_id` = $supplierId";

        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            $row = $result['row'];
            return array(
                'total'     => (int)$row['total'],
                'with_cost' => (int)$row['with_cost'],
                'with_rrp'  => (int)$row['with_rrp'],
                'last_sync' => $row['last_sync'],
            );
        }

        return array('total' => 0, 'with_cost' => 0, 'with_rrp' => 0, 'last_sync' => null);
    }
}
