<?php

/**
 * Оркестрирует полный ценовой пайплайн.
 *
 * Пайплайн:
 *   1. PurchasePriceResolver    — определить закупочную
 *   2. BasePriceCalculator      — рассчитать sale / wholesale / dealer
 *   3. RrpPriceAdjuster         — применить RRP к розничной
 *   4. DiscountStrategyResolver — выбрать стратегию скидок
 *   5. QuantityThresholdResolver— определить пороги количеств
 *   6. DiscountPriceCalculator  — рассчитать 3 уровня скидок
 *   7. PriceConsistencyValidator— проверить согласованность
 */
class PriceEngine
{
    private $purchaseResolver;
    private $baseCalculator;
    private $rrpAdjuster;
    private $discountStrategyResolver;
    private $quantityResolver;
    private $discountCalculator;
    private $validator;

    public function __construct(
        PurchasePriceResolver     $purchaseResolver,
        BasePriceCalculator       $baseCalculator,
        RrpPriceAdjuster          $rrpAdjuster,
        DiscountStrategyResolver  $discountStrategyResolver,
        QuantityThresholdResolver $quantityResolver,
        DiscountPriceCalculator   $discountCalculator,
        PriceConsistencyValidator $validator
    ) {
        $this->purchaseResolver         = $purchaseResolver;
        $this->baseCalculator           = $baseCalculator;
        $this->rrpAdjuster              = $rrpAdjuster;
        $this->discountStrategyResolver = $discountStrategyResolver;
        $this->quantityResolver         = $quantityResolver;
        $this->discountCalculator       = $discountCalculator;
        $this->validator                = $validator;
    }

    /**
     * @param array $product           Строка из product_papir
     * @param array $settings          Строка из product_price_settings (может быть [])
     * @param array $packages          Строки из product_package (может быть [])
     * @param array $discountStrategies Все строки из price_discount_strategy
     * @param array $quantityStrategy  Строка из price_quantity_strategy
     * @return array{
     *   ok: bool,
     *   price_purchase: float,
     *   purchase_source: string,
     *   price_sale: float,
     *   price_wholesale: float,
     *   price_dealer: float,
     *   rrp_applied: bool,
     *   discount_strategy: array,
     *   discounts: array,
     *   qty_source: string,
     *   validation: array
     * }
     */
    public function calculate(
        array $product,
        array $settings,
        array $packages,
        array $discountStrategies,
        array $quantityStrategy,
        array $globalSettings
    ) {
        // 1. Закупочная цена
        $purchase = $this->purchaseResolver->resolve($product, $settings);

        // 2. Базовые цены — с учётом ручных переопределений
        $markups = $this->resolveMarkups($product, $settings, $globalSettings, $purchase['price']);
        $base    = $this->baseCalculator->calculate($purchase['price'], $markups);

        // Применяем ручные переопределения для конкретных цен
        $saleFinal      = $this->resolveManual($settings, 'manual_price',     $base['sale']);
        $wholesaleFinal = $this->resolveManualPrice($settings, 'manual_wholesale', $base['wholesale']);
        $dealerFinal    = $this->resolveManualPrice($settings, 'manual_dealer',    $base['dealer']);

        // 3. RRP — только для розничной цены (если manual уже задан, RRP не применяем)
        $rrpResult  = array('price' => $saleFinal, 'rrp_applied' => false);
        $rrpCleared = false;
        if (empty($settings['manual_price_enabled'])) {
            // Приоритет: ручное → поле товара → из реестра поставщиков (supplier_db)
            if (!empty($settings['manual_rrp_enabled']) && !empty($settings['manual_rrp'])) {
                $rrp = (float)$settings['manual_rrp'];
            } else {
                $productRrp   = (float)(isset($product['price_rrp']) ? $product['price_rrp'] : 0);
                $supplierRrp  = ($purchase['price_rrp'] !== null) ? (float)$purchase['price_rrp'] : 0;
                $rrp = $productRrp > 0 ? $productRrp : ($supplierRrp > 0 ? $supplierRrp : null);
            }

            // Контроль: RRP не может быть ниже себестоимости — удаляем
            if ($rrp !== null && $rrp < $purchase['price']) {
                $rrp        = null;
                $rrpCleared = true;
            }

            $useRrp    = (bool)(isset($product['use_rrp']) ? $product['use_rrp'] : true);
            $rrpResult = $this->rrpAdjuster->adjust($saleFinal, $rrp, $useRrp);
        }

        $saleFinal = $rrpResult['price'];

        // 3.5 Автокоррекция: оптовая/дилерская не могут быть выше розничной
        // (возникает когда RRP снижает розничную или маркапы настроены неверно)
        if ($wholesaleFinal > 0 && $saleFinal > 0 && $wholesaleFinal > $saleFinal) {
            $wholesaleFinal = $saleFinal;
            $dealerFinal    = $saleFinal;
        }
        if ($dealerFinal > 0 && $wholesaleFinal > 0 && $dealerFinal > $wholesaleFinal) {
            $dealerFinal = $wholesaleFinal;
        }

        // 4. Стратегия скидок
        $discountStrategy = $this->discountStrategyResolver->resolve(
            $product,
            $settings,
            $discountStrategies
        );

        // 5. Пороги количеств
        $thresholds = [];
        $qtySource  = 'strategy';
        if (empty($settings['disable_auto_quantity_discounts'])) {
            $resolved  = $this->quantityResolver->resolve($packages, $quantityStrategy, $saleFinal);
            $thresholds = $resolved;
            $qtySource  = $resolved['source'];
        }

        // 6. Скидочные уровни
        $discounts = [];
        if (!empty($thresholds) && !empty($discountStrategy)) {
            $discounts = $this->discountCalculator->calculate(
                $saleFinal,
                $wholesaleFinal,
                $discountStrategy,
                $thresholds
            );
        }

        // 7. Валидация
        $profile = [
            'price_purchase'  => $purchase['price'],
            'price_sale'      => $saleFinal,
            'price_wholesale' => $wholesaleFinal,
            'price_dealer'    => $dealerFinal,
            'discounts'       => $discounts,
        ];
        $validation = $this->validator->validate($profile);

        return array_merge($profile, [
            'ok'               => $validation['ok'],
            'purchase_source'  => $purchase['source'],
            'rrp_applied'      => $rrpResult['rrp_applied'],
            'rrp_cleared'      => $rrpCleared,
            'discount_strategy'=> $discountStrategy,
            'qty_source'       => $qtySource,
            'validation'       => $validation,
        ]);
    }

