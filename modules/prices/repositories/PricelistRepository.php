<?php

/**
 * Репозиторий прайс-листов поставщиков (price_supplier_pricelists).
 */
class PricelistRepository
{
    /** @return array */
    public function getAll()
    {
        $result = Database::fetchAll('Papir',
            "SELECT ppl.*, ps.name AS supplier_name, ps.source_type AS supplier_source_type,
                    ps.is_cost_source, ps.is_active AS supplier_active
             FROM `price_supplier_pricelists` ppl
             JOIN `price_suppliers` ps ON ps.id = ppl.supplier_id
             ORDER BY ps.sort_order ASC, ppl.supplier_id ASC, ppl.id ASC"
        );
        return ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();
    }

    /** @return array  grouped by supplier_id */
    public function getAllGroupedBySupplier()
    {
        $rows = $this->getAll();
        $grouped = array();
        foreach ($rows as $row) {
            $sid = (int)$row['supplier_id'];
            if (!isset($grouped[$sid])) {
                $grouped[$sid] = array(
                    'id'          => $sid,
                    'name'        => $row['supplier_name'],
                    'source_type' => $row['supplier_source_type'],
                    'is_cost_source' => $row['is_cost_source'],
                    'pricelists'  => array(),
                );
            }
            $grouped[$sid]['pricelists'][] = $row;
        }
        return array_values($grouped);
    }

    /** @return array|null */
    public function getById($id)
    {
        $result = Database::fetchRow('Papir',
            "SELECT ppl.*, ps.name AS supplier_name, ps.is_cost_source
             FROM `price_supplier_pricelists` ppl
             JOIN `price_suppliers` ps ON ps.id = ppl.supplier_id
             WHERE ppl.id = " . (int)$id . " LIMIT 1"
        );
        return ($result['ok'] && !empty($result['row'])) ? $result['row'] : null;
    }

    /**
     * @param int   $supplierId
     * @param array $data  name, source_type, source_config (JSON string or array)
     * @return array  ['ok'=>bool, 'id'=>int]
     */
    public function create($supplierId, array $data)
    {
        if (isset($data['source_config']) && is_array($data['source_config'])) {
            $data['source_config'] = json_encode($data['source_config']);
        }
        $data['supplier_id'] = (int)$supplierId;
        return Database::insert('Papir', 'price_supplier_pricelists', $data);
    }

    /**
     * @param int   $id
     * @param array $data
     */
    public function update($id, array $data)
    {
        if (isset($data['source_config']) && is_array($data['source_config'])) {
            $data['source_config'] = json_encode($data['source_config']);
        }
        return Database::update('Papir', 'price_supplier_pricelists', $data, array('id' => (int)$id));
    }

    /** @return array  ['ok'=>bool] */
    public function delete($id)
    {
        return Database::delete('Papir', 'price_supplier_pricelists', array('id' => (int)$id));
    }

    /** Обновить статистику после импорта */
    public function refreshStats($pricelistId)
    {
        $pricelistId = (int)$pricelistId;
        $sql = "UPDATE `price_supplier_pricelists` ppl
                SET
                  ppl.items_total   = (SELECT COUNT(*) FROM `price_supplier_items` WHERE pricelist_id = $pricelistId),
                  ppl.items_matched = (SELECT COUNT(*) FROM `price_supplier_items` WHERE pricelist_id = $pricelistId AND product_id IS NOT NULL AND match_type != 'ignored'),
                  ppl.last_synced_at = NOW()
                WHERE ppl.id = $pricelistId";
        Database::query('Papir', $sql);
    }

    /** Декодировать source_config из JSON */
    public function decodeConfig(array $pricelist)
    {
        if (empty($pricelist['source_config'])) {
            return array();
        }
        $cfg = json_decode($pricelist['source_config'], true);
        return is_array($cfg) ? $cfg : array();
    }
}
