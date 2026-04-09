<?php
// Variables from index.php: $rows, $total, $totalPages, $page, $limit,
//   $filters, $filterOrganizations, $filterManagers, $filterCpName

require_once __DIR__ . '/../../shared/StatusColors.php';
$_dmMap = StatusColors::all('demand');
$statusLabels = array();
$statusColors = array();
foreach ($_dmMap as $_s => $_e) { $statusLabels[$_s] = $_e[0]; $statusColors[$_s] = $_e[1]; }

$search       = isset($_GET['search'])   ? $_GET['search']   : '';
$statusFilter = isset($_GET['status']) && is_array($_GET['status']) ? $_GET['status'] : array();
$dateFrom     = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo       = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$fOrgId       = isset($_GET['organization_id']) ? $_GET['organization_id'] : '';
$fMgrId       = isset($_GET['manager_employee_id']) ? $_GET['manager_employee_id'] : '';
$fCpId        = isset($_GET['counterparty_id']) ? $_GET['counterparty_id'] : '';
$fSumFrom     = isset($_GET['sum_from']) ? $_GET['sum_from'] : '';
$fSumTo       = isset($_GET['sum_to'])   ? $_GET['sum_to']   : '';

if (!isset($filterOrganizations))  $filterOrganizations = array();
if (!isset($filterManagers))       $filterManagers = array();
if (!isset($filterCpName))         $filterCpName = '';

function dm_url($extra = array()) {
    $q = array_merge($_GET, $extra);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) unset($q[$k]);
    }
    $qs = http_build_query($q);
    return '/demand' . ($qs ? '?' . $qs : '');
}

$hasAdvancedFilter = ($fOrgId || $fMgrId || $fCpId || $fSumFrom !== '' || $fSumTo !== '');
$hasAnyFilter = ($hasAdvancedFilter || !empty($statusFilter) || $dateFrom || $dateTo || $search);

