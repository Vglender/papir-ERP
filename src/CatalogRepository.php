<?php

final class CatalogRepository
{
    /** @var mysqli */
    private $msDb;

    /** @var mysqli */
    private $papirDb;

    /** @var mysqli */
    private $offDb;

    /** @var string */
    private $additionalImageBase = 'https://officetorg.com.ua/image/catalog/product/';

    /** @var array<string, string> */
    private $allowedSort = array(
        'id_off'          => 'id_off',
        'product_article' => 'product_article',
        'name'            => 'name',
        'price_cost'      => 'price_cost',
        'price'           => 'price',
    );

    public function __construct(mysqli $msDb, mysqli $papirDb, mysqli $offDb)
    {
        $this->msDb = $msDb;
        $this->papirDb = $papirDb;
        $this->offDb = $offDb;
    }

    public function normalizeSort($sort)
    {
        $sort = (string)$sort;

        if (!isset($this->allowedSort[$sort])) {
            return 'id_off';
        }

        return $sort;
    }

    public function normalizeOrder($order)
    {
        $order = strtolower((string)$order);

        if ($order !== 'asc' && $order !== 'desc') {
            return 'asc';
        }

        return $order;
    }

    public function getTotalCatalogCount()
    {
        $sql = "SELECT COUNT(*) AS cnt
                FROM `product_papir`
                WHERE `id_off` IS NOT NULL
                  AND `id_off` > 0";

        $res = $this->papirDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['cnt'];
        }

