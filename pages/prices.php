<?php

require_once __DIR__ . '/../modules/database/database.php';

$mysqli_ms = connectbd('ms');
$mysqli_papir = connectbd('Papir');
$mysqli_off = connectbd('off');

$priceRepo = new PriceRepository($mysqli_ms, $mysqli_papir, $mysqli_off);

$basePath = '/prices';
$perPage = 50;

$search = isset($_GET['search']) ? (string)$_GET['search'] : '';
$filter = Request::getString('filter', 'all');
$strategyId = Request::getInt('strategy_id', 0);
$sort = $priceRepo->normalizeSort(Request::getString('sort', 'id_off'));
$order = $priceRepo->normalizeOrder(Request::getString('order', 'asc'));
$page = max(1, Request::getInt('page', 1));
$selected = Request::getInt('selected', 0);

$strategies = $priceRepo->getStrategies();

$state = array(
    'search'     => $search,
    'filter'     => $filter,
    'strategy_id'=> $strategyId,
    'sort'       => $sort,
    'order'      => $order,
    'page'       => $page,
    'selected'   => $selected,
);

$totalRows = $priceRepo->getTotalRows($search, $filter, $strategyId);

$paginator = new Paginator($page, $perPage, $totalRows);
$page = $paginator->page;
$totalPages = $paginator->totalPages;
$offset = $paginator->offset;

$state['page'] = $page;

$listResult = $priceRepo->getList($search, $filter, $strategyId, $sort, $order, $offset, $perPage);

$rows = array();
if ($listResult && $listResult->num_rows > 0) {
    while ($row = $listResult->fetch_assoc()) {
        $rows[] = $row;
    }
}

if ($selected <= 0 && !empty($rows)) {
    $selected = (int)$rows[0]['id_off'];
}

$state['selected'] = $selected;

$details = null;
if ($selected > 0) {
    $details = $priceRepo->getProductDetails($selected);
}

function priceSortLink($label, $field, array $state, $basePath)
{
    $newOrder = 'asc';

    if ($state['sort'] === $field && $state['order'] === 'asc') {
        $newOrder = 'desc';
    }

    $params = $state;
    $params['sort'] = $field;
    $params['order'] = $newOrder;
    $params['page'] = 1;

    $url = ViewHelper::buildUrl($basePath, $params);

    return '<a href="' . ViewHelper::h($url) . '" class="sort-link">' . ViewHelper::h($label) . '</a>';
}

function pricePageLink($pageNumber, array $state, $basePath)
{
    $params = $state;
    $params['page'] = (int)$pageNumber;

    return ViewHelper::buildUrl($basePath, $params);
}

function renderPriceValue($value)
{
    if ($value === null || $value === '') {
        return '—';
    }

    return ViewHelper::h(number_format((float)$value, 2, '.', ' '));
}

