<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$name         = isset($_POST['name'])          ? trim($_POST['name'])          : '';
$sourceType   = isset($_POST['source_type'])   ? trim($_POST['source_type'])   : 'moy_sklad';
$isCostSource = isset($_POST['is_cost_source']) ? (int)$_POST['is_cost_source'] : 0;

$allowed = array('moy_sklad', 'google_sheets', 'excel', 'xml', 'parser', 'api');

if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Название не может быть пустым'));
    exit;
}
if (!in_array($sourceType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Неверный тип источника'));
    exit;
}

// Генерируем code из имени
$code = strtolower(trim($name));
$code = preg_replace('/[^a-z0-9]+/', '_', $code);
$code = trim($code, '_');
if ($code === '') {
    $code = 'supplier_' . time();
}

// Порядок сортировки
$sortResult = Database::fetchRow('Papir', "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM `price_suppliers`");
$sortOrder  = ($sortResult['ok'] && !empty($sortResult['row'])) ? (int)$sortResult['row']['next_sort'] : 10;

$repo   = new SupplierRepository();
$result = $repo->create(array(
    'code'          => $code,
    'name'          => $name,
    'source_type'   => $sourceType,
    'is_active'     => 1,
    'is_cost_source' => $isCostSource ? 1 : 0,
    'sort_order'    => $sortOrder,
));

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'DB error'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => (int)$result['id']));
