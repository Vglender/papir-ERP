<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id        = isset($_POST['id'])         ? (int)$_POST['id']       : 0;
$orgId     = isset($_POST['org_id'])     ? (int)$_POST['org_id']   : 0;
$name      = isset($_POST['name'])       ? trim($_POST['name'])     : '';
$itemsJson = isset($_POST['items_json']) ? trim($_POST['items_json']) : '';
$isDefault = isset($_POST['is_default']) ? (int)$_POST['is_default'] : 0;

if (empty($name)) {
    echo json_encode(array('ok' => false, 'error' => 'name required'));
    exit;
}

// Validate JSON
$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    echo json_encode(array('ok' => false, 'error' => 'items_json must be valid JSON array'));
    exit;
}

$fields = array(
    'org_id'     => $orgId > 0 ? $orgId : null,
    'name'       => $name,
    'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE),
    'is_default' => $isDefault ? 1 : 0,
);

if ($id > 0) {
    $r = Database::update('Papir', 'print_pack_profiles', $fields, array('id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Update failed'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => $id));
} else {
    $r = Database::insert('Papir', 'print_pack_profiles', $fields);
    if (!$r['ok'] || empty($r['insert_id'])) {
        echo json_encode(array('ok' => false, 'error' => 'Insert failed'));
        exit;
    }
    echo json_encode(array('ok' => true, 'id' => (int)$r['insert_id']));
}
