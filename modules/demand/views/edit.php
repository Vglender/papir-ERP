<?php
// Variables from edit.php: $demand, $items, $id, $managerName, $updatedAt, $history, $docTransitions
$isNew = empty($demand);

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
$statusHex = array(
    'new'        => '#9ca3af',
    'assembling' => '#f59e0b',
    'assembled'  => '#3b82f6',
    'shipped'    => '#16a34a',
    'arrived'    => '#15803d',
    'transfer'   => '#0369a1',
    'robot'      => '#8b5cf6',
);
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

function dh($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (!$isNew) {
    $currentStatus  = !empty($demand['status']) ? $demand['status'] : 'new';
    $currentStyle   = isset($_statusInlineStyles[$currentStatus]) ? $_statusInlineStyles[$currentStatus] : $_statusInlineStyles['new'];
    $momentFormatted = !empty($demand['moment']) ? date('d.m.Y H:i', strtotime($demand['moment'])) : '—';
    $syncState  = !empty($demand['sync_state']) ? $demand['sync_state'] : 'new';
    $syncStyle  = isset($syncStateStyles[$syncState]) ? $syncStateStyles[$syncState] : 'background:#f3f4f6; color:#6b7280;';
    $syncLabel  = isset($syncStateLabels[$syncState]) ? $syncStateLabels[$syncState] : $syncState;

    $totalItems = count($items);
    $totalQty   = 0; $totalSum = 0; $totalVat = 0; $totalNet = 0;
    foreach ($items as $it) {
        $qty = (float)$it['quantity']; $sum = (float)$it['sum_row']; $vat = (float)$it['vat_rate'];
        $totalQty += $qty; $totalSum += $sum;
        if ($vat > 0) { $net = $sum / (1 + $vat / 100); $totalVat += $sum - $net; $totalNet += $net; }
        else { $totalNet += $sum; }
    }
    $totalVat = round($totalVat, 2); $totalNet = round($totalNet, 2);
}

// StatusColors for RelDocsGraph
$_scAll = array();
foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
    foreach (StatusColors::all($_dt) as $_s => $_e) {
        $_scAll[$_s] = $_e;
    }
}
?>
<style>
    :root {
        --bg:          #f0f2f5;
        --surface:     #ffffff;
        --border:      #e4e7ec;
        --border-light:#eef0f4;
        --text:        #1a1d23;
        --text-muted:  #6b7280;
        --text-light:  #9ca3af;
        --accent:      #2563eb;
        --accent-bg:   #eff4ff;
        --hover-row:   #f8f9fb;
        --sel-row:     #eff4ff;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body {
        font-family: 'Geist', system-ui, sans-serif;
        font-size: 13px; margin: 0; padding: 14px 16px;
        color: var(--text); background: var(--bg); line-height: 1.45;
    }
    .page-shell { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 6px; }

    /* TOOLBAR */
    .toolbar {
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; flex-wrap: wrap;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 10px; padding: 9px 14px;
    }
    .toolbar-left, .toolbar-right { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
    .btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 12px; border-radius: 7px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text); cursor: pointer;
        font-size: 12.5px; font-family: inherit; font-weight: 500;
        white-space: nowrap; text-decoration: none;
        transition: background .12s, border-color .12s;
    }
    .btn:hover { background: var(--hover-row); border-color: #d0d5de; }
    .btn-save { background: #22c55e; border-color: #16a34a; color: #fff; font-weight: 600; }
    .btn-save:hover { background: #16a34a; }
    .btn-save-dirty { box-shadow: 0 0 0 3px rgba(34,197,94,.35); }
    .check-label {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12.5px; color: var(--text-muted); cursor: pointer;
        padding: 5px 8px; border-radius: 6px; border: 1px solid transparent;
    }
    .check-label:hover { background: var(--hover-row); border-color: var(--border); }
    .check-label input { margin: 0; accent-color: var(--accent); }
    .toolbar-meta { display: flex; align-items: center; gap: 14px; }
    .toolbar-meta-item { font-size: 11.5px; color: var(--text-muted); }
    .toolbar-meta-item strong { color: var(--text); font-weight: 500; }
    .toolbar-meta-item a { color: var(--accent); text-decoration: none; }
    .toolbar-meta-item a:hover { text-decoration: underline; }

    /* DOC HEADER */
    .doc-header {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 10px; padding: 14px 18px 16px;
    }
    .doc-title-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 10px; }
    .doc-number { font-size: 20px; font-weight: 600; letter-spacing: -.3px; color: var(--text); }
    .doc-number span { font-size: 13px; font-weight: 400; color: var(--text-muted); margin-left: 6px; }
    .doc-meta-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

    /* Status dropdown */
    .status-dd { position: relative; display: inline-block; }
    .status-dd-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px 4px 11px; border-radius: 6px; border: none;
        font-size: 11.5px; font-weight: 600; font-family: inherit; cursor: pointer; outline: none;
    }
    .status-dd-btn .dd-caret { font-size: 9px; opacity: .7; }
    .status-dd-menu {
        display: none; position: absolute; top: calc(100% + 4px); left: 0; z-index: 9999;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        box-shadow: 0 6px 20px rgba(0,0,0,.12); min-width: 180px; padding: 4px;
        list-style: none; margin: 0;
    }
    .status-dd-menu.open { display: block; }
    .status-dd-opt {
        display: flex; align-items: center; gap: 8px; padding: 6px 10px;
        border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 500; color: #374151;
    }
    .status-dd-opt:hover { background: #f3f4f6; }
    .status-dd-opt .opt-pill { display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

    .sync-tag {
        display: inline-flex; align-items: center; padding: 3px 9px;
        border-radius: 5px; font-size: 11px; font-weight: 500;
    }

    /* FIELDS AREA */
    .fields-area {
        display: grid; grid-template-columns: 1fr 1fr; gap: 0;
        margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-light);
    }
    /* Only left col remains now */
    .fields-col { padding-right: 24px; }
    .fields-col + .fields-col { padding-right: 0; padding-left: 24px; border-left: 1px solid var(--border-light); }

    /* TOTALS COMMENT */
    .totals-comment {
        flex: 1; min-width: 0; padding-right: 24px; display: flex; flex-direction: column; justify-content: flex-end;
    }
    .totals-comment-label {
        font-size: 10.5px; font-weight: 500; color: var(--text-light);
        text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px;
    }
    .totals-comment textarea {
        display: block; width: 100%; box-sizing: border-box;
        padding: 7px 10px; border: 1px solid var(--border);
        border-radius: 6px; outline: none;
        font-family: inherit; font-size: 12.5px; color: var(--text);
        background: var(--surface); resize: vertical; min-height: 72px;
        line-height: 1.5; transition: border-color .12s;
    }
    .totals-comment textarea:focus { border-color: var(--accent); }
    .f { display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px; }
    .f:last-child { margin-bottom: 0; }
    .f label { font-size: 10.5px; font-weight: 500; color: var(--text-light); text-transform: uppercase; letter-spacing: .4px; }
    .f .f-val { font-size: 13px; color: var(--text); padding: 5px 0; }
    .f .f-val a { color: var(--accent); text-decoration: none; }
    .f .f-val a:hover { text-decoration: underline; }
    .f input, .f select, .f textarea {
        width: 100%; padding: 6px 9px; border: 1px solid var(--border);
        border-radius: 6px; background: var(--surface); font-size: 12.5px;
        font-family: inherit; color: var(--text); outline: none; transition: border-color .12s;
    }
    .f input:focus, .f select:focus, .f textarea:focus { border-color: var(--accent); }
    .f textarea { min-height: 80px; resize: vertical; }
    .f-sum { font-size: 22px; font-weight: 700; color: var(--text); padding: 4px 0; }
    .f-sub { font-size: 12px; color: var(--text-muted); padding: 1px 0; }

    /* POSITIONS PANEL */
    .positions-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
    .tabs-bar { display: flex; align-items: center; border-bottom: 1px solid var(--border); padding: 0 14px; background: #fafbfc; }
    .tab-btn {
        padding: 10px 14px; font-size: 12.5px; font-weight: 500; color: var(--text-muted);
        cursor: pointer; border: none; background: transparent;
        border-bottom: 2px solid transparent; margin-bottom: -1px;
        font-family: inherit; transition: color .12s, border-color .12s; white-space: nowrap;
    }
    .tab-btn:hover { color: var(--text); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 600; }
    .tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
        background: var(--accent-bg); color: var(--accent);
        font-size: 10px; font-weight: 600; margin-left: 5px;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ITEMS TABLE */
    .pos-table { width: 100%; border-collapse: collapse; }
    .pos-table thead th {
        padding: 7px 10px; text-align: left; font-size: 11px; font-weight: 500;
        color: var(--text-light); text-transform: uppercase; letter-spacing: .35px;
        background: #fafbfc; border-bottom: 1px solid var(--border); white-space: nowrap;
    }
    .pos-table tbody tr { border-bottom: 1px solid var(--border-light); transition: background .08s; }
    .pos-table tbody tr:hover { background: var(--hover-row); }
    .pos-table td { padding: 7px 10px; vertical-align: middle; font-size: 12.5px; }
    .pos-table .text-r { text-align: right; }
    .pos-table .text-c { text-align: center; }
    .prod-name-link { color: var(--text); text-decoration: none; font-weight: 500; }
    .prod-name-link:hover { color: var(--accent); }

    /* TOTALS */
    .totals-invoice { display: flex; justify-content: space-between; align-items: flex-end; padding: 12px 16px 14px; border-top: 1px solid var(--border); }
    .totals-inner { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 32px; min-width: 480px; }
    .totals-cell { display: flex; flex-direction: column; align-items: flex-end; padding: 5px 0; }
    .totals-cell-label { font-size: 10.5px; font-weight: 500; color: var(--text-light); text-transform: uppercase; letter-spacing: .35px; margin-bottom: 2px; }
    .totals-cell-value { font-size: 14px; font-weight: 600; color: var(--text); font-family: 'Geist Mono', monospace; }
    .totals-cell.big .totals-cell-label { font-size: 11px; color: var(--text-muted); }
    .totals-cell.big .totals-cell-value { font-size: 22px; font-weight: 700; }
    .totals-divider { grid-column: 1 / -1; border: none; border-top: 1px solid var(--border); margin: 6px 0; }

    /* HISTORY PANEL */
    #historyPanel {
        position: fixed; top: 0; right: -520px; width: 500px; height: 100%;
        background: var(--surface); border-left: 1px solid var(--border);
        box-shadow: -6px 0 24px rgba(0,0,0,.08); transition: right .22s ease;
        z-index: 9999; overflow-y: auto; padding: 20px; font-family: inherit;
    }

    /* RELATED DOCS */
    #reldocs-graph-wrap { overflow: auto; min-height: 120px; padding: 6px 10px 10px; }
    #reldocs-svg { display: block; font-family: 'Geist', system-ui, sans-serif; }

    /* CREATE DOC DROPDOWN */
    .create-doc-wrap { position: relative; display: inline-block; }
    .create-doc-menu {
        display: none; position: absolute; top: calc(100% + 4px); left: 0;
        background: #fff; border: 1px solid var(--border); border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,.12); min-width: 220px; z-index: 200; padding: 4px 0;
    }
    .create-doc-menu.open { display: block; }
    .create-doc-item {
        display: block; width: 100%; text-align: left; background: none; border: none;
        padding: 8px 14px; font-size: 13px; color: var(--text); cursor: pointer;
        font-family: inherit; white-space: nowrap;
    }
    .create-doc-item:hover { background: var(--hover-row); }

    .empty-box { padding: 24px; text-align: center; color: var(--text-light); font-size: 13px; }
    .error-box { background:#fff5f5; border:1px solid #fecaca; color:#991b1b; padding:10px 14px; border-radius:8px; font-size:13px; }

    @media (max-width: 900px) {
        .fields-area { grid-template-columns: 1fr; }
        .fields-col + .fields-col { border-left: none; padding-left: 0; border-top: 1px solid var(--border-light); padding-top: 12px; margin-top: 4px; }
        .totals-inner { min-width: unset; grid-template-columns: 1fr 1fr; }
    }
</style>

<div class="page-shell">

<?php if ($isNew): ?>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="/demand" class="btn">← Закрити</a>
        </div>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:48px;text-align:center;color:var(--text-muted);">
        Відвантаження створюються в МойСклад та синхронізуються через вебхук.
    </div>

<?php else: ?>

    <!-- ══ TOOLBAR ══ -->
    <div class="toolbar">
        <div class="toolbar-left">
            <button type="button" id="btnSave" class="btn btn-save" onclick="saveDemand()">
                Зберегти
            </button>
            <a href="/demand" class="btn">Закрити</a>
            <?php if (!empty($docTransitions)): ?>
            <div class="create-doc-wrap" id="createDocWrap">
                <button type="button" class="btn" id="createDocBtn">Створити ▾</button>
                <div class="create-doc-menu" id="createDocMenu">
                    <?php foreach ($docTransitions as $tr): ?>
                    <button type="button" class="create-doc-item"
                            data-to-type="<?php echo dh($tr['to_type']); ?>"
                            data-link-type="<?php echo dh($tr['link_type']); ?>">
                        <?php echo dh($tr['name_uk']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <button type="button" class="btn" disabled>Створити ▾</button>
            <?php endif; ?>
            <button type="button" class="btn" <?php echo $id ? '' : 'disabled'; ?>
                    onclick="PrintModal.open('demand', <?php echo $id; ?>, 0)">
                Друк ▾
            </button>
            <button type="button" class="btn" disabled title="Функція в розробці">Надіслати ▾</button>
            <?php if (!empty($demand['id_ms'])): ?>
            <a href="https://online.moysklad.ru/app/#demand/edit?id=<?php echo dh($demand['id_ms']); ?>"
               target="_blank" class="btn">Відкрити в МС ↗</a>
            <?php endif; ?>
            <label class="check-label">
                <input type="checkbox" id="applicableCheck" value="1"
                    <?php echo !empty($demand['applicable']) ? 'checked' : ''; ?>>
                Проведено
            </label>
        </div>
        <div class="toolbar-right">
            <div class="toolbar-meta">
                <div class="toolbar-meta-item">
                    <strong>Менеджер:</strong> <?php echo dh($managerName ?: '—'); ?>
                </div>
                <div class="toolbar-meta-item">
                    <strong><a href="#" id="historyToggle">Синхронізація:</a></strong>
                    <span id="syncTagInline" style="<?php echo dh($syncStyle); ?>;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:500;">
                        <?php echo dh($syncLabel); ?>
                    </span>
                </div>
                <div class="toolbar-meta-item">
                    <strong>Оновлено:</strong> <?php echo dh($updatedAt ?: '—'); ?>
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
                    № <?php echo dh($demand['number']); ?>
                <?php else: ?>
                    #<?php echo $id; ?>
                <?php endif; ?>
                <span>від <?php echo dh($momentFormatted); ?></span>
            </div>
        </div>

        <!-- Status + sync row -->
        <div class="doc-meta-row">
            <div class="status-dd" id="statusDd">
                <button type="button" class="status-dd-btn" id="statusDdBtn"
                        style="<?php echo dh($currentStyle); ?>">
                    <span id="statusDdLabel">
                        <?php echo isset($statusLabels[$currentStatus]) ? dh($statusLabels[$currentStatus]) : dh($currentStatus); ?>
                    </span>
                    <span class="dd-caret">▾</span>
                </button>
                <input type="hidden" id="statusHidden" value="<?php echo dh($currentStatus); ?>">
                <ul class="status-dd-menu" id="statusDdMenu">
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <li class="status-dd-opt"
                        data-value="<?php echo dh($sv); ?>"
                        data-style="<?php echo dh(isset($_statusInlineStyles[$sv]) ? $_statusInlineStyles[$sv] : ''); ?>"
                        data-hex="<?php echo dh(isset($statusHex[$sv]) ? $statusHex[$sv] : '#9ca3af'); ?>">
                        <span class="opt-pill"
                              style="background:<?php echo dh(isset($statusHex[$sv]) ? $statusHex[$sv] : '#9ca3af'); ?>"></span>
                        <?php echo dh($sl); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Fields area -->
        <div class="fields-area">

            <!-- LEFT: read-only info from MoySklad -->
            <div class="fields-col">
                <?php if (!empty($demand['counterparty_name'])): ?>
                <div class="f">
                    <label>Контрагент</label>
                    <div class="f-val"><?php echo dh($demand['counterparty_name']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($demand['order_number'])): ?>
                <div class="f">
                    <label>Замовлення</label>
                    <div class="f-val">
                        <a href="/customerorder/edit?id=<?php echo (int)$demand['customerorder_id']; ?>">
                            <?php echo dh($demand['order_number']); ?> ↗
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($demand['org_name'])): ?>
                <div class="f">
                    <label>Організація</label>
                    <div class="f-val"><?php echo dh($demand['org_name']); ?></div>
                </div>
                <?php endif; ?>
                <div class="f" style="margin-top:6px;">
                    <label>Сума</label>
                    <div class="f-sum"><?php echo number_format((float)$demand['sum_total'], 2, '.', ' '); ?> грн</div>
                    <?php if ((float)$demand['sum_vat'] > 0): ?>
                    <div class="f-sub">ПДВ: <?php echo number_format((float)$demand['sum_vat'], 2, '.', ' '); ?> грн</div>
                    <?php endif; ?>
                    <?php if ((float)$demand['sum_paid'] > 0): ?>
                    <div class="f-sub">Оплачено: <?php echo number_format((float)$demand['sum_paid'], 2, '.', ' '); ?> грн</div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /fields-area -->
    </div><!-- /doc-header -->

    <!-- ══ POSITIONS PANEL ══ -->
    <div class="positions-panel">
        <div class="tabs-bar">
            <button class="tab-btn active" data-tab="positions">
                Позиції
                <?php if ($totalItems > 0): ?><span class="tab-badge"><?php echo $totalItems; ?></span><?php endif; ?>
            </button>
            <button class="tab-btn" data-tab="related">Пов'язані документи</button>
            <button class="tab-btn" data-tab="files">Файли</button>
            <button class="tab-btn" data-tab="tasks">Задачі</button>
            <button class="tab-btn" data-tab="events">Події</button>
        </div>

        <!-- TAB: Позиції -->
        <div class="tab-content active" id="tab-positions">
            <table class="pos-table">
                <thead>
                    <tr>
                        <th style="width:36px;" class="text-c">#</th>
                        <th>Найменування</th>
                        <th style="width:80px;">Артикул</th>
                        <th style="width:80px;" class="text-r">К-сть</th>
                        <th style="width:100px;" class="text-r">Ціна</th>
                        <th style="width:70px;" class="text-c">ПДВ</th>
                        <th style="width:70px;" class="text-r">Знижка</th>
                        <th style="width:110px;" class="text-r">Сума</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8" class="empty-box">Позиції відсутні</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-c" style="color:var(--text-light);"><?php echo (int)$item['line_no']; ?></td>
                        <td>
                            <?php $nm = !empty($item['name']) ? $item['name'] : $item['product_name']; ?>
                            <?php if (!empty($item['product_id'])): ?>
                            <a href="/catalog?search=<?php echo (int)$item['product_id']; ?>"
                               target="_blank" class="prod-name-link"><?php echo dh($nm ?: '—'); ?></a>
                            <?php else: ?>
                            <?php echo dh($nm ?: '—'); ?>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);font-size:11.5px;">
                            <?php echo dh(!empty($item['article']) ? $item['article'] : ($item['sku'] ?: '—')); ?>
                        </td>
                        <td class="text-r">
                            <?php $qty = (float)$item['quantity'];
                            echo rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'); ?>
                        </td>
                        <td class="text-r"><?php echo number_format((float)$item['price'], 2, '.', ' '); ?></td>
                        <td class="text-c" style="color:var(--text-muted);font-size:12px;">
                            <?php echo (float)$item['vat_rate'] > 0 ? (int)$item['vat_rate'] . '%' : 'Без ПДВ'; ?>
                        </td>
                        <td class="text-r">
                            <?php echo (float)$item['discount_percent'] > 0
                                ? number_format((float)$item['discount_percent'], 1) . '%' : '—'; ?>
                        </td>
                        <td class="text-r" style="font-weight:500;">
                            <?php echo number_format((float)$item['sum_row'], 2, '.', ' '); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($items)): ?>
            <div class="totals-invoice">
                <div class="totals-comment">
                    <div class="totals-comment-label">Коментар</div>
                    <textarea id="descriptionField" placeholder="Коментар до відвантаження…"><?php echo dh($demand['description'] ?: ''); ?></textarea>
                    <?php if (!empty($demand['sync_error'])): ?>
                    <div style="margin-top:4px;color:#b91c1c;font-size:12px;"><?php echo dh($demand['sync_error']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="totals-inner">
                    <div class="totals-cell">
                        <div class="totals-cell-label">Позицій</div>
                        <div class="totals-cell-value"><?php echo $totalItems; ?></div>
                    </div>
                    <div class="totals-cell">
                        <div class="totals-cell-label">К-сть товару</div>
                        <div class="totals-cell-value"><?php echo rtrim(rtrim(number_format($totalQty, 3, '.', ' '), '0'), '.'); ?></div>
                    </div>
                    <div class="totals-cell">
                        <div class="totals-cell-label">Сума без ПДВ</div>
                        <div class="totals-cell-value"><?php echo number_format($totalNet, 2, '.', ' '); ?></div>
                    </div>
                    <hr class="totals-divider">
                    <div class="totals-cell">
                        <div class="totals-cell-label">ПДВ</div>
                        <div class="totals-cell-value"><?php echo number_format($totalVat, 2, '.', ' '); ?></div>
                    </div>
                    <div class="totals-cell">&nbsp;</div>
                    <div class="totals-cell big">
                        <div class="totals-cell-label">Разом до сплати</div>
                        <div class="totals-cell-value"><?php echo number_format($totalSum, 2, '.', ' '); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
    <div id="historyPanel">
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
                        <?php echo dh(!empty($ev['created_at']) ? date('d.m.Y H:i', strtotime($ev['created_at'])) : '—'); ?>
                    </td>
                    <td style="padding:7px 8px;"><?php echo dh($ev['event_label'] ?: $ev['event_type']); ?></td>
                    <td style="padding:7px 8px;"><?php echo dh($ev['employee_name'] ?: '—'); ?></td>
                    <td style="padding:7px 8px;"><?php echo dh($ev['comment'] ?: ''); ?></td>
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
var DEMAND_ID = <?php echo $id; ?>;
var _statusLabels = <?php echo json_encode($statusLabels); ?>;
var _statusStyles  = <?php echo json_encode($_statusInlineStyles); ?>;
var _syncStyles    = <?php echo json_encode($syncStateStyles); ?>;
var _syncLabels    = <?php echo json_encode($syncStateLabels); ?>;

/* ── Status dropdown ── */
(function() {
    var btn    = document.getElementById('statusDdBtn');
    var menu   = document.getElementById('statusDdMenu');
    var hidden = document.getElementById('statusHidden');
    var label  = document.getElementById('statusDdLabel');
    if (!btn) return;
    btn.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('open'); });
    document.addEventListener('click', function() { menu.classList.remove('open'); });
    menu.querySelectorAll('.status-dd-opt').forEach(function(opt) {
        opt.addEventListener('click', function() {
            hidden.value = opt.dataset.value;
            btn.style.cssText = opt.dataset.style;
            label.textContent = _statusLabels[opt.dataset.value] || opt.dataset.value;
            menu.classList.remove('open');
            markDirty();
        });
    });
}());

