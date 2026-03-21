<?php

/**
 * Пересчёт цен: одиночный и массовый.
 */
class PriceRecalculationService
{
    private $builder;
    private $productRepo;

    public function __construct(
        DiscountProfileBuilder $builder,
        ProductPriceRepository $productRepo
    ) {
        $this->builder     = $builder;
        $this->productRepo = $productRepo;
    }

    /** Пересчитать один товар */
    public function recalculateOne($productId)
    {
        return $this->builder->build($productId);
    }

    /** Пересчитать все товары постранично */
    public function recalculateAll($limit = 100)
    {
        $offset  = 0;
        $updated = 0;
        $errors  = [];

        while (true) {
            $page = $this->productRepo->getList([], 'product_id', 'asc', $offset, $limit);

            if (!$page['ok'] || empty($page['rows'])) {
                break;
            }

            foreach ($page['rows'] as $row) {
                $result = $this->builder->build((int)$row['product_id']);

                if ($result['ok']) {
                    $updated++;
                } else {
                    $errors[] = $result;
                }
            }

            if (count($page['rows']) < $limit) {
                break;
            }

            $offset += $limit;
        }

        return [
            'ok'      => empty($errors),
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }
}
