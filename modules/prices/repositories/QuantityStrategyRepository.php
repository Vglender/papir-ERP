<?php

class QuantityStrategyRepository
{
    private $dbName = 'Papir';

    public function getAll()
    {
        $result = Database::fetchAll($this->dbName,
            "SELECT * FROM `price_quantity_strategy` WHERE is_active = 1 ORDER BY sort_order ASC"
        );

        return $result['ok'] ? $result['rows'] : [];
    }

    public function getDefault()
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT * FROM `price_quantity_strategy` WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 1"
        );

        return $result['ok'] ? (isset($result['row']) ? $result['row'] : []) : [];
    }

    public function getById($id)
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT * FROM `price_quantity_strategy` WHERE id = {$id}"
        );

        return $result['ok'] ? (isset($result['row']) ? $result['row'] : []) : [];
    }
}
