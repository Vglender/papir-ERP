<?php

require_once '/var/sqript/products/confif_bp.php';
require_once '/var/sqript/products/lib/Request.php';
require_once '/var/sqript/products/lib/ViewHelper.php';
require_once '/var/sqript/products/lib/TableHelper.php';
require_once '/var/sqript/products/lib/Paginator.php';
require_once '/var/sqript/products/lib/ArrayResult.php';
require_once '/var/sqript/products/lib/CatalogRepository.php';

$mysqli_ms = connectbd('ms');
$mysqli_papir = connectbd('Papir');
$mysqli_off = connectbd('off');

$catalogRepo = new CatalogRepository($mysqli_ms, $mysqli_papir, $mysqli_off);

$basePath = '/catalog';
$perPage = 50;

$search = isset($_GET['search']) ? (string)$_GET['search'] : '';
$filter = Request::getString('filter', 'all');
$sort = $catalogRepo->normalizeSort(Request::getString('sort', 'id_off'));
$order = $catalogRepo->normalizeOrder(Request::getString('order', 'asc'));
$page = max(1, Request::getInt('page', 1));
$selected = Request::getInt('selected', 0);

$state = array(
    'search'   => $search,
    'filter'   => $filter,
    'sort'     => $sort,
    'order'    => $order,
    'page'     => $page,
    'selected' => $selected,
);

$totalCatalog = $catalogRepo->getTotalCatalogCount();
$totalRows = $catalogRepo->getTotalRows($search, $filter);

$paginator = new Paginator($page, $perPage, $totalRows);
$page = $paginator->page;
$totalPages = $paginator->totalPages;
$offset = $paginator->offset;

$state['page'] = $page;

$listResult = $catalogRepo->getList($search, $filter, $sort, $order, $offset, $perPage);

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
    $details = $catalogRepo->getProductDetails($selected);
}

function catalogSortLink($label, $field, array $state, $basePath)
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

	function catalogPageLink($pageNumber, array $state, $basePath)
	{
		$params = $state;
		$params['page'] = (int)$pageNumber;

		return ViewHelper::buildUrl($basePath, $params);
	}

	function renderValue($value, $fallback = '—')
	{
		$value = (string)$value;
		return $value !== '' ? ViewHelper::h($value) : $fallback;
	}

	function renderWeightValue($weight, $weightClassId)
	{
		if ((float)$weight <= 0) {
			return '—';
		}

		$unit = '?';

		if ((int)$weightClassId === 1) {
			$unit = 'г';
		} elseif ((int)$weightClassId === 2) {
			$unit = 'кг';
		}

		return ViewHelper::h(rtrim(rtrim(number_format((float)$weight, 2, '.', ''), '0'), '.')) . ' ' . ViewHelper::h($unit);
	}

	function renderDimensionsValue($length, $width, $height, $lengthClassId)
	{
		if ((float)$length <= 0 && (float)$width <= 0 && (float)$height <= 0) {
			return '—';
		}

		$unit = '?';

		if ((int)$lengthClassId === 1) {
			$unit = 'см';
		} elseif ((int)$lengthClassId === 2) {
			$unit = 'мм';
		}

		$l = rtrim(rtrim(number_format((float)$length, 2, '.', ''), '0'), '.');
		$w = rtrim(rtrim(number_format((float)$width, 2, '.', ''), '0'), '.');
		$h = rtrim(rtrim(number_format((float)$height, 2, '.', ''), '0'), '.');

		return ViewHelper::h($l . ' × ' . $w . ' × ' . $h . ' ' . $unit);
	}

function renderLinkValue($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return '—';
    }

    if (preg_match('~^https?://~i', $value)) {
        return '<a href="' . ViewHelper::h($value) . '" target="_blank" rel="noopener noreferrer">' . ViewHelper::h($value) . '</a>';
    }

    return ViewHelper::h($value);
}

