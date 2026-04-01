<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../MerchantService.php';

/**
 * Перевірка стану Google Automated Discounts (GAD) через Merchant API v1.
 *
 * Структура відповіді Merchant API v1 (відрізняється від Content API v2.1):
 *   product.productAttributes.autoPricingMinPrice  — мін. ціна з нашого фіду
 *   product.automatedDiscounts.gadPrice            — ціна що Google зараз застосовує
 *   product.automatedDiscounts.priorPrice          — попередня ціна (Google зафіксував)
 *   product.automatedDiscounts.priorPriceProgressive
 */

define('GAD_MERCHANT_ID', '121039527');
define('GAD_API_BASE', 'https://merchantapi.googleapis.com/products/v1/accounts/' . GAD_MERCHANT_ID);
define('GAD_PAGE_SIZE', 250);
define('GAD_MAX_PAGES', 20); // макс 5000 товарів

$result = array(
    'ok'             => false,
    'error'          => null,
    'status'         => 'unknown',  // active | pending | not_configured | has_issues | api_error
    'total'          => 0,
    'opted_in'       => 0,   // є autoPricingMinPrice у фіді
    'has_gad_price'  => 0,   // Google зараз активно знижує
    'has_prior_price'=> 0,   // Google зафіксував прайор-прайс (моніторить)
    'pages_fetched'  => 0,
    'sample_active'  => array(),  // до 5 товарів з активним gadPrice
    'sample_pending' => array(),  // до 5 товарів з priorPrice але без gadPrice
    'issues'         => array(),  // disapproved у Shopping/Free (uniq по товару)
    'issues_count'   => 0,
);
$issueMap = array();

function gadMicrosToPrice($amountMicros)
{
    return round((float)$amountMicros / 1000000, 2);
}

function gadApiGet($url, $accessToken)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ),
    ));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($body, true);
    if ($code !== 200) {
        $msg = isset($data['error']['message']) ? $data['error']['message'] : 'HTTP ' . $code;
        return array('_error' => $msg, '_code' => $code);
    }
    return $data;
}

