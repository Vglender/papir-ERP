<?php
/**
 * SiteSyncService — unified facade for OpenCart site synchronization.
 *
 * Abstracts over two transport modes:
 *   - DirectDB:  Database::connection($dbAlias) for same-server or remote MySQL sites
 *   - HttpAgent: REST API calls to papir_agent.php running on the OC site
 *
 * Usage:
 *   $sync = new SiteSyncService();
 *   $sync->batchQuantity($siteId, $items);
 *   $sync->productCreate($siteId, $product, $descriptions, $categories, $images, $seoUrls);
 */

require_once __DIR__ . '/SiteSyncTransportDirectDb.php';
require_once __DIR__ . '/SiteSyncTransportHttpAgent.php';

class SiteSyncService
{
    /** @var array site_id => transport instance */
    private $transports = array();

    /** @var array site_id => site row */
    private $siteCache = array();

    // ── Transport resolution ──────────────────────────────────────────────

    /**
     * Get transport for a site (cached per instance).
     *
     * @param int $siteId
     * @return SiteSyncTransportDirectDb|SiteSyncTransportHttpAgent
     * @throws Exception if site not found or misconfigured
     */
    public function getTransport($siteId)
    {
        $siteId = (int)$siteId;
        if (isset($this->transports[$siteId])) {
            return $this->transports[$siteId];
        }

        $site = $this->loadSite($siteId);
        if (!$site) {
            throw new Exception("Site not found: {$siteId}");
        }

        $transport = isset($site['transport']) ? $site['transport'] : 'direct_db';

        if ($transport === 'http_agent') {
            $conn = $this->loadAgentConnection($siteId, $site);
            $this->transports[$siteId] = new SiteSyncTransportHttpAgent(
                $conn['agent_url'],
                $conn['agent_token']
            );
        } else {
            if (empty($site['db_alias'])) {
                throw new Exception("Site {$siteId} has no db_alias for direct_db transport");
            }
            $this->transports[$siteId] = new SiteSyncTransportDirectDb($site['db_alias']);
        }

        return $this->transports[$siteId];
    }

    /**
     * Get site info from cache or DB.
     *
     * @param int $siteId
     * @return array|null
     */
    public function getSite($siteId)
    {
        return $this->loadSite((int)$siteId);
    }

    /**
     * Get all active sites.
     *
     * @return array
     */
    public function getActiveSites()
    {
        $r = Database::fetchAll('Papir', "SELECT * FROM sites WHERE status = 1 ORDER BY sort_order");
        return ($r['ok']) ? $r['rows'] : array();
    }

    // ── Product operations ────────────────────────────────────────────────

    public function productCreate($siteId, array $product, array $descriptions = array(),
                                  array $categories = array(), array $images = array(),
                                  array $seoUrls = array())
    {
        return $this->getTransport($siteId)->productCreate(
            $product, $descriptions, $categories, $images, $seoUrls
        );
    }

    public function productUpdate($siteId, $siteProductId, array $fields,
                                  array $descriptions = array(), array $categories = array(),
                                  array $seoUrls = array())
    {
        return $this->getTransport($siteId)->productUpdate(
            $siteProductId, $fields, $descriptions, $categories, $seoUrls
        );
    }

    public function productDelete($siteId, $siteProductId)
    {
        return $this->getTransport($siteId)->productDelete($siteProductId);
    }

    public function productSeo($siteId, $siteProductId, array $descriptions, array $seoUrls)
    {
        return $this->getTransport($siteId)->productSeo($siteProductId, $descriptions, $seoUrls);
    }

    public function productImages($siteId, $siteProductId, $mainImage, array $extraImages = array())
    {
        return $this->getTransport($siteId)->productImages($siteProductId, $mainImage, $extraImages);
    }

    public function productAttributes($siteId, $siteProductId, array $attributes, $replaceAll = false)
    {
        return $this->getTransport($siteId)->productAttributes($siteProductId, $attributes, $replaceAll);
    }

    // ── Batch operations ──────────────────────────────────────────────────

    public function batchPrices($siteId, array $items)
    {
        return $this->getTransport($siteId)->batchPrices($items);
    }

    public function batchQuantity($siteId, array $items)
    {
        return $this->getTransport($siteId)->batchQuantity($items);
    }

    public function batchSpecials($siteId, array $items, array $clearGroups = array(),
                                  array $customerGroupIds = array())
    {
        return $this->getTransport($siteId)->batchSpecials($items, $clearGroups, $customerGroupIds);
    }

    // ── Category operations ───────────────────────────────────────────────

    public function categoryCreate($siteId, array $category, array $descriptions = array(),
                                   array $seoUrls = array())
    {
        return $this->getTransport($siteId)->categoryCreate($category, $descriptions, $seoUrls);
    }

