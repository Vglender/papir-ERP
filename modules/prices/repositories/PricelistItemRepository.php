<?php

/**
 * Репозиторий строк прайс-листа (price_supplier_items).
 * Отвечает за импорт, авто-матч, ручное сопоставление и чтение для PurchasePriceResolver.
 */
class PricelistItemRepository
{
    // ── Чтение ──────────────────────────────────────────────────────────────

    /**
     * Получить лучшую закупочную цену для товара по product_id.
     * Используется PurchasePriceResolver.
     *
     * @return array  ['price_cost'=>float|null, 'price_rrp'=>float|null, 'supplier_name'=>string]
     */
    public function getBestCostPrice($productId)
    {
        $productId = (int)$productId;
        if ($productId <= 0) {
            return array('price_cost' => null, 'price_rrp' => null, 'supplier_name' => '');
        }

        $sql = "SELECT psi.price_cost, psi.price_rrp, ps.name AS supplier_name
                FROM `price_supplier_items` psi
                JOIN `price_supplier_pricelists` ppl ON ppl.id = psi.pricelist_id
                JOIN `price_suppliers` ps            ON ps.id  = ppl.supplier_id
                WHERE psi.product_id  = $productId
                  AND psi.match_type != 'ignored'
                  AND ps.is_active   = 1
                  AND ppl.is_active  = 1
                  AND psi.price_cost IS NOT NULL
                  AND psi.price_cost  > 0
                ORDER BY ps.is_cost_source DESC, psi.price_cost ASC
                LIMIT 1";

        $result = Database::fetchRow('Papir', $sql);
        if ($result['ok'] && !empty($result['row'])) {
            $row = $result['row'];
            return array(
                'price_cost'    => (float)$row['price_cost'],
                'price_rrp'     => isset($row['price_rrp']) && $row['price_rrp'] !== null ? (float)$row['price_rrp'] : null,
                'supplier_name' => $row['supplier_name'],
            );
        }

        // Только RRP
        $sql2 = "SELECT psi.price_rrp, ps.name AS supplier_name
                 FROM `price_supplier_items` psi
                 JOIN `price_supplier_pricelists` ppl ON ppl.id = psi.pricelist_id
                 JOIN `price_suppliers` ps            ON ps.id  = ppl.supplier_id
                 WHERE psi.product_id = $productId
                   AND psi.match_type != 'ignored'
                   AND ps.is_active  = 1
                   AND ppl.is_active = 1
                   AND psi.price_rrp IS NOT NULL AND psi.price_rrp > 0
                 LIMIT 1";

        $result2 = Database::fetchRow('Papir', $sql2);
        if ($result2['ok'] && !empty($result2['row'])) {
            return array(
                'price_cost'    => null,
                'price_rrp'     => (float)$result2['row']['price_rrp'],
                'supplier_name' => $result2['row']['supplier_name'],
            );
        }

        return array('price_cost' => null, 'price_rrp' => null, 'supplier_name' => '');
    }

