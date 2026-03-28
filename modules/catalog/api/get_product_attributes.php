<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'product_id required'));
    exit;
}

// Active sites
$sitesR = Database::fetchAll('Papir', "SELECT site_id, name, code FROM sites WHERE status = 1 ORDER BY sort_order");
$sitesRaw = ($sitesR['ok'] && !empty($sitesR['rows'])) ? $sitesR['rows'] : array();

// Language order: UK (2) first, RU (1) second
$languageOrder = array(2 => 'UK', 1 => 'RU');

// All master attribute values for this product
$valuesR = Database::fetchAll('Papir',
    "SELECT pav.attribute_id, pav.language_id, pav.text,
            COALESCE(d_uk.attribute_name, d_ru.attribute_name) AS attribute_name
     FROM product_attribute_value pav
     LEFT JOIN product_attribute_description d_uk
           ON d_uk.attribute_id = pav.attribute_id AND d_uk.language_id = 2
     LEFT JOIN product_attribute_description d_ru
           ON d_ru.attribute_id = pav.attribute_id AND d_ru.language_id = 1
     WHERE pav.product_id = {$productId} AND pav.site_id = 0
     ORDER BY attribute_name, pav.attribute_id, pav.language_id");
$values = ($valuesR['ok'] && !empty($valuesR['rows'])) ? $valuesR['rows'] : array();

// Index: [attribute_id][language_id] => text
$valIdx   = array();
$nameIdx  = array();
foreach ($values as $v) {
    $aid = (int)$v['attribute_id'];
    $lid = (int)$v['language_id'];
    $valIdx[$aid][$lid] = $v['text'];
    if (!isset($nameIdx[$aid])) {
        $nameIdx[$aid] = $v['attribute_name'];
    }
}

$result = array();
foreach ($sitesRaw as $site) {
    $siteId = (int)$site['site_id'];

    // Attributes mapped to this site (sorted by name)
    $mappedR = Database::fetchAll('Papir',
        "SELECT asm.attribute_id,
                COALESCE(d_uk.attribute_name, d_ru.attribute_name) AS attribute_name
         FROM attribute_site_mapping asm
         LEFT JOIN product_attribute_description d_uk
               ON d_uk.attribute_id = asm.attribute_id AND d_uk.language_id = 2
         LEFT JOIN product_attribute_description d_ru
               ON d_ru.attribute_id = asm.attribute_id AND d_ru.language_id = 1
         WHERE asm.site_id = {$siteId}
         ORDER BY attribute_name");
    $mapped = ($mappedR['ok'] && !empty($mappedR['rows'])) ? $mappedR['rows'] : array();

    $mappedIds      = array();
    $availableAttrs = array();
    foreach ($mapped as $m) {
        $mid = (int)$m['attribute_id'];
        $mappedIds[$mid] = $m['attribute_name'];
        $availableAttrs[] = array(
            'attribute_id'   => $mid,
            'attribute_name' => $m['attribute_name'],
        );
    }

    // Build per-language list
    $languages = array();
    foreach ($languageOrder as $langId => $langLabel) {
        $langValues = array();
        foreach ($mappedIds as $attrId => $attrName) {
            if (isset($valIdx[$attrId][$langId])) {
                $langValues[] = array(
                    'attribute_id'   => $attrId,
                    'attribute_name' => $attrName,
                    'text'           => $valIdx[$attrId][$langId],
                );
            }
        }
        usort($langValues, function($a, $b) {
            return strcmp($a['attribute_name'], $b['attribute_name']);
        });
        $languages[] = array(
            'language_id' => $langId,
            'label'       => $langLabel,
            'values'      => $langValues,
        );
    }

    $result[] = array(
        'site_id'         => $siteId,
        'name'            => $site['name'],
        'code'            => $site['code'],
        'available_attrs' => $availableAttrs,
        'languages'       => $languages,
    );
}

echo json_encode(array(
    'ok'    => true,
    'sites' => $result,
), JSON_UNESCAPED_UNICODE);
