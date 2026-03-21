<?php

class ActionRepository
{
    /**
     * UPSERT: INSERT with ON DUPLICATE KEY UPDATE
     *
     * @param int $productId
     * @param int $discount
     * @param int $superDiscont
     * @return array
     */
    public function save($productId, $discount, $superDiscont)
    {
        $productId    = (int)$productId;
        $discount     = (int)$discount;
        $superDiscont = (int)$superDiscont;

        $sql = "INSERT INTO `action_products`
                    (`product_id`, `discount`, `super_discont`, `updated_at`)
                VALUES
                    (" . $productId . ", " . $discount . ", " . $superDiscont . ", NOW())
                ON DUPLICATE KEY UPDATE
                    `discount`      = " . $discount . ",
                    `super_discont` = " . $superDiscont . ",
                    `updated_at`    = NOW()";

        return Database::query('Papir', $sql);
    }

    /**
     * Delete action for a product.
     *
     * @param int $productId
     * @return array
     */
    public function delete($productId)
    {
        $productId = (int)$productId;

        $sql = "DELETE FROM `action_products` WHERE `product_id` = " . $productId;

        return Database::query('Papir', $sql);
    }

    /**
     * Get all action rows.
     *
     * @return array
     */
    public function getAll()
    {
        $sql = "SELECT `product_id`, `discount`, `super_discont`, `updated_at`
                FROM `action_products`
                ORDER BY `product_id` ASC";

        $result = Database::fetchAll('Papir', $sql);

        if ($result['ok'] && !empty($result['rows'])) {
            return $result['rows'];
        }

        return array();
    }

    /**
     * Get single row by product_id, or null if not found.
     *
     * @param int $productId
     * @return array|null
     */
    public function getByProductId($productId)
    {
        $productId = (int)$productId;

        $sql = "SELECT `product_id`, `discount`, `super_discont`, `updated_at`
                FROM `action_products`
                WHERE `product_id` = " . $productId . "
                LIMIT 1";

        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return $result['row'];
        }

        return null;
    }
}
