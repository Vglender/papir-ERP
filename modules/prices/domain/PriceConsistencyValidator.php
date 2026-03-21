<?php

/**
 * Валидирует итоговый ценовой профиль на логическую согласованность.
 */
class PriceConsistencyValidator
{
    /**
     * @param array $profile Результат PriceEngine::calculate()
     * @return array{ok: bool, errors: string[]}
     */
    public function validate(array $profile)
    {
        $errors = [];

        $purchase  = isset($profile['price_purchase'])  ? $profile['price_purchase']  : 0;
        $sale      = isset($profile['price_sale'])      ? $profile['price_sale']      : 0;
        $wholesale = isset($profile['price_wholesale']) ? $profile['price_wholesale'] : 0;
        $dealer    = isset($profile['price_dealer'])    ? $profile['price_dealer']    : 0;

        if ($purchase <= 0) {
            $errors[] = 'Закупочная цена не задана';
        }

        if ($sale > 0 && $wholesale > 0 && $wholesale > $sale) {
            $errors[] = 'Оптовая цена выше розничной';
        }

        if ($wholesale > 0 && $dealer > 0 && $dealer > $wholesale) {
            $errors[] = 'Дилерская цена выше оптовой';
        }

        if ($sale > 0 && $purchase > 0 && $sale < $purchase) {
            $errors[] = 'Розничная цена ниже закупочной';
        }

        if (!empty($profile['discounts'])) {
            foreach ($profile['discounts'] as $i => $level) {
                $level_num = $i + 1;
                if ($level['price'] < $wholesale && $wholesale > 0) {
                    $errors[] = "Скидочная цена уровня {$level_num} ниже оптовой";
                }
            }
        }

        return [
            'ok'     => empty($errors),
            'errors' => $errors,
        ];
    }
}