function renderPrice($value)
{
    if ($value === null || $value === '') {
        return '—';
    }

    return ViewHelper::h(number_format((float)$value, 2, '.', ' '));
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Catalog</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }
        .wrap {
            max-width: 1800px;
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
            grid-template-columns: minmax(720px, 1fr) 460px;
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
        .card h2, .card h3 {
            margin-top: 0;
        }
		.sticky-panel {
			position: sticky;
			top: 16px;
			max-height: calc(100vh - 32px);
			overflow-y: auto;
			padding-right: 6px;
		}
		.sticky-panel::-webkit-scrollbar {
			width: 8px;
		}

		.sticky-panel::-webkit-scrollbar-thumb {
			background: #cfd8e3;
			border-radius: 8px;
		}

		.sticky-panel::-webkit-scrollbar-track {
			background: transparent;
		}
		.filters {
			display: grid;
			grid-template-columns: minmax(260px, 1fr) 220px auto;
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
        .btn-primary {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-small {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 6px;
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
        .selected-row {
            background: #f0f6ff;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
        }
        .status-disabled {
            background: #f4f4f5;
            color: #666;
        }
        .status-action {
            background: #edfdf3;
            color: #157347;
        }
        .empty {
            color: #777;
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
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 12px;
            border: 1px solid #d9e0ea;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-size: 13px;
        }
        .tab-btn.active {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .scroll-box {
            max-height: 180px;
            overflow-y: auto;
            padding: 10px 12px;
            border: 1px solid #e8edf3;
            border-radius: 8px;
            background: #fafcff;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .mini-note {
            color: #666;
            font-size: 12px;
        }
        .photo-btn {
            font-size: 18px;
            line-height: 1;
            text-decoration: none;
        }
        .thumbs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .thumbs img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #d9e0ea;
            cursor: pointer;
            background: #fff;
        }
        .main-photo {
            width: 100%;
            max-width: 220px;
            border-radius: 10px;
            border: 1px solid #d9e0ea;
            display: block;
            cursor: pointer;
        }
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.68);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal.open {
            display: flex;
        }
        .modal-content {
            position: relative;
            max-width: 92vw;
            max-height: 92vh;
        }
        .modal-image {
            max-width: 92vw;
            max-height: 92vh;
            border-radius: 12px;
            background: #fff;
        }
        .modal-close {
            position: absolute;
            right: -10px;
            top: -10px;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 50%;
            background: #fff;
            cursor: pointer;
            font-size: 20px;
        }
        @media (max-width: 1200px) {
            .layout {
                grid-template-columns: 1fr;
            }
            .sticky-panel {
                position: static;
            }
        }
        @media (max-width: 800px) {
            .filters {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
		
		.action-menu {
			position: relative;
			display: inline-block;
		}

		.action-menu-toggle {
			border: 1px solid #c8d1dd;
			background: #fff;
			border-radius: 8px;
			padding: 6px 10px;
			cursor: pointer;
			font-size: 16px;
		}

		.action-menu-dropdown {
			display: none;
			position: absolute;
			right: 0;
			top: 100%;
			margin-top: 6px;
			min-width: 180px;
			background: #fff;
			border: 1px solid #d9e0ea;
			border-radius: 10px;
			box-shadow: 0 8px 24px rgba(0,0,0,0.12);
			z-index: 50;
		}

		.action-menu.open .action-menu-dropdown {
			display: block;
		}

		.action-menu-dropdown a {
			display: block;
			padding: 10px 12px;
			color: #222;
			text-decoration: none;
			font-size: 14px;
		}

		.action-menu-dropdown a:hover {
			background: #f5f8fc;
		}
		tbody tr:hover {
			background: #f8fbff;
			cursor: pointer;
		}
		tbody tr.js-row-click {
			cursor: pointer;
		}

		tbody tr.js-row-click:hover {
			background: #f8fbff;
		}
		.action-menu a {
			cursor: pointer;
		}
		.nav-arrows{
			display:flex;
			gap:8px;
			}

			.nav-arrow{
			border:1px solid #c8d1dd;
			background:#fff;
			border-radius:8px;
			padding:6px 10px;
			cursor:pointer;
			font-size:16px;
			}
			tbody tr{
				transition:background .15s;
				}

				tbody tr:hover{
				background:#f3f7ff;
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

				.status-pill-off {
					background: #fff1f1;
					color: #b42318;
				}
		#eanBarcode {
			max-width: 220px;
			width: 100%;
			height: 60px;
			background: #fff;
			border: 1px solid #e8edf3;
			border-radius: 8px;
			padding: 6px;
			box-sizing: border-box;
		}
		.top-status-badges {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
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

		.status-pill-off {
			background: #fff1f1;
			color: #b42318;
		}

		.status-pill-action {
			background: #fff4e5;
			color: #b26a00;
		}

		.status-pill-stock {
			background: #eef4ff;
			color: #1f4db8;
		}	
		.ean-barcode-wrapper{
			margin-top:18px;
			padding-top:14px;
			border-top:1px solid #eef2f6;
			text-align:center;
		}

		#eanBarcode{
			width:100%;
			height:90px;
			cursor:pointer;
		}
		#eanBarcode{
			width:100%;
			height:80px;
			background:#fff;
			cursor:pointer;
		}

		#eanBarcode:hover{
			background:#f8fafc;
		}	
		.ean-copy-hint{
			margin-top:6px;
			font-size:12px;
			color:#888;
		}
		.copy-toast{
			position:fixed;
			bottom:20px;
			right:20px;
			background:#1f6feb;
			color:#fff;
			padding:8px 14px;
			border-radius:8px;
			font-size:13px;
			opacity:0;
			transform:translateY(10px);
			transition:all .25s ease;
			pointer-events:none;
		}

		.copy-toast.show{
			opacity:1;
			transform:translateY(0);
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

		.price-main-row {
			display: grid;
			gap: 10px;
		}
		.price-main-row-2 {
			grid-template-columns: repeat(2, 1fr);
		}

		.price-sub-row {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 10px;
		}
		.price-sale-text {
			color: #b42318;
			font-weight: bold;
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

		.price-badge-sale {
			display: inline-block;
			padding: 6px 10px;
			border-radius: 8px;
			background: #fff1f1;
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

		.price-empty {
			color: #777;
			font-size: 14px;
		}

		@media (max-width: 800px) {
			.price-main-row,
			.price-sub-row {
				grid-template-columns: 1fr;
			}
		}	
		.stock-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			padding: 12px;
			background: #fafcff;
		}

		.stock-grid {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 10px;
		}

		.stock-item {
			background: #fff;
			border: 1px solid #eef2f6;
			border-radius: 8px;
			padding: 10px;
		}

		.stock-label {
			font-size: 12px;
			color: #666;
			margin-bottom: 6px;
		}

		.stock-value {
			font-size: 18px;
			font-weight: bold;
			line-height: 1.2;
		}	
		.content-card {
			display: flex;
			flex-direction: column;
			gap: 14px;
		}

		.content-field {
			display: flex;
			flex-direction: column;
			gap: 6px;
		}

		.content-label {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		.content-inline-value {
			font-size: 15px;
			font-weight: bold;
			line-height: 1.35;
		}

		.seo-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			background: #fafcff;
			padding: 12px;
		}

		.seo-card-title {
			font-size: 12px;
			font-weight: bold;
			color: #666;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			margin-bottom: 10px;
		}

		.seo-row {
			display: grid;
			grid-template-columns: 130px 1fr;
			gap: 10px;
			padding: 6px 0;
			border-bottom: 1px solid #eef2f6;
		}

		.seo-row:last-child {
			border-bottom: 0;
		}

		.seo-label {
			color: #666;
			font-size: 13px;
		}

		.seo-value {
			font-size: 14px;
			word-break: break-word;
		}
		.specs-card {
			border: 1px solid #e8edf3;
			border-radius: 10px;
			background: #fafcff;
			padding: 12px;
		}

		.spec-row {
			display: grid;
			grid-template-columns: 130px 1fr;
			gap: 10px;
			padding: 8px 0;
			border-bottom: 1px solid #eef2f6;
		}

		.spec-row:last-child {
			border-bottom: 0;
		}

		.spec-label {
			color: #666;
			font-size: 13px;
		}

		.spec-value {
			font-size: 14px;
			word-break: break-word;
		}	
		@media (max-width: 800px) {
			.price-main-row-2,
			.price-sub-row,
			.stock-grid,
			.seo-row,
			.spec-row {
				grid-template-columns: 1fr;
			}
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

		.bulk-left {
			font-size: 14px;
		}

		.bulk-actions {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.row-selector {
			cursor: pointer;
		}		
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1 class="title">Catalog</h1>
            <div class="subtitle">Главный справочник товаров с обзором и переходом в профильные модули.</div>
        </div>

        <div>
            <span class="badge">Всего в каталоге: <?php echo (int)$totalCatalog; ?></span>
            <span class="badge">Найдено: <?php echo (int)$totalRows; ?></span>
        </div>
    </div>

    <div class="layout">
        <div class="card">
			<form method="get" action="/catalog" class="filters">
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
						<option value="with_stock" <?php echo $filter === 'with_stock' ? 'selected' : ''; ?>>Только с остатком</option>
						<option value="with_action" <?php echo $filter === 'with_action' ? 'selected' : ''; ?>>Только с акцией</option>
					</select>
				</div>

				<div class="btn-row">
					<button type="submit" class="btn">Применить</button>
					<a href="/catalog" class="btn">Сброс</a>
				</div>
			</form>
			<div class="bulk-toolbar">
				<div class="bulk-left">
					<strong>Выбрано:</strong>
					<span id="selectedCount">0</span>
				</div>

			<div class="bulk-actions">
				<button type="button" class="btn btn-small" id="bulkSelectPage">Выбрать страницу</button>
				<button type="button" class="btn btn-small" id="bulkClear">Снять выбор</button>
				<button type="button" class="btn btn-small" id="bulkCopyIds">Копировать ID</button>
				<button type="button" class="btn btn-small" id="bulkPriceList">Прайс-лист</button>

				<button type="button" class="btn btn-small" id="bulkOpenAction">Action</button>
				<button type="button" class="btn btn-small" id="bulkOpenVirtual">Virtual</button>
			</div>
			</div>
            <table>
                <thead>
					 <tr>
						<th style="width:36px;">
							<input type="checkbox" id="selectAllRows">
						</th>
						<th><?php echo catalogSortLink('id_off', 'id_off', $state, $basePath); ?></th>
						<th><?php echo catalogSortLink('Артикул', 'product_article', $state, $basePath); ?></th>
						<th><?php echo catalogSortLink('Название', 'name', $state, $basePath); ?></th>
						<th><?php echo catalogSortLink('Закупка', 'price_cost', $state, $basePath); ?></th>
						<th><?php echo catalogSortLink('Продажа', 'price', $state, $basePath); ?></th>
						<th>Action</th>
						<th>Остаток</th>
						<th>Фото</th>
						<th>Действия</th>
					</tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)) { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $isSelected = ((int)$row['id_off'] === (int)$selected);
                        $selectUrl = ViewHelper::buildUrl($basePath, array(
                            'search'   => $search,
                            'sort'     => $sort,
                            'order'    => $order,
                            'page'     => $page,
                            'selected' => (int)$row['id_off'],
                        ));
                        ?>                    
						<tr

							class="<?php echo $isSelected ? 'selected-row' : ''; ?> js-row-click"
							data-url="<?php echo ViewHelper::h($selectUrl); ?>"
							data-product-id="<?php echo (int)$row['id_off']; ?>"
							data-product-article="<?php echo ViewHelper::h((string)$row['product_article']); ?>"
							data-product-name="<?php echo ViewHelper::h((string)$row['name']); ?>"
							data-product-price="<?php echo ViewHelper::h($row['price'] !== null ? (string)$row['price'] : ''); ?>"
							data-product-action-price="<?php echo ViewHelper::h($row['action_price'] !== null ? (string)$row['action_price'] : ''); ?>"
						>

								<td>
									<input
										type="checkbox"
										class="row-selector"
										value="<?php echo (int)$row['id_off']; ?>"
									>
								</td>

								<td class="num">
									<a href="<?php echo ViewHelper::h($selectUrl); ?>">
										<?php echo (int)$row['id_off']; ?>
									</a>
								</td>
                            <td><?php echo renderValue($row['product_article']); ?></td>
                            <td>
                                <a href="<?php echo ViewHelper::h($selectUrl); ?>">
                                    <?php echo renderValue($row['name']); ?>
                                </a>
                            </td>
                            <td class="num"><?php echo renderPrice($row['price_cost']); ?></td>
                            <td class="num"><?php echo renderPrice($row['price']); ?></td>
                            <td class="num">
                                <?php if ($row['action_price'] !== null) { ?>
                                    <span class="status-badge status-action"><?php echo renderPrice($row['action_price']); ?></span>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
                            <td class="num"><?php echo (int)$row['real_stock']; ?></td>
                            <td class="num">
                                <?php if ($row['main_image'] !== '') { ?>
                                    <a
                                        href="#"
                                        class="photo-btn js-open-image"
                                        data-image="<?php echo ViewHelper::h($row['main_image']); ?>"
                                        title="Открыть фото"
                                    >👁</a>
                                <?php } else { ?>
                                    —
                                <?php } ?>
                            </td>
							<td>
								<div class="action-menu">
									<button type="button" class="action-menu-toggle">☰</button>

									<div class="action-menu-dropdown">
										<a
											href="/action?action=edit&product_id=<?php echo (int)$row['id_off']; ?>&search=<?php echo (int)$row['id_off']; ?>&filter=all&sort=product_id&order=asc&page=1"
											target="_blank"
										>Акция</a>

										<a
											href="/virtual?action=edit&product_id=<?php echo (int)$row['id_off']; ?>&search=<?php echo (int)$row['id_off']; ?>&filter=all&sort=product_id&order=asc&page=1"
											target="_blank"
										>Виртуальный остаток</a>
									</div>
								</div>
							</td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="9" class="empty">Данные не найдены.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(catalogPageLink($page - 1, $state, $basePath)); ?>">← Назад</a>
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
                            <a href="<?php echo ViewHelper::h(catalogPageLink($p, $state, $basePath)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo ViewHelper::h(catalogPageLink($page + 1, $state, $basePath)); ?>">Вперёд →</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <div class="card sticky-panel">
            <?php if ($details !== null) { ?>
                <div class="section">
				<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">

					<div style="flex:1;">
						<div style="font-size:22px;font-weight:bold;line-height:1.25;margin-bottom:8px;">
							<?php echo renderValue($details['name']); ?>
						</div>

						<div class="top-status-badges">
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
							<?php } else { ?>
								<span class="status-pill status-pill-off">Нет в наличии</span>
							<?php } ?>
						</div>
					</div>

					<div class="nav-arrows">
						<button class="nav-arrow" id="navPrev" type="button">←</button>
						<button class="nav-arrow" id="navNext" type="button">→</button>
					</div>

				</div>



                    <div class="info-grid">
                        <div class="k">id_off</div>
                        <div class="v"><?php echo (int)$details['id_off']; ?></div>

                        <div class="k">product_id</div>
                        <div class="v"><?php echo (int)$details['product_id']; ?></div>
						<div class="k">EAN</div>
						<div class="v"><?php echo renderValue($details['ean']); ?></div>

                        <div class="k">Производитель</div>
                        <div class="v"><?php echo renderValue($details['manufacturer_name']); ?></div>

						<div class="k">Категория</div>
						<div class="v"><?php echo renderValue($details['category_name']); ?></div>
                    </div>
                </div>

                <div class="section">
                    <h3>Фото</h3>

                    <?php if ($details['main_image'] !== '') { ?>
                        <img
                            src="<?php echo ViewHelper::h($details['main_image']); ?>"
                            alt=""
                            class="main-photo js-open-image"
                            data-image="<?php echo ViewHelper::h($details['main_image']); ?>"
                        >
                    <?php } else { ?>
                        <div class="mini-note">Главное фото отсутствует.</div>
                    <?php } ?>

                    <?php if (!empty($details['additional_images'])) { ?>
                        <div class="thumbs">
                            <?php foreach ($details['additional_images'] as $img) { ?>
                                <img
                                    src="<?php echo ViewHelper::h($img); ?>"
                                    alt=""
                                    class="js-open-image"
                                    data-image="<?php echo ViewHelper::h($img); ?>"
                                >
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

				<div class="section">
					<h3>Цены</h3>

					<div class="price-card">
						<div class="price-group">
							<div class="price-group-title">Основные</div>

							<div class="price-main-row price-main-row-2">
								<div class="price-main-item">
									<div class="price-label">Продажа</div>
									<div class="price-value"><?php echo renderPrice($details['price']); ?></div>
								</div>

								<div class="price-main-item">
									<div class="price-label">Акционная</div>
									<div class="price-value">
										<?php if ($details['special'] !== null) { ?>
											<span
												class="price-sale-text"
												title="Акция действует: <?php echo ViewHelper::h($details['special']['date_start']); ?> — <?php echo ViewHelper::h($details['special']['date_end']); ?>"
											>
												<?php echo renderPrice($details['special']['price']); ?>
											</span>
										<?php } else { ?>
											—
										<?php } ?>
									</div>
								</div>
							</div>
						</div>

						<div class="price-group">
							<div class="price-group-title">Скидки от количества</div>

							<?php if (!empty($details['discounts']['quantity_discounts'])) { ?>
								<div class="price-discounts">
									<?php foreach ($details['discounts']['quantity_discounts'] as $discount) { ?>
										<span class="discount-chip">
											от <?php echo (int)$discount['quantity']; ?> шт — <?php echo renderPrice($discount['price']); ?>
										</span>
									<?php } ?>
								</div>
							<?php } else { ?>
								<div class="price-empty">Нет</div>
							<?php } ?>
						</div>

						<div class="price-group">
							<div class="price-group-title">Спеццены</div>

							<div class="price-sub-row">
								<div class="price-sub-item">
									<div class="price-label">Оптовая</div>
									<div class="price-value">
										<?php
										echo isset($details['discounts']['wholesale_price']) && $details['discounts']['wholesale_price'] !== null
											? renderPrice($details['discounts']['wholesale_price'])
											: '—';
										?>
									</div>
								</div>

								<div class="price-sub-item">
									<div class="price-label">Дилерская</div>
									<div class="price-value">
										<?php
										echo isset($details['discounts']['dealer_price']) && $details['discounts']['dealer_price'] !== null
											? renderPrice($details['discounts']['dealer_price'])
											: '—';
										?>
									</div>
								</div>
							</div>
						</div>

						<div class="price-group price-group-muted">
							<div class="price-group-title">Служебные</div>

							<div class="price-sub-row">
								<div class="price-sub-item">
									<div class="price-label">Закупочная</div>
									<div class="price-value"><?php echo renderPrice($details['price_cost']); ?></div>
								</div>

								<div class="price-sub-item">
									<div class="price-label">RRP</div>
									<div class="price-value"><?php echo renderPrice($details['price_rrp']); ?></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="section">
					<h3>Остатки</h3>

					<div class="stock-card">
						<div class="stock-grid">
							<div class="stock-item">
								<div class="stock-label">На сайте</div>
								<div class="stock-value"><?php echo renderPrice($details['quantity']); ?></div>
							</div>

							<div class="stock-item">
								<div class="stock-label">Реальный</div>
								<div class="stock-value"><?php echo (int)$details['real_stock']; ?></div>
							</div>

							<div class="stock-item">
								<div class="stock-label">Виртуальный</div>
								<div class="stock-value"><?php echo (int)$details['virtual_stock']; ?></div>
							</div>
						</div>
					</div>
				</div>

                <div class="section">
                    <h3>Контент</h3>

                    <div class="tabs">
                        <button type="button" class="tab-btn active" data-tab="lang-2">UA</button>
                        <button type="button" class="tab-btn" data-tab="lang-1">RU</button>
                    </div>

				<div id="lang-2" class="tab-pane active">
					<div class="content-card">
						<div class="content-field">
							<div class="content-label">Название</div>
							<div class="content-inline-value"><?php echo renderValue($details['descriptions'][2]['name']); ?></div>
						</div>

						<div class="content-field">
							<div class="content-label">Полное описание</div>
							<div class="scroll-box"><?php echo renderValue($details['descriptions'][2]['description']); ?></div>
						</div>

						<div class="content-field">
							<div class="content-label">Краткое описание</div>
							<div class="scroll-box"><?php echo renderValue($details['descriptions'][2]['short_description']); ?></div>
						</div>

						<div class="seo-card">
							<div class="seo-card-title">SEO</div>

							<div class="seo-row">
								<div class="seo-label">Meta title</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][2]['meta_title']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">Meta description</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][2]['meta_description']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">Meta H1</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][2]['meta_h1']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">SEO URL</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][2]['seo_url']); ?></div>
							</div>
						</div>
					</div>
				</div>

				<div id="lang-1" class="tab-pane">
					<div class="content-card">
						<div class="content-field">
							<div class="content-label">Название</div>
							<div class="content-inline-value"><?php echo renderValue($details['descriptions'][1]['name']); ?></div>
						</div>

						<div class="content-field">
							<div class="content-label">Полное описание</div>
							<div class="scroll-box"><?php echo renderValue($details['descriptions'][1]['description']); ?></div>
						</div>

						<div class="content-field">
							<div class="content-label">Краткое описание</div>
							<div class="scroll-box"><?php echo renderValue($details['descriptions'][1]['short_description']); ?></div>
						</div>

						<div class="seo-card">
							<div class="seo-card-title">SEO</div>

							<div class="seo-row">
								<div class="seo-label">Meta title</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][1]['meta_title']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">Meta description</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][1]['meta_description']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">Meta H1</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][1]['meta_h1']); ?></div>
							</div>

							<div class="seo-row">
								<div class="seo-label">SEO URL</div>
								<div class="seo-value"><?php echo renderValue($details['descriptions'][1]['seo_url']); ?></div>
							</div>
						</div>
					</div>
				</div>
                </div>
				<div class="section">
					<h3>Характеристики</h3>

					<div class="specs-card">
						<div class="spec-row">
							<div class="spec-label">Вес</div>
							<div class="spec-value"><?php echo renderWeightValue($details['weight'], $details['weight_class_id']); ?></div>
						</div>

						<div class="spec-row">
							<div class="spec-label">Размеры</div>
							<div class="spec-value"><?php echo renderDimensionsValue($details['length'], $details['width'], $details['height'], $details['length_class_id']); ?></div>
						</div>
					</div>
				</div>
                <div class="section">
                    <h3>Ссылки и интеграции</h3>
                    <div class="info-grid">
                        <div class="k">SEO URL</div>
                        <div class="v"><?php echo renderValue($details['seo_url']); ?></div>

                        <div class="k">link_off</div>
                        <div class="v"><?php echo renderLinkValue($details['link_off']); ?></div>

                        <div class="k">links_mf</div>
                        <div class="v"><?php echo renderLinkValue($details['links_mf']); ?></div>

                        <div class="k">links_prm</div>
                        <div class="v"><?php echo renderLinkValue($details['links_prm']); ?></div>

                        <div class="k">links_prom</div>
                        <div class="v"><?php echo renderLinkValue($details['links_prom']); ?></div>

                        <div class="k">id_ms</div>
                        <div class="v"><?php echo renderValue($details['id_ms']); ?></div>

                        <div class="k">id_mf</div>
                        <div class="v"><?php echo renderValue($details['id_mf']); ?></div>

                        <div class="k">id_prm</div>
                        <div class="v"><?php echo renderValue($details['id_prm']); ?></div>

                        <div class="k">id_prom</div>
                        <div class="v"><?php echo renderValue($details['id_prom']); ?></div>

                        <div class="k">EAN</div>
                        <div class="v"><?php echo renderValue($details['ean']); ?></div>

                        <div class="k">ТН ВЭД</div>
                        <div class="v"><?php echo renderValue($details['tnved']); ?></div>

                        <div class="k">Ед. изм.</div>
                        <div class="v"><?php echo renderValue($details['unit']); ?></div>

                        <div class="k">Упаковки</div>
                        <div class="v"><?php echo renderValue($details['packs']); ?></div>

                        <div class="k">Дата добавления</div>
                        <div class="v"><?php echo renderValue($details['date_added']); ?></div>

                        <div class="k">Дата обновления</div> 
						<div class="v"><?php echo renderValue($details['date_updated']); ?></div>						
						
						<div class="module-links">
							<a
								href="/action?action=edit&product_id=<?php echo (int)$details['id_off']; ?>&search=<?php echo (int)$details['id_off']; ?>&filter=all&sort=product_id&order=asc&page=1"
								class="btn btn-small"
								target="_blank"
							>Action</a>

							<a
								href="/virtual?action=edit&product_id=<?php echo (int)$details['id_off']; ?>&search=<?php echo (int)$details['id_off']; ?>&filter=all&sort=product_id&order=asc&page=1"
								class="btn btn-small"
								target="_blank"
							>Virtual</a>
						</div>			
                    </div>
							<?php if (!empty($details['ean'])) { ?>
								<div class="ean-barcode-wrapper">
									<svg id="eanBarcode" data-ean="<?php echo ViewHelper::h($details['ean']); ?>"></svg>
								</div>
							<?php } ?>
			    </div>
            <?php } else { ?>
                <h2>Карточка товара</h2>
                <div class="mini-note">Товар не выбран.</div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal" id="imageModal">
    <div class="modal-content">
        <button type="button" class="modal-close" id="imageModalClose">×</button>
        <img src="" alt="" class="modal-image" id="imageModalImg">
    </div>
</div>
<div class="modal" id="priceListModal">
	<div class="modal-content" style="width: 100%; max-width: 760px;">
		<div class="card" style="margin:0; padding:20px;">
			<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;">
				<h3 style="margin:0;">Прайс-лист по выбранным товарам</h3>
				<button type="button" class="modal-close" id="priceListModalClose" style="position:static;">×</button>
			</div>

			<div class="mini-note" style="margin-bottom:10px;">
				Готовый текст можно скопировать и отправить клиенту.
			</div>

			<textarea id="priceListText" style="width:100%;min-height:320px;box-sizing:border-box;padding:12px;border:1px solid #d9e0ea;border-radius:10px;font:14px/1.5 Arial,sans-serif;resize:vertical;"></textarea>

			<div class="btn-row" style="margin-top:12px;">
				<button type="button" class="btn btn-primary" id="copyPriceList">Скопировать</button>
				<button type="button" class="btn" id="closePriceList">Закрыть</button>
			</div>
		</div>
	</div>
</div>

<script>

    var modal = document.getElementById('imageModal');
    var modalImg = document.getElementById('imageModalImg');
    var modalClose = document.getElementById('imageModalClose');
    var photoTriggers = document.querySelectorAll('.js-open-image');
	
	var rows = document.querySelectorAll('.js-row-click');
	var selectedRow = document.querySelector('.selected-row');

	var index = -1;

rows.forEach(function(r,i){
    if(r === selectedRow){
        index = i;
    }
});

document.getElementById('navPrev')?.addEventListener('click',function(){

    if(index <= 0) return;

    var prev = rows[index-1].getAttribute('data-url');

    if(prev){
        window.location = prev;
    }

});

document.getElementById('navNext')?.addEventListener('click',function(){

    if(index === -1 || index >= rows.length-1) return;

    var next = rows[index+1].getAttribute('data-url');

    if(next){
        window.location = next;
    }

});
	
(function () {
	document.addEventListener('click', function (e) {

		if (e.target.classList.contains('action-menu-toggle')) {
			e.preventDefault();

			var menu = e.target.closest('.action-menu');

			document.querySelectorAll('.action-menu').forEach(function (m) {
				if (m !== menu) {
					m.classList.remove('open');
				}
			});

			menu.classList.toggle('open');
			return;
		}

		document.querySelectorAll('.action-menu').forEach(function (m) {
			if (!m.contains(e.target)) {
				m.classList.remove('open');
			}
		});

	});
	
	document.querySelectorAll('.js-row-click').forEach(function(row){

			row.addEventListener('click', function(e){

			if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input')) {
				return;
			}

				var url = this.getAttribute('data-url');

				if (url) {
					window.location = url;
				}

			});

		});
	

    function openModal(src) {
        if (!src) {
            return;
        }
        modalImg.src = src;
        modal.classList.add('open');
    }

    function closeModal() {
        modal.classList.remove('open');
        modalImg.src = '';
    }

    for (var i = 0; i < photoTriggers.length; i++) {
        photoTriggers[i].addEventListener('click', function (e) {
            e.preventDefault();
            openModal(this.getAttribute('data-image') || this.getAttribute('src'));
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
	

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    var tabButtons = document.querySelectorAll('.tab-btn');

    for (var j = 0; j < tabButtons.length; j++) {
        tabButtons[j].addEventListener('click', function () {
            var target = this.getAttribute('data-tab');
            var panes = document.querySelectorAll('.tab-pane');

            for (var k = 0; k < tabButtons.length; k++) {
                tabButtons[k].classList.remove('active');
            }

            for (var p = 0; p < panes.length; p++) {
                panes[p].classList.remove('active');
            }

            this.classList.add('active');

            var pane = document.getElementById(target);
            if (pane) {
                pane.classList.add('active');
            }
        });
    }
	
var STORAGE_KEY = 'papir_catalog_selected_products';

var selectAllRows = document.getElementById('selectAllRows');
var rowSelectors = document.querySelectorAll('.row-selector');
var selectedCount = document.getElementById('selectedCount');

var bulkSelectPage = document.getElementById('bulkSelectPage');
var bulkClear = document.getElementById('bulkClear');
var bulkCopyIds = document.getElementById('bulkCopyIds');
var bulkOpenAction = document.getElementById('bulkOpenAction');
var bulkOpenVirtual = document.getElementById('bulkOpenVirtual');
var bulkPriceList = document.getElementById('bulkPriceList');

var priceListModal = document.getElementById('priceListModal');
var priceListModalClose = document.getElementById('priceListModalClose');
var closePriceList = document.getElementById('closePriceList');
var copyPriceList = document.getElementById('copyPriceList');
var priceListText = document.getElementById('priceListText');

function loadSelectedProducts() {
    try {
        var raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function saveSelectedProducts() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedProducts));
}

var selectedProducts = loadSelectedProducts();

function getSelectedIds() {
    return Object.keys(selectedProducts);
}

function getRowProductDataByCheckbox(checkbox) {
    var row = checkbox.closest('tr');
    if (!row) {
        return null;
    }

    var id = row.getAttribute('data-product-id') || '';
    if (!id) {
        return null;
    }

    return {
        id: id,
        article: row.getAttribute('data-product-article') || '',
        name: row.getAttribute('data-product-name') || '',
        price: row.getAttribute('data-product-price') || '',
        action_price: row.getAttribute('data-product-action-price') || ''
    };
}

function addSelectedProduct(product) {
    if (!product || !product.id) {
        return;
    }

    selectedProducts[product.id] = product;
    saveSelectedProducts();
}

function removeSelectedProduct(id) {
    if (!id) {
        return;
    }

    delete selectedProducts[id];
    saveSelectedProducts();
}

function syncCheckboxesFromStorage() {
    rowSelectors.forEach(function (checkbox) {
        checkbox.checked = !!selectedProducts[checkbox.value];
    });
}

function refreshSelectedCounter() {
    var ids = getSelectedIds();

    if (selectedCount) {
        selectedCount.textContent = ids.length;
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
        selectAllRows.indeterminate = (checked > 0 && checked < total);
    }
}

function openPriceListModal(text) {
    if (!priceListModal || !priceListText) {
        return;
    }

    priceListText.value = text;
    priceListModal.classList.add('open');
}

function closePriceListModal() {
    if (!priceListModal) {
        return;
    }

    priceListModal.classList.remove('open');
}

function formatPrice(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    var num = parseFloat(value);
    if (isNaN(num)) {
        return value;
    }

    return num.toFixed(2).replace('.', ',');
}

function buildPriceListText() {
    var ids = getSelectedIds();

    if (!ids.length) {
        return '';
    }

    var lines = ['Прайс по выбранным товарам:', ''];

    ids.forEach(function (id, index) {
        var item = selectedProducts[id];
        if (!item) {
            return;
        }

        var effectivePrice = item.action_price !== '' ? item.action_price : item.price;
        var parts = [];

        parts.push((index + 1) + '.');

        if (item.article) {
            parts.push('[' + item.article + ']');
        }

        parts.push(item.name || ('Товар #' + item.id));
        parts.push('—');

        if (effectivePrice !== '') {
            parts.push(formatPrice(effectivePrice) + ' грн');
        } else {
            parts.push('цена не указана');
        }

        if (item.action_price !== '') {
            parts.push('(акция)');
        }

        lines.push(parts.join(' '));
    });

    lines.push('');
    lines.push('Цены актуальны на момент отправки.');

    return lines.join('\n');
}

rowSelectors.forEach(function (checkbox) {
    checkbox.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    checkbox.addEventListener('change', function () {
        var product = getRowProductDataByCheckbox(this);

        if (this.checked) {
            addSelectedProduct(product);
        } else {
            removeSelectedProduct(this.value);
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

            var product = getRowProductDataByCheckbox(checkbox);

            if (checked) {
                addSelectedProduct(product);
            } else {
                removeSelectedProduct(checkbox.value);
            }
        });

        refreshSelectedCounter();
    });
}

if (bulkSelectPage) {
    bulkSelectPage.addEventListener('click', function () {
        rowSelectors.forEach(function (checkbox) {
            checkbox.checked = true;
            addSelectedProduct(getRowProductDataByCheckbox(checkbox));
        });

        refreshSelectedCounter();
    });
}

if (bulkClear) {
    bulkClear.addEventListener('click', function () {
        rowSelectors.forEach(function (checkbox) {
            checkbox.checked = false;
        });

        selectedProducts = {};
        saveSelectedProducts();
        refreshSelectedCounter();
    });
}

if (bulkCopyIds) {
    bulkCopyIds.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        navigator.clipboard.writeText(ids.join(',')).then(function () {
            showCopyToast('ID скопированы');
        }).catch(function () {
            alert('Не удалось скопировать ID');
        });
    });
}

if (bulkOpenAction) {
    bulkOpenAction.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        window.open('/action?product_ids=' + encodeURIComponent(ids.join(',')), '_blank');
    });
}

if (bulkOpenVirtual) {
    bulkOpenVirtual.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        window.open('/virtual?product_ids=' + encodeURIComponent(ids.join(',')), '_blank');
    });
}

function buildPriceListTextFromServerItems(items) {
    if (!items || !items.length) {
        return '';
    }

    var lines = ['Прайс по выбранным товарам:', ''];

    items.forEach(function (item, index) {
        lines.push((index + 1) + '. ' + (item.name || ('Товар #' + item.id)));

        if (item.article) {
            lines.push('Артикул: ' + item.article);
        }

        lines.push('Роздріб: ' + (item.price !== null && item.price !== '' ? formatPrice(item.price) + ' грн' : '—'));

        lines.push('Акція (роздріб): ' + (item.action_price !== null && item.action_price !== '' ? formatPrice(item.action_price) + ' грн' : '—'));

        if (item.quantity_discounts && item.quantity_discounts.length) {
            lines.push('Знижки від кількості:');

            item.quantity_discounts.forEach(function (discount) {
                lines.push('- від ' + discount.quantity + ' шт — ' + formatPrice(discount.price) + ' грн');
            });
        } else {
            lines.push('Знижки від кількості: —');
        }

        lines.push('');
    });

    lines.push('Ціни актуальні на момент відправки.');

    return lines.join('\n');
}

if (bulkPriceList) {
    bulkPriceList.addEventListener('click', function () {
        var ids = getSelectedIds();

        if (!ids.length) {
            alert('Сначала выбери товары.');
            return;
        }

        fetch('/catalog-pricelist', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: 'product_ids=' + encodeURIComponent(ids.join(','))
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.text();
        })
        .then(function (text) {
            console.log('Price list raw response:', text);

            var data = JSON.parse(text);

            if (!data || !data.items || !data.items.length) {
                alert('Не удалось получить данные для прайс-листа.');
                return;
            }

            var textResult = buildPriceListTextFromServerItems(data.items);
            openPriceListModal(textResult);
        })
        .catch(function (err) {
            console.log('Price list error:', err);
            alert('Ошибка при загрузке прайс-листа: ' + err.message);
        });
    });
}

