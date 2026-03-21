<?php

final class VirtualDashboardRepository
{
    /** @var mysqli */
    private $msDb;

    /** @var mysqli */
    private $papirDb;

    /** @var array<string, string> */
    private $allowedSort = array(
        'product_id'    => 'product_id',
        'name'          => 'name',
        'virtual_stock' => 'virtual_stock',
        'real_stock'    => 'real_stock',
        'price_cost'    => 'price_cost',
        'price'         => 'price',
        'price_rrp'     => 'price_rrp',
    );

    public function __construct(mysqli $msDb, mysqli $papirDb)
    {
        $this->msDb = $msDb;
        $this->papirDb = $papirDb;
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
            'product_id'    => '',
            'name'          => '',
            'virtual_stock' => 0,
            'real_stock'    => 0,
            'price_cost'    => 0,
            'price'         => 0,
            'price_rrp'     => 0,
        );
    }

    public function getVirtualCount()
    {
        $sql = "SELECT COUNT(*) AS cnt FROM `virtual`";
        $res = $this->msDb->query($sql);

        if ($res && $row = $res->fetch_assoc()) {
            return isset($row['cnt']) ? (int)$row['cnt'] : 0;
        }

        return 0;
    }

    public function getTotalRows($search, $filter)
    {
        $virtualIdsSet = $this->getVirtualIdsSet();

        $sql = "SELECT
                    pp.`id_off` AS product_id
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2
                    ON pd2.`product_id` = pp.`product_id`
                   AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1
                    ON pd1.`product_id` = pp.`product_id`
                   AND pd1.`language_id` = 1
                " . $this->buildPapirWhereSql($search);

        $res = $this->papirDb->query($sql);
        if (!$res) {
            return 0;
        }

        $count = 0;

        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['product_id'];
            $hasVirtual = isset($virtualIdsSet[$id]);

            if ($filter === 'with_virtual' && !$hasVirtual) {
                continue;
            }

            if ($filter === 'without_virtual' && $hasVirtual) {
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

        $papirRows = $this->getPapirBaseRows($search);
        if ($papirRows === false) {
            return false;
        }

        $virtualIdsSet = $this->getVirtualIdsSet();

        $rows = array();

        foreach ($papirRows as $row) {
            $id = (int)$row['product_id'];
            $hasVirtual = isset($virtualIdsSet[$id]);

            if ($filter === 'with_virtual' && !$hasVirtual) {
                continue;
            }

            if ($filter === 'without_virtual' && $hasVirtual) {
                continue;
            }

            $row['has_virtual_row'] = $hasVirtual;
            $row['virtual_stock'] = 0;
            $row['real_stock'] = 0;

            $rows[] = $row;
        }

        usort($rows, function ($a, $b) use ($sort, $order) {
            $valueA = isset($a[$sort]) ? $a[$sort] : null;
            $valueB = isset($b[$sort]) ? $b[$sort] : null;

            if (is_numeric($valueA) && is_numeric($valueB)) {
                $valueA = (float)$valueA;
                $valueB = (float)$valueB;
            } else {
                $valueA = (string)$valueA;
                $valueB = (string)$valueB;
            }

            if ($valueA == $valueB) {
                return 0;
            }

            $result = ($valueA < $valueB) ? -1 : 1;
            return $order === 'desc' ? -$result : $result;
        });

        $pagedRows = array_slice($rows, $offset, $limit);

        $pageIds = array();
        foreach ($pagedRows as $row) {
            $pageIds[] = (int)$row['product_id'];
        }

        $msMap = $this->getMsDataMapForPage($pageIds);

        foreach ($pagedRows as $index => $row) {
            $id = (int)$row['product_id'];

            if (isset($msMap[$id])) {
                $pagedRows[$index]['virtual_stock'] = $msMap[$id]['virtual_stock'];
                $pagedRows[$index]['real_stock'] = $msMap[$id]['real_stock'];
                $pagedRows[$index]['has_virtual_row'] = $msMap[$id]['has_virtual_row'];
            }
        }

        return new ArrayResult($pagedRows);
    }

    private function getPapirBaseRows($search)
    {
        $sql = "SELECT
                    pp.`id_off` AS product_id,
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
                " . $this->buildPapirWhereSql($search);

        $res = $this->papirDb->query($sql);
        if (!$res) {
            return false;
        }

        $rows = array();

        while ($row = $res->fetch_assoc()) {
            $rows[] = array(
                'product_id'      => (int)$row['product_id'],
                'name'            => $row['name'],
                'price_cost'      => (float)$row['price_cost'],
                'price'           => (float)$row['price'],
                'price_rrp'       => (float)$row['price_rrp'],
                'virtual_stock'   => 0,
                'real_stock'      => 0,
                'has_virtual_row' => false,
            );
        }

        return $rows;
    }

    private function getVirtualIdsSet()
    {
        $sql = "SELECT `product_id` FROM `virtual`";
        $res = $this->msDb->query($sql);

        if (!$res) {
            return array();
        }

        $set = array();

        while ($row = $res->fetch_assoc()) {
            $set[(int)$row['product_id']] = true;
        }

        return $set;
    }

    private function getMsDataMapForPage(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }

        $ids = array_map('intval', array_unique($productIds));
        $idsSql = implode(',', $ids);

        $virtualMap = array();
        $realStockMap = array();

        $sqlVirtual = "SELECT `product_id`, `stock`
                       FROM `virtual`
                       WHERE `product_id` IN (" . $idsSql . ")";
        $resVirtual = $this->msDb->query($sqlVirtual);

        if ($resVirtual) {
            while ($row = $resVirtual->fetch_assoc()) {
                $id = (int)$row['product_id'];
                $virtualMap[$id] = array(
                    'virtual_stock'   => (int)$row['stock'],
                    'has_virtual_row' => true,
                );
            }
        }

        $sqlStock = "SELECT CAST(`model` AS UNSIGNED) AS product_id, `stock`
                     FROM `stock_`
                     WHERE CAST(`model` AS UNSIGNED) IN (" . $idsSql . ")";
        $resStock = $this->msDb->query($sqlStock);

        if ($resStock) {
            while ($row = $resStock->fetch_assoc()) {
                $id = (int)$row['product_id'];
                $realStockMap[$id] = (int)$row['stock'];
            }
        }

        $map = array();

        foreach ($ids as $id) {
            $map[$id] = array(
                'virtual_stock'   => isset($virtualMap[$id]) ? $virtualMap[$id]['virtual_stock'] : 0,
                'real_stock'      => isset($realStockMap[$id]) ? $realStockMap[$id] : 0,
                'has_virtual_row' => isset($virtualMap[$id]),
            );
        }

        return $map;
    }
	
	public function getCatalogTotalCount()
	{
		$sql = "SELECT COUNT(*) AS cnt FROM `product_papir`";
		$res = $this->papirDb->query($sql);

		if ($res && $row = $res->fetch_assoc()) {
			return isset($row['cnt']) ? (int)$row['cnt'] : 0;
		}

		return 0;
	}

	public function getVirtualPositiveCount()
	{
		$sql = "SELECT COUNT(*) AS cnt
				FROM `virtual`
				WHERE `stock` > 0";
		$res = $this->msDb->query($sql);

		if ($res && $row = $res->fetch_assoc()) {
			return isset($row['cnt']) ? (int)$row['cnt'] : 0;
		}

		return 0;
	}

	private function buildPapirWhereSql($search)
	{
		$whereParts = array();
		$search = trim((string)$search);

		if ($search !== '') {
			$tokens = preg_split('/\s+/u', mb_strtolower($search, 'UTF-8'));
			$tokens = array_filter($tokens, function ($token) {
				return $token !== '';
			});

			$tokenConditions = array();

			foreach ($tokens as $token) {
				$tokenEsc = $this->papirDb->real_escape_string($token);

				$tokenConditions[] = "(
					CAST(pp.`id_off` AS CHAR) LIKE '%" . $tokenEsc . "%'
					OR LOWER(COALESCE(NULLIF(pd2.`name`, ''), NULLIF(pd1.`name`, ''), '')) LIKE '%" . $tokenEsc . "%'
				)";
			}

			if (!empty($tokenConditions)) {
				$whereParts[] = implode(' AND ', $tokenConditions);
			}
		}

		if (empty($whereParts)) {
			return '';
		}

		return ' WHERE ' . implode(' AND ', $whereParts);
	}
}