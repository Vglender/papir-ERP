<?php
/**
 * Papir ERP Agent for OpenCart 2.x
 *
 * Single-file REST API agent that runs inside an OpenCart installation.
 * Papir ERP calls this agent instead of accessing the OC database directly.
 *
 * Installation:
 *   1. Place this file in OpenCart root directory
 *   2. Create papir_agent_config.php next to it (auto-generated on first run)
 *   3. Copy the generated token to Papir integration settings
 *
 * PHP 5.6+ compatible.
 */

error_reporting(0);
ini_set('display_errors', 0);

// ---------------------------------------------------------------------------
// 1. Bootstrap: load OC config for DB credentials
// ---------------------------------------------------------------------------
$ocRoot = dirname(__FILE__) . '/';
$configFile = $ocRoot . 'config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    die(json_encode(array('ok' => false, 'error' => 'OpenCart config.php not found')));
}

// OC config defines DB_* constants
require_once($configFile);

// ---------------------------------------------------------------------------
// 2. Agent config (token)
// ---------------------------------------------------------------------------
$agentConfigFile = $ocRoot . 'papir_agent_config.php';

if (!file_exists($agentConfigFile)) {
    // Auto-generate config with random token on first run
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    $configContent = "<?php\n"
        . "// Papir Agent Configuration — auto-generated " . date('Y-m-d H:i:s') . "\n"
        . "// Copy this token to Papir integration settings\n"
        . "define('PAPIR_AGENT_TOKEN', '" . $token . "');\n";

    file_put_contents($agentConfigFile, $configContent);
    chmod($agentConfigFile, 0600);

    http_response_code(200);
    header('Content-Type: application/json');
    die(json_encode(array(
        'ok'      => true,
        'message' => 'Agent installed. Token generated.',
        'token'   => $token,
        'note'    => 'Save this token in Papir. It will not be shown again in response.'
    )));
}

require_once($agentConfigFile);

// ---------------------------------------------------------------------------
// 3. Authentication
// ---------------------------------------------------------------------------
header('Content-Type: application/json');

$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

$token = '';
if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $m)) {
    $token = trim($m[1]);
}

// Also accept token via query param (for simple GET requests)
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (!defined('PAPIR_AGENT_TOKEN') || $token !== PAPIR_AGENT_TOKEN) {
    http_response_code(401);
    die(json_encode(array('ok' => false, 'error' => 'Unauthorized')));
}

// ---------------------------------------------------------------------------
// 4. Database connection
// ---------------------------------------------------------------------------
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if ($db->connect_error) {
    http_response_code(500);
    die(json_encode(array('ok' => false, 'error' => 'DB connection failed')));
}
$db->set_charset('utf8');
$db->query("SET SQL_MODE = ''");

$dbPrefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

// ---------------------------------------------------------------------------
// 5. Request routing
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$body   = array();

if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = array();
    }
}

// Helper: escape value for SQL
function esc($value) {
    global $db;
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $db->real_escape_string($value) . "'";
}

// Helper: integer
function escInt($value) {
    return intval($value);
}

// Helper: float
function escFloat($value) {
    return "'" . floatval($value) . "'";
}

