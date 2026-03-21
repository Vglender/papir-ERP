<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Поставщики и прайс-листы</title>
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
.btn { display:inline-block; padding:8px 14px; border-radius:8px; border:1px solid #c8d1dd; background:#fff; color:#222; cursor:pointer; font-size:13px; box-sizing:border-box; text-align:center; text-decoration:none; }
.btn-primary { background:#1f6feb; border-color:#1f6feb; color:#fff; }
.btn-danger  { background:#fff1f1; border-color:#f5c6c6; color:#b42318; }
.btn-small { padding:5px 9px; font-size:12px; border-radius:6px; }
.btn-xs    { padding:3px 7px; font-size:11px; border-radius:5px; }
.btn-row { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
label { display:block; margin:0 0 4px; font-size:12px; font-weight:bold; color:#555; }
input[type="text"], input[type="number"], select { width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #c8d1dd; border-radius:7px; font-size:13px; background:#fff; }
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
table { width:100%; border-collapse:collapse; }
th, td { padding:9px 10px; border-bottom:1px solid #e8edf3; text-align:left; font-size:13px; vertical-align:middle; }
th { background:#f8fafc; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#555; white-space:nowrap; }
.match-badge { display:inline-block; padding:2px 7px; border-radius:999px; font-size:11px; white-space:nowrap; }
.mb-auto-sku   { background:#edfdf3; color:#157347; }
.mb-auto-model { background:#eef4ff; color:#1f4db8; }
.mb-manual     { background:#fff4e5; color:#b26a00; }
.mb-ignored    { background:#f5f5f5; color:#999; }
.mb-none       { background:#fff1f1; color:#b42318; }
.filters { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:14px; }
.filters > div { flex:0 0 auto; }
.filters input[type="text"] { width:240px; }
.filters select { width:160px; }
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
</style>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div>
            <h1 class="title">Поставщики и прайс-листы</h1>
            <div class="breadcrumb"><a href="/prices">← Цены</a></div>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" onclick="recalculateAll(this)">
                Пересчитать закупочные цены
            </button>
            <span id="recalc_msg" style="font-size:12px;display:none;"></span>
        </div>
    </div>

    <div class="layout">

        <!-- ════ ЛЕВАЯ ПАНЕЛЬ — СПИСОК ПОСТАВЩИКОВ ════ -->
        <div class="sticky">
            <div class="card" style="padding:14px 16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <div style="font-size:13px;font-weight:bold;">Поставщики</div>
                    <div class="btn-row">
                        <button class="btn btn-small btn-primary" onclick="openAddPricelistModal(0)">+ Прайс</button>
                        <button class="btn btn-small" onclick="openAddSupplierModal()">+ Поставщик</button>
                    </div>
                </div>

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
                        $plUrl    = ViewHelper::h(suppliersUrl(array('pricelist_id' => $plId), $basePath));
                        $plActive = !empty($pl['is_active']);
                        $plStyle  = $plActive ? '' : 'opacity:.45;';
                    ?>
                    <div class="pricelist-row <?php echo $isActive ? 'active-pl' : ''; ?>"
                         style="cursor:pointer;<?php echo $plStyle; ?>"
                         id="plrow_<?php echo $plId; ?>"
                         onclick="if(!event.target.closest('button'))window.location='<?php echo $plUrl; ?>'">
                        <div>
                            <div class="pl-name" style="color:<?php echo $plActive ? '#1f6feb' : '#aaa'; ?>;font-weight:bold;">
                                <?php echo ViewHelper::h($pl['name']); ?>
                                <?php if (!$plActive) { ?><span style="font-size:11px;font-weight:normal;color:#aaa;"> (неактивен)</span><?php } ?>
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
        <?php if ($pricelist) { ?>
            <div class="card">
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
                </div>

                <!-- Фильтры -->
                <form method="get" action="/prices/suppliers" class="filters">
                    <input type="hidden" name="pricelist_id" value="<?php echo $pricelistId; ?>">
                    <input type="hidden" name="page" value="1">
                    <div>
                        <label>Поиск</label>
                        <input type="text" name="search" value="<?php echo ViewHelper::h($search); ?>" placeholder="артикул, название...">
                    </div>
                    <div>
                        <label>Статус</label>
                        <select name="match_filter">
                            <option value="all"       <?php echo $matchFilter==='all'       ?'selected':''; ?>>Все</option>
                            <option value="matched"   <?php echo $matchFilter==='matched'   ?'selected':''; ?>>Сопоставлены</option>
                            <option value="unmatched" <?php echo $matchFilter==='unmatched' ?'selected':''; ?>>Не найдены</option>
                            <option value="ignored"   <?php echo $matchFilter==='ignored'   ?'selected':''; ?>>Игнорируются</option>
                        </select>
                    </div>
                    <div><label>&nbsp;</label><button type="submit" class="btn">Применить</button></div>
                </form>

                <!-- Таблица строк -->
                <?php if (empty($items)) { ?>
                    <div style="color:#777;padding:20px 0;">Нет строк.</div>
                <?php } else { ?>
                <div style="overflow-x:auto;">
                <table>
                    <?php $isCostSource = !empty($pricelist['is_cost_source']); ?>
                    <thead>
                    <tr>
                        <th>Артикул (прайс)</th>
                        <th>model</th>
                        <th>Название (прайс)</th>
                        <th><?php echo $isCostSource ? 'Себестоимость' : 'Цена поставщика'; ?></th>
                        <th>RRP</th>
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
                        <td style="font-family:monospace;font-size:12px;"><?php echo ViewHelper::h($item['raw_sku'] ?: '—'); ?></td>
                        <td style="font-family:monospace;font-size:12px;"><?php echo ViewHelper::h($item['raw_model'] ?: '—'); ?></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo ViewHelper::h($item['raw_name']); ?>">
                            <?php echo ViewHelper::h($item['raw_name'] ?: '—'); ?></td>
                        <td class="num"><?php echo $item['price_cost'] !== null ? number_format((float)$item['price_cost'], 2, '.', ' ') : '—'; ?></td>
                        <td class="num"><?php echo $item['price_rrp']  !== null ? number_format((float)$item['price_rrp'],  2, '.', ' ') : '—'; ?></td>
                        <td>
                            <?php if ($isIgnored) { ?>
                                <span class="match-badge mb-ignored">Игнор.</span>
                            <?php } elseif ($matchType === 'auto_model') { ?>
                                <span class="match-badge mb-auto-model">авто id_off</span>
                            <?php } elseif ($matchType === 'auto_sku') { ?>
                                <span class="match-badge mb-auto-sku">авто артикул</span>
                            <?php } elseif ($matchType === 'manual') { ?>
                                <span class="match-badge mb-manual">вручную</span>
                            <?php } else { ?>
                                <span class="match-badge mb-none">не найден</span>
                            <?php } ?>
                        </td>
                        <td style="font-size:12px;" id="item_catalog_<?php echo $itemId; ?>">
                            <?php if ($isMatched) { ?>
                                <span style="color:#1f6feb;font-family:monospace;"><?php echo ViewHelper::h($item['product_article'] ?: '#' . $productId); ?></span>
                                <span style="color:#555;margin-left:4px;"><?php echo ViewHelper::h(mb_strimwidth($item['catalog_name'], 0, 30, '…')); ?></span>
                            <?php } else { ?>—<?php } ?>
                        </td>
                        <td>
                            <div class="btn-row">
                                <?php if (!$isIgnored) { ?>
                                    <button class="btn btn-small btn-primary"
                                            onclick="openMatchModal(<?php echo $itemId; ?>, <?php echo ViewHelper::h(json_encode($item['raw_sku'] . ' ' . $item['raw_name'])); ?>)">
                                        Найти
                                    </button>
                                    <?php if ($isMatched) { ?>
                                        <button class="btn btn-small btn-danger" onclick="doAction(<?php echo $itemId; ?>,'unmatch')">✕</button>
                                    <?php } ?>
                                    <button class="btn btn-small" onclick="doAction(<?php echo $itemId; ?>,'ignore')">Игн.</button>
                                <?php } else { ?>
                                    <button class="btn btn-small" onclick="doAction(<?php echo $itemId; ?>,'unignore')">↩ Восст.</button>
                                <?php } ?>
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
                        $url = suppliersUrl(array('pricelist_id'=>$pricelistId,'match_filter'=>$matchFilter,'search'=>$search,'page'=>$p), $basePath);
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

    // ── Recalculate all purchase prices ─────────────────────────────────────
    function recalculateAll(btn) {
        var msg = document.getElementById('recalc_msg');
        if (btn) btn.disabled = true;
        if (msg) { msg.style.display = 'inline'; msg.style.color = '#555'; msg.textContent = 'Пересчёт...'; }

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
                if (msg) { msg.style.color = '#555'; msg.textContent = 'Пересчитано: ' + done + ' из ' + (d.total || '?'); }
                if (d.next_offset !== null && d.next_offset !== undefined) {
                    runBatch(d.next_offset, done);
                } else {
                    if (btn) btn.disabled = false;
                    if (msg) { msg.style.color = '#157347'; msg.textContent = 'Готово: ' + done + ' товаров пересчитано'; }
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                if (msg) { msg.style.color = '#b42318'; msg.textContent = 'Ошибка сети'; }
            });
        }

        runBatch(0, 0);
    }
    window.recalculateAll = recalculateAll;

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
        .then(function(d){ if(d.ok){window.location.href='/prices/suppliers';}else{alert('Ошибка: '+(d.error||'?'));} })
        .catch(function(){alert('Ошибка сети');});
    }
    window.deletePricelist = deletePricelist;

    // ── Delete supplier ───────────────────────────────────────────────────────
    function deleteSupplier(supId, supName) {
        fetch('/prices/api/delete_supplier', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'supplier_id='+encodeURIComponent(supId)+'&cascade=0'
        })
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.ok) { window.location.href='/prices/suppliers'; return; }
            if (d.error === 'has_pricelists') {
                var msg = 'Поставщик «'+supName+'» имеет '+d.count+' прайс(ов).\nУдалить вместе со всеми прайсами и строками?';
                if (!confirm(msg)) return;
                fetch('/prices/api/delete_supplier', {
                    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'supplier_id='+encodeURIComponent(supId)+'&cascade=1'
                })
                .then(function(r){return r.json();})
                .then(function(d2){ if(d2.ok){window.location.href='/prices/suppliers';}else{alert('Ошибка: '+(d2.error||'?'));} });
            } else {
                alert('Ошибка: '+(d.error||'?'));
            }
        })
        .catch(function(){alert('Ошибка сети');});
    }
    window.deleteSupplier = deleteSupplier;

})();
</script>
</body>
</html>
