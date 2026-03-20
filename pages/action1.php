<?php

require_once '/var/sqript/products/confif_bp.php';

$mysqli = connectbd('ms');
$errors = array();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectToList($query = '') {
    $url = '/action';
    if ($query !== '') {
        $url .= '?' . $query;
    }
    header('Location: ' . $url);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all'; // all | with_action | without_action
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'product_id';
$order = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$per_page = 50;

$allowedSort = array(
    'product_id' => 'product_id',
    'name' => 'name',
    'quantity' => 'quantity',
    'price' => 'price',
    'total_sum' => 'total_sum',
    'discount' => 'discount',
    'super_discont' => 'super_discont'
);

if (!isset($allowedSort[$sort])) {
    $sort = 'product_id';
}

if ($order !== 'asc' && $order !== 'desc') {
    $order = 'asc';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = isset($_POST['form_action']) ? trim($_POST['form_action']) : '';

    if ($form_action === 'save') {
        $post_product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $discount = isset($_POST['discount']) && $_POST['discount'] !== '' ? (int)$_POST['discount'] : 0;
        $super_discont = isset($_POST['super_discont']) && $_POST['super_discont'] !== '' ? (int)$_POST['super_discont'] : 0;

        if ($post_product_id <= 0) {
            $errors[] = 'Product ID должен быть больше нуля.';
        }

        if ($discount < 0 || $discount > 100) {
            $errors[] = 'Discount должен быть от 0 до 100.';
        }

        if ($super_discont < 0 || $super_discont > 100) {
            $errors[] = 'Super Discount должен быть от 0 до 100.';
        }

        if (empty($errors)) {
            $check_sql = "SELECT product_id FROM `action` WHERE product_id = '" . $post_product_id . "' LIMIT 1";
            $check_result = $mysqli->query($check_sql);

            if ($check_result && $check_result->num_rows > 0) {
                $sql = "UPDATE `action`
                        SET `discount` = '" . $discount . "',
                            `super_discont` = '" . $super_discont . "',
                            `updated_at` = NOW()
                        WHERE `product_id` = '" . $post_product_id . "'";
            } else {
                $sql = "INSERT INTO `action`
                        SET `product_id` = '" . $post_product_id . "',
                            `discount` = '" . $discount . "',
                            `super_discont` = '" . $super_discont . "',
                            `updated_at` = NOW()";
            }

            if (!$mysqli->query($sql)) {
                $errors[] = 'Ошибка сохранения: ' . $mysqli->error;
            } else {
                $query = http_build_query(array(
                    'search' => $search,
                    'filter' => $filter,
                    'sort' => $sort,
                    'order' => $order,
                    'page' => $page
                ));
                redirectToList($query);
            }
        }
    }
}

if ($action === 'delete' && $product_id > 0) {
    $sql = "DELETE FROM `action` WHERE `product_id` = '" . $product_id . "'";
    $mysqli->query($sql);
    $query = http_build_query(array(
        'search' => $search,
        'filter' => $filter,
        'sort' => $sort,
        'order' => $order,
        'page' => $page
    ));
    redirectToList($query);
}

$edit_row = array(
    'product_id' => '',
    'discount' => 0,
    'super_discont' => 0,
    'name' => '',
    'stock' => 0,
    'price' => 0,
);


if ($action === 'edit' && $product_id > 0) {
    $sql = "SELECT
                CAST(s.`model` AS UNSIGNED) AS product_id,
                s.`name`,
                s.`stock`,
                s.`price`,
                COALESCE(a.`discount`, 0) AS discount,
                COALESCE(a.`super_discont`, 0) AS super_discont
            FROM `stock_` s
            LEFT JOIN `action` a
                ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
            WHERE CAST(s.`model` AS UNSIGNED) = '" . $product_id . "'
              AND s.`stock` > 0
            LIMIT 1";

    $res = $mysqli->query($sql);
    if ($res && $res->num_rows > 0) {
        $edit_row = $res->fetch_assoc();
    }
}

$updatedAt = '';
$resUpdated = $mysqli->query("SELECT `date` FROM `stock` WHERE `number` = 1 LIMIT 1");
if ($resUpdated && $rowUpdated = $resUpdated->fetch_assoc()) {
    $updatedAt = isset($rowUpdated['date']) ? $rowUpdated['date'] : '';
}

$totalStockSum = 0;
$resSum = $mysqli->query("SELECT SUM(`stock` * `price`) AS total_stock_sum FROM `stock_` WHERE `stock` > 0");
if ($resSum && $rowSum = $resSum->fetch_assoc()) {
    $totalStockSum = isset($rowSum['total_stock_sum']) ? (float)$rowSum['total_stock_sum'] : 0;
}

$whereParts = array();
$whereParts[] = "s.`stock` > 0";

if ($search !== '') {
    $search_esc = $mysqli->real_escape_string($search);
    $whereParts[] = "(CAST(s.`model` AS CHAR) LIKE '%" . $search_esc . "%' OR s.`name` LIKE '%" . $search_esc . "%')";
}

if ($filter === 'with_action') {
    $whereParts[] = "a.`product_id` IS NOT NULL";
} elseif ($filter === 'without_action') {
    $whereParts[] = "a.`product_id` IS NULL";
}

$whereSql = '';
if (!empty($whereParts)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
}

$countSql = "SELECT COUNT(*) AS total_rows
             FROM `stock_` s
             LEFT JOIN `action` a
                ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
             " . $whereSql;
$countRes = $mysqli->query($countSql);
$total_rows = 0;
if ($countRes && $countRow = $countRes->fetch_assoc()) {
    $total_rows = (int)$countRow['total_rows'];
}

$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $per_page) : 1;

