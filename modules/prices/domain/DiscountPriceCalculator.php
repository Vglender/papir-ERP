<?php

/**
 * Рассчитывает 3 уровня скидочных цен.
 *
 * Формула: discount_price = sale - (sale - wholesale) * discount_percent / 100
 *
 * price_floor_mode = 'wholesale_limit' — цена не может быть ниже оптовой.
 */
class DiscountPriceCalculator
{
    /**
     * @param float $salePrice
     * @param float $wholesalePrice
     * @param array $strategy   Строка из price_discount_strategy
     * @param array $thresholds ['qty_1', 'qty_2', 'qty_3', 'source']
     * @return array  3 уровня: [['qty', 'discount_percent', 'price'], ...]
     */
    public function calculate(
        $salePrice,
        $wholesalePrice,
        array $strategy,
        array $thresholds
    ) {
        $levels = [
            ['qty' => $thresholds['qty_1'], 'percent' => (float)$strategy['small_discount_percent']],
            ['qty' => $thresholds['qty_2'], 'percent' => (float)$strategy['medium_discount_percent']],
            ['qty' => $thresholds['qty_3'], 'percent' => (float)$strategy['large_discount_percent']],
        ];

        $floorMode = isset($strategy['price_floor_mode']) ? $strategy['price_floor_mode'] : 'wholesale_limit';
        $result    = [];

        foreach ($levels as $level) {
            $price = $salePrice - ($salePrice - $wholesalePrice) * $level['percent'] / 100;
            $price = round($price, 2);

            if ($floorMode === 'wholesale_limit') {
                $price = max($price, $wholesalePrice);
            }

            $result[] = [
                'qty'              => (int)$level['qty'],
                'discount_percent' => $level['percent'],
                'price'            => $price,
            ];
        }

        return $result;
    }
}
