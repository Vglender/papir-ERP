<?php if ($details === null) { ?>
    <div class="empty">Товар не выбран.</div>
<?php } else { ?>

    <!-- Шапка -->
    <div class="section">
        <div style="font-size:22px;font-weight:bold;line-height:1.25;margin-bottom:8px;">
            <?php echo textVal($details['name']); ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap;">
            <div class="status-badges">
                <?php if ((int)$details['status'] === 1) { ?>
                    <span class="status-pill pill-on">Включен</span>
                <?php } else { ?>
                    <span class="status-pill pill-off">Отключен</span>
                <?php } ?>
                <?php if ((int)$details['real_stock'] > 0) { ?>
                    <span class="status-pill pill-stk">В наличии: <?php echo (int)$details['real_stock']; ?></span>
                <?php } ?>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="/catalog?search=<?php echo (int)$details['product_id']; ?>" class="btn btn-xs btn-ghost" title="Открыть в каталоге">→ Catalog</a>
                <a href="/action?search=<?php echo (int)$details['product_id']; ?>" class="btn btn-xs btn-ghost" title="Открыть в акциях">→ Action</a>
            </div>
        </div>

        <div class="info-grid">
            <div class="k">id_off</div>
            <div class="v"><?php echo (int)$details['id_off']; ?></div>
            <div class="k">Артикул</div>
            <div class="v"><?php echo textVal($details['product_article']); ?></div>
            <div class="k">Обновлено</div>
            <div class="v"><?php echo textVal($details['prices_updated_at'], 'Не считалось'); ?></div>
        </div>
    </div>

    <!-- Закупочные цены -->
    <div class="section">
        <h3>Закупочная цена</h3>
        <div class="price-card">
            <div class="price-group">
                <div class="price-grid-3">
                    <div class="price-item">
                        <div class="price-label">Поставщик</div>
                        <div class="price-value"><?php echo priceVal($details['price_supplier']); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label">Себестоимость (УС)</div>
                        <div class="price-value"><?php echo priceVal($details['price_accounting_cost']); ?></div>
                    </div>
                    <div class="price-item" style="border-color:#1f6feb;">
                        <div class="price-label">Закупочная</div>
                        <div class="price-value"><?php echo priceVal($details['price_purchase']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Расчётные цены -->
    <div class="section">
        <h3>Продажные цены</h3>
        <div class="price-card">
            <div class="price-group">
                <div class="price-group-title">Основные</div>
                <div class="price-grid-3">
                    <div class="price-item">
                        <div class="price-label"><span>Продажная</span><span class="price-tags"><?php if (!empty($details['manual_price_enabled'])) { ?><span class="source-tag manual-tag">M</span><?php } ?><?php if (!empty($details['use_rrp'])) { ?><span class="source-tag">RRP</span><?php } ?></span></div>
                        <div class="price-value"><?php echo priceVal($details['price_sale']); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label"><span>Оптовая</span><?php if (!empty($details['manual_wholesale_enabled'])) { ?><span class="price-tags"><span class="source-tag manual-tag">M</span></span><?php } ?></div>
                        <div class="price-value"><?php echo priceVal($details['price_wholesale']); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label"><span>Дилерская</span><?php if (!empty($details['manual_dealer_enabled'])) { ?><span class="price-tags"><span class="source-tag manual-tag">M</span></span><?php } ?></div>
                        <div class="price-value"><?php echo priceVal($details['price_dealer']); ?></div>
                    </div>
                </div>
            </div>

            <div class="price-group price-group-muted">
                <div class="price-group-title">RRP</div>
                <div class="price-grid-2">
                    <div class="price-item">
                        <div class="price-label"><span>Значение</span><?php if (!empty($details['manual_rrp_enabled'])) { ?><span class="price-tags"><span class="source-tag manual-tag">M</span></span><?php } ?></div>
                        <div class="price-value"><?php echo priceVal($details['price_rrp']); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label">Применяется</div>
                        <div class="price-value"><?php echo !empty($details['use_rrp']) ? 'Да' : 'Нет'; ?></div>
                    </div>
                </div>
            </div>

            <?php if (isset($details['price_act']) && $details['price_act'] !== null && $details['price_act'] !== '') { ?>
            <div class="price-group" style="border-color:#f59e0b;background:#fffbeb;">
                <div class="price-group-title" style="color:#92400e;">Акционная цена</div>
                <div class="price-grid-3">
                    <div class="price-item" style="border-color:#f59e0b;">
                        <div class="price-label">Цена акции</div>
                        <div class="price-value" style="color:#b45309;"><?php echo priceVal($details['price_act']); ?></div>
                    </div>
                    <div class="price-item">
                        <div class="price-label">Скидка / Супер</div>
                        <div class="price-value" style="font-size:14px;">
                            <?php
                            $dParts = array();
                            if (!empty($details['act_discount']))      $dParts[] = (int)$details['act_discount']      . '%';
                            if (!empty($details['act_super_discont'])) $dParts[] = '↓' . (int)$details['act_super_discont'] . '%';
                            echo $dParts ? implode(' / ', $dParts) : '—';
                            ?>
                        </div>
                    </div>
                    <div class="price-item">
                        <div class="price-label">Опубликовано</div>
                        <div class="price-value" style="font-size:13px;">
                            <?php echo isset($details['act_published_at']) && $details['act_published_at'] ? substr($details['act_published_at'], 0, 10) : '—'; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- Дисконтный профиль -->
    <div class="section">
        <h3>Скидки по количеству</h3>
        <?php if (!empty($details['qty_1'])) { ?>
            <div class="price-group">
                <div class="price-group-title">
                    Профиль рассчитан: <?php echo textVal($details['calculated_at'], 'неизвестно'); ?>
                    · Источник: <?php echo $details['qty_source'] === 'packages' ? 'Упаковки' : 'Стратегия'; ?>
                </div>
                <div class="discount-levels">
                    <?php
                    $levels = array(
                        array('qty' => $details['qty_1'], 'pct' => $details['discount_percent_1'], 'price' => $details['price_1']),
                        array('qty' => $details['qty_2'], 'pct' => $details['discount_percent_2'], 'price' => $details['price_2']),
                        array('qty' => $details['qty_3'], 'pct' => $details['discount_percent_3'], 'price' => $details['price_3']),
                    );
                    foreach ($levels as $lvl) {
                        if (empty($lvl['qty'])) continue;
                        echo '<span class="discount-chip">от ' . (int)$lvl['qty'] . ' шт · -' . priceVal($lvl['pct']) . '% · ' . priceVal($lvl['price']) . '</span>';
                    }
                    ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="empty">
                Профиль не рассчитан.
                <button type="button" class="btn btn-small btn-primary" style="margin-left:10px;"
                        onclick="recalculateOne(<?php echo (int)$details['product_id']; ?>)">
                    Рассчитать
                </button>
            </div>
        <?php } ?>
    </div>

    <!-- Упаковки -->
    <div class="section">
        <h3>Упаковки</h3>
        <?php if (!empty($details['packages'])) { ?>
            <div class="settings-card">
                <?php foreach ($details['packages'] as $pkg) { ?>
                    <div class="settings-row">
                        <div class="settings-label">Уровень <?php echo (int)$pkg['level']; ?> · <?php echo textVal($pkg['name']); ?></div>
                        <div class="settings-value"><?php echo (int)$pkg['quantity']; ?> шт</div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="empty">Упаковки не заданы — используется стратегия по сумме.</div>
        <?php } ?>
    </div>

    <!-- Стратегия -->
    <div class="section">
        <h3>Стратегия скидок</h3>
        <div class="settings-card">
            <div class="settings-row">
                <div class="settings-label">Стратегия</div>
                <div class="settings-value">
                    <?php echo textVal($details['discount_strategy_name']); ?>
                    <?php if (!empty($details['discount_strategy_manual'])) { ?><span class="source-tag manual-tag">Ручная</span><?php } else { ?><span class="source-tag">Авто</span><?php } ?>
                </div>
            </div>
            <?php if (!empty($details['small_discount_percent'])) { ?>
                <div class="settings-row">
                    <div class="settings-label">Мелкий опт</div>
                    <div class="settings-value"><?php echo priceVal($details['small_discount_percent']); ?>%</div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">Опт</div>
                    <div class="settings-value"><?php echo priceVal($details['medium_discount_percent']); ?>%</div>
                </div>
                <div class="settings-row">
                    <div class="settings-label">Крупный опт</div>
                    <div class="settings-value"><?php echo priceVal($details['large_discount_percent']); ?>%</div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Ручные overrides -->
    <div class="section">
        <h3>Ручные переопределения</h3>
        <div class="settings-card">
            <?php
            $manuals = array(
                array('label' => 'Закупочная',  'enabled' => 'manual_cost_enabled',       'val' => 'manual_cost'),
                array('label' => 'Продажная',   'enabled' => 'manual_price_enabled',      'val' => 'manual_price'),
                array('label' => 'Оптовая',     'enabled' => 'manual_wholesale_enabled',  'val' => 'manual_wholesale_price'),
                array('label' => 'Дилерская',   'enabled' => 'manual_dealer_enabled',     'val' => 'manual_dealer_price'),
                array('label' => 'RRP',         'enabled' => 'manual_rrp_enabled',        'val' => 'manual_rrp'),
            );
            foreach ($manuals as $m) { ?>
                <div class="settings-row">
                    <div class="settings-label"><?php echo $m['label']; ?></div>
                    <div class="settings-value">
                        <?php if (!empty($details[$m['enabled']])) { ?>
                            <span class="source-tag manual-tag">Ручная</span>
                            <?php echo priceVal($details[$m['val']]); ?>
                        <?php } else { ?>
                            <span style="color:#999">Авто</span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Действия -->
    <div class="section">
        <div class="module-links">
            <button type="button" class="btn btn-primary btn-small"
                    onclick="recalculateOne(<?php echo (int)$details['product_id']; ?>)">
                ↻ Пересчитать
            </button>
        </div>
    </div>

    <!-- ════ Редактирование настроек ════ -->
    <div class="section" id="settingsEditSection">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 style="margin:0;">Настройки цен</h3>
            <button type="button" class="btn btn-small" id="toggleSettingsBtn"
                    onclick="toggleProductSettings()">Редактировать настройки</button>
        </div>

        <div id="productSettingsForm" style="display:none;">
            <style>
                .settings-edit-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:12px; }
                .settings-edit-grid label { display:block; font-size:12px; color:#666; margin-bottom:3px; }
                .settings-edit-grid input[type="text"],
                .settings-edit-grid input[type="number"] { width:100%; box-sizing:border-box; padding:7px 9px; border:1px solid #c8d1dd; border-radius:6px; font-size:13px; }
                .manual-override-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #eef2f6; }
                .manual-override-row:last-child { border-bottom:0; }
                .manual-override-row label { margin:0; font-size:13px; color:#444; flex:0 0 120px; }
                .manual-override-row input[type="number"] { flex:1; padding:5px 8px; border:1px solid #c8d1dd; border-radius:6px; font-size:13px; }
                .manual-override-row input[type="checkbox"] { flex:0 0 auto; }
                .settings-section-title { font-size:12px; font-weight:bold; color:#666; text-transform:uppercase; letter-spacing:.03em; margin:10px 0 6px; }
                .strategy-row { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
                .strategy-row select { flex:1; padding:7px 9px; border:1px solid #c8d1dd; border-radius:6px; font-size:13px; }
                .radio-group { display:flex; gap:10px; align-items:center; font-size:13px; }
                .settings-save-row { display:flex; align-items:center; gap:10px; margin-top:12px; }
                #settingsSaveMsg { font-size:13px; color:#157347; display:none; }
            </style>

            <div class="settings-section-title">Наценки (пусто = использовать глобальные)</div>
            <div class="settings-edit-grid">
                <div>
                    <label for="ps_sale_markup">Продажная %</label>
                    <input type="number" id="ps_sale_markup" step="0.01" min="0"
                           value="<?php echo ($details['sale_markup_percent'] > 0 ? ViewHelper::h($details['sale_markup_percent']) : ''); ?>"
                           placeholder="глобальная">
                </div>
                <div>
                    <label for="ps_wholesale_markup">Оптовая %</label>
                    <input type="number" id="ps_wholesale_markup" step="0.01" min="0"
                           value="<?php echo ($details['wholesale_markup_percent'] > 0 ? ViewHelper::h($details['wholesale_markup_percent']) : ''); ?>"
                           placeholder="глобальная">
                </div>
                <div>
                    <label for="ps_dealer_markup">Дилерская %</label>
                    <input type="number" id="ps_dealer_markup" step="0.01" min="0"
                           value="<?php echo ($details['dealer_markup_percent'] > 0 ? ViewHelper::h($details['dealer_markup_percent']) : ''); ?>"
                           placeholder="глобальная">
                </div>
            </div>

            <div class="settings-section-title">Стратегия скидок</div>
            <div class="strategy-row">
                <?php
                // Determine initial dropdown value
                $psStrategyDropVal = '';
                if (!empty($details['discount_strategy_manual'])) {
                    if (!empty($details['discount_strategy_id'])) {
                        $psStrategyDropVal = (string)(int)$details['discount_strategy_id'];
                    } elseif (!empty($details['custom_small_discount_percent'])) {
                        $psStrategyDropVal = 'custom';
                    } else {
                        $psStrategyDropVal = 'custom';
                    }
                }
                ?>
                <select id="ps_strategy_select" onchange="psOnStrategyChange(this.value)">
                    <option value="" <?php echo $psStrategyDropVal === '' ? 'selected' : ''; ?>>Авто</option>
                    <?php foreach ($strategies as $s) { ?>
                        <option value="<?php echo (int)$s['id']; ?>"
                            <?php echo $psStrategyDropVal === (string)(int)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo ViewHelper::h($s['name']); ?>
                        </option>
                    <?php } ?>
                    <option value="custom" <?php echo $psStrategyDropVal === 'custom' ? 'selected' : ''; ?>>— Ручная (свои %) —</option>
                </select>
            </div>
            <div style="font-size:12px;color:#888;margin-bottom:8px;">
                Авто = система выбирает стратегию по умолчанию (глобальные настройки). Ручная = конкретная стратегия или свои %.
            </div>
            <div id="ps_custom_pcts" style="<?php echo $psStrategyDropVal === 'custom' ? '' : 'display:none;'; ?>background:#f8faff;border:1px solid #d0daea;border-radius:8px;padding:10px;margin-bottom:10px;">
                <div class="settings-edit-grid">
                    <div>
                        <label for="ps_custom_small">Мелкий опт %</label>
                        <input type="number" id="ps_custom_small" step="0.01" min="0"
                               value="<?php echo isset($details['custom_small_discount_percent']) && $details['custom_small_discount_percent'] > 0 ? ViewHelper::h($details['custom_small_discount_percent']) : ''; ?>"
                               placeholder="0">
                    </div>
                    <div>
                        <label for="ps_custom_medium">Опт %</label>
                        <input type="number" id="ps_custom_medium" step="0.01" min="0"
                               value="<?php echo isset($details['custom_medium_discount_percent']) && $details['custom_medium_discount_percent'] > 0 ? ViewHelper::h($details['custom_medium_discount_percent']) : ''; ?>"
                               placeholder="0">
                    </div>
                    <div>
                        <label for="ps_custom_large">Крупный опт %</label>
                        <input type="number" id="ps_custom_large" step="0.01" min="0"
                               value="<?php echo isset($details['custom_large_discount_percent']) && $details['custom_large_discount_percent'] > 0 ? ViewHelper::h($details['custom_large_discount_percent']) : ''; ?>"
                               placeholder="0">
                    </div>
                </div>
            </div>

            <div class="settings-section-title">Ручные цены</div>
            <div style="background:#fafcff;border:1px solid #e8edf3;border-radius:8px;padding:10px;">
                <?php
                $manualFields = array(
                    array('label' => 'Закупочная',  'en' => 'manual_cost_enabled',       'val' => 'manual_cost',            'postEn' => 'manual_cost_enabled',       'postVal' => 'manual_cost'),
                    array('label' => 'Продажная',   'en' => 'manual_price_enabled',      'val' => 'manual_price',           'postEn' => 'manual_price_enabled',      'postVal' => 'manual_price'),
                    array('label' => 'Оптовая',     'en' => 'manual_wholesale_enabled',  'val' => 'manual_wholesale_price', 'postEn' => 'manual_wholesale_enabled',  'postVal' => 'manual_wholesale_price'),
                    array('label' => 'Дилерская',   'en' => 'manual_dealer_enabled',     'val' => 'manual_dealer_price',    'postEn' => 'manual_dealer_enabled',     'postVal' => 'manual_dealer_price'),
                    array('label' => 'RRP',         'en' => 'manual_rrp_enabled',        'val' => 'manual_rrp',             'postEn' => 'manual_rrp_enabled',        'postVal' => 'manual_rrp'),
                );
                foreach ($manualFields as $mf) {
                    $isEnabled = !empty($details[$mf['en']]);
                    $curVal    = isset($details[$mf['val']]) ? (float)$details[$mf['val']] : '';
                    ?>
                    <div class="manual-override-row">
                        <input type="checkbox" id="ps_<?php echo $mf['postEn']; ?>"
                               <?php echo $isEnabled ? 'checked' : ''; ?>
                               onchange="toggleManualInput('ps_<?php echo $mf['postVal']; ?>', this.checked)">
                        <label for="ps_<?php echo $mf['postEn']; ?>"><?php echo $mf['label']; ?></label>
                        <input type="number" id="ps_<?php echo $mf['postVal']; ?>" step="0.01" min="0"
                               value="<?php echo $curVal > 0 ? ViewHelper::h($curVal) : ''; ?>"
                               <?php echo !$isEnabled ? 'disabled' : ''; ?>
                               placeholder="0.00">
                    </div>
                <?php } ?>
            </div>


            <div class="settings-save-row">
                <button type="button" class="btn btn-primary btn-small"
                        onclick="saveProductSettings(<?php echo (int)$details['product_id']; ?>)">
                    Сохранить
                </button>
                <span id="settingsSaveMsg">Сохранено</span>
            </div>
        </div>
    </div>

<?php } ?>

<script>
function recalculateOne(idOff) {
    if (!confirm('Пересчитать цены для товара ' + idOff + '?')) return;
    fetch('/prices/api/recalculate', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + encodeURIComponent(idOff)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            alert('Готово!');
            location.reload();
        } else {
            alert('Ошибка: ' + (data.errors ? data.errors.join(', ') : data.error));
        }
    })
    .catch(function () { alert('Ошибка сети.'); });
}

function toggleProductSettings() {
    var form = document.getElementById('productSettingsForm');
    var btn  = document.getElementById('toggleSettingsBtn');
    if (!form) return;
    if (form.style.display === 'none') {
        form.style.display = 'block';
        btn.textContent = 'Скрыть настройки';
    } else {
        form.style.display = 'none';
        btn.textContent = 'Редактировать настройки';
    }
}

function toggleManualInput(inputId, enabled) {
    var inp = document.getElementById(inputId);
    if (inp) inp.disabled = !enabled;
}

function psOnStrategyChange(v) {
    var block = document.getElementById('ps_custom_pcts');
    if (block) block.style.display = (v === 'custom') ? '' : 'none';
}

function saveProductSettings(productId) {
    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }
    function chk(id) {
        var el = document.getElementById(id);
        return (el && el.checked) ? '1' : '0';
    }

    var strategyDropVal = val('ps_strategy_select');
    var strategyManual, strategyId, customSmall, customMedium, customLarge;

    if (strategyDropVal === '') {
        strategyManual = '0';
        strategyId     = '';
        customSmall    = '';
        customMedium   = '';
        customLarge    = '';
    } else if (strategyDropVal === 'custom') {
        strategyManual = '1';
        strategyId     = '';
        customSmall    = val('ps_custom_small');
        customMedium   = val('ps_custom_medium');
        customLarge    = val('ps_custom_large');
    } else {
        strategyManual = '1';
        strategyId     = strategyDropVal;
        customSmall    = '';
        customMedium   = '';
        customLarge    = '';
    }

    var params = [
        'product_id=' + encodeURIComponent(productId),
        'sale_markup_percent='               + encodeURIComponent(val('ps_sale_markup')),
        'wholesale_markup_percent='          + encodeURIComponent(val('ps_wholesale_markup')),
        'dealer_markup_percent='             + encodeURIComponent(val('ps_dealer_markup')),
        'discount_strategy_id='             + encodeURIComponent(strategyId),
        'discount_strategy_manual='         + encodeURIComponent(strategyManual),
        'custom_small_discount_percent='    + encodeURIComponent(customSmall),
        'custom_medium_discount_percent='   + encodeURIComponent(customMedium),
        'custom_large_discount_percent='    + encodeURIComponent(customLarge),
        'manual_cost_enabled='      + encodeURIComponent(chk('ps_manual_cost_enabled')),
        'manual_cost='              + encodeURIComponent(val('ps_manual_cost')),
        'manual_price_enabled='     + encodeURIComponent(chk('ps_manual_price_enabled')),
        'manual_price='             + encodeURIComponent(val('ps_manual_price')),
        'manual_wholesale_enabled=' + encodeURIComponent(chk('ps_manual_wholesale_enabled')),
        'manual_wholesale_price='   + encodeURIComponent(val('ps_manual_wholesale_price')),
        'manual_dealer_enabled='    + encodeURIComponent(chk('ps_manual_dealer_enabled')),
        'manual_dealer_price='      + encodeURIComponent(val('ps_manual_dealer_price')),
        'manual_rrp_enabled='       + encodeURIComponent(chk('ps_manual_rrp_enabled')),
        'manual_rrp='               + encodeURIComponent(val('ps_manual_rrp')),
        'is_locked=0'
    ];

    fetch('/prices/api/save_product_settings', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.join('&')
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.ok) {
            var msg = document.getElementById('settingsSaveMsg');
            if (msg) { msg.style.display = 'inline'; setTimeout(function () { msg.style.display = 'none'; }, 3000); }
            if (confirm('Настройки сохранены. Пересчитать цены сейчас?')) {
                recalculateOne(productId);
            }
        } else {
            alert('Ошибка: ' + (data.error ? data.error : 'неизвестная ошибка'));
        }
    })
    .catch(function () { alert('Ошибка сети.'); });
}
</script>