// Helper: execute query, return result
function query($sql) {
    global $db;
    $result = $db->query($sql);
    if ($result === false) {
        return array('error' => $db->error, 'sql' => $sql);
    }
    if ($result === true) {
        return array('affected' => $db->affected_rows, 'insert_id' => $db->insert_id);
    }
    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

// Helper: fetch rows
function fetchAll($sql) {
    $result = query($sql);
    if (isset($result['error'])) {
        return array();
    }
    return $result;
}

// Helper: fetch single row
function fetchRow($sql) {
    $rows = fetchAll($sql);
    return !empty($rows) ? $rows[0] : null;
}

// Helper: get table name with prefix
function t($table) {
    global $dbPrefix;
    return $dbPrefix . $table;
}

// Helper: respond JSON and exit
function respond($data, $code = 200) {
    http_response_code($code);
    die(json_encode($data));
}

// Helper: error response
function respondError($msg, $code = 400) {
    respond(array('ok' => false, 'error' => $msg), $code);
}

// Helper: get required field from body
function requireField($body, $field) {
    if (!isset($body[$field])) {
        respondError("Missing required field: " . $field);
    }
    return $body[$field];
}

// ---------------------------------------------------------------------------
// Route to handler
// ---------------------------------------------------------------------------
switch ($action) {

    // ===== SYSTEM =====
    case 'info':
        handleInfo();
        break;

    case 'stats':
        handleStats();
        break;

    case 'ping':
        respond(array('ok' => true, 'time' => date('Y-m-d H:i:s')));
        break;

    // ===== PRODUCTS =====
    case 'product.create':
        handleProductCreate($body);
        break;

    case 'product.update':
        handleProductUpdate($body);
        break;

    case 'product.delete':
        handleProductDelete($body);
        break;

    case 'product.seo':
        handleProductSeo($body);
        break;

    case 'product.images':
        handleProductImages($body);
        break;

    case 'product.attributes':
        handleProductAttributes($body);
        break;

    // ===== BATCH =====
    case 'batch.prices':
        handleBatchPrices($body);
        break;

    case 'batch.quantity':
        handleBatchQuantity($body);
        break;

    case 'batch.specials':
        handleBatchSpecials($body);
        break;

    // ===== CATEGORIES =====
    case 'category.create':
        handleCategoryCreate($body);
        break;

    case 'category.update':
        handleCategoryUpdate($body);
        break;

    // ===== MANUFACTURERS =====
    case 'manufacturer.save':
        handleManufacturerSave($body);
        break;

    // ===== ORDERS =====
    case 'orders.list':
        handleOrdersList();
        break;

    case 'orders.get':
        handleOrderGet($body);
        break;

    // ===== CACHE =====
    case 'cache.clear':
        handleCacheClear($body);
        break;

    default:
        respondError('Unknown action: ' . $action, 404);
}

// ===========================================================================
// HANDLERS
// ===========================================================================

/**
 * System info: OC version, languages, installed modules, DB prefix
 */
function handleInfo() {
    // OC version from index.php or startup.php
    $version = 'unknown';
    $ocRoot = dirname(__FILE__) . '/';
    $indexFile = $ocRoot . 'index.php';
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        if (preg_match("/define\s*\(\s*'VERSION'\s*,\s*'([^']+)'/", $content, $m)) {
            $version = $m[1];
        }
    }

    // Languages
    $languages = fetchAll("SELECT language_id, name, code, status FROM " . t('language') . " ORDER BY sort_order");

    // Detect installed modules
    $modules = array();

    // Simple (checkout module)
    $simple = fetchRow("SELECT * FROM " . t('extension') . " WHERE code = 'simple' OR code = 'simplecheckout' LIMIT 1");
    if ($simple) {
        $modules['simple'] = array('installed' => true);
        // Check Simple tables
        $simpleTables = array();
        $tables = fetchAll("SHOW TABLES LIKE '" . t('simple') . "%'");
        foreach ($tables as $row) {
            $vals = array_values($row);
            $simpleTables[] = $vals[0];
        }
        $modules['simple']['tables'] = $simpleTables;
    }

    // SEO Pro / SEO URL
    $seoUrl = fetchRow("SHOW TABLES LIKE '" . t('seo_url') . "'");
    $modules['seo_url_table'] = !empty($seoUrl);

    $urlAlias = fetchRow("SHOW TABLES LIKE '" . t('url_alias') . "'");
    $modules['url_alias_table'] = !empty($urlAlias);

    // Customer groups
    $groups = fetchAll("SELECT customer_group_id, cgd.name FROM " . t('customer_group') . " cg "
        . "LEFT JOIN " . t('customer_group_description') . " cgd USING(customer_group_id) "
        . "WHERE cgd.language_id = 1 ORDER BY cg.sort_order");

    // Stores
    $stores = fetchAll("SELECT store_id, name, url FROM " . t('store'));
    array_unshift($stores, array('store_id' => '0', 'name' => 'Default', 'url' => defined('HTTP_SERVER') ? HTTP_SERVER : ''));

    respond(array(
        'ok'              => true,
        'oc_version'      => $version,
        'db_prefix'       => t(''),
        'languages'       => $languages,
        'customer_groups'  => $groups,
        'stores'          => $stores,
        'modules'         => $modules,
        'php_version'     => phpversion(),
        'agent_version'   => '1.0.0',
        'max_upload'      => ini_get('upload_max_filesize'),
        'server_time'     => date('Y-m-d H:i:s'),
    ));
}

/**
 * Site statistics: orders, products, revenue
 */
function handleStats() {
    $today = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    $monthAgo = date('Y-m-d', strtotime('-30 days'));

    // Order stats (status > 0 = processed)
    $ordersToday = fetchRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . t('order')
        . " WHERE DATE(date_added) = '{$today}' AND order_status_id > 0");
    $ordersWeek = fetchRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . t('order')
        . " WHERE DATE(date_added) >= '{$weekAgo}' AND order_status_id > 0");
    $ordersMonth = fetchRow("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM " . t('order')
        . " WHERE DATE(date_added) >= '{$monthAgo}' AND order_status_id > 0");

    // Product stats
    $products = fetchRow("SELECT COUNT(*) as total, SUM(status = 1) as active, SUM(quantity > 0) as in_stock FROM " . t('product'));

    // Recent orders
    $recent = fetchAll("SELECT order_id, CONCAT(firstname, ' ', lastname) as customer, total, "
        . "order_status_id, date_added "
        . "FROM " . t('order') . " WHERE order_status_id > 0 "
        . "ORDER BY order_id DESC LIMIT 10");

    respond(array(
        'ok' => true,
        'orders' => array(
            'today' => $ordersToday,
            'week'  => $ordersWeek,
            'month' => $ordersMonth,
        ),
        'products' => $products,
        'recent_orders' => $recent,
    ));
}

// ===========================================================================
// PRODUCT HANDLERS
// ===========================================================================

/**
 * Create product with all related data
 * Body: {product: {model, sku, price, quantity, status, ...}, descriptions: [...], images: [...]}
 */
