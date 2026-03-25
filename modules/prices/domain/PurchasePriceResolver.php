<?php

/**
 * Определяет итоговую закупочную цену товара.
 *
 * Источники (в порядке приоритета):
 *   1. manual      — ручное значение из product_price_settings
 *   2. supplier_db — лучшая цена из price_supplier_items (через PricelistItemRepository)
 *   3. price_supplier / price_accounting_cost — прямые поля product_papir (берём большую)
 *   4. legacy      — старое поле price_cost (фоллбэк)
 *
 * RRP из supplier_db также передаётся в результате для использования в PriceEngine.
 */
class PurchasePriceResolver
{
    /** @var PricelistItemRepository|null */
    private $itemRepo;

    public function __construct(PricelistItemRepository $itemRepo = null)
    {
        $this->itemRepo = $itemRepo;
    }

    /**
     * @param array $product   Строка из product_papir
     * @param array $settings  Строка из product_price_settings (может быть пустой)
     * @return array  ['price' => float, 'source' => string, 'price_rrp' => float|null]
     */
    public function resolve(array $product, array $settings = array())
    {
        // 1. Ручное значение
        if (!empty($settings['manual_cost_enabled']) && !empty($settings['manual_cost'])) {
            return array(
                'price'     => (float)$settings['manual_cost'],
                'source'    => 'manual',
                'price_rrp' => null,
            );
        }

        // 2. Из прайс-листов поставщиков (price_supplier_items, сопоставленные по product_id)
        $rrpFromDb = null;
        if ($this->itemRepo !== null) {
            $productId = isset($product['product_id']) ? (int)$product['product_id'] : 0;
            $best = $this->itemRepo->getBestCostPrice($productId);
            if ($best['price_cost'] !== null && $best['price_cost'] > 0) {
                return array(
                    'price'     => $best['price_cost'],
                    'source'    => 'supplier_db',
                    'price_rrp' => $best['price_rrp'],
                );
            }
            $rrpFromDb = $best['price_rrp'];
        }

        // 3. Прямые поля product_papir (старый подход: price_supplier / price_accounting_cost)
        $supplier   = (float)(isset($product['price_supplier'])        ? $product['price_supplier']        : 0);
        $accounting = (float)(isset($product['price_accounting_cost']) ? $product['price_accounting_cost'] : 0);

        if ($supplier > 0 || $accounting > 0) {
            if ($supplier >= $accounting) {
                return array('price' => $supplier,   'source' => 'supplier',    'price_rrp' => $rrpFromDb);
            }
            return array('price' => $accounting, 'source' => 'accounting', 'price_rrp' => $rrpFromDb);
        }

        // 4. Фоллбэк на старое поле price_cost
        $legacy = (float)(isset($product['price_cost']) ? $product['price_cost'] : 0);
        if ($legacy > 0) {
            return array('price' => $legacy, 'source' => 'legacy', 'price_rrp' => $rrpFromDb);
        }

        return array('price' => 0.0, 'source' => 'none', 'price_rrp' => $rrpFromDb);
    }
}