        return 0;
    }

    public function getTotalRows($search, $filter = 'all')
    {
        $whereSql = $this->buildPapirWhereSql($search);

        $sql = "SELECT
                    pp.`product_id`,
                    pp.`id_off`
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                " . $whereSql;

        $res = $this->papirDb->query($sql);

        if (!$res) {
            return 0;
        }

        $rows = array();
        $idOffs = array();

        while ($row = $res->fetch_assoc()) {
            $idOff = (int)$row['id_off'];

            $rows[] = array(
                'product_id' => (int)$row['product_id'],
                'id_off'     => $idOff,
            );

            $idOffs[] = $idOff;
        }

        if (empty($idOffs)) {
            return 0;
        }

        $stockMap = $this->getRealStockMap($idOffs);
        $actionMap = $this->getActiveSpecialMap($idOffs);

        $count = 0;

        foreach ($rows as $row) {
            $idOff = (int)$row['id_off'];
            $realStock = isset($stockMap[$idOff]) ? (int)$stockMap[$idOff] : 0;
            $hasAction = isset($actionMap[$idOff]);

            if ($filter === 'with_stock' && $realStock <= 0) {
                continue;
            }

            if ($filter === 'with_action' && !$hasAction) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    public function getList($search, $filter, $sort, $order, $offset, $limit)
    {
        $sort = $this->normalizeSort($sort);
        $order = $this->normalizeOrder($order);

        $whereSql = $this->buildPapirWhereSql($search);
        $orderBy = $this->buildPapirOrderBy($sort, $order);

        $useSqlLimit = ($filter === 'all');

        $sql = "SELECT
                    pp.`product_id`,
                    pp.`id_off`,
                    pp.`product_article`,
                    pp.`price_cost`,
                    pp.`price`,
                    pp.`image` AS main_image,
                    pp.`status`,
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                " . $whereSql . "
                ORDER BY " . $orderBy;

        if ($useSqlLimit) {
            $sql .= " LIMIT " . (int)$offset . ", " . (int)$limit;
        }

        $res = $this->papirDb->query($sql);

        if (!$res) {
            return false;
        }

        $rows = array();
        $idOffs = array();

        while ($row = $res->fetch_assoc()) {
            $idOff = (int)$row['id_off'];

            $rows[$idOff] = array(
                'product_id'       => (int)$row['product_id'],
                'id_off'           => $idOff,
                'product_article'  => $row['product_article'],
                'name'             => $row['name'],
                'price_cost'       => $row['price_cost'] !== null ? (float)$row['price_cost'] : 0.0,
                'price'            => $row['price'] !== null ? (float)$row['price'] : 0.0,
                'action_price'     => null,
                'real_stock'       => 0,
                'main_image'       => $this->normalizeImageUrl($row['main_image']),
                'status'           => $row['status'] !== null ? (int)$row['status'] : 0,
            );

            $idOffs[] = $idOff;
        }

        if (empty($idOffs)) {
            return new ArrayResult(array());
        }

        $stockMap = $this->getRealStockMap($idOffs);
        $actionMap = $this->getActiveSpecialMap($idOffs);

        foreach ($rows as $idOff => $row) {
            if (isset($stockMap[$idOff])) {
                $rows[$idOff]['real_stock'] = (int)$stockMap[$idOff];
            }

            if (isset($actionMap[$idOff])) {
                $rows[$idOff]['action_price'] = (float)$actionMap[$idOff]['price'];
            }
        }

        if ($filter === 'with_stock') {
            foreach ($rows as $idOff => $row) {
                if ((int)$row['real_stock'] <= 0) {
                    unset($rows[$idOff]);
                }
            }
        } elseif ($filter === 'with_action') {
            foreach ($rows as $idOff => $row) {
                if ($row['action_price'] === null) {
                    unset($rows[$idOff]);
                }
            }
        }

        $rows = array_values($rows);

        if (!$useSqlLimit) {
            $rows = array_slice($rows, (int)$offset, (int)$limit);
        }

        return new ArrayResult($rows);
    }

    public function getPriceListProducts(array $productIds)
    {
        $cleanIds = array();

        foreach ($productIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $cleanIds[] = $id;
            }
        }

        $cleanIds = array_values(array_unique($cleanIds));

        if (empty($cleanIds)) {
            return array();
        }

        $inList = implode(',', $cleanIds);

        $sql = "SELECT
                    pp.`product_id`,
                    pp.`product_article`,
                    pp.`price_sale`,
                    pp.`price_wholesale`,
                    pp.`price_dealer`,
                    ap.`price_act`,
                    pdp.`qty_1`,   pdp.`price_1`,
                    pdp.`qty_2`,   pdp.`price_2`,
                    pdp.`qty_3`,   pdp.`price_3`,
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name,
                    seo_off.`seo_url` AS slug_off,
                    seo_mff.`seo_url` AS slug_mff
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                LEFT JOIN `action_prices` ap
                    ON ap.`product_id` = pp.`product_id`
                LEFT JOIN `product_discount_profile` pdp
                    ON pdp.`product_id` = pp.`product_id`
                LEFT JOIN `product_seo` seo_off
                    ON seo_off.`product_id` = pp.`product_id`
                   AND seo_off.`site_id` = 1 AND seo_off.`language_id` = 2
                LEFT JOIN `product_seo` seo_mff
                    ON seo_mff.`product_id` = pp.`product_id`
                   AND seo_mff.`site_id` = 2 AND seo_mff.`language_id` = 2
                WHERE pp.`product_id` IN (" . $inList . ")
                ORDER BY FIELD(pp.`product_id`, " . $inList . ")";

        $res = $this->papirDb->query($sql);

        if (!$res) {
            return array();
        }

        $items = array();

        while ($row = $res->fetch_assoc()) {
            $productId = (int)$row['product_id'];

            $qtyDiscounts = array();
            for ($i = 1; $i <= 3; $i++) {
                if (!empty($row['qty_' . $i]) && $row['price_' . $i] !== null) {
                    $qtyDiscounts[] = array(
                        'quantity' => (int)$row['qty_' . $i],
                        'price'    => (float)$row['price_' . $i],
                    );
                }
            }

            $items[$productId] = array(
                'id'                 => $productId,
                'article'            => isset($row['product_article']) ? (string)$row['product_article'] : '',
                'name'               => isset($row['name']) ? (string)$row['name'] : '',
                'price_sale'         => $row['price_sale']      !== null ? (float)$row['price_sale']      : null,
                'price_wholesale'    => $row['price_wholesale'] !== null ? (float)$row['price_wholesale'] : null,
                'price_dealer'       => $row['price_dealer']    !== null ? (float)$row['price_dealer']    : null,
                'action_price'       => $row['price_act']       !== null ? (float)$row['price_act']       : null,
                'quantity_discounts' => $qtyDiscounts,
                'url_off'            => $row['slug_off'] ? 'https://officetorg.com.ua/' . $row['slug_off'] : null,
                'url_mff'            => $row['slug_mff'] ? 'https://menufolder.com.ua/' . $row['slug_mff'] : null,
            );
        }

        return array_values($items);
    }

    public function getProductDetails($idOff)
    {
        $idOff = (int)$idOff;

        if ($idOff <= 0) {
            return null;
        }

        $sql = "SELECT
                    pp.*,
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name,
                    m.`name` AS manufacturer_name
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                LEFT JOIN `manufacturers` m ON m.`manufacturer_id` = pp.`manufacturer_id`
                WHERE pp.`id_off` = " . $idOff . "
                LIMIT 1";

        $res = $this->papirDb->query($sql);

        if (!$res || $res->num_rows === 0) {
            return null;
        }

        $product = $res->fetch_assoc();
        $productId = (int)$product['product_id'];

        $details = array(
            'product_id'         => $productId,
            'id_off'             => (int)$product['id_off'],
            'product_article'    => $product['product_article'],
            'status'             => $product['status'] !== null ? (int)$product['status'] : 0,
            'name'               => $product['name'],
            'price_cost'         => $product['price_cost'] !== null ? (float)$product['price_cost'] : 0.0,
            'price_sale'         => isset($product['price_sale']) ? (float)$product['price_sale'] : 0.0,
            'price_rrp'          => $product['price_rrp'] !== null ? (float)$product['price_rrp'] : 0.0,
            'quantity'           => $this->getRealStockByIdOff($idOff),
            'manufacturer_name'  => isset($product['manufacturer_name']) ? $product['manufacturer_name'] : null,
            'manufacturer_id'    => $product['manufacturer_id'],
            'categoria_id'       => $product['categoria_id'],
            'category_name'      => $this->getCategoryNameById((int)$product['categoria_id']),
            'ean'                => $product['ean'],
            'tnved'              => $product['tnved'],
            'unit'               => $product['unit'],
            'packs'              => $product['packs'],
            'links_prom'         => isset($product['links_prom']) ? $product['links_prom'] : null,
            'id_ms'              => $product['id_ms'],
            'id_mf'              => $product['id_mf'],
            'id_prm'             => $product['id_prm'],
            'id_prom'            => $product['id_prom'],
            'date_added'         => $product['date_added'],
            'date_updated'       => $product['date_updated'],
            'main_image'         => $this->normalizeImageUrl($product['image']),
            'descriptions'       => $this->getDescriptionsByProductId($productId),
            'additional_images'  => $this->getAdditionalImagesByProductId($productId),
            'real_stock'         => $this->getRealStockByIdOff($idOff),
            'special'            => $this->getActiveSpecialByIdOff($idOff),
            'discounts'          => $this->getActiveDiscountsByIdOff($idOff),
            'weight'             => $product['weight'] !== null ? (float)$product['weight'] : 0.0,
            'weight_class_id'    => $product['weight_class_id'] !== null ? (int)$product['weight_class_id'] : 0,
            'length'             => $product['length'] !== null ? (float)$product['length'] : 0.0,
            'width'              => $product['width'] !== null ? (float)$product['width'] : 0.0,
            'height'             => $product['height'] !== null ? (float)$product['height'] : 0.0,
            'length_class_id'    => $product['length_class_id'] !== null ? (int)$product['length_class_id'] : 0,
            'virtual_stock'      => $this->getVirtualStockByIdOff($idOff),
        );

        return $details;
    }

    private function getSiteQuantityByIdOff($idOff)
    {
        $idOff = (int)$idOff;

        $sql = "SELECT `quantity`
                FROM `oc_product`
                WHERE `product_id` = " . $idOff . "
                LIMIT 1";

        $res = $this->offDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return $row['quantity'] !== null ? (float)$row['quantity'] : 0.0;
        }

        return 0.0;
    }

    private function getDescriptionsByProductId($productId)
    {
        $productId = (int)$productId;

        $sql = "SELECT
                    `language_id`,
                    `name`,
                    `description`,
                    `short_description`,
                    `meta_title`,
                    `meta_description`,
                    `meta_h1`,
                    `seo_url`
                FROM `product_description`
                WHERE `product_id` = " . $productId . "
                  AND `language_id` IN (1, 2)";

        $res = $this->papirDb->query($sql);

        $descriptions = array(
            1 => array(
                'language_id'       => 1,
                'name'              => '',
                'description'       => '',
                'short_description' => '',
                'meta_title'        => '',
                'meta_description'  => '',
                'meta_h1'           => '',
                'seo_url'           => '',
            ),
            2 => array(
                'language_id'       => 2,
                'name'              => '',
                'description'       => '',
                'short_description' => '',
                'meta_title'        => '',
                'meta_description'  => '',
                'meta_h1'           => '',
                'seo_url'           => '',
            ),
        );

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $langId = (int)$row['language_id'];
                $descriptions[$langId] = $row;
            }
        }

        return $descriptions;
    }

    private function getAdditionalImagesByProductId($productId)
    {
        $productId = (int)$productId;

        $sql = "SELECT
                    `image`,
                    `sort_order`
                FROM `image`
                WHERE `product_id` = " . $productId . "
                ORDER BY `sort_order` ASC, `product_image_id` ASC";

        $res = $this->papirDb->query($sql);

        $images = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $url = $this->normalizeAdditionalImageUrl($row['image']);

                if ($url !== '') {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    private function getRealStockByIdOff($idOff)
    {
        $idOff = (int)$idOff;

        $sql = "SELECT SUM(COALESCE(`stock`, 0)) AS real_stock
                FROM `stock_`
                WHERE CAST(`model` AS UNSIGNED) = " . $idOff;

        $res = $this->msDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return $row['real_stock'] !== null ? (int)$row['real_stock'] : 0;
        }

        return 0;
    }

    private function getRealStockMap(array $idOffs)
    {
        if (empty($idOffs)) {
            return array();
        }

        $ids = array_map('intval', array_unique($idOffs));
        $inList = implode(',', $ids);

        $sql = "SELECT
                    CAST(`model` AS UNSIGNED) AS id_off,
                    SUM(COALESCE(`stock`, 0)) AS real_stock
                FROM `stock_`
                WHERE CAST(`model` AS UNSIGNED) IN (" . $inList . ")
                GROUP BY CAST(`model` AS UNSIGNED)";

        $res = $this->msDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['id_off']] = (int)$row['real_stock'];
            }
        }

        return $map;
    }

    private function getActiveSpecialByIdOff($idOff)
    {
        $map = $this->getActiveSpecialMap(array((int)$idOff));

        return isset($map[(int)$idOff]) ? $map[(int)$idOff] : null;
    }

    private function getActiveSpecialMap(array $idOffs)
    {
        if (empty($idOffs)) {
            return array();
        }

        $ids = array_map('intval', array_unique($idOffs));
        $inList = implode(',', $ids);

        $sql = "SELECT
                    s.`product_id`,
                    MIN(s.`price`) AS price,
                    MIN(s.`priority`) AS priority,
                    MIN(s.`date_start`) AS date_start,
                    MAX(s.`date_end`) AS date_end
                FROM `oc_product_special` s
                INNER JOIN (
                    SELECT
                        `product_id`,
                        MIN(`priority`) AS min_priority
                    FROM `oc_product_special`
                    WHERE `product_id` IN (" . $inList . ")
                      AND `date_start` <= CURDATE()
                      AND `date_end` >= CURDATE()
                    GROUP BY `product_id`
                ) p
                    ON p.`product_id` = s.`product_id`
                   AND p.`min_priority` = s.`priority`
                WHERE s.`product_id` IN (" . $inList . ")
                  AND s.`date_start` <= CURDATE()
                  AND s.`date_end` >= CURDATE()
                GROUP BY s.`product_id`
                ORDER BY s.`product_id` ASC";

        $res = $this->offDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $productId = (int)$row['product_id'];

                $map[$productId] = array(
                    'price'      => (float)$row['price'],
                    'priority'   => (int)$row['priority'],
                    'date_start' => $row['date_start'],
                    'date_end'   => $row['date_end'],
                );
            }
        }

        return $map;
    }
	
	private function getActiveDiscountsByIdOff($idOff)
{
    $idOff = (int)$idOff;

    $sql = "SELECT
                `customer_group_id`,
                `quantity`,
                `priority`,
                `price`,
                `date_start`,
                `date_end`
            FROM `discount`
            WHERE `product_id` = " . $idOff . "
              AND `customer_group_id` IN (1, 2, 3)
            ORDER BY `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

    $res = $this->msDb->query($sql);

    $result = array(
        'wholesale_price'    => null,
        'dealer_price'       => null,
        'quantity_discounts' => array(),
    );

    $seenDiscounts = array();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $groupId = (int)$row['customer_group_id'];
            $quantity = (int)$row['quantity'];
            $price = (float)$row['price'];

            if ($groupId === 2) {
                if ($result['wholesale_price'] === null) {
                    $result['wholesale_price'] = $price;
                }
                continue;
            }

            if ($groupId === 3) {
                if ($result['dealer_price'] === null) {
                    $result['dealer_price'] = $price;
                }
                continue;
            }

            if ($groupId === 1) {
                $key = $quantity . '|' . $price;

                if (!isset($seenDiscounts[$key])) {
                    $seenDiscounts[$key] = true;

                    $result['quantity_discounts'][] = array(
                        'quantity'   => $quantity,
                        'price'      => $price,
                        'priority'   => (int)$row['priority'],
                        'date_start' => $row['date_start'],
                        'date_end'   => $row['date_end'],
                    );
                }
            }
        }
    }

    return $result;
}

/*     private function getActiveDiscountsByIdOff($idOff)
    {
        $idOff = (int)$idOff;

        $sql = "SELECT
                    `customer_group_id`,
                    `quantity`,
                    `priority`,
                    `price`,
                    `date_start`,
                    `date_end`
                FROM `discount`
                WHERE `product_id` = " . $idOff . "
                  AND (`date_start` IS NULL OR `date_start` <= CURDATE())
                  AND (`date_end` IS NULL OR `date_end` >= CURDATE())
                  AND `customer_group_id` IN (1, 2, 3)
                ORDER BY `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

        $res = $this->msDb->query($sql);

        $result = array(
            'wholesale_price'    => null,
            'dealer_price'       => null,
            'quantity_discounts' => array(),
        );

        $seenDiscounts = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $groupId = (int)$row['customer_group_id'];
                $quantity = (int)$row['quantity'];
                $price = (float)$row['price'];

                if ($groupId === 2) {
                    if ($result['wholesale_price'] === null) {
                        $result['wholesale_price'] = $price;
                    }
                    continue;
                }

                if ($groupId === 3) {
                    if ($result['dealer_price'] === null) {
                        $result['dealer_price'] = $price;
                    }
                    continue;
                }

                if ($groupId === 1) {
                    $key = $quantity . '|' . $price;

                    if (!isset($seenDiscounts[$key])) {
                        $seenDiscounts[$key] = true;

                        $result['quantity_discounts'][] = array(
                            'quantity'   => $quantity,
                            'price'      => $price,
                            'priority'   => (int)$row['priority'],
                            'date_start' => $row['date_start'],
                            'date_end'   => $row['date_end'],
                        );
                    }
                }
            }
        }

        return $result;
    } */
	
	private function getActiveDiscountsMap(array $idOffs)
{
    if (empty($idOffs)) {
        return array();
    }

    $ids = array_map('intval', array_unique($idOffs));
    $inList = implode(',', $ids);

    $sql = "SELECT
                `product_id`,
                `customer_group_id`,
                `quantity`,
                `priority`,
                `price`,
                `date_start`,
                `date_end`
            FROM `discount`
            WHERE `product_id` IN (" . $inList . ")
              AND `customer_group_id` IN (1, 2, 3)
            ORDER BY `product_id` ASC, `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

    $res = $this->msDb->query($sql);

    $result = array();

    foreach ($ids as $idOff) {
        $result[$idOff] = array(
            'wholesale_price'    => null,
            'dealer_price'       => null,
            'quantity_discounts' => array(),
        );
    }

    $seenQuantityDiscounts = array();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $productId = (int)$row['product_id'];
            $groupId = (int)$row['customer_group_id'];
            $quantity = (int)$row['quantity'];
            $price = (float)$row['price'];

            if (!isset($result[$productId])) {
                continue;
            }

            if ($groupId === 2) {
                if ($result[$productId]['wholesale_price'] === null) {
                    $result[$productId]['wholesale_price'] = $price;
                }
                continue;
            }

            if ($groupId === 3) {
                if ($result[$productId]['dealer_price'] === null) {
                    $result[$productId]['dealer_price'] = $price;
                }
                continue;
            }

            if ($groupId === 1) {
                if (!isset($seenQuantityDiscounts[$productId])) {
                    $seenQuantityDiscounts[$productId] = array();
                }

                if (isset($seenQuantityDiscounts[$productId][$quantity])) {
                    continue;
                }

                $seenQuantityDiscounts[$productId][$quantity] = true;

                $result[$productId]['quantity_discounts'][] = array(
                    'quantity'   => $quantity,
                    'price'      => $price,
                    'priority'   => (int)$row['priority'],
                    'date_start' => $row['date_start'],
                    'date_end'   => $row['date_end'],
                );
            }
        }
    }

    return $result;
}

/*     public function getActiveDiscountsMap(array $idOffs)
    {    
        if (empty($idOffs)) {
            return array();
        }

        $ids = array_map('intval', array_unique($idOffs));
        $inList = implode(',', $ids);

        $sql = "SELECT
                    `product_id`,
                    `customer_group_id`,
                    `quantity`,
                    `priority`,
                    `price`,
                    `date_start`,
                    `date_end`
                FROM `discount`
                WHERE `product_id` IN (" . $inList . ")
                  AND (`date_start` IS NULL OR `date_start` <= CURDATE())
                  AND (`date_end` IS NULL OR `date_end` >= CURDATE())
                  AND `customer_group_id` IN (1, 2, 3)
                ORDER BY `product_id` ASC, `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

        $res = $this->msDb->query($sql);
		

        $result = array();

        foreach ($ids as $idOff) {
            $result[$idOff] = array(
                'wholesale_price'    => null,
                'dealer_price'       => null,
                'quantity_discounts' => array(),
            );
        }
        echo $result ;
        $seenQuantityDiscounts = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $productId = (int)$row['product_id'];
                $groupId = (int)$row['customer_group_id'];
                $quantity = (int)$row['quantity'];
                $price = (float)$row['price'];

                if (!isset($result[$productId])) {
                    continue;
                }

                if ($groupId === 2) {
                    if ($result[$productId]['wholesale_price'] === null) {
                        $result[$productId]['wholesale_price'] = $price;
                    }
                    continue;
                }

                if ($groupId === 3) {
                    if ($result[$productId]['dealer_price'] === null) {
                        $result[$productId]['dealer_price'] = $price;
                    }
                    continue;
                }

                if ($groupId === 1) {
                    if (!isset($seenQuantityDiscounts[$productId])) {
                        $seenQuantityDiscounts[$productId] = array();
                    }

                    if (isset($seenQuantityDiscounts[$productId][$quantity])) {
                        continue;
                    }

                    $seenQuantityDiscounts[$productId][$quantity] = true;

                    $result[$productId]['quantity_discounts'][] = array(
                        'quantity'   => $quantity,
                        'price'      => $price,
                        'priority'   => (int)$row['priority'],
                        'date_start' => $row['date_start'],
                        'date_end'   => $row['date_end'],
                    );
                }
            }
        }

        return $result;
    } */

    private function getCategoryNameById($categoryId)
    {
        $categoryId = (int)$categoryId;

        if ($categoryId <= 0) {
            return '';
        }

        $sql = "SELECT
                    COALESCE(NULLIF(cd2.`name`, ''), NULLIF(cd1.`name`, ''), '') AS category_name
                FROM (
                    SELECT " . $categoryId . " AS category_id
                ) c
                LEFT JOIN `category_description` cd2
                    ON cd2.`category_id` = c.`category_id`
                   AND cd2.`language_id` = 2
                LEFT JOIN `category_description` cd1
                    ON cd1.`category_id` = c.`category_id`
                   AND cd1.`language_id` = 1
                LIMIT 1";

        $res = $this->papirDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['category_name']) ? (string)$row['category_name'] : '';
        }

        return '';
    }

    private function getVirtualStockByIdOff($idOff)
    {
        $idOff = (int)$idOff;

        $sql = "SELECT SUM(COALESCE(`stock`, 0)) AS virtual_stock
                FROM `virtual`
                WHERE `product_id` = " . $idOff;

        $res = $this->msDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return $row['virtual_stock'] !== null ? (int)$row['virtual_stock'] : 0;
        }

        return 0;
    }

    private function buildPapirWhereSql($search)
    {
        $rawSearch = (string)$search;
        $search = trim($rawSearch);

        if ($search === '') {
            return " WHERE pp.`id_off` IS NOT NULL AND pp.`id_off` > 0";
        }

        $tokens = preg_split('/\s+/u', mb_strtolower($search, 'UTF-8'));
        $tokens = array_filter($tokens, function ($token) {
            return $token !== '';
        });

        $whereParts = array();
        $whereParts[] = "pp.`id_off` IS NOT NULL";
        $whereParts[] = "pp.`id_off` > 0";

        $endsWithSpace = preg_match('/\s$/u', $rawSearch) === 1;
        $singleNumericToken = count($tokens) === 1 && preg_match('/^\d+$/', reset($tokens));

        foreach ($tokens as $token) {
            $tokenEsc = $this->papirDb->real_escape_string($token);

            if ($endsWithSpace && $singleNumericToken) {
                $whereParts[] = "(
                    CAST(pp.`id_off` AS CHAR) LIKE '" . $tokenEsc . "%'
                    OR LOWER(COALESCE(pp.`product_article`, '')) LIKE '" . $tokenEsc . "%'
                )";
            } else {
                $whereParts[] = "(
                    CAST(pp.`id_off` AS CHAR) LIKE '%" . $tokenEsc . "%'
                    OR LOWER(COALESCE(pp.`product_article`, '')) LIKE '%" . $tokenEsc . "%'
                    OR LOWER(COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '')) LIKE '%" . $tokenEsc . "%'
                )";
            }
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
    }

    private function buildPapirOrderBy($sort, $order)
    {
        $direction = strtoupper($order === 'desc' ? 'desc' : 'asc');

        $map = array(
            'id_off'          => 'pp.`id_off`',
            'product_article' => 'pp.`product_article`',
            'name'            => 'name',
            'price_cost'      => 'pp.`price_cost`',
            'price'           => 'pp.`price`',
        );

        $column = isset($map[$sort]) ? $map[$sort] : 'pp.`id_off`';

        return $column . ' ' . $direction;
    }

    private function normalizeImageUrl($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }

        return $this->additionalImageBase . ltrim($value, '/');
    }

    private function normalizeAdditionalImageUrl($value)
    {
        return $this->normalizeImageUrl($value);
    }
}