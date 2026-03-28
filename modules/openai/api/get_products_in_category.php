<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$languageId = isset($_GET['language_id']) ? (int)$_GET['language_id'] : 2;

if ($categoryId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'category_id required'));
    exit;
}

// Get all site mappings for this category
$mappingsR = Database::fetchAll('Papir',
    "SELECT csm.site_id, csm.site_category_id, s.db_alias
     FROM category_site_mapping csm
     JOIN sites s ON s.site_id = csm.site_id AND s.status = 1
     WHERE csm.category_id = {$categoryId}"
);

$productIds = array();

if ($mappingsR['ok'] && !empty($mappingsR['rows'])) {
    foreach ($mappingsR['rows'] as $mapping) {
        $siteId         = (int)$mapping['site_id'];
        $siteCategoryId = (int)$mapping['site_category_id'];
        $dbAlias        = (string)$mapping['db_alias'];

        // Get OC product IDs in this OC category
        $ocProdsR = Database::fetchAll($dbAlias,
            "SELECT product_id FROM oc_product_to_category
             WHERE category_id = {$siteCategoryId}"
        );
        if (!$ocProdsR['ok'] || empty($ocProdsR['rows'])) {
            continue;
        }

        $siteProdIds = array();
        foreach ($ocProdsR['rows'] as $row) {
            $siteProdIds[] = (int)$row['product_id'];
        }
        $inClause = implode(',', $siteProdIds);

        // Map back to Papir product IDs
        $papirProdsR = Database::fetchAll('Papir',
            "SELECT product_id FROM product_site
             WHERE site_id = {$siteId} AND site_product_id IN ({$inClause})"
        );
        if ($papirProdsR['ok'] && !empty($papirProdsR['rows'])) {
            foreach ($papirProdsR['rows'] as $row) {
                $productIds[(int)$row['product_id']] = true;
            }
        }
    }
}

if (empty($productIds)) {
    echo json_encode(array('ok' => true, 'products' => array()));
    exit;
}

$ids    = implode(',', array_keys($productIds));
$langId = (int)$languageId;

$prodsR = Database::fetchAll('Papir',
    "SELECT pp.product_id, pp.product_article,
            COALESCE(NULLIF(pd.name,''), '') AS name
     FROM product_papir pp
     LEFT JOIN product_description pd
       ON pd.product_id = pp.product_id AND pd.language_id = {$langId}
     WHERE pp.product_id IN ({$ids})
     ORDER BY name ASC
     LIMIT 500"
);

$products = ($prodsR['ok'] && !empty($prodsR['rows'])) ? $prodsR['rows'] : array();

echo json_encode(array('ok' => true, 'products' => $products), JSON_UNESCAPED_UNICODE);
