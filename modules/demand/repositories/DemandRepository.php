<?php

class DemandRepository
{
    private $db = 'Papir';

    /**
     * Build WHERE clauses + flags from shared filter array.
     */
    private function buildWhere($filters)
    {
        $where = array();
        $where[] = 'd.deleted_at IS NULL';
        $needsCpJoin = false;

        // Search (chip)
        $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
        if ($search !== '') {
            $needsCpJoin = true;
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips = array_filter(array_map('trim', preg_split($chipSep, $search)));
            $chipConds = array();
            foreach ($chips as $chip) {
                if (preg_match('/^\d+$/', $chip)) {
                    $chipConds[] = "(d.id = " . (int)$chip . " OR LOWER(d.number) LIKE '%" . Database::escape($this->db, $chip) . "%')";
                } else {
                    $tokens = array_filter(preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8')));
                    $parts = array();
                    foreach ($tokens as $t) {
                        $t = Database::escape($this->db, $t);
                        $parts[] = "(LOWER(d.number) LIKE '%{$t}%'
                            OR LOWER(COALESCE(cp.name,'')) LIKE '%{$t}%'
                            OR LOWER(COALESCE(co.number,'')) LIKE '%{$t}%')";
                    }
                    if ($parts) $chipConds[] = '(' . implode(' AND ', $parts) . ')';
                }
            }
            if ($chipConds) $where[] = '(' . implode(' OR ', $chipConds) . ')';
        }

        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $sts = array();
                foreach ($filters['status'] as $sv) {
                    $sts[] = "'" . Database::escape($this->db, $sv) . "'";
                }
                if (!empty($sts)) {
                    $where[] = 'd.status IN (' . implode(',', $sts) . ')';
                }
            } else {
                $status = Database::escape($this->db, $filters['status']);
                $where[] = "d.status = '{$status}'";
            }
        }

        // Organization filter
        if (!empty($filters['organization_id'])) {
            $orgId = (int)$filters['organization_id'];
            $where[] = "COALESCE(d.organization_id, co.organization_id) = {$orgId}";
        }

        // Manager filter
        if (!empty($filters['manager_employee_id'])) {
            $mgrId = (int)$filters['manager_employee_id'];
            $where[] = "COALESCE(d.manager_employee_id, co.manager_employee_id) = {$mgrId}";
        }

        // Counterparty filter
        if (!empty($filters['counterparty_id'])) {
            $where[] = 'd.counterparty_id = ' . (int)$filters['counterparty_id'];
        }

        // Sum range
        if (isset($filters['sum_from']) && $filters['sum_from'] !== '' && $filters['sum_from'] !== null) {
            $where[] = 'd.sum_total >= ' . (float)$filters['sum_from'];
        }
        if (isset($filters['sum_to']) && $filters['sum_to'] !== '' && $filters['sum_to'] !== null) {
            $where[] = 'd.sum_total <= ' . (float)$filters['sum_to'];
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $df = Database::escape($this->db, $filters['date_from']);
            $where[] = "d.moment >= '{$df} 00:00:00'";
        }
        if (!empty($filters['date_to'])) {
            $dt = Database::escape($this->db, $filters['date_to']);
            $where[] = "d.moment <= '{$dt} 23:59:59'";
        }

        return array('where' => $where, 'needsCpJoin' => $needsCpJoin);
    }

    public function getList($filters = array(), $sort = array(), $page = 1, $limit = 50)
    {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;

        $built = $this->buildWhere($filters);
        $whereSql = implode(' AND ', $built['where']);

        $allowedSort = array(
            'id'         => 'd.id',
            'moment'     => 'd.moment',
            'number'     => 'd.number',
            'status'     => 'd.status',
            'sum_total'  => 'd.sum_total',
            'profit'     => 'd.profit',
            'updated_at' => 'd.updated_at',
        );

        $sortField = 'd.id';
        $sortDir = 'DESC';

        if (!empty($sort['field']) && isset($allowedSort[$sort['field']])) {
            $sortField = $allowedSort[$sort['field']];
        }
        if (!empty($sort['dir']) && in_array(strtoupper($sort['dir']), array('ASC', 'DESC'))) {
            $sortDir = strtoupper($sort['dir']);
        }

        $onlyDeletedFilter = (count($built['where']) === 1);
        $sortById = ($sortField === 'd.id');
        $forceIdx = ($onlyDeletedFilter && $sortById) ? ' FORCE INDEX (PRIMARY)' : '';

        $sql = "
            SELECT
                d.id, d.number, d.moment, d.status,
                d.sum_total, d.profit, d.sync_state,
                d.counterparty_id, d.customerorder_id,
                co.number AS order_number,
                COALESCE(NULLIF(o.short_name,''), o.name) AS organization_short,
                o.name AS organization_name,
                COALESCE(NULLIF(emp.full_name,''), au.display_name) AS manager_display,
                cp.name AS counterparty_name
            FROM demand d{$forceIdx}
            LEFT JOIN customerorder co ON co.id = d.customerorder_id
            LEFT JOIN organization o   ON o.id  = COALESCE(d.organization_id, co.organization_id)
            LEFT JOIN employee emp     ON emp.id = COALESCE(d.manager_employee_id, co.manager_employee_id)
            LEFT JOIN auth_users au    ON au.employee_id = emp.id
            LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
            WHERE {$whereSql}
            ORDER BY {$sortField} {$sortDir}
            LIMIT {$offset}, {$limit}
        ";

        return Database::fetchAll($this->db, $sql);
    }

    public function countList($filters = array())
    {
        $built = $this->buildWhere($filters);
        $whereSql = implode(' AND ', $built['where']);

        $sql = "SELECT COUNT(*) AS total
                FROM demand d
                LEFT JOIN customerorder co ON co.id = d.customerorder_id
                LEFT JOIN organization o   ON o.id  = COALESCE(d.organization_id, co.organization_id)
                LEFT JOIN employee emp     ON emp.id = COALESCE(d.manager_employee_id, co.manager_employee_id)
                LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
                WHERE {$whereSql}";

        $result = Database::fetchValue($this->db, $sql, 'total');
        if ($result['ok']) {
            return array('ok' => true, 'value' => (int)$result['value']);
        }
        return array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Unknown error');
    }

    public function getById($id)
    {
        return Database::fetchRow($this->db,
            "SELECT d.*,
                    co.number AS order_number, co.id_ms AS order_ms_id,
                    COALESCE(d.organization_id, co.organization_id) AS effective_organization_id,
                    COALESCE(d.manager_employee_id, co.manager_employee_id) AS effective_manager_employee_id,
                    COALESCE(d.store_id, co.store_id) AS effective_store_id,
                    COALESCE(d.delivery_method_id, co.delivery_method_id) AS effective_delivery_method_id,
                    COALESCE(o.name, o2.name) AS org_name,
                    cp.name AS counterparty_name, cp.id_ms AS cp_ms_id,
                    cp.type AS counterparty_type,
                    COALESCE(e.full_name, e2.full_name) AS manager_name,
                    COALESCE(s.name, s2.name) AS store_name,
                    dm.name_uk AS delivery_method_name
             FROM demand d
             LEFT JOIN customerorder co ON co.id = d.customerorder_id
             LEFT JOIN organization o   ON o.id  = d.organization_id
             LEFT JOIN organization o2  ON o2.id = co.organization_id
             LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
             LEFT JOIN employee e       ON e.id  = d.manager_employee_id
             LEFT JOIN employee e2      ON e2.id = co.manager_employee_id
             LEFT JOIN store s          ON s.id  = d.store_id
             LEFT JOIN store s2         ON s2.id = co.store_id
             LEFT JOIN delivery_method dm ON dm.id = COALESCE(d.delivery_method_id, co.delivery_method_id)
             WHERE d.id = " . (int)$id . " AND d.deleted_at IS NULL
             LIMIT 1");
    }

    public function getItems($demandId)
    {
        return Database::fetchAll($this->db,
            "SELECT di.*,
                    COALESCE(NULLIF(di.product_name,''),
                             NULLIF(pd_uk.name,''), NULLIF(pd_ru.name,''), '') AS name,
                    COALESCE(NULLIF(di.sku,''), pp.product_article, '') AS article,
                    pp.quantity AS stock_quantity
             FROM demand_item di
             LEFT JOIN product_papir pp    ON pp.product_id = di.product_id
             LEFT JOIN product_description pd_uk ON pd_uk.product_id = di.product_id AND pd_uk.language_id = 2
             LEFT JOIN product_description pd_ru ON pd_ru.product_id = di.product_id AND pd_ru.language_id = 1
             WHERE di.demand_id = " . (int)$demandId . "
             ORDER BY di.line_no ASC");
    }

    public function getOrganizations()
    {
        return Database::fetchAll($this->db,
            "SELECT id, COALESCE(NULLIF(short_name,''), name) AS label FROM organization WHERE status = 1 ORDER BY name");
    }

    public function getManagers()
    {
        return Database::fetchAll($this->db,
            "SELECT e.id, COALESCE(NULLIF(e.full_name,''), u.display_name) AS label
             FROM employee e LEFT JOIN auth_users u ON u.employee_id = e.id
             WHERE e.status=1 ORDER BY label");
    }

    public function softDelete($id)
    {
        return Database::update($this->db, 'demand',
            array('deleted_at' => date('Y-m-d H:i:s'), 'customerorder_id' => null),
            array('id' => (int)$id));
    }

    public function upsertFromMs(array $data)
    {
        $msId = Database::escape($this->db, $data['id_ms']);
        $r = Database::fetchRow($this->db,
            "SELECT id FROM demand WHERE id_ms = '{$msId}' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) {
            return Database::update($this->db, 'demand', $data, array('id' => (int)$r['row']['id']));
        }
        if (!isset($data['uuid'])) {
            $data['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff),
                mt_rand(0,0x0fff)|0x4000,mt_rand(0,0x3fff)|0x8000,
                mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
        }
        return Database::insert($this->db, 'demand', $data);
    }

    public function syncItemsFromMs($demandId, array $positions, array $productMap)
    {
        Database::query($this->db,
            "DELETE FROM demand_item WHERE demand_id = " . (int)$demandId);
        $ln = 1;
        foreach ($positions as $pos) {
            $productMsId = isset($pos['product_ms_id']) ? $pos['product_ms_id'] : null;
            $productId   = ($productMsId && isset($productMap[$productMsId])) ? $productMap[$productMsId] : null;
            Database::insert($this->db, 'demand_item', array(
                'demand_id'       => $demandId,
                'line_no'         => $ln++,
                'product_id'      => $productId,
                'product_ms_id'   => $productMsId,
                'product_name'    => isset($pos['product_name']) ? mb_substr($pos['product_name'],0,255,'UTF-8') : null,
                'sku'             => isset($pos['sku']) ? mb_substr($pos['sku'],0,64,'UTF-8') : null,
                'quantity'        => isset($pos['quantity']) ? (float)$pos['quantity'] : 0,
                'price'           => isset($pos['price']) ? (float)$pos['price'] : 0,
                'discount_percent'=> isset($pos['discount']) ? (float)$pos['discount'] : 0,
                'vat_rate'        => isset($pos['vat']) ? (float)$pos['vat'] : 0,
                'sum_row'         => isset($pos['sum_row']) ? (float)$pos['sum_row'] : 0,
                'shipped_quantity'=> 0,
                'reserve'         => 0,
                'in_transit'      => 0,
                'overhead'        => 0,
            ));
        }
    }

    public function markDeleted($msId)
    {
        return Database::query($this->db,
            "UPDATE demand SET deleted_at = NOW()
             WHERE id_ms = '" . Database::escape($this->db, $msId) . "'
               AND deleted_at IS NULL");
    }
}