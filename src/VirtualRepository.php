<?php

final class VirtualRepository
{
    /** @var mysqli */
    private $msDb;

    /** @var mysqli */
    private $papirDb;

    public function __construct(mysqli $msDb, mysqli $papirDb)
    {
        $this->msDb = $msDb;
        $this->papirDb = $papirDb;
    }

    public function save($productId, $virtualStock, $priceCost, $price, $priceRrp)
    {
        $productId    = (int)$productId;
        $virtualStock = (int)$virtualStock;
        $priceCost    = (float)$priceCost;
        $price        = (float)$price;
        $priceRrp     = (float)$priceRrp;

        $papirRow = $this->getPapirRow($productId);
        if ($papirRow === null) {
            return 'Товар не найден в Papir';
        }
        $idOff = (int)$papirRow['id_off'];

        $this->msDb->begin_transaction();
        $this->papirDb->begin_transaction();

        try {
            if ($this->existsVirtual($idOff)) {
                $sqlVirtual = "UPDATE `virtual`
                               SET `stock` = " . $virtualStock . "
                               WHERE `product_id` = " . $idOff;
            } else {
                $virtualName    = $this->getProductNameForVirtual($productId);
                $virtualNameEsc = $this->msDb->real_escape_string($virtualName);

                $sqlVirtual = "INSERT INTO `virtual`
                               SET `product_id` = " . $idOff . ",
                                   `name` = '" . $virtualNameEsc . "',
                                   `stock` = " . $virtualStock;
            }

            if (!$this->msDb->query($sqlVirtual)) {
                throw new Exception('Ошибка сохранения virtual: ' . $this->msDb->error);
            }

            $sqlProduct = "UPDATE `product_papir`
                           SET `price_cost` = " . $priceCost . ",
                               `price` = " . $price . ",
                               `price_rrp` = " . $priceRrp . "
                           WHERE `product_id` = " . $productId . "
                           LIMIT 1";

            if (!$this->papirDb->query($sqlProduct)) {
                throw new Exception('Ошибка сохранения product_papir: ' . $this->papirDb->error);
            }

            $this->msDb->commit();
            $this->papirDb->commit();

            return true;
        } catch (Exception $e) {
            $this->msDb->rollback();
            $this->papirDb->rollback();

            return $e->getMessage();
        }
    }

    public function deleteVirtual($productId)
    {
        $idOff = $this->getIdOff($productId);
        if ($idOff <= 0) {
            return false;
        }

        $sql = "DELETE FROM `virtual`
                WHERE `product_id` = " . $idOff;

        return $this->msDb->query($sql);
    }

    public function getEditRow($productId)
    {
        $productId = (int)$productId;

        $papirRow = $this->getPapirRow($productId);
        if ($papirRow === null) {
            return null;
        }

        $idOff = (int)$papirRow['id_off'];
        $msRow = $this->getMsRow($idOff);

        return array(
            'product_id'    => $papirRow['product_id'],
            'name'          => $papirRow['name'],
            'virtual_stock' => $msRow !== null ? $msRow['virtual_stock'] : 0,
            'real_stock'    => $msRow !== null ? $msRow['real_stock'] : 0,
            'price_cost'    => $papirRow['price_cost'],
            'price'         => $papirRow['price'],
            'price_rrp'     => $papirRow['price_rrp'],
        );
    }

    // Returns id_off for a given Papir product_id (needed for ms operations)
    private function getIdOff($productId)
    {
        $sql = "SELECT `id_off` FROM `product_papir` WHERE `product_id` = " . (int)$productId . " LIMIT 1";
        $res = $this->papirDb->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['id_off'];
        }
        return 0;
    }

    // Returns true if ms.virtual has an entry for this id_off
    private function existsVirtual($idOff)
    {
        $sql = "SELECT product_id FROM `virtual` WHERE product_id = " . (int)$idOff . " LIMIT 1";
        $res = $this->msDb->query($sql);
        return $res && $res->num_rows > 0;
    }

    private function getPapirRow($productId)
    {
        $sql = "SELECT
                    pp.`product_id`,
                    pp.`id_off`,
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name,
                    COALESCE(pp.`price_cost`, 0) AS price_cost,
                    COALESCE(pp.`price`, 0) AS price,
                    COALESCE(pp.`price_rrp`, 0) AS price_rrp
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                WHERE pp.`product_id` = " . (int)$productId . "
                LIMIT 1";

        $res = $this->papirDb->query($sql);

        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return null;
    }

    // Queries ms tables using id_off (ms.virtual.product_id = id_off, ms.stock_.model = id_off)
    private function getMsRow($idOff)
    {
        $sql = "SELECT
                    v.`product_id`,
                    COALESCE(v.`stock`, 0) AS virtual_stock,
                    COALESCE(s.`stock`, 0) AS real_stock
                FROM (SELECT " . (int)$idOff . " AS product_id) t
                LEFT JOIN `virtual` v
                    ON v.`product_id` = t.`product_id`
                LEFT JOIN `stock_` s
                    ON CAST(s.`model` AS UNSIGNED) = t.`product_id`
                LIMIT 1";

        $res = $this->msDb->query($sql);

        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return array(
            'product_id'    => $idOff,
            'virtual_stock' => 0,
            'real_stock'    => 0,
        );
    }

    private function getProductNameForVirtual($productId)
    {
        $sql = "SELECT
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                WHERE pp.`product_id` = " . (int)$productId . "
                LIMIT 1";

        $res = $this->papirDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['name']) ? (string)$row['name'] : '';
        }

        return '';
    }
}