/* ── Dirty flag ── */
function markDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.add('btn-save-dirty');
}
function clearDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.remove('btn-save-dirty');
}

document.getElementById('descriptionField').addEventListener('input', markDirty);
document.getElementById('applicableCheck').addEventListener('change', markDirty);

/* ── Save ── */
function saveDemand() {
    var btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.textContent = 'Збереження…';

    var params = new URLSearchParams();
    params.append('id',          DEMAND_ID);
    params.append('status',      document.getElementById('statusHidden').value);
    params.append('applicable',  document.getElementById('applicableCheck').checked ? '1' : '0');
    params.append('description', document.getElementById('descriptionField').value);

    fetch('/demand/api/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        btn.textContent = 'Зберегти';
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
        clearDirty();
        var d = res.demand || {};
        // Update sync badge in toolbar
        var ss = d.sync_state || 'synced';
        var tag = document.getElementById('syncTagInline');
        if (tag) { tag.style.cssText = _syncStyles[ss] || ''; tag.textContent = _syncLabels[ss] || ss; }
        showToast('Збережено та синхронізовано з МС ✓');
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Зберегти';
        showToast('Помилка мережі', true);
    });
}

/* ── Create doc dropdown ── */
(function() {
    var wrap    = document.getElementById('createDocWrap');
    var btn     = document.getElementById('createDocBtn');
    var menu    = document.getElementById('createDocMenu');
    if (!wrap || !btn || !menu) return;

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('open');
    });
    document.addEventListener('click', function() { menu.classList.remove('open'); });

    menu.querySelectorAll('.create-doc-item').forEach(function(item) {
        item.addEventListener('click', function() {
            menu.classList.remove('open');
            var toType   = item.dataset.toType;
            var linkType = item.dataset.linkType || '';
            if (toType === 'return_logistics') {
                openReturnLogisticsModal(linkType);
            } else {
                createDocument(toType, linkType, null);
            }
        });
    });
}());

