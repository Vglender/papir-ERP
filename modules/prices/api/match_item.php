<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$action    = isset($_POST['action'])     ? $_POST['action']       : '';
$itemId    = isset($_POST['item_id'])    ? (int)$_POST['item_id'] : 0;
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($itemId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'item_id required'));
    exit;
}

$repo = new PricelistItemRepository();

if ($action === 'ignore') {
    $repo->setIgnored($itemId, true);
} elseif ($action === 'unignore') {
    $repo->setIgnored($itemId, false);
} elseif ($action === 'unmatch') {
    $repo->setMatch($itemId, null);
} elseif ($action === 'match') {
    if ($productId <= 0) {
        echo json_encode(array('ok' => false, 'error' => 'product_id required for match'));
        exit;
    }
    $repo->setMatch($itemId, $productId);
} else {
    echo json_encode(array('ok' => false, 'error' => 'Unknown action'));
    exit;
}

echo json_encode(array('ok' => true));