function handleProductCreate($body) {
    global $db;

    $p = requireField($body, 'product');

    // Required fields with defaults
    $fields = array(
        'model'            => isset($p['model']) ? $p['model'] : '',
        'sku'              => isset($p['sku']) ? $p['sku'] : '',
        'upc'              => isset($p['upc']) ? $p['upc'] : '',
        'ean'              => isset($p['ean']) ? $p['ean'] : '',
        'jan'              => isset($p['jan']) ? $p['jan'] : '',
        'isbn'             => isset($p['isbn']) ? $p['isbn'] : '',
        'mpn'              => isset($p['mpn']) ? $p['mpn'] : '',
        'location'         => isset($p['location']) ? $p['location'] : '',
        'quantity'         => isset($p['quantity']) ? intval($p['quantity']) : 0,
        'stock_status_id'  => isset($p['stock_status_id']) ? intval($p['stock_status_id']) : 5,
        'manufacturer_id'  => isset($p['manufacturer_id']) ? intval($p['manufacturer_id']) : 0,
        'price'            => isset($p['price']) ? floatval($p['price']) : 0,
        'shipping'         => isset($p['shipping']) ? intval($p['shipping']) : 1,
        'points'           => isset($p['points']) ? intval($p['points']) : 0,
        'tax_class_id'     => isset($p['tax_class_id']) ? intval($p['tax_class_id']) : 0,
        'weight'           => isset($p['weight']) ? floatval($p['weight']) : 0,
        'weight_class_id'  => isset($p['weight_class_id']) ? intval($p['weight_class_id']) : 1,
        'length'           => isset($p['length']) ? floatval($p['length']) : 0,
        'width'            => isset($p['width']) ? floatval($p['width']) : 0,
        'height'           => isset($p['height']) ? floatval($p['height']) : 0,
        'length_class_id'  => isset($p['length_class_id']) ? intval($p['length_class_id']) : 1,
        'subtract'         => isset($p['subtract']) ? intval($p['subtract']) : 1,
        'minimum'          => isset($p['minimum']) ? intval($p['minimum']) : 1,
        'sort_order'       => isset($p['sort_order']) ? intval($p['sort_order']) : 0,
        'status'           => isset($p['status']) ? intval($p['status']) : 1,
        'image'            => isset($p['image']) ? $p['image'] : '',
        'date_available'   => isset($p['date_available']) ? $p['date_available'] : date('Y-m-d'),
        'date_added'       => date('Y-m-d H:i:s'),
        'date_modified'    => date('Y-m-d H:i:s'),
    );

    // Optional fields (uuid, noindex, unit, cogs, min_price, options_buy)
    $optionalFields = array('uuid', 'noindex', 'unit', 'cogs', 'min_price', 'options_buy', 'viewed');
    foreach ($optionalFields as $f) {
        if (isset($p[$f])) {
            $fields[$f] = $p[$f];
        }
    }

    // Build INSERT
    $cols = array();
    $vals = array();
    foreach ($fields as $col => $val) {
        $cols[] = '`' . $col . '`';
        if (is_int($val) || is_float($val)) {
            $vals[] = $val;
        } else {
            $vals[] = esc($val);
        }
    }

    $sql = "INSERT INTO " . t('product') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $result = query($sql);

    if (isset($result['error'])) {
        respondError('Product insert failed: ' . $result['error'], 500);
    }

    $productId = $result['insert_id'];

    // Product to store
    $storeId = isset($p['store_id']) ? intval($p['store_id']) : 0;
    query("INSERT INTO " . t('product_to_store') . " (product_id, store_id) VALUES ({$productId}, {$storeId})");

    // Product to category
    if (isset($body['categories']) && is_array($body['categories'])) {
        foreach ($body['categories'] as $cat) {
            $catId = intval($cat['category_id']);
            $main = isset($cat['main_category']) ? intval($cat['main_category']) : 0;
            query("INSERT INTO " . t('product_to_category')
                . " (product_id, category_id, main_category) VALUES ({$productId}, {$catId}, {$main})");
        }
    }

    // Product descriptions (multi-language)
    if (isset($body['descriptions']) && is_array($body['descriptions'])) {
        foreach ($body['descriptions'] as $desc) {
            $langId = intval($desc['language_id']);
            $descFields = array(
                'product_id'        => $productId,
                'language_id'       => $langId,
                'name'              => isset($desc['name']) ? $desc['name'] : '',
                'description'       => isset($desc['description']) ? $desc['description'] : '',
                'short_description' => isset($desc['short_description']) ? $desc['short_description'] : '',
                'tag'               => isset($desc['tag']) ? $desc['tag'] : '',
                'meta_title'        => isset($desc['meta_title']) ? $desc['meta_title'] : '',
                'meta_description'  => isset($desc['meta_description']) ? $desc['meta_description'] : '',
                'meta_keyword'      => isset($desc['meta_keyword']) ? $desc['meta_keyword'] : '',
                'meta_h1'           => isset($desc['meta_h1']) ? $desc['meta_h1'] : '',
            );

            // Optional fields that may not exist in all OC versions
            $optDesc = array('description_mini', 'image_description');
            foreach ($optDesc as $f) {
                if (isset($desc[$f])) {
                    $descFields[$f] = $desc[$f];
                }
            }

            $dCols = array();
            $dVals = array();
            foreach ($descFields as $col => $val) {
                $dCols[] = '`' . $col . '`';
                $dVals[] = is_int($val) ? $val : esc($val);
            }

            query("INSERT INTO " . t('product_description')
                . " (" . implode(',', $dCols) . ") VALUES (" . implode(',', $dVals) . ")");
        }
    }

    // Product images
    if (isset($body['images']) && is_array($body['images'])) {
        foreach ($body['images'] as $idx => $img) {
            $imgPath = esc($img['image']);
            $sortOrder = isset($img['sort_order']) ? intval($img['sort_order']) : $idx;
            $extra = '';
            $extraCols = '';
            if (isset($img['uuid'])) {
                $extraCols = ', uuid';
                $extra = ', ' . esc($img['uuid']);
            }
            query("INSERT INTO " . t('product_image')
                . " (product_id, image, sort_order{$extraCols}) VALUES ({$productId}, {$imgPath}, {$sortOrder}{$extra})");
        }
    }

    // URL alias / SEO URL
    if (isset($body['seo_urls']) && is_array($body['seo_urls'])) {
        _saveSeoUrls('product_id', $productId, $body['seo_urls']);
    }

    respond(array('ok' => true, 'product_id' => $productId));
}

/**
 * Update product fields
 * Body: {product_id: 123, fields: {price: 100, status: 1, ...}, descriptions: [...], categories: [...]}
 */
