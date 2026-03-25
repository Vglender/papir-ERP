<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Поставщики и прайс-листы</title>
<link rel="stylesheet" href="/modules/shared/ui.css">
<style>
body { margin:0; padding:0; font-family:Arial,sans-serif; background:#f5f7fb; color:#222; }
.wrap { max-width:1600px; margin:0 auto; padding:24px; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
.title { margin:0; font-size:26px; }
.breadcrumb { font-size:13px; color:#666; margin-bottom:16px; }
.breadcrumb a { color:#1f6feb; text-decoration:none; }
.layout { display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start; }
.card { background:#fff; border:1px solid #d9e0ea; border-radius:12px; padding:16px 20px; box-shadow:0 2px 8px rgba(0,0,0,.04); }
.sticky { position:sticky; top:16px; }
.btn { display:inline-block; padding:8px 14px; border-radius:8px; border:1px solid #c8d1dd; background:#fff; color:#222; cursor:pointer; font-size:13px; box-sizing:border-box; text-align:center; text-decoration:none; transition:background .15s,border-color .15s; }
.btn:hover { background:#f0f4ff; border-color:#a8bde8; }
.btn-primary { background:#1f6feb; border-color:#1f6feb; color:#fff; }
.btn-primary:hover { background:#1558c8; border-color:#1558c8; }
.btn-ghost { background:#f1f5f9; border-color:#d1d9e0; color:#444; }
.btn-ghost:hover { background:#e2e8f0; }
.btn-apply { background:#f1f5f9; border-color:#c8d1dd; color:#333; }
.btn-apply:hover { background:#e8f0fe; border-color:#a8bde8; color:#1a56c4; }
.btn-danger  { background:#fff1f1; border-color:#f5c6c6; color:#b42318; }
.btn-small { padding:5px 9px; font-size:12px; border-radius:6px; }
.btn-xs    { padding:3px 7px; font-size:11px; border-radius:5px; }
.btn-row { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
label { display:block; margin:0 0 4px; font-size:12px; font-weight:bold; color:#555; }
input[type="text"]:not(.chip-typer), input[type="number"], select { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #c8d1dd; border-radius:7px; font-size:13px; background:#fff; }
.supplier-block { margin-bottom:20px; }
.supplier-header { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f8fafc; border:1px solid #d9e0ea; border-radius:8px 8px 0 0; cursor:default; }
.supplier-name { font-weight:bold; font-size:14px; }
.source-tag { font-size:11px; padding:2px 6px; border-radius:4px; background:#f0f4f8; color:#555; margin-left:5px; }
.cost-tag { background:#fff4e5; color:#b26a00; }
.pricelist-row { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border:1px solid #d9e0ea; border-top:none; background:#fff; gap:10px; flex-wrap:wrap; }
.pricelist-row:last-child { border-radius:0 0 8px 8px; }
.pricelist-row.active-pl { background:#f0f6ff; }
.pl-name { font-size:13px; font-weight:bold; }
.pl-stats { font-size:12px; color:#666; margin-top:2px; }
.stat-ok  { color:#157347; font-weight:bold; }
.stat-bad { color:#b42318; font-weight:bold; }
/* Items table */
table { width:100%; border-collapse:collapse; table-layout:fixed; }
col.col-sku   { width:78px; } col.col-model { width:52px; } col.col-name { width:auto; }
col.col-price { width:68px; } col.col-rrp   { width:70px; } col.col-stock { width:64px; }
col.col-status { width:72px; } col.col-cat  { width:118px; } col.col-act  { width:32px; }
th, td { padding:5px 7px; border-bottom:1px solid #e8edf3; text-align:left; font-size:12px; vertical-align:top; word-wrap:break-word; overflow-wrap:break-word; }
th { background:#f8fafc; font-size:10px; text-transform:uppercase; letter-spacing:.03em; color:#555; white-space:nowrap; vertical-align:middle; }
tbody tr:hover td { background:#eef4ff; }
.num { font-variant-numeric:tabular-nums; }
/* Pricelist name inline edit */
.pl-name-wrap { display:flex; align-items:center; gap:4px; }
.pl-name-text { cursor:default; }
.pl-name-pencil { font-size:11px; color:#aaa; cursor:pointer; opacity:0; transition:opacity .15s; }
.pl-name-wrap:hover .pl-name-pencil { opacity:1; }
.pl-name-input { display:none; font-size:13px; font-weight:bold; border:1px solid #c8d1dd; border-radius:5px; padding:2px 6px; width:160px; }
.match-badge { display:inline-block; padding:2px 7px; border-radius:999px; font-size:11px; white-space:nowrap; }
.mb-auto-sku   { background:#edfdf3; color:#157347; }
.mb-auto-model { background:#eef4ff; color:#1f4db8; }
.mb-manual     { background:#fff4e5; color:#b26a00; }
.mb-ignored    { background:#f5f5f5; color:#999; }
.mb-none       { background:#fff1f1; color:#b42318; }
.filters { display:flex; gap:10px; align-items:flex-end; flex-wrap:nowrap; margin-bottom:14px; }
.filters > .filter-search { flex:1 1 auto; min-width:180px; }
.filters > .filter-actions { flex:0 0 auto; display:flex; gap:8px; align-items:flex-end; }
.filters > div { flex:0 0 auto; }
.filters select { width:160px; }
.toggle-group { display:inline-flex; border-radius:7px; overflow:hidden; }
.toggle-group label { display:block; }
.toggle-group input[type=radio] { display:none; }
.toggle-group label span { display:block; padding:6px 10px; font-size:12px; cursor:pointer; background:#fff; color:#444; white-space:nowrap; border:1px solid #c8d1dd; border-right:none; transition:background .12s,color .12s; }
.toggle-group label:first-child span { border-radius:7px 0 0 7px; }
.toggle-group label:last-child span { border-right:1px solid #c8d1dd; border-radius:0 7px 7px 0; }
.toggle-group input[type=radio]:checked + span { background:#1f6feb; color:#fff; border-color:#1f6feb; }
.pagination { display:flex; gap:6px; flex-wrap:wrap; margin-top:14px; }
.pagination a, .pagination span { display:inline-block; padding:6px 10px; border:1px solid #d9e0ea; border-radius:7px; text-decoration:none; color:#222; background:#fff; font-size:13px; }
.pagination .cur { background:#1f6feb; border-color:#1f6feb; color:#fff; }
/* Sheet config panel */
.sheet-config { display:none; border:1px solid #d9e0ea; border-radius:8px; padding:14px; background:#fafcff; margin-top:10px; }
.cfg-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px; }
.cfg-cols { display:grid; grid-template-columns:repeat(5,1fr); gap:6px; margin-bottom:10px; }
/* Match modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border-radius:12px; padding:24px; width:560px; max-width:95vw; box-shadow:0 8px 40px rgba(0,0,0,.18); }
.modal h3 { margin:0 0 14px; font-size:17px; }
.modal-search { margin-bottom:10px; }
.modal-results { max-height:280px; overflow-y:auto; border:1px solid #e8edf3; border-radius:8px; }
.modal-result-row { padding:10px 12px; border-bottom:1px solid #f0f0f0; cursor:pointer; transition:background .12s; }
.modal-result-row:last-child { border-bottom:none; }
.modal-result-row:hover { background:#f0f6ff; }
.mr-article { font-size:12px; color:#888; }
.mr-name { font-size:13px; font-weight:bold; }
.modal-btns { display:flex; gap:8px; margin-top:14px; justify-content:flex-end; }
@media(max-width:900px) { .layout { grid-template-columns:1fr; } .sticky { position:static; } .cfg-cols { grid-template-columns:1fr 1fr; } }
/* Remove number input spinners in table cells */
td input[type=number]{ -moz-appearance:textfield; }
td input[type=number]::-webkit-outer-spin-button,
td input[type=number]::-webkit-inner-spin-button{ display:none; }
/* Unsaved field highlight */
.input-dirty { background:#fffbe6 !important; border-color:#f0b429 !important; box-shadow:0 0 0 2px rgba(240,180,41,.18); }
/* Action dropdown */
.act-wrap{ position:relative; display:inline-block; }
.act-dot{ background:none; border:1px solid #d5dce6; border-radius:4px; padding:1px 6px; cursor:pointer; font-size:15px; color:#666; line-height:1.2; }
.act-dot:hover{ background:#f0f6ff; border-color:#aac4f0; }
.act-dd{ display:none; position:absolute; right:0; top:100%; z-index:300; background:#fff; border:1px solid #d9e0ea; border-radius:7px; box-shadow:0 4px 14px rgba(0,0,0,.13); min-width:136px; padding:4px 0; }
.act-dd.open{ display:block; }
.act-dd button{ display:block; width:100%; padding:6px 12px; font-size:12px; text-align:left; background:none; border:none; cursor:pointer; white-space:nowrap; color:#222; }
.act-dd button:hover{ background:#f0f6ff; }
.act-dd button.dd-danger{ color:#b42318; }
.act-dd .dd-sep{ margin:3px 0; border:none; border-top:1px solid #eee; }
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div>
            <h1 class="title">Поставщики и прайс-листы</h1>
            <div class="breadcrumb"><a href="<?php echo ViewHelper::h('/prices' . ($search !== '' ? '?search=' . urlencode($search) : '')); ?>">← Цены</a></div>
        </div>
        <div class="btn-row" style="align-items:center;">
            <?php
                $__searchQ   = $search !== '' ? '&search=' . urlencode($search) : '';
                $__toggleUrl = $showAll
                    ? '/prices/suppliers?show_all=0' . $__searchQ
                    : '/prices/suppliers?show_all=1' . $__searchQ;
                $__toggleTitle = $showAll ? 'Скрыть — вернуться к прайсам' : 'Показать все товары из всех прайсов';
                $__toggleText  = $showAll ? '⊗ Скрыть все' : '⊕ Все товары';
            ?>
            <a href="<?php echo ViewHelper::h($__toggleUrl); ?>"
               class="btn btn-small" title="<?php echo $__toggleTitle; ?>">
                <?php echo $__toggleText; ?>
            </a>
            <button class="btn btn-small btn-primary" id="updateBkBtn" onclick="updateBK(this)" title="Пересчитать все цены (БК + акции)">⟳ БК</button>
            <button class="btn btn-small" id="pushPricesBtn" onclick="pushPrices()" title="Выгрузить цены в OpenCart и МойСклад">⬆ Выгрузить цены</button>
            <span id="recalc_msg" style="font-size:12px;color:#555;"></span>
        </div>
        <div id="pushPricesProgress" style="display:none;margin-top:8px;width:100%;"></div>
    </div>

    <div class="layout">

        <!-- ════ ЛЕВАЯ ПАНЕЛЬ — СПИСОК ПОСТАВЩИКОВ ════ -->
        <div class="sticky">
            <div class="card" style="padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <div style="font-size:13px;font-weight:bold;">Поставщики</div>
                    <div class="btn-row">
                        <button class="btn btn-small" id="syncAllBtn" onclick="syncAll(this)" title="Синхронизировать все активные прайсы">↻</button>
                        <button class="btn btn-small btn-primary" onclick="openAddPricelistModal(0)">+ Прайс</button>
                        <button class="btn btn-small" onclick="openAddSupplierModal()">+ Поставщик</button>
                    </div>
                </div>
                <div id="syncAllMsg" style="font-size:11px;color:#555;min-height:14px;margin-bottom:4px;"></div>

                <?php foreach ($suppliers as $sup) { ?>
                <div class="supplier-block">
                    <div class="supplier-header">
                        <div>
                            <span class="supplier-name"><?php echo ViewHelper::h($sup['name']); ?></span>
                            <span class="source-tag"><?php echo ViewHelper::h($sup['source_type']); ?></span>
                            <?php if ($sup['is_cost_source']) { ?>
                                <span class="source-tag cost-tag">себестоимость</span>
                            <?php } ?>
                        </div>
                        <div class="btn-row">
                            <button class="btn btn-xs" onclick="openAddPricelistModal(<?php echo (int)$sup['id']; ?>)" title="Добавить прайс этому поставщику">+ прайс</button>
                            <button class="btn btn-xs btn-danger" onclick="deleteSupplier(<?php echo (int)$sup['id']; ?>, <?php echo ViewHelper::h(json_encode($sup['name'])); ?>)" title="Удалить поставщика">✕</button>
                        </div>
                    </div>
                    <?php foreach ($sup['pricelists'] as $pl) {
                        $plId     = (int)$pl['id'];
                        $isActive = $pricelistId === $plId;
                        $total    = (int)$pl['items_total'];
                        $matched  = (int)$pl['items_matched'];
                        $unmatched = $total - $matched;
                    ?>
                    <?php
                        $plParams = array('pricelist_id' => $plId, 'show_all' => 0);
                        if ($search !== '') $plParams['search'] = $search;
                        $plUrl    = ViewHelper::h(suppliersUrl($plParams, $basePath));
                        $plActive = !empty($pl['is_active']);
                        $plStyle  = $plActive ? '' : 'opacity:.45;';
                    ?>
                    <div class="pricelist-row <?php echo $isActive ? 'active-pl' : ''; ?>"
                         style="cursor:pointer;<?php echo $plStyle; ?>"
                         id="plrow_<?php echo $plId; ?>"
                         onclick="if(!event.target.closest('button,.pl-name-pencil,.pl-name-input'))window.location='<?php echo $plUrl; ?>'">
                        <div>
                            <div class="pl-name pl-name-wrap" style="color:<?php echo $plActive ? '#1f6feb' : '#aaa'; ?>;font-weight:bold;">
                                <span class="pl-name-text" id="pl_name_text_<?php echo $plId; ?>"><?php echo ViewHelper::h($pl['name']); ?></span>
                                <span class="pl-name-pencil" onclick="startRename(<?php echo $plId; ?>)" title="Переименовать">✎</span>
                                <input class="pl-name-input" id="pl_name_input_<?php echo $plId; ?>"
                                       value="<?php echo ViewHelper::h($pl['name']); ?>"
                                       onblur="submitRename(<?php echo $plId; ?>)"
                                       onkeydown="if(event.key==='Enter')this.blur();if(event.key==='Escape')cancelRename(<?php echo $plId; ?>);">
                                <?php if (!$plActive) { ?><span style="font-size:11px;font-weight:normal;color:#aaa;"> (неактивен)</span><?php } ?>
                                <?php if (!empty($pl['allow_manual_edit'])) { ?><span style="font-size:11px;color:#b26a00;margin-left:4px;font-weight:bold;" title="Ручное редактирование включено">[ред]</span><?php } ?>
                            </div>
                            <div class="pl-stats">
                                Всего: <?php echo $total; ?>&nbsp;
                                <span class="stat-ok"><?php echo $matched; ?> сопост.</span>&nbsp;
                                <?php if ($unmatched > 0) { ?>
                                    <span class="stat-bad"><?php echo $unmatched; ?> не найдено</span>
                                <?php } ?>
                                <?php if (!empty($pl['last_synced_at'])) { ?>
                                    <br><span style="color:#aaa;"><?php echo ViewHelper::h($pl['last_synced_at']); ?></span>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="btn-row">
                            <button class="btn btn-small"
                                    id="toggle_btn_<?php echo $plId; ?>"
                                    title="<?php echo $plActive ? 'Деактивировать' : 'Активировать'; ?>"
                                    onclick="togglePricelist(<?php echo $plId; ?>, <?php echo $plActive ? 1 : 0; ?>)">
                                <?php echo $plActive ? '●' : '○'; ?>
                            </button>
                            <?php if ($pl['source_type'] === 'google_sheets') { ?>
                                <button class="btn btn-small" onclick="toggleSheetConfig(<?php echo $plId; ?>)">⚙</button>
                            <?php } ?>
                            <?php if ($plActive) { ?>
                            <button class="btn btn-primary btn-small"
                                    id="sync_btn_<?php echo $plId; ?>"
                                    onclick="syncPricelist(<?php echo $plId; ?>)">↻</button>
                            <?php } ?>
                            <button class="btn btn-small<?php echo !empty($pl['allow_manual_edit']) ? ' btn-primary' : ''; ?>"
                                    id="manual_edit_btn_<?php echo $plId; ?>"
                                    onclick="toggleManualEdit(<?php echo $plId; ?>, '<?php echo ViewHelper::h($search); ?>', <?php echo !empty($pl['allow_manual_edit']) ? 1 : 0; ?>, <?php echo $showAll ? 1 : 0; ?>)"
                                    title="Ручное редактирование">✎</button>
                            <button class="btn btn-small btn-danger"
                                    onclick="deletePricelist(<?php echo $plId; ?>, <?php echo ViewHelper::h(json_encode($pl['name'])); ?>)"
                                    title="Удалить прайс">✕</button>
                            <span id="sync_msg_<?php echo $plId; ?>" style="font-size:11px;display:none;"></span>
                        </div>
                    </div>

                    <?php if ($pl['source_type'] === 'google_sheets') {
                        $cfg = isset($sheetConfigs[$plId]) ? $sheetConfigs[$plId] : array();
                    ?>
                    <div id="sheet_config_<?php echo $plId; ?>" class="sheet-config">
                        <div style="font-size:11px;color:#888;margin-bottom:8px;">
                            ID таблицы: …/spreadsheets/d/<strong>ID</strong>/edit
                        </div>
                        <div class="cfg-grid">
                            <div style="grid-column:1/-1;">
                                <label>ID Google Таблицы</label>
                                <input type="text" id="gs_sid_<?php echo $plId; ?>"
                                       value="<?php echo ViewHelper::h(isset($cfg['spreadsheet_id']) ? $cfg['spreadsheet_id'] : ''); ?>"
                                       placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms">
                            </div>
                            <div>
                                <label>Лист (пусто=Sheet1)</label>
                                <input type="text" id="gs_sn_<?php echo $plId; ?>"
                                       value="<?php echo ViewHelper::h(isset($cfg['sheet_name']) ? $cfg['sheet_name'] : ''); ?>">
                            </div>
                            <div>
                                <label>Заголовков строк</label>
                                <input type="number" id="gs_hr_<?php echo $plId; ?>" min="0"
                                       value="<?php echo (int)(isset($cfg['header_row']) ? $cfg['header_row'] : 1); ?>">
                            </div>
                        </div>
                        <div class="cfg-cols">
                            <?php $colDefs = array('col_sku'=>'Артикул','col_model'=>'id_off','col_name'=>'Название','col_price_cost'=>'Себест.','col_price_rrp'=>'RRP');
                            foreach ($colDefs as $ck => $cl) { ?>
                            <div>
                                <label><?php echo $cl; ?></label>
                                <input type="text" id="gs_<?php echo $ck; ?>_<?php echo $plId; ?>"
                                       value="<?php echo ViewHelper::h(isset($cfg[$ck]) ? $cfg[$ck] : ''); ?>"
                                       placeholder="A" style="text-transform:uppercase;">
                            </div>
                            <?php } ?>
                        </div>
                        <div class="btn-row">
                            <button class="btn btn-primary btn-small" onclick="saveSheetConfig(<?php echo $plId; ?>)">Сохранить</button>
                            <span id="gs_msg_<?php echo $plId; ?>" style="font-size:12px;display:none;"></span>
                        </div>
                    </div>
                    <?php } ?>

                    <?php } ?>
                </div>
                <?php } ?>

            </div>
        </div>

        <!-- ════ ПРАВАЯ ПАНЕЛЬ — СТРОКИ ПРАЙСА ════ -->
        <div>
        <?php if ($showAll) { ?>
            <div class="card">
                <div style="margin-bottom:14px;">
                    <strong style="font-size:15px;"><?php echo $unmatchedOnly ? 'Несопоставленные позиции' : 'Все товары (все активные прайсы)'; ?></strong>
                    <div style="font-size:12px;color:#666;margin-top:2px;">Итого: <?php echo $totalItems; ?> строк<?php echo $unmatchedOnly ? ' без сопоставления' : ' (без игнорируемых)'; ?></div>
                </div>
                <!-- Фильтры -->
                <form method="get" action="/prices/suppliers" class="filters" id="showAllSearchForm">
                    <input type="hidden" name="show_all" value="1">
                    <input type="hidden" name="page" value="1">
                    <div class="filter-search">
                        <label>Поиск</label>
                        <div class="chip-input" id="showAllChipBox">
                            <input type="text" class="chip-typer" id="showAllChipTyper"
                                   placeholder="ID, артикул або назва…" autocomplete="off">
                        </div>
                        <input type="hidden" name="search" id="showAllSearchHidden" value="<?php echo ViewHelper::h($search); ?>">
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;white-space:nowrap;">
                            <input type="checkbox" name="unmatched_only" value="1"
                                   <?php echo $unmatchedOnly ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            Несопоставленные
                        </label>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-apply">Применить</button>
                        <a href="/prices/suppliers?show_all=1" class="btn btn-ghost">Сброс</a>
                    </div>
                </form>
                <?php if (empty($items)) { ?>
                    <div style="color:#777;padding:20px 0;">Нет строк.</div>
                <?php } else { ?>
                <div style="overflow-x:auto;">
                <table>
                    <colgroup>
                        <col style="width:90px"><col style="width:44px"><col class="col-sku"><col class="col-model"><col class="col-name">
                        <col class="col-price"><col class="col-rrp"><col class="col-stock">
                        <col class="col-status"><col class="col-cat">
                    </colgroup>
                    <thead><tr>
                        <th>Прайс</th>
                        <th>ID</th>
                        <th>Артикул</th><th>model</th><th>Название</th>
                        <th>Цена</th><th>RRP</th><th>Ост.</th>
                        <th>Статус</th><th>Каталог</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $item) {
                        $itemId      = (int)$item['id'];
                        $matchType   = isset($item['match_type']) ? $item['match_type'] : null;
                        $productId   = isset($item['product_id']) ? (int)$item['product_id'] : null;
                        $ppProductId = isset($item['pp_product_id']) ? (int)$item['pp_product_id'] : null;
                        $isMatched   = $productId !== null && $matchType !== 'ignored';
                    ?>
                    <tr>
                        <td style="font-size:11px;color:#666;"><?php echo ViewHelper::h($item['supplier_name_item']); ?><br><span style="color:#aaa;"><?php echo ViewHelper::h($item['pricelist_name']); ?></span></td>
                        <td style="font-family:monospace;font-size:11px;color:#555;"><?php echo $ppProductId ? $ppProductId : '—'; ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['raw_sku'] ?: '—'); ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['raw_model'] ?: '—'); ?></td>
                        <td title="<?php echo ViewHelper::h($item['raw_name']); ?>"><?php echo ViewHelper::h($item['raw_name'] ?: '—'); ?></td>
                        <td class="num"><?php echo $item['price_cost'] !== null ? number_format((float)$item['price_cost'], 2, '.', '') : '—'; ?></td>
                        <td class="num"><?php echo $item['price_rrp']  !== null ? number_format((float)$item['price_rrp'],  2, '.', '') : '—'; ?></td>
                        <td class="num"><?php echo $item['stock']      !== null ? (int)$item['stock'] : '—'; ?></td>
                        <td>
                            <?php if ($matchType === 'auto_model') { ?><span class="match-badge mb-auto-model">id_off</span>
                            <?php } elseif ($matchType === 'auto_sku') { ?><span class="match-badge mb-auto-sku">артикул</span>
                            <?php } elseif ($matchType === 'manual') { ?><span class="match-badge mb-manual">вручную</span>
                            <?php } else { ?><span class="match-badge mb-none">не найден</span><?php } ?>
                        </td>
                        <td>
                            <?php if ($isMatched) { ?>
                                <span style="color:#1f6feb;font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['product_article'] ?: '#' . $productId); ?></span>
                                <br><span style="color:#555;font-size:11px;"><?php echo ViewHelper::h(mb_strimwidth($item['catalog_name'], 0, 28, '…')); ?></span>
                            <?php } else { ?>—<?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>
                <!-- Pagination for show_all -->
                <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++) {
                        $url = '/prices/suppliers?show_all=1&search=' . urlencode($search) . '&page=' . $p;
                        if ($p === $page) echo '<span class="cur">' . $p . '</span>';
                        else echo '<a href="' . ViewHelper::h($url) . '">' . $p . '</a>';
                    } ?>
                </div>
                <?php } ?>
                <?php } ?>
            </div>
        <?php } elseif ($pricelist) { ?>
            <div class="card">
                <?php $manualEdit = !empty($pricelist['allow_manual_edit']); ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                    <div>
                        <strong style="font-size:15px;"><?php echo ViewHelper::h($pricelist['supplier_name']); ?>
                            &nbsp;/&nbsp; <?php echo ViewHelper::h($pricelist['name']); ?></strong>
                        <div style="font-size:12px;color:#666;margin-top:2px;">
                            Всего: <?php echo (int)$pricelist['items_total']; ?> &nbsp;·&nbsp;
                            Сопост.: <span style="color:#157347;font-weight:bold;"><?php echo (int)$pricelist['items_matched']; ?></span> &nbsp;·&nbsp;
                            Не найдено: <span style="color:#b42318;font-weight:bold;"><?php echo max(0, (int)$pricelist['items_total'] - (int)$pricelist['items_matched']); ?></span>
                        </div>
                    </div>
                    <?php if ($manualEdit) { ?>
                    <div class="btn-row">
                        <button class="btn btn-small btn-primary" onclick="openAddItemModal(<?php echo $pricelistId; ?>)">+ Строка</button>
                        <button class="btn btn-small btn-primary" id="saveManualEditsBtn" onclick="saveManualEdits()">Сохранить изменения</button>
                    </div>
                    <?php } ?>
                </div>

                <!-- Фильтры -->
                <form method="get" action="/prices/suppliers" class="filters" id="plSearchForm">
                    <input type="hidden" name="pricelist_id" value="<?php echo $pricelistId; ?>">
                    <input type="hidden" name="show_all" value="0">
                    <input type="hidden" name="page" value="1">
                    <div class="filter-search">
                        <label>Поиск</label>
                        <div class="chip-input" id="plChipBox">
                            <input type="text" class="chip-typer" id="plChipTyper"
                                   placeholder="ID, артикул або назва…" autocomplete="off">
                        </div>
                        <input type="hidden" name="search" id="plSearchHidden" value="<?php echo ViewHelper::h($search); ?>">
                    </div>
                    <div>
                        <label>Статус</label>
                        <select name="match_filter" onchange="this.form.submit()">
                            <option value="all"       <?php echo $matchFilter==='all'       ?'selected':''; ?>>Все</option>
                            <option value="matched"   <?php echo $matchFilter==='matched'   ?'selected':''; ?>>Сопоставлены</option>
                            <option value="unmatched" <?php echo $matchFilter==='unmatched' ?'selected':''; ?>>Не найдены</option>
                            <option value="ignored"   <?php echo $matchFilter==='ignored'   ?'selected':''; ?>>Игнорируются</option>
                        </select>
                    </div>
                    <div>
                        <label>Остаток</label>
                        <div class="toggle-group">
                            <label><input type="radio" name="stock_filter" value="all"       onchange="this.form.submit()" <?php echo $stockFilter==='all'       ?'checked':''; ?>><span>Все</span></label>
                            <label><input type="radio" name="stock_filter" value="has_stock" onchange="this.form.submit()" <?php echo $stockFilter==='has_stock' ?'checked':''; ?>><span>Есть</span></label>
                            <label><input type="radio" name="stock_filter" value="no_stock"  onchange="this.form.submit()" <?php echo $stockFilter==='no_stock'  ?'checked':''; ?>><span>Нет</span></label>
                        </div>
                    </div>
                    <div>
                        <label>RRP</label>
                        <div class="toggle-group">
                            <label><input type="radio" name="rrp_filter" value="all"     onchange="this.form.submit()" <?php echo $rrpFilter==='all'     ?'checked':''; ?>><span>Все</span></label>
                            <label><input type="radio" name="rrp_filter" value="has_rrp" onchange="this.form.submit()" <?php echo $rrpFilter==='has_rrp' ?'checked':''; ?>><span>С RRP</span></label>
                            <label><input type="radio" name="rrp_filter" value="no_rrp"  onchange="this.form.submit()" <?php echo $rrpFilter==='no_rrp'  ?'checked':''; ?>><span>Без RRP</span></label>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-apply">Применить</button>
                        <a href="/prices/suppliers?pricelist_id=<?php echo $pricelistId; ?>" class="btn btn-ghost">Сброс</a>
                    </div>
                </form>

                <!-- Таблица строк -->
                <?php if (empty($items)) { ?>
                    <div style="color:#777;padding:20px 0;">Нет строк.</div>
                <?php } else { ?>
                <div style="overflow-x:auto;">
                <table>
                    <colgroup>
                        <col class="col-sku"><col class="col-model"><col class="col-name">
                        <col class="col-price"><col class="col-rrp"><col class="col-stock">
                        <col class="col-status"><col class="col-cat"><col class="col-act">
                    </colgroup>
                    <?php
                    $isCostSource    = !empty($pricelist['is_cost_source']);
                    ?>
                    <thead>
                    <tr>
                        <th>Артикул</th>
                        <th>model</th>
                        <th>Название</th>
                        <th><?php echo $isCostSource ? 'Сб-сть' : 'Цена пост.'; ?></th>
                        <th>RRP</th>
                        <th>Ост.</th>
                        <th>Статус</th>
                        <th>Каталог</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item) {
                        $itemId    = (int)$item['id'];
                        $matchType = isset($item['match_type']) ? $item['match_type'] : null;
                        $productId = isset($item['product_id']) ? (int)$item['product_id'] : null;
                        $isIgnored = $matchType === 'ignored';
                        $isMatched = $productId !== null && !$isIgnored;
                    ?>
                    <tr id="item_row_<?php echo $itemId; ?>" style="<?php echo $isIgnored ? 'opacity:.45;' : ''; ?>">
                        <td style="font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['raw_sku'] ?: '—'); ?></td>
                        <td style="font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['raw_model'] ?: '—'); ?></td>
                        <td title="<?php echo ViewHelper::h($item['raw_name']); ?>">
                            <?php if ($manualEdit) { ?>
                                <input type="text" class="edit-name" style="width:100%;font-size:11px;padding:2px 4px;" data-id="<?php echo $itemId; ?>" value="<?php echo ViewHelper::h($item['raw_name'] ?: ''); ?>">
                            <?php } else { ?>
                                <?php echo ViewHelper::h($item['raw_name'] ?: '—'); ?>
                            <?php } ?>
                        </td>
                        <td class="num">
                            <?php if ($manualEdit) { ?>
                                <input type="number" step="0.01" class="edit-price-cost" style="width:100%;font-size:11px;padding:2px 4px;" data-id="<?php echo $itemId; ?>" value="<?php echo $item['price_cost'] !== null ? (float)$item['price_cost'] : ''; ?>">
                            <?php } else { ?>
                                <?php echo $item['price_cost'] !== null ? number_format((float)$item['price_cost'], 2, '.', '') : '—'; ?>
                            <?php } ?>
                        </td>
                        <td class="num">
                            <?php if ($manualEdit) { ?>
                                <input type="number" step="0.01" class="edit-price-rrp" style="width:100%;font-size:11px;padding:2px 4px;" data-id="<?php echo $itemId; ?>" value="<?php echo $item['price_rrp'] !== null ? (float)$item['price_rrp'] : ''; ?>">
                            <?php } else { ?>
                                <?php echo $item['price_rrp'] !== null ? number_format((float)$item['price_rrp'], 2, '.', '') : '—'; ?>
                            <?php } ?>
                        </td>
                        <td class="num">
                            <?php if ($manualEdit) { ?>
                                <input type="number" class="edit-stock" style="width:100%;font-size:11px;padding:2px 4px;" data-id="<?php echo $itemId; ?>" value="<?php echo $item['stock'] !== null ? (int)$item['stock'] : ''; ?>">
                            <?php } else {
                                if ($isWarehousePl && isset($item['warehouse_stock']) && $item['warehouse_stock'] !== null) {
                                    echo (int)$item['warehouse_stock'];
                                } elseif ($item['stock'] !== null) {
                                    echo (int)$item['stock'];
                                } else {
                                    echo '—';
                                }
                            } ?>
                        </td>
                        <td>
                            <?php if ($isIgnored) { ?>
                                <span class="match-badge mb-ignored">Игнор.</span>
                            <?php } elseif ($matchType === 'auto_model') { ?>
                                <span class="match-badge mb-auto-model">id_off</span>
                            <?php } elseif ($matchType === 'auto_sku') { ?>
                                <span class="match-badge mb-auto-sku">артикул</span>
                            <?php } elseif ($matchType === 'manual') { ?>
                                <span class="match-badge mb-manual">вручную</span>
                            <?php } else { ?>
                                <span class="match-badge mb-none">не найден</span>
                            <?php } ?>
                        </td>
                        <td id="item_catalog_<?php echo $itemId; ?>">
                            <?php if ($isMatched) { ?>
                                <span style="color:#1f6feb;font-family:monospace;font-size:11px;"><?php echo ViewHelper::h($item['product_article'] ?: '#' . $productId); ?></span>
                                <br><span style="color:#555;font-size:11px;"><?php echo ViewHelper::h(mb_strimwidth($item['catalog_name'], 0, 28, '…')); ?></span>
                            <?php } else { ?>—<?php } ?>
                        </td>
                        <td style="text-align:center;padding:2px 4px;">
                            <div class="act-wrap">
                                <button class="act-dot" onclick="toggleActDD(<?php echo $itemId; ?>)">⋮</button>
                                <div class="act-dd" id="act_dd_<?php echo $itemId; ?>">
                                    <?php if (!$isIgnored) { ?>
                                        <button onclick="closeActDD();openMatchModal(<?php echo $itemId; ?>, <?php echo ViewHelper::h(json_encode($item['raw_sku'] . ' ' . $item['raw_name'])); ?>)">🔍 Найти</button>
                                        <?php if ($isMatched) { ?>
                                            <button class="dd-danger" onclick="closeActDD();doAction(<?php echo $itemId; ?>,'unmatch')">✕ Открепить</button>
                                        <?php } ?>
                                        <button onclick="closeActDD();doAction(<?php echo $itemId; ?>,'ignore')">⊘ Игнорировать</button>
                                    <?php } else { ?>
                                        <button onclick="closeActDD();doAction(<?php echo $itemId; ?>,'unignore')">↩ Восстановить</button>
                                    <?php } ?>
                                    <?php if ($manualEdit) { ?>
                                        <hr class="dd-sep">
                                        <button class="dd-danger" onclick="closeActDD();deletePricelistItem(<?php echo $itemId; ?>)">🗑 Удалить</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
                </div>

                <!-- Пагинация -->
                <?php if ($totalPages > 1) { ?>
                <div class="pagination">
                    <?php for ($p = 1; $p <= $totalPages; $p++) {
                        $url = suppliersUrl(array('pricelist_id'=>$pricelistId,'match_filter'=>$matchFilter,'stock_filter'=>$stockFilter,'rrp_filter'=>$rrpFilter,'search'=>$search,'page'=>$p), $basePath);
                        if ($p === $page) {
                            echo '<span class="cur">' . $p . '</span>';
                        } else {
                            echo '<a href="' . ViewHelper::h($url) . '">' . $p . '</a>';
                        }
                    } ?>
                </div>
                <?php } ?>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="card" style="color:#888;padding:30px;text-align:center;">
                Выберите прайс-лист слева
            </div>
        <?php } ?>
        </div>

    </div><!-- /layout -->
</div><!-- /wrap -->

<!-- ════ МОДАЛЬНОЕ ОКНО: ДОБАВИТЬ ПОСТАВЩИКА ════ -->
<div class="modal-overlay" id="addSupplierModal">
    <div class="modal">
        <h3>Новый поставщик</h3>
        <div style="margin-bottom:10px;">
            <label>Название</label>
            <input type="text" id="newSupName" placeholder="Название поставщика">
        </div>
        <div style="margin-bottom:10px;">
            <label>Тип источника данных</label>
            <select id="newSupType">
                <option value="moy_sklad">МойСклад</option>
                <option value="google_sheets" selected>Google Sheets</option>
                <option value="excel" disabled>Excel (скоро)</option>
                <option value="xml" disabled>XML (скоро)</option>
                <option value="parser" disabled>Parser (скоро)</option>
                <option value="api" disabled>API поставщика (скоро)</option>
            </select>
        </div>
        <div style="margin-bottom:14px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
                <input type="checkbox" id="newSupCostSource">
                <span>Источник себестоимости</span>
            </label>
            <div style="font-size:11px;color:#888;margin-top:4px;">Отмечайте только для прайса «Собственное производство» и аналогов</div>
        </div>
        <div id="addSupMsg" style="font-size:12px;display:none;margin-bottom:8px;"></div>
        <div class="modal-btns">
            <button class="btn" onclick="closeAddSupplierModal()">Отмена</button>
            <button class="btn btn-primary" onclick="submitAddSupplier()">Добавить</button>
        </div>
    </div>
</div>

<!-- ════ МОДАЛЬНОЕ ОКНО: ДОБАВИТЬ ПРАЙС ════ -->
<div class="modal-overlay" id="addPricelistModal">
    <div class="modal">
        <h3>Новый прайс-лист</h3>
        <div style="margin-bottom:10px;">
            <label>Поставщик</label>
            <select id="newPlSupplier">
                <?php foreach ($suppliers as $sup) { ?>
                    <option value="<?php echo (int)$sup['id']; ?>"><?php echo ViewHelper::h($sup['name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div style="margin-bottom:10px;">
            <label>Название прайса</label>
            <input type="text" id="newPlName" placeholder="напр. Основной прайс">
        </div>
        <div style="margin-bottom:10px;">
            <label>Источник данных</label>
            <select id="newPlType" onchange="updateSourceHint()">
                <option value="moy_sklad">МойСклад (БД)</option>
                <option value="google_sheets" selected>Google Sheets</option>
                <option value="excel" disabled>Excel / CSV (скоро)</option>
                <option value="xml" disabled>XML-файл (скоро)</option>
                <option value="parser" disabled>Web-парсер (скоро)</option>
                <option value="api" disabled>API поставщика (скоро)</option>
            </select>
        </div>
        <div id="sourceHint" style="font-size:11px;color:#888;margin-bottom:10px;padding:6px 8px;background:#f8fafc;border-radius:6px;"></div>
        <div id="addPlMsg" style="font-size:12px;display:none;margin-bottom:8px;"></div>
        <div class="modal-btns">
            <button class="btn" onclick="closeAddPricelistModal()">Отмена</button>
            <button class="btn btn-primary" onclick="submitAddPricelist()">Добавить</button>
        </div>
    </div>
</div>

<!-- ════ МОДАЛЬНОЕ ОКНО: ДОБАВИТЬ СТРОКУ ПРАЙСА ════ -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal">
        <h3>Новая строка прайса</h3>
        <div style="margin-bottom:10px;">
            <label>Артикул</label>
            <input type="text" id="newItemSku" placeholder="Артикул">
        </div>
        <div style="margin-bottom:10px;">
            <label>Название</label>
            <input type="text" id="newItemName" placeholder="Название товара">
        </div>
        <div style="margin-bottom:10px;">
            <label>Себестоимость</label>
            <input type="number" id="newItemCost" placeholder="0.00" step="0.01">
        </div>
        <div style="margin-bottom:10px;">
            <label>RRP</label>
            <input type="number" id="newItemRrp" placeholder="0.00" step="0.01">
        </div>
        <div style="margin-bottom:14px;">
            <label>Остатки</label>
            <input type="number" id="newItemStock" placeholder="0">
        </div>
        <div id="addItemMsg" style="font-size:12px;display:none;margin-bottom:8px;"></div>
        <div class="modal-btns">
            <button class="btn" onclick="closeAddItemModal()">Отмена</button>
            <button class="btn btn-primary" onclick="submitAddItem()">Добавить</button>
        </div>
    </div>
</div>

<!-- ════ МОДАЛЬНОЕ ОКНО МАТЧИНГА ════ -->
<div class="modal-overlay" id="matchModal">
    <div class="modal">
        <h3>Найти товар в каталоге</h3>
        <div id="modalItemInfo" style="font-size:12px;color:#666;margin-bottom:12px;"></div>
        <div class="modal-search">
            <input type="text" id="modalSearchInput" placeholder="артикул, название, id_off...">
        </div>
        <div class="modal-results" id="modalResults">
            <div style="padding:12px;color:#888;font-size:13px;">Начните вводить для поиска</div>
        </div>
        <div class="modal-btns">
            <button class="btn" onclick="closeMatchModal()">Отмена</button>
        </div>
    </div>
</div>

<script>
(function () {
    var _activePlIds   = <?php echo json_encode($activePlIds); ?>;
    var CURRENT_SEARCH   = <?php echo json_encode($search); ?>;
    var CURRENT_SHOW_ALL = <?php echo $showAll ? 'true' : 'false'; ?>;

    // ── Navigation helper ─────────────────────────────────────────────────────
    function suppliersBaseUrl(params) {
        var url = '/prices/suppliers';
        var parts = [];
        for (var k in params) {
            if (params[k] !== null && params[k] !== undefined && params[k] !== '') {
                parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
        }
        if (parts.length) url += '?' + parts.join('&');
        return url;
    }
    function homeUrl() {
        var p = {show_all: CURRENT_SHOW_ALL ? 1 : 0};
        if (CURRENT_SEARCH) p.search = CURRENT_SEARCH;
        return suppliersBaseUrl(p);
    }



    // ── Recalculate all purchase prices ─────────────────────────────────────
    // ── Update BK (recalculate all purchase prices + action prices) ─────────
    function updateBK(btn) {
        var msg = document.getElementById('recalc_msg');
        if (btn) btn.disabled = true;
        if (msg) { msg.style.color = '#555'; msg.textContent = 'Пересчёт...'; }
        // rest of function stays the same - uses runBatch internally
        function runBatch(offset, totalDone) {
            fetch('/prices/api/recalculate_all', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'offset=' + offset + '&limit=200'
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    if (btn) btn.disabled = false;
                    if (msg) { msg.style.color = '#b42318'; msg.textContent = d.error || 'Ошибка'; }
                    return;
                }
                var done = totalDone + (d.processed || 0);
                if (msg) { msg.style.color = '#555'; msg.textContent = 'БК: ' + done + '/' + (d.total || '?'); }
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runBatch(d.next_offset, done);
                } else {
                    if (btn) btn.disabled = false;
                    if (msg) { msg.style.color = '#157347'; msg.textContent = '✓ БК: ' + done + ' → виг...'; }
                    pushPrices(function() {
                        setTimeout(function(){ if(msg) msg.textContent=''; }, 3000);
                    });
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Ошибка сети'; }
            });
        }
        runBatch(0, 0);
    }
    window.updateBK = updateBK;

    // ── Sync ────────────────────────────────────────────────────────────────
    function syncPricelist(plId) {
        var btn = document.getElementById('sync_btn_' + plId);
        var msg = document.getElementById('sync_msg_' + plId);
        if (btn) btn.disabled = true;
        if (msg) { msg.style.display = 'inline'; msg.style.color = '#555'; msg.textContent = 'Синхр...'; }
        fetch('/prices/api/sync_supplier', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'pricelist_id=' + encodeURIComponent(plId)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (btn) btn.disabled = false;
            if (d.ok) {
                var txt = 'Загружено: ' + d.imported + ', сопост.: ' + d.matched;
                if (msg) { msg.style.color = '#157347'; msg.textContent = txt; }
                setTimeout(function () { window.location.reload(); }, 1500);
            } else {
                if (msg) { msg.style.color = '#b42318'; msg.textContent = d.error || 'Ошибка'; }
            }
        })
        .catch(function () {
            if (btn) btn.disabled = false;
            if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Ошибка сети'; }
        });
    }
    window.syncPricelist = syncPricelist;

    function syncAll(btn) {
        if (!_activePlIds || !_activePlIds.length) { return; }
        var msg = document.getElementById('syncAllMsg');
        if (btn) btn.disabled = true;
        var ids = _activePlIds.slice();
        var total = ids.length;
        var done = 0;
        if (msg) { msg.style.color = '#555'; msg.textContent = 'Синхр. ' + total + ' прайсов...'; }
        function next() {
            if (!ids.length) {
                if (btn) btn.disabled = false;
                if (msg) { msg.style.color = '#157347'; msg.textContent = '✓ Синхр. ' + done + '/' + total; }
                setTimeout(function(){ window.location.reload(); }, 1200);
                return;
            }
            var plId = ids.shift();
            var syncMsg = document.getElementById('sync_msg_' + plId);
            if (syncMsg) { syncMsg.style.display='inline'; syncMsg.textContent='...'; }
            fetch('/prices/api/sync_supplier', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'pricelist_id=' + encodeURIComponent(plId)
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                done++;
                if (msg) { msg.style.color='#555'; msg.textContent = 'Синхр. ' + done + '/' + total; }
                if (syncMsg) { syncMsg.style.color = d.ok ? '#157347':'#b42318'; syncMsg.textContent = d.ok ? '✓':'✗'; }
                next();
            })
            .catch(function(){ done++; next(); });
        }
        next();
    }
    window.syncAll = syncAll;

    // ── Toggle pricelist active ──────────────────────────────────────────────
    function togglePricelist(plId, currentActive) {
        fetch('/prices/api/toggle_pricelist', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'pricelist_id=' + encodeURIComponent(plId)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) window.location.reload();
            else alert('Ошибка: ' + (d.error || '?'));
        });
    }
    window.togglePricelist = togglePricelist;

    // ── Sheet config ────────────────────────────────────────────────────────
    function toggleSheetConfig(plId) {
        var el = document.getElementById('sheet_config_' + plId);
        if (el) el.style.display = el.style.display === 'block' ? 'none' : 'block';
    }
    window.toggleSheetConfig = toggleSheetConfig;

    function saveSheetConfig(plId) {
        function gv(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
        var params = [
            'pricelist_id='   + encodeURIComponent(plId),
            'spreadsheet_id=' + encodeURIComponent(gv('gs_sid_' + plId)),
            'sheet_name='     + encodeURIComponent(gv('gs_sn_'  + plId)),
            'header_row='     + encodeURIComponent(gv('gs_hr_'  + plId)),
            'col_sku='        + encodeURIComponent(gv('gs_col_sku_'        + plId)),
            'col_model='      + encodeURIComponent(gv('gs_col_model_'      + plId)),
            'col_name='       + encodeURIComponent(gv('gs_col_name_'       + plId)),
            'col_price_cost=' + encodeURIComponent(gv('gs_col_price_cost_' + plId)),
            'col_price_rrp='  + encodeURIComponent(gv('gs_col_price_rrp_'  + plId))
        ];
        var msg = document.getElementById('gs_msg_' + plId);
        if (msg) { msg.style.display='inline'; msg.style.color='#555'; msg.textContent='Сохраняем...'; }
        fetch('/prices/api/save_sheet_config', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: params.join('&')
        })
        .then(function(r){return r.json();})
        .then(function(d){
            if (msg) { msg.style.color=d.ok?'#157347':'#b42318'; msg.textContent=d.ok?'Сохранено':(d.error||'Ошибка'); }
        })
        .catch(function(){ if(msg){msg.style.color='#b42318';msg.textContent='Ошибка сети';} });
    }
    window.saveSheetConfig = saveSheetConfig;

    // ── Item actions ─────────────────────────────────────────────────────────
    function doAction(itemId, action, productId) {
        var params = 'item_id=' + encodeURIComponent(itemId) + '&action=' + encodeURIComponent(action);
        if (productId) params += '&product_id=' + encodeURIComponent(productId);
        fetch('/prices/api/match_item', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: params
        })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.ok) window.location.reload();
            else alert('Ошибка: ' + (d.error || '?'));
        });
    }
    window.doAction = doAction;

    // ── Match modal ───────────────────────────────────────────────────────────
    var _currentItemId = null;
    var _searchTimeout = null;

    function openMatchModal(itemId, hint) {
        _currentItemId = itemId;
        var modal = document.getElementById('matchModal');
        var info  = document.getElementById('modalItemInfo');
        var input = document.getElementById('modalSearchInput');
        var res   = document.getElementById('modalResults');
        if (info)  info.textContent = hint || '';
        if (input) { input.value = hint || ''; }
        if (res)   res.innerHTML = '<div style="padding:12px;color:#888;font-size:13px;">Поиск...</div>';
        modal.classList.add('open');
        if (input) {
            input.focus();
            doSearch(input.value);
        }
    }
    window.openMatchModal = openMatchModal;

    function closeMatchModal() {
        _currentItemId = null;
        document.getElementById('matchModal').classList.remove('open');
    }
    window.closeMatchModal = closeMatchModal;

    var modalInput = document.getElementById('modalSearchInput');
    if (modalInput) {
        modalInput.addEventListener('input', function () {
            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(function () { doSearch(modalInput.value); }, 300);
        });
    }

    function doSearch(q) {
        var res = document.getElementById('modalResults');
        if (!res) return;
        if (q.trim() === '') {
            res.innerHTML = '<div style="padding:12px;color:#888;font-size:13px;">Начните вводить для поиска</div>';
            return;
        }
        fetch('/prices/api/search_catalog?q=' + encodeURIComponent(q))
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.rows || d.rows.length === 0) {
                res.innerHTML = '<div style="padding:12px;color:#888;font-size:13px;">Не найдено</div>';
                return;
            }
            var html = '';
            d.rows.forEach(function(row) {
                html += '<div class="modal-result-row" onclick="pickProduct(' + row.product_id + ')">' +
                    '<div class="mr-name">' + escH(row.name || '—') + '</div>' +
                    '<div class="mr-article">' + escH(row.product_article || '') + ' &nbsp;·&nbsp; id_off: ' + (row.id_off || '—') + '</div>' +
                    '</div>';
            });
            res.innerHTML = html;
        })
        .catch(function(){ res.innerHTML = '<div style="padding:12px;color:#b42318;">Ошибка сети</div>'; });
    }

    function pickProduct(productId) {
        if (!_currentItemId) return;
        doAction(_currentItemId, 'match', productId);
        closeMatchModal();
    }
    window.pickProduct = pickProduct;

    // Close modal on overlay click
    document.getElementById('matchModal').addEventListener('click', function (e) {
        if (e.target === this) closeMatchModal();
    });

    function escH(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Add supplier modal ────────────────────────────────────────────────────
    function openAddSupplierModal() {
        document.getElementById('newSupName').value = '';
        document.getElementById('newSupType').value = 'google_sheets';
        document.getElementById('newSupCostSource').checked = false;
        var msg = document.getElementById('addSupMsg');
        if (msg) msg.style.display = 'none';
        document.getElementById('addSupplierModal').classList.add('open');
        setTimeout(function(){ document.getElementById('newSupName').focus(); }, 50);
    }
    window.openAddSupplierModal = openAddSupplierModal;

    function closeAddSupplierModal() {
        document.getElementById('addSupplierModal').classList.remove('open');
    }
    window.closeAddSupplierModal = closeAddSupplierModal;

    function submitAddSupplier() {
        var name = document.getElementById('newSupName').value.trim();
        var type = document.getElementById('newSupType').value;
        var cost = document.getElementById('newSupCostSource').checked ? 1 : 0;
        var msg  = document.getElementById('addSupMsg');
        if (!name) { msg.style.display='inline'; msg.style.color='#b42318'; msg.textContent='Введите название'; return; }
        msg.style.display='inline'; msg.style.color='#555'; msg.textContent='Сохраняем...';
        fetch('/prices/api/create_supplier', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'name='+encodeURIComponent(name)+'&source_type='+encodeURIComponent(type)+'&is_cost_source='+cost
        })
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){window.location.reload();}else{msg.style.color='#b42318';msg.textContent=d.error||'Ошибка';} })
        .catch(function(){msg.style.color='#b42318';msg.textContent='Ошибка сети';});
    }
    window.submitAddSupplier = submitAddSupplier;

    document.getElementById('addSupplierModal').addEventListener('click', function(e){ if(e.target===this) closeAddSupplierModal(); });

    // ── Add pricelist modal ───────────────────────────────────────────────────
    var sourceHints = {
        'moy_sklad':     'Синхронизация из базы данных МойСклад (ms.stock_)',
        'google_sheets': 'Синхронизация из Google Таблицы. После добавления настройте ID таблицы через ⚙',
        'excel':         '🚧 В разработке: загрузка из Excel/CSV файла',
        'xml':           '🚧 В разработке: загрузка из XML-файла поставщика',
        'parser':        '🚧 В разработке: парсинг сайта поставщика',
        'api':           '🚧 В разработке: подключение по API поставщика'
    };

    function updateSourceHint() {
        var type = document.getElementById('newPlType').value;
        var hint = document.getElementById('sourceHint');
        if (hint) hint.textContent = sourceHints[type] || '';
    }
    window.updateSourceHint = updateSourceHint;

    function openAddPricelistModal(preSupId) {
        document.getElementById('newPlName').value = '';
        document.getElementById('newPlType').value = 'google_sheets';
        var msg = document.getElementById('addPlMsg');
        if (msg) msg.style.display = 'none';
        if (preSupId && document.getElementById('newPlSupplier')) {
            document.getElementById('newPlSupplier').value = preSupId;
        }
        updateSourceHint();
        document.getElementById('addPricelistModal').classList.add('open');
        setTimeout(function(){ document.getElementById('newPlName').focus(); }, 50);
    }
    window.openAddPricelistModal = openAddPricelistModal;

    function closeAddPricelistModal() {
        document.getElementById('addPricelistModal').classList.remove('open');
    }
    window.closeAddPricelistModal = closeAddPricelistModal;

    function submitAddPricelist() {
        var supId = document.getElementById('newPlSupplier').value;
        var name  = document.getElementById('newPlName').value.trim();
        var type  = document.getElementById('newPlType').value;
        var msg   = document.getElementById('addPlMsg');
        if (!name) { msg.style.display='inline'; msg.style.color='#b42318'; msg.textContent='Введите название'; return; }
        msg.style.display='inline'; msg.style.color='#555'; msg.textContent='Сохраняем...';
        fetch('/prices/api/create_pricelist', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'supplier_id='+encodeURIComponent(supId)+'&name='+encodeURIComponent(name)+'&source_type='+encodeURIComponent(type)
        })
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){window.location.reload();}else{msg.style.color='#b42318';msg.textContent=d.error||'Ошибка';} })
        .catch(function(){msg.style.color='#b42318';msg.textContent='Ошибка сети';});
    }
    window.submitAddPricelist = submitAddPricelist;

    document.getElementById('addPricelistModal').addEventListener('click', function(e){ if(e.target===this) closeAddPricelistModal(); });

    // ── Delete pricelist ──────────────────────────────────────────────────────
    function deletePricelist(plId, plName) {
        if (!confirm('Удалить прайс-лист «' + plName + '»?\nВсе строки прайса ('+plId+') будут удалены.')) return;
        fetch('/prices/api/delete_pricelist', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'pricelist_id='+encodeURIComponent(plId)
        })
        .then(function(r){return r.json();})
        .then(function(d){ if(d.ok){window.location.href=homeUrl();}else{alert('Ошибка: '+(d.error||'?'));} })
        .catch(function(){alert('Ошибка сети');});
    }
    window.deletePricelist = deletePricelist;

    // ── Toggle manual edit ────────────────────────────────────────────────────
    function toggleManualEdit(plId, search, isActive, isShowAll) {
        // Если режим уже включён и мы в show_all — просто перейти в прайс без toggle
        if (isActive && isShowAll) {
            var url = '/prices/suppliers?pricelist_id=' + plId + '&show_all=0';
            if (search) url += '&search=' + encodeURIComponent(search);
            window.location.href = url;
            return;
        }
        fetch('/prices/api/toggle_manual_edit', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'pricelist_id=' + encodeURIComponent(plId)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) { var url = '/prices/suppliers?pricelist_id=' + plId + '&show_all=0'; if (search) url += '&search=' + encodeURIComponent(search); window.location.href = url; }
            else alert('Ошибка: ' + (d.error || '?'));
        })
        .catch(function() { alert('Ошибка сети'); });
    }
    window.toggleManualEdit = toggleManualEdit;

    // ── Save manual edits (stock inputs) ─────────────────────────────────────
    function saveManualEdits() {
        var btn = document.getElementById('saveManualEditsBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Сохраняем...'; }

        // Collect all edited rows by item_id
        var rowData = {};
        document.querySelectorAll('.edit-stock').forEach(function(inp) {
            var id = inp.getAttribute('data-id');
            if (!rowData[id]) rowData[id] = {};
            rowData[id].stock = inp.value;
        });
        document.querySelectorAll('.edit-price-cost').forEach(function(inp) {
            var id = inp.getAttribute('data-id');
            if (!rowData[id]) rowData[id] = {};
            rowData[id].price_cost = inp.value;
        });
        document.querySelectorAll('.edit-price-rrp').forEach(function(inp) {
            var id = inp.getAttribute('data-id');
            if (!rowData[id]) rowData[id] = {};
            rowData[id].price_rrp = inp.value;
        });
        document.querySelectorAll('.edit-name').forEach(function(inp) {
            var id = inp.getAttribute('data-id');
            if (!rowData[id]) rowData[id] = {};
            rowData[id].raw_name = inp.value;
        });

        var itemIds = Object.keys(rowData);
        if (!itemIds.length) {
            if (btn) { btn.disabled = false; }
            return;
        }

        var promises = itemIds.map(function(id) {
            var d = rowData[id];
            var body = 'item_id=' + encodeURIComponent(id);
            if (d.stock      !== undefined) body += '&stock='      + encodeURIComponent(d.stock);
            if (d.price_cost !== undefined) body += '&price_cost=' + encodeURIComponent(d.price_cost);
            if (d.price_rrp  !== undefined) body += '&price_rrp='  + encodeURIComponent(d.price_rrp);
            if (d.raw_name   !== undefined) body += '&raw_name='   + encodeURIComponent(d.raw_name);
            return fetch('/prices/api/save_pricelist_item', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r) { return r.json(); });
        });

        Promise.all(promises).then(function(results) {
            if (btn) { btn.disabled = false; btn.textContent = 'Сохранено ✓'; }
            // Remove dirty highlight from all saved inputs
            document.querySelectorAll('.edit-stock,.edit-price-cost,.edit-price-rrp,.edit-name').forEach(function(inp) {
                inp.classList.remove('input-dirty');
                inp.setAttribute('data-orig', inp.value);
            });
            // Count recalculated
            var recalc = results.filter(function(d) { return d.recalculated; }).length;
            if (recalc > 0 && btn) {
                btn.textContent = 'Сохранено, пересчитано: ' + recalc;
            }
            setTimeout(function() { if (btn) btn.textContent = 'Сохранить'; }, 3000);
        }).catch(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Ошибка'; }
        });
    }
    window.saveManualEdits = saveManualEdits;

    // ── Dirty tracking for edit inputs ───────────────────────────────────────
    document.querySelectorAll('.edit-stock,.edit-price-cost,.edit-price-rrp,.edit-name').forEach(function(inp) {
        inp.setAttribute('data-orig', inp.value);
        inp.addEventListener('input', function() {
            if (this.value !== this.getAttribute('data-orig')) {
                this.classList.add('input-dirty');
            } else {
                this.classList.remove('input-dirty');
            }
        });
    });

    // ── Rename pricelist ──────────────────────────────────────────────────────
    function startRename(plId) {
        var text  = document.getElementById('pl_name_text_'  + plId);
        var input = document.getElementById('pl_name_input_' + plId);
        if (!text || !input) return;
        text.style.display  = 'none';
        input.style.display = 'inline-block';
        input.focus();
        input.select();
    }
    window.startRename = startRename;

    function cancelRename(plId) {
        var text  = document.getElementById('pl_name_text_'  + plId);
        var input = document.getElementById('pl_name_input_' + plId);
        if (!text || !input) return;
        input.value         = text.textContent;
        text.style.display  = '';
        input.style.display = 'none';
    }
    window.cancelRename = cancelRename;

    function submitRename(plId) {
        var text  = document.getElementById('pl_name_text_'  + plId);
        var input = document.getElementById('pl_name_input_' + plId);
        if (!text || !input) return;
        var newName = input.value.trim();
        if (!newName || newName === text.textContent.trim()) {
            cancelRename(plId);
            return;
        }
        fetch('/prices/api/rename_pricelist', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'pricelist_id=' + encodeURIComponent(plId) + '&name=' + encodeURIComponent(newName)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                text.textContent = newName;
            } else {
                input.value = text.textContent;
            }
            text.style.display  = '';
            input.style.display = 'none';
        })
        .catch(function() { cancelRename(plId); });
    }
    window.submitRename = submitRename;

    // ── Delete pricelist item ─────────────────────────────────────────────────
    function deletePricelistItem(itemId) {
        if (!confirm('Удалить строку ' + itemId + '?')) return;
        fetch('/prices/api/delete_pricelist_item', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'item_id=' + encodeURIComponent(itemId)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                var row = document.getElementById('item_row_' + itemId);
                if (row) row.parentNode.removeChild(row);
            } else {
                alert('Ошибка: ' + (d.error || '?'));
            }
        })
        .catch(function() { alert('Ошибка сети'); });
    }
    window.deletePricelistItem = deletePricelistItem;

    // ── Add pricelist item modal ──────────────────────────────────────────────
    var _addItemPlId = 0;

    function openAddItemModal(plId) {
        _addItemPlId = plId;
        var modal = document.getElementById('addItemModal');
        if (!modal) return;
        document.getElementById('newItemSku').value       = '';
        document.getElementById('newItemName').value      = '';
        document.getElementById('newItemCost').value      = '';
        document.getElementById('newItemRrp').value       = '';
        document.getElementById('newItemStock').value     = '';
        var msg = document.getElementById('addItemMsg');
        if (msg) msg.style.display = 'none';
        modal.classList.add('open');
        setTimeout(function() { document.getElementById('newItemSku').focus(); }, 50);
    }
    window.openAddItemModal = openAddItemModal;

    function closeAddItemModal() {
        var modal = document.getElementById('addItemModal');
        if (modal) modal.classList.remove('open');
    }
    window.closeAddItemModal = closeAddItemModal;

    function submitAddItem() {
        var sku   = document.getElementById('newItemSku').value.trim();
        var name  = document.getElementById('newItemName').value.trim();
        var cost  = document.getElementById('newItemCost').value.trim();
        var rrp   = document.getElementById('newItemRrp').value.trim();
        var stock = document.getElementById('newItemStock').value.trim();
        var msg   = document.getElementById('addItemMsg');
        msg.style.display = 'inline'; msg.style.color = '#555'; msg.textContent = 'Сохраняем...';
        fetch('/prices/api/add_pricelist_item', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'pricelist_id=' + encodeURIComponent(_addItemPlId)
                + '&raw_sku='    + encodeURIComponent(sku)
                + '&raw_name='   + encodeURIComponent(name)
                + '&price_cost=' + encodeURIComponent(cost)
                + '&price_rrp='  + encodeURIComponent(rrp)
                + '&stock='      + encodeURIComponent(stock)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) { window.location.reload(); }
            else { msg.style.color = '#b42318'; msg.textContent = d.error || 'Ошибка'; }
        })
        .catch(function() { msg.style.color = '#b42318'; msg.textContent = 'Ошибка сети'; });
    }
    window.submitAddItem = submitAddItem;

    var addItemModalEl = document.getElementById('addItemModal');
    if (addItemModalEl) {
        addItemModalEl.addEventListener('click', function(e) { if (e.target === this) closeAddItemModal(); });
    }

    // ── Delete supplier ───────────────────────────────────────────────────────
    function deleteSupplier(supId, supName) {
        fetch('/prices/api/delete_supplier', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'supplier_id='+encodeURIComponent(supId)+'&cascade=0'
        })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.ok) { window.location.href=homeUrl(); return; }
            if (d.error === 'has_pricelists') {
                var msg = 'Поставщик «'+supName+'» имеет '+d.count+' прайс(ов).\nУдалить вместе со всеми прайсами и строками?';
                if (!confirm(msg)) return;
                fetch('/prices/api/delete_supplier', {
                    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'supplier_id='+encodeURIComponent(supId)+'&cascade=1'
                })
                .then(function(r){return r.json();})
                .then(function(d2){ if(d2.ok){window.location.href=homeUrl();}else{alert('Ошибка: '+(d2.error||'?'));} });
            } else {
                alert('Ошибка: '+(d.error||'?'));
            }
        })
        .catch(function(){alert('Ошибка сети');});
    }
    window.deleteSupplier = deleteSupplier;

    // ── Push prices: Phase 1 — sites (off+mff), Phase 2 — MoySklad ──────────
    function pushPrices(onDone) {
        var btn      = document.getElementById('pushPricesBtn');
        var progress = document.getElementById('pushPricesProgress');
        if (btn) btn.disabled = true;
        if (progress) { progress.style.display = 'block'; progress.style.fontSize = '12px'; }

        var total     = 0;
        var statSites = {pushed: 0, skipped: 0};
        var statMs    = {pushed: 0, skipped: 0};

        function show(color, text) {
            if (progress) { progress.style.color = color; progress.textContent = text; }
        }
        function abort(msg) {
            if (btn) btn.disabled = false;
            show('#b42318', '✗ ' + msg);
        }

        // Phase 1: сайты (off + mff) — 100 товаров/батч, быстро
        function runSites(offset) {
            show('#555', 'Сайты: ' + offset + '/' + (total || '…'));
            fetch('/prices/api/push_prices', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'offset=' + offset + '&limit=100&phase=sites'
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { abort(d.error || 'Ошибка сайтов'); return; }
                if (d.total) total = d.total;
                statSites.pushed  += d.stats.pushed  || 0;
                statSites.skipped += d.stats.skipped || 0;
                if (d.has_errors) { abort('Сайты: ' + (d.errors[0] || 'ошибка')); return; }
                var done = d.next_offset !== null && d.next_offset !== undefined ? d.next_offset : total;
                show('#555', 'Сайты: ' + done + '/' + total + ' — ' + statSites.pushed + ' ok');
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runSites(d.next_offset);
                } else {
                    show('#1a7f4b', '✓ Сайты готово (' + statSites.pushed + ') → МС…');
                    runMs(0);
                }
            })
            .catch(function(err) { abort('Сеть (сайты): ' + err); });
        }

        // Phase 2: МойСклад — 50 товаров/батч, медленнее (rate limit)
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
                statMs.pushed  += d.stats.pushed  || 0;
                statMs.skipped += d.stats.skipped || 0;
                if (d.has_errors) { abort('МС: ' + (d.errors[0] || 'ошибка')); return; }
                var done = d.next_offset !== null && d.next_offset !== undefined ? d.next_offset : total;
                var pct  = total > 0 ? Math.round(done / total * 100) : 0;
                show('#555', 'МС: ' + done + '/' + total + ' (' + pct + '%) — ' + statMs.pushed + ' ok');
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runMs(d.next_offset);
                } else {
                    if (btn) btn.disabled = false;
                    show('#157347', '✓ Готово — сайты: ' + statSites.pushed + ', МС: ' + statMs.pushed + ' (пропущено: ' + statMs.skipped + ')');
                    setTimeout(function() {
                        if (progress) { progress.style.display = 'none'; progress.textContent = ''; }
                    }, 10000);
                    if (typeof onDone === 'function') onDone();
                }
            })
            .catch(function(err) { abort('Сеть (МС): ' + err); });
        }

        runSites(0);
    }
    window.pushPrices = pushPrices;

    // ── Action dropdown ────────────────────────────────────────────────
    var _openActDD = null;
    function toggleActDD(itemId) {
        var dd = document.getElementById('act_dd_' + itemId);
        if (!dd) return;
        if (_openActDD && _openActDD !== dd) { _openActDD.classList.remove('open'); }
        dd.classList.toggle('open');
        _openActDD = dd.classList.contains('open') ? dd : null;
    }
    function closeActDD() {
        if (_openActDD) { _openActDD.classList.remove('open'); _openActDD = null; }
    }
    window.toggleActDD = toggleActDD;
    window.closeActDD = closeActDD;
    document.addEventListener('click', function(e) {
        if (_openActDD && !e.target.closest('.act-wrap')) { closeActDD(); }
    });

})();
</script>
<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
<?php if ($showAll) { ?>
ChipSearch.init('showAllChipBox', 'showAllChipTyper', 'showAllSearchHidden');
<?php } elseif ($pricelist) { ?>
ChipSearch.init('plChipBox', 'plChipTyper', 'plSearchHidden');
<?php } ?>
</script>
</body>
</html>
