<?php
require_once __DIR__ . '/../IntegrationSettingsService.php';

$appKey = isset($_GET['key']) ? trim($_GET['key']) : '';
$app    = IntegrationSettingsService::getRegistryEntry($appKey);

if (!$app) {
    http_response_code(404);
    echo '<div class="page-wrap"><h2>Додаток не знайдено</h2><a href="/integrations">Повернутись до каталогу</a></div>';
    return;
}

$savedSettings = IntegrationSettingsService::getAll($appKey);
$categories    = IntegrationSettingsService::getCategories();
$appIsActive   = IntegrationSettingsService::get($appKey, 'is_active', '1') === '1';
$isComingSoon  = isset($app['enabled']) && $app['enabled'] === false;
$catLabel  = isset($categories[$app['category']]['label']) ? $categories[$app['category']]['label'] : '';
$iconFile  = '/assets/images/integr/' . $app['icon'];
$hasIcon   = file_exists($_SERVER['DOCUMENT_ROOT'] . $iconFile);
$initial   = mb_substr($app['name'], 0, 1, 'UTF-8');
?>

<style>
.app-settings-wrap {
    max-width: 720px; margin: 0 auto; padding: 24px 16px;
}
.app-settings-back {
    display: inline-flex; align-items: center; gap: 6px;
    color: var(--text-secondary); font-size: 13px;
    text-decoration: none; margin-bottom: 20px;
}
.app-settings-back:hover { color: var(--text); }
.app-settings-back svg { width: 16px; height: 16px; }

.app-settings-header {
    display: flex; align-items: center; gap: 16px;
    margin-bottom: 28px;
}
.app-settings-icon {
    width: 56px; height: 56px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    color: #64748b; font-weight: 700; font-size: 22px;
    flex-shrink: 0;
}
.app-settings-info h1 {
    font-size: 20px; font-weight: 700; margin: 0 0 4px;
}
.app-settings-info .app-cat {
    font-size: 12px; color: var(--text-muted);
}

.app-settings-note {
    padding: 12px 16px; border-radius: var(--radius);
    background: #f0f9ff; border: 1px solid #bae6fd;
    color: #0c4a6e; font-size: 13px;
    margin-bottom: 24px; line-height: 1.5;
}
.app-settings-note a { color: #0369a1; font-weight: 500; }

.app-settings-form .form-group {
    margin-bottom: 16px;
}
.app-settings-form label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
}
.app-settings-form input[type="text"],
.app-settings-form input[type="password"] {
    width: 100%; padding: 9px 12px;
    border: 1px solid var(--border); border-radius: 6px;
    font-size: 14px; font-family: inherit;
    background: var(--bg-card); color: var(--text);
}
.app-settings-form input:focus {
    outline: none; border-color: #475569;
    box-shadow: 0 0 0 3px rgba(71,85,105,.12);
}
.app-settings-form .input-wrap {
    position: relative;
}
.app-settings-form .toggle-secret {
    position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); padding: 4px;
}
.app-settings-form .toggle-secret:hover { color: var(--text); }

.app-settings-actions {
    display: flex; gap: 10px; margin-top: 24px;
    padding-top: 20px; border-top: 1px solid var(--border);
}

.app-settings-saved {
    display: none; align-items: center; gap: 6px;
    color: #15803d; font-size: 13px; font-weight: 500;
}
.app-settings-saved.show { display: inline-flex; }