function handleProductUpdate($body) {
    $productId = intval(requireField($body, 'product_id'));

    // Check product exists
    $existing = fetchRow("SELECT product_id FROM " . t('product') . " WHERE product_id = {$productId}");
    if (!$existing) {
        respondError('Product not found: ' . $productId, 404);
    }

    $updated = array();

    // Update main product fields
    if (isset($body['fields']) && is_array($body['fields'])) {
        $sets = array();
        foreach ($body['fields'] as $col => $val) {
            if ($col === 'product_id') continue; // skip PK
            if (is_null($val)) {
                $sets[] = "`{$col}` = NULL";
            } elseif (is_int($val) || is_float($val)) {
                $sets[] = "`{$col}` = {$val}";
            } else {
                $sets[] = "`{$col}` = " . esc($val);
            }
        }
        if (!empty($sets)) {
            $sets[] = "`date_modified` = NOW()";
            query("UPDATE " . t('product') . " SET " . implode(', ', $sets) . " WHERE product_id = {$productId}");
            $updated[] = 'product';
        }
    }

    // Update descriptions
    if (isset($body['descriptions']) && is_array($body['descriptions'])) {
        foreach ($body['descriptions'] as $desc) {
            $langId = intval($desc['language_id']);
            $sets = array();
            $allowedCols = array('name', 'description', 'short_description', 'description_mini',
                'tag', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1', 'image_description');
            foreach ($allowedCols as $col) {
                if (isset($desc[$col])) {
                    $sets[] = "`{$col}` = " . esc($desc[$col]);
                }
            }
            if (!empty($sets)) {
                query("UPDATE " . t('product_description')
                    . " SET " . implode(', ', $sets)
                    . " WHERE product_id = {$productId} AND language_id = {$langId}");
            }
        }
        $updated[] = 'descriptions';
    }

    // Update categories
    if (isset($body['categories']) && is_array($body['categories'])) {
        query("DELETE FROM " . t('product_to_category') . " WHERE product_id = {$productId}");
        foreach ($body['categories'] as $cat) {
            $catId = intval($cat['category_id']);
            $main = isset($cat['main_category']) ? intval($cat['main_category']) : 0;
            query("INSERT INTO " . t('product_to_category')
                . " (product_id, category_id, main_category) VALUES ({$productId}, {$catId}, {$main})");
        }
        $updated[] = 'categories';
    }

    // Update SEO URLs
    if (isset($body['seo_urls']) && is_array($body['seo_urls'])) {
        _deleteSeoUrls('product_id', $productId);
        _saveSeoUrls('product_id', $productId, $body['seo_urls']);
        $updated[] = 'seo_urls';
    }

    respond(array('ok' => true, 'product_id' => $productId, 'updated' => $updated));
}

/**
 * Delete product with full cascade
 * Body: {product_id: 123}
 */
function handleProductDelete($body) {
    $productId = intval(requireField($body, 'product_id'));

    $existing = fetchRow("SELECT product_id, image FROM " . t('product') . " WHERE product_id = {$productId}");
    if (!$existing) {
        respondError('Product not found: ' . $productId, 404);
    }

    // Collect image paths for cleanup
    $images = fetchAll("SELECT image FROM " . t('product_image') . " WHERE product_id = {$productId}");
    $imagePaths = array();
    if (!empty($existing['image'])) {
        $imagePaths[] = $existing['image'];
    }
    foreach ($images as $img) {
        if (!empty($img['image'])) {
            $imagePaths[] = $img['image'];
        }
    }

    // Cascade delete from all related tables
    $relatedTables = array(
        'product_image', 'product_description', 'product_discount', 'product_special',
        'product_to_category', 'product_to_store', 'product_to_layout',
        'product_attribute', 'product_option', 'product_option_value',
    );

    foreach ($relatedTables as $table) {
        query("DELETE FROM " . t($table) . " WHERE product_id = {$productId}");
    }

    // Related products (both directions)
    query("DELETE FROM " . t('product_related') . " WHERE product_id = {$productId} OR related_id = {$productId}");

    // SEO URLs
    _deleteSeoUrls('product_id', $productId);

    // Main product
    query("DELETE FROM " . t('product') . " WHERE product_id = {$productId}");

    respond(array('ok' => true, 'product_id' => $productId, 'images_to_delete' => $imagePaths));
}

/**
 * Update product SEO: descriptions + URL aliases
 * Body: {product_id, descriptions: [...], seo_urls: [...]}
 */
function handleProductSeo($body) {
    $productId = intval(requireField($body, 'product_id'));

    if (isset($body['descriptions']) && is_array($body['descriptions'])) {
        foreach ($body['descriptions'] as $desc) {
            $langId = intval($desc['language_id']);
            $sets = array();
            $cols = array('name', 'description', 'short_description', 'meta_title',
                'meta_description', 'meta_keyword', 'meta_h1');
            foreach ($cols as $col) {
                if (isset($desc[$col])) {
                    $sets[] = "`{$col}` = " . esc($desc[$col]);
                }
            }
            if (!empty($sets)) {
                query("UPDATE " . t('product_description')
                    . " SET " . implode(', ', $sets)
                    . " WHERE product_id = {$productId} AND language_id = {$langId}");
            }
        }
    }

    if (!empty($body['seo_urls']) && is_array($body['seo_urls'])) {
        _deleteSeoUrls('product_id', $productId);
        _saveSeoUrls('product_id', $productId, $body['seo_urls']);
    }

    respond(array('ok' => true, 'product_id' => $productId));
}

/**
 * Sync product images (replace all)
 * Body: {product_id, main_image: "path", images: [{image, sort_order, uuid?}]}
 */
function handleProductImages($body) {
    $productId = intval(requireField($body, 'product_id'));

    // Update main image
    if (isset($body['main_image'])) {
        query("UPDATE " . t('product') . " SET image = " . esc($body['main_image'])
            . ", date_modified = NOW() WHERE product_id = {$productId}");
    }

    // Replace extra images
    if (isset($body['images']) && is_array($body['images'])) {
        // Get old images for cleanup
        $oldImages = fetchAll("SELECT image FROM " . t('product_image') . " WHERE product_id = {$productId}");

        query("DELETE FROM " . t('product_image') . " WHERE product_id = {$productId}");

        foreach ($body['images'] as $idx => $img) {
            $imgPath = esc($img['image']);
            $sortOrder = isset($img['sort_order']) ? intval($img['sort_order']) : $idx;
            $extra = '';
            $extraCols = '';
            if (isset($img['uuid'])) {
                $extraCols = ', uuid';
                $extra = ', ' . esc($img['uuid']);
            }
            if (isset($img['video'])) {
                $extraCols .= ', video';
                $extra .= ', ' . esc($img['video']);
            }
            if (isset($img['image_description'])) {
                $extraCols .= ', image_description';
                $extra .= ', ' . esc($img['image_description']);
            }
            query("INSERT INTO " . t('product_image')
                . " (product_id, image, sort_order{$extraCols}) VALUES ({$productId}, {$imgPath}, {$sortOrder}{$extra})");
        }

        respond(array('ok' => true, 'product_id' => $productId, 'old_images' => $oldImages));
    }

    respond(array('ok' => true, 'product_id' => $productId));
}

/**
 * Sync product attributes
 * Body: {product_id, attributes: [{attribute_id, language_id, text}]}
 */
function handleProductAttributes($body) {
    $productId = intval(requireField($body, 'product_id'));
    $attrs = requireField($body, 'attributes');

    if (isset($body['replace_all']) && $body['replace_all']) {
        query("DELETE FROM " . t('product_attribute') . " WHERE product_id = {$productId}");
    }

    $count = 0;
    foreach ($attrs as $attr) {
        $attrId = intval($attr['attribute_id']);
        $langId = intval($attr['language_id']);
        $text = esc($attr['text']);

        // Upsert
        $existing = fetchRow("SELECT product_id FROM " . t('product_attribute')
            . " WHERE product_id = {$productId} AND attribute_id = {$attrId} AND language_id = {$langId}");

        if ($existing) {
            query("UPDATE " . t('product_attribute')
                . " SET text = {$text}"
                . " WHERE product_id = {$productId} AND attribute_id = {$attrId} AND language_id = {$langId}");
        } else {
            query("INSERT INTO " . t('product_attribute')
                . " (product_id, attribute_id, language_id, text) VALUES ({$productId}, {$attrId}, {$langId}, {$text})");
        }
        $count++;
    }

    respond(array('ok' => true, 'product_id' => $productId, 'attributes_synced' => $count));
}

// ===========================================================================
// BATCH HANDLERS
// ===========================================================================

/**
 * Batch update prices and discounts
 * Body: {items: [{product_id, price, quantity, discounts: [{customer_group_id, quantity, price, priority?}]}]}
 */
function handleBatchPrices($body) {
    global $db;

    $items = requireField($body, 'items');
    $updated = 0;
    $errors = array();

    foreach ($items as $item) {
        $pid = intval($item['product_id']);
        if (!$pid) continue;

        // Update price + quantity on main product
        $sets = array();
        if (isset($item['price'])) {
            $sets[] = "price = " . floatval($item['price']);
        }
        if (isset($item['quantity'])) {
            $sets[] = "quantity = " . intval($item['quantity']);
        }
        if (!empty($sets)) {
            $sets[] = "date_modified = NOW()";
            $result = query("UPDATE " . t('product') . " SET " . implode(', ', $sets) . " WHERE product_id = {$pid}");
            if (isset($result['error'])) {
                $errors[] = array('product_id' => $pid, 'error' => $result['error']);
                continue;
            }
        }

        // Replace discounts
        if (isset($item['discounts']) && is_array($item['discounts'])) {
            query("DELETE FROM " . t('product_discount') . " WHERE product_id = {$pid}");

            foreach ($item['discounts'] as $disc) {
                $groupId = intval($disc['customer_group_id']);
                $qty = intval($disc['quantity']);
                $price = floatval($disc['price']);
                $priority = isset($disc['priority']) ? intval($disc['priority']) : 0;
                $dateStart = isset($disc['date_start']) ? esc($disc['date_start']) : "'0000-00-00'";
                $dateEnd = isset($disc['date_end']) ? esc($disc['date_end']) : "'" . date('Y-m-d', strtotime('+1 year')) . "'";

                query("INSERT INTO " . t('product_discount')
                    . " (product_id, customer_group_id, quantity, priority, price, date_start, date_end)"
                    . " VALUES ({$pid}, {$groupId}, {$qty}, {$priority}, {$price}, {$dateStart}, {$dateEnd})");
            }
        }

        $updated++;
    }

    respond(array('ok' => true, 'updated' => $updated, 'errors' => $errors));
}

/**
 * Batch update stock quantities
 * Body: {items: [{product_id, quantity}]}
 */
function handleBatchQuantity($body) {
    $items = requireField($body, 'items');
    $updated = 0;

    // Build batch UPDATE via CASE for efficiency
    if (count($items) > 50) {
        // Bulk: single query with CASE
        $cases = array();
        $ids = array();
        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $qty = intval($item['quantity']);
            if (!$pid) continue;
            $cases[] = "WHEN {$pid} THEN {$qty}";
            $ids[] = $pid;
        }

        if (!empty($ids)) {
            $sql = "UPDATE " . t('product') . " SET quantity = CASE product_id "
                . implode(' ', $cases) . " ELSE quantity END"
                . " WHERE product_id IN (" . implode(',', $ids) . ")";
            $result = query($sql);
            $updated = isset($result['affected']) ? $result['affected'] : 0;
        }
    } else {
        // Small batch: individual updates
        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $qty = intval($item['quantity']);
            if (!$pid) continue;
            query("UPDATE " . t('product') . " SET quantity = {$qty} WHERE product_id = {$pid}");
            $updated++;
        }
    }

    respond(array('ok' => true, 'updated' => $updated));
}

