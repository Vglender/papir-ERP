<?php

class CatalogRepository
{
    private $imageBase  = 'https://officetorg.com.ua/image/';
    private $imageDisk  = '/var/www/menufold/data/www/officetorg.com.ua/image/';

    private $allowedSort = array(
        'product_id'      => 'pp.`product_id`',
        'id_off'          => 'pp.`id_off`',
        'product_article' => 'pp.`product_article`',
        'name'            => 'name',
        'price_cost'      => 'pp.`price_cost`',
        'price_sale'      => 'pp.`price_sale`',
    );

    public function normalizeSort($sort)
    {
        return isset($this->allowedSort[$sort]) ? $sort : 'product_id';
    }

    public function normalizeOrder($order)
    {
        $order = strtolower((string)$order);
        return ($order === 'desc') ? 'desc' : 'asc';
    }

    public function getTotalCatalogCount()
    {
        $r = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM `product_papir` WHERE `id_off` IS NOT NULL AND `id_off` > 0"
        );
        return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;
    }

    public function getSites()
    {
        $r = Database::fetchAll('Papir',
            "SELECT site_id, code, name, badge FROM sites WHERE status=1 ORDER BY sort_order ASC"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function getSiteMap(array $productIds)
    {
        if (empty($productIds)) return array();
        $ids = implode(',', array_map('intval', $productIds));
        $r = Database::fetchAll('Papir',
            "SELECT product_id, site_id, status FROM product_site WHERE product_id IN ({$ids})"
        );
        // Returns: {product_id: {site_id: status}}
        $map = array();
        if ($r['ok'] && !empty($r['rows'])) {
            foreach ($r['rows'] as $row) {
                $pid = (int)$row['product_id'];
                if (!isset($map[$pid])) $map[$pid] = array();
                $map[$pid][(int)$row['site_id']] = (int)$row['status'];
            }
        }
        return $map;
    }

    public function getProductSiteStatuses($productId)
    {
        $productId = (int)$productId;
        // LEFT JOIN: returns all active sites; site_product_id/status are NULL if not mapped
        $r = Database::fetchAll('Papir',
            "SELECT s.site_id, s.name AS site_name, s.badge, s.url AS site_url,
                    ps.site_product_id, ps.status
             FROM sites s
             LEFT JOIN product_site ps ON ps.site_id = s.site_id AND ps.product_id = {$productId}
             WHERE s.status = 1
             ORDER BY s.sort_order"
        );
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    public function getTotalRows($search, $siteFilter = array(), $allSiteIds = array())
    {
        $where = $this->buildWhere($search, $siteFilter, $allSiteIds);
        $sql = "SELECT pp.`product_id`
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2 ON pd2.`product_id` = pp.`product_id` AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1 ON pd1.`product_id` = pp.`product_id` AND pd1.`language_id` = 1
                " . $where;

        $result = Database::fetchAll('Papir', $sql);
        if (!$result['ok']) {
            return 0;
        }
        return count($result['rows']);
    }

    public function getList($search, $sort, $order, $offset, $limit, $siteFilter = array(), $allSiteIds = array())
    {
        $sort  = $this->normalizeSort($sort);
        $order = $this->normalizeOrder($order);
        $where = $this->buildWhere($search, $siteFilter, $allSiteIds);
        $orderCol = isset($this->allowedSort[$sort]) ? $this->allowedSort[$sort] : 'pp.`product_id`';
        $orderDir = strtoupper($order);

        $sql = "SELECT pp.`product_id`, pp.`id_off`, pp.`product_article`,
                       COALESCE(pp.`price_cost`, 0) AS price_cost,
                       COALESCE(pp.`price_sale`, 0) AS price_sale,
                       pp.`image` AS main_image,
                       pp.`status`,
                       pp.`quantity`,
                       COALESCE(NULLIF(pd2.`name`,''), NULLIF(pd1.`name`,''), '') AS name
                FROM `product_papir` pp
                LEFT JOIN `product_description` pd2 ON pd2.`product_id` = pp.`product_id` AND pd2.`language_id` = 2
                LEFT JOIN `product_description` pd1 ON pd1.`product_id` = pp.`product_id` AND pd1.`language_id` = 1
                {$where}
                ORDER BY {$orderCol} {$orderDir}
                LIMIT " . (int)$offset . ", " . (int)$limit;

        $result = Database::fetchAll('Papir', $sql);
        if (!$result['ok'] || empty($result['rows'])) {
            return array();
        }

        $rows       = array();
        $idOffs     = array();
        $productIds = array();
        foreach ($result['rows'] as $row) {
            $idOff = (int)$row['id_off'];
            $rows[$idOff] = array(
                'product_id'      => (int)$row['product_id'],
                'id_off'          => $idOff,
                'product_article' => $row['product_article'],
                'name'            => $row['name'],
                'price_cost'      => (float)$row['price_cost'],
                'price_sale'      => (float)$row['price_sale'],
                'action_price'    => null,
                'real_stock'      => 0,
                'main_image'      => $this->normalizeImageUrl($row['main_image']),
                'status'          => (int)$row['status'],
                'site_statuses'   => array(),
            );
            $idOffs[]     = $idOff;
            $productIds[] = (int)$row['product_id'];
        }

        if (empty($idOffs)) {
            return array();
        }

        // Warehouse stock from price_supplier_items (Склад pricelist, source_type=moy_sklad)
        $warehouseMap = $this->getWarehouseStockMap($productIds);
        foreach ($rows as $idOff => $row) {
            $pid = $row['product_id'];
            $rows[$idOff]['real_stock'] = isset($warehouseMap[$pid]) ? (int)$warehouseMap[$pid] : 0;
        }

        // Site presence map: {product_id: {site_id: status}}
        $siteMap = $this->getSiteMap($productIds);
        foreach ($rows as $idOff => $row) {
            $pid = $row['product_id'];
            $rows[$idOff]['site_statuses'] = isset($siteMap[$pid]) ? $siteMap[$pid] : array();
        }

        $actionMap = $this->getActionPriceMap($idOffs);
        foreach ($actionMap as $pid => $priceAct) {
            if (isset($rows[$pid])) {
                $rows[$pid]['action_price'] = $priceAct;
            }
        }

        return array_values($rows);
    }

    public function getProductDetails($productId)
    {
        $productId = (int)$productId;
        if ($productId <= 0) return null;

        $r = Database::fetchRow('Papir',
            "SELECT pp.*,
                    COALESCE(NULLIF(pd2.`name`,''), NULLIF(pd1.`name`,''), '') AS name,
                    m.`name` AS manufacturer_name
             FROM `product_papir` pp
             LEFT JOIN `product_description` pd2 ON pd2.`product_id` = pp.`product_id` AND pd2.`language_id` = 2
             LEFT JOIN `product_description` pd1 ON pd1.`product_id` = pp.`product_id` AND pd1.`language_id` = 1
             LEFT JOIN `manufacturers` m ON m.`manufacturer_id` = pp.`manufacturer_id`
             WHERE pp.`product_id` = {$productId} LIMIT 1"
        );

        if (!$r['ok'] || empty($r['row'])) return null;

        $p = $r['row'];
        $productId = (int)$p['product_id'];

        // Warehouse stock from price_supplier_items (Склад pricelist, source_type=moy_sklad)
        $wsRow = Database::fetchRow('Papir',
            "SELECT COALESCE(SUM(psi.`stock`), 0) AS ws
             FROM `price_supplier_items` psi
             JOIN `price_supplier_pricelists` psp ON psp.`id` = psi.`pricelist_id`
             WHERE psi.`product_id` = {$productId}
               AND psp.`source_type` = 'moy_sklad'
               AND psi.`match_type` != 'ignored'"
        );
        $warehouseStock = ($wsRow['ok'] && !empty($wsRow['row'])) ? (int)$wsRow['row']['ws'] : 0;
        $totalQuantity  = (int)$p['quantity'];
        $virtualStock   = max(0, $totalQuantity - $warehouseStock);

        // Action price (action_prices.product_id stores id_off, not Papir product_id)
        $idOff   = isset($p['id_off']) ? (int)$p['id_off'] : 0;
        $actMap  = ($idOff > 0) ? $this->getActionPriceMap(array($idOff)) : array();
        $special = null;
        if ($idOff > 0 && isset($actMap[$idOff])) {
            $special = array('price' => $actMap[$idOff], 'date_start' => '', 'date_end' => '');
        }

        // Discounts from product_discount_profile + product_papir
        $discounts = $this->getDiscountData($productId, $p);

        // Category name
        $categoryName = '';
        if (!empty($p['categoria_id'])) {
            $cRow = Database::fetchRow('Papir',
                "SELECT COALESCE(NULLIF(cd2.`name`,''), NULLIF(cd1.`name`,''), '') AS category_name
                 FROM (SELECT " . (int)$p['categoria_id'] . " AS category_id) c
                 LEFT JOIN `category_description` cd2 ON cd2.`category_id` = c.`category_id` AND cd2.`language_id` = 2
                 LEFT JOIN `category_description` cd1 ON cd1.`category_id` = c.`category_id` AND cd1.`language_id` = 1
                 LIMIT 1"
            );
            if ($cRow['ok'] && !empty($cRow['row'])) {
                $categoryName = (string)$cRow['row']['category_name'];
            }
        }

        // Descriptions
        $descResult = Database::fetchAll('Papir',
            "SELECT `language_id`, `name`, `description`, `short_description`
             FROM `product_description`
             WHERE `product_id` = {$productId} AND `language_id` IN (1, 2)"
        );
        $descriptions = array(
            1 => array('language_id'=>1,'name'=>'','description'=>'','short_description'=>''),
            2 => array('language_id'=>2,'name'=>'','description'=>'','short_description'=>''),
        );
        if ($descResult['ok'] && !empty($descResult['rows'])) {
            foreach ($descResult['rows'] as $d) {
                $descriptions[(int)$d['language_id']] = $d;
            }
        }

        // Images via ProductImageService
        $imgService = new ProductImageService();
        $images     = $imgService->getImages($productId);

        // Main image per site: {site_id => relative_path}
        $mainImagePerSite = array();
        $rSitesForMain = Database::fetchAll('Papir',
            "SELECT ps.site_id, ps.site_product_id, s.db_alias
             FROM product_site ps
             JOIN sites s ON s.site_id = ps.site_id
             WHERE ps.product_id = {$productId} AND ps.site_product_id > 0"
        );
        if ($rSitesForMain['ok']) {
            foreach ($rSitesForMain['rows'] as $row) {
                $ocId = (int)$row['site_product_id'];
                $db   = $row['db_alias'];
                $rImg = Database::fetchRow($db,
                    "SELECT `image` FROM `oc_product` WHERE `product_id` = {$ocId}"
                );
                if ($rImg['ok'] && !empty($rImg['row'])) {
                    $mainImagePerSite[(int)$row['site_id']] = (string)$rImg['row']['image'];
                }
            }
        }

        return array(
            'product_id'        => $productId,
            'id_off'            => isset($p['id_off']) ? (int)$p['id_off'] : 0,
            'product_article'   => $p['product_article'],
            'status'            => isset($p['status']) ? (int)$p['status'] : 0,
            'name'              => $p['name'],
            'price_cost'        => isset($p['price_cost']) ? (float)$p['price_cost'] : 0.0,
            'price_sale'        => isset($p['price_sale']) ? (float)$p['price_sale'] : 0.0,
            'price_rrp'         => isset($p['price_rrp']) ? (float)$p['price_rrp'] : 0.0,
            'quantity'          => $totalQuantity,
            'real_stock'        => $warehouseStock,
            'virtual_stock'     => $virtualStock,
            'manufacturer_id'   => isset($p['manufacturer_id']) ? $p['manufacturer_id'] : null,
            'manufacturer_name' => isset($p['manufacturer_name']) ? $p['manufacturer_name'] : null,
            'categoria_id'      => isset($p['categoria_id']) ? $p['categoria_id'] : null,
            'category_name'     => $categoryName,
            'ean'               => isset($p['ean']) ? $p['ean'] : null,
            'tnved'             => isset($p['tnved']) ? $p['tnved'] : null,
            'unit'              => isset($p['unit']) ? $p['unit'] : null,
            'packs'             => isset($p['packs']) ? $p['packs'] : null,
            'links_prom'        => isset($p['links_prom']) ? $p['links_prom'] : null,
            'id_ms'             => isset($p['id_ms']) ? $p['id_ms'] : null,
            'id_mf'             => isset($p['id_mf']) ? $p['id_mf'] : null,
            'id_prm'            => isset($p['id_prm']) ? $p['id_prm'] : null,
            'id_prom'           => isset($p['id_prom']) ? $p['id_prom'] : null,
            'date_added'        => isset($p['date_added']) ? $p['date_added'] : null,
            'date_updated'      => isset($p['date_updated']) ? $p['date_updated'] : null,
            'main_image'        => $this->normalizeImageUrl(isset($p['image']) ? $p['image'] : ''),
            'descriptions'      => $descriptions,
            'images'            => $images,
            'main_image_per_site' => $mainImagePerSite,
            'special'           => $special,
            'discounts'         => $discounts,
            'weight'            => isset($p['weight']) ? (float)$p['weight'] : 0.0,
            'weight_class_id'   => isset($p['weight_class_id']) ? (int)$p['weight_class_id'] : 0,
            'length'            => isset($p['length']) ? (float)$p['length'] : 0.0,
            'width'             => isset($p['width']) ? (float)$p['width'] : 0.0,
            'height'            => isset($p['height']) ? (float)$p['height'] : 0.0,
            'length_class_id'   => isset($p['length_class_id']) ? (int)$p['length_class_id'] : 0,
            'seo'               => $this->getProductSeoData($productId),
            'site_statuses'     => $this->getProductSiteStatuses($productId),
        );
    }

    // product_seo indexed by [site_id][language_id]
    // site_id: 1=off, 2=mff  |  language_id: 1=RU, 2=UK (Papir IDs)
    private function getProductSeoData($productId)
    {
        $productId = (int)$productId;
        $r = Database::fetchAll('Papir',
            "SELECT ps.site_id, ps.language_id,
                    ps.seo_url, ps.seo_h1, ps.meta_title,
                    ps.meta_description, ps.meta_keyword, ps.tag,
                    ps.name, ps.short_description, ps.description,
                    s.name AS site_name, s.url AS site_url, s.code AS site_code, s.badge AS site_badge
             FROM product_seo ps
             JOIN sites s ON s.site_id = ps.site_id
             WHERE ps.product_id = {$productId}
             ORDER BY s.sort_order, ps.language_id
             LIMIT 100"
        );

        // Fallback content from product_description (per language, site-independent)
        $fallback = array(1 => array(), 2 => array());
        $pdResult = Database::fetchAll('Papir',
            "SELECT language_id, name, description, short_description
             FROM product_description WHERE product_id = {$productId} AND language_id IN (1,2)"
        );
        if ($pdResult['ok']) {
            foreach ($pdResult['rows'] as $row) {
                $fallback[(int)$row['language_id']] = $row;
            }
        }

        $empty = array(
            'seo_url'=>'','seo_h1'=>'','meta_title'=>'','meta_description'=>'',
            'meta_keyword'=>'','tag'=>'','name'=>'','short_description'=>'','description'=>'',
        );
        $data = array();

        if ($r['ok'] && !empty($r['rows'])) {
            foreach ($r['rows'] as $row) {
                $sid = (int)$row['site_id'];
                $lid = (int)$row['language_id'];
                if (!isset($data[$sid])) {
                    $data[$sid] = array(
                        'site_id' => $sid,
                        'name'    => $row['site_name'],
                        'url'     => $row['site_url'],
                        'code'    => $row['site_code'],
                        'badge'   => isset($row['site_badge']) ? $row['site_badge'] : $row['site_code'],
                        'langs'   => array(),
                    );
                }
                $fb = isset($fallback[$lid]) ? $fallback[$lid] : array();
                $data[$sid]['langs'][$lid] = array(
                    'seo_url'          => (string)$row['seo_url'],
                    'seo_h1'           => (string)$row['seo_h1'],
                    'meta_title'       => (string)$row['meta_title'],
                    'meta_description' => (string)$row['meta_description'],
                    'meta_keyword'     => (string)$row['meta_keyword'],
                    'tag'              => (string)$row['tag'],
                    // name and description always from product_description (language_id is authoritative there)
                    // product_seo.name/description are legacy fields with historically wrong language_ids
                    'name'              => isset($fb['name'])              ? (string)$fb['name']              : '',
                    'short_description' => isset($fb['short_description']) ? (string)$fb['short_description'] : '',
                    'description'       => isset($fb['description'])       ? (string)$fb['description']       : '',
                );
            }
        }

        // If product_seo has no rows, build structure from product_site + fallback
        if (empty($data)) {
            $psResult = Database::fetchAll('Papir',
                "SELECT ps.site_id, s.name AS site_name, s.url AS site_url, s.code AS site_code, s.badge AS site_badge
                 FROM product_site ps
                 JOIN sites s ON s.site_id = ps.site_id
                 WHERE ps.product_id = {$productId}
                 ORDER BY s.sort_order"
            );
            if ($psResult['ok'] && !empty($psResult['rows'])) {
                foreach ($psResult['rows'] as $ps) {
                    $sid = (int)$ps['site_id'];
                    $data[$sid] = array(
                        'site_id' => $sid,
                        'name'    => $ps['site_name'],
                        'url'     => $ps['site_url'],
                        'code'    => $ps['site_code'],
                        'badge'   => isset($ps['site_badge']) ? $ps['site_badge'] : $ps['site_code'],
                        'langs'   => array(),
                    );
                }
            }
        }

        // Ensure lang slots exist for all sites
        foreach ($data as $sid => &$site) {
            foreach (array(1, 2) as $lid) {
                if (!isset($site['langs'][$lid])) {
                    $fb = isset($fallback[$lid]) ? $fallback[$lid] : array();
                    $site['langs'][$lid] = array_merge($empty, array(
                        'name'              => isset($fb['name'])              ? (string)$fb['name']              : '',
                        'short_description' => isset($fb['short_description']) ? (string)$fb['short_description'] : '',
                        'description'       => isset($fb['description'])       ? (string)$fb['description']       : '',
                    ));
                }
            }
        }
        unset($site);

        return array_values($data);
    }

    private function getActionPriceMap(array $idOffs)
    {
        if (empty($idOffs)) return array();
        $ids = array_map('intval', array_unique($idOffs));
        $inList = implode(',', $ids);
        $r = Database::fetchAll('Papir',
            "SELECT `product_id`, `price_act`
             FROM `action_prices`
             WHERE `product_id` IN ({$inList})
               AND `price_act` IS NOT NULL AND `price_act` > 0"
        );
        $map = array();
        if ($r['ok'] && !empty($r['rows'])) {
            foreach ($r['rows'] as $row) {
                $map[(int)$row['product_id']] = (float)$row['price_act'];
            }
        }
        return $map;
    }

    private function getDiscountData($productId, $productRow)
    {
        $productId = (int)$productId;

        // Quantity discounts from product_discount_profile
        $pdpRow = Database::fetchRow('Papir',
            "SELECT `qty_1`,`price_1`,`qty_2`,`price_2`,`qty_3`,`price_3`
             FROM `product_discount_profile`
             WHERE `product_id` = {$productId} LIMIT 1"
        );

        $quantityDiscounts = array();
        if ($pdpRow['ok'] && !empty($pdpRow['row'])) {
            $pdp = $pdpRow['row'];
            for ($i = 1; $i <= 3; $i++) {
                $qty   = isset($pdp['qty_'.$i])   ? (int)$pdp['qty_'.$i]     : 0;
                $price = isset($pdp['price_'.$i]) ? (float)$pdp['price_'.$i] : 0.0;
                if ($qty > 0 && $price > 0) {
                    $quantityDiscounts[] = array('quantity' => $qty, 'price' => $price);
                }
            }
        }

        return array(
            'wholesale_price'    => isset($productRow['price_wholesale']) && $productRow['price_wholesale'] > 0
                                        ? (float)$productRow['price_wholesale'] : null,
            'dealer_price'       => isset($productRow['price_dealer']) && $productRow['price_dealer'] > 0
                                        ? (float)$productRow['price_dealer'] : null,
            'quantity_discounts' => $quantityDiscounts,
        );
    }

    private function buildWhere($search, $siteFilter = array(), $allSiteIds = array())
    {
        $base = "WHERE pp.`id_off` IS NOT NULL AND pp.`id_off` > 0";

        // --- New site filter logic ---
        // Empty $siteFilter = all checked = no filter (show all)
        // Otherwise apply:
        //   BK:   ☐ checked → AND pp.status = 0
        //   Sites: checked → OR(EXISTS checked), unchecked → AND NOT EXISTS(unchecked)
        //   All checked (bk + all sites) = no filter
        if (!empty($siteFilter) && !empty($allSiteIds)) {
            $bkChecked       = in_array('bk', $siteFilter);
            $checkedSiteIds  = array();
            foreach ($siteFilter as $sf) {
                if ((int)$sf > 0) $checkedSiteIds[] = (int)$sf;
            }
            $allSiteIdsNorm  = array_map('intval', $allSiteIds);
            $allSitesChecked = (count($checkedSiteIds) === count($allSiteIdsNorm))
                && empty(array_diff($allSiteIdsNorm, $checkedSiteIds));
            $allChecked      = $bkChecked && $allSitesChecked;

            // BK filter: always apply status=1 when BK is checked (even if all filters on)
            if ($bkChecked) {
                $base .= " AND pp.`status` = 1";
            }

            if (!$allChecked) {
                // Site filter
                if (!$allSitesChecked) {
                    if (empty($checkedSiteIds)) {
                        // No sites checked → products not on any site
                        foreach ($allSiteIdsNorm as $sid) {
                            $base .= " AND NOT EXISTS (SELECT 1 FROM product_site _ps WHERE _ps.product_id = pp.product_id AND _ps.site_id={$sid})";
                        }
                    } else {
                        // OR of checked sites
                        if (count($checkedSiteIds) === 1) {
                            $sid = $checkedSiteIds[0];
                            $base .= " AND EXISTS (SELECT 1 FROM product_site _ps WHERE _ps.product_id = pp.product_id AND _ps.site_id={$sid})";
                        } else {
                            $orClauses = array();
                            foreach ($checkedSiteIds as $sid) {
                                $orClauses[] = "EXISTS (SELECT 1 FROM product_site _ps WHERE _ps.product_id = pp.product_id AND _ps.site_id={$sid})";
                            }
                            $base .= ' AND (' . implode(' OR ', $orClauses) . ')';
                        }
                        // AND NOT EXISTS for unchecked sites
                        $uncheckedSiteIds = array_diff($allSiteIdsNorm, $checkedSiteIds);
                        foreach ($uncheckedSiteIds as $sid) {
                            $base .= " AND NOT EXISTS (SELECT 1 FROM product_site _ps WHERE _ps.product_id = pp.product_id AND _ps.site_id={$sid})";
                        }
                    }
                }
            }
        }

        $search = trim((string)$search);
        if ($search === '') return $base;

        // Split by ||| (noComma mode) or comma → OR between chips; within each chip split by space → AND
        $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
        $rawChips = preg_split($chipSep, $search);
        $chipConditions = array();

        foreach ($rawChips as $chip) {
            $chip = trim($chip);
            if ($chip === '') continue;

            // Pure integer chip → exact product_id match (fast & precise for ID lists)
            if (preg_match('/^\d+$/', $chip)) {
                $n = (int)$chip;
                $chipConditions[] = "pp.`product_id` = {$n}";
                continue;
            }

            // Text chip: split by whitespace → AND across tokens
            $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
            $tokens = array_filter($tokens, function($t) { return $t !== ''; });
            $tokenParts = array();
            foreach ($tokens as $token) {
                $t = Database::escape('Papir', $token);
                $tokenParts[] = "(CAST(pp.`product_id` AS CHAR) LIKE '%{$t}%' OR LOWER(COALESCE(pp.`product_article`,'')) LIKE '%{$t}%' OR LOWER(COALESCE(NULLIF(pd2.`name`,''),NULLIF(pd1.`name`,''),'')) LIKE '%{$t}%')";
            }
            if (!empty($tokenParts)) {
                $chipConditions[] = '(' . implode(' AND ', $tokenParts) . ')';
            }
        }

        if (empty($chipConditions)) return $base;

        $searchClause = count($chipConditions) === 1
            ? $chipConditions[0]
            : '(' . implode(' OR ', $chipConditions) . ')';

        return $base . ' AND ' . $searchClause;
    }

    private function normalizeImageUrl($value)
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        if (preg_match('~^https?://~i', $value)) return $value;
        return $this->imageBase . ltrim($value, '/');
    }

    private function getWarehouseStockMap(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }
        $ids = implode(',', array_map('intval', $productIds));
        $r = Database::fetchAll('Papir',
            "SELECT psi.`product_id`, COALESCE(SUM(psi.`stock`), 0) AS ws
             FROM `price_supplier_items` psi
             JOIN `price_supplier_pricelists` psp ON psp.`id` = psi.`pricelist_id`
             WHERE psi.`product_id` IN ({$ids})
               AND psp.`source_type` = 'moy_sklad'
               AND psi.`match_type` != 'ignored'
             GROUP BY psi.`product_id`"
        );
        $map = array();
        if ($r['ok'] && !empty($r['rows'])) {
            foreach ($r['rows'] as $row) {
                $map[(int)$row['product_id']] = (int)$row['ws'];
            }
        }
        return $map;
    }
}
