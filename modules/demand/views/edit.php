<?php
// /var/www/papir/modules/demand/views/edit.php
// Variables from edit.php: $demand, $items, $id, $managerName, $history,
//   $docTransitions, $organizations, $stores, $employees, $deliveryMethods,
//   $counterpartyName, $relatedDocsCount, $marginData, $linkedOrder

$isNew = empty($demand);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fv($arr, $key, $default = '') { return isset($arr[$key]) ? $arr[$key] : $default; }
function sel($value, $current) {
    if (is_numeric($value) && is_numeric($current)) return (float)$value == (float)$current ? 'selected' : '';
    return (string)$value === (string)$current ? 'selected' : '';
}

$_statusInlineStyles = array(
    'new'        => 'background:#f3f4f6; color:#6b7280;',
    'assembling' => 'background:#fff4e5; color:#b26a00;',
    'assembled'  => 'background:#dbeafe; color:#1e40af;',
    'shipped'    => 'background:#dcfce7; color:#15803d;',
    'arrived'    => 'background:#d1fae5; color:#065f46;',
    'transfer'   => 'background:#e0f2fe; color:#0369a1;',
    'robot'      => 'background:#fae8ff; color:#7e22ce;',
);
$statusLabels = array(
    'new'        => 'Нове',
    'assembling' => 'Збирання',
    'assembled'  => 'Зібрано',
    'shipped'    => 'Відвантажено',
    'arrived'    => 'Прибуло',
    'transfer'   => 'Транзит',
    'robot'      => 'Робот',
);

$currentStatus = fv($demand, 'status', 'new');
$currentStyle  = isset($_statusInlineStyles[$currentStatus]) ? $_statusInlineStyles[$currentStatus] : $_statusInlineStyles['new'];

$syncStateStyles = array(
    'synced'  => 'background:#d1fae5; color:#065f46;',
    'new'     => 'background:#f3f4f6; color:#6b7280;',
    'changed' => 'background:#fff4e5; color:#b26a00;',
    'error'   => 'background:#fee2e2; color:#b91c1c;',
);
$syncStateLabels = array(
    'synced'  => 'синхронізовано',
    'new'     => 'нове',
    'changed' => 'змінено',
    'error'   => 'помилка',
);

$syncState = fv($demand, 'sync_state', 'new');
$syncStyle = isset($syncStateStyles[$syncState]) ? $syncStateStyles[$syncState] : 'background:#f3f4f6; color:#6b7280;';
$syncLabel = isset($syncStateLabels[$syncState]) ? $syncStateLabels[$syncState] : $syncState;

$updatedAt = fv($demand, 'updated_at', '');

// StatusColors for RelDocsGraph
$_scAll = array();
foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
    foreach (StatusColors::all($_dt) as $_s => $_e) {
        $_scAll[$_s] = $_e;
    }
}

$title     = 'Відвантаження' . ($isNew ? '' : ' ' . (fv($demand, 'number') ?: '#' . $id));
$activeNav = 'sales';
$subNav    = 'demands';
$extraCss  = '<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/demand/css/demand-edit.css?v=' . filemtime(__DIR__ . '/../css/demand-edit.css') . '">';
require_once __DIR__ . '/../../shared/layout.php';
?>

<div class="page-shell">

<?php if ($isNew): ?>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="/demand" class="btn">← Закрити</a>
        </div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:48px;text-align:center;color:var(--text-muted);">
        Відвантаження створюються з замовлення покупця або синхронізуються з МойСклад.
    </div>

