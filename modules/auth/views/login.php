<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вхід — Papir ERP</title>
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/modules/shared/ui.css?v=<?php echo filemtime(__DIR__ . '/../../shared/ui.css'); ?>">
<style>
body { background: #0f1117; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
.login-wrap { width: 100%; max-width: 380px; padding: 0 16px; }
.login-logo  { text-align: center; margin-bottom: 32px; }
.login-logo-mark {
    display: inline-flex; align-items: center; justify-content: center;
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #5b8af8, #7c3aed);
    font-size: 26px; font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 12px;
}
.login-logo-name { font-size: 22px; font-weight: 700; color: #fff; }
.login-logo-sub  { font-size: 13px; color: rgba(255,255,255,.35); font-weight: 500; }
.login-card { background: #1a1d27; border: 1px solid rgba(255,255,255,.07); border-radius: 16px; padding: 32px 28px; }
.login-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.login-sub   { font-size: 13px; color: rgba(255,255,255,.4); margin: 0 0 24px; line-height: 1.5; }
.login-label { display: block; font-size: 12px; font-weight: 600; color: rgba(255,255,255,.5); margin-bottom: 6px; letter-spacing: .3px; }
.login-input {
    width: 100%; height: 42px; border-radius: 8px; border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.05); color: #fff; font-size: 15px;
    padding: 0 12px; box-sizing: border-box; font-family: inherit; outline: none; transition: border-color .15s;
}
.login-input:focus { border-color: #5b8af8; }
.login-input::placeholder { color: rgba(255,255,255,.2); }
.login-btn {
    width: 100%; height: 44px; border-radius: 8px; border: none; cursor: pointer;
    background: linear-gradient(135deg, #5b8af8, #7c3aed);
    color: #fff; font-size: 15px; font-weight: 600; font-family: inherit; transition: opacity .15s; margin-top: 4px;
}
.login-btn:hover { opacity: .88; }
.login-btn:disabled { opacity: .45; cursor: not-allowed; }
.login-btn-ghost {
    width: 100%; height: 40px; border-radius: 8px; border: 1px solid rgba(255,255,255,.12); cursor: pointer;
    background: transparent; color: rgba(255,255,255,.55); font-size: 14px; font-weight: 500;
    font-family: inherit; transition: background .12s, color .12s; margin-top: 8px;
}
.login-btn-ghost:hover { background: rgba(255,255,255,.05); color: rgba(255,255,255,.8); }
.login-err { font-size: 13px; color: #f87171; margin-top: 10px; min-height: 18px; }
.login-sep { height: 1px; background: rgba(255,255,255,.07); margin: 20px 0; }
.login-back { background: none; border: none; color: rgba(255,255,255,.35); font-size: 13px; cursor: pointer; padding: 0; font-family: inherit; }
.login-back:hover { color: rgba(255,255,255,.65); }
.login-countdown { font-size: 12px; color: rgba(255,255,255,.3); margin-top: 8px; }
.step { display: none; }
.step.active { display: block; }
.login-greeting { font-size: 13px; color: rgba(255,255,255,.45); margin-bottom: 16px; }
.pwd-strength { font-size: 11px; margin-top: 5px; }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="login-logo-mark">P</div>
        <div class="login-logo-name">Papir ERP</div>
        <div class="login-logo-sub">Система управління</div>
    </div>
    <div class="login-card">

        <!-- Крок 1: телефон -->
        <div class="step active" id="stepPhone">
            <div class="login-title">Вхід до системи</div>
            <div class="login-sub">Введіть номер телефону</div>
            <label class="login-label">Номер телефону</label>
            <input type="tel" class="login-input" id="inpPhone" placeholder="+38 050 000 0000" autocomplete="tel">
            <div class="login-err" id="errPhone"></div>
            <div class="login-sep"></div>
            <button class="login-btn" id="btnNext">Далі →</button>
        </div>

        <!-- Крок 2а: пароль -->
        <div class="step" id="stepPassword">
            <div class="login-greeting" id="greetUser"></div>
            <div class="login-title">Введіть пароль</div>
            <div class="login-sub">Пароль збережений у браузері</div>
            <label class="login-label">Пароль</label>
            <input type="password" class="login-input" id="inpPassword"
                   placeholder="••••••••" autocomplete="current-password">
            <div class="login-err" id="errPassword"></div>
            <div class="login-sep"></div>
            <button class="login-btn" id="btnLoginPwd">Увійти</button>
            <button class="login-btn-ghost" id="btnForgotPwd">Забув пароль — надіслати SMS</button>
            <div style="margin-top:14px; text-align:center;">
                <button class="login-back" id="btnBackFromPwd">← Змінити номер</button>
            </div>
        </div>

        <!-- Крок 2б: OTP (немає пароля або скидання) -->
        <div class="step" id="stepOtp">
            <div class="login-greeting" id="greetUserOtp"></div>
            <div class="login-title" id="otpTitle">Код з SMS</div>
            <div class="login-sub">Код надіслано на <strong id="lblPhone"></strong></div>
            <label class="login-label">6-значний код</label>
            <input type="text" class="login-input" id="inpCode"
                   placeholder="000000" maxlength="6" autocomplete="one-time-code" inputmode="numeric">
            <div class="login-err" id="errCode"></div>
            <div class="login-countdown" id="countdown"></div>
            <div class="login-sep"></div>
            <button class="login-btn" id="btnVerify">Підтвердити</button>
            <div style="margin-top:14px; text-align:center;">
                <button class="login-back" id="btnBackFromOtp">← Змінити номер</button>
            </div>
        </div>

        <!-- Крок 3: встановити пароль -->
        <div class="step" id="stepSetPwd">
            <div class="login-title">Встановіть пароль</div>
            <div class="login-sub">Браузер запропонує зберегти його для наступних входів</div>
            <!-- Прихований username для автозаповнення браузера -->
            <input type="tel" style="display:none" id="hiddenPhone2" autocomplete="username">
            <label class="login-label">Новий пароль</label>
            <input type="password" class="login-input" id="inpNewPwd"
                   placeholder="Мінімум 6 символів" autocomplete="new-password">
            <div class="pwd-strength" id="pwdStrength"></div>
            <label class="login-label" style="margin-top:12px">Повторіть пароль</label>
            <input type="password" class="login-input" id="inpNewPwd2"
                   placeholder="Повторіть пароль" autocomplete="new-password">
            <div class="login-err" id="errSetPwd"></div>
            <div class="login-sep"></div>
            <button class="login-btn" id="btnSetPwd">Зберегти та увійти</button>
            <button class="login-btn-ghost" id="btnSkipPwd">Пропустити — увійти без пароля</button>
        </div>

    </div>
</div>

<script>
(function () {
    var phone      = '';
    var otpToken   = '';
    var otpMode    = 'login'; // 'login' або 'set_password'
    var expiresAt  = null;
    var cdInterval = null;

    function show(id) {
        document.querySelectorAll('.step').forEach(function (s) { s.classList.remove('active'); });
        document.getElementById(id).classList.add('active');
    }
    function setErr(id, msg) { document.getElementById(id).textContent = msg || ''; }

    // ── Крок 1: перевірка телефону ────────────────────────────────────────
    document.getElementById('btnNext').addEventListener('click', checkPhone);
    document.getElementById('inpPhone').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { checkPhone(); }
    });

    function checkPhone() {
        setErr('errPhone', '');
        var val = document.getElementById('inpPhone').value.trim();
        if (!val) { setErr('errPhone', 'Вкажіть номер телефону'); return; }

        var btn = document.getElementById('btnNext');
        btn.disabled = true; btn.textContent = 'Перевіряємо…';

        post('/auth/api/check_phone', {phone: val}, function (d) {
            btn.disabled = false; btn.textContent = 'Далі →';
            if (!d.ok) { setErr('errPhone', d.error); return; }
            phone = val;
            var greeting = 'Привіт, ' + d.name + '!';
            if (d.has_password) {
                // Є пароль — показуємо поле пароля
                document.getElementById('greetUser').textContent = greeting;
                // Підставляємо username для автозаповнення
                var hiddenPh = document.getElementById('hiddenPhone2');
                if (hiddenPh) { hiddenPh.value = val; }
                show('stepPassword');
                document.getElementById('inpPassword').focus();
            } else {
                // Немає пароля — надсилаємо OTP (перший вхід)
                document.getElementById('greetUserOtp').textContent = greeting;
                document.getElementById('otpTitle').textContent = 'Код з SMS';
                otpMode = 'set_password';
                sendOtp(val);
            }
        }, function () { btn.disabled = false; btn.textContent = 'Далі →'; setErr('errPhone', 'Мережева помилка'); });
    }

    // ── Крок 2а: вхід з паролем ───────────────────────────────────────────
    document.getElementById('btnLoginPwd').addEventListener('click', loginPwd);
    document.getElementById('inpPassword').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { loginPwd(); }
    });

    function loginPwd() {
        setErr('errPassword', '');
        var pwd = document.getElementById('inpPassword').value;
        if (!pwd) { setErr('errPassword', 'Введіть пароль'); return; }
        var btn = document.getElementById('btnLoginPwd');
        btn.disabled = true; btn.textContent = 'Входимо…';
        post('/auth/api/login_password', {phone: phone, password: pwd}, function (d) {
            if (d.ok) { window.location.href = d.redirect || '/catalog'; }
            else {
                btn.disabled = false; btn.textContent = 'Увійти';
                setErr('errPassword', d.error);
            }
        }, function () { btn.disabled = false; btn.textContent = 'Увійти'; setErr('errPassword', 'Мережева помилка'); });
    }

    // Забув пароль
    document.getElementById('btnForgotPwd').addEventListener('click', function () {
        otpMode = 'set_password';
        document.getElementById('otpTitle').textContent = 'Скидання пароля';
        sendOtp(phone);
    });

    document.getElementById('btnBackFromPwd').addEventListener('click', function () {
        setErr('errPassword', '');
        document.getElementById('inpPassword').value = '';
        show('stepPhone');
    });

    // ── Крок 2б: OTP ──────────────────────────────────────────────────────
    function sendOtp(ph) {
        post('/auth/api/send_otp', {phone: ph}, function (d) {
            if (!d.ok) { setErr('errPhone', d.error); return; }
            expiresAt = d.expires_at ? new Date(d.expires_at.replace(' ', 'T')) : null;
            document.getElementById('lblPhone').textContent = ph;
            document.getElementById('inpCode').value = '';
            setErr('errCode', '');
            show('stepOtp');
            document.getElementById('inpCode').focus();
            startCountdown();
        }, function () { setErr('errPhone', 'Помилка відправки SMS'); });
    }

    document.getElementById('btnVerify').addEventListener('click', verifyOtp);
    document.getElementById('inpCode').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { verifyOtp(); }
    });
    document.getElementById('inpCode').addEventListener('input', function () {
        if (this.value.replace(/\D/g, '').length === 6) { verifyOtp(); }
    });

    function verifyOtp() {
        setErr('errCode', '');
        var code = document.getElementById('inpCode').value.replace(/\D/g, '');
        if (code.length < 6) { setErr('errCode', 'Введіть 6-значний код'); return; }
        var btn = document.getElementById('btnVerify');
        btn.disabled = true; btn.textContent = 'Перевіряємо…';

        post('/auth/api/verify_otp', {phone: phone, code: code, mode: otpMode}, function (d) {
            btn.disabled = false; btn.textContent = 'Підтвердити';
            if (!d.ok) { setErr('errCode', d.error); return; }
            clearCountdown();
            if (d.mode === 'set_password') {
                otpToken = d.otp_token;
                document.getElementById('hiddenPhone2').value = phone;
                show('stepSetPwd');
                document.getElementById('inpNewPwd').focus();
            } else {
                window.location.href = d.redirect || '/catalog';
            }
        }, function () { btn.disabled = false; btn.textContent = 'Підтвердити'; setErr('errCode', 'Мережева помилка'); });
    }

    document.getElementById('btnBackFromOtp').addEventListener('click', function () {
        clearCountdown();
        show('stepPhone');
    });

    // ── Крок 3: встановити пароль ─────────────────────────────────────────
    document.getElementById('inpNewPwd').addEventListener('input', function () {
        var len = this.value.length;
        var el = document.getElementById('pwdStrength');
        if (!len) { el.textContent = ''; return; }
        if (len < 6)  { el.style.color = '#f87171'; el.textContent = 'Занадто короткий'; }
        else if (len < 10) { el.style.color = '#fb923c'; el.textContent = 'Слабкий'; }
        else          { el.style.color = '#4ade80'; el.textContent = 'Надійний'; }
    });

    document.getElementById('btnSetPwd').addEventListener('click', function () {
        setErr('errSetPwd', '');
        var p1 = document.getElementById('inpNewPwd').value;
        var p2 = document.getElementById('inpNewPwd2').value;
        if (p1.length < 6) { setErr('errSetPwd', 'Пароль має бути не менше 6 символів'); return; }
        if (p1 !== p2)     { setErr('errSetPwd', 'Паролі не збігаються'); return; }

        var btn = this;
        btn.disabled = true; btn.textContent = 'Зберігаємо…';
        post('/auth/api/set_password', {phone: phone, otp_token: otpToken, password: p1}, function (d) {
            if (d.ok) { window.location.href = d.redirect || '/catalog'; }
            else {
                btn.disabled = false; btn.textContent = 'Зберегти та увійти';
                setErr('errSetPwd', d.error);
            }
        }, function () { btn.disabled = false; btn.textContent = 'Зберегти та увійти'; setErr('errSetPwd', 'Мережева помилка'); });
    });

    // Пропустити — просто залогінитись через OTP (без пароля)
    document.getElementById('btnSkipPwd').addEventListener('click', function () {
        // Повторно верифікуємо OTP в режимі login — але токен вже використаний.
        // Тому просто відправляємо новий OTP і логінимо.
        otpMode = 'login';
        sendOtp(phone);
    });

    // ── Таймер ────────────────────────────────────────────────────────────
    function startCountdown() {
        clearCountdown();
        if (!expiresAt) { return; }
        var el = document.getElementById('countdown');
        cdInterval = setInterval(function () {
            var sec = Math.max(0, Math.round((expiresAt - new Date()) / 1000));
            if (sec <= 0) {
                el.textContent = 'Код застарів — поверніться та надішліть новий';
                clearCountdown();
            } else {
                var m = Math.floor(sec / 60), s = sec % 60;
                el.textContent = 'Код дійсний ще ' + m + ':' + (s < 10 ? '0' : '') + s;
            }
        }, 1000);
    }
    function clearCountdown() {
        if (cdInterval) { clearInterval(cdInterval); cdInterval = null; }
        document.getElementById('countdown').textContent = '';
    }

    // ── Хелпер ────────────────────────────────────────────────────────────
    function post(url, data, onOk, onErr) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        fetch(url, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body})
        .then(function (r) { return r.json(); })
        .then(onOk)
        .catch(onErr || function () {});
    }
}());
</script>
</body>
</html>
