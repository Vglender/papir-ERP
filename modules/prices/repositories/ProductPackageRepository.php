<?php

class ProductPackageRepository
{
    private $dbName = 'Papir';

    /** @return array  Отсортированные уровни 1, 2, 3 */
    public function getByProductId($productId)
    {
        $result = Database::fetchAll($this->dbName,
            "SELECT * FROM `product_package`
             WHERE product_id = {$productId}
             ORDER BY level ASC"
        );

        return $result['ok'] ? $result['rows'] : [];
    }

    public function save($productId, $level, $name, $quantity)
    {
        $exists = Database::exists($this->dbName, 'product_package', [
            'product_id' => $productId,
            'level'      => $level,
        ]);

        $data = [
            'name'     => $name,
            'quantity' => $quantity,
        ];

        if ($exists['ok'] && $exists['exists']) {
            return Database::update($this->dbName, 'product_package', $data, [
                'product_id' => $productId,
                'level'      => $level,
            ]);
        }

        $data['product_id'] = $productId;
        $data['level']      = $level;

        return Database::insert($this->dbName, 'product_package', $data);
    }

    public function deleteByProductId($productId)
    {
        return Database::delete($this->dbName, 'product_package', ['product_id' => $productId]);
    }
}