function renderTextValue($value, $fallback = '—')
{
    $value = (string)$value;
    return $value !== '' ? ViewHelper::h($value) : $fallback;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Prices</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }
        .wrap {
            max-width: 1850px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .title {
            margin: 0 0 6px;
            font-size: 30px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
            margin-left: 6px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(760px, 1fr) 480px;
            gap: 20px;
            align-items: start;
        }
        .card {
            background: #fff;
            border: 1px solid #d9e0ea;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .sticky-panel {
            position: sticky;
            top: 16px;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            padding-right: 6px;
        }
        .filters {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) 220px 220px auto;
            gap: 10px;
            margin-bottom: 16px;
            align-items: end;
        }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: bold;
        }
        input[type="text"],
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #c8d1dd;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
        }
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #c8d1dd;
            background: #fff;
            color: #222;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            box-sizing: border-box;
            text-align: center;
        }
        .btn-small {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
        }
        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .bulk-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #d9e0ea;
            border-radius: 10px;
            background: #f8fafc;
        }
        .bulk-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e8edf3;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #555;
        }
        .sort-link {
            color: #555;
            text-decoration: none;
        }
        .sort-link:hover {
            text-decoration: underline;
        }
        .num {
            white-space: nowrap;
        }
        tbody tr.js-row-click {
            cursor: pointer;
            transition: background .15s ease;
        }
        tbody tr.js-row-click:hover {
            background: #f8fbff;
        }
        .selected-row {
            background: #f0f6ff;
        }
        .status-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pill-on {
            background: #edfdf3;
            color: #157347;
        }
        .status-pill-action {
            background: #fff4e5;
            color: #b26a00;
        }
        .status-pill-stock {
            background: #eef4ff;
            color: #1f4db8;
        }
        .status-pill-off {
            background: #fff1f1;
            color: #b42318;
        }
        .section {
            border-top: 1px solid #e8edf3;
            padding-top: 16px;
            margin-top: 16px;
        }
        .section:first-child {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px 12px;
        }
        .info-grid .k {
            color: #666;
            font-size: 13px;
        }
        .info-grid .v {
            font-size: 14px;
            word-break: break-word;
        }
        .price-card {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .price-group {
            border: 1px solid #e8edf3;
            border-radius: 10px;
            padding: 12px;
            background: #fafcff;
        }
        .price-group-muted {
            background: #fbfbfc;
        }
        .price-group-title {
            font-size: 12px;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
        }
        .price-main-row-2,
        .price-sub-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .price-main-item,
        .price-sub-item {
            background: #fff;
            border: 1px solid #eef2f6;
            border-radius: 8px;
            padding: 10px;
        }
        .price-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }
        .price-value {
            font-size: 16px;
            font-weight: bold;
            line-height: 1.2;
        }
        .price-sale-text {
            color: #b42318;
            font-weight: bold;
        }
        .price-discounts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .discount-chip {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef4ff;
            color: #1f4db8;
            font-size: 13px;
            white-space: nowrap;
        }
        .settings-card,
        .formula-card {
            border: 1px solid #e8edf3;
            border-radius: 10px;
            background: #fafcff;
            padding: 12px;
        }
        .settings-row,
        .formula-row {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #eef2f6;
        }
        .settings-row:last-child,
        .formula-row:last-child {
            border-bottom: 0;
        }
        .settings-label,
        .formula-label {
            color: #666;
            font-size: 13px;
        }
        .settings-value,
        .formula-value {
            font-size: 14px;
        }
        .module-links {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }
        .module-links .btn {
            flex: 1;
        }
        .pagination {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #d9e0ea;
            border-radius: 8px;
            text-decoration: none;
            color: #222;
            background: #fff;
            font-size: 14px;
        }
        .pagination .current {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .empty {
            color: #777;
        }
        @media (max-width: 1200px) {
            .layout {
                grid-template-columns: 1fr;
            }
            .sticky-panel {
                position: static;
                max-height: none;
                overflow: visible;
            }
        }
        @media (max-width: 900px) {
            .filters {
                grid-template-columns: 1fr;
            }
            .price-main-row-2,
            .price-sub-row,
            .settings-row,
            .formula-row {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1 class="title">Prices</h1>
            <div class="subtitle">Модуль просмотра и управления ценовыми уровнями.</div>
        </div>

        <div>
            <span class="badge">Найдено: <?php echo (int)$totalRows; ?></span>
        </div>
    </div>

    <div class="layout">
        <div class="card">
            <form method="get" action="/prices" class="filters">
                <input type="hidden" name="sort" value="<?php echo ViewHelper::h($sort); ?>">
                <input type="hidden" name="order" value="<?php echo ViewHelper::h($order); ?>">
                <input type="hidden" name="page" value="1">
                <?php if ($selected > 0) { ?>
                    <input type="hidden" name="selected" value="<?php echo (int)$selected; ?>">
                <?php } ?>

                <div>
                    <label for="search">Поиск</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="<?php echo ViewHelper::h($search); ?>"
                        placeholder="Поиск по id_off, артикулу или названию"
                    >
                </div>

                <div>
                    <label for="filter">Фильтр</label>
                    <select name="filter" id="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все товары</option>
                        <option value="with_action" <?php echo $filter === 'with_action' ? 'selected' : ''; ?>>Только с акцией</option>
                        <option value="with_stock" <?php echo $filter === 'with_stock' ? 'selected' : ''; ?>>Только с остатком</option>
                        <option value="manual_only" <?php echo $filter === 'manual_only' ? 'selected' : ''; ?>>Только с manual override</option>
                    </select>
                </div>

                <div>
                    <label for="strategy_id">Стратегия</label>
                    <select name="strategy_id" id="strategy_id">
                        <option value="0">Все стратегии</option>
                        <?php foreach ($strategies as $strategy) { ?>
                            <option value="<?php echo (int)$strategy['strategy_id']; ?>" <?php echo $strategyId === (int)$strategy['strategy_id'] ? 'selected' : ''; ?>>
                                <?php echo ViewHelper::h($strategy['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn">Применить</button>
                    <a href="/prices" class="btn">Сброс</a>
                </div>
            </form>

            <div class="bulk-toolbar">
                <div><strong>Выбрано:</strong> <span id="selectedCount">0</span></div>

                <div class="bulk-actions">
                    <button type="button" class="btn btn-small" id="bulkSelectPage">Выбрать страницу</button>
                    <button type="button" class="btn btn-small" id="bulkClear">Снять выбор</button>
                    <button type="button" class="btn btn-small" id="bulkCopyIds">Копировать ID</button>
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAllRows"></th>
                    <th><?php echo priceSortLink('id_off', 'id_off', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Артикул', 'product_article', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Название', 'name', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Продажа', 'price', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Акционная', 'action_price', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Оптовая', 'wholesale_price', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Дилерская', 'dealer_price', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Закупочная', 'price_cost', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('RRP', 'price_rrp', $state, $basePath); ?></th>
                    <th><?php echo priceSortLink('Стратегия', 'strategy_name', $state, $basePath); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)) { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $isSelected = ((int)$row['id_off'] === (int)$selected);
                        $selectUrl = ViewHelper::buildUrl($basePath, array(
                            'search'      => $search,
                            'filter'      => $filter,
                            'strategy_id' => $strategyId,
                            'sort'        => $sort,
                            'order'       => $order,
                            'page'        => $page,
                            'selected'    => (int)$row['id_off'],
                        ));
                        ?>
                        <tr
                            class="<?php echo $isSelected ? 'selected-row' : ''; ?> js-row-click"
                            data-url="<?php echo ViewHelper::h($selectUrl); ?>"
                            data-product-id="<?php echo (int)$row['id_off']; ?>"
                        >
                            <td>
                                <input type="checkbox" class="row-selector" value="<?php echo (int)$row['id_off']; ?>">
                            </td>
                            <td class="num">
                                <a href="<?php echo ViewHelper::h($selectUrl); ?>"><?php echo (int)$row['id_off']; ?></a>
                            </td>
                            <td><?php echo renderTextValue($row['product_article']); ?></td>
                            <td>
                                <a href="<?php echo ViewHelper::h($selectUrl); ?>"><?php echo renderTextValue($row['name']); ?></a>
                            </td>
                            <td class="num"><?php echo renderPriceValue($row['price']); ?></td>
                            <td class="num">
                                <?php if ($row['action_price'] !== null) { ?>
                                    <span style="color:#b42318;font-weight:bold;"><?php echo renderPriceValue($row['action_price']); ?></span>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
                            <td class="num"><?php echo renderPriceValue($row['wholesale_price']); ?></td>
                            <td class="num"><?php echo renderPriceValue($row['dealer_price']); ?></td>
                            <td class="num"><?php echo renderPriceValue($row['price_cost']); ?></td>
                            <td class="num"><?php echo renderPriceValue($row['price_rrp']); ?></td>
                            <td><?php echo renderTextValue($row['strategy_name']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="11" class="empty">Данные не найдены.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(pricePageLink($page - 1, $state, $basePath)); ?>">← Назад</a>
                    <?php } ?>

                    <?php
                    $startPage = $page - 3;
                    $endPage = $page + 3;

                    if ($startPage < 1) {
                        $startPage = 1;
                    }

                    if ($endPage > $totalPages) {
                        $endPage = $totalPages;
                    }
                    ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++) { ?>
                        <?php if ($p == $page) { ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php } else { ?>
                            <a href="<?php echo ViewHelper::h(pricePageLink($p, $state, $basePath)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo ViewHelper::h(pricePageLink($page + 1, $state, $basePath)); ?>">Вперёд →</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div class="card sticky-panel">
            <?php if ($details !== null) { ?>
                <div class="section">
                    <div style="font-size:22px;font-weight:bold;line-height:1.25;margin-bottom:8px;">
                        <?php echo renderTextValue($details['name']); ?>
                    </div>

                    <div class="status-badges">
                        <?php if ((int)$details['status'] === 1) { ?>
                            <span class="status-pill status-pill-on">Включен</span>
                        <?php } else { ?>
                            <span class="status-pill status-pill-off">Отключен</span>
                        <?php } ?>

                        <?php if ($details['special'] !== null) { ?>
                            <span class="status-pill status-pill-action">Акция</span>
                        <?php } ?>

                        <?php if ((int)$details['real_stock'] > 0) { ?>
                            <span class="status-pill status-pill-stock">В наличии</span>
                        <?php } ?>
                    </div>

                    <div class="info-grid">
                        <div class="k">id_off</div>
                        <div class="v"><?php echo (int)$details['id_off']; ?></div>

                        <div class="k">Артикул</div>
                        <div class="v"><?php echo renderTextValue($details['product_article']); ?></div>

                        <div class="k">Производитель</div>
                        <div class="v"><?php echo renderTextValue($details['manufacturer_name']); ?></div>
                    </div>
                </div>

                <div class="section">
                    <h3>Цены</h3>

                    <div class="price-card">
                        <div class="price-group">
                            <div class="price-group-title">Основные</div>

                            <div class="price-main-row-2">
                                <div class="price-main-item">
                                    <div class="price-label">Продажа</div>
                                    <div class="price-value"><?php echo renderPriceValue($details['price']); ?></div>
                                </div>

                                <div class="price-main-item">
                                    <div class="price-label">Акционная</div>
                                    <div class="price-value">
                                        <?php if ($details['special'] !== null) { ?>
                                            <span class="price-sale-text"><?php echo renderPriceValue($details['special']['price']); ?></span>
                                        <?php } else { ?>
                                            —
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="price-group">
                            <div class="price-group-title">Скидки от количества</div>

                            <?php if (!empty($details['quantity_discounts'])) { ?>
                                <div class="price-discounts">
                                    <?php foreach ($details['quantity_discounts'] as $discount) { ?>
                                        <span class="discount-chip">
                                            от <?php echo (int)$discount['quantity']; ?> шт — <?php echo renderPriceValue($discount['price']); ?>
                                        </span>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="settings-value">Нет</div>
                            <?php } ?>
                        </div>

                        <div class="price-group">
                            <div class="price-group-title">Спеццены</div>

                            <div class="price-sub-row">
                                <div class="price-sub-item">
                                    <div class="price-label">Оптовая</div>
                                    <div class="price-value"><?php echo renderPriceValue($details['wholesale_price']); ?></div>
                                </div>

                                <div class="price-sub-item">
                                    <div class="price-label">Дилерская</div>
                                    <div class="price-value"><?php echo renderPriceValue($details['dealer_price']); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="price-group price-group-muted">
                            <div class="price-group-title">Служебные</div>

                            <div class="price-sub-row">
                                <div class="price-sub-item">
                                    <div class="price-label">Закупочная</div>
                                    <div class="price-value"><?php echo renderPriceValue($details['price_cost']); ?></div>
                                </div>

                                <div class="price-sub-item">
                                    <div class="price-label">RRP</div>
                                    <div class="price-value"><?php echo renderPriceValue($details['price_rrp']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Стратегия</h3>

                    <div class="formula-card">
                        <div class="formula-row">
                            <div class="formula-label">Стратегия</div>
                            <div class="formula-value">
								<?php
								echo !empty($details['settings']['strategy_name'])
									? renderTextValue($details['settings']['strategy_name'])
									: '—';
								?>
                            </div>
                        </div>

                        <?php if (!empty($details['strategy_rule'])) { ?>
                            <div class="formula-row">
                                <div class="formula-label">Наценка розница</div>
                                <div class="formula-value"><?php echo renderPriceValue($details['strategy_rule']['retail_markup_percent']); ?> %</div>
                            </div>

                            <div class="formula-row">
                                <div class="formula-label">Наценка опт</div>
                                <div class="formula-value"><?php echo renderPriceValue($details['strategy_rule']['wholesale_markup_percent']); ?> %</div>
                            </div>

                            <div class="formula-row">
                                <div class="formula-label">Наценка дилер</div>
                                <div class="formula-value"><?php echo renderPriceValue($details['strategy_rule']['dealer_markup_percent']); ?> %</div>
                            </div>

                            <div class="formula-row">
                                <div class="formula-label">Округление</div>
                                <div class="formula-value">
                                    <?php echo renderPriceValue($details['strategy_rule']['rounding_step']); ?>
                                    / <?php echo renderTextValue($details['strategy_rule']['rounding_mode']); ?>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="formula-row">
                                <div class="formula-label">Правила</div>
                                <div class="formula-value">Не назначены</div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="section">
                    <h3>Ручные override</h3>

                    <div class="settings-card">
                        <div class="settings-row">
                            <div class="settings-label">Продажная</div>
                            <div class="settings-value">
                                <?php echo !empty($details['settings']['manual_price_enabled']) ? 'Да: ' . renderPriceValue($details['settings']['manual_price']) : 'Нет'; ?>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-label">Оптовая</div>
                            <div class="settings-value">
                                <?php echo !empty($details['settings']['manual_wholesale_enabled']) ? 'Да: ' . renderPriceValue($details['settings']['manual_wholesale_price']) : 'Нет'; ?>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-label">Дилерская</div>
                            <div class="settings-value">
                                <?php echo !empty($details['settings']['manual_dealer_enabled']) ? 'Да: ' . renderPriceValue($details['settings']['manual_dealer_price']) : 'Нет'; ?>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-label">RRP</div>
                            <div class="settings-value">
                                <?php echo !empty($details['settings']['manual_rrp_enabled']) ? 'Да: ' . renderPriceValue($details['settings']['manual_rrp']) : 'Нет'; ?>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-label">Закупочная</div>
                            <div class="settings-value">
                                <?php echo !empty($details['settings']['manual_cost_enabled']) ? 'Да: ' . renderPriceValue($details['settings']['manual_cost']) : 'Нет'; ?>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-label">Лок</div>
                            <div class="settings-value"><?php echo !empty($details['settings']['is_locked']) ? 'Да' : 'Нет'; ?></div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3>Модуль</h3>

                    <div class="module-links">
                        <a href="/catalog?selected=<?php echo (int)$details['id_off']; ?>" class="btn btn-small" target="_blank">Catalog</a>
                        <a href="/action?action=edit&product_id=<?php echo (int)$details['id_off']; ?>&search=<?php echo (int)$details['id_off']; ?>&filter=all&sort=product_id&order=asc&page=1" class="btn btn-small" target="_blank">Action</a>
                    </div>
                </div>
            <?php } else { ?>
                <div class="empty">Товар не выбран.</div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.js-row-click').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input')) {
                return;
            }

            var url = this.getAttribute('data-url');

            if (url) {
                window.location = url;
            }
        });
    });

    var selectedIds = new Set();
    var selectAllRows = document.getElementById('selectAllRows');
    var rowSelectors = document.querySelectorAll('.row-selector');
    var selectedCount = document.getElementById('selectedCount');

    var bulkSelectPage = document.getElementById('bulkSelectPage');
    var bulkClear = document.getElementById('bulkClear');
    var bulkCopyIds = document.getElementById('bulkCopyIds');

    function refreshSelectedCounter() {
        if (selectedCount) {
            selectedCount.textContent = selectedIds.size;
        }

        if (selectAllRows) {
            var total = rowSelectors.length;
            var checked = 0;

            rowSelectors.forEach(function (cb) {
                if (cb.checked) {
                    checked++;
                }
            });

            selectAllRows.checked = (total > 0 && checked === total);
        }
    }

    rowSelectors.forEach(function (checkbox) {
        checkbox.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        checkbox.addEventListener('change', function () {
            if (this.checked) {
                selectedIds.add(this.value);
            } else {
                selectedIds.delete(this.value);
            }

            refreshSelectedCounter();
        });
    });

    if (selectAllRows) {
        selectAllRows.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        selectAllRows.addEventListener('change', function () {
            var checked = this.checked;

            rowSelectors.forEach(function (checkbox) {
                checkbox.checked = checked;

                if (checked) {
                    selectedIds.add(checkbox.value);
                } else {
                    selectedIds.delete(checkbox.value);
                }
            });

            refreshSelectedCounter();
        });
    }

    if (bulkSelectPage) {
        bulkSelectPage.addEventListener('click', function () {
            rowSelectors.forEach(function (checkbox) {
                checkbox.checked = true;
                selectedIds.add(checkbox.value);
            });

            refreshSelectedCounter();
        });
    }

    if (bulkClear) {
        bulkClear.addEventListener('click', function () {
            rowSelectors.forEach(function (checkbox) {
                checkbox.checked = false;
            });

            selectedIds.clear();
            refreshSelectedCounter();
        });
    }

    if (bulkCopyIds) {
        bulkCopyIds.addEventListener('click', function () {
            var ids = Array.from(selectedIds);

            if (!ids.length) {
                alert('Сначала выбери товары.');
                return;
            }

            navigator.clipboard.writeText(ids.join(',')).then(function () {
                alert('ID скопированы');
            }).catch(function () {
                alert('Не удалось скопировать ID');
            });
        });
    }

    refreshSelectedCounter();
})();
</script>
</body>
</html>
<?php
$mysqli_ms->close();
$mysqli_papir->close();
$mysqli_off->close();