<?php

require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';

class OpenCartPriceExport
{
    /** @var array db_alias => site_id cache */
    private static $aliasMap = array();

    /**
     * Push prices/discounts for a batch of products to one site via SiteSyncService.
     *
     * Supports two calling conventions:
     *   pushBatch($siteId, $rows)                      — new (site_product_id in rows)
     *   pushBatch($dbAlias, $rows, $idField)            — legacy (id_off/id_mf in rows)
     *
     * @return array array('ok'=>bool, 'pushed'=>int, 'skipped'=>int, 'errors'=>array)
     */
    public function pushBatch($siteIdOrAlias, array $rows, $idField = 'site_product_id')
    {
        $pushed  = 0;
        $skipped = 0;
        $errors  = array();

        $dateStart = date('Y-m-d');
        $dateEnd   = date('Y-m-d', strtotime('+365 days'));

        // Resolve site_id from db_alias if legacy call
        $siteId = $siteIdOrAlias;
        if (!is_numeric($siteIdOrAlias)) {
            $siteId = $this->resolveSiteId($siteIdOrAlias);
            if (!$siteId) {
                return array('ok' => false, 'pushed' => 0, 'skipped' => count($rows),
                    'errors' => array('Unknown db_alias: ' . $siteIdOrAlias));
            }
        }
        $siteId = (int)$siteId;

        $batchItems = array();

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

            // Build discount rows
            $discounts = array();
            $tiers = array(
                array('qty' => $qty1, 'price' => $price1),
                array('qty' => $qty2, 'price' => $price2),
                array('qty' => $qty3, 'price' => $price3),
            );
            $priority = 1;
            foreach ($tiers as $tier) {
                if ($tier['qty'] > 0 && $tier['price'] > 0) {
                    foreach (array(1, 4) as $cgId) {
                        $discounts[] = array(
                            'customer_group_id' => $cgId,
                            'quantity'          => $tier['qty'],
                            'priority'          => $priority,
                            'price'             => $tier['price'],
                            'date_start'        => $dateStart,
                            'date_end'          => $dateEnd,
                        );
                    }
                }
                $priority++;
            }

            if ($priceWholesale > 0) {
                $discounts[] = array(
                    'customer_group_id' => 2,
                    'quantity'          => 1,
                    'priority'          => 1,
                    'price'             => $priceWholesale,
                    'date_start'        => $dateStart,
                    'date_end'          => $dateEnd,
                );
            }

            if ($priceDealer > 0) {
                $discounts[] = array(
                    'customer_group_id' => 3,
                    'quantity'          => 1,
                    'priority'          => 1,
                    'price'             => $priceDealer,
                    'date_start'        => $dateStart,
                    'date_end'          => $dateEnd,
                );
            }

            $batchItems[] = array(
                'product_id' => $ocProductId,
                'price'      => $priceSale,
                'quantity'   => $quantity,
                'discounts'  => $discounts,
            );

            $pushed++;
        }

        if (!empty($batchItems)) {
            $sync = new SiteSyncService();
            $r = $sync->batchPrices($siteId, $batchItems);
            if (!$r['ok']) {
                $errors[] = isset($r['error']) ? $r['error'] : 'batchPrices failed';
            }
        }

        return array(
            'ok'      => true,
            'pushed'  => $pushed,
            'skipped' => $skipped,
            'errors'  => $errors,
        );
    }

    /**
     * Resolve db_alias to site_id for backward compatibility.
     */
    private function resolveSiteId($dbAlias)
    {
        if (isset(self::$aliasMap[$dbAlias])) {
            return self::$aliasMap[$dbAlias];
        }
        $r = Database::fetchRow('Papir',
            "SELECT site_id FROM sites WHERE db_alias = '" . Database::escape('Papir', $dbAlias) . "' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) {
            self::$aliasMap[$dbAlias] = (int)$r['row']['site_id'];
            return self::$aliasMap[$dbAlias];
        }
        return 0;
    }
}
