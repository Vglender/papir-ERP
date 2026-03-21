<?php

class ActionPriceCalculator
{
    /** @var ActionRepository */
    private $actionRepo;

    /** @var ActionPriceRepository */
    private $priceRepo;

    /**
     * @param ActionRepository      $actionRepo
     * @param ActionPriceRepository $priceRepo
     */
    public function __construct($actionRepo, $priceRepo)
    {
        $this->actionRepo = $actionRepo;
        $this->priceRepo  = $priceRepo;
    }

    /**
     * Calculate promotional prices and save to action_prices.
     *
     * @return array
     */
    public function calculate()
    {
        // 1. Get all actions
        $actions = $this->actionRepo->getAll();

        if (empty($actions)) {
            return array('ok' => true, 'calculated' => 0, 'message' => 'No actions found');
        }

        // 2. Get stock from ms with virtual stock
        $stockSql = "SELECT
                         CAST(s.`model` AS UNSIGNED) AS product_id,
                         (s.`stock` + COALESCE(v.`stock`, 0)) AS stock
                     FROM `stock_` s
                     LEFT JOIN `virtual` v
                         ON v.`product_id` = CAST(s.`model` AS UNSIGNED)
                     WHERE CAST(s.`model` AS UNSIGNED) > 0";

        $stockResult = Database::fetchAll('ms', $stockSql);

        // 3. Build stockMap[product_id] => stock
        $stockMap = array();
        if ($stockResult['ok'] && !empty($stockResult['rows'])) {
            foreach ($stockResult['rows'] as $row) {
                $pid = (int)$row['product_id'];
                if ($pid > 0) {
                    $stockMap[$pid] = (int)$row['stock'];
                }
            }
        }

        // Collect product IDs that have stock > 0
        $productIdsWithStock = array();
        foreach ($actions as $action) {
            $pid = (int)$action['product_id'];
            if (isset($stockMap[$pid]) && $stockMap[$pid] > 0) {
                $productIdsWithStock[] = $pid;
            }
        }

        if (empty($productIdsWithStock)) {
            return array('ok' => true, 'calculated' => 0, 'message' => 'No actions with stock > 0');
        }

        // 4. Batch fetch product prices from Papir
        $inList       = implode(',', $productIdsWithStock);
        $productsSql  = "SELECT `id_off`, `price`, `price_cost`
                         FROM `product_papir`
                         WHERE `id_off` IN (" . $inList . ")";

        $productsResult = Database::fetchAll('Papir', $productsSql);

        // Build priceMap[id_off] => array(price, price_cost)
        $priceMap = array();
        if ($productsResult['ok'] && !empty($productsResult['rows'])) {
            foreach ($productsResult['rows'] as $row) {
                $pid = (int)$row['id_off'];
                $priceMap[$pid] = array(
                    'price'      => (float)$row['price'],
                    'price_cost' => (float)$row['price_cost'],
                );
            }
        }

        // 5. Build actions index
        $actionsIndex = array();
        foreach ($actions as $action) {
            $pid = (int)$action['product_id'];
            $actionsIndex[$pid] = $action;
        }

        // 6. Calculate price_act for each product with stock > 0
        $calculatedAt = date('Y-m-d H:i:s');
        $rows         = array();

        foreach ($productIdsWithStock as $pid) {
            if (!isset($priceMap[$pid])) {
                // Product not found in Papir — skip
                continue;
            }

            $action       = $actionsIndex[$pid];
            $prices       = $priceMap[$pid];
            $discount     = (int)$action['discount'];
            $superDiscont = (int)$action['super_discont'];
            $price        = $prices['price'];
            $priceCost    = $prices['price_cost'];
            $stock        = $stockMap[$pid];

            // Price calculation logic
            if ($superDiscont > 0) {
                $priceAct     = $priceCost - $priceCost * $superDiscont / 100;
                $discountType = 'super';
            } else {
                $priceAct     = $price - ($price - $priceCost) * $discount / 100;
                $discountType = 'regular';
            }

            $rows[] = array(
                'product_id'    => $pid,
                'price_act'     => round($priceAct, 4),
                'price_base'    => $price,
                'price_cost'    => $priceCost,
                'stock'         => $stock,
                'discount'      => $discount,
                'super_discont' => $superDiscont,
                'discount_type' => $discountType,
                'calculated_at' => $calculatedAt,
            );
        }

        if (empty($rows)) {
            return array('ok' => true, 'calculated' => 0, 'message' => 'No rows to save after price lookup');
        }

        // 7. Save to action_prices
        $saveResult = $this->priceRepo->saveAll($rows);

        if (!$saveResult['ok']) {
            return array(
                'ok'    => false,
                'error' => isset($saveResult['error']) ? $saveResult['error'] : 'Save failed',
            );
        }

        return array('ok' => true, 'calculated' => count($rows));
    }
}
