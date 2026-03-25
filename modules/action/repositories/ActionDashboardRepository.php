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
            'id_off'        => '',
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
     * Build chip-based search conditions (OR between chips, AND within chip).
     * Pure integer chip = exact product_id match.
     *
     * @param string $search
     * @return array  array of SQL condition strings
     */
    private function buildSearchWhere($search)
    {
        $rawChips = preg_split('/\s*,\s*/u', trim($search));
        $chipConditions = array();

        foreach ($rawChips as $chip) {
            $chip = trim($chip);
            if ($chip === '') continue;

            if (preg_match('/^\d+$/', $chip)) {
                $chipConditions[] = "pp.`product_id` = " . (int)$chip;
                continue;
            }

            $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
            $tokens = array_filter($tokens, function($t) { return $t !== ''; });
            $tokenParts = array();
            foreach ($tokens as $token) {
                $t = Database::escape('Papir', $token);
                $tokenParts[] = "(CAST(pp.`product_id` AS CHAR) LIKE '%{$t}%' OR LOWER(COALESCE(pp.`product_article`,'')) LIKE '%{$t}%' OR LOWER(COALESCE(pd.`name`,'')) LIKE '%{$t}%')";
            }
            if (!empty($tokenParts)) {
                $chipConditions[] = '(' . implode(' AND ', $tokenParts) . ')';
            }
        }

        return $chipConditions;
    }

    /**
     * Build WHERE clause.
     *
     * @param string $search
     * @param string $filter
     * @return string  full WHERE ... clause
     */
    private function buildWhereSql($search, $filter)
    {
        $whereParts = array('pp.`status` = 1', '(pp.`quantity` > 0 OR ap.`product_id` IS NOT NULL)');

        $search = trim((string)$search);
        $filter = trim((string)$filter);

        if ($search !== '') {
            $chips = $this->buildSearchWhere($search);
            if (!empty($chips)) {
                $whereParts[] = count($chips) === 1 ? $chips[0] : '(' . implode(' OR ', $chips) . ')';
            }
        }

        if ($filter === 'with_action') {
            $whereParts[] = "ap.`product_id` IS NOT NULL";
        } elseif ($filter === 'without_action') {
            $whereParts[] = "ap.`product_id` IS NULL AND pp.`quantity` > 0";
        }

        return 'WHERE ' . implode(' AND ', $whereParts);
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
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
                LEFT JOIN `action_products` ap ON ap.product_id = pp.id_off
                LEFT JOIN `action_prices` act_p ON act_p.product_id = pp.id_off
                {$whereSql}";

        $result = Database::fetchRow('Papir', $sql);

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
                    pp.`product_id`,
                    pp.`id_off`,
                    COALESCE(pd.`name`, '') AS name,
                    pp.`quantity` AS stock,
                    COALESCE(pp.`price_sale`, pp.`price`, 0) AS price,
                    (pp.`quantity` * COALESCE(pp.`price_sale`, pp.`price`, 0)) AS total_sum,
                    ap.`discount`,
                    ap.`super_discont`,
                    act_p.`price_act`,
                    act_p.`published_at`,
                    act_p.`calculated_at`
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
                LEFT JOIN `action_products` ap ON ap.product_id = pp.id_off
                LEFT JOIN `action_prices` act_p ON act_p.product_id = pp.id_off
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT " . (int)$offset . ", " . (int)$limit;

        $result = Database::fetchAll('Papir', $sql);

        if ($result['ok'] && !empty($result['rows'])) {
            return $result['rows'];
        }

        return array();
    }

    /**
     * Get a single row for the edit form.
     *
     * @param int $productId  Papir product_id
     * @return array|null
     */
    public function getEditRow($productId)
    {
        $productId = (int)$productId;

        $sql = "SELECT
                    pp.`product_id`,
                    pp.`id_off`,
                    COALESCE(pd.`name`, '') AS name,
                    pp.`quantity` AS stock,
                    COALESCE(pp.`price_sale`, pp.`price`, 0) AS price,
                    COALESCE(ap.`discount`, 0) AS discount,
                    COALESCE(ap.`super_discont`, 0) AS super_discont,
                    act_p.`price_act`,
                    act_p.`published_at`,
                    act_p.`calculated_at`
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
                LEFT JOIN `action_products` ap ON ap.product_id = pp.id_off
                LEFT JOIN `action_prices` act_p ON act_p.product_id = pp.id_off
                WHERE pp.`product_id` = {$productId}
                LIMIT 1";

        $result = Database::fetchRow('Papir', $sql);

        if ($result['ok'] && !empty($result['row'])) {
            return $result['row'];
        }

        return null;
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
            'product_id'    => 'pp.product_id',
            'name'          => 'name',
            'quantity'      => 'stock',
            'price'         => 'price',
            'total_sum'     => 'total_sum',
            'discount'      => 'ap.discount',
            'super_discont' => 'ap.super_discont',
        );

        $column    = isset($map[$sort]) ? $map[$sort] : 'pp.product_id';
        $direction = strtoupper($order === 'desc' ? 'DESC' : 'ASC');

        return $column . ' ' . $direction;
    }
}