/**
 * Batch update specials (promotional prices)
 * Body: {customer_group_ids: [1,4], items: [{product_id, price, date_start, date_end}]}
 * OR:   {clear_groups: [1,4]} to just clear specials
 */
function handleBatchSpecials($body) {
    // Clear old specials for specified groups
    if (isset($body['clear_groups']) && is_array($body['clear_groups'])) {
        $groupIds = array_map('intval', $body['clear_groups']);
        query("DELETE FROM " . t('product_special')
            . " WHERE customer_group_id IN (" . implode(',', $groupIds) . ")");
    }

    $inserted = 0;
    if (isset($body['items']) && is_array($body['items'])) {
        $groupIds = isset($body['customer_group_ids']) ? $body['customer_group_ids'] : array(1);

        foreach ($body['items'] as $item) {
            $pid = intval($item['product_id']);
            if (!$pid) continue;

            $price = floatval($item['price']);
            $dateStart = isset($item['date_start']) ? esc($item['date_start']) : "'" . date('Y-m-d') . "'";
            $dateEnd = isset($item['date_end']) ? esc($item['date_end']) : "'" . date('Y-m-d', strtotime('+1 day')) . "'";
            $priority = isset($item['priority']) ? intval($item['priority']) : 0;

            foreach ($groupIds as $groupId) {
                $gid = intval($groupId);
                query("INSERT INTO " . t('product_special')
                    . " (product_id, customer_group_id, priority, price, date_start, date_end)"
                    . " VALUES ({$pid}, {$gid}, {$priority}, {$price}, {$dateStart}, {$dateEnd})");
                $inserted++;
            }
        }
    }

    respond(array('ok' => true, 'inserted' => $inserted));
}