function createDocument(toType, linkType, extraParams) {
    var btn = document.getElementById('createDocBtn');
    if (btn) btn.disabled = true;

    var params = new URLSearchParams();
    params.append('demand_id', DEMAND_ID);
    params.append('to_type',   toType);
    params.append('link_type', linkType || '');
    if (extraParams) {
        Object.keys(extraParams).forEach(function(k) { params.append(k, extraParams[k]); });
    }

    fetch('/demand/api/create_document', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (btn) btn.disabled = false;
        if (!res.ok) {
            showToast('Помилка: ' + (res.error || ''), true);
            return;
        }
        showToast(res.msg || 'Документ створено ✓');
        // Reload related docs graph if tab is active, otherwise flag as stale
        _relDocsLoaded = false;
        var activeTab = document.querySelector('.tab-btn.active');
        if (activeTab && activeTab.dataset.tab === 'related') {
            RelDocsGraph.load(DEMAND_ID);
        }
        // Switch to related docs tab to show the result
        var relBtn = document.querySelector('[data-tab="related"]');
        if (relBtn) relBtn.click();
    })
    .catch(function() {
        if (btn) btn.disabled = false;
        showToast('Помилка мережі', true);
    });
}

/* ── Return Logistics Modal ── */
function openReturnLogisticsModal(linkType) {
    var modal   = document.getElementById('returnLogisticsModal');
    var selType = document.getElementById('rlReturnType');
    var ttnWrap = document.getElementById('rlTtnWrap');
    var errEl   = document.getElementById('rlError');
    if (!modal) return;

    errEl.style.display = 'none';
    modal.style.display = 'flex';

    // Show/hide TTN field based on return type
    function toggleTtn() {
        var v = selType.value;
        ttnWrap.style.display = (v === 'novaposhta_ttn' || v === 'ukrposhta_ttn') ? '' : 'none';
    }
    selType.removeEventListener('change', toggleTtn);
    selType.addEventListener('change', toggleTtn);
    toggleTtn();

    document.getElementById('rlConfirmBtn').onclick = function() {
        errEl.style.display = 'none';
        var returnType  = selType.value;
        var ttnNumber   = document.getElementById('rlTtnNumber').value.trim();
        var description = document.getElementById('rlDescription').value.trim();

        if ((returnType === 'novaposhta_ttn' || returnType === 'ukrposhta_ttn') && ttnNumber === '') {
            errEl.textContent = 'Введіть номер ТТН';
            errEl.style.display = 'block';
            return;
        }

        modal.style.display = 'none';
        createDocument('return_logistics', linkType, {
            return_type:  returnType,
            ttn_number:   ttnNumber,
            description:  description,
        });
    };

    document.getElementById('rlCancelBtn').onclick =
    document.getElementById('rlModalClose').onclick = function() {
        modal.style.display = 'none';
    };
}

