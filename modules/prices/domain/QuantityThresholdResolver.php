<?php

/**
 * Определяет пороговые количества для 3 уровней скидок.
 *
 * Приоритет:
 *   1. Упаковки товара (product_package) — если заполнены все 3 уровня
 *   2. Стратегия по целевой сумме (price_quantity_strategy)
 *
 * Торгово-понятные шаги для округления: 1, 2, 3, 5, 10, 15, 20, 25, 50, 100, 150, 200, 250, 500
 */
class QuantityThresholdResolver
{
    const SMART_STEPS = [1, 2, 3, 5, 10, 15, 20, 25, 50, 100, 150, 200, 250, 500];

    /**
     * @param array $packages  Строки из product_package (уровни 1, 2, 3)
     * @param array $strategy  Строка из price_quantity_strategy
     * @param float $salePrice Розничная цена
     * @return array{qty_1: int, qty_2: int, qty_3: int, source: string}
     */
    public function resolve(array $packages, array $strategy, $salePrice)
    {
        if ($this->hasAllPackages($packages)) {
            return [
                'qty_1'  => (int)$packages[0]['quantity'],
                'qty_2'  => (int)$packages[1]['quantity'],
                'qty_3'  => (int)$packages[2]['quantity'],
                'source' => 'packages',
            ];
        }

        return $this->resolveByAmount($strategy, $salePrice);
    }

    private function hasAllPackages(array $packages)
    {
        if (count($packages) < 3) {
            return false;
        }

        foreach ($packages as $p) {
            if (empty($p['quantity']) || (int)$p['quantity'] <= 0) {
                return false;
            }
        }

        return true;
    }

    private function resolveByAmount(array $strategy, $salePrice)
    {
        if ($salePrice <= 0) {
            return ['qty_1' => 5, 'qty_2' => 10, 'qty_3' => 20, 'source' => 'strategy'];
        }

        $qty1 = $this->roundToStep((float)(isset($strategy['target_amount_small'])  ? $strategy['target_amount_small']  : 500)  / $salePrice);
        $qty2 = $this->roundToStep((float)(isset($strategy['target_amount_medium']) ? $strategy['target_amount_medium'] : 2000) / $salePrice);
        $qty3 = $this->roundToStep((float)(isset($strategy['target_amount_large'])  ? $strategy['target_amount_large']  : 5000) / $salePrice);

        // Минимальный порог первой скидки — 2 шт (скидка от 1 шт не имеет смысла)
        $qty1 = max(2, $qty1);

        $minGap = (float)(isset($strategy['min_gap_ratio']) ? $strategy['min_gap_ratio'] : 1.2);

        // Гарантируем что каждый следующий порог больше предыдущего с min_gap
        $qty2 = max($qty2, (int)ceil($qty1 * $minGap));
        $qty3 = max($qty3, (int)ceil($qty2 * $minGap));

        // Повторно округляем после корректировки
        $qty2 = $this->roundToStep((float)$qty2);
        $qty3 = $this->roundToStep((float)$qty3);

        return [
            'qty_1'  => $qty1,
            'qty_2'  => $qty2,
            'qty_3'  => $qty3,
            'source' => 'strategy',
        ];
    }

    private function roundToStep($raw)
    {
        $raw = max(1, $raw);

        $closest = self::SMART_STEPS[0];
        $minDiff = PHP_INT_MAX;

        foreach (self::SMART_STEPS as $step) {
            $diff = abs($raw - $step);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $step;
            }
        }

        return $closest;
    }
}