    /**
     * Собирает наценки: приоритет — поля товара, фоллбэк — глобальные настройки (+ ступенчатая).
     */
    private function resolveMarkups(array $product, array $settings, array $globalSettings, $purchasePrice = 0)
    {
        $productSale      = (float)(isset($product['sale_markup_percent'])      ? $product['sale_markup_percent']      : 0);
        $productWholesale = (float)(isset($product['wholesale_markup_percent']) ? $product['wholesale_markup_percent'] : 0);
        $productDealer    = (float)(isset($product['dealer_markup_percent'])    ? $product['dealer_markup_percent']    : 0);

        $globalWholesale = (float)(isset($globalSettings['wholesale_markup_percent']) ? $globalSettings['wholesale_markup_percent'] : 0);
        $globalDealer    = (float)(isset($globalSettings['dealer_markup_percent'])    ? $globalSettings['dealer_markup_percent']    : 0);

        // Ступенчатая наценка для продажной цены
        if ($productSale <= 0) {
            $productSale = $this->resolveTieredSaleMarkup($globalSettings, $purchasePrice);
        }

        return array(
            'sale'      => $productSale,
            'wholesale' => $productWholesale > 0 ? $productWholesale : $globalWholesale,
            'dealer'    => $productDealer    > 0 ? $productDealer    : $globalDealer,
        );
    }

    /**
     * Ищет подходящий тир по закупочной цене.
     * Если тиров нет или use_tiered_markup=0 — возвращает простую глобальную наценку.
     */
    private function resolveTieredSaleMarkup(array $globalSettings, $purchasePrice)
    {
        $useTiered = !empty($globalSettings['use_tiered_markup']);
        $tiers     = (isset($globalSettings['tiers']) && is_array($globalSettings['tiers'])) ? $globalSettings['tiers'] : array();

        if ($useTiered && !empty($tiers)) {
            $best = null;
            foreach ($tiers as $tier) {
                $from = (float)$tier['price_from'];
                if ($purchasePrice >= $from) {
                    if ($best === null || $from > (float)$best['price_from']) {
                        $best = $tier;
                    }
                }
            }
            if ($best !== null) {
                return (float)$best['markup_percent'];
            }
        }

        return (float)(isset($globalSettings['sale_markup_percent']) ? $globalSettings['sale_markup_percent'] : 0);
    }

    /**
     * Возвращает ручное значение если оно включено, иначе calculated.
     * Ключ значения = $key (напр. 'manual_price').
     */
    private function resolveManual(array $settings, $key, $calculated)
    {
        $enabledKey = $key . '_enabled';
        if (!empty($settings[$enabledKey]) && isset($settings[$key]) && (float)$settings[$key] > 0) {
            return (float)$settings[$key];
        }
        return $calculated;
    }

    /**
     * То же что resolveManual, но ключ значения = $key . '_price'
     * (для manual_wholesale_price, manual_dealer_price).
     */
    private function resolveManualPrice(array $settings, $key, $calculated)
    {
        $enabledKey = $key . '_enabled';
        $valueKey   = $key . '_price';
        if (!empty($settings[$enabledKey]) && isset($settings[$valueKey]) && (float)$settings[$valueKey] > 0) {
            return (float)$settings[$valueKey];
        }
        return $calculated;
    }

    /**
     * Фабричный метод — создаёт PriceEngine с зависимостями по умолчанию.
     */
    public static function create(PricelistItemRepository $itemRepo = null)
    {
        return new self(
            new PurchasePriceResolver($itemRepo),
            new BasePriceCalculator(),
            new RrpPriceAdjuster(),
            new DiscountStrategyResolver(),
            new QuantityThresholdResolver(),
            new DiscountPriceCalculator(),
            new PriceConsistencyValidator()
        );
    }
}
