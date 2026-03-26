<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$id      = isset($_POST['attribute_id']) ? (int)$_POST['attribute_id'] : 0;
$groupId = isset($_POST['group_id'])     ? (int)$_POST['group_id']     : 0;
$nameUk  = isset($_POST['name_uk'])      ? trim($_POST['name_uk'])      : '';
$nameRu  = isset($_POST['name_ru'])      ? trim($_POST['name_ru'])      : '';
$status  = isset($_POST['status'])       ? (int)$_POST['status']        : 1;
$offId   = isset($_POST['off_attr_id'])  ? (int)$_POST['off_attr_id']   : 0;
$mffId   = isset($_POST['mff_attr_id'])  ? (int)$_POST['mff_attr_id']   : 0;

if (!$nameUk && !$nameRu) { echo json_encode(array('ok'=>false,'error'=>'Назва обов\'язкова')); exit; }

if ($id > 0) {
    // Update
    Database::update('Papir', 'product_attribute',
        array('group_id' => $groupId, 'status' => $status),
        array('attribute_id' => $id)
    );
} else {
    // Insert
    $r = Database::insert('Papir', 'product_attribute',
        array('group_id' => $groupId, 'sort_order' => 0, 'status' => $status)
    );
    if (!$r['ok']) { echo json_encode(array('ok'=>false,'error'=>'DB error')); exit; }
    $id = $r['insert_id'];
}

// Names
Database::upsertOne('Papir', 'product_attribute_description',
    array('attribute_id'=>$id,'language_id'=>2,'attribute_name'=>$nameUk),
    array('attribute_id','language_id')
);
Database::upsertOne('Papir', 'product_attribute_description',
    array('attribute_id'=>$id,'language_id'=>1,'attribute_name'=>$nameRu),
    array('attribute_id','language_id')
);

// Site mappings
foreach (array(1 => $offId, 2 => $mffId) as $siteId => $siteAttrId) {
    if ($siteAttrId > 0) {
        Database::query('Papir',
            "INSERT INTO attribute_site_mapping (attribute_id, site_id, site_attribute_id)
             VALUES ({$id}, {$siteId}, {$siteAttrId})
             ON DUPLICATE KEY UPDATE site_attribute_id = {$siteAttrId}"
        );
    } else {
        Database::query('Papir',
            "DELETE FROM attribute_site_mapping WHERE attribute_id = {$id} AND site_id = {$siteId}"
        );
    }
}

// Каскад: обновить название атрибута на сайтах
if ($nameUk || $nameRu) {
    $names = array();
    if ($nameUk) $names[2] = $nameUk;
    if ($nameRu) $names[1] = $nameRu;
    AttributeCascadeHelper::cascadeAttributeName($id, $names);
}

echo json_encode(array('ok' => true, 'attribute_id' => $id));
