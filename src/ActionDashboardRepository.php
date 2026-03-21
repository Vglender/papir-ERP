<?php

final class ActionDashboardRepository
{
    /** @var mysqli */
    private $mysqli;

    /** @var array<string, string> */
    private $allowedSort = array(
        'product_id'     => 'product_id',
        'name'           => 'name',
        'quantity'       => 'quantity',
        'price'          => 'price',
        'total_sum'      => 'total_sum',
        'discount'       => 'discount',
        'super_discont'  => 'super_discont',
    );

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function normalizeSort($sort)
    {
        $sort = (string)$sort;

        if (!isset($this->allowedSort[$sort])) {
            return 'product_id';
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

    public function getDefaultEditRow()
    {
        return array(
            'product_id'     => '',
            'discount'       => 0,
            'super_discont'  => 0,
            'name'           => '',
            'stock'          => 0,
            'price'          => 0,
        );
    }

    public function getUpdatedAt()
    {
        $sql = "SELECT `date` FROM `stock` WHERE `number` = 1 LIMIT 1";
        $res = $this->mysqli->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['date']) ? $row['date'] : '';
        }

        return '';
    }

    public function getTotalStockSum()
    {
        $sql = "SELECT SUM(`stock` * `price`) AS total_stock_sum
                FROM `stock_`
                WHERE `stock` > 0";

        $res = $this->mysqli->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['total_stock_sum']) ? (float)$row['total_stock_sum'] : 0.0;
        }

        return 0.0;
    }

    public function getTotalRows($search, $filter)
    {
        $whereSql = $this->buildWhereSql($search, $filter);

        $sql = "SELECT COUNT(*) AS total_rows
                FROM `stock_` s
                LEFT JOIN `action` a
                    ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
                " . $whereSql;

        $res = $this->mysqli->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['total_rows']) ? (int)$row['total_rows'] : 0;
        }

        return 0;
    }

    public function getList($search, $filter, $sort, $order, $offset, $limit)
    {
        $sort = $this->normalizeSort($sort);
        $order = $this->normalizeOrder($order);

        $whereSql = $this->buildWhereSql($search, $filter);
        $orderBy = $this->buildOrderBy($sort, $order);

        $sql = "SELECT
                    CAST(s.`model` AS UNSIGNED) AS product_id,
                    s.`name`,
                    s.`stock`,
                    s.`price`,
                    (s.`stock` * s.`price`) AS total_sum,
                    a.`discount`,
                    a.`super_discont`
                FROM `stock_` s
                LEFT JOIN `action` a
                    ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
                " . $whereSql . "
                ORDER BY " . $orderBy . "
                LIMIT " . (int)$offset . ", " . (int)$limit;

        return $this->mysqli->query($sql);
    }

    private function buildWhereSql($search, $filter)
    {
        $whereParts = array();
        $whereParts[] = "s.`stock` > 0";

        $search = trim((string)$search);
        $filter = trim((string)$filter);

        if ($search !== '') {
            $searchEsc = $this->mysqli->real_escape_string($search);
            $whereParts[] = "(CAST(s.`model` AS CHAR) LIKE '%" . $searchEsc . "%'
                              OR s.`name` LIKE '%" . $searchEsc . "%')";
        }

        if ($filter === 'with_action') {
            $whereParts[] = "a.`product_id` IS NOT NULL";
        } elseif ($filter === 'without_action') {
            $whereParts[] = "a.`product_id` IS NULL";
        }

        if (empty($whereParts)) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $whereParts);
    }

    private function buildOrderBy($sort, $order)
    {
        $map = array(
            'product_id'    => 'product_id',
            'name'          => 'name',
            'quantity'      => 'quantity',
            'price'         => 'price',
            'total_sum'     => 'total_sum',
            'discount'      => 'discount',
            'super_discont' => 'super_discont',
        );

        $column = isset($map[$sort]) ? $map[$sort] : 'product_id';
        $direction = strtoupper($order === 'desc' ? 'desc' : 'asc');

        return $column . ' ' . $direction;
    }
}