    public function categoryUpdate($siteId, $categoryId, array $fields,
                                   array $descriptions = array(), array $seoUrls = array())
    {
        return $this->getTransport($siteId)->categoryUpdate($categoryId, $fields, $descriptions, $seoUrls);
    }

    // ── Other operations ──────────────────────────────────────────────────

    public function manufacturerSave($siteId, array $data)
    {
        return $this->getTransport($siteId)->manufacturerSave($data);
    }

    public function ordersList($siteId, array $params = array())
    {
        return $this->getTransport($siteId)->ordersList($params);
    }

    public function ordersGet($siteId, $orderId)
    {
        return $this->getTransport($siteId)->ordersGet($orderId);
    }

    public function info($siteId)
    {
        return $this->getTransport($siteId)->info();
    }

    public function stats($siteId)
    {
        return $this->getTransport($siteId)->stats();
    }

    public function cacheClear($siteId, array $types = array('all'))
    {
        return $this->getTransport($siteId)->cacheClear($types);
    }

    // ── Lookup helpers (for gradual migration from id_off/id_mf pattern) ─

    /**
     * Get product's site_product_id for a given site.
     * Replaces direct use of product_papir.id_off / id_mf.
     *
     * @param int $productId  Papir product_id
     * @param int $siteId
     * @return int  site_product_id or 0
     */
    public function getSiteProductId($productId, $siteId)
    {
        $r = Database::fetchRow('Papir',
            "SELECT site_product_id FROM product_site
             WHERE product_id = " . (int)$productId . " AND site_id = " . (int)$siteId . " AND status = 1");
        return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['site_product_id'] : 0;
    }

    /**
     * Get all site_product_ids for a product across active sites.
     *
     * @param int $productId
     * @return array [{site_id, site_product_id}, ...]
     */
    public function getProductSites($productId)
    {
        $r = Database::fetchAll('Papir',
            "SELECT ps.site_id, ps.site_product_id
             FROM product_site ps
             JOIN sites s ON s.site_id = ps.site_id AND s.status = 1
             WHERE ps.product_id = " . (int)$productId . " AND ps.status = 1");
        return ($r['ok']) ? $r['rows'] : array();
    }

    /**
     * Get category's site_category_id for a given site.
     * Replaces direct use of categoria.category_off / category_mf.
     *
     * @param int $categoryId  Papir category_id
     * @param int $siteId
     * @return int  site_category_id or 0
     */
    public function getSiteCategoryId($categoryId, $siteId)
    {
        $r = Database::fetchRow('Papir',
            "SELECT site_category_id FROM category_site_mapping
             WHERE category_id = " . (int)$categoryId . " AND site_id = " . (int)$siteId);
        return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['site_category_id'] : 0;
    }

    /**
     * Get language mapping for a site (Papir lang_id → site lang_id).
     *
     * @param int $siteId
     * @return array [papir_lang_id => site_lang_id, ...]
     */
    public function getSiteLanguages($siteId)
    {
        $r = Database::fetchAll('Papir',
            "SELECT language_id, site_lang_id FROM site_languages WHERE site_id = " . (int)$siteId);
        $map = array();
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $map[(int)$row['language_id']] = (int)$row['site_lang_id'];
            }
        }
        return $map;
    }

    // ── Internal ──────────────────────────────────────────────────────────

    private function loadSite($siteId)
    {
        $siteId = (int)$siteId;
        if (isset($this->siteCache[$siteId])) {
            return $this->siteCache[$siteId];
        }

        $r = Database::fetchRow('Papir',
            "SELECT * FROM sites WHERE site_id = {$siteId}");

        if (!$r['ok'] || empty($r['row'])) {
            return null;
        }

        $this->siteCache[$siteId] = $r['row'];
        return $r['row'];
    }

    /**
     * Load agent connection details from integration_connections.
     *
     * @param int   $siteId
     * @param array $site
     * @return array ['agent_url', 'agent_token']
     */
    private function loadAgentConnection($siteId, array $site)
    {
        // Look for connection in integration_connections by app_key
        $appKey = 'opencart2'; // CMS platform key
        $r = Database::fetchAll('Papir',
            "SELECT * FROM integration_connections WHERE app_key = '"
            . Database::escape('Papir', $appKey) . "' AND is_active = 1");

        if ($r['ok'] && !empty($r['rows'])) {
            foreach ($r['rows'] as $conn) {
                $meta = !empty($conn['metadata']) ? json_decode($conn['metadata'], true) : array();
                // Match by site_id in metadata
                if (isset($meta['site_id']) && (int)$meta['site_id'] === (int)$siteId) {
                    return array(
                        'agent_url'   => isset($meta['agent_url']) ? $meta['agent_url'] : '',
                        'agent_token' => isset($conn['api_key']) ? $conn['api_key'] : '',
                    );
                }
            }
        }

        throw new Exception("No agent connection found for site {$siteId}");
    }
}
