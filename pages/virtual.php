<?php

require_once __DIR__ . '/../modules/database/database.php';

$mysqli = connectbd('ms');
$mysqli_papir = connectbd('Papir');

$virtualRepo = new VirtualRepository($mysqli, $mysqli_papir);
$dashboardRepo = new VirtualDashboardRepository($mysqli, $mysqli_papir);

$catalog_total = $dashboardRepo->getCatalogTotalCount();
$virtual_positive_total = $dashboardRepo->getVirtualPositiveCount();


$errors = array();
$basePath = '/virtual';
$perPage = 50;

$action = Request::getString('action');
$product_id = Request::getInt('product_id', 0);

$search = Request::getString('search', '');
$filter = Request::getString('filter', 'all');
$sort = $dashboardRepo->normalizeSort(Request::getString('sort', 'product_id'));
$order = $dashboardRepo->normalizeOrder(Request::getString('order', 'asc'));
$page = max(1, Request::getInt('page', 1));

$state = array(
    'search' => $search,
    'filter' => $filter,
    'sort'   => $sort,
    'order'  => $order,
    'page'   => $page,
);

if (Request::isPost()) {
    $form_action = Request::postString('form_action');

    if ($form_action === 'save') {
        $post_product_id = Request::postInt('product_id', 0);
        $virtual_stock = Request::postInt('virtual_stock', 0);
        $price_cost = (float)Request::postString('price_cost', '0');
        $price = (float)Request::postString('price', '0');
        $price_rrp = (float)Request::postString('price_rrp', '0');

        if ($post_product_id <= 0) {
            $errors[] = 'Product ID должен быть больше нуля.';
        }

        if ($virtual_stock < 0) {
            $errors[] = 'Виртуальный остаток не может быть меньше 0.';
        }

        if ($price_cost < 0) {
            $errors[] = 'Закупочная цена не может быть меньше 0.';
        }

        if ($price < 0) {
            $errors[] = 'Цена не может быть меньше 0.';
        }

        if ($price_rrp < 0) {
            $errors[] = 'Рекомендованная цена не может быть меньше 0.';
        }

        if (empty($errors)) {
            $saveResult = $virtualRepo->save(
                $post_product_id,
                $virtual_stock,
                $price_cost,
                $price,
                $price_rrp
            );

            if ($saveResult !== true) {
                $errors[] = $saveResult;
            } else {
                ViewHelper::redirect($basePath, $state);
            }
        }
    }
}

if ($action === 'delete' && $product_id > 0) {
    $virtualRepo->deleteVirtual($product_id);
    ViewHelper::redirect($basePath, $state);
}

$edit_row = $dashboardRepo->getDefaultEditRow();

if ($action === 'edit' && $product_id > 0) {
    $foundEditRow = $virtualRepo->getEditRow($product_id);

    if ($foundEditRow !== null) {
        $edit_row = $foundEditRow;
    }
}

$total_rows = $dashboardRepo->getTotalRows($search, $filter);
$total_virtual_rows = $dashboardRepo->getVirtualCount();

$paginator = new Paginator($page, $perPage, $total_rows);
$page = $paginator->page;
$total_pages = $paginator->totalPages;
$offset = $paginator->offset;

$state['page'] = $page;

