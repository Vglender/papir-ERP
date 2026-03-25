<?php
/**
 * Add a product to a site (or activate if already linked).
 *
 * Flow:
 *  1. If product_site entry exists with site_product_id → activate oc_product + product_site.
 *  2. If site OC product not found → full cascade INSERT:
 *     oc_product, oc_product_description, oc_product_to_store, oc_product_to_category,
 *     oc_product_image, oc_url_alias, product_site, product_image_site.
 *  3. If category can't be auto-resolved → return {ok:false, error:'category_required'}.
 *
 * POST: product_id (int), site_id (int), [category_id (int, optional)]
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$productId  = Request::postInt('product_id', 0);
$siteId     = Request::postInt('site_id', 0);
$categoryId = Request::postInt('category_id', 0);

if ($productId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid product_id or site_id'));
    exit;
}

// ── Load product ──────────────────────────────────────────────────────────────
$rProd = Database::fetchRow('Papir',
    "SELECT pp.product_id, pp.product_article, pp.status,
            pp.price_sale, pp.quantity, pp.categoria_id,
            COALESCE(m.off_id, 0) AS manufacturer_off_id,
            COALESCE(m.mff_id, 0) AS manufacturer_mff_id,
            COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), '') AS name
     FROM product_papir pp
     LEFT JOIN manufacturers m ON m.manufacturer_id = pp.manufacturer_id
     LEFT JOIN product_description pd2 ON pd2.product_id = pp.product_id AND pd2.language_id = 2
     LEFT JOIN product_description pd1 ON pd1.product_id = pp.product_id AND pd1.language_id = 1
     WHERE pp.product_id = {$productId}"
);
if (!$rProd['ok'] || empty($rProd['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Product not found'));
    exit;
}
$prod = $rProd['row'];

if ((int)$prod['status'] === 0) {
    echo json_encode(array('ok' => false, 'error' => 'BK product is inactive'));
    exit;
}

// ── Load site ─────────────────────────────────────────────────────────────────
$rSite = Database::fetchRow('Papir',
    "SELECT site_id, code, db_alias, badge FROM sites WHERE site_id = {$siteId} AND status = 1"
);
if (!$rSite['ok'] || empty($rSite['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Site not found'));
    exit;
}
$site     = $rSite['row'];
$db       = $site['db_alias'];
$siteCode = $site['code'];
$hasUuid  = ($siteCode === 'off');

// ── Check existing product_site ───────────────────────────────────────────────
$rPs = Database::fetchRow('Papir',
    "SELECT site_product_id, status
     FROM product_site
     WHERE product_id = {$productId} AND site_id = {$siteId}"
);
$existingPs            = ($rPs['ok'] && !empty($rPs['row'])) ? $rPs['row'] : null;
$existingSiteProductId = $existingPs ? (int)$existingPs['site_product_id'] : 0;

// If mapped → verify OC product still exists, then activate
if ($existingSiteProductId > 0) {
    $rOcCheck = Database::fetchRow($db,
        "SELECT product_id FROM oc_product WHERE product_id = {$existingSiteProductId}"
    );
    if ($rOcCheck['ok'] && !empty($rOcCheck['row'])) {
        $modelEsc = Database::escape($db, (string)$productId);
        Database::query($db,
            "UPDATE oc_product SET status = 1, model = '{$modelEsc}'
             WHERE product_id = {$existingSiteProductId}"
        );
        Database::query('Papir',
            "UPDATE product_site SET status = 1
             WHERE product_id = {$productId} AND site_id = {$siteId}"
        );
        echo json_encode(array(
            'ok'              => true,
            'action'          => 'activated',
            'site_product_id' => $existingSiteProductId,
        ));
        exit;
    }
    // OC product gone — fall through to re-create
}

// ── Resolve category ──────────────────────────────────────────────────────────
$catFieldMap = array(1 => 'category_off', 2 => 'category_mf');
$catField    = isset($catFieldMap[$siteId]) ? $catFieldMap[$siteId] : null;
$siteCategoryId = 0;

if ($categoryId > 0) {
    $siteCategoryId = $categoryId;
} elseif ($catField && $prod['categoria_id']) {
    $catId = (int)$prod['categoria_id'];
    $rCat  = Database::fetchRow('Papir',
        "SELECT {$catField} FROM categoria WHERE category_id = {$catId}"
    );
    if ($rCat['ok'] && !empty($rCat['row'])) {
        $siteCategoryId = (int)$rCat['row'][$catField];
    }
}

if ($siteCategoryId <= 0) {
    // Return category list for the picker modal
    $rCats = Database::fetchAll($db,
        "SELECT c.category_id, cd.name, c.parent_id
         FROM oc_category c
         JOIN oc_category_description cd ON cd.category_id = c.category_id AND cd.language_id = 1
         WHERE c.status = 1
         ORDER BY cd.name"
    );
    $siteCats = ($rCats['ok']) ? $rCats['rows'] : array();
    echo json_encode(array(
        'ok'              => false,
        'error'           => 'category_required',
        'site_categories' => $siteCats,
    ));
    exit;
}

// ── Load product descriptions ─────────────────────────────────────────────────
$rDescs = Database::fetchAll('Papir',
    "SELECT language_id, name, description
     FROM product_description
     WHERE product_id = {$productId}"
);
$descsByLangId = array();
if ($rDescs['ok']) {
    foreach ($rDescs['rows'] as $d) {
        $descsByLangId[(int)$d['language_id']] = $d;
    }
}

// ── Load site_languages mapping ───────────────────────────────────────────────
$rLangs = Database::fetchAll('Papir',
    "SELECT language_id, site_lang_id
     FROM site_languages
     WHERE site_id = {$siteId}"
);
$siteLangs = array(); // Papir language_id => OC language_id on that site
if ($rLangs['ok']) {
    foreach ($rLangs['rows'] as $l) {
        $siteLangs[(int)$l['language_id']] = (int)$l['site_lang_id'];
    }
}

// ── Determine values ──────────────────────────────────────────────────────────
$manufacturerId = ($siteCode === 'off') ? (int)$prod['manufacturer_off_id'] : (int)$prod['manufacturer_mff_id'];
$price          = (float)$prod['price_sale'];
$quantity       = (int)$prod['quantity'];
$stockStatusId  = ($quantity > 0) ? 7 : 5;
$now            = date('Y-m-d H:i:s');
$today          = date('Y-m-d');
$model          = (string)$productId;

// ── Create OC product ─────────────────────────────────────────────────────────
// The only valid source for an existing OC product is product_site (checked above).
// If no product_site entry was found, always INSERT a fresh OC product.
// Never use id_off/id_mf from product_papir as a lookup key.
$ocData = array(
    'model'           => $model,
    'sku'             => isset($prod['product_article']) ? (string)$prod['product_article'] : '',
    'upc'             => '',
    'ean'             => '',
    'jan'             => '',
    'isbn'            => '',
    'mpn'             => '',
    'location'        => '',
    'quantity'        => $quantity,
    'stock_status_id' => $stockStatusId,
    'price'           => $price,
    'manufacturer_id' => $manufacturerId,
    'shipping'        => 1,
    'options_buy'     => 0,
    'points'          => 0,
    'tax_class_id'    => 0,
    'date_available'  => $today,
    'weight'          => 0,
    'weight_class_id' => 1,
    'length'          => 0,
    'width'           => 0,
    'height'          => 0,
    'length_class_id' => 1,
    'subtract'        => 1,
    'minimum'         => 1,
    'sort_order'      => 0,
    'status'          => 1,
    'viewed'          => 0,
    'noindex'         => 0,
    'date_added'      => $now,
    'date_modified'   => $now,
    'unit'            => '',
    'cogs'            => 0,
    'min_price'       => 0,
);
if ($hasUuid) {
    $ocData['uuid'] = _makeUuid();
}
$rIns = Database::insert($db, 'oc_product', $ocData);
if (!$rIns['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to insert oc_product'));
    exit;
}
$ocProductId = (int)$rIns['insert_id'];
$actionDone  = 'added';

// ── oc_product_to_store ───────────────────────────────────────────────────────
Database::query($db,
    "INSERT IGNORE INTO oc_product_to_store (product_id, store_id) VALUES ({$ocProductId}, 0)"
);

// ── oc_product_to_category ────────────────────────────────────────────────────
Database::query($db,
    "DELETE FROM oc_product_to_category WHERE product_id = {$ocProductId}"
);
Database::query($db,
    "INSERT INTO oc_product_to_category (product_id, category_id, main_category)
     VALUES ({$ocProductId}, {$siteCategoryId}, 1)"
);

// ── oc_product_description ────────────────────────────────────────────────────
foreach ($siteLangs as $papirLangId => $siteLangId) {
    $desc     = isset($descsByLangId[$papirLangId]) ? $descsByLangId[$papirLangId] : null;
    $name     = $desc ? $desc['name'] : $prod['name'];
    $descFull = $desc ? (string)$desc['description'] : '';
    $nameEsc  = Database::escape($db, $name);
    $descEsc  = Database::escape($db, $descFull);
    Database::query($db,
        "INSERT INTO oc_product_description
         (product_id, language_id, name, description, short_description, description_mini,
          tag, meta_title, meta_description, meta_keyword, meta_h1, image_description)
         VALUES ({$ocProductId}, {$siteLangId}, '{$nameEsc}', '{$descEsc}', '', '', '', '', '', '', '', '')
         ON DUPLICATE KEY UPDATE name = '{$nameEsc}', description = '{$descEsc}'"
    );
}

// ── Images ────────────────────────────────────────────────────────────────────
$rImgs = Database::fetchAll('Papir',
    "SELECT image_id, path, sort_order
     FROM product_image
     WHERE product_id = {$productId}
     ORDER BY sort_order ASC, image_id ASC"
);
$images = ($rImgs['ok']) ? $rImgs['rows'] : array();

// Set main image on oc_product
$mainImage = !empty($images) ? $images[0]['path'] : '';
if ($mainImage) {
    $mainImgEsc = Database::escape($db, $mainImage);
    Database::query($db,
        "UPDATE oc_product SET image = '{$mainImgEsc}' WHERE product_id = {$ocProductId}"
    );
}

// Rebuild oc_product_image (extra images, skip first)
Database::query($db,
    "DELETE FROM oc_product_image WHERE product_id = {$ocProductId}"
);
for ($i = 1; $i < count($images); $i++) {
    $img     = $images[$i];
    $imgData = array(
        'product_id'        => $ocProductId,
        'image'             => $img['path'],
        'sort_order'        => (int)$img['sort_order'],
        'video'             => '',
        'image_description' => '',
    );
    if ($hasUuid) {
        $imgData['uuid'] = _makeUuid();
    }
    Database::insert($db, 'oc_product_image', $imgData);
}

// Sync product_image_site
foreach ($images as $img) {
    $imgId = (int)$img['image_id'];
    Database::query('Papir',
        "INSERT IGNORE INTO product_image_site (image_id, site_id, sort_order)
         VALUES ({$imgId}, {$siteId}, " . (int)$img['sort_order'] . ")"
    );
}

// FTP sync for mff images
if ($siteCode === 'mff' && !empty($images)) {
    $ftp = new MffFtpSync();
    foreach ($images as $img) {
        $ftp->upload($img['path']);
    }
}

// ── URL alias ─────────────────────────────────────────────────────────────────
// Build slug from Ukrainian name — look up language_id from Papir.languages by code='uk'
$rUkLang  = Database::fetchRow('Papir', "SELECT language_id FROM languages WHERE code='uk' LIMIT 1");
$ukLangId = ($rUkLang['ok'] && !empty($rUkLang['row'])) ? (int)$rUkLang['row']['language_id'] : 2;
$uaDesc   = isset($descsByLangId[$ukLangId]) ? $descsByLangId[$ukLangId] : null;
$slugBase = $uaDesc ? $uaDesc['name'] : $prod['name'];
$slug     = _makeSlug($slugBase) . '-' . $productId;
$slugQuery = 'product_id=' . $ocProductId;

// Ensure uniqueness
$finalSlug = $slug;
$suffix    = 0;
while (true) {
    $checkSlug = Database::escape($db, $finalSlug);
    $rAlias    = Database::fetchRow($db,
        "SELECT url_alias_id FROM oc_url_alias WHERE keyword = '{$checkSlug}'"
    );
    if (!$rAlias['ok'] || empty($rAlias['row'])) {
        break;
    }
    $suffix++;
    $finalSlug = $slug . '-' . $suffix;
}

$slugQueryEsc = Database::escape($db, $slugQuery);
Database::query($db,
    "DELETE FROM oc_url_alias WHERE query = '{$slugQueryEsc}'"
);
$finalSlugEsc = Database::escape($db, $finalSlug);
Database::query($db,
    "INSERT INTO oc_url_alias (query, keyword) VALUES ('{$slugQueryEsc}', '{$finalSlugEsc}')"
);

// Invalidate seo_pro cache so OpenCart rebuilds it from DB on next request.
// Without this the new URL alias won't be visible until the cache file expires (~24h).
_invalidateSeoPro($siteCode);

// ── product_seo (populate from OC + product_description if missing) ───────────
// Papir language_id→OC language_id mapping (via site_languages)
// We read OC content back and store in product_seo for the CRM catalog Контент tab
foreach ($siteLangs as $papirLangId => $siteLangId) {
    $rOcDesc = Database::fetchRow($db,
        "SELECT name, description, short_description, meta_title, meta_description, meta_keyword, meta_h1, tag
         FROM oc_product_description
         WHERE product_id = {$ocProductId} AND language_id = {$siteLangId}"
    );
    $ocDesc = ($rOcDesc['ok'] && !empty($rOcDesc['row'])) ? $rOcDesc['row'] : array();

    $rAlias2 = Database::fetchRow($db,
        "SELECT keyword FROM oc_url_alias WHERE query = 'product_id={$ocProductId}'"
    );
    $seoUrl = ($rAlias2['ok'] && !empty($rAlias2['row'])) ? $rAlias2['row']['keyword'] : '';

    $seoName  = isset($ocDesc['name'])              ? $ocDesc['name']              : '';
    $seoDesc  = isset($ocDesc['description'])       ? $ocDesc['description']       : '';
    $seoShort = isset($ocDesc['short_description']) ? $ocDesc['short_description'] : '';
    $seoH1    = isset($ocDesc['meta_h1'])           ? $ocDesc['meta_h1']           : '';
    $seoTitle = isset($ocDesc['meta_title'])        ? $ocDesc['meta_title']        : '';
    $seoMetaD = isset($ocDesc['meta_description'])  ? $ocDesc['meta_description']  : '';
    $seoKw    = isset($ocDesc['meta_keyword'])      ? $ocDesc['meta_keyword']      : '';
    $seoTag   = isset($ocDesc['tag'])               ? $ocDesc['tag']               : '';

    // Fallback to product_description if OC had no content
    if ($seoName === '' && isset($descsByLangId[$papirLangId])) {
        $seoName  = (string)$descsByLangId[$papirLangId]['name'];
        $seoDesc  = (string)$descsByLangId[$papirLangId]['description'];
    }

    $nameEsc  = Database::escape('Papir', $seoName);
    $descEsc  = Database::escape('Papir', $seoDesc);
    $shortEsc = Database::escape('Papir', $seoShort);
    $h1Esc    = Database::escape('Papir', $seoH1);
    $titleEsc = Database::escape('Papir', $seoTitle);
    $metaDEsc = Database::escape('Papir', $seoMetaD);
    $kwEsc    = Database::escape('Papir', $seoKw);
    $tagEsc   = Database::escape('Papir', $seoTag);
    $urlEsc   = Database::escape('Papir', $seoUrl);

    Database::query('Papir',
        "INSERT INTO product_seo
         (product_id, site_id, language_id, name, description, short_description,
          seo_url, seo_h1, meta_title, meta_description, meta_keyword, tag)
         VALUES ({$productId}, {$siteId}, {$papirLangId},
                 '{$nameEsc}', '{$descEsc}', '{$shortEsc}',
                 '{$urlEsc}', '{$h1Esc}', '{$titleEsc}', '{$metaDEsc}', '{$kwEsc}', '{$tagEsc}')
         ON DUPLICATE KEY UPDATE
             name = IF('{$nameEsc}' != '', '{$nameEsc}', name),
             description = IF('{$descEsc}' != '', '{$descEsc}', description),
             short_description = IF('{$shortEsc}' != '', '{$shortEsc}', short_description),
             seo_url = IF('{$urlEsc}' != '', '{$urlEsc}', seo_url)"
    );
}

// ── product_site ──────────────────────────────────────────────────────────────
Database::query('Papir',
    "INSERT INTO product_site (product_id, site_id, site_product_id, status)
     VALUES ({$productId}, {$siteId}, {$ocProductId}, 1)
     ON DUPLICATE KEY UPDATE site_product_id = {$ocProductId}, status = 1"
);

echo json_encode(array(
    'ok'              => true,
    'action'          => $actionDone,
    'site_product_id' => $ocProductId,
    'slug'            => $finalSlug,
));

// ── Helpers ───────────────────────────────────────────────────────────────────

// Delete seo_pro cache files so OpenCart rebuilds them from oc_url_alias on next request.
// The seo_pro cache maps keyword→query for ALL url aliases — it's loaded once per request
// and not updated when new aliases are inserted. Without invalidation the new SEO URL
// returns 404 until the cache file TTL expires (~24h).
function _invalidateSeoPro($siteCode)
{
    $cachePaths = array(
        'off' => '/var/www/menufold/data/www/officetorg.com.ua/system/storage/cache/',
    );
    if (!isset($cachePaths[$siteCode])) {
        return;
    }
    $dir = $cachePaths[$siteCode];
    foreach (glob($dir . 'cache.seo_pro.*') as $file) {
        @unlink($file);
    }
    // Also clear product.seopath cache so new product gets its category path cached fresh
    foreach (glob($dir . 'cache.product.seopath.*') as $file) {
        @unlink($file);
    }
}

function _makeUuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function _makeSlug($str)
{
    $str = mb_strtolower($str, 'UTF-8');
    $map = array(
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh',
        'з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
        'х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'yu','я'=>'ya',
        'ё'=>'yo','э'=>'e','ъ'=>'','ы'=>'y','э'=>'e',
    );
    $str = strtr($str, $map);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    $str = trim($str, '-');
    if (strlen($str) > 80) {
        $str = substr($str, 0, 80);
        $str = rtrim($str, '-');
    }
    return $str;
}
