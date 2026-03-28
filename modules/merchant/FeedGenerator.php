<?php

require_once __DIR__ . '/../database/database.php';

/**
 * Google Merchant Center XML Feed Generator (RSS 2.0 + g: namespace)
 */
class MerchantFeedGenerator
{
    const SITE_URL    = 'https://officetorg.com.ua';
    const IMAGE_BASE  = 'https://officetorg.com.ua/image/';
    const BATCH_SIZE  = 500;

    /**
     * Generate feed and save to file. Returns ['ok'=>bool, 'items'=>int, 'size_kb'=>int, 'elapsed'=>float].
     */
    public static function toFile($path, $filters = array())
    {
        $tmpPath = $path . '.tmp';
        $fh      = fopen($tmpPath, 'w');
        if (!$fh) {
            return array('ok' => false, 'error' => 'Cannot write to ' . $tmpPath);
        }

        $start = microtime(true);
        $items = 0;

        self::generate($filters, function($chunk) use ($fh) {
            fwrite($fh, $chunk);
        }, $items);

        fclose($fh);

        if ($items === 0) {
            @unlink($tmpPath);
            return array('ok' => false, 'error' => 'Feed empty — file not saved');
        }

        rename($tmpPath, $path);
        return array(
            'ok'      => true,
            'items'   => $items,
            'size_kb' => round(filesize($path) / 1024),
            'elapsed' => round(microtime(true) - $start, 1),
        );
    }

    /**
     * Stream XML feed directly to output.
     * Call ob_end_clean() and set headers before calling this.
     *
     * @param array $filters  ['category_id' => int, 'only_stock' => bool, 'limit' => int]
     */
    public static function stream($filters = array())
    {
        while (ob_get_level()) { ob_end_clean(); }
        $items = 0;
        self::generate($filters, function($chunk) {
            echo $chunk;
            if (ob_get_level()) { ob_flush(); }
            flush();
        }, $items);
    }

