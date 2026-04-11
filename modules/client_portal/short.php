<?php
/**
 * Client Portal — публічна сторінка замовлення.
 *
 * Точка входу для обох варіантів URL:
 *   /p/{short_code}         — короткий URL (код прокидається через $_GET['c'] з Router)
 *   /client_portal/view?c={short_code}  — прямий URL
 *
 * Ніякої авторизації: доступ за знанням коду (як у Нової Пошти trackingId).
 */

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../integrations/AppRegistry.php';
require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';
require_once __DIR__ . '/ClientPortalService.php';

// Ініціалізувати БД (публічний маршрут — бутстрап інших модулів не викликається)
$dbConfigs = require __DIR__ . '/../database/config/databases.php';
Database::init($dbConfigs);

// Guard: якщо модуль вимкнено — 404
AppRegistry::boot();
if (!AppRegistry::isActive('client_portal')) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>404</title><p>Not found.</p>';
    exit;
}

// Знайти код у query-string або в path
$code = isset($_GET['c']) ? (string)$_GET['c'] : '';
if ($code === '') {
    // Спробувати витягти з шляху /p/{code}
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/p/([A-Za-z0-9]+)/?$#', $path, $m)) {
        $code = $m[1];
    }
}

$orderId = ClientPortalService::resolveByCode($code);
if (!$orderId) {
    http_response_code(404);
    require __DIR__ . '/views/not_found.php';
    exit;
}

// ── Завантажуємо дані замовлення ─────────────────────────────────────────────
$rOrder = Database::fetchRow('Papir',
    "SELECT co.id, co.number, co.status, co.payment_status, co.shipment_status,
            co.moment, co.sum_total, co.currency_code,
            co.counterparty_id, co.organization_id,
            pm.name_uk AS payment_method_name,
            dm.name_uk AS delivery_method_name,
            org.name    AS organization_name,
            org.okpo    AS organization_okpo,
            org.legal_address AS organization_address
     FROM customerorder co
     LEFT JOIN payment_method  pm  ON pm.id  = co.payment_method_id
     LEFT JOIN delivery_method dm  ON dm.id  = co.delivery_method_id
     LEFT JOIN organization    org ON org.id = co.organization_id
     WHERE co.id = {$orderId} AND co.deleted_at IS NULL
     LIMIT 1");
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    http_response_code(404);
    require __DIR__ . '/views/not_found.php';
    exit;
}
$order = $rOrder['row'];

// Позиції
$rItems = Database::fetchAll('Papir',
    "SELECT line_no, product_id, product_name, sku, quantity, price, sum_row
     FROM customerorder_item
     WHERE customerorder_id = {$orderId}
     ORDER BY line_no ASC");
$items = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();

// Статус (переклад на українську)
$rStatus = Database::fetchRow('Papir',
    "SELECT name FROM order_status_i18n
     WHERE code = '" . Database::escape('Papir', $order['status']) . "' AND language_id = 2
     LIMIT 1");
$statusLabel = ($rStatus['ok'] && !empty($rStatus['row'])) ? $rStatus['row']['name'] : $order['status'];

// Статус оплати (людський)
$payLabels = array(
    'not_paid'       => 'Не сплачено',
    'partially_paid' => 'Частково сплачено',
    'paid'           => 'Сплачено',
);
$paymentStatusLabel = isset($payLabels[$order['payment_status']])
    ? $payLabels[$order['payment_status']]
    : $order['payment_status'];

// Дані клієнта
$customer = array('name' => '', 'phone' => '', 'email' => '');
if (!empty($order['counterparty_id'])) {
    $cpId = (int)$order['counterparty_id'];
    $rCp = Database::fetchRow('Papir',
        "SELECT COALESCE(NULLIF(c.name,''), cc.full_name,
                         TRIM(CONCAT_WS(' ', cp.first_name, cp.last_name))) AS display_name,
                COALESCE(NULLIF(cc.phone,''), NULLIF(cp.phone,''), NULLIF(cp.phone_alt,'')) AS phone,
                COALESCE(NULLIF(cc.email,''), NULLIF(cp.email,''))                          AS email
         FROM counterparty c
         LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
         LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
         WHERE c.id = {$cpId} LIMIT 1");
    if ($rCp['ok'] && !empty($rCp['row'])) {
        $customer['name']  = $rCp['row']['display_name'] ?: '';
        $customer['phone'] = $rCp['row']['phone']        ?: '';
        $customer['email'] = $rCp['row']['email']        ?: '';
    }
}

// Дані доставки
$shipping = null;
$rShip = Database::fetchRow('Papir',
    "SELECT recipient_first_name, recipient_last_name, recipient_middle_name,
            recipient_phone, city_name, branch_name, street, house, flat, comment
     FROM customerorder_shipping
     WHERE customerorder_id = {$orderId} LIMIT 1");
if ($rShip['ok'] && !empty($rShip['row'])) {
    $s = $rShip['row'];
    $recipientName = trim(implode(' ', array_filter(array(
        $s['recipient_last_name'], $s['recipient_first_name'], $s['recipient_middle_name']
    ))));
    $streetLine = trim(implode(' ', array_filter(array(
        $s['street'] ? 'вул. ' . $s['street'] : '',
        $s['house']  ? 'буд. ' . $s['house']  : '',
        $s['flat']   ? 'кв. '  . $s['flat']   : ''
    ))));
    $shipping = array(
        'recipient_name'  => $recipientName,
        'recipient_phone' => $s['recipient_phone'],
        'city'            => $s['city_name'],
        'warehouse'       => $s['branch_name'],
        'address'         => $streetLine,
        'comment'         => $s['comment'],
    );
}

// ТТН Нової Пошти (пов'язана напряму через customerorder_id)
$rTtn = Database::fetchAll('Papir',
    "SELECT int_doc_number, state_name, state_define, moment,
            ew_date_created, estimated_delivery_date,
            city_sender_desc, sender_contact_person,
            city_recipient_desc, recipient_address_desc, recipient_contact_person
     FROM ttn_novaposhta
     WHERE customerorder_id = {$orderId} AND deletion_mark = 0
     ORDER BY moment DESC");
$ttns = ($rTtn['ok'] && !empty($rTtn['rows'])) ? $rTtn['rows'] : array();

// Контакт у Telegram з налаштувань
$telegramUrl = IntegrationSettingsService::get('client_portal', 'telegram_contact_url', 'https://t.me/offtorg_bot');

// Останнє відвантаження (demand) для цього замовлення — якщо є, покажемо накладну
$demand = null;
$rDem = Database::fetchRow('Papir',
    "SELECT id, number FROM demand
     WHERE customerorder_id = {$orderId} AND deleted_at IS NULL
     ORDER BY id DESC LIMIT 1");
if ($rDem['ok'] && !empty($rDem['row'])) {
    $demand = $rDem['row'];
}

// Посилання на реквізити/рахунок/накладну (усередині порталу)
$currentCode     = isset($_GET['c']) ? preg_replace('/[^A-Za-z0-9]/', '', $_GET['c']) : '';
$requisitesUrl   = '/client_portal/requisites?c='    . urlencode($currentCode);
$invoiceUrl      = '/client_portal/invoice?c='       . urlencode($currentCode);
$deliveryNoteUrl = '/client_portal/delivery_note?c=' . urlencode($currentCode);

// Рендер
require __DIR__ . '/views/view.php';