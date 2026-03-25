<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

$attrId = isset($_GET['attribute_id']) ? (int)$_GET['attribute_id'] : 0;
$search = isset($_GET['search'])       ? trim($_GET['search'])       : '';
$langId = isset($_GET['language_id'])  ? (int)$_GET['language_id']  : 2; // UK default
$offset = isset($_GET['offset'])       ? (int)$_GET['offset']        : 0;
$limit  = 60;

if ($attrId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'attribute_id required'));
    exit;
}

$where = "WHERE attribute_id = {$attrId} AND language_id = {$langId} AND TRIM(text) != ''";
if ($search !== '') {
    $s = Database::escape('Papir', mb_strtolower($search, 'UTF-8'));
    $where .= " AND LOWER(text) LIKE '%{$s}%'";
}

// Уникальные значения с количеством товаров
$r = Database::fetchAll('Papir',
    "SELECT text, COUNT(DISTINCT product_id) AS cnt
     FROM product_attribute_value
     {$where}
     GROUP BY text
     ORDER BY cnt DESC, text
     LIMIT {$limit} OFFSET {$offset}"
);

$total = Database::fetchRow('Papir',
    "SELECT COUNT(DISTINCT text) AS cnt
     FROM product_attribute_value
     {$where}"
);

echo json_encode(array(
    'ok'    => true,
    'rows'  => $r['ok'] ? $r['rows'] : array(),
    'total' => ($total['ok'] && !empty($total['row'])) ? (int)$total['row']['cnt'] : 0,
    'offset'=> $offset,
    'limit' => $limit,
));
