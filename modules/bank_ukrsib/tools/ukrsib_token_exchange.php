<?php

$ukrsib = require __DIR__ . '/../ukrsib_config.php';

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

    return $minutes . ' мин. ' . $seconds . ' сек.';
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
        return 'Client code отсутствует';
    }

    $age = time() - (int)$createdAt;

    if ($age > 900) {
        return 'Client code истёк';
    }

    if ($age > 600) {
        return 'Client code скоро истечёт';
    }

    return 'Client code действителен';
}

function ukrsib_config()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../ukrsib_config.php';
    }
    return $config;
}

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
        $result = array(
            '_error' => 'client_code not found'
        );
    } elseif ($age > 900) {
        $result = array(
            '_error' => 'client_code is older than 15 minutes'
        );
    } else {
        $result = ukrsib_request_initial_token_by_client_code($clientCode);
        if (!isset($result['_error']) && !empty($result['access_token']) && !empty($result['refresh_token'])) {
            $success = true;
        }
    }
}

$accessPreview = ($success && !empty($result['access_token'])) ? substr($result['access_token'], 0, 60) . '...' : '—';
$refreshPreview = ($success && !empty($result['refresh_token'])) ? substr($result['refresh_token'], 0, 60) . '...' : '—';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>UKRSIB Token Exchange</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f3f5f7;
            color: #1f2937;
        }
        .wrap {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            padding: 28px;
            margin-bottom: 24px;
        }
        .title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .subtitle {
            color: #6b7280;
            margin: 0 0 24px;
            font-size: 14px;
        }
        .status-box {
            border-radius: 14px;
            padding: 18px 20px;
            color: #fff;
            margin-bottom: 24px;
        }
        .status-green { background: #16a34a; }
        .status-orange { background: #ea580c; }
        .status-red { background: #dc2626; }
        .status-gray { background: #6b7280; }

        .status-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .status-text {
            font-size: 14px;
            opacity: 0.95;
        }
        .grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 12px 18px;
            align-items: start;
        }
        .label {
            color: #6b7280;
            font-weight: 600;
            font-size: 14px;
        }
        .value {
            font-size: 14px;
            word-break: break-word;
        }
        .mono {
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            border-radius: 10px;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: 0.2s ease;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #111827;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #000;
        }
        .btn-light {
            background: #e5e7eb;
            color: #111827;
        }
        .btn-light:hover {
            background: #d1d5db;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 18px;
        }
        .hint {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 14px;
            line-height: 1.5;
        }
        .result-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
        }
        .result-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 12px;
            padding: 16px;
            margin-top: 20px;
        }
        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <h1 class="title">UKRSIB Token Exchange</h1>
        <p class="subtitle">Обмен сохранённого client_code на новую пару access_token / refresh_token</p>

        <div class="status-box <?php echo $statusClass; ?>">
            <div class="status-title"><?php echo htmlspecialchars($statusText); ?></div>
            <div class="status-text">
                <?php if (!empty($clientCode)): ?>
                    Возраст client_code: <?php echo htmlspecialchars(formatAge($createdAt)); ?>
                <?php else: ?>
                    Для обмена токена сначала нужно запустить новую авторизацию.
                <?php endif; ?>
            </div>
        </div>

        <div class="grid">
            <div class="label">Client code</div>
            <div class="value mono"><?php echo !empty($clientCode) ? htmlspecialchars($clientCode) : '—'; ?></div>

            <div class="label">Создан</div>
            <div class="value"><?php echo htmlspecialchars(formatDateTime($createdAt)); ?></div>

            <div class="label">Возраст</div>
            <div class="value"><?php echo htmlspecialchars(formatAge($createdAt)); ?></div>

            <div class="label">Файл токенов</div>
            <div class="value mono"><?php echo htmlspecialchars($ukrsib['token_file']); ?></div>

            <div class="label">Token endpoint</div>
            <div class="value mono"><?php echo htmlspecialchars(rtrim($ukrsib['base_url'], '/') . '/token'); ?></div>
        </div>

        <div class="buttons">
            <a class="btn btn-primary" href="?exchange=1">Обменять токен</a>
            <a class="btn btn-secondary" href="ukrsib_token_status.php">Перейти к статусу токена</a>
            <a class="btn btn-light" href="ukrsib_token_exchange.php">Обновить страницу</a>
        </div>

        <div class="hint">
            Для UKRSIB один и тот же <strong>client_code</strong> должен использоваться и в авторизации, и в обмене токена.
            Между этими шагами должно пройти не более <strong>15 минут</strong>.
        </div>

        <?php if ($result !== null): ?>
            <?php if ($success): ?>
                <div class="result-success">
                    <strong>Токены успешно получены и сохранены.</strong><br><br>
                    <div><strong>Token type:</strong> <?php echo htmlspecialchars(isset($result['token_type']) ? $result['token_type'] : '—'); ?></div>
                    <div><strong>Expires in:</strong> <?php echo htmlspecialchars(isset($result['expires_in']) ? $result['expires_in'] : '—'); ?> сек.</div>
                    <div style="margin-top:10px;"><strong>Access token:</strong></div>
                    <div class="mono"><?php echo htmlspecialchars($accessPreview); ?></div>
                    <div style="margin-top:10px;"><strong>Refresh token:</strong></div>
                    <div class="mono"><?php echo htmlspecialchars($refreshPreview); ?></div>
                </div>
            <?php else: ?>
                <div class="result-error">
                    <strong>Ошибка обмена токена.</strong><br><br>
                    <?php echo htmlspecialchars(isset($result['_error']) ? $result['_error'] : 'Unknown error'); ?><br><br>

                    <?php if (!empty($result['_http_code'])): ?>
                        <div><strong>HTTP code:</strong> <?php echo htmlspecialchars($result['_http_code']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($result['_curl_error'])): ?>
                        <div><strong>cURL error:</strong> <?php echo htmlspecialchars($result['_curl_error']); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($result['_raw'])): ?>
                        <div style="margin-top:10px;"><strong>Raw response:</strong></div>
                        <div class="mono"><?php echo htmlspecialchars($result['_raw']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>