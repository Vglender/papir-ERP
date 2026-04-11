<?php
/**
 * Custom settings view for "order_import" app.
 * Дефолтні організації для імпорту замовлень:
 *   default_org_vat   — платник ПДВ (ТОВ)
 *   default_org_novat — неплатник ПДВ (ФОП/фізособа)
 *
 * Variables from parent: $appKey, $app, $savedSettings
 */
require_once __DIR__ . '/../IntegrationSettingsService.php';

$orgs = array();
$r = \Database::fetchAll('Papir',
    "SELECT id, name, is_vat_payer FROM organization ORDER BY is_vat_payer DESC, name");
if ($r['ok']) $orgs = $r['rows'];

$defaultOrgVat   = (int) IntegrationSettingsService::get('order_import', 'default_org_vat',   '8');
$defaultOrgNoVat = (int) IntegrationSettingsService::get('order_import', 'default_org_novat', '6');
?>

<style>
.oi-section { margin-bottom: 28px; }
.oi-section-title {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text-muted);
    margin-bottom: 12px;
}
.oi-note {
    padding: 12px 16px; border-radius: var(--radius);
    background: #f0f9ff; border: 1px solid #bae6fd;
    color: #0c4a6e; font-size: 13px; line-height: 1.5;
    margin-bottom: 20px;
}
.oi-form .form-group { margin-bottom: 14px; }
.oi-form label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
}
.oi-form select {
    width: 100%; padding: 9px 12px;
    border: 1px solid var(--border); border-radius: 6px;
    font-size: 14px; font-family: inherit;
    background: var(--bg-card); color: var(--text);
}
.oi-form select:focus {
    outline: none; border-color: #475569;
    box-shadow: 0 0 0 3px rgba(71,85,105,.12);
}
.oi-actions { margin-top: 18px; display: flex; align-items: center; gap: 12px; }
.oi-saved { display: none; align-items: center; gap: 6px; color: #15803d; font-size: 13px; font-weight: 500; }
.oi-saved.show { display: inline-flex; }
</style>

<div class="oi-note">
    Ці організації використовуються як <b>дефолтні</b> при імпорті замовлень
    з сайтів (officetorg, menufolder), Prom.ua та як fallback для МойСклад.
    Вибір відбувається автоматично за типом контрагента:
    юрособи/явний ЄДРПОУ → платник ПДВ; ФОП/фізособи → неплатник.
</div>

<form class="oi-form" id="oiForm" autocomplete="off">
    <div class="oi-section">
        <div class="oi-section-title">Дефолтні організації</div>

        <div class="form-group">
            <label for="oi_org_vat">Організація — платник ПДВ (для юросіб)</label>
            <select id="oi_org_vat" name="default_org_vat">
                <?php foreach ($orgs as $o):
                    $isVat = (int)$o['is_vat_payer'] === 1;
                ?>
                <option value="<?php echo (int)$o['id']; ?>"
                    <?php echo (int)$o['id'] === $defaultOrgVat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['name']); ?>
                    <?php echo $isVat ? ' — платник ПДВ' : ' — неплатник'; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="oi_org_novat">Організація — неплатник ПДВ (для ФОП/фізосіб)</label>
            <select id="oi_org_novat" name="default_org_novat">
                <?php foreach ($orgs as $o):
                    $isVat = (int)$o['is_vat_payer'] === 1;
                ?>
                <option value="<?php echo (int)$o['id']; ?>"
                    <?php echo (int)$o['id'] === $defaultOrgNoVat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['name']); ?>
                    <?php echo $isVat ? ' — платник ПДВ' : ' — неплатник'; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="oi-actions">
        <button type="submit" class="btn btn-primary">Зберегти</button>
        <span class="oi-saved" id="oiSaved">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Збережено
        </span>
    </div>
</form>

<script>
(function() {
    var form = document.getElementById('oiForm');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var settings = [
            { key: 'default_org_vat',   value: form.querySelector('[name="default_org_vat"]').value,   secret: 0 },
            { key: 'default_org_novat', value: form.querySelector('[name="default_org_novat"]').value, secret: 0 }
        ];
        var btn = form.querySelector('[type="submit"]');
        btn.disabled = true; btn.textContent = 'Збереження...';
        fetch('/integrations/api/save_settings', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: 'order_import', settings: settings })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false; btn.textContent = 'Зберегти';
            if (d.ok) {
                var msg = document.getElementById('oiSaved');
                msg.classList.add('show');
                setTimeout(function() { msg.classList.remove('show'); }, 2500);
            } else {
                alert(d.error || 'Помилка збереження');
            }
        })
        .catch(function() {
            btn.disabled = false; btn.textContent = 'Зберегти';
            alert("Помилка з'єднання");
        });
    });
}());
</script>