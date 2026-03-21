<?php

/**
 * Синхронизация цен с внешними источниками.
 * Адаптеры подключаются по необходимости.
 *
 * Источники:
 *   - moysklad     — себестоимость и цена поставщика из МойСклад
 *   - google_sheets — цены из Google Sheets (RRP, оптовая, дилерская)
 */
class PriceSyncService
{
    private $productRepo;

    public function __construct(ProductPriceRepository $productRepo)
    {
        $this->productRepo = $productRepo;
    }

    /**
     * Записать закупочные цены из МойСклад.
     *
     * @param array $rows  [['product_id' => int, 'price_accounting_cost' => float, 'price_supplier' => float|null], ...]
     */
    public function syncFromMoySklad(array $rows)
    {
        $updated = 0;
        $errors  = [];

        foreach ($rows as $row) {
            $productId = (int)(isset($row['product_id']) ? $row['product_id'] : 0);
            if (!$productId) {
                continue;
            }

            $data = [];

            if (isset($row['price_accounting_cost'])) {
                $data['price_accounting_cost'] = (float)$row['price_accounting_cost'];
            }

            if (isset($row['price_supplier'])) {
                $data['price_supplier'] = (float)$row['price_supplier'];
            }

            if (empty($data)) {
                continue;
            }

            $result = $this->productRepo->savePrices($productId, $data);

            if ($result['ok']) {
                $updated++;
            } else {
                $errors[] = ['product_id' => $productId, 'error' => isset($result['error']) ? $result['error'] : 'unknown'];
            }
        }

        return ['ok' => empty($errors), 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Записать цены из Google Sheets.
     * Пишет в manual_* поля product_price_settings.
     *
     * @param array $rows  [['product_id', 'wholesale_price'?, 'dealer_price'?, 'rrp'?], ...]
     */
    public function syncFromGoogleSheets(array $rows)
    {
        $updated = 0;
        $errors  = [];

        foreach ($rows as $row) {
            $productId = (int)(isset($row['product_id']) ? $row['product_id'] : 0);
            if (!$productId) {
                continue;
            }

            $settings = [];

            if (isset($row['wholesale_price'])) {
                $settings['manual_wholesale_enabled'] = 1;
                $settings['manual_wholesale_price']   = (float)$row['wholesale_price'];
            }

            if (isset($row['dealer_price'])) {
                $settings['manual_dealer_enabled'] = 1;
                $settings['manual_dealer_price']   = (float)$row['dealer_price'];
            }

            if (isset($row['rrp'])) {
                $settings['manual_rrp_enabled'] = 1;
                $settings['manual_rrp']          = (float)$row['rrp'];
            }

            if (empty($settings)) {
                continue;
            }

            $result = $this->productRepo->saveSettings($productId, $settings);

            if ($result['ok']) {
                $updated++;
            } else {
                $errors[] = ['product_id' => $productId, 'error' => isset($result['error']) ? $result['error'] : 'unknown'];
            }
        }

        return ['ok' => empty($errors), 'updated' => $updated, 'errors' => $errors];
    }
}
