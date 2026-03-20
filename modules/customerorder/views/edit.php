<?php
if (!isset($result)) {
    $result = array(
        'ok' => true,
        'order' => array(),
        'items' => array(),
        'attributes' => array(),
        'history' => array(),
    );
}

if (!isset($organizations)) $organizations = array();
if (!isset($stores)) $stores = array();
if (!isset($employees)) $employees = array();
if (!isset($counterparties)) $counterparties = array();
if (!isset($contactPersons)) $contactPersons = array();
if (!isset($currencies)) {
    $currencies = array(
        array('code' => 'UAH', 'name' => 'Гривня'),
        array('code' => 'EUR', 'name' => 'Євро'),
        array('code' => 'USD', 'name' => 'Долар'),
    );
}
if (!isset($salesChannels)) $salesChannels = array();
if (!isset($contracts)) $contracts = array();

$order = !empty($result['order']) ? $result['order'] : array();
$items = !empty($result['items']) ? $result['items'] : array();
$attributes = !empty($result['attributes']) ? $result['attributes'] : array();
$history = !empty($result['history']) ? $result['history'] : array();

$isNew = empty($order['id']);

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function field_value($array, $key, $default = '')
{
    return isset($array[$key]) ? $array[$key] : $default;
}

function selected($value, $current)
{
    return (string)$value === (string)$current ? 'selected' : '';
}

function checked_attr($value)
{
    return !empty($value) ? 'checked' : '';
}

function status_meta($type, $status)
{
    $map = array(
        'order' => array(
            'draft' => array('label' => 'Чернетка', 'class' => 'status-gray'),
            'new' => array('label' => 'Нове', 'class' => 'status-blue'),
            'confirmed' => array('label' => 'Підтверджено', 'class' => 'status-cyan'),
            'in_progress' => array('label' => 'В роботі', 'class' => 'status-orange'),
            'waiting_payment' => array('label' => 'Очікує оплату', 'class' => 'status-yellow'),
            'paid' => array('label' => 'Оплачено', 'class' => 'status-green'),
            'partially_shipped' => array('label' => 'Частково відвантажено', 'class' => 'status-cyan'),
            'shipped' => array('label' => 'Відвантажено', 'class' => 'status-green'),
            'completed' => array('label' => 'Завершено', 'class' => 'status-green'),
            'cancelled' => array('label' => 'Скасовано', 'class' => 'status-red'),
        ),
        'payment' => array(
            'not_paid' => array('label' => 'Не оплачено', 'class' => 'status-gray'),
            'partially_paid' => array('label' => 'Частково оплачено', 'class' => 'status-yellow'),
            'paid' => array('label' => 'Оплачено', 'class' => 'status-green'),
            'overdue' => array('label' => 'Прострочено', 'class' => 'status-red'),
            'refund' => array('label' => 'Повернення', 'class' => 'status-dark'),
        ),
        'shipment' => array(
            'not_shipped' => array('label' => 'Не відвантажено', 'class' => 'status-gray'),
            'reserved' => array('label' => 'Зарезервовано', 'class' => 'status-yellow'),
            'partially_shipped' => array('label' => 'Частково відвантажено', 'class' => 'status-cyan'),
            'shipped' => array('label' => 'Відвантажено', 'class' => 'status-green'),
            'delivered' => array('label' => 'Доставлено', 'class' => 'status-green'),
            'returned' => array('label' => 'Повернено', 'class' => 'status-red'),
        ),
    );

    if (isset($map[$type][$status])) {
        return $map[$type][$status];
    }

    return array(
        'label' => $status,
        'class' => 'status-gray',
    );
}

$orderStatus = status_meta('order', field_value($order, 'status', 'draft'));
$paymentStatus = status_meta('payment', field_value($order, 'payment_status', 'not_paid'));
$shipmentStatus = status_meta('shipment', field_value($order, 'shipment_status', 'not_shipped'));

$momentValue = !empty($order['moment']) ? date('Y-m-d\TH:i', strtotime($order['moment'])) : date('Y-m-d\TH:i');
$plannedShipDate = !empty($order['planned_shipment_at']) ? date('Y-m-d\TH:i', strtotime($order['planned_shipment_at'])) : '';

$managerName = field_value($order, 'manager_name');
$updatedByName = field_value($order, 'updated_by_name');
$updatedAt = field_value($order, 'updated_at');


