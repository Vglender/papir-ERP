<?php

class ActionPublisher
{
    /** @var ActionPriceRepository */
    private $priceRepo;

    /**
     * @param ActionPriceRepository $priceRepo
     */
    public function __construct($priceRepo)
    {
        $this->priceRepo = $priceRepo;
    }

    /**
     * Publish calculated prices to off.oc_product_special and Google Merchant.
     *
     * @param callable|null $log  Callback: function($message, $type = 'info')
     * @return array
     */
    public function publish($log = null)
    {
        // 1. Get all calculated action_prices
        $prices = $this->priceRepo->getAll();

        if (empty($prices)) {
            $this->log($log, 'No action prices to publish.', 'info');
            return array('ok' => true, 'published' => 0);
        }

        $this->log($log, 'Action prices to publish: ' . count($prices), 'info');

        // 2. Get stock map from ms
        $stockSql = "SELECT
                         CAST(s.`model` AS UNSIGNED) AS product_id,
                         (s.`stock` + COALESCE(v.`stock`, 0)) AS stock
                     FROM `stock_` s
                     LEFT JOIN `virtual` v
                         ON v.`product_id` = CAST(s.`model` AS UNSIGNED)
                     WHERE CAST(s.`model` AS UNSIGNED) > 0";

        $stockResult = Database::fetchAll('ms', $stockSql);

        $stockMap = array();
        if ($stockResult['ok'] && !empty($stockResult['rows'])) {
            foreach ($stockResult['rows'] as $row) {
                $pid = (int)$row['product_id'];
                if ($pid > 0) {
                    $stockMap[$pid] = (int)$row['stock'];
                }
            }
        }

        $dateStart = date('Y-m-d H:i:s');
        $dateEnd   = date('Y-m-d', strtotime('+1 days')) . ' 00:00:00';

        $publishedIds = array();

        // 3. Build batch items for SiteSyncService
        require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
        $sync = new SiteSyncService();
        $siteId = 1; // off site

        $specialItems = array();
        foreach ($prices as $priceRow) {
            $pid      = (int)$priceRow['product_id'];
            $priceAct = (float)$priceRow['price_act'];

            if ($priceAct <= 0) {
                $this->log($log, 'Skipping product_id=' . $pid . ': price_act <= 0', 'progress');
                continue;
            }

            $specialItems[] = array(
                'product_id' => $pid,
                'price'      => $priceAct,
                'date_start' => $dateStart,
                'date_end'   => $dateEnd,
            );
            $publishedIds[] = $pid;
        }

        if (!empty($specialItems)) {
            $sync->batchSpecials($siteId, $specialItems, array(1, 4), array(1, 4));
        }

        $this->log($log, 'oc_product_special updated for ' . count($publishedIds) . ' products.', 'success');
        // Google Merchant оновлюється через фід (generate_merchant_feed.php),
        // який вже містить sale_price, sale_price_effective_date, availability.
        // Пряме batch-оновлення через API прибрано щоб уникнути помилок
        // "Product not found" для товарів відсутніх у фіді.

        // 4. Mark published
        if (!empty($publishedIds)) {
            $this->priceRepo->markPublished($publishedIds);
            $this->log($log, 'Marked published_at for ' . count($publishedIds) . ' products.', 'success');
        }

        return array('ok' => true, 'published' => count($publishedIds));
    }

    /**
     * Log a message using callback if provided.
     *
     * @param callable|null $log
     * @param string        $message
     * @param string        $type
     * @return void
     */
    private function log($log, $message, $type)
    {
        if ($log !== null && is_callable($log)) {
            call_user_func($log, $message, $type);
        }
    }

    /**
     * Log a summary of Merchant batch response.
     *
     * @param callable|null $log
     * @param mixed         $batchResponse
     * @return void
     */
    private function logMerchantSummary($log, $batchResponse)
    {
        if (empty($batchResponse)) {
            $this->log($log, 'Merchant response is empty.', 'error');
            return;
        }

        $entries = array();

        if (is_array($batchResponse)) {
            if (isset($batchResponse['entries']) && is_array($batchResponse['entries'])) {
                $entries = $batchResponse['entries'];
            } else {
                $entries = $batchResponse;
            }
        }

        $total        = isset($batchResponse['upd']) ? (int)$batchResponse['upd'] : count($entries);
        $errorEntries = isset($batchResponse['errors']) ? $batchResponse['errors'] : array();

        $this->log($log, 'Merchant updated: ' . $total, 'success');

        if (!empty($errorEntries)) {
            $this->log($log, 'Merchant errors: ' . count($errorEntries), 'error');

            $shown = 0;
            foreach ($errorEntries as $err) {
                if ($shown >= 5) {
                    break;
                }
                $entryId = isset($err['entry']) ? $err['entry'] : 'n/a';
                $msg     = isset($err['error']) ? $err['error'] : 'unknown';
                $this->log($log, 'Merchant error batchId=' . $entryId . ': ' . $msg, 'error');
                $shown++;
            }

            if (count($errorEntries) > 5) {
                $this->log($log, 'Additional merchant errors hidden: ' . (count($errorEntries) - 5), 'info');
            }
        } else {
            $this->log($log, 'Merchant update completed without errors.', 'success');
        }
    }
}
