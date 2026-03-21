<?php

final class PriceRepository
{
    /** @var mysqli */
    private $msDb;

    /** @var mysqli */
    private $papirDb;

    /** @var mysqli */
    private $offDb;

    /** @var array<string, string> */
    private $allowedSort = array(
        'id_off'           => 'id_off',
        'product_article'  => 'product_article',
        'name'             => 'name',
        'price'            => 'price',
        'action_price'     => 'action_price',
        'wholesale_price'  => 'wholesale_price',
        'dealer_price'     => 'dealer_price',
        'price_cost'       => 'price_cost',
        'price_rrp'        => 'price_rrp',
        'strategy_name'    => 'strategy_name',
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

    public function getStrategies()
    {
        $sql = "SELECT
                    `strategy_id`,
                    `name`
                FROM `price_strategy`
                WHERE `is_active` = 1
                ORDER BY `sort_order` ASC, `name` ASC";

        $res = $this->papirDb->query($sql);

        $rows = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = array(
                    'strategy_id' => (int)$row['strategy_id'],
                    'name'        => (string)$row['name'],
                );
            }
        }

        return $rows;
    }

    public function getTotalRows($search, $filter, $strategyId)
    {
        $list = $this->getPreparedRows($search, $filter, $strategyId, 'id_off', 'asc');
        return count($list);
    }

    public function getList($search, $filter, $strategyId, $sort, $order, $offset, $limit)
    {
        $sort = $this->normalizeSort($sort);
        $order = $this->normalizeOrder($order);

        $rows = $this->getPreparedRows($search, $filter, $strategyId, $sort, $order);

        if (empty($rows)) {
            return new ArrayResult(array());
        }

        $pagedRows = array_slice($rows, (int)$offset, (int)$limit);

        return new ArrayResult($pagedRows);
    }

