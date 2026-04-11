<?php
/**
 * SiteSyncTransportDirectDb — Direct database transport for site sync.
 *
 * Executes SQL operations directly on the OpenCart database via Database::connection().
 * Used for sites on the same server or accessible via MySQL.
 */
class SiteSyncTransportDirectDb
{
    private $dbAlias;
    private $prefix = 'oc_';

    /**
     * @param string $dbAlias  Database alias registered in Database config (e.g. 'off', 'mff')
     */
    public function __construct($dbAlias)
    {
        $this->dbAlias = $dbAlias;
    }

    private function t($table)
    {
        return $this->prefix . $table;
    }

    // ── Product ───────────────────────────────────────────────────────────

    public function productCreate(array $product, array $descriptions, array $categories,
                                  array $images, array $seoUrls)
    {
        $product['date_added']    = date('Y-m-d H:i:s');
        $product['date_modified'] = date('Y-m-d H:i:s');

        $defaults = array(
            'model' => '', 'sku' => '', 'upc' => '', 'ean' => '', 'jan' => '',
            'isbn' => '', 'mpn' => '', 'location' => '', 'quantity' => 0,
            'stock_status_id' => 5, 'manufacturer_id' => 0, 'price' => 0,
            'shipping' => 1, 'points' => 0, 'tax_class_id' => 0,
            'weight' => 0, 'weight_class_id' => 1,
            'length' => 0, 'width' => 0, 'height' => 0, 'length_class_id' => 1,
            'subtract' => 1, 'minimum' => 1, 'sort_order' => 0, 'status' => 1,
            'image' => '', 'date_available' => date('Y-m-d'),
        );
        $product = array_merge($defaults, $product);

        $r = Database::insert($this->dbAlias, $this->t('product'), $product);
        if (!$r['ok']) {
            return array('ok' => false, 'error' => 'Product insert failed');
        }
        $productId = (int)$r['insert_id'];

        // product_to_store
        $storeId = isset($product['store_id']) ? (int)$product['store_id'] : 0;
        Database::insert($this->dbAlias, $this->t('product_to_store'), array(
            'product_id' => $productId, 'store_id' => $storeId,
        ));

        // Categories
        foreach ($categories as $cat) {
            Database::insert($this->dbAlias, $this->t('product_to_category'), array(
                'product_id'    => $productId,
                'category_id'   => (int)$cat['category_id'],
                'main_category' => isset($cat['main_category']) ? (int)$cat['main_category'] : 0,
            ));
        }

        // Descriptions
        $this->insertDescriptions($productId, $descriptions);

        // Images
        $this->insertImages($productId, $images);

        // SEO URLs
        $this->saveSeoUrls('product_id', $productId, $seoUrls);

        return array('ok' => true, 'product_id' => $productId);
    }

    public function productUpdate($productId, array $fields, array $descriptions = array(),
                                  array $categories = array(), array $seoUrls = array())
    {
        $productId = (int)$productId;
        $updated = array();

        if (!empty($fields)) {
            unset($fields['product_id']);
            $fields['date_modified'] = date('Y-m-d H:i:s');
            Database::update($this->dbAlias, $this->t('product'), $fields,
                array('product_id' => $productId));
            $updated[] = 'product';
        }

        if (!empty($descriptions)) {
            foreach ($descriptions as $desc) {
                $langId = (int)$desc['language_id'];
                unset($desc['language_id']);
                if (!empty($desc)) {
                    Database::update($this->dbAlias, $this->t('product_description'), $desc,
                        array('product_id' => $productId, 'language_id' => $langId));
                }
            }
            $updated[] = 'descriptions';
        }

        if (!empty($categories)) {
            Database::delete($this->dbAlias, $this->t('product_to_category'),
                array('product_id' => $productId));
            foreach ($categories as $cat) {
                Database::insert($this->dbAlias, $this->t('product_to_category'), array(
                    'product_id'    => $productId,
                    'category_id'   => (int)$cat['category_id'],
                    'main_category' => isset($cat['main_category']) ? (int)$cat['main_category'] : 0,
                ));
            }
            $updated[] = 'categories';
        }

        if (!empty($seoUrls)) {
            $this->deleteSeoUrls('product_id', $productId);
            $this->saveSeoUrls('product_id', $productId, $seoUrls);
            $updated[] = 'seo_urls';
        }

        return array('ok' => true, 'product_id' => $productId, 'updated' => $updated);
    }

