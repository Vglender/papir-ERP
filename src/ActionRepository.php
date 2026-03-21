<?php

final class ActionRepository
{
    /** @var mysqli */
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function exists($productId)
    {
        $sql = "SELECT product_id
                FROM `action`
                WHERE product_id = " . (int)$productId . "
                LIMIT 1";

        $res = $this->mysqli->query($sql);

        return $res && $res->num_rows > 0;
    }

    public function save($productId, $discount, $superDiscont)
    {
        $productId = (int)$productId;
        $discount = (int)$discount;
        $superDiscont = (int)$superDiscont;

        if ($this->exists($productId)) {
            $sql = "UPDATE `action`
                    SET `discount` = " . $discount . ",
                        `super_discont` = " . $superDiscont . ",
                        `updated_at` = NOW()
                    WHERE `product_id` = " . $productId;
        } else {
            $sql = "INSERT INTO `action`
                    SET `product_id` = " . $productId . ",
                        `discount` = " . $discount . ",
                        `super_discont` = " . $superDiscont . ",
                        `updated_at` = NOW()";
        }

        return $this->mysqli->query($sql);
    }

    public function delete($productId)
    {
        $sql = "DELETE FROM `action`
                WHERE `product_id` = " . (int)$productId;

        return $this->mysqli->query($sql);
    }

    public function getEditRow($productId)
    {
        $sql = "SELECT
                    CAST(s.`model` AS UNSIGNED) AS product_id,
                    s.`name`,
                    s.`stock`,
                    s.`price`,
                    COALESCE(a.`discount`, 0) AS discount,
                    COALESCE(a.`super_discont`, 0) AS super_discont
                FROM `stock_` s
                LEFT JOIN `action` a
                    ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
                WHERE CAST(s.`model` AS UNSIGNED) = " . (int)$productId . "
                  AND s.`stock` > 0
                LIMIT 1";

        $res = $this->mysqli->query($sql);

        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return null;
    }
}