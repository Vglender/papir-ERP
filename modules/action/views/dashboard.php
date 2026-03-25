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
        input[type="text"]:not(.chip-typer),
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
        /* Icon buttons */
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #c8d1dd;
            background: #fff;
            color: #555;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            line-height: 1;
            padding: 0;
        }
        .icon-btn:hover {
            background: #f0f4fa;
            border-color: #aab8cc;
        }
        .icon-btn.edit:hover {
            background: #eef4ff;
            border-color: #1f6feb;
            color: #1f6feb;
        }
        .icon-btn.del {
            color: #b42318;
            border-color: #f3c0c0;
            background: #fff7f7;
        }
        .icon-btn.del:hover {
            background: #fee2e2;
        }
        .icon-btn.add {
            color: #065f46;
            border-color: #a7f3d0;
            background: #f0fdf9;
        }
        .icon-btn.add:hover {
            background: #d1fae5;
        }
        /* Refresh link in table header */
        .th-refresh {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #555;
            text-decoration: none;
            font-size: 13px;
            font-weight: normal;
            text-transform: none;
            letter-spacing: 0;
            padding: 2px 6px;
            border-radius: 5px;
            border: 1px solid #d9e0ea;
            background: #f8fafc;
            white-space: nowrap;
        }
        .th-refresh:hover {
            background: #eef4ff;
            border-color: #1f6feb;
            color: #1f6feb;
        }
        .chip-input { display:flex; flex-wrap:wrap; gap:4px; align-items:center; padding:6px 8px; border:1px solid #c8d1dd; border-radius:8px; background:#fff; cursor:text; min-height:38px; margin-bottom:14px; }
        .chip { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; background:#eef4ff; color:#1f4db8; border-radius:4px; font-size:13px; white-space:nowrap; }
        .chip-x { cursor:pointer; color:#888; line-height:1; }
        .chip-x:hover { color:#b42318; }
        .chip-typer { border:none; outline:none; font-size:13px; padding:2px 4px; min-width:120px; flex:1; background:transparent; }
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
            min-width: 100px;
            text-align: center;
            box-sizing: border-box;
            padding: 8px 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #e8edf3;
            text-align: left;
            vertical-align: middle;
            font-size: 13px;
        }
        th {
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #555;
        }
        th.actions-th {
            text-align: center;
        }
        td.actions-td {
            text-align: center;
            white-space: nowrap;
        }
        td.actions-td .icon-btn {
            margin: 0 1px;
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
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef4ff;
            color: #1f4db8;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-neutral {
            background: #f1f5f9;
            color: #475569;
        }
        .badge-action {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            background: #fff3cd;
            color: #856404;
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
        .status-published {
            color: #065f46;
            font-size: 11px;
            font-weight: bold;
        }
        .status-calculated {
            color: #92400e;
            font-size: 11px;
            font-weight: bold;
        }
        .status-none {
            color: #94a3b8;
            font-size: 11px;
        }
        .dashboard-side {
            text-align: right;
            min-width: 260px;
        }
        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        .badges-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        @media (max-width: 1100px) {
            .grid { grid-template-columns: 1fr; }
            .filters { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1 class="title">Акции</h1>
            <div class="subtitle">
                Товары с остатком &gt; 0 и товары с активной акцией.
            </div>
        </div>

        <div class="dashboard-side">
            <div class="badges-row" style="justify-content:flex-end;">
                <span class="badge">Позиций: <?php echo (int)$total_rows; ?></span>
                <span class="badge badge-warning">Акций: <?php echo (int)$actionCount; ?></span>
                <span class="badge badge-neutral">Ожидают публикации: <?php echo (int)$pendingCount; ?></span>
            </div>
            <div class="action-btns">
                <a href="/action-update-stock" class="btn btn-primary" target="_blank">▲ Обновить остатки</a>
                <a href="/action-update-site" class="btn btn-primary" target="_blank">⇪ Рассчитать и опубликовать</a>
            </div>
        </div>
    </div>

    <div class="grid">
        <!-- Left panel: edit form -->
        <div class="card">
            <h2><?php echo ($action === 'edit' && $product_id > 0) ? 'Редактирование акции' : 'Новая акция'; ?></h2>

            <?php if (!empty($errors)) { ?>
                <?php foreach ($errors as $error) { ?>
                    <div class="error"><?php echo ViewHelper::h($error); ?></div>
                <?php } ?>
            <?php } ?>

            <form method="post" action="<?php echo ViewHelper::h(ViewHelper::buildUrl($basePath, $state)); ?>">
                <input type="hidden" name="form_action" value="save">

                <label for="product_id">Product ID</label>
                <input
                    type="number"
                    name="product_id"
                    id="product_id"
                    value="<?php echo ViewHelper::h($edit_row['product_id']); ?>"
                    <?php echo ($action === 'edit' && $product_id > 0) ? 'readonly' : ''; ?>
                >

                <label>Наименование</label>
                <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['name']) ? $edit_row['name'] : ''); ?>" readonly>

                <label>Остаток</label>
                <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['stock']) ? $edit_row['stock'] : 0); ?>" readonly>

                <label>Цена закупки</label>
                <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['price']) ? $edit_row['price'] : ''); ?>" readonly>

                <?php if (isset($edit_row['price_act']) && $edit_row['price_act'] !== null) { ?>
                    <label>Акционная цена (рассчитана)</label>
                    <input type="text" value="<?php echo ViewHelper::h(number_format((float)$edit_row['price_act'], 2, '.', ' ')); ?>" readonly>
                <?php } ?>

                <label for="discount">Discount (%)</label>
                <input type="number" name="discount" id="discount" value="<?php echo ViewHelper::h($edit_row['discount']); ?>" min="0" max="100">

                <label for="super_discont">Super Discount (%)</label>
                <input type="number" name="super_discont" id="super_discont" value="<?php echo ViewHelper::h($edit_row['super_discont']); ?>" min="0" max="100">

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a href="/action" class="btn">Сбросить</a>
                </div>
            </form>
        </div>

        <!-- Right panel: list table -->
        <div class="card">
            <form method="get" class="filters" action="/action">
                <div>
                    <label>Поиск</label>
                    <div class="chip-input" id="searchChipBox">
                        <input type="text" class="chip-typer" id="searchChipTyper"
                               placeholder="ID, артикул или название…" autocomplete="off">
                    </div>
                    <input type="hidden" name="search" id="searchHidden" value="<?php echo ViewHelper::h($search); ?>">
                </div>

                <div>
                    <label for="filter">Фильтр</label>
                    <select name="filter" id="filter">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все товары</option>
                        <option value="with_action" <?php echo $filter === 'with_action' ? 'selected' : ''; ?>>С акцией</option>
                        <option value="without_action" <?php echo $filter === 'without_action' ? 'selected' : ''; ?>>Без акции</option>
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
                        <th><?php echo TableHelper::sortLink('ID', 'product_id', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Наименование', 'name', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Остаток', 'quantity', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Цена', 'price', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Сумма', 'total_sum', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Disc%', 'discount', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('S.Disc%', 'super_discont', $state, $basePath); ?></th>
                        <th>Акц. цена</th>
                        <th>Статус</th>
                        <th class="actions-th">
                            <a href="/action" class="th-refresh" title="Обновить страницу">↻</a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($list)) { ?>
                    <?php foreach ($list as $row) { ?>
                        <?php
                        $hasAction   = ($row['discount'] !== null || $row['super_discont'] !== null);
                        $hasPriceAct = ($row['price_act'] !== null && $row['price_act'] !== '');

                        if ($hasPriceAct) {
                            if ($row['published_at'] !== null && $row['published_at'] >= $row['calculated_at']) {
                                $statusLabel = 'Опубликовано';
                                $statusClass = 'status-published';
                            } else {
                                $statusLabel = 'Рассчитано';
                                $statusClass = 'status-calculated';
                            }
                        } else {
                            $statusLabel = $hasAction ? 'Ожидает' : '—';
                            $statusClass = 'status-none';
                        }
                        ?>
                        <tr>
                            <td class="num"><?php echo (int)$row['product_id']; ?></td>
                            <td>
                                <?php echo ViewHelper::h($row['name']); ?>
                                <?php if ($hasAction) { ?>
                                    <span class="badge-action">акция</span>
                                <?php } ?>
                            </td>
                            <td class="num"><?php echo (int)$row['stock']; ?></td>
                            <td class="num"><?php echo ViewHelper::h(number_format((float)$row['price'], 2, '.', ' ')); ?></td>
                            <td class="num"><?php echo ViewHelper::h(number_format((float)$row['total_sum'], 2, '.', ' ')); ?></td>
                            <td class="num"><?php echo $row['discount'] !== null ? (int)$row['discount'] : '—'; ?></td>
                            <td class="num"><?php echo $row['super_discont'] !== null ? (int)$row['super_discont'] : '—'; ?></td>
                            <td class="num">
                                <?php if ($hasPriceAct) { ?>
                                    <?php echo ViewHelper::h(number_format((float)$row['price_act'], 2, '.', ' ')); ?>
                                    <?php if ($row['published_at'] !== null) { ?>
                                        <div class="muted" title="Опубликовано: <?php echo ViewHelper::h($row['published_at']); ?>">
                                            <?php echo ViewHelper::h(substr($row['published_at'], 0, 10)); ?>
                                        </div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="muted">—</span>
                                <?php } ?>
                            </td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo ViewHelper::h($statusLabel); ?></span></td>
                            <td class="actions-td">
                                <a
                                    href="<?php echo ViewHelper::h(
                                        TableHelper::pageLink($page, $state, $basePath) .
                                        '&action=edit&product_id=' . (int)$row['product_id']
                                    ); ?>"
                                    class="icon-btn <?php echo $hasAction ? 'edit' : 'add'; ?>"
                                    title="<?php echo $hasAction ? 'Редактировать' : 'Добавить акцию'; ?>"
                                >
                                    <?php echo $hasAction ? '✎' : '+'; ?>
                                </a>

                                <?php if ($hasAction) { ?>
                                    <a
                                        href="<?php echo ViewHelper::h(
                                            TableHelper::pageLink($page, $state, $basePath) .
                                            '&action=delete&product_id=' . (int)$row['product_id']
                                        ); ?>"
                                        class="icon-btn del"
                                        title="Удалить акцию"
                                        onclick="return confirm('Удалить акцию?');"
                                    >✕</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="10">Данные не найдены.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(TableHelper::pageLink($page - 1, $state, $basePath)); ?>">&#8592;</a>
                    <?php } ?>

                    <?php
                    $startPage = max(1, $page - 3);
                    $endPage   = min($total_pages, $page + 3);
                    ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++) { ?>
                        <?php if ($p == $page) { ?>
                            <span class="current"><?php echo $p; ?></span>
                        <?php } else { ?>
                            <a href="<?php echo ViewHelper::h(TableHelper::pageLink($p, $state, $basePath)); ?>"><?php echo $p; ?></a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($page < $total_pages) { ?>
                        <a href="<?php echo ViewHelper::h(TableHelper::pageLink($page + 1, $state, $basePath)); ?>">&#8594;</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
ChipSearch.init('searchChipBox', 'searchChipTyper', 'searchHidden');
</script>
</body>
</html>