if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$orderByMap = array(
    'product_id' => 'product_id',
    'name' => 'name',
    'quantity' => 'quantity',
    'price' => 'price',
    'total_sum' => 'total_sum',
    'discount' => 'discount',
    'super_discont' => 'super_discont'
);
$orderBy = $orderByMap[$sort] . ' ' . strtoupper($order);

$list_sql = "SELECT
                CAST(s.`model` AS UNSIGNED) AS product_id,
                s.`name`,
                s.`stock`,
                s.`price`,
                (s.`stock` * s.`price`) AS total_sum,
                a.`discount`,
                a.`super_discont`
            FROM `stock_` s
            LEFT JOIN `action` a
                ON a.`product_id` = CAST(s.`model` AS UNSIGNED)
            " . $whereSql . "
            ORDER BY " . $orderBy . "
            LIMIT " . (int)$offset . ", " . (int)$per_page;
$list_result = $mysqli->query($list_sql);

function sortLink($label, $field, $search, $filter, $sort, $order) {
    $newOrder = 'asc';
    if ($sort === $field && $order === 'asc') {
        $newOrder = 'desc';
    }

    $query = http_build_query(array(
        'search' => $search,
        'filter' => $filter,
        'sort' => $field,
        'order' => $newOrder,
        'page' => 1
    ));

    return '<a href="/action?' . h($query) . '" class="sort-link">' . h($label) . '</a>';
}

