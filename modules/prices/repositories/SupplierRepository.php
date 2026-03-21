<?php

/**
 * CRUD для реестра поставщиков (price_suppliers).
 */
class SupplierRepository
{
    /** @return array  Все поставщики */
    public function getAll()
    {
        $sql    = "SELECT * FROM `price_suppliers` ORDER BY `sort_order` ASC, `id` ASC";
        $result = Database::fetchAll('Papir', $sql);
        return ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();
    }

    /** @return array|null */
    public function getById($id)
    {
        $sql    = "SELECT * FROM `price_suppliers` WHERE `id` = " . (int)$id . " LIMIT 1";
        $result = Database::fetchRow('Papir', $sql);
        return ($result['ok'] && !empty($result['row'])) ? $result['row'] : null;
    }

    /** @return array|null */
    public function getByCode($code)
    {
        $escaped = Database::escape('Papir', (string)$code);
        $sql     = "SELECT * FROM `price_suppliers` WHERE `code` = '$escaped' LIMIT 1";
        $result  = Database::fetchRow('Papir', $sql);
        return ($result['ok'] && !empty($result['row'])) ? $result['row'] : null;
    }

    /**
     * @param array $data  keys: code, name, source_type, is_active, is_cost_source, sort_order, notes
     * @return array  ['ok' => bool, 'id' => int]
     */
    public function create(array $data)
    {
        return Database::insert('Papir', 'price_suppliers', $data);
    }

    /**
     * @param int   $id
     * @param array $data
     * @return array  ['ok' => bool]
     */
    public function update($id, array $data)
    {
        return Database::update('Papir', 'price_suppliers', $data, array('id' => (int)$id));
    }

    /**
     * @param int $id
     * @return array  ['ok' => bool]
     */
    public function delete($id)
    {
        return Database::delete('Papir', 'price_suppliers', array('id' => (int)$id));
    }

    /**
     * @param int $supplierId
     * @return int
     */
    public function getPricelistCount($supplierId)
    {
        $result = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM `price_supplier_pricelists` WHERE `supplier_id` = " . (int)$supplierId
        );
        return ($result['ok'] && !empty($result['row'])) ? (int)$result['row']['cnt'] : 0;
    }

    /** Конфиг Google Sheets для поставщика */
    public function getSheetConfig($supplierId)
    {
        $sql    = "SELECT * FROM `price_supplier_sheet_config`
                   WHERE `supplier_id` = " . (int)$supplierId . " LIMIT 1";
        $result = Database::fetchRow('Papir', $sql);
        return ($result['ok'] && !empty($result['row'])) ? $result['row'] : array();
    }

    /**
     * @param int   $supplierId
     * @param array $data
     */
    public function saveSheetConfig($supplierId, array $data)
    {
        $supplierId = (int)$supplierId;
        $data['supplier_id'] = $supplierId;
        $data['updated_at']  = date('Y-m-d H:i:s');

        $existing = $this->getSheetConfig($supplierId);
        if (empty($existing)) {
            Database::insert('Papir', 'price_supplier_sheet_config', $data);
        } else {
            Database::update('Papir', 'price_supplier_sheet_config', $data, array('supplier_id' => $supplierId));
        }
    }
}
