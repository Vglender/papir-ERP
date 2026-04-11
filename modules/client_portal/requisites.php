<?php
/**
 * Client Portal — реквізити на оплату.
 * Публічна сторінка. Доступ за коротким кодом у ?c=...
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

// Завантажити замовлення + організацію + дефолтний банк. рахунок
$rOrder = Database::fetchRow('Papir',
    "SELECT co.id, co.number, co.sum_total, co.currency_code, co.organization_id,
            org.name           AS org_name,
            org.okpo           AS org_okpo,
            org.inn            AS org_inn,
            org.vat_number     AS org_vat,
            org.legal_address  AS org_legal_address,
            org.actual_address AS org_actual_address,
            org.director_name  AS org_director,
            org.phone          AS org_phone,
            org.email          AS org_email,
            oba.account_name   AS bank_account_name,
            oba.bank_name      AS bank_name,
            oba.mfo            AS bank_mfo,
            oba.iban           AS bank_iban
     FROM customerorder co
     LEFT JOIN organization org ON org.id = co.organization_id
     LEFT JOIN organization_bank_account oba
            ON oba.organization_id = co.organization_id AND oba.is_default = 1
     WHERE co.id = {$orderId} AND co.deleted_at IS NULL
     LIMIT 1");

if (!$rOrder['ok'] || empty($rOrder['row'])) {
    http_response_code(404);
    require __DIR__ . '/views/not_found.php';
    exit;
}
$o = $rOrder['row'];

$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$ccy = $o['currency_code'] ? $o['currency_code'] : 'UAH';
$num = $o['number'] ? $o['number'] : ('#' . $o['id']);
$backUrl = '/p/' . preg_replace('/[^A-Za-z0-9]/', '', $code);
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Реквізити на оплату · <?= $h($num) ?></title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/modules/client_portal/assets/portal.css">
</head>
<body>

<header class="cp-brand">
    <div class="cp-brand__inner">
        <div class="cp-brand__logo">
            <span class="cp-brand__name">Papir ERP</span>
            <span class="cp-brand__sub">Реквізити на оплату</span>
        </div>
        <a class="cp-brand__back" href="<?= $h($backUrl) ?>">← До замовлення</a>
    </div>
</header>

<div class="cp-wrap">

    <section class="cp-card cp-req">
        <div class="cp-req__title">Замовлення <?= $h($num) ?></div>
        <div class="cp-req__amount">
            <?= number_format((float)$o['sum_total'], 2, '.', ' ') ?> <?= $h($ccy) ?>
        </div>
    </section>

    <?php if (!empty($o['org_name'])): ?>
    <section class="cp-card">
        <h3 class="cp-h3">Отримувач</h3>
        <div class="cp-kv">
            <div class="cp-kv__row"><span>Назва</span><strong><?= $h($o['org_name']) ?></strong></div>
            <?php if (!empty($o['org_okpo'])): ?>
                <div class="cp-kv__row">
                    <span>ЄДРПОУ</span>
                    <strong class="cp-mono cp-copy" data-copy="<?= $h($o['org_okpo']) ?>" title="Натисніть, щоб скопіювати">
                        <?= $h($o['org_okpo']) ?>
                    </strong>
                </div>
            <?php endif; ?>
            <?php if (!empty($o['org_vat'])): ?>
                <div class="cp-kv__row">
                    <span>ІПН</span>
                    <strong class="cp-mono cp-copy" data-copy="<?= $h($o['org_vat']) ?>" title="Натисніть, щоб скопіювати">
                        <?= $h($o['org_vat']) ?>
                    </strong>
                </div>
            <?php endif; ?>
            <?php if (!empty($o['org_legal_address'])): ?>
                <div class="cp-kv__row"><span>Адреса</span><strong><?= $h($o['org_legal_address']) ?></strong></div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($o['bank_iban'])): ?>
    <section class="cp-card">
        <h3 class="cp-h3">Банківські реквізити</h3>
        <div class="cp-kv">
            <div class="cp-kv__row">
                <span>IBAN</span>
                <strong class="cp-mono cp-copy" data-copy="<?= $h($o['bank_iban']) ?>" title="Натисніть, щоб скопіювати">
                    <?= $h($o['bank_iban']) ?>
                </strong>
            </div>
            <?php if (!empty($o['bank_name'])): ?>
                <div class="cp-kv__row"><span>Банк</span><strong><?= $h($o['bank_name']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($o['bank_mfo'])): ?>
                <div class="cp-kv__row"><span>МФО</span><strong class="cp-mono"><?= $h($o['bank_mfo']) ?></strong></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cp-card">
        <h3 class="cp-h3">Призначення платежу</h3>
        <div class="cp-purpose cp-copy" data-copy="Оплата за замовлення №<?= $h($num) ?>">
            Оплата за замовлення №<?= $h($num) ?>
        </div>
        <div class="cp-muted cp-muted--small">Натисніть, щоб скопіювати</div>
    </section>
    <?php else: ?>
    <section class="cp-card">
        <p class="cp-muted">Банківські реквізити для цієї організації ще не налаштовані. Зверніться до нас для отримання інформації.</p>
    </section>
    <?php endif; ?>

    <div class="cp-backlink">
        <a class="cp-btn cp-btn--outline cp-btn--full" href="<?= $h($backUrl) ?>">← Повернутись до замовлення</a>
    </div>

</div>

<footer class="cp-footer">
    <div class="cp-footer__copy">© Papir ERP</div>
</footer>

<script>
document.querySelectorAll('.cp-copy').forEach(function(el){
    el.addEventListener('click', function(){
        var text = el.getAttribute('data-copy') || el.textContent.trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function(){
                el.classList.add('cp-copy--ok');
                setTimeout(function(){ el.classList.remove('cp-copy--ok'); }, 1200);
            });
        }
    });
});
</script>
</body>
</html>