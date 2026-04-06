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

if (!isset($organizations))      $organizations      = array();
if (!isset($stores))             $stores             = array();
if (!isset($employees))          $employees          = array();
if (!isset($deliveryMethods))    $deliveryMethods    = array();
if (!isset($paymentMethods))     $paymentMethods     = array();
if (!isset($counterpartyName))   $counterpartyName   = '';
if (!isset($contactPersonName))  $contactPersonName  = '';
if (!isset($currencies)) {
    $currencies = array(
        array('code' => 'UAH', 'name' => 'Гривня'),
        array('code' => 'EUR', 'name' => 'Євро'),
        array('code' => 'USD', 'name' => 'Долар'),
    );
}
if (!isset($salesChannels))   $salesChannels   = array();
if (!isset($contracts))       $contracts       = array();
if (!isset($projects))        $projects        = array();
if (!isset($initialContacts)) $initialContacts = array();

$order = !empty($result['order']) ? $result['order'] : array();
$currentCpId     = field_value($order, 'counterparty_id',   '');
$currentPersonId = field_value($order, 'contact_person_id', '');
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
    if (is_numeric($value) && is_numeric($current)) {
        return (float)$value == (float)$current ? 'selected' : '';
    }
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
            'not_paid'       => array('label' => 'Не оплачено',   'class' => 'status-gray',   'badge_cls' => 'wsof-pay-none'),
            'partially_paid' => array('label' => 'Частково',       'class' => 'status-yellow', 'badge_cls' => 'wsof-pay-partial'),
            'paid'           => array('label' => 'Оплачено',       'class' => 'status-green',  'badge_cls' => 'wsof-pay-done'),
            'overdue'        => array('label' => 'Прострочено',    'class' => 'status-red',    'badge_cls' => 'wsof-pay-overdue'),
            'refund'         => array('label' => 'Повернення',     'class' => 'status-dark',   'badge_cls' => 'wsof-pay-refund'),
        ),
        'shipment' => array(
            'not_shipped'       => array('label' => 'Не відвантажено',  'class' => 'status-gray',   'badge_cls' => 'wsof-ship-none'),
            'reserved'          => array('label' => 'Зарезервовано',    'class' => 'status-yellow', 'badge_cls' => 'wsof-ship-reserved'),
            'partially_shipped' => array('label' => 'Частково',         'class' => 'status-cyan',   'badge_cls' => 'wsof-ship-partial'),
            'shipped'           => array('label' => 'Відвантажено',     'class' => 'status-green',  'badge_cls' => 'wsof-ship-done'),
            'delivered'         => array('label' => 'Доставлено',       'class' => 'status-green',  'badge_cls' => 'wsof-ship-delivered'),
            'returned'          => array('label' => 'Повернено',        'class' => 'status-red',    'badge_cls' => 'wsof-ship-returned'),
        ),
    );

    if (isset($map[$type][$status])) {
        return $map[$type][$status];
    }

    return array(
        'label'     => $status,
        'class'     => 'status-gray',
        'badge_cls' => 'wsof-pay-none',
    );
}

$orderStatus = status_meta('order', field_value($order, 'status', 'draft'));
$paymentStatus = status_meta('payment', field_value($order, 'payment_status', 'not_paid'));
$shipmentStatus = status_meta('shipment', field_value($order, 'shipment_status', 'not_shipped'));

$plannedShipDate = !empty($order['planned_shipment_at']) ? date('Y-m-d', strtotime($order['planned_shipment_at'])) : '';

$managerName = field_value($order, 'manager_name');
$updatedByName = field_value($order, 'updated_by_name');
$updatedAt = field_value($order, 'updated_at');


