<?php
/** @var array $selOrg — org row with bank_accounts[] */
$o = $selOrg;
$isNew = empty($o['id']);
$bankAccounts = isset($o['bank_accounts']) ? $o['bank_accounts'] : array();
?>
<div class="card" id="orgPanelCard">
    <!-- Tabs -->
    <div class="org-tabs">
        <button class="org-tab active" data-tab="requisites" type="button">Реквізити</button>
        <button class="org-tab" data-tab="bank" type="button">Банк</button>
        <?php if (!$isNew): ?>
        <button class="org-tab" data-tab="assets" type="button">Зображення</button>
        <?php endif; ?>
    </div>

    <form id="orgForm" method="post">
    <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">

    <!-- ── Tab: Реквізити ─────────────────────────────────────────── -->
    <div class="org-tab-pane active" data-pane="requisites">

        <div class="org-form-2col">
            <div class="org-form-row" style="grid-column:span 2">
                <label>Повна назва *</label>
                <input type="text" name="name" value="<?php echo ViewHelper::h($o['name']); ?>" required>
            </div>
            <div class="org-form-row">
                <label>Скорочена назва</label>
                <input type="text" name="short_name" value="<?php echo ViewHelper::h($o['short_name']); ?>">
            </div>
            <div class="org-form-row">
                <label>Псевдонім (префікс номерів)</label>
                <input type="text" name="alias" maxlength="8"
                       value="<?php echo ViewHelper::h($o['alias']); ?>"
                       placeholder="OFF, MFF, FOP…"
                       style="text-transform:uppercase">
            </div>
        </div>

        <div class="org-form-3col">
            <div class="org-form-row">
                <label>ЄДРПОУ / ІПН</label>
                <input type="text" name="okpo" value="<?php echo ViewHelper::h($o['okpo']); ?>">
            </div>
            <div class="org-form-row">
                <label>ІНН</label>
                <input type="text" name="inn" value="<?php echo ViewHelper::h($o['inn']); ?>">
            </div>
            <div class="org-form-row">
                <label>Свідоцтво ПДВ</label>
                <input type="text" name="vat_number" value="<?php echo ViewHelper::h($o['vat_number']); ?>">
            </div>
        </div>

        <div class="org-form-row">
            <label>Юридична адреса</label>
            <textarea name="legal_address" rows="2"><?php echo ViewHelper::h($o['legal_address']); ?></textarea>
        </div>
        <div class="org-form-row">
            <label>Фактична адреса</label>
            <textarea name="actual_address" rows="2"><?php echo ViewHelper::h($o['actual_address']); ?></textarea>
        </div>

        <div class="org-form-2col">
            <div class="org-form-row">
                <label>Директор / ФОП — ПІБ</label>
                <input type="text" name="director_name" value="<?php echo ViewHelper::h($o['director_name']); ?>">
            </div>
            <div class="org-form-row">
                <label>Посада</label>
                <input type="text" name="director_title"
                       value="<?php echo ViewHelper::h($o['director_title']); ?>"
                       placeholder="Директор">
            </div>
        </div>

        <div class="org-form-3col">
            <div class="org-form-row">
                <label>Телефон</label>
                <input type="text" name="phone" value="<?php echo ViewHelper::h($o['phone']); ?>">
            </div>
            <div class="org-form-row">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo ViewHelper::h($o['email']); ?>">
            </div>
            <div class="org-form-row">
                <label>Сайт</label>
                <input type="text" name="website" value="<?php echo ViewHelper::h($o['website']); ?>">
            </div>
        </div>

        <div class="org-form-2col">
            <div class="org-form-row">
                <label>Статус</label>
                <select name="status">
                    <option value="1" <?php echo $o['status'] ? 'selected' : ''; ?>>Активна</option>
                    <option value="0" <?php echo !$o['status'] ? 'selected' : ''; ?>>Неактивна</option>
                </select>
            </div>
        </div>

        <div class="org-form-row">
            <label>Примітка</label>
            <textarea name="description" rows="2"><?php echo ViewHelper::h($o['description']); ?></textarea>
        </div>
    </div>

    <!-- ── Tab: Банк ─────────────────────────────────────────────── -->
    <div class="org-tab-pane" data-pane="bank">
        <?php if ($isNew): ?>
            <p class="text-muted" style="font-size:13px">Спочатку збережіть організацію, потім додайте банківські рахунки.</p>
        <?php else: ?>
        <div class="bank-list" id="bankList">
            <?php foreach ($bankAccounts as $ba): ?>
            <div class="bank-item" data-ba-id="<?php echo (int)$ba['id']; ?>">
                <div class="bank-item-head">
                    <?php echo ViewHelper::h($ba['iban']); ?>
                    <?php if ($ba['is_default']): ?>
                        <span class="badge badge-green" style="font-size:10px">основний</span>
                    <?php endif; ?>
                    <span class="badge badge-gray" style="font-size:10px"><?php echo ViewHelper::h($ba['currency_code']); ?></span>
                </div>
                <div class="bank-item-sub">
                    <?php if ($ba['bank_name']): ?><?php echo ViewHelper::h($ba['bank_name']); ?><?php endif; ?>
                    <?php if ($ba['mfo']): ?> · МФО <?php echo ViewHelper::h($ba['mfo']); ?><?php endif; ?>
                    <?php if ($ba['account_name']): ?> · <?php echo ViewHelper::h($ba['account_name']); ?><?php endif; ?>
                </div>
                <button class="bank-item-del" type="button"
                        data-id="<?php echo (int)$ba['id']; ?>"
                        data-org="<?php echo (int)$o['id']; ?>"
                        title="Видалити рахунок">&#x2715;</button>
            </div>
            <?php endforeach; ?>
            <?php if (empty($bankAccounts)): ?>
            <div class="text-muted" style="font-size:13px;padding:8px 0">Рахунків ще немає</div>
            <?php endif; ?>
        </div>

        <button class="btn btn-ghost btn-sm" type="button" id="bankAddToggle">+ Додати рахунок</button>

        <div class="bank-add-form" id="bankAddForm">
            <div class="org-form-2col">
                <div class="org-form-row" style="grid-column:span 2">
                    <label>IBAN *</label>
                    <input type="text" id="baIban" placeholder="UA…" style="text-transform:uppercase">
                </div>
                <div class="org-form-row">
                    <label>Банк</label>
                    <input type="text" id="baBankName">
                </div>
                <div class="org-form-row">
                    <label>МФО</label>
                    <input type="text" id="baMfo" maxlength="10">
                </div>
                <div class="org-form-row">
                    <label>Назва рахунку</label>
                    <input type="text" id="baName" placeholder="Поточний рахунок">
                </div>
                <div class="org-form-row">
                    <label>Валюта</label>
                    <select id="baCurrency">
                        <option value="UAH">UAH — гривня</option>
                        <option value="USD">USD — долар</option>
                        <option value="EUR">EUR — євро</option>
                    </select>
                </div>
            </div>
            <label style="font-size:13px;cursor:pointer">
                <input type="checkbox" id="baDefault"> Основний рахунок
            </label>
            <div style="display:flex;gap:8px;margin-top:12px">
                <button class="btn btn-primary btn-sm" type="button" id="baSaveBtn">Зберегти</button>
                <button class="btn btn-ghost btn-sm" type="button" id="baCancelBtn">Скасувати</button>
            </div>
            <div id="baError" class="modal-error" style="display:none;margin-top:8px"></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Tab: Зображення ─────────────────────────────────────────── -->
    <?php if (!$isNew): ?>
    <div class="org-tab-pane" data-pane="assets">
        <div class="asset-grid">
            <?php
            $assets = array(
                array('key'=>'logo',      'label'=>'Логотип',  'hint'=>'JPG/PNG, max 400px'),
                array('key'=>'stamp',     'label'=>'Печатка',  'hint'=>'PNG з прозорістю'),
                array('key'=>'signature', 'label'=>'Підпис',   'hint'=>'PNG з прозорістю'),
            );
            foreach ($assets as $a):
                $pathField = $a['key'] . '_path';
                $hasImg    = !empty($o[$pathField]);
                $imgUrl    = $hasImg ? '/' . ltrim($o[$pathField], '/') : '';
            ?>
            <div class="asset-zone <?php echo $hasImg ? 'has-img' : ''; ?>"
                 data-type="<?php echo $a['key']; ?>"
                 data-org="<?php echo (int)$o['id']; ?>"
                 onclick="document.getElementById('assetInput_<?php echo $a['key']; ?>').click()">
                <div class="asset-label"><?php echo $a['label']; ?></div>
                <div class="asset-placeholder">&#x1F4C4;</div>
                <img class="asset-img"
                     id="assetImg_<?php echo $a['key']; ?>"
                     src="<?php echo $hasImg ? ViewHelper::h($imgUrl) : ''; ?>"
                     alt="">
                <div class="asset-hint"><?php echo $a['hint']; ?></div>
                <button class="asset-del" type="button"
                        data-type="<?php echo $a['key']; ?>"
                        data-org="<?php echo (int)$o['id']; ?>"
                        onclick="event.stopPropagation(); orgDeleteAsset(this)"
                        title="Видалити">&#x2715;</button>
            </div>
            <input type="file" accept="image/*"
                   class="asset-input"
                   id="assetInput_<?php echo $a['key']; ?>"
                   data-type="<?php echo $a['key']; ?>"
                   data-org="<?php echo (int)$o['id']; ?>">
            <?php endforeach; ?>
        </div>
        <p class="text-muted" style="font-size:12px">
            Зображення використовуються в PDF-документах. Для печатки і підпису зберігайте PNG з прозорістю.
        </p>
    </div>
    <?php endif; ?>

    </form><!-- /orgForm -->

    <div class="org-panel-foot">
        <button class="btn btn-primary" type="button" id="orgSaveBtn">Зберегти</button>
        <?php if (!$isNew): ?>
        <span id="orgSaveStatus" class="text-muted" style="font-size:12px"></span>
        <?php endif; ?>
    </div>
