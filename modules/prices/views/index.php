<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Prices</title>
    <style>
        body { margin:0; padding:0; font-family: Arial, sans-serif; background:#f5f7fb; color:#222; }
        .wrap { max-width:1850px; margin:0 auto; padding:24px; }
        .topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
        .title { margin:0 0 6px; font-size:30px; }
        .subtitle { color:#666; font-size:14px; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; background:#eef4ff; color:#1f4db8; margin-left:6px; }
        .layout { display:grid; grid-template-columns: minmax(760px,1fr) 480px; gap:20px; align-items:start; }
        .card { background:#fff; border:1px solid #d9e0ea; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        .sticky-panel { position:sticky; top:16px; max-height:calc(100vh - 32px); overflow-y:auto; padding-right:6px; }
        .filters { display:flex; gap:10px; margin-bottom:16px; align-items:flex-end; flex-wrap:nowrap; }
        .filters > .filter-search { flex:1 1 auto; min-width:200px; }
        .filters > .filter-select { flex:0 0 180px; }
        .filters > .filter-actions { flex:0 0 auto; display:flex; gap:8px; align-items:flex-end; }
        label { display:block; margin:0 0 6px; font-size:13px; font-weight:bold; }
        input[type="text"]:not(.chip-typer), select { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #c8d1dd; border-radius:8px; font-size:14px; background:#fff; }
        /* Chip search */
        .chip-input { display:flex; flex-wrap:wrap; gap:4px; padding:3px 6px; min-height:42px; border:1px solid #c8d1dd; border-radius:8px; background:#fff; cursor:text; align-items:center; width:100%; box-sizing:border-box; }
        .chip-input:focus-within { border-color:#4a90e2; }
        .chip { display:inline-flex; align-items:center; gap:3px; background:#eef4ff; color:#1f4db8; border-radius:4px; padding:2px 6px 2px 8px; font-size:13px; white-space:nowrap; line-height:1.5; }
        .chip-x { cursor:pointer; font-size:15px; line-height:1; color:#1f4db8; opacity:.55; margin-left:1px; }
        .chip-x:hover { opacity:1; }
        .chip-typer { border:none; outline:none; background:transparent; font-size:14px; min-width:120px; flex:1; padding:2px 0; font-family:inherit; color:#222; }
        .btn { display:inline-block; padding:10px 14px; border-radius:8px; border:1px solid #c8d1dd; background:#fff; color:#222; text-decoration:none; cursor:pointer; font-size:14px; box-sizing:border-box; text-align:center; transition:background .15s,border-color .15s; }
        .btn:hover { background:#f0f4ff; border-color:#a8bde8; }
        .btn-primary { background:#1f6feb; border-color:#1f6feb; color:#fff; }
        .btn-primary:hover { background:#1558c8; border-color:#1558c8; }
        .btn-ghost { background:#f1f5f9; border-color:#d1d9e0; color:#444; }
        .btn-ghost:hover { background:#e2e8f0; }
        .btn-apply { background:#f1f5f9; border-color:#c8d1dd; color:#333; }
        .btn-apply:hover { background:#e8f0fe; border-color:#a8bde8; color:#1a56c4; }
        .btn-small { padding:6px 10px; font-size:12px; border-radius:6px; }
        .btn-row { display:flex; gap:8px; flex-wrap:wrap; }
        .bulk-left { font-size:14px; display:flex; align-items:center; }
        #bulkClear.has-selection { background:#fff4e5; border-color:#e6951a; color:#b26a00; font-weight:600; }
        #bulkClear.has-selection:hover { background:#ffe8c4; }
        .bulk-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:0; padding:10px 12px; border:1px solid #d9e0ea; border-radius:10px; background:#f8fafc; }
        .bulk-actions { display:flex; gap:8px; flex-wrap:wrap; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { padding:10px 12px; border-bottom:1px solid #e8edf3; text-align:left; vertical-align:middle; font-size:14px; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.02em; color:#555; }
        .sort-link { color:#555; text-decoration:none; white-space:nowrap; }
        .sort-link:hover { text-decoration:underline; }
        td a { color:inherit; text-decoration:none; }
        td a:hover { text-decoration:underline; }
        .num { white-space:nowrap; }
        tbody tr.js-row-click { cursor:pointer; transition:background .15s; }
        tbody tr.js-row-click:hover { background:#f8fbff; }
        .selected-row { background:#f0f6ff !important; }
        .source-tag { font-size:11px; padding:2px 6px; border-radius:4px; background:#f0f4f8; color:#555; }
        .manual-tag { background:#fff4e5; color:#b26a00; }
        .action-tag { font-size:11px; padding:2px 6px; border-radius:4px; background:#fef3c7; color:#92400e; font-weight:bold; }
        .price-act-val { color:#b45309; font-weight:bold; }
        tbody tr.has-action { background:#fffbeb; }
        tbody tr.has-action:hover { background:#fef9e6 !important; }
        tbody tr.has-action.selected-row { background:#fef3c7 !important; }
        .pagination { display:flex; gap:8px; flex-wrap:wrap; margin-top:16px; }
        .pagination a, .pagination span { display:inline-block; padding:8px 12px; border:1px solid #d9e0ea; border-radius:8px; text-decoration:none; color:#222; background:#fff; font-size:14px; }
        .pagination .current { background:#1f6feb; border-color:#1f6feb; color:#fff; }
        .empty { color:#777; }
        /* Detail panel */
        .section { border-top:1px solid #e8edf3; padding-top:16px; margin-top:16px; }
        .section:first-child { border-top:0; padding-top:0; margin-top:0; }
        .section h3 { margin:0 0 12px; font-size:15px; }
        .info-grid { display:grid; grid-template-columns:140px 1fr; gap:8px 12px; }
        .info-grid .k { color:#666; font-size:13px; }
        .info-grid .v { font-size:14px; word-break:break-word; }
        .status-badges { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
        .status-pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:bold; }
        .pill-on   { background:#edfdf3; color:#157347; }
        .pill-off  { background:#fff1f1; color:#b42318; }
        .pill-act  { background:#fff4e5; color:#b26a00; }
        .pill-stk  { background:#eef4ff; color:#1f4db8; }
        .price-card { display:flex; flex-direction:column; gap:14px; }
        .price-group { border:1px solid #e8edf3; border-radius:10px; padding:12px; background:#fafcff; }
        .price-group-muted { background:#fbfbfc; }
        .price-group-title { font-size:11px; font-weight:bold; color:#666; text-transform:uppercase; letter-spacing:.04em; margin-bottom:10px; }
        .price-grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
        .price-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
        .price-item { background:#fff; border:1px solid #eef2f6; border-radius:8px; padding:10px; }
        .price-label { font-size:12px; color:#666; margin-bottom:4px; }
        .price-value { font-size:16px; font-weight:bold; }
        .price-source { font-size:11px; color:#999; margin-top:3px; }
        .discount-levels { display:flex; flex-wrap:wrap; gap:8px; }
        .discount-chip { display:inline-block; padding:5px 10px; border-radius:999px; background:#eef4ff; color:#1f4db8; font-size:13px; white-space:nowrap; }
        .settings-card { border:1px solid #e8edf3; border-radius:10px; background:#fafcff; padding:12px; }
        .settings-row { display:grid; grid-template-columns:150px 1fr; gap:10px; padding:8px 0; border-bottom:1px solid #eef2f6; }
        .settings-row:last-child { border-bottom:0; }
        .settings-label { color:#666; font-size:13px; }
        .settings-value { font-size:14px; }
        .module-links { display:flex; gap:10px; margin-top:14px; flex-wrap:wrap; }
        /* Progress bar */
        .recalc-progress { margin-top:12px; display:none; }
        .recalc-progress-bar-wrap { background:#e8edf3; border-radius:999px; height:10px; overflow:hidden; margin-bottom:6px; }
        .recalc-progress-bar { background:#1f6feb; height:10px; border-radius:999px; width:0; transition:width .3s; }
        .recalc-progress-text { font-size:13px; color:#555; }
        /* Global settings card */
        .global-settings-card { background:#fff; border:1px solid #d9e0ea; border-radius:12px; padding:16px 20px; box-shadow:0 2px 8px rgba(0,0,0,.04); margin-bottom:20px; }
        .global-settings-header { display:flex; justify-content:space-between; align-items:center; cursor:pointer; user-select:none; }
        .global-settings-summary { font-size:13px; color:#555; margin-top:4px; }
        .global-settings-body { margin-top:16px; border-top:1px solid #e8edf3; padding-top:16px; display:none; }
        .gs-form-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px; }
        .gs-form-grid label { font-size:12px; color:#666; margin-bottom:3px; display:block; }
        .gs-form-grid input[type="number"] { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #c8d1dd; border-radius:6px; font-size:13px; }
        .gs-strat-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
        .gs-strat-grid label { font-size:12px; color:#666; margin-bottom:3px; display:block; }
        .gs-strat-grid select { width:100%; }
        /* Bulk settings panel */
        .bulk-settings-panel { background:#f8fafc; border:1px solid #d9e0ea; border-radius:0 0 10px 10px; border-top:none; padding:14px; margin-bottom:14px; display:none; }
        .bulk-settings-grid { display:grid; grid-template-columns:1fr 1fr 1fr 1fr auto; gap:8px; align-items:end; flex-wrap:wrap; }
        .bulk-settings-grid label { font-size:12px; color:#666; margin-bottom:3px; display:block; }
        .bulk-settings-grid input[type="number"],
        .bulk-settings-grid select { width:100%; box-sizing:border-box; padding:7px 9px; border:1px solid #c8d1dd; border-radius:6px; font-size:13px; }
        .supplier-row button.btn-small { white-space:nowrap; }
        @media (max-width:1200px) { .layout { grid-template-columns:1fr; } .sticky-panel { position:static; max-height:none; overflow:visible; } }
        @media (max-width:900px)  { .filters, .price-grid-2, .price-grid-3, .settings-row, .gs-form-grid, .gs-strat-grid, .bulk-settings-grid { grid-template-columns:1fr; } .info-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div>
            <h1 class="title">Prices</h1>
            <div class="subtitle">Управление ценовыми уровнями и стратегиями.</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="badge">Найдено: <?php echo (int)$totalRows; ?></span>
            <button type="button" class="btn btn-small btn-primary" id="recalcAllBtn"
                    onclick="runRecalculateAll(0,0,0)"
                    title="Пересчитать цены для всех товаров"
                    style="display:flex;align-items:center;gap:5px;white-space:nowrap;">
                <span style="font-size:16px;line-height:1;">⟳</span> Цены
            </button>
            <button type="button" class="btn btn-small btn-primary" id="stockUpdateBtn"
                    onclick="runStockUpdate()"
                    title="Обновить остатки из МойСклад"
                    style="white-space:nowrap;">
                &#128230; Остатки
            </button>
            <button type="button" class="btn btn-small btn-primary" id="pushPricesBtn"
                    onclick="pushPrices()"
                    title="Выгрузить цены в OpenCart (offtorg, mff) и МойСклад"
                    style="white-space:nowrap;">
                ⬆ Обновить
            </button>
        </div>
    </div>

    <!-- Прогресс пересчёта (глобальный) -->
    <div class="recalc-progress" id="recalcProgress" style="margin-bottom:16px;">
        <div class="recalc-progress-bar-wrap">
            <div class="recalc-progress-bar" id="recalcBar"></div>
        </div>
        <div class="recalc-progress-text" id="recalcText"></div>
    </div>

    <!-- ════ ГЛОБАЛЬНЫЕ НАСТРОЙКИ ════ -->
    <div class="global-settings-card">
        <div class="global-settings-header" onclick="toggleGlobalSettings()">
            <div>
                <strong>Глобальные настройки наценок</strong>
                <div class="global-settings-summary" id="gsSummary">
                    Продажная: <?php echo ViewHelper::h(number_format((float)$globalSettings['sale_markup_percent'], 2)); ?>%
                    &nbsp;·&nbsp; Оптовая: <?php echo ViewHelper::h(number_format((float)$globalSettings['wholesale_markup_percent'], 2)); ?>%
                    &nbsp;·&nbsp; Дилерская: <?php echo ViewHelper::h(number_format((float)$globalSettings['dealer_markup_percent'], 2)); ?>%
                </div>
            </div>
            <button type="button" class="btn btn-small" id="gsToggleBtn">Изменить</button>
        </div>
        <div class="global-settings-body" id="globalSettingsBody">
            <!-- Тип наценки: простая / ступенчатая -->
            <div style="margin-bottom:12px;display:flex;gap:16px;align-items:center;font-size:13px;">
                <strong>Продажная наценка:</strong>
                <label style="font-weight:normal;cursor:pointer;">
                    <input type="radio" name="gs_markup_type" value="simple"
                        <?php echo empty($globalSettings['use_tiered_markup']) ? 'checked' : ''; ?>
                        onchange="gsToggleMarkupType('simple')">
                    Простая
                </label>
                <label style="font-weight:normal;cursor:pointer;">
                    <input type="radio" name="gs_markup_type" value="tiered"
                        <?php echo !empty($globalSettings['use_tiered_markup']) ? 'checked' : ''; ?>
                        onchange="gsToggleMarkupType('tiered')">
                    Ступенчатая (по цене товара)
                </label>
            </div>

            <!-- Простая наценка -->
            <div id="gs_simple_block" style="<?php echo !empty($globalSettings['use_tiered_markup']) ? 'display:none;' : ''; ?>margin-bottom:12px;">
                <div class="gs-form-grid" style="grid-template-columns:1fr;">
                    <div>
                        <label for="gs_sale_markup">Продажная наценка %</label>
                        <input type="number" id="gs_sale_markup" step="0.01" min="0"
                               value="<?php echo ViewHelper::h($globalSettings['sale_markup_percent']); ?>">
                    </div>
                </div>
            </div>

            <!-- Ступенчатая наценка -->
            <div id="gs_tiered_block" style="<?php echo empty($globalSettings['use_tiered_markup']) ? 'display:none;' : ''; ?>margin-bottom:12px;">
                <div style="font-size:12px;color:#666;margin-bottom:8px;">
                    До 5 ступеней. Пустая строка — пропустить. Применяется наценка наибольшего подходящего порога.
                </div>
                <div style="display:grid;grid-template-columns:160px 120px;gap:6px 12px;align-items:center;">
                    <div style="font-size:11px;color:#999;font-weight:bold;">Закупочная от (грн)</div>
                    <div style="font-size:11px;color:#999;font-weight:bold;">Наценка %</div>
                    <?php
                    $existingTiers = isset($globalSettings['tiers']) ? $globalSettings['tiers'] : array();
                    for ($ti = 1; $ti <= 5; $ti++) {
                        $tData = isset($existingTiers[$ti - 1]) ? $existingTiers[$ti - 1] : array();
                        $tFrom = isset($tData['price_from'])    ? (float)$tData['price_from']    : '';
                        $tPct  = isset($tData['markup_percent']) ? (float)$tData['markup_percent'] : '';
                    ?>
                    <input type="number" id="gs_tier_from_<?php echo $ti; ?>" step="0.01" min="0"
                           placeholder="<?php echo $ti === 1 ? '0' : 'от...'; ?>"
                           value="<?php echo $tFrom !== '' ? ViewHelper::h($tFrom) : ''; ?>"
                           style="padding:6px 8px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;width:100%;box-sizing:border-box;">
                    <input type="number" id="gs_tier_pct_<?php echo $ti; ?>" step="0.01" min="0"
                           placeholder="%"
                           value="<?php echo $tPct !== '' ? ViewHelper::h($tPct) : ''; ?>"
                           style="padding:6px 8px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;width:100%;box-sizing:border-box;">
                    <?php } ?>
                </div>
            </div>

            <div class="gs-form-grid">
                <div>
                    <label for="gs_wholesale_markup">Оптовая наценка %</label>
                    <input type="number" id="gs_wholesale_markup" step="0.01" min="0"
                           value="<?php echo ViewHelper::h($globalSettings['wholesale_markup_percent']); ?>">
                </div>
                <div>
                    <label for="gs_dealer_markup">Дилерская наценка %</label>
                    <input type="number" id="gs_dealer_markup" step="0.01" min="0"
                           value="<?php echo ViewHelper::h($globalSettings['dealer_markup_percent']); ?>">
                </div>
            </div>
            <div class="gs-strat-grid">
                <div>
                    <label for="gs_discount_strategy_id">Стратегия скидок (умолч.)</label>
                    <select id="gs_discount_strategy_id">
                        <option value="">— не задана —</option>
                        <?php foreach ($strategies as $s) {
                            $sel = (isset($globalSettings['discount_strategy_id']) && (int)$globalSettings['discount_strategy_id'] === (int)$s['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $sel; ?>><?php echo ViewHelper::h($s['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label for="gs_quantity_strategy_id">Стратегия кол-ва (умолч.)</label>
                    <select id="gs_quantity_strategy_id">
                        <option value="">— не задана —</option>
                        <?php foreach ($quantityStrategies as $qs) {
                            $sel = (isset($globalSettings['quantity_strategy_id']) && (int)$globalSettings['quantity_strategy_id'] === (int)$qs['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int)$qs['id']; ?>" <?php echo $sel; ?>><?php echo ViewHelper::h($qs['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary btn-small" id="gsSaveBtn" onclick="saveGlobalSettings()">Сохранить</button>
                <span id="gsSaveMsg" style="font-size:13px;display:none;"></span>
            </div>
        </div>
    </div>

    <!-- ════ СТРАТЕГИИ СКИДОК ════ -->
    <div class="global-settings-card strategies-card">
        <div class="global-settings-header" onclick="toggleStrategiesCard()">
            <div>
                <strong>Стратегии скидок</strong>
                <div class="global-settings-summary" id="strategiesSummary">
                    <?php foreach ($strategies as $s) { ?>
                        <?php echo ViewHelper::h($s['name']); ?>:
                        <?php echo ViewHelper::h(number_format((float)$s['small_discount_percent'], 1)); ?>% /
                        <?php echo ViewHelper::h(number_format((float)$s['medium_discount_percent'], 1)); ?>% /
                        <?php echo ViewHelper::h(number_format((float)$s['large_discount_percent'], 1)); ?>%
                        &nbsp;&nbsp;
                    <?php } ?>
                </div>
            </div>
            <button type="button" class="btn btn-small" id="strategiesToggleBtn">Изменить</button>
        </div>
        <div class="global-settings-body" id="strategiesBody">
            <div style="font-size:12px;color:#666;margin-bottom:12px;">
                Мелкий / Опт / Крупный опт — проценты скидки от закупочной цены.
            </div>
            <?php foreach ($strategies as $s) {
                $sid = (int)$s['id'];
            ?>
            <div class="strat-row" style="display:grid;grid-template-columns:200px 110px 110px 110px;gap:8px;align-items:end;margin-bottom:10px;">
                <div>
                    <label for="strat_name_<?php echo $sid; ?>" style="font-size:12px;color:#666;margin-bottom:3px;display:block;">Название</label>
                    <input type="text" id="strat_name_<?php echo $sid; ?>"
                           value="<?php echo ViewHelper::h($s['name']); ?>"
                           style="width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label for="strat_small_<?php echo $sid; ?>" style="font-size:12px;color:#666;margin-bottom:3px;display:block;">Мелкий опт %</label>
                    <input type="number" id="strat_small_<?php echo $sid; ?>" step="0.01" min="0"
                           value="<?php echo ViewHelper::h($s['small_discount_percent']); ?>"
                           style="width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label for="strat_medium_<?php echo $sid; ?>" style="font-size:12px;color:#666;margin-bottom:3px;display:block;">Опт %</label>
                    <input type="number" id="strat_medium_<?php echo $sid; ?>" step="0.01" min="0"
                           value="<?php echo ViewHelper::h($s['medium_discount_percent']); ?>"
                           style="width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label for="strat_large_<?php echo $sid; ?>" style="font-size:12px;color:#666;margin-bottom:3px;display:block;">Крупный опт %</label>
                    <input type="number" id="strat_large_<?php echo $sid; ?>" step="0.01" min="0"
                           value="<?php echo ViewHelper::h($s['large_discount_percent']); ?>"
                           style="width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #c8d1dd;border-radius:6px;font-size:13px;">
                </div>
            </div>
            <?php } ?>
            <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
                <button type="button" class="btn btn-primary btn-small" onclick="saveStrategies()">Сохранить стратегии</button>
                <span id="strategiesSaveMsg" style="font-size:13px;color:#157347;display:none;">Сохранено</span>
            </div>
        </div>
    </div>

    <!-- ════ ИСТОЧНИКИ ЦЕН ПОСТАВЩИКОВ ════ -->
    <div class="global-settings-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <strong>Источники закупочных цен</strong>
                <div class="global-settings-summary">
                    <?php foreach ($allSuppliers as $sup) {
                        $plists = isset($sup['pricelists']) ? $sup['pricelists'] : array();
                        $total = 0; $matched = 0;
                        foreach ($plists as $pl) { $total += (int)$pl['items_total']; $matched += (int)$pl['items_matched']; }
                        $unmatched = $total - $matched;
                        echo ViewHelper::h($sup['name']) . ': ' . $total . ' позиций';
                        if ($unmatched > 0) echo ' <span style="color:#b42318;">(' . $unmatched . ' не сопост.)</span>';
                        echo '&nbsp;&nbsp;';
                    } ?>
                </div>
            </div>
            <?php
                // Строим ссылку: search → сохраняем; иначе если явно выбран товар — его ID
                if ($search !== '') {
                    $__supSearch = $search;
                } elseif ($selectedExplicit > 0) {
                    $__supSearch = (string)$selectedExplicit;
                } else {
                    $__supSearch = '';
                }
                $__supUrl = '/prices/suppliers' . ($__supSearch !== '' ? '?search=' . urlencode($__supSearch) : '');
            ?>
            <a href="<?php echo ViewHelper::h($__supUrl); ?>" class="btn btn-small btn-primary">Управление поставщиками →</a>
        </div>
    </div>

    <div class="layout">

        <!-- ════ ЛЕВАЯ КОЛОНКА — СПИСОК ════ -->
        <div class="card">

            <form method="get" action="/prices" class="filters">
                <input type="hidden" name="sort"  value="<?php echo ViewHelper::h($sort); ?>">
                <input type="hidden" name="order" value="<?php echo ViewHelper::h($order); ?>">
                <input type="hidden" name="page"  value="1">
                <?php if ($selected > 0) { ?>
                    <input type="hidden" name="selected" value="<?php echo (int)$selected; ?>">
                <?php } ?>

                <div class="filter-search">
                    <label>Поиск</label>
                    <div class="chip-input" id="searchChipBox">
                        <input type="text" class="chip-typer" id="searchChipTyper"
                               placeholder="ID, артикул или название…" autocomplete="off">
                    </div>
                    <input type="hidden" name="search" id="search" value="<?php echo ViewHelper::h($search); ?>">
                </div>

                <div class="filter-select">
                    <label for="filter">Фильтр</label>
                    <select name="filter" id="filter">
                        <option value="all"         <?php echo $filter === 'all'         ? 'selected' : ''; ?>>Все товары</option>
                        <option value="manual_only" <?php echo $filter === 'manual_only' ? 'selected' : ''; ?>>С ручными ценами</option>
                        <option value="no_stock"    <?php echo $filter === 'no_stock'    ? 'selected' : ''; ?>>Без остатков</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-apply">Применить</button>
                    <a href="/prices" class="btn btn-ghost">Сброс</a>
                </div>
            </form>

            <div class="bulk-toolbar">
                <div class="bulk-left">
                    <strong>Выбрано:</strong>&nbsp;<span id="selectedCount">0</span>
                    <button type="button" class="btn btn-small btn-ghost" id="bulkClear" style="margin-left:12px;">Снять выбор</button>
                </div>
                <div class="bulk-actions">
                    <button type="button" class="btn btn-small" id="bulkCopyIds">Копировать ID</button>
                    <button type="button" class="btn btn-small" id="bulkRecalculate">Пересчитать</button>
                    <button type="button" class="btn btn-small" id="bulkSettingsToggle">Настройки</button>
                </div>
            </div>

            <!-- Bulk settings panel -->
            <div class="bulk-settings-panel" id="bulkSettingsPanel">
                <div style="font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;letter-spacing:.03em;margin-bottom:10px;">
                    Массовое применение настроек (пустые поля — не изменять)
                </div>
                <div class="bulk-settings-grid">
                    <div>
                        <label for="bulk_sale_markup">Продажная %</label>
                        <input type="number" id="bulk_sale_markup" step="0.01" min="0" placeholder="не менять">
                    </div>
                    <div>
                        <label for="bulk_wholesale_markup">Оптовая %</label>
                        <input type="number" id="bulk_wholesale_markup" step="0.01" min="0" placeholder="не менять">
                    </div>
                    <div>
                        <label for="bulk_dealer_markup">Дилерская %</label>
                        <input type="number" id="bulk_dealer_markup" step="0.01" min="0" placeholder="не менять">
                    </div>
                    <div>
                        <label for="bulk_strategy_id">Стратегия скидок</label>
                        <select id="bulk_strategy_id">
                            <option value="">— не менять —</option>
                            <?php foreach ($strategies as $s) { ?>
                                <option value="<?php echo (int)$s['id']; ?>"><?php echo ViewHelper::h($s['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary btn-small" style="margin-top:18px;white-space:nowrap;"
                                id="bulkApplySettings">Применить к выбранным</button>
                    </div>
                </div>
                <div id="bulkSettingsMsg" style="font-size:13px;margin-top:8px;display:none;"></div>
            </div>

            <table>
                <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="selectAllRows"></th>
                    <th><?php echo pricesSortLink('ID',         'product_id',      $state, $basePath); ?></th>
                    <th><?php echo pricesSortLink('Артикул',    'product_article',  $state, $basePath); ?></th>
                    <th>Название</th>
                    <th><?php echo pricesSortLink('Закупочная', 'price_purchase',   $state, $basePath); ?></th>
                    <th><?php echo pricesSortLink('Продажная',  'price_sale',       $state, $basePath); ?></th>
                    <th><?php echo pricesSortLink('Оптовая',    'price_wholesale',  $state, $basePath); ?></th>
                    <th><?php echo pricesSortLink('Дилерская',  'price_dealer',     $state, $basePath); ?></th>
                    <th><?php echo pricesSortLink('RRP',        'price_rrp',        $state, $basePath); ?></th>
                    <th>Акция</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)) { ?>
                    <?php foreach ($rows as $row) { ?>
                        <?php
                        $isSelected  = ((int)$row['product_id'] === (int)$selected);
                        $isInactive  = isset($row['status']) && (int)$row['status'] === 0;
                        $selectUrl   = ViewHelper::buildUrl($basePath, array_merge($state, array('selected' => (int)$row['product_id'], 'page' => $page)));
                        $hasManual   = !empty($row['manual_price_enabled']) || !empty($row['manual_wholesale_enabled']) || !empty($row['manual_dealer_enabled']);
                        $hasPriceAct = isset($row['price_act']) && $row['price_act'] !== null && $row['price_act'] !== '';
                        ?>
                        <tr class="js-row-click <?php echo $isSelected ? 'selected-row' : ''; ?> <?php echo $hasPriceAct ? 'has-action' : ''; ?>"
                            style="<?php echo $isInactive ? 'opacity:.45;' : ''; ?>"
                            data-url="<?php echo ViewHelper::h($selectUrl); ?>"
                            data-product-id="<?php echo (int)$row['product_id']; ?>">
                            <td><input type="checkbox" class="row-selector" value="<?php echo (int)$row['product_id']; ?>"></td>
                            <td class="num">
                                <a href="<?php echo ViewHelper::h($selectUrl); ?>"><?php echo (int)$row['product_id']; ?></a>
                            </td>
                            <td><?php echo textVal($row['product_article']); ?></td>
                            <td>
                                <a href="<?php echo ViewHelper::h($selectUrl); ?>"><?php echo textVal($row['name']); ?></a>
                                <?php if ($hasManual) { ?><span class="source-tag manual-tag">M</span><?php } ?>
                            </td>
                            <td class="num"><?php echo priceVal($row['price_purchase']); ?></td>
                            <td class="num"><?php echo priceVal($row['price_sale']); ?></td>
                            <td class="num"><?php echo priceVal($row['price_wholesale']); ?></td>
                            <td class="num"><?php echo priceVal($row['price_dealer']); ?></td>
                            <td class="num"><?php echo priceVal($row['price_rrp']); ?></td>
                            <td class="num">
                                <?php if ($hasPriceAct) { ?>
                                    <span class="price-act-val"><?php echo priceVal($row['price_act']); ?></span>
                                    <?php if (!empty($row['act_discount'])) { ?>
                                        <span class="action-tag">-<?php echo (int)$row['act_discount']; ?>%</span>
                                    <?php } elseif (!empty($row['act_super_discont'])) { ?>
                                        <span class="action-tag">↓<?php echo (int)$row['act_super_discont']; ?>%</span>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span style="color:#ccc;">—</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr><td colspan="10" class="empty">Данные не найдены.</td></tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo ViewHelper::h(pricesPageUrl($page - 1, $state, $basePath)); ?>">← Назад</a>
                    <?php } ?>
                    <?php
                    $startPage = max(1, $page - 3);
                    $endPage   = min($totalPages, $page + 3);
                    for ($p = $startPage; $p <= $endPage; $p++) {
                        if ($p == $page) {
                            echo '<span class="current">' . $p . '</span>';
                        } else {
                            echo '<a href="' . ViewHelper::h(pricesPageUrl($p, $state, $basePath)) . '">' . $p . '</a>';
                        }
                    }
                    ?>
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo ViewHelper::h(pricesPageUrl($page + 1, $state, $basePath)); ?>">Вперёд →</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <!-- ════ ПРАВАЯ КОЛОНКА — ПАНЕЛЬ ТОВАРА ════ -->
        <div class="card sticky-panel">
            <?php require __DIR__ . '/product_panel.php'; ?>
        </div>

    </div>
</div>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
(function () {
    // ── Global settings toggle ─────────────────────────────────────────────
    function toggleGlobalSettings() {
        var body = document.getElementById('globalSettingsBody');
        var btn  = document.getElementById('gsToggleBtn');
        if (!body) return;
        if (body.style.display === 'none' || body.style.display === '') {
            body.style.display = 'block';
            if (btn) btn.textContent = 'Свернуть';
        } else {
            body.style.display = 'none';
            if (btn) btn.textContent = 'Изменить';
        }
    }
    window.toggleGlobalSettings = toggleGlobalSettings;

    // ── Strategies card toggle ─────────────────────────────────────────────
    function toggleStrategiesCard() {
        var body = document.getElementById('strategiesBody');
        var btn  = document.getElementById('strategiesToggleBtn');
        if (!body) return;
        if (body.style.display === 'none' || body.style.display === '') {
            body.style.display = 'block';
            if (btn) btn.textContent = 'Свернуть';
        } else {
            body.style.display = 'none';
            if (btn) btn.textContent = 'Изменить';
        }
    }
    window.toggleStrategiesCard = toggleStrategiesCard;

    function saveStrategies() {
        var parts = [];
        var rows = document.querySelectorAll('.strat-row');
        var count = 0;
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var nameEl   = row.querySelector('input[type="text"]');
            var smallEl  = row.querySelector('input[id^="strat_small_"]');
            var mediumEl = row.querySelector('input[id^="strat_medium_"]');
            var largeEl  = row.querySelector('input[id^="strat_large_"]');
            if (!nameEl || !smallEl) continue;
            var idMatch = smallEl.id.match(/strat_small_(\d+)/);
            if (!idMatch) continue;
            var sid = idMatch[1];
            parts.push('strategy[' + sid + '][name]='   + encodeURIComponent(nameEl.value));
            parts.push('strategy[' + sid + '][small]='  + encodeURIComponent(smallEl.value));
            parts.push('strategy[' + sid + '][medium]=' + encodeURIComponent(mediumEl ? mediumEl.value : '0'));
            parts.push('strategy[' + sid + '][large]='  + encodeURIComponent(largeEl  ? largeEl.value  : '0'));
            count++;
        }
        if (count === 0) {
            alert('Нет стратегий для сохранения.');
            return;
        }
        fetch('/prices/api/save_strategies', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: parts.join('&')
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                var msg = document.getElementById('strategiesSaveMsg');
                if (msg) { msg.style.display = 'inline'; setTimeout(function () { msg.style.display = 'none'; }, 3000); }
            } else {
                alert('Ошибка: ' + (data.error ? data.error : 'неизвестная ошибка'));
            }
        })
        .catch(function () { alert('Ошибка сети.'); });
    }
    window.saveStrategies = saveStrategies;

    function gsToggleMarkupType(type) {
        var simple = document.getElementById('gs_simple_block');
        var tiered = document.getElementById('gs_tiered_block');
        if (simple) simple.style.display = (type === 'simple') ? '' : 'none';
        if (tiered) tiered.style.display = (type === 'tiered') ? '' : 'none';
    }
    window.gsToggleMarkupType = gsToggleMarkupType;

    function saveGlobalSettings() {
        var radios      = document.getElementsByName('gs_markup_type');
        var useTiered   = '0';
        for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked && radios[i].value === 'tiered') { useTiered = '1'; break; }
        }

        function gv(id) { var el = document.getElementById(id); return el ? el.value : ''; }

        var params = [
            'use_tiered_markup='        + encodeURIComponent(useTiered),
            'sale_markup_percent='      + encodeURIComponent(gv('gs_sale_markup')),
            'wholesale_markup_percent=' + encodeURIComponent(gv('gs_wholesale_markup')),
            'dealer_markup_percent='    + encodeURIComponent(gv('gs_dealer_markup')),
            'discount_strategy_id='     + encodeURIComponent(gv('gs_discount_strategy_id')),
            'quantity_strategy_id='     + encodeURIComponent(gv('gs_quantity_strategy_id'))
        ];

        for (var t = 1; t <= 5; t++) {
            params.push('tier_from_' + t + '=' + encodeURIComponent(gv('gs_tier_from_' + t)));
            params.push('tier_pct_'  + t + '=' + encodeURIComponent(gv('gs_tier_pct_'  + t)));
        }

        var msg = document.getElementById('gsSaveMsg');
        if (msg) { msg.style.display = 'inline'; msg.style.color = '#555'; msg.textContent = 'Сохраняем...'; }

        fetch('/prices/api/save_global_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.join('&')
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                if (msg) { msg.style.display = 'inline'; msg.style.color = '#b42318'; msg.textContent = 'Ошибка: ' + (data.error || '?'); }
                return;
            }
            if (msg) { msg.style.display = 'inline'; msg.style.color = '#157347'; msg.textContent = 'Сохранено'; }
            setTimeout(function () { if (msg) msg.style.display = 'none'; }, 3000);
        })
        .catch(function () { alert('Ошибка сети.'); });
    }
    window.saveGlobalSettings = saveGlobalSettings;

    function runRecalculateAll(offset, totalProcessed, totalErrors) {
        var progress = document.getElementById('recalcProgress');
        var bar      = document.getElementById('recalcBar');
        var text     = document.getElementById('recalcText');
        var btn      = document.getElementById('recalcAllBtn');

        if (offset === 0) {
            if (btn) { btn.disabled = true; btn.textContent = '⟳ Пересчёт...'; }
            if (bar) bar.style.width = '0%';
        }
        if (progress) progress.style.display = 'block';

        fetch('/prices/api/recalculate_all', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'offset=' + encodeURIComponent(offset) + '&limit=100'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                if (text) { text.style.color = '#b42318'; text.textContent = 'Ошибка: ' + (d.error || '?'); }
                if (btn)  { btn.disabled = false; btn.innerHTML = '<span style="font-size:16px;line-height:1;">⟳</span> Цены'; }
                return;
            }

            var done   = totalProcessed + (d.processed || 0);
            var errors = totalErrors    + (d.errors    || 0);
            var total  = d.total || 1;
            var pct    = Math.min(100, Math.round(done / total * 100));

            if (bar)  bar.style.width = pct + '%';
            if (text) {
                text.style.color = '#555';
                text.textContent = done + ' / ' + total + ' товаров (' + pct + '%)' + (errors > 0 ? ' · ошибок: ' + errors : '');
            }

            if (d.next_offset !== null && d.next_offset !== undefined) {
                runRecalculateAll(d.next_offset, done, errors);
            } else {
                if (bar)  bar.style.width = '100%';
                if (text) {
                    text.style.color = '#157347';
                    text.textContent = 'Готово! Пересчитано: ' + done + ' из ' + total + (errors > 0 ? ', ошибок: ' + errors : '');
                }
                if (btn)  { btn.disabled = false; btn.innerHTML = '<span style="font-size:16px;line-height:1;">⟳</span> Цены'; }
                setTimeout(function () { if (progress) progress.style.display = 'none'; }, 8000);
                pushPrices();
            }
        })
        .catch(function (e) {
            if (text) { text.style.color = '#b42318'; text.textContent = 'Ошибка сети на offset=' + offset; }
            if (btn)  { btn.disabled = false; btn.innerHTML = '<span style="font-size:16px;line-height:1;">⟳</span> Цены'; }
        });
    }
    window.runRecalculateAll = runRecalculateAll;

    // ── Stock update ───────────────────────────────────────────────────────
    function runStockUpdate() {
        var btn      = document.getElementById('stockUpdateBtn');
        var progress = document.getElementById('recalcProgress');
        var bar      = document.getElementById('recalcBar');
        var text     = document.getElementById('recalcText');

        if (btn) { btn.disabled = true; btn.textContent = '⏳ Остатки...'; }
        if (bar)  { bar.style.width = '0%'; }
        if (text) { text.style.color = '#555'; text.textContent = 'Обновляем остатки из МойСклад...'; }
        if (progress) progress.style.display = 'block';

        // Анимация ожидания — ползём до 70% пока ждём ответа
        var fakeTimer = setInterval(function() {
            if (!bar) return;
            var cur = parseFloat(bar.style.width) || 0;
            if (cur < 70) bar.style.width = (cur + 5) + '%';
            else clearInterval(fakeTimer);
        }, 300);

        fetch('/prices/api/update_stock', {method: 'POST'})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            clearInterval(fakeTimer);
            if (btn) { btn.disabled = false; btn.textContent = '📦 Остатки'; }
            if (bar)  bar.style.width = '100%';
            if (d.ok) {
                if (text) {
                    text.style.color = '#157347';
                    text.textContent = 'Готово! Физ.: ' + d.stock_rows + ', произв.: ' + d.virtual_synced + ', quantity: ' + d.quantity_updated;
                }
            } else {
                if (text) { text.style.color = '#b42318'; text.textContent = 'Ошибка: ' + (d.error || '?'); }
            }
            setTimeout(function() { if (progress) progress.style.display = 'none'; }, 8000);
        })
        .catch(function() {
            clearInterval(fakeTimer);
            if (btn) { btn.disabled = false; btn.textContent = '📦 Остатки'; }
            if (text) { text.style.color = '#b42318'; text.textContent = 'Ошибка сети'; }
            setTimeout(function() { if (progress) progress.style.display = 'none'; }, 8000);
        });
    }
    window.runStockUpdate = runStockUpdate;

    // ── Push prices: Phase 1 — sites (off+mff), Phase 2 — MoySklad ──────────
    function pushPrices(onDone) {
        var btn      = document.getElementById('pushPricesBtn');
        var progress = document.getElementById('recalcProgress');
        var bar      = document.getElementById('recalcBar');
        var text     = document.getElementById('recalcText');

        if (btn) { btn.disabled = true; btn.textContent = '⬆ Обновляем...'; }
        if (bar)  bar.style.width = '0%';
        if (text) { text.style.color = '#555'; text.textContent = 'Выгрузка цен: подготовка…'; }
        if (progress) progress.style.display = 'block';

        var total     = 0;
        var statSites = {pushed: 0, skipped: 0, errors: 0};
        var statMs    = {pushed: 0, skipped: 0, errors: 0};

        function show(color, msg) {
            if (text) { text.style.color = color; text.textContent = msg; }
        }
        function setBar(pct) {
            if (bar) bar.style.width = Math.min(100, pct) + '%';
        }
        function abort(msg) {
            if (btn) { btn.disabled = false; btn.textContent = '⬆ Обновить'; }
            show('#b42318', '✗ ' + msg);
            setTimeout(function() { if (progress) progress.style.display = 'none'; }, 8000);
        }

        // Phase 1: сайты (off + mff) — прогресс 0–50%
        function runSites(offset) {
            show('#555', 'Сайты: ' + (offset || 0) + '/' + (total || '…') + ' — ' + statSites.pushed + ' ok');
            fetch('/prices/api/push_prices', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'offset=' + offset + '&limit=100&phase=sites'
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { abort(d.error || 'Ошибка сайтов'); return; }
                if (d.total) total = d.total;
                statSites.pushed += d.stats.pushed || 0;
                statSites.skipped += d.stats.skipped || 0;
                if (d.has_errors) statSites.errors += d.errors.length;
                var done = d.next_offset !== null && d.next_offset !== undefined ? d.next_offset : total;
                setBar(total > 0 ? done / total * 50 : 25);
                show('#555', 'Сайты: ' + done + '/' + total + ' — ' + statSites.pushed + ' ok' + (statSites.errors ? ' · ⚠ ' + statSites.errors + ' ош.' : ''));
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runSites(d.next_offset);
                } else {
                    setBar(50);
                    show('#555', '✓ Сайты (' + statSites.pushed + ') → МойСклад…');
                    runMs(0);
                }
            })
            .catch(function(err) { abort('Сеть (сайты): ' + err); });
        }

        // Phase 2: МойСклад — прогресс 50–100%
        function runMs(offset) {
            fetch('/prices/api/push_prices', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'offset=' + offset + '&limit=50&phase=ms'
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { abort(d.error || 'Ошибка МС'); return; }
                if (d.total) total = d.total;
                statMs.pushed += d.stats.pushed || 0;
                statMs.skipped += d.stats.skipped || 0;
                if (d.has_errors) statMs.errors += d.errors.length;
                var done = d.next_offset !== null && d.next_offset !== undefined ? d.next_offset : total;
                setBar(50 + (total > 0 ? done / total * 50 : 25));
                show('#555', 'МойСклад: ' + done + '/' + total + ' — ' + statMs.pushed + ' ok' + (statMs.errors ? ' · ⚠ ' + statMs.errors + ' ош.' : ''));
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runMs(d.next_offset);
                } else {
                    setBar(100);
                    if (btn) { btn.disabled = false; btn.textContent = '⬆ Обновить'; }
                    var errNote = (statSites.errors + statMs.errors) > 0
                        ? ' · ⚠ ' + (statSites.errors + statMs.errors) + ' ошибок в лог'
                        : '';
                    show('#157347', 'Готово! Сайты: ' + statSites.pushed + ', МС: ' + statMs.pushed + ' (пропущено: ' + statMs.skipped + ')' + errNote);
                    setTimeout(function() { if (progress) progress.style.display = 'none'; }, 8000);
                    if (typeof onDone === 'function') onDone();
                }
            })
            .catch(function(err) { abort('Сеть (МС): ' + err); });
        }

        runSites(0);
    }
    window.pushPrices = pushPrices;

    // ── Row click → navigate ───────────────────────────────────────────────
    document.querySelectorAll('.js-row-click').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a,button,input')) return;
            var url = this.getAttribute('data-url');
            if (url) window.location = url;
        });
    });

    // ── Bulk select ────────────────────────────────────────────────────────
    var selectedIds     = new Set();
    var selectAllRows   = document.getElementById('selectAllRows');
    var rowSelectors    = document.querySelectorAll('.row-selector');
    var selectedCount   = document.getElementById('selectedCount');

    function refreshCounter() {
        var n = selectedIds.size;
        if (selectedCount) selectedCount.textContent = n;
        var clearBtn = document.getElementById('bulkClear');
        if (clearBtn) {
            clearBtn.textContent = n > 0 ? 'Снять выбор (' + n + ')' : 'Снять выбор';
            n > 0 ? clearBtn.classList.add('has-selection') : clearBtn.classList.remove('has-selection');
        }
        if (selectAllRows) {
            var checked = Array.from(rowSelectors).filter(function (cb) { return cb.checked; }).length;
            selectAllRows.checked = rowSelectors.length > 0 && checked === rowSelectors.length;
        }
    }

    rowSelectors.forEach(function (cb) {
        cb.addEventListener('click', function (e) { e.stopPropagation(); });
        cb.addEventListener('change', function () {
            this.checked ? selectedIds.add(this.value) : selectedIds.delete(this.value);
            refreshCounter();
        });
    });

    if (selectAllRows) {
        selectAllRows.addEventListener('click', function (e) { e.stopPropagation(); });
        selectAllRows.addEventListener('change', function () {
            rowSelectors.forEach(function (cb) {
                cb.checked = selectAllRows.checked;
                selectAllRows.checked ? selectedIds.add(cb.value) : selectedIds.delete(cb.value);
            });
            refreshCounter();
        });
    }

    var bulkClear        = document.getElementById('bulkClear');
    var bulkCopyIds      = document.getElementById('bulkCopyIds');
    var bulkRecalculate  = document.getElementById('bulkRecalculate');
    var bulkSettingsToggle = document.getElementById('bulkSettingsToggle');
    var bulkSettingsPanel  = document.getElementById('bulkSettingsPanel');
    var bulkApplySettings  = document.getElementById('bulkApplySettings');

    if (bulkClear) bulkClear.addEventListener('click', function () {
        rowSelectors.forEach(function (cb) { cb.checked = false; });
        selectedIds.clear();
        refreshCounter();
    });

    if (bulkCopyIds) bulkCopyIds.addEventListener('click', function () {
        var ids = Array.from(selectedIds);
        if (!ids.length) { alert('Сначала выбери товары.'); return; }
        navigator.clipboard.writeText(ids.join(',')).then(function () {
            alert('ID скопированы: ' + ids.length);
        });
    });

    if (bulkRecalculate) bulkRecalculate.addEventListener('click', function () {
        var ids = Array.from(selectedIds);
        if (!ids.length) { alert('Сначала выбери товары.'); return; }
        if (!confirm('Пересчитать цены для ' + ids.length + ' товаров?')) return;

        bulkRecalculate.disabled = true;
        bulkRecalculate.textContent = 'Считаем...';

        var promises = ids.map(function (id) {
            return fetch('/prices/api/recalculate', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'product_id=' + encodeURIComponent(id)
            }).then(function (r) { return r.json(); });
        });

        Promise.all(promises).then(function (results) {
            var ok  = results.filter(function (r) { return r.ok; }).length;
            var err = results.filter(function (r) { return !r.ok; }).length;
            alert('Готово: ' + ok + ' ок, ' + err + ' ошибок.');
            if (ok > 0) location.reload();
        }).catch(function () {
            alert('Ошибка при пересчёте.');
        }).finally(function () {
            bulkRecalculate.disabled = false;
            bulkRecalculate.textContent = 'Пересчитать';
        });
    });

    // ── Bulk settings toggle ───────────────────────────────────────────────
    if (bulkSettingsToggle && bulkSettingsPanel) {
        bulkSettingsToggle.addEventListener('click', function () {
            if (bulkSettingsPanel.style.display === 'none' || bulkSettingsPanel.style.display === '') {
                bulkSettingsPanel.style.display = 'block';
                bulkSettingsToggle.textContent = 'Скрыть настройки';
            } else {
                bulkSettingsPanel.style.display = 'none';
                bulkSettingsToggle.textContent = 'Настройки';
            }
        });
    }

    // ── Bulk apply settings ────────────────────────────────────────────────
    if (bulkApplySettings) {
        bulkApplySettings.addEventListener('click', function () {
            var ids = Array.from(selectedIds);
            if (!ids.length) { alert('Сначала выбери товары.'); return; }

            var saleMarkup      = document.getElementById('bulk_sale_markup')      ? document.getElementById('bulk_sale_markup').value      : '';
            var wholesaleMarkup = document.getElementById('bulk_wholesale_markup') ? document.getElementById('bulk_wholesale_markup').value : '';
            var dealerMarkup    = document.getElementById('bulk_dealer_markup')    ? document.getElementById('bulk_dealer_markup').value    : '';
            var strategyId      = document.getElementById('bulk_strategy_id')      ? document.getElementById('bulk_strategy_id').value      : '';

            if (saleMarkup === '' && wholesaleMarkup === '' && dealerMarkup === '' && strategyId === '') {
                alert('Заполни хотя бы одно поле для изменения.');
                return;
            }

            if (!confirm('Применить настройки к ' + ids.length + ' товарам?')) return;

            var params = [
                'product_ids='             + encodeURIComponent(ids.join(',')),
                'sale_markup_percent='     + encodeURIComponent(saleMarkup),
                'wholesale_markup_percent='+ encodeURIComponent(wholesaleMarkup),
                'dealer_markup_percent='   + encodeURIComponent(dealerMarkup),
                'discount_strategy_id='    + encodeURIComponent(strategyId)
            ];

            bulkApplySettings.disabled = true;
            bulkApplySettings.textContent = 'Применяем...';

            fetch('/prices/api/bulk_apply_settings', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params.join('&')
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var msg = document.getElementById('bulkSettingsMsg');
                if (data.ok) {
                    if (msg) {
                        msg.style.display = 'block';
                        msg.style.color = '#157347';
                        msg.textContent = 'Обновлено: ' + data.updated + ' товаров.';
                    }
                } else {
                    var errText = 'Ошибок: ' + (data.errors ? data.errors.length : '?');
                    if (data.error) errText = data.error;
                    if (msg) {
                        msg.style.display = 'block';
                        msg.style.color = '#b42318';
                        msg.textContent = errText + ' (обновлено: ' + (data.updated ? data.updated : 0) + ')';
                    } else {
                        alert(errText);
                    }
                }
            })
            .catch(function () { alert('Ошибка сети.'); })
            .finally(function () {
                bulkApplySettings.disabled = false;
                bulkApplySettings.textContent = 'Применить к выбранным';
            });
        });
    }

    refreshCounter();

    // ── Chip Search ────────────────────────────────────────────────────────
    ChipSearch.init('searchChipBox', 'searchChipTyper', 'search');

    var filterInput   = document.getElementById('filter');
    var strategyInput = document.getElementById('strategy_id');
    var searchForm    = document.getElementById('search') ? document.getElementById('search').closest('form') : null;

    function resetPageAndSubmit() {
        var pageInput = searchForm.querySelector('input[name="page"]');
        if (pageInput) pageInput.value = 1;
        searchForm.submit();
    }

    if (filterInput && searchForm) {
        filterInput.addEventListener('change', resetPageAndSubmit);
    }

    if (strategyInput && searchForm) {
        strategyInput.addEventListener('change', resetPageAndSubmit);
    }
})();
</script>
</body>
</html>