    /**
     * Список строк прайса с пагинацией и фильтром.
     *
     * @param int    $pricelistId
     * @param string $matchFilter  'all' | 'matched' | 'unmatched' | 'ignored'
     * @param string $search
     * @param int    $offset
     * @param int    $limit
     * @return array  ['rows'=>[], 'total'=>int]
     */
    public function getList($pricelistId, $matchFilter = 'all', $search = '', $offset = 0, $limit = 50, $extraFilters = array())
    {
        $pricelistId = (int)$pricelistId;
        // По умолчанию скрываем игнорируемые; они доступны только через фильтр 'ignored'
        $where = "psi.pricelist_id = $pricelistId AND (psi.match_type IS NULL OR psi.match_type != 'ignored')";

        if ($matchFilter === 'matched') {
            $where .= " AND psi.product_id IS NOT NULL";
        } elseif ($matchFilter === 'unmatched') {
            $where .= " AND psi.product_id IS NULL";
        } elseif ($matchFilter === 'ignored') {
            // override: show only ignored
            $where = "psi.pricelist_id = $pricelistId AND psi.match_type = 'ignored'";
        }

        if ($search !== '') {
            $rawChips = preg_split('/\s*,\s*/u', trim($search));
            $chipConditions = array();
            foreach ($rawChips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                if (preg_match('/^\d+$/', $chip)) {
                    $chipConditions[] = "pp.`product_id` = " . (int)$chip;
                    continue;
                }
                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokens = array_filter($tokens, function($t) { return $t !== ''; });
                $tokenParts = array();
                foreach ($tokens as $token) {
                    $t = Database::escape('Papir', $token);
                    $tokenParts[] = "(psi.raw_sku LIKE '%{$t}%' OR psi.raw_name LIKE '%{$t}%' OR psi.raw_model LIKE '%{$t}%' OR LOWER(COALESCE(pp.`product_article`,'')) LIKE '%{$t}%' OR CAST(pp.`product_id` AS CHAR) LIKE '%{$t}%')";
                }
                if (!empty($tokenParts)) {
                    $chipConditions[] = '(' . implode(' AND ', $tokenParts) . ')';
                }
            }
            if (!empty($chipConditions)) {
                $searchClause = count($chipConditions) === 1
                    ? $chipConditions[0]
                    : '(' . implode(' OR ', $chipConditions) . ')';
                $where .= ' AND ' . $searchClause;
            }
        }

        $stockFilter = isset($extraFilters['stock_filter']) ? $extraFilters['stock_filter'] : 'all';
        if ($stockFilter === 'has_stock') {
            $where .= " AND psi.stock IS NOT NULL AND psi.stock > 0";
        } elseif ($stockFilter === 'no_stock') {
            $where .= " AND (psi.stock IS NULL OR psi.stock = 0)";
        }

        $rrpFilter = isset($extraFilters['rrp_filter']) ? $extraFilters['rrp_filter'] : 'all';
        if ($rrpFilter === 'has_rrp') {
            $where .= " AND psi.price_rrp IS NOT NULL AND psi.price_rrp > 0";
        } elseif ($rrpFilter === 'no_rrp') {
            $where .= " AND (psi.price_rrp IS NULL OR psi.price_rrp = 0)";
        }

        $countResult = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM `price_supplier_items` psi LEFT JOIN `product_papir` pp ON pp.product_id = psi.product_id WHERE $where"
        );
        $total = ($countResult['ok'] && !empty($countResult['row'])) ? (int)$countResult['row']['cnt'] : 0;

        $result = Database::fetchAll('Papir',
            "SELECT psi.*,
                    pp.product_id AS pp_product_id, pp.product_article, pp.id_off,
                    COALESCE(pd.name, '') AS catalog_name
             FROM `price_supplier_items` psi
             LEFT JOIN `product_papir` pp ON pp.product_id = psi.product_id
             LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
             WHERE $where
             ORDER BY psi.id DESC
             LIMIT " . (int)$offset . ", " . (int)$limit
        );
        $rows = ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();

