<?php
/**
 * SiteSyncTransportHttpAgent — HTTP transport for site sync via papir_agent.php
 *
 * Calls the REST API agent running inside the remote OpenCart installation.
 * Each method maps 1:1 to an agent action.
 */
class SiteSyncTransportHttpAgent
{
    private $agentUrl;
    private $token;
    private $timeout;

    /**
     * @param string $agentUrl  Full URL to the agent endpoint (e.g. https://site.com/index.php?route=module/papir_agent)
     * @param string $token     Bearer token for authentication
     * @param int    $timeout   Request timeout in seconds
     */
    public function __construct($agentUrl, $token, $timeout = 30)
    {
        $this->agentUrl = $agentUrl;
        $this->token    = $token;
        $this->timeout  = $timeout;
    }

    /**
     * Call the agent with an action and optional JSON body.
     *
     * @param string $action  Agent action (e.g. 'product.create', 'batch.prices')
     * @param array  $body    Request body (sent as JSON POST)
     * @return array          Decoded JSON response, always has 'ok' key
     */
    public function call($action, array $body = array())
    {
        $sep = strpos($this->agentUrl, '?') !== false ? '&' : '?';
        $url = $this->agentUrl . $sep . 'action=' . urlencode($action);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
        ));

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('ok' => false, 'error' => 'curl: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return array('ok' => false, 'error' => 'Invalid JSON response, HTTP ' . $httpCode);
        }

        return $decoded;
    }

    // ── Product ───────────────────────────────────────────────────────────

    public function productCreate(array $product, array $descriptions, array $categories,
                                  array $images, array $seoUrls)
    {
        return $this->call('product.create', array(
            'product'      => $product,
            'descriptions' => $descriptions,
            'categories'   => $categories,
            'images'       => $images,
            'seo_urls'     => $seoUrls,
        ));
    }

    public function productUpdate($productId, array $fields, array $descriptions = array(),
                                  array $categories = array(), array $seoUrls = array())
    {
        $body = array('product_id' => (int)$productId, 'fields' => $fields);
        if (!empty($descriptions)) $body['descriptions'] = $descriptions;
        if (!empty($categories))   $body['categories']   = $categories;
        if (!empty($seoUrls))      $body['seo_urls']     = $seoUrls;
        return $this->call('product.update', $body);
    }

    public function productDelete($productId)
    {
        return $this->call('product.delete', array('product_id' => (int)$productId));
    }

    public function productSeo($productId, array $descriptions, array $seoUrls)
    {
        return $this->call('product.seo', array(
            'product_id'   => (int)$productId,
            'descriptions' => $descriptions,
            'seo_urls'     => $seoUrls,
        ));
    }

    public function productImages($productId, $mainImage, array $extraImages)
    {
        return $this->call('product.images', array(
            'product_id' => (int)$productId,
            'main_image' => $mainImage,
            'images'     => $extraImages,
        ));
    }

    public function productAttributes($productId, array $attributes, $replaceAll = false)
    {
        return $this->call('product.attributes', array(
            'product_id'  => (int)$productId,
            'attributes'  => $attributes,
            'replace_all' => $replaceAll,
        ));
    }

    // ── Batch ─────────────────────────────────────────────────────────────

    public function batchPrices(array $items)
    {
        return $this->call('batch.prices', array('items' => $items));
    }

    public function batchQuantity(array $items)
    {
        return $this->call('batch.quantity', array('items' => $items));
    }

    public function batchSpecials(array $items, array $clearGroups = array(),
                                  array $customerGroupIds = array())
    {
        $body = array('items' => $items);
        if (!empty($clearGroups))      $body['clear_groups']       = $clearGroups;
        if (!empty($customerGroupIds)) $body['customer_group_ids'] = $customerGroupIds;
        return $this->call('batch.specials', $body);
    }

    // ── Category ──────────────────────────────────────────────────────────

    public function categoryCreate(array $category, array $descriptions, array $seoUrls = array())
    {
        return $this->call('category.create', array(
            'category'     => $category,
            'descriptions' => $descriptions,
            'seo_urls'     => $seoUrls,
        ));
    }

    public function categoryUpdate($categoryId, array $fields, array $descriptions = array(),
                                   array $seoUrls = array())
    {
        $body = array('category_id' => (int)$categoryId, 'fields' => $fields);
        if (!empty($descriptions)) $body['descriptions'] = $descriptions;
        if (!empty($seoUrls))      $body['seo_urls']     = $seoUrls;
        return $this->call('category.update', $body);
    }

    // ── Manufacturer ──────────────────────────────────────────────────────

    public function manufacturerSave(array $data)
    {
        return $this->call('manufacturer.save', $data);
    }

    // ── Orders ────────────────────────────────────────────────────────────

    public function ordersList(array $params = array())
    {
        return $this->call('orders.list', $params);
    }

    public function ordersGet($orderId)
    {
        return $this->call('orders.get', array('order_id' => (int)$orderId));
    }

    // ── System ────────────────────────────────────────────────────────────

    public function info()
    {
        return $this->call('info');
    }

    public function stats()
    {
        return $this->call('stats');
    }

    public function cacheClear(array $types = array('all'))
    {
        return $this->call('cache.clear', array('types' => $types));
    }
}
