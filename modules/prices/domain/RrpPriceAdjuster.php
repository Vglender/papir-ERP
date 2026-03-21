<?php

/**
 * Применяет RRP к розничной цене.
 *
 * Если use_rrp = 1 и RRP задан — final_sale = rrp.
 * Иначе — final_sale = calculated_sale.
 */
class RrpPriceAdjuster
{
    /**
     * @param float      $calculatedSale  Цена, рассчитанная от закупочной
     * @param float|null $rrp             RRP (может отсутствовать)
     * @param bool       $useRrp
     * @return array{price: float, rrp_applied: bool}
     */
    public function adjust($calculatedSale, $rrp, $useRrp)
    {
        if ($useRrp && $rrp !== null && $rrp > 0) {
            return [
                'price'       => $rrp,
                'rrp_applied' => true,
            ];
        }

        return [
            'price'       => $calculatedSale,
            'rrp_applied' => false,
        ];
    }
}