</div>

<script>
window.orgPanelInit = function () {

    // ── Tabs ─────────────────────────────────────────────────────────────
    document.querySelectorAll('#orgPanelCard .org-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.dataset.tab;
            document.querySelectorAll('#orgPanelCard .org-tab').forEach(function (b) {
                b.classList.toggle('active', b === btn);
            });
            document.querySelectorAll('#orgPanelCard .org-tab-pane').forEach(function (p) {
                p.classList.toggle('active', p.dataset.pane === tab);
            });
        });
    });

    // ── Save org ─────────────────────────────────────────────────────────
    document.getElementById('orgSaveBtn').addEventListener('click', function () {
        var form   = document.getElementById('orgForm');
        var btn    = this;
        var status = document.getElementById('orgSaveStatus');
        var fd     = new FormData(form);
        btn.disabled = true;
        fetch('/print/api/save_organization', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (!d.ok) { showToast('Помилка: ' + (d.error || ''), true); return; }
            showToast('Збережено');
            if (d.id) {
                window.location.href = '/system/organizations?selected=' + d.id;
            }
        })
        .catch(function () { btn.disabled = false; showToast('Помилка мережі', true); });
    });

    // ── Bank accounts ─────────────────────────────────────────────────────
    var bankAddToggle = document.getElementById('bankAddToggle');
    var bankAddForm   = document.getElementById('bankAddForm');
    var baCancelBtn   = document.getElementById('baCancelBtn');
    var baSaveBtn     = document.getElementById('baSaveBtn');

    if (bankAddToggle) {
        bankAddToggle.addEventListener('click', function () {
            bankAddForm.classList.toggle('open');
        });
    }
    if (baCancelBtn) {
        baCancelBtn.addEventListener('click', function () {
            bankAddForm.classList.remove('open');
        });
    }
    if (baSaveBtn) {
        baSaveBtn.addEventListener('click', function () {
            var orgId = <?php echo (int)$o['id']; ?>;
            var iban  = document.getElementById('baIban').value.trim();
            if (!iban) {
                document.getElementById('baError').textContent = 'IBAN обовʼязковий';
                document.getElementById('baError').style.display = 'block';
                return;
            }
            var params = new URLSearchParams({
                org_id:       orgId,
                iban:         iban,
                bank_name:    document.getElementById('baBankName').value,
                mfo:          document.getElementById('baMfo').value,
                account_name: document.getElementById('baName').value,
                currency_code:document.getElementById('baCurrency').value,
                is_default:   document.getElementById('baDefault').checked ? 1 : 0,
            });
            fetch('/print/api/save_bank_account', { method: 'POST', body: params })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    document.getElementById('baError').textContent = d.error || 'Помилка';
                    document.getElementById('baError').style.display = 'block';
                    return;
                }
                showToast('Рахунок збережено');
                window.location.href = '/system/organizations?selected=' + orgId + '#bank';
            });
        });
    }

    // Delete bank account
    document.querySelectorAll('.bank-item-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Видалити рахунок?')) return;
            var params = new URLSearchParams({
                action: 'delete',
                id:     btn.dataset.id,
                org_id: btn.dataset.org,
            });
            fetch('/print/api/save_bank_account', { method: 'POST', body: params })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { showToast('Помилка', true); return; }
                btn.closest('.bank-item').remove();
                showToast('Видалено');
            });
        });
    });

    // ── Asset upload ──────────────────────────────────────────────────────
    document.querySelectorAll('.asset-input').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) return;
            var orgId = input.dataset.org;
            var type  = input.dataset.type;
            var fd    = new FormData();
            fd.append('org_id',     orgId);
            fd.append('asset_type', type);
            fd.append('image',      input.files[0]);
            fetch('/print/api/upload_org_asset', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { showToast('Помилка: ' + (d.error || ''), true); return; }
                var zone = document.querySelector('.asset-zone[data-type="' + type + '"]');
                var img  = document.getElementById('assetImg_' + type);
                img.src  = d.url + '?t=' + Date.now();
                zone.classList.add('has-img');
                showToast('Зображення завантажено');
            });
            input.value = '';
        });
    });
};

window.orgDeleteAsset = function (btn) {
    if (!confirm('Видалити зображення?')) return;
    var params = new URLSearchParams({
        org_id:     btn.dataset.org,
        asset_type: btn.dataset.type,
    });
    fetch('/print/api/delete_org_asset', { method: 'POST', body: params })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (!d.ok) { showToast('Помилка', true); return; }
        var zone = document.querySelector('.asset-zone[data-type="' + btn.dataset.type + '"]');
        var img  = document.getElementById('assetImg_' + btn.dataset.type);
        img.src  = '';
        zone.classList.remove('has-img');
        showToast('Видалено');
    });
};

window.orgPanelInit();
</script>