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
            'waiting_payment' => array('label' => 'Очікує оплату', 'class' => 'status-yellow'),
            'in_progress' => array('label' => 'В роботі', 'class' => 'status-orange'),
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
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/modules/shared/ttn-detail-modal.css?v=' . filemtime('/var/www/papir/modules/shared/ttn-detail-modal.css') . '">';
require_once __DIR__ . '/../../shared/layout.php';
?>
<link rel="stylesheet" href="/modules/customerorder/css/customerorder-edit.css?v=<?= filemtime(__DIR__ . '/../css/customerorder-edit.css') ?>">
<div class="page-shell">

    <form method="post" action="/customerorder/save">
        <?php require_once __DIR__ . '/../../shared/CsrfService.php'; ?>
        <?= CsrfService::field() ?>
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
                <?php if (!empty($order['id'])): ?>
                <button type="button" class="btn" id="btnPackPrint" title="Друк пакету документів на відвантаження">📦 Пакет ▾</button>
                <?php endif; ?>
                <?php if (!empty($currentCpId)): ?>
                <button type="button" class="btn" id="btnSendTpl" title="Надіслати клієнту">📤 Надіслати ▾</button>
                <button type="button" class="btn" id="btnOpenChat"
                        onclick="ChatModal.open(<?= (int)$currentCpId ?>)"
                        title="Відкрити чат з контрагентом">💬 Чат</button>
                <?php endif; ?>
                <?php if (!empty($order['id'])): ?>
                <button type="button" class="btn" id="btnShareOrder"
                        title="Надіслати замовлення співробітнику">📨 Співробітнику</button>
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
                    'waiting_payment'   => 'background:#fff4e5; color:#b26a00;',
                    'in_progress'       => 'background:#fae8ff; color:#7e22ce;',
                    'shipped'           => 'background:#ede9fe; color:#6d28d9;',
                    'received'          => 'background:#ccfbf1; color:#0f766e;',
                    'return'            => 'background:#ffe4e6; color:#be123c;',
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

                <!-- Payment status icon -->
                <span class="status-icon <?= h($paymentStatus['badge_cls']) ?>" title="Оплата: <?= h($paymentStatus['label']) ?>">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M2 4h12v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4z" stroke="currentColor" stroke-width="1.4"/><path d="M2 4l1-2h10l1 2" stroke="currentColor" stroke-width="1.4"/><path d="M6 8h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M8 6v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                </span>

                <!-- Shipment status icon -->
                <span class="status-icon <?= h($shipmentStatus['badge_cls']) ?>" title="Відвантаження: <?= h($shipmentStatus['label']) ?>">
                    <svg viewBox="0 0 16 16" fill="none"><rect x="1" y="3" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M11 6h2.5l2 2.5V11h-4.5V6z" stroke="currentColor" stroke-width="1.3"/><circle cx="4" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="12.5" cy="12.5" r="1.5" stroke="currentColor" stroke-width="1.2"/></svg>
                </span>

                <!-- Next action hint (from scenario) -->
                <?php
                $_dynamicAction = !empty($order['next_action']) ? $order['next_action'] : null;
                $_dynamicLabel  = !empty($order['next_action_label']) ? $order['next_action_label'] : null;
                $_hasAction     = ($_dynamicAction !== null);
                ?>
                <button type="button" class="next-action-hint<?= $_hasAction ? '' : ' next-action-empty' ?>" id="nextActionBtn"
                        data-next-action="<?= h($_dynamicAction ?: '') ?>"
                        title="<?= $_hasAction ? 'Наступна дія: ' . h($_dynamicLabel) : 'Немає призначених дій' ?>">
                    <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M10 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?= $_hasAction ? h($_dynamicLabel) : 'Немає дій' ?>
                </button>

                <!-- Planned shipment date -->
                <div class="planned-date-wrap" style="margin-left:6px;">
                    <svg class="planned-date-icon" id="plannedDateIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" style="color:var(--text-light);cursor:pointer" title="Планова дата відвантаження"><rect x="2" y="4" width="20" height="17" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M7 2v4M17 2v4M2 10h20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M8 15l3 3 5-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <input type="date" name="planned_shipment_at" id="planned_shipment_at" value="<?= h($plannedShipDate) ?>">
                </div>

                <?php if (!$isNew): ?>
                <!-- Shipment action buttons -->
                <div class="ship-actions-row">
                    <button type="button" id="newTtnNpBtn" class="ship-action-btn ship-action-btn--np" title="Створити ТТН Нова Пошта">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        ТТН
                    </button>
                    <button type="button" id="newDeliveryBtn" class="ship-action-btn ship-action-btn--del" title="Самовивіз або кур'єрська доставка">
                        <span class="ship-action-plus">+</span>
                        Доставка
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

                        <div class="fields-grid-2 cp-fields-row">
                            <div class="f cp-field-main">
                                <label>Контрагент</label>
                                <div class="cp-picker-wrap" id="cpPickerWrap">
                                    <input type="hidden" name="counterparty_id" id="counterparty_id" value="<?= h($currentCpId) ?>">
                                    <input type="text" id="cpPickerInput" class="cp-picker-input"
                                           value="<?= h($counterpartyName) ?>"
                                           placeholder="Пошук контрагента…"
                                           autocomplete="off">
                                    <button type="button" class="cp-picker-clear" id="cpPickerClear" title="Скинути контрагента"<?= $currentCpId ? '' : ' style="display:none"' ?>>×</button>
                                    <button type="button" class="cp-picker-add" id="cpPickerAdd" title="Створити нового контрагента">+</button>
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

                        <?php $hasShipping = !$isNew && !empty($shipping); ?>
                        <div class="f" id="shippingBtnWrap" style="<?= ($hasShipping || $isNew) ? '' : 'display:none' ?>">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-sm" id="btnShipping" title="Дані доставки" style="align-self:flex-start;">+ Доставка</button>
                        </div>

                    </div>
                </div>

            </div><!-- /fields-area -->
        </div><!-- /doc-header -->
    </form>

    <!-- ══ SHIPPING MODAL ══ -->
    <?php
    $sh = $hasShipping ? $shipping : array();
    $shVal = function($key) use ($sh) { return isset($sh[$key]) ? $sh[$key] : ''; };
    ?>
    <div class="modal-overlay" id="shippingOverlay">
        <div class="modal-box" style="width:420px;">
            <div class="modal-head">
                <h4>Дані доставки</h4>
                <button type="button" class="modal-close" id="shippingClose">&#x2715;</button>
            </div>
            <div class="modal-body" id="shippingModalBody" style="padding:16px 20px;">
                <div class="ship-form" style="display:grid;grid-template-columns:1fr 1fr;gap:8px 12px;font-size:12.5px;">
                    <div class="f"><label>Прізвище</label><input type="text" id="shLastName" value="<?= h($shVal('recipient_last_name')) ?>"></div>
                    <div class="f"><label>Ім'я</label><input type="text" id="shFirstName" value="<?= h($shVal('recipient_first_name')) ?>"></div>
                    <div class="f" style="grid-column:1/-1"><label>Телефон</label><input type="text" id="shPhone" value="<?= h($shVal('recipient_phone')) ?>"></div>
                    <div class="f" style="grid-column:1/-1"><label>Місто</label><input type="text" id="shCity" value="<?= h($shVal('city_name')) ?>"></div>
                    <div class="f" style="grid-column:1/-1"><label>Відділення / поштомат</label><input type="text" id="shBranch" value="<?= h($shVal('branch_name')) ?>"></div>
                    <div class="f" style="grid-column:1/-1"><label>Вулиця</label><input type="text" id="shStreet" value="<?= h($shVal('street')) ?>"></div>
                    <div class="f"><label>Будинок</label><input type="text" id="shHouse" value="<?= h($shVal('house')) ?>"></div>
                    <div class="f"><label>Квартира</label><input type="text" id="shFlat" value="<?= h($shVal('flat')) ?>"></div>
                    <div class="f"><label>Індекс</label><input type="text" id="shPostcode" value="<?= h($shVal('postcode')) ?>" placeholder="01001"></div>
                    <div class="f" style="grid-column:1/-1"><label>Спосіб доставки</label><input type="text" id="shMethod" value="<?= h($shVal('delivery_method_name')) ?>"></div>
                    <div class="f" style="grid-column:1/-1"><label>Коментар</label><input type="text" id="shComment" value="<?= h($shVal('comment')) ?>"></div>
                    <label style="grid-column:1/-1;display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                        <input type="checkbox" id="shNoCall" <?= !empty($shVal('no_call')) ? 'checked' : '' ?>> Не телефонувати
                    </label>
                </div>
                <input type="hidden" id="shNpRef" value="<?= h($shVal('np_warehouse_ref')) ?>">
                <input type="hidden" id="shDeliveryCode" value="<?= h($shVal('delivery_code')) ?>">
                <div id="shError" class="modal-error" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" id="shCancelBtn">Скасувати</button>
                <button type="button" class="btn btn-primary" id="shSaveBtn">Зберегти</button>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var btn      = document.getElementById('btnShipping');
        var overlay  = document.getElementById('shippingOverlay');
        var closeBtn = document.getElementById('shippingClose');
        var cancelBtn= document.getElementById('shCancelBtn');
        var saveBtn  = document.getElementById('shSaveBtn');
        var errEl    = document.getElementById('shError');
        if (!btn || !overlay) return;

        var orderId = <?= $isNew ? 0 : (int)$order['id'] ?>;

        function open()  { overlay.classList.add('open'); }
        function close() { overlay.classList.remove('open'); errEl.style.display = 'none'; }

        btn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });

        saveBtn.addEventListener('click', function() {
            if (!orderId) {
                // Для нових замовлень — зберігаємо в window._prefillShipping, реальне збереження після save_order
                close();
                return;
            }
            saveBtn.disabled = true;
            saveBtn.textContent = 'Збереження…';
            errEl.style.display = 'none';

            var body = {
                order_id:              orderId,
                recipient_first_name:  document.getElementById('shFirstName').value.trim(),
                recipient_last_name:   document.getElementById('shLastName').value.trim(),
                recipient_phone:       document.getElementById('shPhone').value.trim(),
                city_name:             document.getElementById('shCity').value.trim(),
                branch_name:           document.getElementById('shBranch').value.trim(),
                street:                document.getElementById('shStreet').value.trim(),
                house:                 document.getElementById('shHouse').value.trim(),
                flat:                  document.getElementById('shFlat').value.trim(),
                postcode:              document.getElementById('shPostcode').value.trim(),
                delivery_method_name:  document.getElementById('shMethod').value.trim(),
                comment:               document.getElementById('shComment').value.trim(),
                no_call:               document.getElementById('shNoCall').checked ? 1 : 0,
                np_warehouse_ref:      document.getElementById('shNpRef').value,
                delivery_code:         document.getElementById('shDeliveryCode').value,
            };

            fetch('/customerorder/api/save_shipping', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Зберегти';
                if (res.ok) {
                    close();
                    if (typeof showToast === 'function') showToast('Дані доставки збережено');
                } else {
                    errEl.textContent = res.error || 'Помилка';
                    errEl.style.display = '';
                }
            })
            .catch(function() {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Зберегти';
                errEl.textContent = 'Помилка з\'єднання';
                errEl.style.display = '';
            });
        });

        if (typeof makeDraggable === 'function') makeDraggable(overlay);
    }());
    </script>

    <!-- ══ POSITIONS + TABS ══ -->
    <?php if (!$isNew): ?>
    <div class="positions-panel">

        <!-- Tabs -->
        <div class="tabs-bar">
            <button class="tab-btn active" data-tab="positions">Позиції</button>
            <button class="tab-btn" data-tab="related">Пов'язані документи <?php if ($relatedDocsCount > 0): ?><span class="tab-badge" id="relatedDocsBadge"><?= $relatedDocsCount ?></span><?php endif; ?></button>
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
                        <td class="text-r"><?= number_format((float)field_value($item, 'stock_quantity', 0) - (float)field_value($item, 'reserved_stock_quantity', 0), 3, '.', ' ') ?></td>
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
                    <td colspan="13">
                        <div class="product-search-wrap">
                            <div id="productSearchResults"></div>
                            <input type="text" id="productSearchInput" placeholder="Додати позицію — введіть найменування, код або артикул...">
                        </div>
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
                <div id="ldTtnNumWrap" style="display:none; min-width:180px;">
                    <label style="display:block; font-size:11.5px; color:var(--text-muted); margin-bottom:3px;">Номер ТТН</label>
                    <input type="text" id="ldTtnNumber" placeholder="Штрих-код ТТН…" style="height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; width:100%; box-sizing:border-box;">
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

