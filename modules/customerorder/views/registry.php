<?php
$title     = 'Замовлення';
$activeNav = 'sales';
$subNav    = 'orders';
require_once __DIR__ . '/../../shared/layout.php';

if (!isset($result)) {
    $result = array('ok' => false, 'error' => 'result not set', 'rows' => array(), 'count' => 0, 'page' => 1, 'limit' => 50);
}

$rows       = !empty($result['rows'])  ? $result['rows']        : array();
$total      = !empty($result['count']) ? (int)$result['count']  : 0;
$totalUnread = !empty($result['global_unread']) ? (int)$result['global_unread'] : 0;
$page       = !empty($result['page'])  ? (int)$result['page']   : 1;
$limit      = !empty($result['limit']) ? (int)$result['limit']  : 50;
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

$search       = isset($_GET['search'])   ? $_GET['search']   : '';
$statusFilter = isset($_GET['status']) && is_array($_GET['status']) ? $_GET['status'] : array();
$dateFrom     = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo       = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$fPayment     = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$fShipment    = isset($_GET['shipment_status']) ? $_GET['shipment_status'] : '';
$fOrgId       = isset($_GET['organization_id']) ? $_GET['organization_id'] : '';
$fMgrId       = isset($_GET['manager_employee_id']) ? $_GET['manager_employee_id'] : '';
$fCpId        = isset($_GET['counterparty_id']) ? $_GET['counterparty_id'] : '';
$fSumFrom     = isset($_GET['sum_from']) ? $_GET['sum_from'] : '';
$fSumTo       = isset($_GET['sum_to'])   ? $_GET['sum_to']   : '';
$fAction      = isset($_GET['next_action']) ? $_GET['next_action'] : '';
$fUnread      = isset($_GET['has_unread']) ? $_GET['has_unread'] : '';
$fTtn         = isset($_GET['has_ttn'])    ? $_GET['has_ttn']    : '';
$fPmId        = isset($_GET['payment_method_id']) ? $_GET['payment_method_id'] : '';
$fDmId        = isset($_GET['delivery_method_id']) ? $_GET['delivery_method_id'] : '';
$fHideCancelled = isset($_GET['hide_cancelled']) ? $_GET['hide_cancelled'] : '1';
$fHideDone      = isset($_GET['hide_done'])      ? $_GET['hide_done']      : '0';

if (!isset($filterOrganizations))  $filterOrganizations = array();
if (!isset($filterManagers))       $filterManagers = array();
if (!isset($filterActions))        $filterActions = array();
if (!isset($filterCpName))         $filterCpName = '';
if (!isset($filterPaymentMethods)) $filterPaymentMethods = array();
if (!isset($filterDeliveryMethods)) $filterDeliveryMethods = array();

require_once __DIR__ . '/../../shared/StatusColors.php';
$_coMap = StatusColors::all('customerorder');
$statusLabels = array();
$statusColors = array();
foreach ($_coMap as $_s => $_e) { $statusLabels[$_s] = $_e[0]; $statusColors[$_s] = $_e[1]; }

function co_url($extra = array()) {
    $q = array_merge($_GET, $extra);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) unset($q[$k]);
    }
    $qs = http_build_query($q);
    return '/customerorder' . ($qs ? '?' . $qs : '');
}