function pageLink($pageNumber, $search, $filter, $sort, $order) {
    $query = http_build_query(array(
        'search' => $search,
        'filter' => $filter,
        'sort' => $sort,
        'order' => $order,
        'page' => $pageNumber
    ));
    return '/action?' . $query;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Action Dashboard</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }
        .wrap {
            max-width: 1400px;
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
            grid-template-columns: 380px 1fr;
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
        .muted {
            color: #666;
            font-size: 13px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
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
        @media (max-width: 1100px) {
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

		.action-buttons {
			display: flex;
			flex-direction: column;
			gap: 8px;
			margin-top: 14px;
			align-items: stretch;
		}

		.action-buttons .btn {
			text-align: center;
			box-sizing: border-box;
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

			.action-buttons-grid .btn:last-child {
				grid-column: 1 / -1;
			}
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1 class="title">Action Dashboard</h1>
            <div class="subtitle">Показываются все товары из <strong>stock_</strong> с остатком больше 0. Последнее обновление акций: <strong><?php echo h($updatedAt !== '' ? $updatedAt : '—'); ?></strong></div>
         </div>

		
		<div class="dashboard-side">
			<div class="badge">Всего позиций: <?php echo (int)$total_rows; ?></div>

			<div class="subtitle" style="margin-top:8px;">
				Сумма остатков:
				<strong><?php echo h(number_format($totalStockSum, 2, '.', ' ')); ?></strong>
			</div>

			<div class="subtitle" style="margin-top:6px;">
				Последнее обновление остатков:
				<strong><?php echo h($updatedAt !== '' ? $updatedAt : '—'); ?></strong>
			</div>

		<div class="action-buttons-grid">
			<a href="/action-update-stock?key=MY_SECRET_123" class="btn btn-primary" target="_blank">
				Обновить stock_
			</a>

			<a href="/action-update-site" class="btn btn-primary" target="_blank">
				Обновить сайт
			</a>

			<a href="/action" class="btn">
				Обновить страницу
			</a>
		</div>
		</div>
		
		
    </div> 

    <div class="grid">
        <div class="card">
            <h2><?php echo ($action === 'edit' && $product_id > 0) ? 'Редактирование акции' : 'Новая акция'; ?></h2>

            <?php if (!empty($errors)) { ?>
                <?php foreach ($errors as $error) { ?>
                    <div class="error"><?php echo h($error); ?></div>
                <?php } ?>
            <?php } ?>

            <form method="post" action="/action?<?php echo h(http_build_query(array('search' => $search, 'filter' => $filter, 'sort' => $sort, 'order' => $order, 'page' => $page))); ?>">
                <input type="hidden" name="form_action" value="save">

                <label for="product_id">Product ID</label>
                <input type="number" name="product_id" id="product_id" value="<?php echo h($edit_row['product_id']); ?>" <?php echo ($action === 'edit' && $product_id > 0) ? 'readonly' : ''; ?>>

                <label>Наименование</label>
                <input type="text" value="<?php echo h(isset($edit_row['name']) ? $edit_row['name'] : ''); ?>" readonly>

                <label>Остаток</label>
                <input type="text" value="<?php echo h(isset($edit_row['stock']) ? $edit_row['stock'] : 0); ?>" readonly>

                <label>Цена закупки</label>
                <input type="text" value="<?php echo h(isset($edit_row['price']) ? $edit_row['price'] : ''); ?>" readonly>

                <label for="discount">Discount</label>
                <input type="number" name="discount" id="discount" value="<?php echo h($edit_row['discount']); ?>" min="0" max="100">

                <label for="super_discont">Super Discount</label>
                <input type="number" name="super_discont" id="super_discont" value="<?php echo h($edit_row['super_discont']); ?>" min="0" max="100">

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="/action" class="btn">Сбросить</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Товары</h2>

		<form method="get" class="filters" action="/action">
			<div>
				<label for="search">Поиск</label>
				<input type="text" name="search" id="search" value="<?php echo h($search); ?>" placeholder="Поиск по product_id или названию">
			</div>

			<div>
				<label for="filter">Фильтр</label>
				<select name="filter" id="filter">
					<option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все товары</option>
					<option value="with_action" <?php echo $filter === 'with_action' ? 'selected' : ''; ?>>Только с акцией</option>
					<option value="without_action" <?php echo $filter === 'without_action' ? 'selected' : ''; ?>>Только без акции</option>
				</select>
			</div>

			<div class="filters-actions">
				<button type="submit" class="btn">Применить</button>
				<a href="/action" class="btn">Сброс</a>
			</div>
		</form>

            <table>
                <thead>
                    <tr>
                        <th><?php echo sortLink('Product ID', 'product_id', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Наименование', 'name', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Остаток', 'quantity', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Цена закупки', 'price', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Сумма остатка', 'total_sum', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Discount', 'discount', $search, $filter, $sort, $order); ?></th>
                        <th><?php echo sortLink('Super Discount', 'super_discont', $search, $filter, $sort, $order); ?></th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($list_result && $list_result->num_rows > 0) { ?>
                    <?php while ($row = $list_result->fetch_assoc()) { ?>
                        <?php $hasAction = ($row['discount'] !== null || $row['super_discont'] !== null); ?>
                        <tr>
                            <td class="num"><?php echo (int)$row['product_id']; ?></td>
                            <td><?php echo h($row['name']); ?></td>
                            <td class="num"><?php echo (int)$row['stock']; ?></td>
                            <td class="num"><?php echo h($row['price']); ?></td>
                            <td class="num"><?php echo h(number_format((float)$row['total_sum'], 2, '.', ' ')); ?></td>
                            <td class="num"><?php echo $row['discount'] !== null ? (int)$row['discount'] : '—'; ?></td>
                            <td class="num"><?php echo $row['super_discont'] !== null ? (int)$row['super_discont'] : '—'; ?></td>
                            <td><?php echo $hasAction ? 'С акцией' : 'Без акции'; ?></td>
                            <td>
                                <div class="btn-row">
                                    <a href="<?php echo h(pageLink($page, $search, $filter, $sort, $order) . '&action=edit&product_id=' . (int)$row['product_id']); ?>" class="btn"><?php echo $hasAction ? 'Редактировать' : 'Добавить акцию'; ?></a>
                                    <?php if ($hasAction) { ?>
                                        <a href="<?php echo h(pageLink($page, $search, $filter, $sort, $order) . '&action=delete&product_id=' . (int)$row['product_id']); ?>" class="btn btn-danger" onclick="return confirm('Удалить акцию?');">Удалить</a>
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
                        <a href="<?php echo h(pageLink($page - 1, $search, $filter, $sort, $order)); ?>">← Назад</a>
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
                            <a href="<?php echo h(pageLink($p, $search, $filter, $sort, $order)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $total_pages) { ?>
                        <a href="<?php echo h(pageLink($page + 1, $search, $filter, $sort, $order)); ?>">Вперёд →</a>
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