$list_result = $dashboardRepo->getList(
    $search,
    $filter,
    $sort,
    $order,
    $offset,
    $perPage
);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Virtual Dashboard</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }
        .wrap {
            max-width: 1500px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .title {
            margin: 0 0 6px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 20px;
        }
        .card {
            background: #fff;
            border: 1px solid #d9e0ea;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card h2 {
            margin-top: 0;
            font-size: 20px;
        }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #c8d1dd;
            border-radius: 8px;
            margin-bottom: 14px;
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
        }
        .btn-primary {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
        .btn-danger {
            color: #b42318;
            border-color: #f3c0c0;
            background: #fff7f7;
        }
        .btn-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filters {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) 220px;
            gap: 10px;
            margin-bottom: 16px;
            align-items: end;
        }
        .filters-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filters-actions .btn {
            min-width: 140px;
            text-align: center;
            box-sizing: border-box;
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
            vertical-align: top;
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
        .error {
            background: #fff1f1;
            border: 1px solid #f0c2c2;
            color: #9b1c1c;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
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
        .num {
            white-space: nowrap;
        }
        @media (max-width: 1200px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .filters {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-side {
            text-align: right;
            min-width: 260px;
        }
		.dashboard-side {
			text-align: right;
			min-width: 260px;
		}
		.action-buttons-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px;
			margin-top: 14px;
		}

		.action-buttons-grid .btn {
			text-align: center;
			box-sizing: border-box;
		}
    </style>
</head>
<body>
<div class="wrap">
	<div class="topbar">
		<div>
			<h1 class="title">Virtual Dashboard</h1>
			<div class="subtitle">
				Основа — <strong>Papir.product_papir</strong>.
				Название берётся из <strong>product_description</strong> с приоритетом language_id=2, затем 1.
			</div>
		</div>

		<div class="dashboard-side">
			<div class="badge">Всего в каталоге: <?php echo (int)$catalog_total; ?></div>
			<div class="badge">С виртуальным остатком: <?php echo (int)$virtual_positive_total; ?></div>
			<div class="badge">Найдено по фильтру: <?php echo (int)$total_rows; ?></div>

			<div class="action-buttons-grid">
			<a href="action-update-stock" class="btn btn-primary" target="_blank">
					Обновить stock
				</a>
				<a href="/virtual-update-site" class="btn btn-primary" target="_blank">
					Обновить остатки сайта (stock + virtual)
				</a>

				<a href="/virtual" class="btn">
					Обновить страницу
				</a>
			</div>
		</div>
	</div>

    <div class="grid">
        <div class="card">
            <h2><?php echo ($action === 'edit' && $product_id > 0) ? 'Редактирование товара' : 'Редактирование / создание virtual'; ?></h2>

            <?php if (!empty($errors)) { ?>
                <?php foreach ($errors as $error) { ?>
                    <div class="error"><?php echo ViewHelper::h($error); ?></div>
                <?php } ?>
            <?php } ?>

            <form method="post" action="<?php echo ViewHelper::h(ViewHelper::buildUrl($basePath, $state)); ?>">
                <input type="hidden" name="form_action" value="save">

                <label for="product_id">Код</label>
                <input
                    type="number"
                    name="product_id"
                    id="product_id"
                    value="<?php echo ViewHelper::h($edit_row['product_id']); ?>"
                    <?php echo ($action === 'edit' && $product_id > 0) ? 'readonly' : ''; ?>
                >

                <label>Наименование</label>
                <input type="text" value="<?php echo ViewHelper::h($edit_row['name']); ?>" readonly>

                <label>Реальный остаток</label>
                <input type="text" value="<?php echo ViewHelper::h($edit_row['real_stock']); ?>" readonly>

                <label for="virtual_stock">Виртуальный остаток</label>
                <input type="number" name="virtual_stock" id="virtual_stock" value="<?php echo ViewHelper::h($edit_row['virtual_stock']); ?>" min="0">

                <label for="price_cost">Закупочная цена</label>
                <input type="text" name="price_cost" id="price_cost" value="<?php echo ViewHelper::h($edit_row['price_cost']); ?>">

                <label for="price">Цена</label>
                <input type="text" name="price" id="price" value="<?php echo ViewHelper::h($edit_row['price']); ?>">

                <label for="price_rrp">Рекомендованная цена</label>
                <input type="text" name="price_rrp" id="price_rrp" value="<?php echo ViewHelper::h($edit_row['price_rrp']); ?>">

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="/virtual" class="btn">Сбросить</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Товары</h2>

            <form method="get" class="filters" action="/virtual">
                <div>
                    <label for="search">Поиск</label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="<?php echo ViewHelper::h($search); ?>"
                        placeholder="Поиск по коду или названию"
                    >
                </div>

                <div>
                    <label for="filter">Фильтр</label>
                    <select name="filter" id="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все товары</option>
                        <option value="with_virtual" <?php echo $filter === 'with_virtual' ? 'selected' : ''; ?>>Только из virtual</option>
                        <option value="without_virtual" <?php echo $filter === 'without_virtual' ? 'selected' : ''; ?>>Только без virtual</option>
                    </select>
                </div>

                <div class="filters-actions">
                    <button type="submit" class="btn">Применить</button>
                    <a href="/virtual" class="btn">Сброс</a>
                </div>
            </form>

            <table>
                <thead>
                <tr>
                    <th><?php echo TableHelper::sortLink('Код', 'product_id', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('Наименование', 'name', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('Virtual', 'virtual_stock', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('Stock', 'real_stock', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('Закупка', 'price_cost', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('Цена', 'price', $state, $basePath); ?></th>
                    <th><?php echo TableHelper::sortLink('RRP', 'price_rrp', $state, $basePath); ?></th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($list_result && $list_result->num_rows > 0) { ?>
                    <?php while ($row = $list_result->fetch_assoc()) { ?>
                        <?php $hasVirtual = ($row['has_virtual_row'] === true); ?>
                        <tr>
                            <td class="num"><?php echo (int)$row['product_id']; ?></td>
                            <td><?php echo ViewHelper::h($row['name']); ?></td>
                            <td class="num"><?php echo (int)$row['virtual_stock']; ?></td>
                            <td class="num"><?php echo (int)$row['real_stock']; ?></td>
                            <td class="num"><?php echo ViewHelper::h($row['price_cost']); ?></td>
                            <td class="num"><?php echo ViewHelper::h($row['price']); ?></td>
                            <td class="num"><?php echo ViewHelper::h($row['price_rrp']); ?></td>
                            <td><?php echo $hasVirtual ? 'Есть в virtual' : 'Нет в virtual'; ?></td>
                            <td>
                                <div class="btn-row">
                                    <a
                                        href="<?php echo ViewHelper::h(TableHelper::pageLink($page, $state, $basePath) . '&action=edit&product_id=' . (int)$row['product_id']); ?>"
                                        class="btn"
                                    >
                                        <?php echo $hasVirtual ? 'Редактировать' : 'Добавить'; ?>
                                    </a>

                                    <?php if ($hasVirtual) { ?>
                                        <a
                                            href="<?php echo ViewHelper::h(TableHelper::pageLink($page, $state, $basePath) . '&action=delete&product_id=' . (int)$row['product_id']); ?>"
                                            class="btn btn-danger"
                                            onclick="return confirm('Удалить запись из virtual?');"
                                        >
                                            Удалить virtual
                                        </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="9">Данные не найдены.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(TableHelper::pageLink($page - 1, $state, $basePath)); ?>">← Назад</a>
                    <?php } ?>

                    <?php
                    $startPage = $page - 3;
                    $endPage = $page + 3;

                    if ($startPage < 1) {
                        $startPage = 1;
                    }

                    if ($endPage > $total_pages) {
                        $endPage = $total_pages;
                    }
                    ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++) { ?>
                        <?php if ($p == $page) { ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php } else { ?>
                            <a href="<?php echo ViewHelper::h(TableHelper::pageLink($p, $state, $basePath)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $total_pages) { ?>
                        <a href="<?php echo ViewHelper::h(TableHelper::pageLink($page + 1, $state, $basePath)); ?>">Вперёд →</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
</body>
</html>
<?php
$mysqli->close();
$mysqli_papir->close();