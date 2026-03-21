<?php

/**
 * Определяет, какую стратегию скидок применить к товару.
 *
 * Если задана вручную (discount_strategy_manual = 1) — используем стратегию товара.
 * Иначе — auto (по умолчанию soft).
 */
class DiscountStrategyResolver
{
    const DEFAULT_CODE = 'soft';

    /**
     * @param array $product    Строка product_papir
     * @param array $settings   Строка product_price_settings
     * @param array $strategies Список стратегий из price_discount_strategy
     * @return array  Строка стратегии или пустой массив если не найдена
     */
    public function resolve(array $product, array $settings, array $strategies)
    {
        $indexed = $this->indexById($strategies);

        // Кастомная стратегия (ручные проценты без strategy_id) на уровне настроек товара
        if (!empty($settings['discount_strategy_manual'])
            && empty($settings['discount_strategy_id'])
            && isset($settings['custom_small_discount_percent'])
            && (float)$settings['custom_small_discount_percent'] > 0
        ) {
            return array(
                'id'                      => null,
                'code'                    => 'custom',
                'name'                    => 'Ручная',
                'small_discount_percent'  => (float)$settings['custom_small_discount_percent'],
                'medium_discount_percent' => (float)(isset($settings['custom_medium_discount_percent']) ? $settings['custom_medium_discount_percent'] : 0),
                'large_discount_percent'  => (float)(isset($settings['custom_large_discount_percent'])  ? $settings['custom_large_discount_percent']  : 0),
                'price_floor_mode'        => 'wholesale_limit',
            );
        }

        // Ручная стратегия на уровне товара
        if (!empty($product['discount_strategy_manual']) && !empty($product['discount_strategy_id'])) {
            return isset($indexed[$product['discount_strategy_id']]) ? $indexed[$product['discount_strategy_id']] : $this->getDefault($strategies);
        }

        // Ручная стратегия на уровне настроек товара
        if (!empty($settings['discount_strategy_manual']) && !empty($settings['discount_strategy_id'])) {
            return isset($indexed[$settings['discount_strategy_id']]) ? $indexed[$settings['discount_strategy_id']] : $this->getDefault($strategies);
        }

        return $this->getDefault($strategies);
    }

    private function getDefault(array $strategies)
    {
        foreach ($strategies as $s) {
            if ($s['code'] === self::DEFAULT_CODE) {
                return $s;
            }
        }

        return isset($strategies[0]) ? $strategies[0] : array();
    }

    private function indexById(array $strategies)
    {
        $result = array();
        foreach ($strategies as $s) {
            $result[$s['id']] = $s;
        }
        return $result;
    }
}