<?php else: ?>

    <?php $currentCpId = fv($demand, 'counterparty_id', ''); ?>
    <!-- ══ TOOLBAR ══ -->
    <div class="toolbar">
        <div class="toolbar-left">
            <button type="button" id="btnSave" class="btn btn-save">Зберегти</button>
            <a href="/demand" class="btn">Закрити</a>
            <?php if (!empty($docTransitions)): ?>
            <div class="create-doc-wrap" id="createDocWrap">
                <button type="button" class="btn" id="createDocBtn">Створити ▾</button>
                <div class="create-doc-menu" id="createDocMenu">
                    <?php foreach ($docTransitions as $tr): ?>
                    <button type="button" class="create-doc-item"
                            data-to-type="<?= h($tr['to_type']) ?>"
                            data-link-type="<?= h($tr['link_type']) ?>">
                        <?= h($tr['name_uk']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <button type="button" class="btn" disabled>Створити ▾</button>
            <?php endif; ?>
            <button type="button" class="btn" <?= $id ? '' : 'disabled' ?>
                    onclick="PrintModal.open('demand', <?= $id ?>, 0)">
                Друк ▾
            </button>
            <button type="button" class="btn" <?= $id ? '' : 'disabled' ?>
                    onclick="PackPrint.open(<?= $id ?>)">
                📦 Пакет ▾
            </button>
            <?php if (!empty($currentCpId)): ?>
            <button type="button" class="btn" id="btnSendTpl" title="Надіслати клієнту">📤 Надіслати ▾</button>
            <button type="button" class="btn" id="btnOpenChat"
                    onclick="ChatModal.open(<?= (int)$currentCpId ?>)"
                    title="Відкрити чат з контрагентом">💬 Чат</button>
            <?php endif; ?>
            <?php if (!empty($demand['id_ms'])): ?>
            <a href="https://online.moysklad.ru/app/#demand/edit?id=<?= h($demand['id_ms']) ?>"
               target="_blank" class="btn">Відкрити в МС ↗</a>
            <?php endif; ?>
            <label class="check-label">
                <input type="checkbox" id="applicable" value="1"
                    <?= !empty($demand['applicable']) ? 'checked' : '' ?>>
                Проведено
            </label>
        </div>
        <div class="toolbar-right">
            <div class="toolbar-meta">
                <div class="toolbar-meta-item">
                    <strong>Менеджер:</strong> <?= h($managerName ?: '—') ?>
                </div>
                <div class="toolbar-meta-item">
                    <strong><a href="#" id="historyToggle">Синхронізація:</a></strong>
                    <span id="syncTagInline" class="sync-tag" style="<?= h($syncStyle) ?>">
                        <?= h($syncLabel) ?>
                    </span>
                </div>
                <div class="toolbar-meta-item">
                    <strong>Оновлено:</strong> <?= h($updatedAt ?: '—') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ DOC HEADER ══ -->
    <div class="doc-header">

        <!-- Title row -->
        <div class="doc-title-row">
            <div class="doc-number">
                Відвантаження
                <?php if (!empty($demand['number'])): ?>
                    № <?= h($demand['number']) ?>
                <?php else: ?>
                    #<?= $id ?>
                <?php endif; ?>
                <span>від <?= h(!empty($demand['moment']) ? date('d.m.Y H:i', strtotime($demand['moment'])) : '—') ?></span>
            </div>
            <?php if (!empty($linkedOrder)): ?>
            <div class="doc-title-links">
                <a href="/customerorder/edit?id=<?= (int)$linkedOrder['id'] ?>" class="doc-title-link">
                    Замовлення № <?= h($linkedOrder['number'] ?: ('#' . $linkedOrder['id'])) ?> ↗
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Status row -->
        <div class="doc-meta-row">
            <input type="hidden" id="statusHidden" value="<?= h($currentStatus) ?>">
            <div class="status-dd" id="statusDd">
                <button type="button" class="status-dd-btn" id="statusDdBtn" style="<?= $currentStyle ?>">
                    <span id="statusDdLabel"><?= h(isset($statusLabels[$currentStatus]) ? $statusLabels[$currentStatus] : $currentStatus) ?></span>
                    <span class="dd-caret">▾</span>
                </button>
                <ul class="status-dd-menu" id="statusDdMenu">
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <?php $_sStyle = isset($_statusInlineStyles[$sv]) ? $_statusInlineStyles[$sv] : ''; ?>
                    <li class="status-dd-opt" data-value="<?= h($sv) ?>" data-style="<?= h($_sStyle) ?>">
                        <span class="opt-pill" style="background:<?= h(StatusColors::hex('demand', $sv) ?: '#9ca3af') ?>"></span>
                        <?= h($sl) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Fields area -->
        <?php
        // Effective values: fallback to customerorder if demand's own is NULL
        $effOrgId       = fv($demand, 'effective_organization_id', fv($demand, 'organization_id'));
        $effManagerId   = fv($demand, 'effective_manager_employee_id', fv($demand, 'manager_employee_id'));
        $effStoreId     = fv($demand, 'effective_store_id', fv($demand, 'store_id'));
        if (empty($effStoreId)) $effStoreId = 1; // Основний склад by default
        $effDeliveryId  = fv($demand, 'effective_delivery_method_id', fv($demand, 'delivery_method_id'));
        $currentCpId    = fv($demand, 'counterparty_id', '');
        ?>
        <div class="fields-area">

            <!-- LEFT col: org, counterparty picker -->
            <div class="fields-col">
                <div class="fields-grid-2">
                    <div class="f">
                        <label>Організація</label>
                        <select id="organization_id">
                            <option value="">— Обрати —</option>
                            <?php foreach ($organizations as $org): ?>
                                <option value="<?= (int)$org['id'] ?>" <?= sel($org['id'], $effOrgId) ?>>
                                    <?= h($org['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="f">
                        <label>Контрагент</label>
                        <div class="cp-picker-wrap" id="cpPickerWrap">
                            <input type="hidden" id="counterparty_id" value="<?= h($currentCpId) ?>">
                            <input type="text" id="cpPickerInput" class="cp-picker-input"
                                   value="<?= h($counterpartyName) ?>"
                                   placeholder="Пошук за ім'ям, телефоном, ЄДРПОУ…"
                                   autocomplete="off">
                            <button type="button" class="cp-picker-clear" id="cpPickerClear" title="Скинути"<?= $currentCpId ? '' : ' style="display:none"' ?>>×</button>
                            <a href="/counterparties/view?id=<?= h($currentCpId) ?>" target="_blank" id="cpCardLink" class="cp-card-link" title="Картка контрагента"<?= $currentCpId ? '' : ' style="display:none"' ?>>↗</a>
                            <button type="button" class="cp-card-link" id="cpAddBtn" title="Додати нового контрагента" style="font-size:16px;font-weight:400;">+</button>
                            <div class="cp-picker-dd" id="cpPickerDd" style="display:none"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT col: store, manager, delivery -->
            <div class="fields-col">
                <div class="fields-grid">
                    <div class="f">
                        <label>Склад</label>
                        <select id="store_id">
                            <option value="">— Обрати —</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?= (int)$store['id'] ?>" <?= sel($store['id'], $effStoreId) ?>><?= h($store['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="f">
                        <label>Менеджер</label>
                        <select id="manager_employee_id">
                            <option value="">— Обрати —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>" <?= sel($emp['id'], $effManagerId) ?>><?= h($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="f">
                        <label>Спосіб доставки</label>
                        <select id="delivery_method_id">
                            <option value="">— Без доставки —</option>
                            <?php foreach ($deliveryMethods as $dm): ?>
                                <option value="<?= (int)$dm['id'] ?>" <?= sel($dm['id'], $effDeliveryId) ?>><?= h($dm['name_uk']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

        </div><!-- /fields-area -->
    </div><!-- /doc-header -->

    <!-- ══ POSITIONS + TABS ══ -->
    <div class="positions-panel">

        <div class="tabs-bar">
            <button class="tab-btn active" data-tab="positions">Позиції</button>
            <button class="tab-btn" data-tab="related">Пов'язані документи <?php if ($relatedDocsCount > 0): ?><span class="tab-badge" id="relatedDocsBadge"><?= $relatedDocsCount ?></span><?php endif; ?></button>
            <button class="tab-btn" data-tab="files">Файли</button>
            <button class="tab-btn" data-tab="tasks">Задачі</button>
            <button class="tab-btn" data-tab="events">Події</button>
        </div>

        <!-- TAB: Позиції -->
        <div class="tab-content active" id="tab-positions">
            <div class="bulk-bar">
                <span style="font-size:11.5px; color:var(--text-muted);">Вибрані:</span>
                <button type="button" class="btn" id="bulkDeleteBtn" disabled>Видалити</button>
            </div>
            <table class="pos-table" id="positionsTable">
                <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="checkAll"></th>
                    <th>Найменування</th>
                    <th style="width:48px;" class="text-c">Од.</th>
                    <th style="width:80px;" class="text-r">К-сть</th>
                    <th style="width:90px;" class="text-r">Ціна</th>
                    <th style="width:90px;" class="text-c">ПДВ</th>
                    <th style="width:70px;" class="text-r">Знижка</th>
                    <th style="width:100px;" class="text-r">Сума</th>
                    <th style="width:36px;"></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="9" class="empty-box">Позицій поки немає.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-item-row="1" data-local-id="<?= (int)$item['id'] ?>" data-sum-changed="0">
                        <td class="text-c">
                            <input type="checkbox" class="row-check" value="<?= (int)$item['id'] ?>">
                        </td>

                        <td>
                            <?php $art = fv($item, 'article') ?: fv($item, 'sku'); $pid = (int)fv($item, 'product_id'); ?>
                            <?php if ($art): ?><a href="/catalog?selected=<?= $pid ?>" target="_blank" style="font-size:11px;color:#9ca3af;margin-right:4px"><?= h($art) ?></a><?php endif; ?>
                            <a href="/catalog?selected=<?= $pid ?>" class="prod-name-link" target="_blank"><?= h(fv($item, 'name') ?: fv($item, 'product_name', '—')) ?></a>
                            <input type="hidden" data-field="item_id"    value="<?= (int)$item['id'] ?>">
                            <input type="hidden" data-field="product_id" value="<?= h(fv($item, 'product_id')) ?>">
                            <input type="hidden" data-field="weight"     value="<?= h(fv($item, 'weight', 0)) ?>">
                        </td>

                        <td class="text-c">
                            <input type="text" data-field="unit" value="<?= h(fv($item, 'unit', 'шт')) ?>" style="width:42px; text-align:center;" readonly>
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="quantity" value="<?= h(fv($item, 'quantity', 1)) ?>" style="width:72px; text-align:right;">
                        </td>

                        <td class="text-r price-cell">
                            <input type="text" data-field="price" value="<?= h(fv($item, 'price', 0)) ?>" style="width:82px; text-align:right;">
                            <div class="price-dd"></div>
                        </td>

                        <td class="text-c">
                            <select data-field="vat_rate" style="width:82px; text-align:center;">
                                <option value="0" <?= sel('0', fv($item, 'vat_rate', 0)) ?>>Без ПДВ</option>
                                <option value="20" <?= sel('20', fv($item, 'vat_rate', 0)) ?>>20%</option>
                            </select>
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="discount_percent" value="<?= h(fv($item, 'discount_percent', 0)) ?>" style="width:58px; text-align:right;">
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="sum_row" value="<?= h(fv($item, 'sum_row', 0)) ?>" style="width:90px; text-align:right; font-weight:500;">
                        </td>

                        <td class="row-actions text-c">
                            <button type="button" class="row-dots" title="Дії">···</button>
                            <div class="row-menu">
                                <button class="row-menu-item" type="button">
                                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 8h6M8 5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                    Дублювати
                                </button>
                                <button class="row-menu-item danger item-del-btn" type="button">
                                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                    Видалити
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <tr class="add-row">
                    <td style="font-size:18px; color:var(--accent); text-align:center; padding-left:8px;">+</td>
                    <td colspan="8">
                        <div class="product-search-wrap">
                            <div id="productSearchResults"></div>
                            <input type="text" id="productSearchInput" placeholder="Додати позицію — введіть найменування, код або артикул...">
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <?php
            $initNet = 0; $initVat = 0; $initSum = 0; $initDisc = 0;
            foreach ($items as $_it) {
                $s = (float)$_it['sum_row']; $v = (float)$_it['vat_rate'];
                $q = (float)$_it['quantity']; $p = (float)$_it['price']; $d = (float)$_it['discount_percent'];
                $gross = round($q * $p, 2);
                $initDisc += round($gross * $d / 100, 2);
                $initSum += $s;
                if ($v > 0) { $net = $s / (1 + $v / 100); $initVat += $s - $net; $initNet += $net; }
                else { $initNet += $s; }
            }
            $overheadCosts      = isset($demand['overhead_costs']) ? (float)$demand['overhead_costs'] : 0;
            if (!isset($deliveryCostDeduct)) $deliveryCostDeduct = !empty($marginData) ? $marginData['delivery_cost_deduct'] : 0;
            $mCost   = !empty($marginData) ? $marginData['cost_total'] : 0;
            $mMargin = $initSum - $mCost - $overheadCosts - $deliveryCostDeduct;
            $mPct    = $initSum > 0 ? round($mMargin / $initSum * 100, 1) : 0;
            ?>
            <div class="totals-invoice">
                <div class="totals-comment">
                    <div class="totals-comment-label">Коментар</div>
                    <textarea id="descriptionField" placeholder="Коментар до відвантаження…"><?= h(fv($demand, 'description', '')) ?></textarea>
                    <?php if (!empty($demand['sync_error'])): ?>
                    <div style="margin-top:4px;color:#b91c1c;font-size:12px;"><?= h($demand['sync_error']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="totals-margin">
                    <div class="totals-row sub">
                        <span>Собівартість</span>
                        <span class="totals-row-value" id="summary-cost"><?= !empty($marginData) ? number_format($mCost, 2, '.', ' ') : '—' ?></span>
                    </div>
                    <div class="totals-row sub">
                        <span>Накладні витрати</span>
                        <span class="totals-row-value">
                            <input type="text" class="overhead-input" id="overheadCosts"
                                   value="<?= number_format($overheadCosts, 2, '.', '') ?>"
                                   title="Накладні витрати (ручне введення)">
                        </span>
                    </div>
                    <div class="totals-row sub">
                        <span>Доставка</span>
                        <span class="totals-row-value" id="summary-delivery"><?= number_format($deliveryCostDeduct, 2, '.', ' ') ?></span>
                    </div>
                    <hr class="totals-divider">
                    <div class="totals-row">
                        <span>Маржа</span>
                        <span class="totals-row-value <?= $mMargin >= 0 ? 'text-green' : 'text-red' ?>" id="summary-margin">
                            <?= number_format($mMargin, 2, '.', ' ') ?>
                            <span style="font-size:11px;font-weight:500;opacity:.7">(<?= $mPct ?>%)</span>
                        </span>
                    </div>
                </div>
                <div class="totals-inner">
                    <div class="totals-row sub">
                        <span>Сума без ПДВ</span>
                        <span class="totals-row-value" id="summary-total-net"><?= number_format($initNet, 2, '.', ' ') ?></span>
                    </div>
                    <div class="totals-row sub">
                        <span>Знижка</span>
                        <span class="totals-row-value" id="summary-total-disc"><?= number_format($initDisc, 2, '.', ' ') ?></span>
                    </div>
                    <div class="totals-row sub">
                        <span>ПДВ</span>
                        <span class="totals-row-value" id="summary-total-vat"><?= number_format($initVat, 2, '.', ' ') ?></span>
                    </div>
                    <hr class="totals-divider">
                    <div class="totals-row big">
                        <span>Разом</span>
                        <span class="totals-row-value" id="summary-total-sum"><?= number_format($initSum, 2, '.', ' ') ?></span>
                    </div>
                </div>
            </div>
        </div><!-- /tab-positions -->

        <!-- TAB: Пов'язані документи -->
        <div class="tab-content" id="tab-related">
            <div id="reldocs-wrap">
                <div id="reldocs-loading" style="display:none; padding:40px; text-align:center; color:#6b7280; font-size:13px;">Завантаження…</div>
                <div id="reldocs-empty"   style="display:none; padding:40px; text-align:center; color:#9ca3af; font-size:13px;">Пов'язані документи відсутні</div>
                <div id="reldocs-graph-wrap" style="overflow:auto; min-height:120px; padding:6px 10px 10px;">
                    <svg id="reldocs-svg" xmlns="http://www.w3.org/2000/svg"
                         style="display:block; font-family:'Geist',system-ui,sans-serif;"></svg>
                </div>
            </div>
        </div>

        <!-- TAB: Файли -->
        <div class="tab-content" id="tab-files">
            <div class="empty-box">Файли — в розробці</div>
        </div>

        <!-- TAB: Задачі -->
        <div class="tab-content" id="tab-tasks">
            <div class="empty-box">Задачі — в розробці</div>
        </div>

        <!-- TAB: Події -->
        <div class="tab-content" id="tab-events">
            <div class="empty-box">Події — в розробці</div>
        </div>

    </div><!-- /positions-panel -->

    <!-- ══ HISTORY PANEL ══ -->
    <div id="historyPanel" style="position:fixed;top:0;right:-520px;width:500px;height:100%;background:var(--surface);border-left:1px solid var(--border);box-shadow:-6px 0 24px rgba(0,0,0,.08);transition:right .22s ease;z-index:9999;overflow-y:auto;padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="margin:0; font-size:16px;">Історія змін</h3>
            <button type="button" id="historyClose" class="btn">Закрити</button>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
            <thead>
            <tr style="border-bottom:1px solid var(--border);">
                <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Дата</th>
                <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Подія</th>
                <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Джерело</th>
                <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Коментар</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($history)): ?>
                <tr><td colspan="4" class="empty-box">Історія порожня</td></tr>
            <?php else: ?>
                <?php foreach ($history as $ev): ?>
                <tr style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:7px 8px;">
                        <?= h(!empty($ev['created_at']) ? date('d.m.Y H:i', strtotime($ev['created_at'])) : '—') ?>
                    </td>
                    <td style="padding:7px 8px;"><?= h($ev['event_label'] ?: $ev['event_type']) ?></td>
                    <td style="padding:7px 8px;"><?= h($ev['employee_name'] ?: '—') ?></td>
                    <td style="padding:7px 8px;"><?= h($ev['comment'] ?: '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div id="historyOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.2); z-index:9998;"></div>

    <!-- ══ RETURN LOGISTICS MODAL ══ -->
    <div class="modal-overlay" id="returnLogisticsModal" style="display:none;">
        <div class="modal-box" style="width:480px; max-width:98vw;">
            <div class="modal-head">
                <span>Логістика повернення</span>
                <button class="modal-close" id="rlModalClose">&#x2715;</button>
            </div>
            <div class="modal-body" style="padding:16px 20px; display:flex; flex-direction:column; gap:12px;">
                <div class="f">
                    <label>Спосіб повернення</label>
                    <select id="rlReturnType">
                        <option value="novaposhta_ttn">Зворотна ТТН Нова Пошта</option>
                        <option value="ukrposhta_ttn">Зворотна ТТН Укрпошта</option>
                        <option value="manual">Інший спосіб (кур'єр, особисто)</option>
                        <option value="left_with_client">Залишили клієнту</option>
                    </select>
                </div>
                <div class="f" id="rlTtnWrap">
                    <label>Номер ТТН повернення</label>
                    <input type="text" id="rlTtnNumber" placeholder="59001234567890">
                </div>
                <div class="f">
                    <label>Коментар (необов'язково)</label>
                    <textarea id="rlDescription" style="min-height:60px;" placeholder="Причина повернення, деталі…"></textarea>
                </div>
                <div class="modal-error" id="rlError" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="rlConfirmBtn">Зареєструвати</button>
                <button type="button" class="btn" id="rlCancelBtn">Скасувати</button>
            </div>
        </div>
    </div>

<?php endif; // !isNew ?>
</div><!-- /page-shell -->

<?php if (!$isNew): ?>
<script>
/* ══ PAGE DATA (server → client) ══ */
var _PAGE = {
    demandId:       <?= (int)$id ?>,
    cpId:           <?= (int)$currentCpId ?>,
    demand:         <?= json_encode(!empty($demand) ? $demand : new stdClass()) ?>,
    items:          <?= json_encode(array_values($items)) ?>,
    version:        <?= (int)fv($demand, 'version', 1) ?>,
    statusLabels:   <?= json_encode($statusLabels) ?>,
    statusStyles:   <?= json_encode($_statusInlineStyles) ?>,
    syncStyles:     <?= json_encode($syncStateStyles) ?>,
    syncLabels:     <?= json_encode($syncStateLabels) ?>,
    marginData:     <?= json_encode($marginData ?: null) ?>,
    statusColorMap: (function() {
        var m = {};
        <?php foreach ($_scAll as $_s => $_e): ?>
        m[<?= json_encode($_s) ?>] = <?= json_encode($_e[2]) ?>;
        <?php endforeach; ?>
        return m;
    }()),
    statusLabelMap: (function() {
        var m = {};
        <?php foreach ($_scAll as $_s => $_e): ?>
        m[<?= json_encode($_s) ?>] = <?= json_encode($_e[0]) ?>;
        <?php endforeach; ?>
        return m;
    }())
};
</script>
<script src="/modules/demand/js/demand-edit.js?v=<?= filemtime(__DIR__ . '/../js/demand-edit.js') ?>"></script>
<?php require_once __DIR__ . '/../../shared/print-modal.php'; ?>
<?php require_once __DIR__ . '/../../shared/pack-print-modal.php'; ?>
<script src="/modules/print/js/pack-print.js?v=<?= filemtime(__DIR__ . '/../../print/js/pack-print.js') ?>"></script>
<script src="/modules/shared/chat-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/chat-modal.js') ?>"></script>

<?php if (!empty($currentCpId)): ?>
<!-- ══ COMPOSE MODAL ══════════════════════════════════════════════════ -->
<div id="sendComposeModal" class="modal-overlay">
    <div class="modal-box" style="width:540px;max-width:98vw">
        <div class="modal-head">
            <span id="sendComposeTitle">Надіслати клієнту</span>
            <button type="button" class="modal-close" id="sendComposeClose">&#x2715;</button>
        </div>
        <div class="modal-body" style="padding:16px 20px">
            <div style="display:flex;gap:6px;margin-bottom:10px;align-items:center">
                <span style="font-size:12px;color:#6b7280;flex-shrink:0">Канал:</span>
                <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer">
                    <input type="radio" name="sendCompCh" value="viber" checked> Viber
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer">
                    <input type="radio" name="sendCompCh" value="sms"> SMS
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer">
                    <input type="radio" name="sendCompCh" value="note"> Нотатка
                </label>
            </div>
            <textarea id="sendComposeText" rows="12"
                style="width:100%;box-sizing:border-box;font-size:13px;font-family:inherit;line-height:1.55;
                       border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;resize:vertical;outline:none"
                onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#d1d5db'"></textarea>
            <div id="sendComposeAttachInfo" style="display:none;margin-top:6px;font-size:12px;color:#6b7280">
                📎 <span id="sendComposeAttachName"></span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="sendComposeSend">📤 Надіслати</button>
            <button type="button" class="btn btn-ghost" id="sendComposeCancel">Скасувати</button>
        </div>
    </div>
</div>

<script src="/modules/demand/js/demand-compose.js?v=<?= filemtime(__DIR__ . '/../js/demand-compose.js') ?>"></script>
<?php endif; ?>

<?php endif; ?>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>