<!-- ══ QUICK CREATE COUNTERPARTY MODAL ══ -->
<div class="modal-overlay" id="cpQuickCreateModal" style="display:none;">
    <div class="modal-box" style="width:420px; max-width:98vw;">
        <div class="modal-head">
            <span>Новий контрагент</span>
            <button class="modal-close" id="cpQuickCreateClose">&#x2715;</button>
        </div>
        <div class="modal-body" style="padding:16px;">
            <div class="form-row" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Тип</label>
                <select id="cpqType" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
                    <option value="person">Фізична особа</option>
                    <option value="fop">ФОП</option>
                    <option value="company">Юридична особа</option>
                </select>
            </div>
            <div class="form-row" style="margin-bottom:12px;" id="cpqPersonFields">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Прізвище</label>
                <input type="text" id="cpqLastName" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;" placeholder="Прізвище">
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <input type="text" id="cpqFirstName" style="flex:1; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;" placeholder="Ім'я">
                    <input type="text" id="cpqMiddleName" style="flex:1; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;" placeholder="По батькові">
                </div>
            </div>
            <div class="form-row" style="margin-bottom:12px; display:none;" id="cpqCompanyFields">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Назва</label>
                <input type="text" id="cpqCompanyName" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;" placeholder="Назва компанії / ФОП">
            </div>
            <div class="form-row" style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-muted); margin-bottom:4px;">Телефон</label>
                <input type="text" id="cpqPhone" style="width:100%; height:32px; font-size:13px; padding:0 8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;" placeholder="+380...">
            </div>
            <div id="cpqError" class="modal-error" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="cpqSaveBtn">Створити</button>
            <button type="button" class="btn" id="cpqCancelBtn">Скасувати</button>
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
/* ══ PAGE DATA (server → client) ══ */
var _PAGE = {
    orderId:           <?= !$isNew ? (int)$order['id'] : 0 ?>,
    isNew:             <?= $isNew ? 'true' : 'false' ?>,
    items:             <?= json_encode(array_values($items)) ?>,
    order:             <?= json_encode(!empty($order) ? $order : new stdClass()) ?>,
    deliveryMethods:   <?= json_encode(array_map(function($dm) {
        return array('id' => (int)$dm['id'], 'code' => $dm['code'], 'name' => $dm['name_uk'], 'has_ttn' => (int)$dm['has_ttn']);
    }, $deliveryMethods)) ?>,
    cpNameForLink:     <?= json_encode(!empty($counterpartyName) ? $counterpartyName : '', JSON_UNESCAPED_UNICODE) ?>,
    statusInlineStyles:<?= json_encode($_statusInlineStyles) ?>,
    initialContacts:   <?= json_encode($initialContacts) ?>,
    cpId:              <?= (int)$currentCpId ?>,
    orderNumber:       <?= json_encode(field_value($order, 'number', $isNew ? '' : (string)$order['id'])) ?>,
    orderSumTotal:     <?= json_encode(field_value($order, 'sum_total', '0')) ?>,
    orderDate:         <?= json_encode(!empty($order['moment']) ? substr($order['moment'], 0, 10) : date('Y-m-d')) ?>,
    orderMoment:       <?= json_encode(field_value($order, 'moment', '')) ?>,
    orderSum:          <?= json_encode(field_value($order, 'sum', '0.00')) ?>,
    statusColorMap:    (function() {
        var m = {};
        <?php
        foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
            foreach (StatusColors::all($_dt) as $_s => $_e) {
                echo "m['" . $_s . "'] = '" . $_e[2] . "';\n        ";
            }
        }
        ?>
        return m;
    }()),
    statusLabelMap:    (function() {
        var m = {};
        <?php
        foreach (array('customerorder','demand','ttn_np','finance') as $_dt) {
            foreach (StatusColors::all($_dt) as $_s => $_e) {
                echo "m['" . $_s . "'] = " . json_encode($_e[0]) . ";\n        ";
            }
        }
        ?>
        return m;
    }())
};
</script>
<script src="/modules/customerorder/js/customerorder-edit.js?v=<?= filemtime(__DIR__ . '/../js/customerorder-edit.js') ?>"></script>