try {
    $client      = MerchantService::getClient();
    $token       = $client->getAccessToken();
    $accessToken = $token['access_token'];

    $pageToken = null;
    $page      = 0;

    do {
        $url  = GAD_API_BASE . '/products?pageSize=' . GAD_PAGE_SIZE;
        if ($pageToken) {
            $url .= '&pageToken=' . urlencode($pageToken);
        }

        $data = gadApiGet($url, $accessToken);

        if (isset($data['_error'])) {
            $result['error']  = $data['_error'];
            $result['status'] = 'api_error';
            echo json_encode($result);
            exit;
        }

        $products = isset($data['products']) ? $data['products'] : array();
        $page++;
        $result['pages_fetched'] = $page;

        foreach ($products as $p) {
            $result['total']++;

            $attrs     = isset($p['productAttributes']) ? $p['productAttributes'] : array();
            $gadInfo   = isset($p['automatedDiscounts']) ? $p['automatedDiscounts'] : null;
            $offerId   = isset($p['offerId']) ? $p['offerId'] : '';
            $title     = isset($attrs['title'])  ? $attrs['title']  : '';

            $isOptedIn  = isset($attrs['autoPricingMinPrice']);
            $hasGadPrice  = isset($gadInfo['gadPrice']);
            $hasPriorPrice = isset($gadInfo['priorPrice']);

            if ($isOptedIn)    { $result['opted_in']++; }
            if ($hasGadPrice)  { $result['has_gad_price']++; }
            if ($hasPriorPrice){ $result['has_prior_price']++; }

            // Статуси товарів — збираємо uniq по offerId
            $destStatuses = isset($p['productStatus']['destinationStatuses'])
                ? $p['productStatus']['destinationStatuses'] : array();
            foreach ($destStatuses as $ds) {
                $disapp = isset($ds['disapprovedCountries']) ? $ds['disapprovedCountries'] : array();
                $ctx    = isset($ds['reportingContext']) ? $ds['reportingContext'] : '';
                // Рахуємо тільки Shopping та FreeListing — головні
                if (!empty($disapp) && in_array($ctx, array('SHOPPING_ADS', 'FREE_PRODUCT_LISTING', 'FREE_LOCAL_PRODUCT_LISTING'))) {
                    if (!isset($issueMap[$offerId])) {
                        $issueMap[$offerId] = array('offer_id' => $offerId, 'title' => mb_substr($title, 0, 60, 'UTF-8'), 'contexts' => array());
                    }
                    $ctxLabels = array(
                        'SHOPPING_ADS'              => 'Платна реклама',
                        'FREE_PRODUCT_LISTING'      => 'Безкоштовні оголошення',
                        'FREE_LOCAL_PRODUCT_LISTING'=> 'Локальні безкоштовні',
                    );
                    $issueMap[$offerId]['contexts'][] = isset($ctxLabels[$ctx]) ? $ctxLabels[$ctx] : $ctx;
                }
            }

            // Зразки активних (з gadPrice)
            if ($hasGadPrice && count($result['sample_active']) < 5) {
                $price    = isset($attrs['price']['amountMicros'])
                    ? gadMicrosToPrice($attrs['price']['amountMicros']) : null;
                $salePrice = isset($attrs['salePrice']['amountMicros'])
                    ? gadMicrosToPrice($attrs['salePrice']['amountMicros']) : null;
                $gadPrice = gadMicrosToPrice($gadInfo['gadPrice']['amountMicros']);
                $minPrice = $isOptedIn
                    ? gadMicrosToPrice($attrs['autoPricingMinPrice']['amountMicros']) : null;
                $priorPrice = $hasPriorPrice
                    ? gadMicrosToPrice($gadInfo['priorPrice']['amountMicros']) : null;

                $result['sample_active'][] = array(
                    'offer_id'   => $offerId,
                    'title'      => mb_substr($title, 0, 80, 'UTF-8'),
                    'price'      => $price,
                    'sale_price' => $salePrice,
                    'gad_price'  => $gadPrice,
                    'min_price'  => $minPrice,
                    'prior_price'=> $priorPrice,
                    'link'       => isset($attrs['link']) ? $attrs['link'] : null,
                );
            }

            // Зразки pending (priorPrice, але без gadPrice)
            if ($hasPriorPrice && !$hasGadPrice && count($result['sample_pending']) < 5) {
                $price    = isset($attrs['price']['amountMicros'])
                    ? gadMicrosToPrice($attrs['price']['amountMicros']) : null;
                $salePrice = isset($attrs['salePrice']['amountMicros'])
                    ? gadMicrosToPrice($attrs['salePrice']['amountMicros']) : null;
                $minPrice = $isOptedIn
                    ? gadMicrosToPrice($attrs['autoPricingMinPrice']['amountMicros']) : null;
                $priorPrice = gadMicrosToPrice($gadInfo['priorPrice']['amountMicros']);

                $result['sample_pending'][] = array(
                    'offer_id'   => $offerId,
                    'title'      => mb_substr($title, 0, 80, 'UTF-8'),
                    'price'      => $price,
                    'sale_price' => $salePrice,
                    'min_price'  => $minPrice,
                    'prior_price'=> $priorPrice,
                    'link'       => isset($attrs['link']) ? $attrs['link'] : null,
                );
            }
        }

        $pageToken = isset($data['nextPageToken']) ? $data['nextPageToken'] : null;

    } while ($pageToken && $page < GAD_MAX_PAGES);

    // Збираємо uniq issues
    foreach ($issueMap as $issue) {
        $issue['contexts'] = array_values(array_unique($issue['contexts']));
        $result['issues'][] = $issue;
    }
    $result['issues_count'] = count($result['issues']);

    // Визначаємо статус
    if ($result['opted_in'] === 0) {
        $result['status'] = 'not_configured';
    } elseif ($result['has_gad_price'] > 0) {
        $result['status'] = 'active';
    } elseif (!empty($result['issues'])) {
        $result['status'] = 'has_issues';
    } else {
        $result['status'] = 'pending';
    }

    $result['ok'] = true;

} catch (Exception $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'merchant_auth_required::') === 0) {
        $result['error']  = 'Потрібна авторизація Google';
        $result['status'] = 'api_error';
    } else {
        $result['error']  = $msg;
        $result['status'] = 'api_error';
    }
}

echo json_encode($result);