?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Замовлення <?= $isNew ? '' : '#' . (int)$order['id'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:        #f0f2f5;
            --surface:   #ffffff;
            --border:    #e4e7ec;
            --border-light: #eef0f4;
            --text:      #1a1d23;
            --text-muted: #6b7280;
            --text-light: #9ca3af;
            --accent:    #2563eb;
            --accent-bg: #eff4ff;
            --hover-row: #f8f9fb;
            --sel-row:   #eff4ff;

            /* status colors */
            --s-draft:    #f3f4f6; --s-draft-t:    #6b7280;
            --s-new:      #dbeafe; --s-new-t:      #1d4ed8;
            --s-confirm:  #d1fae5; --s-confirm-t:  #065f46;
            --s-progress: #fef3c7; --s-progress-t: #92400e;
            --s-wpay:     #ede9fe; --s-wpay-t:     #5b21b6;
            --s-paid:     #dcfce7; --s-paid-t:     #15803d;
            --s-pship:    #ffedd5; --s-pship-t:    #c2410c;
            --s-ship:     #cffafe; --s-ship-t:     #0e7490;
            --s-done:     #d1fae5; --s-done-t:     #065f46;
            --s-cancel:   #fee2e2; --s-cancel-t:   #b91c1c;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Geist', system-ui, sans-serif;
            font-size: 13px;
            margin: 0;
            padding: 14px 16px;
            color: var(--text);
            background: var(--bg);
            line-height: 1.45;
        }

        .page-shell {
            max-width: 1560px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        /* ─── TOOLBAR ─── */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 9px 14px;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            cursor: pointer;
            font-size: 12.5px;
            font-family: inherit;
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
            transition: background .12s, border-color .12s;
        }
        .btn:hover { background: var(--hover-row); border-color: #d0d5de; }

        .btn-save {
            background: #22c55e;
            border-color: #16a34a;
            color: #fff;
            font-weight: 600;
        }
        .btn-save:hover { background: #16a34a; }

        .toolbar-meta {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .toolbar-meta-item {
            font-size: 11.5px;
            color: var(--text-muted);
        }
        .toolbar-meta-item strong { color: var(--text); font-weight: 500; }
        .toolbar-meta-item a { color: var(--accent); text-decoration: none; }
        .toolbar-meta-item a:hover { text-decoration: underline; }

        .check-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 6px;
            border: 1px solid transparent;
        }
        .check-label:hover { background: var(--hover-row); border-color: var(--border); }
        .check-label input { margin: 0; accent-color: var(--accent); }

        /* ─── DOC HEADER BLOCK ─── */
        .doc-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 18px 16px;
        }

        .doc-title-row {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .doc-number {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -.3px;
            color: var(--text);
        }
        .doc-number span {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 6px;
        }

        /* Status pill */
        .status-tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 11px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: .2px;
            white-space: nowrap;
        }
        .status-draft    { background: var(--s-draft);   color: var(--s-draft-t); }
        .status-new      { background: var(--s-new);     color: var(--s-new-t); }
        .status-confirmed{ background: var(--s-confirm); color: var(--s-confirm-t); }
        .status-in_progress { background: var(--s-progress); color: var(--s-progress-t); }
        .status-waiting_payment { background: var(--s-wpay); color: var(--s-wpay-t); }
        .status-paid     { background: var(--s-paid);   color: var(--s-paid-t); }
        .status-partially_shipped { background: var(--s-pship); color: var(--s-pship-t); }
        .status-shipped  { background: var(--s-ship);   color: var(--s-ship-t); }
        .status-completed{ background: var(--s-done);   color: var(--s-done-t); }
        .status-cancelled{ background: var(--s-cancel); color: var(--s-cancel-t); }

        /* legacy pill aliases */
        .status-gray { background: var(--s-draft); color: var(--s-draft-t); }
        .status-blue { background: var(--s-new); color: var(--s-new-t); }
        .status-cyan { background: var(--s-ship); color: var(--s-ship-t); }
        .status-orange { background: var(--s-pship); color: var(--s-pship-t); }
        .status-yellow { background: var(--s-progress); color: var(--s-progress-t); }
        .status-green { background: var(--s-paid); color: var(--s-paid-t); }
        .status-red { background: var(--s-cancel); color: var(--s-cancel-t); }

        /* header meta row: status select + planned date */
        .doc-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* status select — styled as colored tag */
        .status-select-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .status-select-wrap select {
            appearance: none;
            -webkit-appearance: none;
            padding: 4px 28px 4px 11px;
            border-radius: 6px;
            border: none;
            font-size: 11.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            outline: none;
        }
        .status-select-wrap::after {
            content: '▾';
            position: absolute;
            right: 9px;
            font-size: 10px;
            pointer-events: none;
        }

        .planned-date-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .planned-date-wrap input[type="datetime-local"] {
            border: none;
            background: transparent;
            font-size: 12px;
            font-family: inherit;
            color: var(--text);
            outline: none;
            padding: 2px 0;
            cursor: pointer;
        }
        .planned-date-wrap input:hover { color: var(--accent); }

        .pay-status-tag { margin-left: 4px; }

        /* ─── FIELDS AREA ─── */
        .fields-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-light);
        }

        .fields-col {
            padding-right: 24px;
        }
        .fields-col + .fields-col {
            padding-right: 0;
            padding-left: 24px;
            border-left: 1px solid var(--border-light);
        }

        .fields-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 12px;
        }

        .fields-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
        }

        .fields-grid-1 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .f {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .f label {
            font-size: 10.5px;
            font-weight: 500;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .f input,
        .f select,
        .f textarea {
            width: 100%;
            padding: 6px 9px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text);
            transition: border-color .12s;
            outline: none;
        }
        .f input:focus,
        .f select:focus,
        .f textarea:focus {
            border-color: var(--accent);
        }
        .f textarea { min-height: 60px; resize: vertical; }

        .select-plus {
            display: grid;
            grid-template-columns: 1fr 30px;
            gap: 5px;
        }
        .plus-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--hover-row);
            font-size: 18px;
            text-decoration: none;
            color: var(--accent);
            font-weight: 400;
        }
        .plus-btn:hover { background: var(--accent-bg); }

        /* ─── TABS + POSITIONS PANEL ─── */
        .positions-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .tabs-bar {
            display: flex;
            align-items: center;
            gap: 0;
            border-bottom: 1px solid var(--border);
            padding: 0 14px;
            background: #fafbfc;
        }

        .tab-btn {
            padding: 10px 14px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            border: none;
            background: transparent;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            font-family: inherit;
            transition: color .12s, border-color .12s;
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            font-weight: 600;
        }

        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background: var(--accent-bg);
            color: var(--accent);
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }

        /* bulk actions bar */
        .bulk-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .bulk-bar .btn { font-size: 12px; padding: 4px 10px; }

        /* ─── TABLE ─── */
        .pos-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pos-table thead th {
            padding: 7px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 500;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: .35px;
            background: #fafbfc;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .pos-table tbody tr {
            border-bottom: 1px solid var(--border-light);
            transition: background .08s;
        }
        .pos-table tbody tr:hover { background: var(--hover-row); }
        .pos-table tbody tr.row-selected { background: var(--sel-row); }

        .pos-table td {
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 12.5px;
        }

        .pos-table td input[type="text"],
        .pos-table td select {
            border: none;
            background: transparent;
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text);
            outline: none;
            width: 100%;
            padding: 2px 4px;
            border-radius: 4px;
        }
        .pos-table td input[type="text"]:focus,
        .pos-table td select:focus {
            background: var(--accent-bg);
            outline: 1px solid var(--accent);
        }

        .pos-table .text-r { text-align: right; }
        .pos-table .text-c { text-align: center; }

        .prod-name-link {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
        }
        .prod-name-link:hover { color: var(--accent); }

        /* row action: three dots */
        .row-actions {
            position: relative;
        }
        .row-dots {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 5px;
            cursor: pointer;
            color: var(--text-light);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            border: none;
            background: transparent;
            font-family: inherit;
        }
        .row-dots:hover { background: var(--bg); color: var(--text); }

        .row-menu {
            display: none;
            position: fixed;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,.12);
            z-index: 9000;
            min-width: 150px;
            padding: 4px 0;
        }
        .row-menu.open { display: block; }

        .row-menu-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            font-size: 12.5px;
            color: var(--text);
            cursor: pointer;
            white-space: nowrap;
            border: none;
            background: transparent;
            width: 100%;
            font-family: inherit;
            text-align: left;
        }
        .row-menu-item:hover { background: var(--hover-row); }
        .row-menu-item.danger { color: #dc2626; }
        .row-menu-item.danger:hover { background: #fff5f5; }

        /* add row */
        .add-row td {
            padding: 8px 8px;
            border-bottom: none;
        }
        .add-row:hover { background: transparent !important; }

        #productSearchInput {
            width: 100%;
            padding: 7px 11px;
            border: 1px dashed #c8cdd6;
            border-radius: 7px;
            background: transparent;
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text-muted);
            outline: none;
            transition: border-color .15s, background .15s;
        }
        #productSearchInput:focus {
            border-color: var(--accent);
            background: var(--surface);
            color: var(--text);
        }

        /* ─── TOTALS INVOICE BLOCK ─── */
        .totals-invoice {
            display: flex;
            justify-content: flex-end;
            padding: 12px 16px 14px;
            border-top: 1px solid var(--border);
        }

        .totals-inner {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0 32px;
            min-width: 480px;
        }

        .totals-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding: 5px 0;
        }

        .totals-cell-label {
            font-size: 10.5px;
            font-weight: 500;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: .35px;
            margin-bottom: 2px;
        }

        .totals-cell-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            font-family: 'Geist Mono', monospace;
        }

        .totals-cell.big .totals-cell-label {
            font-size: 11px;
            color: var(--text-muted);
        }
        .totals-cell.big .totals-cell-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        .totals-divider {
            grid-column: 1 / -1;
            border: none;
            border-top: 1px solid var(--border);
            margin: 6px 0;
        }

        /* ─── ERRORS ─── */
        .error-box {
            background: #fff5f5;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
        }

        /* ─── MISC ─── */
        .mono { font-family: 'Geist Mono', monospace; }

        .empty-box {
            padding: 24px;
            text-align: center;
            color: var(--text-light);
            font-size: 13px;
        }

        /* ─── HISTORY PANEL ─── */
        #historyPanel {
            position: fixed;
            top: 0;
            right: -520px;
            width: 500px;
            height: 100%;
            background: var(--surface);
            border-left: 1px solid var(--border);
            box-shadow: -6px 0 24px rgba(0,0,0,.08);
            transition: right .22s ease;
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
            font-family: inherit;
        }

        /* tab content */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        @media (max-width: 1100px) {
            .fields-area { grid-template-columns: 1fr; }
            .fields-col + .fields-col { border-left: none; padding-left: 0; border-top: 1px solid var(--border-light); padding-top: 12px; margin-top: 4px; }
            .fields-grid { grid-template-columns: repeat(2,1fr); }
            .totals-inner { min-width: unset; grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="page-shell">

    <form method="post" action="/customerorder/save">
        <?php if (!$isNew): ?>
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="items_json" id="items_json">

        <?php if (!$result['ok']): ?>
            <div class="error-box">
                <strong>Помилка:</strong> <?= h(isset($result['error']) ? $result['error'] : 'Невідома помилка') ?>
            </div>
        <?php endif; ?>

        <!-- ══ TOOLBAR ══ -->
        <div class="toolbar">
            <div class="toolbar-left">
                <button type="submit" class="btn btn-save">Зберегти</button>
                <a href="/customerorder" class="btn btn-close">Закрити</a>
                <button type="button" class="btn">Дії ▾</button>
                <button type="button" class="btn">Друк ▾</button>
                <button type="button" class="btn">Надіслати ▾</button>
                <label class="check-label">
                    <input type="checkbox" name="applicable" value="1" <?= checked_attr(field_value($order, 'applicable', 1)) ?>>
                    Проведено
                </label>
            </div>
            <div class="toolbar-right">
                <div class="toolbar-meta">
                    <div class="toolbar-meta-item"><strong>Менеджер:</strong> <?= h($managerName ?: '—') ?></div>
                    <div class="toolbar-meta-item">
                        <strong><a href="#" id="historyToggle">Змінив:</a></strong> <?= h($updatedByName ?: '—') ?>
                    </div>
                    <div class="toolbar-meta-item"><strong>Оновлено:</strong> <?= h($updatedAt ?: '—') ?></div>
                </div>
            </div>
        </div>

        <!-- ══ DOC HEADER ══ -->
        <div class="doc-header">

            <!-- Title row -->
            <div class="doc-title-row">
                <div class="doc-number">
                    <?php if (!$isNew): ?>
                        Замовлення № <?= h(field_value($order, 'number', field_value($order, 'id'))) ?>
                        <span>від <?= h(!empty($order['moment']) ? date('d.m.Y H:i', strtotime($order['moment'])) : '—') ?></span>
                    <?php else: ?>
                        Новий документ
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status + payment + planned date row -->
            <div class="doc-meta-row">

                <!-- Order status as colored select -->
                <?php
                $statusColors = [
                    'draft'             => 'background:#f3f4f6; color:#6b7280;',
                    'new'               => 'background:#dbeafe; color:#1d4ed8;',
                    'confirmed'         => 'background:#d1fae5; color:#065f46;',
                    'in_progress'       => 'background:#fef3c7; color:#92400e;',
                    'waiting_payment'   => 'background:#ede9fe; color:#5b21b6;',
                    'paid'              => 'background:#dcfce7; color:#15803d;',
                    'partially_shipped' => 'background:#ffedd5; color:#c2410c;',
                    'shipped'           => 'background:#cffafe; color:#0e7490;',
                    'completed'         => 'background:#d1fae5; color:#065f46;',
                    'cancelled'         => 'background:#fee2e2; color:#b91c1c;',
                ];
                $currentStatus = field_value($order, 'status', 'draft');
                $currentStyle = isset($statusColors[$currentStatus]) ? $statusColors[$currentStatus] : $statusColors['draft'];
                ?>
                <div class="status-select-wrap">
                    <select name="status" id="status" style="<?= $currentStyle ?>">
                        <?php foreach ([
                            'draft'             => 'Чернетка',
                            'new'               => 'Нове',
                            'confirmed'         => 'Підтверджено',
                            'in_progress'       => 'В роботі',
                            'waiting_payment'   => 'Очікує оплату',
                            'paid'              => 'Оплачено',
                            'partially_shipped' => 'Частково відвантажено',
                            'shipped'           => 'Відвантажено',
                            'completed'         => 'Завершено',
                            'cancelled'         => 'Скасовано',
                        ] as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= selected($value, $currentStatus) ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Payment status pill (read-only) -->
                <span class="status-tag <?= h($paymentStatus['class']) ?> pay-status-tag">
                    Оплата: <?= h($paymentStatus['label']) ?>
                </span>

                <!-- Shipment status pill -->
                <span class="status-tag <?= h($shipmentStatus['class']) ?>">
                    Відвантаження: <?= h($shipmentStatus['label']) ?>
                </span>

                <!-- Planned shipment date — compact, no big field -->
                <div class="planned-date-wrap" style="margin-left:10px;">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="color:var(--text-light)"><rect x="1" y="3" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1v4M11 1v4M1 7h14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span>Відвантаження:</span>
                    <input type="datetime-local" name="planned_shipment_at" id="planned_shipment_at" value="<?= h($plannedShipDate) ?>">
                </div>
            </div>

            <!-- Fields area -->
            <div class="fields-area">

                <!-- LEFT col: org, bank, counterparty -->
                <div class="fields-col">
                    <div class="fields-grid-1">
                        <div class="f">
                            <label>Організація</label>
                            <select name="organization_id" id="organization_id">
                                <option value="">— Обрати —</option>
                                <?php foreach ($organizations as $org): ?>
                                    <option value="<?= (int)$org['id'] ?>" <?= selected($org['id'], field_value($order, 'organization_id')) ?>>
                                        <?= h($org['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Розрахунковий рахунок</label>
                            <select name="organization_bank_account_id" id="organization_bank_account_id">
                                <option value="">— Обрати рахунок —</option>
                                <?php if (!empty($organizationBankAccounts)): ?>
                                    <?php foreach ($organizationBankAccounts as $account): ?>
                                        <?php
                                        $accountText = $account['iban'];
                                        if (!empty($account['account_name'])) $accountText .= ' — ' . $account['account_name'];
                                        if (!empty($account['currency_code'])) $accountText .= ' (' . $account['currency_code'] . ')';
                                        if (empty($order['organization_id']) && !empty($account['organization_name'])) $accountText = $account['organization_name'] . ': ' . $accountText;
                                        if (!empty($account['is_default'])) $accountText .= ' [Основний]';
                                        ?>
                                        <option value="<?= (int)$account['id'] ?>"
                                            <?= selected($account['id'], field_value($order, 'organization_bank_account_id')) ?>
                                            <?= !empty($account['is_default']) ? 'data-default="1"' : '' ?>>
                                            <?= h($accountText) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="fields-grid-2">
                            <div class="f">
                                <label>Контрагент</label>
                                <div class="select-plus">
                                    <select name="counterparty_id" id="counterparty_id">
                                        <option value="">— Обрати —</option>
                                        <?php foreach ($counterparties as $counterparty): ?>
                                            <option value="<?= (int)$counterparty['id'] ?>" <?= selected($counterparty['id'], field_value($order, 'counterparty_id')) ?>>
                                                <?= h($counterparty['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="#" class="plus-btn" title="Додати">+</a>
                                </div>
                            </div>

                            <div class="f">
                                <label>Контактна особа</label>
                                <div class="select-plus">
                                    <select name="contact_person_id" id="contact_person_id">
                                        <option value="">— Обрати —</option>
                                        <?php foreach ($contactPersons as $person): ?>
                                            <option value="<?= (int)$person['id'] ?>" <?= selected($person['id'], field_value($order, 'contact_person_id')) ?>>
                                                <?= h($person['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="#" class="plus-btn" title="Додати">+</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT col: contract, project, channel, currency, store, manager -->
                <div class="fields-col">
                    <div class="fields-grid">
                        <div class="f">
                            <label>Договір</label>
                            <select name="contract_id" id="contract_id">
                                <option value="">— Без договору —</option>
                                <?php foreach ($contracts as $contract): ?>
                                    <option value="<?= (int)$contract['id'] ?>" <?= selected($contract['id'], field_value($order, 'contract_id')) ?>>
                                        <?= h($contract['number']) ?> — <?= h($contract['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Проєкт</label>
                            <select name="project_id" id="project_id">
                                <option value="">— Без проєкту —</option>
                                <option value="1" <?= selected('1', field_value($order, 'project_id')) ?>>Основний</option>
                                <option value="2" <?= selected('2', field_value($order, 'project_id')) ?>>B2B продажі</option>
                                <option value="3" <?= selected('3', field_value($order, 'project_id')) ?>>Корпоративні</option>
                            </select>
                        </div>

                        <div class="f">
                            <label>Канал продажу</label>
                            <select name="sales_channel" id="sales_channel">
                                <?php if ($salesChannels): ?>
                                    <?php foreach ($salesChannels as $ch): ?>
                                        <option value="<?= h($ch['code']) ?>" <?= selected($ch['code'], field_value($order, 'sales_channel')) ?>><?= h($ch['name']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">— Обрати —</option>
                                    <option value="manual" <?= selected('manual', field_value($order, 'sales_channel')) ?>>Ручне</option>
                                    <option value="site" <?= selected('site', field_value($order, 'sales_channel')) ?>>Сайт</option>
                                    <option value="marketplace" <?= selected('marketplace', field_value($order, 'sales_channel')) ?>>Маркетплейс</option>
                                    <option value="api" <?= selected('api', field_value($order, 'sales_channel')) ?>>API</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Валюта</label>
                            <select name="currency_code" id="currency_code">
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?= h($currency['code']) ?>" <?= selected($currency['code'], field_value($order, 'currency_code', 'UAH')) ?>>
                                        <?= h($currency['code']) ?> — <?= h($currency['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Склад</label>
                            <select name="store_id" id="store_id">
                                <option value="">— Обрати —</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?= (int)$store['id'] ?>" <?= selected($store['id'], field_value($order, 'store_id')) ?>><?= h($store['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Менеджер</label>
                            <select name="manager_employee_id" id="manager_employee_id">
                                <option value="">— Обрати —</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= (int)$employee['id'] ?>" <?= selected($employee['id'], field_value($order, 'manager_employee_id')) ?>><?= h($employee['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

            </div><!-- /fields-area -->
        </div><!-- /doc-header -->
    </form>

    <!-- ══ POSITIONS + TABS ══ -->
    <?php if (!$isNew): ?>
    <div class="positions-panel">

        <!-- Tabs -->
        <div class="tabs-bar">
            <button class="tab-btn active" data-tab="positions">Позиції</button>
            <button class="tab-btn" data-tab="related">Пов'язані документи <span class="tab-badge">1</span></button>
            <button class="tab-btn" data-tab="files">Файли</button>
            <button class="tab-btn" data-tab="tasks">Задачі</button>
            <button class="tab-btn" data-tab="events">Події</button>
        </div>

        <!-- Bulk actions -->
        <div class="bulk-bar">
            <span style="font-size:11.5px; color:var(--text-muted);">Вибрані:</span>
            <button type="button" class="btn" id="bulkDeleteBtn" disabled>Видалити</button>
            <button type="button" class="btn" id="bulkDuplicateBtn" disabled>Дублювати</button>
        </div>

        <!-- Positions tab -->
        <div class="tab-content active" id="tab-positions">
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
                    <th style="width:70px;" class="text-r">Відвант.</th>
                    <th style="width:70px;" class="text-r">Доступно</th>
                    <th style="width:70px;" class="text-r">Залишок</th>
                    <th style="width:60px;" class="text-r">Резерв</th>
                    <th style="width:70px;" class="text-r">Очікув.</th>
                    <th style="width:36px;"></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="14" class="empty-box">Позицій поки немає.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-item-row="1">
                        <td class="text-c">
                            <input type="checkbox" class="row-check" name="selected_items[]" value="<?= (int)$item['id'] ?>">
                        </td>

                        <td>
                            <a href="#" class="prod-name-link"><?= h(field_value($item, 'product_name')) ?></a>
                            <input type="hidden" class="item-id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" class="item-product-id" value="<?= h(field_value($item, 'product_id')) ?>">
                            <input type="hidden" class="item-sku" value="<?= h(field_value($item, 'sku')) ?>">
                            <input type="hidden" class="input-weight" value="<?= h(field_value($item, 'weight', 0)) ?>">
                        </td>

                        <td class="text-c">
                            <input type="text" class="input-unit" value="<?= h(field_value($item, 'unit')) ?>" style="width:42px; text-align:center;" readonly>
                        </td>

                        <td class="text-r">
                            <input type="text" class="input-qty" value="<?= h(field_value($item, 'quantity', 1)) ?>" style="width:72px; text-align:right;">
                        </td>

                        <td class="text-r">
                            <input type="text" class="input-price" value="<?= h(field_value($item, 'price', 0)) ?>" style="width:82px; text-align:right;">
                        </td>

                        <td class="text-c">
                            <select class="input-vat" style="width:82px; text-align:center;">
                                <option value="0" <?= selected('0', field_value($item, 'vat_rate', 20)) ?>>Без ПДВ</option>
                                <option value="20" <?= selected('20', field_value($item, 'vat_rate', 20)) ?>>20%</option>
                            </select>
                        </td>

                        <td class="text-r">
                            <input type="text" class="input-discount" value="<?= h(field_value($item, 'discount_percent', 0)) ?>" style="width:58px; text-align:right;">
                        </td>

                        <td class="text-r">
                            <input type="text" class="input-sum" value="<?= h(field_value($item, 'sum_row', 0)) ?>" style="width:90px; text-align:right; font-weight:500;">
                        </td>

                        <td class="text-r"><?= number_format((float)field_value($item, 'shipped_quantity', 0), 3, '.', ' ') ?></td>
                        <td class="text-r"><?= number_format((float)field_value($item, 'stock_quantity', 0), 3, '.', ' ') ?></td>
                        <td class="text-r"><?= number_format((float)field_value($item, 'stock_quantity', 0), 3, '.', ' ') ?></td>
                        <td class="text-r"><?= number_format((float)field_value($item, 'reserved_stock_quantity', 0), 3, '.', ' ') ?></td>
                        <td class="text-r"><?= number_format((float)field_value($item, 'expected_quantity', 0), 3, '.', ' ') ?></td>

                        <td class="row-actions text-c">
                            <button type="button" class="row-dots" title="Дії">···</button>
                            <div class="row-menu">
                                <button class="row-menu-item" type="button">
                                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 8h6M8 5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                    Дублювати
                                </button>
                                <button class="row-menu-item" type="button">
                                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M7 7v5M9 7v5M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                    Замінити
                                </button>
                                <form method="post" action="/customerorder/item_delete" onsubmit="return confirm('Видалити рядок?');" style="display:contents;">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="row-menu-item danger">
                                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                        Видалити
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <tr class="add-row">
                    <td style="font-size:18px; color:var(--accent); text-align:center; padding-left:8px;">+</td>
                    <td colspan="13" style="position:relative;">
                        <input type="text" id="productSearchInput" placeholder="Додати позицію — введіть найменування, код або артикул...">
                        <div id="productSearchResults" style="
                            display:none;
                            position:absolute;
                            left:0; right:0; bottom:100%;
                            margin-bottom:4px;
                            background:#fff;
                            border:1px solid var(--border);
                            border-radius:8px;
                            box-shadow:0 -8px 20px rgba(0,0,0,.08);
                            z-index:1000;
                            max-height:300px;
                            overflow-y:auto;
                        "></div>
                    </td>
                </tr>
                </tbody>
            </table>

            <!-- Invoice-style totals -->
            <div class="totals-invoice">
                <div class="totals-inner">
                    <div class="totals-cell">
                        <div class="totals-cell-label">Позицій</div>
                        <div class="totals-cell-value" id="summary-total-items"><?= count($items) ?></div>
                    </div>
                    <div class="totals-cell">
                        <div class="totals-cell-label">К-сть товару</div>
                        <div class="totals-cell-value" id="summary-total-qty">
                            <?= number_format(array_sum(array_map(function($r){ return (float)$r['quantity']; }, $items)), 3, '.', ' ') ?>
                        </div>
                    </div>
                    <div class="totals-cell">
                        <div class="totals-cell-label">Вага, кг</div>
                        <div class="totals-cell-value" id="summary-total-weight">
                            <?= number_format(array_sum(array_map(function($r){ return (float)$r['weight']*(float)$r['quantity']; }, $items)), 3, '.', ' ') ?>
                        </div>
                    </div>

                    <hr class="totals-divider">

                    <div class="totals-cell">
                        <div class="totals-cell-label">Сума без ПДВ</div>
                        <div class="totals-cell-value" id="summary-total-net">
                            <?= number_format(array_sum(array_map(function($r){
                                $s=(float)$r['sum_row']; $v=(float)$r['vat_rate'];
                                return $v>0 ? $s/(1+$v/100) : $s;
                            }, $items)), 2, '.', ' ') ?>
                        </div>
                    </div>
                    <div class="totals-cell">
                        <div class="totals-cell-label">ПДВ</div>
                        <div class="totals-cell-value" id="summary-total-vat">
                            <?= number_format(array_sum(array_map(function($r){
                                $s=(float)$r['sum_row']; $v=(float)$r['vat_rate'];
                                if($v>0){ $net=$s/(1+$v/100); return $s-$net; } return 0;
                            }, $items)), 2, '.', ' ') ?>
                        </div>
                    </div>
                    <div class="totals-cell big">
                        <div class="totals-cell-label">Разом до сплати</div>
                        <div class="totals-cell-value" id="summary-total-sum">
                            <?= number_format(array_sum(array_map(function($r){ return (float)$r['sum_row']; }, $items)), 2, '.', ' ') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /tab-positions -->

        <!-- Related docs tab -->
        <div class="tab-content" id="tab-related">
            <div class="empty-box">Пов'язані документи</div>
        </div>
        <div class="tab-content" id="tab-files">
            <div class="empty-box">Файли</div>
        </div>
        <div class="tab-content" id="tab-tasks">
            <div class="empty-box">Задачі</div>
        </div>
        <div class="tab-content" id="tab-events">
            <div class="empty-box">Події</div>
        </div>

    </div><!-- /positions-panel -->
    <?php endif; ?>

</div><!-- /page-shell -->

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
            <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Працівник</th>
            <th style="padding:7px 8px; text-align:left; font-weight:500; color:var(--text-muted);">Коментар</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$history): ?>
            <tr><td colspan="4" class="empty-box">Історія поки порожня.</td></tr>
        <?php else: ?>
            <?php foreach ($history as $event): ?>
                <tr style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:7px 8px;"><?= h(field_value($event, 'created_at')) ?></td>
                    <td style="padding:7px 8px;"><?= h(field_value($event, 'event_type')) ?></td>
                    <td style="padding:7px 8px;"><?= h(field_value($event, 'employee_name')) ?></td>
                    <td style="padding:7px 8px;"><?= h(field_value($event, 'comment')) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<div id="historyOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.2); z-index:9998;"></div>

<script>
/* ══ TABS ══ */
document.querySelectorAll('.tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(c){ c.classList.remove('active'); });
        btn.classList.add('active');
        var tab = document.getElementById('tab-' + btn.dataset.tab);
        if (tab) tab.classList.add('active');
    });
});

/* ══ ROW MENUS — fixed positioning so they never clip ══ */
document.addEventListener('click', function(e) {
    var dotsBtn = e.target.closest('.row-dots');
    if (dotsBtn) {
        e.stopPropagation();
        var menu = dotsBtn.nextElementSibling;
        var isOpen = menu.classList.contains('open');
        // close all
        document.querySelectorAll('.row-menu.open').forEach(function(m){ m.classList.remove('open'); });
        if (!isOpen) {
            var rect = dotsBtn.getBoundingClientRect();
            var menuW = 160;
            var left = rect.right - menuW;
            if (left < 8) left = 8;
            // open above or below based on space
            var spaceBelow = window.innerHeight - rect.bottom;
            menu.classList.add('open');
            if (spaceBelow < 140) {
                menu.style.top = (rect.top + window.scrollY - menu.offsetHeight - 4) + 'px';
            } else {
                menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
            }
            menu.style.left = left + 'px';
            menu.style.width = menuW + 'px';
        }
        return;
    }
    document.querySelectorAll('.row-menu.open').forEach(function(m){ m.classList.remove('open'); });
});

/* ══ CHECKBOXES + ROW HIGHLIGHT ══ */
var checkAll = document.getElementById('checkAll');
if (checkAll) {
    checkAll.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(function(cb) {
            cb.checked = checkAll.checked;
            cb.closest('tr').classList.toggle('row-selected', checkAll.checked);
        });
        updateBulkBar();
    });
}
document.querySelectorAll('.row-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        cb.closest('tr').classList.toggle('row-selected', cb.checked);
        updateBulkBar();
    });
});
function updateBulkBar() {
    var any = document.querySelectorAll('.row-check:checked').length > 0;
    var bDel = document.getElementById('bulkDeleteBtn');
    var bDup = document.getElementById('bulkDuplicateBtn');
    if (bDel) bDel.disabled = !any;
    if (bDup) bDup.disabled = !any;
}

/* ══ STATUS SELECT COLOR UPDATE ══ */
var statusColors = {
    draft:             'background:#f3f4f6; color:#6b7280;',
    new:               'background:#dbeafe; color:#1d4ed8;',
    confirmed:         'background:#d1fae5; color:#065f46;',
    in_progress:       'background:#fef3c7; color:#92400e;',
    waiting_payment:   'background:#ede9fe; color:#5b21b6;',
    paid:              'background:#dcfce7; color:#15803d;',
    partially_shipped: 'background:#ffedd5; color:#c2410c;',
    shipped:           'background:#cffafe; color:#0e7490;',
    completed:         'background:#d1fae5; color:#065f46;',
    cancelled:         'background:#fee2e2; color:#b91c1c;'
};
var statusSel = document.getElementById('status');
if (statusSel) {
    statusSel.addEventListener('change', function() {
        var s = statusColors[this.value] || statusColors.draft;
        this.style.cssText = s;
    });
}

/* ══ HISTORY PANEL ══ */
var historyPanel = document.getElementById('historyPanel');
var historyOverlay = document.getElementById('historyOverlay');
var historyToggle = document.getElementById('historyToggle');
var historyClose = document.getElementById('historyClose');

function openHistory() {
    if (!historyPanel) return;
    historyPanel.style.right = '0';
    historyOverlay.style.display = 'block';
}
function closeHistory() {
    if (!historyPanel) return;
    historyPanel.style.right = '-520px';
    historyOverlay.style.display = 'none';
}
if (historyToggle) historyToggle.addEventListener('click', function(e){ e.preventDefault(); openHistory(); });
if (historyClose)  historyClose.addEventListener('click', closeHistory);
if (historyOverlay) historyOverlay.addEventListener('click', closeHistory);

/* ══ ALL ORIGINAL JS (recalc, search, form, ajax) ══ */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('productSearchInput');
    var results = document.getElementById('productSearchResults');
    var orderId = <?= !$isNew ? (int)$order['id'] : 0 ?>;
    var timer = null;

    function toFloat(value, fallback) {
        var normalized = String(value || '').replace(',', '.').trim();
        var parsed = parseFloat(normalized);
        return isNaN(parsed) ? fallback : parsed;
    }
    function formatMoney(value) { return (Math.round(value * 100) / 100).toFixed(2); }
    function formatQty(value) { return (Math.round(value * 1000) / 1000).toFixed(3); }

    function collectDraftRows() {
        var rows = document.querySelectorAll('#positionsTable tbody tr[data-item-row="1"]');
        var data = [];
        rows.forEach(function(row) {
            var itemId = row.querySelector('.item-id');
            if (!itemId) return;
            data.push({
                item_id: itemId.value,
                product_id: row.querySelector('.item-product-id') ? row.querySelector('.item-product-id').value : '',
                sku: row.querySelector('.item-sku') ? row.querySelector('.item-sku').value : '',
                quantity: row.querySelector('.input-qty') ? row.querySelector('.input-qty').value : '',
                price: row.querySelector('.input-price') ? row.querySelector('.input-price').value : '',
                vat_rate: row.querySelector('.input-vat') ? row.querySelector('.input-vat').value : '',
                discount_percent: row.querySelector('.input-discount') ? row.querySelector('.input-discount').value : '',
                sum_row: row.querySelector('.input-sum') ? row.querySelector('.input-sum').value : '',
                unit: row.querySelector('.input-unit') ? row.querySelector('.input-unit').value : '',
                weight: row.querySelector('.input-weight') ? row.querySelector('.input-weight').value : ''
            });
        });
        sessionStorage.setItem('customerorder_draft_rows_' + orderId, JSON.stringify(data));
    }

    function recalcRow(row, sourceField) {
        if (!row) return;
        var qtyInput = row.querySelector('.input-qty');
        var priceInput = row.querySelector('.input-price');
        var sumInput = row.querySelector('.input-sum');
        var vatInput = row.querySelector('.input-vat');
        var discountInput = row.querySelector('.input-discount');
        if (!qtyInput || !priceInput || !sumInput) return;
        var qty = toFloat(qtyInput.value, 1);
        if (qty <= 0) qty = 1;
        var price = toFloat(priceInput.value, 0);
        var sum = toFloat(sumInput.value, 0);
        var vatRate = vatInput ? parseFloat(vatInput.value) : 20;
        if (isNaN(vatRate)) vatRate = 0;
        var discountPercent = discountInput ? toFloat(discountInput.value, 0) : 0;
        if (sourceField === 'sum') {
            price = qty > 0 ? (sum / qty) : 0;
            priceInput.value = formatMoney(price);
        } else {
            var gross = qty * price;
            var discountAmount = gross * (discountPercent / 100);
            sum = gross - discountAmount;
            sumInput.value = formatMoney(sum);
        }
        qtyInput.value = formatQty(qty);
        recalcDocumentTotals();
        collectDraftRows();
    }

    function recalcDocumentTotals() {
        var rows = document.querySelectorAll('#positionsTable tbody tr[data-item-row="1"]');
        var totalItems = 0, totalQty = 0, totalNet = 0, totalVat = 0, totalSum = 0, totalWeight = 0;
        rows.forEach(function(row) {
            var qtyInput = row.querySelector('.input-qty');
            var priceInput = row.querySelector('.input-price');
            var sumInput = row.querySelector('.input-sum');
            var vatInput = row.querySelector('.input-vat');
            var discountInput = row.querySelector('.input-discount');
            var weightInput = row.querySelector('.input-weight');
            if (!qtyInput || !priceInput || !sumInput) return;
            var qty = toFloat(qtyInput.value, 0);
            var price = toFloat(priceInput.value, 0);
            var sum = toFloat(sumInput.value, 0);
            var vatRate = vatInput ? parseFloat(vatInput.value) : 0;
            if (isNaN(vatRate)) vatRate = 0;
            var discountPercent = discountInput ? toFloat(discountInput.value, 0) : 0;
            var weight = weightInput ? toFloat(weightInput.value, 0) : 0;
            var gross = qty * price;
            var discountAmount = gross * (discountPercent / 100);
            var rowSum = gross - discountAmount;
            var net = 0, vatAmount = 0;
            if (vatRate > 0) { vatAmount = rowSum * vatRate / (100 + vatRate); net = rowSum - vatAmount; }
            else { net = rowSum; vatAmount = 0; }
            totalItems += 1; totalQty += qty; totalNet += net; totalVat += vatAmount; totalSum += rowSum; totalWeight += (weight * qty);
            if (sumInput) sumInput.value = formatMoney(rowSum);
        });
        var el = function(id){ return document.getElementById(id); };
        if (el('summary-total-items')) el('summary-total-items').textContent = totalItems;
        if (el('summary-total-qty'))   el('summary-total-qty').textContent = formatQty(totalQty);
        if (el('summary-total-net'))   el('summary-total-net').textContent = formatMoney(totalNet);
        if (el('summary-total-vat'))   el('summary-total-vat').textContent = formatMoney(totalVat);
        if (el('summary-total-sum'))   el('summary-total-sum').textContent = formatMoney(totalSum);
        if (el('summary-total-weight')) el('summary-total-weight').textContent = formatQty(totalWeight);
    }

    function bindRowRecalc(row) {
        if (!row) return;
        var qtyInput = row.querySelector('.input-qty');
        var priceInput = row.querySelector('.input-price');
        var sumInput = row.querySelector('.input-sum');
        var vatInput = row.querySelector('.input-vat');
        var discountInput = row.querySelector('.input-discount');
        if (sumInput) {
            sumInput.addEventListener('input', function(){ sumInput.dataset.changed = '1'; });
            sumInput.addEventListener('blur', function(){ recalcRow(row, 'sum'); });
        }
        if (qtyInput) qtyInput.addEventListener('blur', function(){ if (sumInput) sumInput.dataset.changed='0'; recalcRow(row,'qty'); });
        if (priceInput) priceInput.addEventListener('blur', function(){ if (sumInput) sumInput.dataset.changed='0'; recalcRow(row,'price'); });
        if (vatInput) vatInput.addEventListener('change', function(){ if (sumInput) sumInput.dataset.changed='0'; recalcRow(row,'vat'); });
        if (discountInput) discountInput.addEventListener('blur', function(){ if (sumInput) sumInput.dataset.changed='0'; recalcRow(row,'discount'); });
    }

    function restoreDraftRows() {
        var raw = sessionStorage.getItem('customerorder_draft_rows_' + orderId);
        if (!raw) return;
        try {
            var data = JSON.parse(raw);
            data.forEach(function(saved) {
                var itemInput = document.querySelector('.item-id[value="' + saved.item_id + '"]');
                if (!itemInput) return;
                var tr = itemInput.closest('tr');
                if (!tr) return;
                var qtyInput = tr.querySelector('.input-qty');
                var priceInput = tr.querySelector('.input-price');
                var vatInput = tr.querySelector('.input-vat');
                var discountInput = tr.querySelector('.input-discount');
                var sumInput = tr.querySelector('.input-sum');
                if (qtyInput) qtyInput.value = saved.quantity || '1';
                if (priceInput) priceInput.value = saved.price || '0';
                if (vatInput) vatInput.value = saved.vat_rate || '20';
                if (discountInput) discountInput.value = saved.discount_percent || '0';
                if (sumInput) { sumInput.value = saved.sum_row || '0'; sumInput.dataset.changed = '1'; }
                recalcRow(tr, 'restore');
            });
            recalcDocumentTotals();
        } catch(e) { console.error('Error restoring draft rows:', e); }
    }

    restoreDraftRows();
    document.querySelectorAll('#positionsTable tbody tr[data-item-row="1"]').forEach(function(row){ bindRowRecalc(row); });
    recalcDocumentTotals();

    if (input && results && orderId) {
        input.addEventListener('input', function() {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { results.style.display='none'; results.innerHTML=''; return; }
            timer = setTimeout(function() {
                fetch('/customerorder/search_product?q=' + encodeURIComponent(q))
                    .then(function(r){ if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then(function(data) {
                        if (!data.ok || !data.items || !data.items.length) {
                            results.innerHTML = '<div style="padding:10px 12px; color:#666; font-size:12.5px;">Нічого не знайдено</div>';
                            results.style.display = 'block'; return;
                        }
                        var html = '';
                        data.items.forEach(function(item) {
                            html += '<div class="product-search-item" ' +
                                'data-product-id="' + item.product_id + '" ' +
                                'data-name="' + (item.name||'') + '" ' +
                                'data-sku="' + (item.product_article||'') + '" ' +
                                'data-price="' + (item.price||0) + '" ' +
                                'style="padding:9px 12px; border-bottom:1px solid #f0f2f5; cursor:pointer; font-size:12.5px;">' +
                                '<span style="font-weight:500;">' + (item.name||'') + '</span><br>' +
                                '<span style="font-size:11.5px; color:#9ca3af;">Артикул: ' + (item.product_article||'') +
                                ' · Ціна: ' + (item.price||0) + ' · Залишок: ' + (item.quantity||0) + ' · ' + (item.unit||'-') + '</span>' +
                                '</div>';
                        });
                        results.innerHTML = html;
                        results.style.display = 'block';
                    })
                    .catch(function(error) {
                        results.innerHTML = '<div style="padding:10px 12px; color:#b91c1c; font-size:12.5px;">Помилка пошуку</div>';
                        results.style.display = 'block';
                    });
            }, 250);
        });

        results.addEventListener('click', function(e) {
            var row = e.target.closest('.product-search-item');
            if (!row) return;
            collectDraftRows();
            var formData = new FormData();
            formData.append('customerorder_id', orderId);
            formData.append('product_id', row.getAttribute('data-product-id'));
            formData.append('product_name', row.getAttribute('data-name'));
            formData.append('sku', row.getAttribute('data-sku'));
            formData.append('quantity', '1');
            formData.append('price', row.getAttribute('data-price') || '0');
            fetch('/customerorder/item_add_ajax', { method: 'POST', body: formData })
                .then(function(r){ return r.text(); })
                .then(function(text) {
                    var data = JSON.parse(text);
                    if (!data.ok) throw new Error(data.error || 'Не вдалося додати товар');
                    input.value = ''; results.innerHTML = ''; results.style.display = 'none';
                    window.location.reload();
                })
                .catch(function(){ alert('Не вдалося додати товар'); });
        });

        document.addEventListener('click', function(e) {
            if (!results.contains(e.target) && e.target !== input) results.style.display = 'none';
        });
    }

    var form = document.querySelector('form[action="/customerorder/save"]');
    if (form) {
        form.addEventListener('submit', function() {
            var rows = document.querySelectorAll('#positionsTable tbody tr[data-item-row="1"]');
            var data = [];
            rows.forEach(function(row) {
                var itemId = row.querySelector('.item-id');
                if (!itemId) return;
                data.push({
                    id: itemId.value,
                    quantity: row.querySelector('.input-qty') ? row.querySelector('.input-qty').value : '1',
                    price: row.querySelector('.input-price') ? row.querySelector('.input-price').value : '0',
                    vat_rate: row.querySelector('.input-vat') ? row.querySelector('.input-vat').value : '20',
                    discount_percent: row.querySelector('.input-discount') ? row.querySelector('.input-discount').value : '0',
                    sum_row: row.querySelector('.input-sum') ? row.querySelector('.input-sum').value : '0',
                    sum_row_changed: row.querySelector('.input-sum') && row.querySelector('.input-sum').dataset.changed === '1' ? 1 : 0
                });
            });
            var itemsJson = document.getElementById('items_json');
            if (itemsJson) itemsJson.value = JSON.stringify(data);
            return true;
        });
    }

    if (window.location.href.indexOf('save=1') > -1) {
        sessionStorage.removeItem('customerorder_draft_rows_' + orderId);
    }

    // AJAX bank accounts
    var orgSelect = document.getElementById('organization_id');
    var bankSelect = document.getElementById('organization_bank_account_id');
    if (orgSelect && bankSelect) {
        orgSelect.addEventListener('change', function() {
            var orgId = this.value;
            if (!orgId) { window.location.reload(); return; }
            bankSelect.innerHTML = '<option value="">Завантаження...</option>';
            bankSelect.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/customerorder/ajax_get_bank_accounts?organization_id=' + orgId + '&t=' + Date.now(), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    bankSelect.disabled = false;
                    if (xhr.status == 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (!response.ok) throw new Error(response.error || 'Unknown error');
                            bankSelect.innerHTML = '<option value="">— Обрати рахунок —</option>';
                            if (response.accounts && response.accounts.length > 0) {
                                response.accounts.forEach(function(account) {
                                    var option = document.createElement('option');
                                    option.value = account.id;
                                    var text = account.iban;
                                    if (account.account_name) text += ' — ' + account.account_name;
                                    text += ' (' + account.currency_code + ')';
                                    if (account.is_default == 1) text += ' [Основний]';
                                    option.text = text;
                                    if (account.is_default == 1) option.selected = true;
                                    bankSelect.appendChild(option);
                                });
                            } else {
                                bankSelect.innerHTML = '<option value="">— Немає рахунків —</option>';
                            }
                        } catch(e) {
                            bankSelect.innerHTML = '<option value="">— Помилка —</option>';
                        }
                    } else {
                        bankSelect.innerHTML = '<option value="">— Помилка сервера —</option>';
                    }
                }
            };
            xhr.send();
        });
    }

    // product search item hover
    document.addEventListener('mouseover', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '#f8f9fb';
    });
    document.addEventListener('mouseout', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '';
    });
});
</script>
</body>
</html>
