<?php

class OpenCartPriceExport
{
    /**
     * Push prices/discounts for a batch of products to one OpenCart DB.
     *
     * @param string $dbAlias 'off' or 'mff'
     * @param array  $rows    product rows with id_off/id_mf, prices, discount profile
     * @param string $idField 'id_off' for offtorg, 'id_mf' for mff
     * @return array array('ok'=>bool, 'pushed'=>int, 'skipped'=>int, 'errors'=>array)
     */
    public function pushBatch($dbAlias, array $rows, $idField)
    {
        $pushed  = 0;
        $skipped = 0;
        $errors  = array();

        $dateStart = date('Y-m-d');
        $dateEnd   = date('Y-m-d', strtotime('+365 days'));

        foreach ($rows as $row) {
            $ocProductId = isset($row[$idField]) ? (int)$row[$idField] : 0;
            if (!$ocProductId) {
                $skipped++;
                continue;
            }

            $priceSale      = isset($row['price_sale'])      ? (float)$row['price_sale']      : 0;
            $priceWholesale = isset($row['price_wholesale'])  ? (float)$row['price_wholesale'] : 0;
            $priceDealer    = isset($row['price_dealer'])     ? (float)$row['price_dealer']    : 0;
            $quantity       = isset($row['quantity'])         ? (int)$row['quantity']          : 0;

            $qty1   = isset($row['qty_1'])   ? (int)$row['qty_1']     : 0;
            $price1 = isset($row['price_1']) ? (float)$row['price_1'] : 0;
            $qty2   = isset($row['qty_2'])   ? (int)$row['qty_2']     : 0;
            $price2 = isset($row['price_2']) ? (float)$row['price_2'] : 0;
            $qty3   = isset($row['qty_3'])   ? (int)$row['qty_3']     : 0;
            $price3 = isset($row['price_3']) ? (float)$row['price_3'] : 0;

            try {
                // Update oc_product: price and quantity
                Database::update($dbAlias, 'oc_product', array(
                    'price'    => $priceSale,
                    'quantity' => $quantity,
                ), array('product_id' => $ocProductId));

                // Delete all existing discount rows for this product
                Database::query(
                    $dbAlias,
                    'DELETE FROM oc_product_discount WHERE product_id = ' . $ocProductId
                );

                // Build discount rows to insert
                $discountRows = array();

                // Groups 1 and 4: volume tiers (3 tiers)
                $tiers = array(
                    array('qty' => $qty1, 'price' => $price1),
                    array('qty' => $qty2, 'price' => $price2),
                    array('qty' => $qty3, 'price' => $price3),
                );
                $priority = 1;
                foreach ($tiers as $tier) {
                    if ($tier['qty'] > 0 && $tier['price'] > 0) {
                        // Group 1
                        $discountRows[] = array(
                            'product_id'        => $ocProductId,
                            'customer_group_id' => 1,
                            'quantity'          => $tier['qty'],
                            'priority'          => $priority,
                            'price'             => $tier['price'],
                            'date_start'        => $dateStart,
                            'date_end'          => $dateEnd,
                        );
                        // Group 4
                        $discountRows[] = array(
                            'product_id'        => $ocProductId,
                            'customer_group_id' => 4,
                            'quantity'          => $tier['qty'],
                            'priority'          => $priority,
                            'price'             => $tier['price'],
                            'date_start'        => $dateStart,
                            'date_end'          => $dateEnd,
                        );
                    }
                    $priority++;
                }

                // Group 2: wholesale, qty=1
                if ($priceWholesale > 0) {
                    $discountRows[] = array(
                        'product_id'        => $ocProductId,
                        'customer_group_id' => 2,
                        'quantity'          => 1,
                        'priority'          => 1,
                        'price'             => $priceWholesale,
                        'date_start'        => $dateStart,
                        'date_end'          => $dateEnd,
                    );
                }

                // Group 3: dealer, qty=1
                if ($priceDealer > 0) {
                    $discountRows[] = array(
                        'product_id'        => $ocProductId,
                        'customer_group_id' => 3,
                        'quantity'          => 1,
                        'priority'          => 1,
                        'price'             => $priceDealer,
                        'date_start'        => $dateStart,
                        'date_end'          => $dateEnd,
                    );
                }

                foreach ($discountRows as $dr) {
                    Database::insert($dbAlias, 'oc_product_discount', $dr);
                }

                $pushed++;
            } catch (Exception $e) {
                $errors[] = 'product_id=' . (isset($row['product_id']) ? $row['product_id'] : '?')
                    . ' oc_id=' . $ocProductId . ': ' . $e->getMessage();
            }
        }

        return array(
            'ok'      => true,
            'pushed'  => $pushed,
            'skipped' => $skipped,
            'errors'  => $errors,
        );
    }
}