    public function productDelete($productId)
    {
        $productId = (int)$productId;

        // Collect images for cleanup
        $imgResult = Database::fetchAll($this->dbAlias,
            "SELECT image FROM " . $this->t('product_image') . " WHERE product_id = {$productId}");
        $mainResult = Database::fetchRow($this->dbAlias,
            "SELECT image FROM " . $this->t('product') . " WHERE product_id = {$productId}");

        $imagePaths = array();
        if ($mainResult['ok'] && !empty($mainResult['row']['image'])) {
            $imagePaths[] = $mainResult['row']['image'];
        }
        if ($imgResult['ok']) {
            foreach ($imgResult['rows'] as $img) {
                if (!empty($img['image'])) $imagePaths[] = $img['image'];
            }
        }

        // Cascade delete
        $tables = array('product_image', 'product_description', 'product_discount',
            'product_special', 'product_to_category', 'product_to_store',
            'product_to_layout', 'product_attribute');
        foreach ($tables as $table) {
            Database::delete($this->dbAlias, $this->t($table), array('product_id' => $productId));
        }

        // Related (both directions)
        Database::query($this->dbAlias,
            "DELETE FROM " . $this->t('product_related')
            . " WHERE product_id = {$productId} OR related_id = {$productId}");

        $this->deleteSeoUrls('product_id', $productId);

        Database::delete($this->dbAlias, $this->t('product'), array('product_id' => $productId));

        return array('ok' => true, 'product_id' => $productId, 'images_to_delete' => $imagePaths);
    }

    public function productSeo($productId, array $descriptions, array $seoUrls)
    {
        $productId = (int)$productId;

        foreach ($descriptions as $desc) {
            $langId = (int)$desc['language_id'];
            unset($desc['language_id']);
            if (!empty($desc)) {
                Database::update($this->dbAlias, $this->t('product_description'), $desc,
                    array('product_id' => $productId, 'language_id' => $langId));
            }
        }

        if (!empty($seoUrls)) {
            $this->deleteSeoUrls('product_id', $productId);
            $this->saveSeoUrls('product_id', $productId, $seoUrls);
        }

        return array('ok' => true, 'product_id' => $productId);
    }

    public function productImages($productId, $mainImage, array $extraImages)
    {
        $productId = (int)$productId;

        if ($mainImage !== null) {
            Database::update($this->dbAlias, $this->t('product'),
                array('image' => $mainImage, 'date_modified' => date('Y-m-d H:i:s')),
                array('product_id' => $productId));
        }

        // Collect old images
        $old = Database::fetchAll($this->dbAlias,
            "SELECT image FROM " . $this->t('product_image') . " WHERE product_id = {$productId}");
        $oldImages = ($old['ok']) ? $old['rows'] : array();

        Database::delete($this->dbAlias, $this->t('product_image'), array('product_id' => $productId));
        $this->insertImages($productId, $extraImages);

        return array('ok' => true, 'product_id' => $productId, 'old_images' => $oldImages);
    }

    public function productAttributes($productId, array $attributes, $replaceAll = false)
    {
        $productId = (int)$productId;

        if ($replaceAll) {
            Database::delete($this->dbAlias, $this->t('product_attribute'),
                array('product_id' => $productId));
        }

        $count = 0;
        foreach ($attributes as $attr) {
            $attrId = (int)$attr['attribute_id'];
            $langId = (int)$attr['language_id'];
            $text   = (string)$attr['text'];

            $existing = Database::fetchRow($this->dbAlias,
                "SELECT product_id FROM " . $this->t('product_attribute')
                . " WHERE product_id = {$productId} AND attribute_id = {$attrId} AND language_id = {$langId}");

            if ($existing['ok'] && !empty($existing['row'])) {
                Database::update($this->dbAlias, $this->t('product_attribute'),
                    array('text' => $text),
                    array('product_id' => $productId, 'attribute_id' => $attrId, 'language_id' => $langId));
            } else {
                Database::insert($this->dbAlias, $this->t('product_attribute'), array(
                    'product_id'   => $productId,
                    'attribute_id' => $attrId,
                    'language_id'  => $langId,
                    'text'         => $text,
                ));
            }
            $count++;
        }

        return array('ok' => true, 'product_id' => $productId, 'attributes_synced' => $count);
    }

    // ── Batch ─────────────────────────────────────────────────────────────

