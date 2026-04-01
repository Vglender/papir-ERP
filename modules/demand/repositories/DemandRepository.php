<?php

class DemandRepository
{
    private $db = 'Papir';

    public function getList($filters = array(), $page = 1, $limit = 50)
    {
        $where = 'd.deleted_at IS NULL';

        // Search (chip)
        $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
        if ($search !== '') {
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $chips = array_filter(array_map('trim', preg_split($chipSep, $search)));
            $chipConds = array();
            foreach ($chips as $chip) {
                if (preg_match('/^\d+$/', $chip)) {
                    $chipConds[] = "d.id = " . (int)$chip;
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
            if ($chipConds) $where .= ' AND (' . implode(' OR ', $chipConds) . ')';
        }

        // Status filter
        if (!empty($filters['status'])) {
            $statuses = array_map(function($s) { return "'" . Database::escape('Papir', $s) . "'"; },
                (array)$filters['status']);
            $where .= ' AND d.status IN (' . implode(',', $statuses) . ')';
        }

        // Organization filter (via customerorder)
        if (!empty($filters['organization_id'])) {
            $orgId = (int)$filters['organization_id'];
            $where .= " AND co.organization_id = {$orgId}";
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $df = Database::escape($this->db, $filters['date_from']);
            $where .= " AND DATE(d.moment) >= '{$df}'";
        }
        if (!empty($filters['date_to'])) {
            $dt = Database::escape($this->db, $filters['date_to']);
            $where .= " AND DATE(d.moment) <= '{$dt}'";
        }

        $offset = ($page - 1) * $limit;

        $sql = "SELECT d.id, d.number, d.moment, d.status, d.sum_total, d.sum_paid,
                       d.customerorder_id, d.sync_state, d.id_ms,
                       co.number AS order_number, co.organization_id,
                       o.name AS org_name,
                       cp.name AS counterparty_name
                FROM demand d
                LEFT JOIN customerorder co ON co.id = d.customerorder_id
                LEFT JOIN organization o   ON o.id  = co.organization_id
                LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
                WHERE {$where}
                ORDER BY d.moment DESC, d.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) AS cnt
                     FROM demand d
                     LEFT JOIN customerorder co ON co.id = d.customerorder_id
                     LEFT JOIN organization o   ON o.id  = co.organization_id
                     LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
                     WHERE {$where}";

        $rows  = Database::fetchAll($this->db, $sql);
        $count = Database::fetchRow($this->db, $countSql);

        return array(
            'ok'    => $rows['ok'],
            'rows'  => $rows['ok'] ? $rows['rows'] : array(),
            'total' => ($count['ok'] && $count['row']) ? (int)$count['row']['cnt'] : 0,
        );
    }

    public function getById($id)
    {
        return Database::fetchRow($this->db,
            "SELECT d.*,
                    co.number AS order_number, co.organization_id, co.id_ms AS order_ms_id,
                    o.name AS org_name,
                    cp.name AS counterparty_name, cp.id_ms AS cp_ms_id
             FROM demand d
             LEFT JOIN customerorder co ON co.id = d.customerorder_id
             LEFT JOIN organization o   ON o.id  = co.organization_id
             LEFT JOIN counterparty cp  ON cp.id = d.counterparty_id
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
            "SELECT id, name FROM organization WHERE status = 1 ORDER BY name");
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