// ===========================================================================
// CATEGORY HANDLERS
// ===========================================================================

/**
 * Create category
 * Body: {category: {parent_id, sort_order, status, ...}, descriptions: [...], seo_urls: [...]}
 */
function handleCategoryCreate($body) {
    $c = requireField($body, 'category');

    $parentId = isset($c['parent_id']) ? intval($c['parent_id']) : 0;
    $fields = array(
        'parent_id'     => $parentId,
        'top'           => isset($c['top']) ? intval($c['top']) : 0,
        'column'        => isset($c['column']) ? intval($c['column']) : 1,
        'sort_order'    => isset($c['sort_order']) ? intval($c['sort_order']) : 0,
        'status'        => isset($c['status']) ? intval($c['status']) : 1,
        'date_added'    => date('Y-m-d H:i:s'),
        'date_modified' => date('Y-m-d H:i:s'),
    );

    $optional = array('noindex', 'uuid', 'image');
    foreach ($optional as $f) {
        if (isset($c[$f])) {
            $fields[$f] = $c[$f];
        }
    }

    $cols = array();
    $vals = array();
    foreach ($fields as $col => $val) {
        $cols[] = '`' . $col . '`';
        $vals[] = is_int($val) ? $val : esc($val);
    }

    $result = query("INSERT INTO " . t('category') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
    if (isset($result['error'])) {
        respondError('Category insert failed: ' . $result['error'], 500);
    }

    $categoryId = $result['insert_id'];

    // Category to store
    $storeId = isset($c['store_id']) ? intval($c['store_id']) : 0;
    query("INSERT INTO " . t('category_to_store') . " (category_id, store_id) VALUES ({$categoryId}, {$storeId})");

    // Descriptions
    if (isset($body['descriptions']) && is_array($body['descriptions'])) {
        foreach ($body['descriptions'] as $desc) {
            $langId = intval($desc['language_id']);
            query("INSERT INTO " . t('category_description')
                . " (category_id, language_id, name, description, meta_title, meta_description, meta_keyword"
                . (isset($desc['meta_h1']) ? ", meta_h1" : "") . ")"
                . " VALUES ({$categoryId}, {$langId}, " . esc(isset($desc['name']) ? $desc['name'] : '')
                . ", " . esc(isset($desc['description']) ? $desc['description'] : '')
                . ", " . esc(isset($desc['meta_title']) ? $desc['meta_title'] : '')
                . ", " . esc(isset($desc['meta_description']) ? $desc['meta_description'] : '')
                . ", " . esc(isset($desc['meta_keyword']) ? $desc['meta_keyword'] : '')
                . (isset($desc['meta_h1']) ? ", " . esc($desc['meta_h1']) : "") . ")");
        }
    }

    // Category path
    // Get parent's path
    $parentPath = array();
    if ($parentId > 0) {
        $parentPath = fetchAll("SELECT path_id FROM " . t('category_path')
            . " WHERE category_id = {$parentId} ORDER BY level ASC");
    }

    $level = 0;
    foreach ($parentPath as $pp) {
        query("INSERT INTO " . t('category_path') . " (category_id, path_id, level) VALUES ({$categoryId}, " . intval($pp['path_id']) . ", {$level})");
        $level++;
    }
    // Self path
    query("INSERT INTO " . t('category_path') . " (category_id, path_id, level) VALUES ({$categoryId}, {$categoryId}, {$level})");

    // SEO URLs
    if (isset($body['seo_urls']) && is_array($body['seo_urls'])) {
        _saveSeoUrls('category_id', $categoryId, $body['seo_urls']);
    }

    respond(array('ok' => true, 'category_id' => $categoryId));
}

/**
 * Update category
 * Body: {category_id, fields: {...}, descriptions: [...], seo_urls: [...]}
 */
function handleCategoryUpdate($body) {
    $categoryId = intval(requireField($body, 'category_id'));

    if (isset($body['fields']) && is_array($body['fields'])) {
        $sets = array();
        foreach ($body['fields'] as $col => $val) {
            if ($col === 'category_id') continue;
            if (is_null($val)) {
                $sets[] = "`{$col}` = NULL";
            } elseif (is_int($val) || is_float($val)) {
                $sets[] = "`{$col}` = {$val}";
            } else {
                $sets[] = "`{$col}` = " . esc($val);
            }
        }
        if (!empty($sets)) {
            $sets[] = "`date_modified` = NOW()";
            query("UPDATE " . t('category') . " SET " . implode(', ', $sets) . " WHERE category_id = {$categoryId}");
        }
    }

    if (isset($body['descriptions']) && is_array($body['descriptions'])) {
        foreach ($body['descriptions'] as $desc) {
            $langId = intval($desc['language_id']);
            $sets = array();
            $cols = array('name', 'description', 'meta_title', 'meta_description', 'meta_keyword', 'meta_h1');
            foreach ($cols as $col) {
                if (isset($desc[$col])) {
                    $sets[] = "`{$col}` = " . esc($desc[$col]);
                }
            }
            if (!empty($sets)) {
                query("UPDATE " . t('category_description')
                    . " SET " . implode(', ', $sets)
                    . " WHERE category_id = {$categoryId} AND language_id = {$langId}");
            }
        }
    }

    if (isset($body['seo_urls']) && is_array($body['seo_urls'])) {
        _deleteSeoUrls('category_id', $categoryId);
        _saveSeoUrls('category_id', $categoryId, $body['seo_urls']);
    }

    respond(array('ok' => true, 'category_id' => $categoryId));
}

// ===========================================================================
// MANUFACTURER HANDLERS
// ===========================================================================

/**
 * Create or update manufacturer
 * Body: {manufacturer_id?: int, name, image?, noindex?, sort_order?, uuid?}
 * Returns: {ok, manufacturer_id}
 */
function handleManufacturerSave($body) {
    $name = requireField($body, 'name');
    $mfId = isset($body['manufacturer_id']) ? intval($body['manufacturer_id']) : 0;

    if ($mfId > 0) {
        // Update
        $sets = array("name = " . esc($name));
        $optional = array('image', 'noindex', 'sort_order', 'uuid');
        foreach ($optional as $f) {
            if (isset($body[$f])) {
                $sets[] = "`{$f}` = " . esc($body[$f]);
            }
        }
        query("UPDATE " . t('manufacturer') . " SET " . implode(', ', $sets) . " WHERE manufacturer_id = {$mfId}");
    } else {
        // Create
        $cols = array('name');
        $vals = array(esc($name));

        $optional = array('image', 'noindex', 'sort_order', 'uuid');
        foreach ($optional as $f) {
            if (isset($body[$f])) {
                $cols[] = '`' . $f . '`';
                $vals[] = esc($body[$f]);
            }
        }

        $result = query("INSERT INTO " . t('manufacturer') . " (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
        if (isset($result['error'])) {
            respondError('Manufacturer insert failed: ' . $result['error'], 500);
        }
        $mfId = $result['insert_id'];

        // Manufacturer to store
        $storeId = isset($body['store_id']) ? intval($body['store_id']) : 0;
        query("INSERT INTO " . t('manufacturer_to_store') . " (manufacturer_id, store_id) VALUES ({$mfId}, {$storeId})");
    }

    respond(array('ok' => true, 'manufacturer_id' => $mfId));
}

// ===========================================================================
// ORDER HANDLERS
// ===========================================================================

/**
 * List recent orders
 * Query params: ?limit=20&offset=0&status=&date_from=&date_to=
 */
function handleOrdersList() {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = min($limit, 200);

    $where = array("o.order_status_id > 0");

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = "o.order_status_id = " . intval($_GET['status']);
    }
    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
        $where[] = "o.date_added >= " . esc($_GET['date_from'] . ' 00:00:00');
    }
    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
        $where[] = "o.date_added <= " . esc($_GET['date_to'] . ' 23:59:59');
    }

    $whereStr = implode(' AND ', $where);

    $total = fetchRow("SELECT COUNT(*) as cnt FROM " . t('order') . " o WHERE {$whereStr}");

    $orders = fetchAll(
        "SELECT o.order_id, o.firstname, o.lastname, o.email, o.telephone,"
        . " o.total, o.currency_code, o.order_status_id, o.date_added, o.date_modified,"
        . " o.payment_method, o.shipping_method, o.comment,"
        . " os.name as status_name"
        . " FROM " . t('order') . " o"
        . " LEFT JOIN " . t('order_status') . " os ON os.order_status_id = o.order_status_id AND os.language_id = 1"
        . " WHERE {$whereStr}"
        . " ORDER BY o.order_id DESC"
        . " LIMIT {$offset}, {$limit}"
    );

    respond(array(
        'ok'     => true,
        'total'  => intval($total['cnt']),
        'orders' => $orders,
    ));
}