?>
<?php
$title     = 'Замовлення' . ($isNew ? '' : ' #' . (int)$order['id']);
$activeNav = 'sales';
$subNav    = 'orders';
$extraCss  = '<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">';
require_once __DIR__ . '/../../shared/layout.php';
?>
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
            color: var(--text);
            background: var(--bg);
            line-height: 1.45;
        }

        .page-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 14px 16px;
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
        .btn-primary { background: #2563c4; border-color: #2563c4; color: #fff; }
        .btn-primary:hover { background: #1a4fa0; border-color: #1a4fa0; color: #fff; }
        .badge-indigo { background: #ede9fe; color: #5b21b6; }
        .badge-purple { background: #fae8ff; color: #7e22ce; }
        .badge-teal   { background: #ccfbf1; color: #0f766e; }

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

        .doc-title-links {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .doc-title-link {
            font-size: 12px;
            color: var(--accent);
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 3px 9px;
            background: var(--surface);
            white-space: nowrap;
        }
        .doc-title-link:hover {
            background: var(--accent-bg);
            border-color: var(--accent);
        }

        .doc-number {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -.3px;
            color: var(--text);
        }
        .order-traffic-source {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 500; padding: 3px 10px;
            border-radius: 20px; background: #e8f0fe; color: #4285f4;
            border: 1px solid #c5d8fc; cursor: default;
        }
        .order-traffic-campaign {
            font-weight: 400; opacity: .75; font-size: 11px;
            max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .doc-number span {
            font-size: 13px;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 6px;
        }

        /* Payment / shipment mini-badges */
        .ws-of-mini-badge {
            display: inline-flex; align-items: center;
            font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px;
            white-space: nowrap; flex-shrink: 0; user-select: none;
        }
        .wsof-pay-none     { background: #fee2e2; color: #991b1b; }
        .wsof-pay-partial  { background: #fef3c7; color: #92400e; }
        .wsof-pay-done     { background: #dcfce7; color: #15803d; }
        .wsof-pay-overdue  { background: #fee2e2; color: #7f1d1d; }
        .wsof-pay-refund   { background: #f3e8ff; color: #6b21a8; }
        .wsof-ship-none     { background: #f3f4f6; color: #6b7280; }
        .wsof-ship-reserved { background: #dbeafe; color: #1e40af; }
        .wsof-ship-partial  { background: #fef3c7; color: #92400e; }
        .wsof-ship-done     { background: #e0f2fe; color: #0369a1; }
        .wsof-ship-delivered{ background: #dcfce7; color: #15803d; }
        .wsof-ship-returned { background: #fee2e2; color: #9a3412; }

        /* header meta row: status select + planned date */
        .doc-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* custom status dropdown */
        .status-dd {
            position: relative;
            display: inline-block;
        }
        .status-dd-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px 4px 11px;
            border-radius: 6px;
            border: 1.5px solid currentColor;
            font-size: 11.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            outline: none;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            transition: box-shadow .15s, filter .15s;
        }
        .status-dd-btn:hover { box-shadow: 0 2px 6px rgba(0,0,0,.15); filter: brightness(.95); }
        .status-dd-btn .dd-caret { font-size: 9px; opacity: .5; margin-left: 1px; }
        .status-dd-menu {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            z-index: 9999;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
            min-width: 180px;
            padding: 4px;
            list-style: none;
            margin: 0;
        }
        .status-dd-menu.open { display: block; }
        .status-dd-opt {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
        }
        .status-dd-opt:hover { background: #f3f4f6; }
        .status-dd-opt .opt-pill {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .planned-date-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .planned-date-wrap input[type="date"] {
            border: none;
            background: transparent;
            font-size: 12px;
            font-family: inherit;
            color: var(--text);
            outline: none;
            padding: 2px 0;
            cursor: pointer;
        }
        .planned-date-wrap input[type="date"]::-webkit-calendar-picker-indicator {
            display: none;
            -webkit-appearance: none;
        }
        .planned-date-wrap .planned-date-icon {
            cursor: pointer;
            flex-shrink: 0;
        }
        .planned-date-wrap .planned-date-icon:hover { color: var(--accent); }
        .planned-date-wrap input:hover { color: var(--accent); }

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
            min-width: 0;
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

        /* price picker dropdown */
        .price-cell { position: relative; }
        .price-dd {
            display: none;
            position: absolute;
            right: 0; top: calc(100% + 2px);
            z-index: 120;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            min-width: 200px;
            padding: 4px 0;
            font-size: 12.5px;
        }
        .price-dd.open { display: block; }
        .price-dd-row {
            display: flex;
            align-items: center;
            padding: 5px 12px;
            gap: 8px;
            white-space: nowrap;
        }
        .price-dd-row.selectable {
            cursor: pointer;
        }
        .price-dd-row.selectable:hover { background: #f0f6ff; }
        .price-dd-label { flex: 1; color: #374151; }
        .price-dd-val { font-weight: 600; color: #111; font-variant-numeric: tabular-nums; }
        .price-dd-badge {
            font-size: 10px; padding: 1px 5px; border-radius: 3px;
            background: #fef9c3; color: #92400e; flex-shrink: 0;
        }
        .price-dd-badge.act { background: #fce7f3; color: #9d174d; }
        .price-dd-info { color: #9ca3af; font-size: 11.5px; }
        .price-dd-sep { border: none; border-top: 1px solid #e5e7eb; margin: 3px 0; }
        .price-dd-loading { padding: 8px 12px; color: #9ca3af; font-style: italic; }

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
            align-items: stretch;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 16px 14px;
            border-top: 1px solid var(--border);
        }

        .totals-comment {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .totals-comment-label {
            font-size: 10.5px;
            font-weight: 500;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .totals-comment textarea {
            width: 100%;
            resize: vertical;
            flex: 1;
            min-height: 0;
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text);
            background: transparent;
            border: 1px solid transparent;
            border-radius: 5px;
            padding: 5px 7px;
            line-height: 1.45;
            box-sizing: border-box;
            outline: none;
        }
        .totals-comment textarea:hover {
            border-color: var(--border);
            background: var(--bg);
        }
        .totals-comment textarea:focus {
            border-color: var(--accent);
            background: var(--bg);
        }

        .totals-inner {
            display: flex;
            flex-direction: column;
            min-width: 240px;
            max-width: 300px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 3px 0;
            font-size: 12.5px;
            color: var(--text-muted);
        }
        .totals-row-value {
            font-family: 'Geist Mono', monospace;
            font-weight: 600;
            color: var(--text);
            text-align: right;
        }
        .totals-row.big {
            padding: 6px 0 3px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }
        .totals-row.big .totals-row-value {
            font-size: 18px;
            font-weight: 700;
        }
        .totals-row.sub {
            font-size: 11.5px;
        }
        .totals-row.sub .totals-row-value {
            font-weight: 500;
            font-size: 12px;
        }

        .totals-divider {
            border: none;
            border-top: 1px solid var(--border-light);
            margin: 4px 0;
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

        /* ─── HISTORY PANEL — видалено, замінено на history-modal ─── */

        /* tab content */
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        #tab-positions { overflow-x: auto; }

        @media (max-width: 1100px) {
            .fields-area { grid-template-columns: 1fr; }
            .fields-col + .fields-col { border-left: none; padding-left: 0; border-top: 1px solid var(--border-light); padding-top: 12px; margin-top: 4px; }
            .fields-grid { grid-template-columns: repeat(2,1fr); }
            .totals-inner { min-width: unset; }
        }

        /* ── Wait call checkbox ── */
        .wait-call-label {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 400; color: var(--text-muted);
            cursor: pointer; user-select: none;
        }
        .wait-call-label input[type=checkbox] { cursor: pointer; accent-color: #f59e0b; }
        .wait-call-label:has(input:checked) { color: #b45309; font-weight: 600; }

        .cp-card-link {
            display: inline-flex; align-items: center; justify-content: center;
            width: 22px; height: 22px; border-radius: 5px;
            font-size: 13px; font-weight: 600; line-height: 1;
            color: var(--accent); text-decoration: none;
            flex-shrink: 0; margin-left: 2px;
        }
        .cp-card-link:hover { background: #ede9fe; color: #5b21b6; }

        /* ── Shipment quick-action buttons ── */
        .ship-action-sep { width: 1px; height: 16px; background: var(--border); flex-shrink: 0; margin: 0 2px; }
        .ship-actions-row { display: flex; align-items: center; gap: 6px; }
        .ship-action-btn {
            display: inline-flex; align-items: center; gap: 5px;
            height: 28px; padding: 0 10px;
            border: 1.5px solid; border-radius: 7px;
            font-size: 12px; font-weight: 600;
            cursor: pointer; white-space: nowrap; font-family: inherit;
            transition: background .15s, filter .1s;
            line-height: 1;
        }
        .ship-action-btn svg { flex-shrink: 0; }
        .ship-action-plus { font-size: 15px; font-weight: 700; line-height: 1; margin-right: 1px; }

        /* ТТН Нова Пошта — фірмовий червоно-помаранчевий */
        .ship-action-btn--np {
            background: #fff3f0; color: #c0392b; border-color: #f5a89a;
        }
        .ship-action-btn--np:hover { background: #ffe4de; filter: none; border-color: #e07060; }
        .ship-action-btn--np .ship-action-plus { color: #c0392b; }
        .ship-action-btn--np svg { stroke: #c0392b; }

        /* Доставка — бірюзовий */
        .ship-action-btn--del {
            background: #f0fdf9; color: #0f766e; border-color: #99e6d8;
        }
        .ship-action-btn--del:hover { background: #ccfbf1; border-color: #2dd4bf; }
        .ship-action-btn--del .ship-action-plus { color: #0f766e; }
        .ship-action-btn--del svg { stroke: #0f766e; }

        /* ── Counterparty picker ── */
        .cp-picker-wrap { position: relative; display: flex; align-items: center; gap: 4px; }
        .cp-picker-input { flex: 1; padding: 6px 9px; border: 1px solid var(--border); border-radius: 6px;
            font-size: 12.5px; font-family: inherit; color: var(--text); outline: none; background: var(--surface); }
        .cp-picker-input:focus { border-color: var(--accent); }
        .cp-picker-clear { background: none; border: none; cursor: pointer; font-size: 15px; color: var(--text-light);
            padding: 0 3px; line-height: 1; }
        .cp-picker-clear:hover { color: var(--text); }
        .cp-picker-dd { position: absolute; top: calc(100% + 3px); left: 0; right: 0; z-index: 8000;
            background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,.1); max-height: 240px; overflow-y: auto; }
        .cp-picker-opt { padding: 7px 12px; font-size: 12.5px; cursor: pointer; border-bottom: 1px solid var(--border-light); display: flex; align-items: baseline; gap: 7px; }
        .cp-picker-opt:last-child { border-bottom: none; }
        .cp-picker-opt:hover { background: var(--hover-row); }
        .cp-picker-opt-name { flex: 1; min-width: 0; }
        .cp-picker-opt-sub { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
        .cp-picker-type-badge { font-size: 10px; font-weight: 600; padding: 1px 5px; border-radius: 3px; white-space: nowrap; flex-shrink: 0; }
        .cp-picker-type-badge.t-company { background: #dbeafe; color: #1e40af; }
        .cp-picker-type-badge.t-fop     { background: #fef9c3; color: #854d0e; }
        .cp-picker-type-badge.t-person  { background: #f3f4f6; color: #374151; }

        /* ── dirty save button ── */
        .btn-save-dirty { box-shadow: 0 0 0 3px rgba(34,197,94,.35); }

        /* ── create doc dropdown ── */
        .create-doc-wrap { position: relative; display: inline-block; }
        .create-doc-menu {
            display: none; position: absolute; top: calc(100% + 4px); left: 0;
            background: #fff; border: 1px solid var(--border); border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,.12); min-width: 200px; z-index: 200;
            padding: 4px 0;
        }
        .create-doc-menu.open { display: block; }
        .create-doc-item {
            display: block; width: 100%; text-align: left; background: none; border: none;
            padding: 8px 14px; font-size: 13px; color: var(--text); cursor: pointer;
            white-space: nowrap;
        }
        .create-doc-item:hover { background: var(--hover-row); }

        /* ── Shipment cards ── */
        .shipment-card {
            background: var(--card-bg, #fff); border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 12px; font-size: 13px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .shipment-card-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .shipment-card-body { flex: 1; min-width: 0; }
        .shipment-card-title {
            font-weight: 600; font-size: 13px; color: var(--text);
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        }
        .shipment-card-num { font-family: monospace; font-size: 12.5px; color: #374151; }
        .shipment-card-meta { color: var(--text-muted); font-size: 12px; margin-top: 3px; line-height: 1.5; }
        .shipment-card-acts { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
        .ttn-status-created    { background: #e0f2fe; color: #0369a1; }
        .ttn-status-in_transit { background: #fef9c3; color: #854d0e; }
        .ttn-status-at_branch  { background: #ffedd5; color: #c2410c; }
        .ttn-status-delivered  { background: #dcfce7; color: #166534; }
        .ttn-status-returned   { background: #fce7f3; color: #9d174d; }
        .ttn-status-deleted    { background: #f3f4f6; color: #6b7280; }
        .nd-status-pending    { background: #e0f2fe; color: #0369a1; }
        .nd-status-sent       { background: #fef9c3; color: #854d0e; }
        .nd-status-delivered  { background: #dcfce7; color: #166534; }
        .nd-status-cancelled  { background: #f3f4f6; color: #6b7280; }

        /* ── NP TTN form inside modal ── */
        .np-form-section {
            font-size: 10px; font-weight: 700; color: #6b7280;
            text-transform: uppercase; margin: 10px 0 6px; letter-spacing: .5px;
        }
        .np-form-section:first-child { margin-top: 0; }
        .np-field-label { color: #9ca3af; font-size: 11px; display: block; margin-bottom: 3px; margin-top: 8px; }
        .np-field-label:first-child { margin-top: 0; }
        .np-inp {
            font-size: 12px; border: 1px solid #d1d5db; border-radius: 4px;
            padding: 4px 7px; width: 100%; box-sizing: border-box; background: #fff;
            height: 28px;
        }
        .np-inp:focus { outline: none; border-color: #6366f1; }
        .np-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .np-ac-wrap { position: relative; }
        .np-ac-dd {
            position: absolute; top: 100%; left: 0; right: 0; background: #fff;
            border: 1px solid #d1d5db; border-radius: 4px; max-height: 160px;
            overflow-y: auto; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,.12);
            font-size: 11px; display: none;
        }
        .np-ac-item {
            padding: 5px 8px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
            line-height: 1.3;
        }
        .np-ac-item:hover { background: #f0f6ff; }
        .np-ac-item .np-ac-sub { font-size: 10px; color: #9ca3af; }
        .np-form-error {
            display: none; color: #dc2626; font-size: 11px; margin-top: 8px;
            padding: 6px 8px; background: #fef2f2; border-radius: 4px; line-height: 1.4;
        }
    </style>
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
                <button type="button" id="btnSave" class="btn btn-save">Зберегти</button>
                <a href="/customerorder" class="btn btn-close">Закрити</a>
                <?php if (!$isNew && !empty($docTransitions)): ?>
                <div class="create-doc-wrap" id="createDocWrap">
                    <button type="button" class="btn" id="createDocBtn">Створити ▾</button>
                    <div class="create-doc-menu" id="createDocMenu">
                        <?php foreach ($docTransitions as $tr): ?>
                        <button type="button" class="create-doc-item"
                                data-to-type="<?= h($tr['to_type']) ?>"
                                data-link-type="<?= h($tr['link_type']) ?>"
                                data-order-id="<?= (int)$order['id'] ?>">
                            <?= h($tr['name_uk']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <button type="button" class="btn" disabled title="Спочатку збережіть замовлення">Створити ▾</button>
                <?php endif; ?>
                <?php if (!empty($order['id'])): ?>
                <button type="button" class="btn" id="printOpenBtn"
                        onclick="PrintModal.open('order', <?php echo (int)$order['id']; ?>, <?php echo (int)isset($order['organization_id']) ? (int)$order['organization_id'] : 0; ?>)">
                    Друк ▾
                </button>
                <?php else: ?>
                <button type="button" class="btn" disabled title="Спочатку збережіть замовлення">Друк ▾</button>
                <?php endif; ?>
                <?php if (!empty($currentCpId)): ?>
                <button type="button" class="btn" id="btnSendTpl" title="Надіслати клієнту">📤 Надіслати ▾</button>
                <button type="button" class="btn" id="btnOpenChat"
                        onclick="ChatModal.open(<?= (int)$currentCpId ?>)"
                        title="Відкрити чат з контрагентом">💬 Чат</button>
                <?php else: ?>
                <button type="button" class="btn" disabled title="Спочатку оберіть контрагента">📤 Надіслати ▾</button>
                <?php endif; ?>
                <label class="check-label">
                    <input type="checkbox" id="applicable" name="applicable" value="1" <?= checked_attr(field_value($order, 'applicable', 1)) ?>>
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
                <?php if (!empty($trafficSource)): ?>
                <div class="order-traffic-source" title="<?= h($trafficSource['utm_campaign'] ?: ($trafficSource['gclid'] ? 'gclid: '.substr($trafficSource['gclid'],0,20).'...' : '')) ?>">
                    <svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="flex-shrink:0"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 1.5C8 1.5 5 5 5 8s3 6.5 3 6.5M8 1.5C8 1.5 11 5 11 8s-3 6.5-3 6.5M1.5 8h13" stroke="currentColor" stroke-width="1.3"/></svg>
                    <?= h($trafficSource['label']) ?>
                    <?php if (!empty($trafficSource['utm_campaign'])): ?>
                    <span class="order-traffic-campaign"><?= h($trafficSource['utm_campaign']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!$isNew && (!empty($linkedDemands) || !empty($order['id_ms']))): ?>
                <div class="doc-title-links">
                    <?php foreach ($linkedDemands as $_dem): ?>
                    <a href="/demand/edit?id=<?= (int)$_dem['id'] ?>" class="doc-title-link">
                        Відвантаження № <?= h($_dem['number'] ?: ('#'.$_dem['id'])) ?> ↗
                    </a>
                    <?php endforeach; ?>
                    <?php if (!empty($order['id_ms'])): ?>
                    <a href="https://online.moysklad.ru/app/#customerorder/edit?id=<?= h($order['id_ms']) ?>"
                       target="_blank" class="doc-title-link">МС ↗</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status + payment + planned date row -->
            <div class="doc-meta-row">

                <!-- Order status as custom colored dropdown (StatusColors.php) -->
                <?php
                $_statusInlineStyles = array(
                    'draft'             => 'background:#f0f4f8; color:#6b7280;',
                    'new'               => 'background:#dbeafe; color:#1e40af;',
                    'confirmed'         => 'background:#ede9fe; color:#5b21b6;',
                    'in_progress'       => 'background:#fae8ff; color:#7e22ce;',
                    'waiting_payment'   => 'background:#fff4e5; color:#b26a00;',
                    'paid'              => 'background:#ccfbf1; color:#0f766e;',
                    'partially_shipped' => 'background:#ede9fe; color:#5b21b6;',
                    'shipped'           => 'background:#dcfce7; color:#15803d;',
                    'completed'         => 'background:#d1fae5; color:#065f46;',
                    'cancelled'         => 'background:#fee2e2; color:#b91c1c;',
                );
                $currentStatus = field_value($order, 'status', 'draft');
                $currentStyle  = isset($_statusInlineStyles[$currentStatus])
                    ? $_statusInlineStyles[$currentStatus]
                    : $_statusInlineStyles['draft'];
                ?>
                <input type="hidden" name="status" id="statusHidden" value="<?= h($currentStatus) ?>">
                <div class="status-dd" id="statusDd">
                    <button type="button" class="status-dd-btn" id="statusDdBtn" style="<?= $currentStyle ?>">
                        <span id="statusDdLabel"><?= h(StatusColors::label('customerorder', $currentStatus, $currentStatus)) ?></span>
                        <span class="dd-caret">▾</span>
                    </button>
                    <ul class="status-dd-menu" id="statusDdMenu">
                        <?php foreach (StatusColors::all('customerorder') as $_sv => $_se): ?>
                        <?php $_sStyle = isset($_statusInlineStyles[$_sv]) ? $_statusInlineStyles[$_sv] : ''; ?>
                        <li class="status-dd-opt" data-value="<?= h($_sv) ?>" data-style="<?= h($_sStyle) ?>" data-hex="<?= h($_se[2]) ?>">
                            <span class="opt-pill" style="background:<?= h($_se[2]) ?>"></span>
                            <?= h($_se[0]) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Payment status badge (read-only) -->
                <span class="ws-of-mini-badge <?= h($paymentStatus['badge_cls']) ?>" title="Статус оплати">₴ <?= h($paymentStatus['label']) ?></span>

                <!-- Shipment status badge (read-only) -->
                <span class="ws-of-mini-badge <?= h($shipmentStatus['badge_cls']) ?>" title="Статус відвантаження">📦 <?= h($shipmentStatus['label']) ?></span>

                <!-- Planned shipment date — compact, no big field -->
                <div class="planned-date-wrap" style="margin-left:10px;">
                    <svg class="planned-date-icon" id="plannedDateIcon" width="13" height="13" viewBox="0 0 16 16" fill="none" style="color:var(--text-light)" title="Обрати дату відвантаження"><rect x="1" y="3" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1v4M11 1v4M1 7h14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span>Відвантаження:</span>
                    <input type="date" name="planned_shipment_at" id="planned_shipment_at" value="<?= h($plannedShipDate) ?>">
                </div>

                <?php if (!$isNew): ?>
                <!-- Shipment action buttons -->
                <div class="ship-actions-row">
                    <button type="button" id="newTtnNpBtn" class="ship-action-btn ship-action-btn--np" title="Створити ТТН Нова Пошта">
                        <span class="ship-action-plus">+</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        ТТН Нова Пошта
                    </button>
                    <button type="button" id="newDeliveryBtn" class="ship-action-btn ship-action-btn--del" title="Додати самовивіз / кур'єрську доставку">
                        <span class="ship-action-plus">+</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        Самовивіз / доставка
                    </button>
                </div>
                <?php endif; ?>
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
                            <?php
                            $savedBankId = field_value($order, 'organization_bank_account_id');
                            ?>
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
                                        // Auto-select default when no saved value
                                        $isSel = $savedBankId !== ''
                                            ? selected($account['id'], $savedBankId)
                                            : (!empty($account['is_default']) ? 'selected' : '');
                                        ?>
                                        <option value="<?= (int)$account['id'] ?>"
                                            <?= $isSel ?>
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
                                <div class="cp-picker-wrap" id="cpPickerWrap">
                                    <input type="hidden" name="counterparty_id" id="counterparty_id" value="<?= h($currentCpId) ?>">
                                    <input type="text" id="cpPickerInput" class="cp-picker-input"
                                           value="<?= h($counterpartyName) ?>"
                                           placeholder="Пошук контрагента…"
                                           autocomplete="off">
                                    <button type="button" class="cp-picker-clear" id="cpPickerClear" title="Скинути контрагента"<?= $currentCpId ? '' : ' style="display:none"' ?>>×</button>
                                    <label class="wait-call-label" title="Клієнт чекає на дзвінок від менеджера" style="margin-left:6px;flex-shrink:0">
                                        <input type="checkbox" id="wait_call" name="wait_call" value="1"<?= !empty($order['wait_call']) ? ' checked' : '' ?>>
                                        <span>📞</span>
                                    </label>
                                    <a href="/counterparties/view?id=<?= h($currentCpId) ?>" target="_blank" id="cpCardLink" class="cp-card-link" title="Картка контрагента"<?= $currentCpId ? '' : ' style="display:none"' ?>>↗</a>
                                    <div class="cp-picker-dd" id="cpPickerDd" style="display:none"></div>
                                </div>
                            </div>

                            <div class="f" id="contactPersonField"<?= empty($initialContacts) ? ' style="display:none"' : '' ?>>
                                <label>Контактна особа</label>
                                <div class="cp-picker-wrap" id="personPickerWrap">
                                    <input type="hidden" name="contact_person_id" id="contact_person_id" value="<?= h($currentPersonId) ?>">
                                    <input type="text" id="personPickerInput" class="cp-picker-input"
                                           value="<?= h($contactPersonName) ?>"
                                           placeholder="Введіть ім'я…"
                                           autocomplete="off">
                                    <button type="button" class="cp-picker-clear" id="personPickerClear" title="Очистити"<?= $currentPersonId ? '' : ' style="display:none"' ?>>×</button>
                                    <div class="cp-picker-dd" id="personPickerDd" style="display:none"></div>
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
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?= (int)$proj['id'] ?>" <?= selected($proj['id'], field_value($order, 'project_id')) ?>><?= h($proj['name']) ?></option>
                                <?php endforeach; ?>
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

                        <div class="f">
                            <label>Спосіб доставки</label>
                            <select name="delivery_method_id" id="delivery_method_id">
                                <option value="">— Без доставки —</option>
                                <?php foreach ($deliveryMethods as $dm): ?>
                                    <option value="<?= (int)$dm['id'] ?>" <?= selected($dm['id'], field_value($order, 'delivery_method_id')) ?>><?= h($dm['name_uk']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="f">
                            <label>Спосіб оплати</label>
                            <select name="payment_method_id" id="payment_method_id">
                                <option value="">— Без оплати —</option>
                                <?php foreach ($paymentMethods as $pm): ?>
                                    <option value="<?= (int)$pm['id'] ?>" <?= selected($pm['id'], field_value($order, 'payment_method_id')) ?>><?= h($pm['name_uk']) ?></option>
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

        <!-- Positions tab -->
        <div class="tab-content active" id="tab-positions">
        <!-- Bulk actions (positions tab only) -->
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
                    <tr data-item-row="1" data-local-id="<?= (int)$item['id'] ?>" data-sum-changed="0">
                        <td class="text-c">
                            <input type="checkbox" class="row-check" name="selected_items[]" value="<?= (int)$item['id'] ?>">
                        </td>

                        <td>
                            <?php $art = field_value($item, 'sku') ?: field_value($item, 'product_article'); $pid = (int)field_value($item, 'product_id'); if ($art): ?><a href="/catalog?selected=<?= $pid ?>" target="_blank" style="font-size:11px;color:#9ca3af;margin-right:4px"><?= h($art) ?></a><?php endif; ?><a href="/catalog?selected=<?= $pid ?>" class="prod-name-link" target="_blank"><?= h(field_value($item, 'product_name')) ?></a>
                            <input type="hidden" data-field="item_id"     value="<?= (int)$item['id'] ?>">
                            <input type="hidden" data-field="product_id"  value="<?= h(field_value($item, 'product_id')) ?>">
                            <input type="hidden" data-field="weight"      value="<?= h(field_value($item, 'weight', 0)) ?>">
                        </td>

                        <td class="text-c">
                            <input type="text" data-field="unit" value="<?= h(field_value($item, 'unit')) ?>" style="width:42px; text-align:center;" readonly>
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="quantity" value="<?= h(field_value($item, 'quantity', 1)) ?>" style="width:72px; text-align:right;">
                        </td>

                        <td class="text-r price-cell">
                            <input type="text" data-field="price" value="<?= h(field_value($item, 'price', 0)) ?>" style="width:82px; text-align:right;">
                            <div class="price-dd"></div>
                        </td>

                        <td class="text-c">
                            <select data-field="vat_rate" style="width:82px; text-align:center;">
                                <option value="0" <?= selected('0', field_value($item, 'vat_rate', 0)) ?>>Без ПДВ</option>
                                <option value="20" <?= selected('20', field_value($item, 'vat_rate', 0)) ?>>20%</option>
                            </select>
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="discount_percent" value="<?= h(field_value($item, 'discount_percent', 0)) ?>" style="width:58px; text-align:right;">
                        </td>

                        <td class="text-r">
                            <input type="text" data-field="sum_row" value="<?= h(field_value($item, 'sum_row', 0)) ?>" style="width:90px; text-align:right; font-weight:500;">
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
                <div class="totals-comment">
                    <div class="totals-comment-label">Коментар</div>
                    <textarea id="order_description" name="description" placeholder="Коментар до замовлення…"><?= h(field_value($order, 'description', '')) ?></textarea>
                </div>
                <div class="totals-inner">
                    <div class="totals-row sub">
                        <span>Сума без ПДВ</span>
                        <span class="totals-row-value" id="summary-total-net"><?= number_format(array_sum(array_map(function($r){
                            $s=(float)$r['sum_row']; $v=(float)$r['vat_rate'];
                            return $v>0 ? $s/(1+$v/100) : $s;
                        }, $items)), 2, '.', ' ') ?></span>
                    </div>
                    <div class="totals-row sub">
                        <span>ПДВ</span>
                        <span class="totals-row-value" id="summary-total-vat"><?= number_format(array_sum(array_map(function($r){
                            $s=(float)$r['sum_row']; $v=(float)$r['vat_rate'];
                            if($v>0){ $net=$s/(1+$v/100); return $s-$net; } return 0;
                        }, $items)), 2, '.', ' ') ?></span>
                    </div>
                    <hr class="totals-divider">
                    <div class="totals-row big">
                        <span>До сплати</span>
                        <span class="totals-row-value" id="summary-total-sum"><?= number_format(array_sum(array_map(function($r){ return (float)$r['sum_row']; }, $items)), 2, '.', ' ') ?></span>
                    </div>
                    <?php if (!empty($marginData)): ?>
                    <hr class="totals-divider">
                    <div class="totals-row sub">
                        <span>Собівартість</span>
                        <span class="totals-row-value" id="summary-cost"><?= number_format($marginData['cost_total'], 2, '.', ' ') ?></span>
                    </div>
                    <div class="totals-row">
                        <span>Маржа</span>
                        <span class="totals-row-value <?= $marginData['margin'] >= 0 ? 'text-green' : 'text-red' ?>" id="summary-margin"><?= number_format($marginData['margin'], 2, '.', ' ') ?> <span style="font-size:11px;font-weight:500;opacity:.7">(<?= $marginData['margin_pct'] ?>%)</span></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /tab-positions -->

        <!-- Related docs tab -->
        <div class="tab-content" id="tab-related">

            <!-- ── Пов'язані документи (граф) ── -->
            <div id="reldocs-wrap">
                <div style="display:flex; align-items:center; padding:10px 14px 6px; gap:8px;">
                    <button type="button" class="btn btn-sm" id="linkDocBtn">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="margin-right:5px;vertical-align:middle"><path d="M6.5 9.5a3.5 3.5 0 0 0 4.95 0l2-2a3.5 3.5 0 0 0-4.95-4.95l-1.25 1.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 6.5a3.5 3.5 0 0 0-4.95 0l-2 2a3.5 3.5 0 0 0 4.95 4.95l1.25-1.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        Зв'язати документ
                    </button>
                </div>
                <div id="reldocs-loading" style="display:none; padding:40px; text-align:center; color:#6b7280; font-size:13px;">Завантаження…</div>
                <div id="reldocs-empty"   style="display:none; padding:40px; text-align:center; color:#9ca3af; font-size:13px;">Пов'язані документи відсутні</div>
                <div id="reldocs-graph-wrap" style="overflow:auto; min-height:120px; padding:6px 10px 10px;">
                    <svg id="reldocs-svg" xmlns="http://www.w3.org/2000/svg" style="display:block; font-family:'Geist',system-ui,sans-serif;"></svg>
                </div>
            </div>
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

<!-- ══ LINK DOCUMENT MODAL ══ -->
<div class="modal-overlay" id="linkDocModal" style="display:none;">
    <div class="modal-box" style="width:860px; max-width:98vw;">
        <div class="modal-head">
            <span>Зв'язати документ із замовленням</span>
            <button class="modal-close" id="linkDocModalClose">&#x2715;</button>
        </div>
        <div class="modal-body">
            <!-- filters -->
            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:11.5px; color:var(--text-muted); margin-bottom:3px;">Тип документу</label>
                    <select id="ldDocType" style="height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; min-width:200px;">
                        <?php foreach ($docTransitions as $tr): ?>
                            <option value="<?= h($tr['to_type']) ?>"><?= h($tr['name_uk']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:11.5px; color:var(--text-muted); margin-bottom:3px;">Дата від</label>
                    <input type="date" id="ldDateFrom" style="height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size:11.5px; color:var(--text-muted); margin-bottom:3px;">Дата до</label>
                    <input type="date" id="ldDateTo" style="height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div style="flex:1; min-width:160px;">
                    <label style="display:block; font-size:11.5px; color:var(--text-muted); margin-bottom:3px;">Контрагент</label>
                    <input type="text" id="ldCounterparty" placeholder="Пошук за іменем…" style="height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; width:100%; box-sizing:border-box;">
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" id="ldSearchBtn" style="height:32px;">Знайти</button>
                </div>
            </div>
            <!-- results -->
            <div id="ldResultsWrap" style="min-height:120px;">
                <div id="ldResultsEmpty" style="display:none; padding:30px; text-align:center; color:#9ca3af; font-size:13px;">Документів не знайдено</div>
                <div id="ldResultsLoading" style="display:none; padding:30px; text-align:center; color:#6b7280; font-size:13px;">Завантаження…</div>
                <table class="crm-table" id="ldResultsTable" style="display:none;">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="ldCheckAll"></th>
                            <th>Тип</th>
                            <th>№</th>
                            <th>Дата</th>
                            <th>Контрагент</th>
                            <th style="text-align:right;">Сума</th>
                        </tr>
                    </thead>
                    <tbody id="ldResultsTbody"></tbody>
                </table>
            </div>
            <div id="ldError" class="modal-error" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="ldLinkBtn" disabled>Прив'язати</button>
            <button type="button" class="btn" id="ldCancelBtn">Скасувати</button>
            <span id="ldSelectedCount" style="font-size:12px; color:var(--text-muted); margin-left:8px;"></span>
        </div>
    </div>
</div>

<!-- ══ TTN NP CREATE MODAL ══ -->
<div class="modal-overlay" id="newTtnModal">
    <div class="modal-box" style="width:560px; max-width:98vw;">
        <div class="modal-head">
            <span>Нова ТТН Нова Пошта</span>
            <button class="modal-close" id="newTtnModalClose">&#x2715;</button>
        </div>
        <div class="modal-body" id="npTtnBody" style="overflow-y:auto; max-height:calc(100vh - 180px); padding:14px 16px;">
            <div style="text-align:center; color:#9ca3af; padding:30px;">Завантаження…</div>
        </div>
    </div>
</div>

<!-- ══ DELIVERY (PICKUP/COURIER) MODAL ══ -->
<div class="modal-overlay" id="newDeliveryModal">
    <div class="modal-box" style="width:400px; max-width:98vw;">
        <div class="modal-head">
            <span id="newDeliveryModalTitle">Відправлення</span>
            <button class="modal-close" id="newDeliveryModalClose">&#x2715;</button>
        </div>
        <div class="modal-body" style="padding:16px;">
            <input type="hidden" id="ndDeliveryId" value="0">
            <div class="form-row" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Спосіб доставки</label>
                <select id="ndMethodId" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
                    <?php foreach ($deliveryMethods as $dm): ?>
                    <?php if (empty($dm['has_ttn'])): ?>
                    <option value="<?= (int)$dm['id'] ?>"><?= h($dm['name_uk']) ?></option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Статус</label>
                <select id="ndStatus" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
                    <option value="pending">Очікує</option>
                    <option value="sent">Відправлено</option>
                    <option value="delivered">Доставлено</option>
                    <option value="cancelled">Скасовано</option>
                </select>
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Коментар</label>
                <textarea id="ndComment" rows="2" style="width:100%; font-size:13px; padding:6px 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; resize:vertical;"></textarea>
            </div>
            <div id="ndError" class="modal-error" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="ndSaveBtn">Зберегти</button>
            <button type="button" class="btn" id="ndCancelBtn">Скасувати</button>
        </div>
    </div>
</div>

<!-- history-modal монтируется динамически через history-modal.js -->

<script>
/* ══ INIT STATE ══ */
var _orderId = <?= !$isNew ? (int)$order['id'] : 0 ?>;
var _isNew   = <?= $isNew ? 'true' : 'false' ?>;

var _stateItems = <?= json_encode(array_values($items)) ?>.map(function(it) {
    var copy = JSON.parse(JSON.stringify(it));
    copy._localId = String(it.id);
    copy.id       = parseInt(it.id) || null;
    return copy;
});
var _state    = { order: <?= json_encode(!empty($order) ? $order : new stdClass()) ?>, items: _stateItems };
var _original = JSON.parse(JSON.stringify(_state));

/* ══ HELPERS ══ */
function toFloat(v, fallback) {
    var n = parseFloat(String(v || '').replace(',', '.').trim());
    return isNaN(n) ? fallback : n;
}
function fmt2(v) { return (Math.round(v * 100) / 100).toFixed(2); }
function fmt3(v) { return (Math.round(v * 1000) / 1000).toFixed(3); }

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function stateItemByLocalId(localId) {
    for (var i = 0; i < _state.items.length; i++) {
        if (String(_state.items[i]._localId) === String(localId)) return _state.items[i];
    }
    return null;
}

/* ══ ITEM CALCULATION ══ */
function calcItem(item) {
    var gross   = Math.round(item.quantity * item.price * 100) / 100;
    var discAmt = Math.round(gross * (item.discount_percent || 0) / 100 * 100) / 100;
    item.sum_row         = Math.round((gross - discAmt) * 100) / 100;
    item.discount_amount = discAmt;
    item.vat_amount      = (item.vat_rate > 0)
        ? Math.round((item.sum_row - item.sum_row / (1 + item.vat_rate / 100)) * 100) / 100 : 0;
}

/* ══ SYNC DOM ROW → STATE ══ */
function syncRowToState(tr) {
    var item = stateItemByLocalId(tr.dataset.localId);
    if (!item) return;
    var qInp = tr.querySelector('[data-field="quantity"]');
    var pInp = tr.querySelector('[data-field="price"]');
    var dInp = tr.querySelector('[data-field="discount_percent"]');
    var vSel = tr.querySelector('[data-field="vat_rate"]');
    var sInp = tr.querySelector('[data-field="sum_row"]');
    item.quantity         = toFloat(qInp ? qInp.value : 1, 1);
    item.price            = toFloat(pInp ? pInp.value : 0, 0);
    item.discount_percent = toFloat(dInp ? dInp.value : 0, 0);
    item.vat_rate         = toFloat(vSel ? vSel.value : 0, 0);
    // Back-calculate price from sum if user edited sum directly
    if (tr.dataset.sumChanged === '1') {
        var enteredSum = toFloat(sInp ? sInp.value : 0, 0);
        var q = item.quantity, d = item.discount_percent;
        item.price = (q > 0) ? (enteredSum / q / (1 - d / 100)) : 0;
        item.price = Math.round(item.price * 100) / 100;
        if (pInp) pInp.value = item.price.toFixed(2);
        tr.dataset.sumChanged = '0';
    }
    calcItem(item);
    if (sInp) sInp.value = item.sum_row.toFixed(2);
    renderDocTotals();
    markDirty();
}

/* ══ RENDER TOTALS ══ */
function renderDocTotals() {
    var items = _state.items.filter(function(it) { return !it._deleted; });
    var count = 0, totalQty = 0, totalWeight = 0, totalNet = 0, totalVat = 0, totalSum = 0;
    items.forEach(function(it) {
        count++;
        var qty    = toFloat(it.quantity, 0);
        var sumRow = toFloat(it.sum_row, 0);
        var vatAmt = toFloat(it.vat_amount, 0);
        totalQty    += qty;
        totalWeight += qty * toFloat(it.weight, 0);
        totalVat    += vatAmt;
        totalSum    += sumRow;
        totalNet    += sumRow - vatAmt;
    });
    var g = function(id) { return document.getElementById(id); };
    if (g('summary-total-items'))  g('summary-total-items').textContent  = count;
    if (g('summary-total-qty'))    g('summary-total-qty').textContent    = fmt3(totalQty);
    if (g('summary-total-weight')) g('summary-total-weight').textContent = fmt3(totalWeight);
    if (g('summary-total-net'))    g('summary-total-net').textContent    = fmt2(totalNet);
    if (g('summary-total-vat'))    g('summary-total-vat').textContent    = fmt2(totalVat);
    if (g('summary-total-sum'))    g('summary-total-sum').textContent    = fmt2(totalSum);
}

/* ══ DIRTY FLAG ══ */
function markDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.add('btn-save-dirty');
}
function clearDirty() {
    var btn = document.getElementById('btnSave');
    if (btn) btn.classList.remove('btn-save-dirty');
}

/* ══ BIND ROW EVENTS ══ */
function bindItemRow(tr) {
    var qInp = tr.querySelector('[data-field="quantity"]');
    var pInp = tr.querySelector('[data-field="price"]');
    var dInp = tr.querySelector('[data-field="discount_percent"]');
    var vSel = tr.querySelector('[data-field="vat_rate"]');
    var sInp = tr.querySelector('[data-field="sum_row"]');

    if (sInp) {
        sInp.addEventListener('input',  function() { tr.dataset.sumChanged = '1'; markDirty(); });
        sInp.addEventListener('blur',   function() { syncRowToState(tr); });
    }
    if (qInp) qInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (pInp) pInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (vSel) vSel.addEventListener('change', function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });
    if (dInp) dInp.addEventListener('blur',   function() { tr.dataset.sumChanged = '0'; syncRowToState(tr); });

    var delBtn = tr.querySelector('.item-del-btn');
    if (delBtn) {
        delBtn.addEventListener('click', function() {
            if (!confirm('Видалити рядок?')) return;
            // Close dropdown menu if open
            tr.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
            var item = stateItemByLocalId(tr.dataset.localId);
            if (item) item._deleted = true;
            tr.remove();
            renderDocTotals();
            markDirty();
        });
    }

    // Price picker dropdown
    if (pInp) {
        var priceDd = tr.querySelector('.price-dd');
        if (priceDd) bindPricePicker(tr, pInp, priceDd);
    }
}

/* ══ PRICE PICKER ══ */
var _priceCache = {};

function bindPricePicker(tr, inp, dd) {
    var _mouseInDd = false;

    inp.addEventListener('focus', function() {
        var pid = parseInt((tr.querySelector('[data-field="product_id"]') || {}).value) || 0;
        if (!pid) return;
        openPriceDd(pid, tr, inp, dd);
    });

    inp.addEventListener('blur', function() {
        if (_mouseInDd) return;
        closePriceDd(dd);
    });

    dd.addEventListener('mouseenter', function() { _mouseInDd = true; });
    dd.addEventListener('mouseleave', function() { _mouseInDd = false; });
}

function openPriceDd(pid, tr, inp, dd) {
    dd.innerHTML = '<div class="price-dd-loading">Завантаження…</div>';
    dd.classList.add('open');

    if (_priceCache[pid]) {
        renderPriceDd(pid, tr, inp, dd, _priceCache[pid]);
        return;
    }

    fetch('/customerorder/api/get_product_prices?product_id=' + pid)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { dd.innerHTML = ''; dd.classList.remove('open'); return; }
            _priceCache[pid] = data;
            renderPriceDd(pid, tr, inp, dd, data);
        })
        .catch(function() { dd.innerHTML = ''; dd.classList.remove('open'); });
}

function closePriceDd(dd) {
    dd.classList.remove('open');
    dd.innerHTML = '';
}

function fmt2n(v) {
    if (v === null || v === undefined || v === '') return null;
    var n = parseFloat(v);
    return isNaN(n) ? null : n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
}

function renderPriceDd(pid, tr, inp, dd, data) {
    var rows = '';
    var hasAny = false;

    function makeSelectable(label, val, badgeText, badgeCls) {
        var fmtVal = fmt2n(val);
        if (fmtVal === null) return '';
        hasAny = true;
        var badge = badgeText ? '<span class="price-dd-badge ' + (badgeCls||'') + '">' + badgeText + '</span>' : '';
        return '<div class="price-dd-row selectable" data-pick="' + parseFloat(val).toFixed(4) + '">'
            + '<span class="price-dd-label">' + label + '</span>'
            + badge
            + '<span class="price-dd-val">' + fmtVal + '</span>'
            + '</div>';
    }

    rows += makeSelectable('Роздрібна', data.price_sale, '', '');
    if (data.price_act) {
        rows += makeSelectable('Акційна', data.price_act, 'акція', 'act');
    }
    rows += makeSelectable('Оптова', data.price_wholesale, '', '');
    rows += makeSelectable('Дилерська', data.price_dealer, '', '');

    var tiers = data.qty_tiers || [];
    if (tiers.length > 0) {
        if (hasAny) rows += '<hr class="price-dd-sep">';
        rows += '<div class="price-dd-row"><span class="price-dd-label price-dd-info">Знижки від кількості</span></div>';
        for (var i = 0; i < tiers.length; i++) {
            var t = tiers[i];
            var pct = t.discount_percent > 0 ? ' <span class="price-dd-badge">−' + parseFloat(t.discount_percent).toFixed(0) + '%</span>' : '';
            rows += '<div class="price-dd-row">'
                + '<span class="price-dd-label price-dd-info">від ' + t.qty + ' шт.</span>'
                + pct
                + '<span class="price-dd-val price-dd-info">' + fmt2n(t.price) + '</span>'
                + '</div>';
        }
    }

    if (!hasAny && tiers.length === 0) {
        dd.classList.remove('open');
        dd.innerHTML = '';
        return;
    }

    dd.innerHTML = rows;

    // Bind click on selectable rows
    dd.querySelectorAll('.price-dd-row.selectable').forEach(function(row) {
        row.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var val = parseFloat(row.dataset.pick || 0).toFixed(2);
            inp.value = val;
            inp.dispatchEvent(new Event('input'));
            tr.dataset.sumChanged = '0';
            syncRowToState(tr);
            markDirty();
            closePriceDd(dd);
            inp.focus();
        });
    });
}

/* ══ BUILD NEW ROW HTML ══ */
function buildNewRowHtml(p) {
    var pid = parseInt(p.product_id) || 0;
    var art = esc(p.product_article || '');
    var nm  = esc(p.name || '');
    var pr  = parseFloat(p.price || 0).toFixed(2);
    var wt  = parseFloat(p.weight || 0);
    return '<td class="text-c"><input type="checkbox" class="row-check"></td>'
        + '<td>'
        +   (art ? ('<a href="/catalog?selected=' + pid + '" class="prod-name-link" style="font-size:11px;color:#9ca3af;margin-right:4px" target="_blank">' + art + '</a>') : '')
        +   '<span class="prod-name-link">' + nm + '</span>'
        +   '<input type="hidden" data-field="item_id" value="">'
        +   '<input type="hidden" data-field="product_id" value="' + pid + '">'
        +   '<input type="hidden" data-field="weight" value="' + wt + '">'
        + '</td>'
        + '<td class="text-c"><input type="text" data-field="unit" value="' + esc(p.unit||'') + '" style="width:42px;text-align:center;" readonly></td>'
        + '<td class="text-r"><input type="text" data-field="quantity" value="1" style="width:72px;text-align:right;"></td>'
        + '<td class="text-r price-cell"><input type="text" data-field="price" value="' + pr + '" style="width:82px;text-align:right;"><div class="price-dd"></div></td>'
        + '<td class="text-c"><select data-field="vat_rate" style="width:82px;text-align:center;"><option value="0">Без ПДВ</option><option value="20">20%</option></select></td>'
        + '<td class="text-r"><input type="text" data-field="discount_percent" value="" placeholder="0" style="width:58px;text-align:right;"></td>'
        + '<td class="text-r"><input type="text" data-field="sum_row" value="' + pr + '" style="width:90px;text-align:right;font-weight:500;"></td>'
        + '<td class="text-r">0.000</td><td class="text-r">—</td><td class="text-r">—</td><td class="text-r">—</td><td class="text-r">—</td>'
        + '<td class="row-actions text-c">'
        +   '<button type="button" class="row-dots" title="Дії">···</button>'
        +   '<div class="row-menu">'
        +     '<button class="row-menu-item danger item-del-btn" type="button">'
        +       '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4h12M6 4V2h4v2M3 4l1 10h8l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>'
        +       ' Видалити'
        +     '</button>'
        +   '</div>'
        + '</td>';
}

/* ══ SAVE ORDER (AJAX) ══ */
function saveOrder() {
    if (_isNew) {
        // New order — use regular form submit
        var form = document.querySelector('form[action="/customerorder/save"]');
        if (form) form.submit();
        return;
    }

    var saveBtn = document.getElementById('btnSave');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Збереження…'; }

    // Sync all visible rows to state first
    document.querySelectorAll('#positionsTable tbody tr[data-local-id]').forEach(function(tr) {
        syncRowToState(tr);
    });

    function getVal(id) { var el = document.getElementById(id); return el ? el.value : ''; }
    function getChk(id) { var el = document.getElementById(id); return (el && el.checked) ? '1' : '0'; }

    var body = 'order_id='            + encodeURIComponent(_orderId)
        + '&version='                 + encodeURIComponent(parseInt(_state.order.version) || 0)
        + '&status='                  + encodeURIComponent(getVal('statusHidden'))
        + '&organization_id='         + encodeURIComponent(getVal('organization_id'))
        + '&manager_employee_id='     + encodeURIComponent(getVal('manager_employee_id'))
        + '&delivery_method_id='      + encodeURIComponent(getVal('delivery_method_id'))
        + '&payment_method_id='       + encodeURIComponent(getVal('payment_method_id'))
        + '&counterparty_id='         + encodeURIComponent(getVal('counterparty_id'))
        + '&contact_person_id='       + encodeURIComponent(getVal('contact_person_id'))
        + '&organization_bank_account_id=' + encodeURIComponent(getVal('organization_bank_account_id'))
        + '&contract_id='             + encodeURIComponent(getVal('contract_id'))
        + '&project_id='              + encodeURIComponent(getVal('project_id'))
        + '&sales_channel='           + encodeURIComponent(getVal('sales_channel'))
        + '&currency_code='           + encodeURIComponent(getVal('currency_code'))
        + '&store_id='                + encodeURIComponent(getVal('store_id'))
        + '&planned_shipment_at='     + encodeURIComponent(getVal('planned_shipment_at'))
        + '&applicable='              + encodeURIComponent(getChk('applicable'))
        + '&wait_call='               + encodeURIComponent(getChk('wait_call'))
        + '&description='             + encodeURIComponent(getVal('order_description'))
        + '&items='                   + encodeURIComponent(JSON.stringify(_state.items));

    fetch('/counterparties/api/save_order', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        if (res.conflict) {
            if (confirm('Замовлення було змінено іншим користувачем.\nОновити сторінку та втратити незбережені зміни?')) {
                window.location.reload();
            }
            return;
        }
        if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
        clearDirty();
        showToast('Збережено ✓');
        // Reload to show fresh totals, history, new item IDs
        window.location.href = '/customerorder/edit?id=' + _orderId + '&saved=1';
    }).catch(function() {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Зберегти'; }
        showToast('Помилка з\'єднання', true);
    });
}

/* ══ TABS ══ */
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
            RelDocsGraph.load(<?php echo (int)(isset($order['id']) ? $order['id'] : 0); ?>);
            ShipmentsPanel.load(<?php echo (int)(isset($order['id']) ? $order['id'] : 0); ?>);
        }
    });
});

/* ══ RELATED DOCS GRAPH ══ */
var RelDocsGraph = (function() {

    var _currentOrderId = 0;

    // ── visual constants ──────────────────────────────────────────────────────
    var NW = 190, NH = 96, STATUS_H = 22; // node width / height / status bar
    var COL_W = 240;                       // column step
    var ROW_H = 114;                       // row step
    var PAD_X = 20, PAD_Y = 30;           // canvas padding

    var TYPE_NAME = {
        customerorder: 'Замовлення покупця',
        demand:        'Відвантаження',
        ttn_np:        'ТТН Нова Пошта',
        ttn_up:        'ТТН Укрпошта',
        cashin:        'Касовий ордер',
        paymentin:     'Вхідний платіж',
        cashout:       'Видатковий ордер',
        paymentout:    'Вихідний платіж',
        salesreturn:   'Повернення покупця',
        overflow:      '…',
    };

    // status → bottom-bar hex color (generated from StatusColors.php)
    var STATUS_COLOR = (function() {
        var m = {};
        <?php
        foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
            foreach (StatusColors::all($_dt) as $_s => $_e) {
                echo "m['" . $_s . "'] = '" . $_e[2] . "';\n        ";
            }
        }
        ?>
        return m;
    }());

    // status → label (generated from StatusColors.php)
    var STATUS_LABEL_MAP = (function() {
        var m = {};
        <?php
        foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
            foreach (StatusColors::all($_dt) as $_s => $_e) {
                echo "m['" . $_s . "'] = " . json_encode($_e[0]) . ";\n        ";
            }
        }
        ?>
        return m;
    }());

    var NS = 'http://www.w3.org/2000/svg';

    function svgEl(tag, attrs) {
        var el = document.createElementNS(NS, tag);
        if (attrs) Object.keys(attrs).forEach(function(k) { el.setAttribute(k, attrs[k]); });
        return el;
    }

    // ── layout ────────────────────────────────────────────────────────────────
    function assignPositions(nodes) {
        // Group by column
        var cols = {};
        nodes.forEach(function(n) {
            var c = n.col || 0;
            if (!cols[c]) cols[c] = [];
            cols[c].push(n);
        });

        // Find max column count to determine SVG height
        var maxRows = 0;
        Object.keys(cols).forEach(function(c) { if (cols[c].length > maxRows) maxRows = cols[c].length; });
        var svgH = Math.max(maxRows * ROW_H + PAD_Y * 2, NH + PAD_Y * 2);

        // For each column assign x (fixed) and y (centered)
        Object.keys(cols).forEach(function(c) {
            var colNodes = cols[c];
            var colH = colNodes.length * ROW_H - (ROW_H - NH);
            var startY = Math.round((svgH - colH) / 2);
            colNodes.forEach(function(n, i) {
                n._x = PAD_X + parseInt(c, 10) * COL_W;
                n._y = startY + i * ROW_H;
            });
        });

        // Determine columns used to size SVG width
        var maxCol = 0;
        nodes.forEach(function(n) { if ((n.col || 0) > maxCol) maxCol = n.col || 0; });
        var svgW = PAD_X * 2 + (maxCol + 1) * COL_W - (COL_W - NW);

        return { w: svgW, h: svgH };
    }

    // ── helpers ───────────────────────────────────────────────────────────────
    function trunc(s, max) {
        s = String(s || '');
        return s.length > max ? s.slice(0, max - 1) + '…' : s;
    }
    function fmtMoment(m) {
        if (!m) return '';
        // "2025-12-31 14:05:00" → "31.12.2025"
        var p = String(m).split(' ')[0].split('-');
        if (p.length < 3) return m;
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    // ── rendering ─────────────────────────────────────────────────────────────
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
        svg.setAttribute('width',  dim.w);
        svg.setAttribute('height', dim.h);
        svg.setAttribute('viewBox', '0 0 ' + dim.w + ' ' + dim.h);
        svg.style.display = 'block';

        // ── defs: clipPaths for rounded status bars ───────────────────────────
        var defs = svgEl('defs');
        data.nodes.forEach(function(node) {
            var cp = svgEl('clipPath', { id: 'clip-' + node.id });
            var cr = svgEl('rect', {
                x: node._x, y: node._y,
                width: NW, height: NH,
                rx: '8', ry: '8',
            });
            cp.appendChild(cr);
            defs.appendChild(cp);
        });
        svg.appendChild(defs);

        // ── edges (orthogonal, dashed) ────────────────────────────────────────
        var edgeGroup = svgEl('g', { 'class': 'edges' });
        svg.appendChild(edgeGroup);
        var edgeEls = {};

        data.edges.forEach(function(edge, idx) {
            var src = nodeMap[edge.from];
            var tgt = nodeMap[edge.to];
            if (!src || !tgt) return;

            var x1, y1, x2, y2, d;
            if (src._x < tgt._x) {
                // forward: right-center → left-center, orthogonal
                x1 = src._x + NW;
                y1 = src._y + Math.round(NH / 2);
                x2 = tgt._x;
                y2 = tgt._y + Math.round(NH / 2);
                var mx = Math.round((x1 + x2) / 2);
                d = 'M' + x1 + ',' + y1
                  + ' H' + mx
                  + ' V' + y2
                  + ' H' + x2;
            } else {
                // backward: bottom-center → bottom-center
                x1 = src._x + Math.round(NW / 2);
                y1 = src._y + NH;
                x2 = tgt._x + Math.round(NW / 2);
                y2 = tgt._y + NH;
                var my = Math.max(y1, y2) + 18;
                d = 'M' + x1 + ',' + y1
                  + ' V' + my
                  + ' H' + x2
                  + ' V' + y2;
            }

            var path = svgEl('path', {
                d: d,
                fill: 'none',
                stroke: '#c5ccd6',
                'stroke-width': '1.5',
                'stroke-dasharray': '5,3',
                'class': 'edge-path',
                'data-edge': idx,
            });
            edgeGroup.appendChild(path);
            edgeEls[idx] = path;
        });

        // ── nodes ─────────────────────────────────────────────────────────────
        var nodeGroup = svgEl('g', { 'class': 'nodes' });
        svg.appendChild(nodeGroup);

        data.nodes.forEach(function(node) {
            var x = node._x, y = node._y;
            var isCurrent  = node.current  === true;
            var isOverflow = node.type === 'overflow';

            var statusStr  = String(node.status || '');
            var statusCol  = STATUS_COLOR[statusStr] || '#9ca3af';
            var statusLbl  = STATUS_LABEL_MAP[statusStr] || statusStr;

            var bgFill     = isCurrent ? '#1a1d23' : '#ffffff';
            var textMain   = isCurrent ? '#ffffff' : '#1a1d23';
            var textMuted  = isCurrent ? '#9ca3af' : '#6b7280';
            var borderCol  = isCurrent ? '#1a1d23' : '#e2e7ef';

            var g = svgEl('g', {
                'data-node': node.id,
                style: 'cursor:' + (node.url ? 'pointer' : 'default'),
            });

            // Card background
            var rect = svgEl('rect', {
                x: x, y: y,
                width: NW, height: NH,
                rx: '8', ry: '8',
                fill: bgFill,
                stroke: borderCol,
                'stroke-width': '1',
                'class': 'node-rect',
            });
            g.appendChild(rect);

            if (isOverflow) {
                // Simple overflow node
                var ovt = svgEl('text', {
                    x: x + NW / 2, y: y + NH / 2,
                    'text-anchor': 'middle',
                    'dominant-baseline': 'middle',
                    'font-size': '16',
                    fill: '#9ca3af',
                    'font-family': 'inherit',
                });
                ovt.textContent = '…';
                g.appendChild(ovt);
            } else {
                // ── top row: type name + checkmark ────────────────────────────
                var typeName = trunc(TYPE_NAME[node.type] || node.type, 22);
                var tnEl = svgEl('text', {
                    x: x + 10, y: y + 16,
                    'font-size': '10',
                    'font-weight': '600',
                    fill: textMuted,
                    'font-family': 'inherit',
                });
                tnEl.textContent = typeName;
                g.appendChild(tnEl);

                // checkmark if has status 'shipped','arrived','delivered','completed','paid'
                var doneStatuses = {shipped:1,arrived:1,delivered:1,completed:1,paid:1};
                if (doneStatuses[statusStr]) {
                    var ck = svgEl('text', {
                        x: x + NW - 10, y: y + 16,
                        'text-anchor': 'end',
                        'font-size': '11',
                        fill: statusCol,
                        'font-family': 'inherit',
                    });
                    ck.textContent = '✓';
                    g.appendChild(ck);
                }

                // ── divider ───────────────────────────────────────────────────
                var div = svgEl('line', {
                    x1: x + 10, y1: y + 22,
                    x2: x + NW - 10, y2: y + 22,
                    stroke: isCurrent ? '#3a3f4a' : '#eaecf0',
                    'stroke-width': '1',
                });
                g.appendChild(div);

                // ── number ────────────────────────────────────────────────────
                var numStr = node.number ? '№' + trunc(node.number, 14) : '—';
                var numEl = svgEl('text', {
                    x: x + 10, y: y + 36,
                    'font-size': '11',
                    'font-weight': '700',
                    fill: textMain,
                    'font-family': 'inherit',
                });
                numEl.textContent = numStr;
                g.appendChild(numEl);

                // ── date ──────────────────────────────────────────────────────
                var dateStr = fmtMoment(node.moment);
                if (dateStr) {
                    var dateEl = svgEl('text', {
                        x: x + NW - 10, y: y + 36,
                        'text-anchor': 'end',
                        'font-size': '10',
                        fill: textMuted,
                        'font-family': 'inherit',
                    });
                    dateEl.textContent = dateStr;
                    g.appendChild(dateEl);
                }

                // ── amount ────────────────────────────────────────────────────
                if (node.amount) {
                    var amtEl = svgEl('text', {
                        x: x + 10, y: y + NH - STATUS_H - 8,
                        'font-size': '12',
                        'font-weight': '700',
                        fill: textMain,
                        'font-family': 'inherit',
                    });
                    amtEl.textContent = trunc(node.amount, 18);
                    g.appendChild(amtEl);
                }

                // ── status bar (bottom, clipped to card corners) ───────────────
                var barY = y + NH - STATUS_H;
                var barGroup = svgEl('g', { 'clip-path': 'url(#clip-' + node.id + ')' });

                var bar = svgEl('rect', {
                    x: x, y: barY,
                    width: NW, height: STATUS_H,
                    fill: statusCol,
                    opacity: isCurrent ? '0.85' : '1',
                });
                barGroup.appendChild(bar);

                if (statusLbl) {
                    var barTxt = svgEl('text', {
                        x: x + NW / 2, y: barY + STATUS_H / 2 + 1,
                        'text-anchor': 'middle',
                        'dominant-baseline': 'middle',
                        'font-size': '9.5',
                        'font-weight': '600',
                        fill: '#ffffff',
                        'font-family': 'inherit',
                    });
                    barTxt.textContent = trunc(statusLbl, 24);
                    barGroup.appendChild(barTxt);
                }
                g.appendChild(barGroup);
            }

            // ── hover & click ─────────────────────────────────────────────────
            g.addEventListener('mouseenter', function() {
                if (!isCurrent) rect.setAttribute('fill', '#f5f7ff');
                rect.setAttribute('stroke-width', '2');
                data.edges.forEach(function(edge, idx) {
                    if (edge.from === node.id || edge.to === node.id) {
                        if (edgeEls[idx]) {
                            edgeEls[idx].setAttribute('stroke', '#6b7280');
                            edgeEls[idx].setAttribute('stroke-width', '2');
                        }
                    }
                });
            });

            g.addEventListener('mouseleave', function() {
                rect.setAttribute('fill', bgFill);
                rect.setAttribute('stroke-width', '1');
                Object.keys(edgeEls).forEach(function(k) {
                    edgeEls[k].setAttribute('stroke', '#c5ccd6');
                    edgeEls[k].setAttribute('stroke-width', '1.5');
                });
            });

            if (node.url) {
                g.addEventListener('click', function(e) {
                    e.stopPropagation();
                    window.location.href = node.url;
                });
            }

            // ── unlink button (×) for non-current, non-overflow nodes ─────────
            if (!isCurrent && !isOverflow) {
                var unlinkBtn = svgEl('g', {
                    'class': 'node-unlink-btn',
                    style: 'cursor:pointer; opacity:0; transition:opacity .15s;',
                });
                var unlinkCircle = svgEl('circle', {
                    cx: x + NW - 8, cy: y + 8, r: '7',
                    fill: '#ef4444',
                });
                var unlinkX = svgEl('text', {
                    x: x + NW - 8, y: y + 8,
                    'text-anchor': 'middle',
                    'dominant-baseline': 'middle',
                    'font-size': '9',
                    'font-weight': '700',
                    fill: '#fff',
                    'font-family': 'inherit',
                    style: 'pointer-events:none;',
                });
                unlinkX.textContent = '×';
                unlinkBtn.appendChild(unlinkCircle);
                unlinkBtn.appendChild(unlinkX);
                g.appendChild(unlinkBtn);

                // Show on node hover
                g.addEventListener('mouseenter', function() { unlinkBtn.style.opacity = '1'; });
                g.addEventListener('mouseleave', function() { unlinkBtn.style.opacity = '0'; });

                (function(n, ub) {
                    ub.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (!confirm('Відв\'язати документ від замовлення?')) return;
                        var body = 'order_id=' + _currentOrderId
                                 + '&doc_type=' + encodeURIComponent(n.type)
                                 + '&doc_id='   + encodeURIComponent(n.entity_id);
                        fetch('/customerorder/api/unlink_document', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: body
                        }).then(function(r) { return r.json(); }).then(function(res) {
                            if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
                            _relDocsLoaded = false;
                            RelDocsGraph.load(_currentOrderId);
                            showToast('Документ відв\'язано');
                        }).catch(function() { alert('Помилка з\'єднання'); });
                    });
                }(node, unlinkBtn));
            }

            nodeGroup.appendChild(g);
        });

        // legend removed
    }

    // ── public API ────────────────────────────────────────────────────────────
    function load(orderId) {
        if (!orderId) return;
        _currentOrderId = orderId;
        var loading = document.getElementById('reldocs-loading');
        var wrap    = document.getElementById('reldocs-graph-wrap');
        var empty   = document.getElementById('reldocs-empty');

        loading.style.display = 'block';
        wrap.style.display    = 'none';
        empty.style.display   = 'none';

        fetch('/customerorder/api/get_linked_docs?order_id=' + orderId)
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

    return { load: load, reload: function(id) { _relDocsLoaded = false; load(id); } };
}());

/* ══ LINK DOCUMENT MODAL ══ */
(function() {
    var orderId  = <?= isset($order['id']) ? (int)$order['id'] : 0 ?>;
    var modal    = document.getElementById('linkDocModal');
    if (!modal) return;

    var openBtn  = document.getElementById('linkDocBtn');
    var closeBtn = document.getElementById('linkDocModalClose');
    var cancelBtn= document.getElementById('ldCancelBtn');
    var searchBtn= document.getElementById('ldSearchBtn');
    var linkBtn  = document.getElementById('ldLinkBtn');
    var checkAll = document.getElementById('ldCheckAll');
    var tbody    = document.getElementById('ldResultsTbody');
    var table    = document.getElementById('ldResultsTable');
    var emptyEl  = document.getElementById('ldResultsEmpty');
    var loadEl   = document.getElementById('ldResultsLoading');
    var errorEl  = document.getElementById('ldError');
    var countEl  = document.getElementById('ldSelectedCount');

    function openModal() {
        modal.style.display = 'flex';
        errorEl.style.display = 'none';
        emptyEl.style.display = 'none';
        loadEl.style.display  = 'none';
        table.style.display   = 'none';
        linkBtn.disabled = true;
        countEl.textContent  = '';
    }
    function closeModal() { modal.style.display = 'none'; }

    if (openBtn) openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    function updateSelection() {
        var checked = tbody.querySelectorAll('input[type=checkbox]:checked');
        var n = checked.length;
        linkBtn.disabled = n === 0;
        countEl.textContent = n > 0 ? ('Обрано: ' + n) : '';
        checkAll.indeterminate = false;
        var all = tbody.querySelectorAll('input[type=checkbox]');
        if (n === 0) checkAll.checked = false;
        else if (n === all.length) checkAll.checked = true;
        else { checkAll.checked = false; checkAll.indeterminate = true; }
    }

    checkAll.addEventListener('change', function() {
        tbody.querySelectorAll('input[type=checkbox]').forEach(function(cb) { cb.checked = checkAll.checked; });
        updateSelection();
    });

    function fmtDate(m) {
        if (!m) return '—';
        var p = String(m).split(' ')[0].split('-');
        return p.length >= 3 ? p[2] + '.' + p[1] + '.' + p[0] : m;
    }
    function h(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function doSearch() {
        var docType = document.getElementById('ldDocType').value;
        var dateFrom= document.getElementById('ldDateFrom').value;
        var dateTo  = document.getElementById('ldDateTo').value;
        var cpQ     = document.getElementById('ldCounterparty').value;

        emptyEl.style.display = 'none';
        table.style.display   = 'none';
        errorEl.style.display = 'none';
        loadEl.style.display  = 'block';
        linkBtn.disabled = true;
        checkAll.checked = false;
        countEl.textContent = '';

        var qs = 'order_id=' + orderId
               + '&doc_type=' + encodeURIComponent(docType)
               + '&date_from=' + encodeURIComponent(dateFrom)
               + '&date_to='   + encodeURIComponent(dateTo)
               + '&cp_q='      + encodeURIComponent(cpQ);

        fetch('/customerorder/api/search_linkable_docs?' + qs)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                loadEl.style.display = 'none';
                if (!data.ok) { errorEl.textContent = data.error || 'Помилка'; errorEl.style.display='block'; return; }
                if (!data.rows || data.rows.length === 0) { emptyEl.style.display = 'block'; return; }
                tbody.innerHTML = '';
                data.rows.forEach(function(row) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><input type="checkbox" data-id="' + h(row.id) + '" data-type="' + h(row.type) + '"></td>'
                        + '<td>' + h(row.type_name) + '</td>'
                        + '<td style="font-weight:600;">' + h(row.number || ('—')) + '</td>'
                        + '<td style="color:var(--text-muted);">' + fmtDate(row.moment) + '</td>'
                        + '<td>' + h(row.counterparty || '—') + '</td>'
                        + '<td style="text-align:right; font-weight:600;">' + h(row.amount || '—') + '</td>';
                    tbody.appendChild(tr);
                    tr.querySelector('input[type=checkbox]').addEventListener('change', updateSelection);
                });
                table.style.display = '';
            })
            .catch(function() { loadEl.style.display='none'; errorEl.textContent='Помилка завантаження'; errorEl.style.display='block'; });
    }

    searchBtn.addEventListener('click', doSearch);
    document.getElementById('ldDocType').addEventListener('change', function() {
        emptyEl.style.display='none'; table.style.display='none'; errorEl.style.display='none'; loadEl.style.display='none';
        linkBtn.disabled=true; checkAll.checked=false; countEl.textContent=''; tbody.innerHTML='';
    });

    linkBtn.addEventListener('click', function() {
        var checked = tbody.querySelectorAll('input[type=checkbox]:checked');
        if (checked.length === 0) return;
        var docs = [];
        checked.forEach(function(cb) { docs.push({ type: cb.dataset.type, id: cb.dataset.id }); });

        linkBtn.disabled = true;
        linkBtn.textContent = 'Зберігаємо…';

        var body = 'order_id=' + orderId + '&docs=' + encodeURIComponent(JSON.stringify(docs));
        fetch('/customerorder/api/link_documents', { method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                linkBtn.textContent = 'Прив\'язати';
                if (!data.ok) { errorEl.textContent = data.error || 'Помилка'; errorEl.style.display='block'; linkBtn.disabled=false; return; }
                closeModal();
                _relDocsLoaded = false;
                RelDocsGraph.load(orderId);
                showToast('Прив\'язано: ' + data.linked + ' документ(ів)');
            })
            .catch(function() { linkBtn.textContent='Прив\'язати'; linkBtn.disabled=false; errorEl.textContent='Помилка'; errorEl.style.display='block'; });
    });
}());

/* ══ ROW MENUS — fixed positioning ══ */
document.addEventListener('click', function(e) {
    var dotsBtn = e.target.closest('.row-dots');
    if (dotsBtn) {
        e.stopPropagation();
        var menu = dotsBtn.nextElementSibling;
        var isOpen = menu.classList.contains('open');
        document.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
        if (!isOpen) {
            var rect   = dotsBtn.getBoundingClientRect();
            var menuW  = 160;
            var left   = rect.right - menuW;
            if (left < 8) left = 8;
            menu.classList.add('open');
            var spaceBelow = window.innerHeight - rect.bottom;
            menu.style.top   = (spaceBelow < 140 ? (rect.top + window.scrollY - menu.offsetHeight - 4) : (rect.bottom + window.scrollY + 4)) + 'px';
            menu.style.left  = left + 'px';
            menu.style.width = menuW + 'px';
        }
        return;
    }
    document.querySelectorAll('.row-menu.open').forEach(function(m) { m.classList.remove('open'); });
});

/* ══ CHECKBOXES ══ */
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

/* ══ STATUS CUSTOM DROPDOWN ══ */
(function() {
    var _statusColors = <?= json_encode($_statusInlineStyles) ?>;
    var dd     = document.getElementById('statusDd');
    var btn    = document.getElementById('statusDdBtn');
    var menu   = document.getElementById('statusDdMenu');
    var label  = document.getElementById('statusDdLabel');
    var hidden = document.getElementById('statusHidden');
    if (!dd || !btn || !menu || !hidden) return;

    function closeMenu() { menu.classList.remove('open'); }
    function openMenu()  { menu.classList.add('open'); }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (menu.classList.contains('open')) { closeMenu(); } else { openMenu(); }
    });

    menu.addEventListener('click', function(e) {
        var opt = e.target.closest('.status-dd-opt');
        if (!opt) return;
        var val   = opt.getAttribute('data-value');
        var style = opt.getAttribute('data-style');
        hidden.value      = val;
        label.textContent = opt.textContent.trim();
        btn.style.cssText = style;
        closeMenu();
        if (window._state && _state.order) _state.order.status = val;
        markDirty();
    });

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target)) closeMenu();
    });
}());

/* ══ HISTORY PANEL ══ */
var historyToggle = document.getElementById('historyToggle');
if (historyToggle) {
    historyToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (_orderId) HistoryModal.open('customerorder', _orderId);
    });
}

/* ══ SAVE BUTTON ══ */
var btnSave = document.getElementById('btnSave');
if (btnSave) btnSave.addEventListener('click', saveOrder);

/* ══ DIRTY FLAG ON HEADER FIELDS ══ */
['organization_id','manager_employee_id','delivery_method_id','payment_method_id',
 'counterparty_id','contact_person_id','organization_bank_account_id','contract_id',
 'project_id','sales_channel','currency_code','store_id','planned_shipment_at',
 'status','applicable','order_description'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', markDirty);
});
var descEl = document.getElementById('order_description');
if (descEl) descEl.addEventListener('input', markDirty);

/* ══ PLANNED DATE ICON CLICK ══ */
var _pdIcon = document.getElementById('plannedDateIcon');
var _pdInput = document.getElementById('planned_shipment_at');
if (_pdIcon && _pdInput) {
    _pdIcon.addEventListener('click', function() {
        if (_pdInput.showPicker) { _pdInput.showPicker(); } else { _pdInput.focus(); _pdInput.click(); }
    });
}

/* ══ BANK ACCOUNTS AJAX ══ */
var orgSelect  = document.getElementById('organization_id');
var bankSelect = document.getElementById('organization_bank_account_id');
if (orgSelect && bankSelect) {
    orgSelect.addEventListener('change', function() {
        var orgId = this.value;
        if (!orgId) { bankSelect.innerHTML = '<option value="">— Обрати рахунок —</option>'; return; }
        bankSelect.innerHTML = '<option value="">Завантаження...</option>';
        bankSelect.disabled  = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/customerorder/ajax_get_bank_accounts?organization_id=' + orgId + '&t=' + Date.now(), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            bankSelect.disabled = false;
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (!resp.ok) throw new Error(resp.error || '');
                    bankSelect.innerHTML = '<option value="">— Обрати рахунок —</option>';
                    (resp.accounts || []).forEach(function(acc) {
                        var opt  = document.createElement('option');
                        opt.value = acc.id;
                        var txt  = acc.iban;
                        if (acc.account_name) txt += ' — ' + acc.account_name;
                        txt += ' (' + acc.currency_code + ')';
                        if (acc.is_default == 1) { txt += ' [Основний]'; opt.selected = true; }
                        opt.text = txt;
                        bankSelect.appendChild(opt);
                    });
                } catch(ex) { bankSelect.innerHTML = '<option value="">— Помилка —</option>'; }
            } else { bankSelect.innerHTML = '<option value="">— Помилка сервера —</option>'; }
        };
        xhr.send();
    });
}

/* ══ BIND EXISTING ROWS + INIT TOTALS ══ */
document.querySelectorAll('#positionsTable tbody tr[data-local-id]').forEach(function(tr) {
    bindItemRow(tr);
});
renderDocTotals();

/* ══ PRODUCT SEARCH (state-based, no reload) ══ */
(function() {
    var input   = document.getElementById('productSearchInput');
    var results = document.getElementById('productSearchResults');
    if (!input || !results) return;
    var timer = null;
    var _lastList = [];

    function closeDd() { results.style.display = 'none'; results.innerHTML = ''; _lastList = []; }

    input.addEventListener('input', function() {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { closeDd(); return; }
        timer = setTimeout(function() {
            fetch('/customerorder/search_product?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    _lastList = (data.ok && data.items) ? data.items : [];
                    if (!_lastList.length) {
                        results.innerHTML = '<div style="padding:10px 12px;color:#666;font-size:12.5px;">Нічого не знайдено</div>';
                        results.style.display = 'block';
                        return;
                    }
                    var html = '';
                    _lastList.forEach(function(p) {
                        html += '<div class="product-search-item" data-pid="' + p.product_id + '" style="padding:9px 12px;border-bottom:1px solid #f0f2f5;cursor:pointer;font-size:12.5px;">'
                            + '<span style="font-weight:500;">' + esc(p.name || '') + '</span><br>'
                            + '<span style="font-size:11.5px;color:#9ca3af;">Артикул: ' + esc(p.product_article || '') + ' · Ціна: ' + (p.price || 0) + ' · Залишок: ' + (p.quantity || 0) + ' · ' + esc(p.unit || '—') + '</span>'
                            + '</div>';
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                })
                .catch(function() {
                    results.innerHTML = '<div style="padding:10px 12px;color:#b91c1c;font-size:12.5px;">Помилка пошуку</div>';
                    results.style.display = 'block';
                });
        }, 250);
    });

    results.addEventListener('mousedown', function(e) {
        var row = e.target.closest('.product-search-item');
        if (!row) return;
        e.preventDefault();
        var pid = row.dataset.pid;
        var product = null;
        for (var j = 0; j < _lastList.length; j++) {
            if (String(_lastList[j].product_id) === String(pid)) { product = _lastList[j]; break; }
        }
        closeDd();
        input.value = '';
        if (!product) return;

        // Add to state
        var localId = 'n' + Date.now();
        var newItem = {
            _localId:          localId,
            id:                null,
            product_id:        parseInt(product.product_id) || null,
            product_name:      product.name || '',
            name:              product.name || '',
            sku:               product.product_article || '',
            article:           product.product_article || '',
            unit:              product.unit || '',
            quantity:          1,
            price:             parseFloat(product.price) || 0,
            discount_percent:  0,
            vat_rate:          0,
            stock_quantity:    parseFloat(product.quantity) || 0,
            shipped_quantity:  0,
            reserved_quantity: 0,
            weight:            parseFloat(product.weight) || 0,
            sum_row: 0, discount_amount: 0, vat_amount: 0
        };
        calcItem(newItem);
        _state.items.push(newItem);

        // Insert DOM row
        var tbody = document.querySelector('#positionsTable tbody');
        var addTr = document.querySelector('#positionsTable tbody tr.add-row');
        if (tbody) {
            var tr = document.createElement('tr');
            tr.dataset.itemRow  = '1';
            tr.dataset.localId  = localId;
            tr.dataset.sumChanged = '0';
            tr.innerHTML = buildNewRowHtml(product);
            if (addTr) { tbody.insertBefore(tr, addTr); } else { tbody.appendChild(tr); }
            bindItemRow(tr);
            tr.scrollIntoView({block: 'nearest'});
            var qtyInp = tr.querySelector('[data-field="quantity"]');
            if (qtyInp) { qtyInp.focus(); qtyInp.select(); }
        }
        renderDocTotals();
        markDirty();
    });

    // Enter key → pick first result
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var first = results.querySelector('.product-search-item');
            if (first) first.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
        }
        if (e.key === 'Escape') { closeDd(); input.value = ''; }
    });

    document.addEventListener('click', function(e) {
        if (!results.contains(e.target) && e.target !== input) closeDd();
    });

    // Hover effect
    document.addEventListener('mouseover', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '#f8f9fb';
    });
    document.addEventListener('mouseout', function(e) {
        var item = e.target.closest('.product-search-item');
        if (item) item.style.background = '';
    });
}());

/* ══ COUNTERPARTY PICKER ══ */
function makeCpPicker(inputId, hiddenId, ddId, clearId, cpType, onPick) {
    var inp    = document.getElementById(inputId);
    var hidden = document.getElementById(hiddenId);
    var dd     = document.getElementById(ddId);
    var clear  = document.getElementById(clearId);
    if (!inp || !hidden || !dd) return;

    var timer = null;
    var _list = [];

    function closeDd() { dd.style.display = 'none'; dd.innerHTML = ''; _list = []; }

    function pick(id, name) {
        hidden.value = id;
        inp.value    = name;
        if (clear) clear.style.display = id ? '' : 'none';
        closeDd();
        markDirty();
        if (onPick) onPick(id, name);
    }

    function doSearch(q, delay) {
        clearTimeout(timer);
        if (q.length < 1) { closeDd(); return; }
        timer = setTimeout(function() {
            var url = '/counterparties/api/search?q=' + encodeURIComponent(q)
                    + (cpType ? '&type=' + encodeURIComponent(cpType) : '');
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    _list = (res.ok && res.items) ? res.items : [];
                    if (!_list.length) {
                        dd.innerHTML = '<div class="cp-picker-opt" style="color:var(--text-muted)">Нічого не знайдено</div>';
                        dd.style.display = 'block';
                        return;
                    }
                    var typeCls = { company: 't-company', fop: 't-fop', person: 't-person' };
                    dd.innerHTML = _list.slice(0, 20).map(function(p) {
                        var badge = '<span class="cp-picker-type-badge ' + (typeCls[p.type] || '') + '">'
                                  + esc(p.type_label || p.type) + '</span>';
                        var sub = '';
                        if (p.okpo)  sub += 'ЄДРПОУ: ' + esc(p.okpo);
                        if (p.phone) sub += (sub ? ' · ' : '') + esc(p.phone);
                        return '<div class="cp-picker-opt" data-id="' + p.id + '" data-name="' + esc(p.name) + '">'
                            + '<div class="cp-picker-opt-name">' + esc(p.name)
                            + (sub ? '<div class="cp-picker-opt-sub">' + sub + '</div>' : '')
                            + '</div>'
                            + badge
                            + '</div>';
                    }).join('');
                    dd.style.display = 'block';
                })
                .catch(function() { closeDd(); });
        }, delay !== undefined ? delay : 200);
    }

    inp.addEventListener('input', function() {
        doSearch(inp.value.trim(), 200);
    });

    inp.addEventListener('focus', function() {
        inp.select();
        doSearch(inp.value.trim(), 0);
    });

    dd.addEventListener('mousedown', function(e) {
        var opt = e.target.closest('.cp-picker-opt[data-id]');
        if (!opt) return;
        e.preventDefault();
        pick(opt.dataset.id, opt.dataset.name);
    });

    inp.addEventListener('blur', function() { setTimeout(closeDd, 150); });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { inp.value = hidden.value ? inp.value : ''; closeDd(); }
    });

    // If user clears the text manually — reset hidden
    inp.addEventListener('change', function() {
        if (!inp.value.trim()) { pick('', ''); }
    });

    if (clear) {
        clear.addEventListener('click', function() { pick('', ''); inp.focus(); });
    }
}

/* ══ CONTACT PERSON PICKER (local list, linked contacts only) ══ */
var _personContacts = <?= json_encode($initialContacts) ?>;

function renderPersonDd(q) {
    var dd = document.getElementById('personPickerDd');
    if (!dd) return;
    var filtered = _personContacts.filter(function(c) {
        if (!q) return true;
        var tokens = q.toLowerCase().split(/\s+/).filter(function(t) { return t.length > 0; });
        var lname  = c.name.toLowerCase();
        for (var i = 0; i < tokens.length; i++) {
            if (lname.indexOf(tokens[i]) === -1) return false;
        }
        return true;
    });
    if (!filtered.length) {
        dd.innerHTML = '<div class="cp-picker-opt" style="color:var(--text-muted)">Нічого не знайдено</div>';
        dd.style.display = 'block';
        return;
    }
    dd.innerHTML = filtered.slice(0, 20).map(function(p) {
        var sub = p.position ? '<div class="cp-picker-opt-sub">' + esc(p.position) + '</div>' : '';
        return '<div class="cp-picker-opt" data-id="' + p.id + '" data-name="' + esc(p.name) + '">'
            + esc(p.name) + sub + '</div>';
    }).join('');
    dd.style.display = 'block';
}

(function () {
    var inp    = document.getElementById('personPickerInput');
    var hidden = document.getElementById('contact_person_id');
    var dd     = document.getElementById('personPickerDd');
    var clear  = document.getElementById('personPickerClear');
    var field  = document.getElementById('contactPersonField');
    if (!inp || !hidden || !dd || !field) return;

    function closeDd() { dd.style.display = 'none'; dd.innerHTML = ''; }

    function pickPerson(id, name) {
        hidden.value = id;
        inp.value    = name;
        if (clear) clear.style.display = id ? '' : 'none';
        closeDd();
        markDirty();
    }

    inp.addEventListener('focus', function() {
        if (_personContacts.length) renderPersonDd(inp.value.trim());
    });

    inp.addEventListener('input', function() {
        if (!_personContacts.length) { closeDd(); return; }
        renderPersonDd(inp.value.trim());
    });

    dd.addEventListener('mousedown', function(e) {
        var opt = e.target.closest('.cp-picker-opt[data-id]');
        if (!opt) return;
        e.preventDefault();
        pickPerson(opt.dataset.id, opt.dataset.name);
    });

    inp.addEventListener('blur', function() { setTimeout(closeDd, 150); });

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeDd(); }
    });

    inp.addEventListener('change', function() {
        if (!inp.value.trim()) { pickPerson('', ''); }
    });

    if (clear) {
        clear.addEventListener('click', function() { pickPerson('', ''); inp.focus(); });
    }
}());

function loadContactsForCp(cpId) {
    var field      = document.getElementById('contactPersonField');
    var personHidden = document.getElementById('contact_person_id');
    var personInp    = document.getElementById('personPickerInput');
    var personClear  = document.getElementById('personPickerClear');
    if (!field) return;

    // Clear person selection when counterparty changes
    if (personHidden) personHidden.value = '';
    if (personInp)    personInp.value    = '';
    if (personClear)  personClear.style.display = 'none';

    if (!cpId) {
        _personContacts = [];
        field.style.display = 'none';
        return;
    }

    fetch('/counterparties/api/get_contacts?counterparty_id=' + encodeURIComponent(cpId))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            _personContacts = (res.ok && res.items) ? res.items : [];
            field.style.display = _personContacts.length ? '' : 'none';
        })
        .catch(function() {
            _personContacts = [];
            field.style.display = 'none';
        });
}

makeCpPicker('cpPickerInput', 'counterparty_id', 'cpPickerDd', 'cpPickerClear', '', function(id) {
    loadContactsForCp(id);
    var cpLink = document.getElementById('cpCardLink');
    if (cpLink) {
        if (id) { cpLink.href = '/counterparties/view?id=' + id; cpLink.style.display = ''; }
        else { cpLink.style.display = 'none'; }
    }
});

/* ── Create document dropdown ── */
(function () {
    var btn  = document.getElementById('createDocBtn');
    var menu = document.getElementById('createDocMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('open');
    });

    document.addEventListener('click', function () {
        menu.classList.remove('open');
    });

    menu.addEventListener('click', function (e) {
        var item = e.target.closest('.create-doc-item');
        if (!item) return;
        menu.classList.remove('open');

        var toType   = item.dataset.toType;
        var linkType = item.dataset.linkType;
        var orderId  = item.dataset.orderId;
        var label    = item.textContent.trim();

        item.disabled = true;
        item.textContent = label + '…';

        var body = 'order_id=' + encodeURIComponent(orderId)
                 + '&to_type=' + encodeURIComponent(toType)
                 + '&link_type=' + encodeURIComponent(linkType);

        fetch('/customerorder/api/create_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            item.disabled = false;
            item.textContent = label;
            if (res.ok && res.redirect_url) {
                window.location.href = res.redirect_url;
            } else if (res.todo) {
                showToast(res.error, false);
            } else {
                showToast('Помилка: ' + (res.error || 'невідома'), true);
            }
        })
        .catch(function () {
            item.disabled = false;
            item.textContent = label;
            showToast('Помилка з\'єднання', true);
        });
    });
}());
</script>

<?php require_once __DIR__ . '/../../shared/print-modal.php'; ?>
<script src="/modules/shared/history-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/history-modal.js') ?>"></script>
<script src="/modules/shared/chat-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/chat-modal.js') ?>"></script>
<?php if (!empty($currentCpId) && !$isNew): ?>

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

<script>
(function() {
    var _cpId    = <?= (int)$currentCpId ?>;
    var _orderId = <?= (int)$order['id'] ?>;
    var _num     = <?= json_encode(field_value($order, 'number', (string)$order['id'])) ?>;
    var _sum     = <?= json_encode(field_value($order, 'sum_total', '0')) ?>;
    var _date    = <?= json_encode(!empty($order['moment']) ? substr($order['moment'], 0, 10) : date('Y-m-d')) ?>;

    var _menu = null;

    /* ── compose modal state ── */
    var _composeAttachUrl  = null;
    var _composeAttachName = null;

    /* ── Compose modal open / close ── */
    function _openCompose(title, text, attachUrl, attachName) {
        _composeAttachUrl  = attachUrl  || null;
        _composeAttachName = attachName || null;

        document.getElementById('sendComposeTitle').textContent = title || 'Надіслати клієнту';
        document.getElementById('sendComposeText').value = text || '';

        var attachInfo = document.getElementById('sendComposeAttachInfo');
        var attachNameEl = document.getElementById('sendComposeAttachName');
        if (attachUrl) {
            attachNameEl.textContent = attachName || attachUrl;
            attachInfo.style.display = '';
        } else {
            attachInfo.style.display = 'none';
        }

        document.getElementById('sendComposeModal').classList.add('open');
        setTimeout(function() {
            var ta = document.getElementById('sendComposeText');
            ta.focus();
            ta.setSelectionRange(0, 0);
        }, 50);
    }

    function _closeCompose() {
        document.getElementById('sendComposeModal').classList.remove('open');
    }

    document.getElementById('sendComposeClose').addEventListener('click', _closeCompose);
    document.getElementById('sendComposeCancel').addEventListener('click', _closeCompose);
    document.getElementById('sendComposeModal').addEventListener('click', function(e) {
        if (e.target === this) _closeCompose();
    });

    document.getElementById('sendComposeSend').addEventListener('click', function() {
        var text = document.getElementById('sendComposeText').value.trim();
        if (!text) { document.getElementById('sendComposeText').focus(); return; }
        var ch = (document.querySelector('input[name="sendCompCh"]:checked') || {}).value || 'viber';
        _closeCompose();
        ChatModal.open(_cpId, ch, text, _composeAttachUrl, _composeAttachName);
    });

    /* ── Build order items text ── */
    function _buildItemsText() {
        var rows = document.querySelectorAll('#positionsTable tbody tr[data-local-id]');
        if (!rows.length) return '';

        var lines = ['Замовлення №' + _num + ':'];
        var n = 0;
        var total = 0;

        rows.forEach(function(tr) {
            var nameEl = tr.querySelector('.prod-name-link');
            var name = nameEl ? (nameEl.textContent || nameEl.innerText || '').trim() : '';
            var artEl = tr.querySelector('a[style*="9ca3af"]');
            var art = artEl ? (artEl.textContent || '').trim() : '';
            var qty = parseFloat((tr.querySelector('[data-field="quantity"]') || {}).value) || 0;
            var price = parseFloat((tr.querySelector('[data-field="price"]') || {}).value) || 0;
            var disc = parseFloat((tr.querySelector('[data-field="discount_percent"]') || {}).value) || 0;
            var unit = (tr.querySelector('[data-field="unit"]') || {}).value || 'шт';
            var gross = qty * price;
            var sum = disc > 0 ? gross * (1 - disc / 100) : gross;
            total += sum;
            n++;

            var label = name || art || ('Позиція ' + n);
            var discStr = disc > 0 ? ' (−' + disc + '%)' : '';
            lines.push(n + '. ' + label
                + '\n   ' + qty + ' ' + unit + ' × ' + _fmt2(price) + discStr
                + ' = ' + _fmt2(sum) + ' грн');
        });

        if (!n) return '';
        lines.push('');
        lines.push('РАЗОМ: ' + _fmt2(total) + ' грн');
        return lines.join('\n');
    }

    function _fmt2(v) {
        return parseFloat(v).toFixed(2).replace('.', ',');
    }

    /* ── Menu ── */
    function _closeMenu() {
        if (_menu) { _menu.remove(); _menu = null; }
    }

    function _openMenu(anchor) {
        _closeMenu();
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:10px;'
            + 'box-shadow:0 6px 24px rgba(0,0,0,.13);min-width:230px;overflow:hidden;font-family:inherit';
        div.innerHTML =
            '<div style="padding:8px 13px 6px;border-bottom:1px solid #f3f4f6;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px">Надіслати клієнту</div>'
            + '<div id="_sendMenuItems"></div>';

        var items = [
            { icon: '📋', label: 'Склад замовлення',    key: 'items_list' },
            { icon: '💳', label: 'Посилання на оплату', key: 'pay' },
            { icon: '📄', label: 'Рахунок (PDF-файл)',   key: 'invoice' },
        ];
        var itemsEl = div.querySelector('#_sendMenuItems');
        items.forEach(function(it) {
            var btn = document.createElement('div');
            btn.style.cssText = 'padding:9px 13px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:#1f2937;transition:background .1s';
            btn.innerHTML = '<span style="font-size:16px">' + it.icon + '</span><span>' + it.label + '</span>';
            btn.addEventListener('mouseover', function() { btn.style.background = '#f5f3ff'; });
            btn.addEventListener('mouseout',  function() { btn.style.background = ''; });
            btn.addEventListener('click', function() { _closeMenu(); _runAction(it.key); });
            itemsEl.appendChild(btn);
        });

        document.body.appendChild(div);
        _menu = div;

        var rect = anchor.getBoundingClientRect();
        var menuW = 234;
        var left  = rect.left;
        if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8;
        div.style.top  = (rect.bottom + 4) + 'px';
        div.style.left = left + 'px';

        setTimeout(function() {
            document.addEventListener('click', function _cl(e) {
                if (!div.contains(e.target)) { _closeMenu(); document.removeEventListener('click', _cl); }
            });
        }, 10);
    }

    function _generatePdf(callback) {
        var btn = document.getElementById('btnSendTpl');
        if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Формую…'; }
        var fd = new FormData();
        fd.append('order_id', _orderId);
        fetch('/print/api/generate_order_pdf', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                if (!d.ok) { alert('Помилка генерації PDF: ' + (d.error || '')); return; }
                callback(d);
            })
            .catch(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = '📤 Надіслати ▾'; }
                alert('Помилка мережі при генерації PDF');
            });
    }

    function _runAction(key) {
        if (key === 'items_list') {
            var text = _buildItemsText();
            _openCompose('Склад замовлення', text);
        } else if (key === 'pay') {
            _generatePdf(function(d) {
                var text = 'Рахунок №' + _num + ' від ' + _date + ':\n' + d.url;
                _openCompose('Посилання на оплату', text);
            });
        } else if (key === 'invoice') {
            _generatePdf(function(d) {
                var text = 'Рахунок №' + _num + ' від ' + _date;
                _openCompose('Рахунок (PDF)', text, d.url, d.filename);
            });
        }
    }

    var btn = document.getElementById('btnSendTpl');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (_menu) { _closeMenu(); return; }
            _openMenu(btn);
        });
    }
}());

/* ══ SHIPMENTS PANEL ══ */
var ShipmentsPanel = (function() {
    var _orderId = 0;

    var TN_STATUS = {
        draft:      { cls: 'ttn-status-created',    label: 'Чернетка' },
        created:    { cls: 'ttn-status-created',    label: 'Створено' },
        in_transit: { cls: 'ttn-status-in_transit', label: 'В дорозі' },
        at_branch:  { cls: 'ttn-status-at_branch',  label: 'У відділенні' },
        delivered:  { cls: 'ttn-status-delivered',  label: 'Доставлено' },
        returned:   { cls: 'ttn-status-returned',   label: 'Повернення' },
        refused:    { cls: 'ttn-status-returned',   label: 'Відмова' },
        deleted:    { cls: 'ttn-status-deleted',     label: 'Видалено' },
    };
    var ND_STATUS = {
        pending:   { cls: 'nd-status-pending',   label: 'Очікує' },
        sent:      { cls: 'nd-status-sent',       label: 'Відправлено' },
        delivered: { cls: 'nd-status-delivered',  label: 'Доставлено' },
        cancelled: { cls: 'nd-status-cancelled',  label: 'Скасовано' },
    };
    var DEMAND_STATUS = {
        new:        { cls: 'badge-blue',   label: 'Нове' },
        assembling: { cls: 'badge-orange', label: 'Збирається' },
        assembled:  { cls: 'badge-indigo', label: 'Зібрано' },
        shipped:    { cls: 'badge-green',  label: 'Відвантажено' },
        arrived:    { cls: 'badge-teal',   label: 'Отримано' },
        transfer:   { cls: 'badge-purple', label: 'Передача' },
        robot:      { cls: 'badge-gray',   label: 'Авто' },
        cancelled:  { cls: 'badge-red',    label: 'Скасовано' },
    };

    function ttnStatus(t) {
        if (t.deletion_mark) return 'deleted';
        var def = parseInt(t.state_define, 10);
        if (def === 9)                                               return 'delivered';
        if (def === 7 || def === 8 || def === 105)                   return 'at_branch';
        if (def === 4 || def === 5 || def === 6 || def === 41
            || def === 101 || def === 104)                           return 'in_transit';
        if (def === 10 || def === 11 || def === 103)                 return 'returned';
        if (def === 102 || def === 106)                              return 'refused';
        if (def === 2 || def === 3)                                  return 'deleted';
        if (def === 1)                                               return 'draft';
        return 'created';
    }

    function badge(cls, label) {
        return '<span class="badge ' + cls + '" style="font-size:11px;">' + esc(label) + '</span>';
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmt2(n) {
        return parseFloat(n || 0).toFixed(2).replace(/\.?0+$/, function(m, o, s){ return s.replace(/\./,'') === s.replace(/[^.]/g,'').repeat(s.length) ? '' : m; });
    }

    function renderTtn(t) {
        var st = ttnStatus(t);
        var stInfo = TN_STATUS[st] || TN_STATUS.created;
        var num = t.int_doc_number || ('#' + t.id);

        var meta = [];
        if (t.recipient_contact_person) meta.push(esc(t.recipient_contact_person));
        if (t.city_recipient_desc)       meta.push('📍 ' + esc(t.city_recipient_desc));
        if (t.cost_on_site && parseFloat(t.cost_on_site) > 0) meta.push('Доставка: ' + parseFloat(t.cost_on_site).toFixed(0) + ' ₴');
        if (t.backward_delivery_money && parseFloat(t.backward_delivery_money) > 0) meta.push('НП: ' + parseFloat(t.backward_delivery_money).toFixed(0) + ' ₴');
        if (t.state_name) meta.push('<i style="color:#6b7280">' + esc(t.state_name) + '</i>');

        var trackUrl = 'https://track.novaposhta.ua/#/' + encodeURIComponent(num);

        return '<div class="shipment-card">'
            + '<div class="shipment-card-icon">🚚</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span class="shipment-card-num">' + esc(num) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<a href="' + trackUrl + '" target="_blank" class="btn btn-xs">Трекінг ↗</a>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderDelivery(d) {
        var st = d.status || 'pending';
        var stInfo = ND_STATUS[st] || ND_STATUS.pending;
        var icon = d.code === 'pickup' ? '🏠' : '🚐';
        var meta = [];
        if (d.comment) meta.push(esc(d.comment));

        return '<div class="shipment-card" data-nd-id="' + (int(d.id)) + '">'
            + '<div class="shipment-card-icon">' + icon + '</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span>' + esc(d.name_uk) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<button type="button" class="btn btn-xs nd-edit-btn">Редагувати</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderDemand(d) {
        var st = d.status || 'new';
        var stInfo = DEMAND_STATUS[st] || { cls: 'badge-gray', label: st };
        var num = d.number || ('#' + d.id);
        var meta = [];
        if (d.moment) {
            var p = String(d.moment).split(' ')[0].split('-');
            if (p.length >= 3) meta.push(p[2] + '.' + p[1] + '.' + p[0]);
        }
        if (d.sum_total && parseFloat(d.sum_total) > 0) meta.push(parseFloat(d.sum_total).toFixed(2).replace(/\.00$/, '') + ' ₴');
        var url = '/demand/edit?id=' + int(d.id);
        return '<div class="shipment-card">'
            + '<div class="shipment-card-icon">📋</div>'
            + '<div class="shipment-card-body">'
            + '<div class="shipment-card-title">'
            + badge(stInfo.cls, stInfo.label)
            + '<span class="shipment-card-num">' + esc(num) + '</span>'
            + '</div>'
            + (meta.length ? '<div class="shipment-card-meta">' + meta.join(' · ') + '</div>' : '')
            + '<div class="shipment-card-acts">'
            + '<a href="' + url + '" class="btn btn-xs">Відкрити ↗</a>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function int(v) { return parseInt(v, 10) || 0; }

    function render(data) {
        var list    = document.getElementById('shipments-list');
        var loading = document.getElementById('shipments-loading');
        var empty   = document.getElementById('shipments-empty');
        if (!list) return;
        loading.style.display = 'none';

        var html = '';
        (data.ttns      || []).forEach(function(t) { html += renderTtn(t); });
        (data.deliveries|| []).forEach(function(d) { html += renderDelivery(d); });

        if (!html) {
            empty.style.display = '';
            list.innerHTML = '';
        } else {
            empty.style.display = 'none';
            list.innerHTML = html;

            // bind "Редагувати" buttons on delivery cards
            list.querySelectorAll('.nd-edit-btn').forEach(function(btn) {
                var card = btn.closest('.shipment-card');
                var ndId = int(card.dataset.ndId);
                var match = (data.deliveries || []).filter(function(d){ return int(d.id) === ndId; })[0];
                if (match) btn.addEventListener('click', function(){ DeliveryModal.open(match); });
            });
        }
    }

    function load(orderId) {
        _orderId = orderId;
        if (!orderId) return;
        var loading = document.getElementById('shipments-loading');
        var empty   = document.getElementById('shipments-empty');
        var list    = document.getElementById('shipments-list');
        if (!loading) return;
        loading.style.display = '';
        empty.style.display = 'none';
        if (list) list.innerHTML = '';

        fetch('/customerorder/api/get_order_shipments?order_id=' + orderId)
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.ok) render(data);
                else {
                    loading.style.display = 'none';
                    empty.style.display = '';
                }
            })
            .catch(function() {
                loading.style.display = 'none';
                empty.style.display = '';
            });
    }

    function reload() { load(_orderId); }

    // bind toolbar buttons
    document.addEventListener('DOMContentLoaded', function() {
        var ttnBtn = document.getElementById('newTtnNpBtn');
        if (ttnBtn) ttnBtn.addEventListener('click', function() { NpTtnModal.open(window._orderId); });
        var ndBtn  = document.getElementById('newDeliveryBtn');
        if (ndBtn)  ndBtn.addEventListener('click', function() { DeliveryModal.open(null); });
    });

    return { load: load, reload: reload };
}());

/* ══ DELIVERY (PICKUP/COURIER) MODAL ══ */
var DeliveryModal = (function() {
    var _orderId = <?php echo (int)(isset($order['id']) ? $order['id'] : 0); ?>;

    function open(existing) {
        var modal    = document.getElementById('newDeliveryModal');
        var title    = document.getElementById('newDeliveryModalTitle');
        var idEl     = document.getElementById('ndDeliveryId');
        var methodEl = document.getElementById('ndMethodId');
        var statusEl = document.getElementById('ndStatus');
        var commentEl= document.getElementById('ndComment');
        var errEl    = document.getElementById('ndError');
        if (!modal) return;

        errEl.style.display = 'none';
        if (existing) {
            title.textContent      = 'Редагувати відправлення';
            idEl.value             = existing.id;
            if (methodEl) methodEl.value = existing.delivery_method_id;
            if (statusEl) statusEl.value = existing.status;
            if (commentEl) commentEl.value = existing.comment || '';
        } else {
            title.textContent = 'Нове відправлення';
            idEl.value = '0';
            if (statusEl) statusEl.value = 'pending';
            if (commentEl) commentEl.value = '';
        }
        modal.classList.add('open');
    }

    function close() {
        var modal = document.getElementById('newDeliveryModal');
        if (modal) modal.classList.remove('open');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var closeBtn  = document.getElementById('newDeliveryModalClose');
        var cancelBtn = document.getElementById('ndCancelBtn');
        var saveBtn   = document.getElementById('ndSaveBtn');
        var errEl     = document.getElementById('ndError');
        if (closeBtn)  closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);

        if (saveBtn) saveBtn.addEventListener('click', function() {
            var idEl     = document.getElementById('ndDeliveryId');
            var methodEl = document.getElementById('ndMethodId');
            var statusEl = document.getElementById('ndStatus');
            var commentEl= document.getElementById('ndComment');
            if (!methodEl || !methodEl.value) {
                errEl.textContent = 'Оберіть спосіб доставки'; errEl.style.display = ''; return;
            }
            saveBtn.disabled = true;
            var body = 'customerorder_id=' + _orderId
                + '&id='                  + (idEl ? idEl.value : '0')
                + '&delivery_method_id='  + encodeURIComponent(methodEl.value)
                + '&status='              + encodeURIComponent(statusEl ? statusEl.value : 'pending')
                + '&comment='             + encodeURIComponent(commentEl ? commentEl.value : '');
            fetch('/customerorder/api/save_order_delivery', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r){ return r.json(); }).then(function(res) {
                saveBtn.disabled = false;
                if (!res.ok) { errEl.textContent = res.error || 'Помилка'; errEl.style.display = ''; return; }
                close();
                ShipmentsPanel.reload();
                showToast('Збережено ✓');
            }).catch(function() { saveBtn.disabled = false; errEl.textContent = 'Помилка з\'єднання'; errEl.style.display = ''; });
        });
    });

    return { open: open, close: close };
}());

/* ══ NP TTN CREATE MODAL ══ */
var NpTtnModal = (function() {
    var _orderId = 0;
    var _prefillData = null;

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function open(orderId) {
        _orderId = orderId;
        var modal = document.getElementById('newTtnModal');
        var body  = document.getElementById('npTtnBody');
        if (!modal || !body) return;
        body.innerHTML = '<div style="text-align:center;color:#9ca3af;padding:30px;">Завантаження…</div>';
        modal.classList.add('open');

        fetch('/novaposhta/api/get_ttn_form?order_id=' + orderId)
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.ok) { body.innerHTML = '<div style="color:#dc2626;padding:16px">' + esc(res.error || 'Помилка') + '</div>'; return; }
                _prefillData = res.data;
                renderForm(res.data, body);
            })
            .catch(function() { body.innerHTML = '<div style="color:#dc2626;padding:16px">Помилка завантаження</div>'; });
    }

    function close() {
        var modal = document.getElementById('newTtnModal');
        if (modal) modal.classList.remove('open');
    }

    function renderForm(data, body) {
        var senders   = data.senders   || [];
        var recipient = data.recipient || {};

        var sOpts = senders.map(function(s) {
            var sel = (s.Ref === data.sender_ref) ? ' selected' : '';
            return '<option value="' + esc(s.Ref) + '"' + sel + '>' + esc(s.Description) + '</option>';
        }).join('');

        var stOpts = [
            ['WarehouseWarehouse', 'Відділення → Відділення'],
            ['WarehouseDoors',     'Відділення → Адреса'],
            ['DoorsWarehouse',     'Адреса → Відділення'],
            ['DoorsDoor',          'Адреса → Адреса'],
        ].map(function(o) {
            return '<option value="' + o[0] + '">' + o[1] + '</option>';
        }).join('');

        var html = '';

        // Sender
        html += '<div class="np-form-section">Відправник</div>';
        html += '<label class="np-field-label" style="margin-top:0">Відправник</label>';
        html += '<select id="npSenderRef" class="np-inp" style="height:auto;padding:4px 7px;">' + sOpts + '</select>';
        html += '<label class="np-field-label">Адреса відправки</label>';
        html += '<select id="npSenderAddr" class="np-inp" style="height:auto;padding:4px 7px;"><option value="">Завантаження…</option></select>';

        // Recipient
        html += '<div class="np-form-section">Одержувач</div>';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Прізвище</label><input type="text" id="npRcpLast" class="np-inp" value="' + esc(recipient.last_name||'') + '"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Ім\'я</label><input type="text" id="npRcpFirst" class="np-inp" value="' + esc(recipient.first_name||'') + '"></div>';
        html += '</div>';
        html += '<label class="np-field-label">Телефон</label>';
        html += '<input type="text" id="npRcpPhone" class="np-inp" value="' + esc(recipient.phone||'') + '" placeholder="0671234567">';

        html += '<label class="np-field-label">Місто одержувача</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npCityInput" class="np-inp" value="' + esc(recipient.city_hint||'') + '" placeholder="Введіть місто…" autocomplete="off">';
        html += '<input type="hidden" id="npCityRef" value=""><div class="np-ac-dd" id="npCityDd"></div></div>';

        html += '<div id="npWhSection"><label class="np-field-label">Відділення / Поштомат</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npWhInput" class="np-inp" value="' + esc(recipient.address_hint||'') + '" placeholder="Відділення або поштомат…" autocomplete="off">';
        html += '<input type="hidden" id="npWhRef" value="' + esc(recipient.np_warehouse_ref||'') + '"><div class="np-ac-dd" id="npWhDd"></div></div></div>';

        html += '<div id="npAddrSection" style="display:none"><label class="np-field-label">Вулиця</label>';
        html += '<div class="np-ac-wrap"><input type="text" id="npStreetInput" class="np-inp" placeholder="Вулиця…" autocomplete="off">';
        html += '<input type="hidden" id="npStreetRef" value=""><div class="np-ac-dd" id="npStreetDd"></div></div>';
        html += '<div class="np-2col" style="margin-top:6px"><div><label class="np-field-label" style="margin-top:0">Будинок</label><input type="text" id="npBuilding" class="np-inp"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Квартира</label><input type="text" id="npFlat" class="np-inp"></div></div></div>';

        // Cargo
        html += '<div class="np-form-section">Вантаж</div>';
        html += '<label class="np-field-label" style="margin-top:0">Тип доставки</label>';
        html += '<select id="npServiceType" class="np-inp" style="height:auto;padding:4px 7px;">' + stOpts + '</select>';
        html += '<div class="np-2col" style="margin-top:8px">';
        html += '<div><label class="np-field-label" style="margin-top:0">Вага (кг)</label><input type="number" id="npWeight" class="np-inp" value="0.5" step="0.1" min="0.1"></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Місць</label><input type="number" id="npSeats" class="np-inp" value="1" step="1" min="1"></div>';
        html += '</div>';
        html += '<label class="np-field-label">Опис</label><input type="text" id="npDesc" class="np-inp" value="Товар">';
        html += '<label class="np-field-label">Оголошена вартість (грн)</label><input type="number" id="npCost" class="np-inp" value="1" min="1">';

        // Payment
        html += '<div class="np-form-section">Оплата</div>';
        html += '<div class="np-2col">';
        html += '<div><label class="np-field-label" style="margin-top:0">Платник</label>'
              + '<select id="npPayerType" class="np-inp" style="height:auto;padding:4px 7px;">'
              + '<option value="Recipient">Одержувач</option><option value="Sender">Відправник</option></select></div>';
        html += '<div><label class="np-field-label" style="margin-top:0">Спосіб оплати</label>'
              + '<select id="npPayMethod" class="np-inp" style="height:auto;padding:4px 7px;">'
              + '<option value="Cash">Готівка</option><option value="NonCash">Безготівка</option></select></div>';
        html += '</div>';
        html += '<label class="np-field-label">Накладений платіж (грн), 0 = без</label>';
        html += '<input type="number" id="npBackMoney" class="np-inp" value="' + esc(data.backward_money_hint || 0) + '" min="0" step="0.01">';
        html += '<label class="np-field-label">Дата відправки</label>';
        html += '<input type="date" id="npDate" class="np-inp" value="' + (new Date().toISOString().slice(0,10)) + '">';

        html += '<div id="npTtnError" class="np-form-error"></div>';

        html += '<div style="display:flex;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">';
        html += '<button type="button" class="btn btn-primary btn-sm" id="npTtnSubmitBtn">Створити ТТН</button>';
        html += '<button type="button" class="btn btn-sm" id="npTtnCancelBtn">Скасувати</button>';
        html += '</div>';

        body.innerHTML = html;
        bindFormEvents(data);
    }

    function bindFormEvents(data) {
        document.getElementById('npTtnCancelBtn').addEventListener('click', close);

        // Service type toggle
        var stSel = document.getElementById('npServiceType');
        function toggleDelivery() {
            var v = stSel.value;
            var isAddr = (v === 'WarehouseDoors' || v === 'DoorsDoor');
            document.getElementById('npWhSection').style.display   = isAddr ? 'none' : '';
            document.getElementById('npAddrSection').style.display = isAddr ? '' : 'none';
        }
        stSel.addEventListener('change', toggleDelivery);
        toggleDelivery();

        // Sender addresses
        function loadSenderAddresses(senderRef) {
            var addrSel = document.getElementById('npSenderAddr');
            if (!addrSel) return;
            addrSel.innerHTML = '<option value="">Завантаження…</option>';
            fetch('/novaposhta/api/get_senders?sender_ref=' + encodeURIComponent(senderRef))
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (!res.ok || !res.addresses || !res.addresses.length) {
                        addrSel.innerHTML = '<option value="">Адреси не знайдено</option>'; return;
                    }
                    addrSel.innerHTML = res.addresses.map(function(a) {
                        var s = a.is_default ? ' selected' : '';
                        return '<option value="' + esc(a.Ref) + '"' + s
                            + ' data-city="' + esc(a.CityRef||'') + '"'
                            + ' data-city-desc="' + esc(a.CityDescription||'') + '">'
                            + esc(a.Description||a.Ref) + '</option>';
                    }).join('');
                });
        }
        var senderSel = document.getElementById('npSenderRef');
        if (senderSel.value) loadSenderAddresses(senderSel.value);
        senderSel.addEventListener('change', function(){ loadSenderAddresses(this.value); });

        // Autocomplete helper
        function makeAc(inpId, ddId, hiddenId, fetchFn, renderFn) {
            var inp    = document.getElementById(inpId);
            var dd     = document.getElementById(ddId);
            var hidden = document.getElementById(hiddenId);
            if (!inp || !dd || !hidden) return;
            var timer;
            inp.addEventListener('input', function() {
                clearTimeout(timer);
                var q = inp.value.trim();
                if (q.length < 2) { dd.style.display = 'none'; return; }
                timer = setTimeout(function() {
                    fetchFn(q, function(items) {
                        if (!items.length) { dd.style.display = 'none'; return; }
                        dd.innerHTML = items.slice(0, 15).map(function(item) {
                            var r = renderFn(item);
                            return '<div class="np-ac-item" data-ref="' + esc(item.Ref) + '" data-label="' + esc(r.label) + '">'
                                + esc(r.label) + (r.sub ? '<div class="np-ac-sub">' + esc(r.sub) + '</div>' : '') + '</div>';
                        }).join('');
                        dd.style.display = 'block';
                    });
                }, 280);
            });
            dd.addEventListener('mousedown', function(e) {
                var item = e.target.closest('.np-ac-item');
                if (!item) return;
                inp.value    = item.dataset.label;
                hidden.value = item.dataset.ref;
                dd.style.display = 'none';
                inp.dispatchEvent(new Event('np-selected', { bubbles: true }));
            });
            document.addEventListener('click', function(e) {
                if (!inp.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
            });
        }

        var curSenderRef = function() { return (document.getElementById('npSenderRef') || {}).value || ''; };

        makeAc('npCityInput', 'npCityDd', 'npCityRef',
            function(q, cb) {
                fetch('/novaposhta/api/search_city?q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.cities||[]); });
            },
            function(c) { return { label: c.Description, sub: c.SettlementTypeDescription || '' }; }
        );
        document.getElementById('npCityInput').addEventListener('np-selected', function() {
            var wh = document.getElementById('npWhInput');
            var ws = document.getElementById('npWhRef');
            var si = document.getElementById('npStreetInput');
            var sr = document.getElementById('npStreetRef');
            if (wh) wh.value = ''; if (ws) ws.value = '';
            if (si) si.value = ''; if (sr) sr.value = '';
        });

        makeAc('npWhInput', 'npWhDd', 'npWhRef',
            function(q, cb) {
                var cityRef = (document.getElementById('npCityRef') || {}).value || '';
                if (!cityRef) { cb([]); return; }
                fetch('/novaposhta/api/search_warehouse?city_ref=' + encodeURIComponent(cityRef)
                    + '&q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.warehouses||[]); });
            },
            function(w) { return { label: 'Відд. №' + w.Number + (w.ShortAddress ? ': ' + w.ShortAddress : ''), sub: w.Description }; }
        );

        makeAc('npStreetInput', 'npStreetDd', 'npStreetRef',
            function(q, cb) {
                var cityRef = (document.getElementById('npCityRef') || {}).value || '';
                if (!cityRef) { cb([]); return; }
                fetch('/novaposhta/api/search_street?city_ref=' + encodeURIComponent(cityRef)
                    + '&q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
                    .then(function(r){ return r.json(); }).then(function(res){ cb(res.streets||[]); });
            },
            function(s) { return { label: s.Description, sub: s.StreetsType || '' }; }
        );

        // Submit
        document.getElementById('npTtnSubmitBtn').addEventListener('click', function() {
            var btn    = this;
            var errDiv = document.getElementById('npTtnError');
            errDiv.style.display = 'none';

            var senderRef   = (document.getElementById('npSenderRef')   || {}).value || '';
            var addrSel     = document.getElementById('npSenderAddr');
            var senderAddr  = addrSel ? addrSel.value : '';
            var cityRcpRef  = (document.getElementById('npCityRef')     || {}).value || '';
            var cityRcpDesc = (document.getElementById('npCityInput')   || {}).value || '';
            var phone       = ((document.getElementById('npRcpPhone')   || {}).value || '').trim();
            var weight      = parseFloat((document.getElementById('npWeight') || {}).value) || 0;
            var serviceType = (document.getElementById('npServiceType') || {}).value || 'WarehouseWarehouse';
            var whRef       = (document.getElementById('npWhRef')       || {}).value || '';

            if (!senderRef)   { errDiv.textContent = 'Оберіть відправника';         errDiv.style.display = ''; return; }
            if (!senderAddr)  { errDiv.textContent = 'Оберіть адресу відправки';    errDiv.style.display = ''; return; }
            if (!cityRcpRef)  { errDiv.textContent = 'Оберіть місто одержувача';    errDiv.style.display = ''; return; }
            if (!phone)       { errDiv.textContent = 'Введіть телефон одержувача';  errDiv.style.display = ''; return; }
            if (weight <= 0)  { errDiv.textContent = 'Вага повинна бути > 0';       errDiv.style.display = ''; return; }
            if ((serviceType === 'WarehouseWarehouse' || serviceType === 'DoorsWarehouse') && !whRef) {
                errDiv.textContent = 'Оберіть відділення одержувача'; errDiv.style.display = ''; return;
            }

            var addrOpt = addrSel ? addrSel.options[addrSel.selectedIndex] : null;
            var citySenderRef  = addrOpt ? (addrOpt.dataset.city     || '') : '';
            var citySenderDesc = addrOpt ? (addrOpt.dataset.cityDesc || '') : '';

            var dateVal   = (document.getElementById('npDate') || {}).value || '';
            var dateParts = dateVal.split('-');
            var dateNp    = dateParts.length === 3 ? dateParts[2]+'.'+dateParts[1]+'.'+dateParts[0] : '';

            btn.disabled = true; btn.textContent = 'Створення…';

            var body = [
                'customerorder_id='        + _orderId,
                'sender_ref='              + encodeURIComponent(senderRef),
                'sender_address_ref='      + encodeURIComponent(senderAddr),
                'city_sender_ref='         + encodeURIComponent(citySenderRef),
                'city_sender_desc='        + encodeURIComponent(citySenderDesc),
                'city_recipient_ref='      + encodeURIComponent(cityRcpRef),
                'city_recipient_desc='     + encodeURIComponent(cityRcpDesc),
                'service_type='            + encodeURIComponent(serviceType),
                'recipient_type=PrivatePerson',
                'recipient_last_name='     + encodeURIComponent(((document.getElementById('npRcpLast')||{}).value||'').trim()),
                'recipient_first_name='    + encodeURIComponent(((document.getElementById('npRcpFirst')||{}).value||'').trim()),
                'recipient_middle_name=',
                'recipient_phone='         + encodeURIComponent(phone),
                'counterparty_id='         + (_prefillData && _prefillData.recipient ? (_prefillData.recipient.counterparty_id || 0) : 0),
                'recipient_warehouse_ref=' + encodeURIComponent(whRef),
                'recipient_address_desc='  + encodeURIComponent(((document.getElementById('npWhInput')||{}).value||'')),
                'recipient_street_ref='    + encodeURIComponent(((document.getElementById('npStreetRef')||{}).value||'')),
                'recipient_building='      + encodeURIComponent(((document.getElementById('npBuilding')||{}).value||'').trim()),
                'recipient_flat='          + encodeURIComponent(((document.getElementById('npFlat')||{}).value||'').trim()),
                'weight='                  + weight,
                'seats_amount='            + (parseInt(((document.getElementById('npSeats')||{}).value||'1'))||1),
                'cargo_type=Cargo',
                'description='             + encodeURIComponent(((document.getElementById('npDesc')||{}).value||'Товар').trim()),
                'cost='                    + (parseInt(((document.getElementById('npCost')||{}).value||'1'))||1),
                'payment_method='          + encodeURIComponent(((document.getElementById('npPayMethod')||{}).value||'Cash')),
                'payer_type='              + encodeURIComponent(((document.getElementById('npPayerType')||{}).value||'Recipient')),
                'backward_delivery_money=' + (parseFloat(((document.getElementById('npBackMoney')||{}).value||'0'))||0),
                'date='                    + encodeURIComponent(dateNp),
            ].join('&');

            fetch('/novaposhta/api/create_ttn', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r){ return r.json(); }).then(function(res) {
                btn.disabled = false; btn.textContent = 'Створити ТТН';
                if (!res.ok) {
                    errDiv.textContent = res.error || 'Невідома помилка'; errDiv.style.display = ''; return;
                }
                close();
                ShipmentsPanel.reload();
                _relDocsLoaded = false;
                RelDocsGraph.load(_orderId);
                showToast('ТТН ' + (res.int_doc_number || '') + ' створено ✓');
            }).catch(function() {
                btn.disabled = false; btn.textContent = 'Створити ТТН';
                errDiv.textContent = 'Помилка з\'єднання'; errDiv.style.display = '';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var closeBtn = document.getElementById('newTtnModalClose');
        if (closeBtn) closeBtn.addEventListener('click', close);
    });

    return { open: open, close: close };
}());
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
