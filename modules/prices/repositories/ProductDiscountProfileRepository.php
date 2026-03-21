<?php

class ProductDiscountProfileRepository
{
    private $dbName = 'Papir';

    public function getByProductId($productId)
    {
        $result = Database::fetchRow($this->dbName,
            "SELECT * FROM `product_discount_profile` WHERE product_id = {$productId}"
        );

        return $result['ok'] ? (isset($result['row']) ? $result['row'] : []) : [];
    }

    public function save($productId, array $data)
    {
        $data['product_id']   = $productId;
        $data['calculated_at'] = date('Y-m-d H:i:s');

        return Database::upsertOne($this->dbName, 'product_discount_profile', $data, 'product_id');
    }
}