$hasAdvancedFilter = ($fPayment || $fShipment || $fOrgId || $fMgrId || $fCpId || $fSumFrom !== '' || $fSumTo !== '' || $fAction || $fUnread || $fTtn || $fPmId || $fDmId);
$hasAnyFilter = ($hasAdvancedFilter || !empty($statusFilter) || $dateFrom || $dateTo || $search);
?>
<style>
/* ── Toolbar ── */
.co-toolbar { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.co-toolbar h1 { margin:0; font-size:18px; font-weight:700; flex-shrink:0; }
.co-search-wrap { flex:1; min-width:160px; }
.co-toolbar .btn        { height:34px; padding:0 12px; }
.co-toolbar .chip-input { min-height:34px; max-height:34px; overflow:hidden; }
.co-num-link { color:#1d4ed8; text-decoration:none; font-weight:600; }
.co-num-link:hover { text-decoration:underline; }
.co-cp-link { color:#374151; text-decoration:none; }
.co-cp-link:hover { color:#1d4ed8; text-decoration:underline; }

/* ── Filter bar (primary row) ── */
.co-fbar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    padding:8px 12px; background:#fafbfc; border:1px solid #e5e7eb;
    border-radius:8px; margin-bottom:8px; font-size:13px;
}
.co-fbar select, .co-fbar input[type=date] {
    height:28px; font-size:13px; padding:0 6px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
}
.co-fbar input[type=date] { width:128px; }
.co-fsep { width:1px; height:20px; background:#d1d5db; flex-shrink:0; }
.co-flabel { font-size:12px; color:#6b7280; flex-shrink:0; }

/* Date quick buttons */
.co-date-quick { display:inline-flex; gap:2px; }
.co-date-qbtn {
    font-size:11px; padding:2px 7px; border:1px solid #d1d5db; border-radius:4px;
    background:#fff; color:#374151; cursor:pointer; white-space:nowrap; line-height:1.5;
}
.co-date-qbtn:hover { background:#f3f4f6; }
.co-date-qbtn.active { background:#2563eb; color:#fff; border-color:#2563eb; }

/* Gear wrapper */
.co-gear-wrap { position:relative; margin-left:auto; flex-shrink:0; }
.co-gear-btn {
    width:28px; height:28px; display:flex; align-items:center; justify-content:center;
    border:1px solid #d1d5db; border-radius:5px; background:#fff; cursor:pointer; color:#6b7280;
    position:relative;
}
.co-gear-btn:hover { background:#f3f4f6; }
.co-gear-btn.has-active { color:#2563eb; border-color:#93c5fd; }
.co-gear-btn.has-active::after {
    content:''; position:absolute; top:-2px; right:-2px;
    width:7px; height:7px; border-radius:50%; background:#2563eb; border:1.5px solid #fff;
}

/* Gear dropdown */
.co-gear-dd {
    display:none; position:absolute; top:calc(100% + 4px); right:0; z-index:200;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); padding:10px 14px; width:220px;
}
.co-gear-dd.open { display:block; }
.co-gear-dd h4 { margin:0 0 6px; font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
.co-gear-dd label {
    display:flex; align-items:center; gap:6px; padding:3px 0;
    font-size:13px; cursor:pointer; color:#374151;
}
.co-gear-dd input[type=checkbox] { accent-color:#2563eb; }

/* ── Advanced filters (strict 4-col grid) ── */
.co-adv {
    padding:10px 12px; background:#fafbfc; border:1px solid #e5e7eb;
    border-top:none; border-radius:0 0 8px 8px; margin-top:-9px; margin-bottom:8px;
}
.co-adv-grid {
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:8px 20px;
}
.co-adv-cell {
    display:flex; align-items:center; gap:6px; min-width:0;
}
.co-adv-cell .co-flabel {
    flex:0 0 80px; text-align:right; font-size:12px; color:#6b7280;
}
.co-adv-cell .co-fctrl {
    flex:1; min-width:0; display:flex; align-items:center; gap:4px;
}
.co-adv-cell select, .co-adv-cell input[type=number] {
    height:28px; font-size:13px; padding:0 6px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
    width:100%; min-width:0;
}
.co-adv-cell input[type=number] { width:60px; flex:1; }
@media (max-width:900px) {
    .co-adv-grid { grid-template-columns: repeat(2, 1fr); }
}

/* Counterparty live search */
.co-cp-wrap { position:relative; display:flex; gap:4px; align-items:center; width:100%; }
.co-cp-wrap input[type=text] {
    height:28px; font-size:13px; padding:0 8px;
    border:1px solid #d1d5db; border-radius:5px; background:#fff; width:100%; min-width:0;
}
.co-cp-dd {
    position:absolute; top:100%; left:0; z-index:100;
    background:#fff; border:1px solid #d1d5db; border-radius:6px;
    box-shadow:0 4px 12px rgba(0,0,0,.12); max-height:220px; overflow-y:auto;
    width:100%; min-width:260px; display:none;
}
.co-cp-dd.open { display:block; }
.co-cp-dd-item {
    padding:6px 10px; cursor:pointer; font-size:13px;
    display:flex; justify-content:space-between; align-items:center;
}
.co-cp-dd-item:hover { background:#f3f4f6; }
.co-cp-dd-item .cp-type { font-size:11px; color:#9ca3af; }
.co-cp-clear { cursor:pointer; color:#9ca3af; font-size:14px; line-height:1; padding:2px; }
.co-cp-clear:hover { color:#ef4444; }

/* ── Table indicators ── */
.co-ind {
    display:inline-flex; align-items:center; justify-content:center;
    width:22px; height:22px; border-radius:50%; flex-shrink:0;
}
.co-ind svg { width:12px; height:12px; }
.co-pay-none     { background:#f3f4f6; color:#9ca3af; }
.co-pay-partial  { background:#fef3c7; color:#92400e; }
.co-pay-done     { background:#dcfce7; color:#15803d; }
.co-pay-overdue  { background:#fee2e2; color:#7f1d1d; }
.co-pay-refund   { background:#f3e8ff; color:#6b21a8; }
.co-pm-label { font-size:8px; font-weight:700; line-height:1; letter-spacing:.2px; text-align:center; }
.co-ship-none     { background:#f3f4f6; color:#6b7280; }
.co-ship-reserved { background:#dbeafe; color:#1e40af; }
.co-ship-partial  { background:#fef3c7; color:#92400e; }
.co-ship-done     { background:#e0f2fe; color:#0369a1; }
.co-ship-delivered{ background:#dcfce7; color:#15803d; }
.co-ship-returned { background:#fee2e2; color:#9a3412; }
/* TTN / Delivery */
.co-ttn-yes  { background:#dcfce7; color:#15803d; }
.co-ttn-no   { background:#f3f4f6; color:#9ca3af; }
.co-pq-yes   { background:#ede9fe; color:#7c3aed; }
.co-done-mark {
    display:inline-block; color:#15803d; font-size:14px; font-weight:700;
}
.co-dm-label { font-size:9px; font-weight:700; line-height:1; letter-spacing:.3px; }

.co-unread {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:18px; height:18px; padding:0 5px;
    border-radius:9px; background:#ef4444; color:#fff;
    font-size:11px; font-weight:600; line-height:1;
}
.co-next-action {
    display:inline-flex; align-items:center; gap:3px;
    max-width:180px; padding:2px 8px;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    font-size:11px; font-weight:500; border-radius:5px;
    background:#f0f6ff; color:#2563eb;
}

/* ── Row checkbox ── */
.co-row-cb { accent-color:#2563eb; cursor:pointer; }
.co-cb-th { width:32px; text-align:center; }

/* ── Context menu (actions) ── */
.co-act-wrap { position:relative; }
.co-act-btn {
    width:28px; height:28px; display:flex; align-items:center; justify-content:center;
    border:none; background:transparent; cursor:pointer; border-radius:5px; color:#6b7280;
}
.co-act-btn:hover { background:#f3f4f6; color:#374151; }
.co-act-dd {
    display:none; position:absolute; right:0; top:100%; z-index:300;
    background:#fff; border:1px solid #e5e7eb; border-radius:6px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:160px; padding:4px 0;
}
.co-act-dd.open { display:block; }
.co-act-dd button {
    display:flex; align-items:center; gap:6px; width:100%;
    padding:7px 12px; border:none; background:none; cursor:pointer;
    font-size:13px; color:#374151; text-align:left;
}
.co-act-dd button:hover { background:#f3f4f6; }
.co-act-dd button.danger { color:#dc2626; }
.co-act-dd button.danger:hover { background:#fef2f2; }

/* ── Bulk actions bar ── */
.co-bulk-bar {
    display:none; align-items:center; gap:10px;
    margin-left:16px; font-size:13px; color:#374151;
}
.co-bulk-bar.visible { display:inline-flex; }
.co-bulk-count { font-weight:600; color:#2563eb; }
.co-bulk-actions { position:relative; }
.co-bulk-btn {
    height:28px; padding:0 10px; font-size:12px; font-weight:500;
    border:1px solid #d1d5db; border-radius:5px; background:#fff;
    cursor:pointer; color:#374151; display:flex; align-items:center; gap:4px;
}
.co-bulk-btn:hover { background:#f3f4f6; }
.co-bulk-dd {
    display:none; position:absolute; left:0; top:100%; z-index:300;
    background:#fff; border:1px solid #e5e7eb; border-radius:6px;
    box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:160px; padding:4px 0;
}
.co-bulk-dd.open { display:block; }
.co-bulk-dd button {
    display:flex; align-items:center; gap:6px; width:100%;
    padding:7px 12px; border:none; background:none; cursor:pointer;
    font-size:13px; color:#374151; text-align:left;
}
.co-bulk-dd button:hover { background:#f3f4f6; }
.co-bulk-dd button.danger { color:#dc2626; }
.co-bulk-dd button.danger:hover { background:#fef2f2; }
.co-manager { font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
</style>

<div class="page-wrap">

    <form method="get" action="/customerorder" id="coForm">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="counterparty_id" id="fCpId" value="<?= htmlspecialchars($fCpId, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Toolbar -->
        <div class="co-toolbar">
            <h1>Замовлення</h1>
            <a href="/customerorder/edit" class="btn btn-primary">+ Нове замовлення</a>
            <button type="button" class="btn" onclick="location.reload()" title="Оновити"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-2px"><path d="M2 8a6 6 0 0110.5-4M14 2v4h-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 8a6 6 0 01-10.5 4M2 14v-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            <div class="co-search-wrap">
                <div class="chip-input" id="coChipBox">
                    <input type="text" class="chip-typer" id="coChipTyper"
                           placeholder="ID, номер, контрагент, телефон…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="coChipClear" title="Очистити">&#x2715;</button>
                        <button type="submit" class="chip-act-btn chip-act-submit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" name="search" id="coSearchHidden" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <!-- ═══ Primary filter bar ═══ -->
        <div class="co-fbar" id="filterBar">
            <!-- Status select -->
            <span class="co-flabel">Статус</span>
            <select name="status[]" id="fStatus" multiple size="1" onchange="this.form.submit()" style="min-width:140px;">
                <?php foreach ($statusLabels as $sv => $sl): ?>
                <option value="<?= $sv ?>" <?= in_array($sv, $statusFilter) ? 'selected' : '' ?>><?= $sl ?></option>
                <?php endforeach; ?>
            </select>

            <input type="hidden" name="hide_cancelled" value="0">
            <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;cursor:pointer;margin-left:4px">
                <input type="checkbox" name="hide_cancelled" value="1"
                       <?= $fHideCancelled === '1' ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                Без скасованих
            </label>

            <input type="hidden" name="hide_done" value="0">
            <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#6b7280;cursor:pointer;margin-left:4px">
                <input type="checkbox" name="hide_done" value="1"
                       <?= $fHideDone === '1' ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                Без комплектних
            </label>

            <div class="co-fsep"></div>

            <!-- Date: quick + pickers -->
            <span class="co-flabel">Дата</span>
            <div class="co-date-quick">
                <button type="button" class="co-date-qbtn" data-range="today">Сьогодні</button>
                <button type="button" class="co-date-qbtn" data-range="yesterday">Вчора</button>
                <button type="button" class="co-date-qbtn" data-range="week">Тиждень</button>
                <button type="button" class="co-date-qbtn" data-range="month">Місяць</button>
            </div>
            <input type="date" name="date_from" id="fDateFrom" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
            <span class="text-muted" style="font-size:12px;">—</span>
            <input type="date" name="date_to" id="fDateTo" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">

            <!-- Gear -->
            <div class="co-gear-wrap">
                <button type="button" class="co-gear-btn <?= $hasAdvancedFilter ? 'has-active' : '' ?>" id="gearBtn" title="Розширені фільтри">
                    <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><path d="M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.4"/><path d="M13.3 6.4l-.8-.5a5 5 0 0 0 0-1.8l.8-.5a.7.7 0 0 0 .2-.9l-.8-1.4a.7.7 0 0 0-.9-.3l-.8.5a5 5 0 0 0-1.6-.9V.7A.7.7 0 0 0 8.7 0H7.3a.7.7 0 0 0-.7.7v.9a5 5 0 0 0-1.6.9l-.8-.5a.7.7 0 0 0-.9.3L2.5 3.7a.7.7 0 0 0 .2.9l.8.5a5 5 0 0 0 0 1.8l-.8.5a.7.7 0 0 0-.2.9l.8 1.4c.2.3.6.4.9.3l.8-.5a5 5 0 0 0 1.6.9v.9c0 .4.3.7.7.7h1.4c.4 0 .7-.3.7-.7v-.9a5 5 0 0 0 1.6-.9l.8.5c.3.1.7 0 .9-.3l.8-1.4a.7.7 0 0 0-.2-.9Z" stroke="currentColor" stroke-width="1.3"/></svg>
                </button>
                <div class="co-gear-dd" id="gearDd">
                    <h4>Показувати фільтри</h4>
                    <label><input type="checkbox" data-filter="f-counterparty" checked> Контрагент</label>
                    <label><input type="checkbox" data-filter="f-sum" checked> Сума</label>
                    <label><input type="checkbox" data-filter="f-payment" checked> Оплата</label>
                    <label><input type="checkbox" data-filter="f-shipment" checked> Відвантаження</label>
                    <label><input type="checkbox" data-filter="f-payment-method" checked> Спосіб оплати</label>
                    <label><input type="checkbox" data-filter="f-delivery-method" checked> Спосіб доставки</label>
                    <label><input type="checkbox" data-filter="f-ttn" checked> ТТН</label>
                    <label><input type="checkbox" data-filter="f-organization" checked> Організація</label>
                    <label><input type="checkbox" data-filter="f-manager" checked> Менеджер</label>
                    <label><input type="checkbox" data-filter="f-action" checked> Дія</label>
                    <label><input type="checkbox" data-filter="f-unread" checked> Повідомлення</label>
                </div>
            </div>
        </div>

        <!-- ═══ Advanced filters (4-col grid) ═══ -->
        <div class="co-adv" id="advFilters">
            <div class="co-adv-grid">

                <!-- Col 1 -->
                <div class="co-adv-cell" data-fid="f-counterparty">
                    <span class="co-flabel">Контрагент</span>
                    <div class="co-fctrl">
                        <div class="co-cp-wrap">
                            <input type="text" id="cpSearchInput" placeholder="Пошук…" autocomplete="off"
                                   value="<?= htmlspecialchars($filterCpName, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($fCpId): ?>
                            <span class="co-cp-clear" id="cpClearBtn" title="Скинути">&#x2715;</span>
                            <?php endif; ?>
                            <div class="co-cp-dd" id="cpDropdown"></div>
                        </div>
                    </div>
                </div>

                <!-- Col 2 -->
                <div class="co-adv-cell" data-fid="f-sum">
                    <span class="co-flabel">Сума</span>
                    <div class="co-fctrl">
                        <input type="number" name="sum_from" placeholder="від" step="0.01" value="<?= htmlspecialchars($fSumFrom, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                        <span style="color:#9ca3af;flex-shrink:0;">—</span>
                        <input type="number" name="sum_to" placeholder="до" step="0.01" value="<?= htmlspecialchars($fSumTo, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                    </div>
                </div>

                <!-- Col 3 -->
                <div class="co-adv-cell" data-fid="f-payment">
                    <span class="co-flabel">Оплата</span>
                    <div class="co-fctrl">
                        <select name="payment_status" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <option value="paid" <?= $fPayment === 'paid' ? 'selected' : '' ?>>Оплачено</option>
                            <option value="partially_paid" <?= $fPayment === 'partially_paid' ? 'selected' : '' ?>>Частково</option>
                            <option value="not_paid" <?= $fPayment === 'not_paid' ? 'selected' : '' ?>>Не оплачено</option>
                            <option value="overdue" <?= $fPayment === 'overdue' ? 'selected' : '' ?>>Прострочено</option>
                            <option value="refund" <?= $fPayment === 'refund' ? 'selected' : '' ?>>Повернення</option>
                        </select>
                    </div>
                </div>

                <!-- Col 4 -->
                <div class="co-adv-cell" data-fid="f-shipment">
                    <span class="co-flabel">Відвантаж.</span>
                    <div class="co-fctrl">
                        <select name="shipment_status" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <option value="shipped" <?= $fShipment === 'shipped' ? 'selected' : '' ?>>Відвантажено</option>
                            <option value="delivered" <?= $fShipment === 'delivered' ? 'selected' : '' ?>>Доставлено</option>
                            <option value="partially_shipped" <?= $fShipment === 'partially_shipped' ? 'selected' : '' ?>>Частково</option>
                            <option value="reserved" <?= $fShipment === 'reserved' ? 'selected' : '' ?>>Зарезервовано</option>
                            <option value="not_shipped" <?= $fShipment === 'not_shipped' ? 'selected' : '' ?>>Не відвантажено</option>
                            <option value="returned" <?= $fShipment === 'returned' ? 'selected' : '' ?>>Повернено</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 1 -->
                <div class="co-adv-cell" data-fid="f-payment-method">
                    <span class="co-flabel">Спосіб опл.</span>
                    <div class="co-fctrl">
                        <select name="payment_method_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterPaymentMethods as $pm): ?>
                            <option value="<?= (int)$pm['id'] ?>" <?= $fPmId == $pm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pm['name_uk'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 2 -->
                <div class="co-adv-cell" data-fid="f-delivery-method">
                    <span class="co-flabel">Спосіб дост.</span>
                    <div class="co-fctrl">
                        <select name="delivery_method_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterDeliveryMethods as $dmf): ?>
                            <option value="<?= (int)$dmf['id'] ?>" <?= $fDmId == $dmf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dmf['name_uk'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 3 -->
                <div class="co-adv-cell" data-fid="f-ttn">
                    <span class="co-flabel">ТТН</span>
                    <div class="co-fctrl">
                        <select name="has_ttn" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <option value="yes" <?= $fTtn === 'yes' ? 'selected' : '' ?>>Є ТТН</option>
                            <option value="no" <?= $fTtn === 'no' ? 'selected' : '' ?>>Немає ТТН</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 4 -->
                <div class="co-adv-cell" data-fid="f-organization">
                    <span class="co-flabel">Організація</span>
                    <div class="co-fctrl">
                        <select name="organization_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterOrganizations as $org): ?>
                            <option value="<?= (int)$org['id'] ?>" <?= $fOrgId == $org['id'] ? 'selected' : '' ?>><?= htmlspecialchars($org['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 2 -->
                <div class="co-adv-cell" data-fid="f-manager">
                    <span class="co-flabel">Менеджер</span>
                    <div class="co-fctrl">
                        <select name="manager_employee_id" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <?php foreach ($filterManagers as $mgr): ?>
                            <option value="<?= (int)$mgr['id'] ?>" <?= $fMgrId == $mgr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mgr['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 3 -->
                <div class="co-adv-cell" data-fid="f-action">
                    <span class="co-flabel">Дія</span>
                    <div class="co-fctrl">
                        <select name="next_action" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <option value="__none__" <?= $fAction === '__none__' ? 'selected' : '' ?>>Без дій</option>
                            <?php foreach ($filterActions as $act): ?>
                            <option value="<?= htmlspecialchars($act['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $fAction === $act['code'] ? 'selected' : '' ?>><?= htmlspecialchars($act['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2, Col 4 -->
                <div class="co-adv-cell" data-fid="f-unread">
                    <span class="co-flabel">Повідомл.</span>
                    <div class="co-fctrl">
                        <select name="has_unread" onchange="this.form.submit()">
                            <option value="">— Всі —</option>
                            <option value="yes" <?= $fUnread === 'yes' ? 'selected' : '' ?>>Є непрочитані</option>
                            <option value="no" <?= $fUnread === 'no' ? 'selected' : '' ?>>Немає</option>
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

        <div class="co-bulk-bar" id="bulkBar">
            <span>Обрано: <span class="co-bulk-count" id="bulkCount">0</span></span>
            <div class="co-bulk-actions">
                <button type="button" class="co-bulk-btn" id="bulkActBtn">
                    Дії
                    <svg width="10" height="10" viewBox="0 0 16 16" fill="none"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="co-bulk-dd" id="bulkDd">
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
                <th class="co-cb-th"><input type="checkbox" class="co-row-cb" id="cbAll" title="Обрати все"></th>
                <th style="width:16px;padding:0"></th>
                <th>Номер</th>
                <th style="width:90px">Дата</th>
                <th>Контрагент</th>
                <th style="width:100px;text-align:center">Статус</th>
                <th style="text-align:right">Сума</th>
                <th style="width:36px;text-align:center" title="Оплата">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="14" height="10" rx="1.5" stroke="#6b7280" stroke-width="1.3"/><path d="M1 6h14" stroke="#6b7280" stroke-width="1.3"/><rect x="3" y="9" width="4" height="1.5" rx=".5" fill="#6b7280"/></svg>
                </th>
                <th style="width:36px;text-align:center" title="Відвантаження">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M1 3h8v9H1z" stroke="#6b7280" stroke-width="1.3"/><path d="M9 6l4 2v4H9" stroke="#6b7280" stroke-width="1.3"/><circle cx="4" cy="12.5" r="1.5" stroke="#6b7280" stroke-width="1.2"/><circle cx="11" cy="12.5" r="1.5" stroke="#6b7280" stroke-width="1.2"/></svg>
                </th>
                <th style="width:36px;text-align:center" title="ТТН (відправка)">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 2h8l4 4v8H2V2z" stroke="#6b7280" stroke-width="1.3"/><path d="M10 2v4h4" stroke="#6b7280" stroke-width="1.3"/><path d="M5 8h6M5 10.5h4" stroke="#6b7280" stroke-width="1.1" stroke-linecap="round"/></svg>
                </th>
                <th style="width:36px;text-align:center" title="Черга друку">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 2h8v3H4z" stroke="#6b7280" stroke-width="1.3"/><path d="M2 5h12v8H2z" stroke="#6b7280" stroke-width="1.3"/><path d="M6 8h4" stroke="#6b7280" stroke-width="1.3" stroke-linecap="round"/></svg>
                </th>
                <th style="white-space:nowrap">Організація</th>
                <th>Менеджер</th>
                <th style="width:80px;text-align:center">Дія</th>
                <th style="width:36px;text-align:center" title="Непрочитані повідомлення">
                    <span style="position:relative;display:inline-block">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M2 3h12v8H4l-2 2V3z" stroke="#6b7280" stroke-width="1.3"/><path d="M5 7h6M5 9.5h3" stroke="#6b7280" stroke-width="1.2" stroke-linecap="round"/></svg>
                        <?php if ($totalUnread > 0): ?>
                        <span style="position:absolute;top:-8px;right:-10px;background:#ef4444;color:#fff;font-size:9px;font-weight:600;min-width:16px;height:16px;line-height:16px;border-radius:8px;padding:0 4px;text-align:center;"><?= $totalUnread ?></span>
                        <?php endif; ?>
                    </span>
                </th>
                <th style="width:36px"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="15" style="text-align:center;color:#9ca3af;padding:32px 0;">Записів не знайдено</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php
                $st  = isset($row['status'])          ? $row['status']          : '';
                $pay = isset($row['payment_status'])   ? $row['payment_status']  : '';
                $shp = isset($row['shipment_status'])  ? $row['shipment_status'] : '';
                $unread = isset($row['unread_count'])  ? (int)$row['unread_count'] : 0;
                $ttnCnt   = isset($row['ttn_count'])    ? (int)$row['ttn_count']    : 0;
                $ttnNpCnt = isset($row['ttn_np_count']) ? (int)$row['ttn_np_count'] : 0;
                $ttnUpCnt = isset($row['ttn_up_count']) ? (int)$row['ttn_up_count'] : 0;
                $nextAction = isset($row['next_action_label']) && $row['next_action_label'] !== '' ? $row['next_action_label'] : '';
                $cpId = isset($row['counterparty_id']) ? (int)$row['counterparty_id'] : 0;

                // Fully processed: paid (or cash_on_delivery) + shipped/delivered + has TTN (or pickup)
                $dmCode = isset($row['delivery_method_code']) ? $row['delivery_method_code'] : '';
                $pmCode = isset($row['payment_method_code']) ? $row['payment_method_code'] : '';
                $payOk = ($pay === 'paid' || $pay === 'refund' || $pmCode === 'cash_on_delivery');
                $isFullyDone = $payOk
                    && in_array($shp, array('shipped', 'delivered'))
                    && ($ttnCnt > 0 || $dmCode === 'pickup' || $dmCode === 'courier');

                if ($fHideDone === '1' && $isFullyDone) continue;

                $payCls = array('not_paid'=>'co-pay-none','partially_paid'=>'co-pay-partial','paid'=>'co-pay-done','overdue'=>'co-pay-overdue','refund'=>'co-pay-refund');
                $payLbl = array('not_paid'=>'Не оплачено','partially_paid'=>'Частково оплачено','paid'=>'Оплачено','overdue'=>'Прострочено','refund'=>'Повернення');
                $_pc = isset($payCls[$pay]) ? $payCls[$pay] : 'co-pay-none';
                $_pl = isset($payLbl[$pay]) ? $payLbl[$pay] : $pay;

                $pmCode = isset($row['payment_method_code']) ? $row['payment_method_code'] : '';
                $pmSvgs = array(
                    'cash_on_delivery' => array(
                        'title' => 'Накладений платіж',
                        'svg'   => '<svg viewBox="0 0 16 16" fill="none"><path d="M5 2h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12 2c1.1 0 2 .9 2 2v4c0 1.1-.9 2-2 2H5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M7 10.5L4.5 8 7 5.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="13" r="1" fill="currentColor"/></svg>',
                    ),
                    'online' => array(
                        'title' => 'Онлайн оплата',
                        'svg'   => '<svg viewBox="0 0 16 16" fill="none"><rect x="1.5" y="3.5" width="13" height="9" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M1.5 6.5h13" stroke="currentColor" stroke-width="1.3"/><path d="M4 10h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
                    ),
                    'cash' => array(
                        'title' => 'Готівка',
                        'svg'   => '<svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M8 5v6M6 6.5c0-.8.9-1.5 2-1.5s2 .7 2 1.5-.9 1.5-2 1.5-2 .7-2 1.5.9 1.5 2 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>',
                    ),
                    'bank_company' => array(
                        'title' => 'Безготівкова юрособа',
                        'svg'   => '<svg viewBox="0 0 16 16" fill="none"><path d="M2 6l6-3.5L14 6" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M3 6v6M7 6v6M9 6v6M13 6v6" stroke="currentColor" stroke-width="1.3"/><path d="M2 12h12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>',
                    ),
                    'bank_personal' => array(
                        'title' => 'Безготівкова фізособа',
                        'svg'   => '<svg viewBox="0 0 16 16" fill="none"><path d="M2 6l6-3.5L14 6" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M4 6v6M8 6v6M12 6v6" stroke="currentColor" stroke-width="1.3"/><path d="M2 12h12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>',
                    ),
                );
                $pmInfo = isset($pmSvgs[$pmCode]) ? $pmSvgs[$pmCode] : null;
                $payTitle = ($pmInfo ? $pmInfo['title'] : 'Оплата') . ' — ' . $_pl;
                if ($pmInfo) {
                    $payHtml = '<span class="co-ind '.$_pc.'" title="'.htmlspecialchars($payTitle, ENT_QUOTES, 'UTF-8').'">'.$pmInfo['svg'].'</span>';
                } else {
                    $payHtml = '<span class="co-ind '.$_pc.'" title="'.htmlspecialchars($payTitle, ENT_QUOTES, 'UTF-8').'"><svg viewBox="0 0 16 16" fill="none"><path d="M2 4h12v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4z" stroke="currentColor" stroke-width="1.4"/><path d="M2 4l1-2h10l1 2" stroke="currentColor" stroke-width="1.4"/><path d="M6 8h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M8 6v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></span>';
                }

                $shipCls = array('not_shipped'=>'co-ship-none','reserved'=>'co-ship-reserved','partially_shipped'=>'co-ship-partial','shipped'=>'co-ship-done','delivered'=>'co-ship-delivered','returned'=>'co-ship-returned');
                $shipLbl = array('not_shipped'=>'Не відвантажено','reserved'=>'Зарезервовано','partially_shipped'=>'Частково відвантажено','shipped'=>'Відвантажено','delivered'=>'Доставлено','returned'=>'Повернено');
                $_sc = isset($shipCls[$shp]) ? $shipCls[$shp] : 'co-ship-none';
                $_sl = isset($shipLbl[$shp]) ? $shipLbl[$shp] : $shp;
                $shipHtml = '<span class="co-ind '.$_sc.'" title="'.htmlspecialchars($_sl, ENT_QUOTES, 'UTF-8').'"><svg viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M11 6h2.5l2 2.5V11h-4.5V6z" stroke="currentColor" stroke-width="1.3"/><circle cx="4" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="12.5" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.2"/></svg></span>';
            ?>
            <tr style="cursor:pointer" data-order-id="<?= (int)$row['id'] ?>" onclick="window.location='/customerorder/edit?id=<?= (int)$row['id'] ?>'">
                <td style="text-align:center" onclick="event.stopPropagation()">
                    <input type="checkbox" class="co-row-cb co-row-select" value="<?= (int)$row['id'] ?>">
                </td>
                <td style="padding:0;text-align:center"><?php if ($isFullyDone): ?><span class="co-done-mark" title="Оплачено · Відвантажено · Відправлено">✓</span><?php endif; ?></td>
                <td><a class="co-num-link" href="/customerorder/edit?id=<?= (int)$row['id'] ?>" onclick="event.stopPropagation()"><?= htmlspecialchars($row['number'] ?: ('# ' . $row['id']), ENT_QUOTES, 'UTF-8') ?></a></td>
                <td class="nowrap fs-12"><?= $row['moment'] ? substr($row['moment'], 0, 10) : '—' ?></td>
                <td>
                    <?php if ($cpId > 0): ?>
                    <a class="co-cp-link" href="/counterparties/view?id=<?= $cpId ?>" target="_blank" onclick="event.stopPropagation()" title="<?= htmlspecialchars($row['counterparty_name'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($row['counterparty_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php else: ?>
                        <?= htmlspecialchars($row['counterparty_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($st): ?>
                    <span class="badge <?= isset($statusColors[$st]) ? $statusColors[$st] : 'badge-gray' ?>">
                        <?= isset($statusLabels[$st]) ? $statusLabels[$st] : $st ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td style="text-align:right" class="nowrap"><?= number_format((float)$row['sum_total'], 2, '.', ' ') ?></td>
                <td style="text-align:center"><?= $payHtml ?></td>
                <td style="text-align:center"><?= $shipHtml ?></td>
                <td style="text-align:center"><?php
                    // Determine actual carrier from linked TTN documents
                    // TTN НП/УП overrides delivery_method_code (e.g. courier via NP = НП)
                    $dmCode = isset($row['delivery_method_code']) ? $row['delivery_method_code'] : '';
                    $dmLabels = array(
                        'novaposhta' => array('НП', 'Нова Пошта'),
                        'ukrposhta'  => array('УП', 'Укрпошта'),
                        'courier'    => array('КР', 'Кур\'єр'),
                        'pickup'     => array('СВ', 'Самовивіз'),
                    );
                    // TTN overrides delivery_method (courier via NP → НП)
                    if ($ttnNpCnt > 0) {
                        $carrierLabel = 'НП';
                        $carrierTitle = 'Нова Пошта';
                    } elseif ($ttnUpCnt > 0) {
                        $carrierLabel = 'УП';
                        $carrierTitle = 'Укрпошта';
                    } elseif (isset($dmLabels[$dmCode])) {
                        $carrierLabel = $dmLabels[$dmCode][0];
                        $carrierTitle = $dmLabels[$dmCode][1];
                    } else {
                        $carrierLabel = '';
                        $carrierTitle = '';
                    }
                    $ttnTitle = $carrierTitle ?: 'Доставка';
                    if ($ttnCnt > 0) {
                        $ttnTitle .= ' — ТТН: ' . $ttnCnt;
                    }
                    $ttnClass = $ttnCnt > 0 ? 'co-ttn-yes' : 'co-ttn-no';
                    if ($carrierLabel): ?>
                    <span class="co-ind <?= $ttnClass ?>" title="<?= htmlspecialchars($ttnTitle, ENT_QUOTES, 'UTF-8') ?>"><span class="co-dm-label"><?= $carrierLabel ?></span></span>
                    <?php elseif ($ttnCnt > 0): ?>
                    <span class="co-ind co-ttn-yes" title="ТТН: <?= $ttnCnt ?>"><svg viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <?php else: ?>
                    <span class="co-ind co-ttn-no" title="Немає ТТН"><svg viewBox="0 0 16 16" fill="none"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                    <?php endif; ?><?php
                    ?></td>
                <td style="text-align:center"><?php
                    $pqCnt = isset($row['print_queue']) ? (int)$row['print_queue'] : 0;
                    if ($pqCnt > 0): ?>
                    <span class="co-ind co-pq-yes" title="В черзі друку: <?= $pqCnt ?>"><svg viewBox="0 0 16 16" fill="none"><path d="M4 2h8v3H4z" stroke="currentColor" stroke-width="1.3"/><path d="M2 5h12v8H2z" stroke="currentColor" stroke-width="1.3"/><path d="M6 8h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></span>
                    <?php endif; ?></td>
                <td class="fs-12" style="white-space:nowrap"><?= htmlspecialchars($row['organization_short'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="co-manager" title="<?= htmlspecialchars($row['manager_display'] ?: '', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($row['manager_display'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td style="text-align:center">
                    <?php if ($nextAction): ?>
                    <span class="co-next-action" title="<?= htmlspecialchars($nextAction, ENT_QUOTES, 'UTF-8') ?>">
                        <svg width="10" height="10" viewBox="0 0 16 16" fill="none" style="flex-shrink:0"><path d="M3 8h10M10 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?= htmlspecialchars($nextAction, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($unread > 0): ?>
                    <span class="co-unread"><?= $unread ?></span>
                    <?php endif; ?>
                </td>
                <td onclick="event.stopPropagation()" style="text-align:center">
                    <div class="co-act-wrap">
                        <button type="button" class="co-act-btn" data-act-toggle>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="3" r="1.2" fill="currentColor"/><circle cx="8" cy="8" r="1.2" fill="currentColor"/><circle cx="8" cy="13" r="1.2" fill="currentColor"/></svg>
                        </button>
                        <div class="co-act-dd">
                            <button type="button" class="danger" data-delete-order="<?= (int)$row['id'] ?>">
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
            <a href="<?= co_url(array('page' => $page - 1)) ?>">&laquo;</a>
        <?php endif; ?>
        <?php
        $pStart = max(1, $page - 3);
        $pEnd   = min($totalPages, $page + 3);
        for ($p = $pStart; $p <= $pEnd; $p++):
        ?>
            <?php if ($p == $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= co_url(array('page' => $p)) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?= co_url(array('page' => $page + 1)) ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script src="/modules/shared/chip-search.js?v=<?= @filemtime(__DIR__ . '/../../shared/chip-search.js') ?>"></script>
<script>
(function() {
    var FKEY = 'co_reg_filters';

    // ── Restore filters: if page opened with no params but sessionStorage has saved filters → redirect ──
    if (window.location.search === '' || window.location.search === '?page=1') {
        var saved = sessionStorage.getItem(FKEY);
        if (saved) {
            window.location.replace('/customerorder?' + saved);
            return; // stop further execution while redirecting
        }
    }

    // ── Save current filters to sessionStorage (runs on every filtered page load) ──
    (function saveFilters() {
        var params = new URLSearchParams(window.location.search);
        params.delete('page'); // don't persist page number
        var qs = params.toString();
        if (qs) {
            sessionStorage.setItem(FKEY, qs);
        }
        // note: if no params, we don't clear — that's done only by the reset button
    })();

    var form = document.getElementById('coForm');

    // ── Chip search ──
    ChipSearch.init('coChipBox', 'coChipTyper', 'coSearchHidden', form, {noComma: false});
    var clearBtn = document.getElementById('coChipClear');
    var chipBox  = document.getElementById('coChipBox');
    var typer    = document.getElementById('coChipTyper');
    var hidden   = document.getElementById('coSearchHidden');

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

    // ── Date quick buttons ──
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

    document.querySelectorAll('.co-date-qbtn[data-range]').forEach(function(btn) {
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

    // ── Gear button & settings dropdown ──
    var STORAGE_KEY = 'co_filter_vis';
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

    // Gear click: toggle settings dropdown
    gearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        gearDd.classList.toggle('open');
    });

    // Close gear dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!gearDd.contains(e.target) && e.target !== gearBtn) {
            gearDd.classList.remove('open');
        }
    });

    // Gear checkboxes
    gearDd.querySelectorAll('input[data-filter]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            savedVis[cb.dataset.filter] = cb.checked;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(savedVis));
            applyVisibility();
        });
    });

    applyVisibility();


    // ── Counterparty live search ──
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
                            div.className = 'co-cp-dd-item';
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

        // Clear button
        var cpClearBtn = document.getElementById('cpClearBtn');
        if (cpClearBtn) {
            cpClearBtn.addEventListener('click', function() {
                cpHid.value = '';
                cpInput.value = '';
                form.submit();
            });
        }
    }

    // ── Reset all button: clear sessionStorage + go to clean URL ──
    var resetBtn = document.getElementById('resetAllBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sessionStorage.removeItem(FKEY);
            window.location.href = '/customerorder';
        });
    }

    // ── Checkbox selection + Bulk actions ──
    var cbAll      = document.getElementById('cbAll');
    var rowCbs     = document.querySelectorAll('.co-row-select');
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
        // Update "select all" state
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

    // Bulk actions dropdown
    if (bulkActBtn) {
        bulkActBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            bulkDd.classList.toggle('open');
        });
    }

    // ── Delete logic (shared) ──
    function deleteOrders(ids) {
        var word = ids.length === 1 ? 'замовлення' : 'замовлень';
        if (!confirm('Видалити ' + ids.length + ' ' + word + '? Дія незворотня.')) return;

        fetch('/customerorder/api/delete_orders', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ids: ids})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.deleted && data.deleted.length > 0) {
                // Remove deleted rows from DOM
                data.deleted.forEach(function(id) {
                    var row = document.querySelector('tr[data-order-id="' + id + '"]');
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

    // Bulk delete button
    if (bulkDelBtn) {
        bulkDelBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            bulkDd.classList.remove('open');
            deleteOrders(ids);
        });
    }

    // Bulk pack print / queue for orders
    function orderBulkPackAction(ids, queue) {
        // Find demands for selected orders, generate packs
        var fd = new FormData();
        fd.append('order_ids', ids.join(','));
        if (queue) fd.append('queue', '1');
        return fetch('/print/api/generate_pack_for_orders', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); });
    }

    var bulkPackBtn = document.getElementById('bulkPackBtn');
    if (bulkPackBtn) {
        bulkPackBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (!ids.length) return;
            bulkDd.classList.remove('open');
            bulkPackBtn.disabled = true;
            bulkPackBtn.textContent = '⏳ Формую…';
            orderBulkPackAction(ids, false).then(function(d) {
                bulkPackBtn.disabled = false;
                bulkPackBtn.textContent = '📦 Друк пакетів';
                if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                var urls = d.urls || [];
                if (!urls.length) { alert('Немає документів для друку (відвантажень не знайдено)'); return; }
                urls.forEach(function(u) { window.open(u, '_blank'); });
                if (typeof showToast === 'function') showToast('Відкрито ' + urls.length + ' документів');
            }).catch(function() {
                bulkPackBtn.disabled = false;
                bulkPackBtn.textContent = '📦 Друк пакетів';
            });
        });
    }

    var bulkQueueBtn = document.getElementById('bulkQueueBtn');
    if (bulkQueueBtn) {
        bulkQueueBtn.addEventListener('click', function() {
            var ids = getSelectedIds();
            if (!ids.length) return;
            bulkDd.classList.remove('open');
            bulkQueueBtn.disabled = true;
            bulkQueueBtn.textContent = '⏳ Формую…';
            orderBulkPackAction(ids, true).then(function(d) {
                bulkQueueBtn.disabled = false;
                bulkQueueBtn.textContent = '📥 В чергу';
                if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                if (typeof showToast === 'function') showToast('Додано ' + (d.queued || 0) + ' пакетів у чергу');
            }).catch(function() {
                bulkQueueBtn.disabled = false;
                bulkQueueBtn.textContent = '📥 В чергу';
            });
        });
    }

    // ── Context menu (per-row actions) ──
    document.addEventListener('click', function(e) {
        // Toggle context menu
        var toggler = e.target.closest('[data-act-toggle]');
        if (toggler) {
            e.stopPropagation();
            var dd = toggler.nextElementSibling;
            // Close all other menus first
            document.querySelectorAll('.co-act-dd.open').forEach(function(d) {
                if (d !== dd) d.classList.remove('open');
            });
            dd.classList.toggle('open');
            return;
        }

        // Delete button in context menu
        var delBtn = e.target.closest('[data-delete-order]');
        if (delBtn) {
            var orderId = parseInt(delBtn.dataset.deleteOrder);
            delBtn.closest('.co-act-dd').classList.remove('open');
            deleteOrders([orderId]);
            return;
        }

        // Close all context menus and bulk dropdown on outside click
        document.querySelectorAll('.co-act-dd.open').forEach(function(d) { d.classList.remove('open'); });
        bulkDd.classList.remove('open');
    });
}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>