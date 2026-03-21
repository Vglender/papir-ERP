<?php

class ProductPriceRepository
{
    private $dbName = 'Papir';

    public function getById($productId)
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT pp.*,
                    pps.manual_price_enabled,  pps.manual_price,
                    pps.manual_wholesale_enabled, pps.manual_wholesale_price,
                    pps.manual_dealer_enabled, pps.manual_dealer_price,
                    pps.manual_rrp_enabled,    pps.manual_rrp,
                    pps.manual_cost_enabled,   pps.manual_cost,
                    pps.disable_auto_quantity_discounts,
                    pps.discount_strategy_id   AS settings_discount_strategy_id,
                    pps.quantity_strategy_id   AS settings_quantity_strategy_id,
                    pps.is_locked,
                    pps.comment
             FROM `product_papir` pp
             LEFT JOIN `product_price_settings` pps ON pps.product_id = pp.product_id
             WHERE pp.product_id = {$productId}"
        );

        return $result['ok'] ? (isset($result['row']) ? $result['row'] : []) : [];
    }

    public function getSettings($productId)
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT * FROM `product_price_settings` WHERE product_id = {$productId}"
        );

        return $result['ok'] ? (isset($result['row']) ? $result['row'] : []) : [];
    }

    public function savePrices($productId, array $data)
    {
        $exists = Database::exists($this->dbName, 'product_papir', ['product_id' => $productId]);

        if (!$exists['ok'] || !$exists['exists']) {
            return ['ok' => false, 'error' => 'Product not found'];
        }

        return Database::update($this->dbName, 'product_papir', $data, ['product_id' => $productId]);
    }

    public function saveSettings($productId, array $data)
    {
        $exists = Database::exists($this->dbName, 'product_price_settings', ['product_id' => $productId]);

        if ($exists['ok'] && $exists['exists']) {
            return Database::update($this->dbName, 'product_price_settings', $data, ['product_id' => $productId]);
        }

        $data['product_id'] = $productId;
        return Database::insert($this->dbName, 'product_price_settings', $data);
    }

    private function buildSearchWhere($search)
    {
        $parts = array_filter(array_map('trim', preg_split('/\s+/', $search)));
        $conditions = [];
        foreach ($parts as $token) {
            $t = Database::escape($this->dbName, $token);
            $conditions[] = "(pp.product_article LIKE '%{$t}%' OR pd.name LIKE '%{$t}%')";
        }
        return $conditions;
    }

    public function getList(array $filters = [], $sort = 'product_id', $order = 'asc', $offset = 0, $limit = 50)
    {
        $where = ['1=1'];

        // По умолчанию — только активные товары (status=1)
        if (empty($filters['show_inactive'])) {
            $where[] = "pp.status = 1";
        }

        if (!empty($filters['search'])) {
            foreach ($this->buildSearchWhere($filters['search']) as $cond) {
                $where[] = $cond;
            }
        }

        if (!empty($filters['strategy_id'])) {
            $sid = (int)$filters['strategy_id'];
            $where[] = "pp.discount_strategy_id = {$sid}";
        }

        if (!empty($filters['filter'])) {
            if ($filters['filter'] === 'manual_only') {
                $where[] = "pps.product_id IS NOT NULL AND (
                    pps.manual_price_enabled = 1 OR pps.manual_wholesale_enabled = 1 OR
                    pps.manual_dealer_enabled = 1 OR pps.manual_rrp_enabled = 1 OR
                    pps.manual_cost_enabled = 1
                )";
            }
        }

        $allowedSort = ['product_id', 'id_off', 'product_article', 'price_purchase', 'price_sale', 'price_wholesale', 'price_dealer', 'price_rrp'];
        $sort  = in_array($sort, $allowedSort) ? $sort : 'product_id';
        $order = $order === 'desc' ? 'DESC' : 'ASC';
        $whereSql = implode(' AND ', $where);

        $sql = "SELECT pp.product_id, pp.product_article, pp.id_off, pp.status,
                       pd.name,
                       pp.price_supplier, pp.price_accounting_cost,
                       COALESCE(pp.price_purchase, pp.price_cost) AS price_purchase,
                       pp.purchase_price_source,
                       COALESCE(pp.price_sale, pp.price)          AS price_sale,
                       pp.price_wholesale, pp.price_dealer,
                       pp.price_rrp, pp.use_rrp,
                       pp.discount_strategy_id, pp.quantity_strategy_id,
                       pp.discount_strategy_manual, pp.quantity_strategy_manual,
                       pp.prices_updated_at,
                       pds.name AS discount_strategy_name,
                       pps.manual_price_enabled, pps.manual_wholesale_enabled, pps.manual_dealer_enabled,
                       ap.price_act, ap.discount AS act_discount, ap.super_discont AS act_super_discont
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
                LEFT JOIN `price_discount_strategy` pds ON pds.id = pp.discount_strategy_id
                LEFT JOIN `product_price_settings` pps ON pps.product_id = pp.product_id
                LEFT JOIN `action_prices` ap ON ap.product_id = pp.id_off
                WHERE {$whereSql}
                ORDER BY pp.`{$sort}` {$order}
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($this->dbName, $sql);
    }

    /**
     * Детальные данные для боковой панели (поиск по id_off).
     */
    public function getProductDetails($productId)
    {
        $productId = (int)$productId;
        $result = Database::fetchRow($this->dbName,
            "SELECT pp.*,
                    COALESCE(pp.price_purchase, pp.price_cost) AS price_purchase,
                    COALESCE(pp.price_sale,     pp.price)      AS price_sale,
                    pd.name, pd.description,
                    pds.name  AS discount_strategy_name,
                    pds.small_discount_percent,
                    pds.medium_discount_percent,
                    pds.large_discount_percent,
                    pps.manual_price_enabled,    pps.manual_price,
                    pps.manual_wholesale_enabled,pps.manual_wholesale_price,
                    pps.manual_dealer_enabled,   pps.manual_dealer_price,
                    pps.manual_rrp_enabled,      pps.manual_rrp,
                    pps.manual_cost_enabled,     pps.manual_cost,
                    pps.disable_auto_quantity_discounts,
                    pps.is_locked,               pps.comment,
                    pdp.qty_1,  pdp.discount_percent_1, pdp.price_1,
                    pdp.qty_2,  pdp.discount_percent_2, pdp.price_2,
                    pdp.qty_3,  pdp.discount_percent_3, pdp.price_3,
                    pdp.qty_source, pdp.calculated_at,
                    ap.price_act, ap.discount AS act_discount, ap.super_discont AS act_super_discont,
                    ap.published_at AS act_published_at, ap.calculated_at AS act_calculated_at
             FROM `product_papir` pp
             LEFT JOIN `product_description` pd
                    ON pd.product_id = pp.product_id AND pd.language_id = 2
             LEFT JOIN `price_discount_strategy` pds
                    ON pds.id = pp.discount_strategy_id
             LEFT JOIN `product_price_settings` pps
                    ON pps.product_id = pp.product_id
             LEFT JOIN `product_discount_profile` pdp
                    ON pdp.product_id = pp.product_id
             LEFT JOIN `action_prices` ap
                    ON ap.product_id = pp.id_off
             WHERE pp.product_id = {$productId}
             LIMIT 1"
        );

        if (!$result['ok'] || empty($result['row'])) {
            return null;
        }

        $row = $result['row'];

        // Упаковки
        $packages = Database::fetchAll($this->dbName,
            "SELECT * FROM `product_package`
             WHERE product_id = {$row['product_id']}
             ORDER BY level ASC"
        );
        $row['packages'] = $packages['ok'] ? $packages['rows'] : [];

        // Остаток из ms
        $stock = Database::fetchRow('ms',
            "SELECT (COALESCE(s.stock, 0) + COALESCE(v.stock, 0)) AS total
             FROM `stock_` s
             LEFT JOIN `virtual` v ON v.product_id = CAST(s.model AS UNSIGNED)
             WHERE CAST(s.model AS UNSIGNED) = {$row['id_off']}
             LIMIT 1"
        );
        $row['real_stock'] = ($stock['ok'] && $stock['row']) ? (int)$stock['row']['total'] : 0;

        return $row;
    }

    public function countList(array $filters = [])
    {
        $where = ['1=1'];

        if (empty($filters['show_inactive'])) {
            $where[] = "pp.status = 1";
        }

        if (!empty($filters['search'])) {
            foreach ($this->buildSearchWhere($filters['search']) as $cond) {
                $where[] = $cond;
            }
        }

        $whereSql = implode(' AND ', $where);

        $result = Database::fetchRow($this->dbName,
            "SELECT COUNT(*) AS cnt
             FROM `product_papir` pp
             LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
             WHERE {$whereSql}"
        );

        return (int)(isset($result['row']['cnt']) ? $result['row']['cnt'] : 0);
    }
}
