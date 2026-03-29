<style>
.profile-wrap { max-width: 600px; margin: 32px auto; padding: 0 16px; }
.profile-back { display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); font-size: 13px; text-decoration: none; margin-bottom: 16px; }
.profile-back:hover { color: var(--text); }
.profile-avatar-row {
    display: flex; align-items: center; gap: 20px; margin-bottom: 28px;
}
.profile-avatar-big {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, #5b8af8, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.profile-name { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
.profile-role { font-size: 13px; color: var(--text-muted); }
.profile-section { margin-bottom: 24px; }
.profile-section-title {
    font-size: 11px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase;
    color: var(--text-muted); margin-bottom: 12px;
}
.profile-field { margin-bottom: 14px; }
.profile-field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
.profile-field select, .profile-field input[type=text] {
    width: 100%; height: 36px; border-radius: 7px;
    border: 1px solid var(--border); background: var(--bg-input, #fff);
    font-family: inherit; font-size: 14px; padding: 0 10px; box-sizing: border-box;
}
.methods-list { display: flex; flex-direction: column; gap: 8px; }
.method-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);
    font-size: 14px;
}
.method-badge {
    padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;
    background: #dbeafe; color: #1d4ed8; flex-shrink: 0;
}
.method-val { flex: 1; color: var(--text-muted); }
.method-check { color: #16a34a; font-size: 16px; }
</style>

<div class="profile-wrap">
    <?php
    $me2 = \Papir\Crm\AuthService::getCurrentUser();
    $back = ($me2 && isset($me2['home_screen'])) ? $me2['home_screen'] : '/catalog';
    // home_screen зберігається в settings
    $s2 = $me2 ? \Papir\Crm\UserRepository::getSettings($me2['user_id']) : array();
    $back = isset($s2['home_screen']) ? $s2['home_screen'] : '/catalog';
    ?>
    <a class="profile-back" href="<?php echo htmlspecialchars($back); ?>">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        До системи
    </a>

    <div class="card">
        <?php if ($me): ?>
        <div class="profile-avatar-row">
            <div class="profile-avatar-big"><?php echo htmlspecialchars($me['initials']); ?></div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($me['display_name']); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($me['role_name']); ?></div>
            </div>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted)">Ви не авторизовані. <a href="/login">Увійти →</a></p>
        <?php endif; ?>

        <div class="profile-section">
            <div class="profile-section-title">Параметри входу</div>
            <div class="methods-list">
                <?php if (empty($loginMethods)): ?>
                    <div style="color:var(--text-muted);font-size:14px;">Способів входу не додано</div>
                <?php else: foreach ($loginMethods as $m): ?>
                <div class="method-item">
                    <span class="method-badge"><?php echo htmlspecialchars(strtoupper($m['provider'])); ?></span>
                    <span class="method-val"><?php echo htmlspecialchars($m['provider_id']); ?></span>
                    <?php if ($m['is_verified']): ?>
                    <span class="method-check" title="Підтверджено">✓</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <?php if ($me): ?>
        <div class="profile-section-title">Налаштування</div>
        <form id="profileForm">
            <div class="profile-field">
                <label>Початковий екран</label>
                <select name="home_screen" id="selHome">
                    <?php
                    $screens = array(
                        '/catalog'        => 'Каталог',
                        '/prices'         => 'Прайси',
                        '/customerorder'  => 'Замовлення',
                        '/counterparties' => 'Контрагенти',
                        '/payments'       => 'Платежі',
                        '/action'         => 'Акції',
                    );
                    $curHome = isset($settings['home_screen']) ? $settings['home_screen'] : '/catalog';
                    foreach ($screens as $url => $lbl):
                    ?>
                    <option value="<?php echo $url; ?>" <?php echo ($curHome === $url ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($lbl); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="btn btn-primary" id="btnSave">Зберегти</button>
                <span id="profileMsg" style="font-size:13px; margin-left:12px; color:var(--text-muted);"></span>
            </div>
        </form>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    var form = document.getElementById('profileForm');
    if (!form) { return; }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSave');
        var msg = document.getElementById('profileMsg');
        btn.disabled = true;
        fetch('/auth/api/save_profile', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(new FormData(form)).toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (d.ok) {
                msg.style.color = '#16a34a';
                msg.textContent = 'Збережено';
                setTimeout(function () { msg.textContent = ''; }, 2500);
            } else {
                msg.style.color = '#dc2626';
                msg.textContent = d.error || 'Помилка';
            }
        })
        .catch(function () { btn.disabled = false; msg.textContent = 'Помилка'; });
    });
}());
</script>
