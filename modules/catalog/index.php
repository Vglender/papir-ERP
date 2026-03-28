<?php

require_once __DIR__ . '/catalog_bootstrap.php';

$catalogRepo = new CatalogRepository();

$basePath = '/catalog';
$perPage  = 50;

$search      = Request::getString('search', '');
$sort        = $catalogRepo->normalizeSort(Request::getString('sort', 'name'));
$order       = $catalogRepo->normalizeOrder(Request::getString('order', 'asc'));
$page        = max(1, Request::getInt('page', 1));
$selected    = Request::getInt('selected', 0);
$siteFilterRaw = Request::getString('site_filter', '');

$sites = $catalogRepo->getSites();

// All active site IDs
$allSiteIds = array();
foreach ($sites as $site) {
    $allSiteIds[] = (int)$site['site_id'];
}

// Parse site_filter: comma-separated checked values like "bk", "1", "2"
// Empty = all checked = no filter (default)
$siteFilter = array();
if ($siteFilterRaw !== '') {
    foreach (explode(',', $siteFilterRaw) as $sf) {
        $sf = trim($sf);
        if ($sf === 'bk' || (int)$sf > 0) $siteFilter[] = $sf;
    }
}

// Determine which checkboxes are checked for UI rendering
// Default (empty param) = all checked
if ($siteFilterRaw === '') {
    $checkedSiteFilter = array_merge(array('bk'), array_map('strval', $allSiteIds));
} else {
    $checkedSiteFilter = $siteFilter;
}

$state = array(
    'search'      => $search,
    'site_filter' => $siteFilterRaw,
    'sort'        => $sort,
    'order'       => $order,
    'page'        => $page,
    'selected'    => $selected,
);

$totalCatalog = $catalogRepo->getTotalCatalogCount();
$totalRows    = $catalogRepo->getTotalRows($search, $siteFilter, $allSiteIds);

$paginator  = new Paginator($page, $perPage, $totalRows);
$page       = $paginator->page;
$totalPages = $paginator->totalPages;
$offset     = $paginator->offset;

$state['page'] = $page;

$rows = $catalogRepo->getList($search, $sort, $order, $offset, $perPage, $siteFilter, $allSiteIds);

if ($selected <= 0 && !empty($rows)) {
    $selected = (int)$rows[0]['product_id'];
}
$state['selected'] = $selected;

$details = null;
if ($selected > 0) {
    $details = $catalogRepo->getProductDetails($selected);
}

$weightClasses = array();
$wcR = Database::fetchAll('Papir',
    "SELECT wc.weight_class_id, wcd.title
     FROM weight_class wc
     JOIN weight_class_description wcd ON wcd.weight_class_id = wc.weight_class_id AND wcd.language_id = 2
     ORDER BY wc.weight_class_id");
if ($wcR['ok']) $weightClasses = $wcR['rows'];

$lengthClasses = array();
$lcR = Database::fetchAll('Papir',
    "SELECT lc.length_class_id, lcd.title
     FROM length_class lc
     JOIN length_class_description lcd ON lcd.length_class_id = lc.length_class_id AND lcd.language_id = 2
     ORDER BY lc.length_class_id");
if ($lcR['ok']) $lengthClasses = $lcR['rows'];

function catalogSortLink($label, $field, array $state, $basePath)
{
    $isActive = ($state['sort'] === $field);
    $newOrder = ($isActive && $state['order'] === 'asc') ? 'desc' : 'asc';
    $params = array_merge($state, array('sort' => $field, 'order' => $newOrder, 'page' => 1));
    $icon = $isActive ? ($state['order'] === 'asc' ? ' ↑' : ' ↓') : '';
    $class = $isActive ? 'sort-link active' : 'sort-link';
    $url = ViewHelper::buildUrl($basePath, $params);
    return '<a href="' . ViewHelper::h($url) . '" class="' . $class . '">' . ViewHelper::h($label . $icon) . '</a>';
}

function catalogPageLink($pageNum, array $state, $basePath)
{
    return ViewHelper::buildUrl($basePath, array_merge($state, array('page' => $pageNum)));
}

function renderValue($value, $fallback = '—')
{
    $value = trim((string)$value);
    return $value !== '' ? ViewHelper::h($value) : $fallback;
}

function renderWeightValue($weight, $weightClassId)
{
    if ((float)$weight <= 0) return '—';
    $unit = (int)$weightClassId === 1 ? 'г' : ((int)$weightClassId === 2 ? 'кг' : '?');
    return ViewHelper::h(rtrim(rtrim(number_format((float)$weight, 2, '.', ''), '0'), '.')) . ' ' . ViewHelper::h($unit);
}

function renderDimensionsValue($length, $width, $height, $lengthClassId)
{
    if ((float)$length <= 0 && (float)$width <= 0 && (float)$height <= 0) return '—';
    $unit = (int)$lengthClassId === 1 ? 'см' : ((int)$lengthClassId === 2 ? 'мм' : '?');
    $l = rtrim(rtrim(number_format((float)$length, 2, '.', ''), '0'), '.');
    $w = rtrim(rtrim(number_format((float)$width, 2, '.', ''), '0'), '.');
    $h = rtrim(rtrim(number_format((float)$height, 2, '.', ''), '0'), '.');
    return ViewHelper::h($l . ' × ' . $w . ' × ' . $h . ' ' . $unit);
}

function renderLinkValue($value)
{
    $value = trim((string)$value);
    if ($value === '') return '—';
    if (preg_match('~^https?://~i', $value)) {
        return '<a href="' . ViewHelper::h($value) . '" target="_blank" rel="noopener noreferrer">' . ViewHelper::h($value) . '</a>';
    }
    return ViewHelper::h($value);
}

function renderPrice($value)
{
    if ($value === null || $value === '') return '—';
    return ViewHelper::h(number_format((float)$value, 2, '.', ' '));
}

require __DIR__ . '/views/index.php';
