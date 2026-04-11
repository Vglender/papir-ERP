<?php
/**
 * Client Portal — видача PDF-накладної (з підписом та печаткою) за токеном.
 *
 * Валідує short_code → знаходить замовлення → останнє відвантаження,
 * делегує генерацію print/api/generate_demand_pdf.php, редіректить на PDF.
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

// Останнє відвантаження для цього замовлення
$rDem = Database::fetchRow('Papir',
    "SELECT id FROM demand
     WHERE customerorder_id = {$orderId} AND deleted_at IS NULL
     ORDER BY id DESC LIMIT 1");
if (!$rDem['ok'] || empty($rDem['row'])) {
    http_response_code(404);
    _cpRenderError(
        'Накладна ще не сформована',
        'Документ буде доступний після відвантаження замовлення.',
        $code
    );
    exit;
}
$demandId = (int)$rDem['row']['id'];

// Делегуємо генерацію існуючому ендпоінту
$_GET['demand_id'] = $demandId;

ob_start();
include __DIR__ . '/../print/api/generate_demand_pdf.php';
$json = ob_get_clean();

if (!headers_sent()) {
    header_remove('Content-Type');
}

$data = json_decode($json, true);
if (!empty($data['ok']) && !empty($data['url'])) {
    header('Location: ' . $data['url']);
    exit;
}

$errMsg = isset($data['error']) ? $data['error'] : 'Невідома помилка при створенні накладної';
_cpRenderError('Не вдалось створити накладну', $errMsg, $code);

// ── helpers ─────────────────────────────────────────────────────────────
function _cpRenderError($title, $msg, $code)
{
    $backUrl = '/p/' . preg_replace('/[^A-Za-z0-9]/', '', $code);
    ?><!doctype html>
    <html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
        <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
        <link rel="stylesheet" href="/modules/client_portal/assets/portal.css">
    </head>
    <body>
    <header class="cp-brand">
        <div class="cp-brand__inner">
            <div class="cp-brand__logo">
                <span class="cp-brand__name">Papir ERP</span>
                <span class="cp-brand__sub">Накладна</span>
            </div>
        </div>
    </header>
    <div class="cp-wrap cp-wrap--center">
        <div class="cp-notfound">
            <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
            <a class="cp-btn cp-btn--outline" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">
                ← До замовлення
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
}