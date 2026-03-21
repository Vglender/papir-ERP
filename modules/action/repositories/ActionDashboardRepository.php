<?php

class ActionDashboardRepository
{
    /** @var array */
    private $allowedSort = array(
        'product_id'    => 'product_id',
        'name'          => 'name',
        'quantity'      => 'stock',
        'price'         => 'price',
        'total_sum'     => 'total_sum',
        'discount'      => 'discount',
        'super_discont' => 'super_discont',
    );

    /**
     * Normalize sort column name.
     *
     * @param string $sort
     * @return string
     */
    public function normalizeSort($sort)
    {
        $sort = (string)$sort;

        if (!isset($this->allowedSort[$sort])) {
            return 'product_id';
        }

        return $sort;
    }

    /**
     * Normalize order direction.
     *
     * @param string $order
     * @return string
     */
    public function normalizeOrder($order)
    {
        $order = strtolower((string)$order);

        if ($order !== 'asc' && $order !== 'desc') {
            return 'asc';
        }

        return $order;
    }

    /**
     * Return empty edit row defaults.
     *
     * @return array
     */
    public function getDefaultEditRow()
    {
        return array(
            'product_id'    => '',
            'discount'      => 0,
            'super_discont' => 0,
            'name'          => '',
            'stock'         => 0,
            'price'         => 0,
            'price_act'     => null,
            'published_at'  => null,
            'calculated_at' => null,
        );
    }

    /**
     * Get the last stock update timestamp.
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        $sql    = "SELECT `date` FROM `stock` WHERE `number` = 1 LIMIT 1";
        $result = Database::fetchRow('ms', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return isset($result['row']['date']) ? $result['row']['date'] : '';
        }

        return '';
    }

    /**
     * Get total sum of stock value.
     *
     * @return float
     */
    public function getTotalStockSum()
    {
        $sql    = "SELECT SUM(`stock` * `price`) AS total_stock_sum FROM `stock_` WHERE `stock` > 0";
        $result = Database::fetchRow('ms', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return isset($result['row']['total_stock_sum']) ? (float)$result['row']['total_stock_sum'] : 0.0;
        }

        return 0.0;
    }

    /**
     * Count total rows matching search/filter criteria.
     *
     * @param string $search
     * @param string $filter
     * @return int
     */
    public function getTotalRows($search, $filter)
    {
        $whereSql = $this->buildWhereSql($search, $filter);

        $sql = "SELECT COUNT(*) AS total_rows
                FROM `stock_` s
                LEFT JOIN `virtual` v
                    ON v.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_products` ap
                    ON ap.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_prices` act_p
                    ON act_p.`product_id` = CAST(s.`model` AS UNSIGNED)
                " . $whereSql;

        $result = Database::fetchRow('ms', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return isset($result['row']['total_rows']) ? (int)$result['row']['total_rows'] : 0;
        }

        return 0;
    }

    /**
     * Get paginated list of products with action data.
     *
     * @param string $search
     * @param string $filter
     * @param string $sort
     * @param string $order
     * @param int    $offset
     * @param int    $limit
     * @return array
     */
    public function getList($search, $filter, $sort, $order, $offset, $limit)
    {
        $sort  = $this->normalizeSort($sort);
        $order = $this->normalizeOrder($order);

        $whereSql = $this->buildWhereSql($search, $filter);
        $orderBy  = $this->buildOrderBy($sort, $order);

        $sql = "SELECT
                    CAST(s.`model` AS UNSIGNED) AS product_id,
                    s.`name`,
                    (s.`stock` + COALESCE(v.`stock`, 0)) AS stock,
                    s.`price`,
                    ((s.`stock` + COALESCE(v.`stock`, 0)) * s.`price`) AS total_sum,
                    ap.`discount`,
                    ap.`super_discont`,
                    act_p.`price_act`,
                    act_p.`published_at`,
                    act_p.`calculated_at`
                FROM `stock_` s
                LEFT JOIN `virtual` v
                    ON v.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_products` ap
                    ON ap.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_prices` act_p
                    ON act_p.`product_id` = CAST(s.`model` AS UNSIGNED)
                " . $whereSql . "
                ORDER BY " . $orderBy . "
                LIMIT " . (int)$offset . ", " . (int)$limit;

        $result = Database::fetchAll('ms', $sql);

        if ($result['ok'] && !empty($result['rows'])) {
            return $result['rows'];
        }

        return array();
    }

    /**
     * Get a single row for the edit form.
     *
     * @param int $productId
     * @return array|null
     */
    public function getEditRow($productId)
    {
        $productId = (int)$productId;

        $sql = "SELECT
                    CAST(s.`model` AS UNSIGNED) AS product_id,
                    s.`name`,
                    (s.`stock` + COALESCE(v.`stock`, 0)) AS stock,
                    s.`price`,
                    COALESCE(ap.`discount`, 0) AS discount,
                    COALESCE(ap.`super_discont`, 0) AS super_discont,
                    act_p.`price_act`,
                    act_p.`published_at`,
                    act_p.`calculated_at`
                FROM `stock_` s
                LEFT JOIN `virtual` v
                    ON v.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_products` ap
                    ON ap.`product_id` = CAST(s.`model` AS UNSIGNED)
                LEFT JOIN `Papir`.`action_prices` act_p
                    ON act_p.`product_id` = CAST(s.`model` AS UNSIGNED)
                WHERE CAST(s.`model` AS UNSIGNED) = " . $productId . "
                LIMIT 1";

        $result = Database::fetchRow('ms', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return $result['row'];
        }

        return null;
    }

    /**
     * Build WHERE clause.
     *
     * @param string $search
     * @param string $filter
     * @return string
     */
    private function buildWhereSql($search, $filter)
    {
        $whereParts   = array();
        $whereParts[] = "CAST(s.`model` AS UNSIGNED) > 0";

        $search = trim((string)$search);
        $filter = trim((string)$filter);

        if ($search !== '') {
            $searchEsc    = Database::escape('ms', $search);
            $whereParts[] = "(CAST(s.`model` AS CHAR) LIKE '%" . $searchEsc . "%'
                              OR s.`name` LIKE '%" . $searchEsc . "%')";
        }

        if ($filter === 'with_action') {
            $whereParts[] = "ap.`product_id` IS NOT NULL";
        } elseif ($filter === 'without_action') {
            $whereParts[] = "ap.`product_id` IS NULL";
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
    }

    /**
     * Build ORDER BY clause.
     *
     * @param string $sort
     * @param string $order
     * @return string
     */
    private function buildOrderBy($sort, $order)
    {
        $map = array(
            'product_id'    => 'product_id',
            'name'          => 'name',
            'quantity'      => 'stock',
            'price'         => 'price',
            'total_sum'     => 'total_sum',
            'discount'      => 'discount',
            'super_discont' => 'super_discont',
        );

        $column    = isset($map[$sort]) ? $map[$sort] : 'product_id';
        $direction = strtoupper($order === 'desc' ? 'DESC' : 'ASC');

        return $column . ' ' . $direction;
    }
}
