<?php

/**
 * Автоматически выбирает стратегию скидок для товара.
 * Сейчас — всегда soft. Здесь можно добавить логику по категории, марже и т.д.
 */
class PriceStrategyAutoSelector
{
    private $discountStrategyRepo;

    public function __construct(DiscountStrategyRepository $discountStrategyRepo)
    {
        $this->discountStrategyRepo = $discountStrategyRepo;
    }

    public function select(array $product)
    {
        $strategies = $this->discountStrategyRepo->getAll();

        foreach ($strategies as $strategy) {
            if ($strategy['code'] === 'soft') {
                return $strategy;
            }
        }

        return isset($strategies[0]) ? $strategies[0] : [];
    }
}