/**
 * Get single order with positions
 * Body: {order_id: 123}
 */
function handleOrderGet($body) {
    $orderId = intval(requireField($body, 'order_id'));

    $order = fetchRow(
        "SELECT o.*, os.name as status_name"
        . " FROM " . t('order') . " o"
        . " LEFT JOIN " . t('order_status') . " os ON os.order_status_id = o.order_status_id AND os.language_id = 1"
        . " WHERE o.order_id = {$orderId}"
    );

    if (!$order) {
        respondError('Order not found: ' . $orderId, 404);
    }

    // Order products
    $products = fetchAll(
        "SELECT op.*, p.sku, p.model"
        . " FROM " . t('order_product') . " op"
        . " LEFT JOIN " . t('product') . " p ON p.product_id = op.product_id"
        . " WHERE op.order_id = {$orderId}"
    );

    // Order totals
    $totals = fetchAll(
        "SELECT * FROM " . t('order_total') . " WHERE order_id = {$orderId} ORDER BY sort_order"
    );

    // Order history
    $history = fetchAll(
        "SELECT oh.*, os.name as status_name"
        . " FROM " . t('order_history') . " oh"
        . " LEFT JOIN " . t('order_status') . " os ON os.order_status_id = oh.order_status_id AND os.language_id = 1"
        . " WHERE oh.order_id = {$orderId}"
        . " ORDER BY oh.date_added"
    );

    // Simple checkout fields (if module installed)
    $simpleFields = array();
    $simpleTable = fetchRow("SHOW TABLES LIKE '" . t('simple_order') . "'");
    if ($simpleTable) {
        $simpleFields = fetchAll("SELECT * FROM " . t('simple_order') . " WHERE order_id = {$orderId}");
    }

    $order['products'] = $products;
    $order['totals'] = $totals;
    $order['history'] = $history;
    if (!empty($simpleFields)) {
        $order['simple_fields'] = $simpleFields;
    }

    respond(array('ok' => true, 'order' => $order));
}