$syncColors = array(
    'synced'  => 'badge-green',
    'new'     => 'badge-gray',
    'changed' => 'badge-orange',
    'error'   => 'badge-red',
);
?>
<style>
/* ── Toolbar ── */
.dm-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.dm-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.dm-search-wrap { flex:1; min-width:160px; }
.dm-toolbar .btn        { height:34px; padding:0 12px; }
.dm-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }
.dm-num-link { color:#1d4ed8; text-decoration:none; font-weight:600; }
.dm-num-link:hover { text-decoration:underline; }
.dm-cp-link { color:#374151; text-decoration:none; }
.dm-cp-link:hover { color:#1d4ed8; text-decoration:underline; }

/* ── Filter bar (primary row) ── */
.dm-fbar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:8px 12px; background:#fafbfc; border:1px solid #e5e7eb;
    border-radius:8px; margin-bottom:8px; font-size:13px;
}
.dm-fbar select, .dm-fbar input[type=date] {
    height:28px; font-size:13px; padding:0 6px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
}
.dm-fbar input[type=date] { width:128px; }
.dm-fsep { width:1px; height:20px; background:#d1d5db; flex-shrink:0; }
.dm-flabel { font-size:12px; color:#6b7280; flex-shrink:0; }

/* Date quick buttons */
.dm-date-quick { display:inline-flex; gap:2px; }
.dm-date-qbtn {
    font-size:11px; padding:2px 7px; border:1px solid #d1d5db; border-radius:4px;
    background:#fff; color:#374151; cursor:pointer; white-space:nowrap; line-height:1.5;
}
.dm-date-qbtn:hover { background:#f3f4f6; }
.dm-date-qbtn.active { background:#2563eb; color:#fff; border-color:#2563eb; }

/* Gear wrapper */
.dm-gear-wrap { position:relative; margin-left:auto; flex-shrink:0; }
.dm-gear-btn {
    width:28px; height:28px; display:flex; align-items:center; justify-content:center;
    border:1px solid #d1d5db; border-radius:5px; background:#fff; cursor:pointer; color:#6b7280;
    position:relative;
}
.dm-gear-btn:hover { background:#f3f4f6; }
.dm-gear-btn.has-active { color:#2563eb; border-color:#93c5fd; }
.dm-gear-btn.has-active::after {
    content:''; position:absolute; top:-2px; right:-2px;
    width:7px; height:7px; border-radius:50%; background:#2563eb; border:1.5px solid #fff;
}

/* Gear dropdown */
.dm-gear-dd {
    display:none; position:absolute; top:calc(100% + 4px); right:0; z-index:200;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); padding:10px 14px; width:220px;
}
.dm-gear-dd.open { display:block; }
.dm-gear-dd h4 { margin:0 0 6px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.dm-gear-dd label {
    display:flex; align-items:center; gap:6px; padding:3px 0;
    font-size:13px; cursor:pointer; color:#374151;
}
.dm-gear-dd input[type=checkbox] { accent-color:#2563eb; }

/* ── Advanced filters (4-col grid) ── */
.dm-adv {
    padding:10px 12px; background:#fafbfc; border:1px solid #e5e7eb;
    border-top:none; border-radius:0 0 8px 8px; margin-top:-9px; margin-bottom:8px;
}
.dm-adv-grid {
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:8px 20px;
}
.dm-adv-cell {
    display:flex; align-items:center; gap:6px; min-width:0;
}
.dm-adv-cell .dm-flabel {
    flex:0 0 80px; text-align:right; font-size:12px; color:#6b7280;
}
.dm-adv-cell .dm-fctrl {
    flex:1; min-width:0; display:flex; align-items:center; gap:4px;
}
.dm-adv-cell select, .dm-adv-cell input[type=number] {
    height:28px; font-size:13px; padding:0 6px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
    width:100%; min-width:0;
}
.dm-adv-cell input[type=number] { width:60px; flex:1; }
@media (max-width:900px) {
    .dm-adv-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Counterparty live search */
.dm-cp-wrap { position:relative; display:flex; gap:4px; align-items:center; width:100%; }
.dm-cp-wrap input[type=text] {
    height:28px; font-size:13px; padding:0 8px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff; width:100%; min-width:0;
}
.dm-cp-dd {
    position:absolute; top:100%; left:0; z-index:100;
    background:#fff; border:1px solid #d1d5db; border-radius:6px;
    box-shadow:0 4px 12px rgba(0,0,0,.12); max-height:220px; overflow-y:auto;
    width:100%; min-width:260px; display:none;
}
.dm-cp-dd.open { display:block; }
.dm-cp-dd-item {
    padding:6px 10px; cursor:pointer; font-size:13px;
    display:flex; justify-content:space-between; align-items:center;
}
.dm-cp-dd-item:hover { background:#f3f4f6; }
.dm-cp-dd-item .cp-type { font-size:11px; color:#9ca3af; }
.dm-cp-clear { cursor:pointer; color:#9ca3af; font-size:14px; line-height:1; padding:2px; }
.dm-cp-clear:hover { color:#ef4444; }

/* ── Row checkbox ── */
.dm-row-cb { accent-color:#2563eb; cursor:pointer; }
.dm-cb-th { width:32px; text-align:center; }

/* ── Context menu (actions) ── */
.dm-act-wrap { position:relative; }
.dm-act-btn {
    width:28px; height:28px; display:flex; align-items:center; justify-content:center;
    border:none; background:transparent; cursor:pointer; border-radius:5px; color:#6b7280;
}
.dm-act-btn:hover { background:#f3f4f6; color:#374151; }
.dm-act-dd {
    display:none; position:absolute; right:0; top:100%; z-index:300;
    background:#fff; border:1px solid #e5e7eb; border-radius:6px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:160px; padding:4px 0;
}
.dm-act-dd.open { display:block; }
.dm-act-dd button {
    display:flex; align-items:center; gap:6px; width:100%;
    padding:7px 12px; border:none; background:none; cursor:pointer;
    font-size:13px; color:#374151; text-align:left;
}
.dm-act-dd button:hover { background:#f3f4f6; }
.dm-act-dd button.danger { color:#dc2626; }
.dm-act-dd button.danger:hover { background:#fef2f2; }

/* ── Bulk actions bar ── */
.dm-bulk-bar {
    display:none; align-items:center; gap:10px;
    margin-left:16px; font-size:13px; color:#374151;
}
.dm-bulk-bar.visible { display:inline-flex; }
.dm-bulk-count { font-weight:600; color:#2563eb; }
.dm-bulk-actions { position:relative; }
.dm-bulk-btn {
    height:28px; padding:0 10px; font-size:12px; font-weight:500;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
    cursor:pointer; color:#374151; display:flex; align-items:center; gap:4px;
}
.dm-bulk-btn:hover { background:#f3f4f6; }
.dm-bulk-dd {
    display:none; position:absolute; left:0; top:100%; z-index:300;
    background:#fff; border:1px solid #e5e7eb; border-radius:6px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:160px; padding:4px 0;
}
.dm-bulk-dd.open { display:block; }
.dm-bulk-dd button {
    display:flex; align-items:center; gap:6px; width:100%;
    padding:7px 12px; border:none; background:none; cursor:pointer;
    font-size:13px; color:#374151; text-align:left;
}
.dm-bulk-dd button:hover { background:#f3f4f6; }
.dm-bulk-dd button.danger { color:#dc2626; }
.dm-bulk-dd button.danger:hover { background:#fef2f2; }

.dm-manager { font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
.dm-profit-pos { color:#15803d; }
.dm-profit-neg { color:#dc2626; }
.dm-profit-zero { color:#9ca3af; }
</style>

<div class="page-wrap">

    <form method="get" action="/demand" id="dmForm">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="counterparty_id" id="fCpId" value="<?= htmlspecialchars($fCpId, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Toolbar -->
        <div class="dm-toolbar">
            <h1>Відвантаження</h1>
            <div class="dm-search-wrap">
                <div class="chip-input" id="dmChipBox">
                    <input type="text" class="chip-typer" id="dmChipTyper"
                           placeholder="ID, номер, контрагент…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="dmChipClear" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="search" id="dmSearchHidden" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <!-- Primary filter bar -->
        <div class="dm-fbar" id="filterBar">
            <!-- Status select -->
            <span class="dm-flabel">Статус</span>
            <select name="status[]" id="fStatus" multiple size="1" onchange="this.form.submit()" style="min-width:140px;">
                <?php foreach ($statusLabels as $sv => $sl): ?>
                <option value="<?= $sv ?>" <?= in_array($sv, $statusFilter) ? 'selected' : '' ?>><?= $sl ?></option>
                <?php endforeach; ?>
            </select>

            <div class="dm-fsep"></div>

            <!-- Date: quick + pickers -->
            <span class="dm-flabel">Дата</span>
            <div class="dm-date-quick">
                <button type="button" class="dm-date-qbtn" data-range="today">Сьогодні</button>
                <button type="button" class="dm-date-qbtn" data-range="yesterday">Вчора</button>
                <button type="button" class="dm-date-qbtn" data-range="week">Тиждень</button>
                <button type="button" class="dm-date-qbtn" data-range="month">Місяць</button>
            </div>
            <input type="date" name="date_from" id="fDateFrom" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
            <span class="text-muted" style="font-size:12px;">—</span>
            <input type="date" name="date_to" id="fDateTo" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">

            <!-- Gear -->
            <div class="dm-gear-wrap">
                <button type="button" class="dm-gear-btn <?= $hasAdvancedFilter ? 'has-active' : '' ?>" id="gearBtn" title="Розширені фільтри">
                    <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.4"/><path d="M13.3 6.4l-.8-.5a5 5 0 0 0 0-1.8l.8-.5a.7.7 0 0 0 .2-.9l-.8-1.4a.7.7 0 0 0-.9-.3l-.8.5a5 5 0 0 0-1.6-.9V.7A.7.7 0 0 0 8.7 0H7.3a.7.7 0 0 0-.7.7v.9a5 5 0 0 0-1.6.9l-.8-.5a.7.7 0 0 0-.9.3L2.5 3.7a.7.7 0 0 0 .2.9l.8.5a5 5 0 0 0 0 1.8l-.8.5a.7.7 0 0 0-.2.9l.8 1.4c.2.3.6.4.9.3l.8-.5a5 5 0 0 0 1.6.9v.9c0 .4.3.7.7.7h1.4c.4 0 .7-.3.7-.7v-.9a5 5 0 0 0 1.6-.9l.8.5c.3.1.7 0 .9-.3l.8-1.4a.7.7 0 0 0-.2-.9Z" stroke="currentColor" stroke-width="1.3"/></svg>
                </button>
                <div class="dm-gear-dd" id="gearDd">
                    <h4>Показувати фільтри</h4>
                    <label><input type="checkbox" data-filter="f-counterparty" checked> Контрагент</label>
                    <label><input type="checkbox" data-filter="f-sum" checked> Сума</label>
                    <label><input type="checkbox" data-filter="f-organization" checked> Організація</label>
                    <label><input type="checkbox" data-filter="f-manager" checked> Менеджер</label>
                </div>
            </div>
        </div>

        <!-- Advanced filters (4-col grid) -->
        <div class="dm-adv" id="advFilters">
            <div class="dm-adv-grid">

                <!-- Counterparty -->
                <div class="dm-adv-cell" data-fid="f-counterparty">
                    <span class="dm-flabel">Контрагент</span>
                    <div class="dm-fctrl">
                        <div class="dm-cp-wrap">
                            <input type="text" id="cpSearchInput" placeholder="Пошук…" autocomplete="off"
                                   value="<?= htmlspecialchars($filterCpName, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($fCpId): ?>
                            <span class="dm-cp-clear" id="cpClearBtn" title="Скинути">&#x2715;</span>
                            <?php endif; ?>
                            <div class="dm-cp-dd" id="cpDropdown"></div>
                        </div>
                    </div>
                </div>

                <!-- Sum -->
                <div class="dm-adv-cell" data-fid="f-sum">
                    <span class="dm-flabel">Сума</span>
                    <div class="dm-fctrl">
                        <input type="number" name="sum_from" placeholder="від" step="0.01" value="<?= htmlspecialchars($fSumFrom, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                        <span style="color:#9ca3af;flex-shrink:0;">—</span>
                        <input type="number" name="sum_to" placeholder="до" step="0.01" value="<?= htmlspecialchars($fSumTo, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                    </div>
                </div>

                <!-- Organization -->
                <div class="dm-adv-cell" data-fid="f-organization">
                    <span class="dm-flabel">Організація</span>
                    <div class="dm-fctrl">
                        <select name="organization_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterOrganizations as $org): ?>
                            <option value="<?= (int)$org['id'] ?>" <?= $fOrgId == $org['id'] ? 'selected' : '' ?>><?= htmlspecialchars($org['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Manager -->
                <div class="dm-adv-cell" data-fid="f-manager">
                    <span class="dm-flabel">Менеджер</span>
                    <div class="dm-fctrl">
                        <select name="manager_employee_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterManagers as $mgr): ?>
                            <option value="<?= (int)$mgr['id'] ?>" <?= $fMgrId == $mgr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mgr['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

            </div>
        </div>
    </form>

    <!-- Count + Bulk actions -->
    <div style="font-size:13px;color:#6b7280;margin-bottom:8px;display:flex;align-items:center;">
        <span>Знайдено: <strong><?= number_format($total) ?></strong></span>
        <?php if ($hasAnyFilter): ?>
        <a href="#" id="resetAllBtn" style="margin-left:8px;font-size:12px;color:#ef4444;">Скинути все</a>
        <?php endif; ?>

        <div class="dm-bulk-bar" id="bulkBar">
            <span>Обрано: <span class="dm-bulk-count" id="bulkCount">0</span></span>
            <div class="dm-bulk-actions">
                <button type="button" class="dm-bulk-btn" id="bulkActBtn">
                    Дії
                    <svg width="10" height="10" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="dm-bulk-dd" id="bulkDd">
                    <button type="button" id="bulkPackBtn">
                        📦 Друк пакетів
                    </button>
                    <button type="button" id="bulkQueueBtn">
                        📥 В чергу
                    </button>
                    <button type="button" class="danger" id="bulkDeleteBtn">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V3a1 1 0 011-1h2a1 1 0 011 1v1M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Видалити
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <table class="crm-table">
        <thead>
            <tr>
                <th class="dm-cb-th"><input type="checkbox" class="dm-row-cb" id="cbAll" title="Обрати все"></th>
                <th>Номер</th>
                <th style="width:90px">Дата</th>
                <th>Контрагент</th>
                <th>Замовлення</th>
                <th style="width:130px">Статус</th>
                <th style="text-align:right">Сума</th>
                <th>Організація</th>
                <th>Менеджер</th>
                <th style="width:80px">Синх.</th>
                <th style="text-align:right;width:100px">Маржа</th>
                <th style="width:36px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:32px 0;">Записів не знайдено</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php
                $st  = isset($row['status']) ? $row['status'] : 'new';
                $cpId = isset($row['counterparty_id']) ? (int)$row['counterparty_id'] : 0;
                $profit = isset($row['profit']) ? (float)$row['profit'] : 0;
                $profitCls = $profit > 0 ? 'dm-profit-pos' : ($profit < 0 ? 'dm-profit-neg' : 'dm-profit-zero');
            ?>
            <tr style="cursor:pointer" data-demand-id="<?= (int)$row['id'] ?>" onclick="window.location='/demand/edit?id=<?= (int)$row['id'] ?>'">
                <td style="text-align:center" onclick="event.stopPropagation()">
                    <input type="checkbox" class="dm-row-cb dm-row-select" value="<?= (int)$row['id'] ?>">
                </td>
                <td>
                    <a class="dm-num-link" href="/demand/edit?id=<?= (int)$row['id'] ?>" onclick="event.stopPropagation()">
                        <?= htmlspecialchars($row['number'] ?: ('# ' . $row['id']), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </td>
                <td class="nowrap fs-12"><?= $row['moment'] ? substr($row['moment'], 0, 10) : '—' ?></td>
                <td>
                    <?php if ($cpId > 0): ?>
                    <a class="dm-cp-link" href="/counterparties/view?id=<?= $cpId ?>" target="_blank" onclick="event.stopPropagation()" title="<?= htmlspecialchars($row['counterparty_name'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($row['counterparty_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php else: ?>
                        <?= htmlspecialchars($row['counterparty_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </td>
                <td class="fs-12">
                    <?php if (!empty($row['order_number'])): ?>
                    <a href="/customerorder/edit?id=<?= (int)$row['customerorder_id'] ?>" onclick="event.stopPropagation()" style="color:#1d4ed8;text-decoration:none;">
                        <?= htmlspecialchars($row['order_number'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= isset($statusColors[$st]) ? $statusColors[$st] : 'badge-gray' ?>">
                        <?= isset($statusLabels[$st]) ? $statusLabels[$st] : $st ?>
                    </span>
                </td>
                <td style="text-align:right" class="nowrap">
                    <?= number_format((float)$row['sum_total'], 2, '.', ' ') ?>
                </td>
                <td class="fs-12"><?= htmlspecialchars($row['organization_short'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="dm-manager" title="<?= htmlspecialchars($row['manager_display'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($row['manager_display'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td>
                    <?php $ss = isset($row['sync_state']) ? $row['sync_state'] : 'new'; ?>
                    <span class="badge <?= isset($syncColors[$ss]) ? $syncColors[$ss] : 'badge-gray' ?>" style="font-size:11px">
                        <?= $ss ?>
                    </span>
                </td>
                <td style="text-align:right" class="nowrap <?= $profitCls ?>">
                    <?= number_format($profit, 2, '.', ' ') ?>
                </td>
                <td onclick="event.stopPropagation()" style="text-align:center">
                    <div class="dm-act-wrap">
                        <button type="button" class="dm-act-btn" data-act-toggle>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="3" r="1.2" fill="currentColor"/><circle cx="8" cy="8" r="1.2" fill="currentColor"/><circle cx="8" cy="13" r="1.2" fill="currentColor"/></svg>
                        </button>
                        <div class="dm-act-dd">
                            <button type="button" class="danger" data-delete-demand="<?= (int)$row['id'] ?>">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 4h10M6 4V3a1 1 0 011-1h2a1 1 0 011 1v1M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Видалити
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px;">
        <?php if ($page > 1): ?>
            <a href="<?= dm_url(array('page' => $page - 1)) ?>">&laquo;</a>
        <?php endif; ?>
        <?php
        $pStart = max(1, $page - 3);
        $pEnd   = min($totalPages, $page + 3);
        for ($p = $pStart; $p <= $pEnd; $p++):
        ?>
            <?php if ($p == $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= dm_url(array('page' => $p)) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= dm_url(array('page' => $page + 1)) ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script src="/modules/shared/chip-search.js?v=<?= @filemtime(__DIR__ . '/../../shared/chip-search.js') ?>"></script>
<script>
(function() {
    var FKEY = 'dm_reg_filters';

    // Restore filters from sessionStorage
    if (window.location.search === '' || window.location.search === '?page=1') {
        var saved = sessionStorage.getItem(FKEY);
        if (saved) {
            window.location.replace('/demand?' + saved);
            return;
        }
    }

    // Save current filters
    (function saveFilters() {
        var params = new URLSearchParams(window.location.search);
        params.delete('page');
        var qs = params.toString();
        if (qs) {
            sessionStorage.setItem(FKEY, qs);
        }
    })();

    var form = document.getElementById('dmForm');

    // Chip search
    ChipSearch.init('dmChipBox', 'dmChipTyper', 'dmSearchHidden', form, {noComma: false});
    var clearBtn = document.getElementById('dmChipClear');
    var chipBox  = document.getElementById('dmChipBox');
    var typer    = document.getElementById('dmChipTyper');
    var hidden   = document.getElementById('dmSearchHidden');

    function updateClearBtn() {
        var has = chipBox.querySelectorAll('.chip').length > 0 || typer.value.trim() !== '';
        clearBtn.classList.toggle('hidden', !has);
    }
    new MutationObserver(updateClearBtn).observe(chipBox, {childList:true});
    typer.addEventListener('input', updateClearBtn);
    clearBtn.addEventListener('click', function() {
        chipBox.querySelectorAll('.chip').forEach(function(c){c.remove();});
        typer.value=''; hidden.value='';
        clearBtn.classList.add('hidden');
        var pi = form.querySelector('input[name="page"]'); if(pi) pi.value=1;
        form.submit();
    });
    updateClearBtn();

    // Date quick buttons
    var dateFrom = document.getElementById('fDateFrom');
    var dateTo   = document.getElementById('fDateTo');

    function fmtDate(d) {
        var y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0');
        return y+'-'+m+'-'+dd;
    }
    function setRange(from, to) {
        dateFrom.value = fmtDate(from);
        dateTo.value   = fmtDate(to);
        form.submit();
    }

    document.querySelectorAll('.dm-date-qbtn[data-range]').forEach(function(btn) {
        var r = btn.dataset.range;
        var today = new Date(); today.setHours(0,0,0,0);
        var df = dateFrom.value, dt = dateTo.value;
        if (r === 'today'     && df === fmtDate(today) && dt === fmtDate(today)) btn.classList.add('active');
        if (r === 'yesterday') { var y=new Date(today); y.setDate(y.getDate()-1); if(df===fmtDate(y)&&dt===fmtDate(y)) btn.classList.add('active'); }
        if (r === 'week')      { var w=new Date(today); w.setDate(w.getDate()-6); if(df===fmtDate(w)&&dt===fmtDate(today)) btn.classList.add('active'); }
        if (r === 'month')     { var m=new Date(today); m.setDate(m.getDate()-29); if(df===fmtDate(m)&&dt===fmtDate(today)) btn.classList.add('active'); }

        btn.addEventListener('click', function() {
            var t = new Date(); t.setHours(0,0,0,0);
            if (r === 'today')     return setRange(t, t);
            if (r === 'yesterday') { var y2=new Date(t); y2.setDate(y2.getDate()-1); return setRange(y2,y2); }
            if (r === 'week')      { var w2=new Date(t); w2.setDate(w2.getDate()-6); return setRange(w2,t); }
            if (r === 'month')     { var m2=new Date(t); m2.setDate(m2.getDate()-29); return setRange(m2,t); }
        });
    });

    // Gear button & settings dropdown
    var STORAGE_KEY = 'dm_filter_vis';
    var gearBtn  = document.getElementById('gearBtn');
    var gearDd   = document.getElementById('gearDd');

    var savedVis = {};
    try { savedVis = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch(e){}

    function applyVisibility() {
        document.querySelectorAll('[data-fid]').forEach(function(el) {
            var fid = el.dataset.fid;
            el.style.display = (savedVis[fid] !== false) ? '' : 'none';
        });
        gearDd.querySelectorAll('input[data-filter]').forEach(function(cb) {
            cb.checked = savedVis[cb.dataset.filter] !== false;
        });
    }

    gearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        gearDd.classList.toggle('open');
    });

    document.addEventListener('click', function(e) {
        if (!gearDd.contains(e.target) && e.target !== gearBtn) {
            gearDd.classList.remove('open');
        }
    });

    gearDd.querySelectorAll('input[data-filter]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            savedVis[cb.dataset.filter] = cb.checked;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(savedVis));
            applyVisibility();
        });
    });

    applyVisibility();

    // Counterparty live search
    var cpInput = document.getElementById('cpSearchInput');
    var cpDd    = document.getElementById('cpDropdown');
    var cpHid   = document.getElementById('fCpId');
    var cpTimer = null;

    if (cpInput) {
        cpInput.addEventListener('input', function() {
            clearTimeout(cpTimer);
            var q = cpInput.value.trim();
            if (q.length < 2) { cpDd.classList.remove('open'); return; }
            cpTimer = setTimeout(function() {
                fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&limit=12')
                    .then(function(r){ return r.json(); })
                    .then(function(data) {
                        var items = data.items || [];
                        if (!items.length) { cpDd.classList.remove('open'); return; }
                        cpDd.innerHTML = '';
                        items.forEach(function(it) {
                            var div = document.createElement('div');
                            div.className = 'dm-cp-dd-item';
                            div.innerHTML = '<span>' + it.name + '</span><span class="cp-type">' + (it.type_label||'') + '</span>';
                            div.addEventListener('click', function() {
                                cpHid.value = it.id;
                                cpInput.value = it.name;
                                cpDd.classList.remove('open');
                                form.submit();
                            });
                            cpDd.appendChild(div);
                        });
                        cpDd.classList.add('open');
                    });
            }, 250);
        });

        cpInput.addEventListener('focus', function() {
            if (cpDd.children.length > 0) cpDd.classList.add('open');
        });

        document.addEventListener('click', function(e) {
            if (!cpInput.contains(e.target) && !cpDd.contains(e.target)) {
                cpDd.classList.remove('open');
            }
        });

        cpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') cpDd.classList.remove('open');
        });

        var cpClearBtn = document.getElementById('cpClearBtn');
        if (cpClearBtn) {
            cpClearBtn.addEventListener('click', function() {
                cpHid.value = '';
                cpInput.value = '';
                form.submit();
            });
        }
    }

    // Reset all button
    var resetBtn = document.getElementById('resetAllBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sessionStorage.removeItem(FKEY);
            window.location.href = '/demand';
        });
    }

    // Checkbox selection + Bulk actions
    var cbAll      = document.getElementById('cbAll');
    var rowCbs     = document.querySelectorAll('.dm-row-select');
    var bulkBar    = document.getElementById('bulkBar');
    var bulkCount  = document.getElementById('bulkCount');
    var bulkActBtn = document.getElementById('bulkActBtn');
    var bulkDd     = document.getElementById('bulkDd');
    var bulkDelBtn = document.getElementById('bulkDeleteBtn');

    function getSelectedIds() {
        var ids = [];
        rowCbs.forEach(function(cb) { if (cb.checked) ids.push(parseInt(cb.value)); });
        return ids;
    }

    function updateBulkBar() {
        var ids = getSelectedIds();
        var n = ids.length;
        bulkCount.textContent = n;
        if (n > 0) {
            bulkBar.classList.add('visible');
        } else {
            bulkBar.classList.remove('visible');
            bulkDd.classList.remove('open');
        }
        cbAll.checked = rowCbs.length > 0 && n === rowCbs.length;
        cbAll.indeterminate = n > 0 && n < rowCbs.length;
    }

    if (cbAll) {
        cbAll.addEventListener('change', function() {
            rowCbs.forEach(function(cb) { cb.checked = cbAll.checked; });
            updateBulkBar();
        });
    }

    rowCbs.forEach(function(cb) {
        cb.addEventListener('change', updateBulkBar);
    });

    if (bulkActBtn) {
        bulkActBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkDd.classList.toggle('open');
        });
    }

    // Delete logic
    function deleteDemands(ids) {
        var word = ids.length === 1 ? 'відвантаження' : 'відвантажень';
        if (!confirm('Видалити ' + ids.length + ' ' + word + '? Дія незворотня.')) return;

        fetch('/demand/api/delete_demands', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ids: ids})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.deleted && data.deleted.length > 0) {
                data.deleted.forEach(function(id) {
                    var row = document.querySelector('tr[data-demand-id="' + id + '"]');
                    if (row) row.remove();
                });
                updateBulkBar();
            }
            if (data.errors && data.errors.length > 0) {
                var msgs = data.errors.map(function(e) { return '#' + e.id + ': ' + e.error; });
                alert('Помилки:\n' + msgs.join('\n'));
            }
        })
        .catch(function(err) {
            alert('Помилка мережі: ' + err.message);
        });
    }

    if (bulkDelBtn) {
        bulkDelBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            bulkDd.classList.remove('open');
            deleteDemands(ids);
        });
    }

    // Bulk pack print
    var bulkPackBtn = document.getElementById('bulkPackBtn');
    if (bulkPackBtn) {
        bulkPackBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            bulkDd.classList.remove('open');
            bulkPackBtn.disabled = true;
            bulkPackBtn.textContent = '⏳ Формую…';

            var fd = new FormData();
            fd.append('demand_ids', ids.join(','));
            fetch('/print/api/generate_pack_bulk', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    bulkPackBtn.disabled = false;
                    bulkPackBtn.textContent = '📦 Друк пакетів';
                    if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                    // Open results in pack print modal for first demand, show summary
                    var results = d.results || [];
                    var okCount = results.filter(function(r){ return r.result && r.result.ok; }).length;
                    var errCount = results.length - okCount;
                    var msg = 'Сформовано пакетів: ' + okCount;
                    if (errCount > 0) msg += ', помилок: ' + errCount;
                    alert(msg);
                    if (okCount > 0 && typeof PackPrint !== 'undefined') {
                        // Open first successful pack
                        var first = results.filter(function(r){ return r.result && r.result.ok; })[0];
                        if (first) PackPrint.open(first.demand_id);
                    }
                })
                .catch(function() {
                    bulkPackBtn.disabled = false;
                    bulkPackBtn.textContent = '📦 Друк пакетів';
                    alert('Помилка мережі');
                });
        });
    }

    // Bulk queue
    var bulkQueueBtn = document.getElementById('bulkQueueBtn');
    if (bulkQueueBtn) {
        bulkQueueBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            bulkDd.classList.remove('open');
            bulkQueueBtn.disabled = true;
            bulkQueueBtn.textContent = '⏳ Формую…';

            var fd = new FormData();
            fd.append('demand_ids', ids.join(','));
            fetch('/print/api/generate_pack_bulk', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    bulkQueueBtn.disabled = false;
                    bulkQueueBtn.textContent = '📥 В чергу';
                    if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                    // Queue all generated packs
                    var packIds = [];
                    (d.results || []).forEach(function(r) {
                        if (r.result && r.result.ok) packIds.push(r.result.pack_id);
                    });
                    if (!packIds.length) { alert('Жоден пакет не сформувався'); return; }
                    // Set queued=1 for all
                    var promises = packIds.map(function(pid) {
                        var fd2 = new FormData();
                        fd2.append('pack_id', pid);
                        return fetch('/print/api/queue_add', { method: 'POST', body: fd2 });
                    });
                    Promise.all(promises).then(function() {
                        if (typeof showToast === 'function') showToast('Додано ' + packIds.length + ' пакетів у чергу');
                    });
                })
                .catch(function() {
                    bulkQueueBtn.disabled = false;
                    bulkQueueBtn.textContent = '📥 В чергу';
                    alert('Помилка мережі');
                });
        });
    }

    // Context menu (per-row actions) — direct binding to avoid stopPropagation conflict on <td>
    document.querySelectorAll('[data-act-toggle]').forEach(function(toggler) {
        toggler.addEventListener('click', function(e) {
            e.stopPropagation();
            var dd = toggler.nextElementSibling;
            document.querySelectorAll('.dm-act-dd.open').forEach(function(d) {
                if (d !== dd) d.classList.remove('open');
            });
            dd.classList.toggle('open');
        });
    });

    document.querySelectorAll('[data-delete-demand]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var demandId = parseInt(btn.dataset.deleteDemand);
            btn.closest('.dm-act-dd').classList.remove('open');
            deleteDemands([demandId]);
        });
    });

    // Close context menus and bulk dropdown on outside click
    document.addEventListener('click', function() {
        document.querySelectorAll('.dm-act-dd.open').forEach(function(d) { d.classList.remove('open'); });
        bulkDd.classList.remove('open');
    });
}());
</script>
<?php require_once __DIR__ . '/../../shared/pack-print-modal.php'; ?>
<script src="/modules/print/js/pack-print.js?v=<?= filemtime(__DIR__ . '/../../print/js/pack-print.js') ?>"></script>