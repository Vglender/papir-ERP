<?php

require_once '/var/sqript/products/confif_bp.php';
require_once '/var/sqript/products/lib/Request.php';
require_once '/var/sqript/products/lib/CatalogRepository.php';

header('Content-Type: application/json; charset=utf-8');

$mysqli_ms = connectbd('ms');
$mysqli_papir = connectbd('Papir');
$mysqli_off = connectbd('off');

$catalogRepo = new CatalogRepository($mysqli_ms, $mysqli_papir, $mysqli_off);

$productIdsRaw = '';

if (isset($_POST['product_ids'])) {
    $productIdsRaw = (string)$_POST['product_ids'];
} elseif (isset($_GET['product_ids'])) {
    $productIdsRaw = (string)$_GET['product_ids'];
}

$productIds = array();

if ($productIdsRaw !== '') {
    foreach (explode(',', $productIdsRaw) as $id) {
        $id = (int)trim($id);
        if ($id > 0) {
            $productIds[] = $id;
        }
    }
}

$items = $catalogRepo->getPriceListProducts($productIds);

echo json_encode(array(
    'items' => $items,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$mysqli_ms->close();
$mysqli_papir->close();
$mysqli_off->close();