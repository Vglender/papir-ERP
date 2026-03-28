<?php
$title     = 'Акції';
$activeNav = 'prices';
$subNav    = 'actions';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
    .action-toolbar {
        display: flex; align-items: center;
        gap: 8px; margin-bottom: 10px;
    }
    .action-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }
    .action-search-wrap { flex: 1; min-width: 160px; }
    .action-toolbar .btn       { height: 34px; padding: 0 12px; }
    .action-toolbar .chip-input { min-height: 34px; max-height: 34px; overflow: hidden; }
    .action-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 20px;
        align-items: start;
        margin-top: 10px;
    }
    @media (max-width: 1100px) { .action-grid { grid-template-columns: 1fr; } }
    .icon-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 6px;
        border: 1px solid var(--border-input); background: #fff; color: #555;
        text-decoration: none; cursor: pointer; font-size: 14px;
        transition: background .15s, border-color .15s, color .15s;
        line-height: 1; padding: 0;
    }
    .icon-btn:hover { background: #f0f4fa; border-color: #aab8cc; }
    .icon-btn.edit:hover { background: #eef4ff; border-color: var(--blue); color: var(--blue); }
    .icon-btn.del { color: var(--red); border-color: #f3c0c0; background: #fff7f7; }
    .icon-btn.del:hover { background: #fee2e2; }
    .icon-btn.add { color: #065f46; border-color: #a7f3d0; background: #f0fdf9; }
    .icon-btn.add:hover { background: #d1fae5; }
    .status-published  { color: #065f46; font-size: 11px; font-weight: bold; }
    .status-calculated { color: #92400e; font-size: 11px; font-weight: bold; }
    .status-none       { color: #94a3b8; font-size: 11px; }
    .badge-action {
        display: inline-block; padding: 2px 6px; border-radius: 4px;
        font-size: 11px; font-weight: bold; background: #fff3cd; color: #856404;
    }
    .th-refresh {
        display: inline-flex; align-items: center; gap: 4px;
        color: #555; text-decoration: none; font-size: 13px; font-weight: normal;
        text-transform: none; letter-spacing: 0; padding: 2px 6px; border-radius: 5px;
        border: 1px solid var(--border); background: var(--bg-header); white-space: nowrap;
    }
    .th-refresh:hover { background: #eef4ff; border-color: var(--blue); color: var(--blue); }
    .crm-table th.actions-th { text-align: center; }
    .crm-table td.actions-td { text-align: center; white-space: nowrap; }
    .crm-table td.actions-td .icon-btn { margin: 0 1px; }
    .card h2 { margin: 0 0 16px; font-size: 17px; }
    .edit-fg { margin-bottom: 12px; }
    .edit-fg label { display: block; margin-bottom: 4px; font-size: 13px; font-weight: 600; }
    .edit-fg input[type="text"],
    .edit-fg input[type="number"] {
        width: 100%; box-sizing: border-box; padding: 8px 12px;
        border: 1px solid var(--border-input); border-radius: var(--radius);
        font-size: 14px; font-family: var(--font); background: #fff;
    }
    .edit-fg input[readonly] { background: var(--bg-header); color: var(--text-muted); }
    .edit-fg input[type="number"]:not([readonly]):focus { outline: none; border-color: var(--blue-light); }
</style>

<div class="page-wrap-lg">

    <form method="get" action="/action" id="actionFilterForm">
        <div class="action-toolbar">
            <h1>Акції</h1>
            <a href="/action-update-stock" class="btn btn-primary" target="_blank">&#9650; Оновити залишки</a>
            <a href="/action-update-site" class="btn btn-primary" target="_blank">&#8682; Розрахувати</a>
            <div class="action-search-wrap">
                <div class="chip-input" id="searchChipBox">
                    <input type="text" class="chip-typer" id="searchChipTyper"
                           placeholder="ID, артикул або назва…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="chipClearBtn" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="search" id="searchHidden" value="<?php echo ViewHelper::h($search); ?>">
        </div>

        <div class="filter-bar">
            <div class="filter-bar-group">
                <span class="filter-bar-label">Фільтр</span>
                <label class="filter-pill">
                    <input type="radio" name="filter" value="all"
                        <?php echo $filter === 'all' ? 'checked' : ''; ?>> Всі
                </label>
                <label class="filter-pill">
                    <input type="radio" name="filter" value="with_action"
                        <?php echo $filter === 'with_action' ? 'checked' : ''; ?>> З акцією
                </label>
                <label class="filter-pill">
                    <input type="radio" name="filter" value="without_action"
                        <?php echo $filter === 'without_action' ? 'checked' : ''; ?>> Без акції
                </label>
            </div>
            <div class="filter-bar-sep"></div>
            <div class="filter-bar-group">
                <span class="badge">Позицій: <?php echo (int)$total_rows; ?></span>
                <span class="badge badge-orange">Акцій: <?php echo (int)$actionCount; ?></span>
                <span class="badge badge-gray">Очікують: <?php echo (int)$pendingCount; ?></span>
            </div>
            <button type="button" class="filter-bar-gear" title="Налаштувати фільтри">
                <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><path d="M6.5 1h3l.4 1.6a5 5 0 0 1 1.2.7l1.6-.5 1.5 2.6-1.2 1.1a5 5 0 0 1 0 1.4l1.2 1.1-1.5 2.6-1.6-.5a5 5 0 0 1-1.2.7L9.5 15h-3l-.4-1.6a5 5 0 0 1-1.2-.7l-1.6.5L1.8 10.6l1.2-1.1a5 5 0 0 1 0-1.4L1.8 6.9 3.3 4.3l1.6.5a5 5 0 0 1 1.2-.7L6.5 1z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/></svg>
            </button>
        </div>
    </form>

    <div class="action-grid">

        <!-- Left: edit form -->
        <div class="card">
            <h2><?php echo ($action === 'edit' && $product_id > 0) ? 'Редагування акції' : 'Нова акція'; ?></h2>

            <?php if (!empty($errors)) { ?>
                <?php foreach ($errors as $error) { ?>
                    <div class="modal-error" style="margin-bottom:12px;"><?php echo ViewHelper::h($error); ?></div>
                <?php } ?>
            <?php } ?>

            <form method="post" action="<?php echo ViewHelper::h(ViewHelper::buildUrl($basePath, $state)); ?>">
                <input type="hidden" name="form_action" value="save">

                <div class="edit-fg">
                    <label for="product_id">Product ID</label>
                    <input type="number" name="product_id" id="product_id"
                           value="<?php echo ViewHelper::h($edit_row['product_id']); ?>"
                           <?php echo ($action === 'edit' && $product_id > 0) ? 'readonly' : ''; ?>>
                </div>

                <div class="edit-fg">
                    <label>Найменування</label>
                    <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['name']) ? $edit_row['name'] : ''); ?>" readonly>
                </div>

                <div class="edit-fg">
                    <label>Залишок</label>
                    <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['stock']) ? $edit_row['stock'] : 0); ?>" readonly>
                </div>

                <div class="edit-fg">
                    <label>Ціна закупки</label>
                    <input type="text" value="<?php echo ViewHelper::h(isset($edit_row['price']) ? $edit_row['price'] : ''); ?>" readonly>
                </div>

                <?php if (isset($edit_row['price_act']) && $edit_row['price_act'] !== null) { ?>
                    <div class="edit-fg">
                        <label>Акційна ціна (розрахована)</label>
                        <input type="text" value="<?php echo ViewHelper::h(number_format((float)$edit_row['price_act'], 2, '.', ' ')); ?>" readonly>
                    </div>
                <?php } ?>

                <div class="edit-fg">
                    <label for="discount">Discount (%)</label>
                    <input type="number" name="discount" id="discount"
                           value="<?php echo ViewHelper::h($edit_row['discount']); ?>" min="0" max="100">
                </div>

                <div class="edit-fg">
                    <label for="super_discont">Super Discount (%)</label>
                    <input type="number" name="super_discont" id="super_discont"
                           value="<?php echo ViewHelper::h($edit_row['super_discont']); ?>" min="0" max="100">
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Зберегти</button>
                    <a href="/action" class="btn">Скинути</a>
                </div>
            </form>
        </div>

        <!-- Right: table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th><?php echo TableHelper::sortLink('ID', 'product_id', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Найменування', 'name', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Залишок', 'quantity', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Ціна', 'price', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Сума', 'total_sum', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('Disc%', 'discount', $state, $basePath); ?></th>
                        <th><?php echo TableHelper::sortLink('S.Disc%', 'super_discont', $state, $basePath); ?></th>
                        <th>Акц. ціна</th>
                        <th>Статус</th>
                        <th class="actions-th">
                            <a href="/action" class="th-refresh" title="Оновити сторінку">&#8635;</a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($list)) { ?>
                    <?php foreach ($list as $row) { ?>
                        <?php
                        $hasAction   = ($row['discount'] !== null || $row['super_discont'] !== null);
                        $hasPriceAct = ($row['price_act'] !== null && $row['price_act'] !== '');
                        $isSelected  = ((int)$row['product_id'] === $product_id);

                        if ($hasPriceAct) {
                            if ($row['published_at'] !== null && $row['published_at'] >= $row['calculated_at']) {
                                $statusLabel = 'Опубліковано';
                                $statusClass = 'status-published';
                            } else {
                                $statusLabel = 'Розраховано';
                                $statusClass = 'status-calculated';
                            }
                        } else {
                            $statusLabel = $hasAction ? 'Очікує' : '—';
                            $statusClass = 'status-none';
                        }
                        ?>
                        <tr<?php echo $isSelected ? ' class="row-selected"' : ''; ?>>
                            <td class="nowrap"><?php echo (int)$row['product_id']; ?></td>
                            <td>
                                <?php echo ViewHelper::h($row['name']); ?>
                                <?php if ($hasAction) { ?>
                                    <span class="badge-action">акція</span>
                                <?php } ?>
                            </td>
                            <td class="nowrap"><?php echo (int)$row['stock']; ?></td>
                            <td class="nowrap"><?php echo ViewHelper::h(number_format((float)$row['price'], 2, '.', ' ')); ?></td>
                            <td class="nowrap"><?php echo ViewHelper::h(number_format((float)$row['total_sum'], 2, '.', ' ')); ?></td>
                            <td class="nowrap"><?php echo $row['discount'] !== null ? (int)$row['discount'] : '—'; ?></td>
                            <td class="nowrap"><?php echo $row['super_discont'] !== null ? (int)$row['super_discont'] : '—'; ?></td>
                            <td class="nowrap">
                                <?php if ($hasPriceAct) { ?>
                                    <?php echo ViewHelper::h(number_format((float)$row['price_act'], 2, '.', ' ')); ?>
                                    <?php if ($row['published_at'] !== null) { ?>
                                        <div class="text-muted fs-12" title="Опубліковано: <?php echo ViewHelper::h($row['published_at']); ?>">
                                            <?php echo ViewHelper::h(substr($row['published_at'], 0, 10)); ?>
                                        </div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td><span class="<?php echo $statusClass; ?>"><?php echo ViewHelper::h($statusLabel); ?></span></td>
                            <td class="actions-td">
                                <a href="<?php echo ViewHelper::h(
                                    TableHelper::pageLink($page, $state, $basePath) .
                                    '&action=edit&product_id=' . (int)$row['product_id']
                                ); ?>"
                                   class="icon-btn <?php echo $hasAction ? 'edit' : 'add'; ?>"
                                   title="<?php echo $hasAction ? 'Редагувати' : 'Додати акцію'; ?>">
                                    <?php echo $hasAction ? '&#9998;' : '+'; ?>
                                </a>
                                <?php if ($hasAction) { ?>
                                    <a href="<?php echo ViewHelper::h(
                                        TableHelper::pageLink($page, $state, $basePath) .
                                        '&action=delete&product_id=' . (int)$row['product_id']
                                    ); ?>"
                                       class="icon-btn del"
                                       title="Видалити акцію"
                                       onclick="return confirm('Видалити акцію?');">&#x2715;</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="10" class="text-muted" style="padding: 24px; text-align: center;">Дані не знайдено.</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) { ?>
                <div class="pagination" style="padding: 12px 16px;">
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
ChipSearch.init('searchChipBox', 'searchChipTyper', 'searchHidden',
    document.getElementById('actionFilterForm'), {noComma: true});

// Radio filter pills — submit on change
(function () {
    var form = document.getElementById('actionFilterForm');
    var radios = form ? form.querySelectorAll('input[type="radio"][name="filter"]') : [];
    for (var i = 0; i < radios.length; i++) {
        radios[i].addEventListener('change', function () { form.submit(); });
    }
}());

// Chip clear button
(function () {
    var clearBtn  = document.getElementById('chipClearBtn');
    var chipBox   = document.getElementById('searchChipBox');
    var typer     = document.getElementById('searchChipTyper');
    var hidden    = document.getElementById('searchHidden');
    var form      = document.getElementById('actionFilterForm');
    if (!clearBtn || !chipBox || !typer || !hidden) return;

    function updateClearBtn() {
        var hasChips = chipBox.querySelectorAll('.chip').length > 0;
        var hasText  = typer.value.trim() !== '';
        if (hasChips || hasText) { clearBtn.classList.remove('hidden'); }
        else                     { clearBtn.classList.add('hidden'); }
    }
    var observer = new MutationObserver(updateClearBtn);
    observer.observe(chipBox, { childList: true });
    typer.addEventListener('input', updateClearBtn);

    clearBtn.addEventListener('click', function () {
        chipBox.querySelectorAll('.chip').forEach(function (c) { c.remove(); });
        typer.value = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
        if (form) form.submit();
    });
    updateClearBtn();
}());
</script>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
