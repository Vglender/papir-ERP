<?php

class ActionPriceRepository
{
    /**
     * Bulk REPLACE INTO action_prices in chunks of 500.
     *
     * Each row must have: product_id, price_act, price_base, price_cost,
     * stock, discount, super_discont, discount_type, calculated_at
     *
     * @param array $rows
     * @return array
     */
    public function saveAll($rows)
    {
        if (empty($rows)) {
            return array('ok' => true, 'saved' => 0);
        }

        $chunkSize = 500;
        $total     = count($rows);
        $saved     = 0;

        for ($i = 0; $i < $total; $i += $chunkSize) {
            $chunk  = array_slice($rows, $i, $chunkSize);
            $values = array();

            foreach ($chunk as $row) {
                $productId    = (int)$row['product_id'];
                $priceAct     = (float)$row['price_act'];
                $priceBase    = (float)$row['price_base'];
                $priceCost    = (float)$row['price_cost'];
                $stock        = (int)$row['stock'];
                $discount     = (int)$row['discount'];
                $superDiscont = (int)$row['super_discont'];
                $discountType = Database::escape('Papir', $row['discount_type']);
                $calculatedAt = Database::escape('Papir', $row['calculated_at']);

                $values[] = "(" . $productId . ", "
                    . $priceAct . ", "
                    . $priceBase . ", "
                    . $priceCost . ", "
                    . $stock . ", "
                    . $discount . ", "
                    . $superDiscont . ", "
                    . "'" . $discountType . "', "
                    . "'" . $calculatedAt . "', "
                    . "NULL)";
            }

            $sql = "REPLACE INTO `action_prices`
                        (`product_id`, `price_act`, `price_base`, `price_cost`,
                         `stock`, `discount`, `super_discont`, `discount_type`,
                         `calculated_at`, `published_at`)
                    VALUES " . implode(', ', $values);

            $result = Database::query('Papir', $sql);

            if (!$result['ok']) {
                return array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Unknown error', 'saved' => $saved);
            }

            $saved += count($chunk);
        }

        return array('ok' => true, 'saved' => $saved);
    }

    /**
     * Get all rows from action_prices.
     *
     * @return array
     */
    public function getAll()
    {
        $sql = "SELECT `product_id`, `price_act`, `price_base`, `price_cost`,
                       `stock`, `discount`, `super_discont`, `discount_type`,
                       `calculated_at`, `published_at`
                FROM `action_prices`
                ORDER BY `product_id` ASC";

        $result = Database::fetchAll('Papir', $sql);

        if ($result['ok'] && !empty($result['rows'])) {
            return $result['rows'];
        }

        return array();
    }

    /**
     * Delete action_prices row for a single product.
     *
     * @param int $productId
     * @return array
     */
    public function deleteByProductId($productId)
    {
        $productId = (int)$productId;
        return Database::query('Papir', "DELETE FROM `action_prices` WHERE `product_id` = " . $productId);
    }

    /**
     * Count all rows in action_prices.
     *
     * @return int
     */
    public function countAll()
    {
        $sql    = "SELECT COUNT(*) AS cnt FROM `action_prices`";
        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return (int)$result['row']['cnt'];
        }

        return 0;
    }

    /**
     * Mark rows as published (set published_at = NOW()) for given product IDs.
     *
     * @param array $productIds
     * @return array
     */
    public function markPublished($productIds)
    {
        if (empty($productIds)) {
            return array('ok' => true);
        }

        $ids = array();
        foreach ($productIds as $pid) {
            $ids[] = (int)$pid;
        }

        $inList = implode(',', $ids);

        $sql = "UPDATE `action_prices`
                SET `published_at` = NOW()
                WHERE `product_id` IN (" . $inList . ")";

        return Database::query('Papir', $sql);
    }

    /**
     * Count rows where published_at IS NULL or published_at < calculated_at.
     *
     * @return int
     */
    public function getPendingPublishCount()
    {
        $sql = "SELECT COUNT(*) AS cnt
                FROM `action_prices`
                WHERE `published_at` IS NULL
                   OR `published_at` < `calculated_at`";

        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return (int)$result['row']['cnt'];
        }

        return 0;
    }
}
