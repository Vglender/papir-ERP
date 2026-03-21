<?php

require_once '/var/sqript/vendor/autoload.php';

use Google\Client;
use Google\Service\ShoppingContent;
use Google\Service\ShoppingContent\Product;
use Google\Service\ShoppingContent\Price;
use Google\Service\ShoppingContent\ProductsCustomBatchRequest;
use Google\Service\ShoppingContent\ProductsCustomBatchRequestEntry;

if (!defined('MERCHANT_ID')) {
    define('MERCHANT_ID', '121039527');
}
if (!defined('BASE_MERCHANT_ID')) {
    define('BASE_MERCHANT_ID', 'online:uk:UA:');
}
if (!defined('MERCHANT_CREDENTIALS_PATH')) {
    define('MERCHANT_CREDENTIALS_PATH', '/var/sqript/Merchant/credentials.json');
}
if (!defined('MERCHANT_TOKEN_PATH')) {
    define('MERCHANT_TOKEN_PATH', '/var/sqript/Merchant/token.json');
}

class MerchantService
{
    /**
     * Get authenticated Google API client.
     *
     * @return Google\Client
     * @throws Exception
     */
    public static function getClient()
    {
        $client = new Google\Client();
        $client->setAuthConfig(MERCHANT_CREDENTIALS_PATH);
        $client->addScope(Google\Service\ShoppingContent::CONTENT);
        $client->setAccessType('offline');
        $client->setRedirectUri('https://officetorg.com.ua/webhooks/oauth2callback.php');
        $client->setPrompt('select_account consent');

        if (file_exists(MERCHANT_TOKEN_PATH)) {
            $accessToken = json_decode(file_get_contents(MERCHANT_TOKEN_PATH), true);

            if (!is_array($accessToken)) {
                throw new InvalidArgumentException('Invalid token format in ' . MERCHANT_TOKEN_PATH);
            }

            $client->setAccessToken($accessToken);
        }

        if (!$client->getAccessToken()) {
            $authUrl = $client->createAuthUrl();
            throw new Exception('merchant_auth_required::' . $authUrl);
        }

        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $newAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                if (isset($newAccessToken['error'])) {
                    throw new Exception('Error refreshing token: ' . $newAccessToken['error']);
                }

                $client->setAccessToken($newAccessToken);

                if (!file_exists(dirname(MERCHANT_TOKEN_PATH))) {
                    mkdir(dirname(MERCHANT_TOKEN_PATH), 0700, true);
                }

                file_put_contents(MERCHANT_TOKEN_PATH, json_encode($client->getAccessToken()));
            } else {
                $authUrl = $client->createAuthUrl();
                throw new Exception('merchant_auth_required::' . $authUrl);
            }
        }

        return $client;
    }

    /**
     * Build batch request entries for custombatch API call.
     *
     * @param array  $products    Array of product data arrays
     * @param string $merchantId
     * @param string $method      'update' or 'insert'
     * @return array
     */
    public static function createBatchEntries($products, $merchantId, $method)
    {
        $entries      = array();
        $minOrderValue = 50;

        foreach ($products as $index => $productData) {
            $productDataArray = array();

            if (isset($productData['offerId'])) {
                $productDataArray['offerId'] = $productData['offerId'];
            }
            if (isset($productData['title'])) {
                $productDataArray['title'] = $productData['title'];
            }
            if (isset($productData['availability'])) {
                $productDataArray['availability'] = $productData['availability'];
            }
            if (isset($productData['sale_price'])) {
                $productDataArray['salePrice'] = new Price(array(
                    'value'    => $productData['sale_price'],
                    'currency' => 'UAH',
                ));
                $productDataArray['salePriceEffectiveDate'] = $productData['sale_price_effective_date'];
            }
            if (isset($productData['price'])) {
                $productDataArray['price'] = new Price(array(
                    'value'    => $productData['price'],
                    'currency' => 'UAH',
                ));
            }
            if (isset($productData['costOfGoodsSold'])) {
                $productDataArray['costOfGoodsSold'] = new Price(array(
                    'value'    => $productData['costOfGoodsSold'],
                    'currency' => 'UAH',
                ));
            }
            if (isset($productData['auto_pricing_min_price'])) {
                $productDataArray['autoPricingMinPrice'] = new Price(array(
                    'value'    => $productData['auto_pricing_min_price'],
                    'currency' => 'UAH',
                ));
            }

            $productDataArray['shippingLabel'] = $minOrderValue;

            if ($method === 'insert') {
                $productDataArray['contentLanguage'] = 'uk';
                $productDataArray['condition']       = 'new';
                $productDataArray['targetCountry']   = 'UA';
                $productDataArray['channel']         = 'online';

                if (!empty($productData['manufacturer_name'])) {
                    $productDataArray['brand'] = $productData['manufacturer_name'];
                }
            }

            $product = new Product($productDataArray);

            $entry = new ProductsCustomBatchRequestEntry();
            $entry->setBatchId($index);
            $entry->setMethod($method);
            $entry->setProduct($product);
            $entry->setMerchantId($merchantId);
            $entry->setProductId($productData['productId']);

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Send batch requests to Google Merchant in chunks of 1000.
     *
     * @param Google\Service\ShoppingContent $service
     * @param array                          $entries
     * @return array  ['upd' => int, 'errors' => array]
     */
    public static function sendBatch($service, $entries)
    {
        $batchSize   = 1000;
        $upd         = 0;
        $result      = array('upd' => 0, 'errors' => array());
        $total       = count($entries);

        for ($i = 0; $i < $total; $i += $batchSize) {
            $batchRequest = new ProductsCustomBatchRequest();
            $batchRequest->setEntries(array_slice($entries, $i, $batchSize));

            try {
                $response = $service->products->custombatch($batchRequest);

                foreach ($response->getEntries() as $entry) {
                    if ($entry->getErrors()) {
                        foreach ($entry->getErrors()->getErrors() as $error) {
                            $result['errors'][] = array(
                                'entry' => $entry->getBatchId(),
                                'error' => $error->getMessage(),
                            );
                        }
                    } else {
                        $upd++;
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = array('entry' => 'batch', 'error' => $e->getMessage());
            }
        }

        $result['upd'] = $upd;

        return $result;
    }
}
