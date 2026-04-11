<?php
/**
 * Admin API: повернути публічне посилання на портал клієнта для замовлення.
 *
 * GET/POST: order_id
 * Response: { ok: true, url: "https://papir.officetorg.com.ua/p/xxx" }
 *
 * Використовується кнопкою «Надіслати клієнту → Посилання на портал»
 * у customerorder/edit. Захищене auth middleware (роут не публічний).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/src/Database.php';
require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
require_once __DIR__ . '/../ClientPortalService.php';

$dbConfigs = require __DIR__ . '/../../database/config/databases.php';
Database::init($dbConfigs);

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id']
         : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

// Перевірка що замовлення існує та не видалене
$r = Database::fetchRow('Papir',
    "SELECT id, number FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення не знайдено'));
    exit;
}

$url = ClientPortalService::getOrCreateUrl($orderId);
if (!$url) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось створити токен'));
    exit;
}

echo json_encode(array(
    'ok'     => true,
    'url'    => $url,
    'number' => $r['row']['number'],
));