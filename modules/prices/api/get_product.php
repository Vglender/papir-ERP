<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!$productId) {
    echo json_encode(['ok' => false, 'error' => 'product_id required']);
    exit;
}

$productRepo = new ProductPriceRepository();
$packageRepo = new ProductPackageRepository();
$profileRepo = new ProductDiscountProfileRepository();

$product  = $productRepo->getById($productId);
$packages = $packageRepo->getByProductId($productId);
$profile  = $profileRepo->getByProductId($productId);

echo json_encode([
    'ok'       => !empty($product),
    'product'  => $product,
    'packages' => $packages,
    'profile'  => $profile,
]);
