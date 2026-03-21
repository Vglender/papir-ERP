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

        // Контроль: оптовая не может быть ниже розничной или акционной цены
        $idOff    = isset($product['id_off']) ? (int)$product['id_off'] : 0;
        $priceAct = $this->getActionPrice($idOff);
        $priceSale = $result['price_sale'];

        // Нижняя граница оптовой = розничная цена
        // Если есть акционная и она ниже розничной — оптовая не может быть ниже акционной
        $wholesaleFloor = $priceSale;
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

        return ['ok' => true, 'product_id' => $productId, 'result' => $result];
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