    public function batchPrices(array $items)
    {
        $updated = 0;
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            if (!$pid) continue;

            $fields = array('date_modified' => date('Y-m-d H:i:s'));
            if (isset($item['price']))    $fields['price']    = $item['price'];
            if (isset($item['quantity'])) $fields['quantity'] = (int)$item['quantity'];

            Database::update($this->dbAlias, $this->t('product'), $fields,
                array('product_id' => $pid));

            // Replace discounts
            if (isset($item['discounts']) && is_array($item['discounts'])) {
                Database::delete($this->dbAlias, $this->t('product_discount'),
                    array('product_id' => $pid));

                foreach ($item['discounts'] as $disc) {
                    $dateEnd = isset($disc['date_end'])
                        ? $disc['date_end']
                        : date('Y-m-d', strtotime('+1 year'));
                    Database::insert($this->dbAlias, $this->t('product_discount'), array(
                        'product_id'        => $pid,
                        'customer_group_id' => (int)$disc['customer_group_id'],
                        'quantity'          => (int)$disc['quantity'],
                        'priority'          => isset($disc['priority']) ? (int)$disc['priority'] : 0,
                        'price'             => $disc['price'],
                        'date_start'        => isset($disc['date_start']) ? $disc['date_start'] : '0000-00-00',
                        'date_end'          => $dateEnd,
                    ));
                }
            }

            $updated++;
        }