// ===========================================================================
// CACHE HANDLERS
// ===========================================================================

/**
 * Clear OC caches
 * Body: {types: ["seo_pro", "product", "category", "all"]}
 */
function handleCacheClear($body) {
    $types = isset($body['types']) ? $body['types'] : array('all');
    $cleared = array();

    $cacheDir = defined('DIR_CACHE') ? DIR_CACHE : dirname(__FILE__) . '/system/storage/cache/';

    if (!is_dir($cacheDir)) {
        respondError('Cache directory not found: ' . $cacheDir, 500);
    }

    $patterns = array();
    foreach ($types as $type) {
        switch ($type) {
            case 'seo_pro':
                $patterns[] = 'cache.seo_pro.*';
                break;
            case 'product':
                $patterns[] = 'cache.product.*';
                break;
            case 'category':
                $patterns[] = 'cache.category.*';
                break;
            case 'all':
                $patterns[] = 'cache.*';
                break;
            default:
                $patterns[] = 'cache.' . $type . '.*';
        }
    }

    foreach ($patterns as $pattern) {
        $files = glob($cacheDir . $pattern);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $cleared[] = basename($file);
                }
            }
        }
    }

    respond(array('ok' => true, 'cleared_count' => count($cleared)));
}

// ===========================================================================
// SEO URL HELPERS
// ===========================================================================

/**
 * Save SEO URLs — handles both url_alias (OC 2.x) and seo_url (OC 3.x) tables
 * @param string $entity  'product_id' or 'category_id'
 * @param int    $id
 * @param array  $urls    [{keyword, language_id?, store_id?}]
 */
function _saveSeoUrls($entity, $id, $urls) {
    // Check which table exists
    $hasUrlAlias = !empty(fetchRow("SHOW TABLES LIKE '" . t('url_alias') . "'"));
    $hasSeoUrl   = !empty(fetchRow("SHOW TABLES LIKE '" . t('seo_url') . "'"));

    $queryVal = $entity . '=' . intval($id);

    foreach ($urls as $url) {
        $keyword = esc($url['keyword']);

        if ($hasUrlAlias) {
            // OC 2.x style: single url_alias row
            query("INSERT INTO " . t('url_alias') . " (`query`, keyword) VALUES (" . esc($queryVal) . ", {$keyword})");
        }

        if ($hasSeoUrl) {
            // OC 3.x style: per-language, per-store
            $langId = isset($url['language_id']) ? intval($url['language_id']) : 1;
            $storeId = isset($url['store_id']) ? intval($url['store_id']) : 0;
            query("INSERT INTO " . t('seo_url') . " (store_id, language_id, `query`, keyword)"
                . " VALUES ({$storeId}, {$langId}, " . esc($queryVal) . ", {$keyword})");
        }
    }
}

/**
 * Delete SEO URLs for entity
 */
function _deleteSeoUrls($entity, $id) {
    $queryVal = esc($entity . '=' . intval($id));

    $hasUrlAlias = !empty(fetchRow("SHOW TABLES LIKE '" . t('url_alias') . "'"));
    $hasSeoUrl   = !empty(fetchRow("SHOW TABLES LIKE '" . t('seo_url') . "'"));

    if ($hasUrlAlias) {
        query("DELETE FROM " . t('url_alias') . " WHERE `query` = {$queryVal}");
    }
    if ($hasSeoUrl) {
        query("DELETE FROM " . t('seo_url') . " WHERE `query` = {$queryVal}");
    }
}

// Close DB
$db->close();
