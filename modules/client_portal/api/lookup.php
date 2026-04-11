<?php
/**
 * Public API: пошук замовлення клієнтом за номером + телефоном.
 *
 * POST: order_number, phone
 * Response: { ok: true, url: "https://papir.officetorg.com.ua/p/xxx" }
 *         | { ok: false, error: "Не знайдено" }
 *
 * Захист від перебору: обов'язковий номер телефону, який звіряється
 * з контактами контрагента замовлення (останні 10 цифр).
 *
 * CORS: дозволяємо POST з https://officetorg.com.ua для віджету на сайті.
 */

// ── CORS ─────────────────────────────────────────────────────────────────
$allowedOrigins = array(
    'https://officetorg.com.ua',
    'https://www.officetorg.com.ua',
);
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../database/src/Database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../ClientPortalService.php';

$dbConfigs = require __DIR__ . '/../../database/config/databases.php';
Database::init($dbConfigs);

AppRegistry::boot();
if (!AppRegistry::isActive('client_portal')) {
    echo json_encode(array('ok' => false, 'error' => 'inactive'));
    exit;
}

$orderNumber = isset($_POST['order_number']) ? trim($_POST['order_number']) : '';
$phoneRaw    = isset($_POST['phone'])        ? trim($_POST['phone'])        : '';

if ($orderNumber === '' || $phoneRaw === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть номер замовлення та телефон'));
    exit;
}

// Нормалізація телефону: залишаємо лише цифри, беремо останні 10
$phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
$phoneTail   = strlen($phoneDigits) >= 10 ? substr($phoneDigits, -10) : $phoneDigits;
if (strlen($phoneTail) < 10) {
    echo json_encode(array('ok' => false, 'error' => 'Некоректний номер телефону'));
    exit;
}

// ── Збираємо кандидатів: exact match + (якщо введено лише цифри) regexp match
$candidates = array();

$numEsc = Database::escape('Papir', $orderNumber);
$rExact = Database::fetchRow('Papir',
    "SELECT id, counterparty_id FROM customerorder
     WHERE number = '{$numEsc}' AND deleted_at IS NULL LIMIT 1");
if ($rExact['ok'] && !empty($rExact['row'])) {
    $candidates[] = $rExact['row'];
}

// Якщо користувач ввів лише цифри (без суфіксу типу OFF/MF) — пошук за regexp
if (preg_match('/^\d+$/', $orderNumber)) {
    $digitsEsc = Database::escape('Papir', $orderNumber);
    $rFuzzy = Database::fetchAll('Papir',
        "SELECT id, counterparty_id FROM customerorder
         WHERE number REGEXP '^{$digitsEsc}[A-Za-z]*\$'
           AND deleted_at IS NULL
         ORDER BY id DESC
         LIMIT 20");
    if ($rFuzzy['ok'] && !empty($rFuzzy['rows'])) {
        foreach ($rFuzzy['rows'] as $row) {
            $candidates[] = $row;
        }
    }
}

if (empty($candidates)) {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення не знайдено. Перевірте номер та телефон.'));
    exit;
}

// Перевіряємо телефон проти кожного кандидата, повертаємо перший збіг
$matchedOrderId = 0;
foreach ($candidates as $cand) {
    $oid  = (int)$cand['id'];
    $cpId = (int)$cand['counterparty_id'];

    $phones = array();
    if ($cpId > 0) {
        $rCp = Database::fetchRow('Papir',
            "SELECT cp.phone, cp.phone_alt, cc.phone AS company_phone
             FROM counterparty c
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             WHERE c.id = {$cpId} LIMIT 1");
        if ($rCp['ok'] && !empty($rCp['row'])) {
            foreach (array('phone', 'phone_alt', 'company_phone') as $key) {
                if (!empty($rCp['row'][$key])) $phones[] = $rCp['row'][$key];
            }
        }
    }

    $rShip = Database::fetchRow('Papir',
        "SELECT recipient_phone FROM customerorder_shipping
         WHERE customerorder_id = {$oid} LIMIT 1");
    if ($rShip['ok'] && !empty($rShip['row']['recipient_phone'])) {
        $phones[] = $rShip['row']['recipient_phone'];
    }

    foreach ($phones as $p) {
        $d = preg_replace('/\D+/', '', $p);
        if (strlen($d) >= 10 && substr($d, -10) === $phoneTail) {
            $matchedOrderId = $oid;
            break 2;
        }
    }
}

if (!$matchedOrderId) {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення не знайдено. Перевірте номер та телефон.'));
    exit;
}

$url = ClientPortalService::getOrCreateUrl($matchedOrderId);
if (!$url) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось створити посилання'));
    exit;
}

echo json_encode(array('ok' => true, 'url' => $url));