    /**
     * Core generator — calls $out($chunk) for each piece of XML.
     * $itemsCount is passed by reference and incremented per item.
     */
    private static function generate($filters, $out, &$itemsCount)
    {
        $onlyStock  = !empty($filters['only_stock']);
        $categoryId = isset($filters['category_id']) ? (int)$filters['category_id'] : 0;
        $limit      = isset($filters['limit'])       ? (int)$filters['limit']       : 0;

        $where = self::buildWhere($onlyStock, $categoryId);
        $totalR = Database::fetchRow('Papir', "SELECT COUNT(*) AS cnt FROM product_papir pp
            JOIN product_site ps ON ps.product_id = pp.product_id AND ps.site_id = 1
            WHERE pp.status = 1" . $where);
        $total = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
        if ($limit > 0 && $total > $limit) { $total = $limit; }

        $out('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $out('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n");
        $out('<channel>' . "\n");
        $out('<title>Officetorg.com.ua</title>' . "\n");
        $out('<link>' . self::SITE_URL . '</link>' . "\n");
        $out('<description>Товари Officetorg — ' . date('Y-m-d H:i') . ' | ' . $total . ' позицій</description>' . "\n");

        $offset    = 0;
        $processed = 0;

        while (true) {
            $batchLimit = self::BATCH_SIZE;
            if ($limit > 0) {
                $remaining = $limit - $processed;
                if ($remaining <= 0) break;
                if ($remaining < $batchLimit) $batchLimit = $remaining;
            }

            $products = self::fetchBatch($where, $batchLimit, $offset);
            if (empty($products)) break;

            $ids    = array_map(function($p) { return (int)$p['product_id']; }, $products);
            $images = self::fetchImages($ids);

            foreach ($products as $p) {
                $pid = (int)$p['product_id'];
                ob_start();
                self::outputItem($p, isset($images[$pid]) ? $images[$pid] : array());
                $chunk = ob_get_clean();
                if ($chunk !== '') {
                    $out($chunk);
                    $processed++;
                    $itemsCount++;
                }
            }

            $offset += count($products);
            if (count($products) < $batchLimit) break;
        }

        $out('</channel>' . "\n");
        $out('</rss>' . "\n");
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function buildWhere($onlyStock, $categoryId)
    {
        $where = '';
        if ($onlyStock)   { $where .= ' AND pp.quantity > 0'; }
        if ($categoryId)  { $where .= ' AND pp.categoria_id = ' . $categoryId; }
        return $where;
    }

    private static function fetchBatch($where, $limit, $offset)
    {
        $sql = "SELECT
                    pp.product_id,
                    pp.product_article,
                    pp.quantity,
                    pp.price_sale,
                    pp.price_rrp,
                    pp.ean,
                    pp.weight,
                    pp.weight_class_id,
                    pp.customLabel0,
                    pp.link_off,
                    ps.site_product_id AS off_id,
                    COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), '') AS title,
                    COALESCE(NULLIF(pd2.short_description,''), NULLIF(pd1.short_description,''),
                             NULLIF(pd2.description,''), NULLIF(pd1.description,''), '') AS description_raw,
                    ap.price_act AS sale_price,
                    m.name AS manufacturer_name,
                    COALESCE(NULLIF(cd2.name,''), NULLIF(cd1.name,''), '') AS category_name,
                    COALESCE(NULLIF(pc.name,''), '') AS parent_category_name
                FROM product_papir pp
                JOIN product_site ps ON ps.product_id = pp.product_id AND ps.site_id = 1
                LEFT JOIN product_description pd2 ON pd2.product_id = pp.product_id AND pd2.language_id = 2
                LEFT JOIN product_description pd1 ON pd1.product_id = pp.product_id AND pd1.language_id = 1
                LEFT JOIN action_prices ap ON ap.product_id = ps.site_product_id
                    AND (ap.discount > 0 OR ap.super_discont > 0)
                LEFT JOIN menufold_offtorg.oc_product op ON op.product_id = ps.site_product_id
                LEFT JOIN menufold_offtorg.oc_manufacturer m ON m.manufacturer_id = op.manufacturer_id
                LEFT JOIN categoria c ON c.category_id = pp.categoria_id
                LEFT JOIN category_description cd2 ON cd2.category_id = c.category_id AND cd2.language_id = 2
                LEFT JOIN category_description cd1 ON cd1.category_id = c.category_id AND cd1.language_id = 1
                LEFT JOIN categoria cp ON cp.category_id = c.parent_id
                LEFT JOIN category_description pc ON pc.category_id = cp.category_id AND pc.language_id = 2
                WHERE pp.status = 1" . $where . "
                ORDER BY pp.product_id
                LIMIT " . $limit . " OFFSET " . $offset;

        $r = Database::fetchAll('Papir', $sql);
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    private static function fetchImages($productIds)
    {
        if (empty($productIds)) return array();

        $in  = implode(',', $productIds);
        $sql = "SELECT pi.product_id, pi.path, pi.sort_order
                FROM product_image pi
                JOIN product_image_site pis ON pis.image_id = pi.image_id AND pis.site_id = 1
                WHERE pi.product_id IN (" . $in . ")
                ORDER BY pi.product_id, pi.sort_order";

        $r = Database::fetchAll('Papir', $sql);
        if (!$r['ok'] || empty($r['rows'])) return array();

        $map = array();
        foreach ($r['rows'] as $row) {
            $pid = (int)$row['product_id'];
            $map[$pid][] = self::IMAGE_BASE . $row['path'];
        }
        return $map;
    }

    private static function outputItem($p, $imageUrls)
    {
        $offId = (int)$p['off_id'];
        $title = trim($p['title']);
        if ($title === '') return; // Skip products without name

        // Link
        $link = trim($p['link_off']);
        if ($link === '') {
            $link = self::SITE_URL . '/index.php?route=product/product&product_id=' . $offId;
        }

        // Description: strip tags, normalize whitespace, limit 5000 chars
        $desc = strip_tags(html_entity_decode($p['description_raw'], ENT_QUOTES, 'UTF-8'));
        $desc = preg_replace('/\s+/', ' ', $desc);
        $desc = trim($desc);
        if ($desc === '') { $desc = $title; }
        if (mb_strlen($desc, 'UTF-8') > 5000) {
            $desc = mb_substr($desc, 0, 4997, 'UTF-8') . '...';
        }

        // Price
        $price     = round((float)$p['price_sale'], 2);
        $salePrice = $p['sale_price'] ? round((float)$p['sale_price'], 2) : null;
        if ($price <= 0) return; // Skip zero-price products

        // Availability
        $availability = ((int)$p['quantity'] > 0) ? 'in stock' : 'out of stock';

        // Images
        $mainImage = !empty($imageUrls) ? $imageUrls[0] : null;
        $extraImages = array_slice($imageUrls, 1, 9); // up to 9 additional

        // Product type breadcrumb
        $productType = '';
        if ($p['parent_category_name'] !== '') {
            $productType = $p['parent_category_name'] . ' > ' . $p['category_name'];
        } elseif ($p['category_name'] !== '') {
            $productType = $p['category_name'];
        }

        // Has identifier?
        $hasEan          = !empty($p['ean']);
        $hasManufacturer = !empty($p['manufacturer_name']);
        $hasMpn          = !empty($p['product_article']);

        echo "  <item>\n";
        self::tag('g:id',           $offId);
        self::tag('g:title',        $title);
        self::tag('g:description',  $desc);
        self::tag('g:link',         $link);

        if ($mainImage) {
            self::tag('g:image_link', $mainImage);
        }
        foreach ($extraImages as $img) {
            self::tag('g:additional_image_link', $img);
        }

        self::tag('g:availability', $availability);
        self::tag('g:price',        $price . ' UAH');

        if ($salePrice && $salePrice < $price) {
            self::tag('g:sale_price', $salePrice . ' UAH');
            // Effective date: today + 365 days
            $dateStart = date('Y-m-d') . 'T00:00:00+02:00';
            $dateEnd   = date('Y-m-d', strtotime('+365 days')) . 'T23:59:59+02:00';
            self::tag('g:sale_price_effective_date', $dateStart . '/' . $dateEnd);
        }

        self::tag('g:condition', 'new');

        if ($hasEan) {
            self::tag('g:gtin', $p['ean']);
        }
        if ($hasMpn) {
            self::tag('g:mpn', $p['product_article']);
        }
        if ($hasManufacturer) {
            self::tag('g:brand', $p['manufacturer_name']);
        }
        if (!$hasEan && (!$hasManufacturer || !$hasMpn)) {
            self::tag('g:identifier_exists', 'no');
        }

        if ($productType !== '') {
            self::tag('g:product_type', $productType);
        }
        if (!empty($p['customLabel0'])) {
            self::tag('g:custom_label_0', $p['customLabel0']);
        }
        if ((float)$p['weight'] > 0) {
            // weight_class_id: 1=kg, 2=g
            $weightKg = ((int)$p['weight_class_id'] === 2)
                ? (float)$p['weight'] / 1000
                : (float)$p['weight'];
            if ($weightKg > 0) {
                self::tag('g:shipping_weight', round($weightKg, 3) . ' kg');
            }
        }

        echo "  </item>\n";
    }

    private static function tag($name, $value)
    {
        echo '    <' . $name . '>'
           . htmlspecialchars((string)$value, ENT_XML1, 'UTF-8')
           . '</' . $name . '>' . "\n";
    }
}
