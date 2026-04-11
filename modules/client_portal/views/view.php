<?php
/** @var array       $order */
/** @var array       $items */
/** @var string      $statusLabel */
/** @var string      $paymentStatusLabel */
/** @var array       $customer */
/** @var array|null  $shipping */
/** @var array       $ttns */
/** @var string      $telegramUrl */
/** @var string      $requisitesUrl */
/** @var string      $invoiceUrl */

$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$ccy = $order['currency_code'] ? $order['currency_code'] : 'UAH';
$num = $order['number'] ? $order['number'] : ('#' . $order['id']);
$momentFmt = $order['moment'] ? date('d.m.Y', strtotime($order['moment'])) : '';

$statusClass  = 'cp-status--' . preg_replace('/[^a-z0-9]/i', '', $order['status']);
$payClass     = 'cp-status--' . preg_replace('/[^a-z0-9]/i', '', $order['payment_status']);
?><!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Замовлення <?= $h($num) ?> · Papir ERP</title>
    <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/modules/client_portal/assets/portal.css">
</head>
<body>

<?php
// Збираємо компактний опис доставки для шапки
$shipSnippetParts = array();
if ($shipping) {
    if (!empty($shipping['city']))      $shipSnippetParts[] = $shipping['city'];
    if (!empty($shipping['warehouse'])) $shipSnippetParts[] = $shipping['warehouse'];
    elseif (!empty($shipping['address'])) $shipSnippetParts[] = $shipping['address'];
}
$shipSnippet = $shipSnippetParts ? implode(' · ', $shipSnippetParts) : '';
?>
<!-- ── Brand header ────────────────────────────────────────────────────── -->
<header class="cp-brand">
    <div class="cp-brand__inner">
        <div class="cp-brand__logo">
            <span class="cp-brand__name">Papir ERP</span>
            <span class="cp-brand__sub">Інтерфейс клієнта</span>
        </div>
        <div class="cp-brand__order">
            <span class="cp-brand__label">Замовлення</span>
            <span class="cp-brand__num"><?= $h($num) ?></span>
            <?php if ($shipSnippet): ?>
                <span class="cp-brand__ship">→ <?= $h($shipSnippet) ?></span>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="cp-wrap">

    <!-- ── Summary card ─────────────────────────────────────────────── -->
    <section class="cp-card cp-summary">
        <div class="cp-summary__row">
            <div class="cp-summary__item">
                <div class="cp-summary__label">Статус</div>
                <span class="cp-status <?= $h($statusClass) ?>"><?= $h($statusLabel) ?></span>
            </div>
            <div class="cp-summary__item">
                <div class="cp-summary__label">Оплата</div>
                <span class="cp-status <?= $h($payClass) ?>"><?= $h($paymentStatusLabel) ?></span>
            </div>
            <?php if ($momentFmt): ?>
            <div class="cp-summary__item">
                <div class="cp-summary__label">Дата</div>
                <div class="cp-summary__value"><?= $h($momentFmt) ?></div>
            </div>
            <?php endif; ?>
            <div class="cp-summary__item cp-summary__item--sum">
                <div class="cp-summary__label">Сума</div>
                <div class="cp-summary__value cp-summary__value--big">
                    <?= number_format((float)$order['sum_total'], 2, '.', ' ') ?> <?= $h($ccy) ?>
                </div>
            </div>
        </div>

        <div class="cp-summary__ctas">
            <?php if ($order['payment_status'] !== 'paid'): ?>
                <a class="cp-cta cp-cta--pay" href="<?= $h($requisitesUrl) ?>" target="_blank" rel="noopener">
                    <span class="cp-cta__icon">₴</span>
                    <div class="cp-cta__text">
                        <div class="cp-cta__title">Перейти до оплати</div>
                        <div class="cp-cta__sub">Реквізити та IBAN</div>
                    </div>
                </a>
            <?php endif; ?>
            <a class="cp-cta cp-cta--tg" href="<?= $h($telegramUrl) ?>" target="_blank" rel="noopener">
                <span class="cp-cta__icon">✈</span>
                <div class="cp-cta__text">
                    <div class="cp-cta__title">Написати в Telegram</div>
                    <div class="cp-cta__sub">Швидкий зв'язок</div>
                </div>
            </a>
        </div>
    </section>

    <!-- ── Tabs ─────────────────────────────────────────────────────── -->
    <nav class="cp-tabs" role="tablist">
        <button class="cp-tab cp-tab--active" data-tab="order">Замовлення</button>
        <button class="cp-tab" data-tab="delivery">Доставка</button>
        <button class="cp-tab" data-tab="docs">Документи</button>
    </nav>

    <!-- ── Tab 1: Замовлення ────────────────────────────────────────── -->
    <section class="cp-pane cp-pane--active" data-pane="order">

        <?php if ($customer['name'] || $customer['phone'] || $customer['email']): ?>
            <div class="cp-card">
                <h3 class="cp-h3">Клієнт</h3>
                <div class="cp-kv">
                    <?php if ($customer['name']):  ?><div class="cp-kv__row"><span>Ім'я</span><strong><?= $h($customer['name'])  ?></strong></div><?php endif; ?>
                    <?php if ($customer['phone']): ?><div class="cp-kv__row"><span>Телефон</span><strong><?= $h($customer['phone']) ?></strong></div><?php endif; ?>
                    <?php if ($customer['email']): ?><div class="cp-kv__row"><span>Email</span><strong><?= $h($customer['email']) ?></strong></div><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($order['organization_name'])): ?>
            <div class="cp-card">
                <h3 class="cp-h3">Продавець</h3>
                <div class="cp-kv">
                    <div class="cp-kv__row"><span>Організація</span><strong><?= $h($order['organization_name']) ?></strong></div>
                    <?php if (!empty($order['organization_okpo'])): ?>
                        <div class="cp-kv__row"><span>ЄДРПОУ</span><strong><?= $h($order['organization_okpo']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($order['organization_address'])): ?>
                        <div class="cp-kv__row"><span>Адреса</span><strong><?= $h($order['organization_address']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($order['payment_method_name']) || !empty($order['delivery_method_name'])): ?>
            <div class="cp-card">
                <h3 class="cp-h3">Умови</h3>
                <div class="cp-kv">
                    <?php if (!empty($order['payment_method_name'])): ?>
                        <div class="cp-kv__row"><span>Оплата</span><strong><?= $h($order['payment_method_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($order['delivery_method_name'])): ?>
                        <div class="cp-kv__row"><span>Доставка</span><strong><?= $h($order['delivery_method_name']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="cp-card">
            <h3 class="cp-h3">Позиції (<?= count($items) ?>)</h3>
            <?php if (empty($items)): ?>
                <p class="cp-muted">Позицій немає.</p>
            <?php else: ?>
                <ul class="cp-items">
                    <?php foreach ($items as $it):
                        $pid = (int)$it['product_id'];
                    ?>
                        <li class="cp-item">
                            <div class="cp-item__name">
                                <?= $h($it['product_name']) ?>
                                <?php if ($it['sku']): ?>
                                    <?php if ($pid > 0): ?>
                                        <a href="#" class="cp-item__sku cp-item__sku--link"
                                           data-product-id="<?= $pid ?>"
                                           title="Переглянути фото">
                                            <?= $h($it['sku']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="cp-item__sku"><?= $h($it['sku']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="cp-item__meta">
                                <span><?= (float)$it['quantity'] ?> × <?= number_format((float)$it['price'], 2, '.', ' ') ?></span>
                                <strong><?= number_format((float)$it['sum_row'], 2, '.', ' ') ?> <?= $h($ccy) ?></strong>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="cp-total">
                    <span>Разом</span>
                    <strong><?= number_format((float)$order['sum_total'], 2, '.', ' ') ?> <?= $h($ccy) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Tab 2: Доставка ──────────────────────────────────────────── -->
    <section class="cp-pane" data-pane="delivery">
        <?php if ($shipping): ?>
            <div class="cp-card">
                <h3 class="cp-h3">Адреса доставки</h3>
                <div class="cp-kv">
                    <?php if (!empty($shipping['recipient_name'])): ?>
                        <div class="cp-kv__row"><span>Отримувач</span><strong><?= $h($shipping['recipient_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($shipping['recipient_phone'])): ?>
                        <div class="cp-kv__row"><span>Телефон</span><strong><?= $h($shipping['recipient_phone']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($shipping['city'])): ?>
                        <div class="cp-kv__row"><span>Місто</span><strong><?= $h($shipping['city']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($shipping['warehouse'])): ?>
                        <div class="cp-kv__row"><span>Відділення</span><strong><?= $h($shipping['warehouse']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($shipping['address'])): ?>
                        <div class="cp-kv__row"><span>Адреса</span><strong><?= $h($shipping['address']) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="cp-card">
                <p class="cp-muted">Адреса доставки ще не вказана.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($ttns)): ?>
            <?php foreach ($ttns as $t):
                $ttn = $t['int_doc_number'];
                if (!$ttn) continue;
                $trackUrl = 'https://novaposhta.ua/tracking/' . urlencode($ttn);

                // Колір центральної точки за state_define:
                //  1-3 = створено/обробка (gray), 4 = в дорозі (blue),
                //  5-6 = прибув/на складі (orange), 7 = отримано (green),
                //  8+ = проблеми (red)
                $sd = (int)$t['state_define'];
                if      ($sd >= 7 && $sd <= 7)  $stateColor = 'cp-tl__dot--green';
                elseif  ($sd >= 8)              $stateColor = 'cp-tl__dot--red';
                elseif  ($sd >= 5)              $stateColor = 'cp-tl__dot--orange';
                elseif  ($sd == 4)              $stateColor = 'cp-tl__dot--blue';
                else                            $stateColor = 'cp-tl__dot--gray';

                $createdFmt = !empty($t['ew_date_created'])
                    ? date('d.m.Y', strtotime($t['ew_date_created']))
                    : (!empty($t['moment']) ? date('d.m.Y', strtotime($t['moment'])) : '');
                $etaFmt = !empty($t['estimated_delivery_date'])
                    ? date('d.m.Y', strtotime($t['estimated_delivery_date']))
                    : '';
            ?>
            <div class="cp-card cp-card--ttn">
                <div class="cp-ttn-head">
                    <div>
                        <div class="cp-ttn-head__label">ТТН Нової Пошти</div>
                        <div class="cp-ttn-head__num cp-mono"><?= $h($ttn) ?></div>
                    </div>
                    <a class="cp-btn cp-btn--outline" href="<?= $h($trackUrl) ?>" target="_blank" rel="noopener">
                        Відстежити
                    </a>
                </div>

                <ol class="cp-tl">
                    <!-- Відправлення -->
                    <li class="cp-tl__step">
                        <span class="cp-tl__dot cp-tl__dot--blue"></span>
                        <div class="cp-tl__body">
                            <div class="cp-tl__title">
                                Дата відправлення<?= $createdFmt ? ': ' . $h($createdFmt) : '' ?>
                            </div>
                            <?php
                            // Відправник = місто + організація (продавець), а не контактна особа
                            $fromLine = array();
                            if (!empty($t['city_sender_desc']))       $fromLine[] = $t['city_sender_desc'];
                            if (!empty($order['organization_name']))  $fromLine[] = $order['organization_name'];
                            if ($fromLine): ?>
                                <div class="cp-tl__desc"><?= $h(implode(', ', $fromLine)) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>

                    <!-- Поточний статус -->
                    <li class="cp-tl__step">
                        <span class="cp-tl__dot <?= $h($stateColor) ?>"></span>
                        <div class="cp-tl__body">
                            <?php if (!empty($t['state_name'])): ?>
                                <span class="cp-tl__pill cp-tl__pill<?= str_replace('cp-tl__dot', '', $stateColor) ?>">
                                    <?= $h($t['state_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="cp-tl__pill">Статус невідомий</span>
                            <?php endif; ?>
                        </div>
                    </li>

                    <!-- Отримання -->
                    <li class="cp-tl__step cp-tl__step--last">
                        <span class="cp-tl__dot cp-tl__dot--outline"></span>
                        <div class="cp-tl__body">
                            <div class="cp-tl__title">
                                <?= $sd >= 7 ? 'Отримано' : 'Плановий час доставки' ?><?= $etaFmt ? ': ' . $h($etaFmt) : '' ?>
                            </div>
                            <?php
                            $toLine = array();
                            if (!empty($t['city_recipient_desc']))       $toLine[] = $t['city_recipient_desc'];
                            if (!empty($t['recipient_address_desc']))    $toLine[] = $t['recipient_address_desc'];
                            if ($toLine): ?>
                                <div class="cp-tl__desc"><?= $h(implode(', ', $toLine)) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                </ol>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="cp-card">
                <h3 class="cp-h3">ТТН Нової Пошти</h3>
                <p class="cp-muted">ТТН ще не створено. Ви отримаєте повідомлення коли замовлення буде передано перевізнику.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- ── Tab 3: Документи ─────────────────────────────────────────── -->
    <section class="cp-pane" data-pane="docs">
        <div class="cp-card">
            <h3 class="cp-h3">Документи на оплату</h3>
            <p class="cp-muted">Завантажте реквізити або сформуйте рахунок для оплати цього замовлення.</p>

            <div class="cp-actions">
                <a class="cp-action" href="<?= $h($requisitesUrl) ?>" target="_blank" rel="noopener">
                    <div class="cp-action__icon">₴</div>
                    <div class="cp-action__body">
                        <div class="cp-action__title">Отримати реквізити на оплату</div>
                        <div class="cp-action__desc">IBAN, ЄДРПОУ, сума</div>
                    </div>
                    <div class="cp-action__arrow">→</div>
                </a>
                <a class="cp-action" href="<?= $h($invoiceUrl) ?>" target="_blank" rel="noopener">
                    <div class="cp-action__icon">📄</div>
                    <div class="cp-action__body">
                        <div class="cp-action__title">Отримати рахунок</div>
                        <div class="cp-action__desc">PDF для друку або пересилки</div>
                    </div>
                    <div class="cp-action__arrow">→</div>
                </a>
            </div>
        </div>

        <div class="cp-card">
            <h3 class="cp-h3">Документи по відвантаженню</h3>
            <?php if ($demand): ?>
                <p class="cp-muted">Накладну сформовано. Документ містить підпис та печатку.</p>
                <div class="cp-actions">
                    <a class="cp-action" href="<?= $h($deliveryNoteUrl) ?>" target="_blank" rel="noopener">
                        <div class="cp-action__icon">📦</div>
                        <div class="cp-action__body">
                            <div class="cp-action__title">Накладна з печаткою</div>
                            <div class="cp-action__desc">
                                Відвантаження №<?= $h($demand['number'] ?: ('#' . $demand['id'])) ?> · PDF
                            </div>
                        </div>
                        <div class="cp-action__arrow">→</div>
                    </a>
                </div>
            <?php else: ?>
                <div class="cp-actions">
                    <div class="cp-action cp-action--disabled" aria-disabled="true">
                        <div class="cp-action__icon cp-action__icon--muted">📦</div>
                        <div class="cp-action__body">
                            <div class="cp-action__title">Накладна з печаткою</div>
                            <div class="cp-action__desc">Буде доступна після відвантаження замовлення</div>
                        </div>
                        <div class="cp-action__arrow">⏳</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<!-- ── Footer ──────────────────────────────────────────────────────────── -->
<footer class="cp-footer">
    <div class="cp-footer__inner">
        <div class="cp-footer__text">
            Потрібна допомога? Напишіть нам.
        </div>
        <a class="cp-btn cp-btn--tg" href="<?= $h($telegramUrl) ?>" target="_blank" rel="noopener">
            <span class="cp-btn__icon">✈</span> Написати в Telegram
        </a>
    </div>
    <div class="cp-footer__copy">© Papir ERP</div>
</footer>

<?php
// Код поточного токену для fetch у JS (фото товарів)
$jsCode = isset($_GET['c']) ? preg_replace('/[^A-Za-z0-9]/', '', $_GET['c']) : '';
?>

<!-- ── Photo modal ─────────────────────────────────────────────────────── -->
<div id="cpPhotoModal" class="cp-pm" aria-hidden="true">
    <div class="cp-pm__backdrop" data-pm-close></div>
    <div class="cp-pm__box" role="dialog" aria-modal="true">
        <button type="button" class="cp-pm__close" data-pm-close aria-label="Закрити">&times;</button>
        <div class="cp-pm__head">
            <div class="cp-pm__title" id="cpPmTitle">Завантаження…</div>
            <div class="cp-pm__sku cp-mono" id="cpPmSku"></div>
        </div>
        <div class="cp-pm__body">
            <div class="cp-pm__stage">
                <button type="button" class="cp-pm__nav cp-pm__nav--prev" id="cpPmPrev" aria-label="Попереднє">‹</button>
                <img class="cp-pm__img" id="cpPmImg" alt="">
                <button type="button" class="cp-pm__nav cp-pm__nav--next" id="cpPmNext" aria-label="Наступне">›</button>
            </div>
            <div class="cp-pm__thumbs" id="cpPmThumbs"></div>
            <div class="cp-pm__empty" id="cpPmEmpty" style="display:none">
                Фото для цього товару поки немає.
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var tabs  = document.querySelectorAll('.cp-tab');
    var panes = document.querySelectorAll('.cp-pane');

    function activateTab(key) {
        var found = false;
        tabs.forEach(function(x){
            if (x.getAttribute('data-tab') === key) { x.classList.add('cp-tab--active'); found = true; }
            else                                    { x.classList.remove('cp-tab--active'); }
        });
        panes.forEach(function(p){
            if (p.getAttribute('data-pane') === key) p.classList.add('cp-pane--active');
            else                                     p.classList.remove('cp-pane--active');
        });
        return found;
    }

    tabs.forEach(function(t){
        t.addEventListener('click', function(){
            activateTab(t.getAttribute('data-tab'));
            // Синхронізуємо URL-хеш — щоб перезавантаження/шаринг зберігав стан
            if (history.replaceState) {
                history.replaceState(null, '', '#' + t.getAttribute('data-tab'));
            }
        });
    });

    // Якщо URL має #delivery / #order / #docs — відкриваємо відповідну закладку
    // при завантаженні. Це дозволяє слати пряме посилання з повідомлень,
    // наприклад https://.../p/xxxxxx#delivery
    function applyHash() {
        var h = (window.location.hash || '').replace(/^#/, '');
        if (!h) return;
        activateTab(h);
    }
    applyHash();
    window.addEventListener('hashchange', applyHash);
})();

// ── Photo modal ──────────────────────────────────────────────────────────
(function(){
    var CODE = <?= json_encode($jsCode) ?>;
    var modal   = document.getElementById('cpPhotoModal');
    var titleEl = document.getElementById('cpPmTitle');
    var skuEl   = document.getElementById('cpPmSku');
    var imgEl   = document.getElementById('cpPmImg');
    var stageEl = modal.querySelector('.cp-pm__stage');
    var thumbsEl= document.getElementById('cpPmThumbs');
    var emptyEl = document.getElementById('cpPmEmpty');
    var prevBtn = document.getElementById('cpPmPrev');
    var nextBtn = document.getElementById('cpPmNext');

    var _photos = [];
    var _idx    = 0;

    function open()  { modal.classList.add('cp-pm--open'); document.body.style.overflow = 'hidden'; }
    function close() { modal.classList.remove('cp-pm--open'); document.body.style.overflow = ''; }

    modal.querySelectorAll('[data-pm-close]').forEach(function(el){
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function(e){
        if (!modal.classList.contains('cp-pm--open')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft')  show(_idx - 1);
        else if (e.key === 'ArrowRight') show(_idx + 1);
    });

    function show(i) {
        if (!_photos.length) return;
        if (i < 0) i = _photos.length - 1;
        if (i >= _photos.length) i = 0;
        _idx = i;
        imgEl.src = _photos[i];
        thumbsEl.querySelectorAll('.cp-pm__thumb').forEach(function(th, idx){
            th.classList.toggle('cp-pm__thumb--active', idx === i);
        });
    }
    prevBtn.addEventListener('click', function(){ show(_idx - 1); });
    nextBtn.addEventListener('click', function(){ show(_idx + 1); });

    function loadForProduct(productId) {
        open();
        titleEl.textContent = 'Завантаження…';
        skuEl.textContent   = '';
        imgEl.src = '';
        imgEl.style.display = 'none';
        thumbsEl.innerHTML  = '';
        emptyEl.style.display = 'none';
        stageEl.style.display = '';
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';

        fetch('/client_portal/api/photos?c=' + encodeURIComponent(CODE) + '&product_id=' + productId)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) { titleEl.textContent = 'Товар не знайдено'; emptyEl.style.display = ''; stageEl.style.display = 'none'; return; }
                titleEl.textContent = d.product.name || '';
                skuEl.textContent   = d.product.sku  || '';
                _photos = d.photos || [];
                if (!_photos.length) {
                    emptyEl.style.display = '';
                    stageEl.style.display = 'none';
                    return;
                }
                imgEl.style.display = '';
                // Thumbs
                _photos.forEach(function(url, i){
                    var t = document.createElement('img');
                    t.src = url;
                    t.className = 'cp-pm__thumb';
                    t.addEventListener('click', function(){ show(i); });
                    thumbsEl.appendChild(t);
                });
                if (_photos.length > 1) {
                    prevBtn.style.display = '';
                    nextBtn.style.display = '';
                }
                show(0);
            })
            .catch(function(){
                titleEl.textContent = 'Помилка завантаження';
                emptyEl.style.display = '';
                stageEl.style.display = 'none';
            });
    }

    document.querySelectorAll('.cp-item__sku--link').forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            var pid = parseInt(a.getAttribute('data-product-id'), 10);
            if (pid > 0) loadForProduct(pid);
        });
    });
})();
</script>
</body>
</html>