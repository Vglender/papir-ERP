<?php

class MoySkladPriceExport
{
    /** @var MoySkladApi */
    private $ms;

    // Currency href
    const CURRENCY_HREF = '41b7ab2b-d29a-11ea-0a80-0517000f0d2c';

    // Price type hrefs
    const PRICE_RETAIL    = '41b88405-d29a-11ea-0a80-0517000f0d2d';
    const PRICE_DEALER    = '7f25e9bf-74d7-11eb-0a80-00ab002702ef';
    const PRICE_WHOLESALE = '7f25e83a-74d7-11eb-0a80-00ab002702ee';
    const PRICE_COST      = 'cc096389-8a9b-11eb-0a80-076100219825';

    // Attribute hrefs
    const ATTR_LINKS_PROM = 'ea90c75e-c5cf-11ee-0a80-173a0031191b';
    const ATTR_LINK_OFF   = '04c14a89-7d03-11ee-0a80-0f6b001ba2ae';
    const ATTR_LINKS_MF   = '6df01ab4-254f-11f1-0a80-1452001ab090';

    public function __construct(MoySkladApi $ms)
    {
        $this->ms = $ms;
    }

    /**
     * Push prices for a batch of products to MoySklad via API.
     *
     * @param array $rows product rows with id_ms, prices, article, links
     * @return array array('ok'=>bool, 'pushed'=>int, 'skipped'=>int, 'errors'=>array)
     */
    public function pushBatch(array $rows)
    {
        $pushed  = 0;
        $skipped = 0;
        $errors  = array();

        // getEntityBaseUrl() returns "https://api.moysklad.ru/api/remap/1.2/entity/"
        // Root base (without "entity/") needed for context/* and currency paths
        $entityBase = $this->ms->getEntityBaseUrl(); // ends with "entity/"
        $rootBase   = substr($entityBase, 0, -strlen('entity/')); // ends with "1.2/"

        foreach ($rows as $row) {
            $idMs = isset($row['id_ms']) ? trim($row['id_ms']) : '';
            if (!$idMs) {
                $skipped++;
                continue;
            }

            $priceSale      = isset($row['price_sale'])      ? (float)$row['price_sale']      : 0;
            $priceDealer    = isset($row['price_dealer'])     ? (float)$row['price_dealer']    : 0;
            $priceWholesale = isset($row['price_wholesale'])  ? (float)$row['price_wholesale'] : 0;
            $pricePurchase  = isset($row['price_purchase'])   ? (float)$row['price_purchase']  : 0;
            $article        = isset($row['product_article'])  ? $row['product_article']        : '';
            $idOff          = isset($row['id_off'])           ? (string)$row['id_off']         : '';
            $linkOff        = isset($row['link_off'])         ? $row['link_off']               : '';
            $linksMf        = isset($row['links_mf'])         ? $row['links_mf']               : '';
            $linksProm      = isset($row['links_prom'])       ? $row['links_prom']             : '';

            $currencyMeta = array(
                'href'      => $entityBase . 'currency/' . self::CURRENCY_HREF,
                'type'      => 'currency',
                'mediaType' => 'application/json',
            );

            $salePrices = array(
                array(
                    'value'     => (int)round($priceSale * 100),
                    'currency'  => array('meta' => $currencyMeta),
                    'priceType' => array('meta' => array(
                        'href'      => $rootBase . 'context/companysettings/pricetype/' . self::PRICE_RETAIL,
                        'type'      => 'pricetype',
                        'mediaType' => 'application/json',
                    )),
                ),
                array(
                    'value'     => (int)round($priceDealer * 100),
                    'currency'  => array('meta' => $currencyMeta),
                    'priceType' => array('meta' => array(
                        'href'      => $rootBase . 'context/companysettings/pricetype/' . self::PRICE_DEALER,
                        'type'      => 'pricetype',
                        'mediaType' => 'application/json',
                    )),
                ),
                array(
                    'value'     => (int)round($priceWholesale * 100),
                    'currency'  => array('meta' => $currencyMeta),
                    'priceType' => array('meta' => array(
                        'href'      => $rootBase . 'context/companysettings/pricetype/' . self::PRICE_WHOLESALE,
                        'type'      => 'pricetype',
                        'mediaType' => 'application/json',
                    )),
                ),
                array(
                    'value'     => (int)round($pricePurchase * 100),
                    'currency'  => array('meta' => $currencyMeta),
                    'priceType' => array('meta' => array(
                        'href'      => $rootBase . 'context/companysettings/pricetype/' . self::PRICE_COST,
                        'type'      => 'pricetype',
                        'mediaType' => 'application/json',
                    )),
                ),
            );

            $attributes = array(
                array(
                    'meta'  => array(
                        'href'      => $entityBase . 'product/metadata/attributes/' . self::ATTR_LINK_OFF,
                        'type'      => 'attributemetadata',
                        'mediaType' => 'application/json',
                    ),
                    'value' => $linkOff,
                ),
                array(
                    'meta'  => array(
                        'href'      => $entityBase . 'product/metadata/attributes/' . self::ATTR_LINKS_MF,
                        'type'      => 'attributemetadata',
                        'mediaType' => 'application/json',
                    ),
                    'value' => $linksMf,
                ),
                array(
                    'meta'  => array(
                        'href'      => $entityBase . 'product/metadata/attributes/' . self::ATTR_LINKS_PROM,
                        'type'      => 'attributemetadata',
                        'mediaType' => 'application/json',
                    ),
                    'value' => $linksProm,
                ),
            );

            $payload = array(
                'salePrices' => $salePrices,
                'buyPrice'   => array(
                    'value'    => (int)round($pricePurchase * 100),
                    'currency' => array('meta' => $currencyMeta),
                ),
                'code'       => $idOff,
                'article'    => $article,
                'attributes' => $attributes,
            );

            $url = $entityBase . 'product/' . $idMs;

            try {
                $result = $this->ms->querySend($url, $payload, 'PUT');

                // querySend returns decoded JSON; check for errors object
                if (isset($result->errors)) {
                    $firstError = is_array($result->errors) ? $result->errors[0] : $result->errors;
                    $errMsg = isset($firstError->error) ? $firstError->error : 'MoySklad error';
                    $errors[] = 'id_ms=' . $idMs . ': ' . $errMsg;
                } else {
                    $pushed++;
                }
            } catch (Exception $e) {
                $errors[] = 'id_ms=' . $idMs . ': ' . $e->getMessage();
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
