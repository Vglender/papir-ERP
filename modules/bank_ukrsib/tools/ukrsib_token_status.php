<?php

$ukrsib = require __DIR__ . '/../ukrsib_config.php';

function ukrsib_uuid_v4()
{
    $data = openssl_random_pseudo_bytes(16);
    if ($data === false || strlen($data) < 16) {
        $data = md5(uniqid(mt_rand(), true), true);
    }

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ukrsib_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../ukrsib_config.php';
    }
    return $config;
}

function ukrsib_token_read()
{
    $ukrsib = ukrsib_config();

    if (!file_exists($ukrsib['token_file'])) {
        return array();
    }

    $json = file_get_contents($ukrsib['token_file']);
    if ($json === false || trim($json) === '') {
        return array();
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : array();
}

function ukrsib_client_code_read()
{
    $ukrsib = ukrsib_config();

    if (!file_exists($ukrsib['client_code_file'])) {
        return array();
    }

    $json = file_get_contents($ukrsib['client_code_file']);
    if ($json === false || trim($json) === '') {
        return array();
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : array();
}

function ukrsib_client_code_write($data)
{
    $ukrsib = ukrsib_config();

    file_put_contents(
        $ukrsib['client_code_file'],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function ukrsib_get_authorize_url_by_client_code($clientCode)
{
    $ukrsib = ukrsib_config();

    return rtrim($ukrsib['base_url'], '/')
        . '/authorize?client_id=' . urlencode(trim($ukrsib['client_id']))
        . '&client_code=' . urlencode(trim($clientCode))
        . '&response_type=code';
}

function formatDateTime($timestamp)
{
    if (empty($timestamp)) {
        return '—';
    }

    return date('d.m.Y H:i:s', (int)$timestamp);
}

function formatIntervalDays($expiresAt)
{
    if (empty($expiresAt)) {
        return '—';
    }

    $seconds = (int)$expiresAt - time();
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    if ($seconds <= 0) {
        return 'Токен закінчився';
    }

    return $days . ' дн. ' . $hours . ' год. ' . $minutes . ' хв.';
}

function getTokenStatusClass($expiresAt)
{
    if (empty($expiresAt)) {
        return 'status-gray';
    }

    $seconds = (int)$expiresAt - time();

    if ($seconds <= 3 * 86400) {
        return 'status-red';
    }

    if ($seconds <= 7 * 86400) {
        return 'status-orange';
    }

    return 'status-green';
}

function getTokenStatusText($expiresAt)
{
    if (empty($expiresAt)) {
        return 'Токен відсутній';
    }

    $seconds = (int)$expiresAt - time();

    if ($seconds <= 0) {
        return 'Токен закінчився';
    }

    if ($seconds <= 3 * 86400) {
        return 'Термін дії скоро закінчиться';
    }

    if ($seconds <= 7 * 86400) {
        return 'Потрібно оновити найближчими днями';
    }

    return 'Токен дійсний';
}

function ukrsib_decode_jwt_exp($jwt)
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return 0;
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    return isset($payload['exp']) ? (int)$payload['exp'] : 0;
}

// ── Redirect to bank auth ────────────────────────────────────────────────────
if (isset($_GET['start']) && $_GET['start'] == '1') {
    $clientCode = ukrsib_uuid_v4();

    ukrsib_client_code_write(array(
        'client_code' => $clientCode,
        'created_at'  => time()
    ));

    header('Location: ' . ukrsib_get_authorize_url_by_client_code($clientCode));
    exit;
}

// ── Data ─────────────────────────────────────────────────────────────────────
$token = ukrsib_token_read();
$clientCodeData = ukrsib_client_code_read();

$expiresAt = isset($token['expires_at']) ? (int)$token['expires_at'] : 0;
$statusClass = getTokenStatusClass($expiresAt);
$statusText = getTokenStatusText($expiresAt);

$tokenExists = !empty($token['access_token']) && !empty($token['refresh_token']);
$clientCodeExists = !empty($clientCodeData['client_code']);

$accessPreview = $tokenExists ? substr($token['access_token'], 0, 40) . '...' : '—';
$refreshPreview = $tokenExists ? substr($token['refresh_token'], 0, 40) . '...' : '—';

// Refresh token expiry from JWT
$refreshExpiresAt = $tokenExists ? ukrsib_decode_jwt_exp($token['refresh_token']) : 0;

// ── Layout ───────────────────────────────────────────────────────────────────
$title     = 'УкрСибБанк';
$activeNav = 'integr';
$subNav    = 'ukrsib';
require_once __DIR__ . '/../../shared/layout.php';
?>

<style>
    .ukrsib-wrap { max-width: 980px; margin: 28px auto; padding: 0 20px; }
    .ukrsib-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); padding: 28px; margin-bottom: 24px; }
    .ukrsib-title { font-size: 22px; font-weight: 700; margin: 0 0 6px; }
    .ukrsib-subtitle { color: #6b7280; margin: 0 0 20px; font-size: 13px; }
    .ukrsib-status-box { border-radius: 14px; padding: 18px 20px; color: #fff; margin-bottom: 24px; }
    .ukrsib-status-box.status-green { background: #16a34a; }
    .ukrsib-status-box.status-orange { background: #ea580c; }
    .ukrsib-status-box.status-red { background: #dc2626; }
    .ukrsib-status-box.status-gray { background: #6b7280; }
    .ukrsib-status-title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .ukrsib-status-text { font-size: 13px; opacity: 0.95; }
    .ukrsib-grid { display: grid; grid-template-columns: 200px 1fr; gap: 10px 16px; align-items: start; }
    .ukrsib-label { color: #6b7280; font-weight: 600; font-size: 13px; }
    .ukrsib-value { font-size: 13px; word-break: break-word; }
    .ukrsib-mono { font-family: Consolas, Monaco, monospace; font-size: 12px; background: #f9fafb; border: 1px solid #e5e7eb; padding: 8px 10px; border-radius: 8px; }
    .ukrsib-buttons { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
    .ukrsib-btn { display: inline-block; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; transition: 0.2s ease; border: none; cursor: pointer; }
    .ukrsib-btn-primary { background: #2563eb; color: #fff; }
    .ukrsib-btn-primary:hover { background: #1d4ed8; }
    .ukrsib-btn-secondary { background: #111827; color: #fff; }
    .ukrsib-btn-secondary:hover { background: #000; }
    .ukrsib-btn-light { background: #e5e7eb; color: #111827; }
    .ukrsib-btn-light:hover { background: #d1d5db; }
    .ukrsib-section-title { font-size: 16px; font-weight: 700; margin: 0 0 16px; }
    .ukrsib-hint { margin-top: 16px; padding: 12px 14px; border-radius: 8px; background: #eff6ff; color: #1e3a8a; font-size: 13px; line-height: 1.5; }
    @media (max-width: 700px) { .ukrsib-grid { grid-template-columns: 1fr; } }
</style>

<div class="ukrsib-wrap">

    <div class="ukrsib-card">
        <h1 class="ukrsib-title">УкрСибБанк — Статус токена</h1>
        <p class="ukrsib-subtitle">Контроль access/refresh token для інтеграції Papir з UKRSIB business API</p>

        <div class="ukrsib-status-box <?php echo $statusClass; ?>">
            <div class="ukrsib-status-title"><?php echo htmlspecialchars($statusText); ?></div>
            <div class="ukrsib-status-text">
                <?php if ($tokenExists): ?>
                    До закінчення access: <?php echo htmlspecialchars(formatIntervalDays($expiresAt)); ?>
                <?php else: ?>
                    У файлі токенів немає діючої пари access_token / refresh_token.
                <?php endif; ?>
            </div>
        </div>

        <div class="ukrsib-grid">
            <div class="ukrsib-label">Base URL</div>
            <div class="ukrsib-value"><?php echo htmlspecialchars($ukrsib['base_url']); ?></div>

            <div class="ukrsib-label">Client ID</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars($ukrsib['client_id']); ?></div>

            <div class="ukrsib-label">Файл токенів</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars($ukrsib['token_file']); ?></div>

            <div class="ukrsib-label">Access token закінчується</div>
            <div class="ukrsib-value"><?php echo htmlspecialchars(formatDateTime($expiresAt)); ?> (<?php echo htmlspecialchars(formatIntervalDays($expiresAt)); ?>)</div>

            <div class="ukrsib-label">Refresh token закінчується</div>
            <div class="ukrsib-value">
                <?php if ($refreshExpiresAt > 0): ?>
                    <?php echo htmlspecialchars(formatDateTime($refreshExpiresAt)); ?> (<?php echo htmlspecialchars(formatIntervalDays($refreshExpiresAt)); ?>)
                <?php else: ?>
                    —
                <?php endif; ?>
            </div>

            <div class="ukrsib-label">Access token</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars($accessPreview); ?></div>

            <div class="ukrsib-label">Refresh token</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars($refreshPreview); ?></div>
        </div>

        <div class="ukrsib-buttons">
            <a class="ukrsib-btn ukrsib-btn-primary" href="?start=1">Нова авторизація</a>
            <a class="ukrsib-btn ukrsib-btn-secondary" href="/ukrsib_token_exchange">Обмін токена</a>
            <a class="ukrsib-btn ukrsib-btn-light" href="/ukrsib_token_status">Оновити</a>
        </div>

        <div class="ukrsib-hint">
            Після натискання <strong>«Нова авторизація»</strong> буде створено новий <strong>client_code</strong>,
            збережено у файл та виконано перехід на сторінку авторизації UKRSIB business.
            Після успішного входу відкрийте сторінку обміну токена.
        </div>
    </div>

    <div class="ukrsib-card">
        <h2 class="ukrsib-section-title">Останній client_code</h2>

        <div class="ukrsib-grid">
            <div class="ukrsib-label">Client code</div>
            <div class="ukrsib-value ukrsib-mono">
                <?php echo $clientCodeExists ? htmlspecialchars($clientCodeData['client_code']) : '—'; ?>
            </div>

            <div class="ukrsib-label">Створено</div>
            <div class="ukrsib-value">
                <?php
                echo $clientCodeExists && !empty($clientCodeData['created_at'])
                    ? htmlspecialchars(formatDateTime($clientCodeData['created_at']))
                    : '—';
                ?>
            </div>

            <div class="ukrsib-label">Вік</div>
            <div class="ukrsib-value">
                <?php
                if ($clientCodeExists && !empty($clientCodeData['created_at'])) {
                    $age = time() - (int)$clientCodeData['created_at'];
                    echo htmlspecialchars(floor($age / 60)) . ' хв. ' . htmlspecialchars($age % 60) . ' сек.';
                } else {
                    echo '—';
                }
                ?>
            </div>
        </div>

        <div class="ukrsib-hint">
            Для UKRSIB один і той самий <strong>client_code</strong> має використовуватися і в авторизації, і в обміні токена.
            Між цими кроками має пройти не більше 15 хвилин.
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>