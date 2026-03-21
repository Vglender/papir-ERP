<?php

/**
 * Строит и сохраняет дисконтный профиль одного товара.
 */
class DiscountProfileBuilder
{
    private $engine;
    private $productRepo;
    private $discountStrategyRepo;
    private $quantityStrategyRepo;
    private $packageRepo;
    private $profileRepo;
    private $globalSettingsRepo;
    public function __construct(
        PriceEngine                      $engine,
        ProductPriceRepository           $productRepo,
        DiscountStrategyRepository       $discountStrategyRepo,
        QuantityStrategyRepository       $quantityStrategyRepo,
        ProductPackageRepository         $packageRepo,
        ProductDiscountProfileRepository $profileRepo,
        GlobalSettingsRepository         $globalSettingsRepo
    ) {
        $this->engine               = $engine;
        $this->productRepo          = $productRepo;
        $this->discountStrategyRepo = $discountStrategyRepo;
        $this->quantityStrategyRepo = $quantityStrategyRepo;
        $this->packageRepo          = $packageRepo;
        $this->profileRepo          = $profileRepo;
        $this->globalSettingsRepo   = $globalSettingsRepo;
    }

    public function build($productId)
    {
        $product  = $this->productRepo->getById($productId);
        $settings = $this->productRepo->getSettings($productId);

        if (empty($product)) {
            return ['ok' => false, 'error' => 'Product not found: ' . $productId];
        }

        // Sync product_papir.price_rrp from supplier items before calculation.
        // If no active supplier item has RRP — clear it so PriceEngine doesn't use a stale value.
        if (empty($settings['manual_rrp_enabled'])) {
            $rrpRow = Database::fetchRow('Papir',
                "SELECT MAX(psi.price_rrp) AS best_rrp
                 FROM price_supplier_items psi
                 JOIN price_supplier_pricelists ppl ON ppl.id = psi.pricelist_id
                 JOIN price_suppliers ps ON ps.id = ppl.supplier_id
                 WHERE psi.product_id = " . (int)$productId . "
                   AND psi.match_type != 'ignored'
                   AND ps.is_active = 1
                   AND ppl.is_active = 1
                   AND psi.price_rrp IS NOT NULL AND psi.price_rrp > 0"
            );
            $syncedRrp = ($rrpRow['ok'] && !empty($rrpRow['row']) && $rrpRow['row']['best_rrp'] !== null)
                ? (float)$rrpRow['row']['best_rrp']
                : null;
            // Only update if differs from current value to avoid unnecessary writes
            $currentRrp = isset($product['price_rrp']) && $product['price_rrp'] !== null
                ? (float)$product['price_rrp']
                : null;
            if ($syncedRrp !== $currentRrp) {
                Database::update('Papir', 'product_papir',
                    array('price_rrp' => $syncedRrp),
                    array('product_id' => (int)$productId)
                );
                $product['price_rrp'] = $syncedRrp;
            }
        }

        $discountStrategies = $this->discountStrategyRepo->getAll();
        $quantityStrategy   = $this->quantityStrategyRepo->getDefault();
        $packages           = $this->packageRepo->getByProductId($productId);
        $globalSettings     = $this->globalSettingsRepo->get();

        $result = $this->engine->calculate(
            $product,
            $settings,
            $packages,
            $discountStrategies,
            $quantityStrategy,
            $globalSettings
        );

        if (!$result['ok']) {
            return ['ok' => false, 'product_id' => $productId, 'errors' => $result['validation']['errors']];
        }

        // Контроль: оптовая не может быть ниже акционной цены
        // (чтобы оптовый покупатель не получал лучшую цену чем акционный)
        // Потолок оптовой = розничная (уже контролируется в PriceEngine шаг 3.5)
        $idOff    = isset($product['id_off']) ? (int)$product['id_off'] : 0;
        $priceAct = $this->getActionPrice($idOff);
        $priceSale = $result['price_sale'];

        // Нижняя граница оптовой = акционная цена (если есть и ниже розничной)
        // Без акции — нижняя граница не ограничена (оптовая может быть ниже розничной)
        $wholesaleFloor = 0;
        if ($priceAct !== null && $priceAct > 0 && $priceAct < $priceSale) {
            $wholesaleFloor = $priceAct;
        }

        $wholesaleFinal = $result['price_wholesale'];
        $dealerFinal    = $result['price_dealer'];

        if ($wholesaleFinal > 0 && $wholesaleFinal < $wholesaleFloor) {
            $wholesaleFinal = $wholesaleFloor;
            $dealerFinal    = $wholesaleFloor;
        }

        // Если RRP был аннулирован — очищаем поле в product_papir
        $pricesSave = array(
            'price_purchase'         => $result['price_purchase'],
            'purchase_price_source'  => $result['purchase_source'],
            'price_sale'             => $result['price_sale'],
            'price_wholesale'        => $wholesaleFinal,
            'price_dealer'           => $dealerFinal,
            'prices_updated_at'      => date('Y-m-d H:i:s'),
        );
        if (!empty($result['rrp_cleared'])) {
            $pricesSave['price_rrp'] = null;
            $pricesSave['use_rrp']   = 0;
        }

        // Сохраняем в product_papir
        $this->productRepo->savePrices($productId, $pricesSave);

        // Пересчёт action_prices если у товара есть активная акция
        $actionResult = null;
        if ($idOff > 0) {
            $actionRow = Database::fetchRow('Papir',
                "SELECT discount, super_discont FROM action_products WHERE product_id = " . $idOff . " LIMIT 1"
            );
            if ($actionRow['ok'] && !empty($actionRow['row'])) {
                $discount     = (int)$actionRow['row']['discount'];
                $superDiscont = (int)$actionRow['row']['super_discont'];
                $priceSaleAct = (float)$result['price_sale'];
                $priceCostAct = (float)$result['price_purchase'];

                if ($superDiscont > 0) {
                    $priceAct = $priceCostAct - $priceCostAct * $superDiscont / 100;
                } else {
                    $priceAct = $priceSaleAct - ($priceSaleAct - $priceCostAct) * $discount / 100;
                }
                $priceAct = round($priceAct, 4);
                if ($priceAct < $priceCostAct) {
                    $priceAct = $priceCostAct;
                }

                $exists = Database::exists('Papir', 'action_prices', array('product_id' => $idOff));
                if ($exists['ok'] && $exists['exists']) {
                    Database::update('Papir', 'action_prices', array(
                        'price_act'     => $priceAct,
                        'price_base'    => $priceSaleAct,
                        'price_cost'    => $priceCostAct,
                        'discount'      => $discount,
                        'super_discont' => $superDiscont,
                        'discount_type' => $superDiscont > 0 ? 'super' : 'regular',
                        'calculated_at' => date('Y-m-d H:i:s'),
                    ), array('product_id' => $idOff));
                } else {
                    Database::insert('Papir', 'action_prices', array(
                        'product_id'    => $idOff,
                        'price_act'     => $priceAct,
                        'price_base'    => $priceSaleAct,
                        'price_cost'    => $priceCostAct,
                        'discount'      => $discount,
                        'super_discont' => $superDiscont,
                        'discount_type' => $superDiscont > 0 ? 'super' : 'regular',
                        'calculated_at' => date('Y-m-d H:i:s'),
                    ));
                }

                $actionResult = array('action_recalc' => true, 'price_act' => $priceAct);
            }
        }

        // Сохраняем дисконтный профиль (с учётом скорректированных оптовой/дилерской)
        $result['price_wholesale'] = $wholesaleFinal;
        $result['price_dealer']    = $dealerFinal;
        $discounts = $result['discounts'];
        $this->profileRepo->save($productId, [
            'discount_strategy_id' => isset($result['discount_strategy']['id']) ? $result['discount_strategy']['id'] : null,
            'quantity_strategy_id' => isset($quantityStrategy['id']) ? $quantityStrategy['id'] : null,
            'qty_source'           => $result['qty_source'],
            'qty_1'                => isset($discounts[0]['qty'])               ? $discounts[0]['qty']               : null,
            'discount_percent_1'   => isset($discounts[0]['discount_percent'])  ? $discounts[0]['discount_percent']  : null,
            'price_1'              => isset($discounts[0]['price'])             ? $discounts[0]['price']             : null,
            'qty_2'                => isset($discounts[1]['qty'])               ? $discounts[1]['qty']               : null,
            'discount_percent_2'   => isset($discounts[1]['discount_percent'])  ? $discounts[1]['discount_percent']  : null,
            'price_2'              => isset($discounts[1]['price'])             ? $discounts[1]['price']             : null,
            'qty_3'                => isset($discounts[2]['qty'])               ? $discounts[2]['qty']               : null,
            'discount_percent_3'   => isset($discounts[2]['discount_percent'])  ? $discounts[2]['discount_percent']  : null,
            'price_3'              => isset($discounts[2]['price'])             ? $discounts[2]['price']             : null,
        ]);

        return ['ok' => true, 'product_id' => $productId, 'result' => $result, 'action' => $actionResult];
    }

    /**
     * Получить акционную цену товара из action_prices (по id_off).
     * Возвращает float или null если нет.
     */
    private function getActionPrice($idOff)
    {
        if ($idOff <= 0) {
            return null;
        }
        $r = Database::fetchRow('Papir',
            "SELECT price_act FROM `action_prices` WHERE `product_id` = " . (int)$idOff . " LIMIT 1"
        );
        if ($r['ok'] && !empty($r['row']) && $r['row']['price_act'] !== null) {
            return (float)$r['row']['price_act'];
        }
        return null;
    }
}