if (copyPriceList) {
    copyPriceList.addEventListener('click', function () {
        if (!priceListText || !priceListText.value) {
            return;
        }

        navigator.clipboard.writeText(priceListText.value).then(function () {
            showCopyToast('Прайс-лист скопирован');
        }).catch(function () {
            alert('Не удалось скопировать прайс-лист');
        });
    });
}

if (priceListModalClose) {
    priceListModalClose.addEventListener('click', closePriceListModal);
}

if (closePriceList) {
    closePriceList.addEventListener('click', closePriceListModal);
}

if (priceListModal) {
    priceListModal.addEventListener('click', function (e) {
        if (e.target === priceListModal) {
            closePriceListModal();
        }
    });
}

syncCheckboxesFromStorage();
refreshSelectedCounter();
	
})();

var searchInput = document.getElementById('search');
var filterInput = document.getElementById('filter');
var searchForm = searchInput ? searchInput.closest('form') : null;

if (filterInput && searchForm) {
    filterInput.addEventListener('change', function () {
        var pageInput = searchForm.querySelector('input[name="page"]');
        if (pageInput) {
            pageInput.value = 1;
        }
        searchForm.submit();
    });
}

if (searchInput && searchForm) {
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();

            var pageInput = searchForm.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = 1;
            }

            searchForm.submit();
        }
    });
}

	    
</script>
<script src="/papir/assets/js/JsBarcode.all.min.js"></script>

