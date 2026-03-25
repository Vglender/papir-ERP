<?php

require_once __DIR__ . '/prices_bootstrap.php';

$productRepo          = new ProductPriceRepository();
$discountStrategyRepo = new DiscountStrategyRepository();
$quantityStrategyRepo = new QuantityStrategyRepository();
$globalSettingsRepo   = new GlobalSettingsRepository();
$globalSettings       = $globalSettingsRepo->get();
$quantityStrategies   = $quantityStrategyRepo->getAll();

$pricelistRepo = new PricelistRepository();
$allSuppliers  = $pricelistRepo->getAllGroupedBySupplier();

// ── Параметры запроса ──────────────────────────────────────────────────────
$basePath   = '/prices';
$perPage    = 50;

$search       = Request::getString('search', '');
$filter       = Request::getString('filter', 'all');
$allowedFilters = array('all', 'manual_only', 'no_stock');
if (!in_array($filter, $allowedFilters)) $filter = 'all';
$strategyId   = Request::getInt('strategy_id', 0);
$sort         = Request::getString('sort', 'product_id');
$order        = Request::getString('order', 'asc');
$page         = max(1, Request::getInt('page', 1));
$selected         = Request::getInt('selected', 0);
$selectedExplicit = $selected; // до авто-подстановки первой строки
$showInactive     = Request::getInt('show_inactive', 0);

$allowedSort  = ['product_id', 'product_article', 'price_purchase', 'price_sale', 'price_wholesale', 'price_dealer', 'price_rrp'];
$sort         = in_array($sort, $allowedSort) ? $sort : 'product_id';
$order        = $order === 'desc' ? 'desc' : 'asc';

$filters = [
    'search'        => $search,
    'strategy_id'   => $strategyId,
    'filter'        => $filter,
    'show_inactive' => $showInactive,
];

$state = [
    'search'        => $search,
    'filter'        => $filter,
    'strategy_id'   => $strategyId,
    'sort'          => $sort,
    'order'         => $order,
    'page'          => $page,
    'selected'      => $selected,
    'show_inactive' => $showInactive,
];

// ── Данные ─────────────────────────────────────────────────────────────────
$strategies = $discountStrategyRepo->getAll();
$totalRows  = $productRepo->countList($filters);

$paginator  = new Paginator($page, $perPage, $totalRows);
$page       = $paginator->page;
$totalPages = $paginator->totalPages;
$offset     = $paginator->offset;

$state['page'] = $page;

$listResult = $productRepo->getList($filters, $sort, $order, $offset, $perPage);
$rows       = $listResult['ok'] ? $listResult['rows'] : [];

if ($selected <= 0 && !empty($rows)) {
    $selected = (int)$rows[0]['product_id'];
}
$state['selected'] = $selected;

$details = null;
if ($selected > 0) {
    $details = $productRepo->getProductDetails($selected);
}

// ── Хелперы ───────────────────────────────────────────────────────────────
function pricesSortLink($label, $field, array $state, $basePath)
{
    $newOrder = ($state['sort'] === $field && $state['order'] === 'asc') ? 'desc' : 'asc';
    $params   = array_merge($state, ['sort' => $field, 'order' => $newOrder, 'page' => 1]);
    $icon     = '';
    if ($state['sort'] === $field) {
        $icon = $state['order'] === 'asc' ? ' ↑' : ' ↓';
    }
    $url = ViewHelper::buildUrl($basePath, $params);
    return '<a href="' . ViewHelper::h($url) . '" class="sort-link">' . ViewHelper::h($label . $icon) . '</a>';
}

function pricesPageUrl($pageNum, array $state, $basePath)
{
    return ViewHelper::buildUrl($basePath, array_merge($state, ['page' => $pageNum]));
}

function priceVal($value, $fallback = '—')
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    return ViewHelper::h(number_format((float)$value, 2, '.', ' '));
}

function textVal($value, $fallback = '—')
{
    $value = trim((string)$value);
    return $value !== '' ? ViewHelper::h($value) : $fallback;
}

// ── View ──────────────────────────────────────────────────────────────────
require __DIR__ . '/views/index.php';