public function getProductDetails($idOff)
{
    $idOff = (int)$idOff;

    if ($idOff <= 0) {
        return null;
    }

    $sql = "SELECT
                pp.`product_id`,
                pp.`id_off`,
                pp.`product_article`,
                pp.`price`,
                pp.`price_cost`,
                pp.`price_rrp`,
                pp.`status`,
                pp.`manufacturer_name`,
                pp.`categoria_id`,
                COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name,

                pps.`strategy_id`,
                pps.`manual_price_enabled`,
                pps.`manual_price`,
                pps.`manual_wholesale_enabled`,
                pps.`manual_wholesale_price`,
                pps.`manual_dealer_enabled`,
                pps.`manual_dealer_price`,
                pps.`manual_rrp_enabled`,
                pps.`manual_rrp`,
                pps.`manual_cost_enabled`,
                pps.`manual_cost`,
                pps.`disable_auto_quantity_discounts`,
                pps.`is_locked`,
                pps.`comment`,

                ps.`name` AS strategy_name
            FROM `product_papir` pp
            LEFT JOIN `product_description` pd2
                ON pd2.`product_id` = pp.`product_id`
               AND pd2.`language_id` = 2
            LEFT JOIN `product_description` pd1
                ON pd1.`product_id` = pp.`product_id`
               AND pd1.`language_id` = 1
            LEFT JOIN `product_price_settings` pps
                ON pps.`product_id` = pp.`product_id`
            LEFT JOIN `price_strategy` ps
                ON ps.`strategy_id` = pps.`strategy_id`
            WHERE pp.`id_off` = " . $idOff . "
            LIMIT 1";

    $res = $this->papirDb->query($sql);

    if (!$res || $res->num_rows === 0) {
        return null;
    }

    $row = $res->fetch_assoc();
    $productId = (int)$row['product_id'];
    $strategyId = isset($row['strategy_id']) ? (int)$row['strategy_id'] : 0;

    $settings = array(
        'strategy_id'                       => $strategyId,
        'manual_price_enabled'             => isset($row['manual_price_enabled']) ? (int)$row['manual_price_enabled'] : 0,
        'manual_price'                     => $row['manual_price'] !== null ? (float)$row['manual_price'] : null,
        'manual_wholesale_enabled'         => isset($row['manual_wholesale_enabled']) ? (int)$row['manual_wholesale_enabled'] : 0,
        'manual_wholesale_price'           => $row['manual_wholesale_price'] !== null ? (float)$row['manual_wholesale_price'] : null,
        'manual_dealer_enabled'            => isset($row['manual_dealer_enabled']) ? (int)$row['manual_dealer_enabled'] : 0,
        'manual_dealer_price'              => $row['manual_dealer_price'] !== null ? (float)$row['manual_dealer_price'] : null,
        'manual_rrp_enabled'               => isset($row['manual_rrp_enabled']) ? (int)$row['manual_rrp_enabled'] : 0,
        'manual_rrp'                       => $row['manual_rrp'] !== null ? (float)$row['manual_rrp'] : null,
        'manual_cost_enabled'              => isset($row['manual_cost_enabled']) ? (int)$row['manual_cost_enabled'] : 0,
        'manual_cost'                      => $row['manual_cost'] !== null ? (float)$row['manual_cost'] : null,
        'disable_auto_quantity_discounts'  => isset($row['disable_auto_quantity_discounts']) ? (int)$row['disable_auto_quantity_discounts'] : 0,
        'is_locked'                        => isset($row['is_locked']) ? (int)$row['is_locked'] : 0,
        'comment'                          => isset($row['comment']) ? (string)$row['comment'] : '',
        'strategy_name'                    => isset($row['strategy_name']) ? (string)$row['strategy_name'] : '',
    );

    $strategyRule = null;
    if ($strategyId > 0) {
        $strategyRule = $this->getStrategyRule($strategyId);
    }

    $discounts = $this->getActiveDiscountsByIdOff($idOff);
    $special = $this->getActiveSpecialByIdOff($idOff);

    return array(
        'product_id'              => $productId,
        'id_off'                  => (int)$row['id_off'],
        'product_article'         => (string)$row['product_article'],
        'name'                    => (string)$row['name'],
        'status'                  => $row['status'] !== null ? (int)$row['status'] : 0,
        'manufacturer_name'       => (string)$row['manufacturer_name'],
        'categoria_id'            => $row['categoria_id'] !== null ? (int)$row['categoria_id'] : 0,

        'price'                   => $row['price'] !== null ? (float)$row['price'] : 0.0,
        'price_cost'              => $row['price_cost'] !== null ? (float)$row['price_cost'] : 0.0,
        'price_rrp'               => $row['price_rrp'] !== null ? (float)$row['price_rrp'] : 0.0,

        'real_stock'              => $this->getRealStockByIdOff($idOff),
        'special'                 => $special,

        'wholesale_price'         => $discounts['wholesale_price'],
        'dealer_price'            => $discounts['dealer_price'],
        'quantity_discounts'      => $discounts['quantity_discounts'],

        'settings'                => $settings,
        'strategy_rule'           => $strategyRule,
    );
}

    private function getPreparedRows($search, $filter, $strategyId, $sort, $order)
    {
        $baseRows = $this->getBaseRows($search, $strategyId);

        if (empty($baseRows)) {
            return array();
        }

        $idOffs = array();
        $productIds = array();

        foreach ($baseRows as $row) {
            $idOffs[] = (int)$row['id_off'];
            $productIds[] = (int)$row['product_id'];
        }

        $stockMap = $this->getRealStockMap($idOffs);
        $specialMap = $this->getActiveSpecialMap($idOffs);
        $discountMap = $this->getDiscountSummaryMap($idOffs);
        $settingsMap = $this->getProductPriceSettingsMap($productIds);
        $strategyNameMap = $this->getStrategyNameMap();

        $rows = array();

        foreach ($baseRows as $row) {
            $idOff = (int)$row['id_off'];
            $productId = (int)$row['product_id'];

            $settings = isset($settingsMap[$productId]) ? $settingsMap[$productId] : $this->getDefaultSettings();
            $strategyName = '';

            if (!empty($settings['strategy_id']) && isset($strategyNameMap[(int)$settings['strategy_id']])) {
                $strategyName = $strategyNameMap[(int)$settings['strategy_id']];
            }

            $prepared = array(
                'product_id'            => $productId,
                'id_off'                => $idOff,
                'product_article'       => (string)$row['product_article'],
                'name'                  => (string)$row['name'],
                'price'                 => $row['price'] !== null ? (float)$row['price'] : 0.0,
                'price_cost'            => $row['price_cost'] !== null ? (float)$row['price_cost'] : 0.0,
                'price_rrp'             => $row['price_rrp'] !== null ? (float)$row['price_rrp'] : 0.0,
                'status'                => $row['status'] !== null ? (int)$row['status'] : 0,
                'real_stock'            => isset($stockMap[$idOff]) ? (int)$stockMap[$idOff] : 0,
                'action_price'          => isset($specialMap[$idOff]) ? (float)$specialMap[$idOff]['price'] : null,
                'wholesale_price'       => isset($discountMap[$idOff]) ? $discountMap[$idOff]['wholesale_price'] : null,
                'dealer_price'          => isset($discountMap[$idOff]) ? $discountMap[$idOff]['dealer_price'] : null,
                'quantity_discount_cnt' => isset($discountMap[$idOff]) ? (int)$discountMap[$idOff]['quantity_discount_cnt'] : 0,
                'strategy_id'           => (int)$settings['strategy_id'],
                'strategy_name'         => $strategyName,
                'has_manual_override'   => (
                    !empty($settings['manual_price_enabled']) ||
                    !empty($settings['manual_wholesale_enabled']) ||
                    !empty($settings['manual_dealer_enabled']) ||
                    !empty($settings['manual_rrp_enabled']) ||
                    !empty($settings['manual_cost_enabled'])
                ),
            );

            if ($filter === 'with_action' && $prepared['action_price'] === null) {
                continue;
            }

            if ($filter === 'with_stock' && (int)$prepared['real_stock'] <= 0) {
                continue;
            }

            if ($filter === 'manual_only' && !$prepared['has_manual_override']) {
                continue;
            }

            $rows[] = $prepared;
        }

        usort($rows, function ($a, $b) use ($sort, $order) {
            $valueA = isset($a[$sort]) ? $a[$sort] : null;
            $valueB = isset($b[$sort]) ? $b[$sort] : null;

            if (is_numeric($valueA) && is_numeric($valueB)) {
                $valueA = (float)$valueA;
                $valueB = (float)$valueB;
            } else {
                $valueA = mb_strtolower((string)$valueA, 'UTF-8');
                $valueB = mb_strtolower((string)$valueB, 'UTF-8');
            }

            if ($valueA == $valueB) {
                return 0;
            }

            $result = ($valueA < $valueB) ? -1 : 1;

            return $order === 'desc' ? -$result : $result;
        });

        return $rows;
    }

    private function getBaseRows($search, $strategyId)
    {
        $whereSql = $this->buildBaseWhereSql($search, $strategyId);

        $sql = "SELECT
                    pp.`product_id`,
                    pp.`id_off`,
                    pp.`product_article`,
                    pp.`price`,
                    pp.`price_cost`,
                    pp.`price_rrp`,
                    pp.`status`,
                    COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '') AS name
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                LEFT JOIN `product_price_settings` pps
                    ON pps.`product_id` = pp.`product_id`
                " . $whereSql;

        $res = $this->papirDb->query($sql);

        $rows = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function buildBaseWhereSql($search, $strategyId)
    {
        $whereParts = array();
        $whereParts[] = "pp.`id_off` IS NOT NULL";
        $whereParts[] = "pp.`id_off` > 0";

        $rawSearch = (string)$search;
        $search = trim($rawSearch);

        if ($search !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($search, 'UTF-8'));
            $tokens = array_filter($tokens, function ($token) {
                return $token !== '';
            });

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
        }

        $strategyId = (int)$strategyId;
        if ($strategyId > 0) {
            $whereParts[] = "pps.`strategy_id` = " . $strategyId;
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
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

    private function getRealStockByIdOff($idOff)
    {
        $map = $this->getRealStockMap(array((int)$idOff));
        return isset($map[(int)$idOff]) ? (int)$map[(int)$idOff] : 0;
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
                    MIN(s.`priority`) AS priority
                FROM `oc_product_special` s
                INNER JOIN (
                    SELECT
                        `product_id`,
                        MIN(`priority`) AS min_priority
                    FROM `oc_product_special`
                    WHERE `product_id` IN (" . $inList . ")
                      AND (`date_start` <= CURDATE() OR YEAR(`date_start`) = 0)
                      AND (`date_end` >= CURDATE() OR YEAR(`date_end`) = 0)
                    GROUP BY `product_id`
                ) p
                    ON p.`product_id` = s.`product_id`
                   AND p.`min_priority` = s.`priority`
                WHERE s.`product_id` IN (" . $inList . ")
                  AND (`date_start` <= CURDATE() OR YEAR(`date_start`) = 0)
                  AND (`date_end` >= CURDATE() OR YEAR(`date_end`) = 0)
                GROUP BY s.`product_id`";

        $res = $this->offDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['product_id']] = array(
                    'price'    => (float)$row['price'],
                    'priority' => (int)$row['priority'],
                );
            }
        }

        return $map;
    }

    private function getDiscountSummaryMap(array $idOffs)
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
                    `price`
                FROM `discount`
                WHERE `product_id` IN (" . $inList . ")
                  AND (`date_start` IS NULL OR `date_start` <= CURDATE())
                  AND (`date_end` IS NULL OR `date_end` >= CURDATE())
                  AND `customer_group_id` IN (1,2,3)
                ORDER BY `product_id` ASC, `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

        $res = $this->msDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $idOff = (int)$row['product_id'];
                $groupId = (int)$row['customer_group_id'];
                $price = (float)$row['price'];

                if (!isset($map[$idOff])) {
                    $map[$idOff] = array(
                        'wholesale_price'      => null,
                        'dealer_price'         => null,
                        'quantity_discount_cnt'=> 0,
                    );
                }

                if ($groupId === 2 && $map[$idOff]['wholesale_price'] === null) {
                    $map[$idOff]['wholesale_price'] = $price;
                }

                if ($groupId === 3 && $map[$idOff]['dealer_price'] === null) {
                    $map[$idOff]['dealer_price'] = $price;
                }

                if ($groupId === 1) {
                    $map[$idOff]['quantity_discount_cnt']++;
                }
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
                  AND (`date_start` IS NULL OR `date_start` <= CURDATE())
                  AND (`date_end` IS NULL OR `date_end` >= CURDATE())
                  AND `customer_group_id` IN (1,2,3)
                ORDER BY `customer_group_id` ASC, `quantity` ASC, `priority` ASC, `price` ASC";

        $res = $this->msDb->query($sql);

        $result = array(
            'wholesale_price'   => null,
            'dealer_price'      => null,
            'quantity_discounts'=> array(),
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

    private function getProductPriceSettingsMap(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }

        $ids = array_map('intval', array_unique($productIds));
        $inList = implode(',', $ids);

        $sql = "SELECT *
                FROM `product_price_settings`
                WHERE `product_id` IN (" . $inList . ")";

        $res = $this->papirDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['product_id']] = $row;
            }
        }

        return $map;
    }

    private function getProductPriceSettings($productId)
    {
        $map = $this->getProductPriceSettingsMap(array((int)$productId));
        return isset($map[(int)$productId]) ? $map[(int)$productId] : $this->getDefaultSettings();
    }

    private function getDefaultSettings()
    {
        return array(
            'strategy_id'               => 0,
            'manual_price_enabled'      => 0,
            'manual_price'              => null,
            'manual_wholesale_enabled'  => 0,
            'manual_wholesale_price'    => null,
            'manual_dealer_enabled'     => 0,
            'manual_dealer_price'       => null,
            'manual_rrp_enabled'        => 0,
            'manual_rrp'                => null,
            'manual_cost_enabled'       => 0,
            'manual_cost'               => null,
            'disable_auto_quantity_discounts' => 0,
            'is_locked'                 => 0,
            'comment'                   => '',
        );
    }

    private function getStrategyNameMap()
    {
        $sql = "SELECT `strategy_id`, `name`
                FROM `price_strategy`";

        $res = $this->papirDb->query($sql);

        $map = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $map[(int)$row['strategy_id']] = (string)$row['name'];
            }
        }

        return $map;
    }

    private function getStrategyRule($strategyId)
    {
        $strategyId = (int)$strategyId;

        if ($strategyId <= 0) {
            return null;
        }

        $sql = "SELECT *
                FROM `price_strategy_rule`
                WHERE `strategy_id` = " . $strategyId . "
                  AND `is_active` = 1
                ORDER BY `strategy_rule_id` DESC
                LIMIT 1";

        $res = $this->papirDb->query($sql);

        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc();
        }

        return null;
    }
}