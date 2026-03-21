<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$pricelistId   = isset($_POST['pricelist_id'])   ? (int)$_POST['pricelist_id'] : 0;
$spreadsheetId = isset($_POST['spreadsheet_id']) ? trim($_POST['spreadsheet_id']) : '';
$sheetName     = isset($_POST['sheet_name'])     ? trim($_POST['sheet_name'])     : '';
$headerRow     = isset($_POST['header_row'])     ? max(0, (int)$_POST['header_row']) : 1;
$colSku        = isset($_POST['col_sku'])        ? strtoupper(trim($_POST['col_sku']))        : '';
$colModel      = isset($_POST['col_model'])      ? strtoupper(trim($_POST['col_model']))      : '';
$colName       = isset($_POST['col_name'])       ? strtoupper(trim($_POST['col_name']))       : '';
$colPriceCost  = isset($_POST['col_price_cost']) ? strtoupper(trim($_POST['col_price_cost'])) : '';
$colPriceRrp   = isset($_POST['col_price_rrp'])  ? strtoupper(trim($_POST['col_price_rrp']))  : '';

if ($pricelistId <= 0 || $spreadsheetId === '') {
    echo json_encode(array('ok' => false, 'error' => 'pricelist_id and spreadsheet_id required'));
    exit;
}

$pricelistRepo = new PricelistRepository();
$pricelist     = $pricelistRepo->getById($pricelistId);

if (!$pricelist || $pricelist['source_type'] !== 'google_sheets') {
    echo json_encode(array('ok' => false, 'error' => 'Pricelist not found or wrong type'));
    exit;
}

$config = array(
    'spreadsheet_id' => $spreadsheetId,
    'sheet_name'     => $sheetName,
    'header_row'     => $headerRow,
    'col_sku'        => $colSku,
    'col_model'      => $colModel,
    'col_name'       => $colName,
    'col_price_cost' => $colPriceCost,
    'col_price_rrp'  => $colPriceRrp,
);

$pricelistRepo->update($pricelistId, array('source_config' => json_encode($config)));

echo json_encode(array('ok' => true));
