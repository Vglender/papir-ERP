<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$attrId     = isset($_POST['attribute_id'])  ? (int)$_POST['attribute_id']  : 0;
$sourceText = isset($_POST['source_text'])   ? trim($_POST['source_text'])   : '';
$targetText = isset($_POST['target_text'])   ? trim($_POST['target_text'])   : '';
$langId     = isset($_POST['language_id'])   ? (int)$_POST['language_id']    : 0;

if ($attrId <= 0 || $sourceText === '' || $targetText === '') {
    echo json_encode(array('ok' => false, 'error' => 'attribute_id, source_text і target_text обов\'язкові'));
    exit;
}
if ($sourceText === $targetText) {
    echo json_encode(array('ok' => false, 'error' => 'Значення однакові'));
    exit;
}

$srcEsc  = Database::escape('Papir', $sourceText);
$tgtEsc  = Database::escape('Papir', $targetText);
$langSql = $langId > 0 ? " AND language_id = {$langId}" : '';

// Видалити дублі (де target вже є у того ж товару)
Database::query('Papir',
    "DELETE FROM product_attribute_value
     WHERE attribute_id = {$attrId}{$langSql} AND text = '{$srcEsc}'
       AND (product_id, attribute_id, language_id, site_id) IN (
           SELECT product_id, attribute_id, language_id, site_id FROM (
               SELECT product_id, attribute_id, language_id, site_id
               FROM product_attribute_value
               WHERE attribute_id = {$attrId}{$langSql} AND text = '{$tgtEsc}'
           ) AS has_target
       )"
);

// Перейменувати решту
$r = Database::query('Papir',
    "UPDATE product_attribute_value
     SET text = '{$tgtEsc}'
     WHERE attribute_id = {$attrId}{$langSql} AND text = '{$srcEsc}'"
);

echo json_encode(array('ok' => true, 'affected' => $r['affected_rows']));
