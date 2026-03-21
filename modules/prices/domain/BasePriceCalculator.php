<?php

/**
 * Рассчитывает базовые цены от закупочной.
 *
 * price_sale      = price_purchase * (1 + sale_markup / 100)
 * price_wholesale = price_purchase * (1 + wholesale_markup / 100)
 * price_dealer    = price_purchase * (1 + dealer_markup / 100)
 */
class BasePriceCalculator
{
    /**
     * @param float $purchasePrice
     * @param array $markups  ['sale' => %, 'wholesale' => %, 'dealer' => %]
     * @return array{sale: float, wholesale: float, dealer: float}
     */
    public function calculate($purchasePrice, array $markups)
    {
        if ($purchasePrice <= 0) {
            return ['sale' => 0.0, 'wholesale' => 0.0, 'dealer' => 0.0];
        }

        return [
            'sale'      => $this->applyMarkup($purchasePrice, (float)(isset($markups['sale'])      ? $markups['sale']      : 0)),
            'wholesale' => $this->applyMarkup($purchasePrice, (float)(isset($markups['wholesale']) ? $markups['wholesale'] : 0)),
            'dealer'    => $this->applyMarkup($purchasePrice, (float)(isset($markups['dealer'])    ? $markups['dealer']    : 0)),
        ];
    }

    private function applyMarkup($base, $percent)
    {
        return round($base * (1 + $percent / 100), 2);
    }
}
