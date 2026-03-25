<?php

require_once __DIR__ . '/prices_bootstrap.php';

$pricelistRepo = new PricelistRepository();
$itemRepo      = new PricelistItemRepository();
$supplierRepo  = new SupplierRepository();

$basePath = '/prices/suppliers';

// ── Параметры ────────────────────────────────────────────────────────────────
$pricelistId  = Request::getInt('pricelist_id', 0);
$matchFilter  = Request::getString('match_filter', 'all');
$stockFilter  = Request::getString('stock_filter', 'all');
$rrpFilter    = Request::getString('rrp_filter', 'all');
$search       = Request::getString('search', '');
$page         = max(1, Request::getInt('page', 1));
$perPage      = 50;
$showAll      = Request::getInt('show_all', 1);

$allowedFilters      = array('all', 'matched', 'unmatched', 'ignored');
$allowedStockFilters = array('all', 'has_stock', 'no_stock');
$allowedRrpFilters   = array('all', 'has_rrp', 'no_rrp');
if (!in_array($matchFilter, $allowedFilters))      $matchFilter = 'all';
if (!in_array($stockFilter, $allowedStockFilters)) $stockFilter = 'all';
if (!in_array($rrpFilter,   $allowedRrpFilters))   $rrpFilter   = 'all';

$suppliers = $pricelistRepo->getAllGroupedBySupplier();

$activePlIds = array();
foreach ($suppliers as $sup) {
    foreach ($sup['pricelists'] as $pl) {
        if (!empty($pl['is_active'])) $activePlIds[] = (int)$pl['id'];
    }
}

// Если выбран прайс-лист — показываем его строки
$pricelist    = null;
$items        = array();
$totalItems   = 0;
$totalPages   = 1;
$offset       = 0;

if ($showAll) {
    $offset = ($page - 1) * $perPage;
    $result = $itemRepo->getAllMatchedItems($search, $offset, $perPage);
    $items = $result['rows'];
    $totalItems = $result['total'];
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
} else if ($pricelistId > 0) {
    $pricelist = $pricelistRepo->getById($pricelistId);
    if ($pricelist) {
        $offset     = ($page - 1) * $perPage;
        $extraFilters = array('stock_filter' => $stockFilter, 'rrp_filter' => $rrpFilter);
        $result     = $itemRepo->getList($pricelistId, $matchFilter, $search, $offset, $perPage, $extraFilters);
        $items      = $result['rows'];
        $totalItems = $result['total'];
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
    }
}

// Декодируем конфиг для Google Sheets прайс-листов
$sheetConfigs = array();
foreach ($suppliers as $sup) {
    foreach ($sup['pricelists'] as $pl) {
        if ($pl['source_type'] === 'google_sheets') {
            $sheetConfigs[(int)$pl['id']] = $pricelistRepo->decodeConfig($pl);
        }
    }
}

// true если текущий прайс — склад МойСклад (source_type=moy_sklad)
$isWarehousePl = $pricelist && isset($pricelist['source_type']) && $pricelist['source_type'] === 'moy_sklad';

function suppliersUrl($params, $basePath)
{
    return ViewHelper::buildUrl($basePath, $params);
}

require __DIR__ . '/views/suppliers.php';
