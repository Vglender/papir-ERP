<?php
/**
 * Client Portal — публічна форма пошуку замовлення.
 * Клієнт вводить номер замовлення + телефон → отримує посилання на портал.
 */

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../integrations/AppRegistry.php';

$dbConfigs = require __DIR__ . '/../database/config/databases.php';
Database::init($dbConfigs);

AppRegistry::boot();
if (!AppRegistry::isActive('client_portal')) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>404</title><p>Not found.</p>';
    exit;
}
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Знайти моє замовлення · Papir ERP</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/modules/client_portal/assets/portal.css">
</head>
<body>

<header class="cp-brand">
    <div class="cp-brand__inner">
        <div class="cp-brand__logo">
            <span class="cp-brand__name">Papir ERP</span>
            <span class="cp-brand__sub">Кабінет клієнта</span>
        </div>
    </div>
</header>

<div class="cp-wrap">
    <section class="cp-card cp-lookup">
        <h1 class="cp-lookup__title">Знайти моє замовлення</h1>
        <p class="cp-lookup__desc">
            Введіть номер замовлення та телефон, вказаний при оформленні.
            Ми покажемо статус, позиції, документи та реквізити для оплати.
        </p>

        <form id="cpLookupForm" class="cp-lookup__form" novalidate>
            <label class="cp-field">
                <span class="cp-field__label">Номер замовлення</span>
                <input type="text" name="order_number" id="fOrderNumber"
                       placeholder="напр. 98672OFF" autocomplete="off"
                       required>
            </label>
            <label class="cp-field">
                <span class="cp-field__label">Телефон</span>
                <input type="tel" name="phone" id="fPhone"
                       placeholder="+380 67 123 4567"
                       required>
            </label>

            <div id="cpLookupError" class="cp-lookup__error" style="display:none"></div>

            <button type="submit" class="cp-btn cp-btn--primary cp-btn--full" id="cpLookupSubmit">
                Знайти замовлення →
            </button>
        </form>

        <p class="cp-lookup__hint">
            Не пам'ятаєте номер? Напишіть нам у Telegram
            <a href="https://t.me/offtorg_bot" target="_blank" rel="noopener">@offtorg_bot</a>
            або подивіться у SMS/email-підтвердженні замовлення.
        </p>
    </section>
</div>

<footer class="cp-footer">
    <div class="cp-footer__copy">© Papir ERP</div>
</footer>

<script>
(function(){
    var form   = document.getElementById('cpLookupForm');
    var errEl  = document.getElementById('cpLookupError');
    var btn    = document.getElementById('cpLookupSubmit');

    form.addEventListener('submit', function(e){
        e.preventDefault();
        errEl.style.display = 'none';
        var num   = document.getElementById('fOrderNumber').value.trim();
        var phone = document.getElementById('fPhone').value.trim();
        if (!num || !phone) {
            errEl.textContent = 'Заповніть обидва поля';
            errEl.style.display = '';
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Шукаємо…';

        var fd = new FormData();
        fd.append('order_number', num);
        fd.append('phone', phone);

        fetch('/client_portal/api/lookup', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok && d.url) {
                    window.location.href = d.url;
                    return;
                }
                errEl.textContent = d.error || 'Не вдалось знайти замовлення';
                errEl.style.display = '';
                btn.disabled = false;
                btn.textContent = 'Знайти замовлення →';
            })
            .catch(function(){
                errEl.textContent = 'Помилка мережі. Спробуйте ще раз.';
                errEl.style.display = '';
                btn.disabled = false;
                btn.textContent = 'Знайти замовлення →';
            });
    });
})();
</script>
</body>
</html>