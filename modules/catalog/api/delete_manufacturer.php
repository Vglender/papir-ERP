<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id = isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : 0;
if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'manufacturer_id required'));
    exit;
}

// Check if any products reference this manufacturer
$check = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt FROM product_papir WHERE manufacturer_id = {$id}"
);
if ($check['ok'] && (int)$check['row']['cnt'] > 0) {
    echo json_encode(array('ok' => false, 'error' => 'Неможливо видалити: є ' . (int)$check['row']['cnt'] . ' прив\'язаних товарів'));
    exit;
}

$r = Database::query('Papir', "DELETE FROM manufacturers WHERE manufacturer_id = {$id}");
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
    exit;
}

echo json_encode(array('ok' => true));