/* ── Tabs ── */
var _relDocsLoaded = false;
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        btn.classList.add('active');
        var tab = document.getElementById('tab-' + btn.dataset.tab);
        if (tab) tab.classList.add('active');
        if (btn.dataset.tab === 'related' && !_relDocsLoaded) {
            _relDocsLoaded = true;
            RelDocsGraph.load(DEMAND_ID);
        }
    });
});

/* ── History panel ── */
(function() {
    var panel   = document.getElementById('historyPanel');
    var overlay = document.getElementById('historyOverlay');
    var toggle  = document.getElementById('historyToggle');
    var close   = document.getElementById('historyClose');
    if (!panel) return;
    function open() {
        panel.style.right = '0';
        overlay.style.display = 'block';
    }
    function closePanel() {
        panel.style.right = '-520px';
        overlay.style.display = 'none';
    }
    if (toggle) toggle.addEventListener('click', function(e) { e.preventDefault(); open(); });
    if (close)  close.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);
}());

/* ══ RELATED DOCS GRAPH ══ */
var RelDocsGraph = (function() {
    var _currentDemandId = 0;

    var NW = 190, NH = 96, STATUS_H = 22;
    var COL_W = 240, ROW_H = 114, PAD_X = 20, PAD_Y = 30;

    var TYPE_NAME = {
        customerorder: 'Замовлення покупця',
        demand:        'Відвантаження',
        ttn_np:        'ТТН Нова Пошта',
        cashin:        'Касовий ордер',
        paymentin:     'Вхідний платіж',
        salesreturn:   'Повернення покупця',
        overflow:      '…',
    };

    var STATUS_COLOR = (function() {
        var m = {};
        <?php foreach ($_scAll as $_s => $_e): ?>
        m[<?php echo json_encode($_s); ?>] = <?php echo json_encode($_e[2]); ?>;
        <?php endforeach; ?>
        return m;
    }());

    var STATUS_LABEL_MAP = (function() {
        var m = {};
        <?php foreach ($_scAll as $_s => $_e): ?>
        m[<?php echo json_encode($_s); ?>] = <?php echo json_encode($_e[0]); ?>;
        <?php endforeach; ?>
        return m;
    }());

    var NS = 'http://www.w3.org/2000/svg';
    function svgEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        if (attrs) Object.keys(attrs).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    function assignPositions(nodes) {
        var cols = {};
        nodes.forEach(function(n) {
            var c = n.col || 0;
            if (!cols[c]) cols[c] = [];
            cols[c].push(n);
        });
        var maxRows = 0;
        Object.keys(cols).forEach(function(c) { if (cols[c].length > maxRows) maxRows = cols[c].length; });
        var svgH = Math.max(maxRows * ROW_H + PAD_Y * 2, NH + PAD_Y * 2);
        Object.keys(cols).forEach(function(c) {
            var colNodes = cols[c];
            var colH = colNodes.length * ROW_H - (ROW_H - NH);
            var startY = Math.round((svgH - colH) / 2);
            colNodes.forEach(function(n, i) {
                n._x = PAD_X + parseInt(c, 10) * COL_W;
                n._y = startY + i * ROW_H;
            });
        });
        var maxCol = 0;
        nodes.forEach(function(n) { if ((n.col || 0) > maxCol) maxCol = n.col || 0; });
        var svgW = PAD_X * 2 + (maxCol + 1) * COL_W - (COL_W - NW);
        return { w: svgW, h: svgH };
    }

    function trunc(s, max) { s = String(s || ''); return s.length > max ? s.slice(0, max - 1) + '…' : s; }
    function fmtMoment(m) {
        if (!m) return '';
        var p = String(m).split(' ')[0].split('-');
        if (p.length < 3) return m;
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    function render(data) {
        var svg  = document.getElementById('reldocs-svg');
        var wrap = document.getElementById('reldocs-graph-wrap');
        while (svg.firstChild) svg.removeChild(svg.firstChild);
        if (!data.nodes || data.nodes.length === 0) {
            document.getElementById('reldocs-empty').style.display = 'block';
            wrap.style.display = 'none';
            return;
        }
        document.getElementById('reldocs-empty').style.display = 'none';
        wrap.style.display = '';

        var nodeMap = {};
        data.nodes.forEach(function(n) { nodeMap[n.id] = n; });
        var dim = assignPositions(data.nodes);
        svg.setAttribute('width', dim.w);
        svg.setAttribute('height', dim.h);
        svg.setAttribute('viewBox', '0 0 ' + dim.w + ' ' + dim.h);
        svg.style.display = 'block';

        var defs = svgEl('defs');
        data.nodes.forEach(function(node) {
            var cp = svgEl('clipPath', { id: 'clip-' + node.id });
            var cr = svgEl('rect', { x: node._x, y: node._y, width: NW, height: NH, rx: '8', ry: '8' });
            cp.appendChild(cr); defs.appendChild(cp);
        });
        svg.appendChild(defs);

        var edgeGroup = svgEl('g', { 'class': 'edges' });
        svg.appendChild(edgeGroup);
        var edgeEls = {};

        data.edges.forEach(function(edge, idx) {
            var src = nodeMap[edge.from], tgt = nodeMap[edge.to];
            if (!src || !tgt) return;
            var x1, y1, x2, y2, d;
            if (src._x < tgt._x) {
                x1 = src._x + NW; y1 = src._y + Math.round(NH / 2);
                x2 = tgt._x;      y2 = tgt._y + Math.round(NH / 2);
                var mx = Math.round((x1 + x2) / 2);
                d = 'M' + x1 + ',' + y1 + ' H' + mx + ' V' + y2 + ' H' + x2;
            } else {
                x1 = src._x + Math.round(NW / 2); y1 = src._y + NH;
                x2 = tgt._x + Math.round(NW / 2); y2 = tgt._y + NH;
                var my = Math.max(y1, y2) + 18;
                d = 'M' + x1 + ',' + y1 + ' V' + my + ' H' + x2 + ' V' + y2;
            }
            var path = svgEl('path', {
                d: d, fill: 'none', stroke: '#c5ccd6',
                'stroke-width': '1.5', 'stroke-dasharray': '5,3',
                'class': 'edge-path', 'data-edge': idx,
            });
            edgeGroup.appendChild(path);
            edgeEls[idx] = path;
        });

        var nodeGroup = svgEl('g', { 'class': 'nodes' });
        svg.appendChild(nodeGroup);

        data.nodes.forEach(function(node) {
            var x = node._x, y = node._y;
            var isCurrent  = node.current === true;
            var isOverflow = node.type === 'overflow';
            var statusStr  = String(node.status || '');
            var statusCol  = STATUS_COLOR[statusStr] || '#9ca3af';
            var statusLbl  = STATUS_LABEL_MAP[statusStr] || statusStr;
            var bgFill    = isCurrent ? '#1a1d23' : '#ffffff';
            var textMain  = isCurrent ? '#ffffff' : '#1a1d23';
            var textMuted = isCurrent ? '#9ca3af' : '#6b7280';
            var borderCol = isCurrent ? '#1a1d23' : '#e2e7ef';

            var g = svgEl('g', { 'data-node': node.id, style: 'cursor:' + (node.url ? 'pointer' : 'default') });
            var rect = svgEl('rect', { x: x, y: y, width: NW, height: NH, rx: '8', ry: '8', fill: bgFill, stroke: borderCol, 'stroke-width': '1' });
            g.appendChild(rect);

            if (isOverflow) {
                var ovt = svgEl('text', { x: x + NW / 2, y: y + NH / 2, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': '16', fill: '#9ca3af', 'font-family': 'inherit' });
                ovt.textContent = '…'; g.appendChild(ovt);
            } else {
                var typeName = trunc(TYPE_NAME[node.type] || node.type, 22);
                var tnEl = svgEl('text', { x: x + 10, y: y + 16, 'font-size': '10', 'font-weight': '600', fill: textMuted, 'font-family': 'inherit' });
                tnEl.textContent = typeName; g.appendChild(tnEl);

                var doneStatuses = { shipped:1, arrived:1, delivered:1, completed:1, paid:1 };
                if (doneStatuses[statusStr]) {
                    var ck = svgEl('text', { x: x + NW - 10, y: y + 16, 'text-anchor': 'end', 'font-size': '11', fill: statusCol, 'font-family': 'inherit' });
                    ck.textContent = '✓'; g.appendChild(ck);
                }

                var div = svgEl('line', { x1: x + 10, y1: y + 22, x2: x + NW - 10, y2: y + 22, stroke: isCurrent ? '#3a3f4a' : '#eaecf0', 'stroke-width': '1' });
                g.appendChild(div);

                var numStr = node.number ? '№' + trunc(node.number, 14) : '—';
                var numEl = svgEl('text', { x: x + 10, y: y + 36, 'font-size': '11', 'font-weight': '700', fill: textMain, 'font-family': 'inherit' });
                numEl.textContent = numStr; g.appendChild(numEl);

                var dateStr = fmtMoment(node.moment);
                if (dateStr) {
                    var dateEl = svgEl('text', { x: x + NW - 10, y: y + 36, 'text-anchor': 'end', 'font-size': '10', fill: textMuted, 'font-family': 'inherit' });
                    dateEl.textContent = dateStr; g.appendChild(dateEl);
                }

                if (node.amount) {
                    var amtEl = svgEl('text', { x: x + 10, y: y + NH - STATUS_H - 8, 'font-size': '12', 'font-weight': '700', fill: textMain, 'font-family': 'inherit' });
                    amtEl.textContent = trunc(node.amount, 18); g.appendChild(amtEl);
                }

                var barY = y + NH - STATUS_H;
                var barGroup = svgEl('g', { 'clip-path': 'url(#clip-' + node.id + ')' });
                var bar = svgEl('rect', { x: x, y: barY, width: NW, height: STATUS_H, fill: statusCol, opacity: isCurrent ? '0.85' : '1' });
                barGroup.appendChild(bar);
                if (statusLbl) {
                    var barTxt = svgEl('text', { x: x + NW / 2, y: barY + STATUS_H / 2 + 1, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': '9.5', 'font-weight': '600', fill: '#ffffff', 'font-family': 'inherit' });
                    barTxt.textContent = trunc(statusLbl, 24); barGroup.appendChild(barTxt);
                }
                g.appendChild(barGroup);
            }

            g.addEventListener('mouseenter', function() {
                if (!isCurrent) rect.setAttribute('fill', '#f5f7ff');
                rect.setAttribute('stroke-width', '2');
                data.edges.forEach(function(edge, idx) {
                    if (edge.from === node.id || edge.to === node.id) {
                        if (edgeEls[idx]) { edgeEls[idx].setAttribute('stroke', '#6b7280'); edgeEls[idx].setAttribute('stroke-width', '2'); }
                    }
                });
            });
            g.addEventListener('mouseleave', function() {
                rect.setAttribute('fill', bgFill);
                rect.setAttribute('stroke-width', '1');
                Object.keys(edgeEls).forEach(function(k) { edgeEls[k].setAttribute('stroke', '#c5ccd6'); edgeEls[k].setAttribute('stroke-width', '1.5'); });
            });
            if (node.url) {
                g.addEventListener('click', function(e) { e.stopPropagation(); window.location.href = node.url; });
            }
            nodeGroup.appendChild(g);
        });
    }

    function load(demandId) {
        if (!demandId) return;
        _currentDemandId = demandId;
        var loading = document.getElementById('reldocs-loading');
        var wrap    = document.getElementById('reldocs-graph-wrap');
        var empty   = document.getElementById('reldocs-empty');
        loading.style.display = 'block';
        wrap.style.display    = 'none';
        empty.style.display   = 'none';

        fetch('/demand/api/get_linked_docs?demand_id=' + demandId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loading.style.display = 'none';
                if (!data.ok) {
                    empty.style.display = 'block';
                    empty.textContent = 'Помилка: ' + (data.error || '');
                    return;
                }
                wrap.style.display = 'block';
                render(data);
            })
            .catch(function() {
                loading.style.display = 'none';
                empty.style.display   = 'block';
                empty.textContent     = 'Помилка завантаження';
            });
    }

    return { load: load };
}());
</script>
<?php endif; ?>