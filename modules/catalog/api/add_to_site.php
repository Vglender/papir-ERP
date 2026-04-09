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
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();

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
    $rUpd = $sync->productUpdate($siteId, $existingSiteProductId,
        array('status' => 1, 'model' => (string)$productId));
    if ($rUpd['ok']) {
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
$siteCategoryId = 0;

if ($categoryId > 0) {
    $siteCategoryId = $categoryId;
} elseif ($prod['categoria_id']) {
    $siteCategoryId = $sync->getSiteCategoryId((int)$prod['categoria_id'], $siteId);
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
$model          = (string)$productId;

// ── Load images from Papir ───────────────────────────────────────────────────
$rImgs = Database::fetchAll('Papir',
    "SELECT image_id, path, sort_order
     FROM product_image
     WHERE product_id = {$productId}
     ORDER BY sort_order ASC, image_id ASC"
);
$images    = ($rImgs['ok']) ? $rImgs['rows'] : array();
$mainImage = !empty($images) ? $images[0]['path'] : '';

// ── Build slug ───────────────────────────────────────────────────────────────
$rUkLang  = Database::fetchRow('Papir', "SELECT language_id FROM languages WHERE code='uk' LIMIT 1");
$ukLangId = ($rUkLang['ok'] && !empty($rUkLang['row'])) ? (int)$rUkLang['row']['language_id'] : 2;
$uaDesc   = isset($descsByLangId[$ukLangId]) ? $descsByLangId[$ukLangId] : null;
$slugBase = $uaDesc ? $uaDesc['name'] : $prod['name'];
$finalSlug = _makeSlug($slugBase) . '-' . $productId;

// ── Build data for SiteSyncService::productCreate ────────────────────────────
$ocData = array(
    'model'           => $model,
    'sku'             => isset($prod['product_article']) ? (string)$prod['product_article'] : '',
    'quantity'        => $quantity,
    'stock_status_id' => $stockStatusId,
    'price'           => $price,
    'manufacturer_id' => $manufacturerId,
    'status'          => 1,
    'image'           => $mainImage,
);
if ($hasUuid) {
    $ocData['uuid'] = _makeUuid();
}

// Descriptions per site language
$ocDescriptions = array();
foreach ($siteLangs as $papirLangId => $siteLangId) {
    $desc     = isset($descsByLangId[$papirLangId]) ? $descsByLangId[$papirLangId] : null;
    $name     = $desc ? $desc['name'] : $prod['name'];
    $descFull = $desc ? (string)$desc['description'] : '';
    $ocDescriptions[] = array(
        'language_id'  => $siteLangId,
        'name'         => $name,
        'description'  => $descFull,
    );
}

// Categories
$ocCategories = array(array('category_id' => $siteCategoryId, 'main_category' => 1));

// Extra images (skip main)
$ocImages = array();
for ($i = 1; $i < count($images); $i++) {
    $imgRow = array('image' => $images[$i]['path'], 'sort_order' => (int)$images[$i]['sort_order']);
    if ($hasUuid) $imgRow['uuid'] = _makeUuid();
    $ocImages[] = $imgRow;
}

// SEO URLs
$ocSeoUrls = array(array('keyword' => $finalSlug));

// ── Create via SiteSyncService ───────────────────────────────────────────────
$rCreate = $sync->productCreate($siteId, $ocData, $ocDescriptions, $ocCategories, $ocImages, $ocSeoUrls);
if (!$rCreate['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to create product on site'));
    exit;
}
$ocProductId = (int)$rCreate['product_id'];
$actionDone  = 'added';

// ── Papir-side: sync image assignments ───────────────────────────────────────
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

// Invalidate seo_pro cache
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
