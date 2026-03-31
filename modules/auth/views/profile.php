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
    overflow: hidden; position: relative; cursor: pointer;
    transition: filter .15s;
}
.profile-avatar-big:hover { filter: brightness(.88); }
.profile-avatar-big img { width:100%;height:100%;object-fit:cover;border-radius:50%; }

/* Avatar picker */
.av-picker { display: flex; flex-direction: column; gap: 12px; }
.av-colors { display: flex; flex-wrap: wrap; gap: 8px; }
.av-color-swatch {
    width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
    border: 3px solid transparent; transition: border-color .12s, transform .12s;
    flex-shrink: 0;
}
.av-color-swatch:hover { transform: scale(1.15); }
.av-color-swatch.selected { border-color: #111; }
.av-upload-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.av-upload-label { cursor: pointer; font-size: 13px; color: var(--blue); display: inline-flex; align-items: center; gap: 5px; }
.av-upload-label:hover { text-decoration: underline; }
.av-reset-btn { font-size: 12px; color: var(--text-muted); cursor: pointer; background: none; border: none; padding: 0; font-family: inherit; }
.av-reset-btn:hover { color: var(--red); }
.av-msg { font-size: 12px; color: var(--text-muted); }

/* Emoji grid */
.av-emoji-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.av-emoji-item {
    width: 36px; height: 36px; border-radius: 8px; border: 2px solid transparent;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; cursor: pointer; background: var(--bg-card);
    transition: border-color .1s, transform .1s; flex-shrink: 0;
}
.av-emoji-item:hover { border-color: var(--border); transform: scale(1.12); }
.av-emoji-item.selected { border-color: #5b8af8; background: #eff6ff; }
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
        <?php if ($me):
            require_once __DIR__ . '/../../auth/avatar_helper.php';
            $_myAvatarVal  = papirAvatarFromSettings($settings);
            $_myAvatarInfo = papirAvatarInfo($_myAvatarVal);
            $_AVATAR_GRADIENTS = papirAvatarGradients();
        ?>
        <div class="profile-avatar-row">
            <div class="profile-avatar-big" id="profileAvatarBig"
                 <?php if (!$_myAvatarInfo['isImage']): ?>
                 style="background:<?php echo $_myAvatarInfo['style']; ?>"
                 <?php endif; ?>>
                <?php if ($_myAvatarInfo['isImage']): ?>
                <img src="<?php echo $_myAvatarInfo['style']; ?>?v=<?php echo time(); ?>" alt="" id="profileAvatarImg">
                <?php else: ?>
                <span id="profileAvatarIni"><?php echo htmlspecialchars($me['initials']); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div class="profile-name"><?php echo htmlspecialchars($me['display_name']); ?></div>
                <div class="profile-role"><?php echo htmlspecialchars($me['role_name']); ?></div>
                <div style="margin-top:8px">
                    <button type="button" class="btn btn-ghost btn-sm" id="btnEditAvatar" style="font-size:12px">Змінити аватар</button>
                </div>
            </div>
        </div>

        <!-- Avatar picker (hidden by default) -->
        <?php
            $_avInfo    = $_myAvatarInfo;
            $_avBgKey   = $_avInfo['bgKey'];
            $_avEmoji   = $_avInfo['emoji'];
            $_avEmojis  = papirAvatarEmojis();
        ?>
        <div id="avatarPickerWrap" style="display:none;margin-bottom:20px;padding:16px;background:var(--bg-hover);border-radius:10px;border:1px solid var(--border)">
            <div class="profile-section-title" style="margin-bottom:10px">Аватар</div>
            <div class="av-picker">

                <!-- Іконки -->
                <div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600">Іконка</div>
                    <div class="av-emoji-grid" id="avEmojiGrid">
                        <div class="av-emoji-item <?php echo (!$_avEmoji && $_avInfo['type'] !== 'image') ? 'selected' : ''; ?>"
                             data-emoji="" title="Ініціали" id="avEmojiInitials">
                            <span style="font-size:11px;font-weight:700;color:var(--text-muted)">АБ</span>
                        </div>
                        <?php foreach ($_avEmojis as $em): ?>
                        <div class="av-emoji-item <?php echo ($_avEmoji === $em) ? 'selected' : ''; ?>"
                             data-emoji="<?php echo htmlspecialchars($em); ?>"
                             title="<?php echo htmlspecialchars($em); ?>">
                            <?php echo $em; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Колір фону -->
                <div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600">Колір фону</div>
                    <div class="av-colors" id="avColorSwatches">
                        <?php foreach ($_AVATAR_GRADIENTS as $colorKey => $grad): ?>
                        <div class="av-color-swatch <?php echo ($_avBgKey === $colorKey) ? 'selected' : ''; ?>"
                             data-color="<?php echo $colorKey; ?>"
                             style="background:<?php echo $grad; ?>"
                             title="<?php echo ucfirst($colorKey); ?>"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Фото -->
                <div class="av-upload-row">
                    <label class="av-upload-label">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 3v8M4 7l4-4 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 13h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        Завантажити фото
                        <input type="file" id="avFileInput" accept="image/*" style="display:none">
                    </label>
                    <?php if ($_myAvatarVal): ?>
                    <button type="button" class="av-reset-btn" id="avResetBtn">✕ Скинути</button>
                    <?php endif; ?>
                </div>
                <div class="av-msg" id="avMsg"></div>
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
    // ── Avatar picker ──────────────────────────────────────────────────────────
    var btnEdit  = document.getElementById('btnEditAvatar');
    var picker   = document.getElementById('avatarPickerWrap');
    var avatarEl = document.getElementById('profileAvatarBig');
    var avMsg    = document.getElementById('avMsg');

    var GRADIENTS = {
        blue:   'linear-gradient(135deg,#5b8af8,#7c3aed)',
        green:  'linear-gradient(135deg,#10b981,#059669)',
        orange: 'linear-gradient(135deg,#f97316,#ea580c)',
        red:    'linear-gradient(135deg,#ef4444,#dc2626)',
        pink:   'linear-gradient(135deg,#ec4899,#db2777)',
        indigo: 'linear-gradient(135deg,#6366f1,#4f46e5)',
        teal:   'linear-gradient(135deg,#14b8a6,#0d9488)',
        gray:   'linear-gradient(135deg,#6b7280,#4b5563)',
        amber:  'linear-gradient(135deg,#fbbf24,#f59e0b)',
        cyan:   'linear-gradient(135deg,#06b6d4,#0891b2)'
    };

    // Track current selected color and emoji
    var curBgColor  = '<?php echo $this_esc_or_default = $_avBgKey; echo $this_esc_or_default; ?>';
    var curEmoji    = '<?php echo htmlspecialchars($_avEmoji, ENT_QUOTES); ?>';

    if (btnEdit && picker) {
        btnEdit.addEventListener('click', function () {
            picker.style.display = picker.style.display === 'none' ? '' : 'none';
        });
    }

    function applyPreviewBg(color) {
        if (avatarEl) avatarEl.style.background = GRADIENTS[color] || GRADIENTS.blue;
        var hdrAv = document.querySelector('.app-user-avatar');
        if (hdrAv && !hdrAv.querySelector('img')) { hdrAv.style.background = GRADIENTS[color] || GRADIENTS.blue; }
    }

    function setPreviewEmoji(emoji) {
        var img = document.getElementById('profileAvatarImg');
        if (img) { img.parentNode.removeChild(img); }
        var ini = document.getElementById('profileAvatarIni');
        if (emoji) {
            if (ini) { ini.textContent = emoji; ini.style.fontSize = '28px'; ini.style.display = ''; }
            else {
                ini = document.createElement('span');
                ini.id = 'profileAvatarIni';
                ini.textContent = emoji;
                ini.style.fontSize = '28px';
                if (avatarEl) avatarEl.appendChild(ini);
            }
            // header
            var hdrAv = document.querySelector('.app-user-avatar');
            if (hdrAv) { hdrAv.innerHTML = '<span style="font-size:16px">' + emoji + '</span>'; hdrAv.style.background = GRADIENTS[curBgColor] || GRADIENTS.blue; }
        } else {
            // Restore initials
            var initials = '<?php echo htmlspecialchars($me['initials']); ?>';
            if (ini) { ini.textContent = initials; ini.style.fontSize = ''; ini.style.display = ''; }
            var hdrAv = document.querySelector('.app-user-avatar');
            if (hdrAv) { hdrAv.innerHTML = initials; hdrAv.style.background = GRADIENTS[curBgColor] || GRADIENTS.blue; }
        }
    }

    function setMsg(text, ok) {
        if (!avMsg) return;
        avMsg.style.color = ok ? '#16a34a' : '#dc2626';
        avMsg.textContent = text;
    }

    // ── Color swatches ────────────────────────────────────────────────────────
    var swatches = document.querySelectorAll('.av-color-swatch');
    swatches.forEach(function (sw) {
        sw.addEventListener('click', function () {
            var color = this.dataset.color;
            swatches.forEach(function(s){ s.classList.remove('selected'); });
            this.classList.add('selected');
            curBgColor = color;
            setMsg('Зберігаємо…', true);

            fetch('/auth/api/save_avatar', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'color=' + encodeURIComponent(color)
            })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    applyPreviewBg(color);
                    setMsg('Збережено ✓', true);
                } else { setMsg(d.error || 'Помилка', false); }
            });
        });
    });

    // ── Emoji items ───────────────────────────────────────────────────────────
    var emojiItems = document.querySelectorAll('.av-emoji-item');
    emojiItems.forEach(function (item) {
        item.addEventListener('click', function () {
            var emoji = this.dataset.emoji; // '' means initials
            emojiItems.forEach(function(e){ e.classList.remove('selected'); });
            this.classList.add('selected');
            curEmoji = emoji;
            setMsg('Зберігаємо…', true);

            var body;
            if (emoji === '') {
                body = 'use_initials=1&bg_color=' + encodeURIComponent(curBgColor);
            } else {
                body = 'emoji=' + encodeURIComponent(emoji) + '&bg_color=' + encodeURIComponent(curBgColor);
            }
            fetch('/auth/api/save_avatar', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    setPreviewEmoji(emoji);
                    applyPreviewBg(curBgColor);
                    setMsg('Збережено ✓', true);
                } else { setMsg(d.error || 'Помилка', false); }
            });
        });
    });

    // ── File upload ───────────────────────────────────────────────────────────
    var fileInput = document.getElementById('avFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var f = this.files[0];
            if (!f) return;
            setMsg('Завантажуємо…', true);
            var fd = new FormData();
            fd.append('avatar_file', f);
            fetch('/auth/api/save_avatar', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    var ini = document.getElementById('profileAvatarIni');
                    if (ini) ini.style.display = 'none';
                    var imgEl = document.getElementById('profileAvatarImg');
                    if (!imgEl) {
                        imgEl = document.createElement('img');
                        imgEl.id = 'profileAvatarImg';
                        imgEl.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%';
                        if (avatarEl) avatarEl.appendChild(imgEl);
                    }
                    imgEl.src = d.url + '?v=' + Date.now();
                    if (avatarEl) avatarEl.style.background = 'none';
                    var hdrAv = document.querySelector('.app-user-avatar');
                    if (hdrAv) {
                        hdrAv.innerHTML = '<img src="' + d.url + '?v=' + Date.now() + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
                        hdrAv.style.background = 'none';
                    }
                    emojiItems.forEach(function(e){ e.classList.remove('selected'); });
                    setMsg('Фото збережено ✓', true);
                } else { setMsg(d.error || 'Помилка', false); }
            });
        });
    }

    // ── Reset ─────────────────────────────────────────────────────────────────
    var resetBtn = document.getElementById('avResetBtn');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (!confirm('Скинути аватар?')) return;
            fetch('/auth/api/save_avatar', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'reset=1'
            })
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) { location.reload(); } });
        });
    }
}());

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
