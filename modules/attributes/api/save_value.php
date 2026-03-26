<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$attrId  = isset($_POST['attribute_id']) ? (int)$_POST['attribute_id']     : 0;
$oldText = isset($_POST['old_text'])     ? $_POST['old_text']               : '';
$newText = isset($_POST['new_text'])     ? trim($_POST['new_text'])         : '';
$langId  = isset($_POST['language_id'])  ? (int)$_POST['language_id']       : 0;

if ($attrId <= 0 || $oldText === '') {
    echo json_encode(array('ok' => false, 'error' => 'attribute_id і old_text обов\'язкові'));
    exit;
}
if ($newText === '') {
    echo json_encode(array('ok' => false, 'error' => 'Нове значення не може бути порожнім'));
    exit;
}
if ($oldText === $newText) {
    echo json_encode(array('ok' => true, 'affected' => 0));
    exit;
}

$oldEsc = Database::escape('Papir', $oldText);
$newEsc = Database::escape('Papir', $newText);
$langSql = $langId > 0 ? " AND language_id = {$langId}" : '';

// Если новое значение уже существует у некоторых товаров — нужно объединить
// (DELETE старые где новое уже есть, потом UPDATE остальные)
$rDel = Database::query('Papir',
    "DELETE FROM product_attribute_value
     WHERE attribute_id = {$attrId}{$langSql}
       AND text = '{$oldEsc}'
       AND (product_id, attribute_id, language_id, site_id) IN (
           SELECT product_id, attribute_id, language_id, site_id
           FROM (
               SELECT product_id, attribute_id, language_id, site_id
               FROM product_attribute_value
               WHERE attribute_id = {$attrId}{$langSql} AND text = '{$newEsc}'
           ) AS already_has
       )"
);

$rUpd = Database::query('Papir',
    "UPDATE product_attribute_value
     SET text = '{$newEsc}'
     WHERE attribute_id = {$attrId}{$langSql} AND text = '{$oldEsc}'"
);

$totalAffected = ($rDel['ok'] ? $rDel['affected_rows'] : 0)
               + ($rUpd['ok'] ? $rUpd['affected_rows'] : 0);

// Каскад на сайты
AttributeCascadeHelper::cascadeRenameValue($attrId, $oldText, $newText, $langId);

echo json_encode(array('ok' => true, 'affected' => $totalAffected));