<script>
(function () {
    var eanBarcode = document.getElementById('eanBarcode');

    if (eanBarcode && typeof JsBarcode !== 'undefined') {
        var eanValue = eanBarcode.getAttribute('data-ean');

        if (eanValue) {
            try {
				JsBarcode(eanBarcode, eanValue, {
					format: "EAN13",
					displayValue: true,
					fontSize: 16,
					height: 70,
					margin: 0,
					width: 2.2
				});
            } catch (err) {
                console.log('Barcode render error:', err);
            }
        }
    }
})();
	var eanBarcode = document.getElementById('eanBarcode');

		if (eanBarcode && typeof JsBarcode !== 'undefined') {

			var eanValue = eanBarcode.getAttribute('data-ean');

			if (eanValue) {

				try {
					JsBarcode(eanBarcode, eanValue, {
						format: "EAN13",
						displayValue: true,
						fontSize: 14,
						height: 60,
						margin: 0,
						width: 2
					});
				} catch (err) {
					console.log('Barcode render error:', err);
				}

				// копирование по клику
				eanBarcode.addEventListener('click', function(){

					navigator.clipboard.writeText(eanValue).then(function(){

						showCopyToast("EAN скопирован");

					}).catch(function(err){

						console.log("Copy failed:", err);

					});

				});

			}
	}
	function showCopyToast(text){

		var toast = document.createElement('div');

		toast.className = 'copy-toast';
		toast.innerText = text;

		document.body.appendChild(toast);

		setTimeout(function(){
			toast.classList.add('show');
		},20);

		setTimeout(function(){
			toast.classList.remove('show');

			setTimeout(function(){
				toast.remove();
			},250);

		},1500);

	}
</script>
</body>
</html>
<?php
$mysqli_ms->close();
$mysqli_papir->close();
$mysqli_off->close();