/* External link for apps with separate settings */
.app-settings-ext {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 6px;
    background: #475569; color: #fff;
    text-decoration: none; font-size: 14px; font-weight: 500;
}
.app-settings-ext:hover { background: #334155; }
</style>

<div class="app-settings-wrap">

    <a href="/integrations" class="app-settings-back">
        <svg viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Додатки
    </a>

    <div class="app-settings-header">
        <?php if ($hasIcon): ?>
        <div class="app-settings-icon" style="background:none"><img src="<?php echo $iconFile; ?>" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:14px"></div>
        <?php else: ?>
        <div class="app-settings-icon"><?php echo $initial; ?></div>
        <?php endif; ?>
        <div class="app-settings-info">
            <h1><?php echo htmlspecialchars($app['name']); ?></h1>
            <div class="app-cat"><?php echo htmlspecialchars($catLabel); ?> &middot; <?php echo htmlspecialchars($app['description']); ?></div>
        </div>
    </div>

    <?php if (!$isComingSoon && empty($app['custom_view'])): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding:14px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);">
        <label style="font-size:14px;font-weight:600;flex:1;">Додаток активний у проекті</label>
        <label style="position:relative;width:44px;height:24px;cursor:pointer;">
            <input type="checkbox" id="appActiveToggle" <?php echo $appIsActive ? 'checked' : ''; ?> style="opacity:0;width:0;height:0;">
            <span style="position:absolute;inset:0;border-radius:12px;background:<?php echo $appIsActive ? '#22c55e' : '#cbd5e1'; ?>;transition:background .2s;"></span>
            <span style="position:absolute;top:2px;left:<?php echo $appIsActive ? '22px' : '2px'; ?>;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s;"></span>
        </label>
    </div>
    <script>
    (function(){
        var t = document.getElementById('appActiveToggle');
        if (!t) return;
        t.addEventListener('change', function() {
            var track = this.nextElementSibling;
            var knob = track.nextElementSibling;
            track.style.background = this.checked ? '#22c55e' : '#cbd5e1';
            knob.style.left = this.checked ? '22px' : '2px';
            fetch('/integrations/api/toggle_app', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ app_key: '<?php echo $appKey; ?>', is_active: this.checked ? 1 : 0 })
            });
        });
    }());
    </script>
    <?php endif; ?>

    <?php if (!empty($app['note'])): ?>
    <div class="app-settings-note">
        <?php echo htmlspecialchars($app['note']); ?>
        <?php if (!empty($app['settings_url'])): ?>
            &mdash; <a href="<?php echo htmlspecialchars($app['settings_url']); ?>">Перейти до налаштувань</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($app['custom_view'])): ?>
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . $app['custom_view']; ?>
    <?php elseif (!empty($app['settings'])): ?>
    <form class="app-settings-form" id="appSettingsForm" autocomplete="off">
        <input type="hidden" name="app_key" value="<?php echo htmlspecialchars($appKey); ?>">

        <?php foreach ($app['settings'] as $field):
            $fKey    = $field['key'];
            $saved   = isset($savedSettings[$fKey]) ? $savedSettings[$fKey]['value'] : '';
            $isSecret = !empty($field['secret']);
            $inputType = $isSecret ? 'password' : 'text';
        ?>
        <div class="form-group">
            <label for="f_<?php echo $fKey; ?>"><?php echo htmlspecialchars($field['label']); ?></label>
            <div class="input-wrap">
                <input type="<?php echo $inputType; ?>"
                       id="f_<?php echo $fKey; ?>"
                       name="<?php echo htmlspecialchars($fKey); ?>"
                       value="<?php echo htmlspecialchars($saved); ?>"
                       data-secret="<?php echo $isSecret ? '1' : '0'; ?>"
                       placeholder="<?php echo htmlspecialchars($field['label']); ?>">
                <?php if ($isSecret): ?>
                <button type="button" class="toggle-secret" title="Показати/сховати">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M1.5 9s3-5.5 7.5-5.5S16.5 9 16.5 9s-3 5.5-7.5 5.5S1.5 9 1.5 9z" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="app-settings-actions">
            <button type="submit" class="btn btn-primary">Зберегти</button>
            <span class="app-settings-saved" id="savedMsg">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Збережено
            </span>
        </div>
    </form>

    <?php elseif (!empty($app['settings_url'])): ?>
    <a href="<?php echo htmlspecialchars($app['settings_url']); ?>" class="app-settings-ext">
        Перейти до налаштувань
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <?php endif; ?>

    <?php if (!empty($app['extra_links'])): ?>
    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border); display:flex; gap:10px; flex-wrap:wrap;">
        <?php foreach ($app['extra_links'] as $link): ?>
        <a href="<?php echo htmlspecialchars($link['url']); ?>" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php echo htmlspecialchars($link['label']); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
(function() {
    // Toggle secret visibility (only for the standard settings form, not custom views)
    document.querySelectorAll('#appSettingsForm .toggle-secret').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = this.parentElement.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });

    // Save form
    var form = document.getElementById('appSettingsForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var appKey = form.querySelector('[name="app_key"]').value;
        var inputs = form.querySelectorAll('input[name]:not([name="app_key"])');
        var settings = [];
        inputs.forEach(function(inp) {
            settings.push({
                key:    inp.name,
                value:  inp.value,
                secret: inp.dataset.secret === '1' ? 1 : 0
            });
        });

        var btn = form.querySelector('[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Збереження...';

        fetch('/integrations/api/save_settings', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ app_key: appKey, settings: settings })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            btn.textContent = 'Зберегти';
            if (d.ok) {
                var msg = document.getElementById('savedMsg');
                msg.classList.add('show');
                setTimeout(function() { msg.classList.remove('show'); }, 2500);
            } else {
                alert(d.error || 'Помилка збереження');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Зберегти';
            alert('Помилка з\'єднання');
        });
    });
}());
</script>
