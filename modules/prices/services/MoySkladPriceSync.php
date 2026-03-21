<?php

/**
 * Синхронизирует себестоимость из ms.stock_ в price_supplier_items.
 *
 * ms.stock_.price → price_cost (себестоимость)
 * salePrice НЕ импортируется как price_rrp — это наша собственная розничная
 * цена отражённая в МойСклад, импорт создавал цикл: price_sale → МС → price_rrp → price_sale
 * Связь: ms.stock_.model = product_papir.id_off
 *        ms.stock_.sku   = product_papir.product_article
 */
class MoySkladPriceSync
{
    /** @var SupplierRepository */
    private $supplierRepo;

    /** @var PricelistRepository */
    private $pricelistRepo;

    /** @var PricelistItemRepository */
    private $itemRepo;

    public function __construct(
        SupplierRepository     $supplierRepo,
        PricelistRepository    $pricelistRepo,
        PricelistItemRepository $itemRepo
    ) {
        $this->supplierRepo  = $supplierRepo;
        $this->pricelistRepo = $pricelistRepo;
        $this->itemRepo      = $itemRepo;
    }

    /**
     * @param int $pricelistId
     * @return array  ['ok'=>bool, 'imported'=>int, 'matched'=>int, 'error'=>string]
     */
    public function sync($pricelistId)
    {
        $pricelist = $this->pricelistRepo->getById($pricelistId);
        if (!$pricelist || $pricelist['source_type'] !== 'moy_sklad') {
            return array('ok' => false, 'error' => 'Pricelist not found or wrong type', 'imported' => 0, 'matched' => 0);
        }

        $result = Database::fetchAll('ms',
            "SELECT `model`, `sku`, `name`, `price`
             FROM `stock_`
             WHERE `price` IS NOT NULL AND `price` > 0"
        );
        if (!$result['ok']) {
            return array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Failed to read ms.stock_', 'imported' => 0, 'matched' => 0);
        }

        $rawRows = array();
        foreach ($result['rows'] as $r) {
            $model = isset($r['model']) ? trim((string)$r['model']) : '';
            $sku   = isset($r['sku'])   ? trim((string)$r['sku'])   : '';
            if ($model === '' && $sku === '') continue;

            $rawRows[] = array(
                'raw_model'  => $model,
                'raw_sku'    => $sku,
                'raw_name'   => isset($r['name']) ? (string)$r['name'] : '',
                'price_cost' => isset($r['price']) && $r['price'] > 0 ? (float)$r['price'] : null,
                'price_rrp'  => null,
            );
        }

        $stats = $this->itemRepo->replaceAll($pricelistId, $rawRows);
        $this->pricelistRepo->refreshStats($pricelistId);

        return array(
            'ok'      => true,
            'imported' => $stats['inserted'],
            'matched'  => $stats['matched'],
            'error'   => '',
        );
    }
}