        return array('ok' => true, 'updated' => $updated);
    }

    public function batchQuantity(array $items)
    {
        $updated = 0;

        if (count($items) > 50) {
            // Bulk CASE update
            $cases = array();
            $ids   = array();
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                if (!$pid) continue;
                $cases[] = "WHEN {$pid} THEN {$qty}";
                $ids[]   = $pid;
            }
            if (!empty($ids)) {
                Database::query($this->dbAlias,
                    "UPDATE " . $this->t('product') . " SET quantity = CASE product_id "
                    . implode(' ', $cases) . " ELSE quantity END"
                    . " WHERE product_id IN (" . implode(',', $ids) . ")");
                $updated = count($ids);
            }
        } else {
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                if (!$pid) continue;
                Database::update($this->dbAlias, $this->t('product'),
                    array('quantity' => $qty),
                    array('product_id' => $pid));
                $updated++;
            }
        }

        return array('ok' => true, 'updated' => $updated);
    }

    public function batchSpecials(array $items, array $clearGroups = array(),
                                  array $customerGroupIds = array())
    {
        if (!empty($clearGroups)) {
            $gids = implode(',', array_map('intval', $clearGroups));
            Database::query($this->dbAlias,
                "DELETE FROM " . $this->t('product_special')
                . " WHERE customer_group_id IN ({$gids})");
        }

        $inserted = 0;
        if (empty($customerGroupIds)) $customerGroupIds = array(1);

        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            if (!$pid) continue;

            foreach ($customerGroupIds as $gid) {
                Database::insert($this->dbAlias, $this->t('product_special'), array(
                    'product_id'        => $pid,
                    'customer_group_id' => (int)$gid,
                    'priority'          => isset($item['priority']) ? (int)$item['priority'] : 0,
                    'price'             => $item['price'],
                    'date_start'        => isset($item['date_start']) ? $item['date_start'] : date('Y-m-d'),
                    'date_end'          => isset($item['date_end']) ? $item['date_end'] : date('Y-m-d', strtotime('+1 day')),
                ));
                $inserted++;
            }
        }

        return array('ok' => true, 'inserted' => $inserted);
    }

    // ── Category ──────────────────────────────────────────────────────────

    public function categoryCreate(array $category, array $descriptions, array $seoUrls = array())
    {
        $parentId = isset($category['parent_id']) ? (int)$category['parent_id'] : 0;

        $defaults = array(
            'parent_id' => $parentId, 'top' => 0, 'column' => 1,
            'sort_order' => 0, 'status' => 1,
            'date_added' => date('Y-m-d H:i:s'), 'date_modified' => date('Y-m-d H:i:s'),
        );
        $category = array_merge($defaults, $category);

        $r = Database::insert($this->dbAlias, $this->t('category'), $category);
        if (!$r['ok']) {
            return array('ok' => false, 'error' => 'Category insert failed');
        }
        $categoryId = (int)$r['insert_id'];

        $storeId = isset($category['store_id']) ? (int)$category['store_id'] : 0;
        Database::insert($this->dbAlias, $this->t('category_to_store'), array(
            'category_id' => $categoryId, 'store_id' => $storeId,
        ));

        foreach ($descriptions as $desc) {
            $desc['category_id'] = $categoryId;
            Database::insert($this->dbAlias, $this->t('category_description'), $desc);
        }

        // Category path
        $parentPath = array();
        if ($parentId > 0) {
            $pp = Database::fetchAll($this->dbAlias,
                "SELECT path_id FROM " . $this->t('category_path')
                . " WHERE category_id = {$parentId} ORDER BY level ASC");
            if ($pp['ok']) $parentPath = $pp['rows'];
        }

        $level = 0;
        foreach ($parentPath as $pp) {
            Database::insert($this->dbAlias, $this->t('category_path'), array(
                'category_id' => $categoryId, 'path_id' => (int)$pp['path_id'], 'level' => $level,
            ));
            $level++;
        }
        Database::insert($this->dbAlias, $this->t('category_path'), array(
            'category_id' => $categoryId, 'path_id' => $categoryId, 'level' => $level,
        ));

        $this->saveSeoUrls('category_id', $categoryId, $seoUrls);

        return array('ok' => true, 'category_id' => $categoryId);
    }

    public function categoryUpdate($categoryId, array $fields, array $descriptions = array(),
                                   array $seoUrls = array())
    {
        $categoryId = (int)$categoryId;

        if (!empty($fields)) {
            unset($fields['category_id']);
            $fields['date_modified'] = date('Y-m-d H:i:s');
            Database::update($this->dbAlias, $this->t('category'), $fields,
                array('category_id' => $categoryId));
        }

        foreach ($descriptions as $desc) {
            $langId = (int)$desc['language_id'];
            unset($desc['language_id']);
            if (!empty($desc)) {
                Database::update($this->dbAlias, $this->t('category_description'), $desc,
                    array('category_id' => $categoryId, 'language_id' => $langId));
            }
        }

        if (!empty($seoUrls)) {
            $this->deleteSeoUrls('category_id', $categoryId);
            $this->saveSeoUrls('category_id', $categoryId, $seoUrls);
        }

        return array('ok' => true, 'category_id' => $categoryId);
    }

    // ── Manufacturer ──────────────────────────────────────────────────────

    public function manufacturerSave(array $data)
    {
        $mfId = isset($data['manufacturer_id']) ? (int)$data['manufacturer_id'] : 0;

        if ($mfId > 0) {
            $update = $data;
            unset($update['manufacturer_id'], $update['store_id']);
            Database::update($this->dbAlias, $this->t('manufacturer'), $update,
                array('manufacturer_id' => $mfId));
        } else {
            $insert = $data;
            unset($insert['manufacturer_id'], $insert['store_id']);
            $r = Database::insert($this->dbAlias, $this->t('manufacturer'), $insert);
            if (!$r['ok']) {
                return array('ok' => false, 'error' => 'Manufacturer insert failed');
            }
            $mfId = (int)$r['insert_id'];
            $storeId = isset($data['store_id']) ? (int)$data['store_id'] : 0;
            Database::insert($this->dbAlias, $this->t('manufacturer_to_store'), array(
                'manufacturer_id' => $mfId, 'store_id' => $storeId,
            ));
        }

        return array('ok' => true, 'manufacturer_id' => $mfId);
    }

    // ── Orders ────────────────────────────────────────────────────────────

    public function ordersList(array $params = array())
    {
        $limit  = isset($params['limit']) ? min((int)$params['limit'], 200) : 20;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

        $where = array("o.order_status_id > 0");
        if (isset($params['status']))    $where[] = "o.order_status_id = " . (int)$params['status'];
        if (isset($params['date_from'])) $where[] = "o.date_added >= '" . Database::escape($this->dbAlias, $params['date_from'] . ' 00:00:00') . "'";
        if (isset($params['date_to']))   $where[] = "o.date_added <= '" . Database::escape($this->dbAlias, $params['date_to'] . ' 23:59:59') . "'";

        $ws = implode(' AND ', $where);
        $total = Database::fetchRow($this->dbAlias, "SELECT COUNT(*) as cnt FROM " . $this->t('order') . " o WHERE {$ws}");
        $orders = Database::fetchAll($this->dbAlias,
            "SELECT o.order_id, o.firstname, o.lastname, o.email, o.telephone, "
            . "o.total, o.currency_code, o.order_status_id, o.date_added, o.date_modified, "
            . "o.payment_method, o.shipping_method, o.comment, os.name as status_name "
            . "FROM " . $this->t('order') . " o "
            . "LEFT JOIN " . $this->t('order_status') . " os ON os.order_status_id = o.order_status_id AND os.language_id = 1 "
            . "WHERE {$ws} ORDER BY o.order_id DESC LIMIT {$offset}, {$limit}");

        return array(
            'ok'     => true,
            'total'  => ($total['ok'] && isset($total['row']['cnt'])) ? (int)$total['row']['cnt'] : 0,
            'orders' => ($orders['ok']) ? $orders['rows'] : array(),
        );
    }

    public function ordersGet($orderId)
    {
        $orderId = (int)$orderId;
        $order = Database::fetchRow($this->dbAlias,
            "SELECT o.*, os.name as status_name FROM " . $this->t('order') . " o "
            . "LEFT JOIN " . $this->t('order_status') . " os ON os.order_status_id = o.order_status_id AND os.language_id = 1 "
            . "WHERE o.order_id = {$orderId}");
        if (!$order['ok'] || empty($order['row'])) {
            return array('ok' => false, 'error' => 'Order not found');
        }
        $o = $order['row'];
        $prods = Database::fetchAll($this->dbAlias,
            "SELECT op.*, p.sku, p.model FROM " . $this->t('order_product') . " op "
            . "LEFT JOIN " . $this->t('product') . " p ON p.product_id = op.product_id "
            . "WHERE op.order_id = {$orderId}");
        $o['products'] = ($prods['ok']) ? $prods['rows'] : array();

        $totals = Database::fetchAll($this->dbAlias,
            "SELECT * FROM " . $this->t('order_total') . " WHERE order_id = {$orderId} ORDER BY sort_order");
        $o['totals'] = ($totals['ok']) ? $totals['rows'] : array();

        // Order options per product (MFF: sizes, cuts, etc.)
        foreach ($o['products'] as &$prod) {
            $prod['options'] = array();
            if ($this->tableExists('order_option')) {
                $opId = (int)$prod['order_product_id'];
                $opts = Database::fetchAll($this->dbAlias,
                    "SELECT name, value, type FROM " . $this->t('order_option') . " WHERE order_product_id = {$opId}");
                if ($opts['ok']) $prod['options'] = $opts['rows'];
            }
        }
        unset($prod);

        // Simple checkout fields
        $o['simple_fields'] = array();
        if ($this->tableExists('order_simple_fields')) {
            $sf = Database::fetchRow($this->dbAlias,
                "SELECT * FROM " . $this->t('order_simple_fields') . " WHERE order_id = {$orderId}");
            if ($sf['ok'] && !empty($sf['row'])) {
                $o['simple_fields'] = array($sf['row']);
                // Promote key fields to order level for easy access
                if (isset($sf['row']['no_call']))         $o['no_call']         = $sf['row']['no_call'];
                if (isset($sf['row']['edrpou']))           $o['edrpou']          = $sf['row']['edrpou'];
                if (isset($sf['row']['shipping_street']))  $o['shipping_street'] = $sf['row']['shipping_street'];
                if (isset($sf['row']['shipping_house']))   $o['shipping_house']  = $sf['row']['shipping_house'];
                if (isset($sf['row']['shipping_flat']))    $o['shipping_flat']   = $sf['row']['shipping_flat'];
            }
        }

        // Payment receipts (LiqPay, etc.)
        $o['payment_receipts'] = array();
        if ($this->tableExists('order_receipt_payment_info')) {
            $pr = Database::fetchAll($this->dbAlias,
                "SELECT * FROM " . $this->t('order_receipt_payment_info') . " WHERE order_id = {$orderId}");
            if ($pr['ok']) $o['payment_receipts'] = $pr['rows'];
        }

        return array('ok' => true, 'order' => $o);
    }

    // ── System ────────────────────────────────────────────────────────────

    public function info()
    {
        $langs = Database::fetchAll($this->dbAlias,
            "SELECT language_id, name, code, status FROM " . $this->t('language') . " ORDER BY sort_order");
        $groups = Database::fetchAll($this->dbAlias,
            "SELECT cg.customer_group_id, cgd.name FROM " . $this->t('customer_group') . " cg "
            . "LEFT JOIN " . $this->t('customer_group_description') . " cgd USING(customer_group_id) "
            . "WHERE cgd.language_id = 1 ORDER BY cg.sort_order");

        return array(
            'ok'              => true,
            'db_prefix'       => $this->prefix,
            'transport'       => 'direct_db',
            'db_alias'        => $this->dbAlias,
            'languages'       => ($langs['ok']) ? $langs['rows'] : array(),
            'customer_groups' => ($groups['ok']) ? $groups['rows'] : array(),
        );
    }

    public function stats()
    {
        $today = date('Y-m-d');
        $week  = date('Y-m-d', strtotime('-7 days'));
        $month = date('Y-m-d', strtotime('-30 days'));

        $ot = Database::fetchRow($this->dbAlias, "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . $this->t('order') . " WHERE DATE(date_added) = '{$today}' AND order_status_id > 0");
        $ow = Database::fetchRow($this->dbAlias, "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . $this->t('order') . " WHERE DATE(date_added) >= '{$week}' AND order_status_id > 0");
        $om = Database::fetchRow($this->dbAlias, "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . $this->t('order') . " WHERE DATE(date_added) >= '{$month}' AND order_status_id > 0");
        $p  = Database::fetchRow($this->dbAlias, "SELECT COUNT(*) as total, SUM(status=1) as active, SUM(quantity>0) as in_stock FROM " . $this->t('product'));

        return array(
            'ok' => true,
            'orders' => array(
                'today' => ($ot['ok']) ? $ot['row'] : null,
                'week'  => ($ow['ok']) ? $ow['row'] : null,
                'month' => ($om['ok']) ? $om['row'] : null,
            ),
            'products' => ($p['ok']) ? $p['row'] : null,
        );
    }

    public function cacheClear(array $types = array('all'))
    {
        // Direct DB has no remote cache to clear
        return array('ok' => true, 'cleared_count' => 0, 'note' => 'Direct DB — no remote cache');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function insertDescriptions($productId, array $descriptions)
    {
        foreach ($descriptions as $desc) {
            $desc['product_id'] = (int)$productId;
            if (!isset($desc['language_id'])) continue;
            Database::insert($this->dbAlias, $this->t('product_description'), $desc);
        }
    }

    private function insertImages($productId, array $images)
    {
        foreach ($images as $idx => $img) {
            $row = array(
                'product_id' => (int)$productId,
                'image'      => $img['image'],
                'sort_order'  => isset($img['sort_order']) ? (int)$img['sort_order'] : $idx,
            );
            if (isset($img['uuid']))              $row['uuid']              = $img['uuid'];
            if (isset($img['video']))             $row['video']             = $img['video'];
            if (isset($img['image_description'])) $row['image_description'] = $img['image_description'];
            Database::insert($this->dbAlias, $this->t('product_image'), $row);
        }
    }

    private function saveSeoUrls($entity, $id, array $urls)
    {
        if (empty($urls)) return;
        $queryVal = $entity . '=' . (int)$id;

        $hasUrlAlias = $this->tableExists('url_alias');
        $hasSeoUrl   = $this->tableExists('seo_url');

        foreach ($urls as $url) {
            if ($hasUrlAlias) {
                Database::insert($this->dbAlias, $this->t('url_alias'), array(
                    'query'   => $queryVal,
                    'keyword' => $url['keyword'],
                ));
            }
            if ($hasSeoUrl) {
                Database::insert($this->dbAlias, $this->t('seo_url'), array(
                    'store_id'    => isset($url['store_id']) ? (int)$url['store_id'] : 0,
                    'language_id' => isset($url['language_id']) ? (int)$url['language_id'] : 1,
                    'query'       => $queryVal,
                    'keyword'     => $url['keyword'],
                ));
            }
        }
    }

    private function deleteSeoUrls($entity, $id)
    {
        $queryVal = $entity . '=' . (int)$id;

        if ($this->tableExists('url_alias')) {
            Database::query($this->dbAlias,
                "DELETE FROM " . $this->t('url_alias') . " WHERE `query` = '" . Database::escape($this->dbAlias, $queryVal) . "'");
        }
        if ($this->tableExists('seo_url')) {
            Database::query($this->dbAlias,
                "DELETE FROM " . $this->t('seo_url') . " WHERE `query` = '" . Database::escape($this->dbAlias, $queryVal) . "'");
        }
    }

    /** @var array table existence cache */
    private $tableCache = array();

    private function tableExists($table)
    {
        $full = $this->t($table);
        if (!isset($this->tableCache[$full])) {
            $r = Database::fetchAll($this->dbAlias, "SHOW TABLES LIKE '{$full}'");
            $this->tableCache[$full] = ($r['ok'] && !empty($r['rows']));
        }
        return $this->tableCache[$full];
    }
}
