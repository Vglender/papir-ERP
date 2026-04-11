<?php
/**
 * Client Portal — видача PDF-рахунку за коротким токеном.
 *
 * Валідує short_code, знаходить order_id, делегує генерацію
 * існуючому print/api/generate_order_pdf.php, потім редіректить
 * клієнта на готовий PDF URL.
 */

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../integrations/AppRegistry.php';
require_once __DIR__ . '/ClientPortalService.php';

$dbConfigs = require __DIR__ . '/../database/config/databases.php';
Database::init($dbConfigs);

AppRegistry::boot();
if (!AppRegistry::isActive('client_portal')) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>404</title><p>Not found.</p>';
    exit;
}

$code    = isset($_GET['c']) ? (string)$_GET['c'] : '';
$orderId = ClientPortalService::resolveByCode($code);
if (!$orderId) {
    http_response_code(404);
    require __DIR__ . '/views/not_found.php';
    exit;
}

// Делегуємо генерацію існуючому принтер-ендпоінту.
// Він очікує $_GET['order_id'] та повертає JSON з URL готового PDF.
$_GET['order_id'] = $orderId;

ob_start();
include __DIR__ . '/../print/api/generate_order_pdf.php';
$json = ob_get_clean();

// generate_order_pdf.php встановлює Content-Type: application/json — прибираємо,
// щоб можна було видати Location-редірект.
if (!headers_sent()) {
    header_remove('Content-Type');
}

$data = json_decode($json, true);
if (!empty($data['ok']) && !empty($data['url'])) {
    header('Location: ' . $data['url']);
    exit;
}

// Помилка генерації — показуємо дружню сторінку
$errMsg = isset($data['error']) ? $data['error'] : 'Невідома помилка при створенні рахунку';
$backUrl = '/p/' . preg_replace('/[^A-Za-z0-9]/', '', $code);
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Помилка створення рахунку</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/modules/client_portal/assets/portal.css">
</head>
<body>
<header class="cp-brand">
    <div class="cp-brand__inner">
        <div class="cp-brand__logo">
            <span class="cp-brand__name">Papir ERP</span>
            <span class="cp-brand__sub">Рахунок</span>
        </div>
    </div>
</header>
<div class="cp-wrap cp-wrap--center">
    <div class="cp-notfound">
        <h1>Не вдалось створити рахунок</h1>
        <p><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="cp-btn cp-btn--outline" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">
            ← До замовлення
        </a>
    </div>
</div>
</body>
</html>