        return array('rows' => $rows, 'total' => $total);
    }

    // ── Импорт ──────────────────────────────────────────────────────────────

    /**
     * Заменить все строки прайса и выполнить авто-матч.
     *
     * @param int   $pricelistId
     * @param array $rawRows  [['raw_sku'=>, 'raw_model'=>, 'raw_name'=>, 'price_cost'=>, 'price_rrp'=>], ...]
     * @return array  ['inserted'=>int, 'matched'=>int]
     */
    public function replaceAll($pricelistId, array $rawRows)
    {
        $pricelistId = (int)$pricelistId;
        $now         = date('Y-m-d H:i:s');

        // Сохраняем ручные привязки перед удалением
        $savedMatches = array();
        $existingResult = Database::fetchAll('Papir',
            "SELECT raw_sku, raw_model, product_id, match_type
             FROM `price_supplier_items`
             WHERE pricelist_id = $pricelistId
               AND match_type IN ('manual', 'ignored')
               AND product_id IS NOT NULL"
        );
        if ($existingResult['ok'] && !empty($existingResult['rows'])) {
            foreach ($existingResult['rows'] as $m) {
                $key = $m['raw_sku'] . '|||' . $m['raw_model'];
                $savedMatches[$key] = array(
                    'product_id' => (int)$m['product_id'],
                    'match_type' => $m['match_type'],
                );
            }
        }

        Database::query('Papir', "DELETE FROM `price_supplier_items` WHERE `pricelist_id` = $pricelistId");

        if (empty($rawRows)) {
            return array('inserted' => 0, 'matched' => 0);
        }

        // Строим карту авто-матча из product_papir
        $matchMap = $this->buildMatchMap($rawRows);

        $matched = 0;
        foreach (array_chunk($rawRows, 500) as $chunk) {
            $values = array();
            foreach ($chunk as $r) {
                $rawSku   = isset($r['raw_sku'])   ? trim((string)$r['raw_sku'])   : '';
                $rawModel = isset($r['raw_model']) ? trim((string)$r['raw_model']) : '';
                $rawName  = isset($r['raw_name'])  ? trim((string)$r['raw_name'])  : '';

                $skuVal   = $rawSku   !== '' ? "'" . Database::escape('Papir', $rawSku)   . "'" : 'NULL';
                $modelVal = $rawModel !== '' ? "'" . Database::escape('Papir', $rawModel) . "'" : 'NULL';
                $nameVal  = $rawName  !== '' ? "'" . Database::escape('Papir', $rawName)  . "'" : 'NULL';
                $costVal  = (isset($r['price_cost']) && (float)$r['price_cost'] > 0) ? "'" . (float)$r['price_cost'] . "'" : 'NULL';
                $rrpVal   = (isset($r['price_rrp'])  && (float)$r['price_rrp']  > 0) ? "'" . (float)$r['price_rrp']  . "'" : 'NULL';

                // Авто-матч (с приоритетом ручных привязок из предыдущего синка)
                $productId = null;
                $matchType = 'NULL';

                $savedKey = $rawSku . '|||' . $rawModel;
                if (isset($savedMatches[$savedKey])) {
                    $productId = $savedMatches[$savedKey]['product_id'];
                    $matchType = "'" . $savedMatches[$savedKey]['match_type'] . "'";
                } elseif (isset($matchMap['model'][$rawModel]) && $rawModel !== '') {
                    $productId = $matchMap['model'][$rawModel];
                    $matchType = "'auto_model'";
                } elseif (isset($matchMap['sku'][$rawSku]) && $rawSku !== '') {
                    $productId = $matchMap['sku'][$rawSku];
                    $matchType = "'auto_sku'";
                }

                $productIdVal = $productId !== null ? (int)$productId : 'NULL';
                if ($productId !== null) $matched++;

                $values[] = "($pricelistId, $skuVal, $modelVal, $nameVal, $costVal, $rrpVal, 'UAH', $productIdVal, $matchType, '$now')";
            }

            Database::query('Papir',
                "INSERT INTO `price_supplier_items`
                 (`pricelist_id`,`raw_sku`,`raw_model`,`raw_name`,`price_cost`,`price_rrp`,`currency`,`product_id`,`match_type`,`synced_at`)
                 VALUES " . implode(',', $values)
            );
        }

        return array('inserted' => count($rawRows), 'matched' => $matched);
    }

    // ── Матчинг ─────────────────────────────────────────────────────────────

    /**
     * Установить/сменить match для строки прайса.
     *
     * @param int      $itemId
     * @param int|null $productId  null = снять привязку
     */
    public function setMatch($itemId, $productId)
    {
        $itemId    = (int)$itemId;
        $productId = $productId !== null ? (int)$productId : null;
        $matchType = $productId !== null ? 'manual' : null;

        return Database::update('Papir', 'price_supplier_items',
            array('product_id' => $productId, 'match_type' => $matchType),
            array('id' => $itemId)
        );
    }

    /**
     * Пометить строку как игнорируемую (или снять этот флаг).
     */
    public function setIgnored($itemId, $ignored = true)
    {
        $itemId = (int)$itemId;
        return Database::update('Papir', 'price_supplier_items',
            array('match_type' => $ignored ? 'ignored' : null),
            array('id' => $itemId)
        );
    }

    // ── Поиск в каталоге для матчинга ────────────────────────────────────────

    /**
     * Поиск товаров в product_papir для ручного сопоставления.
     * @return array
     */
    public function searchCatalog($query, $limit = 20)
    {
        $limit = max(1, min(100, (int)$limit));
        if (trim($query) === '') {
            return array();
        }

        $tokens = preg_split('/\s+/', trim($query));
        $where  = '1=1';
        foreach ($tokens as $t) {
            if ($t === '') continue;
            $e = Database::escape('Papir', $t);
            $where .= " AND (pp.product_article LIKE '%$e%' OR CAST(pp.product_id AS CHAR) LIKE '%$e%' OR pd.name LIKE '%$e%')";
        }

        $result = Database::fetchAll('Papir',
            "SELECT pp.product_id, pp.id_off, pp.product_article,
                    COALESCE(pd.name, '') AS name
             FROM `product_papir` pp
             LEFT JOIN `product_description` pd ON pd.product_id = pp.product_id AND pd.language_id = 2
             WHERE $where
             LIMIT $limit"
        );
        return ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();
    }

    // ── Статусы товаров ──────────────────────────────────────────────────────

    /**
     * Возвращает список product_id, сопоставленных в указанном прайсе (или во всех).
     * Используется для авто-пересчёта после синка.
     *
     * @param int|null $pricelistId  null = все прайсы
     * @return int[]
     */
    public function getMatchedProductIds($pricelistId = null)
    {
        $where = "psi.product_id IS NOT NULL AND psi.match_type != 'ignored'";
        if ($pricelistId !== null) {
            $where .= " AND psi.pricelist_id = " . (int)$pricelistId;
        }
        $result = Database::fetchAll('Papir',
            "SELECT DISTINCT psi.product_id FROM `price_supplier_items` psi WHERE $where"
        );
        if (!$result['ok'] || empty($result['rows'])) return array();
        return array_map(function ($r) { return (int)$r['product_id']; }, $result['rows']);
    }

    /**
     * Обновляет product_papir.status на основе сопоставлений в активных прайсах.
     * Товары без матча в активных прайсах → status=0.
     * Товары с матчем хотя бы в одном активном прайсе → status=1.
     *
     * @return array  ['activated'=>int, 'deactivated'=>int]
     */
    public function syncProductStatuses()
    {
        $result = Database::fetchAll('Papir',
            "SELECT DISTINCT psi.product_id
             FROM `price_supplier_items` psi
             JOIN `price_supplier_pricelists` ppl ON ppl.id = psi.pricelist_id AND ppl.is_active = 1
             JOIN `price_suppliers` ps             ON ps.id  = ppl.supplier_id  AND ps.is_active  = 1
             WHERE psi.product_id IS NOT NULL
               AND psi.match_type != 'ignored'"
        );

        if (!$result['ok']) return array('activated' => 0, 'deactivated' => 0);

        if (empty($result['rows'])) {
            Database::query('Papir', "UPDATE `product_papir` SET `status` = 0 WHERE `status` = 1");
            return array('activated' => 0, 'deactivated' => 0);
        }

        $ids = implode(',', array_map(function ($r) { return (int)$r['product_id']; }, $result['rows']));

        $r1 = Database::query('Papir', "UPDATE `product_papir` SET `status` = 1 WHERE `product_id` IN ($ids)     AND `status` != 1");
        $r2 = Database::query('Papir', "UPDATE `product_papir` SET `status` = 0 WHERE `product_id` NOT IN ($ids) AND `status` != 0");

        $activated   = ($r1['ok'] && isset($r1['affected_rows'])) ? (int)$r1['affected_rows'] : 0;
        $deactivated = ($r2['ok'] && isset($r2['affected_rows'])) ? (int)$r2['affected_rows'] : 0;

        // ── Cascade: sync status + noindex in OpenCart (off) based on product_papir.status ──
        Database::query('off',
            "UPDATE `oc_product` op
             JOIN `Papir`.`product_papir` pp ON pp.id_off = op.product_id
             SET op.status  = pp.status,
                 op.noindex = pp.status"
        );

        // ── Cascade: clean stale data for inactive products ──

        // product_discount_profile
        Database::query('Papir',
            "DELETE pdp FROM `product_discount_profile` pdp
             JOIN `product_papir` pp ON pp.product_id = pdp.product_id
             WHERE pp.status = 0"
        );

        // action_prices
        Database::query('Papir',
            "DELETE ap FROM `action_prices` ap
             JOIN `product_papir` pp ON pp.id_off = ap.product_id
             WHERE pp.status = 0"
        );

        // action_products
        Database::query('Papir',
            "DELETE apo FROM `action_products` apo
             JOIN `product_papir` pp ON pp.product_id = apo.product_id
             WHERE pp.status = 0"
        );

        // oc_product_discount (off)
        Database::query('off',
            "DELETE od FROM `oc_product_discount` od
             JOIN `Papir`.`product_papir` pp ON pp.id_off = od.product_id
             WHERE pp.status = 0"
        );

        // oc_product_special (off)
        Database::query('off',
            "DELETE os FROM `oc_product_special` os
             JOIN `Papir`.`product_papir` pp ON pp.id_off = os.product_id
             WHERE pp.status = 0"
        );

        // product_papir: для неактивных обнуляем расчётные цены,
        // сохраняем только price_purchase/price_cost и price_sale/price
        Database::query('Papir',
            "UPDATE `product_papir`
             SET `price_wholesale` = NULL,
                 `price_dealer`    = NULL,
                 `price_rrp`       = NULL
             WHERE `status` = 0
               AND (`price_wholesale` IS NOT NULL
                 OR `price_dealer`    IS NOT NULL
                 OR `price_rrp`       IS NOT NULL)"
        );

        return array('activated' => $activated, 'deactivated' => $deactivated);
    }

    // ── Все сопоставленные строки (show_all режим) ──────────────────────────

    public function getAllMatchedItems($search = '', $offset = 0, $limit = 50)
    {
        $offset = (int)$offset; $limit = (int)$limit;
        $where = "(psi.match_type IS NULL OR psi.match_type != 'ignored') AND ppl.is_active = 1";
        if ($search !== '') {
            $rawChips = preg_split('/\s*,\s*/u', trim($search));
            $chipConditions = array();
            foreach ($rawChips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;
                if (preg_match('/^\d+$/', $chip)) {
                    $chipConditions[] = "pp.`product_id` = " . (int)$chip;
                    continue;
                }
                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokens = array_filter($tokens, function($t) { return $t !== ''; });
                $tokenParts = array();
                foreach ($tokens as $token) {
                    $t = Database::escape('Papir', $token);
                    $tokenParts[] = "(psi.raw_sku LIKE '%{$t}%' OR psi.raw_name LIKE '%{$t}%' OR LOWER(COALESCE(pp.`product_article`,'')) LIKE '%{$t}%' OR CAST(pp.`product_id` AS CHAR) LIKE '%{$t}%')";
                }
                if (!empty($tokenParts)) {
                    $chipConditions[] = '(' . implode(' AND ', $tokenParts) . ')';
                }
            }
            if (!empty($chipConditions)) {
                $where .= ' AND ' . (count($chipConditions) === 1 ? $chipConditions[0] : '(' . implode(' OR ', $chipConditions) . ')');
            }
        }
        $countResult = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM price_supplier_items psi
             JOIN price_supplier_pricelists ppl ON ppl.id = psi.pricelist_id
             LEFT JOIN product_papir pp ON pp.product_id = psi.product_id
             WHERE $where"
        );
        $total = ($countResult['ok'] && !empty($countResult['row'])) ? (int)$countResult['row']['cnt'] : 0;
        $result = Database::fetchAll('Papir',
            "SELECT psi.*,
                    pp.product_id AS pp_product_id, pp.product_article, pp.id_off,
                    COALESCE(pd.name, '') AS catalog_name,
                    ppl.name AS pricelist_name,
                    ps.name AS supplier_name_item
             FROM price_supplier_items psi
             JOIN price_supplier_pricelists ppl ON ppl.id = psi.pricelist_id
             JOIN price_suppliers ps ON ps.id = ppl.supplier_id
             LEFT JOIN product_papir pp ON pp.product_id = psi.product_id
             LEFT JOIN product_description pd ON pd.product_id = pp.product_id AND pd.language_id = 2
             WHERE $where
             ORDER BY psi.id ASC
             LIMIT $offset, $limit"
        );
        $rows = ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();
        return array('rows' => $rows, 'total' => $total);
    }

    // ── Внутренние ──────────────────────────────────────────────────────────

    /**
     * Строит карты model→product_id и sku→product_id по данным из product_papir.
     * Для авто-матча при импорте.
     */
    private function buildMatchMap(array $rawRows)
    {
        $models = array();
        $skus   = array();
        foreach ($rawRows as $r) {
            if (!empty($r['raw_model'])) $models[] = Database::escape('Papir', trim($r['raw_model']));
            if (!empty($r['raw_sku']))   $skus[]   = Database::escape('Papir', trim($r['raw_sku']));
        }

        $mapModel = array();
        $mapSku   = array();

        if (!empty($models)) {
            $inList = "'" . implode("','", array_unique($models)) . "'";
            $res = Database::fetchAll('Papir',
                "SELECT product_id, id_off FROM `product_papir`
                 WHERE CAST(`id_off` AS CHAR) IN ($inList) AND id_off IS NOT NULL"
            );
            if ($res['ok']) {
                foreach ($res['rows'] as $row) {
                    $mapModel[(string)$row['id_off']] = (int)$row['product_id'];
                }
            }
        }

        if (!empty($skus)) {
            $inList = "'" . implode("','", array_unique($skus)) . "'";
            $res = Database::fetchAll('Papir',
                "SELECT product_id, product_article FROM `product_papir`
                 WHERE `product_article` IN ($inList)"
            );
            if ($res['ok']) {
                foreach ($res['rows'] as $row) {
                    // Не перезаписываем если уже есть из model
                    $mapSku[(string)$row['product_article']] = (int)$row['product_id'];
                }
            }
        }

        return array('model' => $mapModel, 'sku' => $mapSku);
    }
}