<?php require_once __DIR__ . '/../../shared/print-modal.php'; ?>
<?php require_once __DIR__ . '/../../shared/pack-print-modal.php'; ?>
<script src="/modules/print/js/pack-print.js?v=<?= filemtime(__DIR__ . '/../../print/js/pack-print.js') ?>"></script>
<script>
// ── Pack button in order toolbar ──
(function() {
    var btn = document.getElementById('btnPackPrint');
    if (!btn) return;
    var orderId = <?= (int)(isset($order['id']) ? $order['id'] : 0) ?>;
    var _menu = null;

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (_menu) { _menu.remove(); _menu = null; return; }

        // Fetch demands for this order
        fetch('/customerorder/api/get_order_shipments?order_id=' + orderId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var demands = data.demands || [];
                if (demands.length === 0) {
                    alert('Відвантажень немає. Спочатку створіть відвантаження.');
                    return;
                }
                if (demands.length === 1) {
                    PackPrint.open(demands[0].id);
                    return;
                }
                // Multiple demands — show picker
                var div = document.createElement('div');
                div.style.cssText = 'position:fixed;z-index:9999;background:#fff;border:1px solid #e5e7eb;border-radius:10px;'
                    + 'box-shadow:0 6px 24px rgba(0,0,0,.13);min-width:240px;overflow:hidden;font-family:inherit';
                div.innerHTML = '<div style="padding:8px 13px 6px;border-bottom:1px solid #f3f4f6;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px">Обрати відвантаження</div>';
                demands.forEach(function(d) {
                    var row = document.createElement('div');
                    row.style.cssText = 'padding:9px 13px;cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13px;color:#1f2937;transition:background .1s';
                    row.innerHTML = '<span style="font-size:15px">📋</span><span>№ ' + (d.number || d.id) + '</span>'
                        + '<span style="font-size:11px;color:#94a3b8;margin-left:auto">' + (d.status || '') + '</span>';
                    row.addEventListener('mouseover', function() { row.style.background = '#f5f3ff'; });
                    row.addEventListener('mouseout',  function() { row.style.background = ''; });
                    row.addEventListener('click', function() { div.remove(); _menu = null; PackPrint.open(d.id); });
                    div.appendChild(row);
                });
                document.body.appendChild(div);
                _menu = div;
                var rect = btn.getBoundingClientRect();
                div.style.top = (rect.bottom + 4) + 'px';
                div.style.left = Math.min(rect.left, window.innerWidth - 260) + 'px';
                setTimeout(function() {
                    document.addEventListener('click', function _cl(e) {
                        if (!div.contains(e.target)) { div.remove(); _menu = null; document.removeEventListener('click', _cl); }
                    });
                }, 10);
            });
    });
}());
</script>
<script src="/modules/shared/history-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/history-modal.js') ?>"></script>
<script src="/modules/shared/chat-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/chat-modal.js') ?>"></script>
<script src="/modules/shared/ttn-detail-modal.js?v=<?= filemtime(__DIR__ . '/../../shared/ttn-detail-modal.js') ?>"></script>
<script src="/modules/shared/share-order.js?v=<?= filemtime(__DIR__ . '/../../shared/share-order.js') ?>"></script>
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

<script src="/modules/customerorder/js/customerorder-compose.js?v=<?= filemtime(__DIR__ . '/../js/customerorder-compose.js') ?>"></script>
<?php endif; ?>

<script>
// ── TTN Detail Modal — init on order edit page ──
(function() {
    if (typeof TtnDetailModal === 'undefined') return;
    var orderId = _PAGE.orderId;
    TtnDetailModal.init();
    function reloadGraph() {
        _relDocsLoaded = false;
        if (typeof RelDocsGraph !== 'undefined' && orderId) {
            RelDocsGraph.load(orderId);
        }
        if (typeof ShipmentsPanel !== 'undefined') ShipmentsPanel.reload();
    }
    TtnDetailModal.onDelete = reloadGraph;
    TtnDetailModal.onSave   = reloadGraph;
}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
