<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$rawIds = isset($_POST['product_ids']) ? trim($_POST['product_ids']) : '';
if ($rawIds === '') {
    echo json_encode(array('ok' => false, 'error' => 'product_ids required'));
    exit;
}

$productIds = array_filter(array_map('intval', explode(',', $rawIds)));
if (empty($productIds)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid product_ids'));
    exit;
}

// Build the data array — only include fields that are non-empty strings
$data = array();

$saleMarkup = isset($_POST['sale_markup_percent']) ? trim($_POST['sale_markup_percent']) : '';
if ($saleMarkup !== '') {
    $data['sale_markup_percent'] = (float)$saleMarkup;
}

$wholesaleMarkup = isset($_POST['wholesale_markup_percent']) ? trim($_POST['wholesale_markup_percent']) : '';
if ($wholesaleMarkup !== '') {
    $data['wholesale_markup_percent'] = (float)$wholesaleMarkup;
}

$dealerMarkup = isset($_POST['dealer_markup_percent']) ? trim($_POST['dealer_markup_percent']) : '';
if ($dealerMarkup !== '') {
    $data['dealer_markup_percent'] = (float)$dealerMarkup;
}

$discountStrategyId = isset($_POST['discount_strategy_id']) ? trim($_POST['discount_strategy_id']) : '';
if ($discountStrategyId !== '') {
    $data['discount_strategy_id'] = (int)$discountStrategyId;
}

$discountStrategyManual = isset($_POST['discount_strategy_manual']) ? trim($_POST['discount_strategy_manual']) : '';
if ($discountStrategyManual !== '') {
    $data['discount_strategy_manual'] = (int)$discountStrategyManual ? 1 : 0;
}

if (empty($data)) {
    echo json_encode(array('ok' => false, 'error' => 'No fields to update'));
    exit;
}

$updated = 0;
$errors  = array();

foreach ($productIds as $productId) {
    $result = Database::update('Papir', 'product_papir', $data, array('product_id' => $productId));
    if ($result['ok']) {
        $updated++;
    } else {
        $errMsg = isset($result['error']) ? $result['error'] : 'Update failed';
        $errors[] = 'product_id=' . $productId . ': ' . $errMsg;
    }
}

echo json_encode(array(
    'ok'      => empty($errors),
    'updated' => $updated,
    'errors'  => $errors,
));
