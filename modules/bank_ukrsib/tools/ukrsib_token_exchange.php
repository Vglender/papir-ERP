<?php

$ukrsib = require __DIR__ . '/../ukrsib_config.php';

function ukrsib_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../ukrsib_config.php';
    }
    return $config;
}

function ukrsib_token_write($data)
{
    $ukrsib = ukrsib_config();

    file_put_contents(
        $ukrsib['token_file'],
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function ukrsib_set_initial_tokens($accessToken, $refreshToken, $expiresIn)
{
    $data = array(
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_at'    => time() + (int)$expiresIn - 60
    );

    ukrsib_token_write($data);
    return $data;
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

function ukrsib_http_post_form($url, $fields, $headers)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return array(
        'http_code'  => $httpCode,
        'response'   => $response,
        'curl_error' => $curlErr
    );
}

function ukrsib_request_initial_token_by_client_code($clientCode)
{
    $ukrsib = ukrsib_config();

    $url = rtrim($ukrsib['base_url'], '/') . '/token';

    $fields = array(
        'grant_type'    => 'client_code',
        'client_code'   => trim($clientCode),
        'client_id'     => trim($ukrsib['client_id']),
        'client_secret' => trim($ukrsib['client_secret'])
    );

    $headers = array(
        'Content-Type: application/x-www-form-urlencoded'
    );

    $res = ukrsib_http_post_form($url, $fields, $headers);

    if ($res['curl_error']) {
        return array(
            '_error'      => 'cURL error',
            '_curl_error' => $res['curl_error']
        );
    }

    $decoded = json_decode($res['response'], true);

    if ($res['http_code'] != 200 || !is_array($decoded)) {
        return array(
            '_error'     => 'Initial token request failed',
            '_http_code' => $res['http_code'],
            '_raw'       => $res['response']
        );
    }

    if (empty($decoded['access_token']) || empty($decoded['refresh_token'])) {
        return array(
            '_error' => 'Token response does not contain required tokens',
            '_data'  => $decoded
        );
    }

    ukrsib_set_initial_tokens(
        $decoded['access_token'],
        $decoded['refresh_token'],
        isset($decoded['expires_in']) ? (int)$decoded['expires_in'] : 3600
    );

    return $decoded;
}

function formatDateTime($timestamp)
{
    if (empty($timestamp)) {
        return '—';
    }

    return date('d.m.Y H:i:s', (int)$timestamp);
}

function formatAge($createdAt)
{
    if (empty($createdAt)) {
        return '—';
    }

    $age = time() - (int)$createdAt;

    if ($age < 0) {
        $age = 0;
    }

    $minutes = floor($age / 60);
    $seconds = $age % 60;

    return $minutes . ' хв. ' . $seconds . ' сек.';
}

function getClientCodeStatusClass($createdAt)
{
    if (empty($createdAt)) {
        return 'status-gray';
    }

    $age = time() - (int)$createdAt;

    if ($age > 900) {
        return 'status-red';
    }

    if ($age > 600) {
        return 'status-orange';
    }

    return 'status-green';
}

function getClientCodeStatusText($createdAt)
{
    if (empty($createdAt)) {
        return 'Client code відсутній';
    }

    $age = time() - (int)$createdAt;

    if ($age > 900) {
        return 'Client code закінчився';
    }

    if ($age > 600) {
        return 'Client code скоро закінчиться';
    }

    return 'Client code дійсний';
}

// ── Data ─────────────────────────────────────────────────────────────────────
$clientCodeData = ukrsib_client_code_read();

$clientCode = isset($clientCodeData['client_code']) ? $clientCodeData['client_code'] : '';
$createdAt = isset($clientCodeData['created_at']) ? (int)$clientCodeData['created_at'] : 0;
$age = $createdAt ? (time() - $createdAt) : 0;

$statusClass = getClientCodeStatusClass($createdAt);
$statusText = getClientCodeStatusText($createdAt);

$result = null;
$success = false;

if (isset($_GET['exchange']) && $_GET['exchange'] == '1') {
    if (empty($clientCode)) {
        $result = array('_error' => 'client_code not found');
    } elseif ($age > 900) {
        $result = array('_error' => 'client_code is older than 15 minutes');
    } else {
        $result = ukrsib_request_initial_token_by_client_code($clientCode);
        if (!isset($result['_error']) && !empty($result['access_token']) && !empty($result['refresh_token'])) {
            $success = true;
        }
    }
}

$accessPreview = ($success && !empty($result['access_token'])) ? substr($result['access_token'], 0, 60) . '...' : '—';
$refreshPreview = ($success && !empty($result['refresh_token'])) ? substr($result['refresh_token'], 0, 60) . '...' : '—';

// ── Layout ───────────────────────────────────────────────────────────────────
$title     = 'УкрСибБанк — Обмін токена';
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
    .ukrsib-hint { margin-top: 16px; padding: 12px 14px; border-radius: 8px; background: #eff6ff; color: #1e3a8a; font-size: 13px; line-height: 1.5; }
    .ukrsib-result-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 10px; padding: 16px; margin-top: 20px; font-size: 13px; }
    .ukrsib-result-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 16px; margin-top: 20px; font-size: 13px; }
    @media (max-width: 700px) { .ukrsib-grid { grid-template-columns: 1fr; } }
</style>

<div class="ukrsib-wrap">

    <div class="ukrsib-card">
        <h1 class="ukrsib-title">УкрСибБанк — Обмін токена</h1>
        <p class="ukrsib-subtitle">Обмін збереженого client_code на нову пару access_token / refresh_token</p>

        <div class="ukrsib-status-box <?php echo $statusClass; ?>">
            <div class="ukrsib-status-title"><?php echo htmlspecialchars($statusText); ?></div>
            <div class="ukrsib-status-text">
                <?php if (!empty($clientCode)): ?>
                    Вік client_code: <?php echo htmlspecialchars(formatAge($createdAt)); ?>
                <?php else: ?>
                    Для обміну токена спочатку потрібно запустити нову авторизацію.
                <?php endif; ?>
            </div>
        </div>

        <div class="ukrsib-grid">
            <div class="ukrsib-label">Client code</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo !empty($clientCode) ? htmlspecialchars($clientCode) : '—'; ?></div>

            <div class="ukrsib-label">Створено</div>
            <div class="ukrsib-value"><?php echo htmlspecialchars(formatDateTime($createdAt)); ?></div>

            <div class="ukrsib-label">Вік</div>
            <div class="ukrsib-value"><?php echo htmlspecialchars(formatAge($createdAt)); ?></div>

            <div class="ukrsib-label">Файл токенів</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars($ukrsib['token_file']); ?></div>

            <div class="ukrsib-label">Token endpoint</div>
            <div class="ukrsib-value ukrsib-mono"><?php echo htmlspecialchars(rtrim($ukrsib['base_url'], '/') . '/token'); ?></div>
        </div>

        <div class="ukrsib-buttons">
            <a class="ukrsib-btn ukrsib-btn-primary" href="?exchange=1">Обміняти токен</a>
            <a class="ukrsib-btn ukrsib-btn-secondary" href="/ukrsib_token_status">Статус токена</a>
            <a class="ukrsib-btn ukrsib-btn-light" href="/ukrsib_token_exchange">Оновити</a>
        </div>

        <div class="ukrsib-hint">
            Для UKRSIB один і той самий <strong>client_code</strong> має використовуватися і в авторизації, і в обміні токена.
            Між цими кроками має пройти не більше <strong>15 хвилин</strong>.
        </div>

        <?php if ($result !== null): ?>
            <?php if ($success): ?>
                <div class="ukrsib-result-success">
                    <strong>Токени успішно отримано та збережено.</strong><br><br>
                    <div><strong>Token type:</strong> <?php echo htmlspecialchars(isset($result['token_type']) ? $result['token_type'] : '—'); ?></div>
                    <div><strong>Expires in:</strong> <?php echo htmlspecialchars(isset($result['expires_in']) ? $result['expires_in'] : '—'); ?> сек.</div>
                    <div style="margin-top:10px;"><strong>Access token:</strong></div>
                    <div class="ukrsib-mono"><?php echo htmlspecialchars($accessPreview); ?></div>
                    <div style="margin-top:10px;"><strong>Refresh token:</strong></div>
                    <div class="ukrsib-mono"><?php echo htmlspecialchars($refreshPreview); ?></div>
                </div>
            <?php else: ?>
                <div class="ukrsib-result-error">
                    <strong>Помилка обміну токена.</strong><br><br>
                    <?php echo htmlspecialchars(isset($result['_error']) ? $result['_error'] : 'Unknown error'); ?><br><br>

                    <?php if (!empty($result['_http_code'])): ?>
                        <div><strong>HTTP code:</strong> <?php echo htmlspecialchars($result['_http_code']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($result['_curl_error'])): ?>
                        <div><strong>cURL error:</strong> <?php echo htmlspecialchars($result['_curl_error']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($result['_raw'])): ?>
                        <div style="margin-top:10px;"><strong>Raw response:</strong></div>
                        <div class="ukrsib-mono"><?php echo htmlspecialchars($result['_raw']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>